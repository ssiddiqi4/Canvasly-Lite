<?php
namespace CanvaslyLite\Admin;

use CanvaslyLite\Document\Documents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cheap admin-screen routing so plugin JS/CSS, background work, and
 * secondary admin logic stay off unrelated wp-admin pages.
 */
class AdminContext {
	const EDITOR_SLUG = 'canvasly-lite';

	/** @var array<string,mixed> */
	private static $memo = array();

	public static function flush_runtime() {
		self::$memo = array();
	}

	/**
	 * @return bool
	 */
	public static function is_admin() {
		return function_exists( 'is_admin' ) && is_admin();
	}

	/**
	 * WordPress, Gutenberg REST, or plugin autosave in progress.
	 *
	 * @return bool
	 */
	public static function is_autosave() {
		if ( isset( self::$memo['autosave'] ) ) {
			return self::$memo['autosave'];
		}
		$yes = ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ( function_exists( 'wp_doing_autosave' ) && wp_doing_autosave() );
		if ( ! $yes && function_exists( 'wp_is_post_autosave' ) && ! empty( $GLOBALS['post']->ID ) ) {
			$yes = (bool) wp_is_post_autosave( $GLOBALS['post']->ID );
		}
		if ( ! $yes && self::is_rest() ) {
			$route = self::rest_route();
			$yes   = $route !== '' && (bool) preg_match( '#/autosave(?:/|\?|$)#', $route );
		}
		return self::$memo['autosave'] = (bool) $yes;
	}

	/**
	 * @return bool
	 */
	public static function is_rest() {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( function_exists( 'wp_doing_rest' ) && wp_doing_rest() );
	}

	/**
	 * @return bool
	 */
	public static function is_ajax() {
		return ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			|| ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() );
	}

	/**
	 * @return bool
	 */
	public static function is_cron() {
		return ( defined( 'DOING_CRON' ) && DOING_CRON )
			|| ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() );
	}

	/**
	 * @return string
	 */
	public static function pagenow() {
		if ( isset( self::$memo['pagenow'] ) ) {
			return self::$memo['pagenow'];
		}
		$p = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( $p === '' && isset( $_SERVER['SCRIPT_NAME'] ) ) {
			$p = basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) );
		}
		return self::$memo['pagenow'] = $p;
	}

	/**
	 * Current `page=` query / `$plugin_page` slug.
	 *
	 * @return string
	 */
	public static function plugin_page_slug() {
		if ( isset( self::$memo['page'] ) ) {
			return self::$memo['page'];
		}
		$page = '';
		if ( isset( $GLOBALS['plugin_page'] ) && is_string( $GLOBALS['plugin_page'] ) ) {
			$page = sanitize_key( $GLOBALS['plugin_page'] );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin page slug.
		if ( $page === '' && isset( $_GET['page'] ) ) {
			$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return self::$memo['page'] = $page;
	}

	/**
	 * @param string $slug
	 * @return bool
	 */
	public static function is_plugin_slug( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( $slug === '' ) {
			return false;
		}
		return $slug === self::EDITOR_SLUG || strpos( $slug, self::EDITOR_SLUG . '-' ) === 0;
	}

	/**
	 * Canvasly admin.php screens (editor, settings, tools, roles, templates, …).
	 *
	 * @param string $hook_suffix `admin_enqueue_scripts` argument.
	 * @return bool
	 */
	public static function is_plugin_page( $hook_suffix = '' ) {
		if ( self::is_plugin_slug( self::plugin_page_slug() ) ) {
			return true;
		}
		$hook = is_string( $hook_suffix ) ? $hook_suffix : '';
		if ( $hook === '' ) {
			return false;
		}
		return strpos( $hook, 'canvasly-lite' ) !== false || strpos( $hook, 'lb_template' ) !== false;
	}

	/**
	 * Post list / add / edit screens for Canvasly-enabled types or saved templates.
	 *
	 * @param string $hook_suffix
	 * @return bool
	 */
	public static function is_relevant_post_screen( $hook_suffix = '' ) {
		$pagenow   = self::pagenow();
		$screens   = array( 'post.php', 'post-new.php', 'edit.php' );
		$hook      = is_string( $hook_suffix ) ? $hook_suffix : '';
		$from_hook = in_array( $hook, $screens, true ) || strpos( $hook, 'lb_template' ) !== false;
		if ( ! in_array( $pagenow, $screens, true ) && ! $from_hook ) {
			return false;
		}
		$type = self::current_post_type( $hook_suffix );
		if ( $type === 'lb_template' || $type === 'lb_component' ) {
			return true;
		}
		if ( class_exists( Documents::class ) ) {
			return Documents::supports( $type );
		}
		return in_array( $type, array( 'post', 'page' ), true );
	}

	/**
	 * @param string $hook_suffix
	 * @return string
	 */
	public static function current_post_type( $hook_suffix = '' ) {
		if ( isset( self::$memo['post_type'] ) ) {
			return self::$memo['post_type'];
		}
		$type = '';
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && ! empty( $screen->post_type ) ) {
				$type = sanitize_key( (string) $screen->post_type );
			}
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only current-screen query vars.
		if ( $type === '' && isset( $_GET['post_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
		}
		if ( $type === '' && isset( $_GET['post'] ) && function_exists( 'get_post' ) ) {
			$post = get_post( absint( wp_unslash( $_GET['post'] ) ) );
			if ( is_object( $post ) && ! empty( $post->post_type ) ) {
				$type = sanitize_key( (string) $post->post_type );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$pagenow = self::pagenow();
		if ( $type === '' && ( $pagenow === 'post-new.php' || $pagenow === 'edit.php' ) ) {
			$type = 'post';
		}
		$hook = is_string( $hook_suffix ) ? $hook_suffix : '';
		if ( $type === '' && strpos( $hook, 'lb_template' ) !== false ) {
			$type = 'lb_template';
		}
		return self::$memo['post_type'] = $type;
	}

	/**
	 * Enqueue plugin JS/CSS only on Canvasly screens or a relevant post-type editor.
	 *
	 * @param string $hook_suffix
	 * @return bool
	 */
	public static function should_enqueue( $hook_suffix = '' ) {
		return self::is_plugin_page( $hook_suffix ) || self::is_relevant_post_screen( $hook_suffix );
	}

	/**
	 * Fullscreen visual builder (`admin.php?page=canvasly-lite`).
	 *
	 * @param string $hook_suffix
	 * @return bool
	 */
	public static function is_editor_page( $hook_suffix = '' ) {
		$hook = is_string( $hook_suffix ) ? $hook_suffix : '';
		if ( $hook === 'toplevel_page_canvasly-lite' ) {
			return true;
		}
		return self::plugin_page_slug() === self::EDITOR_SLUG;
	}

	/**
	 * Upgrades, MU-plugin reinstall, one-shot option writes — not dashboard / Gutenberg.
	 *
	 * @return bool
	 */
	public static function allows_background() {
		if ( self::is_cron() ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( self::is_autosave() || self::is_ajax() ) {
			return false;
		}
		if ( self::is_rest() ) {
			return strpos( self::rest_route(), '/canvasly-lite/' ) !== false;
		}
		return self::is_plugin_page();
	}

	/**
	 * @return string
	 */
	public static function rest_route() {
		if ( isset( self::$memo['rest_route'] ) ) {
			return self::$memo['rest_route'];
		}
		$route = '';
		if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$route = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}
		return self::$memo['rest_route'] = $route;
	}
}
