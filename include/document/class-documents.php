<?php
namespace CanvaslyLite\Document;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which public post types Canvasly can edit.
 *
 * Reads the `post_types` global setting (default `post` and `page`) and is
 * safe to load before the rest of the plugin so the Gutenberg zero-bootstrap
 * guard can use it.
 */
class Documents {
	const OPTION_KEY = 'canvasly_lite_global_settings';

	/** @var string[]|null */
	private static $enabled = null;
	/** @var mixed */
	private static $enabled_raw = null;

	public static function flush_runtime() {
		self::$enabled     = null;
		self::$enabled_raw = null;
	}

	/**
	 * Built-in or plugin types that must never be treated as Canvasly documents.
	 *
	 * @return string[]
	 */
	public static function excluded() {
		return array(
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
			'lb_template',
			'lb_component',
			'lb_global_class',
		);
	}

	/**
	 * @return string[]
	 */
	public static function defaults() {
		return array( 'post', 'page' );
	}

	/**
	 * Sanitize keys, drop empties / excluded slugs, preserve order.
	 *
	 * Does not intersect with registered types so a stored CPT still works
	 * during plugin load, before other plugins register it.
	 *
	 * @param mixed $raw
	 * @return string[]
	 */
	public static function normalize( $raw ) {
		$out = array();
		foreach ( (array) $raw as $type ) {
			$type = sanitize_key( $type );
			if ( $type === '' || self::is_excluded( $type ) ) {
				continue;
			}
			$out[] = $type;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Persistable list: normalize, then keep only currently available public types.
	 *
	 * @param mixed $raw
	 * @return string[]
	 */
	public static function sanitize( $raw ) {
		$allowed = self::available_slugs();
		$out     = array();
		foreach ( self::normalize( $raw ) as $type ) {
			if ( $allowed && ! in_array( $type, $allowed, true ) ) {
				continue;
			}
			$out[] = $type;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Enabled post type slugs from the global setting.
	 *
	 * @return string[]
	 */
	public static function enabled() {
		$option = function_exists( 'get_option' ) ? get_option( self::OPTION_KEY, array() ) : array();
		if ( self::$enabled !== null && self::$enabled_raw === $option ) {
			return self::$enabled;
		}
		if ( ! is_array( $option ) || ! array_key_exists( 'post_types', $option ) ) {
			$types = self::defaults();
		} else {
			$types = self::normalize( $option['post_types'] );
		}
		/**
		 * Filter the post types Canvasly can edit.
		 *
		 * @param string[] $types
		 */
		$filtered = apply_filters( 'canvasly-lite/documents/post_types', $types );
		self::$enabled_raw = $option;
		return self::$enabled = self::normalize( is_array( $filtered ) ? $filtered : $types );
	}

	/**
	 * Whether Canvasly may edit this post type.
	 *
	 * @param string $post_type
	 * @return bool
	 */
	public static function supports( $post_type ) {
		$post_type = sanitize_key( (string) $post_type );
		if ( $post_type === '' ) {
			return false;
		}
		return in_array( $post_type, self::enabled(), true );
	}

	/**
	 * @param mixed $post Post object, post id, or null.
	 * @return bool
	 */
	public static function supports_post( $post ) {
		if ( is_numeric( $post ) && function_exists( 'get_post' ) ) {
			$post = get_post( (int) $post );
		}
		if ( ! is_object( $post ) || empty( $post->post_type ) ) {
			return false;
		}
		return self::supports( $post->post_type );
	}

	/**
	 * Primitive capability used to create posts of this type.
	 *
	 * @param string $post_type
	 * @return string
	 */
	public static function edit_cap( $post_type ) {
		$post_type = sanitize_key( (string) $post_type );
		if ( function_exists( 'get_post_type_object' ) ) {
			$obj = get_post_type_object( $post_type );
			if ( $obj && ! empty( $obj->cap->edit_posts ) ) {
				return (string) $obj->cap->edit_posts;
			}
		}
		return $post_type === 'page' ? 'edit_pages' : 'edit_posts';
	}

	/**
	 * First enabled type, used when creating a document without a type.
	 *
	 * @return string
	 */
	public static function fallback() {
		$enabled = self::enabled();
		if ( in_array( 'page', $enabled, true ) ) {
			return 'page';
		}
		return $enabled[0] ?? '';
	}

	/**
	 * Public, UI-visible post types that can be enabled, keyed by slug.
	 *
	 * @return array<string,string> slug => label
	 */
	public static function available() {
		$out = array();
		if ( function_exists( 'get_post_types' ) ) {
			$objects = get_post_types(
				array(
					'public'  => true,
					'show_ui' => true,
				),
				'objects'
			);
			foreach ( (array) $objects as $slug => $obj ) {
				$slug = sanitize_key( is_object( $obj ) && isset( $obj->name ) ? $obj->name : $slug );
				if ( $slug === '' || self::is_excluded( $slug ) ) {
					continue;
				}
				$label = $slug;
				if ( is_object( $obj ) && isset( $obj->labels ) && is_object( $obj->labels ) && ! empty( $obj->labels->name ) ) {
					$label = (string) $obj->labels->name;
				}
				$out[ $slug ] = $label;
			}
		}
		foreach ( self::defaults() as $slug ) {
			if ( ! isset( $out[ $slug ] ) ) {
				$out[ $slug ] = $slug === 'page' ? __( 'Pages', 'canvasly-lite' ) : __( 'Posts', 'canvasly-lite' );
			}
		}
		$ordered = array();
		foreach ( self::defaults() as $slug ) {
			if ( isset( $out[ $slug ] ) ) {
				$ordered[ $slug ] = $out[ $slug ];
				unset( $out[ $slug ] );
			}
		}
		asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		$out = $ordered + $out;
		/**
		 * Filter the post types offered on the enabled-types setting.
		 *
		 * @param array<string,string> $out slug => label
		 */
		$filtered = apply_filters( 'canvasly-lite/documents/available_post_types', $out );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * @return string[]
	 */
	public static function available_slugs() {
		return array_keys( self::available() );
	}

	/**
	 * @param string $post_type
	 * @return bool
	 */
	public static function is_excluded( $post_type ) {
		return in_array( sanitize_key( $post_type ), self::excluded(), true );
	}
}
