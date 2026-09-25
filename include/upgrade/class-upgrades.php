<?php
namespace CanvaslyLite\Upgrade;

use CanvaslyLite\Design\CssPrint;
use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Document\Documents;
use CanvaslyLite\Log\Logger;
use CanvaslyLite\Settings\AdminSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versioned upgrade callbacks with background-batched document migrations (Roadmap 7.4).
 *
 * `Upgrades::run()` compares `canvasly_lite_version` to `CANVASLY_LITE_VERSION` and
 * queues callbacks whose version is greater than the stored version. Document
 * tasks walk posts in pages of `batch_size()` via WP-Cron and `admin_init`.
 */
class Upgrades {
	const OPTION_VERSION = 'canvasly_lite_version';
	const OPTION_QUEUE   = 'canvasly_lite_upgrade_queue';
	const OPTION_FAILED  = 'canvasly_lite_upgrade_failed';
	const LOCK           = 'canvasly_lite_upgrade_lock';
	const CRON           = 'canvasly_lite_upgrade_batch';
	const NOTICE         = 'canvasly_lite_upgrade_notice';
	const BATCH          = 40;
	const LOCK_TTL       = 120;

	public static function init() {
		add_action( 'init', array( self::class, 'maybe_run' ), 20 );
		add_action( 'admin_init', array( self::class, 'maybe_continue' ), 5 );
		add_action( self::CRON, array( self::class, 'cron_batch' ) );
		add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		add_action( 'canvasly-lite/tools/screen', array( self::class, 'tools_screen' ), 26 );
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		add_action( 'admin_post_lb_upgrade_retry', array( self::class, 'handle_retry' ) );
		add_action( 'admin_post_lb_upgrade_run', array( self::class, 'handle_run' ) );
		add_action( 'admin_post_lb_log_clear', array( self::class, 'handle_clear_log' ) );
		add_action( 'admin_post_lb_log_download', array( self::class, 'handle_download_log' ) );
	}

	public static function can_manage() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * First install only: record the current plugin version so historical
	 * callbacks are not replayed on an empty site.
	 */
	public static function on_activate() {
		$stored = self::stored_version();
		if ( $stored === '' ) {
			update_option( self::OPTION_VERSION, self::current_version(), false );
		}
	}

