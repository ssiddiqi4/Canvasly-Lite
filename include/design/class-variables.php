<?php
namespace CanvaslyLite\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide design tokens: system + custom global colors and typography presets,
 * plus sizes, fonts, effects and arbitrary custom groups. Output as CSS custom
 * properties (`--lb-color-*`, `--lb-typo-*`, …). Color controls bind with
 * `{{var:colors.id}}`; typography groups bind with the `typography_global` setting.
 */
class Variables {
	const KEY = 'canvasly_lite_variables';

	/** Group name in `{{var:group.name}}` → CSS custom-property prefix. */
	const GROUP_PREFIX = array(
		'colors'      => 'color',
		'fonts'       => 'font',
		'sizes'       => 'size',
		'effects'     => 'effect',
		'typography'  => 'typo',
		'typo'        => 'typo',
	);

	const TYPO_PROPS = array( 'font_family', 'font_size', 'font_weight', 'font_style', 'text_transform', 'text_decoration', 'line_height', 'letter_spacing' );

	public static function system_color_ids() {
		return array( 'primary', 'secondary', 'text', 'accent' );
	}

	public static function system_typography_ids() {
		return array( 'primary', 'secondary', 'text', 'accent' );
	}

	public static function defaults() {
		return array(
			'colors'       => array(
				'primary'   => '#3f7fdf',
				'secondary' => '#20242a',
				'text'      => '#333333',
				'accent'    => '#6c5ce7',
			),
			'color_titles' => array(
				'primary'   => __( 'Primary', 'canvasly-lite' ),
				'secondary' => __( 'Secondary', 'canvasly-lite' ),
				'text'      => __( 'Text', 'canvasly-lite' ),
				'accent'    => __( 'Accent', 'canvasly-lite' ),
			),
			'sizes'        => array(
				'space-sm' => '8px',
				'space-md' => '16px',
				'space-lg' => '32px',
			),
			'fonts'        => array(
				'heading' => '',
				'body'    => '',
			),
			'effects'      => array(
				'shadow' => '0 8px 24px rgba(0,0,0,.12)',
				'radius' => '8px',
			),
			'breakpoints'  => array(
				'tablet' => 1024,
				'mobile' => 767,
			),
			'typography'   => self::default_typography(),
			'custom'       => array(),
		);
	}

	public static function default_typography() {
		return array(
			'primary'   => self::typo_item( __( 'Primary Headline', 'canvasly-lite' ), true, array( 'font_size' => '32px', 'font_weight' => '600', 'line_height' => '1.2' ) ),
			'secondary' => self::typo_item( __( 'Secondary Headline', 'canvasly-lite' ), true, array( 'font_size' => '24px', 'font_weight' => '600', 'line_height' => '1.3' ) ),
			'text'      => self::typo_item( __( 'Body Text', 'canvasly-lite' ), true, array( 'font_size' => '16px', 'font_weight' => '400', 'line_height' => '1.6' ) ),
			'accent'    => self::typo_item( __( 'Accent Text', 'canvasly-lite' ), true, array( 'font_size' => '16px', 'font_weight' => '500', 'line_height' => '1.5' ) ),
		);
	}

	private static function typo_item( $title, $system, $extra = array() ) {
		$base = array(
			'title'           => $title,
			'system'          => $system,
			'font_family'     => '',
			'font_size'       => '',
			'font_weight'     => '',
			'font_style'      => '',
			'text_transform'  => '',
			'text_decoration' => '',
			'line_height'     => '',
			'letter_spacing'  => '',
		);
		return array_merge( $base, $extra );
	}

	public static function all() {
		$d      = self::defaults();
		$saved  = get_option( self::KEY, array() );
		if ( is_array( $saved ) ) {
			$d = self::merge( $d, $saved );
		}
		$d['colors']       = self::normalize_colors_map( $d['colors'] ?? array() );
		$d['color_titles'] = self::normalize_titles( $d['color_titles'] ?? array(), $d['colors'] );
		$d['typography']   = self::normalize_typography_map( $d['typography'] ?? array(), $d['fonts'] ?? array() );
		return $d;
	}

