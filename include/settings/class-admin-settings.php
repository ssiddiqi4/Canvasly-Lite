<?php
namespace CanvaslyLite\Settings;

use CanvaslyLite\Design\CssPrint;
use CanvaslyLite\Design\Fonts;
use CanvaslyLite\Design\Kit;
use CanvaslyLite\Design\Optimize;
use CanvaslyLite\Document\Documents;
use CanvaslyLite\Tools\ReplaceUrl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single admin settings API and screens (Roadmap 7.1).
 *
 * General, Integrations, Advanced, Performance, Tools and Features all read/write
 * through sanitize() / save() with a manage_options capability check. Overlapping
 * keys live on `canvasly_lite_global_settings` so existing CSS/fonts/optimize
 * helpers keep working.
 */
class AdminSettings {
	const PAGE       = 'canvasly-lite-settings';
	const NONCE      = 'lb_admin_settings';
	const CAPABILITY = 'manage_options';
	const SAFE_META  = 'canvasly_lite_safe_mode';
	const SECRET_MASK = '********';

	/**
	 * Extra keys stored on the global-settings option (beyond design-system fields).
	 *
	 * @return array<string,mixed>
	 */
	public static function extra_defaults() {
		return array(
			'disable_default_colors'     => false,
			'disable_default_fonts'      => false,
			'google_maps_api_key'        => '',
			'recaptcha_type'             => 'v2',
			'recaptcha_site_key'         => '',
			'recaptcha_secret_key'       => '',
			'editor_loader_mode'         => 'default',
			'maintenance_mode'           => 'off',
			'maintenance_template'       => 0,
			'maintenance_exclude_roles'  => array( 'administrator' ),
			'experiments'                => array(),
			'rollback_keep'              => 3,
		);
	}

