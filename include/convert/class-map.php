<?php
namespace CanvaslyLite\Convert;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mapping table: stored third-party builder JSON → Canvasly types/settings.
 *
 * Add-ons extend this via `canvasly-lite/convert/widgets` and
 * `canvasly-lite/convert/common_settings`. Reads stored post meta only;
 * no third-party builder code is loaded.
 */
class Map {
	const LAYOUT_SECTION   = 'section';
	const LAYOUT_COLUMN    = 'column';
	const LAYOUT_CONTAINER = 'container';

	/**
	 * Responsive suffixes on stored control keys → Canvasly breakpoint names.
	 *
	 * @return array<string,string>
	 */
	public static function breakpoints() {
		$map = array(
			''               => 'desktop',
			'_tablet'        => 'tablet',
			'_mobile'        => 'mobile',
			'_mobile_extra'  => 'mobile_extra',
			'_tablet_extra'  => 'tablet_extra',
			'_laptop'        => 'laptop',
			'_widescreen'    => 'widescreen',
		);
		$filtered = apply_filters( 'canvasly-lite/convert/breakpoints', $map );
		return is_array( $filtered ) ? $filtered : $map;
	}

	/**
	 * Stored layout `elType` values that become containers (not widgets).
	 *
	 * @return string[]
	 */
	public static function layout_types() {
		return array( self::LAYOUT_SECTION, self::LAYOUT_COLUMN, self::LAYOUT_CONTAINER );
	}

	/**
	 * Page-template values stored on the source post → Canvasly templates.
	 *
	 * @return array<string,string>
	 */
	public static function page_templates() {
		return array(
			'elementor_canvas'         => 'canvas',
			'elementor_header_footer'  => 'full_width',
			'canvas'                   => 'canvas',
			'full_width'               => 'full_width',
			'default'                  => 'default',
			'theme'                    => 'default',
		);
	}

	/**
	 * Source library template types → Canvasly saved-template types.
	 *
	 * @return array<string,string>
	 */
	public static function library_types() {
		return array(
			'wp-page'    => 'page',
			'page'       => 'page',
			'section'    => 'section',
			'container'  => 'container',
			'header'     => 'header',
			'footer'     => 'footer',
			'single'     => 'single',
			'archive'    => 'archive',
			'loop-item'  => 'loop_item',
			'loop_item'  => 'loop_item',
			'widget'     => 'container',
		);
	}

	/**
	 * Shared advanced/style keys applied to every converted node.
	 *
	 * Dest values are a Canvasly setting key, or `[key, transformer]`.
	 *
	 * @return array<string,string|array>
	 */
	public static function common_settings() {
		$map = array(
			'_css_classes'   => 'css_class',
			'css_classes'    => 'css_class',
			'_unit_id'    => 'css_id',
			'css_id'         => 'css_id',
			'_z_index'       => 'z_index',
			'z_index'        => 'z_index',
			'_margin'        => array( 'margin', 'dimensions' ),
			'_padding'       => array( 'padding', 'dimensions' ),
			'margin'         => array( 'margin', 'dimensions' ),
			'padding'        => array( 'padding', 'dimensions' ),
			'_position'      => array( 'position', 'position' ),
			'position'       => array( 'position', 'position' ),
			'hide_desktop'   => array( 'hide_desktop', 'switch' ),
			'hide_laptop'    => array( 'hide_laptop', 'switch' ),
			'hide_tablet'    => array( 'hide_tablet', 'switch' ),
			'hide_tablet_extra' => array( 'hide_tablet_extra', 'switch' ),
			'hide_mobile'    => array( 'hide_mobile', 'switch' ),
			'hide_mobile_extra' => array( 'hide_mobile_extra', 'switch' ),
			'hide_widescreen'   => array( 'hide_widescreen', 'switch' ),
			'_animation'     => 'interaction',
			'animation'      => 'interaction',
			'_animation_delay' => array( 'interaction_delay', 'seconds' ),
			'animation_duration' => array( 'interaction_duration', 'seconds' ),
			'custom_css'     => 'custom_css',
			'html_tag'       => 'html_tag',
			'overflow'       => 'overflow',
			'opacity'        => array( 'opacity', 'slider' ),
		);
		$filtered = apply_filters( 'canvasly-lite/convert/common_settings', $map );
		return is_array( $filtered ) ? $filtered : $map;
	}

