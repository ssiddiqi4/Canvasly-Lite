<?php
namespace CanvaslyLite\Convert;

use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Document\Documents;
use CanvaslyLite\Templates\SavedTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert stored third-party builder JSON (`_elementor_data`) into a
 * Canvasly document. Layout sections/columns become containers; widgets
 * are mapped through {@see Map}; responsive suffixes become breakpoint keys;
 * global color binds become `{{var:colors.*}}`.
 */
class Converter {
	const SOURCE_META           = '_elementor_data';
	const SOURCE_EDIT_MODE      = '_elementor_edit_mode';
	const SOURCE_PAGE_SETTINGS  = '_elementor_page_settings';
	const SOURCE_TEMPLATE_TYPE  = '_elementor_template_type';
	const SOURCE_LIBRARY_TYPE   = 'elementor_library';
	const CONVERTED_META        = '_lb_converted_from';
	const CONVERTED_AT          = '_lb_converted_at';

	/** @var array */
	private $report = array();
	/** @var int */
	private $seq = 0;

	public function __construct() {
		$this->reset_report();
	}

	private function reset_report() {
		$this->report = array(
			'mapped'    => 0,
			'unmapped'  => array(),
			'warnings'  => array(),
			'nodes'     => 0,
			'globals'   => 0,
			'layout'    => 0,
		);
		$this->seq = 0;
	}