	public static function on_deactivate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		delete_option( self::LOCK );
	}

	/**
	 * @return string
	 */
	public static function current_version() {
		return defined( 'CANVASLY_LITE_VERSION' ) ? (string) CANVASLY_LITE_VERSION : '0';
	}

	/**
	 * @return string
	 */
	public static function stored_version() {
		$v = function_exists( 'get_option' ) ? get_option( self::OPTION_VERSION, '' ) : '';
		return is_string( $v ) ? $v : '';
	}

	/**
	 * Versioned callback catalog. Keys are plugin versions; each value is a list
	 * of task arrays (`id`, `callback`, optional `batch`).
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function catalog() {
		$map = array(
			'0.12.69' => array(
				array(
					'id'       => 'persist_documents',
					'callback' => array( self::class, 'task_persist_documents' ),
					'batch'    => true,
				),
			),
		);
		/**
		 * Filter the upgrade catalog. Add-ons append `{version => [tasks]}`.
		 *
		 * @param array $map
		 */
		$filtered = apply_filters( 'canvasly-lite/upgrades/register', $map );
		return is_array( $filtered ) ? $filtered : $map;
	}

	/**
	 * Tasks whose version is greater than `$from` and less than or equal to `$to`.
	 *
	 * @param string $from
	 * @param string $to
	 * @return array<int,array<string,mixed>>
	 */
	public static function pending( $from, $to ) {
		$from = (string) $from;
		$to   = (string) $to;
		$out  = array();
		$map  = self::catalog();
		$vers = array_keys( $map );
		usort( $vers, 'version_compare' );
		foreach ( $vers as $version ) {
			if ( version_compare( $from, (string) $version, '>=' ) ) {
				continue;
			}
			if ( version_compare( (string) $version, $to, '>' ) ) {
				continue;
			}
			foreach ( (array) $map[ $version ] as $task ) {
				$norm = self::normalize_task( $task, (string) $version );
				if ( $norm ) {
					$out[] = $norm;
				}
			}
		}
		return $out;
	}

	/**
	 * Compare stored vs current version and start a queue when they differ.
	 */
	public static function maybe_run() {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::allows_background() ) {
			return;
		}
		self::run( false );
	}

	/**
	 * Continue a running queue from wp-admin (WP-Cron is often disabled).
	 */
	public static function maybe_continue() {
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) ) {
			if ( \CanvaslyLite\Admin\AdminContext::is_autosave() ) {
				return;
			}
			if ( ! \CanvaslyLite\Admin\AdminContext::allows_background() ) {
				return;
			}
		}
		$queue = self::queue();
		if ( $queue && ( $queue['status'] ?? '' ) === 'running' ) {
			self::process_batch();
		}
	}

	public static function cron_batch() {
		self::process_batch();
	}

	/**
	 * @param bool $force Re-queue document persistence even when versions already match.
	 * @return array<string,mixed>
	 */
	public static function run( $force = false ) {
		$from  = self::stored_version();
		$to    = self::current_version();
		$queue = self::queue();
		if ( $queue && in_array( $queue['status'] ?? '', array( 'running', 'failed' ), true ) && ! $force ) {
			return self::status();
		}
		if ( $from === $to && ! $force ) {
			return self::status();
		}
		if ( $from === $to && $force ) {
			return self::start_persist( $from === '' ? $to : $from, $to );
		}
		if ( $from === '' ) {
			$from = '0';
		}
		return self::start( $from, $to );
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @return array<string,mixed>
	 */
	public static function start( $from, $to ) {
		$tasks = self::pending( $from, $to );
		if ( ! $tasks ) {
			self::finish( $to );
			return self::status();
		}
		self::store_queue(
			array(
				'from'    => (string) $from,
				'to'      => (string) $to,
				'tasks'   => $tasks,
				'index'   => 0,
				'started' => time(),
				'status'  => 'running',
			)
		);
		delete_option( self::OPTION_FAILED );
		if ( class_exists( Logger::class ) ) {
			Logger::info(
				'upgrade.start',
				array(
					'from'  => $from,
					'to'    => $to,
					'tasks' => count( $tasks ),
				)
			);
		}
		/**
		 * Fires after an upgrade queue is created.
		 *
		 * @param string $from
		 * @param string $to
		 * @param array  $tasks
		 */
		do_action( 'canvasly-lite/upgrade/before', $from, $to, $tasks );
		self::process_batch();
		return self::status();
	}

	/**
	 * Queue the document-persist task without a version gap.
	 *
	 * @param string $from
	 * @param string $to
	 * @return array<string,mixed>
	 */
	public static function start_persist( $from, $to ) {
		$task = self::normalize_task(
			array(
				'id'       => 'persist_documents',
				'callback' => array( self::class, 'task_persist_documents' ),
				'batch'    => true,
			),
			$to
		);
		if ( ! $task ) {
			return self::status();
		}
		self::store_queue(
			array(
				'from'    => (string) $from,
				'to'      => (string) $to,
				'tasks'   => array( $task ),
				'index'   => 0,
				'started' => time(),
				'status'  => 'running',
			)
		);
		delete_option( self::OPTION_FAILED );
		if ( class_exists( Logger::class ) ) {
			Logger::info( 'upgrade.persist', array( 'version' => $to ) );
		}
		self::process_batch();
		return self::status();
	}

	/**
	 * Run the current task; drain synchronous tasks, then one document page.
	 *
	 * @return array<string,mixed>
	 */
	public static function process_batch() {
		if ( ! self::acquire_lock() ) {
			return self::status();
		}
		$queue = self::queue();
		if ( ! $queue || ( $queue['status'] ?? '' ) !== 'running' ) {
			self::release_lock();
			return self::status();
		}
		$tasks = is_array( $queue['tasks'] ?? null ) ? $queue['tasks'] : array();
		$index = absint( $queue['index'] ?? 0 );
		while ( isset( $tasks[ $index ] ) ) {
			$task = $tasks[ $index ];
			$task['status'] = 'running';
			$tasks[ $index ] = $task;
			$queue['tasks']  = $tasks;
			$queue['index']  = $index;
			self::store_queue( $queue );

			$result = self::invoke( $task );
			if ( ! empty( $result['error'] ) ) {
				$task['status']  = 'failed';
				$task['error']   = (string) $result['error'];
				$tasks[ $index ] = $task;
				$queue['tasks']  = $tasks;
				$queue['status'] = 'failed';
				self::store_queue( $queue );
				self::record_failure( $queue, $task );
				self::release_lock();
				return self::status();
			}

			$processed = absint( $task['processed'] ?? 0 ) + absint( $result['processed'] ?? 0 );
			$failed    = absint( $task['failed'] ?? 0 ) + absint( $result['failed'] ?? 0 );
			$task['processed'] = $processed;
			$task['failed']    = $failed;
			$task['offset']    = absint( $result['offset'] ?? ( $task['offset'] ?? 0 ) );

			$batch = ! empty( $task['batch'] );
			$done  = $batch ? ! empty( $result['done'] ) : true;
			if ( ! $done ) {
				$task['status']  = 'running';
				$tasks[ $index ] = $task;
				$queue['tasks']  = $tasks;
				self::store_queue( $queue );
				self::schedule();
				self::release_lock();
				return self::status();
			}

			$task['status']  = 'done';
			$tasks[ $index ] = $task;
			$index++;
			$queue['tasks'] = $tasks;
			$queue['index'] = $index;
			self::store_queue( $queue );
			if ( $batch ) {
				break;
			}
		}

		if ( ! isset( $tasks[ $index ] ) ) {
			self::finish( (string) ( $queue['to'] ?? self::current_version() ), $queue );
			self::release_lock();
			return self::status();
		}
		self::schedule();
		self::release_lock();
		return self::status();
	}

	/**
	 * Reset a failed queue and continue.
	 *
	 * @return array<string,mixed>
	 */
	public static function retry() {
		$queue = self::queue();
		if ( ! $queue ) {
			return self::run( true );
		}
		$index = absint( $queue['index'] ?? 0 );
		$tasks = is_array( $queue['tasks'] ?? null ) ? $queue['tasks'] : array();
		if ( isset( $tasks[ $index ] ) ) {
			$tasks[ $index ] = self::refresh_task( $tasks[ $index ] );
		}
		$queue['tasks']  = $tasks;
		$queue['status'] = 'running';
		self::store_queue( $queue );
		delete_option( self::OPTION_FAILED );
		if ( class_exists( Logger::class ) ) {
			Logger::notice( 'upgrade.retry', array( 'index' => $index ) );
		}
		self::release_lock();
		return self::process_batch();
	}

	/**
	 * Persist one document through `DocumentManager::migrate()` without a revision.
	 *
	 * @param array<string,mixed> $task
	 * @return array<string,mixed>
	 */
	public static function task_persist_documents( $task ) {
		$limit  = self::batch_size();
		$offset = absint( $task['offset'] ?? 0 );
		$ids    = self::document_ids( $offset, $limit );
		$processed = 0;
		$failed    = 0;
		$changed   = 0;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( ! $id ) {
				continue;
			}
			try {
				$did = class_exists( DocumentManager::class ) ? DocumentManager::persist_migrated( $id ) : false;
				$processed++;
				if ( $did ) {
					$changed++;
				}
			} catch ( \Throwable $e ) {
				$failed++;
				if ( class_exists( Logger::class ) ) {
					Logger::error(
						'upgrade.document',
						array(
							'id'    => $id,
							'error' => $e->getMessage(),
						)
					);
				}
			}
		}
		if ( class_exists( Logger::class ) ) {
			Logger::info(
				'upgrade.batch',
				array(
					'offset'    => $offset,
					'count'     => count( $ids ),
					'changed'   => $changed,
					'failed'    => $failed,
				)
			);
		}
		return array(
			'done'      => count( $ids ) < $limit,
			'offset'    => $offset + count( $ids ),
			'processed' => $processed,
			'failed'    => $failed,
			'changed'   => $changed,
		);
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 * @return int[]
	 */
	public static function document_ids( $offset = 0, $limit = 0 ) {
		$limit  = $limit > 0 ? absint( $limit ) : self::batch_size();
		$offset = max( 0, absint( $offset ) );
		if ( class_exists( CssPrint::class ) && $offset === 0 && method_exists( CssPrint::class, 'document_ids' ) && ! class_exists( '\WP_Query' ) ) {
			$ids = CssPrint::document_ids( $limit );
			return array_slice( $ids, 0, $limit );
		}
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}
		$types = class_exists( Documents::class ) ? Documents::enabled() : array( 'post', 'page' );
		$types = is_array( $types ) ? $types : array( 'post', 'page' );
		$types[] = 'lb_template';
		$types[] = 'lb_component';
		$types   = array_values( array_unique( array_filter( $types ) ) );
		$meta    = class_exists( DocumentManager::class ) ? DocumentManager::META : '_lb_document_data';
		$q       = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'any',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'fields'                 => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Upgrade batch selects posts that store Canvasly document JSON.
				'meta_query'             => array(
					'relation' => 'OR',
					array(
						'key'     => $meta,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_lb_template_data',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_lb_component_data',
						'compare' => 'EXISTS',
					),
				),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			)
		);
		$ids = array();
		foreach ( (array) $q->posts as $id ) {
			$id = absint( is_object( $id ) && isset( $id->ID ) ? $id->ID : $id );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		/**
		 * Filter document ids walked by a batched upgrade.
		 *
		 * @param int[] $ids
		 * @param int   $offset
		 * @param int   $limit
		 */
		$filtered = apply_filters( 'canvasly-lite/upgrades/document_ids', $ids, $offset, $limit );
		return is_array( $filtered ) ? array_values( array_unique( array_map( 'absint', $filtered ) ) ) : $ids;
	}

	/**
	 * @return int
	 */
	public static function batch_size() {
		$n = self::BATCH;
		/**
		 * Filter documents processed per upgrade batch.
		 *
		 * @param int $n
		 */
		$filtered = apply_filters( 'canvasly-lite/upgrades/batch_size', $n );
		return max( 1, min( 200, absint( $filtered ) ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function status() {
		$queue  = self::queue();
		$failed = get_option( self::OPTION_FAILED, array() );
		$state  = 'idle';
		if ( $queue ) {
			$state = (string) ( $queue['status'] ?? 'running' );
		} elseif ( is_array( $failed ) && ! empty( $failed['error'] ) ) {
			$state = 'failed';
		}
		$task = null;
		if ( $queue && isset( $queue['tasks'][ absint( $queue['index'] ?? 0 ) ] ) ) {
			$task = $queue['tasks'][ absint( $queue['index'] ) ];
		}
		$log = class_exists( Logger::class )
			? array(
				'path'      => Logger::path(),
				'size'      => Logger::size(),
				'threshold' => Logger::threshold(),
			)
			: array();
		return array(
			'state'   => $state,
			'from'    => $queue['from'] ?? self::stored_version(),
			'to'      => $queue['to'] ?? self::current_version(),
			'stored'  => self::stored_version(),
			'current' => self::current_version(),
			'index'   => absint( $queue['index'] ?? 0 ),
			'total'   => $queue ? count( (array) ( $queue['tasks'] ?? array() ) ) : 0,
			'task'    => $task,
			'failed'  => is_array( $failed ) ? $failed : array(),
			'log'     => $log,
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function queue() {
		$q = get_option( self::OPTION_QUEUE, null );
		return is_array( $q ) ? $q : null;
	}

	public static function admin_notice() {
		if ( ! self::can_manage() ) {
			return;
		}
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::is_plugin_page() ) {
			return;
		}
		$key  = self::NOTICE . '_' . ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 );
		$flash = function_exists( 'get_transient' ) ? get_transient( $key ) : null;
		if ( is_array( $flash ) && ! empty( $flash['message'] ) ) {
			delete_transient( $key );
			$class = ( $flash['type'] ?? '' ) === 'error' ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) $flash['message'] ) . '</p></div>';
		}

		$failed = get_option( self::OPTION_FAILED, array() );
		if ( is_array( $failed ) && ! empty( $failed['error'] ) ) {
			$url = self::retry_url();
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: previous version, 2: new version */
					__( 'Canvasly could not finish an upgrade from %1$s to %2$s.', 'canvasly-lite' ),
					(string) ( $failed['from'] ?? '' ),
					(string) ( $failed['to'] ?? self::current_version() )
				)
			);
			echo ' ' . esc_html( (string) $failed['error'] );
			echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Retry upgrade', 'canvasly-lite' ) . '</a>';
			echo ' <a href="' . esc_url( self::tools_url() ) . '">' . esc_html__( 'View log', 'canvasly-lite' ) . '</a>';
			echo '</p></div>';
			return;
		}

		$queue = self::queue();
		if ( ! $queue || ( $queue['status'] ?? '' ) !== 'running' ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$ops    = class_exists( AdminSettings::class ) && AdminSettings::is_ops_screen( $screen );
		if ( ! $ops && function_exists( 'get_current_screen' ) ) {
			$id = is_object( $screen ) ? (string) ( $screen->id ?? '' ) : '';
			if ( $id !== '' && strpos( $id, 'canvasly-lite' ) === false ) {
				return;
			}
		}
		$task = isset( $queue['tasks'][ absint( $queue['index'] ?? 0 ) ] ) ? $queue['tasks'][ absint( $queue['index'] ) ] : array();
		$done = absint( $task['processed'] ?? 0 );
		echo '<div class="notice notice-info"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: documents processed in the current task */
				__( 'Canvasly is applying document upgrades in the background (%d processed so far).', 'canvasly-lite' ),
				$done
			)
		);
		echo '</p></div>';
	}

	public static function tools_screen() {
		if ( ! self::can_manage() ) {
			return;
		}
		$s     = self::status();
		$state = (string) ( $s['state'] ?? 'idle' );
		echo '<hr><h2>' . esc_html__( 'Upgrades and log', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'When the plugin version changes, Canvasly runs versioned callbacks and re-saves documents in the background so schema migrations persist. Failures appear as an admin notice with a retry link.', 'canvasly-lite' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Installed', 'canvasly-lite' ) . '</th><td><code>' . esc_html( (string) $s['stored'] ) . '</code></td></tr>';
		echo '<tr><th>' . esc_html__( 'Plugin', 'canvasly-lite' ) . '</th><td><code>' . esc_html( (string) $s['current'] ) . '</code></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'canvasly-lite' ) . '</th><td>' . esc_html( self::state_label( $state ) ) . '</td></tr>';
		if ( ! empty( $s['task']['id'] ) ) {
			echo '<tr><th>' . esc_html__( 'Current task', 'canvasly-lite' ) . '</th><td><code>' . esc_html( (string) $s['task']['id'] ) . '</code>';
			echo ' — ' . esc_html(
				sprintf(
					/* translators: %d: processed count */
					__( '%d processed', 'canvasly-lite' ),
					absint( $s['task']['processed'] ?? 0 )
				)
			);
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:12px">';
		if ( $state === 'failed' ) {
			wp_nonce_field( 'lb_upgrade_retry' );
			echo '<input type="hidden" name="action" value="lb_upgrade_retry">';
			if ( class_exists( AdminSettings::class ) ) {
				AdminSettings::echo_return_tab( 'tools' );
			}
			echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Retry upgrade', 'canvasly-lite' ) . '</button></p>';
		} else {
			wp_nonce_field( 'lb_upgrade_run' );
			echo '<input type="hidden" name="action" value="lb_upgrade_run">';
			if ( class_exists( AdminSettings::class ) ) {
				AdminSettings::echo_return_tab( 'tools' );
			}
			$label = $state === 'running' ? __( 'Continue upgrade', 'canvasly-lite' ) : __( 'Migrate documents now', 'canvasly-lite' );
			echo '<p><button class="button" type="submit">' . esc_html( $label ) . '</button></p>';
		}
		echo '</form>';

		$tail = class_exists( Logger::class ) ? Logger::tail( 80 ) : '';
		$size = class_exists( Logger::class ) ? Logger::size() : 0;
		echo '<h3>' . esc_html__( 'Log', 'canvasly-lite' ) . '</h3>';
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %s: log file size */
				__( 'Rotating file under uploads/canvasly-lite/logs (%s).', 'canvasly-lite' ),
				function_exists( 'size_format' ) ? (string) size_format( $size ) : (string) $size
			)
		) . '</p>';
		echo '<textarea class="large-text code" rows="12" readonly>' . esc_textarea( $tail ) . '</textarea>';
		echo '<p><a class="button" href="' . esc_url( self::download_log_url() ) . '">' . esc_html__( 'Download log', 'canvasly-lite' ) . '</a></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_log_clear' );
		echo '<input type="hidden" name="action" value="lb_log_clear">';
		if ( class_exists( AdminSettings::class ) ) {
			AdminSettings::echo_return_tab( 'tools' );
		}
		echo '<p><button class="button" type="submit">' . esc_html__( 'Clear log', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		$ns = $namespace !== '' ? $namespace : 'canvasly-lite/v1';
		register_rest_route(
			$ns,
			'/upgrades',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'rest_get' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest_run' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			$ns,
			'/upgrades/retry',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_retry' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			$ns,
			'/log',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'rest_log' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'rest_log_clear' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
	}

	public static function rest_get() {
		return rest_ensure_response( self::status() );
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_REST_Response
	 */
	public static function rest_run( $req ) {
		$d     = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		$force = ! empty( $d['force'] );
		return rest_ensure_response( self::run( $force ) );
	}

	public static function rest_retry() {
		return rest_ensure_response( self::retry() );
	}

	public static function rest_log() {
		$tail = class_exists( Logger::class ) ? Logger::tail( 200 ) : '';
		return rest_ensure_response(
			array(
				'log'  => $tail,
				'size' => class_exists( Logger::class ) ? Logger::size() : 0,
			)
		);
	}

	public static function rest_log_clear() {
		$ok = class_exists( Logger::class ) ? Logger::clear() : true;
		return rest_ensure_response( array( 'cleared' => (bool) $ok ) );
	}

	public static function handle_retry() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can run upgrades.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_upgrade_retry' );
		self::retry();
		self::store_notice( 'success', __( 'The upgrade was resumed.', 'canvasly-lite' ) );
		self::redirect_back();
	}

	public static function handle_run() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can run upgrades.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_upgrade_run' );
		$queue = self::queue();
		if ( $queue && ( $queue['status'] ?? '' ) === 'running' ) {
			self::process_batch();
			self::store_notice( 'success', __( 'The upgrade continued.', 'canvasly-lite' ) );
		} elseif ( $queue && ( $queue['status'] ?? '' ) === 'failed' ) {
			self::retry();
			self::store_notice( 'success', __( 'The upgrade was resumed.', 'canvasly-lite' ) );
		} else {
			self::run( true );
			self::store_notice( 'success', __( 'Document migrations started.', 'canvasly-lite' ) );
		}
		self::redirect_back();
	}

	public static function handle_clear_log() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can clear the log.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_log_clear' );
		if ( class_exists( Logger::class ) ) {
			Logger::clear();
		}
		self::store_notice( 'success', __( 'The log was cleared.', 'canvasly-lite' ) );
		self::redirect_back();
	}

	public static function handle_download_log() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can download the log.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_log_download' );
		$path = class_exists( Logger::class ) ? Logger::path() : '';
		$text = ( $path && is_readable( $path ) ) ? (string) file_get_contents( $path ) : '';
		$date = gmdate( 'Y-m-d' );
		$filename = 'canvasly-lite-log-' . $date . '.log';
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) strlen( $text ) );
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param mixed  $task
	 * @param string $version
	 * @return array<string,mixed>|null
	 */
	public static function normalize_task( $task, $version ) {
		if ( is_string( $task ) ) {
			$task = array(
				'id'       => sanitize_key( $task ),
				'callback' => $task,
			);
		}
		if ( ! is_array( $task ) ) {
			return null;
		}
		$id = sanitize_key( (string) ( $task['id'] ?? '' ) );
		$cb = $task['callback'] ?? '';
		if ( is_array( $cb ) && isset( $cb[0], $cb[1] ) ) {
			$cb = array( $cb[0], (string) $cb[1] );
			if ( $id === '' ) {
				$id = sanitize_key( (string) $cb[1] );
			}
		} elseif ( is_string( $cb ) && $cb !== '' ) {
			if ( $id === '' ) {
				$id = sanitize_key( $cb );
			}
		} else {
			return null;
		}
		if ( $id === '' ) {
			$id = 'task';
		}
		return array(
			'id'        => $id,
			'version'   => (string) $version,
			'callback'  => $cb,
			'batch'     => ! empty( $task['batch'] ),
			'offset'    => absint( $task['offset'] ?? 0 ),
			'processed' => absint( $task['processed'] ?? 0 ),
			'failed'    => absint( $task['failed'] ?? 0 ),
			'status'    => 'pending',
			'error'     => '',
		);
	}

	/**
	 * Re-read a queued task from the catalog so a retry can pick up a fixed callback.
	 *
	 * @param array<string,mixed> $task
	 * @return array<string,mixed>
	 */
	public static function refresh_task( $task ) {
		$id      = sanitize_key( (string) ( $task['id'] ?? '' ) );
		$version = (string) ( $task['version'] ?? '' );
		$map     = self::catalog();
		if ( $id !== '' && $version !== '' && isset( $map[ $version ] ) ) {
			foreach ( (array) $map[ $version ] as $row ) {
				$norm = self::normalize_task( $row, $version );
				if ( $norm && ( $norm['id'] ?? '' ) === $id ) {
					$norm['offset']    = absint( $task['offset'] ?? 0 );
					$norm['processed'] = absint( $task['processed'] ?? 0 );
					$norm['failed']    = absint( $task['failed'] ?? 0 );
					return $norm;
				}
			}
		}
		$task['status'] = 'pending';
		$task['error']  = '';
		return $task;
	}

	/**
	 * @param array<string,mixed> $task
	 * @return array<string,mixed>
	 */
	public static function invoke( $task ) {
		$cb = $task['callback'] ?? null;
		if ( is_string( $cb ) && strpos( $cb, '::' ) !== false ) {
			$cb = explode( '::', $cb, 2 );
		}
		if ( ! is_callable( $cb ) ) {
			return array(
				'done'  => false,
				'error' => __( 'Upgrade callback is missing.', 'canvasly-lite' ),
			);
		}
		try {
			$result = call_user_func( $cb, $task );
		} catch ( \Throwable $e ) {
			if ( class_exists( Logger::class ) ) {
				Logger::error( 'upgrade.exception', array( 'error' => $e->getMessage(), 'id' => $task['id'] ?? '' ) );
			}
			return array(
				'done'  => false,
				'error' => $e->getMessage(),
			);
		}
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			return array(
				'done'  => false,
				'error' => $result->get_error_message(),
			);
		}
		if ( $result === true || $result === null ) {
			return array( 'done' => true );
		}
		if ( ! is_array( $result ) ) {
			return array( 'done' => true );
		}
		$error = (string) ( $result['error'] ?? '' );
		$done  = array_key_exists( 'done', $result ) ? ! empty( $result['done'] ) : empty( $task['batch'] );
		return array(
			'done'      => $error === '' ? $done : false,
			'offset'    => absint( $result['offset'] ?? ( $task['offset'] ?? 0 ) ),
			'processed' => absint( $result['processed'] ?? 0 ),
			'failed'    => absint( $result['failed'] ?? 0 ),
			'error'     => $error,
		);
	}

	/**
	 * @param array<string,mixed> $queue
	 */
	private static function store_queue( $queue ) {
		update_option( self::OPTION_QUEUE, $queue, false );
	}

	/**
	 * @param string                   $to
	 * @param array<string,mixed>|null $queue
	 */
	private static function finish( $to, $queue = null ) {
		$from = is_array( $queue ) ? (string) ( $queue['from'] ?? '' ) : self::stored_version();
		update_option( self::OPTION_VERSION, (string) $to, false );
		delete_option( self::OPTION_QUEUE );
		delete_option( self::OPTION_FAILED );
		delete_option( self::LOCK );
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		if ( class_exists( Logger::class ) ) {
			Logger::info( 'upgrade.complete', array( 'from' => $from, 'to' => $to ) );
		}
		/**
		 * Fires after all upgrade callbacks for a version jump have finished.
		 *
		 * @param string $from
		 * @param string $to
		 */
		do_action( 'canvasly-lite/upgrade/after', $from, $to );
	}

	/**
	 * @param array<string,mixed> $queue
	 * @param array<string,mixed> $task
	 */
	private static function record_failure( $queue, $task ) {
		$error = (string) ( $task['error'] ?? '' );
		$row   = array(
			'from'  => (string) ( $queue['from'] ?? '' ),
			'to'    => (string) ( $queue['to'] ?? '' ),
			'task'  => (string) ( $task['id'] ?? '' ),
			'error' => $error,
			'time'  => time(),
		);
		update_option( self::OPTION_FAILED, $row, false );
		if ( class_exists( Logger::class ) ) {
			Logger::error( 'upgrade.failed', $row );
		}
	}

	private static function schedule() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_single_event' ) ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON ) ) {
			return;
		}
		$delay = 5;
		/**
		 * Filter seconds before the next upgrade batch cron event.
		 *
		 * @param int $delay
		 */
		$delay = absint( apply_filters( 'canvasly-lite/upgrades/delay', $delay ) );
		wp_schedule_single_event( time() + max( 1, $delay ), self::CRON );
	}

	/**
	 * @return bool
	 */
	private static function acquire_lock() {
		$now  = time();
		$lock = absint( get_option( self::LOCK, 0 ) );
		if ( $lock && ( $now - $lock ) < self::LOCK_TTL ) {
			return false;
		}
		update_option( self::LOCK, $now, false );
		return true;
	}

	private static function release_lock() {
		delete_option( self::LOCK );
	}

	/**
	 * @param string $state
	 * @return string
	 */
	private static function state_label( $state ) {
		if ( $state === 'running' ) {
			return __( 'Running', 'canvasly-lite' );
		}
		if ( $state === 'failed' ) {
			return __( 'Failed', 'canvasly-lite' );
		}
		return __( 'Idle', 'canvasly-lite' );
	}

	/**
	 * @return string
	 */
	public static function retry_url() {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=lb_upgrade_retry' ), 'lb_upgrade_retry' );
		return is_string( $url ) ? $url : admin_url( 'admin-post.php?action=lb_upgrade_retry' );
	}

	/**
	 * @return string
	 */
	public static function download_log_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=lb_log_download' ), 'lb_log_download' );
	}

	/**
	 * @return string
	 */
	private static function tools_url() {
		if ( class_exists( AdminSettings::class ) ) {
			return AdminSettings::url( 'tools' );
		}
		return admin_url( 'admin.php?page=canvasly-lite-tools' );
	}

	private static function redirect_back() {
		if ( class_exists( AdminSettings::class ) ) {
			$url = AdminSettings::action_return_url();
			if ( $url !== '' ) {
				wp_safe_redirect( $url );
				exit;
			}
		}
		$ref = function_exists( 'wp_get_referer' ) ? wp_get_referer() : '';
		wp_safe_redirect( $ref ? $ref : admin_url( 'admin.php?page=canvasly-lite-tools' ) );
		exit;
	}

	/**
	 * @param string $type
	 * @param string $message
	 */
	public static function store_notice( $type, $message ) {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		$ttl = defined( 'MINUTE_IN_SECONDS' ) ? 5 * MINUTE_IN_SECONDS : 300;
		set_transient(
			self::NOTICE . '_' . ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 ),
			array(
				'type'    => $type,
				'message' => $message,
			),
			$ttl
		);
	}
}
