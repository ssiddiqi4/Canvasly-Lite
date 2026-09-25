<?php
namespace CanvaslyLite\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-level Theme Style (Roadmap 2.2): typography, buttons, images and form-field
 * defaults compiled into the global stylesheet and inherited by the editor canvas.
 */
class ThemeStyle {
	const KEY = 'canvasly_lite_theme_style';
	const ROOT = '.lb-page, .lb-frame-root';

	const TYPO_PROPS = array( 'font_family', 'font_size', 'font_weight', 'font_style', 'text_transform', 'text_decoration', 'line_height', 'letter_spacing' );

	const COLOR_KEYS = array(
		'color',
		'background',
		'border_color',
		'hover_color',
		'hover_background',
		'hover_border_color',
		'focus_color',
		'focus_background',
		'focus_border_color',
		'caption_color',
		'label_color',
		'placeholder_color',
	);

	public static function typography_targets() {
		return array( 'body', 'paragraph', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'link' );
	}

	public static function defaults() {
		$typo = array();
		foreach ( self::typography_targets() as $id ) {
			$typo[ $id ] = self::empty_typo();
			if ( $id === 'paragraph' ) {
				$typo[ $id ]['margin_bottom'] = '';
			}
			if ( $id === 'link' ) {
				$typo[ $id ]['hover_color']           = '';
				$typo[ $id ]['hover_text_decoration'] = '';
			}
		}
		return array(
			'typography'  => $typo,
			'buttons'     => self::empty_buttons(),
			'images'      => self::empty_images(),
			'form_fields' => self::empty_form_fields(),
		);
	}

	private static function empty_typo() {
		$out = array( 'color' => '' );
		foreach ( self::TYPO_PROPS as $p ) {
			$out[ $p ] = '';
		}
		return $out;
	}

	private static function empty_buttons() {
		return array(
			'font_family'        => '',
			'font_size'          => '',
			'font_weight'        => '',
			'font_style'         => '',
			'text_transform'     => '',
			'text_decoration'    => '',
			'line_height'        => '',
			'letter_spacing'     => '',
			'color'              => '',
			'background'         => '',
			'border_width'       => '',
			'border_style'       => '',
			'border_color'       => '',
			'border_radius'      => '',
			'padding'            => '',
			'shadow'             => '',
			'hover_color'        => '',
			'hover_background'   => '',
			'hover_border_color' => '',
			'hover_shadow'       => '',
			'transition'         => '',
		);
	}

	private static function empty_images() {
		return array(
			'border_width'    => '',
			'border_style'    => '',
			'border_color'    => '',
			'border_radius'   => '',
			'opacity'         => '',
			'shadow'          => '',
			'css_filter'      => '',
			'hover_opacity'   => '',
			'hover_shadow'    => '',
			'hover_css_filter'=> '',
			'caption_color'   => '',
			'caption_spacing' => '',
		);
	}

	private static function empty_form_fields() {
		return array(
			'font_family'         => '',
			'font_size'           => '',
			'font_weight'         => '',
			'font_style'          => '',
			'line_height'         => '',
			'letter_spacing'      => '',
			'color'               => '',
			'background'          => '',
			'placeholder_color'   => '',
			'border_width'        => '',
			'border_style'        => '',
			'border_color'        => '',
			'border_radius'       => '',
			'padding'             => '',
			'shadow'              => '',
			'label_color'         => '',
			'label_spacing'       => '',
			'hover_background'    => '',
			'hover_border_color'  => '',
			'focus_background'    => '',
			'focus_border_color'  => '',
			'focus_shadow'        => '',
			'transition'          => '',
		);
	}

	public static function all() {
		$saved = get_option( self::KEY, array() );
		$out   = self::sanitize_map( is_array( $saved ) ? $saved : array() );
		/** Filter the Theme Style map used by CSS and the editor. @param array $out */
		$filtered = apply_filters( 'canvasly-lite/theme_style', $out );
		return is_array( $filtered ) ? self::sanitize_map( $filtered ) : $out;
	}