	public static function init() {
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		add_action( 'canvasly-lite/frontend/enqueue', array( self::class, 'register_frontend' ) );
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'admin_menu', array( self::class, 'menu' ), 12 );
			add_action( 'admin_init', array( self::class, 'maybe_save' ) );
			add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ), 10, 1 );
			add_filter( 'admin_body_class', array( self::class, 'admin_body_class' ) );
		}
	}

	public static function menu() {
		add_submenu_page(
			'canvasly-lite',
			__( 'Settings', 'canvasly-lite' ),
			__( 'Settings', 'canvasly-lite' ),
			self::CAPABILITY,
			self::PAGE,
			array( self::class, 'screen' )
		);
	}

	public static function can_manage() {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * REST permission_callback.
	 *
	 * @param mixed $request
	 * @return true|\WP_Error
	 */
	public static function rest_can_manage( $request = null ) {
		unset( $request );
		if ( self::can_manage() ) {
			return true;
		}
		return new \WP_Error(
			'forbidden',
			__( 'Only administrators can manage Canvasly settings.', 'canvasly-lite' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * @param string $hook_suffix
	 */
	public static function enqueue( $hook_suffix = '' ) {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) ) {
			if ( ! \CanvaslyLite\Admin\AdminContext::is_plugin_page( $hook_suffix ) ) {
				return;
			}
		} else {
			$settings = is_string( $hook_suffix ) && strpos( $hook_suffix, self::PAGE ) !== false;
			$tools    = is_string( $hook_suffix ) && strpos( $hook_suffix, 'canvasly-lite-tools' ) !== false;
			$roles    = is_string( $hook_suffix ) && strpos( $hook_suffix, 'canvasly-lite-roles' ) !== false;
			$units = is_string( $hook_suffix ) && strpos( $hook_suffix, 'canvasly-lite-units' ) !== false;
			$sysinfo  = is_string( $hook_suffix ) && strpos( $hook_suffix, 'canvasly-lite-system-info' ) !== false;
			$editor   = $hook_suffix === 'toplevel_page_canvasly-lite';
			if ( ! $settings && ! $tools && ! $roles && ! $units && ! $sysinfo && ! $editor ) {
				return;
			}
		}
		$file = CANVASLY_LITE_PATH . 'assets/css/admin-settings.css';
		$ver  = defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0';
		if ( is_readable( $file ) ) {
			$ver .= '-' . (string) filemtime( $file );
		}
		wp_enqueue_style(
			'canvasly-lite-admin-settings',
			CANVASLY_LITE_URL . 'assets/css/admin-settings.css',
			array(),
			$ver
		);
		if ( self::is_editor_iframe() || self::is_editor_iframe_shell() ) {
			wp_enqueue_style(
				'canvasly-lite-editor-iframe',
				CANVASLY_LITE_URL . 'assets/css/editor-iframe.css',
				array(),
				$ver
			);
		}
	}

	/**
	 * @param string $classes
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		if ( self::is_editor_iframe_shell() ) {
			$classes .= ' lb-editor-iframe-shell';
		}
		if ( self::is_editor_iframe() ) {
			$classes .= ' lb-editor-iframe';
		}
		return $classes;
	}

	public static function editor_iframe_head() {
		if ( ! self::is_editor_iframe() && ! self::is_editor_iframe_shell() ) {
			return;
		}
		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		$ver = defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0';
		wp_enqueue_style(
			'canvasly-lite-editor-iframe',
			CANVASLY_LITE_URL . 'assets/css/editor-iframe.css',
			array(),
			$ver
		);
	}

	/**
	 * Outer admin page that hosts the editor iframe (loader mode = iframe).
	 *
	 * @return bool
	 */
	public static function is_editor_iframe_shell() {
		if ( self::editor_loader_mode() !== 'iframe' ) {
			return false;
		}
		if ( ! empty( $_GET['lb_iframe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $page === 'canvasly-lite';
	}

	/**
	 * Inner editor document loaded inside the iframe.
	 *
	 * @return bool
	 */
	public static function is_editor_iframe() {
		if ( empty( $_GET['lb_iframe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $page === 'canvasly-lite';
	}

	/**
	 * @return array<string,string>
	 */
	public static function tabs() {
		return array(
			'general'      => __( 'General', 'canvasly-lite' ),
			'integrations' => __( 'Integrations', 'canvasly-lite' ),
			'advanced'     => __( 'Advanced', 'canvasly-lite' ),
			'performance'  => __( 'Performance', 'canvasly-lite' ),
			'tools'        => __( 'Tools', 'canvasly-lite' ),
			'features'     => __( 'Features', 'canvasly-lite' ),
		);
	}

	/**
	 * @return string
	 */
	public static function current_tab() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = self::tabs();
		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	/**
	 * @param string $tab
	 * @return string
	 */
	public static function url( $tab = '' ) {
		$args = array( 'page' => self::PAGE );
		$tab  = sanitize_key( (string) $tab );
		if ( $tab !== '' && isset( self::tabs()[ $tab ] ) ) {
			$args['tab'] = $tab;
		}
		return admin_url( 'admin.php?' . http_build_query( $args ) );
	}

	/**
	 * Redirect target after a Tools action posted from Settings.
	 *
	 * @return string empty when the request did not come from Settings
	 */
	public static function action_return_url() {
		// phpcs:disable WordPress.Security.NonceVerification -- Return-tab is a display hint; mutating tools verify their own nonce first.
		$tab = '';
		if ( ! empty( $_POST['lb_settings_tab'] ) ) {
			$tab = sanitize_key( wp_unslash( $_POST['lb_settings_tab'] ) );
		} elseif ( ! empty( $_GET['lb_settings_tab'] ) ) {
			$tab = sanitize_key( wp_unslash( $_GET['lb_settings_tab'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification
		if ( $tab !== '' && isset( self::tabs()[ $tab ] ) ) {
			return self::url( $tab );
		}
		return '';
	}

	/**
	 * @param string $fallback_tab
	 * @return string
	 */
	public static function tools_or_settings_url( $fallback_tab = 'tools' ) {
		$url = self::action_return_url();
		if ( $url !== '' ) {
			return $url;
		}
		return admin_url( 'admin.php?page=canvasly-lite-tools' );
	}

	/**
	 * @param mixed $screen
	 * @return bool
	 */
	public static function is_ops_screen( $screen = null ) {
		if ( $screen === null && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}
		$id = is_object( $screen ) ? (string) ( $screen->id ?? '' ) : '';
		return $id === 'canvasly-lite_page_canvasly-lite-tools' || $id === 'canvasly-lite_page_' . self::PAGE;
	}

	/**
	 * Full settings map (secrets in the clear). Used internally.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		$d = self::sanitize( $g, $g, false );
		/**
		 * Filter the admin settings map.
		 *
		 * @param array $d
		 */
		$filtered = apply_filters( 'canvasly-lite/settings', $d );
		return is_array( $filtered ) ? self::sanitize( $filtered, $d, false ) : $d;
	}

	/**
	 * Public REST/export payload (secrets redacted).
	 *
	 * @param array|null $data
	 * @return array<string,mixed>
	 */
	public static function export( $data = null ) {
		$d = is_array( $data ) ? $data : self::get();
		$d['recaptcha_secret_key'] = self::redact( (string) ( $d['recaptcha_secret_key'] ?? '' ) );
		$d['has_recaptcha_secret'] = self::recaptcha_secret_key() !== '';
		$d['experiments']          = class_exists( Experiments::class ) ? Experiments::all( $d['experiments'] ?? array() ) : array();
		$d['safe_mode']            = self::safe_mode();
		return $d;
	}

	/**
	 * @param mixed $raw
	 * @param array $existing
	 * @param bool  $partial  When true, missing keys keep $existing (REST PATCH-style).
	 * @return array<string,mixed>
	 */
	public static function sanitize( $raw, $existing = array(), $partial = false ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$existing = is_array( $existing ) ? $existing : array();
		$base     = array_merge( ( class_exists( GlobalSettings::class ) ? GlobalSettings::defaults() : array() ), self::extra_defaults() );
		$src      = $partial ? array_merge( $base, $existing ) : array_merge( $base, $existing, $raw );
		if ( ! $partial ) {
			$src = array_merge( $src, $raw );
		} else {
			foreach ( $raw as $k => $v ) {
				$src[ $k ] = $v;
			}
		}

		$out = array();

		if ( class_exists( Documents::class ) ) {
			$types = $partial && ! array_key_exists( 'post_types', $raw )
				? ( $existing['post_types'] ?? Documents::defaults() )
				: ( $raw['post_types'] ?? ( $src['post_types'] ?? Documents::defaults() ) );
			$out['post_types'] = Documents::sanitize( $types );
		} else {
			$out['post_types'] = is_array( $src['post_types'] ?? null ) ? array_values( array_unique( array_map( 'sanitize_key', $src['post_types'] ) ) ) : array( 'post', 'page' );
		}

		$out['disable_default_colors'] = self::to_bool( $src['disable_default_colors'] ?? false );
		$out['disable_default_fonts']  = self::to_bool( $src['disable_default_fonts'] ?? false );

		$out['google_maps_api_key'] = self::sanitize_api_key( $src['google_maps_api_key'] ?? '' );
		$type = sanitize_key( (string) ( $src['recaptcha_type'] ?? 'v2' ) );
		$out['recaptcha_type']      = in_array( $type, array( 'v2', 'v3' ), true ) ? $type : 'v2';
		$out['recaptcha_site_key']  = self::sanitize_api_key( $src['recaptcha_site_key'] ?? '' );
		$secret_in                  = array_key_exists( 'recaptcha_secret_key', $raw ) ? $raw['recaptcha_secret_key'] : ( $partial ? null : ( $src['recaptcha_secret_key'] ?? '' ) );
		$existing_secret            = (string) ( $existing['recaptcha_secret_key'] ?? '' );
		if ( $secret_in === null ) {
			$out['recaptcha_secret_key'] = $existing_secret;
		} else {
			$out['recaptcha_secret_key'] = self::sanitize_secret( $secret_in, $existing_secret );
		}

		$method = class_exists( CssPrint::class )
			? CssPrint::sanitize_method( $src['css_print_method'] ?? 'external' )
			: ( ( ( $src['css_print_method'] ?? '' ) === 'inline' ) ? 'inline' : 'external' );
		$out['css_print_method'] = $method;

		$out['font_display'] = class_exists( Fonts::class )
			? Fonts::sanitize_display( $src['font_display'] ?? 'swap' )
			: 'swap';
		$out['google_fonts_local'] = class_exists( Fonts::class )
			? Fonts::sanitize_local( $src['google_fonts_local'] ?? false )
			: self::to_bool( $src['google_fonts_local'] ?? false );

		$loader = sanitize_key( (string) ( $src['editor_loader_mode'] ?? 'default' ) );
		$out['editor_loader_mode'] = in_array( $loader, array( 'default', 'iframe' ), true ) ? $loader : 'default';

		if ( class_exists( Optimize::class ) ) {
			$out['unit_cache']      = Optimize::sanitize_bool( $src['unit_cache'] ?? true );
			$out['unit_cache_ttl']  = Optimize::sanitize_ttl( $src['unit_cache_ttl'] ?? 86400 );
			$out['lazy_load']          = Optimize::sanitize_bool( $src['lazy_load'] ?? true );
			$out['optimized_markup']   = Optimize::sanitize_bool( $src['optimized_markup'] ?? false );
		} else {
			$out['unit_cache']     = ! isset( $src['unit_cache'] ) || self::to_bool( $src['unit_cache'] );
			$out['unit_cache_ttl'] = max( 60, (int) ( $src['unit_cache_ttl'] ?? 86400 ) );
			$out['lazy_load']         = ! isset( $src['lazy_load'] ) || self::to_bool( $src['lazy_load'] );
			$out['optimized_markup']  = self::to_bool( $src['optimized_markup'] ?? false );
		}

		$mode = sanitize_key( (string) ( $src['maintenance_mode'] ?? 'off' ) );
		$out['maintenance_mode']     = in_array( $mode, array( 'off', 'coming_soon', 'maintenance' ), true ) ? $mode : 'off';
		$out['maintenance_template'] = absint( $src['maintenance_template'] ?? 0 );
		$out['maintenance_exclude_roles'] = self::sanitize_roles( $src['maintenance_exclude_roles'] ?? array( 'administrator' ) );

		$keep = absint( $src['rollback_keep'] ?? 3 );
		$out['rollback_keep'] = max( 1, min( 10, $keep ? $keep : 3 ) );

		$out['experiments'] = class_exists( Experiments::class )
			? Experiments::sanitize( $src['experiments'] ?? array() )
			: array();

		if ( isset( $src['content_width'] ) ) {
			$out['content_width'] = sanitize_text_field( (string) $src['content_width'] );
		}
		if ( isset( $src['breakpoints'] ) && is_array( $src['breakpoints'] ) ) {
			$out['breakpoints'] = $src['breakpoints'];
		}
		if ( isset( $src['colors'] ) && is_array( $src['colors'] ) ) {
			$out['colors'] = $src['colors'];
		}
		if ( isset( $src['fonts'] ) && is_array( $src['fonts'] ) ) {
			$out['fonts'] = $src['fonts'];
		}

		/**
		 * Filter sanitized admin settings.
		 *
		 * @param array $out
		 * @param array $raw
		 */
		$filtered = apply_filters( 'canvasly-lite/settings/sanitize', $out, $raw );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * Fill extra keys on a GlobalSettings::get() map without recursion.
	 *
	 * @param array $d
	 * @return array
	 */
	public static function normalize_stored( $d ) {
		$d    = is_array( $d ) ? $d : array();
		$extra = self::extra_defaults();
		foreach ( $extra as $k => $v ) {
			if ( ! array_key_exists( $k, $d ) ) {
				$d[ $k ] = $v;
			}
		}
		$d['disable_default_colors'] = self::to_bool( $d['disable_default_colors'] ?? false );
		$d['disable_default_fonts']  = self::to_bool( $d['disable_default_fonts'] ?? false );
		$d['google_maps_api_key']    = self::sanitize_api_key( $d['google_maps_api_key'] ?? '' );
		$type = sanitize_key( (string) ( $d['recaptcha_type'] ?? 'v2' ) );
		$d['recaptcha_type']         = in_array( $type, array( 'v2', 'v3' ), true ) ? $type : 'v2';
		$d['recaptcha_site_key']     = self::sanitize_api_key( $d['recaptcha_site_key'] ?? '' );
		$d['recaptcha_secret_key']   = self::sanitize_api_key( $d['recaptcha_secret_key'] ?? '' );
		$loader = sanitize_key( (string) ( $d['editor_loader_mode'] ?? 'default' ) );
		$d['editor_loader_mode']     = in_array( $loader, array( 'default', 'iframe' ), true ) ? $loader : 'default';
		$mode = sanitize_key( (string) ( $d['maintenance_mode'] ?? 'off' ) );
		$d['maintenance_mode']       = in_array( $mode, array( 'off', 'coming_soon', 'maintenance' ), true ) ? $mode : 'off';
		$d['maintenance_template']   = absint( $d['maintenance_template'] ?? 0 );
		$d['maintenance_exclude_roles'] = self::sanitize_roles( $d['maintenance_exclude_roles'] ?? array( 'administrator' ) );
		$keep = absint( $d['rollback_keep'] ?? 3 );
		$d['rollback_keep']          = max( 1, min( 10, $keep ? $keep : 3 ) );
		$d['experiments']            = class_exists( Experiments::class ) ? Experiments::sanitize( $d['experiments'] ?? array() ) : array();
		return $d;
	}

	/**
	 * Persist a (possibly partial) settings payload.
	 *
	 * @param array $raw
	 * @param bool  $partial
	 * @return array|\WP_Error
	 */
	public static function save( $raw, $partial = true ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error(
				'forbidden',
				__( 'Only administrators can manage Canvasly settings.', 'canvasly-lite' ),
				array( 'status' => 403 )
			);
		}
		$existing = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		$clean    = self::sanitize( is_array( $raw ) ? $raw : array(), $existing, $partial );

		if ( class_exists( GlobalSettings::class ) && array_key_exists( 'post_types', $clean ) ) {
			$existing = GlobalSettings::save_post_types( $clean['post_types'] );
		}
		if ( class_exists( CssPrint::class ) && array_key_exists( 'css_print_method', $clean ) ) {
			CssPrint::save_method( $clean['css_print_method'] );
		}
		if ( class_exists( Fonts::class ) ) {
			Fonts::save_from( $clean );
		}
		if ( class_exists( Optimize::class ) ) {
			Optimize::save_from( $clean );
		}

		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		foreach ( array_keys( self::extra_defaults() ) as $k ) {
			if ( array_key_exists( $k, $clean ) ) {
				$g[ $k ] = $clean[ $k ];
			}
		}
		if ( class_exists( GlobalSettings::class ) ) {
			update_option( GlobalSettings::KEY, $g, false );
			if ( ! empty( $clean['disable_default_colors'] ) !== ! empty( $existing['disable_default_colors'] )
				|| ! empty( $clean['disable_default_fonts'] ) !== ! empty( $existing['disable_default_fonts'] ) ) {
				GlobalSettings::invalidate_css_cache();
			}
		}

		if ( array_key_exists( 'safe_mode', is_array( $raw ) ? $raw : array() ) ) {
			self::save_safe_mode( self::to_bool( $raw['safe_mode'] ) );
		}

		$saved = self::get();
		/**
		 * Fires after admin settings are saved.
		 *
		 * @param array $saved
		 * @param array $raw
		 */
		do_action( 'canvasly-lite/settings/after_save', $saved, is_array( $raw ) ? $raw : array() );
		return $saved;
	}

	public static function maybe_save() {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::is_plugin_page() ) {
			return;
		}
		if ( empty( $_POST['lb_save_settings'] ) ) {
			return;
		}
		if ( ! self::can_manage() ) {
			return;
		}
		check_admin_referer( self::NONCE );
		$tab  = isset( $_POST['lb_settings_tab'] ) ? sanitize_key( wp_unslash( $_POST['lb_settings_tab'] ) ) : self::current_tab();
		$raw  = self::from_post( $tab );
		$save = self::save( $raw, true );
		if ( is_wp_error( $save ) ) {
			add_settings_error( 'canvasly_lite_settings', 'forbidden', $save->get_error_message(), 'error' );
		} else {
			add_settings_error( 'canvasly_lite_settings', 'saved', __( 'Settings saved.', 'canvasly-lite' ), 'updated' );
		}
	}

	/**
	 * Build a partial payload from the posted tab.
	 *
	 * @param string $tab
	 * @return array
	 */
	public static function from_post( $tab ) {
		$tab = sanitize_key( (string) $tab );
		$p   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$out = array();
		if ( $tab === 'general' ) {
			$out['post_types']             = isset( $p['post_types'] ) && is_array( $p['post_types'] ) ? $p['post_types'] : array();
			$out['disable_default_colors'] = ! empty( $p['disable_default_colors'] );
			$out['disable_default_fonts']  = ! empty( $p['disable_default_fonts'] );
		} elseif ( $tab === 'integrations' ) {
			$out['google_maps_api_key'] = $p['google_maps_api_key'] ?? '';
			$out['recaptcha_type']      = $p['recaptcha_type'] ?? 'v2';
			$out['recaptcha_site_key']  = $p['recaptcha_site_key'] ?? '';
			if ( array_key_exists( 'recaptcha_secret_key', $p ) ) {
				$out['recaptcha_secret_key'] = $p['recaptcha_secret_key'];
			}
		} elseif ( $tab === 'advanced' ) {
			$out['css_print_method']   = $p['css_print_method'] ?? 'external';
			$out['font_display']       = $p['font_display'] ?? 'swap';
			$out['google_fonts_local'] = ! empty( $p['google_fonts_local'] );
			$out['editor_loader_mode'] = $p['editor_loader_mode'] ?? 'default';
		} elseif ( $tab === 'performance' ) {
			$out['unit_cache']     = ! empty( $p['unit_cache'] );
			$hours                    = max( 1, min( 168, absint( $p['unit_cache_ttl'] ?? 24 ) ) );
			$hour                     = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
			$out['unit_cache_ttl'] = $hours * $hour;
			$out['lazy_load']         = ! empty( $p['lazy_load'] );
			$out['optimized_markup']  = ! empty( $p['optimized_markup'] );
		} elseif ( $tab === 'tools' ) {
			$out['maintenance_mode']          = $p['maintenance_mode'] ?? 'off';
			$out['maintenance_template']      = $p['maintenance_template'] ?? 0;
			$out['maintenance_exclude_roles'] = isset( $p['maintenance_exclude_roles'] ) && is_array( $p['maintenance_exclude_roles'] ) ? $p['maintenance_exclude_roles'] : array();
			$out['rollback_keep']             = $p['rollback_keep'] ?? 3;
			$out['safe_mode']                 = ! empty( $p['safe_mode'] );
		} elseif ( $tab === 'features' ) {
			$out['experiments'] = isset( $p['experiments'] ) && is_array( $p['experiments'] ) ? $p['experiments'] : array();
		}
		return $out;
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		$ns = $namespace !== '' ? $namespace : 'canvasly-lite/v1';
		register_rest_route(
			$ns,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'rest_get' ),
					'permission_callback' => array( self::class, 'rest_can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest_save' ),
					'permission_callback' => array( self::class, 'rest_can_manage' ),
				),
			)
		);
	}

	public static function rest_get() {
		return rest_ensure_response(
			array(
				'settings'    => self::export(),
				'experiments' => class_exists( Experiments::class ) ? Experiments::all() : array(),
				'tabs'        => self::tabs(),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_save( $req ) {
		$d    = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		$save = self::save( $d, true );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		return rest_ensure_response(
			array(
				'settings'    => self::export( $save ),
				'experiments' => class_exists( Experiments::class ) ? Experiments::all( $save['experiments'] ?? array() ) : array(),
			)
		);
	}

	public static function register_frontend() {
		if ( ! self::recaptcha_enabled() || ! function_exists( 'wp_register_script' ) ) {
			return;
		}
		$type = self::recaptcha_type();
		$key  = self::recaptcha_site_key();
		$url  = 'https://www.google.com/recaptcha/api.js';
		if ( $type === 'v3' && $key !== '' ) {
			$url = add_query_arg( 'render', $key, $url );
		} else {
			$url = add_query_arg( 'render', 'explicit', $url );
		}
		$version = defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '1.0.0';
		wp_register_script( 'google-recaptcha', $url, array(), $version, true );
	}

	public static function disable_default_colors() {
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		return ! empty( $g['disable_default_colors'] );
	}

	public static function disable_default_fonts() {
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		return ! empty( $g['disable_default_fonts'] );
	}

	public static function google_maps_api_key() {
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		return self::sanitize_api_key( $g['google_maps_api_key'] ?? '' );
	}

	public static function recaptcha_type() {
		$g    = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		$type = sanitize_key( (string) ( $g['recaptcha_type'] ?? 'v2' ) );
		return $type === 'v3' ? 'v3' : 'v2';
	}

	public static function recaptcha_site_key() {
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		return self::sanitize_api_key( $g['recaptcha_site_key'] ?? '' );
	}

	public static function recaptcha_secret_key() {
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		return self::sanitize_api_key( $g['recaptcha_secret_key'] ?? '' );
	}

	public static function recaptcha_enabled() {
		if ( class_exists( Experiments::class ) && ! Experiments::is_active( 'form_recaptcha' ) ) {
			return false;
		}
		return self::recaptcha_site_key() !== '' && self::recaptcha_secret_key() !== '';
	}

	public static function maps_embed_enabled() {
		if ( class_exists( Experiments::class ) && ! Experiments::is_active( 'google_maps_embed' ) ) {
			return false;
		}
		return self::google_maps_api_key() !== '';
	}

	public static function editor_loader_mode() {
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		$m = sanitize_key( (string) ( $g['editor_loader_mode'] ?? 'default' ) );
		return $m === 'iframe' ? 'iframe' : 'default';
	}

	/**
	 * @return array{mode:string,template:int,exclude_roles:string[]}
	 */
	public static function maintenance() {
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		$m = sanitize_key( (string) ( $g['maintenance_mode'] ?? 'off' ) );
		if ( ! in_array( $m, array( 'off', 'coming_soon', 'maintenance' ), true ) ) {
			$m = 'off';
		}
		return array(
			'mode'          => $m,
			'template'      => absint( $g['maintenance_template'] ?? 0 ),
			'exclude_roles' => self::sanitize_roles( $g['maintenance_exclude_roles'] ?? array( 'administrator' ) ),
		);
	}

	/**
	 * @param int $user_id
	 * @return bool
	 */
	public static function safe_mode( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 );
		if ( ! $user_id || ! function_exists( 'get_user_meta' ) ) {
			return false;
		}
		return (string) get_user_meta( $user_id, self::SAFE_META, true ) === '1';
	}

	/**
	 * @param bool $on
	 * @param int  $user_id
	 */
	public static function save_safe_mode( $on, $user_id = 0 ) {
		if ( class_exists( '\\CanvaslyLite\\Ops\\SafeMode' ) ) {
			\CanvaslyLite\Ops\SafeMode::set( (bool) $on, $user_id );
			return;
		}
		$user_id = $user_id ? absint( $user_id ) : ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 );
		if ( ! $user_id || ! function_exists( 'update_user_meta' ) ) {
			return;
		}
		if ( $on ) {
			update_user_meta( $user_id, self::SAFE_META, '1' );
		} else {
			delete_user_meta( $user_id, self::SAFE_META );
		}
	}

	/**
	 * @param \WP_REST_Request|array $req
	 * @return true|\WP_Error
	 */
	public static function verify_form_request( $req ) {
		if ( ! self::recaptcha_enabled() ) {
			return true;
		}
		$params = array();
		if ( is_object( $req ) && method_exists( $req, 'get_params' ) ) {
			$params = (array) $req->get_params();
		} elseif ( is_array( $req ) ) {
			$params = $req;
		}
		$token = (string) ( $params['g-recaptcha-response'] ?? $params['recaptcha_token'] ?? '' );
		if ( ! self::verify_recaptcha( $token ) ) {
			return new \WP_Error(
				'recaptcha',
				__( 'reCAPTCHA verification failed. Please try again.', 'canvasly-lite' ),
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * @param string $token
	 * @return bool
	 */
	public static function verify_recaptcha( $token ) {
		$secret = self::recaptcha_secret_key();
		if ( $secret === '' ) {
			return true;
		}
		$token = trim( (string) $token );
		if ( $token === '' ) {
			return false;
		}
		$pre = apply_filters( 'canvasly-lite/recaptcha/verify', null, $token, $secret );
		if ( $pre !== null ) {
			return (bool) $pre;
		}
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return false;
		}
		$res = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return $code === 200 && is_array( $body ) && ! empty( $body['success'] );
	}

	public static function screen() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can manage Canvasly settings.', 'canvasly-lite' ) );
		}
		$tab = self::current_tab();
		$d   = self::get();
		echo '<div class="wrap lb-settings-wrap">';
		echo '<h1>' . esc_html__( 'Canvasly Settings', 'canvasly-lite' ) . '</h1>';
		settings_errors( 'canvasly_lite_settings' );
		echo '<nav class="nav-tab-wrapper lb-settings-tabs">';
		foreach ( self::tabs() as $id => $label ) {
			echo '<a class="nav-tab' . ( $tab === $id ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( $id ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( $tab === 'tools' ) {
			self::render_tools( $d );
		} else {
			echo '<form method="post" action="' . esc_url( self::url( $tab ) ) . '">';
			wp_nonce_field( self::NONCE );
			echo '<input type="hidden" name="lb_settings_tab" value="' . esc_attr( $tab ) . '">';
			if ( $tab === 'general' ) {
				self::render_general( $d );
			} elseif ( $tab === 'integrations' ) {
				self::render_integrations( $d );
			} elseif ( $tab === 'advanced' ) {
				self::render_advanced( $d );
			} elseif ( $tab === 'performance' ) {
				self::render_performance( $d );
			} elseif ( $tab === 'features' ) {
				self::render_features( $d );
			}
			echo '<p class="submit"><button type="submit" class="button button-primary" name="lb_save_settings" value="1">' . esc_html__( 'Save Changes', 'canvasly-lite' ) . '</button></p>';
			echo '</form>';
			if ( $tab === 'performance' ) {
				self::render_performance_actions();
			}
			if ( $tab === 'advanced' ) {
				self::render_advanced_actions();
			}
		}
		echo '</div>';
	}

	/**
	 * @param array $d
	 */
	public static function render_general( $d ) {
		echo '<p class="description">' . esc_html__( 'Choose which public post types Canvasly can edit, and whether the theme should keep its own default colors and fonts.', 'canvasly-lite' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Post Types', 'canvasly-lite' ) . '</th><td>';
		$enabled   = class_exists( Documents::class ) ? Documents::enabled() : (array) ( $d['post_types'] ?? array( 'post', 'page' ) );
		$available = class_exists( Documents::class ) ? Documents::available() : array( 'post' => __( 'Posts', 'canvasly-lite' ), 'page' => __( 'Pages', 'canvasly-lite' ) );
		echo '<fieldset>';
		foreach ( $available as $slug => $label ) {
			echo '<label class="lb-settings-check"><input type="checkbox" name="post_types[]" value="' . esc_attr( $slug ) . '"' . ( in_array( $slug, $enabled, true ) ? ' checked' : '' ) . '> ' . esc_html( $label ) . ' <code>' . esc_html( $slug ) . '</code></label>';
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Posts and Pages are enabled by default. Any public custom post type can be added.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Default Colors', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="disable_default_colors" value="1"' . ( ! empty( $d['disable_default_colors'] ) ? ' checked' : '' ) . '> ' . esc_html__( 'Disable default colors', 'canvasly-lite' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Let the theme control colors. Canvasly global color tokens and Theme Style colors will not be printed.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Default Fonts', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="disable_default_fonts" value="1"' . ( ! empty( $d['disable_default_fonts'] ) ? ' checked' : '' ) . '> ' . esc_html__( 'Disable default fonts', 'canvasly-lite' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Let the theme control typography. Global heading/body fonts and Theme Style font-family rules will not be printed.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr></tbody></table>';
	}

	/**
	 * @param array $d
	 */
	public static function render_integrations( $d ) {
		echo '<p class="description">' . esc_html__( 'API keys used by Google Maps and Form reCAPTCHA. Secret keys are never sent to the browser.', 'canvasly-lite' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="lb-maps-key">' . esc_html__( 'Google Maps API key', 'canvasly-lite' ) . '</label></th><td>';
		echo '<input class="regular-text code" id="lb-maps-key" name="google_maps_api_key" type="text" value="' . esc_attr( (string) ( $d['google_maps_api_key'] ?? '' ) ) . '" autocomplete="off">';
		echo '<p class="description">' . esc_html__( 'Used by the Google Maps widget with the Maps Embed API when the matching Features toggle is active. Leave empty to use the public iframe embed.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'reCAPTCHA type', 'canvasly-lite' ) . '</th><td>';
		$type = ( $d['recaptcha_type'] ?? 'v2' ) === 'v3' ? 'v3' : 'v2';
		echo '<label><input type="radio" name="recaptcha_type" value="v2"' . ( $type === 'v2' ? ' checked' : '' ) . '> ' . esc_html__( 'v2 checkbox', 'canvasly-lite' ) . '</label><br>';
		echo '<label><input type="radio" name="recaptcha_type" value="v3"' . ( $type === 'v3' ? ' checked' : '' ) . '> ' . esc_html__( 'v3 invisible', 'canvasly-lite' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th><label for="lb-recaptcha-site">' . esc_html__( 'reCAPTCHA site key', 'canvasly-lite' ) . '</label></th><td>';
		echo '<input class="regular-text code" id="lb-recaptcha-site" name="recaptcha_site_key" type="text" value="' . esc_attr( (string) ( $d['recaptcha_site_key'] ?? '' ) ) . '" autocomplete="off">';
		echo '</td></tr>';
		echo '<tr><th><label for="lb-recaptcha-secret">' . esc_html__( 'reCAPTCHA secret key', 'canvasly-lite' ) . '</label></th><td>';
		$secret = (string) ( $d['recaptcha_secret_key'] ?? '' );
		echo '<input class="regular-text code" id="lb-recaptcha-secret" name="recaptcha_secret_key" type="password" value="' . esc_attr( $secret !== '' ? self::SECRET_MASK : '' ) . '" autocomplete="new-password">';
		echo '<p class="description">' . esc_html__( 'Leave the masked value unchanged to keep the stored secret. Enable Form reCAPTCHA under Features, then set both keys.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr></tbody></table>';
	}

	/**
	 * @param array $d
	 */
	public static function render_advanced( $d ) {
		$method  = ( $d['css_print_method'] ?? 'external' ) === 'inline' ? 'inline' : 'external';
		$display = (string) ( $d['font_display'] ?? 'swap' );
		$labels  = array(
			'auto'     => __( 'Auto', 'canvasly-lite' ),
			'block'    => __( 'Block', 'canvasly-lite' ),
			'swap'     => __( 'Swap (recommended)', 'canvasly-lite' ),
			'fallback' => __( 'Fallback', 'canvasly-lite' ),
			'optional' => __( 'Optional', 'canvasly-lite' ),
		);
		$loader  = ( $d['editor_loader_mode'] ?? 'default' ) === 'iframe' ? 'iframe' : 'default';
		echo '<p class="description">' . esc_html__( 'How Canvasly delivers CSS and fonts, and how the editor is loaded in wp-admin.', 'canvasly-lite' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'CSS print method', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="radio" name="css_print_method" value="external"' . ( $method === 'external' ? ' checked' : '' ) . '> ' . esc_html__( 'External files', 'canvasly-lite' ) . '</label><br>';
		echo '<label><input type="radio" name="css_print_method" value="inline"' . ( $method === 'inline' ? ' checked' : '' ) . '> ' . esc_html__( 'Internal embedding', 'canvasly-lite' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'External files are written to uploads/canvasly-lite/css with hash-based cache busting. Inline embeds the same minified CSS.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th><label for="lb-font-display">' . esc_html__( 'font-display', 'canvasly-lite' ) . '</label></th><td>';
		echo '<select id="lb-font-display" name="font_display">';
		foreach ( $labels as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . ( $display === $k ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'How text renders while a webfont loads. Swap is recommended.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Self-host Google Fonts', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="google_fonts_local" value="1"' . ( ! empty( $d['google_fonts_local'] ) ? ' checked' : '' ) . '> ' . esc_html__( 'Download used Google Fonts to this site and serve them locally.', 'canvasly-lite' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Editor loader', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="radio" name="editor_loader_mode" value="default"' . ( $loader === 'default' ? ' checked' : '' ) . '> ' . esc_html__( 'Default', 'canvasly-lite' ) . '</label><br>';
		echo '<label><input type="radio" name="editor_loader_mode" value="iframe"' . ( $loader === 'iframe' ? ' checked' : '' ) . '> ' . esc_html__( 'Iframe', 'canvasly-lite' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Iframe mode loads the editor in an isolated frame so other admin CSS and scripts are less likely to conflict. Use this if the editor fails to open.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr></tbody></table>';
	}

	public static function render_advanced_actions() {
		if ( ! class_exists( Fonts::class ) || ! Fonts::can_manage() ) {
			return;
		}
		echo '<hr><h2>' . esc_html__( 'Download Google Fonts', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Fetch the font files currently used on this site into uploads/canvasly-lite/fonts. Enables self-hosting if it is not already on.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_fonts_download' );
		echo '<input type="hidden" name="action" value="lb_fonts_download">';
		self::echo_return_tab( 'advanced' );
		echo '<p><button class="button" type="submit">' . esc_html__( 'Download Google Fonts', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * @param array $d
	 */
	public static function render_performance( $d ) {
		$cache = ! isset( $d['unit_cache'] ) || ! empty( $d['unit_cache'] );
		$ttl   = absint( $d['unit_cache_ttl'] ?? 86400 );
		$hours = max( 1, (int) round( $ttl / ( defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600 ) ) );
		$lazy  = ! isset( $d['lazy_load'] ) || ! empty( $d['lazy_load'] );
		$mark  = ! empty( $d['optimized_markup'] );
		echo '<p class="description">' . esc_html__( 'Fragment cache stores rendered HTML for units that do not use dynamic tags. Lazy load defers background images below the first one and sets fetchpriority on the first image. Optimized markup removes the extra node wrapper when the widget already has a single root.', 'canvasly-lite' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Unit cache', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="unit_cache" value="1"' . ( $cache ? ' checked' : '' ) . '> ' . esc_html__( 'Cache HTML fragments for non-dynamic units.', 'canvasly-lite' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th><label for="lb-cache-ttl">' . esc_html__( 'Cache TTL (hours)', 'canvasly-lite' ) . '</label></th><td>';
		echo '<input id="lb-cache-ttl" type="number" min="1" max="168" name="unit_cache_ttl" value="' . esc_attr( (string) $hours ) . '">';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Lazy load', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="lazy_load" value="1"' . ( $lazy ? ' checked' : '' ) . '> ' . esc_html__( 'Lazy-load background images below the first one, fetchpriority=high on the first image, loading=lazy after.', 'canvasly-lite' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Optimized markup', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="optimized_markup" value="1"' . ( $mark ? ' checked' : '' ) . '> ' . esc_html__( 'Remove the extra node wrapper on simple widgets when safe.', 'canvasly-lite' ) . '</label>';
		echo '</td></tr></tbody></table>';
	}

	public static function render_performance_actions() {
		if ( ! class_exists( Optimize::class ) || ! Optimize::can_manage() ) {
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_optimize_flush' );
		echo '<input type="hidden" name="action" value="lb_optimize_flush">';
		self::echo_return_tab( 'performance' );
		echo '<p><button class="button" type="submit">' . esc_html__( 'Flush unit cache', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * @param array $d
	 */
	public static function render_tools( $d ) {
		echo '<p class="description">' . esc_html__( 'Operational tools. Regenerating CSS, replacing URLs and kit import/export use the same handlers as Canvasly → Tools.', 'canvasly-lite' ) . '</p>';

		if ( class_exists( CssPrint::class ) && CssPrint::can_manage() ) {
			$report = function_exists( 'get_transient' ) ? get_transient( 'canvasly_lite_css_report_' . get_current_user_id() ) : null;
			echo '<h2>' . esc_html__( 'Regenerate CSS', 'canvasly-lite' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'Rebuild the global stylesheet and every document CSS file. Use this after a migration, a breakpoint change, or if styles look stale.', 'canvasly-lite' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'lb_regenerate_css' );
			echo '<input type="hidden" name="action" value="lb_regenerate_css">';
			self::echo_return_tab( 'tools' );
			echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Regenerate CSS', 'canvasly-lite' ) . '</button></p>';
			echo '</form>';
			if ( is_array( $report ) ) {
				echo '<p><strong>' . esc_html__( 'Last regeneration', 'canvasly-lite' ) . '</strong> ';
				echo esc_html(
					sprintf(
						/* translators: 1: files written, 2: documents, 3: failures */
						__( '%1$d files written, %2$d documents, %3$d failed.', 'canvasly-lite' ),
						(int) ( $report['written'] ?? 0 ),
						(int) ( $report['posts'] ?? 0 ),
						(int) ( $report['failed'] ?? 0 )
					)
				);
				echo '</p>';
			}
		}

		if ( class_exists( ReplaceUrl::class ) && ReplaceUrl::can_manage() ) {
			echo '<hr>';
			$report = function_exists( 'get_transient' ) ? get_transient( ReplaceUrl::REPORT . '_' . get_current_user_id() ) : null;
			echo '<h2>' . esc_html__( 'Replace URL', 'canvasly-lite' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'Rewrite a site URL inside Canvasly documents and compiled CSS. The change cannot be undone — run a dry run first.', 'canvasly-lite' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'lb_replace_url' );
			echo '<input type="hidden" name="action" value="lb_replace_url">';
			self::echo_return_tab( 'tools' );
			echo '<table class="form-table"><tbody>';
			echo '<tr><th><label for="lb-replace-from">' . esc_html__( 'Old URL', 'canvasly-lite' ) . '</label></th><td>';
			echo '<input class="regular-text code" id="lb-replace-from" name="from" type="url" required placeholder="https://staging.example.com">';
			echo '</td></tr>';
			echo '<tr><th><label for="lb-replace-to">' . esc_html__( 'New URL', 'canvasly-lite' ) . '</label></th><td>';
			echo '<input class="regular-text code" id="lb-replace-to" name="to" type="url" required placeholder="https://www.example.com">';
			echo '</td></tr></tbody></table>';
			echo '<p><button class="button" type="submit" name="mode" value="preview">' . esc_html__( 'Dry run', 'canvasly-lite' ) . '</button> ';
			echo '<button class="button button-primary" type="submit" name="mode" value="run">' . esc_html__( 'Replace URL', 'canvasly-lite' ) . '</button></p>';
			echo '</form>';
			if ( is_array( $report ) && method_exists( ReplaceUrl::class, 'render_report' ) ) {
				ReplaceUrl::render_report( $report );
			}
		}

		if ( class_exists( Kit::class ) && Kit::can_manage() ) {
			echo '<hr>';
			Kit::render_forms();
		}

		echo '<hr><h2>' . esc_html__( 'Maintenance mode', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Show a coming-soon or maintenance document to visitors. Roles listed below still see the live site. Maintenance uses HTTP 503; coming soon stays 200.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( self::url( 'tools' ) ) . '">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="lb_settings_tab" value="tools">';
		$mode = (string) ( $d['maintenance_mode'] ?? 'off' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Mode', 'canvasly-lite' ) . '</th><td>';
		foreach ( array( 'off' => __( 'Disabled', 'canvasly-lite' ), 'coming_soon' => __( 'Coming soon', 'canvasly-lite' ), 'maintenance' => __( 'Maintenance', 'canvasly-lite' ) ) as $k => $label ) {
			echo '<label class="lb-settings-check"><input type="radio" name="maintenance_mode" value="' . esc_attr( $k ) . '"' . ( $mode === $k ? ' checked' : '' ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</td></tr>';
		echo '<tr><th><label for="lb-maint-tpl">' . esc_html__( 'Template', 'canvasly-lite' ) . '</label></th><td>';
		echo '<select id="lb-maint-tpl" name="maintenance_template">';
		echo '<option value="0">' . esc_html__( '— None —', 'canvasly-lite' ) . '</option>';
		foreach ( self::template_choices() as $id => $title ) {
			echo '<option value="' . esc_attr( (string) $id ) . '"' . ( absint( $d['maintenance_template'] ?? 0 ) === (int) $id ? ' selected' : '' ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'A saved Canvasly template shown to visitors while the mode is on. Leave empty for a built-in fallback page.', 'canvasly-lite' ) . '</p>';
		if ( class_exists( '\\CanvaslyLite\\Ops\\Maintenance' ) ) {
			echo '<p><a class="button" href="' . esc_url( \CanvaslyLite\Ops\Maintenance::preview_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Preview', 'canvasly-lite' ) . '</a></p>';
		}
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Exclude roles', 'canvasly-lite' ) . '</th><td><fieldset>';
		$selected = is_array( $d['maintenance_exclude_roles'] ?? null ) ? $d['maintenance_exclude_roles'] : array( 'administrator' );
		foreach ( self::role_choices() as $slug => $label ) {
			echo '<label class="lb-settings-check"><input type="checkbox" name="maintenance_exclude_roles[]" value="' . esc_attr( $slug ) . '"' . ( in_array( $slug, $selected, true ) ? ' checked' : '' ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset></td></tr>';
		echo '<tr><th>' . esc_html__( 'Safe mode', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="safe_mode" value="1"' . ( self::safe_mode() ? ' checked' : '' ) . '> ' . esc_html__( 'Load the editor in Safe Mode for my account', 'canvasly-lite' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Safe Mode is stored per user. It loads the editor without other plugins and the theme, so you can recover a broken session.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th><label for="lb-rollback-keep">' . esc_html__( 'Rollback versions to keep', 'canvasly-lite' ) . '</label></th><td>';
		echo '<input id="lb-rollback-keep" type="number" min="1" max="10" name="rollback_keep" value="' . esc_attr( (string) ( $d['rollback_keep'] ?? 3 ) ) . '">';
		echo '<p class="description">' . esc_html__( 'How many previous plugin ZIPs to retain under uploads/canvasly-lite/rollback.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr></tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary" name="lb_save_settings" value="1">' . esc_html__( 'Save tool settings', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';

		if ( class_exists( '\\CanvaslyLite\\Ops\\Rollback' ) ) {
			\CanvaslyLite\Ops\Rollback::tools_screen();
		}
		if ( class_exists( '\\CanvaslyLite\\Ops\\SystemInfo' ) ) {
			\CanvaslyLite\Ops\SystemInfo::tools_screen();
		}
		if ( class_exists( '\\CanvaslyLite\\Upgrade\\Upgrades' ) ) {
			\CanvaslyLite\Upgrade\Upgrades::tools_screen();
		}
	}

	/**
	 * @param array $d
	 */
	public static function render_features( $d ) {
		$rows = class_exists( Experiments::class ) ? Experiments::all( $d['experiments'] ?? array() ) : array();
		echo '<p class="description">' . esc_html__( 'Experiments ship with an alpha, beta or stable flag. Stable features default on; alpha and beta default off until you activate them.', 'canvasly-lite' ) . '</p>';
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No experiments are registered.', 'canvasly-lite' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped lb-experiments"><thead><tr>';
		echo '<th>' . esc_html__( 'Feature', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'State', 'canvasly-lite' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$id     = $row['id'];
			$status = $row['status'];
			$state  = $row['state'];
			echo '<tr>';
			echo '<td><strong>' . esc_html( $row['title'] ) . '</strong>';
			if ( $row['description'] !== '' ) {
				echo '<p class="description">' . esc_html( $row['description'] ) . '</p>';
			}
			echo '</td>';
			echo '<td><span class="lb-exp-badge lb-exp-' . esc_attr( $status ) . '">' . esc_html( Experiments::status_label( $status ) ) . '</span></td>';
			echo '<td><select name="experiments[' . esc_attr( $id ) . ']">';
			echo '<option value="active"' . ( $state === 'active' ? ' selected' : '' ) . '>' . esc_html__( 'Active', 'canvasly-lite' ) . '</option>';
			echo '<option value="inactive"' . ( $state === 'inactive' ? ' selected' : '' ) . '>' . esc_html__( 'Inactive', 'canvasly-lite' ) . '</option>';
			echo '</select></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param string $tab
	 */
	public static function echo_return_tab( $tab ) {
		$tab = sanitize_key( (string) $tab );
		if ( $tab === '' ) {
			return;
		}
		echo '<input type="hidden" name="lb_settings_tab" value="' . esc_attr( $tab ) . '">';
	}

	/**
	 * @return array<int,string>
	 */
	public static function template_choices() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => 'lb_template',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 80,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( (array) $posts as $p ) {
			$id = is_object( $p ) ? (int) ( $p->ID ?? 0 ) : 0;
			if ( ! $id ) {
				continue;
			}
			$title = is_object( $p ) ? (string) ( $p->post_title ?? '' ) : '';
			$out[ $id ] = $title !== '' ? $title : ( '#' . $id );
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	public static function role_choices() {
		if ( function_exists( 'wp_roles' ) ) {
			$roles = wp_roles();
			$names = is_object( $roles ) ? (array) ( $roles->role_names ?? array() ) : array();
			if ( $names ) {
				$out = array();
				foreach ( $names as $slug => $label ) {
					$out[ sanitize_key( $slug ) ] = sanitize_text_field( (string) $label );
				}
				return $out;
			}
		}
		return array(
			'administrator' => __( 'Administrator', 'canvasly-lite' ),
			'editor'        => __( 'Editor', 'canvasly-lite' ),
			'author'        => __( 'Author', 'canvasly-lite' ),
			'contributor'   => __( 'Contributor', 'canvasly-lite' ),
			'subscriber'    => __( 'Subscriber', 'canvasly-lite' ),
		);
	}

	/**
	 * @return array<int,array{name:string,size:int}>
	 */
	public static function rollback_versions() {
		if ( class_exists( '\\CanvaslyLite\\Ops\\Rollback' ) ) {
			$list = array();
			foreach ( \CanvaslyLite\Ops\Rollback::versions() as $row ) {
				$list[] = array(
					'name' => (string) ( $row['name'] ?? '' ),
					'size' => (int) ( $row['size'] ?? 0 ),
				);
			}
			return $list;
		}
		$dir = self::rollback_dir();
		if ( $dir === '' || ! is_dir( $dir ) ) {
			return array();
		}
		$files = glob( trailingslashit( $dir ) . '*.zip' );
		if ( ! is_array( $files ) ) {
			return array();
		}
		$out = array();
		foreach ( $files as $file ) {
			$out[] = array(
				'name' => basename( $file ),
				'size' => (int) filesize( $file ),
			);
		}
		return $out;
	}

	/**
	 * @return string
	 */
	public static function rollback_dir() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}
		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) || empty( $up['basedir'] ) ) {
			return '';
		}
		return trailingslashit( $up['basedir'] ) . 'canvasly-lite/rollback';
	}

	/**
	 * @param mixed $raw
	 * @return string[]
	 */
	public static function sanitize_roles( $raw ) {
		$allowed = array_keys( self::role_choices() );
		$raw     = is_array( $raw ) ? $raw : array();
		$out     = array();
		foreach ( $raw as $role ) {
			$role = sanitize_key( (string) $role );
			if ( $role !== '' && in_array( $role, $allowed, true ) ) {
				$out[] = $role;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $raw
	 * @return string
	 */
	public static function sanitize_api_key( $raw ) {
		$s = preg_replace( '/[^a-zA-Z0-9_\-.]/', '', (string) $raw );
		$s = is_string( $s ) ? $s : '';
		return substr( $s, 0, 200 );
	}

	/**
	 * @param mixed  $new
	 * @param string $existing
	 * @return string
	 */
	public static function sanitize_secret( $new, $existing ) {
		$new = is_string( $new ) ? trim( $new ) : '';
		if ( $new === '' || $new === self::SECRET_MASK || preg_match( '/^\*+$/', $new ) ) {
			return self::sanitize_api_key( $existing );
		}
		return self::sanitize_api_key( $new );
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public static function redact( $key ) {
		$key = (string) $key;
		if ( $key === '' ) {
			return '';
		}
		return self::SECRET_MASK;
	}

	/**
	 * @param mixed $v
	 * @return bool
	 */
	public static function to_bool( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_numeric( $v ) ) {
			return (int) $v !== 0;
		}
		$s = strtolower( trim( (string) $v ) );
		return in_array( $s, array( '1', 'true', 'yes', 'on' ), true );
	}
}
