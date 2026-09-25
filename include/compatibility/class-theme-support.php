<?php
namespace CanvaslyLite\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme integration: `add_theme_support( 'canvasly-lite', $args )`, WordPress
 * `$content_width`, and container / page-title selectors.
 *
 * Example in a theme `functions.php`:
 *
 *   add_theme_support( 'canvasly-lite', array(
 *     'content_width'        => 1140,
 *     'container'            => '.site-main, .entry-content',
 *     'page_title_selector'  => '.entry-title',
 *   ) );
 */
class ThemeSupport {
	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'after_setup_theme', array( self::class, 'sync_content_width' ), 99 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'print_container_css' ), 20 );
	}

	/**
	 * Normalized theme-support map.
	 *
	 * @return array{enabled:bool,content_width:string,container:string,page_title_selector:string}
	 */
	public static function all() {
		$out = array(
			'enabled'             => self::enabled(),
			'content_width'       => self::raw_content_width(),
			'container'           => self::raw_container(),
			'page_title_selector' => self::raw_page_title_selector(),
		);
		/**
		 * Filter the resolved Canvasly theme-support map.
		 *
		 * @param array $out
		 */
		$filtered = apply_filters( 'canvasly-lite/theme_support', $out );
		if ( ! is_array( $filtered ) ) {
			return $out;
		}
		$out['enabled']             = ! empty( $filtered['enabled'] );
		$out['content_width']       = self::normalize_width( $filtered['content_width'] ?? $out['content_width'] );
		$out['container']           = self::sanitize_selectors( $filtered['container'] ?? $out['container'] );
		$out['page_title_selector'] = self::sanitize_selectors( $filtered['page_title_selector'] ?? $out['page_title_selector'] );
		return $out;
	}

	/**
	 * @return bool
	 */
	public static function enabled() {
		if ( function_exists( 'current_theme_supports' ) ) {
			return (bool) current_theme_supports( 'canvasly-lite' );
		}
		if ( function_exists( 'get_theme_support' ) ) {
			return get_theme_support( 'canvasly-lite' ) !== false;
		}
		return false;
	}

	/**
	 * CSS length from theme support or `$GLOBALS['content_width']`.
	 *
	 * @return string
	 */
	public static function content_width() {
		$w = self::all();
		if ( $w['content_width'] !== '' ) {
			return $w['content_width'];
		}
		return self::global_content_width();
	}

	/**
	 * @return string[]
	 */
	public static function container_selectors() {
		$s = self::all();
		return self::split_selectors( $s['container'] );
	}

	/**
	 * @return string
	 */
	public static function page_title_selector() {
		$s = self::all();
		return $s['page_title_selector'];
	}

	/**
	 * Copy a numeric theme `$content_width` into the CSS custom property fallback
	 * only when the kit has not set one. Does not overwrite saved kit values.
	 */
	public static function sync_content_width() {
		self::all();
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}
		if ( self::enabled() ) {
			$classes[] = 'lb-theme-support';
		}
		return $classes;
	}

	/**
	 * Expand theme content containers on Full Width / Canvas templates.
	 */
	public static function print_container_css() {
		$selectors = self::container_selectors();
		if ( ! $selectors ) {
			return;
		}
		$prefixed = array();
		foreach ( $selectors as $sel ) {
			$prefixed[] = 'body.lb-template-full-width ' . $sel;
			$prefixed[] = 'body.lb-template-canvas ' . $sel;
		}
		$css = implode( ',', $prefixed ) . '{width:100%;max-width:none;margin-left:auto;margin-right:auto;padding-left:0;padding-right:0;box-sizing:border-box;}';
		/**
		 * Filter CSS that stretches theme containers on Full Width / Canvas.
		 *
		 * @param string   $css
		 * @param string[] $selectors
		 */
		$css = apply_filters( 'canvasly-lite/theme_support/container_css', $css, $selectors );
		if ( ! is_string( $css ) || $css === '' ) {
			return;
		}
		if ( ! function_exists( 'wp_register_style' ) || ! function_exists( 'wp_add_inline_style' ) ) {
			return;
		}
		wp_register_style( 'canvasly-lite-theme-containers', false, array(), defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : null );
		wp_enqueue_style( 'canvasly-lite-theme-containers' );
		wp_add_inline_style( 'canvasly-lite-theme-containers', wp_strip_all_tags( $css ) );
	}

	/**
	 * Arguments passed to `add_theme_support( 'canvasly-lite', … )`.
	 *
	 * @return array
	 */
	public static function args() {
		if ( ! function_exists( 'get_theme_support' ) ) {
			return array();
		}
		$raw = get_theme_support( 'canvasly-lite' );
		if ( $raw === false || $raw === true ) {
			return array();
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		if ( isset( $raw[0] ) && is_array( $raw[0] ) && self::is_map( $raw[0] ) ) {
			return $raw[0];
		}
		if ( self::is_map( $raw ) ) {
			return $raw;
		}
		return array();
	}

	/**
	 * @return string
	 */
	private static function raw_content_width() {
		$args = self::args();
		if ( isset( $args['content_width'] ) ) {
			return self::normalize_width( $args['content_width'] );
		}
		if ( isset( $args['content-width'] ) ) {
			return self::normalize_width( $args['content-width'] );
		}
		return '';
	}

	/**
	 * @return string
	 */
	private static function raw_container() {
		$args = self::args();
		foreach ( array( 'container', 'containers', 'container_selector', 'container-selector' ) as $k ) {
			if ( ! empty( $args[ $k ] ) ) {
				return self::sanitize_selectors( $args[ $k ] );
			}
		}
		return '';
	}

	/**
	 * @return string
	 */
	private static function raw_page_title_selector() {
		$args = self::args();
		foreach ( array( 'page_title_selector', 'page-title-selector', 'title_selector' ) as $k ) {
			if ( ! empty( $args[ $k ] ) ) {
				return self::sanitize_selectors( $args[ $k ] );
			}
		}
		return '';
	}

	/**
	 * @return string
	 */
	private static function global_content_width() {
		if ( empty( $GLOBALS['content_width'] ) || ! is_numeric( $GLOBALS['content_width'] ) ) {
			return '';
		}
		$n = absint( $GLOBALS['content_width'] );
		return $n > 0 ? $n . 'px' : '';
	}

	/**
	 * @param mixed $v
	 * @return string
	 */
	public static function normalize_width( $v ) {
		if ( is_numeric( $v ) ) {
			$n = absint( $v );
			return $n > 0 ? $n . 'px' : '';
		}
		$s = trim( (string) $v );
		if ( $s === '' ) {
			return '';
		}
		if ( preg_match( '/^\d+(\.\d+)?$/', $s ) ) {
			return $s . 'px';
		}
		$s = preg_replace( '/[{}<>]|expression\s*\(|javascript\s*:|@import/i', '', $s );
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $s ) : $s;
	}

	/**
	 * @param mixed $v
	 * @return string
	 */
	public static function sanitize_selectors( $v ) {
		if ( is_array( $v ) ) {
			$v = implode( ',', $v );
		}
		$s = trim( (string) $v );
		if ( $s === '' ) {
			return '';
		}
		$s = preg_replace( '/[{}<>]|expression|javascript|@import/i', '', $s );
		$s = preg_replace( '/[^a-zA-Z0-9_\-\.\#\[\]=\"\'\,\:\>\+\~\*\(\)\s]/', '', $s );
		return substr( trim( (string) $s ), 0, 400 );
	}

	/**
	 * @param string $s
	 * @return string[]
	 */
	public static function split_selectors( $s ) {
		$out = array();
		foreach ( explode( ',', (string) $s ) as $part ) {
			$part = trim( $part );
			if ( $part !== '' ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/**
	 * @param array $a
	 * @return bool
	 */
	private static function is_map( $a ) {
		if ( ! is_array( $a ) || $a === array() ) {
			return false;
		}
		$keys = array_keys( $a );
		return $keys !== range( 0, count( $a ) - 1 );
	}
}
