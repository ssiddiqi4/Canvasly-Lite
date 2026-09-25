<?php
namespace CanvaslyLite\Templates;

use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Rendering\FrontendRenderer;
use CanvaslyLite\Utils\Style;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared renderer for saved templates used by the shortcode, Gutenberg block,
 * Template widget and WordPress sidebar widget (Roadmap 5.2).
 */
class TemplateEmbed {
	/** @var array<int,true> */
	private static $stack = array();
	/** @var int */
	private static $prints = 0;

	/**
	 * @param int $id
	 * @return bool
	 */
	public static function is_template( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		if ( class_exists( SavedTemplates::class ) ) {
			return SavedTemplates::is_template( $id );
		}
		return function_exists( 'get_post_type' ) && get_post_type( $id ) === 'lb_template';
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	public static function document( $id ) {
		$id = absint( $id );
		if ( ! $id || ! self::is_template( $id ) ) {
			return null;
		}
		if ( class_exists( SavedTemplates::class ) ) {
			$doc = SavedTemplates::get_document( $id );
			return is_array( $doc ) ? $doc : null;
		}
		$raw = get_post_meta( $id, '_lb_template_data', true );
		$doc = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $doc ) ) {
			return null;
		}
		if ( class_exists( DocumentManager::class ) ) {
			$doc = DocumentManager::migrate( $doc );
		}
		return $doc;
	}

	/**
	 * Published templates always render. Drafts/private render for editors
	 * (Gutenberg server-side preview) but stay hidden from visitors.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function can_render( $id ) {
		$id = absint( $id );
		if ( ! $id || ! self::is_template( $id ) ) {
			return false;
		}
		$post = function_exists( 'get_post' ) ? get_post( $id ) : null;
		if ( ! $post ) {
			return false;
		}
		$status = is_object( $post ) ? (string) ( $post->post_status ?? '' ) : '';
		if ( $status === 'publish' ) {
			return true;
		}
		if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $id ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $title
	 * @return int
	 */
	public static function id_from_title( $title ) {
		$title = trim( (string) $title );
		if ( $title === '' ) {
			return 0;
		}
		$query = new \WP_Query(
			array(
				'post_type'              => 'lb_template',
				'title'                  => $title,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);
		$post = ( $query->have_posts() && isset( $query->posts[0] ) ) ? $query->posts[0] : null;
		return $post && ! empty( $post->ID ) ? absint( $post->ID ) : 0;
	}

	/**
	 * Rewrite `#lb-node-{id}` selectors so they match `.lb-src-{id}` descendants
	 * of a unique embed wrapper (same pattern Collection Loop uses).
	 *
	 * @param string $css
	 * @param string $wrapper_selector e.g. `#lb-embed-12-1` or `#lb-node-abc`
	 * @return string
	 */
	public static function scope_css( $css, $wrapper_selector ) {
		$css               = (string) $css;
		$wrapper_selector  = trim( (string) $wrapper_selector );
		if ( $css === '' || $wrapper_selector === '' ) {
			return $css;
		}
		return (string) preg_replace( '/#lb-node-([a-zA-Z0-9_-]+)/', $wrapper_selector . ' .lb-src-$1', $css );
	}

	/**
	 * CSS for a template scoped under a host node (Template widget).
	 *
	 * @param int    $template_id
	 * @param string $host_id
	 * @return string
	 */
	public static function host_css( $template_id, $host_id ) {
		$template_id = absint( $template_id );
		$host_id     = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $host_id );
		$css         = self::document_css( $template_id );
		if ( $css === '' || $host_id === '' ) {
			return '';
		}
		return self::scope_css( $css, '#lb-node-' . $host_id );
	}

	/**
	 * @param int $template_id
	 * @return string
	 */
	public static function document_css( $template_id ) {
		$doc = self::document( $template_id );
		if ( ! $doc || empty( $doc['root'] ) || ! class_exists( Style::class ) ) {
			return '';
		}
		$css = Style::nodes_css( $doc['root'] );
		if ( ! empty( $doc['settings']['custom_css'] ) && class_exists( DocumentManager::class ) && method_exists( DocumentManager::class, 'sanitize_page_css' ) ) {
			$css .= DocumentManager::sanitize_page_css( $doc['settings']['custom_css'] );
		}
		return is_string( $css ) ? $css : '';
	}

	/**
	 * Full embed used by the shortcode, Gutenberg block and WP widget.
	 *
	 * @param int   $id
	 * @param array $args {
	 *   @type int    $post_id Dynamic-tag context. Defaults to the current post.
	 *   @type string $class   Extra wrapper class.
	 * }
	 * @return string
	 */
	public static function render( $id, $args = array() ) {
		$id   = absint( $id );
		$args = is_array( $args ) ? $args : array();
		if ( ! $id || ! self::can_render( $id ) ) {
			return '';
		}
		if ( ! self::begin( $id ) ) {
			return '';
		}
		try {
			$doc = self::document( $id );
			if ( ! $doc ) {
				return '';
			}
			$nodes = self::region_nodes( $doc, $args );
			if ( ! $nodes ) {
				return '';
			}
			$render_doc         = $doc;
			$render_doc['root'] = $nodes;
			self::enqueue( $render_doc, $id );
			$instance = ++self::$prints;
			$wrap_id  = 'lb-embed-' . $id . '-' . $instance;
			$suffix   = 'e' . $instance;
			$post_id  = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : ( function_exists( 'get_the_ID' ) ? absint( get_the_ID() ) : 0 );
			$html     = '';
			if ( class_exists( FrontendRenderer::class ) && method_exists( FrontendRenderer::class, 'render_nodes' ) ) {
				$html = FrontendRenderer::render_nodes( $nodes, $post_id, array(), $suffix );
			}
			$css = self::scope_css( self::document_css( $id ), '#' . $wrap_id );
			if ( $css !== '' && function_exists( 'wp_add_inline_style' ) ) {
				wp_add_inline_style( 'canvasly-lite-frontend', $css );
			}
			$class = 'lb-template-embed';
			$extra = trim( (string) ( $args['class'] ?? '' ) );
			if ( $extra !== '' ) {
				$class .= ' ' . sanitize_html_class( $extra );
			}
			return '<div id="' . esc_attr( $wrap_id ) . '" class="' . esc_attr( $class ) . '" data-lb-template="' . esc_attr( (string) $id ) . '">' . ( is_string( $html ) ? $html : '' ) . '</div>';
		} finally {
			self::end( $id );
		}
	}

	/**
	 * Nodes for one document region. Theme locations pass `header` or `footer`
	 * when a combined Header & Footer template is printing that part.
	 *
	 * @param array $doc
	 * @param array $args
	 * @return array
	 */
	private static function region_nodes( array $doc, array $args ) {
		$region = 'root';
		if ( isset( $args['region'] ) ) {
			$candidate = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $args['region'] ) : preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $args['region'] ) );
			if ( in_array( $candidate, array( 'root', 'header', 'footer' ), true ) ) {
				$region = $candidate;
			}
		}
		$nodes = isset( $doc[ $region ] ) && is_array( $doc[ $region ] ) ? $doc[ $region ] : array();
		return $nodes;
	}

	/**
	 * Inner HTML for the Template unit. The parent renderer already wraps
	 * `#lb-node-{host_id}`; nested nodes use that id as a suffix.
	 *
	 * @param int    $template_id
	 * @param string $host_id
	 * @param int    $post_id
	 * @return string
	 */
	public static function render_inside( $template_id, $host_id, $post_id = 0 ) {
		$template_id = absint( $template_id );
		$host_id     = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $host_id );
		if ( ! $template_id || ! self::can_render( $template_id ) ) {
			return '';
		}
		if ( ! self::begin( $template_id ) ) {
			return '';
		}
		try {
			$doc = self::document( $template_id );
			if ( ! $doc || empty( $doc['root'] ) || ! is_array( $doc['root'] ) ) {
				return '';
			}
			if ( class_exists( FrontendRenderer::class ) && method_exists( FrontendRenderer::class, 'enqueue_document_assets' ) ) {
				FrontendRenderer::enqueue_document_assets( $doc, $template_id );
			}
			$suffix  = $host_id !== '' ? $host_id : ( 'e' . ( ++self::$prints ) );
			$post_id = absint( $post_id );
			if ( ! $post_id && function_exists( 'get_the_ID' ) ) {
				$post_id = absint( get_the_ID() );
			}
			$html = '';
			if ( class_exists( FrontendRenderer::class ) && method_exists( FrontendRenderer::class, 'render_nodes' ) ) {
				$html = FrontendRenderer::render_nodes( $doc['root'], $post_id, array(), $suffix );
			}
			return is_string( $html ) ? $html : '';
		} finally {
			self::end( $template_id );
		}
	}

	/**
	 * Gutenberg `render_callback`.
	 *
	 * @param array $attrs
	 * @return string
	 */
	public static function block_render( $attrs ) {
		$attrs = is_array( $attrs ) ? $attrs : array();
		$id    = absint( $attrs['id'] ?? $attrs['templateId'] ?? 0 );
		return self::render( $id );
	}

	/**
	 * Shortcode `[canvasly_lite_template id="" title=""]`.
	 *
	 * @param array $atts
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = function_exists( 'shortcode_atts' )
			? shortcode_atts(
				array(
					'id'    => 0,
					'title' => '',
				),
				is_array( $atts ) ? $atts : array(),
				'canvasly_lite_template'
			)
			: ( is_array( $atts ) ? $atts : array() );
		$id = absint( $atts['id'] ?? 0 );
		if ( ! $id && ! empty( $atts['title'] ) ) {
			$id = self::id_from_title( $atts['title'] );
		}
		return self::render( $id );
	}

	/**
	 * @param int $id
	 * @return bool
	 */
	public static function begin( $id ) {
		$id = absint( $id );
		if ( ! $id || isset( self::$stack[ $id ] ) || count( self::$stack ) > 5 ) {
			return false;
		}
		self::$stack[ $id ] = true;
		return true;
	}

	/**
	 * @param int $id
	 */
	public static function end( $id ) {
		unset( self::$stack[ absint( $id ) ] );
	}

	/** @return array<int,true> */
	public static function stack() {
		return self::$stack;
	}

	public static function reset_for_tests() {
		self::$stack  = array();
		self::$prints = 0;
	}

	/**
	 * @param array $doc
	 * @param int   $id
	 */
	private static function enqueue( $doc, $id ) {
		if ( class_exists( FrontendRenderer::class ) && method_exists( FrontendRenderer::class, 'enqueue_document_assets' ) ) {
			FrontendRenderer::enqueue_document_assets( is_array( $doc ) ? $doc : array(), absint( $id ) );
			return;
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'canvasly-lite-frontend' );
		}
	}
}
