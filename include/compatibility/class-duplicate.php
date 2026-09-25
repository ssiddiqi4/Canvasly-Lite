<?php
namespace CanvaslyLite\Compatibility;

use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Design\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copy Canvasly document meta when a post is duplicated by another plugin.
 */
class Duplicate {
	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'dp_duplicate_post', array( self::class, 'from_duplicate_post' ), 10, 2 );
		add_action( 'dp_duplicate_page', array( self::class, 'from_duplicate_post' ), 10, 2 );
		add_filter( 'duplicate_post_excludelist_filter', array( self::class, 'exclude_keys' ) );
		add_action( 'woocommerce_product_duplicate', array( self::class, 'from_woocommerce' ), 10, 2 );
		add_action( 'mtphr_post_duplicator_created', array( self::class, 'from_ids' ), 10, 2 );
	}

	/**
	 * Yoast Duplicate Post and similar: ($new_id, $original_post).
	 *
	 * @param int               $new_id
	 * @param \WP_Post|int|null $post
	 */
	public static function from_duplicate_post( $new_id, $post = null ) {
		$from = 0;
		if ( is_object( $post ) && isset( $post->ID ) ) {
			$from = (int) $post->ID;
		} elseif ( is_numeric( $post ) ) {
			$from = absint( $post );
		}
		self::copy( $from, $new_id );
	}

	/**
	 * @param object $duplicate
	 * @param object $product
	 */
	public static function from_woocommerce( $duplicate, $product ) {
		$from = ( is_object( $product ) && method_exists( $product, 'get_id' ) ) ? (int) $product->get_id() : 0;
		$to   = ( is_object( $duplicate ) && method_exists( $duplicate, 'get_id' ) ) ? (int) $duplicate->get_id() : 0;
		self::copy( $from, $to );
	}

	/**
	 * @param int $new_id
	 * @param int $original_id
	 */
	public static function from_ids( $new_id, $original_id ) {
		self::copy( $original_id, $new_id );
	}

	/**
	 * Prefix-matched exclude list used by Yoast Duplicate Post.
	 *
	 * @param string[] $list
	 * @return string[]
	 */
	public static function exclude_keys( $list ) {
		$list = is_array( $list ) ? $list : array();
		foreach ( class_exists( Meta::class ) ? Meta::ephemeral_keys() : array( '_lb_css_cache', '_lb_autosave_data' ) as $key ) {
			if ( ! in_array( $key, $list, true ) ) {
				$list[] = $key;
			}
		}
		if ( ! in_array( '_lb_lock_', $list, true ) ) {
			$list[] = '_lb_lock_';
		}
		return $list;
	}

	/**
	 * Copy Canvasly meta from one post to another and drop caches/locks.
	 *
	 * @param int $from_id
	 * @param int $to_id
	 * @return bool
	 */
	public static function copy( $from_id, $to_id ) {
		$from_id = absint( $from_id );
		$to_id   = absint( $to_id );
		if ( ! $from_id || ! $to_id || $from_id === $to_id ) {
			return false;
		}

		$copied = 0;
		foreach ( self::source_meta( $from_id ) as $key => $values ) {
			if ( ! class_exists( Meta::class ) || Meta::is_ephemeral( $key ) || ! Meta::is_ours( $key ) ) {
				continue;
			}
			if ( ! in_array( $key, Meta::copyable_keys(), true ) && ! Meta::is_json_key( $key ) ) {
				continue;
			}
			$values = is_array( $values ) ? $values : array( $values );
			$value  = reset( $values );
			Meta::write( $to_id, $key, $value );
			$copied++;
		}

		self::drop_ephemeral( $to_id );

		if ( class_exists( Performance::class ) ) {
			Performance::invalidate( $to_id );
		} elseif ( class_exists( DocumentManager::class ) ) {
			delete_post_meta( $to_id, DocumentManager::CSS_CACHE );
		} else {
			delete_post_meta( $to_id, '_lb_css_cache' );
		}

		/**
		 * Fires after Canvasly meta has been copied onto a duplicated post.
		 *
		 * @param int $to_id
		 * @param int $from_id
		 * @param int $copied Number of meta values written.
		 */
		do_action( 'canvasly-lite/document/duplicated', $to_id, $from_id, $copied );
		return $copied > 0;
	}

	/**
	 * @param int $post_id
	 * @return array<string,array>
	 */
	public static function source_meta( $post_id ) {
		$post_id = absint( $post_id );
		if ( function_exists( 'get_post_custom' ) ) {
			$custom = get_post_custom( $post_id );
			if ( is_array( $custom ) && $custom ) {
				return $custom;
			}
		}
		$all = get_post_meta( $post_id );
		if ( is_array( $all ) && $all && ! self::looks_like_single( $all ) ) {
			return $all;
		}
		$out = array();
		$keys = class_exists( Meta::class ) ? Meta::copyable_keys() : array( '_lb_document_data' );
		foreach ( $keys as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( $val === '' || $val === null || $val === false ) {
				continue;
			}
			$out[ $key ] = array( $val );
		}
		return $out;
	}

	/**
	 * get_post_meta( $id ) without a key returns key => list. A single-key
	 * test stub that forgot that shape is detected here.
	 *
	 * @param array $all
	 * @return bool
	 */
	private static function looks_like_single( $all ) {
		if ( count( $all ) === 1 && isset( $all[0] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param int $post_id
	 */
	public static function drop_ephemeral( $post_id ) {
		$post_id = absint( $post_id );
		foreach ( class_exists( Meta::class ) ? Meta::ephemeral_keys() : array( '_lb_css_cache', '_lb_autosave_data' ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
		if ( function_exists( 'get_post_custom' ) ) {
			$custom = get_post_custom( $post_id );
			if ( is_array( $custom ) ) {
				foreach ( array_keys( $custom ) as $key ) {
					if ( strpos( (string) $key, '_lb_lock_' ) === 0 ) {
						delete_post_meta( $post_id, $key );
					}
				}
			}
		}
	}
}
