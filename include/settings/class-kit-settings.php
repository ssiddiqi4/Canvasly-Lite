<?php
namespace CanvaslyLite\Settings;

use CanvaslyLite\Design\Variables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site kit settings (Roadmap 2.3): layout, identity, lightbox and background.
 * Identity fields read/write WordPress core options (blogname, blogdescription,
 * custom_logo, site_icon). Layout/lightbox/background compile to global CSS.
 */
class KitSettings {
	const KEY  = 'canvasly_lite_kit_settings';
	const ROOT = '.lb-page, .lb-frame-root';

	public static function init() {
		add_action( 'after_setup_theme', array( self::class, 'theme_support' ), 20 );
	}

	public static function theme_support() {
		if ( ! function_exists( 'get_theme_support' ) || ! get_theme_support( 'custom-logo' ) ) {
			add_theme_support( 'custom-logo' );
		}
	}

	public static function defaults() {
		return array(
			'layout'     => array(
				'content_width'        => '',
				'widgets_space'        => '',
				'page_title_selector'  => '',
				'default_template'     => 'default',
			),
			'identity'   => array(
				'title'       => '',
				'description' => '',
				'logo_id'     => 0,
				'logo_url'    => '',
				'favicon_id'  => 0,
				'favicon_url' => '',
			),
			'lightbox'   => array(
				'overlay_color'   => '',
				'ui_color'        => '',
				'show_close'      => true,
				'show_counter'    => false,
				'show_fullscreen' => false,
				'caption_source'  => 'caption',
			),
			'background' => array(
				'color'      => '',
				'image_id'   => 0,
				'image_url'  => '',
				'size'       => 'cover',
				'position'   => 'center center',
				'repeat'     => 'no-repeat',
				'attachment' => 'scroll',
			),
		);
	}

	public static function all() {
		$saved = get_option( self::KEY, array() );
		$out   = self::sanitize_map( is_array( $saved ) ? $saved : array() );
		$out   = self::merge_identity_from_core( $out );
		/** Filter the kit settings map. @param array $out */
		$filtered = apply_filters( 'canvasly-lite/kit_settings', $out );
		return is_array( $filtered ) ? self::sanitize_map( self::merge_identity_from_core( $filtered ) ) : $out;
	}

	public static function save( $data ) {
		if ( ! current_user_can( 'canvasly_lite_design' ) ) {
			return false;
		}
		$clean = self::sanitize_map( is_array( $data ) ? $data : array() );
		update_option( self::KEY, $clean, false );
		self::write_identity_to_core( $clean );
		self::sync_content_width( $clean['layout']['content_width'] ?? '' );
		if ( class_exists( GlobalSettings::class ) ) {
			GlobalSettings::invalidate_css_cache();
		}
		return self::merge_identity_from_core( $clean );
	}

	public static function default_template() {
		$d = self::all();
		$t = sanitize_key( (string) ( $d['layout']['default_template'] ?? 'default' ) );
		return in_array( $t, array( 'default', 'full_width', 'canvas' ), true ) ? $t : 'default';
	}

	public static function content_width() {
		$d = self::all();
		$w = trim( (string) ( $d['layout']['content_width'] ?? '' ) );
		if ( $w !== '' ) {
			return $w;
		}
		if ( class_exists( '\\CanvaslyLite\\Compatibility\\ThemeSupport' ) ) {
			$tw = \CanvaslyLite\Compatibility\ThemeSupport::content_width();
			if ( $tw !== '' ) {
				return $tw;
			}
		}
		if ( class_exists( GlobalSettings::class ) ) {
			$g = GlobalSettings::get();
			return trim( (string) ( $g['content_width'] ?? '' ) );
		}
		return '';
	}

	public static function lightbox_public() {
		$d = self::all();
		$lb = is_array( $d['lightbox'] ?? null ) ? $d['lightbox'] : array();
		return array(
			'overlay_color'   => self::css_val( $lb['overlay_color'] ?? '' ),
			'ui_color'        => self::css_val( $lb['ui_color'] ?? '' ),
			'show_close'      => ! empty( $lb['show_close'] ),
			'show_counter'    => ! empty( $lb['show_counter'] ),
			'show_fullscreen' => ! empty( $lb['show_fullscreen'] ),
			'caption_source'  => self::caption_source( $lb['caption_source'] ?? 'caption' ),
		);
	}