	/**
	 * Decode stored JSON (string, slashed string, or already-an-array).
	 *
	 * @param mixed $raw
	 * @return array
	 */
	public static function decode( $raw ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || $raw === '' ) {
			return array();
		}
		$try = function ( $s ) {
			$d = json_decode( $s, true );
			return is_array( $d ) ? $d : null;
		};
		$d = $try( $raw );
		if ( $d !== null ) {
			return $d;
		}
		if ( function_exists( 'wp_unslash' ) ) {
			$d = $try( wp_unslash( $raw ) );
			if ( $d !== null ) {
				return $d;
			}
		}
		$d = $try( stripslashes( $raw ) );
		if ( $d !== null ) {
			return $d;
		}
		// Some import/export and migration paths leave `_elementor_data`
		// HTML-entity-encoded (e.g. "&#8221;" instead of a literal quote),
		// which breaks json_decode even though the meta value itself is
		// clearly non-empty. Try decoding entities as a last resort before
		// giving up.
		if ( function_exists( 'wp_specialchars_decode' ) ) {
			$d = $try( wp_specialchars_decode( $raw, ENT_QUOTES ) );
			if ( $d !== null ) {
				return $d;
			}
		}
		$d = $try( html_entity_decode( $raw, ENT_QUOTES ) );
		return is_array( $d ) ? $d : array();
	}

	/**
	 * Convert a source unit list into a Canvasly document.
	 *
	 * @param mixed $units
	 * @param array $page_settings
	 * @return array{document:array,report:array}
	 */
	public function convert_tree( $units, $page_settings = array() ) {
		$this->reset_report();
		$list = is_array( $units ) ? $units : self::decode( $units );
		if ( is_array( $list ) && ! isset( $list[0] ) ) {
			// Elementor's own top-level data is always a plain numeric list;
			// this only fires for some other wrapped source format.
			if ( isset( $list['elements'] ) && is_array( $list['elements'] ) ) {
				$list = $list['elements'];
			} elseif ( isset( $list['units'] ) && is_array( $list['units'] ) ) {
				$list = $list['units'];
			}
		}
		$root = array();
		foreach ( $list as $el ) {
			$node = $this->convert_node( $el );
			if ( $node ) {
				$root[] = $node;
			}
		}
		$settings = $this->convert_page_settings( is_array( $page_settings ) ? $page_settings : array() );
		$doc      = array(
			'version'  => class_exists( DocumentManager::class ) ? DocumentManager::SCHEMA : '2.8',
			'root'     => $root,
			'settings' => $settings,
		);
		$filtered = apply_filters( 'canvasly-lite/convert/document', $doc, $this->report );
		if ( is_array( $filtered ) ) {
			$doc = $filtered;
		}
		return array(
			'document' => $doc,
			'report'   => $this->report,
		);
	}

	/**
	 * @param mixed $el
	 * @return array|null
	 */
	public function convert_node( $el ) {
		if ( ! is_array( $el ) ) {
			return null;
		}
		$kind = sanitize_key( (string) ( $el['elType'] ?? '' ) );
		if ( $kind === '' && ! empty( $el['widgetType'] ) ) {
			$kind = 'widget';
		}
		if ( in_array( $kind, Map::layout_types(), true ) ) {
			return $this->convert_layout( $el, $kind );
		}
		if ( $kind === 'widget' ) {
			return $this->convert_widget( $el );
		}
		if ( $this->child_elements( $el ) ) {
			return $this->convert_layout( $el, Map::LAYOUT_CONTAINER );
		}
		$this->note_unmapped( $kind !== '' ? $kind : 'unknown' );
		return $this->placeholder_html( $kind !== '' ? $kind : 'unknown', $el );
	}

	/**
	 * A node's children. Elementor's own JSON tree always uses the key
	 * "elements" at every level (section > elements > column > elements >
	 * widget), so that is checked first; "units" is accepted as a
	 * fallback for any other source format that already uses that name.
	 *
	 * @param array $el
	 * @return array
	 */
	private function child_elements( $el ) {
		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
			return $el['elements'];
		}
		if ( isset( $el['units'] ) && is_array( $el['units'] ) ) {
			return $el['units'];
		}
		return array();
	}

	/**
	 * @param array  $el
	 * @param string $kind section|column|container
	 * @return array
	 */
	private function convert_layout( $el, $kind ) {
		$src      = is_array( $el['settings'] ?? null ) ? $el['settings'] : array();
		$settings = $this->apply_map( $src, Map::common_settings() );
		$settings = array_merge( $settings, $this->layout_settings( $src, $kind ) );
		$bg       = $this->background_from( $src );
		if ( $bg ) {
			$settings['background'] = $bg;
		}
		$this->apply_globals( $src, $settings, Map::common_settings() );
		$this->shapes_from( $src, $settings );

		$children = array();
		foreach ( $this->child_elements( $el ) as $child ) {
			$node = $this->convert_node( $child );
			if ( $node ) {
				$children[] = $node;
			}
		}

		$node = array(
			'id'       => $this->node_id( $el['id'] ?? '' ),
			'type'     => 'container',
			'settings' => $settings,
			'children' => $children,
		);
		$this->report['layout']++;
		$this->report['nodes']++;
		$this->report['mapped']++;
		$filtered = apply_filters( 'canvasly-lite/convert/node', $node, $el, $kind );
		return is_array( $filtered ) ? $filtered : $node;
	}

	/**
	 * @param array  $src
	 * @param string $kind
	 * @return array
	 */
	private function layout_settings( $src, $kind ) {
		$out = array();
		if ( $kind === Map::LAYOUT_SECTION ) {
			$out['layout']    = 'flex';
			$out['direction'] = 'row';
			$out['wrap']      = 'wrap';
			$out['html_tag']  = 'section';
			$out['align']     = 'stretch';
			$content          = sanitize_key( (string) ( $src['layout'] ?? 'boxed' ) );
			if ( $content === 'boxed' || $content === '' ) {
				$w = $this->slider_string( $src['content_width'] ?? null );
				if ( $w !== '' ) {
					$out['max_width'] = $w;
				} else {
					$out['max_width'] = '1140px';
				}
			}
			$gap = $this->slider_string( $src['gap'] ?? ( $src['flex_gap'] ?? null ) );
			if ( $gap !== '' ) {
				$out['gap'] = $gap;
			}
			$min = $this->slider_string( $src['min_height'] ?? null );
			if ( $min !== '' ) {
				$out['min_height'] = $min;
			}
		} elseif ( $kind === Map::LAYOUT_COLUMN ) {
			$out['layout']    = 'flex';
			$out['direction'] = 'column';
			$out['align']     = 'stretch';
			$width            = $this->column_width( $src );
			if ( $width ) {
				$out['width'] = $width;
			}
			$gap = $this->slider_string( $src['gap'] ?? null );
			if ( $gap !== '' ) {
				$out['gap'] = $gap;
			}
		} else {
			$out['layout'] = 'flex';
			$dir           = sanitize_key( (string) ( $src['flex_direction'] ?? $src['direction'] ?? 'column' ) );
			if ( in_array( $dir, array( 'row', 'column', 'row-reverse', 'column-reverse' ), true ) ) {
				$out['direction'] = $dir;
			}
			$wrap = sanitize_key( (string) ( $src['flex_wrap'] ?? $src['wrap'] ?? '' ) );
			if ( in_array( $wrap, array( 'wrap', 'nowrap', 'wrap-reverse' ), true ) ) {
				$out['wrap'] = $wrap;
			}
			$justify = sanitize_key( (string) ( $src['flex_justify_content'] ?? $src['justify_content'] ?? '' ) );
			if ( $justify !== '' ) {
				$out['justify'] = $justify;
			}
			$align = sanitize_key( (string) ( $src['flex_align_items'] ?? $src['align_items'] ?? '' ) );
			if ( $align !== '' ) {
				$out['align'] = $align;
			}
			$gap = $this->slider_string( $src['flex_gap'] ?? ( $src['gap'] ?? null ) );
			if ( $gap !== '' ) {
				$out['gap'] = $gap;
			}
			$w = $this->slider_string( $src['width'] ?? null );
			if ( $w !== '' ) {
				$out['width'] = $w;
			}
			$min = $this->slider_string( $src['min_height'] ?? null );
			if ( $min !== '' ) {
				$out['min_height'] = $min;
			}
			$max = $this->slider_string( $src['boxed_width'] ?? ( $src['content_width'] ?? null ) );
			$cw  = sanitize_key( (string) ( $src['content_width'] ?? '' ) );
			if ( $cw === 'boxed' && $max !== '' ) {
				$out['max_width'] = $max;
			}
			$link = $this->url_string( $src['link'] ?? null );
			if ( $link !== '' ) {
				$out['link'] = $link;
				if ( $this->url_blank( $src['link'] ) ) {
					$out['link_target'] = '_blank';
				}
			}
			$tag = sanitize_key( (string) ( $src['html_tag'] ?? '' ) );
			if ( $tag !== '' && $tag !== 'div' ) {
				$out['html_tag'] = $tag;
			}
		}
		return $out;
	}

	/**
	 * @param array $src
	 * @return array|string
	 */
	private function column_width( $src ) {
		$map = $this->collect_responsive( $src, '_inline_size' );
		if ( ! $map ) {
			$map = $this->collect_responsive( $src, '_column_size' );
		}
		if ( ! $map ) {
			return '';
		}
		$out = array();
		foreach ( $map as $bp => $val ) {
			$n = is_array( $val ) ? ( $val['size'] ?? $val ) : $val;
			$n = is_numeric( $n ) ? (float) $n : 0;
			if ( $n <= 0 ) {
				continue;
			}
			if ( $n <= 12 ) {
				$n = round( ( $n / 12 ) * 100, 4 );
			}
			$out[ $bp ] = rtrim( rtrim( number_format( $n, 4, '.', '' ), '0' ), '.' ) . '%';
		}
		return $this->collapse_responsive( $out );
	}

	/**
	 * @param array $el
	 * @return array
	 */
	private function convert_widget( $el ) {
		$src_type = (string) ( $el['widgetType'] ?? '' );
		$def      = Map::widget( $src_type );
		$src      = is_array( $el['settings'] ?? null ) ? $el['settings'] : array();
		if ( ! $def ) {
			$this->note_unmapped( $src_type !== '' ? $src_type : 'widget' );
			$node = $this->placeholder_html( $src_type !== '' ? $src_type : 'widget', $el );
			$filtered = apply_filters( 'canvasly-lite/convert/node', $node, $el, 'widget' );
			return is_array( $filtered ) ? $filtered : $node;
		}

		$settings = $this->apply_map( $src, Map::common_settings() );
		$wmap     = is_array( $def['settings'] ?? null ) ? $def['settings'] : array();
		$settings = array_merge( $settings, $this->apply_map( $src, $wmap ) );
		$this->apply_globals( $src, $settings, array_merge( Map::common_settings(), $wmap ) );
		$this->enrich_widget( Map::normalize_type( $src_type ), $src, $settings, $el );

		$children = array();
		foreach ( $this->child_elements( $el ) as $i => $child ) {
			$node = $this->convert_node( $child );
			if ( ! $node ) {
				continue;
			}
			$slot = $this->slot_for_child( $settings, $i );
			if ( $slot !== '' ) {
				$node['slot'] = $slot;
			}
			$children[] = $node;
		}

		$node = array(
			'id'       => $this->node_id( $el['id'] ?? '' ),
			'type'     => $def['type'],
			'settings' => $settings,
		);
		if ( $children ) {
			$node['children'] = $children;
		}
		$this->report['mapped']++;
		$this->report['nodes']++;
		$filtered = apply_filters( 'canvasly-lite/convert/node', $node, $el, 'widget' );
		return is_array( $filtered ) ? $filtered : $node;
	}

	/**
	 * @param array $settings
	 * @param int   $index
	 * @return string
	 */
	private function slot_for_child( $settings, $index ) {
		foreach ( array( 'tabs', 'items' ) as $key ) {
			if ( empty( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				continue;
			}
			$row = $settings[ $key ][ $index ] ?? null;
			if ( is_array( $row ) && ! empty( $row['_id'] ) ) {
				return (string) $row['_id'];
			}
		}
		return '';
	}

	/**
	 * Extra per-widget packing (repeaters, packed backgrounds, video URL fallback).
	 *
	 * @param string $src_type Normalized hyphenated widgetType.
	 * @param array  $src
	 * @param array  $settings
	 * @param array  $el
	 */
	private function enrich_widget( $src_type, $src, &$settings, $el ) {
		switch ( $src_type ) {
			case 'heading':
				if ( empty( $settings['link'] ) && ! empty( $src['link'] ) ) {
					$settings['link'] = $this->url_string( $src['link'] );
				}
				if ( $this->url_blank( $src['link'] ?? null ) ) {
					$settings['link_target'] = '_blank';
				}
				break;
			case 'button':
				if ( $this->url_blank( $src['link'] ?? null ) ) {
					$settings['target'] = '_blank';
				}
				$typo = $this->typography_from( $src, 'typography' );
				if ( $typo ) {
					$settings['typography'] = $typo;
				}
				break;
			case 'image':
				$this->unpack_media( $src['image'] ?? null, $settings, 'image_id', 'image_url' );
				if ( ( $settings['link_to'] ?? '' ) === 'file' ) {
					$settings['lightbox'] = true;
				}
				break;
			case 'icon-list':
				$settings['items'] = $this->repeater(
					$src['icon_list'] ?? array(),
					function ( $row ) {
						$icon = $this->icon_id( $row['selected_icon'] ?? ( $row['icon'] ?? '' ) );
						return array(
							'text' => (string) ( $row['text'] ?? '' ),
							'icon' => $icon,
							'url'  => $this->url_string( $row['link'] ?? '' ),
						);
					}
				);
				break;
			case 'tabs':
				$settings['tabs'] = $this->repeater(
					$src['tabs'] ?? array(),
					function ( $row ) {
						return array(
							'title'   => (string) ( $row['tab_title'] ?? ( $row['title'] ?? '' ) ),
							'content' => (string) ( $row['tab_content'] ?? ( $row['content'] ?? '' ) ),
						);
					}
				);
				break;
			case 'accordion':
			case 'toggle':
				$settings['items'] = $this->repeater(
					$src['tabs'] ?? array(),
					function ( $row ) {
						return array(
							'title'   => (string) ( $row['tab_title'] ?? ( $row['title'] ?? '' ) ),
							'content' => (string) ( $row['tab_content'] ?? ( $row['content'] ?? '' ) ),
						);
					}
				);
				break;
			case 'nested-tabs':
				$settings['tabs'] = $this->repeater(
					$src['tabs'] ?? array(),
					function ( $row ) {
						return array( 'title' => (string) ( $row['tab_title'] ?? ( $row['title'] ?? '' ) ) );
					}
				);
				break;
			case 'wpforms':
				// The WPForms Elementor widget only stores a numeric
				// form_id; build the equivalent shortcode so the
				// generic Shortcode Unit can render it via do_shortcode().
				$form_id = absint( $src['form_id'] ?? 0 );
				if ( $form_id ) {
					$settings['shortcode'] = '[wpforms id="' . $form_id . '"]';
				}
				break;
			case 'nested-accordion':
			case 'nested-toggle':
				$settings['items'] = $this->repeater(
					$src['items'] ?? ( $src['tabs'] ?? array() ),
					function ( $row ) {
						return array( 'title' => (string) ( $row['tab_title'] ?? ( $row['item_title'] ?? ( $row['title'] ?? '' ) ) ) );
					}
				);
				break;
			case 'social-icons':
				$settings['links'] = $this->repeater(
					$src['social_icon_list'] ?? array(),
					function ( $row ) {
						$icon = $this->icon_id( $row['social_icon'] ?? ( $row['icon'] ?? '' ) );
						$url  = $this->url_string( $row['link'] ?? '' );
						$net  = $icon !== '' ? $icon : 'link';
						return array(
							'network' => $net,
							'url'     => $url,
							'icon'    => $icon,
						);
					}
				);
				break;
			case 'testimonial':
				$item = array(
					'quote'     => (string) ( $src['testimonial_content'] ?? ( $src['content'] ?? '' ) ),
					'author'    => (string) ( $src['testimonial_name'] ?? ( $src['name'] ?? '' ) ),
					'role'      => (string) ( $src['testimonial_job'] ?? ( $src['job'] ?? '' ) ),
					'link'      => $this->url_string( $src['link'] ?? '' ),
					'image_id'  => 0,
					'image_url' => '',
				);
				$this->unpack_media( $src['testimonial_image'] ?? ( $src['image'] ?? null ), $item, 'image_id', 'image_url' );
				$settings['items'] = array( $item );
				break;
			case 'form':
				if ( empty( $settings['fields'] ) ) {
					$settings['fields'] = $this->transform_value( 'form_fields', $src['form_fields'] ?? array() );
				}
				break;
			case 'price-table':
				if ( empty( $settings['features'] ) ) {
					$settings['features'] = $this->transform_value( 'price_features', $src['features_list'] ?? array() );
				}
				break;
			case 'image-gallery':
				if ( empty( $settings['ids'] ) ) {
					$settings['ids'] = $this->transform_value( 'gallery', $src['gallery'] ?? array() );
				}
				break;
			case 'image-carousel':
				if ( empty( $settings['slides'] ) ) {
					$settings['slides'] = $this->transform_value( 'carousel', $src['carousel'] ?? array() );
				}
				break;
			case 'video':
				if ( empty( $settings['url'] ) ) {
					foreach ( array( 'youtube_url', 'vimeo_url', 'dailymotion_url', 'insert_url' ) as $k ) {
						$u = $this->url_string( $src[ $k ] ?? '' );
						if ( $u !== '' ) {
							$settings['url'] = $u;
							break;
						}
					}
					if ( empty( $settings['url'] ) ) {
						$hosted = $src['hosted_url'] ?? null;
						$settings['url'] = $this->url_string( is_array( $hosted ) ? ( $hosted['url'] ?? '' ) : $hosted );
					}
				}
				$this->unpack_media( $src['image_overlay'] ?? null, $settings, 'overlay_image_id', 'overlay_image_url' );
				break;
			case 'loop-grid':
			case 'posts':
				$pt = $src['post_type'] ?? ( $src['posts_post_type'] ?? '' );
				if ( is_array( $pt ) ) {
					$pt = reset( $pt );
				}
				if ( $pt ) {
					$settings['post_type'] = sanitize_key( (string) $pt );
				}
				$settings['query_type'] = 'posts';
				break;
		}
	}

	/**
	 * Apply a source→dest setting map, including responsive suffixes.
	 *
	 * @param array $src
	 * @param array $map
	 * @return array
	 */
	public function apply_map( array $src, array $map ) {
		$out = array();
		foreach ( $map as $src_key => $dest ) {
			$tf = '';
			if ( is_array( $dest ) ) {
				$tf   = (string) ( $dest[1] ?? '' );
				$dest = (string) ( $dest[0] ?? '' );
			}
			$dest = (string) $dest;
			if ( $dest === '' ) {
				continue;
			}
			$collected = $this->collect_responsive( $src, $src_key );
			if ( ! $collected ) {
				continue;
			}
			$converted = array();
			foreach ( $collected as $bp => $val ) {
				$converted[ $bp ] = $this->transform_value( $tf, $val, $src, $dest );
			}
			$collapsed = $this->collapse_responsive( $converted );
			if ( $collapsed === '' || $collapsed === null || $collapsed === array() ) {
				continue;
			}
			if ( $tf === 'media' && is_array( $collapsed ) && ! isset( $collapsed['desktop'] ) ) {
				$this->unpack_media( $collapsed, $out, $dest === 'image' ? 'image_id' : ( $dest . '_id' ), $dest === 'image' ? 'image_url' : ( $dest . '_url' ) );
				continue;
			}
			$out[ $dest ] = $collapsed;
		}
		return $out;
	}

	/**
	 * Collect desktop + breakpoint variants of a source key.
	 *
	 * @param array  $src
	 * @param string $key
	 * @return array<string,mixed>
	 */
	public function collect_responsive( array $src, $key ) {
		$out  = array();
		$bmap = Map::breakpoints();
		foreach ( $bmap as $suffix => $bp ) {
			$k = $key . $suffix;
			if ( array_key_exists( $k, $src ) && $src[ $k ] !== '' && $src[ $k ] !== null ) {
				$out[ $bp ] = $src[ $k ];
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $map
	 * @return mixed
	 */
	private function collapse_responsive( array $map ) {
		$map = array_filter(
			$map,
			function ( $v ) {
				return $v !== '' && $v !== null && $v !== array();
			}
		);
		if ( ! $map ) {
			return '';
		}
		$keys = array_keys( $map );
		if ( count( $map ) === 1 && $keys[0] === 'desktop' ) {
			return $map['desktop'];
		}
		return $map;
	}

	/**
	 * @param string $tf
	 * @param mixed  $val
	 * @param array  $src
	 * @param string $dest
	 * @return mixed
	 */
	public function transform_value( $tf, $val, $src = array(), $dest = '' ) {
		switch ( $tf ) {
			case 'slider':
				return $this->slider_string( $val );
			case 'slider_number':
				return $this->slider_number( $val );
			case 'dimensions':
				return $this->dimensions( $val );
			case 'url':
				return $this->url_string( $val );
			case 'icon':
				return $this->icon_id( $val );
			case 'switch':
				return $this->as_bool( $val );
			case 'int':
				return absint( is_array( $val ) ? ( $val['size'] ?? $val['id'] ?? 0 ) : $val );
			case 'media':
				return $val;
			case 'media_url':
				return $this->url_string( is_array( $val ) ? ( $val['url'] ?? '' ) : $val );
			case 'seconds':
				$n = $this->slider_number( $val );
				return $n > 30 ? round( $n / 1000, 3 ) : $n;
			case 'button_size':
				$s = is_array( $val ) ? (string) ( $val['size'] ?? '' ) : (string) $val;
				$m = array( 'xs' => 'xs', 'sm' => 'small', 'md' => 'medium', 'lg' => 'large', 'xl' => 'xl', 'small' => 'small', 'medium' => 'medium', 'large' => 'large' );
				return $m[ $s ] ?? 'medium';
			case 'icon_align':
				$s = (string) $val;
				return ( $s === 'right' || $s === 'after' || $s === 'end' ) ? 'after' : 'before';
			case 'icon_align_lr':
				$s = (string) $val;
				return ( $s === 'left' || $s === 'start' ) ? 'left' : 'right';
			case 'box_position':
				$s = (string) $val;
				if ( in_array( $s, array( 'left', 'right', 'top' ), true ) ) {
					return $s;
				}
				return $s === 'aside' ? 'left' : 'top';
			case 'caption_source':
				$s = (string) $val;
				if ( $s === 'none' || $s === '' ) {
					return 'none';
				}
				return $s === 'custom' ? 'custom' : 'attachment';
			case 'lightbox':
				if ( $val === 'yes' || $val === true || $val === '1' || $val === 1 ) {
					return true;
				}
				return $val === 'no' ? false : ! empty( $val );
			case 'divider_look':
				$s = (string) $val;
				if ( $s === 'text' ) {
					return 'line_text';
				}
				if ( $s === 'icon' ) {
					return 'line_icon';
				}
				return 'line';
			case 'list_view':
				return ( (string) $val === 'inline' ) ? 'inline' : 'traditional';
			case 'tabs_type':
				return ( (string) $val === 'vertical' ) ? 'vertical' : 'horizontal';
			case 'position':
				$s = sanitize_key( (string) $val );
				return in_array( $s, array( 'relative', 'absolute', 'fixed', 'sticky' ), true ) ? $s : '';
			case 'star_unmarked':
				return ( (string) $val === 'outline' ) ? 'outline' : 'solid';
			case 'gallery_order':
				return ! empty( $val ) && $val !== 'none' ? 'random' : 'default';
			case 'loop_pagination':
				$s = sanitize_key( (string) $val );
				if ( in_array( $s, array( 'numbers', 'load_more', 'prev_next' ), true ) ) {
					return $s;
				}
				return $s === '' || $s === 'none' ? 'none' : 'numbers';
			case 'gallery':
				$ids = array();
				foreach ( (array) $val as $item ) {
					$id = is_array( $item ) ? absint( $item['id'] ?? 0 ) : absint( $item );
					if ( $id ) {
						$ids[] = $id;
					}
				}
				return implode( ',', $ids );
			case 'carousel':
				$slides = array();
				foreach ( (array) $val as $item ) {
					if ( ! is_array( $item ) ) {
						$id = absint( $item );
						if ( $id ) {
							$slides[] = array( 'image_id' => $id, 'image_url' => '', 'caption' => '', 'alt' => '', 'link' => '' );
						}
						continue;
					}
					$slides[] = array(
						'image_id'  => absint( $item['id'] ?? 0 ),
						'image_url' => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
						'caption'   => (string) ( $item['caption'] ?? '' ),
						'alt'       => (string) ( $item['alt'] ?? '' ),
						'link'      => $this->url_string( $item['link'] ?? '' ),
					);
				}
				return $slides;
			case 'form_fields':
				$fields = array();
				foreach ( (array) $val as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$type = sanitize_key( (string) ( $row['field_type'] ?? 'text' ) );
					$ok   = array( 'text', 'email', 'tel', 'url', 'textarea', 'select', 'checkbox', 'acceptance' );
					if ( ! in_array( $type, $ok, true ) ) {
						$type = $type === 'number' ? 'text' : ( $type === 'radio' ? 'select' : 'text' );
					}
					if ( $type === 'acceptance' ) {
						$type = 'checkbox';
					}
					$fields[] = array(
						'label'       => (string) ( $row['field_label'] ?? '' ),
						'name'        => sanitize_key( (string) ( $row['custom_id'] ?? ( $row['field_label'] ?? 'field' ) ) ),
						'type'        => $type,
						'required'    => $this->as_bool( $row['required'] ?? false ),
						'placeholder' => (string) ( $row['placeholder'] ?? '' ),
						'options'     => (string) ( $row['field_options'] ?? '' ),
					);
				}
				return $fields;
			case 'price_features':
				$items = array();
				foreach ( (array) $val as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$items[] = array(
						'text' => (string) ( $row['item_text'] ?? ( $row['text'] ?? '' ) ),
						'icon' => $this->icon_id( $row['selected_icon'] ?? ( $row['item_icon'] ?? '' ) ),
					);
				}
				return $items;
			case 'json':
				return is_string( $val ) ? $val : wp_json_encode( $val );
			default:
				if ( is_array( $val ) && array_key_exists( 'size', $val ) ) {
					return $this->slider_string( $val );
				}
				if ( is_array( $val ) && ( isset( $val['url'] ) || isset( $val['is_external'] ) ) ) {
					return $this->url_string( $val );
				}
				if ( is_array( $val ) && ( isset( $val['value'] ) && isset( $val['library'] ) ) ) {
					return $this->icon_id( $val );
				}
				return $val;
		}
	}

	/**
	 * @param mixed $val
	 * @return string
	 */
	public function slider_string( $val ) {
		if ( $val === null || $val === '' ) {
			return '';
		}
		if ( is_numeric( $val ) ) {
			return (string) $val;
		}
		if ( is_string( $val ) ) {
			return $val;
		}
		if ( ! is_array( $val ) ) {
			return '';
		}
		if ( array_key_exists( 'desktop', $val ) || array_key_exists( 'tablet', $val ) || array_key_exists( 'mobile', $val ) ) {
			return $val;
		}
		$size = $val['size'] ?? '';
		if ( $size === '' || $size === null ) {
			return '';
		}
		$unit = (string) ( $val['unit'] ?? 'px' );
		if ( $unit === 'custom' ) {
			$unit = 'px';
		}
		return rtrim( rtrim( (string) $size, ' ' ), '' ) . ( $unit === '' ? '' : $unit );
	}

	/**
	 * @param mixed $val
	 * @return float|int|string
	 */
	public function slider_number( $val ) {
		if ( is_array( $val ) ) {
			$val = $val['size'] ?? 0;
		}
		if ( $val === '' || $val === null ) {
			return '';
		}
		return is_numeric( $val ) ? $val + 0 : 0;
	}

	/**
	 * @param mixed $val
	 * @return array
	 */
	public function dimensions( $val ) {
		if ( ! is_array( $val ) ) {
			$s = trim( (string) $val );
			return $s === '' ? array() : array(
				'top'    => $s,
				'right'  => $s,
				'bottom' => $s,
				'left'   => $s,
				'linked' => true,
			);
		}
		$unit   = (string) ( $val['unit'] ?? 'px' );
		$linked = ! empty( $val['isLinked'] ) || ! empty( $val['linked'] );
		$out    = array( 'linked' => $linked );
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$n = $val[ $side ] ?? '';
			if ( $n === '' || $n === null ) {
				$out[ $side ] = '';
				continue;
			}
			$out[ $side ] = is_numeric( $n ) ? ( $n . $unit ) : (string) $n;
		}
		return $out;
	}

	/**
	 * @param mixed $val
	 * @return string
	 */
	public function url_string( $val ) {
		if ( is_array( $val ) ) {
			return esc_url_raw( (string) ( $val['url'] ?? '' ) );
		}
		return esc_url_raw( (string) $val );
	}

	/**
	 * @param mixed $val
	 * @return bool
	 */
	private function url_blank( $val ) {
		return is_array( $val ) && ( ! empty( $val['is_external'] ) || (string) ( $val['is_external'] ?? '' ) === 'on' );
	}

	/**
	 * @param mixed $val
	 * @return string
	 */
	public function icon_id( $val ) {
		if ( is_array( $val ) ) {
			$val = (string) ( $val['value'] ?? '' );
		}
		$val = trim( (string) $val );
		if ( $val === '' ) {
			return '';
		}
		if ( strpos( $val, ' ' ) === false && strpos( $val, 'fa-' ) !== 0 ) {
			return sanitize_key( $val );
		}
		$parts = preg_split( '/\s+/', $val );
		$id    = (string) end( $parts );
		$id    = preg_replace( '/^fa[srlb]?-/', '', $id );
		return sanitize_key( $id );
	}

	/**
	 * @param mixed $val
	 * @return bool
	 */
	private function as_bool( $val ) {
		if ( is_bool( $val ) ) {
			return $val;
		}
		if ( is_numeric( $val ) ) {
			return (int) $val === 1;
		}
		$s = strtolower( (string) $val );
		return in_array( $s, array( 'yes', 'true', 'on', '1' ), true );
	}

	/**
	 * @param mixed  $media
	 * @param array  $out
	 * @param string $id_key
	 * @param string $url_key
	 */
	private function unpack_media( $media, array &$out, $id_key, $url_key ) {
		if ( ! is_array( $media ) ) {
			if ( is_numeric( $media ) && (int) $media > 0 ) {
				$out[ $id_key ] = absint( $media );
			} elseif ( is_string( $media ) && $media !== '' ) {
				$out[ $url_key ] = esc_url_raw( $media );
			}
			return;
		}
		$id = absint( $media['id'] ?? 0 );
		if ( $id ) {
			$out[ $id_key ] = $id;
		}
		$url = (string) ( $media['url'] ?? '' );
		if ( $url !== '' ) {
			$out[ $url_key ] = esc_url_raw( $url );
		}
	}

	/**
	 * @param array    $rows
	 * @param callable $fn
	 * @return array
	 */
	private function repeater( $rows, $fn ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = $fn( $row );
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $row['_id'] ?? '' ) );
			$item['_id'] = $id !== '' ? $id : $this->node_id( '' );
			$out[]       = $item;
		}
		return $out;
	}

	/**
	 * @param array  $src
	 * @param string $prefix
	 * @return array
	 */
	private function typography_from( array $src, $prefix ) {
		$out = array();
		$keys = array(
			'font_family'     => $prefix . '_font_family',
			'font_size'       => $prefix . '_font_size',
			'font_weight'     => $prefix . '_font_weight',
			'font_style'      => $prefix . '_font_style',
			'text_transform'  => $prefix . '_text_transform',
			'text_decoration' => $prefix . '_text_decoration',
			'line_height'     => $prefix . '_line_height',
			'letter_spacing'  => $prefix . '_letter_spacing',
		);
		foreach ( $keys as $dest => $src_key ) {
			if ( ! array_key_exists( $src_key, $src ) ) {
				continue;
			}
			$v = $src[ $src_key ];
			if ( in_array( $dest, array( 'font_size', 'line_height', 'letter_spacing' ), true ) ) {
				$v = $this->slider_string( $v );
			} else {
				$v = is_array( $v ) ? (string) ( $v['value'] ?? '' ) : (string) $v;
			}
			if ( $v !== '' ) {
				$out[ $dest ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Bind `__globals__` color tokens onto already-mapped dest keys.
	 *
	 * @param array $src
	 * @param array $settings
	 * @param array $map source key => dest
	 */
	public function apply_globals( array $src, array &$settings, array $map ) {
		$globals = $src['__globals__'] ?? array();
		if ( ! is_array( $globals ) || ! $globals ) {
			return;
		}
		$dest_of = array();
		foreach ( $map as $src_key => $dest ) {
			$dest_of[ $src_key ] = is_array( $dest ) ? (string) ( $dest[0] ?? '' ) : (string) $dest;
		}
		foreach ( $globals as $src_key => $ref ) {
			$parsed = self::parse_global_ref( (string) $ref );
			if ( ! $parsed ) {
				continue;
			}
			$this->report['globals']++;
			$dest = $dest_of[ $src_key ] ?? '';
			if ( $parsed['group'] === 'colors' ) {
				$token = '{{var:colors.' . $parsed['id'] . '}}';
				if ( $dest === 'background' || $src_key === 'background_color' ) {
					if ( ! isset( $settings['background'] ) || ! is_array( $settings['background'] ) ) {
						$settings['background'] = array( 'type' => 'classic' );
					}
					$settings['background']['color'] = $token;
					continue;
				}
				if ( $dest !== '' ) {
					$settings[ $dest ] = $token;
				} elseif ( isset( $settings[ $src_key ] ) ) {
					$settings[ $src_key ] = $token;
				}
			} elseif ( $parsed['group'] === 'typography' ) {
				$settings['typography_global'] = $parsed['id'];
			}
		}
	}

	/**
	 * Parse `globals/colors?id=primary` (stored global bind).
	 *
	 * @param string $ref
	 * @return array{group:string,id:string}|null
	 */
	public static function parse_global_ref( $ref ) {
		$ref = trim( (string) $ref );
		if ( $ref === '' ) {
			return null;
		}
		if ( preg_match( '#globals/(colors|typography)\?id=([a-zA-Z0-9_-]+)#', $ref, $m ) ) {
			return array(
				'group' => $m[1],
				'id'    => sanitize_key( $m[2] ),
			);
		}
		if ( preg_match( '#^colors[:/]([a-zA-Z0-9_-]+)$#', $ref, $m ) ) {
			return array( 'group' => 'colors', 'id' => sanitize_key( $m[1] ) );
		}
		return null;
	}

	/**
	 * @param array $src
	 * @return array
	 */
	private function background_from( array $src ) {
		$type = sanitize_key( (string) ( $src['background_background'] ?? '' ) );
		if ( $type === '' && empty( $src['background_color'] ) && empty( $src['background_image'] ) ) {
			$overlay = $src['background_overlay_background'] ?? '';
			if ( $overlay === '' ) {
				return array();
			}
		}
		if ( $type === '' ) {
			$type = ! empty( $src['background_image'] ) ? 'classic' : ( ! empty( $src['background_color'] ) ? 'classic' : 'classic' );
		}
		if ( ! in_array( $type, array( 'classic', 'gradient', 'video', 'slideshow' ), true ) ) {
			$type = 'classic';
		}
		$bg = array( 'type' => $type );
		if ( ! empty( $src['background_color'] ) ) {
			$bg['color'] = (string) $src['background_color'];
		}
		if ( ! empty( $src['background_image'] ) && is_array( $src['background_image'] ) ) {
			$bg['image_id']  = absint( $src['background_image']['id'] ?? 0 );
			$bg['image_url'] = esc_url_raw( (string) ( $src['background_image']['url'] ?? '' ) );
		}
		$size = sanitize_key( (string) ( $src['background_size'] ?? '' ) );
		if ( in_array( $size, array( 'cover', 'contain', 'auto' ), true ) ) {
			$bg['size'] = $size;
		}
		if ( ! empty( $src['background_position'] ) ) {
			$bg['position'] = is_array( $src['background_position'] ) ? sanitize_text_field( (string) ( $src['background_position']['value'] ?? '' ) ) : sanitize_text_field( (string) $src['background_position'] );
		}
		$repeat = sanitize_key( (string) ( $src['background_repeat'] ?? '' ) );
		if ( in_array( $repeat, array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ), true ) ) {
			$bg['repeat'] = $repeat;
		}
		$att = sanitize_key( (string) ( $src['background_attachment'] ?? '' ) );
		if ( in_array( $att, array( 'scroll', 'fixed' ), true ) ) {
			$bg['attachment'] = $att;
		}
		if ( $type === 'gradient' ) {
			$bg['gradient_a']     = (string) ( $src['background_color'] ?? '' );
			$bg['gradient_b']     = (string) ( $src['background_color_b'] ?? '' );
			$bg['gradient_angle'] = (int) $this->slider_number( $src['background_gradient_angle'] ?? 180 );
			$bg['gradient_type']  = ( ( $src['background_gradient_type'] ?? '' ) === 'radial' ) ? 'radial' : 'linear';
		}
		if ( $type === 'video' ) {
			$bg['video_url'] = $this->url_string( $src['background_video_link'] ?? '' );
		}
		if ( ! empty( $src['background_overlay_color'] ) ) {
			$bg['overlay_color'] = (string) $src['background_overlay_color'];
		}
		if ( isset( $src['background_overlay_opacity'] ) ) {
			$bg['overlay_opacity'] = (float) $this->slider_number( $src['background_overlay_opacity'] );
		}
		$has = ( $bg['color'] ?? '' ) !== '' || ! empty( $bg['image_id'] ) || ! empty( $bg['image_url'] ) || ! empty( $bg['gradient_b'] ) || ! empty( $bg['video_url'] ) || ! empty( $bg['overlay_color'] );
		return $has ? $bg : array();
	}

	/**
	 * @param array $src
	 * @param array $settings
	 */
	private function shapes_from( array $src, array &$settings ) {
		foreach ( array( 'top', 'bottom' ) as $side ) {
			$shape = sanitize_key( (string) ( $src[ 'shape_divider_' . $side ] ?? '' ) );
			$ok    = array( 'wave', 'tilt', 'triangle', 'curve', 'arrow', 'zigzag', 'mountains' );
			if ( $shape === 'drops' ) {
				$shape = 'curve';
			}
			if ( $shape === '' || ! in_array( $shape, $ok, true ) ) {
				continue;
			}
			$settings[ 'shape_' . $side ] = $shape;
			if ( ! empty( $src[ 'shape_divider_' . $side . '_color' ] ) ) {
				$settings[ 'shape_' . $side . '_color' ] = (string) $src[ 'shape_divider_' . $side . '_color' ];
			}
			$h = $this->slider_number( $src[ 'shape_divider_' . $side . '_height' ] ?? null );
			if ( $h !== '' ) {
				$settings[ 'shape_' . $side . '_height' ] = $h;
			}
			if ( $this->as_bool( $src[ 'shape_divider_' . $side . '_negative' ] ?? false ) ) {
				$settings[ 'shape_' . $side . '_flip' ] = true;
			}
			if ( $this->as_bool( $src[ 'shape_divider_' . $side . '_above_content' ] ?? false ) ) {
				$settings[ 'shape_' . $side . '_front' ] = true;
			}
		}
	}

	/**
	 * @param array $page
	 * @return array
	 */
	public function convert_page_settings( array $page ) {
		$out = array();
		$tpl = (string) ( $page['template'] ?? '' );
		$map = Map::page_templates();
		if ( $tpl !== '' && isset( $map[ $tpl ] ) ) {
			$out['template'] = $map[ $tpl ];
		}
		if ( ! empty( $page['custom_css'] ) ) {
			$out['custom_css'] = (string) $page['custom_css'];
		}
		return $out;
	}

	/**
	 * @param string $type
	 * @param array  $el
	 * @return array
	 */
	private function placeholder_html( $type, $el ) {
		$label = sanitize_text_field( (string) $type );
		$html  = '<p class="lb-convert-unmapped">' . sprintf(
			/* translators: %s: source widget type slug */
			esc_html__( 'This widget could not be converted (%s).', 'canvasly-lite' ),
			esc_html( $label )
		) . '</p>';
		$node  = array(
			'id'       => $this->node_id( $el['id'] ?? '' ),
			'type'     => 'html',
			'settings' => array( 'html' => $html ),
		);
		$this->report['nodes']++;
		return $node;
	}

	/**
	 * @param string $type
	 */
	private function note_unmapped( $type ) {
		$type = $type !== '' ? (string) $type : 'unknown';
		if ( ! isset( $this->report['unmapped'][ $type ] ) ) {
			$this->report['unmapped'][ $type ] = 0;
		}
		$this->report['unmapped'][ $type ]++;
		$this->report['warnings'][] = sprintf(
			/* translators: %s: source widget type slug */
			__( 'Unmapped widget: %s', 'canvasly-lite' ),
			$type
		);
	}

	/**
	 * @param string $id
	 * @return string
	 */
	private function node_id( $id ) {
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', substr( (string) $id, 0, 40 ) );
		if ( $id !== '' ) {
			return $id;
		}
		$this->seq++;
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return 'n_' . wp_generate_uuid4();
		}
		return 'n_c' . $this->seq;
	}

	/**
	 * Read source JSON from a post.
	 *
	 * @param int $post_id
	 * @return array
	 */
	public static function source_data( $post_id ) {
		$raw = get_post_meta( absint( $post_id ), self::SOURCE_META, true );
		return self::decode( $raw );
	}

	/**
	 * @param int $post_id
	 * @return array
	 */
	public static function source_page_settings( $post_id ) {
		$raw = get_post_meta( absint( $post_id ), self::SOURCE_PAGE_SETTINGS, true );
		$d   = self::decode( $raw );
		if ( $d ) {
			return $d;
		}
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$tpl = (string) get_post_meta( absint( $post_id ), '_wp_page_template', true );
		return $tpl !== '' ? array( 'template' => $tpl ) : array();
	}

	/**
	 * Whether a post has source builder JSON.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public static function has_source( $post_id ) {
		$raw = get_post_meta( absint( $post_id ), self::SOURCE_META, true );
		return $raw !== '' && $raw !== null && $raw !== false && $raw !== array();
	}

	/**
	 * Short, safe-to-display diagnosis of why source_data() came back empty,
	 * for surfacing in the conversion report instead of a bare "error".
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function source_diagnostic( $post_id ) {
		$raw = get_post_meta( absint( $post_id ), self::SOURCE_META, true );
		if ( is_array( $raw ) ) {
			return empty( $raw ) ? __( 'Stored value is an empty array.', 'canvasly-lite' ) : '';
		}
		if ( ! is_string( $raw ) || $raw === '' ) {
			return __( 'No _elementor_data meta value is stored on this post (empty or missing).', 'canvasly-lite' );
		}
		$len     = function_exists( 'mb_strlen' ) ? mb_strlen( $raw, '8bit' ) : strlen( $raw );
		$excerpt = substr( $raw, 0, 60 );
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$excerpt = @mb_convert_encoding( $excerpt, 'UTF-8', 'UTF-8' );
		}
		$excerpt  = preg_replace( '/[\x00-\x1F\x7F]/', '?', (string) $excerpt );
		$direct   = json_decode( $raw, true );
		$json_err = function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : '';
		$parsed   = ( json_last_error() === JSON_ERROR_NONE );
		if ( $parsed && is_array( $direct ) && empty( $direct ) ) {
			return sprintf(
				/* translators: 1: byte length, 2: first characters of the stored value */
				__( 'Stored value (%1$d bytes) parsed fine as JSON but decoded to an empty layout. First characters: %2$s', 'canvasly-lite' ),
				$len,
				$excerpt
			);
		}
		if ( ! $parsed ) {
			// One more attempt after removing WP-style slashing, purely to
			// tell "genuinely malformed" apart from "just needed unslashing
			// but was still empty after that" in the message we show.
			if ( function_exists( 'wp_unslash' ) ) {
				json_decode( wp_unslash( $raw ), true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					return sprintf(
						/* translators: 1: byte length, 2: first characters of the stored value */
						__( 'Stored value (%1$d bytes) parsed fine as JSON after removing slashes, but decoded to an empty layout. First characters: %2$s', 'canvasly-lite' ),
						$len,
						$excerpt
					);
				}
			}
			return sprintf(
				/* translators: 1: byte length, 2: JSON parser error, 3: first characters of the stored value */
				__( 'Stored value is %1$d bytes but failed to parse as JSON (%2$s). First characters: %3$s', 'canvasly-lite' ),
				$len,
				$json_err,
				$excerpt
			);
		}
		return sprintf(
			/* translators: 1: byte length, 2: JSON parser error, 3: first characters of the stored value */
			__( 'Stored value is %1$d bytes but failed to parse as JSON (%2$s). First characters: %3$s', 'canvasly-lite' ),
			$len,
			$json_err,
			$excerpt
		);
	}

	/**
	 * Posts that still have source builder JSON.
	 *
	 * @param array $args
	 * @return array<int,array>
	 */
	public static function candidates( $args = array() ) {
		$args  = is_array( $args ) ? $args : array();
		$limit = max( 1, min( 500, absint( $args['limit'] ?? 200 ) ) );
		$types = $args['post_type'] ?? 'any';
		if ( $types === 'any' || $types === '' ) {
			$types = array_merge(
				class_exists( Documents::class ) ? Documents::enabled() : array( 'post', 'page' ),
				array( self::SOURCE_LIBRARY_TYPE )
			);
		}
		$query = array(
			'post_type'              => $types,
			'post_status'            => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'posts_per_page'         => $limit,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Conversion candidates are posts that still store source-builder JSON.
			'meta_key'               => self::SOURCE_META,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$posts = function_exists( 'get_posts' ) ? get_posts( $query ) : array();
		$out   = array();
		foreach ( (array) $posts as $p ) {
			$id = is_object( $p ) ? (int) $p->ID : absint( $p );
			if ( ! $id || ! self::has_source( $id ) ) {
				continue;
			}
			$post = is_object( $p ) ? $p : get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$has_lb = (string) get_post_meta( $id, class_exists( DocumentManager::class ) ? DocumentManager::META : '_lb_document_data', true ) !== '';
			$out[]  = array(
				'id'         => $id,
				'title'      => function_exists( 'get_the_title' ) ? get_the_title( $id ) : (string) ( $post->post_title ?? '' ),
				'type'       => (string) ( $post->post_type ?? '' ),
				'status'     => (string) ( $post->post_status ?? '' ),
				'has_loom'   => $has_lb,
				'converted'  => (string) get_post_meta( $id, self::CONVERTED_META, true ) !== '',
				'edit_url'   => function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=canvasly-lite&post_id=' . $id ) : '',
			);
		}
		$filtered = apply_filters( 'canvasly-lite/convert/candidates', $out, $args );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * Convert one post. Dry-run returns the document without writing.
	 *
	 * @param int   $post_id
	 * @param array $args {dry_run:bool, force:bool, sanitize:bool}
	 * @return array|\WP_Error
	 */
	public function convert_post( $post_id, $args = array() ) {
		$post_id = absint( $post_id );
		$args    = is_array( $args ) ? $args : array();
		$dry     = ! empty( $args['dry_run'] );
		$force   = ! empty( $args['force'] );
		$post    = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( $post_id && function_exists( 'current_user_can' ) && ! current_user_can( 'edit_post', $post_id ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot convert this document.', 'canvasly-lite' ) );
		}
		$source = self::source_data( $post_id );
		if ( ! $source ) {
			$why = self::source_diagnostic( $post_id );
			return new \WP_Error(
				'no_source',
				$why !== ''
					? __( 'No convertible layout data was found on this post.', 'canvasly-lite' ) . ' ' . $why
					: __( 'No convertible layout data was found on this post.', 'canvasly-lite' )
			);
		}
		$lb_key = class_exists( DocumentManager::class ) ? DocumentManager::META : '_lb_document_data';
		$has_lb = (string) get_post_meta( $post_id, $lb_key, true ) !== ''
			|| (string) get_post_meta( $post_id, self::CONVERTED_META, true ) !== '';
		if ( $has_lb && ! $force && ! $dry ) {
			return array(
				'id'       => $post_id,
				'status'   => 'skipped',
				'reason'   => 'exists',
				'report'   => array(),
				'document' => null,
			);
		}
		$page = self::source_page_settings( $post_id );
		$pack = $this->convert_tree( $source, $page );
		$doc  = $pack['document'];
		$rep  = $pack['report'];
		if ( ! $dry ) {
			if ( $post && ( $post->post_type ?? '' ) === self::SOURCE_LIBRARY_TYPE && class_exists( SavedTemplates::class ) ) {
				$saved = $this->save_as_template( $post, $doc );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				update_post_meta( $post_id, self::CONVERTED_META, 'library:' . (int) $saved );
				update_post_meta( $post_id, self::CONVERTED_AT, function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'c' ) );
				return array(
					'id'          => $post_id,
					'title'       => function_exists( 'get_the_title' ) ? get_the_title( $post_id ) : (string) ( $post->post_title ?? '' ),
					'type'        => (string) ( $post->post_type ?? '' ),
					'template_id' => (int) $saved,
					'status'      => 'converted',
					'report'      => $rep,
					'document'    => $doc,
				);
			}
			if ( class_exists( DocumentManager::class ) ) {
				$saved = DocumentManager::save( $post_id, $doc );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				$doc = $saved;
			} else {
				update_post_meta( $post_id, $lb_key, wp_json_encode( $doc ) );
			}
			update_post_meta( $post_id, self::CONVERTED_META, 'document' );
			update_post_meta( $post_id, self::CONVERTED_AT, function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'c' ) );
		}
		return array(
			'id'       => $post_id,
			'title'    => $post ? ( function_exists( 'get_the_title' ) ? get_the_title( $post_id ) : (string) ( $post->post_title ?? '' ) ) : '',
			'type'     => $post ? (string) $post->post_type : '',
			'status'   => $dry ? 'preview' : 'converted',
			'report'   => $rep,
			'document' => $doc,
		);
	}

	/**
	 * @param \WP_Post $post
	 * @param array    $doc
	 * @return int|\WP_Error
	 */
	private function save_as_template( $post, array $doc ) {
		$src_type = (string) get_post_meta( $post->ID, self::SOURCE_TEMPLATE_TYPE, true );
		$map      = Map::library_types();
		$type     = $map[ $src_type ] ?? 'section';
		$title    = $post->post_title !== '' ? $post->post_title : __( 'Converted template', 'canvasly-lite' );
		$key      = 'converted-' . $post->ID;
		if ( method_exists( SavedTemplates::class, 'create' ) ) {
			return SavedTemplates::create(
				array(
					'title'    => $title,
					'type'     => $type,
					'key'      => $key,
					'document' => $doc,
				)
			);
		}
		$id = wp_insert_post(
			array(
				'post_type'   => 'lb_template',
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_lb_template_data', wp_json_encode( $doc ) );
		update_post_meta( $id, '_lb_template_type', $type );
		update_post_meta( $id, '_lb_template_key', $key );
		if ( class_exists( DocumentManager::class ) ) {
			DocumentManager::save( $id, $doc );
		}
		return (int) $id;
	}

	/**
	 * Convert many posts. Always safe to call with dry_run=true.
	 *
	 * @param int[] $ids
	 * @param array $args
	 * @return array
	 */
	public function convert_posts( $ids, $args = array() ) {
		$args  = is_array( $args ) ? $args : array();
		$items = array();
		$agg   = array(
			'posts'     => 0,
			'converted' => 0,
			'skipped'   => 0,
			'errors'    => 0,
			'mapped'    => 0,
			'unmapped'  => array(),
			'warnings'  => array(),
			'globals'   => 0,
			'nodes'     => 0,
			'dry_run'   => ! empty( $args['dry_run'] ),
			'items'     => array(),
		);
		foreach ( (array) $ids as $id ) {
			$id = absint( $id );
			if ( ! $id ) {
				continue;
			}
			$agg['posts']++;
			$one = $this->convert_post( $id, $args );
			if ( is_wp_error( $one ) ) {
				$agg['errors']++;
				$items[] = array(
					'id'     => $id,
					'status' => 'error',
					'error'  => $one->get_error_message(),
				);
				continue;
			}
			$status = $one['status'] ?? '';
			if ( $status === 'skipped' ) {
				$agg['skipped']++;
			} elseif ( $status === 'converted' || $status === 'preview' ) {
				$agg['converted']++;
			}
			$rep = is_array( $one['report'] ?? null ) ? $one['report'] : array();
			$agg['mapped']  += (int) ( $rep['mapped'] ?? 0 );
			$agg['nodes']   += (int) ( $rep['nodes'] ?? 0 );
			$agg['globals'] += (int) ( $rep['globals'] ?? 0 );
			foreach ( (array) ( $rep['unmapped'] ?? array() ) as $w => $n ) {
				if ( ! isset( $agg['unmapped'][ $w ] ) ) {
					$agg['unmapped'][ $w ] = 0;
				}
				$agg['unmapped'][ $w ] += (int) $n;
			}
			foreach ( (array) ( $rep['warnings'] ?? array() ) as $w ) {
				$agg['warnings'][] = $w;
			}
			$items[] = array(
				'id'          => $one['id'] ?? $id,
				'title'       => $one['title'] ?? '',
				'type'        => $one['type'] ?? '',
				'status'      => $status,
				'reason'      => $one['reason'] ?? '',
				'template_id' => $one['template_id'] ?? 0,
				'mapped'      => (int) ( $rep['mapped'] ?? 0 ),
				'nodes'       => (int) ( $rep['nodes'] ?? 0 ),
				'unmapped'    => $rep['unmapped'] ?? array(),
			);
		}
		$agg['warnings'] = array_values( array_unique( $agg['warnings'] ) );
		$agg['items']    = $items;
		$filtered        = apply_filters( 'canvasly-lite/convert/report', $agg, $ids, $args );
		return is_array( $filtered ) ? $filtered : $agg;
	}

	/**
	 * Empty report shape for the Tools UI.
	 *
	 * @return array
	 */
	public static function empty_report() {
		return array(
			'posts'     => 0,
			'converted' => 0,
			'skipped'   => 0,
			'errors'    => 0,
			'mapped'    => 0,
			'unmapped'  => array(),
			'warnings'  => array(),
			'globals'   => 0,
			'nodes'     => 0,
			'dry_run'   => true,
			'items'     => array(),
		);
	}
}
