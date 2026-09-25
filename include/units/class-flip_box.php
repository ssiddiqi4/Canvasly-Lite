<?php
namespace CanvaslyLite\Units;
use CanvaslyLite\Controls\Groups;
use CanvaslyLite\Utils\Icons;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flip Box: two-sided card with per-side image/icon/title/description, a back-side
 * button, and flip / slide / push / zoom / fade motion (direction + optional 3D depth).
 */
class FlipBox extends Unit {
	public function type() {
		return 'flip_box';
	}
	public function scripts( $settings = array() ) {
		return $this->frontend_scripts();
	}
	public function uses_button() {
		return true;
	}
	public function title() {
		return __( 'Flip Box', 'canvasly-lite' );
	}
	public function icon() {
		return '▱';
	}
	public function category() {
		return 'basic';
	}
	public function keywords() {
		return array( 'flip box', 'flip', 'card', 'rotate', 'hover', 'front', 'back' );
	}

	public static function effects() {
		return array( 'flip', 'slide', 'push', 'zoom', 'fade' );
	}
	public static function directions() {
		return array( 'left', 'right', 'up', 'down' );
	}
	public static function graphics() {
		return array( 'none', 'icon', 'image' );
	}

	public function defaults() {
		$pad = array(
			'top'    => '30px',
			'right'  => '30px',
			'bottom' => '30px',
			'left'   => '30px',
			'linked' => true,
		);
		return array(
			'front_graphic'         => 'icon',
			'front_icon'            => 'star',
			'front_icon_view'       => 'default',
			'front_shape'           => 'circle',
			'front_image_id'        => 0,
			'front_image_url'       => '',
			'front_title'           => __( 'Front', 'canvasly-lite' ),
			'front_title_tag'       => 'h3',
			'front_text'            => __( 'Front content', 'canvasly-lite' ),
			'back_graphic'          => 'none',
			'back_icon'             => 'star',
			'back_icon_view'        => 'default',
			'back_shape'            => 'circle',
			'back_image_id'         => 0,
			'back_image_url'        => '',
			'back_title'            => __( 'Back', 'canvasly-lite' ),
			'back_title_tag'        => 'h3',
			'back_text'             => __( 'Back content', 'canvasly-lite' ),
			'show_button'           => true,
			'back_button_text'      => __( 'Click Here', 'canvasly-lite' ),
			'back_button_url'       => '#',
			'back_button_target'    => '_self',
			'flip_effect'           => 'flip',
			'flip_direction'        => 'right',
			'flip_3d'               => false,
			'flip_depth'            => 50,
			'flip_duration'         => 0.8,
			'flip_trigger'          => 'hover',
			'box_height'            => 280,
			'front_background'      => array(
				'type'  => 'classic',
				'color' => '#4054b2',
			),
			'back_background'       => array(
				'type'  => 'classic',
				'color' => '#1f2124',
			),
			'front_align'           => 'center',
			'front_valign'          => 'middle',
			'front_padding'         => $pad,
			'front_title_color'     => '#ffffff',
			'front_desc_color'      => '#ffffff',
			'front_icon_size'       => 40,
			'front_icon_color'      => '#ffffff',
			'front_icon_space'      => 15,
			'front_image_width'     => 80,
			'front_image_radius'    => 0,
			'front_title_space'     => 10,
			'back_align'            => 'center',
			'back_valign'           => 'middle',
			'back_padding'          => $pad,
			'back_title_color'      => '#ffffff',
			'back_desc_color'       => '#ffffff',
			'back_icon_size'        => 40,
			'back_icon_color'       => '#ffffff',
			'back_icon_space'       => 15,
			'back_image_width'      => 80,
			'back_image_radius'     => 0,
			'back_title_space'      => 10,
			'button_background'     => '#ffffff',
			'button_text_color'     => '#1f2124',
			'button_hover_background' => '',
			'button_hover_color'    => '',
			'button_radius'         => 4,
			'button_padding'        => array(
				'top'    => '10px',
				'right'  => '20px',
				'bottom' => '10px',
				'left'   => '20px',
				'linked' => false,
			),
		);
	}