	public static function save( $data ) {
		if ( ! current_user_can( 'canvasly_lite_design' ) ) {
			return false;
		}
		$clean = self::sanitize_map( is_array( $data ) ? $data : array() );
		update_option( self::KEY, $clean, false );
		if ( class_exists( '\\CanvaslyLite\\Settings\\GlobalSettings' ) ) {
			\CanvaslyLite\Settings\GlobalSettings::invalidate_css_cache();
		}
		return $clean;
	}

	public static function sanitize_map( $raw ) {
		$d    = self::defaults();
		$raw  = is_array( $raw ) ? $raw : array();
		$typo = is_array( $raw['typography'] ?? null ) ? $raw['typography'] : array();
		foreach ( self::typography_targets() as $id ) {
			$item = is_array( $typo[ $id ] ?? null ) ? $typo[ $id ] : array();
			$d['typography'][ $id ] = self::sanitize_group( $item, $d['typography'][ $id ] );
		}
		$d['buttons']     = self::sanitize_group( is_array( $raw['buttons'] ?? null ) ? $raw['buttons'] : array(), $d['buttons'] );
		$d['images']      = self::sanitize_group( is_array( $raw['images'] ?? null ) ? $raw['images'] : array(), $d['images'] );
		$d['form_fields'] = self::sanitize_group( is_array( $raw['form_fields'] ?? null ) ? $raw['form_fields'] : array(), $d['form_fields'] );
		return $d;
	}

	private static function sanitize_group( $item, $shape ) {
		$out = $shape;
		foreach ( $shape as $key => $fallback ) {
			$val = $item[ $key ] ?? '';
			if ( in_array( $key, self::COLOR_KEYS, true ) ) {
				$out[ $key ] = Variables::sanitize_color_value( $val );
			} else {
				$out[ $key ] = self::sanitize_css_value( $val );
			}
		}
		return $out;
	}

	public static function sanitize_css_value( $v ) {
		$s = trim( (string) $v );
		if ( $s === '' ) {
			return '';
		}
		if ( preg_match( '/^\{\{var:colors\.([a-zA-Z0-9_-]+)\}\}$/', $s, $m ) ) {
			return '{{var:colors.' . sanitize_key( $m[1] ) . '}}';
		}
		if ( preg_match( '/^var\(--lb-color-([a-zA-Z0-9_-]+)\)$/i', $s, $m ) ) {
			return '{{var:colors.' . sanitize_key( $m[1] ) . '}}';
		}
		$s = preg_replace( '/[{}<>]|expression\s*\(|javascript\s*:|vbscript\s*:|@import/i', '', $s );
		return sanitize_text_field( $s );
	}

