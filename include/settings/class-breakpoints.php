<?php
namespace CanvaslyLite\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seven named breakpoints: mobile, mobile_extra, tablet, tablet_extra, laptop,
 * desktop (base, no media query) and widescreen (min-width).
 *
 * Extra devices are off by default so existing tablet/mobile documents keep
 * the same CSS. Responsive values are keyed by these names.
 */
class Breakpoints {
	const NAMES = array( 'mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'desktop', 'widescreen' );

	/**
	 * Default catalog. `enabled` flags, `value` is the CSS threshold in px,
	 * `preview` is the editor canvas width (0 = 100%), `direction` is
	 * max | min | base.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function catalog() {
		return array(
			'mobile'       => array(
				'name'      => 'mobile',
				'label'     => __( 'Mobile', 'canvasly-lite' ),
				'short'     => 'M',
				'enabled'   => true,
				'value'     => 767,
				'direction' => 'max',
				'preview'   => 390,
			),
			'mobile_extra' => array(
				'name'      => 'mobile_extra',
				'label'     => __( 'Mobile Extra', 'canvasly-lite' ),
				'short'     => 'M+',
				'enabled'   => false,
				'value'     => 880,
				'direction' => 'max',
				'preview'   => 568,
			),
			'tablet'       => array(
				'name'      => 'tablet',
				'label'     => __( 'Tablet', 'canvasly-lite' ),
				'short'     => 'T',
				'enabled'   => true,
				'value'     => 1024,
				'direction' => 'max',
				'preview'   => 1024,
			),
			'tablet_extra' => array(
				'name'      => 'tablet_extra',
				'label'     => __( 'Tablet Extra', 'canvasly-lite' ),
				'short'     => 'T+',
				'enabled'   => false,
				'value'     => 1200,
				'direction' => 'max',
				'preview'   => 1200,
			),
			'laptop'       => array(
				'name'      => 'laptop',
				'label'     => __( 'Laptop', 'canvasly-lite' ),
				'short'     => 'L',
				'enabled'   => false,
				'value'     => 1366,
				'direction' => 'max',
				'preview'   => 1366,
			),
			'desktop'      => array(
				'name'      => 'desktop',
				'label'     => __( 'Desktop', 'canvasly-lite' ),
				'short'     => 'D',
				'enabled'   => true,
				'value'     => 0,
				'direction' => 'base',
				'preview'   => 0,
			),
			'widescreen'   => array(
				'name'      => 'widescreen',
				'label'     => __( 'Widescreen', 'canvasly-lite' ),
				'short'     => 'W',
				'enabled'   => false,
				'value'     => 2400,
				'direction' => 'min',
				'preview'   => 2400,
			),
		);
	}

	/**
	 * @return string[]
	 */
	public static function names() {
		return self::NAMES;
	}

	/**
	 * True when `$v` is a responsive map keyed by breakpoint name.
	 * Dimensions `{top,right,bottom,left}` are not maps.
	 *
	 * @param mixed $v
	 * @return bool
	 */
	public static function is_map( $v ) {
		if ( ! is_array( $v ) ) {
			return false;
		}
		foreach ( self::NAMES as $name ) {
			if ( array_key_exists( $name, $v ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keep only named breakpoint keys. Legacy desktop/tablet/mobile maps pass through.
	 *
	 * @param mixed $v
	 * @return mixed
	 */
	public static function migrate_map( $v ) {
		if ( ! is_array( $v ) || ! self::is_map( $v ) ) {
			return $v;
		}
		$out = array();
		foreach ( self::NAMES as $name ) {
			if ( array_key_exists( $name, $v ) ) {
				$out[ $name ] = $v[ $name ];
			}
		}
		return $out;
	}

	/**
	 * Merge stored settings (legacy `{tablet:1024,mobile:767}` or the full catalog)
	 * onto the default catalog.
	 *
	 * @param mixed $raw
	 * @return array<string,array<string,mixed>>
	 */
	public static function normalize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$out = self::catalog();
		foreach ( $out as $name => $def ) {
			$item = $raw[ $name ] ?? null;
			if ( is_numeric( $item ) ) {
				$out[ $name ]['value'] = self::clamp_value( $name, (int) $item );
				continue;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( $name !== 'desktop' && array_key_exists( 'enabled', $item ) ) {
				$out[ $name ]['enabled'] = ! empty( $item['enabled'] );
			}
			if ( array_key_exists( 'value', $item ) ) {
				$out[ $name ]['value'] = self::clamp_value( $name, (int) $item['value'] );
			}
			if ( array_key_exists( 'preview', $item ) ) {
				$out[ $name ]['preview'] = max( 0, (int) $item['preview'] );
			}
		}
		$out['desktop']['enabled']     = true;
		$out['desktop']['direction']   = 'base';
		$out['desktop']['value']       = 0;
		$out['widescreen']['direction'] = 'min';
		foreach ( $out as $name => $def ) {
			if ( ( $def['direction'] ?? '' ) === 'max' ) {
				$out[ $name ]['direction'] = 'max';
			}
		}
		$filtered = apply_filters( 'canvasly-lite/breakpoints', $out );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * @param mixed $raw
	 * @return array<string,array<string,mixed>>
	 */
	public static function sanitize( $raw ) {
		return self::normalize( $raw );
	}

	/**
	 * Normalized catalog from Global Settings (or `$raw` when provided).
	 *
	 * @param mixed $raw
	 * @return array<string,array<string,mixed>>
	 */
	public static function all( $raw = null ) {
		if ( $raw !== null ) {
			return self::normalize( $raw );
		}
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		return self::normalize( $g['breakpoints'] ?? array() );
	}

	/**
	 * Enabled breakpoints, catalog order.
	 *
	 * @param mixed $raw
	 * @return array<string,array<string,mixed>>
	 */
	public static function enabled( $raw = null ) {
		$out = array();
		foreach ( self::all( $raw ) as $name => $b ) {
			if ( ! empty( $b['enabled'] ) ) {
				$out[ $name ] = $b;
			}
		}
		return $out;
	}

	/**
	 * Exclusive viewport range per enabled breakpoint, for hide/show.
	 * Each entry is `['min'=>int, 'max'=>int]` where 0 means unbounded.
	 *
	 * @param mixed $raw
	 * @return array<string,array{min:int,max:int}>
	 */
	public static function ranges( $raw = null ) {
		$list = self::all( $raw );
		$max  = array();
		foreach ( $list as $name => $b ) {
			if ( empty( $b['enabled'] ) || ( $b['direction'] ?? '' ) !== 'max' ) {
				continue;
			}
			$max[ $name ] = (int) $b['value'];
		}
		asort( $max, SORT_NUMERIC );
		$ranges = array();
		$prev   = 0;
		foreach ( $max as $name => $value ) {
			$ranges[ $name ] = array(
				'min' => $prev ? $prev + 1 : 0,
				'max' => $value,
			);
			$prev = $value;
		}
		$wide = 0;
		if ( ! empty( $list['widescreen']['enabled'] ) ) {
			$wide                   = (int) $list['widescreen']['value'];
			$ranges['widescreen'] = array(
				'min' => $wide,
				'max' => 0,
			);
		}
		$ranges['desktop'] = array(
			'min' => $prev ? $prev + 1 : 0,
			'max' => $wide ? $wide - 1 : 0,
		);
		return $ranges;
	}

	/**
	 * Exclusive `@media` condition (no `@media` prefix) for hide/show.
	 *
	 * @param string $name
	 * @param mixed  $raw
	 * @return string Empty for a query that should always apply.
	 */
	public static function range_query( $name, $raw = null ) {
		$ranges = self::ranges( $raw );
		if ( ! isset( $ranges[ $name ] ) ) {
			return '';
		}
		$r     = $ranges[ $name ];
		$parts = array();
		if ( ! empty( $r['min'] ) ) {
			$parts[] = '(min-width:' . (int) $r['min'] . 'px)';
		}
		if ( ! empty( $r['max'] ) ) {
			$parts[] = '(max-width:' . (int) $r['max'] . 'px)';
		}
		return implode( ' and ', $parts );
	}

	/**
	 * Cascade order for value overrides: largest max-width first, then widescreen.
	 * Desktop is omitted (it is the base layer).
	 *
	 * @param mixed $raw
	 * @return string[]
	 */
	public static function cascade_order( $raw = null ) {
		$list = self::all( $raw );
		$max  = array();
		foreach ( $list as $name => $b ) {
			if ( empty( $b['enabled'] ) || ( $b['direction'] ?? '' ) !== 'max' ) {
				continue;
			}
			$max[ $name ] = (int) $b['value'];
		}
		arsort( $max, SORT_NUMERIC );
		$order = array_keys( $max );
		if ( ! empty( $list['widescreen']['enabled'] ) ) {
			$order[] = 'widescreen';
		}
		return $order;
	}

	/**
	 * Overlapping `@media` condition for cascading responsive values.
	 * Widescreen uses min-width; other devices use max-width; desktop is empty.
	 *
	 * @param string $name
	 * @param mixed  $raw
	 * @return string
	 */
	public static function cascade_query( $name, $raw = null ) {
		$list = self::all( $raw );
		if ( ! isset( $list[ $name ] ) || empty( $list[ $name ]['enabled'] ) ) {
			return '';
		}
		$dir = $list[ $name ]['direction'] ?? 'max';
		$val = (int) $list[ $name ]['value'];
		if ( $dir === 'base' || $name === 'desktop' ) {
			return '';
		}
		if ( $dir === 'min' ) {
			return '(min-width:' . $val . 'px)';
		}
		return '(max-width:' . $val . 'px)';
	}

	/**
	 * @param string $name
	 * @param string $css
	 * @param mixed  $raw
	 * @return string
	 */
	public static function wrap_range( $name, $css, $raw = null ) {
		if ( $css === '' ) {
			return '';
		}
		$q = self::range_query( $name, $raw );
		return $q ? '@media' . $q . '{' . $css . '}' : $css;
	}

	/**
	 * @param string $name
	 * @param string $css
	 * @param mixed  $raw
	 * @return string
	 */
	public static function wrap_cascade( $name, $css, $raw = null ) {
		if ( $css === '' ) {
			return '';
		}
		$q = self::cascade_query( $name, $raw );
		return $q ? '@media' . $q . '{' . $css . '}' : $css;
	}

	/**
	 * CSS custom properties plus the mobile video utility query.
	 *
	 * @param mixed $raw
	 * @return string
	 */
	public static function css( $raw = null ) {
		$list = self::all( $raw );
		$o    = ':root{';
		foreach ( $list as $name => $b ) {
			if ( ( $b['direction'] ?? '' ) === 'base' ) {
				continue;
			}
			$o .= '--lb-bp-' . sanitize_key( $name ) . ':' . absint( $b['value'] ) . 'px;';
		}
		$o     .= '}';
		$mobile = (int) ( $list['mobile']['value'] ?? 767 );
		if ( $mobile > 0 ) {
			$o .= '@media(max-width:' . $mobile . 'px){.lb-hide-mobile-video{display:none}}';
		}
		return $o;
	}

	/**
	 * Numeric thresholds keyed by name (no desktop). Used by Variables.
	 *
	 * @param mixed $raw
	 * @return array<string,int>
	 */
	public static function values( $raw = null ) {
		$out = array();
		foreach ( self::all( $raw ) as $name => $b ) {
			if ( $name === 'desktop' ) {
				continue;
			}
			$out[ $name ] = (int) $b['value'];
		}
		return $out;
	}

	/**
	 * @param string $name
	 * @param int    $value
	 * @return int
	 */
	private static function clamp_value( $name, $value ) {
		$value = (int) $value;
		if ( $name === 'desktop' ) {
			return 0;
		}
		return max( 240, min( 4000, $value ) );
	}
}
