<?php
namespace CanvaslyLite\Compatibility;

use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Units\UnitRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPML / Polylang: register translatable document strings on save and apply
 * them on the frontend. Document JSON is copied onto translation posts
 * (`wpml-config.xml` + `pll_copy_post_metas`).
 */
class Multilingual {
	const DOMAIN = 'canvasly-lite';
	const GROUP  = 'Canvasly';

	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'canvasly-lite/document/after_save', array( self::class, 'on_after_save' ), 50, 2 );
		add_filter( 'canvasly-lite/unit/settings', array( self::class, 'filter_settings' ), 20, 4 );
		add_action( 'icl_make_duplicate', array( self::class, 'on_wpml_duplicate' ), 10, 4 );
		add_filter( 'pll_copy_post_metas', array( self::class, 'pll_copy_metas' ), 10, 2 );
		add_filter( 'pll_get_post_types', array( self::class, 'pll_post_types' ), 10, 2 );
	}

	/**
	 * Control types whose stored values are user-facing copy.
	 *
	 * @return string[]
	 */
	public static function translatable_types() {
		$types = array( 'text', 'textarea', 'wysiwyg', 'url', 'html' );
		/**
		 * Filter control types registered with WPML / Polylang.
		 *
		 * @param string[] $types
		 */
		$filtered = apply_filters( 'canvasly-lite/multilingual/types', $types );
		return is_array( $filtered ) ? array_values( array_unique( array_map( 'sanitize_key', $filtered ) ) ) : $types;
	}

	/**
	 * Whether a normalized control definition is translatable.
	 *
	 * @param array|string $def
	 * @return bool
	 */
	public static function control_is_translatable( $def ) {
		if ( is_string( $def ) ) {
			$def = array( 'type' => $def );
		}
		if ( ! is_array( $def ) ) {
			return false;
		}
		if ( array_key_exists( 'translatable', $def ) ) {
			return ! empty( $def['translatable'] );
		}
		$type = sanitize_key( (string) ( $def['type'] ?? 'text' ) );
		return in_array( $type, self::translatable_types(), true );
	}

	/**
	 * Collect translatable strings from a document tree.
	 *
	 * @param array $doc
	 * @param int   $post_id
	 * @return array<int,array{name:string,value:string,multiline:bool}>
	 */
	public static function collect( $doc, $post_id = 0 ) {
		$post_id = absint( $post_id );
		$root    = is_array( $doc['root'] ?? null ) ? $doc['root'] : ( is_array( $doc ) && isset( $doc[0] ) ? $doc : array() );
		$out     = array();
		self::walk( $root, $post_id, $out );
		/**
		 * Filter collected document strings before they are registered.
		 *
		 * @param array $out
		 * @param array $doc
		 * @param int   $post_id
		 */
		$filtered = apply_filters( 'canvasly-lite/multilingual/strings', $out, is_array( $doc ) ? $doc : array(), $post_id );
		return is_array( $filtered ) ? array_values( $filtered ) : $out;
	}

	/**
	 * Register strings after a document save.
	 *
	 * @param int   $post_id
	 * @param array $clean
	 */
	public static function on_after_save( $post_id, $clean = array() ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}
		$doc = is_array( $clean ) && isset( $clean['root'] ) ? $clean : ( class_exists( DocumentManager::class ) ? DocumentManager::get( $post_id ) : array() );
		foreach ( self::collect( $doc, $post_id ) as $row ) {
			self::register_string( $row['name'], $row['value'], ! empty( $row['multiline'] ) );
		}
	}

	/**
	 * Overlay translated values onto resolved unit settings.
	 *
	 * @param array  $settings
	 * @param array  $node
	 * @param int    $post_id
	 * @param object $unit
	 * @return array
	 */
	public static function filter_settings( $settings, $node = array(), $post_id = 0, $unit = null ) {
		$settings = is_array( $settings ) ? $settings : array();
		$node     = is_array( $node ) ? $node : array();
		$post_id  = absint( $post_id );
		$id       = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $node['id'] ?? '' ) );
		if ( $id === '' ) {
			return $settings;
		}
		$controls = array();
		if ( is_object( $unit ) && method_exists( $unit, 'all_controls' ) ) {
			$controls = $unit->all_controls();
		}
		return self::translate_settings( $settings, $controls, $post_id, $id );
	}

	/**
	 * @param array  $settings
	 * @param array  $controls
	 * @param int    $post_id
	 * @param string $node_id
	 * @return array
	 */
	public static function translate_settings( $settings, $controls, $post_id, $node_id ) {
		$settings = is_array( $settings ) ? $settings : array();
		$controls = is_array( $controls ) ? $controls : array();
		foreach ( $settings as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( $key === '' || $key === '_dynamic' || strpos( $key, '_' ) === 0 ) {
				continue;
			}
			$def = $controls[ $key ] ?? null;
			if ( is_array( $value ) && self::is_repeater_value( $value, $def ) ) {
				foreach ( $value as $i => $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$item_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $item['_id'] ?? $i ) );
					$fields  = is_array( $def['fields'] ?? null ) ? $def['fields'] : array();
					$settings[ $key ][ $i ] = self::translate_settings( $item, $fields, $post_id, $node_id . '.' . $key . '.' . $item_id );
				}
				continue;
			}
			if ( ! is_string( $value ) || $value === '' ) {
				continue;
			}
			if ( $def !== null && ! self::control_is_translatable( $def ) ) {
				continue;
			}
			if ( $def === null && ! self::heuristic_key( $key ) ) {
				continue;
			}
			$name               = self::string_name( $post_id, $node_id, $key );
			$settings[ $key ] = self::translate_string( $value, $name );
		}
		return $settings;
	}

	/**
	 * WPML duplicate: copy Canvasly meta onto the new language post.
	 *
	 * @param int    $master_id
	 * @param string $lang
	 * @param array  $post_array
	 * @param int    $id
	 */
	public static function on_wpml_duplicate( $master_id, $lang, $post_array, $id ) {
		unset( $lang, $post_array );
		if ( class_exists( Duplicate::class ) ) {
			Duplicate::copy( $master_id, $id );
		}
	}

	/**
	 * @param string[] $keys
	 * @param bool     $sync
	 * @return string[]
	 */
	public static function pll_copy_metas( $keys, $sync = false ) {
		unset( $sync );
		$keys = is_array( $keys ) ? $keys : array();
		if ( class_exists( Meta::class ) ) {
			foreach ( Meta::copyable_keys() as $key ) {
				$keys[] = $key;
			}
			$keys = array_values( array_diff( $keys, Meta::ephemeral_keys() ) );
		} else {
			$keys[] = '_lb_document_data';
			$keys[] = '_lb_document_version';
		}
		return array_values( array_unique( array_map( 'strval', $keys ) ) );
	}

	/**
	 * @param array $types
	 * @param bool  $is_settings
	 * @return array
	 */
	public static function pll_post_types( $types, $is_settings = false ) {
		unset( $is_settings );
		$types = is_array( $types ) ? $types : array();
		$types['lb_template'] = 'lb_template';
		return $types;
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @param bool   $multiline
	 */
	public static function register_string( $name, $value, $multiline = false ) {
		$value = (string) $value;
		$name  = (string) $name;
		if ( $name === '' || $value === '' ) {
			return;
		}
		if ( function_exists( 'has_action' ) && has_action( 'wpml_register_single_string' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML integration hook.
			do_action( 'wpml_register_single_string', self::DOMAIN, $name, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML integration hook.
		}
		if ( function_exists( 'pll_register_string' ) ) {
			pll_register_string( $name, $value, self::GROUP, (bool) $multiline );
		}
	}

	/**
	 * @param string $value
	 * @param string $name
	 * @return string
	 */
	public static function translate_string( $value, $name ) {
		$value = (string) $value;
		$out   = $value;
		if ( function_exists( 'has_filter' ) && has_filter( 'wpml_translate_single_string' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML integration hook.
			$out = apply_filters( 'wpml_translate_single_string', $value, self::DOMAIN, $name ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML integration hook.
			if ( is_string( $out ) && $out !== '' ) {
				return $out;
			}
			$out = $value;
		}
		if ( function_exists( 'pll_translate_string' ) ) {
			$translated = pll_translate_string( $value );
			if ( is_string( $translated ) && $translated !== '' ) {
				return $translated;
			}
		}
		return $out;
	}

	/**
	 * @param string $post_id
	 * @param string $node_id
	 * @param string $key
	 * @return string
	 */
	public static function string_name( $post_id, $node_id, $key ) {
		return absint( $post_id ) . '.' . preg_replace( '/[^a-zA-Z0-9_.-]/', '', (string) $node_id ) . '.' . sanitize_key( $key );
	}

	/**
	 * @param array $nodes
	 * @param int   $post_id
	 * @param array $out
	 */
	private static function walk( $nodes, $post_id, &$out ) {
		foreach ( (array) $nodes as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $n['id'] ?? '' ) );
			if ( $id === '' ) {
				$id = 'n';
			}
			$settings = is_array( $n['settings'] ?? null ) ? $n['settings'] : array();
			$controls = self::controls_for( $n['type'] ?? '' );
			self::collect_settings( $settings, $controls, $post_id, $id, $out );
			if ( ! empty( $n['children'] ) && is_array( $n['children'] ) ) {
				self::walk( $n['children'], $post_id, $out );
			}
		}
	}

	/**
	 * @param array  $settings
	 * @param array  $controls
	 * @param int    $post_id
	 * @param string $node_id
	 * @param array  $out
	 */
	private static function collect_settings( $settings, $controls, $post_id, $node_id, &$out ) {
		foreach ( (array) $settings as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( $key === '' || $key === '_dynamic' || strpos( $key, '_' ) === 0 ) {
				continue;
			}
			$def = $controls[ $key ] ?? null;
			if ( is_array( $value ) && self::is_repeater_value( $value, $def ) ) {
				$fields = is_array( $def['fields'] ?? null ) ? $def['fields'] : array();
				foreach ( $value as $i => $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$item_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $item['_id'] ?? $i ) );
					self::collect_settings( $item, $fields, $post_id, $node_id . '.' . $key . '.' . $item_id, $out );
				}
				continue;
			}
			if ( ! is_string( $value ) || trim( $value ) === '' ) {
				continue;
			}
			if ( $def !== null && ! self::control_is_translatable( $def ) ) {
				continue;
			}
			if ( $def === null && ! self::heuristic_key( $key ) ) {
				continue;
			}
			$out[] = array(
				'name'       => self::string_name( $post_id, $node_id, $key ),
				'value'      => $value,
				'multiline'  => ( is_array( $def ) && in_array( sanitize_key( (string) ( $def['type'] ?? '' ) ), array( 'textarea', 'wysiwyg', 'html' ), true ) ) || strpos( $value, "\n" ) !== false || strlen( $value ) > 80,
			);
		}
	}

	/**
	 * @param string $type
	 * @return array
	 */
	private static function controls_for( $type ) {
		$type = sanitize_key( (string) $type );
		if ( $type === '' || ! class_exists( UnitRegistry::class ) ) {
			return array();
		}
		$el = UnitRegistry::instance()->get( $type );
		if ( ! $el || ! method_exists( $el, 'all_controls' ) ) {
			return array();
		}
		$controls = $el->all_controls();
		return is_array( $controls ) ? $controls : array();
	}

	/**
	 * @param mixed $value
	 * @param mixed $def
	 * @return bool
	 */
	private static function is_repeater_value( $value, $def ) {
		if ( ! is_array( $value ) || $value === array() ) {
			return false;
		}
		if ( is_array( $def ) && sanitize_key( (string) ( $def['type'] ?? '' ) ) === 'repeater' ) {
			return true;
		}
		$first = reset( $value );
		return is_array( $first ) && ( isset( $first['_id'] ) || self::looks_like_repeater_item( $first ) );
	}

	/**
	 * @param array $item
	 * @return bool
	 */
	private static function looks_like_repeater_item( $item ) {
		if ( ! is_array( $item ) ) {
			return false;
		}
		foreach ( array( 'title', 'content', 'text', 'label', 'url', 'caption' ) as $k ) {
			if ( array_key_exists( $k, $item ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fallback when the unit registry is not loaded (tests, early boot).
	 *
	 * @param string $key
	 * @return bool
	 */
	private static function heuristic_key( $key ) {
		$allow = array(
			'text', 'title', 'content', 'html', 'caption', 'alt', 'url', 'link',
			'quote', 'author', 'role', 'label', 'placeholder', 'button_text',
			'description', 'heading', 'name', 'subtitle', 'prefix', 'suffix',
			'message', 'success_message', 'error_message', 'ribbon', 'period',
			'front_title', 'back_title', 'front_text', 'back_text', 'bio',
		);
		return in_array( sanitize_key( $key ), $allow, true );
	}
}
