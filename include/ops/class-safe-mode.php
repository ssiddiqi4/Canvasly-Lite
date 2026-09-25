<?php
namespace CanvaslyLite\Ops;

use CanvaslyLite\Settings\AdminSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-user Safe Mode: editor requests load without other plugins or the theme.
 *
 * An mu-plugin (copied into wp-content/mu-plugins) filters active_plugins and
 * the theme before they bootstrap. A signed cookie identifies the user so the
 * rest of the site stays unchanged.
 */
class SafeMode {
	const COOKIE    = 'lb_safe_mode';
	const TOKEN     = 'canvasly_lite_safe_mode_token';
	const BOOT      = 'canvasly_lite_safe_mode_boot';
	const MU_FILE   = 'canvasly-lite-safe-mode.php';
	const NOTICE    = 'canvasly_lite_safe_mode_notice';
	const THEME     = 'lb-safe';

	public static function init() {
		self::load_loader();
		add_action( 'init', array( self::class, 'refresh_cookie' ), 1 );
		add_action( 'init', array( self::class, 'maybe_reinstall' ), 2 );
		add_action( 'admin_post_lb_safe_mode_exit', array( self::class, 'handle_exit' ) );
		add_action( 'admin_post_lb_safe_mode_enter', array( self::class, 'handle_enter' ) );
		add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar' ), 81 );
		add_action( 'canvasly-lite/tools/screen', array( self::class, 'tools_screen' ), 22 );
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		add_filter( 'canvasly-lite/editor/localize_data', array( self::class, 'localize' ), 10, 2 );
		add_filter( 'admin_body_class', array( self::class, 'admin_body_class' ) );
	}

	public static function load_loader() {
		$file = CANVASLY_LITE_PATH . 'includes/ops/safe-mode-loader.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	public static function can_manage() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * @param int $user_id
	 * @return bool
	 */
	public static function enabled( $user_id = 0 ) {
		if ( class_exists( AdminSettings::class ) ) {
			return AdminSettings::safe_mode( $user_id );
		}
		$user_id = $user_id ? absint( $user_id ) : ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 );
		if ( ! $user_id || ! function_exists( 'get_user_meta' ) ) {
			return false;
		}
		return (string) get_user_meta( $user_id, AdminSettings::SAFE_META, true ) === '1';
	}

	/**
	 * Cookie is valid and this request is a Safe Mode target.
	 *
	 * @return bool
	 */
	public static function active() {
		self::load_loader();
		return function_exists( 'canvasly_lite_safe_mode_applies' ) && canvasly_lite_safe_mode_applies();
	}

