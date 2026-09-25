<?php
namespace CanvaslyLite\Ops;

use CanvaslyLite\Settings\AdminSettings;
use CanvaslyLite\Settings\Experiments;
use CanvaslyLite\Settings\GlobalSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System Info report page and download (Roadmap 7.3).
 */
class SystemInfo {
	const PAGE = 'canvasly-lite-system-info';

	public static function init() {
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'admin_menu', array( self::class, 'menu' ), 22 );
			add_action( 'admin_post_lb_system_info_download', array( self::class, 'handle_download' ) );
			add_action( 'canvasly-lite/tools/screen', array( self::class, 'tools_screen' ), 24 );
		}
	}

	public static function can_manage() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	public static function menu() {
		add_submenu_page(
			'canvasly-lite',
			__( 'System Info', 'canvasly-lite' ),
			__( 'System Info', 'canvasly-lite' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'screen' )
		);
	}

	/**
	 * Structured report. Secrets are never included.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function report() {
		$sections = array(
			'wordpress'    => self::section_wordpress(),
			'server'       => self::section_server(),
			'theme'        => self::section_theme(),
			'plugins'      => self::section_plugins(),
			'canvasly-lite'  => self::section_canvasly_lite(),
		);
		/**
		 * Filter the System Info report sections.
		 *
		 * @param array $sections
		 */
		$filtered = apply_filters( 'canvasly-lite/system_info', $sections );
		return is_array( $filtered ) ? $filtered : $sections;
	}

	/**
	 * @param array|null $report
	 * @return string
	 */
	public static function to_text( $report = null ) {
		$report = is_array( $report ) ? $report : self::report();
		$labels = self::section_labels();
		$out    = array();
		$out[]  = 'Canvasly System Info';
		$out[]  = 'Generated: ' . ( function_exists( 'current_time' ) ? current_time( 'c' ) : gmdate( 'c' ) );
		$out[]  = '';
		foreach ( $report as $id => $rows ) {
			if ( ! is_array( $rows ) ) {
				continue;
			}
			$title = $labels[ $id ] ?? ( is_string( $id ) ? $id : '' );
			$out[] = '== ' . $title . ' ==';
			foreach ( $rows as $key => $value ) {
				if ( is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}
				$out[] = $key . ': ' . (string) $value;
			}
			$out[] = '';
		}
		return implode( "\n", $out );
	}

	/**
	 * @return array<string,string>
	 */
	public static function section_labels() {
		return array(
			'wordpress'   => __( 'WordPress', 'canvasly-lite' ),
			'server'      => __( 'Server', 'canvasly-lite' ),
			'theme'       => __( 'Theme', 'canvasly-lite' ),
			'plugins'     => __( 'Active Plugins', 'canvasly-lite' ),
			'canvasly-lite' => __( 'Canvasly', 'canvasly-lite' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function section_wordpress() {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		return array(
			'Version'            => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
			'Site URL'           => function_exists( 'site_url' ) ? site_url() : '',
			'Home URL'           => function_exists( 'home_url' ) ? home_url() : '',
			'Multisite'          => ( defined( 'MULTISITE' ) && MULTISITE ) ? 'Yes' : 'No',
			'Language'           => function_exists( 'get_locale' ) ? get_locale() : '',
			'Permalink'          => function_exists( 'get_option' ) ? (string) get_option( 'permalink_structure', '' ) : '',
			'WP Memory Limit'    => defined( 'WP_MEMORY_LIMIT' ) ? (string) WP_MEMORY_LIMIT : '',
			'WP_DEBUG'           => $debug ? 'Yes' : 'No',
			'WP_DEBUG_LOG'       => ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? 'Yes' : 'No',
			'WP_DEBUG_DISPLAY'   => ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) ? 'Yes' : 'No',
			'Script Debug'       => ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'Yes' : 'No',
			'Timezone'           => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : (string) ( function_exists( 'get_option' ) ? get_option( 'timezone_string', '' ) : '' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function section_server() {
		global $wpdb;
		$mysql = '';
		if ( isset( $wpdb ) && is_object( $wpdb ) && isset( $wpdb->db_version ) ) {
			$mysql = is_callable( array( $wpdb, 'db_version' ) ) ? (string) $wpdb->db_version() : '';
		}
		$ext = array( 'json', 'zip', 'gd', 'imagick', 'mbstring', 'curl', 'xml', 'exif' );
		$on  = array();
		foreach ( $ext as $e ) {
			$on[] = $e . '=' . ( extension_loaded( $e ) ? 'yes' : 'no' );
		}
		return array(
			'PHP'                 => PHP_VERSION,
			'SAPI'                => PHP_SAPI,
			'OS'                  => PHP_OS,
			'Software'            => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'MySQL'               => $mysql,
			'memory_limit'        => (string) ini_get( 'memory_limit' ),
			'max_execution_time'  => (string) ini_get( 'max_execution_time' ),
			'upload_max_filesize' => (string) ini_get( 'upload_max_filesize' ),
			'post_max_size'       => (string) ini_get( 'post_max_size' ),
			'max_input_vars'      => (string) ini_get( 'max_input_vars' ),
			'Extensions'          => implode( ', ', $on ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function section_theme() {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return array();
		}
		$theme  = wp_get_theme();
		$parent = $theme && method_exists( $theme, 'parent' ) ? $theme->parent() : false;
		return array(
			'Name'    => $theme ? (string) $theme->get( 'Name' ) : '',
			'Version' => $theme ? (string) $theme->get( 'Version' ) : '',
			'Author'  => $theme ? wp_strip_all_tags( (string) $theme->get( 'Author' ) ) : '',
			'Parent'  => $parent ? (string) $parent->get( 'Name' ) . ' ' . (string) $parent->get( 'Version' ) : '',
			'Template'=> function_exists( 'get_template' ) ? get_template() : '',
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function section_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			$path = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/plugin.php' : '';
			if ( $path && is_readable( $path ) ) {
				require_once $path;
			}
		}
		$active = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
		$all    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$out    = array();
		foreach ( $active as $file ) {
			$file = (string) $file;
			$meta = isset( $all[ $file ] ) && is_array( $all[ $file ] ) ? $all[ $file ] : array();
			$name = (string) ( $meta['Name'] ?? $file );
			$ver  = (string) ( $meta['Version'] ?? '' );
			$out[ $file ] = $ver !== '' ? $name . ' ' . $ver : $name;
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	public static function section_canvasly_lite() {
		$g    = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		$m    = class_exists( AdminSettings::class ) ? AdminSettings::maintenance() : array();
		$exp  = class_exists( Experiments::class ) ? Experiments::all() : array();
		$active_exp = array();
		foreach ( $exp as $row ) {
			if ( is_array( $row ) && ( $row['state'] ?? '' ) === 'active' ) {
				$active_exp[] = (string) ( $row['id'] ?? '' );
			}
		}
		$rolls = class_exists( Rollback::class ) ? Rollback::versions() : array();
		$names = array();
		foreach ( $rolls as $z ) {
			$names[] = (string) ( $z['name'] ?? '' );
		}
		return array(
			'Version'              => defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '',
			'Path'                 => defined( 'CANVASLY_LITE_PATH' ) ? CANVASLY_LITE_PATH : '',
			'CSS print method'     => (string) ( $g['css_print_method'] ?? '' ),
			'font-display'         => (string) ( $g['font_display'] ?? '' ),
			'Google Fonts local'   => ! empty( $g['google_fonts_local'] ) ? 'Yes' : 'No',
			'Unit cache'        => ! isset( $g['unit_cache'] ) || ! empty( $g['unit_cache'] ) ? 'Yes' : 'No',
			'Lazy load'            => ! isset( $g['lazy_load'] ) || ! empty( $g['lazy_load'] ) ? 'Yes' : 'No',
			'Optimized markup'     => ! empty( $g['optimized_markup'] ) ? 'Yes' : 'No',
			'Editor loader'        => (string) ( $g['editor_loader_mode'] ?? 'default' ),
			'Maintenance mode'     => (string) ( $m['mode'] ?? 'off' ),
			'Maintenance template' => (string) (int) ( $m['template'] ?? 0 ),
			'Safe Mode (user)'     => ( class_exists( AdminSettings::class ) && AdminSettings::safe_mode() ) ? 'Yes' : 'No',
			'Safe Mode mu-plugin'  => ( class_exists( SafeMode::class ) && SafeMode::mu_installed() ) ? 'Yes' : 'No',
			'Rollback keep'        => (string) (int) ( $g['rollback_keep'] ?? 3 ),
			'Stored rollbacks'     => $names ? implode( ', ', $names ) : '(none)',
			'Active experiments'   => $active_exp ? implode( ', ', $active_exp ) : '(none)',
			'Has Maps API key'     => ( class_exists( AdminSettings::class ) && AdminSettings::google_maps_api_key() !== '' ) ? 'Yes' : 'No',
			'Has reCAPTCHA secret' => ( class_exists( AdminSettings::class ) && AdminSettings::recaptcha_secret_key() !== '' ) ? 'Yes' : 'No',
			'Upgrade stored'       => class_exists( '\\CanvaslyLite\\Upgrade\\Upgrades' ) ? (string) \CanvaslyLite\Upgrade\Upgrades::stored_version() : '',
			'Upgrade status'       => class_exists( '\\CanvaslyLite\\Upgrade\\Upgrades' ) ? (string) ( \CanvaslyLite\Upgrade\Upgrades::status()['state'] ?? '' ) : '',
			'Log size'             => class_exists( '\\CanvaslyLite\\Log\\Logger' ) ? (string) \CanvaslyLite\Log\Logger::size() : '0',
		);
	}

	public static function screen() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can view system info.', 'canvasly-lite' ) );
		}
		echo '<div class="wrap lb-settings-wrap">';
		echo '<h1>' . esc_html__( 'Canvasly System Info', 'canvasly-lite' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Environment report for support. API keys and secrets are not included.', 'canvasly-lite' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( self::download_url() ) . '">' . esc_html__( 'Download report', 'canvasly-lite' ) . '</a></p>';
		self::render_report( self::report() );
		echo '</div>';
	}

	/**
	 * @param array $report
	 */
	public static function render_report( $report ) {
		$labels = self::section_labels();
		foreach ( (array) $report as $id => $rows ) {
			if ( ! is_array( $rows ) ) {
				continue;
			}
			$title = $labels[ $id ] ?? (string) $id;
			echo '<h2>' . esc_html( $title ) . '</h2>';
			echo '<table class="widefat striped lb-ops-table"><tbody>';
			foreach ( $rows as $key => $value ) {
				echo '<tr><th>' . esc_html( (string) $key ) . '</th><td><code>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</code></td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	public static function tools_screen() {
		if ( ! self::can_manage() ) {
			return;
		}
		echo '<hr><h2>' . esc_html__( 'System Info', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'WordPress, server, theme, plugin and Canvasly environment. Secrets are redacted.', 'canvasly-lite' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'View report', 'canvasly-lite' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( self::download_url() ) . '">' . esc_html__( 'Download report', 'canvasly-lite' ) . '</a></p>';
	}

	/**
	 * @return string
	 */
	public static function download_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=lb_system_info_download' ), 'lb_system_info_download' );
	}

	public static function handle_download() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can view system info.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_system_info_download' );
		$text = self::to_text();
		$date = gmdate( 'Y-m-d' );
		$filename = 'canvasly-lite-system-info-' . $date . '.txt';
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) strlen( $text ) );
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		$ns = $namespace !== '' ? $namespace : 'canvasly-lite/v1';
		register_rest_route(
			$ns,
			'/system-info',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'rest_get' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
	}

	public static function rest_get() {
		return rest_ensure_response(
			array(
				'report' => self::report(),
				'text'   => self::to_text(),
			)
		);
	}
}
