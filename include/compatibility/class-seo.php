<?php
namespace CanvaslyLite\Compatibility;

use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Rendering\FrontendRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expose rendered Canvasly HTML to Yoast SEO and Rank Math analysis.
 */
class Seo {
	private static $booted    = false;
	private static $rendering = false;
	/** @var array<int,string> */
	private static $html      = array();

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_filter( 'wpseo_pre_analysis_post_content', array( self::class, 'analysis_content' ), 10, 2 );
		add_filter( 'wpseo_sitemap_urlimages', array( self::class, 'sitemap_images' ), 10, 2 );
		add_filter( 'rank_math/researches/parsed_content', array( self::class, 'rank_math_content' ) );
		add_filter( 'rank_math/sitemap/urlimages', array( self::class, 'sitemap_images' ), 10, 2 );
	}

	/**
	 * @param string              $content
	 * @param \WP_Post|int|null   $post
	 * @return string
	 */
	public static function analysis_content( $content, $post = null ) {
		$id = self::post_id( $post );
		if ( ! $id ) {
			return $content;
		}
		$html = self::html( $id );
		if ( $html === '' ) {
			return $content;
		}
		/**
		 * Filter HTML handed to SEO analysis plugins.
		 *
		 * @param string $html
		 * @param int    $id
		 * @param string $content Original post_content.
		 */
		$filtered = apply_filters( 'canvasly-lite/seo/content', $html, $id, (string) $content );
		return is_string( $filtered ) ? $filtered : $html;
	}

	/**
	 * Rank Math only passes the content string.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function rank_math_content( $content ) {
		$post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		return self::analysis_content( $content, $post );
	}

	/**
	 * @param array $images
	 * @param int   $post_id
	 * @return array
	 */
	public static function sitemap_images( $images, $post_id ) {
		$images  = is_array( $images ) ? $images : array();
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return $images;
		}
		$seen = array();
		foreach ( $images as $row ) {
			$src = is_array( $row ) ? (string) ( $row['src'] ?? '' ) : (string) $row;
			if ( $src !== '' ) {
				$seen[ $src ] = true;
			}
		}
		foreach ( self::image_urls( $post_id ) as $url ) {
			if ( $url === '' || isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$images[]     = array( 'src' => $url );
		}
		return $images;
	}

	/**
	 * Rendered document HTML, or extracted text when the renderer is unavailable.
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function html( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || self::$rendering ) {
			return '';
		}
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && \CanvaslyLite\Admin\AdminContext::is_autosave() ) {
			return '';
		}
		if ( isset( self::$html[ $post_id ] ) ) {
			return self::$html[ $post_id ];
		}
		if ( class_exists( DocumentManager::class ) && ! DocumentManager::has( $post_id ) ) {
			return self::$html[ $post_id ] = '';
		}
		$doc = class_exists( DocumentManager::class ) ? DocumentManager::get( $post_id ) : array();
		if ( empty( $doc['root'] ) || ! is_array( $doc['root'] ) ) {
			return self::$html[ $post_id ] = '';
		}
		self::$rendering = true;
		try {
			if ( class_exists( FrontendRenderer::class ) ) {
				$html = FrontendRenderer::render_document( $doc, $post_id );
				return self::$html[ $post_id ] = is_string( $html ) ? $html : '';
			}
			return self::$html[ $post_id ] = self::text_from_document( $doc );
		} finally {
			self::$rendering = false;
		}
	}

	/**
	 * Plain text for analysis when HTML is not needed.
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function plain_text( $post_id ) {
		$html = self::html( $post_id );
		if ( $html === '' ) {
			return '';
		}
		return trim( wp_strip_all_tags( $html ) );
	}

	/**
	 * Image URLs stored on a document (settings `*_url` plus attachment ids).
	 *
	 * @param int $post_id
	 * @return string[]
	 */
	public static function image_urls( $post_id ) {
		$post_id = absint( $post_id );
		$doc     = class_exists( DocumentManager::class ) ? DocumentManager::get( $post_id ) : array();
		$urls    = array();
		self::walk_images( is_array( $doc['root'] ?? null ) ? $doc['root'] : array(), $urls );
		/**
		 * Filter image URLs exposed to SEO sitemaps.
		 *
		 * @param string[] $urls
		 * @param int      $post_id
		 */
		$filtered = apply_filters( 'canvasly-lite/seo/images', array_values( array_unique( $urls ) ), $post_id );
		return is_array( $filtered ) ? array_values( array_filter( array_map( 'strval', $filtered ) ) ) : array_values( array_unique( $urls ) );
	}

	/**
	 * Concatenate translatable-looking strings when the renderer cannot run.
	 *
	 * @param array $doc
	 * @return string
	 */
	public static function text_from_document( $doc ) {
		$parts = array();
		self::walk_text( is_array( $doc['root'] ?? null ) ? $doc['root'] : array(), $parts );
		return trim( implode( "\n", $parts ) );
	}

	/**
	 * @param mixed $post
	 * @return int
	 */
	private static function post_id( $post ) {
		if ( is_object( $post ) && isset( $post->ID ) ) {
			return absint( $post->ID );
		}
		if ( is_numeric( $post ) ) {
			return absint( $post );
		}
		if ( isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) && isset( $GLOBALS['post']->ID ) ) {
			return absint( $GLOBALS['post']->ID );
		}
		if ( function_exists( 'get_the_ID' ) ) {
			return absint( get_the_ID() );
		}
		return 0;
	}

	/**
	 * @param array $nodes
	 * @param array $parts
	 */
	private static function walk_text( $nodes, &$parts ) {
		$keys = array( 'text', 'title', 'content', 'html', 'caption', 'alt', 'quote', 'description', 'heading', 'label', 'button_text', 'bio' );
		foreach ( (array) $nodes as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			$s = is_array( $n['settings'] ?? null ) ? $n['settings'] : array();
			foreach ( $keys as $k ) {
				if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
					$parts[] = $s[ $k ];
				}
			}
			foreach ( $s as $val ) {
				if ( ! is_array( $val ) ) {
					continue;
				}
				foreach ( $val as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					foreach ( $keys as $k ) {
						if ( ! empty( $item[ $k ] ) && is_string( $item[ $k ] ) ) {
							$parts[] = $item[ $k ];
						}
					}
				}
			}
			if ( ! empty( $n['children'] ) ) {
				self::walk_text( $n['children'], $parts );
			}
		}
	}

	/**
	 * @param array $nodes
	 * @param array $urls
	 */
	private static function walk_images( $nodes, &$urls ) {
		foreach ( (array) $nodes as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			$s = is_array( $n['settings'] ?? null ) ? $n['settings'] : array();
			foreach ( $s as $key => $val ) {
				if ( is_string( $val ) && $val !== '' && ( $key === 'image_url' || $key === 'background_image' || substr( (string) $key, -4 ) === '_url' ) && preg_match( '#^https?://#i', $val ) ) {
					$urls[] = $val;
				}
				if ( ( $key === 'image_id' || $key === 'background_image_id' ) && is_numeric( $val ) && function_exists( 'wp_get_attachment_image_url' ) ) {
					$u = wp_get_attachment_image_url( absint( $val ), 'full' );
					if ( $u ) {
						$urls[] = $u;
					}
				}
				if ( is_array( $val ) ) {
					self::walk_images( array( array( 'settings' => $val, 'children' => array() ) ), $urls );
					self::walk_images( $val, $urls );
				}
			}
			if ( ! empty( $n['children'] ) ) {
				self::walk_images( $n['children'], $urls );
			}
		}
	}
}
