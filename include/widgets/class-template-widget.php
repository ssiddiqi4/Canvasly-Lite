<?php
namespace CanvaslyLite\Widgets;

use CanvaslyLite\Templates\SavedTemplates;
use CanvaslyLite\Templates\TemplateEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic WordPress sidebar widget that embeds a saved Canvasly template.
 */
class TemplateWidget extends \WP_Widget {
	public function __construct() {
		parent::__construct(
			'canvasly_lite_template',
			__( 'Canvasly Template', 'canvasly-lite' ),
			array(
				'description' => __( 'Display a saved Canvasly template.', 'canvasly-lite' ),
			)
		);
	}

	public static function register() {
		if ( class_exists( '\WP_Widget', false ) || class_exists( '\WP_Widget' ) ) {
			register_widget( __CLASS__ );
		}
	}

	public function widget( $args, $instance ) {
		$args     = is_array( $args ) ? $args : array();
		$instance = is_array( $instance ) ? $instance : array();
		$id       = absint( $instance['template_id'] ?? 0 );
		$html     = class_exists( TemplateEmbed::class ) ? TemplateEmbed::render( $id ) : '';
		if ( $html === '' ) {
			return;
		}
		echo isset( $args['before_widget'] ) ? $args['before_widget'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		if ( $title !== '' ) {
			$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );
			if ( $title !== '' ) {
				echo ( isset( $args['before_title'] ) ? $args['before_title'] : '' ) . esc_html( $title ) . ( isset( $args['after_title'] ) ? $args['after_title'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo isset( $args['after_widget'] ) ? $args['after_widget'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ) {
		$instance = is_array( $instance ) ? $instance : array();
		$title    = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		$current  = absint( $instance['template_id'] ?? 0 );
		$opts     = class_exists( SavedTemplates::class ) && method_exists( SavedTemplates::class, 'select_options' )
			? SavedTemplates::select_options()
			: array( '0' => __( 'Select a template', 'canvasly-lite' ) );
		echo '<p><label for="' . esc_attr( $this->get_field_id( 'title' ) ) . '">' . esc_html__( 'Title', 'canvasly-lite' ) . '</label>';
		echo '<input class="widefat" id="' . esc_attr( $this->get_field_id( 'title' ) ) . '" name="' . esc_attr( $this->get_field_name( 'title' ) ) . '" type="text" value="' . esc_attr( $title ) . '"></p>';
		echo '<p><label for="' . esc_attr( $this->get_field_id( 'template_id' ) ) . '">' . esc_html__( 'Saved Template', 'canvasly-lite' ) . '</label>';
		echo '<select class="widefat" id="' . esc_attr( $this->get_field_id( 'template_id' ) ) . '" name="' . esc_attr( $this->get_field_name( 'template_id' ) ) . '">';
		foreach ( $opts as $value => $label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( $current, absint( $value ), false ) . '>' . esc_html( (string) $label ) . '</option>';
		}
		echo '</select></p>';
	}

	public function update( $new_instance, $old_instance ) {
		$old = is_array( $old_instance ) ? $old_instance : array();
		$new = is_array( $new_instance ) ? $new_instance : array();
		return array(
			'title'       => sanitize_text_field( $new['title'] ?? ( $old['title'] ?? '' ) ),
			'template_id' => absint( $new['template_id'] ?? ( $old['template_id'] ?? 0 ) ),
		);
	}
}