	/**
	 * Widget-type table: source `widgetType` → Canvasly type + setting map.
	 *
	 * @return array<string,array{type:string,settings?:array}>
	 */
	public static function widgets() {
		$heading_typo = array(
			'typography_font_family'     => 'font_family',
			'typography_font_size'       => array( 'size', 'slider' ),
			'typography_font_weight'     => 'weight',
			'typography_font_style'      => 'font_style',
			'typography_text_transform'  => 'text_transform',
			'typography_text_decoration' => 'text_decoration',
			'typography_line_height'     => array( 'line_height', 'slider' ),
			'typography_letter_spacing'  => array( 'letter_spacing', 'slider' ),
		);
		$text_typo    = array(
			'typography_font_family'     => 'font_family',
			'typography_font_size'       => array( 'font_size', 'slider' ),
			'typography_font_weight'     => 'weight',
			'typography_font_style'      => 'font_style',
			'typography_text_transform'  => 'text_transform',
			'typography_text_decoration' => 'text_decoration',
			'typography_line_height'     => array( 'line_height', 'slider' ),
			'typography_letter_spacing'  => array( 'letter_spacing', 'slider' ),
		);

		$map = array(
			'heading'         => array(
				'type'     => 'heading',
				'settings' => array_merge(
					array(
						'title'              => 'text',
						'header_size'        => 'tag',
						'align'              => 'align',
						'title_color'        => 'color',
						'title_color_hover'  => 'hover_color',
						'link'               => array( 'link', 'url' ),
						'size'               => 'size_preset',
						'blend_mode'         => 'mix_blend_mode',
					),
					$heading_typo
				),
			),
			'text-editor'     => array(
				'type'     => 'text',
				'settings' => array_merge(
					array(
						'editor'             => 'text',
						'align'              => 'align',
						'text_color'         => 'color',
						'drop_cap'           => array( 'drop_cap', 'switch' ),
						'columns'            => 'text_columns',
						'column_gap'         => array( 'column_gap', 'slider' ),
						'paragraph_spacing'  => array( 'paragraph_spacing', 'slider' ),
					),
					$text_typo
				),
			),
			'image'           => array(
				'type'     => 'image',
				'settings' => array(
					'image'            => array( 'image', 'media' ),
					'image_size'       => 'image_size',
					'align'            => 'alignment',
					'caption_source'   => array( 'caption_type', 'caption_source' ),
					'caption'          => 'caption',
					'link_to'          => 'link_to',
					'link'             => array( 'link', 'url' ),
					'open_lightbox'    => array( 'lightbox', 'lightbox' ),
					'width'            => array( 'width', 'slider' ),
					'height'           => array( 'height', 'slider' ),
					'object-fit'       => 'object_fit',
					'object_fit'       => 'object_fit',
					'object-position'  => 'object_position',
					'opacity'          => array( 'opacity', 'slider' ),
					'hover_animation'  => 'hover_animation',
				),
			),
			'button'          => array(
				'type'     => 'button',
				'settings' => array(
					'text'                 => 'text',
					'link'                 => array( 'url', 'url' ),
					'size'                 => array( 'size', 'button_size' ),
					'align'                => 'align',
					'button_text_color'    => 'text_color',
					'background_color'     => 'background',
					'hover_color'          => 'hover_text_color',
					'button_background_hover_color' => 'hover_background',
					'border_color'         => 'border_color',
					'hover_border_color'   => 'hover_border_color',
					'border_width'         => array( 'border_width', 'slider' ),
					'border_radius'        => array( 'radius', 'slider' ),
					'selected_icon'        => array( 'icon', 'icon' ),
					'icon_align'           => array( 'icon_placement', 'icon_align' ),
					'icon_indent'          => array( 'icon_spacing', 'slider' ),
					'button_css_id'        => 'button_id',
					'button_type'           => 'button_type',
					'hover_animation'      => 'hover_animation',
				),
			),
			'divider'         => array(
				'type'     => 'divider',
				'settings' => array(
					'style'     => 'style',
					'weight'    => array( 'thickness', 'slider_number' ),
					'color'     => 'color',
					'width'     => array( 'divider_width', 'slider' ),
					'align'     => 'align',
					'gap'       => array( 'divider_gap', 'slider_number' ),
					'look'      => array( 'look', 'divider_look' ),
					'text'      => 'text',
					'icon'      => array( 'icon', 'icon' ),
				),
			),
			'spacer'          => array(
				'type'     => 'spacer',
				'settings' => array(
					'space'  => array( 'height', 'slider_number' ),
					'height' => array( 'height', 'slider_number' ),
				),
			),
			'icon'            => array(
				'type'     => 'icon',
				'settings' => array(
					'selected_icon'    => array( 'icon', 'icon' ),
					'view'             => 'icon_view',
					'shape'            => 'shape',
					'align'            => 'align',
					'size'             => array( 'size', 'slider' ),
					'primary_color'    => 'color',
					'secondary_color'  => 'secondary_color',
					'link'             => array( 'link', 'url' ),
					'rotate'           => array( 'rotate', 'slider' ),
					'hover_animation'  => 'hover_animation',
				),
			),
			'icon-box'        => array(
				'type'     => 'icon_box',
				'settings' => array(
					'selected_icon'    => array( 'icon', 'icon' ),
					'view'             => 'icon_view',
					'shape'            => 'shape',
					'title_text'       => 'title',
					'title_size'       => 'title_tag',
					'description_text' => 'text',
					'link'             => array( 'link', 'url' ),
					'position'         => array( 'box_layout', 'box_position' ),
					'title_color'      => 'title_color',
					'description_color'=> 'text_color',
					'primary_color'    => 'icon_color',
					'secondary_color'  => 'secondary_color',
					'hover_primary_color' => 'hover_color',
					'icon_space'       => array( 'icon_space', 'slider_number' ),
					'title_bottom_space' => array( 'title_space', 'slider_number' ),
				),
			),
			'image-box'       => array(
				'type'     => 'image_box',
				'settings' => array(
					'image'            => array( 'image', 'media' ),
					'image_size'       => 'image_size',
					'title_text'       => 'title',
					'title_size'       => 'title_tag',
					'description_text' => 'text',
					'link'             => array( 'link', 'url' ),
					'position'         => array( 'box_layout', 'box_position' ),
					'title_color'      => 'title_color',
					'description_color'=> 'text_color',
					'image_space'      => array( 'image_space', 'slider_number' ),
				),
			),
			'icon-list'       => array(
				'type'     => 'icon_list',
				'settings' => array(
					'view'         => array( 'list_layout', 'list_view' ),
					'space_between'=> array( 'space_between', 'slider_number' ),
					'icon_color'   => 'icon_color',
					'text_color'   => 'text_color',
					'icon_size'    => array( 'icon_size', 'slider_number' ),
					'text_indent'  => array( 'text_indent', 'slider_number' ),
					'divider'      => array( 'divider', 'switch' ),
				),
			),
			'video'           => array(
				'type'     => 'video',
				'settings' => array(
					'video_type'       => 'source',
					'youtube_url'      => 'url',
					'vimeo_url'        => 'url',
					'dailymotion_url'  => 'url',
					'insert_url'       => 'url',
					'hosted_url'       => array( 'url', 'media_url' ),
					'start'            => array( 'start', 'slider_number' ),
					'end'              => array( 'end', 'slider_number' ),
					'autoplay'         => array( 'autoplay', 'switch' ),
					'mute'             => array( 'mute', 'switch' ),
					'loop'             => array( 'loop', 'switch' ),
					'controls'         => array( 'controls', 'switch' ),
					'privacy_mode'     => array( 'privacy_mode', 'switch' ),
					'image_overlay'    => array( 'overlay_image', 'media' ),
					'show_image_overlay' => array( 'show_overlay', 'switch' ),
					'lightbox'         => array( 'lightbox', 'switch' ),
					'aspect_ratio'     => 'aspect_ratio',
				),
			),
			'html'            => array(
				'type'     => 'html',
				'settings' => array( 'html' => 'html' ),
			),
			'oembed'          => array(
				'type'     => 'embed',
				'settings' => array(
					'url'          => 'url',
					'aspect_ratio' => 'aspect_ratio',
				),
			),
			'embed'           => array(
				'type'     => 'embed',
				'settings' => array(
					'url'          => 'url',
					'aspect_ratio' => 'aspect_ratio',
				),
			),
			'shortcode'       => array(
				'type'     => 'shortcode',
				'settings' => array( 'shortcode' => 'shortcode' ),
			),
			'sidebar'         => array(
				'type'     => 'sidebar',
				'settings' => array( 'sidebar' => 'sidebar' ),
			),
			'alert'           => array(
				'type'     => 'alert',
				'settings' => array(
					'alert_title'       => 'title',
					'alert_description' => 'text',
					'alert_type'        => 'type',
					'show_dismiss'      => array( 'dismissible', 'switch' ),
				),
			),
			'progress'        => array(
				'type'     => 'progress',
				'settings' => array(
					'title'            => 'label',
					'percent'          => array( 'value', 'slider_number' ),
					'display_percentage' => array( 'show_percentage', 'switch' ),
					'inner_text'       => 'inner_text',
					'bar_color'        => 'color',
					'bar_bg_color'     => 'background',
					'bar_height'       => array( 'bar_height', 'slider_number' ),
				),
			),
			'counter'         => array(
				'type'     => 'counter',
				'settings' => array(
					'starting_number'     => array( 'start', 'slider_number' ),
					'ending_number'       => array( 'number', 'slider_number' ),
					'prefix'              => 'prefix',
					'suffix'              => 'suffix',
					'duration'            => array( 'duration', 'slider_number' ),
					'title'               => 'title',
					'thousand_separator'  => array( 'thousand_separator', 'switch' ),
				),
			),
			'tabs'            => array(
				'type'     => 'tabs',
				'settings' => array(
					'type'  => array( 'orientation', 'tabs_type' ),
				),
			),
			'accordion'       => array(
				'type'     => 'accordion',
				'settings' => array(
					'icon'           => array( 'icon', 'icon' ),
					'selected_icon'  => array( 'icon', 'icon' ),
					'icon_align'     => array( 'icon_position', 'icon_align_lr' ),
					'faq_schema'     => array( 'faq_schema', 'switch' ),
				),
			),
			'toggle'          => array(
				'type'     => 'toggle',
				'settings' => array(
					'icon'          => array( 'icon', 'icon' ),
					'selected_icon' => array( 'icon', 'icon' ),
					'icon_align'    => array( 'icon_position', 'icon_align_lr' ),
				),
			),
			'nested-tabs'     => array(
				'type'     => 'nested_tabs',
				'settings' => array(
					'type' => array( 'orientation', 'tabs_type' ),
				),
			),
			'nested-accordion'=> array(
				'type'     => 'nested_accordion',
				'settings' => array(),
			),
			'nested-toggle'   => array(
				'type'     => 'nested_toggle',
				'settings' => array(),
			),
			'social-icons'    => array(
				'type'     => 'social',
				'settings' => array(
					'shape'     => 'shape',
					'align'     => 'align',
					'icon_size' => array( 'size', 'slider_number' ),
					'gap'       => array( 'gap', 'slider_number' ),
				),
			),
			'image-gallery'   => array(
				'type'     => 'gallery',
				'settings' => array(
					'gallery'          => array( 'ids', 'gallery' ),
					'gallery_columns'  => array( 'columns', 'slider_number' ),
					'gallery_rand'     => array( 'order_by', 'gallery_order' ),
					'gallery_link'     => 'link',
					'thumbnail_size'   => 'size',
					'open_lightbox'    => array( 'lightbox', 'lightbox' ),
				),
			),
			'image-carousel'  => array(
				'type'     => 'carousel',
				'settings' => array(
					'carousel'         => array( 'slides', 'carousel' ),
					'slides_to_show'   => array( 'slides_to_show', 'slider_number' ),
					'slides_to_scroll' => array( 'slides_to_scroll', 'slider_number' ),
					'navigation'       => 'navigation',
					'autoplay'         => array( 'autoplay', 'switch' ),
					'pause_on_hover'   => array( 'pause_on_hover', 'switch' ),
					'autoplay_speed'   => array( 'interval', 'slider_number' ),
					'infinite'         => array( 'loop', 'switch' ),
					'effect'           => 'effect',
					'speed'            => array( 'speed', 'slider_number' ),
					'image_stretch'    => array( 'image_stretch', 'switch' ),
					'link_to'          => 'link',
				),
			),
			'testimonial'     => array(
				'type'     => 'testimonial',
				'settings' => array(),
			),
			'star-rating'     => array(
				'type'     => 'star_rating',
				'settings' => array(
					'rating_scale'     => 'scale',
					'rating'           => array( 'rating', 'slider_number' ),
					'title'            => 'title',
					'align'            => 'align',
					'star_style'       => array( 'unmarked_style', 'star_unmarked' ),
					'star_color'       => 'color',
					'star_unmarked_color' => 'unmarked_color',
					'size'             => array( 'size', 'slider_number' ),
				),
			),
			'rating'          => array(
				'type'     => 'rating',
				'settings' => array(
					'rating' => array( 'rating', 'slider_number' ),
					'scale'  => array( 'max', 'slider_number' ),
					'title'  => 'label',
				),
			),
			'menu-anchor'     => array(
				'type'     => 'menu_anchor',
				'settings' => array( 'anchor' => 'anchor' ),
			),
			'read-more'       => array(
				'type'     => 'read_more',
				'settings' => array(
					'link' => array( 'url', 'url' ),
					'text' => 'text',
				),
			),
			'audio'           => array(
				'type'     => 'audio',
				'settings' => array(
					'src'      => array( 'url', 'media_url' ),
					'autoplay' => array( 'autoplay', 'switch' ),
					'loop'     => array( 'loop', 'switch' ),
					'preload'  => 'preload',
				),
			),
			'soundcloud'      => array(
				'type'     => 'soundcloud',
				'settings' => array(
					'embed'      => 'url',
					'visual'     => array( 'visual', 'switch' ),
					'auto_play'  => array( 'auto_play', 'switch' ),
				),
			),
			'google_maps'     => array(
				'type'     => 'google_maps',
				'settings' => array(
					'address' => 'address',
					'zoom'    => array( 'zoom', 'slider_number' ),
					'height'  => array( 'height', 'slider_number' ),
				),
			),
			'login'           => array(
				'type'     => 'login',
				'settings' => array(
					'button_text'          => 'button',
					'redirect_after_login' => array( 'redirect', 'url' ),
				),
			),
			'form'            => array(
				'type'     => 'form',
				'settings' => array(
					'form_name'            => 'title',
					'button_text'          => 'submit',
					'success_message'      => 'success',
					'email_to'             => 'email',
					'form_fields'          => array( 'fields', 'form_fields' ),
				),
			),
			'price-table'     => array(
				'type'     => 'price_table',
				'settings' => array(
					'heading'      => 'title',
					'price'        => 'price',
					'period'       => 'period',
					'button_text'  => 'button',
					'link'         => array( 'url', 'url' ),
					'features_list'=> array( 'features', 'price_features' ),
				),
			),
			'flip-box'        => array(
				'type'     => 'flip_box',
				'settings' => array(
					'title_text_a'         => 'front_title',
					'description_text_a'   => 'front_text',
					'selected_icon_a'      => array( 'front_icon', 'icon' ),
					'title_text_b'         => 'back_title',
					'description_text_b'   => 'back_text',
					'button_text'          => 'back_button_text',
					'link'                 => array( 'back_button_url', 'url' ),
					'flip_effect'          => 'flip_effect',
					'flip_direction'       => 'flip_direction',
					'height'               => array( 'box_height', 'slider_number' ),
				),
			),
			'text-path'       => array(
				'type'     => 'text_path',
				'settings' => array(
					'text'  => 'text',
					'start_point' => array( 'speed', 'slider_number' ),
				),
			),
			'template'        => array(
				'type'     => 'template',
				'settings' => array(
					'template_id' => array( 'template_id', 'int' ),
				),
			),
			'loop-grid'       => array(
				'type'     => 'collection_loop',
				'settings' => array(
					'posts_per_page' => array( 'posts_per_page', 'slider_number' ),
					'columns'        => array( 'columns', 'slider_number' ),
					'pagination_type'=> array( 'pagination', 'loop_pagination' ),
				),
			),
			'posts'           => array(
				'type'     => 'collection_loop',
				'settings' => array(
					'posts_per_page' => array( 'posts_per_page', 'slider_number' ),
					'columns'        => array( 'columns', 'slider_number' ),
				),
			),
			'code-highlight'  => array(
				'type'     => 'code',
				'settings' => array(
					'code'     => 'code',
					'language' => 'language',
				),
			),
			'html5'           => array(
				'type'     => 'html',
				'settings' => array( 'html' => 'html' ),
			),
			'wp-widget-generic' => array(
				'type'     => 'wordpress',
				'settings' => array(
					'wp' => array( 'widget_options', 'json' ),
				),
			),
		);

		$aliases = array(
			'text_editor'       => 'text-editor',
			'icon_box'          => 'icon-box',
			'image_box'         => 'image-box',
			'icon_list'         => 'icon-list',
			'social_icons'      => 'social-icons',
			'image_gallery'     => 'image-gallery',
			'image_carousel'    => 'image-carousel',
			'star_rating'       => 'star-rating',
			'menu_anchor'       => 'menu-anchor',
			'read_more'         => 'read-more',
			'google-maps'       => 'google_maps',
			'price_table'       => 'price-table',
			'flip_box'          => 'flip-box',
			'text_path'         => 'text-path',
			'loop_grid'         => 'loop-grid',
			'nested_tabs'       => 'nested-tabs',
			'nested_accordion'  => 'nested-accordion',
			'nested_toggle'     => 'nested-toggle',
			'n-tabs'            => 'nested-tabs',
			'n-accordion'       => 'nested-accordion',
			'inner-section'     => self::LAYOUT_SECTION,
		);
		foreach ( $aliases as $alias => $canon ) {
			if ( isset( $map[ $canon ] ) && ! isset( $map[ $alias ] ) ) {
				$map[ $alias ] = $map[ $canon ];
			}
		}

		$filtered = apply_filters( 'canvasly-lite/convert/widgets', $map );
		return is_array( $filtered ) ? $filtered : $map;
	}

	/**
	 * @param string $widget_type Source widgetType.
	 * @return array{type:string,settings?:array}|null
	 */
	public static function widget( $widget_type ) {
		$type = self::normalize_type( $widget_type );
		$all  = self::widgets();
		if ( isset( $all[ $type ] ) ) {
			return $all[ $type ];
		}
		if ( isset( $all[ $widget_type ] ) ) {
			return $all[ $widget_type ];
		}
		return null;
	}

	/**
	 * @param string $type
	 * @return string
	 */
	public static function normalize_type( $type ) {
		return strtolower( str_replace( '_', '-', (string) $type ) );
	}

	/**
	 * Known system color ids shared by both builders.
	 *
	 * @return string[]
	 */
	public static function system_color_ids() {
		return array( 'primary', 'secondary', 'text', 'accent' );
	}
}
