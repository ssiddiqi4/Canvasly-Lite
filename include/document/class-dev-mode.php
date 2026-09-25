<?php
namespace CanvaslyLite\Document;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Back development-mode sanitizer.
 *
 * AI layout payloads can include raw HTML, CSS expressions, PHP tags, or
 * mustache-style tokens that look like they should be evaluated. This class
 * never eval()s, never instantiates Function, and never runs arbitrary
 * expressions. Tokens resolve only from an allowlist; everything else is
 * stripped before the editor canvas or a preview render sees it.
 */
class DevMode {
	const DYNAMIC_KEYS = array( 'title', 'excerpt', 'url', 'featured_image', 'author', 'date' );
	const HTML_KEYS    = array( 'html', 'content', 'text', 'quote', 'front', 'back', 'front_text', 'back_text', 'tabs', 'description', 'features' );
	const CSS_KEYS     = array( 'custom_css', 'background_gradient', 'filter', 'transform', 'text_shadow', 'box_shadow', 'background', 'shadow' );
	const URL_KEYS     = array( 'url', 'link', 'image_url', 'background_image', 'background_video', 'background_video_poster' );

	/**
	 * Whether Back development mode is on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( defined( 'CANVASLY_LITE_DEV_MODE' ) ) {
			$on = (bool) CANVASLY_LITE_DEV_MODE;
		} else {
			$on = ( defined( 'WP_DEBUG' ) && WP_DEBUG );
			if ( ! $on && function_exists( 'wp_get_environment_type' ) ) {
				$on = in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
			}
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'canvasly-lite/dev-mode/enabled', $on );
			if ( is_bool( $filtered ) || is_int( $filtered ) || is_string( $filtered ) ) {
				$on = (bool) $filtered;
			}
		}
		return $on;
	}

	/**
	 * Sanitize a document tree for storage, preview, or canvas.
	 *
	 * @param array $doc
	 * @return array
	 */
	public static function sanitize_document( $doc ) {
		$schema = class_exists( DocumentManager::class, false ) ? DocumentManager::SCHEMA : '2.1';
		if ( ! is_array( $doc ) ) {
			return array(
				'version'  => $schema,
				'root'     => array(),
				'settings' => array(),
			);
		}
		$out = array(
			'version'  => (string) ( $doc['version'] ?? $schema ),
			'settings' => self::sanitize_settings_map( is_array( $doc['settings'] ?? null ) ? $doc['settings'] : array() ),
			'root'     => array(),
			'header'   => array(),
			'footer'   => array(),
		);
		foreach ( array( 'root', 'header', 'footer' ) as $part ) {
			foreach ( (array) ( $doc[ $part ] ?? array() ) as $node ) {
				$clean = self::sanitize_node( $node );
				if ( $clean ) {
					$out[ $part ][] = $clean;
				}
			}
		}
		return $out;
	}