	/**
	 * data-lb-alt / data-lb-caption / data-lb-title for lightbox links.
	 *
	 * @param int    $attachment_id
	 * @param string $alt
	 * @param string $caption
	 * @param string $title
	 * @return string
	 */
	public static function lightbox_data_attrs( $attachment_id = 0, $alt = '', $caption = '', $title = '' ) {
		$id = absint( $attachment_id );
		if ( $id ) {
			if ( $alt === '' ) {
				$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			}
			if ( $caption === '' && function_exists( 'wp_get_attachment_caption' ) ) {
				$caption = (string) wp_get_attachment_caption( $id );
			}
			if ( $title === '' && function_exists( 'get_the_title' ) ) {
				$title = (string) get_the_title( $id );
			}
		}
		$out = '';
		if ( $alt !== '' ) {
			$out .= ' data-lb-alt="' . esc_attr( $alt ) . '"';
		}
		if ( $caption !== '' ) {
			$out .= ' data-lb-caption="' . esc_attr( $caption ) . '"';
		}
		if ( $title !== '' ) {
			$out .= ' data-lb-title="' . esc_attr( $title ) . '"';
		}
		return $out;
	}

	public static function sanitize_map( $raw ) {
		$d   = self::defaults();
		$raw = is_array( $raw ) ? $raw : array();

		$layout = is_array( $raw['layout'] ?? null ) ? $raw['layout'] : array();
		$d['layout']['content_width']       = self::sanitize_css_value( $layout['content_width'] ?? '' );
		$d['layout']['widgets_space']       = self::sanitize_css_value( $layout['widgets_space'] ?? '' );
		$d['layout']['page_title_selector'] = self::sanitize_selector( $layout['page_title_selector'] ?? '' );
		$tpl = sanitize_key( (string) ( $layout['default_template'] ?? 'default' ) );
		$d['layout']['default_template']    = in_array( $tpl, array( 'default', 'full_width', 'canvas' ), true ) ? $tpl : 'default';

		$ident = is_array( $raw['identity'] ?? null ) ? $raw['identity'] : array();
		$d['identity']['title']       = sanitize_text_field( $ident['title'] ?? '' );
		$d['identity']['description'] = sanitize_text_field( $ident['description'] ?? '' );
		$d['identity']['logo_id']     = absint( $ident['logo_id'] ?? 0 );
		$d['identity']['favicon_id']  = absint( $ident['favicon_id'] ?? 0 );
		$d['identity']['logo_url']    = self::sanitize_url( $ident['logo_url'] ?? '' );
		$d['identity']['favicon_url'] = self::sanitize_url( $ident['favicon_url'] ?? '' );
		if ( $d['identity']['logo_id'] && $d['identity']['logo_url'] === '' ) {
			$d['identity']['logo_url'] = self::attachment_url( $d['identity']['logo_id'] );
		}
		if ( $d['identity']['favicon_id'] && $d['identity']['favicon_url'] === '' ) {
			$d['identity']['favicon_url'] = self::attachment_url( $d['identity']['favicon_id'] );
		}

		$lb = is_array( $raw['lightbox'] ?? null ) ? $raw['lightbox'] : array();
		$d['lightbox']['overlay_color']   = class_exists( Variables::class ) ? Variables::sanitize_color_value( $lb['overlay_color'] ?? '' ) : self::sanitize_css_value( $lb['overlay_color'] ?? '' );
		$d['lightbox']['ui_color']        = class_exists( Variables::class ) ? Variables::sanitize_color_value( $lb['ui_color'] ?? '' ) : self::sanitize_css_value( $lb['ui_color'] ?? '' );
		$d['lightbox']['show_close']      = self::bool_val( $lb['show_close'] ?? true, true );
		$d['lightbox']['show_counter']    = self::bool_val( $lb['show_counter'] ?? false, false );
		$d['lightbox']['show_fullscreen'] = self::bool_val( $lb['show_fullscreen'] ?? false, false );
		$d['lightbox']['caption_source']  = self::caption_source( $lb['caption_source'] ?? 'caption' );

		$bg = is_array( $raw['background'] ?? null ) ? $raw['background'] : array();
		$d['background']['color']      = class_exists( Variables::class ) ? Variables::sanitize_color_value( $bg['color'] ?? '' ) : self::sanitize_css_value( $bg['color'] ?? '' );
		$d['background']['image_id']   = absint( $bg['image_id'] ?? 0 );
		$d['background']['image_url']  = self::sanitize_url( $bg['image_url'] ?? '' );
		if ( $d['background']['image_id'] && $d['background']['image_url'] === '' ) {
			$d['background']['image_url'] = self::attachment_url( $d['background']['image_id'] );
		}
		$size = sanitize_key( (string) ( $bg['size'] ?? 'cover' ) );
		$d['background']['size']       = in_array( $size, array( 'cover', 'contain', 'auto' ), true ) ? $size : 'cover';
		$d['background']['position']   = self::sanitize_css_value( $bg['position'] ?? 'center center' );
		if ( $d['background']['position'] === '' ) {
			$d['background']['position'] = 'center center';
		}
		$repeat = sanitize_key( (string) ( $bg['repeat'] ?? 'no-repeat' ) );
		$d['background']['repeat']     = in_array( $repeat, array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ), true ) ? $repeat : 'no-repeat';
		$attach = sanitize_key( (string) ( $bg['attachment'] ?? 'scroll' ) );
		$d['background']['attachment'] = in_array( $attach, array( 'scroll', 'fixed' ), true ) ? $attach : 'scroll';

