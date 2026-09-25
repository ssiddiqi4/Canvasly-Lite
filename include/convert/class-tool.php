<?php
namespace CanvaslyLite\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk convert tool: Tools screen, dry-run report, REST endpoints.
 */
class Tool {
	const NOTICE = 'canvasly_lite_convert_notice';
	const REPORT = 'canvasly_lite_convert_report';

	public static function init() {
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'canvasly-lite/tools/screen', array( self::class, 'screen' ) );
			add_action( 'admin_post_lb_convert', array( self::class, 'handle' ) );
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
			'/convert/candidates',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'rest_candidates' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			$ns,
			'/convert/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_preview' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			$ns,
			'/convert/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_run' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
	}

	public static function rest_candidates() {
		return rest_ensure_response(
			array(
				'candidates' => Converter::candidates(),
				'widgets'    => array_keys( Map::widgets() ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $req
	 */
	public static function rest_preview( $req ) {
		return rest_ensure_response( self::run_from_request( $req, true ) );
	}

	/**
	 * @param \WP_REST_Request $req
	 */
	public static function rest_run( $req ) {
		return rest_ensure_response( self::run_from_request( $req, false ) );
	}

	/**
	 * @param \WP_REST_Request $req
	 * @param bool             $dry
	 * @return array|\WP_Error
	 */
	private static function run_from_request( $req, $dry ) {
		$d     = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		$ids   = self::ids_from( $d['ids'] ?? array() );
		$force = ! empty( $d['force'] );
		if ( ! $ids ) {
			foreach ( Converter::candidates() as $row ) {
				$ids[] = absint( $row['id'] ?? 0 );
			}
			$ids = array_values( array_filter( $ids ) );
		}
		$conv = new Converter();
		return $conv->convert_posts( $ids, array( 'dry_run' => $dry, 'force' => $force ) );
	}

	public static function handle() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can convert layout data.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_convert' );
		$dry   = ( sanitize_key( wp_unslash( $_POST['mode'] ?? 'preview' ) ) !== 'run' );
		$ids   = self::ids_from( isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array() );
		$force = ! empty( $_POST['force'] );
		if ( ! empty( $_POST['all'] ) && ! $ids ) {
			foreach ( Converter::candidates() as $row ) {
				$ids[] = absint( $row['id'] ?? 0 );
			}
			$ids = array_values( array_filter( $ids ) );
		}
		$conv   = new Converter();
		$report = $conv->convert_posts( $ids, array( 'dry_run' => $dry, 'force' => $force ) );
		$ttl    = defined( 'MINUTE_IN_SECONDS' ) ? 10 * MINUTE_IN_SECONDS : 600;
		set_transient( self::REPORT . '_' . get_current_user_id(), $report, $ttl );
		$count = (int) ( $report['converted'] ?? 0 );
		if ( $dry ) {
			self::store_notice(
				'success',
				sprintf(
					/* translators: %d: number of posts in the dry-run */
					_n( 'Dry run finished for %d item.', 'Dry run finished for %d items.', $count, 'canvasly-lite' ),
					$count
				)
			);
		} else {
			self::store_notice(
				empty( $report['errors'] ) ? 'success' : 'error',
				sprintf(
					/* translators: %d: number of converted posts */
					_n( 'Converted %d item.', 'Converted %d items.', $count, 'canvasly-lite' ),
					$count
				)
			);
		}
		wp_safe_redirect( self::tools_url() );
		exit;
	}

	/**
	 * @param mixed $raw
	 * @return int[]
	 */
	public static function ids_from( $raw ) {
		$ids = array();
		foreach ( (array) $raw as $id ) {
			$id = absint( $id );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
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
		if ( ! $screen || ( $screen->id ?? '' ) !== 'canvasly-lite_page_canvasly-lite-tools' ) {
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
		$candidates = Converter::candidates();
		$report     = get_transient( self::REPORT . '_' . get_current_user_id() );
		if ( ! is_array( $report ) ) {
			$report = null;
		}

		echo '<hr><h2>' . esc_html__( 'Convert layout data', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Read stored third-party builder JSON, map sections and columns to containers, and produce Canvasly documents. Run a dry-run report first. Source data is left in place.', 'canvasly-lite' ) . '</p>';

		if ( ! $candidates ) {
			echo '<p>' . esc_html__( 'No convertible posts were found.', 'canvasly-lite' ) . '</p>';
			if ( $report ) {
				self::render_report( $report );
			}
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_convert' );
		echo '<input type="hidden" name="action" value="lb_convert">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Posts', 'canvasly-lite' ) . '</th><td>';
		echo '<fieldset style="max-height:260px;overflow:auto;border:1px solid #dcdcde;padding:8px 12px;max-width:640px">';
		foreach ( $candidates as $p ) {
			$label = ( $p['title'] ?? '' ) !== '' ? $p['title'] : '#' . (int) ( $p['id'] ?? 0 );
			$meta  = (string) ( $p['type'] ?? 'post' );
			if ( ! empty( $p['has_loom'] ) ) {
				$meta .= ' · ' . __( 'already has a Canvasly document', 'canvasly-lite' );
			}
			echo '<label style="display:block;margin:3px 0;"><input type="checkbox" name="ids[]" value="' . esc_attr( (string) ( $p['id'] ?? 0 ) ) . '" checked> ' . esc_html( $label . ' (' . $meta . ')' ) . '</label>';
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Library items are saved as Canvasly templates. Pages and posts that already have a Canvasly document are skipped unless you force overwrite.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Options', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="force" value="1"> ' . esc_html__( 'Overwrite existing Canvasly documents', 'canvasly-lite' ) . '</label>';
		echo '</td></tr></tbody></table>';
		echo '<p>';
		echo '<button class="button" type="submit" name="mode" value="preview">' . esc_html__( 'Dry run', 'canvasly-lite' ) . '</button> ';
		echo '<button class="button button-primary" type="submit" name="mode" value="run">' . esc_html__( 'Convert selected', 'canvasly-lite' ) . '</button>';
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
		echo '<h3>' . esc_html( $dry ? __( 'Dry-run report', 'canvasly-lite' ) : __( 'Conversion report', 'canvasly-lite' ) ) . '</h3>';
		echo '<p>';
		echo esc_html(
			sprintf(
				/* translators: 1: posts, 2: mapped widgets, 3: unmapped widget types, 4: global binds */
				__( 'Posts: %1$d. Mapped widgets: %2$d. Unmapped types: %3$d. Global color binds: %4$d.', 'canvasly-lite' ),
				(int) ( $report['posts'] ?? 0 ),
				(int) ( $report['mapped'] ?? 0 ),
				count( (array) ( $report['unmapped'] ?? array() ) ),
				(int) ( $report['globals'] ?? 0 )
			)
		);
		if ( ! empty( $report['skipped'] ) ) {
			echo ' ' . esc_html(
				sprintf(
					/* translators: %d: skipped count */
					__( 'Skipped: %d.', 'canvasly-lite' ),
					(int) $report['skipped']
				)
			);
		}
		if ( ! empty( $report['errors'] ) ) {
			echo ' ' . esc_html(
				sprintf(
					/* translators: %d: error count */
					__( 'Errors: %d.', 'canvasly-lite' ),
					(int) $report['errors']
				)
			);
		}
		echo '</p>';

		$unmapped = (array) ( $report['unmapped'] ?? array() );
		if ( $unmapped ) {
			arsort( $unmapped );
			echo '<h4>' . esc_html__( 'Unmapped widgets', 'canvasly-lite' ) . '</h4>';
			echo '<table class="widefat striped" style="max-width:480px"><thead><tr><th>' . esc_html__( 'Source type', 'canvasly-lite' ) . '</th><th>' . esc_html__( 'Count', 'canvasly-lite' ) . '</th></tr></thead><tbody>';
			foreach ( $unmapped as $type => $n ) {
				echo '<tr><td><code>' . esc_html( (string) $type ) . '</code></td><td>' . esc_html( (string) (int) $n ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		$items = (array) ( $report['items'] ?? array() );
		if ( $items ) {
			echo '<h4>' . esc_html__( 'Items', 'canvasly-lite' ) . '</h4>';
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'ID', 'canvasly-lite' ) . '</th>';
			echo '<th>' . esc_html__( 'Title', 'canvasly-lite' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'canvasly-lite' ) . '</th>';
			echo '<th>' . esc_html__( 'Mapped', 'canvasly-lite' ) . '</th>';
			echo '<th>' . esc_html__( 'Unmapped', 'canvasly-lite' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $items as $row ) {
				$un = (array) ( $row['unmapped'] ?? array() );
				$ul = array();
				foreach ( $un as $t => $n ) {
					$ul[] = $t . '×' . (int) $n;
				}
				echo '<tr>';
				echo '<td>' . esc_html( (string) (int) ( $row['id'] ?? 0 ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['title'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['status'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) (int) ( $row['mapped'] ?? 0 ) ) . '</td>';
				echo '<td>' . esc_html( $ul ? implode( ', ', $ul ) : '—' ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
	}
}
