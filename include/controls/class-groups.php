<?php
namespace CanvaslyLite\Controls;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured visual group controls: typography, border, background, shadows,
 * CSS filters, transform, transition, gaps, and linked dimensions.
 *
 * Free-text `transform`, `filter`, `background_gradient`, `text_shadow` and
 * `transition` values are parsed into these objects on load and save.
 */
class Groups {
	const TYPES = array( 'typography', 'border', 'background', 'text_shadow', 'css_filter', 'transform', 'transition', 'gaps', 'box_shadow', 'dimensions', 'spacing' );

	public static function init() {
		$c = Controls::instance();
		foreach ( array( 'typography', 'border', 'background', 'text_shadow', 'css_filter', 'transform', 'transition', 'gaps', 'box_shadow', 'dimensions', 'spacing' ) as $type ) {
			$c->register(
				$type,
				function ( $value ) use ( $type ) {
					return Groups::sanitize_type( $type, $value );
				},
				null,
				array( 'label' => ucwords( str_replace( '_', ' ', $type ) ) )
			);
		}
		$c->register(
			'gradient',
			function ( $value ) {
				if ( is_string( $value ) && $value !== '' ) {
					return Groups::parse_gradient_string( $value );
				}
				return is_array( $value ) ? Groups::sanitize_background( array_merge( $value, array( 'type' => 'gradient' ) ) ) : Groups::sanitize_background( array( 'type' => 'gradient' ) );
			},
			null,
			array( 'label' => 'Gradient' )
		);
		add_filter( 'canvasly-lite/unit/render_html', array( self::class, 'inject_layers' ), 10, 4 );
	}

	public static function handles( $type ) {
		return in_array( (string) $type, self::TYPES, true );
	}

	public static function sanitize( $type, $value ) {
		return self::sanitize_type( $type, $value );
	}

	public static function sanitize_type( $type, $value ) {
		switch ( $type ) {
			case 'typography':
				return self::sanitize_typography( $value );
			case 'border':
				return self::sanitize_border( $value );
			case 'background':
				return self::sanitize_background( $value );
			case 'text_shadow':
				return self::sanitize_text_shadow( $value );
			case 'box_shadow':
				return self::sanitize_box_shadow( $value );
			case 'css_filter':
				return self::sanitize_filter( $value );
			case 'transform':
				return self::sanitize_transform( $value );
			case 'transition':
				return self::sanitize_transition( $value );
			case 'gaps':
				return self::sanitize_gaps( $value );
			case 'dimensions':
			case 'spacing':
				return self::sanitize_dimensions( $value );
		}
		return $value;
	}

	public static function compile( $type, $value ) {
		switch ( $type ) {
			case 'typography':
				return self::compile_typography( $value );
			case 'border':
				return self::compile_border( $value );
			case 'background':
				$d = array();
				foreach ( self::background_map( $value ) as $p => $x ) {
					$d[] = $p . ':' . $x;
				}
				return implode( ';', $d );
			case 'text_shadow':
				return self::compile_text_shadow( $value );
			case 'box_shadow':
				return self::compile_box_shadow( $value );
			case 'css_filter':
				return self::compile_filter( $value );
			case 'transform':
				return self::compile_transform( $value );
			case 'transition':
				return self::compile_transition( $value );
			case 'gaps':
				return self::compile_gaps( $value );
			case 'dimensions':
			case 'spacing':
				return self::compile_dimensions( $value );
		}
		return '';
	}

	/** Walk settings and convert legacy free-text fields to structured objects. */
	public static function migrate_settings( array $s ) {
		if ( isset( $s['transform'] ) && is_string( $s['transform'] ) ) {
			$s['transform'] = self::parse_transform( $s['transform'] );
		}
		if ( isset( $s['filter'] ) && is_string( $s['filter'] ) ) {
			$s['filter'] = self::parse_filter( $s['filter'] );
		}
		if ( isset( $s['transition'] ) && is_string( $s['transition'] ) ) {
			$s['transition'] = self::parse_transition( $s['transition'] );
		}
		if ( isset( $s['text_shadow'] ) && is_string( $s['text_shadow'] ) ) {
			$s['text_shadow'] = self::parse_text_shadow( $s['text_shadow'] );
		}
		if ( isset( $s['box_shadow'] ) && is_string( $s['box_shadow'] ) ) {
			$s['box_shadow'] = self::parse_box_shadow( $s['box_shadow'] );
		}
		if ( isset( $s['shadow'] ) && is_string( $s['shadow'] ) ) {
			$s['shadow'] = self::parse_box_shadow( $s['shadow'] );
		}
		if ( self::should_struct_background( $s ) ) {
			$s['background'] = self::migrate_background( $s );
		}
		if ( ! is_array( $s['gaps'] ?? null ) && ( isset( $s['column_gap'] ) || isset( $s['row_gap'] ) || isset( $s['gaps'] ) ) ) {
			$row = $s['row_gap'] ?? $s['gaps'] ?? '';
			$col = $s['column_gap'] ?? $s['gaps'] ?? '';
			if ( is_array( $row ) ) {
				$row = $row['desktop'] ?? reset( $row );
			}
			if ( is_array( $col ) ) {
				$col = $col['desktop'] ?? reset( $col );
			}
			$s['gaps'] = self::sanitize_gaps(
				array(
					'column' => $col,
					'row'    => $row,
					'linked' => false,
				)
			);
		}
		return $s;
	}

	/* ---------- length / color helpers ---------- */

	public static function length( $v ) {
		if ( is_array( $v ) ) {
			$v = $v['desktop'] ?? reset( $v );
		}
		$v = trim( (string) $v );
		if ( $v === '' || $v === '0' ) {
			return $v === '0' ? '0' : '';
		}
		if ( $v === 'auto' ) {
			return 'auto';
		}
		if ( preg_match( '/^-?\d+(\.\d+)?$/', $v ) ) {
			return $v . 'px';
		}
		if ( preg_match( '/^-?\d*\.?\d+\s*(px|%|em|rem|vw|vh|deg|s|ms)$/i', $v ) ) {
			return preg_replace( '/\s+/', '', $v );
		}
		return $v;
	}

