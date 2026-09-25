<?php
namespace CanvaslyLite\Templates {

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin-owned record of whether the theme header/footer file should be suppressed.
 *
 * Global state is a site option. A post can opt in or out with its own meta.
 * Missing or empty post meta inherits the global flag. An explicit post value
 * wins, including an explicit off that opts out of a global override.
 *
 * Persisted forms are `1` and `0`, not PHP booleans. WordPress stores `false`
 * as an empty string, which would be indistinguishable from "not set".
 */
class ThemeOverrides {
	const OPTION = 'canvasly_theme_overrides_global';
	const META   = '_canvasly_theme_override';

	/**
	 * @param bool $on
	 * @return bool
	 */
	public static function set_global( $on ) {
		return (bool) update_option( self::OPTION, self::flag( $on ), false );
	}

	/**
	 * Site-wide choice. Null means the option was never saved (or is empty)
	 * and is not an explicit off. Callers that need a suppress/don't-suppress
	 * boolean must treat null as off; should_suppress() does that.
	 *
	 * @return bool|null
	 */
	public static function global_enabled() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}
		$value = get_option( self::OPTION, null );
		return self::read_flag( $value );
	}

	/**
	 * Store an explicit per-post choice, or clear it so the post inherits the global flag.
	 *
	 * @param int       $post_id
	 * @param bool|null $on Null deletes the meta key.
	 * @return bool
	 */
	public static function set_post( $post_id, $on ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}
		if ( null === $on ) {
			return (bool) delete_post_meta( $post_id, self::META );
		}
		return (bool) update_post_meta( $post_id, self::META, self::flag( $on ) );
	}

	/**
	 * Explicit per-post choice. Null means inherit the global flag.
	 *
	 * @param int $post_id
	 * @return bool|null
	 */
	public static function post_override( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! function_exists( 'metadata_exists' ) || ! metadata_exists( 'post', $post_id, self::META ) ) {
			return null;
		}
		$value = get_post_meta( $post_id, self::META, true );
		// Empty meta is inherit, same as a key that was never written.
		// '0' and '1' stay explicit; do not use empty() or a truthy cast.
		return self::read_flag( $value );
	}

	/**
	 * Whether this request should suppress the theme header/footer file.
	 *
	 * @param int|null $post_id Current singular post when null.
	 * @return bool
	 */
	public static function should_suppress( $post_id = null ) {
		$post_id = self::resolve_post_id( $post_id );
		$post    = $post_id ? self::post_override( $post_id ) : null;
		if ( null !== $post ) {
			return $post;
		}
		return true === self::global_enabled();
	}

	/**
	 * @param int|null $post_id
	 * @return int
	 */
	private static function resolve_post_id( $post_id ) {
		if ( null !== $post_id && '' !== $post_id ) {
			return absint( $post_id );
		}
		if ( function_exists( 'is_singular' ) && ! is_singular() ) {
			return 0;
		}
		if ( function_exists( 'get_queried_object_id' ) ) {
			$id = absint( get_queried_object_id() );
			if ( $id && ( ! function_exists( 'get_post' ) || get_post( $id ) ) ) {
				return $id;
			}
		}
		if ( function_exists( 'get_the_ID' ) ) {
			return absint( get_the_ID() );
		}
		return 0;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function flag( $value ) {
		return self::is_on( $value ) ? '1' : '0';
	}

	/**
	 * Map a stored flag to an explicit choice. Null and '' are not choices.
	 * '0' is off. Anything else unrecognized inherits.
	 *
	 * @param mixed $value
	 * @return bool|null
	 */
	private static function read_flag( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( self::is_on( $value ) ) {
			return true;
		}
		if ( self::is_off( $value ) ) {
			return false;
		}
		return null;
	}

	/**
	 * @param mixed $value
	 * @return bool
	 */
	private static function is_on( $value ) {
		return true === $value || 1 === $value || '1' === $value;
	}

	/**
	 * Explicit off. Empty string, null, and other junk are not a choice.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private static function is_off( $value ) {
		return false === $value || 0 === $value || '0' === $value;
	}
}

}

namespace {
	if ( ! function_exists( 'canvasly_should_suppress_theme_file' ) ) {
		/**
		 * Whether the current request should suppress the theme header/footer file.
		 *
		 * Post meta `_canvasly_theme_override` wins when it is an explicit `1` or `0`.
		 * Otherwise the site option `canvasly_theme_overrides_global` applies.
		 *
		 * @param int|null $post_id
		 * @return bool
		 */
		function canvasly_should_suppress_theme_file( $post_id = null ) {
			return \CanvaslyLite\Templates\ThemeOverrides::should_suppress( $post_id );
		}
	}
}