	/**
	 * @param bool $on
	 * @param int  $user_id
	 * @return true|\WP_Error
	 */
	public static function set( $on, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 );
		if ( ! $user_id ) {
			return new \WP_Error( 'no_user', __( 'Safe Mode needs a logged-in user.', 'canvasly-lite' ) );
		}
		$meta = class_exists( AdminSettings::class ) ? AdminSettings::SAFE_META : 'canvasly_lite_safe_mode';
		if ( $on ) {
			update_user_meta( $user_id, $meta, '1' );
			$token = self::token_for( $user_id, true );
			self::set_cookie( $user_id, $token );
			$installed = self::install_mu_plugin();
			if ( is_wp_error( $installed ) ) {
				return $installed;
			}
		} else {
			delete_user_meta( $user_id, $meta );
			delete_user_meta( $user_id, self::TOKEN );
			self::clear_cookie();
			if ( ! self::any_user_enabled() ) {
				self::remove_mu_plugin();
			}
		}
		return true;
	}

	/**
	 * @param int  $user_id
	 * @param bool $rotate
	 * @return string
	 */
	public static function token_for( $user_id, $rotate = false ) {
		$user_id = absint( $user_id );
		$stored  = $user_id && function_exists( 'get_user_meta' ) ? (string) get_user_meta( $user_id, self::TOKEN, true ) : '';
		if ( ! $rotate && preg_match( '/^[a-f0-9]{32}$/', $stored ) ) {
			return $stored;
		}
		try {
			$token = bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			$token = md5( uniqid( (string) $user_id, true ) );
		}
		if ( $user_id && function_exists( 'update_user_meta' ) ) {
			update_user_meta( $user_id, self::TOKEN, $token );
		}
		return $token;
	}

	public static function refresh_cookie() {
		if ( ! self::enabled() ) {
			return;
		}
		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		if ( ! $user_id ) {
			return;
		}
		self::set_cookie( $user_id, self::token_for( $user_id ) );
	}

	public static function maybe_reinstall() {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::allows_background() ) {
			return;
		}
		if ( ! self::enabled() || ! self::can_manage() ) {
			return;
		}
		if ( ! is_readable( self::mu_path() ) ) {
			self::install_mu_plugin();
		}
	}

	/**
	 * @param int    $user_id
	 * @param string $token
	 */
	public static function set_cookie( $user_id, $token ) {
		if ( headers_sent() ) {
			return;
		}
		$user_id = absint( $user_id );
		$token   = preg_replace( '/[^a-f0-9]/', '', (string) $token );
		if ( ! $user_id || strlen( $token ) !== 32 ) {
			return;
		}
		$value   = $user_id . '.' . $token;
		$expire  = time() + ( defined( 'DAY_IN_SECONDS' ) ? 2 * DAY_IN_SECONDS : 172800 );
		$path    = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain  = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure  = function_exists( 'is_ssl' ) && is_ssl();
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				self::COOKIE,
				$value,
				array(
					'expires'  => $expire,
					'path'     => $path,
					'domain'   => is_string( $domain ) ? $domain : '',
					'secure'   => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		} else {
			setcookie( self::COOKIE, $value, $expire, $path, is_string( $domain ) ? $domain : '', $secure, true );
		}
		$_COOKIE[ self::COOKIE ] = $value;
	}

	public static function clear_cookie() {
		if ( headers_sent() ) {
			return;
		}
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure = function_exists( 'is_ssl' ) && is_ssl();
		setcookie( self::COOKIE, '', time() - 3600, $path, is_string( $domain ) ? $domain : '', $secure, true );
		unset( $_COOKIE[ self::COOKIE ] );
	}

	/**
	 * @return array
	 */
	public static function boot_payload() {
		$plugin = function_exists( 'plugin_basename' ) && defined( 'CANVASLY_LITE_FILE' )
			? plugin_basename( CANVASLY_LITE_FILE )
			: 'canvasly-lite/canvasly-lite.php';
		return array(
			'plugin'     => $plugin,
			'theme'      => self::THEME,
			'theme_root' => CANVASLY_LITE_PATH . 'includes/ops/themes',
			'loader'     => CANVASLY_LITE_PATH . 'includes/ops/safe-mode-loader.php',
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function install_mu_plugin() {
		update_option( self::BOOT, self::boot_payload(), false );
		$dir = self::mu_dir();
		if ( $dir === '' ) {
			return new \WP_Error(
				'safe_mode_mu',
				__( 'Could not locate the must-use plugins directory.', 'canvasly-lite' )
			);
		}
		if ( ! is_dir( $dir ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $dir );
			} else {
				wp_mkdir_p( $dir );
			}
		}
		$src  = CANVASLY_LITE_PATH . 'includes/ops/mu-plugin.php';
		$dest = self::mu_path();
		if ( ! is_readable( $src ) ) {
			return new \WP_Error( 'safe_mode_mu', __( 'The Safe Mode loader file is missing.', 'canvasly-lite' ) );
		}
		if ( function_exists( 'copy' ) && @copy( $src, $dest ) ) {
			return true;
		}
		$data = file_get_contents( $src );
		if ( $data === false || file_put_contents( $dest, $data ) === false ) {
			return new \WP_Error(
				'safe_mode_mu',
				__( 'Could not write the Safe Mode must-use plugin. Allow writes to wp-content/mu-plugins.', 'canvasly-lite' )
			);
		}
		return true;
	}

	public static function remove_mu_plugin() {
		$path = self::mu_path();
		if ( $path !== '' && is_file( $path ) ) {
			wp_delete_file( $path );
		}
		delete_option( self::BOOT );
	}

	/**
	 * @return string
	 */
	public static function mu_dir() {
		if ( defined( 'WPMU_PLUGIN_DIR' ) && WPMU_PLUGIN_DIR ) {
			return untrailingslashit( WPMU_PLUGIN_DIR );
		}
		if ( defined( 'WP_CONTENT_DIR' ) && WP_CONTENT_DIR ) {
			return untrailingslashit( WP_CONTENT_DIR ) . '/mu-plugins';
		}
		return '';
	}

	/**
	 * @return string
	 */
	public static function mu_path() {
		$dir = self::mu_dir();
		return $dir !== '' ? $dir . '/' . self::MU_FILE : '';
	}

	/**
	 * @return bool
	 */
	public static function mu_installed() {
		$path = self::mu_path();
		return $path !== '' && is_readable( $path );
	}

	/**
	 * @return bool
	 */
	public static function any_user_enabled() {
		if ( ! function_exists( 'get_users' ) ) {
			return self::enabled();
		}
		$users = get_users(
			array(
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Detect whether any user has Safe Mode enabled.
				'meta_key'   => class_exists( AdminSettings::class ) ? AdminSettings::SAFE_META : 'canvasly_lite_safe_mode',
				'meta_value' => '1',
				// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		);
		return ! empty( $users );
	}

	/**
	 * @return string
	 */
	public static function exit_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=lb_safe_mode_exit' ), 'lb_safe_mode_exit' );
	}

	/**
	 * @return string
	 */
	public static function enter_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=lb_safe_mode_enter' ), 'lb_safe_mode_enter' );
	}

	public static function handle_exit() {
		check_admin_referer( 'lb_safe_mode_exit' );
		self::set( false );
		self::store_notice( 'updated', __( 'Safe Mode is off. Other plugins and the theme will load again on the next request.', 'canvasly-lite' ) );
		self::redirect_back();
	}

	public static function handle_enter() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can enable Safe Mode.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_safe_mode_enter' );
		$result = self::set( true );
		if ( is_wp_error( $result ) ) {
			self::store_notice( 'error', $result->get_error_message() );
		} else {
			self::store_notice( 'updated', __( 'Safe Mode is on for your account. Reload the editor to load it without other plugins or the theme.', 'canvasly-lite' ) );
		}
		self::redirect_back();
	}

	private static function redirect_back() {
		$ref = wp_get_referer();
		if ( ! $ref ) {
			$ref = class_exists( AdminSettings::class ) ? AdminSettings::url( 'tools' ) : admin_url( 'admin.php?page=canvasly-lite-tools' );
		}
		wp_safe_redirect( $ref );
		exit;
	}

	/**
	 * @param array $data
	 * @param int   $post_id
	 * @return array
	 */
	public static function localize( $data, $post_id = 0 ) {
		unset( $post_id );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$data['safeMode'] = self::enabled();
		return $data;
	}

	/**
	 * @param string $classes
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		if ( self::enabled() ) {
			$classes .= ' lb-safe-mode-user';
		}
		if ( self::active() ) {
			$classes .= ' lb-safe-mode-active';
		}
		return $classes;
	}

	/**
	 * @param \WP_Admin_Bar $bar
	 */
	public static function admin_bar( $bar ) {
		if ( ! is_object( $bar ) || ! method_exists( $bar, 'add_node' ) || ! self::enabled() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'canvasly-lite-safe-mode',
				'title' => __( 'Safe Mode is on', 'canvasly-lite' ),
				'href'  => self::exit_url(),
				'meta'  => array(
					'class' => 'lb-ab-safe-mode',
					'title' => __( 'Exit Safe Mode', 'canvasly-lite' ),
				),
			)
		);
	}

	public static function print_banner() {
		if ( ! self::enabled() ) {
			return;
		}
		$active = self::active() || self::mu_installed();
		echo '<div class="lb-safe-mode-banner" role="status">';
		echo '<strong>' . esc_html__( 'Safe Mode', 'canvasly-lite' ) . '</strong> ';
		if ( $active && self::mu_installed() ) {
			echo esc_html__( 'Other plugins and the theme are disabled for this editor session.', 'canvasly-lite' );
		} elseif ( ! self::mu_installed() ) {
			echo esc_html__( 'Safe Mode is flagged on your account, but the must-use plugin could not be written. Check writes to wp-content/mu-plugins.', 'canvasly-lite' );
		} else {
			echo esc_html__( 'Safe Mode is on for your account. Reload the editor to isolate it from other plugins and the theme.', 'canvasly-lite' );
		}
		echo ' <a class="button button-small" href="' . esc_url( self::exit_url() ) . '">' . esc_html__( 'Exit Safe Mode', 'canvasly-lite' ) . '</a>';
		echo '</div>';
	}

	public static function tools_screen() {
		if ( ! self::can_manage() ) {
			return;
		}
		echo '<hr><h2>' . esc_html__( 'Safe Mode', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Load the Canvasly editor without other plugins and with a minimal theme, so a conflict cannot take the editor down. Safe Mode is stored per user.', 'canvasly-lite' ) . '</p>';
		if ( self::enabled() ) {
			echo '<p><span class="lb-ops-status lb-ops-on">' . esc_html__( 'On for your account', 'canvasly-lite' ) . '</span></p>';
			if ( ! self::mu_installed() ) {
				echo '<p class="notice notice-warning inline"><span>' . esc_html__( 'The must-use plugin is not installed, so other plugins still load. Allow writes to wp-content/mu-plugins.', 'canvasly-lite' ) . '</span></p>';
			}
			echo '<p><a class="button" href="' . esc_url( self::exit_url() ) . '">' . esc_html__( 'Exit Safe Mode', 'canvasly-lite' ) . '</a></p>';
		} else {
			echo '<p><a class="button" href="' . esc_url( self::enter_url() ) . '">' . esc_html__( 'Enter Safe Mode', 'canvasly-lite' ) . '</a></p>';
		}
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		$ns = $namespace !== '' ? $namespace : 'canvasly-lite/v1';
		register_rest_route(
			$ns,
			'/safe-mode',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'rest_get' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest_set' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
	}

	public static function rest_get() {
		return rest_ensure_response(
			array(
				'enabled'       => self::enabled(),
				'active'        => self::active(),
				'mu_installed'  => self::mu_installed(),
				'plugin'        => self::boot_payload()['plugin'],
			)
		);
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_set( $req ) {
		$d      = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		$on     = ! empty( $d['enabled'] ) || ! empty( $d['safe_mode'] );
		$result = self::set( $on );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::rest_get();
	}

	public static function admin_notice() {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::is_plugin_page() ) {
			return;
		}
		$key  = self::NOTICE . '_' . get_current_user_id();
		$data = function_exists( 'get_transient' ) ? get_transient( $key ) : null;
		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$class = ( $data['type'] ?? 'updated' ) === 'error' ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) $data['message'] ) . '</p></div>';
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
			self::NOTICE . '_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			$ttl
		);
	}
}
