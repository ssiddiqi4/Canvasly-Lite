<?php
namespace CanvaslyLite\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects whether the active theme already supplies a header and footer.
 *
 * When both exist, the editor canvas inherits them and does not offer its own
 * Header and Footer areas. When either is missing, those areas are the header
 * and footer.
 */
class ThemeChrome {
	const MAX_HTML = 250000;

	/**
	 * @var bool
	 */
	private static $capturing = false;

	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
	}

	public static function register_route() {
		register_rest_route(
			'canvasly-lite/v1',
			'/theme-chrome',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'rest' ),
				'permission_callback' => array( self::class, 'can_read' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function can_read() {
		if ( class_exists( '\\CanvaslyLite\\Settings\\Roles' ) && ! \CanvaslyLite\Settings\Roles::can_edit() ) {
			return false;
		}
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * @return \WP_REST_Response|array
	 */
	/**
	 * @param mixed $request
	 * @return \WP_REST_Response|array
	 */
	public static function rest( $request = null ) {
		$post_id = 0;
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$post_id = absint( $request->get_param( 'post_id' ) );
		}
		if ( class_exists( ThemeChromeEdits::class ) ) {
			$payload = ThemeChromeEdits::payload( $post_id );
		} else {
			$inherit = self::provides();
			$payload = array(
				'inherit' => $inherit,
				'header'  => $inherit ? self::markup( 'header' ) : '',
				'footer'  => $inherit ? self::markup( 'footer' ) : '',
				'scope'   => 'page',
			);
		}
		return function_exists( 'rest_ensure_response' ) ? rest_ensure_response( $payload ) : $payload;
	}

	/**
	 * Editor flag: inherit only when the theme has both parts.
	 *
	 * @return array{header:bool,footer:bool,inherit:bool}
	 */
	public static function export() {
		$header = self::has( 'header' );
		$footer = self::has( 'footer' );
		return array(
			'header'  => $header,
			'footer'  => $footer,
			'inherit' => $header && $footer,
		);
	}

	/**
	 * Styles the editor iframe needs so an inherited header and footer match the theme.
	 *
	 * Block themes paint layout from global styles, which are not part of the header
	 * fragment. Classic themes need their stylesheet as an absolute URL because the
	 * editor canvas is a srcdoc document.
	 *
	 * @return array{css:string,links:string[]}
	 */
	public static function editor_styles() {
		$css   = '';
		$links = array();
		if ( function_exists( 'wp_get_global_stylesheet' ) ) {
			$css .= (string) wp_get_global_stylesheet( array( 'variables', 'presets', 'styles' ) );
			$css .= (string) wp_get_global_stylesheet( array( 'base-layout-styles' ) );
		}
		if ( function_exists( 'get_stylesheet_uri' ) ) {
			$uri = (string) get_stylesheet_uri();
			if ( '' !== $uri ) {
				$links[] = $uri;
			}
		}
		if ( function_exists( 'get_template_directory_uri' ) && function_exists( 'get_stylesheet' ) && function_exists( 'get_template' ) && get_template() !== get_stylesheet() ) {
			$links[] = trailingslashit( (string) get_template_directory_uri() ) . 'style.css';
		}
		if ( function_exists( 'includes_url' ) ) {
			$rtl     = function_exists( 'is_rtl' ) && is_rtl();
			$links[] = includes_url( 'css/dist/block-library/style' . ( $rtl ? '-rtl' : '' ) . '.min.css' );
		}
		$links = array_values( array_unique( array_filter( $links ) ) );
		return array(
			'css'   => $css,
			'links' => $links,
		);
	}

	/**
	 * @param string $part header|footer
	 * @return bool
	 */
	public static function has( $part ) {
		$part  = $part === 'footer' ? 'footer' : 'header';
		$found = self::classic_file( $part ) || ( self::is_block_theme() && self::block_part( $part ) );
		$found = apply_filters( 'canvasly-lite/theme/has_part', $found, $part );
		return (bool) $found;
	}

	/**
	 * True when the theme can supply both a header and a footer.
	 *
	 * @return bool
	 */
	public static function provides() {
		$on = self::has( 'header' ) && self::has( 'footer' );
		return (bool) apply_filters( 'canvasly-lite/theme/provides_chrome', $on );
	}

	/**
	 * @return bool
	 */
	public static function is_block_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Captured theme markup for the editor canvas. Empty when the part is absent.
	 *
	 * @param string $part header|footer
	 * @return string
	 */
	public static function markup( $part ) {
		$part = $part === 'footer' ? 'footer' : 'header';
		if ( self::$capturing || ! self::has( $part ) ) {
			return '';
		}
		self::$capturing = true;
		ob_start();
		try {
			if ( self::is_block_theme() ) {
				if ( $part === 'header' && function_exists( 'block_header_area' ) ) {
					block_header_area();
				} elseif ( $part === 'footer' && function_exists( 'block_footer_area' ) ) {
					block_footer_area();
				}
			} elseif ( $part === 'header' && function_exists( 'get_header' ) ) {
				get_header();
			} elseif ( $part === 'footer' && function_exists( 'get_footer' ) ) {
				get_footer();
			}
			$html = (string) ob_get_clean();
		} catch ( \Throwable $e ) {
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			$html = '';
		}
		self::$capturing = false;
		return self::fragment( $html );
	}

	/**
	 * @param string $part
	 * @return bool
	 */
	private static function classic_file( $part ) {
		if ( ! function_exists( 'locate_template' ) ) {
			return false;
		}
		$path = locate_template( array( $part . '.php' ), false, false );
		return is_string( $path ) && $path !== '';
	}

	/**
	 * @param string $part
	 * @return bool
	 */
	private static function block_part( $part ) {
		if ( function_exists( 'get_block_template' ) && function_exists( 'get_stylesheet' ) ) {
			$ids = array( get_stylesheet() . '//' . $part );
			if ( function_exists( 'get_template' ) && get_template() !== get_stylesheet() ) {
				$ids[] = get_template() . '//' . $part;
			}
			foreach ( $ids as $id ) {
				$tpl = get_block_template( $id, 'wp_template_part' );
				if ( is_object( $tpl ) && ! empty( $tpl->content ) ) {
					return true;
				}
			}
		}
		$dirs = array();
		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$dirs[] = get_stylesheet_directory();
		}
		if ( function_exists( 'get_template_directory' ) ) {
			$dirs[] = get_template_directory();
		}
		foreach ( array_unique( $dirs ) as $dir ) {
			$dir = rtrim( (string) $dir, '/\\' );
			if ( $dir === '' ) {
				continue;
			}
			if ( is_readable( $dir . '/parts/' . $part . '.html' ) || is_readable( $dir . '/block-template-parts/' . $part . '.html' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keep the visible header/footer fragment and its styles. Drop the document
	 * shell and active content so the editor iframe can show the theme chrome.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function fragment( $html ) {
		$html = (string) $html;
		if ( $html === '' ) {
			return '';
		}
		$styles = '';
		if ( preg_match( '#<head\b[^>]*>(.*?)</head>#is', $html, $head ) ) {
			if ( preg_match_all( '#<link\b[^>]*>#i', $head[1], $links ) ) {
				foreach ( $links[0] as $tag ) {
					if ( preg_match( '#\brel\s*=\s*([\'"])stylesheet\1#i', $tag ) || preg_match( '#\brel\s*=\s*stylesheet\b#i', $tag ) ) {
						$styles .= $tag;
					}
				}
			}
			if ( preg_match_all( '#<style\b[^>]*>.*?</style>#is', $head[1], $blocks ) ) {
				$styles .= implode( '', $blocks[0] );
			}
			$html = preg_replace( '#<head\b[^>]*>.*?</head>#is', '', $html );
		}
		$html = preg_replace( '#<!DOCTYPE[^>]*>#i', '', (string) $html );
		$html = preg_replace( '#</?(?:html|body)\b[^>]*>#i', '', (string) $html );
		$html = self::strip_active( $styles . (string) $html );
		if ( strlen( $html ) > self::MAX_HTML ) {
			$html = substr( $html, 0, self::MAX_HTML );
		}
		return trim( $html );
	}

	/**
	 * @param string $html
	 * @return string
	 */
	/**
	 * Drop scripts and inline handlers from theme or editor HTML.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function safe_html( $html ) {
		return self::strip_active( (string) $html );
	}

	/**
	 * @param string $html
	 * @return string
	 */
	private static function strip_active( $html ) {
		$html = preg_replace( '#<(script|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', (string) $html );
		$html = preg_replace( '#<(script|iframe|object|embed)\b[^>]*\/?>#i', '', (string) $html );
		$html = preg_replace( '#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', (string) $html );
		$html = preg_replace( '#javascript\s*:#i', '', (string) $html );
		return (string) $html;
	}
}
