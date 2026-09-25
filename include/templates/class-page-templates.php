<?php
namespace CanvaslyLite\Templates;

use CanvaslyLite\Document\DocumentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Document page templates: Theme default, Full Width, and Canvas.
 *
 * Canvas inherits the theme header and footer when the theme has both. Without
 * them it is a blank HTML document (wp_head / wp_footer only) and the editor
 * provides Header and Footer areas. Full Width keeps the theme header and
 * footer but skips the theme content container.
 */
class PageTemplates {
	const CANVAS     = 'canvas';
	const FULL_WIDTH = 'full_width';
	const DEFAULT    = 'default';

	public static function init() {
		add_filter( 'template_include', array( self::class, 'template_include' ), 99 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * @param mixed $value
	 * @return string default|full_width|canvas
	 */
	public static function normalize( $value ) {
		if ( class_exists( DocumentManager::class ) ) {
			return DocumentManager::normalize_page_template( $value );
		}
		$t = sanitize_key( (string) $value );
		return in_array( $t, array( self::DEFAULT, self::FULL_WIDTH, self::CANVAS ), true ) ? $t : self::DEFAULT;
	}

	/**
	 * Resolved template for a post, filterable by add-ons.
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function for_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return self::DEFAULT;
		}
		$doc = DocumentManager::get( $post_id );
		$tpl = self::normalize( is_array( $doc['settings'] ?? null ) ? ( $doc['settings']['template'] ?? self::DEFAULT ) : self::DEFAULT );
		/**
		 * Filter the page template used for a Canvasly document.
		 *
		 * @param string $tpl     default|full_width|canvas
		 * @param int    $post_id
		 * @param array  $doc
		 */
		$filtered = apply_filters( 'canvasly-lite/document/template', $tpl, $post_id, $doc );
		return self::normalize( is_string( $filtered ) ? $filtered : $tpl );
	}

	/**
	 * PHP file for a template key, or empty string for theme default.
	 *
	 * @param string $template
	 * @param int    $post_id
	 * @return string
	 */
	public static function file_for( $template, $post_id = 0 ) {
		$template = self::normalize( $template );
		$map      = array(
			self::CANVAS     => CANVASLY_LITE_PATH . 'includes/templates/canvas.php',
			self::FULL_WIDTH => CANVASLY_LITE_PATH . 'includes/templates/full-width.php',
		);
		$file     = $map[ $template ] ?? '';
		/**
		 * Filter the PHP file used for a Canvasly page template.
		 *
		 * @param string $file     Absolute path, or empty to keep the theme template.
		 * @param string $template default|full_width|canvas
		 * @param int    $post_id
		 */
		$file = apply_filters( 'canvasly-lite/document/template_file', $file, $template, $post_id );
		if ( is_string( $file ) && $file !== '' && is_readable( $file ) ) {
			return $file;
		}
		return '';
	}

	/**
	 * @param string $template Theme template path.
	 * @return string
	 */
	public static function template_include( $template ) {
		if ( is_admin() || ( function_exists( 'is_embed' ) && is_embed() ) || ! is_singular() ) {
			return $template;
		}
		$post = get_queried_object();
		$id   = ( $post && isset( $post->ID ) ) ? absint( $post->ID ) : 0;
		if ( ! $id ) {
			return $template;
		}
		$file = self::file_for( self::for_post( $id ), $id );
		return $file !== '' ? $file : $template;
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}
		if ( ! is_singular() ) {
			return $classes;
		}
		$id = function_exists( 'get_queried_object_id' ) ? absint( get_queried_object_id() ) : 0;
		if ( ! $id && function_exists( 'get_the_ID' ) ) {
			$id = absint( get_the_ID() );
		}
		if ( ! $id ) {
			return $classes;
		}
		$doc = DocumentManager::get( $id );
		if ( empty( $doc['root'] ) && empty( $doc['settings'] ) ) {
			return $classes;
		}
		$classes[] = 'lb-document';
		$tpl = self::for_post( $id );
		if ( $tpl === self::CANVAS ) {
			$classes[] = 'lb-template-canvas';
		} elseif ( $tpl === self::FULL_WIDTH ) {
			$classes[] = 'lb-template-full-width';
		}
		$custom = trim( (string) ( ( $doc['settings']['body_class'] ?? '' ) ) );
		if ( $custom !== '' ) {
			foreach ( preg_split( '/\s+/', $custom ) as $class ) {
				$class = sanitize_html_class( $class );
				if ( $class !== '' ) {
					$classes[] = $class;
				}
			}
		}
		return $classes;
	}
}
