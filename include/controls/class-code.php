<?php
namespace CanvaslyLite\Controls;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `code` control: stores source as a string and is edited with WordPress `wp.codeEditor`
 * (CodeMirror) in the panel. Language is taken from the schema `language` key, the
 * sibling `language` setting, or inferred from the setting name (`html`, `custom_css`).
 *
 * HTML values (`html` key or language html) are kses'd. CSS is stripped of tags and
 * expressions. JS/PHP/JSON/text keep the source (null bytes / invalid UTF-8 removed)
 * because they are escaped on output by the Code widget.
 */
class Code {
	const LANGUAGES = array( 'html', 'css', 'javascript', 'json', 'php', 'text' );

	public static function init() {
		$c = Controls::instance();
		$c->register(
			'code',
			array( self::class, 'sanitize' ),
			null,
			array(
				'label'  => __( 'Code', 'canvasly-lite' ),
				'editor' => array( 'languages' => self::LANGUAGES ),
			)
		);
	}

	/**
	 * Enqueue CodeMirror + language modes and return per-language settings for the editor.
	 *
	 * @return array<string,array>
	 */
	public static function enqueue() {
		if ( ! function_exists( 'wp_enqueue_code_editor' ) ) {
			return array();
		}
		$out   = array();
		$types = array(
			'html'       => 'text/html',
			'css'        => 'text/css',
			'javascript' => 'application/javascript',
			'json'       => 'application/json',
			'php'        => 'application/x-httpd-php',
		);
		foreach ( $types as $lang => $mime ) {
			$settings = wp_enqueue_code_editor(
				array(
					'type'       => $mime,
					'codemirror' => array(
						'indentUnit'   => 2,
						'tabSize'      => 2,
						'lineNumbers'  => true,
						'lineWrapping' => true,
					),
				)
			);
			if ( is_array( $settings ) ) {
				$out[ $lang ] = $settings;
			}
		}
		return $out;
	}

	/**
	 * @param mixed  $value
	 * @param string $key
	 * @param array  $settings
	 * @param array  $def Control schema (optional 4th argument from Controls::sanitize).
	 * @return string
	 */
	public static function sanitize( $value, $key = '', array $settings = array(), array $def = array() ) {
		if ( 'html' === $key ) {
			return function_exists( 'wp_kses_post' ) ? wp_kses_post( (string) $value ) : (string) $value;
		}
		$lang = self::language_of( $key, $settings, $def );
		if ( 'css' === $lang && 'code' !== $key ) {
			return self::sanitize_css( $value );
		}
		return self::sanitize_source( $value );
	}

	/**
	 * @param string $key
	 * @param array  $settings
	 * @param array  $def
	 * @return string
	 */
	public static function language_of( $key, array $settings = array(), array $def = array() ) {
		$raw = '';
		if ( ! empty( $def['language'] ) ) {
			$raw = (string) $def['language'];
		} elseif ( 'custom_css' === $key || 'css' === $key ) {
			$raw = 'css';
		} elseif ( 'html' === $key ) {
			$raw = 'html';
		} elseif ( ! empty( $settings['language'] ) ) {
			$raw = (string) $settings['language'];
		}
		return self::normalize_language( $raw );
	}

	/**
	 * @param string $lang
	 * @return string One of self::LANGUAGES.
	 */
	public static function normalize_language( $lang ) {
		$lang = strtolower( trim( (string) $lang ) );
		if ( 'js' === $lang || 'javascript' === $lang ) {
			return 'javascript';
		}
		if ( 'xml' === $lang ) {
			return 'html';
		}
		if ( in_array( $lang, self::LANGUAGES, true ) ) {
			return $lang;
		}
		return 'text';
	}

	/**
	 * Preserve source (Code widget, JSON, PHP, JS) without stripping tags.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_source( $value ) {
		$v = str_replace( "\0", '', (string) $value );
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$v = wp_check_invalid_utf8( $v );
		}
		return $v;
	}

	/**
	 * Same rules as DocumentManager::sanitize_css (tags, expression, javascript:).
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_css( $value ) {
		$css = (string) $value;
		$css = wp_strip_all_tags( $css );
		return preg_replace( '/<[^>]*>|expression\s*\(|javascript\s*:/i', '', $css );
	}
}
