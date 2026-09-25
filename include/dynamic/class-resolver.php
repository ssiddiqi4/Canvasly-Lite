<?php
namespace CanvaslyLite\Dynamic;
use CanvaslyLite\Document\DevMode;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve `_dynamic` bindings (and leftover `{{lb:*}}` tokens) on unit settings.
 */
class Resolver {
	/** @var array */
	private static $context = array();

	public static function set_context( array $ctx ) {
		self::$context = $ctx;
	}

	/**
	 * @param int  $post_id
	 * @param bool $for_canvas Skip executing post content / shortcodes / request params.
	 * @return array
	 */
	public static function context( $post_id = 0, $for_canvas = false ) {
		$ctx = self::$context;
		$post_id = absint( $post_id );
		if ( $post_id ) {
			$ctx['post_id'] = $post_id;
		} elseif ( empty( $ctx['post_id'] ) ) {
			$ctx['post_id'] = 0;
			if ( isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) && isset( $GLOBALS['post']->ID ) ) {
				$ctx['post_id'] = absint( $GLOBALS['post']->ID );
			}
		}
		$ctx['for_canvas'] = (bool) $for_canvas;
		if ( empty( $ctx['post'] ) && ! empty( $ctx['post_id'] ) && function_exists( 'get_post' ) ) {
			$ctx['post'] = get_post( (int) $ctx['post_id'] );
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'canvasly-lite/dynamic_tags/context', $ctx );
			if ( is_array( $filtered ) ) {
				$ctx = $filtered;
			}
		}
		return $ctx;
	}

	/**
	 * Replace bound controls with resolved values. Walks repeater items. Then resolves `{{lb:*}}`.
	 *
	 * @param array $settings
	 * @param array $context
	 * @param array $controls Optional control schema for type-aware image/url extraction.
	 * @return array
	 */
	public static function settings( array $settings, array $context = array(), array $controls = array() ) {
		if ( ! $context ) {
			$context = self::context();
		}
		$map = isset( $settings['_dynamic'] ) && is_array( $settings['_dynamic'] ) ? $settings['_dynamic'] : array();
		foreach ( $map as $key => $binding ) {
			$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $key ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $key ) );
			if ( $key === '' || $key === '_dynamic' ) {
				continue;
			}
			$type = '';
			if ( isset( $controls[ $key ] ) ) {
				$def = $controls[ $key ];
				$type = is_array( $def ) ? (string) ( $def['type'] ?? '' ) : (string) $def;
			}
			$resolved = self::resolve_binding( is_array( $binding ) ? $binding : array(), $context, $type );
			$settings[ $key ] = $resolved['value'];
			if ( $type === 'media' && $resolved['url'] !== '' ) {
				$url_key = preg_replace( '/_id$/', '_url', $key );
				if ( $url_key !== $key ) {
					$settings[ $url_key ] = $resolved['url'];
				}
			}
		}
		foreach ( $settings as $k => $v ) {
			if ( $k === '_dynamic' || ! is_array( $v ) ) {
				continue;
			}
			$fields = array();
			if ( isset( $controls[ $k ] ) && is_array( $controls[ $k ] ) && ( $controls[ $k ]['type'] ?? '' ) === 'repeater' ) {
				$fields = is_array( $controls[ $k ]['fields'] ?? null ) ? $controls[ $k ]['fields'] : array();
			}
			$is_items = $v && array_keys( $v ) === range( 0, count( $v ) - 1 );
			if ( ! $is_items ) {
				continue;
			}
			foreach ( $v as $i => $item ) {
				if ( is_array( $item ) ) {
					$settings[ $k ][ $i ] = self::settings( $item, $context, $fields );
				}
			}
		}
		if ( class_exists( DevMode::class ) ) {
			$post_id = absint( $context['post_id'] ?? 0 );
			$map     = ! empty( $context['for_canvas'] ) ? DevMode::canvas_dynamic_map( $post_id ) : DevMode::frontend_dynamic_map( $post_id );
			$settings = DevMode::resolve_settings( $settings, $map, ! empty( $context['for_canvas'] ) );
		}
		return $settings;
	}

	/**
	 * @param array  $binding {tag, before, after, fallback, ...tag settings}
	 * @param array  $context
	 * @param string $control_type
	 * @return array{value:mixed,url:string,url_key:string}
	 */
	public static function resolve_binding( array $binding, array $context = array(), $control_type = '' ) {
		$empty = array( 'value' => '', 'url' => '', 'url_key' => '' );
		$tag_name = function_exists( 'sanitize_key' ) ? sanitize_key( (string) ( $binding['tag'] ?? '' ) ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) ( $binding['tag'] ?? '' ) ) );
		if ( $tag_name === '' ) {
			return $empty;
		}
		$tags = Tags::ready();
		$tag = $tags->get( $tag_name );
		if ( ! $tag ) {
			$fallback = isset( $binding['fallback'] ) ? (string) $binding['fallback'] : '';
			return array( 'value' => self::wrap( $fallback, $binding ), 'url' => '', 'url_key' => '' );
		}
		$raw = $tag->render( $binding, $context );
		if ( function_exists( 'apply_filters' ) ) {
			$raw = apply_filters( 'canvasly-lite/dynamic_tags/value', $raw, $tag_name, $binding, $context );
		}
		$extracted = self::extract( $raw, $control_type );
		$value = $extracted['value'];
		if ( $control_type === 'media' ) {
			$empty_val = (int) $value === 0 && $extracted['url'] === '';
		} elseif ( $value === null ) {
			$empty_val = true;
		} elseif ( is_string( $value ) ) {
			$empty_val = trim( $value ) === '';
		} else {
			$empty_val = false;
		}
		if ( $empty_val ) {
			$fallback = isset( $binding['fallback'] ) ? $binding['fallback'] : '';
			if ( $control_type === 'media' && is_numeric( $fallback ) ) {
				$value = absint( $fallback );
			} else {
				$value = (string) $fallback;
			}
		}
		if ( $control_type === 'media' ) {
			return array(
				'value'   => is_numeric( $value ) ? absint( $value ) : 0,
				'url'     => $extracted['url'] !== '' ? $extracted['url'] : ( is_string( $value ) && ! is_numeric( $value ) ? $value : '' ),
				'url_key' => 'image_url',
			);
		}
		if ( $control_type === 'number' || $control_type === 'slider' ) {
			if ( is_numeric( $value ) ) {
				return array( 'value' => 0 + $value, 'url' => '', 'url_key' => '' );
			}
			return array( 'value' => $value, 'url' => '', 'url_key' => '' );
		}
		return array( 'value' => self::wrap( (string) $value, $binding ), 'url' => $extracted['url'], 'url_key' => '' );
	}

	/** Prefix/suffix around a resolved string. Empty value is not wrapped. */
	public static function wrap( $value, array $binding ) {
		$value = (string) $value;
		if ( $value === '' ) {
			return '';
		}
		$before = isset( $binding['before'] ) ? (string) $binding['before'] : '';
		$after  = isset( $binding['after'] ) ? (string) $binding['after'] : '';
		return $before . $value . $after;
	}

	/**
	 * @param mixed  $raw
	 * @param string $control_type
	 * @return array{value:mixed,url:string}
	 */
	public static function extract( $raw, $control_type = '' ) {
		if ( is_array( $raw ) ) {
			$id  = absint( $raw['id'] ?? 0 );
			$url = isset( $raw['url'] ) ? (string) $raw['url'] : '';
			if ( $control_type === 'media' ) {
				return array( 'value' => $id, 'url' => $url );
			}
			if ( $control_type === 'url' || $control_type === 'image' ) {
				return array( 'value' => $url !== '' ? $url : (string) $id, 'url' => $url );
			}
			if ( $control_type === 'number' ) {
				return array( 'value' => $id, 'url' => $url );
			}
			return array( 'value' => $url !== '' ? $url : (string) $id, 'url' => $url );
		}
		if ( is_bool( $raw ) ) {
			return array( 'value' => $raw ? '1' : '', 'url' => '' );
		}
		if ( is_int( $raw ) || is_float( $raw ) ) {
			return array( 'value' => $raw, 'url' => '' );
		}
		return array( 'value' => (string) $raw, 'url' => is_string( $raw ) && preg_match( '#^https?://#i', $raw ) ? (string) $raw : '' );
	}

	public static function preview_string( $raw ) {
		$extracted = self::extract( $raw, 'text' );
		$v = $extracted['value'];
		if ( is_array( $v ) ) {
			return '';
		}
		$s = (string) $v;
		$s = wp_strip_all_tags( $s );
		$s = trim( preg_replace( '/\s+/', ' ', $s ) );
		if ( strlen( $s ) > 140 ) {
			$s = substr( $s, 0, 137 ) . '...';
		}
		return $s;
	}

	/**
	 * Sanitize a `_dynamic` map. Unknown tags are dropped. Keys must exist in `$controls` when given.
	 *
	 * @param mixed $map
	 * @param array $controls
	 * @return array
	 */
	public static function sanitize_map( $map, array $controls = array() ) {
		if ( ! is_array( $map ) ) {
			return array();
		}
		$out = array();
		foreach ( $map as $key => $binding ) {
			$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $key ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $key ) );
			if ( $key === '' || $key === '_dynamic' ) {
				continue;
			}
			if ( $controls && ! array_key_exists( $key, $controls ) ) {
				continue;
			}
			$clean = self::sanitize_binding( $binding, $controls[ $key ] ?? array() );
			if ( $clean ) {
				$out[ $key ] = $clean;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $binding
	 * @param mixed $control_def
	 * @return array|null
	 */
	public static function sanitize_binding( $binding, $control_def = array() ) {
		if ( ! is_array( $binding ) ) {
			return null;
		}
		$tag_name = function_exists( 'sanitize_key' ) ? sanitize_key( (string) ( $binding['tag'] ?? '' ) ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) ( $binding['tag'] ?? '' ) ) );
		if ( $tag_name === '' ) {
			return null;
		}
		$tags = class_exists( Tags::class ) ? Tags::ready() : null;
		$tag  = $tags ? $tags->get( $tag_name ) : null;
		if ( $tags && count( $tags->all() ) > 0 && ! $tag ) {
			return null;
		}
		$out = array(
			'tag'      => $tag_name,
			'before'   => self::sanitize_affix( $binding['before'] ?? '' ),
			'after'    => self::sanitize_affix( $binding['after'] ?? '' ),
			'fallback' => self::sanitize_fallback( $binding['fallback'] ?? '', $control_def ),
		);
		$extra = $tag ? $tag->controls() : array();
		foreach ( $extra as $ck => $cdef ) {
			$ck = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $ck ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $ck ) );
			if ( $ck === '' || in_array( $ck, array( 'tag', 'before', 'after', 'fallback' ), true ) ) {
				continue;
			}
			$type = is_array( $cdef ) ? (string) ( $cdef['type'] ?? 'text' ) : 'text';
			$raw  = $binding[ $ck ] ?? ( is_array( $cdef ) ? ( $cdef['default'] ?? '' ) : '' );
			if ( $type === 'switch' ) {
				$out[ $ck ] = ! empty( $raw );
			} elseif ( $type === 'number' ) {
				$out[ $ck ] = is_numeric( $raw ) ? 0 + $raw : '';
			} elseif ( $type === 'select' ) {
				$opts = is_array( $cdef ) && isset( $cdef['options'] ) ? $cdef['options'] : array();
				$val  = is_scalar( $raw ) ? (string) $raw : '';
				$keys = array();
				foreach ( (array) $opts as $ok => $ov ) {
					$keys[] = is_int( $ok ) ? (string) $ov : (string) $ok;
				}
				$out[ $ck ] = ( $keys && ! in_array( $val, $keys, true ) ) ? (string) ( $keys[0] ?? '' ) : ( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $val ) : wp_strip_all_tags( $val ) );
			} else {
				$out[ $ck ] = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $raw ) : wp_strip_all_tags( (string) $raw );
			}
		}
		// Allow a few well-known extra keys even if the registry is not booted (tests, import).
		foreach ( array( 'key', 'format', 'taxonomy', 'separator', 'shortcode', 'source' ) as $ck ) {
			if ( ! array_key_exists( $ck, $out ) && array_key_exists( $ck, $binding ) ) {
				$out[ $ck ] = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $binding[ $ck ] ) : wp_strip_all_tags( (string) $binding[ $ck ] );
			}
		}
		return $out;
	}

	private static function sanitize_affix( $v ) {
		$v = (string) $v;
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $v );
		}
		return trim( wp_strip_all_tags( $v ) );
	}

	private static function sanitize_fallback( $v, $control_def ) {
		$type = is_array( $control_def ) ? (string) ( $control_def['type'] ?? 'text' ) : ( is_string( $control_def ) ? $control_def : 'text' );
		if ( $type === 'media' ) {
			return absint( $v );
		}
		if ( $type === 'url' ) {
			return function_exists( 'esc_url_raw' ) ? esc_url_raw( (string) $v ) : (string) $v;
		}
		if ( $type === 'wysiwyg' || $type === 'html' || $type === 'code' ) {
			return function_exists( 'wp_kses_post' ) ? wp_kses_post( (string) $v ) : wp_strip_all_tags( (string) $v );
		}
		if ( $type === 'textarea' ) {
			return function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( (string) $v ) : (string) $v;
		}
		if ( $type === 'number' || $type === 'slider' ) {
			return is_numeric( $v ) ? 0 + $v : ( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $v ) : (string) $v );
		}
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $v ) : trim( wp_strip_all_tags( (string) $v ) );
	}

	/**
	 * Convert legacy unit-level `dynamic_key` / `dynamic_source` / `dynamic_meta_key` into `_dynamic`.
	 *
	 * @param array  $s
	 * @param string $type Unit type.
	 * @return array
	 */
	public static function migrate_settings( array $s, $type = '' ) {
		if ( isset( $s['_dynamic'] ) && is_array( $s['_dynamic'] ) && $s['_dynamic'] ) {
			return $s;
		}
		$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) ( $s['dynamic_key'] ?? '' ) ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) ( $s['dynamic_key'] ?? '' ) ) );
		$meta = function_exists( 'sanitize_key' ) ? sanitize_key( (string) ( $s['dynamic_meta_key'] ?? '' ) ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) ( $s['dynamic_meta_key'] ?? '' ) ) );
		if ( $key === '' && $meta === '' ) {
			return $s;
		}
		$source = (string) ( $s['dynamic_source'] ?? 'post' );
		if ( ! in_array( $source, array( '', 'post', 'site' ), true ) ) {
			$source = 'post';
		}
		$tag = '';
		if ( $meta !== '' && ( $key === '' || $key === 'content' ) ) {
			$tag = 'post_meta';
		} elseif ( $source === 'site' ) {
			$map = array( 'title' => 'site_title', 'url' => 'site_url', 'excerpt' => 'site_tagline', 'featured_image' => 'site_logo' );
			$tag = $map[ $key ] ?? '';
		} else {
			$map = array(
				'title'          => 'post_title',
				'excerpt'        => 'post_excerpt',
				'content'        => 'post_content',
				'date'           => 'post_date',
				'url'            => 'post_url',
				'featured_image' => 'post_featured_image',
				'author'         => 'author_name',
			);
			$tag = $map[ $key ] ?? '';
		}
		if ( $tag === '' ) {
			return $s;
		}
		$target = self::legacy_target( $s, $type, $tag );
		if ( $target === '' ) {
			return $s;
		}
		$binding = array(
			'tag'      => $tag,
			'before'   => '',
			'after'    => '',
			'fallback' => '',
		);
		if ( $tag === 'post_meta' && $meta !== '' ) {
			$binding['key'] = $meta;
		}
		$s['_dynamic'] = array( $target => $binding );
		return $s;
	}

	private static function legacy_target( array $s, $type, $tag ) {
		if ( $tag === 'post_featured_image' || $tag === 'site_logo' ) {
			foreach ( array( 'image_id', 'image_url', 'url' ) as $k ) {
				if ( array_key_exists( $k, $s ) ) {
					return $k;
				}
			}
		}
		if ( $tag === 'post_url' || $tag === 'site_url' || $tag === 'author_url' ) {
			foreach ( array( 'url', 'link', 'text' ) as $k ) {
				if ( array_key_exists( $k, $s ) ) {
					return $k;
				}
			}
		}
		$preferred = array( 'text', 'title', 'content', 'html', 'quote', 'address', 'shortcode', 'url', 'link', 'image_url', 'image_id' );
		foreach ( $preferred as $k ) {
			if ( array_key_exists( $k, $s ) ) {
				return $k;
			}
		}
		if ( $type === 'heading' || $type === 'button' || $type === 'text' ) {
			return 'text';
		}
		if ( $type === 'image' ) {
			return 'image_id';
		}
		return 'text';
	}

	/** True when a settings map (or its repeaters) has a live binding. */
	public static function settings_are_dynamic( array $settings ) {
		if ( ! empty( $settings['_dynamic'] ) && is_array( $settings['_dynamic'] ) ) {
			return true;
		}
		foreach ( $settings as $k => $v ) {
			if ( $k === '_dynamic' || ! is_array( $v ) ) {
				continue;
			}
			foreach ( $v as $item ) {
				if ( is_array( $item ) && self::settings_are_dynamic( $item ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function node_is_dynamic( array $node ) {
		$s = is_array( $node['settings'] ?? null ) ? $node['settings'] : array();
		if ( self::settings_are_dynamic( $s ) ) {
			return true;
		}
		foreach ( (array) ( $node['children'] ?? array() ) as $child ) {
			if ( is_array( $child ) && self::node_is_dynamic( $child ) ) {
				return true;
			}
		}
		return false;
	}
}