	private static function merge( $base, $extra ) {
		foreach ( $extra as $k => $v ) {
			if ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) ) {
				$base[ $k ] = self::merge( $base[ $k ], $v );
			} else {
				$base[ $k ] = $v;
			}
		}
		return $base;
	}

	/**
	 * Persist tokens. When `$data` includes `typography` (Site Settings) the
	 * colors map is treated as complete; otherwise incoming colors are merged
	 * so the wp-admin form cannot drop custom presets.
	 */
	public static function save( $data ) {
		if ( ! current_user_can( 'canvasly_lite_design' ) ) {
			return false;
		}
		$old  = self::all();
		$data = is_array( $data ) ? $data : array();
		$d    = self::defaults();

		$replace_colors = array_key_exists( 'typography', $data ) || ! empty( $data['replace_colors'] );
		$d['colors']       = self::sanitize_colors_map( $data['colors'] ?? null, $old['colors'], $replace_colors );
		$d['color_titles'] = self::sanitize_titles( $data['color_titles'] ?? ( $old['color_titles'] ?? array() ), $d['colors'] );

		foreach ( $d['sizes'] as $k => $v ) {
			$d['sizes'][ $k ] = sanitize_text_field( (string) ( $data['sizes'][ $k ] ?? $old['sizes'][ $k ] ?? $v ) );
		}
		foreach ( $d['effects'] as $k => $v ) {
			$d['effects'][ $k ] = sanitize_text_field( (string) ( $data['effects'][ $k ] ?? $old['effects'][ $k ] ?? $v ) );
		}

		$d['typography'] = self::sanitize_typography_map( $data['typography'] ?? null, $old['typography'] );
		$d['fonts']['heading'] = sanitize_text_field( (string) ( $data['fonts']['heading'] ?? ( $d['typography']['primary']['font_family'] ?? $old['fonts']['heading'] ?? '' ) ) );
		$d['fonts']['body']    = sanitize_text_field( (string) ( $data['fonts']['body'] ?? ( $d['typography']['text']['font_family'] ?? $old['fonts']['body'] ?? '' ) ) );
		if ( $d['fonts']['heading'] !== '' ) {
			$d['typography']['primary']['font_family'] = $d['fonts']['heading'];
		}
		if ( $d['fonts']['body'] !== '' ) {
			$d['typography']['text']['font_family'] = $d['fonts']['body'];
		}

		$bp_in = is_array( $data['breakpoints'] ?? null ) ? $data['breakpoints'] : ( is_array( $old['breakpoints'] ?? null ) ? $old['breakpoints'] : $d['breakpoints'] );
		$d['breakpoints'] = array();
		foreach ( (array) $bp_in as $k => $v ) {
			$k = sanitize_key( (string) $k );
			if ( $k === '' || $k === 'desktop' ) {
				continue;
			}
			$val = is_array( $v ) ? absint( $v['value'] ?? 0 ) : absint( $v );
			if ( $val ) {
				$d['breakpoints'][ $k ] = max( 240, $val );
			}
		}
		if ( ! $d['breakpoints'] ) {
			$d['breakpoints'] = array( 'tablet' => 1024, 'mobile' => 767 );
		}

		$custom = array();
		foreach ( (array) ( $old['custom'] ?? array() ) as $group => $items ) {
			foreach ( (array) $items as $name => $item ) {
				$custom[ sanitize_key( $group ) ][ sanitize_key( $name ) ] = self::clean_token( $item );
			}
		}
		foreach ( (array) ( $data['custom'] ?? array() ) as $group => $items ) {
			$g = sanitize_key( $group );
			if ( ! $g ) {
				continue;
			}
			foreach ( (array) $items as $name => $item ) {
				$n = sanitize_key( $name );
				if ( $n ) {
					$custom[ $g ][ $n ] = self::clean_token( $item );
				}
			}
		}
		$d['custom'] = $custom;
		update_option( self::KEY, $d, false );
		if ( class_exists( '\\CanvaslyLite\\Settings\\GlobalSettings' ) ) {
			\CanvaslyLite\Settings\GlobalSettings::invalidate_css_cache();
		}
		return $d;
	}

	private static function sanitize_colors_map( $incoming, $old, $replace ) {
		$system = self::system_color_ids();
		$out    = $replace ? array() : self::normalize_colors_map( $old );
		if ( is_array( $incoming ) ) {
			$incoming = self::normalize_colors_map( $incoming );
			foreach ( $incoming as $id => $hex ) {
				$out[ $id ] = $hex;
			}
			if ( $replace ) {
				$keep = $incoming;
				foreach ( $system as $id ) {
					if ( ! isset( $keep[ $id ] ) ) {
						$keep[ $id ] = $old[ $id ] ?? self::defaults()['colors'][ $id ];
					}
				}
				$out = $keep;
			}
		}
		foreach ( $system as $id ) {
			if ( empty( $out[ $id ] ) ) {
				$out[ $id ] = $old[ $id ] ?? self::defaults()['colors'][ $id ];
			}
		}
		return $out;
	}

	private static function normalize_colors_map( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		$is_list = array_keys( $raw ) === range( 0, count( $raw ) - 1 );
		foreach ( $raw as $k => $v ) {
			if ( $is_list && is_array( $v ) ) {
				$id  = sanitize_key( (string) ( $v['id'] ?? '' ) );
				$hex = self::sanitize_hex( $v['value'] ?? ( $v['color'] ?? '' ) );
			} else {
				$id = sanitize_key( (string) $k );
				$hex = is_array( $v ) ? self::sanitize_hex( $v['value'] ?? ( $v['color'] ?? '' ) ) : self::sanitize_hex( $v );
			}
			if ( $id === '' ) {
				continue;
			}
			$out[ $id ] = $hex ?: '#000000';
		}
		return $out;
	}

	private static function sanitize_hex( $v ) {
		$hex = sanitize_hex_color( (string) $v );
		return $hex ? $hex : '';
	}

	private static function normalize_titles( $titles, $colors ) {
		$out     = array();
		$titles  = is_array( $titles ) ? $titles : array();
		$defaults = self::defaults()['color_titles'];
		foreach ( $colors as $id => $hex ) {
			$label = sanitize_text_field( (string) ( $titles[ $id ] ?? ( $defaults[ $id ] ?? '' ) ) );
			if ( $label === '' ) {
				$label = ucwords( str_replace( array( '-', '_' ), ' ', $id ) );
			}
			$out[ $id ] = $label;
		}
		return $out;
	}

	private static function sanitize_titles( $titles, $colors ) {
		$out = array();
		$titles = is_array( $titles ) ? $titles : array();
		foreach ( $colors as $id => $hex ) {
			$label = sanitize_text_field( (string) ( $titles[ $id ] ?? '' ) );
			$out[ $id ] = $label !== '' ? $label : ( self::defaults()['color_titles'][ $id ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $id ) ) );
		}
		return $out;
	}

	private static function normalize_typography_map( $raw, $fonts = array() ) {
		$defaults = self::default_typography();
		$out      = $defaults;
		if ( is_array( $raw ) ) {
			foreach ( $raw as $id => $item ) {
				$id = sanitize_key( (string) $id );
				if ( $id === '' ) {
					continue;
				}
				$out[ $id ] = self::normalize_typo_item( $item, $defaults[ $id ] ?? null, in_array( $id, self::system_typography_ids(), true ) );
			}
		}
		if ( ! empty( $fonts['heading'] ) && empty( $out['primary']['font_family'] ) ) {
			$out['primary']['font_family'] = sanitize_text_field( (string) $fonts['heading'] );
		}
		if ( ! empty( $fonts['body'] ) && empty( $out['text']['font_family'] ) ) {
			$out['text']['font_family'] = sanitize_text_field( (string) $fonts['body'] );
		}
		return $out;
	}

	private static function sanitize_typography_map( $incoming, $old ) {
		$old = self::normalize_typography_map( $old );
		if ( ! is_array( $incoming ) ) {
			return $old;
		}
		$out = array();
		foreach ( $incoming as $id => $item ) {
			$id = sanitize_key( (string) $id );
			if ( $id === '' ) {
				continue;
			}
			$out[ $id ] = self::normalize_typo_item( $item, $old[ $id ] ?? null, in_array( $id, self::system_typography_ids(), true ) );
		}
		foreach ( self::system_typography_ids() as $id ) {
			if ( ! isset( $out[ $id ] ) ) {
				$out[ $id ] = $old[ $id ] ?? self::default_typography()[ $id ];
			}
			$out[ $id ]['system'] = true;
		}
		return $out;
	}

	private static function normalize_typo_item( $item, $fallback = null, $system = false ) {
		$base = $fallback && is_array( $fallback ) ? $fallback : self::typo_item( '', $system );
		$item = is_array( $item ) ? $item : array();
		$out  = array(
			'title'           => sanitize_text_field( (string) ( $item['title'] ?? ( $base['title'] ?? '' ) ) ),
			'system'          => $system || ! empty( $item['system'] ) || ! empty( $base['system'] ),
			'font_family'     => sanitize_text_field( (string) ( $item['font_family'] ?? ( $base['font_family'] ?? '' ) ) ),
			'font_size'       => sanitize_text_field( (string) ( $item['font_size'] ?? ( $base['font_size'] ?? '' ) ) ),
			'font_weight'     => sanitize_text_field( (string) ( $item['font_weight'] ?? ( $base['font_weight'] ?? '' ) ) ),
			'font_style'      => sanitize_text_field( (string) ( $item['font_style'] ?? ( $base['font_style'] ?? '' ) ) ),
			'text_transform'  => sanitize_text_field( (string) ( $item['text_transform'] ?? ( $base['text_transform'] ?? '' ) ) ),
			'text_decoration' => sanitize_text_field( (string) ( $item['text_decoration'] ?? ( $base['text_decoration'] ?? '' ) ) ),
			'line_height'     => sanitize_text_field( (string) ( $item['line_height'] ?? ( $base['line_height'] ?? '' ) ) ),
			'letter_spacing'  => sanitize_text_field( (string) ( $item['letter_spacing'] ?? ( $base['letter_spacing'] ?? '' ) ) ),
		);
		if ( $out['title'] === '' ) {
			$out['title'] = $base['title'] ?? '';
		}
		return $out;
	}

	private static function clean_token( $item ) {
		if ( is_array( $item ) ) {
			return array(
				'value' => sanitize_text_field( (string) ( $item['value'] ?? '' ) ),
				'type'  => sanitize_key( $item['type'] ?? 'text' ),
				'label' => sanitize_text_field( $item['label'] ?? '' ),
			);
		}
		return array(
			'value' => sanitize_text_field( (string) $item ),
			'type'  => 'text',
			'label' => '',
		);
	}

	public static function css() {
		$d = self::all();
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) && \CanvaslyLite\Settings\AdminSettings::disable_default_colors() ) {
			$d['colors'] = array();
		}
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) && \CanvaslyLite\Settings\AdminSettings::disable_default_fonts() ) {
			$d['fonts'] = array();
			foreach ( (array) ( $d['typography'] ?? array() ) as $id => $item ) {
				if ( is_array( $item ) ) {
					$item['font_family'] = '';
					$d['typography'][ $id ] = $item;
				}
			}
		}
		$o = ':root{';
		foreach ( $d['colors'] as $k => $v ) {
			$o .= '--lb-color-' . sanitize_key( $k ) . ':' . esc_attr( $v ) . ';';
		}
		foreach ( $d['sizes'] as $k => $v ) {
			$o .= '--lb-size-' . sanitize_key( $k ) . ':' . esc_attr( $v ) . ';';
		}
		foreach ( $d['fonts'] as $k => $v ) {
			if ( $v ) {
				$o .= '--lb-font-' . sanitize_key( $k ) . ':' . self::css_font_family( $v ) . ';';
			}
		}
		foreach ( $d['effects'] as $k => $v ) {
			$o .= '--lb-effect-' . sanitize_key( $k ) . ':' . esc_attr( $v ) . ';';
		}
		foreach ( (array) ( $d['breakpoints'] ?? array() ) as $k => $v ) {
			$val = is_array( $v ) ? absint( $v['value'] ?? 0 ) : absint( $v );
			if ( $val ) {
				$o .= '--lb-bp-' . sanitize_key( $k ) . ':' . $val . 'px;';
			}
		}
		foreach ( (array) ( $d['typography'] ?? array() ) as $id => $item ) {
			$id = sanitize_key( $id );
			if ( $id === '' || ! is_array( $item ) ) {
				continue;
			}
			foreach ( self::TYPO_PROPS as $prop ) {
				$val = trim( (string) ( $item[ $prop ] ?? '' ) );
				if ( $val === '' ) {
					continue;
				}
				if ( $prop === 'font_family' ) {
					$val = self::css_font_family( $val );
				} else {
					$val = esc_attr( $val );
				}
				$o .= '--lb-typo-' . $id . '-' . str_replace( '_', '-', $prop ) . ':' . $val . ';';
			}
		}
		foreach ( (array) ( $d['custom'] ?? array() ) as $group => $items ) {
			foreach ( (array) $items as $name => $item ) {
				$value = is_array( $item ) ? ( $item['value'] ?? '' ) : $item;
				if ( $value !== '' ) {
					$o .= '--lb-' . sanitize_key( $group ) . '-' . sanitize_key( $name ) . ':' . esc_attr( $value ) . ';';
				}
			}
		}
		$o .= '}';
		return $o;
	}

	private static function css_font_family( $v ) {
		$v = trim( (string) $v );
		if ( $v === '' ) {
			return '';
		}
		if ( $v[0] === '"' || $v[0] === "'" || strpos( $v, ',' ) !== false ) {
			return esc_attr( $v );
		}
		if ( preg_match( '/\s/', $v ) ) {
			return esc_attr( '"' . $v . '"' );
		}
		return esc_attr( $v );
	}

	public static function css_prefix( $group ) {
		$group = sanitize_key( (string) $group );
		return self::GROUP_PREFIX[ $group ] ?? $group;
	}

	public static function token_reference( $group, $name ) {
		return 'var(--lb-' . self::css_prefix( $group ) . '-' . sanitize_key( $name ) . ')';
	}

	public static function resolve_references( $value ) {
		if ( ! is_string( $value ) || strpos( $value, '{{var:' ) === false ) {
			return $value;
		}
		return preg_replace_callback(
			'/\{\{var:([a-zA-Z0-9_-]+)\.([a-zA-Z0-9_-]+)\}\}/',
			function ( $m ) {
				return self::token_reference( $m[1], $m[2] );
			},
			$value
		);
	}

	/**
	 * Accept a hex color or a `{{var:colors.id}}` / `var(--lb-color-id)` bind.
	 */
	public static function sanitize_color_value( $v ) {
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
		$hex = sanitize_hex_color( $s );
		return $hex ? $hex : '';
	}

	public static function typography_preset( $id ) {
		$id   = sanitize_key( (string) $id );
		$all  = self::all();
		$item = $all['typography'][ $id ] ?? null;
		return is_array( $item ) ? $item : null;
	}

	public static function color_value( $id ) {
		$id  = sanitize_key( (string) $id );
		$all = self::all();
		return isset( $all['colors'][ $id ] ) ? (string) $all['colors'][ $id ] : '';
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
	 * Families with weight/style used by global typography presets.
	 *
	 * @return array<int,array{font_family:string,font_weight:string,font_style:string}>
	 */
	public static function used_fonts() {
		$out = array();
		$d   = self::all();
		foreach ( array( $d['fonts']['heading'] ?? '', $d['fonts']['body'] ?? '' ) as $f ) {
			$f = trim( (string) $f );
			if ( $f !== '' && ! preg_match( '/[,"\']/', $f ) ) {
				$out[] = array(
					'font_family' => $f,
					'font_weight' => '',
					'font_style'  => '',
				);
			}
		}
		foreach ( (array) ( $d['typography'] ?? array() ) as $item ) {
			$f = trim( (string) ( $item['font_family'] ?? '' ) );
			if ( $f !== '' && ! preg_match( '/[,"\']/', $f ) ) {
				$out[] = array(
					'font_family' => $f,
					'font_weight' => (string) ( $item['font_weight'] ?? '' ),
					'font_style'  => (string) ( $item['font_style'] ?? '' ),
				);
			}
		}
		return $out;
	}

	public static function exists( $group, $name ) {
		$d = self::all();
		return isset( $d[ $group ] ) && is_array( $d[ $group ] ) && array_key_exists( $name, $d[ $group ] );
	}

	public static function delete_custom( $group, $name ) {
		if ( ! current_user_can( 'canvasly_lite_design' ) ) {
			return false;
		}
		$d = self::all();
		$g = sanitize_key( $group );
		$n = sanitize_key( $name );
		if ( ! $g || ! $n || empty( $d['custom'][ $g ] ) || ! array_key_exists( $n, $d['custom'][ $g ] ) ) {
			return false;
		}
		unset( $d['custom'][ $g ][ $n ] );
		if ( ! $d['custom'][ $g ] ) {
			unset( $d['custom'][ $g ] );
		}
		update_option( self::KEY, $d, false );
		if ( class_exists( '\\CanvaslyLite\\Settings\\GlobalSettings' ) ) {
			\CanvaslyLite\Settings\GlobalSettings::invalidate_css_cache();
		}
		return true;
	}
}
