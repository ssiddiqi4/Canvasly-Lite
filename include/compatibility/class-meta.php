<?php
namespace CanvaslyLite\Compatibility;

use CanvaslyLite\Document\DocumentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared Canvasly post-meta keys and JSON write/repair helpers.
 *
 * WordPress `update_post_meta()` unslashes values. JSON documents contain
 * `\"` sequences, so writes must `wp_slash()` first or quotes inside strings
 * are stripped and the document no longer parses.
 */
class Meta {
	/**
	 * Post meta stored as JSON strings (document / template / component trees).
	 *
	 * @return string[]
	 */
	public static function json_keys() {
		$keys = array(
			'_lb_document_data',
			'_lb_template_data',
			'_lb_component_data',
		);
		if ( class_exists( DocumentManager::class ) ) {
			$keys[] = DocumentManager::META;
		}
		$keys = array_values( array_unique( array_filter( $keys ) ) );
		/**
		 * Filter JSON document meta keys.
		 *
		 * @param string[] $keys
		 */
		$filtered = apply_filters( 'canvasly-lite/portability/json_keys', $keys );
		return is_array( $filtered ) ? array_values( array_unique( array_map( 'strval', $filtered ) ) ) : $keys;
	}

	/**
	 * Meta keys Replace URL rewrites (JSON documents plus compiled CSS cache).
	 *
	 * @return string[]
	 */
	public static function replaceable_keys() {
		$keys = self::json_keys();
		$keys[] = '_lb_css_cache';
		if ( class_exists( DocumentManager::class ) ) {
			$keys[] = DocumentManager::CSS_CACHE;
		}
		$keys = array_values( array_unique( array_filter( $keys ) ) );
		/**
		 * Filter meta keys scanned by Replace URL.
		 *
		 * @param string[] $keys
		 */
		$filtered = apply_filters( 'canvasly-lite/replace_url/keys', $keys );
		return is_array( $filtered ) ? array_values( array_unique( array_map( 'strval', $filtered ) ) ) : $keys;
	}

	/**
	 * Regenerable or session meta that should not export or duplicate.
	 *
	 * @return string[]
	 */
	public static function ephemeral_keys() {
		$keys = array(
			'_lb_css_cache',
			'_lb_css_hash',
			'_lb_autosave_data',
			'_lb_asset_version',
			'_lb_frag_gen',
			'_lb_document_revisions',
			'_lb_revision_label',
			'_lb_revisions_migrated',
		);
		if ( class_exists( DocumentManager::class ) ) {
			$keys[] = DocumentManager::CSS_CACHE;
			$keys[] = DocumentManager::AUTOSAVE;
			$keys[] = DocumentManager::REVISIONS;
		}
		$keys = array_values( array_unique( array_filter( $keys ) ) );
		/**
		 * Filter meta keys skipped on WXR export and post duplication.
		 *
		 * @param string[] $keys
		 */
		$filtered = apply_filters( 'canvasly-lite/portability/ephemeral_keys', $keys );
		return is_array( $filtered ) ? array_values( array_unique( array_map( 'strval', $filtered ) ) ) : $keys;
	}

	/**
	 * Document-identity keys copied when a post is duplicated.
	 *
	 * @return string[]
	 */
	public static function copyable_keys() {
		$keys = array(
			'_lb_document_data',
			'_lb_document_version',
			'_lb_document_updated',
			'_lb_template_data',
			'_lb_template_type',
			'_lb_template_key',
			'_lb_component_data',
			'_lb_component_key',
			'_lb_component_version',
			'_lb_component_exposed',
			'_lb_kit_source',
			'_lb_converted_from',
			'_lb_converted_at',
		);
		if ( class_exists( DocumentManager::class ) ) {
			$keys[] = DocumentManager::META;
			$keys[] = DocumentManager::VERSION;
			$keys[] = DocumentManager::UPDATED;
		}
		$keys = array_values( array_unique( array_filter( $keys ) ) );
		/**
		 * Filter meta keys copied onto a duplicated post.
		 *
		 * @param string[] $keys
		 */
		$filtered = apply_filters( 'canvasly-lite/duplicate/meta_keys', $keys );
		return is_array( $filtered ) ? array_values( array_unique( array_map( 'strval', $filtered ) ) ) : $keys;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function is_json_key( $key ) {
		return in_array( (string) $key, self::json_keys(), true );
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function is_ephemeral( $key ) {
		$key = (string) $key;
		if ( in_array( $key, self::ephemeral_keys(), true ) ) {
			return true;
		}
		if ( strpos( $key, '_lb_lock_' ) === 0 ) {
			return true;
		}
		return false;
	}

	/**
	 * Whether this key is Canvasly post meta.
	 *
	 * @param string $key
	 * @return bool
	 */
	public static function is_ours( $key ) {
		$key = (string) $key;
		return strpos( $key, '_lb_' ) === 0;
	}

	/**
	 * Restore a JSON string that was over-slashed or stored as an array.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function repair_json( $value ) {
		if ( is_array( $value ) ) {
			return function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value );
		}
		if ( ! is_string( $value ) || $value === '' ) {
			return $value;
		}
		if ( self::json_ok( $value ) ) {
			return $value;
		}
		$current = $value;
		for ( $i = 0; $i < 3; $i++ ) {
			$next = function_exists( 'wp_unslash' ) ? wp_unslash( $current ) : stripslashes( $current );
			if ( $next === $current ) {
				break;
			}
			if ( self::json_ok( $next ) ) {
				return $next;
			}
			$current = $next;
		}
		$stripped = stripslashes( $value );
		if ( $stripped !== $value && self::json_ok( $stripped ) ) {
			return $stripped;
		}
		return $value;
	}

	/**
	 * @param mixed $value
	 * @return bool
	 */
	public static function json_ok( $value ) {
		if ( ! is_string( $value ) || $value === '' ) {
			return false;
		}
		$d = json_decode( $value, true );
		return is_array( $d );
	}

	/**
	 * Persist meta, slashing strings so `update_post_meta()` unslash is a no-op
	 * on JSON escape sequences.
	 *
	 * @param int    $post_id
	 * @param string $key
	 * @param mixed  $value
	 * @return bool
	 */
	public static function write( $post_id, $key, $value ) {
		$post_id = absint( $post_id );
		$key     = (string) $key;
		if ( ! $post_id || $key === '' ) {
			return false;
		}
		if ( self::is_json_key( $key ) ) {
			$value = self::repair_json( $value );
		}
		if ( function_exists( 'wp_slash' ) ) {
			$value = wp_slash( $value );
		}
		return (bool) update_post_meta( $post_id, $key, $value );
	}
}