	public static function sanitize_length( $v, $default_unit = '' ) {
		if ( is_array( $v ) ) {
			$v = $v['desktop'] ?? ( $v['size'] ?? '' ) . ( $v['unit'] ?? '' );
		}
		$v = trim( (string) $v );
		if ( $v === '' || $v === 'auto' ) {
			return $v;
		}
		if ( ! preg_match( '/^(-?\d*\.?\d+)\s*([a-z%]*)?$/i', $v, $m ) ) {
			return '';
		}
		$n = rtrim( rtrim( number_format( (float) $m[1], 4, '.', '' ), '0' ), '.' );
		$u = strtolower( $m[2] ?? '' );
		$ok = array( '', 'px', '%', 'em', 'rem', 'vw', 'vh', 'deg', 's', 'ms' );
		if ( $u === '' ) {
			$u = $default_unit;
		} elseif ( ! in_array( $u, $ok, true ) ) {
			$u = $default_unit !== '' ? $default_unit : 'px';
		}
		return $n . $u;
	}

	public static function color( $v ) {
		$v = trim( (string) $v );
		if ( $v === '' ) {
			return '';
		}
		if ( class_exists( '\\CanvaslyLite\\Design\\Variables' ) ) {
			return \CanvaslyLite\Design\Variables::sanitize_color_value( $v );
		}
		if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $v ) ) {
			return $v;
		}
		if ( preg_match( '/^(rgba?|hsla?)\(/i', $v ) || strpos( $v, '{{var:' ) === 0 ) {
			return sanitize_text_field( $v );
		}
		return sanitize_text_field( $v );
	}

	public static function sanitize_dimensions( $v ) {
		if ( is_string( $v ) || is_numeric( $v ) ) {
			$v = trim( (string) $v );
			$parts = $v === '' ? array() : preg_split( '/\s+/', $v );
			$lens  = array();
			foreach ( (array) $parts as $p ) {
				$one = self::sanitize_length( $p, 'px' );
				if ( $one !== '' ) {
					$lens[] = $one;
				}
			}
			$n = count( $lens );
			if ( $n === 0 ) {
				$one = self::sanitize_length( $v, 'px' );
				return array(
					'top'    => $one,
					'right'  => $one,
					'bottom' => $one,
					'left'   => $one,
					'linked' => true,
				);
			}
			if ( $n === 1 ) {
				return array(
					'top'    => $lens[0],
					'right'  => $lens[0],
					'bottom' => $lens[0],
					'left'   => $lens[0],
					'linked' => true,
				);
			}
			if ( $n === 2 ) {
				return array(
					'top'    => $lens[0],
					'right'  => $lens[1],
					'bottom' => $lens[0],
					'left'   => $lens[1],
					'linked' => false,
				);
			}
			if ( $n === 3 ) {
				return array(
					'top'    => $lens[0],
					'right'  => $lens[1],
					'bottom' => $lens[2],
					'left'   => $lens[1],
					'linked' => false,
				);
			}
			return array(
				'top'    => $lens[0],
				'right'  => $lens[1],
				'bottom' => $lens[2],
				'left'   => $lens[3],
				'linked' => false,
			);
		}
		$v = is_array( $v ) ? $v : array();
		$out = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$out[ $side ] = self::sanitize_length( $v[ $side ] ?? '', 'px' );
		}
		$out['linked'] = ! empty( $v['linked'] );
		if ( $out['linked'] ) {
			$out['right'] = $out['bottom'] = $out['left'] = $out['top'];
		}
		return $out;
	}

	public static function compile_dimensions( $v ) {
		if ( ! is_array( $v ) ) {
			return self::length( $v );
		}
		$a = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$x = self::length( $v[ $side ] ?? '' );
			$a[] = $x === '' ? '0' : $x;
		}
		if ( $a === array( '0', '0', '0', '0' ) ) {
			return '';
		}
		return implode( ' ', $a );
	}

	/* ---------- typography ---------- */

	public static function sanitize_typography( $v ) {
		if ( is_string( $v ) && $v !== '' ) {
			$v = array( 'font_family' => $v );
		}
		$v = is_array( $v ) ? $v : array();
		$w = sanitize_text_field( (string) ( $v['font_weight'] ?? '' ) );
		if ( $w !== '' && ! preg_match( '/^(normal|bold|bolder|lighter|[1-9]00)$/', $w ) ) {
			$w = '';
		}
		return array(
			'font_family'     => sanitize_text_field( (string) ( $v['font_family'] ?? '' ) ),
			'font_size'       => self::sanitize_length( $v['font_size'] ?? '' ),
			'font_weight'     => $w,
			'font_style'      => in_array( $v['font_style'] ?? '', array( 'normal', 'italic', 'oblique' ), true ) ? $v['font_style'] : '',
			'text_transform'  => in_array( $v['text_transform'] ?? '', array( 'none', 'uppercase', 'lowercase', 'capitalize' ), true ) ? $v['text_transform'] : '',
			'text_decoration' => in_array( $v['text_decoration'] ?? '', array( 'none', 'underline', 'overline', 'line-through' ), true ) ? $v['text_decoration'] : '',
			'line_height'     => self::sanitize_length( $v['line_height'] ?? '' ),
			'letter_spacing'  => self::sanitize_length( $v['letter_spacing'] ?? '' ),
		);
	}

	public static function typography_map( $v ) {
		$v = self::sanitize_typography( $v );
		$map = array(
			'font-family'     => self::quote_family( $v['font_family'] ),
			'font-size'       => self::length( $v['font_size'] ),
			'font-weight'     => $v['font_weight'],
			'font-style'      => $v['font_style'],
			'text-transform'  => $v['text_transform'],
			'text-decoration' => $v['text_decoration'],
			'line-height'     => $v['line_height'] !== '' ? self::length( $v['line_height'] ) : '',
			'letter-spacing'  => $v['letter_spacing'] !== '' ? self::length( $v['letter_spacing'] ) : '',
		);
		return array_filter( $map, function ( $x ) {
			return $x !== '' && $x !== null;
		} );
	}

	/** Quote a family name that contains spaces so the CSS font stack stays one family. */
	public static function quote_family( $family ) {
		$family = trim( (string) $family );
		if ( $family === '' ) {
			return '';
		}
		$first = $family[0];
		if ( $first === '"' || $first === "'" || strpos( $family, ',' ) !== false ) {
			return $family;
		}
		if ( preg_match( '/\s/', $family ) ) {
			return '"' . str_replace( '"', '', $family ) . '"';
		}
		return $family;
	}

	public static function compile_typography( $v ) {
		$d = array();
		foreach ( self::typography_map( $v ) as $p => $x ) {
			$d[] = $p . ':' . $x;
		}
		return implode( ';', $d );
	}

	/* ---------- border ---------- */

	public static function sanitize_border( $v ) {
		if ( is_string( $v ) && $v !== '' ) {
			$v = array( 'style' => $v );
		}
		$v = is_array( $v ) ? $v : array();
		$style = sanitize_key( (string) ( $v['style'] ?? '' ) );
		if ( ! in_array( $style, array( '', 'none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge' ), true ) ) {
			$style = '';
		}
		return array(
			'style'  => $style,
			'width'  => self::sanitize_dimensions( $v['width'] ?? array() ),
			'color'  => self::color( $v['color'] ?? '' ),
			'radius' => self::sanitize_dimensions( $v['radius'] ?? array() ),
		);
	}

	public static function border_map( $v ) {
		$v = self::sanitize_border( $v );
		$out = array();
		if ( $v['style'] !== '' ) {
			$out['border-style'] = $v['style'];
		}
		$w = self::compile_dimensions( $v['width'] );
		if ( $w !== '' ) {
			$out['border-width'] = $w;
		}
		if ( $v['color'] !== '' ) {
			$out['border-color'] = $v['color'];
		}
		$r = self::compile_dimensions( $v['radius'] );
		if ( $r !== '' ) {
			$out['border-radius'] = $r;
		}
		return $out;
	}

	public static function compile_border( $v ) {
		$d = array();
		foreach ( self::border_map( $v ) as $p => $x ) {
			$d[] = $p . ':' . $x;
		}
		return implode( ';', $d );
	}

	/* ---------- background ---------- */

	public static function sanitize_background( $v ) {
		if ( is_string( $v ) && $v !== '' ) {
			if ( stripos( $v, 'gradient(' ) !== false ) {
				$v = self::parse_gradient_string( $v );
			} else {
				$v = array(
					'type'  => 'classic',
					'color' => $v,
				);
			}
		}
		$v = is_array( $v ) ? $v : array();
		if ( ! empty( $v['custom'] ) && is_string( $v['custom'] ) && ( $v['gradient_a'] ?? '' ) === '' && ( $v['gradient_b'] ?? '' ) === '' ) {
			$parsed = self::parse_gradient_string( $v['custom'] );
			if ( ( $parsed['custom'] ?? '' ) === '' ) {
				$v = array_merge( $v, $parsed );
				$v['custom'] = '';
			}
		}
		$type = sanitize_key( (string) ( $v['type'] ?? 'classic' ) );
		if ( ! in_array( $type, array( 'classic', 'gradient', 'video', 'slideshow' ), true ) ) {
			$type = 'classic';
		}
		$urls = array();
		foreach ( (array) ( $v['slideshow_urls'] ?? array() ) as $id => $url ) {
			$aid = absint( $id );
			if ( $aid ) {
				$urls[ (string) $aid ] = esc_url_raw( (string) $url );
			}
		}
		$angle = isset( $v['gradient_angle'] ) ? (int) $v['gradient_angle'] : 180;
		$angle = max( 0, min( 360, $angle ) );
		return array(
			'type'                 => $type,
			'color'                => self::color( $v['color'] ?? '' ),
			'image_id'             => absint( $v['image_id'] ?? 0 ),
			'image_url'            => esc_url_raw( (string) ( $v['image_url'] ?? '' ) ),
			'size'                 => in_array( $v['size'] ?? '', array( 'cover', 'contain', 'auto' ), true ) ? $v['size'] : '',
			'position'             => sanitize_text_field( (string) ( $v['position'] ?? '' ) ),
			'repeat'               => in_array( $v['repeat'] ?? '', array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ), true ) ? $v['repeat'] : '',
			'attachment'           => in_array( $v['attachment'] ?? '', array( 'scroll', 'fixed' ), true ) ? $v['attachment'] : '',
			'overlay_color'        => self::color( $v['overlay_color'] ?? '' ),
			'overlay_opacity'      => max( 0, min( 1, (float) ( $v['overlay_opacity'] ?? 0.5 ) ) ),
			'overlay_blend'        => sanitize_key( (string) ( $v['overlay_blend'] ?? '' ) ),
			'gradient_type'        => ( $v['gradient_type'] ?? '' ) === 'radial' ? 'radial' : 'linear',
			'gradient_angle'       => $angle,
			'gradient_position'    => self::sanitize_gradient_position( $v['gradient_position'] ?? '' ),
			'gradient_a'           => self::color( $v['gradient_a'] ?? '' ),
			'gradient_b'           => self::color( $v['gradient_b'] ?? '' ),
			'gradient_a_pos'       => max( 0, min( 100, (int) ( $v['gradient_a_pos'] ?? 0 ) ) ),
			'gradient_b_pos'       => max( 0, min( 100, (int) ( $v['gradient_b_pos'] ?? 100 ) ) ),
			'custom'               => self::sanitize_css_value( $v['custom'] ?? '' ),
			'video_url'            => esc_url_raw( (string) ( $v['video_url'] ?? '' ) ),
			'video_start'          => max( 0, (int) ( $v['video_start'] ?? 0 ) ),
			'video_end'            => max( 0, (int) ( $v['video_end'] ?? 0 ) ),
			'video_poster'         => esc_url_raw( (string) ( $v['video_poster'] ?? '' ) ),
			'video_loop'           => ! isset( $v['video_loop'] ) || ! empty( $v['video_loop'] ),
			'video_mobile'         => ! empty( $v['video_mobile'] ),
			'slideshow_ids'        => preg_replace( '/[^0-9,\s]/', '', (string) ( $v['slideshow_ids'] ?? '' ) ),
			'slideshow_urls'       => $urls,
			'slideshow_duration'   => max( 1, min( 60, (int) ( $v['slideshow_duration'] ?? 5 ) ) ),
			'slideshow_transition' => in_array( ( $st = ( $v['slideshow_transition'] ?? 'fade' ) ), array( 'fade', 'slide' ), true ) ? $st : 'fade',
		);
	}

	public static function migrate_background( array $s ) {
		$bg = $s['background'] ?? null;
		if ( is_string( $bg ) && $bg !== '' ) {
			$bg = self::sanitize_background( $bg );
		} elseif ( is_array( $bg ) ) {
			$bg = self::sanitize_background( $bg );
		} else {
			$bg = self::sanitize_background( array() );
		}
		if ( ! empty( $s['background_image'] ) && $bg['image_url'] === '' ) {
			$bg['image_url'] = esc_url_raw( (string) $s['background_image'] );
		}
		if ( ! empty( $s['background_size'] ) && $bg['size'] === '' ) {
			$bg['size'] = in_array( $s['background_size'], array( 'cover', 'contain', 'auto' ), true ) ? $s['background_size'] : $bg['size'];
		}
		if ( ! empty( $s['background_position'] ) && $bg['position'] === '' ) {
			$bg['position'] = sanitize_text_field( (string) $s['background_position'] );
		}
		if ( ! empty( $s['background_repeat'] ) && $bg['repeat'] === '' ) {
			$bg['repeat'] = sanitize_text_field( (string) $s['background_repeat'] );
		}
		if ( ! empty( $s['background_overlay'] ) && $bg['overlay_color'] === '' ) {
			$bg['overlay_color'] = self::color( $s['background_overlay'] );
		}
		if ( ! empty( $s['overlay_color'] ) && $bg['overlay_color'] === '' ) {
			$bg['overlay_color'] = self::color( $s['overlay_color'] );
			if ( isset( $s['overlay_opacity'] ) ) {
				$bg['overlay_opacity'] = max( 0, min( 1, (float) $s['overlay_opacity'] ) );
			}
			if ( ! empty( $s['overlay_blend_mode'] ) ) {
				$bg['overlay_blend'] = sanitize_key( (string) $s['overlay_blend_mode'] );
			}
		}
		if ( ! empty( $s['background_gradient'] ) && is_string( $s['background_gradient'] ) ) {
			$g = self::parse_gradient_string( $s['background_gradient'] );
			$bg = array_merge( $bg, array_filter( $g, function ( $x ) {
				return $x !== '' && $x !== null;
			} ) );
			if ( ( $bg['type'] ?? '' ) === 'classic' && ( $bg['custom'] ?? '' ) !== '' ) {
				$bg['type'] = 'gradient';
			}
		}
		if ( ! empty( $s['background_video'] ) && $bg['video_url'] === '' ) {
			$bg['video_url']   = esc_url_raw( (string) $s['background_video'] );
			$bg['video_start'] = absint( $s['background_video_start'] ?? 0 );
			$bg['video_end']   = absint( $s['background_video_end'] ?? 0 );
			$bg['video_poster'] = esc_url_raw( (string) ( $s['background_video_poster'] ?? '' ) );
			$bg['video_loop']   = ! isset( $s['background_video_loop'] ) || ! empty( $s['background_video_loop'] );
			$bg['video_mobile'] = ! empty( $s['background_video_mobile'] );
			if ( $bg['type'] === 'classic' ) {
				$bg['type'] = 'video';
			}
		}
		return $bg;
	}

	public static function should_struct_background( array $s ) {
		$bg = $s['background'] ?? null;
		if ( is_array( $bg ) ) {
			return true;
		}
		if ( is_string( $bg ) && stripos( $bg, 'gradient(' ) !== false ) {
			return true;
		}
		foreach ( array( 'background_image', 'background_gradient', 'background_overlay', 'background_video', 'overlay_color' ) as $k ) {
			if ( ! empty( $s[ $k ] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function background_of( array $s ) {
		$bg = $s['background'] ?? null;
		if ( is_array( $bg ) ) {
			$bg = self::sanitize_background( $bg );
		} else {
			$bg = self::migrate_background( $s );
		}
		return is_array( $bg ) ? $bg : self::sanitize_background( array() );
	}

	public static function background_map( $v ) {
		$v = is_array( $v ) ? self::sanitize_background( $v ) : self::sanitize_background( array() );
		$out = array();
		$type = $v['type'];
		if ( $type === 'gradient' ) {
			$img = self::compile_background_image( $v );
			if ( $img !== '' ) {
				$out['background-image'] = $img;
			}
			if ( $v['color'] !== '' ) {
				$out['background-color'] = $v['color'];
			}
			return $out;
		}
		if ( $v['color'] !== '' ) {
			$out['background-color'] = $v['color'];
		}
		$url = $v['image_url'];
		if ( $url === '' && $type === 'video' && $v['video_poster'] !== '' ) {
			$url = $v['video_poster'];
		}
		if ( $url !== '' && ( $type === 'classic' || $type === 'video' ) ) {
			$out['background-image'] = 'url(' . esc_url( $url ) . ')';
		}
		if ( $v['size'] !== '' ) {
			$out['background-size'] = $v['size'];
		}
		if ( $v['position'] !== '' ) {
			$out['background-position'] = $v['position'];
		}
		if ( $v['repeat'] !== '' ) {
			$out['background-repeat'] = $v['repeat'];
		}
		if ( $v['attachment'] !== '' ) {
			$out['background-attachment'] = $v['attachment'];
		}
		return $out;
	}

	public static function sanitize_gradient_position( $v ) {
		$allowed = array( 'center center', 'center left', 'center right', 'top center', 'top left', 'top right', 'bottom center', 'bottom left', 'bottom right' );
		$v       = strtolower( trim( (string) $v ) );
		return in_array( $v, $allowed, true ) ? $v : 'center center';
	}

	public static function compile_gradient( $v ) {
		if ( is_string( $v ) && $v !== '' ) {
			$v = self::parse_gradient_string( $v );
		}
		$v = is_array( $v ) ? $v : array();
		if ( ( $v['custom'] ?? '' ) !== '' && ( $v['gradient_a'] ?? '' ) === '' && ( $v['gradient_b'] ?? '' ) === '' ) {
			return self::sanitize_css_value( $v['custom'] );
		}
		$a    = self::color( $v['gradient_a'] ?? '' );
		$b    = self::color( $v['gradient_b'] ?? '' );
		$base = self::color( $v['color'] ?? '' );
		if ( $a === '' ) {
			$a = $base !== '' ? $base : '#000000';
		}
		if ( $b === '' ) {
			$b = '#ffffff';
		}
		$a_pos = max( 0, min( 100, (int) ( $v['gradient_a_pos'] ?? 0 ) ) );
		$b_pos = max( 0, min( 100, (int) ( $v['gradient_b_pos'] ?? 100 ) ) );
		$stops = $a . ' ' . $a_pos . '%, ' . $b . ' ' . $b_pos . '%';
		if ( ( $v['gradient_type'] ?? '' ) === 'radial' ) {
			return 'radial-gradient(circle at ' . self::sanitize_gradient_position( $v['gradient_position'] ?? '' ) . ', ' . $stops . ')';
		}
		$angle = isset( $v['gradient_angle'] ) ? (int) $v['gradient_angle'] : 180;
		$angle = max( 0, min( 360, $angle ) );
		return 'linear-gradient(' . $angle . 'deg, ' . $stops . ')';
	}

	public static function compile_background_image( $v ) {
		$v = is_array( $v ) ? self::sanitize_background( $v ) : self::sanitize_background( array() );
		if ( $v['type'] !== 'gradient' ) {
			return $v['image_url'] !== '' ? 'url(' . esc_url( $v['image_url'] ) . ')' : '';
		}
		return self::compile_gradient( $v );
	}

	public static function parse_gradient_string( $css ) {
		$css = trim( (string) $css );
		$out = array( 'type' => 'gradient', 'custom' => $css );
		if ( preg_match( '/linear-gradient\s*\(\s*(-?\d+(?:\.\d+)?)deg\s*,\s*([^,]+?)\s+(\d+%)?\s*,\s*([^,)]+?)\s+(\d+%)?\s*\)/i', $css, $m ) ) {
			$out['gradient_type']  = 'linear';
			$out['gradient_angle'] = (int) $m[1];
			$out['gradient_a']     = trim( $m[2] );
			$out['gradient_a_pos'] = isset( $m[3] ) ? (int) $m[3] : 0;
			$out['gradient_b']     = trim( $m[4] );
			$out['gradient_b_pos'] = isset( $m[5] ) ? (int) $m[5] : 100;
			$out['custom']         = '';
		} elseif ( preg_match( '/linear-gradient\s*\(\s*(-?\d+(?:\.\d+)?)deg\s*,\s*([^,]+)\s*,\s*([^)]+)\)/i', $css, $m ) ) {
			$out['gradient_type']  = 'linear';
			$out['gradient_angle'] = (int) $m[1];
			$out['gradient_a']     = trim( $m[2] );
			$out['gradient_b']     = trim( $m[3] );
			$out['custom']         = '';
		}
		return $out;
	}

	public static function layers_html( array $s ) {
		$bg = self::background_of( $s );
		$html = '';
		if ( $bg['type'] === 'video' && $bg['video_url'] !== '' ) {
			$start  = $bg['video_start'];
			$end    = $bg['video_end'];
			$src    = esc_url( $bg['video_url'] ) . ( ( $start || $end ) ? '#t=' . $start . ( $end ? ',' . $end : '' ) : '' );
			$poster = $bg['video_poster'] !== '' ? ' poster="' . esc_url( $bg['video_poster'] ) . '"' : '';
			$html  .= '<div class="lb-container-video' . ( $bg['video_mobile'] ? '' : ' lb-hide-mobile-video' ) . '" aria-hidden="true"><video class="lb-container-video-media" autoplay muted playsinline' . ( $bg['video_loop'] ? ' loop' : '' ) . ' preload="metadata"' . $poster . ' data-lb-bg-video data-start="' . $start . '" data-end="' . $end . '"><source src="' . $src . '"></video></div>';
		}
		if ( $bg['type'] === 'slideshow' && $bg['slideshow_ids'] !== '' ) {
			$ids = array_values( array_filter( array_map( 'absint', preg_split( '/[,\s]+/', $bg['slideshow_ids'] ) ) ) );
			if ( $ids ) {
				$html .= '<div class="lb-bg-slideshow lb-bg-slideshow-' . esc_attr( $bg['slideshow_transition'] ) . '" data-lb-bg-slideshow data-duration="' . esc_attr( (string) $bg['slideshow_duration'] ) . '" aria-hidden="true">';
				foreach ( $ids as $i => $id ) {
					$url = $bg['slideshow_urls'][ (string) $id ] ?? '';
					if ( $url === '' && function_exists( 'wp_get_attachment_image_url' ) ) {
						$url = (string) wp_get_attachment_image_url( $id, 'large' );
					}
					if ( $url === '' ) {
						continue;
					}
					$html .= '<img src="' . esc_url( $url ) . '" alt="" class="' . ( $i === 0 ? 'is-active' : '' ) . '">';
				}
				$html .= '</div>';
			}
		}
		if ( $bg['overlay_color'] !== '' ) {
			$blend = $bg['overlay_blend'] !== '' ? 'mix-blend-mode:' . esc_attr( $bg['overlay_blend'] ) . ';' : '';
			$html .= '<div class="lb-container-overlay" aria-hidden="true" style="background:' . esc_attr( $bg['overlay_color'] ) . ';opacity:' . esc_attr( (string) $bg['overlay_opacity'] ) . ';' . $blend . '"></div>';
		}
		return $html;
	}

	public static function has_layers( array $s ) {
		return self::layers_html( $s ) !== '';
	}

	public static function inject_layers( $html, $el, $s, $n ) {
		$s = is_array( $s ) ? $s : array();
		// Container already paints its own layers from the same helper.
		if ( is_object( $el ) && method_exists( $el, 'type' ) && $el->type() === 'container' ) {
			return $html;
		}
		$layers = self::layers_html( $s );
		if ( $layers === '' || ! is_string( $html ) ) {
			return $html;
		}
		return $layers . $html;
	}

	/* ---------- shadows ---------- */

	public static function sanitize_text_shadow( $v ) {
		if ( is_string( $v ) ) {
			$v = self::parse_text_shadow( $v );
		}
		$v = is_array( $v ) ? $v : array();
		return array(
			'x'     => self::num( $v['x'] ?? 0, -50, 50 ),
			'y'     => self::num( $v['y'] ?? 0, -50, 50 ),
			'blur'  => self::num( $v['blur'] ?? 0, 0, 80 ),
			'color' => self::color( $v['color'] ?? '' ) ?: 'rgba(0,0,0,.25)',
		);
	}

	public static function sanitize_box_shadow( $v ) {
		if ( is_string( $v ) ) {
			$v = self::parse_box_shadow( $v );
		}
		$v = is_array( $v ) ? $v : array();
		return array(
			'x'      => self::num( $v['x'] ?? 0, -80, 80 ),
			'y'      => self::num( $v['y'] ?? 0, -80, 80 ),
			'blur'   => self::num( $v['blur'] ?? 0, 0, 80 ),
			'spread' => self::num( $v['spread'] ?? 0, -40, 40 ),
			'color'  => self::color( $v['color'] ?? '' ) ?: 'rgba(0,0,0,.15)',
			'inset'  => ! empty( $v['inset'] ),
		);
	}

	public static function compile_text_shadow( $v ) {
		$v = self::sanitize_text_shadow( $v );
		if ( (float) $v['x'] === 0.0 && (float) $v['y'] === 0.0 && (float) $v['blur'] === 0.0 ) {
			return '';
		}
		return $v['x'] . 'px ' . $v['y'] . 'px ' . $v['blur'] . 'px ' . $v['color'];
	}

	public static function compile_box_shadow( $v ) {
		$v = self::sanitize_box_shadow( $v );
		if ( (float) $v['x'] === 0.0 && (float) $v['y'] === 0.0 && (float) $v['blur'] === 0.0 && (float) $v['spread'] === 0.0 && empty( $v['inset'] ) ) {
			return '';
		}
		return ( $v['inset'] ? 'inset ' : '' ) . $v['x'] . 'px ' . $v['y'] . 'px ' . $v['blur'] . 'px ' . $v['spread'] . 'px ' . $v['color'];
	}

	public static function parse_text_shadow( $css ) {
		$css = trim( (string) $css );
		if ( $css === '' ) {
			return array(
				'x'     => 0,
				'y'     => 0,
				'blur'  => 0,
				'color' => 'rgba(0,0,0,.25)',
			);
		}
		$color = 'rgba(0,0,0,.25)';
		if ( preg_match( '/(rgba?\([^)]+\)|hsla?\([^)]+\)|#[0-9a-fA-F]{3,8}|\b[a-z]+)$/i', $css, $m ) ) {
			$color = $m[1];
			$css   = trim( substr( $css, 0, -strlen( $m[1] ) ) );
		}
		$nums = preg_split( '/\s+/', $css );
		return array(
			'x'     => isset( $nums[0] ) ? (float) $nums[0] : 0,
			'y'     => isset( $nums[1] ) ? (float) $nums[1] : 0,
			'blur'  => isset( $nums[2] ) ? (float) $nums[2] : 0,
			'color' => $color,
		);
	}

	public static function parse_box_shadow( $css ) {
		$css   = trim( (string) $css );
		$inset = (bool) preg_match( '/\binset\b/i', $css );
		$css   = trim( preg_replace( '/\binset\b/i', '', $css ) );
		$t     = self::parse_text_shadow( $css );
		$t['spread'] = 0;
		$t['inset']  = $inset;
		if ( preg_match_all( '/-?\d*\.?\d+/', preg_replace( '/(rgba?\([^)]+\)|hsla?\([^)]+\)|#[0-9a-fA-F]{3,8})/i', '', $css ), $m ) && count( $m[0] ) >= 4 ) {
			$t['spread'] = (float) $m[0][3];
		}
		return $t;
	}

	/* ---------- filter / transform / transition ---------- */

	public static function sanitize_filter( $v ) {
		if ( is_string( $v ) ) {
			$v = self::parse_filter( $v );
		}
		$v = is_array( $v ) ? $v : array();
		return array(
			'blur'       => self::sanitize_length( $v['blur'] ?? '' ),
			'brightness' => self::unitless( $v['brightness'] ?? '', 0, 4 ),
			'contrast'   => self::unitless( $v['contrast'] ?? '', 0, 4 ),
			'saturate'   => self::unitless( $v['saturate'] ?? '', 0, 4 ),
			'hue'        => self::sanitize_length( $v['hue'] ?? '' ),
			'grayscale'  => self::unitless( $v['grayscale'] ?? '', 0, 1 ),
			'invert'     => self::unitless( $v['invert'] ?? '', 0, 1 ),
			'sepia'      => self::unitless( $v['sepia'] ?? '', 0, 1 ),
		);
	}

	public static function compile_filter( $v ) {
		if ( is_string( $v ) ) {
			return self::sanitize_css_value( $v );
		}
		$v = self::sanitize_filter( $v );
		$parts = array();
		if ( $v['blur'] !== '' && (float) $v['blur'] !== 0.0 ) {
			$parts[] = 'blur(' . self::length( $v['blur'] ) . ')';
		}
		if ( $v['brightness'] !== '' && (float) $v['brightness'] !== 1.0 ) {
			$parts[] = 'brightness(' . $v['brightness'] . ')';
		}
		if ( $v['contrast'] !== '' && (float) $v['contrast'] !== 1.0 ) {
			$parts[] = 'contrast(' . $v['contrast'] . ')';
		}
		if ( $v['saturate'] !== '' && (float) $v['saturate'] !== 1.0 ) {
			$parts[] = 'saturate(' . $v['saturate'] . ')';
		}
		if ( $v['hue'] !== '' && (float) $v['hue'] !== 0.0 ) {
			$h = self::length( $v['hue'] );
			if ( ! preg_match( '/deg$/i', $h ) ) {
				$h .= 'deg';
			}
			$parts[] = 'hue-rotate(' . $h . ')';
		}
		if ( $v['grayscale'] !== '' && (float) $v['grayscale'] !== 0.0 ) {
			$parts[] = 'grayscale(' . $v['grayscale'] . ')';
		}
		if ( $v['invert'] !== '' && (float) $v['invert'] !== 0.0 ) {
			$parts[] = 'invert(' . $v['invert'] . ')';
		}
		if ( $v['sepia'] !== '' && (float) $v['sepia'] !== 0.0 ) {
			$parts[] = 'sepia(' . $v['sepia'] . ')';
		}
		return implode( ' ', $parts );
	}

	public static function parse_filter( $css ) {
		$out = array(
			'blur'       => '',
			'brightness' => '',
			'contrast'   => '',
			'saturate'   => '',
			'hue'        => '',
			'grayscale'  => '',
			'invert'     => '',
			'sepia'      => '',
		);
		if ( preg_match_all( '/(blur|brightness|contrast|saturate|hue-rotate|grayscale|invert|sepia)\(\s*([^)]+)\s*\)/i', (string) $css, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $hit ) {
				$fn = strtolower( $hit[1] );
				$val = trim( $hit[2] );
				if ( $fn === 'hue-rotate' ) {
					$out['hue'] = $val;
				} else {
					$out[ $fn ] = $val;
				}
			}
		}
		return $out;
	}

	public static function sanitize_transform( $v ) {
		if ( is_string( $v ) ) {
			$v = self::parse_transform( $v );
		}
		$v = is_array( $v ) ? $v : array();
		return array(
			'translate_x' => self::sanitize_length( $v['translate_x'] ?? '' ),
			'translate_y' => self::sanitize_length( $v['translate_y'] ?? '' ),
			'rotate'      => self::sanitize_length( $v['rotate'] ?? '' ),
			'scale_x'     => self::unitless( $v['scale_x'] ?? '', 0, 8 ),
			'scale_y'     => self::unitless( $v['scale_y'] ?? '', 0, 8 ),
			'skew_x'      => self::sanitize_length( $v['skew_x'] ?? '' ),
			'skew_y'      => self::sanitize_length( $v['skew_y'] ?? '' ),
			'origin'      => sanitize_text_field( (string) ( $v['origin'] ?? '' ) ),
		);
	}

	public static function compile_transform( $v ) {
		if ( is_string( $v ) ) {
			return self::sanitize_css_value( $v );
		}
		$v = self::sanitize_transform( $v );
		$parts = array();
		$tx = $v['translate_x'] !== '' ? self::length( $v['translate_x'] ) : '';
		$ty = $v['translate_y'] !== '' ? self::length( $v['translate_y'] ) : '';
		if ( $tx !== '' || $ty !== '' ) {
			$parts[] = 'translate(' . ( $tx !== '' ? $tx : '0' ) . ', ' . ( $ty !== '' ? $ty : '0' ) . ')';
		}
		if ( $v['rotate'] !== '' && (float) $v['rotate'] !== 0.0 ) {
			$r = self::length( $v['rotate'] );
			if ( ! preg_match( '/deg$/i', $r ) ) {
				$r .= 'deg';
			}
			$parts[] = 'rotate(' . $r . ')';
		}
		$sx = $v['scale_x'];
		$sy = $v['scale_y'];
		if ( ( $sx !== '' && (float) $sx !== 1.0 ) || ( $sy !== '' && (float) $sy !== 1.0 ) ) {
			$parts[] = 'scale(' . ( $sx !== '' ? $sx : '1' ) . ', ' . ( $sy !== '' ? $sy : '1' ) . ')';
		}
		if ( $v['skew_x'] !== '' && (float) $v['skew_x'] !== 0.0 ) {
			$x = self::length( $v['skew_x'] );
			if ( ! preg_match( '/deg$/i', $x ) ) {
				$x .= 'deg';
			}
			$parts[] = 'skewX(' . $x . ')';
		}
		if ( $v['skew_y'] !== '' && (float) $v['skew_y'] !== 0.0 ) {
			$y = self::length( $v['skew_y'] );
			if ( ! preg_match( '/deg$/i', $y ) ) {
				$y .= 'deg';
			}
			$parts[] = 'skewY(' . $y . ')';
		}
		return implode( ' ', $parts );
	}

	public static function parse_transform( $css ) {
		$out = array(
			'translate_x' => '',
			'translate_y' => '',
			'rotate'      => '',
			'scale_x'     => '',
			'scale_y'     => '',
			'skew_x'      => '',
			'skew_y'      => '',
			'origin'      => '',
		);
		$css = (string) $css;
		if ( preg_match( '/translate\(\s*([^,)]+)\s*,\s*([^)]+)\)/i', $css, $m ) ) {
			$out['translate_x'] = trim( $m[1] );
			$out['translate_y'] = trim( $m[2] );
		}
		if ( preg_match( '/translateX\(\s*([^)]+)\)/i', $css, $m ) ) {
			$out['translate_x'] = trim( $m[1] );
		}
		if ( preg_match( '/translateY\(\s*([^)]+)\)/i', $css, $m ) ) {
			$out['translate_y'] = trim( $m[1] );
		}
		if ( preg_match( '/rotate\(\s*([^)]+)\)/i', $css, $m ) ) {
			$out['rotate'] = trim( $m[1] );
		}
		if ( preg_match( '/scale\(\s*([^,)]+)\s*(?:,\s*([^)]+))?\)/i', $css, $m ) ) {
			$out['scale_x'] = trim( $m[1] );
			$out['scale_y'] = isset( $m[2] ) && trim( $m[2] ) !== '' ? trim( $m[2] ) : trim( $m[1] );
		}
		if ( preg_match( '/scaleX\(\s*([^)]+)\)/i', $css, $m ) ) {
			$out['scale_x'] = trim( $m[1] );
		}
		if ( preg_match( '/scaleY\(\s*([^)]+)\)/i', $css, $m ) ) {
			$out['scale_y'] = trim( $m[1] );
		}
		if ( preg_match( '/skewX\(\s*([^)]+)\)/i', $css, $m ) ) {
			$out['skew_x'] = trim( $m[1] );
		}
		if ( preg_match( '/skewY\(\s*([^)]+)\)/i', $css, $m ) ) {
			$out['skew_y'] = trim( $m[1] );
		}
		if ( preg_match( '/skew\(\s*([^,)]+)\s*(?:,\s*([^)]+))?\)/i', $css, $m ) ) {
			$out['skew_x'] = trim( $m[1] );
			$out['skew_y'] = isset( $m[2] ) ? trim( $m[2] ) : '';
		}
		return $out;
	}

	public static function sanitize_transition( $v ) {
		if ( is_string( $v ) ) {
			$v = self::parse_transition( $v );
		}
		$v = is_array( $v ) ? $v : array();
		$ease = sanitize_text_field( (string) ( $v['easing'] ?? 'ease' ) );
		$ok   = array( 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear' );
		if ( ! in_array( $ease, $ok, true ) ) {
			$ease = 'ease';
		}
		$prop = sanitize_text_field( (string) ( $v['property'] ?? 'all' ) );
		if ( $prop === '' ) {
			$prop = 'all';
		}
		return array(
			'property' => $prop,
			'duration' => self::sanitize_time( $v['duration'] ?? '0.3s' ),
			'delay'    => self::sanitize_time( $v['delay'] ?? '0s' ),
			'easing'   => $ease,
		);
	}

	public static function compile_transition( $v ) {
		if ( is_string( $v ) ) {
			return self::sanitize_css_value( $v );
		}
		$v = self::sanitize_transition( $v );
		$dur = $v['duration'] !== '' ? $v['duration'] : '0s';
		if ( $dur === '0s' && ( $v['delay'] === '' || $v['delay'] === '0s' ) && $v['property'] === 'all' ) {
			return '';
		}
		$out = $v['property'] . ' ' . $dur . ' ' . $v['easing'];
		if ( $v['delay'] !== '' && $v['delay'] !== '0s' ) {
			$out .= ' ' . $v['delay'];
		}
		return $out;
	}

	public static function parse_transition( $css ) {
		$css = trim( (string) $css );
		$out = array(
			'property' => 'all',
			'duration' => '0.3s',
			'delay'    => '0s',
			'easing'   => 'ease',
		);
		if ( $css === '' ) {
			return $out;
		}
		$parts = preg_split( '/\s+/', $css );
		if ( ! $parts ) {
			return $out;
		}
		$times = array();
		foreach ( $parts as $p ) {
			if ( preg_match( '/^\d*\.?\d+(s|ms)$/i', $p ) ) {
				$times[] = $p;
			} elseif ( in_array( $p, array( 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear' ), true ) ) {
				$out['easing'] = $p;
			} elseif ( ! preg_match( '/^\d/', $p ) ) {
				$out['property'] = $p;
			}
		}
		if ( isset( $times[0] ) ) {
			$out['duration'] = $times[0];
		}
		if ( isset( $times[1] ) ) {
			$out['delay'] = $times[1];
		}
		return $out;
	}

	public static function sanitize_gaps( $v ) {
		if ( is_string( $v ) || is_numeric( $v ) ) {
			$one = self::sanitize_length( $v, 'px' );
			return array(
				'row'    => $one,
				'column' => $one,
				'linked' => true,
			);
		}
		$v = is_array( $v ) ? $v : array();
		$out = array(
			'row'    => self::sanitize_length( $v['row'] ?? '', 'px' ),
			'column' => self::sanitize_length( $v['column'] ?? '', 'px' ),
			'linked' => ! empty( $v['linked'] ),
		);
		if ( $out['linked'] ) {
			$out['column'] = $out['row'];
		}
		return $out;
	}

	public static function compile_gaps( $v ) {
		$v = self::sanitize_gaps( $v );
		$row = $v['row'] !== '' ? self::length( $v['row'] ) : '';
		$col = $v['column'] !== '' ? self::length( $v['column'] ) : '';
		if ( $row === '' && $col === '' ) {
			return '';
		}
		if ( $v['linked'] || $row === $col ) {
			return $row !== '' ? $row : $col;
		}
		return ( $row !== '' ? $row : '0' ) . ' ' . ( $col !== '' ? $col : '0' );
	}

	/* ---------- layers / utils ---------- */

	private static function num( $v, $min, $max ) {
		return max( $min, min( $max, (float) $v ) );
	}

	private static function unitless( $v, $min, $max ) {
		$v = trim( (string) $v );
		if ( $v === '' ) {
			return '';
		}
		if ( ! is_numeric( $v ) ) {
			return '';
		}
		$n = max( $min, min( $max, (float) $v ) );
		return rtrim( rtrim( number_format( $n, 4, '.', '' ), '0' ), '.' );
	}

	private static function sanitize_time( $v ) {
		$v = trim( (string) $v );
		if ( $v === '' ) {
			return '';
		}
		if ( preg_match( '/^(\d*\.?\d+)\s*(s|ms)?$/i', $v, $m ) ) {
			$n = rtrim( rtrim( number_format( (float) $m[1], 4, '.', '' ), '0' ), '.' );
			$u = strtolower( $m[2] ?? 's' );
			if ( $u === '' ) {
				$u = 's';
			}
			return $n . $u;
		}
		return '';
	}

	private static function sanitize_css_value( $v ) {
		$v = preg_replace( '/<[^>]*>|expression\s*\(|javascript\s*:/i', '', wp_strip_all_tags( (string) $v ) );
		return is_string( $v ) ? trim( $v ) : '';
	}
}
