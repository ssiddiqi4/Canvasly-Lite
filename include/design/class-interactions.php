<?php
namespace CanvaslyLite\Design;

use CanvaslyLite\Settings\Breakpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interactions 2.0 — CSS-only entrance/exit presets, custom keyframes,
 * triggers, timing, breakpoint exclusions and reduced-motion handling.
 */
class Interactions {
	const KINDS    = array( 'entrance', 'exit', 'custom' );
	const TRIGGERS = array( 'viewport', 'load', 'hover', 'click', 'scroll', 'focus' );
	const EASINGS  = array( 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear' );

	/**
	 * Catalog of ~50 CSS presets. Each entry is id => [label, group, from|special].
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function presets() {
		$p = array(
			'fade'            => array( 'label' => __( 'Fade', 'canvasly-lite' ), 'group' => 'fade', 'from' => array( 'opacity' => 0 ) ),
			'fade-up'         => array( 'label' => __( 'Fade Up', 'canvasly-lite' ), 'group' => 'fade', 'from' => array( 'opacity' => 0, 'y' => 28 ) ),
			'fade-down'       => array( 'label' => __( 'Fade Down', 'canvasly-lite' ), 'group' => 'fade', 'from' => array( 'opacity' => 0, 'y' => -28 ) ),
			'fade-left'       => array( 'label' => __( 'Fade Left', 'canvasly-lite' ), 'group' => 'fade', 'from' => array( 'opacity' => 0, 'x' => 28 ) ),
			'fade-right'      => array( 'label' => __( 'Fade Right', 'canvasly-lite' ), 'group' => 'fade', 'from' => array( 'opacity' => 0, 'x' => -28 ) ),
			'fade-up-far'     => array( 'label' => __( 'Fade Up Far', 'canvasly-lite' ), 'group' => 'fade', 'from' => array( 'opacity' => 0, 'y' => 80 ) ),
			'fade-down-far'   => array( 'label' => __( 'Fade Down Far', 'canvasly-lite' ), 'group' => 'fade', 'from' => array( 'opacity' => 0, 'y' => -80 ) ),
			'fade-left-far'   => array( 'label' => __( 'Fade Left Far', 'canvasly-lite' ), 'group' => 'fade', 'from' => array( 'opacity' => 0, 'x' => 80 ) ),
			'fade-right-far'  => array( 'label' => __( 'Fade Right Far', 'canvasly-lite' ), 'group' => 'fade', 'from' => array( 'opacity' => 0, 'x' => -80 ) ),
			'slide-up'        => array( 'label' => __( 'Slide Up', 'canvasly-lite' ), 'group' => 'slide', 'from' => array( 'opacity' => 0, 'y' => 48 ) ),
			'slide-down'      => array( 'label' => __( 'Slide Down', 'canvasly-lite' ), 'group' => 'slide', 'from' => array( 'opacity' => 0, 'y' => -48 ) ),
			'slide-left'      => array( 'label' => __( 'Slide Left', 'canvasly-lite' ), 'group' => 'slide', 'from' => array( 'opacity' => 0, 'x' => 48 ) ),
			'slide-right'     => array( 'label' => __( 'Slide Right', 'canvasly-lite' ), 'group' => 'slide', 'from' => array( 'opacity' => 0, 'x' => -48 ) ),
			'slide-up-far'    => array( 'label' => __( 'Slide Up Far', 'canvasly-lite' ), 'group' => 'slide', 'from' => array( 'opacity' => 0, 'y' => 120 ) ),
			'slide-down-far'  => array( 'label' => __( 'Slide Down Far', 'canvasly-lite' ), 'group' => 'slide', 'from' => array( 'opacity' => 0, 'y' => -120 ) ),
			'slide-left-far'  => array( 'label' => __( 'Slide Left Far', 'canvasly-lite' ), 'group' => 'slide', 'from' => array( 'opacity' => 0, 'x' => 120 ) ),
			'slide-right-far' => array( 'label' => __( 'Slide Right Far', 'canvasly-lite' ), 'group' => 'slide', 'from' => array( 'opacity' => 0, 'x' => -120 ) ),
			'zoom-in'         => array( 'label' => __( 'Zoom In', 'canvasly-lite' ), 'group' => 'zoom', 'from' => array( 'opacity' => 0, 'scale' => 0.6 ) ),
			'zoom-out'        => array( 'label' => __( 'Zoom Out', 'canvasly-lite' ), 'group' => 'zoom', 'from' => array( 'opacity' => 0, 'scale' => 1.35 ) ),
			'zoom-in-up'      => array( 'label' => __( 'Zoom In Up', 'canvasly-lite' ), 'group' => 'zoom', 'from' => array( 'opacity' => 0, 'scale' => 0.7, 'y' => 40 ) ),
			'zoom-in-down'    => array( 'label' => __( 'Zoom In Down', 'canvasly-lite' ), 'group' => 'zoom', 'from' => array( 'opacity' => 0, 'scale' => 0.7, 'y' => -40 ) ),
			'zoom-in-left'    => array( 'label' => __( 'Zoom In Left', 'canvasly-lite' ), 'group' => 'zoom', 'from' => array( 'opacity' => 0, 'scale' => 0.7, 'x' => 40 ) ),
			'zoom-in-right'   => array( 'label' => __( 'Zoom In Right', 'canvasly-lite' ), 'group' => 'zoom', 'from' => array( 'opacity' => 0, 'scale' => 0.7, 'x' => -40 ) ),
			'scale'           => array( 'label' => __( 'Scale', 'canvasly-lite' ), 'group' => 'zoom', 'from' => array( 'opacity' => 0, 'scale' => 0.94 ) ),
			'grow'            => array( 'label' => __( 'Grow', 'canvasly-lite' ), 'group' => 'zoom', 'from' => array( 'opacity' => 0, 'scale' => 0.5 ) ),
			'shrink'          => array( 'label' => __( 'Shrink In', 'canvasly-lite' ), 'group' => 'zoom', 'from' => array( 'opacity' => 0, 'scale' => 1.5 ) ),
			'pop'             => array( 'label' => __( 'Pop', 'canvasly-lite' ), 'group' => 'zoom', 'special' => 'pop' ),
			'bounce'          => array( 'label' => __( 'Bounce', 'canvasly-lite' ), 'group' => 'bounce', 'special' => 'bounce' ),
			'bounce-up'       => array( 'label' => __( 'Bounce Up', 'canvasly-lite' ), 'group' => 'bounce', 'special' => 'bounce-up' ),
			'bounce-down'     => array( 'label' => __( 'Bounce Down', 'canvasly-lite' ), 'group' => 'bounce', 'special' => 'bounce-down' ),
			'bounce-left'     => array( 'label' => __( 'Bounce Left', 'canvasly-lite' ), 'group' => 'bounce', 'special' => 'bounce-left' ),
			'bounce-right'    => array( 'label' => __( 'Bounce Right', 'canvasly-lite' ), 'group' => 'bounce', 'special' => 'bounce-right' ),
			'spin'            => array( 'label' => __( 'Spin In', 'canvasly-lite' ), 'group' => 'spin', 'from' => array( 'opacity' => 0, 'rotate' => -90, 'scale' => 0.9 ) ),
			'rotate'          => array( 'label' => __( 'Rotate', 'canvasly-lite' ), 'group' => 'spin', 'from' => array( 'opacity' => 0, 'rotate' => -8, 'scale' => 0.98 ) ),
			'spin-down-left'  => array( 'label' => __( 'Spin Down Left', 'canvasly-lite' ), 'group' => 'spin', 'from' => array( 'opacity' => 0, 'rotate' => -45, 'x' => -24, 'y' => -24 ) ),
			'spin-down-right' => array( 'label' => __( 'Spin Down Right', 'canvasly-lite' ), 'group' => 'spin', 'from' => array( 'opacity' => 0, 'rotate' => 45, 'x' => 24, 'y' => -24 ) ),
			'spin-up-left'    => array( 'label' => __( 'Spin Up Left', 'canvasly-lite' ), 'group' => 'spin', 'from' => array( 'opacity' => 0, 'rotate' => 45, 'x' => -24, 'y' => 24 ) ),
			'spin-up-right'   => array( 'label' => __( 'Spin Up Right', 'canvasly-lite' ), 'group' => 'spin', 'from' => array( 'opacity' => 0, 'rotate' => -45, 'x' => 24, 'y' => 24 ) ),
			'flip-x'          => array( 'label' => __( 'Flip Horizontal', 'canvasly-lite' ), 'group' => 'flip', 'from' => array( 'opacity' => 0, 'rotateY' => 90 ) ),
			'flip-y'          => array( 'label' => __( 'Flip Vertical', 'canvasly-lite' ), 'group' => 'flip', 'from' => array( 'opacity' => 0, 'rotateX' => 90 ) ),
			'blur'            => array( 'label' => __( 'Blur In', 'canvasly-lite' ), 'group' => 'special', 'from' => array( 'opacity' => 0.1, 'blur' => 10 ) ),
			'drop'            => array( 'label' => __( 'Drop', 'canvasly-lite' ), 'group' => 'special', 'from' => array( 'opacity' => 0, 'y' => -60, 'scale' => 0.95 ) ),
			'rise'            => array( 'label' => __( 'Rise', 'canvasly-lite' ), 'group' => 'special', 'from' => array( 'opacity' => 0, 'y' => 16, 'blur' => 4 ) ),
			'tilt'            => array( 'label' => __( 'Tilt In', 'canvasly-lite' ), 'group' => 'special', 'from' => array( 'opacity' => 0, 'skewX' => 12, 'x' => -16 ) ),
			'expand'          => array( 'label' => __( 'Expand', 'canvasly-lite' ), 'group' => 'special', 'from' => array( 'opacity' => 0, 'scaleX' => 0.2 ) ),
			'reveal'          => array( 'label' => __( 'Reveal', 'canvasly-lite' ), 'group' => 'special', 'from' => array( 'opacity' => 0, 'y' => 12, 'scale' => 0.98 ) ),
			'roll'            => array( 'label' => __( 'Roll In', 'canvasly-lite' ), 'group' => 'special', 'from' => array( 'opacity' => 0, 'rotate' => -120, 'x' => -80 ) ),
			'unfold'          => array( 'label' => __( 'Unfold', 'canvasly-lite' ), 'group' => 'special', 'special' => 'unfold' ),
			'swing'           => array( 'label' => __( 'Swing In', 'canvasly-lite' ), 'group' => 'special', 'special' => 'swing' ),
			'flash'           => array( 'label' => __( 'Flash In', 'canvasly-lite' ), 'group' => 'special', 'special' => 'flash' ),
			'pulse-in'        => array( 'label' => __( 'Pulse In', 'canvasly-lite' ), 'group' => 'special', 'special' => 'pulse-in' ),
			'shake'           => array( 'label' => __( 'Shake In', 'canvasly-lite' ), 'group' => 'special', 'special' => 'shake' ),
			'wobble'          => array( 'label' => __( 'Wobble In', 'canvasly-lite' ), 'group' => 'special', 'special' => 'wobble' ),
			'wipe-up'         => array( 'label' => __( 'Wipe Up', 'canvasly-lite' ), 'group' => 'wipe', 'from' => array( 'clip' => 'inset(100% 0 0 0)' ) ),
			'wipe-down'       => array( 'label' => __( 'Wipe Down', 'canvasly-lite' ), 'group' => 'wipe', 'from' => array( 'clip' => 'inset(0 0 100% 0)' ) ),
			'wipe-left'       => array( 'label' => __( 'Wipe Left', 'canvasly-lite' ), 'group' => 'wipe', 'from' => array( 'clip' => 'inset(0 0 0 100%)' ) ),
			'wipe-right'      => array( 'label' => __( 'Wipe Right', 'canvasly-lite' ), 'group' => 'wipe', 'from' => array( 'clip' => 'inset(0 100% 0 0)' ) ),
		);
		$filtered = apply_filters( 'canvasly-lite/interactions/presets', $p );
		return is_array( $filtered ) ? $filtered : $p;
	}

	/**
	 * @return array<string,string>
	 */
	public static function groups() {
		return array(
			'fade'    => __( 'Fading', 'canvasly-lite' ),
			'slide'   => __( 'Sliding', 'canvasly-lite' ),
			'zoom'    => __( 'Zooming', 'canvasly-lite' ),
			'bounce'  => __( 'Bouncing', 'canvasly-lite' ),
			'spin'    => __( 'Spinning', 'canvasly-lite' ),
			'flip'    => __( 'Flipping', 'canvasly-lite' ),
			'special' => __( 'Special', 'canvasly-lite' ),
			'wipe'    => __( 'Wipe', 'canvasly-lite' ),
		);
	}

	/**
	 * Editor/REST catalog.
	 *
	 * @return array<string,mixed>
	 */
	public static function export() {
		$presets = array();
		foreach ( self::presets() as $id => $p ) {
			$presets[] = array(
				'id'    => $id,
				'label' => (string) ( $p['label'] ?? $id ),
				'group' => (string) ( $p['group'] ?? 'special' ),
			);
		}
		return array(
			'presets'  => $presets,
			'groups'   => self::groups(),
			'triggers' => array(
				'viewport' => __( 'In Viewport', 'canvasly-lite' ),
				'load'     => __( 'On Load', 'canvasly-lite' ),
				'hover'    => __( 'Hover', 'canvasly-lite' ),
				'click'    => __( 'Click', 'canvasly-lite' ),
				'scroll'   => __( 'Scroll Progress', 'canvasly-lite' ),
				'focus'    => __( 'Focus', 'canvasly-lite' ),
			),
			'kinds'    => array(
				'entrance' => __( 'Entrance', 'canvasly-lite' ),
				'exit'     => __( 'Exit', 'canvasly-lite' ),
				'custom'   => __( 'Custom Keyframes', 'canvasly-lite' ),
			),
			'easings'  => array(
				'ease'        => __( 'Ease', 'canvasly-lite' ),
				'ease-in'     => __( 'Ease In', 'canvasly-lite' ),
				'ease-out'    => __( 'Ease Out', 'canvasly-lite' ),
				'ease-in-out' => __( 'Ease In Out', 'canvasly-lite' ),
				'linear'      => __( 'Linear', 'canvasly-lite' ),
			),
		);
	}

	public static function preset_ids() {
		return array_keys( self::presets() );
	}

	public static function is_preset( $id ) {
		return isset( self::presets()[ sanitize_key( (string) $id ) ] );
	}

	/**
	 * Global preset CSS (keyframes + play/skip + reduced-motion + breakpoint skips).
	 */
	public static function css() {
		$css  = self::base_css();
		$css .= self::preset_css();
		$css .= self::legacy_css();
		$css .= self::breakpoint_skip_css();
		$css .= self::reduced_motion_css();
		return $css;
	}

	private static function base_css() {
		return '.lb-fx{animation-duration:var(--lb-fx-duration,.6s);animation-delay:var(--lb-fx-delay,0s);animation-timing-function:var(--lb-fx-easing,ease);animation-iteration-count:var(--lb-fx-iteration,1);animation-fill-mode:both;animation-play-state:paused}'
			. '.lb-fx.lb-fx-play,.lb-fx.lb-fx-preview{animation-play-state:running}'
			. '.lb-fx.lb-fx-exit{animation-direction:reverse}'
			. '.lb-fx.lb-fx-scroll{animation-duration:1s;animation-delay:calc(var(--lb-fx-progress,0)*-1s);animation-play-state:paused;animation-timing-function:linear}'
			. '.lb-fx.lb-fx-skip{animation:none!important;opacity:1!important;transform:none!important;filter:none!important;clip-path:none!important}'
			. '.lb-hover-lift{transition:transform .2s ease}.lb-hover-lift:hover{transform:translateY(-4px)}';
	}

	private static function preset_css() {
		$css = '';
		foreach ( self::presets() as $id => $p ) {
			$id = sanitize_key( $id );
			if ( $id === '' ) {
				continue;
			}
			$name = 'lb-fx-' . $id;
			if ( ! empty( $p['special'] ) ) {
				$css .= self::special_keyframes( $name, (string) $p['special'] );
			} else {
				$from = self::from_decl( is_array( $p['from'] ?? null ) ? $p['from'] : array( 'opacity' => 0 ) );
				$css .= '@keyframes ' . $name . '{0%{' . $from . '}100%{opacity:1;transform:none;filter:none;clip-path:none}}';
			}
			$css .= '.' . $name . '{animation-name:' . $name . '}';
		}
		return $css;
	}

	private static function from_decl( array $from ) {
		$op    = array_key_exists( 'opacity', $from ) ? max( 0, min( 1, (float) $from['opacity'] ) ) : 1;
		$x     = (float) ( $from['x'] ?? 0 );
		$y     = (float) ( $from['y'] ?? 0 );
		$scale = array_key_exists( 'scale', $from ) ? (float) $from['scale'] : 1;
		$sx    = array_key_exists( 'scaleX', $from ) ? (float) $from['scaleX'] : $scale;
		$sy    = array_key_exists( 'scaleY', $from ) ? (float) $from['scaleY'] : $scale;
		$r     = (float) ( $from['rotate'] ?? 0 );
		$rx    = (float) ( $from['rotateX'] ?? 0 );
		$ry    = (float) ( $from['rotateY'] ?? 0 );
		$sk    = (float) ( $from['skewX'] ?? 0 );
		$blur  = (float) ( $from['blur'] ?? 0 );
		$clip  = (string) ( $from['clip'] ?? '' );
		$tf    = array();
		if ( $rx || $ry ) {
			$tf[] = 'perspective(800px)';
		}
		if ( $x || $y ) {
			$tf[] = 'translate3d(' . $x . 'px,' . $y . 'px,0)';
		}
		if ( $sx != 1.0 || $sy != 1.0 ) {
			$tf[] = 'scale(' . $sx . ',' . $sy . ')';
		}
		if ( $r ) {
			$tf[] = 'rotate(' . $r . 'deg)';
		}
		if ( $rx ) {
			$tf[] = 'rotateX(' . $rx . 'deg)';
		}
		if ( $ry ) {
			$tf[] = 'rotateY(' . $ry . 'deg)';
		}
		if ( $sk ) {
			$tf[] = 'skewX(' . $sk . 'deg)';
		}
		$d = 'opacity:' . $op;
		if ( $tf ) {
			$d .= ';transform:' . implode( ' ', $tf );
		}
		if ( $blur ) {
			$d .= ';filter:blur(' . $blur . 'px)';
		}
		if ( $clip !== '' && preg_match( '/^inset\([^;{}]+\)\s*$/', $clip ) ) {
			$d .= ';clip-path:' . $clip;
		}
		return $d;
	}

	private static function special_keyframes( $name, $kind ) {
		switch ( $kind ) {
			case 'bounce':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:scale(.3)}50%{opacity:1;transform:scale(1.08)}70%{transform:scale(.95)}100%{opacity:1;transform:none}}';
			case 'bounce-up':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:translateY(60px)}55%{opacity:1;transform:translateY(-12px)}75%{transform:translateY(6px)}100%{opacity:1;transform:none}}';
			case 'bounce-down':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:translateY(-60px)}55%{opacity:1;transform:translateY(12px)}75%{transform:translateY(-6px)}100%{opacity:1;transform:none}}';
			case 'bounce-left':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:translateX(60px)}55%{opacity:1;transform:translateX(-12px)}75%{transform:translateX(6px)}100%{opacity:1;transform:none}}';
			case 'bounce-right':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:translateX(-60px)}55%{opacity:1;transform:translateX(12px)}75%{transform:translateX(-6px)}100%{opacity:1;transform:none}}';
			case 'pop':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:scale(.4)}80%{opacity:1;transform:scale(1.08)}100%{opacity:1;transform:none}}';
			case 'unfold':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:rotateX(-85deg);transform-origin:top}100%{opacity:1;transform:none;transform-origin:top}}';
			case 'swing':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:rotate(-12deg);transform-origin:top center}40%{opacity:1;transform:rotate(8deg)}70%{transform:rotate(-4deg)}100%{opacity:1;transform:none}}';
			case 'flash':
				return '@keyframes ' . $name . '{0%,50%,100%{opacity:1}25%,75%{opacity:0}}';
			case 'pulse-in':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:scale(.85)}50%{opacity:1;transform:scale(1.06)}100%{opacity:1;transform:none}}';
			case 'shake':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:translateX(-16px)}20%{opacity:1;transform:translateX(12px)}40%{transform:translateX(-8px)}60%{transform:translateX(6px)}80%{transform:translateX(-3px)}100%{opacity:1;transform:none}}';
			case 'wobble':
				return '@keyframes ' . $name . '{0%{opacity:0;transform:translateX(-12px) rotate(-4deg)}25%{opacity:1;transform:translateX(8px) rotate(3deg)}50%{transform:translateX(-6px) rotate(-2deg)}75%{transform:translateX(3px) rotate(1deg)}100%{opacity:1;transform:none}}';
		}
		return '@keyframes ' . $name . '{0%{opacity:0}100%{opacity:1}}';
	}

	private static function legacy_css() {
		return '.lb-interact-fade{opacity:0;transition:opacity var(--lb-duration,.6s) ease}.lb-interact-fade.lb-interact-active{opacity:1}'
			. '.lb-interact-slide-up{opacity:0;transform:translateY(24px);transition:opacity var(--lb-duration,.6s) ease,transform var(--lb-duration,.6s) ease}.lb-interact-slide-up.lb-interact-active{opacity:1;transform:none}'
			. '.lb-interact-scale{opacity:0;transform:scale(.94);transition:opacity var(--lb-duration,.6s) ease,transform var(--lb-duration,.6s) ease}.lb-interact-scale.lb-interact-active{opacity:1;transform:none}'
			. '.lb-interact-slide-right{opacity:0;transform:translateX(-24px);transition:opacity var(--lb-duration,.6s) var(--lb-easing,ease),transform var(--lb-duration,.6s) var(--lb-easing,ease)}.lb-interact-slide-right.lb-interact-active{opacity:1;transform:none}'
			. '.lb-interact-rotate{opacity:0;transform:rotate(-5deg) scale(.98);transition:opacity var(--lb-duration,.6s) var(--lb-easing,ease),transform var(--lb-duration,.6s) var(--lb-easing,ease)}.lb-interact-rotate.lb-interact-active{opacity:1;transform:none}'
			. '.lb-interact-blur{opacity:.1;filter:blur(8px);transition:opacity var(--lb-duration,.6s) var(--lb-easing,ease),filter var(--lb-duration,.6s) var(--lb-easing,ease)}.lb-interact-blur.lb-interact-active{opacity:1;filter:none}';
	}

	private static function breakpoint_skip_css() {
		if ( ! class_exists( Breakpoints::class ) ) {
			return '';
		}
		$css = '';
		foreach ( Breakpoints::enabled() as $name => $bp ) {
			$name = sanitize_key( $name );
			if ( $name === '' ) {
				continue;
			}
			$sel   = '.lb-fx-no-' . $name;
			$chunk = $sel . '{animation:none!important;opacity:1!important;transform:none!important;filter:none!important;clip-path:none!important}';
			if ( method_exists( Breakpoints::class, 'wrap_range' ) ) {
				$css .= Breakpoints::wrap_range( $name, $chunk );
			} else {
				$css .= $chunk;
			}
		}
		return $css;
	}

	private static function reduced_motion_css() {
		return '@media (prefers-reduced-motion:reduce){.lb-fx,.lb-fx-preview,[data-lb-fx],[data-lb-interaction],.lb-interact-fade,.lb-interact-slide-up,.lb-interact-scale,.lb-interact-slide-right,.lb-interact-rotate,.lb-interact-blur{animation:none!important;transition:none!important;transform:none!important;filter:none!important;clip-path:none!important;opacity:1!important}}';
	}

	/**
	 * Per-node CSS for custom keyframe interactions.
	 *
	 * @param array $node
	 */
	public static function custom_css( $node ) {
		$id    = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $node['id'] ?? '' ) );
		$items = is_array( $node['interactions'] ?? null ) ? $node['interactions'] : array();
		$css   = '';
		foreach ( $items as $i => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$kind = sanitize_key( $item['kind'] ?? 'entrance' );
			if ( $kind !== 'custom' && ( $item['effect'] ?? '' ) !== 'custom' ) {
				continue;
			}
			$frames = is_array( $item['keyframes'] ?? null ) ? $item['keyframes'] : array();
			$body   = self::keyframes_body( $frames );
			if ( $body === '' ) {
				continue;
			}
			$name = 'lb-fx-c-' . $id . '-' . (int) $i;
			$css .= '@keyframes ' . $name . '{' . $body . '}';
			$css .= '#lb-node-' . $id . '.lb-fx-custom-' . (int) $i . '{animation-name:' . $name . '}';
		}
		return $css;
	}

	private static function keyframes_body( array $frames ) {
		$out = '';
		foreach ( $frames as $frame ) {
			if ( ! is_array( $frame ) ) {
				continue;
			}
			$off = max( 0, min( 100, (float) ( $frame['offset'] ?? 0 ) ) );
			$out .= $off . '%{' . self::from_decl( $frame ) . '}';
		}
		return $out;
	}

	/**
	 * Convert legacy settings.interaction* into node.interactions[].
	 *
	 * @param array $items Existing interactions.
	 * @param array $settings Node settings.
	 * @return array
	 */
	public static function merge_legacy( $items, array $settings ) {
		$items = is_array( $items ) ? array_values( $items ) : array();
		if ( $items ) {
			return $items;
		}
		$effect = sanitize_key( (string) ( $settings['interaction'] ?? '' ) );
		if ( $effect === '' ) {
			return array();
		}
		if ( $effect === 'slide-right' ) {
			$effect = 'slide-right';
		}
		return array(
			array(
				'id'        => 'i_legacy',
				'kind'      => 'entrance',
				'trigger'   => sanitize_key( (string) ( $settings['interaction_trigger'] ?? 'viewport' ) ),
				'effect'    => $effect,
				'duration'  => (float) ( $settings['interaction_duration'] ?? 0.6 ),
				'delay'     => (float) ( $settings['interaction_delay'] ?? 0 ),
				'easing'    => (string) ( $settings['interaction_easing'] ?? 'ease' ),
				'iteration' => 1,
				'repeat'    => ! empty( $settings['interaction_repeat'] ),
				'threshold' => (float) ( $settings['interaction_threshold'] ?? 0.15 ),
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Interaction breakpoint list, not a get_posts() arg.
				'exclude'   => array(),
				'keyframes' => array(),
			),
		);
	}

	/**
	 * Walk a node tree and fold legacy settings into interactions[].
	 *
	 * @param array $nodes
	 */
	public static function migrate_tree( array &$nodes ) {
		foreach ( $nodes as &$n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			$n['interactions'] = self::merge_legacy( $n['interactions'] ?? array(), is_array( $n['settings'] ?? null ) ? $n['settings'] : array() );
			if ( ! empty( $n['children'] ) && is_array( $n['children'] ) ) {
				self::migrate_tree( $n['children'] );
			}
		}
		unset( $n );
	}

	/**
	 * @param mixed $items
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize( $items ) {
		$out     = array();
		$presets = self::preset_ids();
		$names   = class_exists( Breakpoints::class ) ? Breakpoints::names() : array( 'mobile', 'tablet', 'desktop' );
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', substr( (string) ( $item['id'] ?? '' ), 0, 40 ) );
			if ( $id === '' ) {
				$id = 'i_' . substr( md5( wp_json_encode( $item ) ), 0, 12 );
			}
			$kind    = sanitize_key( $item['kind'] ?? 'entrance' );
			$trigger = sanitize_key( $item['trigger'] ?? 'viewport' );
			$effect  = sanitize_key( $item['effect'] ?? 'fade' );
			if ( ! in_array( $kind, self::KINDS, true ) ) {
				$kind = 'entrance';
			}
			if ( ! in_array( $trigger, self::TRIGGERS, true ) ) {
				$trigger = 'viewport';
			}
			if ( $kind === 'custom' ) {
				$effect = 'custom';
			} elseif ( ! in_array( $effect, $presets, true ) ) {
				$effect = 'fade';
			}
			$iteration = $item['iteration'] ?? 1;
			if ( $iteration === 'infinite' || $iteration === 'inf' ) {
				$iteration = 'infinite';
			} else {
				$iteration = max( 1, min( 20, (int) $iteration ) );
			}
			$exclude = array();
			foreach ( (array) ( $item['exclude'] ?? array() ) as $bp ) {
				$bp = sanitize_key( (string) $bp );
				if ( $bp !== '' && in_array( $bp, $names, true ) ) {
					$exclude[] = $bp;
				}
			}
			$row = array(
				'id'        => $id,
				'kind'      => $kind,
				'trigger'   => $trigger,
				'effect'    => $effect,
				'duration'  => max( 0, min( 30, (float) ( $item['duration'] ?? 0.6 ) ) ),
				'delay'     => max( 0, min( 30, (float) ( $item['delay'] ?? 0 ) ) ),
				'easing'    => self::sanitize_easing( $item['easing'] ?? 'ease' ),
				'iteration' => $iteration,
				'repeat'    => ! empty( $item['repeat'] ),
				'threshold' => max( 0, min( 1, (float) ( $item['threshold'] ?? 0.15 ) ) ),
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Interaction breakpoint list, not a get_posts() arg.
				'exclude'   => array_values( array_unique( $exclude ) ),
				'keyframes' => array(),
			);
			if ( $kind === 'custom' ) {
				$row['keyframes'] = self::sanitize_keyframes( $item['keyframes'] ?? array() );
			}
			$out[] = $row;
		}
		return $out;
	}

	public static function sanitize_easing( $v ) {
		$v = strtolower( trim( (string) $v ) );
		if ( in_array( $v, self::EASINGS, true ) ) {
			return $v;
		}
		if ( preg_match( '/^cubic-bezier\(\s*-?\d*\.?\d+\s*,\s*-?\d*\.?\d+\s*,\s*-?\d*\.?\d+\s*,\s*-?\d*\.?\d+\s*\)$/', $v ) ) {
			return $v;
		}
		return 'ease';
	}

	private static function sanitize_keyframes( $frames ) {
		$out = array();
		foreach ( array_slice( (array) $frames, 0, 12 ) as $frame ) {
			if ( ! is_array( $frame ) ) {
				continue;
			}
			$out[] = array(
				'offset'  => max( 0, min( 100, (float) ( $frame['offset'] ?? 0 ) ) ),
				'opacity' => max( 0, min( 1, (float) ( $frame['opacity'] ?? 1 ) ) ),
				'x'       => max( -2000, min( 2000, (float) ( $frame['x'] ?? 0 ) ) ),
				'y'       => max( -2000, min( 2000, (float) ( $frame['y'] ?? 0 ) ) ),
				'scale'   => max( 0, min( 5, (float) ( $frame['scale'] ?? 1 ) ) ),
				'rotate'  => max( -720, min( 720, (float) ( $frame['rotate'] ?? 0 ) ) ),
				'blur'    => max( 0, min( 50, (float) ( $frame['blur'] ?? 0 ) ) ),
			);
		}
		return $out;
	}

	/**
	 * Wrapper classes for a node (first armable entrance/custom).
	 *
	 * @param array $node
	 */
	public static function classes( $node ) {
		$items = self::list_for( $node );
		if ( ! $items ) {
			$s = is_array( $node ) ? ( $node['settings'] ?? array() ) : array();
			if ( is_array( $s ) && ! empty( $s['interaction'] ) ) {
				return ' lb-interact-' . sanitize_html_class( $s['interaction'] );
			}
			return '';
		}
		$first = self::primary( $items );
		if ( ! $first ) {
			return '';
		}
		$kind    = $first['kind'];
		$trigger = $first['trigger'];
		$effect  = $first['effect'];
		$arm     = ( $kind !== 'exit' ) && in_array( $trigger, array( 'viewport', 'load', 'scroll' ), true );
		$cls     = $arm ? ' lb-fx' : '';
		if ( $kind === 'exit' ) {
			$cls .= ' lb-fx-exit';
		}
		if ( $trigger === 'scroll' ) {
			$cls .= ' lb-fx-scroll';
		}
		if ( $kind === 'custom' || $effect === 'custom' ) {
			$cls .= ' lb-fx-custom-0';
		} elseif ( $arm ) {
			$cls .= ' lb-fx-' . sanitize_html_class( $effect );
		}
		foreach ( (array) ( $first['exclude'] ?? array() ) as $bp ) {
			$bp = sanitize_html_class( $bp );
			if ( $bp ) {
				$cls .= ' lb-fx-no-' . $bp;
			}
		}
		unset( $trigger );
		return $cls;
	}

	/**
	 * data-* attributes for the frontend handler.
	 *
	 * @param array $node
	 */
	public static function data_attrs( $node ) {
		$items = self::list_for( $node );
		if ( ! $items ) {
			$s = is_array( $node ) ? ( $node['settings'] ?? array() ) : array();
			if ( ! is_array( $s ) || empty( $s['interaction'] ) ) {
				return '';
			}
			return ' data-lb-interaction="' . esc_attr( $s['interaction'] ) . '" data-lb-trigger="' . esc_attr( $s['interaction_trigger'] ?? 'viewport' ) . '" data-lb-delay="' . esc_attr( $s['interaction_delay'] ?? 0 ) . '" data-lb-duration="' . esc_attr( $s['interaction_duration'] ?? 0.6 ) . '" data-lb-repeat="' . ( ! empty( $s['interaction_repeat'] ) ? '1' : '0' ) . '" data-lb-threshold="' . esc_attr( $s['interaction_threshold'] ?? .15 ) . '"';
		}
		$payload = array();
		foreach ( $items as $item ) {
			$row = array(
				'id'        => $item['id'],
				'kind'      => $item['kind'],
				'trigger'   => $item['trigger'],
				'effect'    => $item['effect'],
				'duration'  => $item['duration'],
				'delay'     => $item['delay'],
				'easing'    => $item['easing'],
				'iteration' => $item['iteration'],
				'repeat'    => ! empty( $item['repeat'] ),
				'threshold' => $item['threshold'],
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Interaction breakpoint list, not a get_posts() arg.
				'exclude'   => $item['exclude'],
			);
			$payload[] = $row;
		}
		$first = self::primary( $items );
		$style = '';
		if ( $first ) {
			$iter  = $first['iteration'] === 'infinite' ? 'infinite' : (string) max( 1, (int) $first['iteration'] );
			$style = '--lb-fx-duration:' . (float) $first['duration'] . 's;--lb-fx-delay:' . (float) $first['delay'] . 's;--lb-fx-easing:' . esc_attr( $first['easing'] ) . ';--lb-fx-iteration:' . esc_attr( $iter ) . ';';
		}
		$json = wp_json_encode( $payload );
		$out  = ' data-lb-fx="' . esc_attr( is_string( $json ) ? $json : '[]' ) . '"';
		if ( $style !== '' ) {
			$out .= ' style="' . $style . '"';
		}
		return $out;
	}

	/**
	 * @param array $nodes
	 */
	public static function has( $nodes ) {
		foreach ( (array) $nodes as $n ) {
			if ( ! empty( $n['interactions'] ) && is_array( $n['interactions'] ) ) {
				return true;
			}
			$s = $n['settings'] ?? array();
			if ( ! empty( $s['interaction'] ) ) {
				return true;
			}
			if ( ! empty( $n['children'] ) && self::has( $n['children'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array $node
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for( $node ) {
		$items = is_array( $node['interactions'] ?? null ) ? $node['interactions'] : array();
		if ( ! $items && is_array( $node ) ) {
			$items = self::merge_legacy( array(), is_array( $node['settings'] ?? null ) ? $node['settings'] : array() );
		}
		return self::sanitize( $items );
	}

	private static function primary( array $items ) {
		foreach ( $items as $item ) {
			if ( ( $item['kind'] ?? '' ) === 'entrance' || ( $item['kind'] ?? '' ) === 'custom' ) {
				return $item;
			}
		}
		return $items[0] ?? null;
	}
}
