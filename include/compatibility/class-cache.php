<?php
namespace CanvaslyLite\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Purge page-cache plugins when a Canvasly document is saved.
 */
class Cache {
	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'canvasly-lite/document/after_save', array( self::class, 'purge' ), 40, 1 );
	}

	/**
	 * @param int $post_id
	 * @return bool True when at least the WordPress object cache was cleared.
	 */
	public static function purge( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		if ( function_exists( 'clean_post_cache' ) ) {
			clean_post_cache( $post_id );
		}

		self::call_if( 'wp_cache_post_change', $post_id );
		self::call_if( 'w3tc_flush_post', $post_id );
		self::call_if( 'rocket_clean_post', $post_id );
		self::call_if( 'wpfc_clear_post_cache_by_id', $post_id );
		self::call_if( 'breeze_clear_post_cache', $post_id );

		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_purge_post', $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache integration hook.
			do_action( 'litespeed_purge_post_meta', $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache integration hook.
		}

		if ( isset( $GLOBALS['wp_fastest_cache'] ) && is_object( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'singleDeleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->singleDeleteCache( false, $post_id );
		}

		if ( class_exists( '\\SiteGround_Optimizer\\Supercacher\\Supercacher' ) && method_exists( '\\SiteGround_Optimizer\\Supercacher\\Supercacher', 'purge_cache' ) ) {
			\SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();
		} elseif ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		if ( function_exists( 'nitropack_sdk_purge_local' ) ) {
			nitropack_sdk_purge_local( $post_id );
		} elseif ( function_exists( 'nitropack_purge_cache' ) ) {
			nitropack_purge_cache();
		}

		if ( function_exists( 'flying_press_purge_post' ) ) {
			flying_press_purge_post( $post_id );
		} elseif ( class_exists( '\\FlyingPress\\Purge' ) && method_exists( '\\FlyingPress\\Purge', 'purge_post' ) ) {
			\FlyingPress\Purge::purge_post( $post_id );
		}

		if ( class_exists( '\\Hummingbird\\WP_Hummingbird' ) && method_exists( '\\Hummingbird\\WP_Hummingbird', 'flush_cache' ) ) {
			\Hummingbird\WP_Hummingbird::flush_cache();
		}

		if ( function_exists( 'has_action' ) && has_action( 'cache_enabler_clear_page_cache_by_post' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Cache Enabler integration hook.
			do_action( 'cache_enabler_clear_page_cache_by_post', $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Cache Enabler integration hook.
		}

		/**
		 * Fires after Canvasly has asked cache plugins to drop a post.
		 *
		 * @param int $post_id
		 */
		do_action( 'canvasly-lite/cache/purge', $post_id );
		return true;
	}

	/**
	 * Site-wide purge (kit / global CSS). Used by tools, not every save.
	 */
	public static function purge_all() {
		self::call_if( 'wp_cache_clear_cache' );
		self::call_if( 'w3tc_flush_all' );
		self::call_if( 'rocket_clean_domain' );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache integration hook.
		}
		/**
		 * Fires after a site-wide cache purge request.
		 */
		do_action( 'canvasly-lite/cache/purge_all' );
	}

	/**
	 * @param string $fn
	 * @param mixed  ...$args
	 */
	private static function call_if( $fn, ...$args ) {
		if ( function_exists( $fn ) ) {
			$fn( ...$args );
		}
	}
}