	/**
	 * Sanitize then resolve allowlisted dynamic tokens without evaluating code.
	 * Used when a document is about to paint on the editor canvas.
	 *
	 * @param array $doc
	 * @param array $context Allowlisted token values (title, excerpt, …).
	 * @return array
	 */
	public static function prepare_for_canvas( array $doc, array $context = array() ) {
		$clean = self::sanitize_document( $doc );
		$ctx   = self::normalize_context( $context, true );
		$walk  = function ( &$nodes ) use ( &$walk, $ctx ) {
			foreach ( $nodes as &$node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
					$node['settings'] = self::resolve_settings( $node['settings'], $ctx, true );
				}
				if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
					$walk( $node['children'] );
				}
			}
			unset( $node );
		};
		$walk( $clean['root'] );
		$walk( $clean['header'] );
		$walk( $clean['footer'] );
		return $clean;
	}

	/**
	 * Resolve `{{lb:*}}` tokens in unit settings. Never eval()s the value.
	 *
	 * @param array $settings
	 * @param array $context
	 * @param bool  $for_canvas When true, skip content/meta execution.
	 * @return array
	 */
	public static function resolve_settings( array $settings, array $context = array(), $for_canvas = false ) {
		$ctx = self::normalize_context( $context, $for_canvas );
		foreach ( $settings as $key => $value ) {
			$settings[ $key ] = self::resolve_value( $value, $ctx, $for_canvas, (string) $key );
		}
		return $settings;
	}

	/**
	 * Safe token map for the editor canvas (no post content, no raw meta).
	 *
	 * @param int $post_id
	 * @return array
	 */
	public static function canvas_dynamic_map( $post_id ) {
		return self::dynamic_map( absint( $post_id ), true );
	}

	/**
	 * Token map for frontend render.
	 *
	 * @param mixed $post Post object or id.
	 * @return array
	 */
	public static function frontend_dynamic_map( $post ) {
		$id = 0;
		if ( is_object( $post ) && isset( $post->ID ) ) {
			$id = absint( $post->ID );
		} elseif ( is_numeric( $post ) ) {
			$id = absint( $post );
		}
		return self::dynamic_map( $id, false );
	}

	/**
	 * Post excerpt without running `the_content`.
	 *
	 * WordPress `get_the_excerpt()` falls back to `wp_trim_excerpt()`, which
	 * applies `the_content`. On a Canvasly page that re-enters the frontend
	 * renderer and exhausts memory while compiling CSS.
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function safe_excerpt( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! function_exists( 'get_post_field' ) ) {
			return '';
		}
		$manual = trim( (string) get_post_field( 'post_excerpt', $post_id ) );
		if ( $manual !== '' ) {
			return wp_strip_all_tags( $manual );
		}
		$text = (string) get_post_field( 'post_content', $post_id );
		if ( $text === '' ) {
			return '';
		}
		if ( function_exists( 'strip_shortcodes' ) ) {
			$text = strip_shortcodes( $text );
		}
		$text = wp_strip_all_tags( $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( $text === '' ) {
			return '';
		}
		$len  = 55;
		$more = ' [&hellip;]';
		if ( function_exists( 'apply_filters' ) ) {
			$filtered_len = apply_filters( 'excerpt_length', $len ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core excerpt length.
			if ( is_numeric( $filtered_len ) && (int) $filtered_len > 0 ) {
				$len = (int) $filtered_len;
			}
			$filtered_more = apply_filters( 'excerpt_more', $more ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core excerpt more string.
			if ( is_scalar( $filtered_more ) ) {
				$more = (string) $filtered_more;
			}
		}
		if ( function_exists( 'wp_trim_words' ) ) {
			return wp_trim_words( $text, $len, $more );
		}
		$words = preg_split( '/\s+/', $text, $len + 1 );
		if ( is_array( $words ) && count( $words ) > $len ) {
			return implode( ' ', array_slice( $words, 0, $len ) ) . $more;
		}
		return $text;
	}

	/**
	 * Strip executable markup from a rich-text / HTML setting.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function sanitize_html( $html ) {
		$html = (string) $html;
		$html = self::strip_eval_constructs( $html );
		$html = preg_replace( '/<\?(?:php|=)?[\s\S]*?\?>/i', '', $html );
		$html = preg_replace( '/<(script|iframe|object|embed|link|meta|base|form|svg|math)\b[^>]*>[\s\S]*?<\/\1>/i', '', $html );
		$html = preg_replace( '/<(script|iframe|object|embed|link|meta|base|form)[^>]*\/?>/i', '', $html );
		$html = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html );
		$html = preg_replace( '/\s(href|src|xlink:href|action|formaction|poster)\s*=\s*(["\']?)\s*(javascript|vbscript|data\s*:\s*text\s*\/\s*html)\b[^"\'>\s]*/i', ' $1=$2', $html );
		$html = preg_replace( '/expression\s*\(|javascript\s*:|vbscript\s*:/i', '', $html );
		if ( function_exists( 'wp_kses_post' ) ) {
			$html = wp_kses_post( $html );
		}
		$html = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html );
		return $html;
	}

	/**
	 * Neutralize CSS that can execute code or break out of a style block.
	 *
	 * @param string $css
	 * @return string
	 */
	public static function sanitize_css( $css ) {
		$css = (string) $css;
		$css = self::strip_eval_constructs( $css );
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$css = wp_strip_all_tags( $css );
		} else {
			$css = preg_replace( '/<[^>]*>/', '', $css );
		}
		$css = preg_replace( '/expression\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding\s*:|@import/i', '', $css );
		$css = preg_replace( '/url\s*\(\s*[\'"]?\s*(javascript|vbscript|data\s*:\s*text)/i', 'url(', $css );
		$css = str_replace( array( '</', '<' ), '', $css );
		return $css;
	}

	/**
	 * Resolve or strip a single string that may contain dynamic tokens.
	 * Unknown tokens and eval-like payloads become empty strings.
	 *
	 * @param string $value
	 * @param array  $context
	 * @param bool   $for_canvas
	 * @param bool   $resolve When false, keep allowlisted tokens instead of substituting values.
	 * @return string
	 */
	public static function sanitize_dynamic_string( $value, array $context = array(), $for_canvas = false, $resolve = true ) {
		$value = (string) $value;
		if ( $value === '' ) {
			return '';
		}
		$value = self::strip_eval_constructs( $value );
		$ctx   = self::normalize_context( $context, $for_canvas );
		if ( preg_match( '/^\{\{lb:([a-z0-9_:-]+)\}\}$/i', trim( $value ), $m ) ) {
			$key = strtolower( $m[1] );
			if ( ! self::is_allowed_token( $key ) ) {
				return '';
			}
			return $resolve ? self::token_value( $key, $ctx, $for_canvas ) : '{{lb:' . $key . '}}';
		}
		$value = preg_replace_callback(
			'/\{\{lb:([a-z0-9_:-]+)\}\}/i',
			function ( $m ) use ( $ctx, $for_canvas, $resolve ) {
				$key = strtolower( $m[1] );
				if ( ! self::is_allowed_token( $key ) ) {
					return '';
				}
				return $resolve ? self::token_value( $key, $ctx, $for_canvas ) : '{{lb:' . $key . '}}';
			},
			$value
		);
		$value = preg_replace( '/\{\{(?!var:[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+|lb:[a-z0-9_:-]+)[\s\S]*?\}\}/i', '', $value );
		return $value;
	}

	private static function is_allowed_token( $key ) {
		if ( in_array( $key, self::DYNAMIC_KEYS, true ) || $key === 'content' ) {
			return true;
		}
		if ( strpos( $key, 'meta:' ) === 0 ) {
			$meta  = substr( $key, 5 );
			$clean = function_exists( 'sanitize_key' ) ? sanitize_key( $meta ) : strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $meta ) );
			return $clean !== '' && $clean === $meta;
		}
		return false;
	}

	private static function sanitize_node( $node ) {
		if ( ! is_array( $node ) ) {
			return null;
		}
		$type = function_exists( 'sanitize_key' ) ? sanitize_key( (string) ( $node['type'] ?? '' ) ) : preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $node['type'] ?? '' ) );
		if ( $type === '' ) {
			return null;
		}
		$out = $node;
		$out['type'] = $type;
		if ( isset( $out['settings'] ) && is_array( $out['settings'] ) ) {
			$out['settings'] = self::sanitize_settings_map( $out['settings'], $type );
		}
		if ( isset( $out['children'] ) && is_array( $out['children'] ) ) {
			$kids = array();
			foreach ( $out['children'] as $child ) {
				$clean = self::sanitize_node( $child );
				if ( $clean ) {
					$kids[] = $clean;
				}
			}
			$out['children'] = $kids;
		}
		return $out;
	}

	private static function sanitize_settings_map( array $settings, $type = '' ) {
		$out = array();
		foreach ( $settings as $key => $value ) {
			$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $key ) : preg_replace( '/[^a-z0-9_-]/i', '', (string) $key );
			if ( $key === '' ) {
				continue;
			}
			$out[ $key ] = self::sanitize_setting_value( $key, $value, $type );
		}
		return $out;
	}

	private static function sanitize_setting_value( $key, $value, $type ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $item ) {
				$k         = is_int( $k ) ? $k : ( function_exists( 'sanitize_key' ) ? sanitize_key( (string) $k ) : preg_replace( '/[^a-z0-9_-]/i', '', (string) $k ) );
				$out[ $k ] = self::sanitize_setting_value( is_string( $k ) ? $k : $key, $item, $type );
			}
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		$value = (string) $value;
		if ( $key === 'code' && $type === 'code' ) {
			return self::strip_eval_constructs( $value );
		}
		if ( in_array( $key, self::URL_KEYS, true ) ) {
			return self::sanitize_url( $value );
		}
		if ( in_array( $key, self::CSS_KEYS, true ) || $key === 'custom_css' ) {
			return self::sanitize_css( self::sanitize_dynamic_string( $value, array(), false, false ) );
		}
		if ( in_array( $key, self::HTML_KEYS, true ) || $key === 'html' ) {
			return self::sanitize_html( self::sanitize_dynamic_string( $value, array(), false, false ) );
		}
		if ( $key === 'dynamic_key' ) {
			$allowed = array_merge( self::DYNAMIC_KEYS, array( '', 'content' ) );
			$key_val = function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) );
			return in_array( $key_val, $allowed, true ) ? $key_val : '';
		}
		if ( $key === 'dynamic_source' ) {
			return in_array( $value, array( '', 'post', 'site' ), true ) ? $value : '';
		}
		if ( $key === 'dynamic_meta_key' ) {
			return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) );
		}
		return self::sanitize_dynamic_string( $value, array(), false, false );
	}

	private static function resolve_value( $value, array $context, $for_canvas, $key ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $item ) {
				$out[ $k ] = self::resolve_value( $item, $context, $for_canvas, is_string( $k ) ? $k : $key );
			}
			return $out;
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}
		$resolved = self::sanitize_dynamic_string( $value, $context, $for_canvas, true );
		if ( in_array( $key, self::HTML_KEYS, true ) || $key === 'html' ) {
			return self::sanitize_html( $resolved );
		}
		if ( in_array( $key, self::CSS_KEYS, true ) ) {
			return self::sanitize_css( $resolved );
		}
		if ( in_array( $key, self::URL_KEYS, true ) ) {
			return self::sanitize_url( $resolved );
		}
		return $resolved;
	}

	private static function token_value( $key, array $context, $for_canvas ) {
		if ( strpos( $key, 'meta:' ) === 0 ) {
			if ( $for_canvas || self::enabled() ) {
				return '';
			}
			$meta_key = substr( $key, 5 );
			$meta_key = function_exists( 'sanitize_key' ) ? sanitize_key( $meta_key ) : strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $meta_key ) );
			if ( $meta_key === '' || ! array_key_exists( 'meta:' . $meta_key, $context ) ) {
				return '';
			}
			$raw = $context[ 'meta:' . $meta_key ];
			return is_scalar( $raw ) ? self::sanitize_html( (string) $raw ) : '';
		}
		if ( $key === 'content' ) {
			if ( $for_canvas || self::enabled() ) {
				return '';
			}
			$raw = isset( $context['content'] ) ? (string) $context['content'] : '';
			return self::sanitize_html( $raw );
		}
		if ( ! in_array( $key, self::DYNAMIC_KEYS, true ) ) {
			return '';
		}
		if ( ! array_key_exists( $key, $context ) ) {
			return '';
		}
		$raw = $context[ $key ];
		return is_scalar( $raw ) ? (string) $raw : '';
	}

	private static function normalize_context( array $context, $for_canvas ) {
		$out = array();
		foreach ( self::DYNAMIC_KEYS as $key ) {
			if ( isset( $context[ $key ] ) && is_scalar( $context[ $key ] ) ) {
				$out[ $key ] = (string) $context[ $key ];
			}
		}
		if ( ! $for_canvas && ! self::enabled() && isset( $context['content'] ) && is_scalar( $context['content'] ) ) {
			$out['content'] = (string) $context['content'];
		}
		foreach ( $context as $key => $value ) {
			if ( strpos( (string) $key, 'meta:' ) === 0 && is_scalar( $value ) && ! $for_canvas && ! self::enabled() ) {
				$out[ $key ] = (string) $value;
			}
		}
		return $out;
	}

	/** @var array<string,array> */
	private static $map_cache = array();

	private static function dynamic_map( $post_id, $for_canvas ) {
		$post_id = absint( $post_id );
		$key     = $post_id . ':' . ( $for_canvas ? 'c' : 'f' );
		if ( isset( self::$map_cache[ $key ] ) ) {
			return self::$map_cache[ $key ];
		}
		$out = array();
		if ( ! $post_id ) {
			return $out;
		}
		if ( function_exists( 'get_the_title' ) ) {
			$out['title'] = (string) get_the_title( $post_id );
		}
		$out['excerpt'] = self::safe_excerpt( $post_id );
		if ( function_exists( 'get_permalink' ) ) {
			$out['url'] = self::sanitize_url( (string) get_permalink( $post_id ) );
		}
		if ( function_exists( 'get_the_post_thumbnail_url' ) ) {
			$out['featured_image'] = self::sanitize_url( (string) get_the_post_thumbnail_url( $post_id, 'full' ) );
		}
		if ( function_exists( 'get_post_field' ) && function_exists( 'get_the_author_meta' ) ) {
			$out['author'] = (string) get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
			$out['date']   = (string) get_post_field( 'post_date', $post_id );
		}
		// Never apply `the_content` here: this map is built from inside that filter
		// while compiling CSS / rendering a Canvasly document.
		if ( ! $for_canvas && ! self::enabled() && function_exists( 'get_post_field' ) ) {
			$out['content'] = self::sanitize_html( (string) get_post_field( 'post_content', $post_id ) );
		}
		self::$map_cache[ $key ] = $out;
		return $out;
	}

	private static function sanitize_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}
		if ( preg_match( '/^\s*(javascript|vbscript|data)\s*:/i', $url ) ) {
			return '';
		}
		if ( function_exists( 'esc_url_raw' ) ) {
			$clean = esc_url_raw( $url );
			return is_string( $clean ) ? $clean : '';
		}
		return $url;
	}

	private static function strip_eval_constructs( $value ) {
		$value = (string) $value;
		$value = preg_replace( '/\b(?:eval|Function|setTimeout|setInterval)\s*\(/i', '(', $value );
		$value = preg_replace( '/new\s+Function\s*\(/i', '(', $value );
		$value = preg_replace( '/\{\{[\s]*[=#\/]|\{%[\s\S]*?%\}/', '', $value );
		return $value;
	}
}
