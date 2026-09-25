<?php
namespace CanvaslyLite\Design;

use CanvaslyLite\Dynamic\Resolver;
use CanvaslyLite\Settings\GlobalSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unit fragment cache, lazy backgrounds, image loading and optimized markup (Roadmap 6.3).
 *
 * Non-dynamic units are stored in the request map and, when an external object
 * cache is present, in that cache. Fragments are never written as wp_options
 * transients: unique keys plus generation bumps would leak expired rows forever.
 * Background images after the first in a document are omitted from CSS and applied
 * in view via `data-lb-bg`. The first `<img>` gets `fetchpriority="high"` /
 * `loading="eager"`; later images get `loading="lazy"`. Optimized markup merges
 * the node wrapper onto a single inner root when it is safe.
 */
class Optimize {
	const TTL_DEFAULT      = 86400;
	const META_GEN         = '_lb_frag_gen';
	const OPTION_GEN       = 'canvasly_lite_frag_gen';
	const TRANSIENT_PREFIX = 'lb_el_';
	const CACHE_GROUP      = 'canvasly_lite_frag';
	const RUNTIME_MAX      = 250;
	const PURGE_FLAG       = 'canvasly_lite_frag_transients_purged';

	/** @var bool */
	private static $booted = false;
	/** @var array<string,string>|null node id => url for lazy backgrounds */
	private static $lazy_bgs = null;
	/** @var int */
	private static $image_n = 0;
	/** @var array<string,string> in-request fragment map */
	private static $runtime = array();

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		add_action( 'canvasly-lite/document/after_save', array( self::class, 'on_after_save' ), 15, 1 );
		add_action( 'deleted_post', array( self::class, 'invalidate_post' ) );
		add_action( 'init', array( self::class, 'maybe_purge_option_transients' ), 30 );
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'canvasly-lite/tools/screen', array( self::class, 'screen' ), 7 );
			add_action( 'admin_post_lb_optimize_settings', array( self::class, 'handle_settings' ) );
			add_action( 'admin_post_lb_optimize_flush', array( self::class, 'handle_flush' ) );
			add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		}
	}

	public static function reset_runtime() {
		self::$lazy_bgs = null;
		self::$image_n  = 0;
		self::$runtime  = array();
	}

	/**
	 * Start a document render or CSS compile so first-image / first-background tracking is fresh.
	 *
	 * @param array $nodes Document root.
	 */
	public static function begin( $nodes = array() ) {
		self::$image_n  = 0;
		self::$lazy_bgs = self::lazy_load() ? self::collect_lazy_ids( is_array( $nodes ) ? $nodes : array() ) : array();
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
			'/optimize',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'rest_get' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest_save' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			$ns,
			'/optimize/flush',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_flush' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
	}

	public static function rest_get() {
		return rest_ensure_response( self::status() );
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_save( $req ) {
		$d = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		self::save_from( $d );
		return rest_ensure_response( self::status() );
	}

	public static function rest_flush() {
		self::flush_all();
		return rest_ensure_response( self::status() );
	}

	/**
	 * Persist keys present on a settings payload (REST / global-settings).
	 *
	 * @param array $d
	 */
	public static function save_from( $d ) {
		$d       = is_array( $d ) ? $d : array();
		$changed = false;
		$before  = array(
			'lazy_load'         => self::lazy_load(),
			'optimized_markup'  => self::markup(),
		);
		foreach ( array( 'unit_cache', 'unit_cache_ttl', 'lazy_load', 'optimized_markup' ) as $k ) {
			if ( array_key_exists( $k, $d ) ) {
				$changed = true;
			}
		}
		if ( ! $changed ) {
			return;
		}
		if ( class_exists( GlobalSettings::class ) ) {
			$g = GlobalSettings::get();
		} else {
			$g = array();
		}
		if ( array_key_exists( 'unit_cache', $d ) ) {
			$g['unit_cache'] = self::sanitize_bool( $d['unit_cache'] );
		}
		if ( array_key_exists( 'unit_cache_ttl', $d ) ) {
			$g['unit_cache_ttl'] = self::sanitize_ttl( $d['unit_cache_ttl'] );
		}
		if ( array_key_exists( 'lazy_load', $d ) ) {
			$g['lazy_load'] = self::sanitize_bool( $d['lazy_load'] );
		}
		if ( array_key_exists( 'optimized_markup', $d ) ) {
			$g['optimized_markup'] = self::sanitize_bool( $d['optimized_markup'] );
		}
		if ( class_exists( GlobalSettings::class ) ) {
			update_option( GlobalSettings::KEY, $g, false );
		} else {
			update_option( 'canvasly_lite_unit_cache', $g['unit_cache'] ?? true, false );
			update_option( 'canvasly_lite_unit_cache_ttl', $g['unit_cache_ttl'] ?? self::TTL_DEFAULT, false );
			update_option( 'canvasly_lite_lazy_load', $g['lazy_load'] ?? true, false );
			update_option( 'canvasly_lite_optimized_markup', $g['optimized_markup'] ?? false, false );
		}
		self::flush_all();
		$after_lazy = self::lazy_load();
		$after_mark = self::markup();
		if ( $before['lazy_load'] !== $after_lazy || $before['optimized_markup'] !== $after_mark ) {
			if ( class_exists( CssPrint::class ) ) {
				CssPrint::invalidate_all();
			}
		}
	}

	/**
	 * @return array
	 */
	public static function status() {
		return array(
			'unit_cache'      => self::cache_enabled(),
			'unit_cache_ttl'  => self::ttl(),
			'lazy_load'          => self::lazy_load(),
			'optimized_markup'   => self::markup(),
			'generation'         => self::site_generation(),
		);
	}

	/**
	 * @param mixed $v
	 * @return bool
	 */
	public static function sanitize_bool( $v ) {
		if ( is_string( $v ) ) {
			$v = strtolower( trim( $v ) );
			if ( in_array( $v, array( '0', 'false', 'no', 'off', '' ), true ) ) {
				return false;
			}
			if ( in_array( $v, array( '1', 'true', 'yes', 'on' ), true ) ) {
				return true;
			}
		}
		return ! empty( $v );
	}

	/**
	 * TTL in seconds (1 minute – 7 days).
	 *
	 * @param mixed $v
	 * @return int
	 */
	public static function sanitize_ttl( $v ) {
		$n   = is_numeric( $v ) ? (int) $v : self::TTL_DEFAULT;
		$min = defined( 'MINUTE_IN_SECONDS' ) ? MINUTE_IN_SECONDS : 60;
		$max = 7 * ( defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400 );
		if ( $n < $min ) {
			$n = self::TTL_DEFAULT;
		}
		return max( $min, min( $max, $n ) );
	}

	public static function cache_enabled() {
		$raw = self::setting( 'unit_cache', true );
		$on  = self::sanitize_bool( $raw );
		/**
		 * Filter whether unit fragment cache is active.
		 *
		 * @param bool $on
		 */
		$filtered = apply_filters( 'canvasly-lite/optimize/unit_cache', $on );
		return ! empty( $filtered );
	}

	public static function ttl() {
		$raw = self::setting( 'unit_cache_ttl', self::TTL_DEFAULT );
		$ttl = self::sanitize_ttl( $raw );
		/**
		 * Filter fragment-cache TTL in seconds.
		 *
		 * @param int $ttl
		 */
		$filtered = apply_filters( 'canvasly-lite/optimize/ttl', $ttl );
		return self::sanitize_ttl( is_numeric( $filtered ) ? $filtered : $ttl );
	}

	public static function lazy_load() {
		$raw = self::setting( 'lazy_load', true );
		$on  = self::sanitize_bool( $raw );
		/**
		 * Filter lazy background / image loading.
		 *
		 * @param bool $on
		 */
		$filtered = apply_filters( 'canvasly-lite/optimize/lazy_load', $on );
		return ! empty( $filtered );
	}

	public static function markup() {
		$raw = self::setting( 'optimized_markup', false );
		$on  = self::sanitize_bool( $raw );
		/**
		 * Filter optimized markup (wrapper flattening).
		 *
		 * @param bool $on
		 */
		$filtered = apply_filters( 'canvasly-lite/optimize/markup', $on );
		return ! empty( $filtered );
	}

	/**
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	private static function setting( $key, $default ) {
		if ( class_exists( GlobalSettings::class ) ) {
			$g = get_option( GlobalSettings::KEY, array() );
			if ( is_array( $g ) && array_key_exists( $key, $g ) ) {
				return $g[ $key ];
			}
		}
		$opt = get_option( 'canvasly_lite_' . $key, null );
		return $opt === null ? $default : $opt;
	}

	/* ---------- fragment cache ---------- */

	/**
	 * Types whose HTML depends on request / user / query and must not be cached.
	 *
	 * @return string[]
	 */
	public static function live_types() {
		$types = array(
			'collection_loop',
			'shortcode',
			'login',
			'template',
			'component',
			'wordpress_widget',
			'sidebar',
			'form',
		);
		/**
		 * Filter unit types excluded from the fragment cache.
		 *
		 * @param string[] $types
		 */
		$filtered = apply_filters( 'canvasly-lite/optimize/live_types', $types );
		return is_array( $filtered ) ? array_values( array_map( 'strval', $filtered ) ) : $types;
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	public static function is_live( $type ) {
		return in_array( (string) $type, self::live_types(), true );
	}

	/**
	 * True when this node and every descendant can be stored as a fragment.
	 *
	 * @param array  $node
	 * @param array  $extra_ctx Collection-loop dynamic context.
	 * @param string $id_suffix Loop item suffix.
	 * @return bool
	 */
	public static function cacheable( array $node, $extra_ctx = array(), $id_suffix = '' ) {
		if ( ! self::cache_enabled() ) {
			return false;
		}
		if ( ( is_array( $extra_ctx ) && $extra_ctx ) || (string) $id_suffix !== '' ) {
			return false;
		}
		if ( function_exists( 'is_preview' ) && is_preview() ) {
			return false;
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}
		if ( self::is_live( (string) ( $node['type'] ?? '' ) ) ) {
			return false;
		}
		$s = is_array( $node['settings'] ?? null ) ? $node['settings'] : array();
		if ( ! empty( $s['_dynamic'] ) && is_array( $s['_dynamic'] ) ) {
			return false;
		}
		if ( class_exists( Resolver::class ) && Resolver::node_is_dynamic( $node ) ) {
			return false;
		}
		foreach ( (array) ( $node['children'] ?? array() ) as $child ) {
			if ( is_array( $child ) && ! self::cacheable( $child, $extra_ctx, $id_suffix ) ) {
				return false;
			}
		}
		$ok = true;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'canvasly-lite/unit/cacheable', $ok, $node );
			$ok       = ! empty( $filtered );
		}
		return $ok;
	}

	/**
	 * @param int $post_id
	 * @return int
	 */
	public static function generation( $post_id = 0 ) {
		$site = self::site_generation();
		$post = 0;
		$post_id = absint( $post_id );
		if ( $post_id && function_exists( 'get_post_meta' ) ) {
			$post = absint( get_post_meta( $post_id, self::META_GEN, true ) );
		}
		return $site + $post;
	}

	public static function site_generation() {
		return absint( get_option( self::OPTION_GEN, 0 ) );
	}

	/**
	 * @param int $post_id
	 */
	public static function bump_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! function_exists( 'update_post_meta' ) ) {
			return;
		}
		$n = absint( get_post_meta( $post_id, self::META_GEN, true ) ) + 1;
		update_post_meta( $post_id, self::META_GEN, $n );
		self::$runtime = array();
	}

	public static function bump_site() {
		update_option( self::OPTION_GEN, self::site_generation() + 1, false );
		self::$runtime = array();
	}

	/**
	 * @param int $post_id
	 */
	public static function invalidate_post( $post_id ) {
		self::bump_post( $post_id );
	}

	/**
	 * @param int $id
	 */
	public static function on_after_save( $id ) {
		self::invalidate_post( $id );
	}

	public static function flush_all() {
		self::bump_site();
		self::purge_option_transients();
	}

	/**
	 * One-time removal of leaked `_transient_lb_el_*` rows from wp_options.
	 * Older builds stored a unique transient per fragment; generation bumps
	 * left those rows unreachable until a manual options-table cleanup.
	 */
	public static function maybe_purge_option_transients() {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::allows_background() ) {
			return;
		}
		if ( function_exists( 'get_option' ) && get_option( self::PURGE_FLAG ) ) {
			return;
		}
		self::purge_option_transients();
		if ( function_exists( 'update_option' ) ) {
			update_option( self::PURGE_FLAG, 1, false );
		}
	}

	/**
	 * Delete leftover fragment-cache transients from the options table.
	 *
	 * @return int Rows removed, or 0 when the table is unavailable.
	 */
	public static function purge_option_transients() {
		global $wpdb;
		if ( ! $wpdb || empty( $wpdb->options ) || ! method_exists( $wpdb, 'query' ) ) {
			return 0;
		}
		$like  = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( '_transient_' . self::TRANSIENT_PREFIX ) . '%' : '_transient_' . self::TRANSIENT_PREFIX . '%';
		$like2 = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( '_transient_timeout_' . self::TRANSIENT_PREFIX ) . '%' : '_transient_timeout_' . self::TRANSIENT_PREFIX . '%';
		if ( method_exists( $wpdb, 'prepare' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot cleanup of leftover fragment-cache transients.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$like,
					$like2
				)
			);
		} else {
			$deleted = 0;
		}
		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}

	/** @return bool */
	private static function using_object_cache() {
		return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
	}

	/**
	 * @param array $node
	 * @param int   $post_id
	 * @return string
	 */
	public static function cache_key( array $node, $post_id = 0 ) {
		$payload = array(
			'v'  => defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '',
			'g'  => self::generation( $post_id ),
			'id' => (string) ( $node['id'] ?? '' ),
			't'  => (string) ( $node['type'] ?? '' ),
			's'  => $node['settings'] ?? array(),
			'c'  => $node['children'] ?? array(),
			'i'  => $node['interactions'] ?? array(),
			'st' => $node['styles'] ?? array(),
			'm'  => self::markup() ? 1 : 0,
			'l'  => self::lazy_load() ? 1 : 0,
			'sl' => $node['slot'] ?? '',
		);
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );
		return self::TRANSIENT_PREFIX . md5( (string) $json );
	}

	/**
	 * @param array $node
	 * @param int   $post_id
	 * @return string|null
	 */
	public static function get( array $node, $post_id = 0 ) {
		$key = self::cache_key( $node, $post_id );
		if ( $key !== '' && array_key_exists( $key, self::$runtime ) ) {
			$hit = self::$runtime[ $key ];
			return is_string( $hit ) && $hit !== '' ? $hit : null;
		}
		if ( self::using_object_cache() && function_exists( 'wp_cache_get' ) ) {
			$hit = wp_cache_get( $key, self::CACHE_GROUP );
			if ( is_string( $hit ) && $hit !== '' ) {
				if ( count( self::$runtime ) < self::RUNTIME_MAX ) {
					self::$runtime[ $key ] = $hit;
				}
				return $hit;
			}
		}
		return null;
	}

	/**
	 * @param array  $node
	 * @param int    $post_id
	 * @param string $html
	 * @return bool
	 */
	public static function set( array $node, $post_id, $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return false;
		}
		$key = self::cache_key( $node, $post_id );
		if ( $key === '' ) {
			return false;
		}
		if ( count( self::$runtime ) < self::RUNTIME_MAX ) {
			self::$runtime[ $key ] = $html;
		}
		if ( self::using_object_cache() && function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( $key, $html, self::CACHE_GROUP, self::ttl() );
		}
		return true;
	}

	/* ---------- lazy backgrounds ---------- */

	/**
	 * Raster background URL on a settings map (classic image or video poster). Gradients are ignored.
	 *
	 * @param array $s
	 * @return string
	 */
	public static function background_url( array $s ) {
		$bg   = is_array( $s['background'] ?? null ) ? $s['background'] : array();
		$type = (string) ( $bg['type'] ?? 'classic' );
		if ( $type === 'gradient' ) {
			return '';
		}
		$url = isset( $bg['image_url'] ) ? trim( (string) $bg['image_url'] ) : '';
		if ( $url === '' && ! empty( $s['background_image'] ) && is_string( $s['background_image'] ) ) {
			$url = trim( $s['background_image'] );
		}
		if ( $url === '' && $type === 'video' && ! empty( $bg['video_poster'] ) ) {
			$url = trim( (string) $bg['video_poster'] );
		}
		if ( $url === '' || stripos( $url, 'gradient(' ) !== false ) {
			return '';
		}
		return $url;
	}

	/**
	 * Node ids whose background image should be deferred (everything after the first raster bg).
	 *
	 * @param array $nodes
	 * @return array<string,string>
	 */
	public static function collect_lazy_ids( array $nodes ) {
		$ids   = array();
		$count = 0;
		$walk  = function ( $items ) use ( &$walk, &$ids, &$count ) {
			foreach ( (array) $items as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				$s   = is_array( $n['settings'] ?? null ) ? $n['settings'] : array();
				$url = self::background_url( $s );
				if ( $url !== '' ) {
					$count++;
					$id = (string) ( $n['id'] ?? '' );
					if ( $count > 1 && $id !== '' ) {
						$ids[ $id ] = $url;
					}
				}
				if ( ! empty( $n['children'] ) ) {
					$walk( $n['children'] );
				}
			}
		};
		$walk( $nodes );
		return $ids;
	}

	/**
	 * @param string $id
	 * @return bool
	 */
	public static function is_lazy_bg( $id ) {
		if ( ! self::lazy_load() ) {
			return false;
		}
		if ( self::$lazy_bgs === null ) {
			return false;
		}
		return isset( self::$lazy_bgs[ (string) $id ] );
	}

	/**
	 * @param string $id
	 * @return string
	 */
	public static function lazy_bg_url( $id ) {
		if ( self::$lazy_bgs === null ) {
			return '';
		}
		return (string) ( self::$lazy_bgs[ (string) $id ] ?? '' );
	}

	/**
	 * Drop `background-image:url(...)` from rules that paint this node (or its `.lb-container`).
	 *
	 * @param string $css
	 * @param string $id
	 * @return string
	 */
	public static function strip_node_bg_image( $css, $id ) {
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $id );
		if ( $id === '' || $css === '' ) {
			return $css;
		}
		$sel = preg_quote( '#lb-node-' . $id, '/' );
		return preg_replace_callback(
			'/' . $sel . '(?:\s+\.lb-container)?\s*\{[^}]*\}/',
			function ( $m ) {
				return preg_replace( '/background-image\s*:\s*url\([^;]*\)\s*;?/i', '', $m[0] );
			},
			$css
		);
	}

	/**
	 * Attributes + class for a lazy background node wrapper.
	 *
	 * @param string $id
	 * @param string $classes
	 * @return array{class:string,attr:string}
	 */
	public static function bg_wrap( $id, $classes ) {
		$url = self::lazy_bg_url( $id );
		if ( $url === '' ) {
			return array( 'class' => $classes, 'attr' => '' );
		}
		$classes = trim( $classes . ' lb-lazy-bg' );
		$attr    = ' data-lb-bg="' . esc_url( $url ) . '"';
		return array( 'class' => $classes, 'attr' => $attr );
	}

	/**
	 * Whether the frontend script is needed for lazy backgrounds in this tree.
	 *
	 * @param array $nodes
	 * @return bool
	 */
	public static function needs_script( array $nodes ) {
		if ( ! self::lazy_load() ) {
			return false;
		}
		$ids = self::$lazy_bgs !== null ? self::$lazy_bgs : self::collect_lazy_ids( $nodes );
		return ! empty( $ids );
	}

	/* ---------- image loading ---------- */

	/**
	 * First image: eager + fetchpriority=high. Later images: loading=lazy unless already eager/high.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function tune_images( $html ) {
		if ( ! self::lazy_load() || ! is_string( $html ) || $html === '' || stripos( $html, '<img' ) === false ) {
			return $html;
		}
		return preg_replace_callback(
			'/<img\b([^>]*?)(\/?)>/i',
			function ( $m ) {
				$attrs = $m[1];
				$slash = $m[2];
				self::$image_n++;
				$first = self::$image_n === 1;
				$has_loading = preg_match( '/\bloading\s*=\s*["\']?([a-z]+)/i', $attrs, $lm );
				$loading     = $has_loading ? strtolower( $lm[1] ) : '';
				$has_fp      = preg_match( '/\bfetchpriority\s*=\s*["\']?([a-z]+)/i', $attrs, $fm );
				$fp          = $has_fp ? strtolower( $fm[1] ) : '';
				if ( $first ) {
					if ( $has_loading ) {
						$attrs = preg_replace( '/\sloading\s*=\s*(["\']).*?\1|\sloading\s*=\s*[^\s>]+/i', '', $attrs );
					}
					$attrs .= ' loading="eager"';
					if ( ! $has_fp || $fp === 'auto' ) {
						if ( $has_fp ) {
							$attrs = preg_replace( '/\sfetchpriority\s*=\s*(["\']).*?\1|\sfetchpriority\s*=\s*[^\s>]+/i', '', $attrs );
						}
						$attrs .= ' fetchpriority="high"';
					}
				} else {
					if ( ! $has_loading || $loading === 'auto' ) {
						if ( $has_loading ) {
							$attrs = preg_replace( '/\sloading\s*=\s*(["\']).*?\1|\sloading\s*=\s*[^\s>]+/i', '', $attrs );
						}
						if ( $fp !== 'high' ) {
							$attrs .= ' loading="lazy"';
						}
					}
				}
				$attrs = preg_replace( '/\s+/', ' ', trim( $attrs ) );
				$attrs = $attrs !== '' ? ' ' . $attrs : '';
				return '<img' . $attrs . ( $slash !== '' ? ' /' : '' ) . '>';
			},
			$html
		);
	}

	/* ---------- optimized markup ---------- */

	/**
	 * Dual-target descendant selectors so flattened roots still match widget CSS.
	 *
	 * `#lb-node-x .lb-heading` becomes `#lb-node-x.lb-heading, #lb-node-x .lb-heading`.
	 *
	 * @param string $css
	 * @return string
	 */
	public static function expand_selectors( $css ) {
		if ( ! self::markup() || ! is_string( $css ) || $css === '' ) {
			return $css;
		}
		return preg_replace_callback(
			'/(#lb-node-[a-zA-Z0-9_-]+)((?::+[a-z-]+(?:\([^)]*\))?)*)\s+(\.[a-zA-Z0-9_-]+)/',
			function ( $m ) {
				return $m[1] . $m[3] . $m[2] . ', ' . $m[0];
			},
			$css
		);
	}

	/**
	 * @param string $html
	 * @return bool
	 */
	public static function is_single_root( $html ) {
		$html = trim( (string) $html );
		if ( $html === '' || $html[0] !== '<' ) {
			return false;
		}
		$void  = array( 'img', 'hr', 'br', 'input', 'meta', 'link', 'source', 'area', 'col', 'embed', 'wbr' );
		$depth = 0;
		$roots = 0;
		$i     = 0;
		$len   = strlen( $html );
		while ( $i < $len ) {
			$lt = strpos( $html, '<', $i );
			if ( $lt === false ) {
				if ( $depth === 0 && trim( substr( $html, $i ) ) !== '' ) {
					return false;
				}
				break;
			}
			if ( $depth === 0 && $lt > $i && trim( substr( $html, $i, $lt - $i ) ) !== '' ) {
				return false;
			}
			if ( $lt + 1 < $len && ( $html[ $lt + 1 ] === '!' || $html[ $lt + 1 ] === '?' ) ) {
				$end = strpos( $html, '>', $lt );
				$i   = $end === false ? $len : $end + 1;
				continue;
			}
			if ( ! preg_match( '/\G<\/?([a-zA-Z0-9]+)(\s[^>]*)?(\/)?>/s', $html, $m, 0, $lt ) ) {
				return false;
			}
			$close = $html[ $lt + 1 ] === '/';
			$tag   = strtolower( $m[1] );
			$self  = isset( $m[3] ) && $m[3] === '/' || in_array( $tag, $void, true );
			if ( $close ) {
				$depth--;
				if ( $depth < 0 ) {
					return false;
				}
			} else {
				if ( $depth === 0 ) {
					$roots++;
					if ( $roots > 1 ) {
						return false;
					}
				}
				if ( ! $self ) {
					$depth++;
				}
			}
			$i = $lt + strlen( $m[0] );
		}
		return $roots === 1 && $depth === 0;
	}

	/**
	 * @param array       $n
	 * @param object|null $el
	 * @param array       $s
	 * @param string      $inner
	 * @return bool
	 */
	public static function can_flatten( array $n, $el, array $s, $inner ) {
		if ( ! self::markup() ) {
			return false;
		}
		if ( ! empty( $s['css_id'] ) ) {
			return false;
		}
		if ( is_object( $el ) && method_exists( $el, 'supports_children' ) && $el->supports_children() ) {
			return false;
		}
		if ( is_object( $el ) && method_exists( $el, 'supports_slots' ) && $el->supports_slots() ) {
			return false;
		}
		if ( ! empty( $n['children'] ) ) {
			return false;
		}
		if ( class_exists( '\\CanvaslyLite\\Controls\\Groups' ) && \CanvaslyLite\Controls\Groups::has_layers( $s ) ) {
			return false;
		}
		if ( ! empty( $n['interactions'] ) && is_array( $n['interactions'] ) ) {
			foreach ( $n['interactions'] as $item ) {
				if ( is_array( $item ) && ! empty( $item['effect'] ) ) {
					return false;
				}
			}
		}
		if ( ! self::is_single_root( $inner ) ) {
			return false;
		}
		if ( preg_match( '/^(\s*)<[a-zA-Z0-9]+[^>]*\sid\s*=/i', ltrim( $inner ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Wrap inner HTML in the node shell, or merge onto the inner root when flattening.
	 *
	 * @param string      $html_id
	 * @param string      $classes
	 * @param string      $attrs
	 * @param string      $inner
	 * @param array       $n
	 * @param object|null $el
	 * @param array       $s
	 * @return string
	 */
	public static function wrap( $html_id, $classes, $attrs, $inner, array $n, $el, array $s ) {
		$bg      = self::bg_wrap( (string) ( $n['id'] ?? $html_id ), $classes );
		$classes = $bg['class'];
		$attrs  .= $bg['attr'];
		$inner   = is_string( $inner ) ? $inner : '';
		$open    = '<div id="lb-node-' . esc_attr( $html_id ) . '" class="' . esc_attr( $classes ) . '"' . $attrs . '>';
		if ( ! self::can_flatten( $n, $el, $s, $inner ) ) {
			return $open . $inner . '</div>';
		}
		$merged = self::merge_root( $inner, $html_id, $classes, $attrs );
		return $merged !== null ? $merged : $open . $inner . '</div>';
	}

	/**
	 * @param string $inner
	 * @param string $html_id
	 * @param string $classes
	 * @param string $attrs
	 * @return string|null
	 */
	public static function merge_root( $inner, $html_id, $classes, $attrs ) {
		$ok = preg_match( '/^(\s*)<([a-zA-Z0-9]+)(\s[^>]*)?>/', $inner, $m );
		if ( ! $ok ) {
			return null;
		}
		$rest = isset( $m[3] ) ? $m[3] : '';
		if ( preg_match( '/\sid\s*=/i', $rest ) ) {
			return null;
		}
		$n = 0;
		$rest = preg_replace( '/\sclass=(["\'])(.*?)\1/', ' class=$1' . esc_attr( $classes ) . ' $2$1', $rest, 1, $n );
		if ( ! $n ) {
			$rest .= ' class="' . esc_attr( $classes ) . '"';
		}
		$open = $m[1] . '<' . $m[2] . ' id="lb-node-' . esc_attr( $html_id ) . '"' . $rest . $attrs . '>';
		return $open . substr( $inner, strlen( $m[0] ) );
	}

	public static function tools_url() {
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) ) {
			return \CanvaslyLite\Settings\AdminSettings::tools_or_settings_url();
		}
		return admin_url( 'admin.php?page=canvasly-lite-tools' );
	}

	public static function handle_settings() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can change performance settings.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_optimize_settings' );
		$hours = max( 1, min( 168, absint( wp_unslash( $_POST['unit_cache_ttl'] ?? 24 ) ) ) );
		$hour  = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
		self::save_from(
			array(
				'unit_cache'     => ! empty( $_POST['unit_cache'] ),
				'unit_cache_ttl' => $hours * $hour,
				'lazy_load'         => ! empty( $_POST['lazy_load'] ),
				'optimized_markup'  => ! empty( $_POST['optimized_markup'] ),
			)
		);
		self::store_notice( 'success', __( 'Performance settings saved.', 'canvasly-lite' ) );
		wp_safe_redirect( self::tools_url() );
		exit;
	}

	public static function handle_flush() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can flush the unit cache.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_optimize_flush' );
		self::flush_all();
		self::store_notice( 'success', __( 'Unit fragment cache flushed.', 'canvasly-lite' ) );
		wp_safe_redirect( self::tools_url() );
		exit;
	}

	/**
	 * @param string $type
	 * @param string $message
	 */
	private static function store_notice( $type, $message ) {
		if ( function_exists( 'set_transient' ) ) {
			set_transient(
				'canvasly_lite_optimize_notice_' . get_current_user_id(),
				array( 'type' => $type, 'message' => $message ),
				defined( 'MINUTE_IN_SECONDS' ) ? 10 * MINUTE_IN_SECONDS : 600
			);
		}
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
		$n = get_transient( 'canvasly_lite_optimize_notice_' . get_current_user_id() );
		if ( ! is_array( $n ) ) {
			return;
		}
		delete_transient( 'canvasly_lite_optimize_notice_' . get_current_user_id() );
		$class = ( $n['type'] ?? '' ) === 'success' ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $n['message'] ?? '' ) ) . '</p></div>';
	}

	public static function screen() {
		if ( ! self::can_manage() ) {
			return;
		}
		$cache = self::cache_enabled();
		$ttl   = self::ttl();
		$hours = max( 1, (int) round( $ttl / ( defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600 ) ) );
		$lazy  = self::lazy_load();
		$mark  = self::markup();

		echo '<hr><h2>' . esc_html__( 'Performance', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Fragment cache stores rendered HTML in memory (and the object cache when one is present) for units that do not use dynamic tags. It is not written to the WordPress options table. Lazy load defers background images below the first one and sets fetchpriority on the first image. Optimized markup removes the extra node wrapper when the widget already has a single root.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_optimize_settings' );
		echo '<input type="hidden" name="action" value="lb_optimize_settings">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Unit cache', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="unit_cache" value="1"' . ( $cache ? ' checked' : '' ) . '> ' . esc_html__( 'Cache HTML fragments for non-dynamic units.', 'canvasly-lite' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Cache TTL (hours)', 'canvasly-lite' ) . '</th><td>';
		echo '<input type="number" min="1" max="168" name="unit_cache_ttl" value="' . esc_attr( (string) $hours ) . '">';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Lazy load', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="lazy_load" value="1"' . ( $lazy ? ' checked' : '' ) . '> ' . esc_html__( 'Lazy-load background images below the first one, fetchpriority=high on the first image, loading=lazy after.', 'canvasly-lite' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Optimized markup', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="optimized_markup" value="1"' . ( $mark ? ' checked' : '' ) . '> ' . esc_html__( 'Remove the extra node wrapper on simple widgets (heading, image, button, …) when safe.', 'canvasly-lite' ) . '</label>';
		echo '</td></tr>';
		echo '</tbody></table>';
		echo '<p><button class="button" type="submit">' . esc_html__( 'Save performance settings', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_optimize_flush' );
		echo '<input type="hidden" name="action" value="lb_optimize_flush">';
		echo '<p><button class="button" type="submit">' . esc_html__( 'Flush unit cache', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';
	}
}
