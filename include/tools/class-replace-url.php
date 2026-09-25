<?php
namespace CanvaslyLite\Tools;

use CanvaslyLite\Compatibility\Meta;
use CanvaslyLite\Document\DocumentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Replace URL: rewrite site URLs inside document JSON and CSS cache.
 */
class ReplaceUrl {
	const NOTICE = 'canvasly_lite_replace_url_notice';
	const REPORT = 'canvasly_lite_replace_url_report';

	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'canvasly-lite/tools/screen', array( self::class, 'screen' ), 5 );
			add_action( 'admin_post_lb_replace_url', array( self::class, 'handle' ) );
			add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		}
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		$ns = $namespace !== '' ? $namespace : 'canvasly-lite/v1';
		register_rest_route(
			$ns,
			'/tools/replace-url',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_replace' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_replace( $req ) {
		$d   = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		$out = self::replace(
			isset( $d['from'] ) ? (string) $d['from'] : '',
			isset( $d['to'] ) ? (string) $d['to'] : '',
			array( 'dry_run' => ! empty( $d['dry_run'] ) )
		);
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		return rest_ensure_response( $out );
	}

	public static function handle() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can replace URLs.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_replace_url' );
		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		$dry  = ( sanitize_key( wp_unslash( $_POST['mode'] ?? 'run' ) ) !== 'run' );
		$out  = self::replace( $from, $to, array( 'dry_run' => $dry ) );
		$ttl  = defined( 'MINUTE_IN_SECONDS' ) ? 10 * MINUTE_IN_SECONDS : 600;
		if ( is_wp_error( $out ) ) {
			self::store_notice( 'error', $out->get_error_message() );
		} else {
			set_transient( self::REPORT . '_' . get_current_user_id(), $out, $ttl );
			$count = (int) ( $out['replacements'] ?? 0 );
			$posts = (int) ( $out['posts'] ?? 0 );
			if ( $dry ) {
				self::store_notice(
					'success',
					sprintf(
						/* translators: 1: replacement count, 2: post count */
						_n(
							'Dry run: %1$d replacement across %2$d item.',
							'Dry run: %1$d replacements across %2$d items.',
							$count,
							'canvasly-lite'
						),
						$count,
						$posts
					)
				);
			} else {
				self::store_notice(
					'success',
					sprintf(
						/* translators: 1: replacement count, 2: post count */
						_n(
							'Replaced %1$d occurrence across %2$d item.',
							'Replaced %1$d occurrences across %2$d items.',
							$count,
							'canvasly-lite'
						),
						$count,
						$posts
					)
				);
			}
		}
		wp_safe_redirect( self::tools_url() );
		exit;
	}

	public static function tools_url() {
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) ) {
			return \CanvaslyLite\Settings\AdminSettings::tools_or_settings_url();
		}
		return admin_url( 'admin.php?page=canvasly-lite-tools' );
	}

	private static function store_notice( $type, $message ) {
		set_transient(
			self::NOTICE . '_' . get_current_user_id(),
			array(
				'type'    => $type === 'success' ? 'success' : 'error',
				'message' => $message,
			),
			120
		);
	}

	public static function admin_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) ) {
			if ( ! \CanvaslyLite\Settings\AdminSettings::is_ops_screen( $screen ) ) {
				return;
			}
		} elseif ( ! $screen || ( $screen->id ?? '' ) !== 'canvasly-lite_page_canvasly-lite-tools' ) {
			return;
		}
		$n = get_transient( self::NOTICE . '_' . get_current_user_id() );
		if ( ! is_array( $n ) ) {
			return;
		}
		delete_transient( self::NOTICE . '_' . get_current_user_id() );
		$class = ( $n['type'] ?? '' ) === 'success' ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $n['message'] ?? '' ) ) . '</p></div>';
	}

	public static function screen() {
		if ( ! self::can_manage() ) {
			return;
		}
		$report = get_transient( self::REPORT . '_' . get_current_user_id() );
		if ( ! is_array( $report ) ) {
			$report = null;
		}

		echo '<hr><h2>' . esc_html__( 'Replace URL', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Rewrite a site URL inside Canvasly documents and compiled CSS. Use this after moving from staging to production. The change cannot be undone — run a dry run first.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_replace_url' );
		echo '<input type="hidden" name="action" value="lb_replace_url">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="lb-replace-from">' . esc_html__( 'Old URL', 'canvasly-lite' ) . '</label></th><td>';
		echo '<input class="regular-text code" id="lb-replace-from" name="from" type="url" required placeholder="https://staging.example.com">';
		echo '</td></tr>';
		echo '<tr><th><label for="lb-replace-to">' . esc_html__( 'New URL', 'canvasly-lite' ) . '</label></th><td>';
		echo '<input class="regular-text code" id="lb-replace-to" name="to" type="url" required placeholder="https://www.example.com">';
		echo '<p class="description">' . esc_html__( 'Enter the full URL including the protocol. JSON-escaped and percent-encoded copies of the same URL are updated too.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr></tbody></table>';
		echo '<p>';
		echo '<button class="button" type="submit" name="mode" value="preview">' . esc_html__( 'Dry run', 'canvasly-lite' ) . '</button> ';
		echo '<button class="button button-primary" type="submit" name="mode" value="run">' . esc_html__( 'Replace URL', 'canvasly-lite' ) . '</button>';
		echo '</p>';
		echo '</form>';

		if ( $report ) {
			self::render_report( $report );
		}
	}

	/**
	 * @param array $report
	 */
	public static function render_report( array $report ) {
		$dry = ! empty( $report['dry_run'] );
		echo '<h3>' . esc_html( $dry ? __( 'Dry-run report', 'canvasly-lite' ) : __( 'Replace URL report', 'canvasly-lite' ) ) . '</h3>';
		echo '<p>';
		echo esc_html(
			sprintf(
				/* translators: 1: posts, 2: replacements, 3: css caches */
				__( 'Items: %1$d. Replacements: %2$d. CSS caches: %3$d.', 'canvasly-lite' ),
				(int) ( $report['posts'] ?? 0 ),
				(int) ( $report['replacements'] ?? 0 ),
				(int) ( $report['css'] ?? 0 )
			)
		);
		echo '</p>';
	}

	/**
	 * Replace $from with $to across document JSON and CSS cache.
	 *
	 * @param string $from
	 * @param string $to
	 * @param array  $args {dry_run:bool}
	 * @return array|\WP_Error
	 */
	public static function replace( $from, $to, $args = array() ) {
		$args = is_array( $args ) ? $args : array();
		$dry  = ! empty( $args['dry_run'] );
		$from = trim( (string) $from );
		$to   = trim( (string) $to );

		$valid = self::validate( $from, $to );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$from = $valid[0];
		$to   = $valid[1];

		$pairs = self::pairs( $from, $to );
		/**
		 * Filter old→new URL pairs (includes escaped/encoded variants).
		 *
		 * @param array  $pairs
		 * @param string $from
		 * @param string $to
		 */
		$filtered = apply_filters( 'canvasly-lite/replace_url/pairs', $pairs, $from, $to );
		if ( is_array( $filtered ) && $filtered ) {
			$pairs = $filtered;
		}

		$report = array(
			'dry_run'       => $dry,
			'from'          => $from,
			'to'            => $to,
			'posts'         => 0,
			'replacements'  => 0,
			'css'           => 0,
			'items'         => array(),
		);

		$seen = array();
		foreach ( self::rows() as $row ) {
			$id  = absint( $row['post_id'] ?? 0 );
			$key = (string) ( $row['meta_key'] ?? '' );
			$raw = $row['meta_value'] ?? '';
			if ( ! $id || $key === '' ) {
				continue;
			}
			if ( is_array( $raw ) ) {
				$raw = function_exists( 'wp_json_encode' ) ? wp_json_encode( $raw ) : json_encode( $raw );
			}
			if ( ! is_string( $raw ) || $raw === '' ) {
				continue;
			}
			$count = self::count_in( $raw, $pairs );
			if ( $count < 1 ) {
				continue;
			}
			$new = self::replace_in( $raw, $pairs );
			if ( $new === $raw ) {
				continue;
			}
			if ( ! $dry && class_exists( Meta::class ) ) {
				Meta::write( $id, $key, $new );
			} elseif ( ! $dry ) {
				update_post_meta( $id, $key, $new );
			}
			$report['replacements'] += $count;
			$is_css = ( $key === '_lb_css_cache' || ( class_exists( DocumentManager::class ) && $key === DocumentManager::CSS_CACHE ) );
			if ( $is_css ) {
				$report['css']++;
			}
			if ( ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$report['posts']++;
			}
			$report['items'][] = array(
				'id'    => $id,
				'key'   => $key,
				'count' => $count,
				'css'   => $is_css,
			);
		}

		/**
		 * Filter the Replace URL report.
		 *
		 * @param array $report
		 * @param array $args
		 */
		$filtered_report = apply_filters( 'canvasly-lite/replace_url/report', $report, $args );
		return is_array( $filtered_report ) ? $filtered_report : $report;
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @return array|\WP_Error
	 */
	public static function validate( $from, $to ) {
		$from = trim( (string) $from );
		$to   = trim( (string) $to );
		if ( $from === '' || $to === '' ) {
			return new \WP_Error(
				'empty',
				__( 'Both URLs are required.', 'canvasly-lite' ),
				array( 'status' => 400 )
			);
		}
		if ( $from === $to ) {
			return new \WP_Error(
				'same',
				__( 'The two URLs are the same.', 'canvasly-lite' ),
				array( 'status' => 400 )
			);
		}
		if ( ! self::looks_like_url( $from ) || ! self::looks_like_url( $to ) ) {
			return new \WP_Error(
				'invalid',
				__( 'Enter a full URL including the protocol (https://).', 'canvasly-lite' ),
				array( 'status' => 400 )
			);
		}
		return array( $from, $to );
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	public static function looks_like_url( $url ) {
		return (bool) preg_match( '#^https?://[^\s]+#i', (string) $url );
	}

	/**
	 * Old→new map including JSON-escaped slashes and percent-encoding.
	 *
	 * @param string $from
	 * @param string $to
	 * @return array<string,string>
	 */
	public static function pairs( $from, $to ) {
		$pairs           = array();
		$pairs[ $from ]  = $to;
		$esc_from        = str_replace( '/', '\\/', $from );
		$esc_to          = str_replace( '/', '\\/', $to );
		if ( $esc_from !== $from ) {
			$pairs[ $esc_from ] = $esc_to;
		}
		$enc_from = rawurlencode( $from );
		$enc_to   = rawurlencode( $to );
		if ( $enc_from !== $from && $enc_from !== $esc_from ) {
			$pairs[ $enc_from ] = $enc_to;
		}
		return $pairs;
	}

	/**
	 * @param string               $text
	 * @param array<string,string> $pairs
	 * @return string
	 */
	public static function replace_in( $text, $pairs ) {
		$text = (string) $text;
		foreach ( $pairs as $old => $new ) {
			if ( $old === '' || $old === $new ) {
				continue;
			}
			$text = str_replace( $old, $new, $text );
		}
		return $text;
	}

	/**
	 * @param string               $text
	 * @param array<string,string> $pairs
	 * @return int
	 */
	public static function count_in( $text, $pairs ) {
		$n    = 0;
		$text = (string) $text;
		foreach ( $pairs as $old => $new ) {
			if ( $old === '' ) {
				continue;
			}
			$n += substr_count( $text, $old );
		}
		return $n;
	}

	/**
	 * @return array<int,array{post_id:int,meta_key:string,meta_value:mixed}>
	 */
	public static function rows() {
		$keys = class_exists( Meta::class ) ? Meta::replaceable_keys() : array( '_lb_document_data', '_lb_template_data', '_lb_component_data', '_lb_css_cache' );
		$keys = array_values( array_filter( array_map( 'strval', $keys ) ) );
		if ( ! $keys ) {
			return array();
		}

		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->postmeta ) && method_exists( $wpdb, 'get_results' ) && method_exists( $wpdb, 'prepare' ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
			$cache_key    = 'canvasly_lite_replace_url_rows_' . md5( implode( ',', $keys ) );
			$found        = function_exists( 'wp_cache_get' ) ? wp_cache_get( $cache_key, 'canvasly-lite' ) : false;
			if ( false === $found ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Bulk postmeta lookup; IN() uses generated %s placeholders passed to $wpdb->prepare().
				$found = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)",
						$keys
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				if ( function_exists( 'wp_cache_set' ) ) {
					wp_cache_set( $cache_key, $found, 'canvasly-lite', 60 );
				}
			}
			$out          = array();
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Result map keys from postmeta rows, not a WP_Query.
			foreach ( (array) $found as $row ) {
				$out[] = array(
					'post_id'    => (int) ( is_object( $row ) ? $row->post_id : ( $row['post_id'] ?? 0 ) ),
					'meta_key'   => (string) ( is_object( $row ) ? $row->meta_key : ( $row['meta_key'] ?? '' ) ),
					'meta_value' => is_object( $row ) ? $row->meta_value : ( $row['meta_value'] ?? '' ),
				);
			}
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			return $out;
		}

		$ids = array();
		if ( function_exists( 'get_posts' ) ) {
			$ids = get_posts(
				array(
					'post_type'              => 'any',
					'post_status'            => 'any',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
		}
		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = absint( $id );
			if ( ! $id ) {
				continue;
			}
			foreach ( $keys as $key ) {
				$val = get_post_meta( $id, $key, true );
				if ( $val === '' || $val === null || $val === false ) {
					continue;
				}
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Result map keys from get_post_meta(), not a WP_Query.
				$out[] = array(
					'post_id'    => $id,
					'meta_key'   => $key,
					'meta_value' => $val,
				);
				// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			}
		}
		return $out;
	}
}
