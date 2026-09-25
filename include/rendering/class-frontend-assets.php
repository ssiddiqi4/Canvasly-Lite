<?php
namespace CanvaslyLite\Rendering;

use CanvaslyLite\Design\Interactions;
use CanvaslyLite\Design\Optimize;
use CanvaslyLite\Units\CollectionLoop;
use CanvaslyLite\Units\Unit;
use CanvaslyLite\Units\UnitRegistry;
use CanvaslyLite\Templates\TemplateEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects script/style handles declared by units on a document tree and
 * enqueues only those the current page uses. Replaces the hardcoded
 * FrontendRenderer::SCRIPTED_TYPES list.
 */
class FrontendAssets {
	const SCRIPT = 'canvasly-lite-frontend';
	const STYLE  = 'canvasly-lite-frontend';

	/**
	 * Unique script and style handles needed by `$nodes`.
	 *
	 * @param array $nodes Document root (or any subtree).
	 * @return array{scripts:string[],styles:string[]}
	 */
	public static function collect( $nodes, $post_id = 0 ) {
		$scripts = array();
		$styles  = array();
		$seen    = array();
		$post_id = absint( $post_id );
		$nodes   = is_array( $nodes ) ? $nodes : array();
		$respect = function_exists( 'has_filter' ) && has_filter( 'canvasly-lite/unit/should_render' );
		if ( $respect ) {
			$nodes = self::omit_hidden( $nodes, $post_id );
		}
		self::walk( $nodes, $scripts, $styles, $seen, $post_id, $respect );
		if ( class_exists( Interactions::class ) && Interactions::has( $nodes ) ) {
			$scripts[ self::SCRIPT ] = true;
		}
		if ( class_exists( Optimize::class ) && Optimize::needs_script( $nodes ) ) {
			$scripts[ self::SCRIPT ] = true;
		}
		$out = array(
			'scripts' => array_keys( $scripts ),
			'styles'  => array_keys( $styles ),
		);
		$filtered = apply_filters( 'canvasly-lite/frontend/assets', $out, $nodes );
		if ( ! is_array( $filtered ) ) {
			return $out;
		}
		return array(
			'scripts' => Unit::normalize_handles( $filtered['scripts'] ?? array() ),
			'styles'  => Unit::normalize_handles( $filtered['styles'] ?? array() ),
		);
	}

	/**
	 * Enqueue collected handles. Unknown/unregistered handles are skipped by WP.
	 *
	 * @param array $nodes
	 * @param int   $post_id
	 */
	public static function enqueue( $nodes, $post_id = 0 ) {
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$assets = self::collect( $nodes, $post_id );
		foreach ( $assets['styles'] as $handle ) {
			wp_enqueue_style( $handle );
		}
		foreach ( $assets['scripts'] as $handle ) {
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * True when the core frontend bundle is among the collected scripts.
	 *
	 * @param array $nodes
	 * @param int   $post_id
	 */
	public static function needs_script( $nodes, $post_id = 0 ) {
		return in_array( self::SCRIPT, self::collect( $nodes, $post_id )['scripts'], true );
	}

	/**
	 * @param array             $nodes
	 * @param array<string,bool> $scripts
	 * @param array<string,bool> $styles
	 * @param array<int,bool>    $seen Template ids already walked.
	 * @param int                $post_id
	 * @param bool               $respect Skip nodes `should_render` rejects, including embeds.
	 */
	private static function walk( $nodes, &$scripts, &$styles, &$seen, $post_id = 0, $respect = false ) {
		$reg = class_exists( UnitRegistry::class ) ? UnitRegistry::instance() : null;
		foreach ( (array) $nodes as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			if ( $respect && class_exists( FrontendRenderer::class ) && ! FrontendRenderer::should_render( $n, $post_id ) ) {
				continue;
			}
			$s    = is_array( $n['settings'] ?? null ) ? $n['settings'] : array();
			$type = (string) ( $n['type'] ?? '' );
			$el   = ( $reg && $type !== '' && method_exists( $reg, 'get' ) ) ? $reg->get( $type ) : null;
			if ( $el && method_exists( $el, 'get_scripts' ) ) {
				foreach ( $el->get_scripts( $s ) as $h ) {
					$scripts[ $h ] = true;
				}
			} elseif ( class_exists( Unit::class ) && Unit::settings_need_frontend( $s ) ) {
				$scripts[ self::SCRIPT ] = true;
			}
			if ( $el && method_exists( $el, 'get_styles' ) ) {
				foreach ( $el->get_styles( $s ) as $h ) {
					$styles[ $h ] = true;
				}
			}
			$tid = self::embedded_template( $type, $s );
			if ( $tid && empty( $seen[ $tid ] ) ) {
				$seen[ $tid ] = true;
				$doc          = class_exists( TemplateEmbed::class ) ? TemplateEmbed::document( $tid ) : null;
				if ( $doc && ! empty( $doc['root'] ) ) {
					self::walk( $doc['root'], $scripts, $styles, $seen, $post_id, $respect );
				}
			}
			if ( ! empty( $n['children'] ) ) {
				self::walk( $n['children'], $scripts, $styles, $seen, $post_id, $respect );
			}
		}
	}

	/**
	 * Template id a node embeds, including a loop-item template chosen by
	 * `canvasly-lite/loop/template_id`.
	 *
	 * @param string $type
	 * @param array  $settings
	 * @return int
	 */
	private static function embedded_template( $type, array $settings ) {
		if ( 'template' === $type ) {
			return absint( $settings['template_id'] ?? 0 );
		}
		if ( 'collection_loop' === $type && class_exists( CollectionLoop::class ) ) {
			return CollectionLoop::embedded_template_id( $settings );
		}
		return 0;
	}

	/**
	 * Drop nodes (and their descendants) that `should_render` rejects.
	 *
	 * @param array $nodes
	 * @param int   $post_id
	 * @return array
	 */
	private static function omit_hidden( $nodes, $post_id ) {
		$out = array();
		foreach ( (array) $nodes as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			if ( class_exists( FrontendRenderer::class ) && ! FrontendRenderer::should_render( $n, $post_id ) ) {
				continue;
			}
			if ( ! empty( $n['children'] ) && is_array( $n['children'] ) ) {
				$n['children'] = self::omit_hidden( $n['children'], $post_id );
			}
			$out[] = $n;
		}
		return $out;
	}
}
