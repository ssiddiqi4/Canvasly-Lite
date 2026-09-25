<?php
namespace CanvaslyLite\API;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authorization wrapper for agent-facing layout APIs.
 *
 * When an AI agent posts a layout back through the plugin, every write and
 * render path must hold `edit_theme_options`. That blocks unauthorized
 * database writes and keeps untrusted markup off the render path.
 */
class Capabilities {
	const THEME_OPTIONS = 'edit_theme_options';

	/**
	 * Whether the current user may change theme-level layout options.
	 *
	 * @return bool
	 */
	public static function can_edit_theme_options() {
		return current_user_can( self::THEME_OPTIONS );
	}

	/**
	 * REST permission_callback: require edit_theme_options.
	 *
	 * @param mixed $request Unused; accepted so this can be registered directly.
	 * @return true|\WP_Error
	 */
	public static function rest_can_edit_theme_options( $request = null ) {
		unset( $request );
		if ( self::can_edit_theme_options() ) {
			return true;
		}
		return self::forbidden( __('You do not have permission to modify layouts.', 'canvasly-lite') );
	}

	/**
	 * REST permission_callback for persisting a post layout.
	 * Requires edit_theme_options and edit_post for the target id.
	 *
	 * @param mixed $request REST request or array with an `id` key.
	 * @return true|\WP_Error
	 */
	public static function rest_can_write_layout( $request ) {
		$gate = self::rest_can_edit_theme_options( $request );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$id = self::request_id( $request );
		if ( $id && class_exists( '\CanvaslyLite\Document\Documents' ) && ! \CanvaslyLite\Document\Documents::supports_post( $id ) ) {
			return self::forbidden( __('Canvasly is not enabled for this post type.', 'canvasly-lite') );
		}
		if ( $id && ! current_user_can( 'edit_post', $id ) ) {
			return self::forbidden( __('You cannot edit this layout document.', 'canvasly-lite') );
		}
		return true;
	}

	/**
	 * In-process guard for layout save and preview.
	 *
	 * @param int $post_id Post id, or 0 when no document is being written.
	 * @return true|\WP_Error
	 */
	public static function authorize_layout_mutation( $post_id = 0 ) {
		if ( ! self::can_edit_theme_options() ) {
			return self::forbidden( __('You do not have permission to modify layouts.', 'canvasly-lite') );
		}
		$post_id = absint( $post_id );
		if ( $post_id && class_exists( '\CanvaslyLite\Document\Documents' ) && ! \CanvaslyLite\Document\Documents::supports_post( $post_id ) ) {
			return self::forbidden( __('Canvasly is not enabled for this post type.', 'canvasly-lite') );
		}
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return self::forbidden( __('You cannot edit this layout document.', 'canvasly-lite') );
		}
		return true;
	}

	/**
	 * @param string $message
	 * @return \WP_Error
	 */
	public static function forbidden( $message ) {
		return new \WP_Error(
			'forbidden',
			sanitize_text_field( (string) $message ),
			array( 'status' => 403 )
		);
	}

	/**
	 * @param mixed $request
	 * @return int
	 */
	public static function request_id( $request ) {
		if ( is_array( $request ) ) {
			return absint( $request['id'] ?? 0 );
		}
		if ( is_object( $request ) ) {
			if ( isset( $request['id'] ) ) {
				return absint( $request['id'] );
			}
			if ( method_exists( $request, 'get_param' ) ) {
				return absint( $request->get_param( 'id' ) );
			}
		}
		return 0;
	}
}