		return $d;
	}

	public static function css( $data = null ) {
		$d   = is_array( $data ) ? self::sanitize_map( $data ) : self::all();
		$out = '';
		$vars = array();

		$width = self::css_val( $d['layout']['content_width'] ?? '' );
		if ( $width !== '' ) {
			$vars['page-width'] = $width;
		}
		$space = self::css_val( $d['layout']['widgets_space'] ?? '' );
		if ( $space !== '' ) {
			$vars['widgets-space'] = $space;
			$out .= self::sel( ' > .lb-node:not(:last-child)' ) . '{margin-block-end:' . $space . ';}';
		}

		$overlay = self::css_val( $d['lightbox']['overlay_color'] ?? '' );
		if ( $overlay !== '' ) {
			$vars['lightbox-overlay'] = $overlay;
		}
		$ui = self::css_val( $d['lightbox']['ui_color'] ?? '' );
		if ( $ui !== '' ) {
			$vars['lightbox-ui'] = $ui;
		}

		$title_sel = trim( (string) ( $d['layout']['page_title_selector'] ?? '' ) );
		if ( $title_sel === '' && class_exists( '\\CanvaslyLite\\Compatibility\\ThemeSupport' ) ) {
			$title_sel = \CanvaslyLite\Compatibility\ThemeSupport::page_title_selector();
		}
		if ( $title_sel !== '' ) {
			$out .= self::prefixed_selector( 'body.lb-document', $title_sel ) . '{display:none;}';
		}

		$bg_decls = array();
		$bg_color = self::css_val( $d['background']['color'] ?? '' );
		if ( $bg_color !== '' ) {
			$bg_decls[] = 'background-color:' . $bg_color;
			$vars['site-bg-color'] = $bg_color;
		}
		$bg_url = self::sanitize_url( $d['background']['image_url'] ?? '' );
		if ( $bg_url === '' && ! empty( $d['background']['image_id'] ) ) {
			$bg_url = self::attachment_url( absint( $d['background']['image_id'] ) );
		}
		if ( $bg_url !== '' ) {
			$bg_decls[] = 'background-image:url(' . esc_url( $bg_url ) . ')';
			$bg_decls[] = 'background-size:' . self::css_val( $d['background']['size'] ?? 'cover' );
			$bg_decls[] = 'background-position:' . self::css_val( $d['background']['position'] ?? 'center center' );
			$bg_decls[] = 'background-repeat:' . self::css_val( $d['background']['repeat'] ?? 'no-repeat' );
			$bg_decls[] = 'background-attachment:' . self::css_val( $d['background']['attachment'] ?? 'scroll' );
		}
		if ( $bg_decls ) {
			$bg_sel = self::sel( '' ) . ',body.lb-template-canvas,body.lb-template-full-width';
			$out   .= $bg_sel . '{' . implode( ';', $bg_decls ) . ';}';
		}

		$custom = '';
		foreach ( $vars as $name => $val ) {
			if ( $val === '' ) {
				continue;
			}
			$custom .= '--lb-' . $name . ':' . $val . ';';
			if ( $name === 'lightbox-overlay' ) {
				$custom .= '--lb-lightbox-overlay:' . $val . ';';
			}
			if ( $name === 'lightbox-ui' ) {
				$custom .= '--lb-lightbox-ui:' . $val . ';';
			}
			if ( $name === 'page-width' ) {
				$custom .= '--lb-page-width:' . $val . ';';
			}
			if ( $name === 'widgets-space' ) {
				$custom .= '--lb-widgets-space:' . $val . ';';
			}
		}
		$head = $custom ? self::sel( '' ) . '{' . $custom . '}' : '';
		$css  = $head . $out;
		if ( $css !== '' ) {
			$css = '/*lb-kit-style*/' . $css . '/*lb-kit-style-end*/';
		}
		/** Filter compiled kit CSS. @param string $css @param array $d */
		$filtered = apply_filters( 'canvasly-lite/kit_settings/css', $css, $d );
		return is_string( $filtered ) ? $filtered : $css;
	}

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

	private static function prefixed_selector( $prefix, $selector ) {
		$out = array();
		foreach ( array_map( 'trim', explode( ',', $selector ) ) as $part ) {
			if ( $part !== '' ) {
				$out[] = $prefix . ' ' . $part;
			}
		}
		return implode( ', ', $out );
	}

	public static function sanitize_css_value( $v ) {
		$s = trim( (string) $v );
		if ( $s === '' ) {
			return '';
		}
		if ( preg_match( '/^\{\{var:colors\.([a-zA-Z0-9_-]+)\}\}$/', $s, $m ) ) {
			return '{{var:colors.' . sanitize_key( $m[1] ) . '}}';
		}
		$s = preg_replace( '/[{}<>]|expression\s*\(|javascript\s*:|vbscript\s*:|@import/i', '', $s );
		return sanitize_text_field( $s );
	}

	public static function sanitize_selector( $v ) {
		$s = trim( (string) $v );
		if ( $s === '' ) {
			return '';
		}
		$s = preg_replace( '/[{}<>]|expression|javascript|@import/i', '', $s );
		$s = preg_replace( '/[^a-zA-Z0-9_\-\.\#\[\]=\"\'\,\:\>\+\~\*\(\)\s]/', '', $s );
		return substr( trim( (string) $s ), 0, 240 );
	}

	private static function sanitize_url( $v ) {
		$s = trim( (string) $v );
		if ( $s === '' ) {
			return '';
		}
		if ( preg_match( '/^\s*(javascript|vbscript|data)\s*:/i', $s ) ) {
			return '';
		}
		return esc_url_raw( $s );
	}

	private static function css_val( $v ) {
		$v = trim( (string) $v );
		if ( $v === '' ) {
			return '';
		}
		if ( class_exists( Variables::class ) ) {
			$v = Variables::resolve_references( $v );
		}
		return esc_attr( $v );
	}

	private static function bool_val( $v, $default ) {
		if ( $v === null || $v === '' ) {
			return (bool) $default;
		}
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_numeric( $v ) ) {
			return (int) $v !== 0;
		}
		$s = strtolower( (string) $v );
		if ( in_array( $s, array( 'false', '0', 'no', 'off' ), true ) ) {
			return false;
		}
		return (bool) $v;
	}

	private static function caption_source( $v ) {
		$s = sanitize_key( (string) $v );
		return in_array( $s, array( 'none', 'alt', 'caption', 'title' ), true ) ? $s : 'caption';
	}

	private static function attachment_url( $id ) {
		$id = absint( $id );
		if ( ! $id || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, 'full' );
		return $url ? esc_url_raw( $url ) : '';
	}

	private static function merge_identity_from_core( $d ) {
		$d['identity']['title']       = (string) get_option( 'blogname', $d['identity']['title'] );
		$d['identity']['description'] = (string) get_option( 'blogdescription', $d['identity']['description'] );
		$logo                         = function_exists( 'get_theme_mod' ) ? absint( get_theme_mod( 'custom_logo', $d['identity']['logo_id'] ) ) : absint( $d['identity']['logo_id'] );
		$icon                         = absint( get_option( 'site_icon', $d['identity']['favicon_id'] ) );
		$d['identity']['logo_id']     = $logo;
		$d['identity']['favicon_id']  = $icon;
		if ( $logo ) {
			$url = self::attachment_url( $logo );
			if ( $url !== '' ) {
				$d['identity']['logo_url'] = $url;
			}
		} else {
			$d['identity']['logo_url'] = '';
		}
		if ( $icon ) {
			$url = self::attachment_url( $icon );
			if ( $url !== '' ) {
				$d['identity']['favicon_url'] = $url;
			}
		} else {
			$d['identity']['favicon_url'] = '';
		}
		return $d;
	}

	private static function write_identity_to_core( $d ) {
		$ident = is_array( $d['identity'] ?? null ) ? $d['identity'] : array();
		update_option( 'blogname', sanitize_text_field( $ident['title'] ?? '' ) );
		update_option( 'blogdescription', sanitize_text_field( $ident['description'] ?? '' ) );
		$logo = absint( $ident['logo_id'] ?? 0 );
		if ( function_exists( 'set_theme_mod' ) ) {
			if ( $logo ) {
				set_theme_mod( 'custom_logo', $logo );
			} elseif ( function_exists( 'remove_theme_mod' ) ) {
				remove_theme_mod( 'custom_logo' );
			} else {
				set_theme_mod( 'custom_logo', 0 );
			}
		}
		update_option( 'site_icon', absint( $ident['favicon_id'] ?? 0 ) );
	}

	private static function sync_content_width( $width ) {
		$width = trim( (string) $width );
		if ( $width === '' || ! class_exists( GlobalSettings::class ) ) {
			return;
		}
		$g = GlobalSettings::get();
		if ( ( $g['content_width'] ?? '' ) === $width ) {
			return;
		}
		$g['content_width'] = $width;
		update_option( GlobalSettings::KEY, $g, false );
	}
}
