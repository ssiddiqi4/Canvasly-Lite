<?php
namespace CanvaslyLite\Admin;

use CanvaslyLite\Document\Documents;
use CanvaslyLite\Settings\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-bar "Edit with Canvasly" node on the frontend and back end (Roadmap 7.6).
 *
 * Boots even on native Gutenberg screens, where the rest of the plugin does not.
 */
class AdminBar {
	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		if ( ! class_exists( Roles::class, false ) && defined( 'CANVASLY_LITE_PATH' ) ) {
			$file = CANVASLY_LITE_PATH . 'includes/settings/class-roles.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
		if ( class_exists( Roles::class ) && method_exists( Roles::class, 'maybe_sync' ) ) {
			Roles::maybe_sync();
		} elseif ( class_exists( Roles::class ) && method_exists( Roles::class, 'sync' ) ) {
			Roles::sync();
		}
		add_action( 'admin_bar_menu', array( self::class, 'menu' ), 81 );
	}

	public static function reset_for_tests() {
		self::$booted = false;
	}

	/**
	 * @param \WP_Admin_Bar $bar
	 */
	public static function menu( $bar ) {
		if ( ! is_object( $bar ) || ! method_exists( $bar, 'add_node' ) ) {
			return;
		}
		$node = self::node();
		if ( ! $node ) {
			return;
		}
		/**
		 * Filter the admin-bar node args before they are added.
		 *
		 * @param array $node
		 */
		$filtered = apply_filters( 'canvasly-lite/admin_bar/node', $node );
		if ( ! is_array( $filtered ) || empty( $filtered['id'] ) ) {
			return;
		}
		$bar->add_node( $filtered );
	}

	/**
	 * Node args, or null when the link should not appear.
	 *
	 * @return array|null
	 */
	public static function node() {
		$ctx = self::context();
		if ( ! $ctx ) {
			return null;
		}
		$label = __( 'Edit with Canvasly', 'canvasly-lite' );
		return array(
			'id'    => 'canvasly-lite-edit',
			'title' => $label,
			'href'  => $ctx['url'],
			'meta'  => array(
				'class' => 'lb-ab-edit',
				'title' => $label,
			),
		);
	}

	/**
	 * Current document the bar can open, or null.
	 *
	 * @return array{id:int,url:string,type:string}|null
	 */
	public static function context() {
		if ( ! self::can_see() ) {
			return null;
		}
		if ( self::is_builder_screen() ) {
			return null;
		}
		if ( self::is_skipped_request() ) {
			return null;
		}
		$post = self::current_post();
		if ( ! is_object( $post ) || empty( $post->ID ) || empty( $post->post_type ) ) {
			return null;
		}
		$id   = absint( $post->ID );
		$type = sanitize_key( (string) $post->post_type );
		if ( ! $id || ! self::supports_type( $type ) ) {
			return null;
		}
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'edit_post', $id ) ) {
			return null;
		}
		$ctx = array(
			'id'   => $id,
			'type' => $type,
			'url'  => self::editor_url( $id ),
		);
		/**
		 * Filter the admin-bar editor context. Return null to hide the node.
		 *
		 * @param array $ctx
		 * @param object $post
		 */
		$filtered = apply_filters( 'canvasly-lite/admin_bar/context', $ctx, $post );
		if ( ! is_array( $filtered ) || empty( $filtered['id'] ) || empty( $filtered['url'] ) ) {
			return null;
		}
		return $filtered;
	}

	/**
	 * @return bool
	 */
	public static function can_see() {
		if ( class_exists( Roles::class ) && method_exists( Roles::class, 'can_edit' ) ) {
			return Roles::can_edit();
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' );
	}

	/**
	 * @param string $post_type
	 * @return bool
	 */
	public static function supports_type( $post_type ) {
		$post_type = sanitize_key( (string) $post_type );
		if ( $post_type === '' ) {
			return false;
		}
		$ok = in_array( $post_type, array( 'lb_template', 'lb_component' ), true );
		if ( ! $ok && class_exists( Documents::class ) ) {
			$ok = Documents::supports( $post_type );
		} elseif ( ! $ok ) {
			$ok = in_array( $post_type, array( 'post', 'page' ), true );
		}
		/**
		 * Filter whether a post type gets an admin-bar edit node.
		 *
		 * @param bool   $ok
		 * @param string $post_type
		 */
		return (bool) apply_filters( 'canvasly-lite/admin_bar/supports', $ok, $post_type );
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	public static function editor_url( $post_id ) {
		$post_id = absint( $post_id );
		$url     = function_exists( 'admin_url' )
			? admin_url( 'admin.php?page=canvasly-lite&post_id=' . $post_id )
			: 'admin.php?page=canvasly-lite&post_id=' . $post_id;
		/**
		 * Filter the admin-bar editor URL.
		 *
		 * @param string $url
		 * @param int    $post_id
		 */
		$filtered = apply_filters( 'canvasly-lite/admin_bar/editor_url', $url, $post_id );
		return is_string( $filtered ) ? $filtered : $url;
	}

	/**
	 * @return object|null
	 */
	public static function current_post() {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			$id = 0;
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin post ID from the current screen URL.
			if ( isset( $_GET['post'] ) ) {
				$id = absint( wp_unslash( $_GET['post'] ) );
			} elseif ( isset( $_GET['post_id'] ) ) {
				$id = absint( wp_unslash( $_GET['post_id'] ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( $id && function_exists( 'get_post' ) ) {
				$post = get_post( $id );
				return is_object( $post ) ? $post : null;
			}
			return null;
		}
		if ( function_exists( 'is_singular' ) && ! is_singular() ) {
			return null;
		}
		if ( function_exists( 'get_queried_object' ) ) {
			$obj = get_queried_object();
			if ( is_object( $obj ) && ! empty( $obj->ID ) && ! empty( $obj->post_type ) ) {
				return $obj;
			}
		}
		if ( isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) && ! empty( $GLOBALS['post']->ID ) ) {
			return $GLOBALS['post'];
		}
		return null;
	}

	/**
	 * Already inside the visual builder.
	 *
	 * @return bool
	 */
	public static function is_builder_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $page === 'canvasly-lite' ) {
			return true;
		}
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && isset( $screen->id ) && $screen->id === 'toplevel_page_canvasly-lite' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public static function is_skipped_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		return false;
	}
}