	public static function css( $data = null ) {
		$d    = is_array( $data ) ? self::sanitize_map( $data ) : self::all();
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) && \CanvaslyLite\Settings\AdminSettings::disable_default_colors() ) {
			$d = self::strip_keys( $d, self::COLOR_KEYS );
		}
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) && \CanvaslyLite\Settings\AdminSettings::disable_default_fonts() ) {
			$d = self::strip_keys( $d, array( 'font_family' ) );
		}
		$out  = '';
		$vars = array();

		$body = $d['typography']['body'] ?? array();
		$out .= self::rule( self::sel( '' ), self::typo_decls( $body ) );
		self::collect_vars( $vars, 'body', $body );

		$p = $d['typography']['paragraph'] ?? array();
		$p_decl = self::typo_decls( $p );
		$mb     = self::css_val( $p['margin_bottom'] ?? '' );
		if ( $mb !== '' ) {
			$p_decl[]                  = 'margin-bottom:' . $mb;
			$vars['paragraph-spacing'] = $mb;
		}
		$out .= self::rule( self::sel( ' p' ) . ',' . self::sel( ' .lb-text' ) . ',' . self::sel( ' .lb-text-content' ), $p_decl );
		self::collect_vars( $vars, 'paragraph', $p );

		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) {
			$item = $d['typography'][ $tag ] ?? array();
			$out .= self::rule( self::sel( ' ' . $tag ), self::typo_decls( $item ) );
			self::collect_vars( $vars, $tag, $item );
		}

		$link = $d['typography']['link'] ?? array();
		$link_sel = self::sel( ' a:not(.lb-button):not(.lb-tab-button)' );
		$out .= self::rule( $link_sel, self::typo_decls( $link ) );
		$hover = array();
		$hc    = self::css_val( $link['hover_color'] ?? '' );
		if ( $hc !== '' ) {
			$hover[]                  = 'color:' . $hc;
			$vars['link-hover-color'] = $hc;
		}
		$hd = self::css_val( $link['hover_text_decoration'] ?? '' );
		if ( $hd !== '' ) {
			$hover[] = 'text-decoration:' . $hd;
		}
		$out .= self::rule( self::each( $link_sel, ':hover' ) . ',' . self::each( $link_sel, ':focus' ), $hover );
		self::collect_vars( $vars, 'link', $link );

		$btn     = $d['buttons'] ?? array();
		$btn_sel = self::sel( ' .lb-button' ) . ',' . self::sel( ' .lb-form button' ) . ',' . self::sel( ' .lb-read-more' ) . ',' . self::sel( ' .lb-price-table>a' ) . ',' . self::sel( ' .lb-login button' ) . ',' . self::sel( ' .lb-link-bio-links a' );
		$out    .= self::rule( $btn_sel, array_merge( self::typo_decls( $btn, false ), self::box_decls( $btn ) ) );
		$out    .= self::rule( self::each( $btn_sel, ':hover' ) . ',' . self::each( $btn_sel, ':focus' ), self::hover_decls( $btn ) );
		self::collect_vars( $vars, 'button', $btn );
		self::collect_box_vars( $vars, 'button', $btn );

		$img     = $d['images'] ?? array();
		$img_sel = self::sel( ' .lb-image-img' ) . ',' . self::sel( ' .lb-image img' ) . ',' . self::sel( ' .lb-node-image img' );
		$out    .= self::rule( $img_sel, self::image_decls( $img ) );
		$out    .= self::rule( self::each( $img_sel, ':hover' ), self::image_hover_decls( $img ) );
		$cap     = array();
		$cc      = self::css_val( $img['caption_color'] ?? '' );
		if ( $cc !== '' ) {
			$cap[] = 'color:' . $cc;
		}
		$cs = self::css_val( $img['caption_spacing'] ?? '' );
		if ( $cs !== '' ) {
			$cap[] = 'margin-top:' . $cs;
		}
		$out .= self::rule( self::sel( ' .lb-image figcaption' ) . ',' . self::sel( ' .lb-image-preview figcaption' ), $cap );
		self::collect_box_vars( $vars, 'image', $img );
		foreach ( array( 'opacity', 'css_filter', 'hover_opacity', 'hover_css_filter', 'caption_color', 'caption_spacing' ) as $k ) {
			$val = self::css_val( $img[ $k ] ?? '' );
			if ( $val !== '' ) {
				$vars[ 'image-' . str_replace( '_', '-', $k ) ] = $val;
			}
		}

		$form      = $d['form_fields'] ?? array();
		$field_sel = self::sel( ' .lb-form-field input:not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=hidden])' ) . ',' . self::sel( ' .lb-form-field textarea' ) . ',' . self::sel( ' .lb-form-field select' ) . ',' . self::sel( ' .lb-login input[type=text]' ) . ',' . self::sel( ' .lb-login input[type=password]' );
		$out      .= self::rule( $field_sel, array_merge( self::typo_decls( $form, false ), self::box_decls( $form ) ) );
		$ph        = self::css_val( $form['placeholder_color'] ?? '' );
		if ( $ph !== '' ) {
			$out .= self::rule( self::each( $field_sel, '::placeholder' ), array( 'color:' . $ph ) );
		}
		$out .= self::rule( self::each( $field_sel, ':hover' ), self::hover_decls( $form, array( 'hover_color' ) ) );
		$focus = self::hover_decls(
			array(
				'hover_color'        => $form['focus_color'] ?? '',
				'hover_background'   => $form['focus_background'] ?? '',
				'hover_border_color' => $form['focus_border_color'] ?? '',
				'hover_shadow'       => $form['focus_shadow'] ?? '',
			)
		);
		$out .= self::rule( self::each( $field_sel, ':focus' ) . ',' . self::each( $field_sel, ':focus-visible' ), $focus );
		$label = array();
		$lc    = self::css_val( $form['label_color'] ?? '' );
		if ( $lc !== '' ) {
			$label[] = 'color:' . $lc;
		}
		$ls = self::css_val( $form['label_spacing'] ?? '' );
		if ( $ls !== '' ) {
			$label[] = 'margin-bottom:' . $ls;
		}
		$out .= self::rule( self::sel( ' .lb-form-field > label' ) . ',' . self::sel( ' .lb-form-field label' ), $label );
		self::collect_vars( $vars, 'form', $form );
		self::collect_box_vars( $vars, 'form', $form );
		foreach ( array( 'placeholder_color', 'label_color', 'label_spacing', 'focus_background', 'focus_border_color', 'focus_shadow' ) as $k ) {
			$val = self::css_val( $form[ $k ] ?? '' );
			if ( $val !== '' ) {
				$vars[ 'form-' . str_replace( '_', '-', $k ) ] = $val;
			}
		}

		$link_color = self::css_val( $link['color'] ?? '' );
		if ( $link_color !== '' ) {
			$vars['link-color'] = $link_color;
		}
		$para_mb = self::css_val( $p['margin_bottom'] ?? '' );
		if ( $para_mb !== '' ) {
			$vars['paragraph-spacing'] = $para_mb;
		}

		$custom = '';
		foreach ( $vars as $name => $val ) {
			if ( $val === '' ) {
				continue;
			}
			$custom .= '--lb-theme-' . $name . ':' . $val . ';';
			if ( $name === 'link-color' ) {
				$custom .= '--lb-link-color:' . $val . ';';
			}
			if ( $name === 'link-hover-color' ) {
				$custom .= '--lb-link-hover:' . $val . ';';
			}
			if ( $name === 'paragraph-spacing' ) {
				$custom .= '--lb-paragraph-spacing:' . $val . ';';
			}
		}
		$head = $custom ? self::sel( '' ) . '{' . $custom . '}' : '';
		$css  = $head . $out;
		if ( $css !== '' ) {
			$css = '/*lb-theme-style*/' . $css . '/*lb-theme-style-end*/';
		}
		/** Filter compiled Theme Style CSS. @param string $css @param array $d */
		$filtered = apply_filters( 'canvasly-lite/theme_style/css', $css, $d );
		return is_string( $filtered ) ? $filtered : $css;
	}

	/** Expand `.lb-page, .lb-frame-root` + suffix into a valid grouped selector. */
	private static function sel( $suffix ) {
		$parts = array_map( 'trim', explode( ',', self::ROOT ) );
		if ( $suffix === '' ) {
			return implode( ', ', $parts );
		}
		$out = array();
		foreach ( $parts as $root ) {
			$out[] = $root . $suffix;
		}
		return implode( ', ', $out );
	}

	/** Append a pseudo-class/element to every selector in a comma list. */
	private static function each( $sel, $suffix ) {
		$out = array();
		foreach ( array_map( 'trim', explode( ',', $sel ) ) as $part ) {
			if ( $part !== '' ) {
				$out[] = $part . $suffix;
			}
		}
		return implode( ', ', $out );
	}

	private static function typo_decls( $item, $include_color = true ) {
		$item = is_array( $item ) ? $item : array();
		$d    = array();
		$map  = array(
			'font_family'     => 'font-family',
			'font_size'       => 'font-size',
			'font_weight'     => 'font-weight',
			'font_style'      => 'font-style',
			'text_transform'  => 'text-transform',
			'text_decoration' => 'text-decoration',
			'line_height'     => 'line-height',
			'letter_spacing'  => 'letter-spacing',
		);
		foreach ( $map as $key => $css ) {
			$val = self::css_val( $item[ $key ] ?? '', $key === 'font_family' );
			if ( $val !== '' ) {
				$d[] = $css . ':' . $val;
			}
		}
		if ( $include_color ) {
			$c = self::css_val( $item['color'] ?? '' );
			if ( $c !== '' ) {
				$d[] = 'color:' . $c;
			}
		}
		return $d;
	}

	private static function box_decls( $item ) {
		$item = is_array( $item ) ? $item : array();
		$d    = array();
		$c    = self::css_val( $item['color'] ?? '' );
		if ( $c !== '' ) {
			$d[] = 'color:' . $c;
		}
		$bg = self::css_val( $item['background'] ?? '' );
		if ( $bg !== '' ) {
			$d[] = 'background:' . $bg;
		}
		$bw = self::css_val( $item['border_width'] ?? '' );
		if ( $bw !== '' ) {
			$d[] = 'border-width:' . $bw;
		}
		$bs = self::css_val( $item['border_style'] ?? '' );
		if ( $bs !== '' ) {
			$d[] = 'border-style:' . $bs;
		}
		$bc = self::css_val( $item['border_color'] ?? '' );
		if ( $bc !== '' ) {
			$d[] = 'border-color:' . $bc;
		}
		$br = self::css_val( $item['border_radius'] ?? '' );
		if ( $br !== '' ) {
			$d[] = 'border-radius:' . $br;
		}
		$pad = self::css_val( $item['padding'] ?? '' );
		if ( $pad !== '' ) {
			$d[] = 'padding:' . $pad;
		}
		$sh = self::css_val( $item['shadow'] ?? '' );
		if ( $sh !== '' ) {
			$d[] = 'box-shadow:' . $sh;
		}
		$tr = self::css_val( $item['transition'] ?? '' );
		if ( $tr !== '' ) {
			$d[] = 'transition:' . $tr;
		}
		return $d;
	}

	private static function hover_decls( $item, $skip = array() ) {
		$item = is_array( $item ) ? $item : array();
		$d    = array();
		$map  = array(
			'hover_color'        => 'color',
			'hover_background'   => 'background',
			'hover_border_color' => 'border-color',
			'hover_shadow'       => 'box-shadow',
		);
		foreach ( $map as $key => $css ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			$val = self::css_val( $item[ $key ] ?? '' );
			if ( $val !== '' ) {
				$d[] = $css . ':' . $val;
			}
		}
		return $d;
	}

	private static function image_decls( $item ) {
		$item = is_array( $item ) ? $item : array();
		$d    = self::box_decls(
			array(
				'border_width'  => $item['border_width'] ?? '',
				'border_style'  => $item['border_style'] ?? '',
				'border_color'  => $item['border_color'] ?? '',
				'border_radius' => $item['border_radius'] ?? '',
				'shadow'        => $item['shadow'] ?? '',
			)
		);
		$op = self::css_val( $item['opacity'] ?? '' );
		if ( $op !== '' ) {
			$d[] = 'opacity:' . $op;
		}
		$f = self::css_val( $item['css_filter'] ?? '' );
		if ( $f !== '' ) {
			$d[] = 'filter:' . $f;
		}
		return $d;
	}

	private static function image_hover_decls( $item ) {
		$item = is_array( $item ) ? $item : array();
		$d    = array();
		$op   = self::css_val( $item['hover_opacity'] ?? '' );
		if ( $op !== '' ) {
			$d[] = 'opacity:' . $op;
		}
		$sh = self::css_val( $item['hover_shadow'] ?? '' );
		if ( $sh !== '' ) {
			$d[] = 'box-shadow:' . $sh;
		}
		$f = self::css_val( $item['hover_css_filter'] ?? '' );
		if ( $f !== '' ) {
			$d[] = 'filter:' . $f;
		}
		return $d;
	}

	private static function collect_vars( &$vars, $prefix, $item ) {
		$item = is_array( $item ) ? $item : array();
		foreach ( self::TYPO_PROPS as $prop ) {
			$val = self::css_val( $item[ $prop ] ?? '', $prop === 'font_family' );
			if ( $val !== '' ) {
				$vars[ $prefix . '-' . str_replace( '_', '-', $prop ) ] = $val;
			}
		}
		$c = self::css_val( $item['color'] ?? '' );
		if ( $c !== '' ) {
			$vars[ $prefix . '-color' ] = $c;
		}
	}

	private static function collect_box_vars( &$vars, $prefix, $item ) {
		$item = is_array( $item ) ? $item : array();
		foreach ( array( 'background', 'border_width', 'border_style', 'border_color', 'border_radius', 'padding', 'shadow', 'transition', 'hover_color', 'hover_background', 'hover_border_color', 'hover_shadow' ) as $key ) {
			$val = self::css_val( $item[ $key ] ?? '' );
			if ( $val !== '' ) {
				$vars[ $prefix . '-' . str_replace( '_', '-', $key ) ] = $val;
			}
		}
		$c = self::css_val( $item['color'] ?? '' );
		if ( $c !== '' ) {
			$vars[ $prefix . '-color' ] = $c;
		}
	}

	private static function rule( $selector, $decls ) {
		$decls = array_values( array_filter( (array) $decls, 'strlen' ) );
		if ( ! $decls ) {
			return '';
		}
		return $selector . '{' . implode( ';', $decls ) . ';}';
	}

	private static function css_val( $v, $font = false ) {
		$v = trim( (string) $v );
		if ( $v === '' ) {
			return '';
		}
		$v = Variables::resolve_references( $v );
		if ( $font ) {
			return self::css_font_family( $v );
		}
		return esc_attr( $v );
	}

	private static function css_font_family( $v ) {
		$v = trim( (string) $v );
		if ( $v === '' ) {
			return '';
		}
		if ( $v[0] === '"' || $v[0] === "'" || strpos( $v, ',' ) !== false || strpos( $v, 'var(' ) === 0 ) {
			return esc_attr( $v );
		}
		if ( preg_match( '/\s/', $v ) ) {
			return esc_attr( '"' . $v . '"' );
		}
		return esc_attr( $v );
	}

	public static function used_font_families() {
		$fonts = array();
		foreach ( self::used_fonts() as $item ) {
			$f = trim( (string) ( $item['font_family'] ?? '' ) );
			if ( $f !== '' ) {
				$fonts[ $f ] = true;
			}
		}
		return array_keys( $fonts );
	}

	/**
	 * Families with weight/style used by theme-style typography.
	 *
	 * @return array<int,array{font_family:string,font_weight:string,font_style:string}>
	 */
	public static function used_fonts() {
		$out  = array();
		$d    = self::all();
		$scan = function ( $item ) use ( &$out ) {
			$item = is_array( $item ) ? $item : array();
			$f    = trim( (string) ( $item['font_family'] ?? '' ) );
			if ( $f !== '' && strpos( $f, '{{' ) === false && ! preg_match( '/[,"\']/', $f ) ) {
				$out[] = array(
					'font_family' => $f,
					'font_weight' => (string) ( $item['font_weight'] ?? '' ),
					'font_style'  => (string) ( $item['font_style'] ?? '' ),
				);
			}
		};
		foreach ( (array) ( $d['typography'] ?? array() ) as $item ) {
			$scan( $item );
		}
		$scan( $d['buttons'] ?? array() );
		$scan( $d['form_fields'] ?? array() );
		return $out;
	}

	/**
	 * Blank listed keys recursively so Theme Style can skip default colors/fonts.
	 *
	 * @param mixed    $data
	 * @param string[] $keys
	 * @return mixed
	 */
	private static function strip_keys( $data, $keys ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		foreach ( $data as $k => $v ) {
			if ( is_array( $v ) ) {
				$data[ $k ] = self::strip_keys( $v, $keys );
			} elseif ( in_array( $k, $keys, true ) ) {
				$data[ $k ] = '';
			}
		}
		return $data;
	}
}