	public function controls() {
		$front    = __( 'Front', 'canvasly-lite' );
		$back     = __( 'Back', 'canvasly-lite' );
		$settings = __( 'Settings', 'canvasly-lite' );
		$s_front  = __( 'Front', 'canvasly-lite' );
		$s_back   = __( 'Back', 'canvasly-lite' );
		$s_btn    = __( 'Button', 'canvasly-lite' );
		$graphic  = array(
			'none'  => __( 'None', 'canvasly-lite' ),
			'icon'  => __( 'Icon', 'canvasly-lite' ),
			'image' => __( 'Image', 'canvasly-lite' ),
		);
		$view     = array(
			'default' => __( 'Default', 'canvasly-lite' ),
			'stacked' => __( 'Stacked', 'canvasly-lite' ),
			'framed'  => __( 'Framed', 'canvasly-lite' ),
		);
		$shape    = array(
			'circle'  => __( 'Circle', 'canvasly-lite' ),
			'rounded' => __( 'Rounded', 'canvasly-lite' ),
			'square'  => __( 'Square', 'canvasly-lite' ),
		);
		$view_on  = array( 'stacked', 'framed' );
		$sizes    = array(
			'thumbnail'    => __( 'Thumbnail', 'canvasly-lite' ),
			'medium'       => __( 'Medium', 'canvasly-lite' ),
			'medium_large' => __( 'Medium Large', 'canvasly-lite' ),
			'large'        => __( 'Large', 'canvasly-lite' ),
			'full'         => __( 'Full', 'canvasly-lite' ),
		);

		return array(
			// Content — Front
			'front_graphic'      => $this->ctrl( 'choose', __( 'Graphic', 'canvasly-lite' ), 'content', $front, array( 'options' => $graphic ) ),
			'front_icon'         => $this->ctrl( 'icon', __( 'Icon', 'canvasly-lite' ), 'content', $front, array( 'condition' => array( 'front_graphic' => 'icon' ) ) ),
			'front_icon_view'    => $this->ctrl( 'select', __( 'Icon View', 'canvasly-lite' ), 'content', $front, array( 'options' => $view, 'condition' => array( 'front_graphic' => 'icon' ) ) ),
			'front_shape'        => $this->ctrl( 'select', __( 'Icon Shape', 'canvasly-lite' ), 'content', $front, array( 'options' => $shape, 'condition' => array( 'front_graphic' => 'icon', 'front_icon_view' => $view_on ) ) ),
			'front_image_id'     => $this->ctrl( 'media', __( 'Image', 'canvasly-lite' ), 'content', $front, array( 'dynamic' => true, 'condition' => array( 'front_graphic' => 'image' ) ) ),
			'front_image_url'    => $this->ctrl( 'url', __( 'Image URL', 'canvasly-lite' ), 'content', $front, array( 'hidden' => true, 'dynamic' => true ) ),
			'front_image_size'   => $this->ctrl( 'select', __( 'Image Size', 'canvasly-lite' ), 'content', $front, array( 'options' => $sizes, 'condition' => array( 'front_graphic' => 'image' ) ) ),
			'front_title'        => $this->ctrl( 'text', __( 'Title', 'canvasly-lite' ), 'content', $front, array( 'dynamic' => true ) ),
			'front_title_tag'    => $this->ctrl( 'select', __( 'Title HTML Tag', 'canvasly-lite' ), 'content', $front, array( 'options' => self::opt_title_tags() ) ),
			'front_text'         => $this->ctrl( 'textarea', __( 'Description', 'canvasly-lite' ), 'content', $front, array( 'dynamic' => true ) ),

			// Content — Back
			'back_graphic'       => $this->ctrl( 'choose', __( 'Graphic', 'canvasly-lite' ), 'content', $back, array( 'options' => $graphic ) ),
			'back_icon'          => $this->ctrl( 'icon', __( 'Icon', 'canvasly-lite' ), 'content', $back, array( 'condition' => array( 'back_graphic' => 'icon' ) ) ),
			'back_icon_view'     => $this->ctrl( 'select', __( 'Icon View', 'canvasly-lite' ), 'content', $back, array( 'options' => $view, 'condition' => array( 'back_graphic' => 'icon' ) ) ),
			'back_shape'         => $this->ctrl( 'select', __( 'Icon Shape', 'canvasly-lite' ), 'content', $back, array( 'options' => $shape, 'condition' => array( 'back_graphic' => 'icon', 'back_icon_view' => $view_on ) ) ),
			'back_image_id'      => $this->ctrl( 'media', __( 'Image', 'canvasly-lite' ), 'content', $back, array( 'dynamic' => true, 'condition' => array( 'back_graphic' => 'image' ) ) ),
			'back_image_url'     => $this->ctrl( 'url', __( 'Image URL', 'canvasly-lite' ), 'content', $back, array( 'hidden' => true, 'dynamic' => true ) ),
			'back_image_size'    => $this->ctrl( 'select', __( 'Image Size', 'canvasly-lite' ), 'content', $back, array( 'options' => $sizes, 'condition' => array( 'back_graphic' => 'image' ) ) ),
			'back_title'         => $this->ctrl( 'text', __( 'Title', 'canvasly-lite' ), 'content', $back, array( 'dynamic' => true ) ),
			'back_title_tag'     => $this->ctrl( 'select', __( 'Title HTML Tag', 'canvasly-lite' ), 'content', $back, array( 'options' => self::opt_title_tags() ) ),
			'back_text'          => $this->ctrl( 'textarea', __( 'Description', 'canvasly-lite' ), 'content', $back, array( 'dynamic' => true ) ),
			'show_button'        => $this->ctrl( 'switch', __( 'Show Button', 'canvasly-lite' ), 'content', $back ),
			'back_button_text'   => $this->ctrl( 'text', __( 'Button Text', 'canvasly-lite' ), 'content', $back, array( 'dynamic' => true, 'condition' => array( 'show_button' => true ) ) ),
			'back_button_url'    => $this->ctrl( 'url', __( 'Button Link', 'canvasly-lite' ), 'content', $back, array( 'dynamic' => true, 'condition' => array( 'show_button' => true ) ) ),
			'back_button_target' => $this->ctrl( 'select', __( 'Link Target', 'canvasly-lite' ), 'content', $back, array( 'options' => self::opt_target(), 'condition' => array( 'show_button' => true, 'back_button_url!' => '' ) ) ),
			'button_background'  => $this->ctrl( 'color', __( 'Button Color', 'canvasly-lite' ), 'content', $back, array( 'condition' => array( 'show_button' => true ), 'selectors' => array( '{{WRAPPER}}' => '--lb-flip-btn-bg: {{VALUE}};' ) ) ),
			'button_text_color'  => $this->ctrl( 'color', __( 'Button Text Color', 'canvasly-lite' ), 'content', $back, array( 'condition' => array( 'show_button' => true ), 'selectors' => array( '{{WRAPPER}}' => '--lb-flip-btn-color: {{VALUE}};' ) ) ),

			// Content — Settings
			'flip_effect'        => $this->ctrl(
				'choose',
				__( 'Flip Effect', 'canvasly-lite' ),
				'content',
				$settings,
				array(
					'options' => array(
						'flip'  => __( 'Flip', 'canvasly-lite' ),
						'slide' => __( 'Slide', 'canvasly-lite' ),
						'push'  => __( 'Push', 'canvasly-lite' ),
						'zoom'  => __( 'Zoom', 'canvasly-lite' ),
						'fade'  => __( 'Fade', 'canvasly-lite' ),
					),
					'icons'   => array(
						'flip'  => '⟳',
						'slide' => '↔',
						'push'  => '⇉',
						'zoom'  => '⤢',
						'fade'  => '◌',
					),
				)
			),
			'flip_direction'     => $this->ctrl(
				'choose',
				__( 'Direction', 'canvasly-lite' ),
				'content',
				$settings,
				array(
					'options'   => array(
						'left'  => __( 'Left', 'canvasly-lite' ),
						'right' => __( 'Right', 'canvasly-lite' ),
						'up'    => __( 'Up', 'canvasly-lite' ),
						'down'  => __( 'Down', 'canvasly-lite' ),
					),
					'condition' => array( 'flip_effect' => array( 'flip', 'slide', 'push' ) ),
				)
			),
			'flip_3d'            => $this->ctrl( 'switch', __( '3D Depth', 'canvasly-lite' ), 'content', $settings, array( 'condition' => array( 'flip_effect' => 'flip' ) ) ),
			'flip_depth'         => $this->ctrl(
				'slider',
				__( 'Depth', 'canvasly-lite' ),
				'content',
				$settings,
				array(
					'units'     => array( 'px' ),
					'range'     => array( 'min' => 0, 'max' => 200 ),
					'condition' => array( 'flip_effect' => 'flip', 'flip_3d' => true ),
					'selectors' => array( '{{WRAPPER}}' => '--lb-flip-depth: {{SIZE}}{{UNIT}};' ),
				)
			),
			'flip_duration'      => $this->ctrl(
				'slider',
				__( 'Duration', 'canvasly-lite' ),
				'content',
				$settings,
				array(
					'units'     => array(),
					'range'     => array( 'min' => 0.1, 'max' => 3, 'step' => 0.05 ),
					'selectors' => array( '{{WRAPPER}}' => '--lb-flip-duration: {{SIZE}}s;' ),
				)
			),
			'flip_trigger'       => $this->ctrl(
				'select',
				__( 'Trigger', 'canvasly-lite' ),
				'content',
				$settings,
				array(
					'options' => array(
						'hover' => __( 'Hover', 'canvasly-lite' ),
						'click' => __( 'Click', 'canvasly-lite' ),
					),
				)
			),
			'box_height'         => $this->ctrl(
				'slider',
				__( 'Height', 'canvasly-lite' ),
				'content',
				$settings,
				array(
					'responsive' => true,
					'units'      => array( 'px', 'vh', 'em' ),
					'range'      => array( 'min' => 100, 'max' => 800 ),
					'selectors'  => array(
						'{{WRAPPER}}'              => '--lb-flip-height: {{SIZE}}{{UNIT}}; min-height: {{SIZE}}{{UNIT}};',
						'{{WRAPPER}} .lb-flip-box' => 'min-height: {{SIZE}}{{UNIT}};',
					),
				)
			),

			// Style — Front
			'front_background'   => $this->ctrl( 'background', __( 'Background', 'canvasly-lite' ), 'style', $s_front, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-front' => '{{VALUE}}' ) ) ),
			'front_align'        => $this->ctrl(
				'choose',
				__( 'Horizontal Align', 'canvasly-lite' ),
				'style',
				$s_front,
				array(
					'options'   => self::opt_lcr(),
					'map'       => array(
						'left'   => 'flex-start',
						'center' => 'center',
						'right'  => 'flex-end',
					),
					'selectors' => array( '{{WRAPPER}} .lb-flip-front .lb-flip-content' => 'align-items: {{VALUE}}; text-align: {{RAW}};' ),
				)
			),
			'front_valign'       => $this->ctrl(
				'choose',
				__( 'Vertical Align', 'canvasly-lite' ),
				'style',
				$s_front,
				array(
					'options'   => array(
						'top'    => __( 'Top', 'canvasly-lite' ),
						'middle' => __( 'Middle', 'canvasly-lite' ),
						'bottom' => __( 'Bottom', 'canvasly-lite' ),
					),
					'map'       => array(
						'top'    => 'flex-start',
						'middle' => 'center',
						'bottom' => 'flex-end',
					),
					'selectors' => array( '{{WRAPPER}} .lb-flip-front' => 'justify-content: {{VALUE}};' ),
				)
			),
			'front_padding'      => $this->ctrl( 'dimensions', __( 'Padding', 'canvasly-lite' ), 'style', $s_front, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-front .lb-flip-content' => 'padding: {{VALUE}};' ) ) ),
			'front_title_color'  => $this->ctrl( 'color', __( 'Title Color', 'canvasly-lite' ), 'style', $s_front, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-front .lb-flip-title' => 'color: {{VALUE}};' ) ) ),
			'front_title_typography' => $this->ctrl( 'typography', __( 'Title Typography', 'canvasly-lite' ), 'style', $s_front, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-front .lb-flip-title' => '{{VALUE}}' ) ) ),
			'front_title_space'  => $this->ctrl(
				'slider',
				__( 'Title Spacing', 'canvasly-lite' ),
				'style',
				$s_front,
				array(
					'units'     => array( 'px', 'em' ),
					'range'     => array( 'min' => 0, 'max' => 80 ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-front' => '--lb-flip-title-space: {{SIZE}}{{UNIT}};' ),
				)
			),
			'front_desc_color'   => $this->ctrl( 'color', __( 'Description Color', 'canvasly-lite' ), 'style', $s_front, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-front .lb-flip-desc' => 'color: {{VALUE}};' ) ) ),
			'front_desc_typography' => $this->ctrl( 'typography', __( 'Description Typography', 'canvasly-lite' ), 'style', $s_front, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-front .lb-flip-desc' => '{{VALUE}}' ) ) ),
			'front_icon_size'    => $this->ctrl(
				'slider',
				__( 'Icon Size', 'canvasly-lite' ),
				'style',
				$s_front,
				array(
					'units'     => array( 'px', 'em', 'rem' ),
					'range'     => array( 'min' => 8, 'max' => 200 ),
					'condition' => array( 'front_graphic' => 'icon' ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-front' => '--lb-flip-icon-size: {{SIZE}}{{UNIT}};' ),
				)
			),
			'front_icon_color'   => $this->ctrl(
				'color',
				__( 'Icon Color', 'canvasly-lite' ),
				'style',
				$s_front,
				array(
					'condition' => array( 'front_graphic' => 'icon' ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-front' => '--lb-flip-icon-color: {{VALUE}};' ),
				)
			),
			'front_icon_space'   => $this->ctrl(
				'slider',
				__( 'Icon Spacing', 'canvasly-lite' ),
				'style',
				$s_front,
				array(
					'units'     => array( 'px', 'em' ),
					'range'     => array( 'min' => 0, 'max' => 80 ),
					'condition' => array( 'front_graphic' => array( 'icon', 'image' ) ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-front' => '--lb-flip-graphic-space: {{SIZE}}{{UNIT}};' ),
				)
			),
			'front_image_width'  => $this->ctrl(
				'slider',
				__( 'Image Width', 'canvasly-lite' ),
				'style',
				$s_front,
				array(
					'units'     => array( 'px', '%' ),
					'range'     => array( 'min' => 10, 'max' => 400 ),
					'condition' => array( 'front_graphic' => 'image' ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-front' => '--lb-flip-image-width: {{SIZE}}{{UNIT}};' ),
				)
			),
			'front_image_radius' => $this->ctrl(
				'slider',
				__( 'Image Radius', 'canvasly-lite' ),
				'style',
				$s_front,
				array(
					'units'     => array( 'px', '%' ),
					'range'     => array( 'min' => 0, 'max' => 200 ),
					'condition' => array( 'front_graphic' => 'image' ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-front' => '--lb-flip-image-radius: {{SIZE}}{{UNIT}};' ),
				)
			),

			// Style — Back
			'back_background'    => $this->ctrl( 'background', __( 'Background', 'canvasly-lite' ), 'style', $s_back, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-back' => '{{VALUE}}' ) ) ),
			'back_align'         => $this->ctrl(
				'choose',
				__( 'Horizontal Align', 'canvasly-lite' ),
				'style',
				$s_back,
				array(
					'options'   => self::opt_lcr(),
					'map'       => array(
						'left'   => 'flex-start',
						'center' => 'center',
						'right'  => 'flex-end',
					),
					'selectors' => array( '{{WRAPPER}} .lb-flip-back .lb-flip-content' => 'align-items: {{VALUE}}; text-align: {{RAW}};' ),
				)
			),
			'back_valign'        => $this->ctrl(
				'choose',
				__( 'Vertical Align', 'canvasly-lite' ),
				'style',
				$s_back,
				array(
					'options'   => array(
						'top'    => __( 'Top', 'canvasly-lite' ),
						'middle' => __( 'Middle', 'canvasly-lite' ),
						'bottom' => __( 'Bottom', 'canvasly-lite' ),
					),
					'map'       => array(
						'top'    => 'flex-start',
						'middle' => 'center',
						'bottom' => 'flex-end',
					),
					'selectors' => array( '{{WRAPPER}} .lb-flip-back' => 'justify-content: {{VALUE}};' ),
				)
			),
			'back_padding'       => $this->ctrl( 'dimensions', __( 'Padding', 'canvasly-lite' ), 'style', $s_back, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-back .lb-flip-content' => 'padding: {{VALUE}};' ) ) ),
			'back_title_color'   => $this->ctrl( 'color', __( 'Title Color', 'canvasly-lite' ), 'style', $s_back, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-back .lb-flip-title' => 'color: {{VALUE}};' ) ) ),
			'back_title_typography' => $this->ctrl( 'typography', __( 'Title Typography', 'canvasly-lite' ), 'style', $s_back, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-back .lb-flip-title' => '{{VALUE}}' ) ) ),
			'back_title_space'   => $this->ctrl(
				'slider',
				__( 'Title Spacing', 'canvasly-lite' ),
				'style',
				$s_back,
				array(
					'units'     => array( 'px', 'em' ),
					'range'     => array( 'min' => 0, 'max' => 80 ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-back' => '--lb-flip-title-space: {{SIZE}}{{UNIT}};' ),
				)
			),
			'back_desc_color'    => $this->ctrl( 'color', __( 'Description Color', 'canvasly-lite' ), 'style', $s_back, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-back .lb-flip-desc' => 'color: {{VALUE}};' ) ) ),
			'back_desc_typography' => $this->ctrl( 'typography', __( 'Description Typography', 'canvasly-lite' ), 'style', $s_back, array( 'selectors' => array( '{{WRAPPER}} .lb-flip-back .lb-flip-desc' => '{{VALUE}}' ) ) ),
			'back_icon_size'     => $this->ctrl(
				'slider',
				__( 'Icon Size', 'canvasly-lite' ),
				'style',
				$s_back,
				array(
					'units'     => array( 'px', 'em', 'rem' ),
					'range'     => array( 'min' => 8, 'max' => 200 ),
					'condition' => array( 'back_graphic' => 'icon' ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-back' => '--lb-flip-icon-size: {{SIZE}}{{UNIT}};' ),
				)
			),
			'back_icon_color'    => $this->ctrl(
				'color',
				__( 'Icon Color', 'canvasly-lite' ),
				'style',
				$s_back,
				array(
					'condition' => array( 'back_graphic' => 'icon' ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-back' => '--lb-flip-icon-color: {{VALUE}};' ),
				)
			),
			'back_icon_space'    => $this->ctrl(
				'slider',
				__( 'Icon Spacing', 'canvasly-lite' ),
				'style',
				$s_back,
				array(
					'units'     => array( 'px', 'em' ),
					'range'     => array( 'min' => 0, 'max' => 80 ),
					'condition' => array( 'back_graphic' => array( 'icon', 'image' ) ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-back' => '--lb-flip-graphic-space: {{SIZE}}{{UNIT}};' ),
				)
			),
			'back_image_width'   => $this->ctrl(
				'slider',
				__( 'Image Width', 'canvasly-lite' ),
				'style',
				$s_back,
				array(
					'units'     => array( 'px', '%' ),
					'range'     => array( 'min' => 10, 'max' => 400 ),
					'condition' => array( 'back_graphic' => 'image' ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-back' => '--lb-flip-image-width: {{SIZE}}{{UNIT}};' ),
				)
			),
			'back_image_radius'  => $this->ctrl(
				'slider',
				__( 'Image Radius', 'canvasly-lite' ),
				'style',
				$s_back,
				array(
					'units'     => array( 'px', '%' ),
					'range'     => array( 'min' => 0, 'max' => 200 ),
					'condition' => array( 'back_graphic' => 'image' ),
					'selectors' => array( '{{WRAPPER}} .lb-flip-back' => '--lb-flip-image-radius: {{SIZE}}{{UNIT}};' ),
				)
			),

			// Style — Button
			'button_typography'  => $this->ctrl( 'typography', __( 'Typography', 'canvasly-lite' ), 'style', $s_btn, array( 'condition' => array( 'show_button' => true ), 'selectors' => array( '{{WRAPPER}} .lb-flip-button' => '{{VALUE}}' ) ) ),
			'button_hover_background' => $this->ctrl( 'color', __( 'Hover Background', 'canvasly-lite' ), 'style', $s_btn, array( 'condition' => array( 'show_button' => true ), 'selectors' => array( '{{WRAPPER}}' => '--lb-flip-btn-hover-bg: {{VALUE}};' ) ) ),
			'button_hover_color' => $this->ctrl( 'color', __( 'Hover Text Color', 'canvasly-lite' ), 'style', $s_btn, array( 'condition' => array( 'show_button' => true ), 'selectors' => array( '{{WRAPPER}}' => '--lb-flip-btn-hover-color: {{VALUE}};' ) ) ),
			'button_padding'     => $this->ctrl( 'dimensions', __( 'Padding', 'canvasly-lite' ), 'style', $s_btn, array( 'condition' => array( 'show_button' => true ), 'selectors' => array( '{{WRAPPER}} .lb-flip-button' => 'padding: {{VALUE}};' ) ) ),
		);
	}

	public function render( $s, $children = '' ) {
		$effect    = $this->pick( $s['flip_effect'] ?? 'flip', self::effects(), 'flip' );
		$direction = $this->pick( $s['flip_direction'] ?? 'right', self::directions(), 'right' );
		$trigger   = ( $s['flip_trigger'] ?? 'hover' ) === 'click' ? 'click' : 'hover';
		$depth     = ! empty( $s['flip_3d'] ) && $effect === 'flip';
		$cls       = array(
			$this->cls( $s ),
			'lb-flip-box',
			'lb-flip-effect-' . $effect,
			'lb-flip-dir-' . $direction,
			'lb-flip-trigger-' . $trigger,
		);
		if ( $depth ) {
			$cls[] = 'lb-flip-3d';
		}
		$vars  = $this->style_attr(
			array(
				'--lb-flip-height'      => $this->unit( $s['box_height'] ?? 280 ),
				'--lb-flip-duration'    => $this->duration( $s['flip_duration'] ?? 0.8 ),
				'--lb-flip-depth'       => $this->unit( $s['flip_depth'] ?? 50 ),
				'--lb-flip-perspective' => $effect === 'flip' ? '1000px' : 'none',
				'--lb-btn-radius'       => $this->unit( $s['button_radius'] ?? '' ),
			)
		);
		$label = trim( wp_strip_all_tags( (string) ( $s['front_title'] ?? '' ) ) );
		$aria  = $label !== '' ? ' aria-label="' . esc_attr( $label ) . '"' : '';
		$role  = $trigger === 'click' ? ' role="button" tabindex="0" aria-pressed="false"' : '';
		$out   = '<div class="' . esc_attr( implode( ' ', $cls ) ) . '" data-lb-flip="' . esc_attr( $trigger ) . '"' . $role . $aria . $vars . '>';
		$out  .= '<div class="lb-flip-layer">';
		$out  .= $this->face_html( 'front', $s );
		$out  .= $this->face_html( 'back', $s );
		return $out . '</div></div>';
	}

	public function style_css( $id, $s ) {
		$sel = '#lb-node-' . $id;
		$css = $sel . '{--lb-flip-height:' . esc_attr( $this->unit( $s['box_height'] ?? 280 ) ) . ';}';
		foreach ( array( 'front', 'back' ) as $side ) {
			$bg  = $this->resolve_background( $s[ $side . '_background' ] ?? array() );
			$url = is_array( $bg ) ? (string) ( $bg['image_url'] ?? '' ) : '';
			if ( $url !== '' && empty( ( $s[ $side . '_background' ]['image_url'] ?? '' ) ) ) {
				$css .= $sel . ' .lb-flip-' . $side . '{background-image:url(' . esc_url( $url ) . ');}';
			}
		}
		return $css;
	}

	private function face_html( $side, array $s ) {
		$align  = $this->pick( $s[ $side . '_align' ] ?? 'center', array( 'left', 'center', 'right' ), 'center' );
		$valign = $this->pick( $s[ $side . '_valign' ] ?? 'middle', array( 'top', 'middle', 'bottom' ), 'middle' );
		$graphic_type = $this->pick( $s[ $side . '_graphic' ] ?? 'none', self::graphics(), 'none' );
		$vars   = array(
			'--lb-flip-title-color'   => $s[ $side . '_title_color' ] ?? '',
			'--lb-flip-desc-color'    => $s[ $side . '_desc_color' ] ?? '',
			'--lb-flip-title-space'   => $this->unit( $s[ $side . '_title_space' ] ?? '' ),
			'--lb-flip-graphic-space' => $this->unit( $s[ $side . '_icon_space' ] ?? '' ),
			'--lb-flip-icon-size'     => $this->unit( $s[ $side . '_icon_size' ] ?? '' ),
			'--lb-flip-icon-color'    => $s[ $side . '_icon_color' ] ?? '',
			'--lb-flip-image-width'   => $this->unit( $s[ $side . '_image_width' ] ?? '' ),
			'--lb-flip-image-radius'  => $this->unit( $s[ $side . '_image_radius' ] ?? '' ),
		);
		if ( $side === 'back' ) {
			$vars['--lb-flip-btn-bg']          = $s['button_background'] ?? '#ffffff';
			$vars['--lb-flip-btn-color']       = $s['button_text_color'] ?? '#1f2124';
			$vars['--lb-flip-btn-hover-bg']    = $s['button_hover_background'] ?? '';
			$vars['--lb-flip-btn-hover-color'] = $s['button_hover_color'] ?? '';
		}
		$style = $this->face_inline_style( $s, $side, $vars );
		$view  = $this->pick( $s[ $side . '_icon_view' ] ?? 'default', array( 'default', 'stacked', 'framed' ), 'default' );
		$shape = $this->pick( $s[ $side . '_shape' ] ?? 'circle', array( 'circle', 'rounded', 'square' ), 'circle' );
		$html  = '<div class="lb-flip-' . $side . ' lb-flip-align-' . $align . ' lb-flip-valign-' . $valign . ' lb-icon-view-' . $view . ' lb-icon-shape-' . $shape . '"' . $style . '>';
		$html .= $this->face_layers( $s, $side );
		$html .= '<div class="lb-flip-content">';
		$html .= $this->graphic_html( $side, $s, $graphic_type );
		$tag   = $this->tag( $s[ $side . '_title_tag' ] ?? 'h3', $this->title_tags(), 'h3' );
		$title = (string) ( $s[ $side . '_title' ] ?? '' );
		if ( $title !== '' ) {
			$html .= '<' . $tag . ' class="lb-flip-title">' . esc_html( $title ) . '</' . $tag . '>';
		}
		$text = (string) ( $s[ $side . '_text' ] ?? '' );
		if ( $text !== '' ) {
			$html .= '<div class="lb-flip-desc">' . nl2br( esc_html( $text ) ) . '</div>';
		}
		if ( $side === 'back' && ! empty( $s['show_button'] ) && (string) ( $s['back_button_text'] ?? '' ) !== '' ) {
			$url = esc_url( (string) ( $s['back_button_url'] ?? '#' ) );
			if ( $url === '' ) {
				$url = '#';
			}
			$t    = ( $s['back_button_target'] ?? '_self' ) === '_blank' ? ' target="_blank" rel="noopener"' : '';
			$btn_style = $this->style_attr(
				array(
					'background' => $s['button_background'] ?? '#ffffff',
					'color'      => $s['button_text_color'] ?? '#1f2124',
				)
			);
			$html .= '<a class="lb-flip-button" href="' . $url . '"' . $t . $btn_style . '>' . esc_html( $s['back_button_text'] ) . '</a>';
		}
		return $html . '</div></div>';
	}

	private function graphic_html( $side, array $s, $type ) {
		if ( $type === 'icon' ) {
			$svg = Icons::svg( $s[ $side . '_icon' ] ?? 'star' );
			$iv  = $this->style_attr(
				array(
					'--lb-icon-size'    => $this->unit( $s[ $side . '_icon_size' ] ?? 40 ),
					'--lb-icon-primary' => $s[ $side . '_icon_color' ] ?? '',
				)
			);
			return '<div class="lb-flip-graphic"' . $iv . '><span class="lb-icon-glyph">' . $svg . '</span></div>';
		}
		if ( $type === 'image' ) {
			$id   = absint( $s[ $side . '_image_id' ] ?? 0 );
			$size = sanitize_key( $s[ $side . '_image_size' ] ?? 'large' );
			$url  = $this->media_url( $id, $s[ $side . '_image_url' ] ?? '', $size ?: 'large' );
			if ( ! $url ) {
				return '';
			}
			$alt = (string) ( $s[ $side . '_title' ] ?? '' );
			return '<div class="lb-flip-graphic"><img class="lb-flip-image" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy"></div>';
		}
		return '';
	}

	private function face_layers( array $s, $side ) {
		if ( ! class_exists( Groups::class ) ) {
			return '';
		}
		$bg = $this->resolve_background( $s[ $side . '_background' ] ?? array() );
		if ( ! $bg ) {
			return '';
		}
		return Groups::layers_html( array( 'background' => $bg ) );
	}

	private function face_inline_style( array $s, $side, array $vars ) {
		$props = $vars;
		if ( class_exists( Groups::class ) ) {
			$bg = $this->resolve_background( $s[ $side . '_background' ] ?? array() );
			if ( $bg ) {
				foreach ( Groups::background_map( $bg ) as $p => $x ) {
					$props[ $p ] = $x;
				}
			}
			$pad = Groups::compile_dimensions( $s[ $side . '_padding' ] ?? array() );
			if ( $pad !== '' ) {
				$props['--lb-flip-padding'] = $pad;
			}
		}
		return $this->style_attr( $props );
	}

	private function resolve_background( $bg ) {
		if ( ! class_exists( Groups::class ) ) {
			return is_array( $bg ) ? $bg : array();
		}
		$bg = Groups::sanitize_background( is_array( $bg ) ? $bg : array() );
		if ( $bg['image_url'] === '' && ! empty( $bg['image_id'] ) && function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = wp_get_attachment_image_url( (int) $bg['image_id'], 'full' );
			if ( $url ) {
				$bg['image_url'] = $url;
			}
		}
		return $bg;
	}

	private function pick( $value, array $allowed, $fallback ) {
		$value = is_string( $value ) ? $value : (string) $value;
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private function duration( $v ) {
		$v = $this->scalar( $v, 0.8 );
		$n = is_numeric( $v ) ? (float) $v : 0.8;
		if ( $n <= 0 ) {
			$n = 0.8;
		}
		return $n . 's';
	}
}
