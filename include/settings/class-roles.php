<?php
namespace CanvaslyLite\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Role Manager (Roadmap 7.2).
 *
 * Maps each WordPress role to Canvasly access: none, content-only (Style/Advanced
 * hidden), or full. Grants `canvasly_lite_edit` / `canvasly_lite_design` so design-system
 * saves are not tied to `manage_options`.
 */
class Roles {
	const OPTION     = 'canvasly_lite_role_access';
	const PAGE       = 'canvasly-lite-roles';
	const NONCE      = 'lb_role_manager';
	const CAP_EDIT   = 'canvasly_lite_edit';
	const CAP_DESIGN = 'canvasly_lite_design';
	const NONE       = 'none';
	const CONTENT    = 'content';
	const FULL       = 'full';

	public static function init() {
		self::maybe_sync();
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'admin_menu', array( self::class, 'menu' ), 11 );
			add_action( 'admin_init', array( self::class, 'maybe_save' ) );
		}
	}

	public static function menu() {
		add_submenu_page(
			'canvasly-lite',
			__( 'Role Manager', 'canvasly-lite' ),
			__( 'Role Manager', 'canvasly-lite' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'screen' )
		);
	}

	/**
	 * @return bool
	 */
	public static function can_manage() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * Open the editor and save document content.
	 *
	 * @return bool
	 */
	public static function can_edit() {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAP_EDIT );
	}

	/**
	 * Style / Advanced tabs, design-system REST, site settings.
	 * Not tied to manage_options.
	 *
	 * @return bool
	 */
	public static function can_design() {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAP_DESIGN );
	}

	/**
	 * @return bool
	 */
	public static function is_content_only() {
		return self::can_edit() && ! self::can_design();
	}

	/**
	 * Highest access among the current user's roles.
	 *
	 * @return string none|content|full
	 */
	public static function current_access() {
		$roles = self::current_roles();
		if ( in_array( 'administrator', $roles, true ) ) {
			return self::FULL;
		}
		$rank = array(
			self::NONE    => 0,
			self::CONTENT => 1,
			self::FULL    => 2,
		);
		$best = self::NONE;
		$map  = self::all();
		foreach ( $roles as $slug ) {
			$level = isset( $map[ $slug ] ) ? $map[ $slug ] : self::NONE;
			if ( ( $rank[ $level ] ?? 0 ) > ( $rank[ $best ] ?? 0 ) ) {
				$best = $level;
			}
		}
		/**
		 * Filter the current user's Role Manager access.
		 *
		 * @param string   $best
		 * @param string[] $roles
		 */
		$filtered = apply_filters( 'canvasly-lite/roles/current_access', $best, $roles );
		return self::sanitize_access( $filtered );
	}

	/**
	 * @return string[]
	 */
	public static function current_roles() {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return array();
		}
		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $user->roles ) ) ) );
	}

	/**
	 * Stored + default access map for every editable role.
	 *
	 * @return array<string,string>
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();
		foreach ( self::role_list() as $slug => $role ) {
			if ( $slug === 'administrator' ) {
				$out[ $slug ] = self::FULL;
				continue;
			}
			if ( isset( $saved[ $slug ] ) ) {
				$out[ $slug ] = self::sanitize_access( $saved[ $slug ] );
			} else {
				$out[ $slug ] = self::default_access( $role );
			}
		}
		/**
		 * Filter the Role Manager access map.
		 *
		 * @param array<string,string> $out
		 */
		$filtered = apply_filters( 'canvasly-lite/roles/access', $out );
		return is_array( $filtered ) ? array_map( array( self::class, 'sanitize_access' ), $filtered ) : $out;
	}

	/**
	 * @param mixed $raw
	 * @return array<string,string>
	 */
	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( self::role_list() as $slug => $role ) {
			if ( $slug === 'administrator' ) {
				$out[ $slug ] = self::FULL;
				continue;
			}
			if ( isset( $raw[ $slug ] ) ) {
				$out[ $slug ] = self::sanitize_access( $raw[ $slug ] );
			} else {
				$out[ $slug ] = self::default_access( $role );
			}
		}
		return $out;
	}

	/**
	 * @param array $map
	 * @return array<string,string>
	 */
	public static function save( $map ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'Only administrators can change role access.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$clean = self::sanitize( $map );
		update_option( self::OPTION, $clean, false );
		self::apply_capabilities( $clean );
		self::mark_synced();
		/**
		 * Fires after Role Manager settings are saved.
		 *
		 * @param array<string,string> $clean
		 */
		do_action( 'canvasly-lite/roles/after_save', $clean );
		return $clean;
	}

	/**
	 * Grant or revoke plugin caps from the current access map.
	 */
	public static function sync() {
		self::apply_capabilities( self::all() );
		self::mark_synced();
	}

	/**
	 * Sync role caps at most once per plugin version (not on every page load).
	 */
	public static function maybe_sync() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$ver    = defined( 'CANVASLY_LITE_VERSION' ) ? (string) CANVASLY_LITE_VERSION : '';
		$stored = function_exists( 'get_option' ) ? (string) get_option( 'canvasly_lite_roles_synced', '' ) : '';
		if ( $ver !== '' && $stored === $ver ) {
			$done = true;
			return;
		}
		self::sync();
		$done = true;
	}

	private static function mark_synced() {
		$ver = defined( 'CANVASLY_LITE_VERSION' ) ? (string) CANVASLY_LITE_VERSION : '';
		if ( $ver !== '' && function_exists( 'update_option' ) ) {
			update_option( 'canvasly_lite_roles_synced', $ver, false );
		}
	}

	/**
	 * @param array<string,string> $map
	 */
	public static function apply_capabilities( $map ) {
		if ( ! function_exists( 'wp_roles' ) ) {
			return;
		}
		$wp_roles = wp_roles();
		if ( ! $wp_roles || ! is_object( $wp_roles ) ) {
			return;
		}
		$map = is_array( $map ) ? $map : array();
		foreach ( self::role_list() as $slug => $_role ) {
			$obj = method_exists( $wp_roles, 'get_role' ) ? $wp_roles->get_role( $slug ) : null;
			if ( ! $obj ) {
				continue;
			}
			$access      = ( $slug === 'administrator' ) ? self::FULL : self::sanitize_access( $map[ $slug ] ?? self::NONE );
			$want_edit   = $access !== self::NONE;
			$want_design = $access === self::FULL;
			self::set_cap( $obj, self::CAP_EDIT, $want_edit );
			self::set_cap( $obj, self::CAP_DESIGN, $want_design );
		}
	}

	/**
	 * @param mixed $role WP_Role
	 * @param string $cap
	 * @param bool   $grant
	 */
	private static function set_cap( $role, $cap, $grant ) {
		$has = method_exists( $role, 'has_cap' ) ? (bool) $role->has_cap( $cap ) : ! empty( $role->capabilities[ $cap ] );
		if ( $grant && ! $has && method_exists( $role, 'add_cap' ) ) {
			$role->add_cap( $cap );
		}
		if ( ! $grant && $has && method_exists( $role, 'remove_cap' ) ) {
			$role->remove_cap( $cap );
		}
	}

	/**
	 * @param mixed $role WP_Role|array
	 * @return string
	 */
	public static function default_access( $role ) {
		$caps = array();
		if ( is_object( $role ) && isset( $role->capabilities ) && is_array( $role->capabilities ) ) {
			$caps = $role->capabilities;
		} elseif ( is_array( $role ) ) {
			$caps = is_array( $role['capabilities'] ?? null ) ? $role['capabilities'] : $role;
		}
		if ( ! empty( $caps['edit_posts'] ) || ! empty( $caps['edit_pages'] ) ) {
			return self::FULL;
		}
		return self::NONE;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_access( $value ) {
		$v = sanitize_key( (string) $value );
		return in_array( $v, array( self::NONE, self::CONTENT, self::FULL ), true ) ? $v : self::NONE;
	}

	/**
	 * @return array<string,object|array>
	 */
	public static function role_list() {
		$roles = array();
		if ( function_exists( 'get_editable_roles' ) ) {
			$editable = get_editable_roles();
			if ( is_array( $editable ) ) {
				$roles = $editable;
			}
		}
		if ( ! $roles && function_exists( 'wp_roles' ) ) {
			$wp = wp_roles();
			if ( $wp && isset( $wp->roles ) && is_array( $wp->roles ) ) {
				$roles = $wp->roles;
			} elseif ( $wp && isset( $wp->role_objects ) && is_array( $wp->role_objects ) ) {
				foreach ( $wp->role_objects as $slug => $obj ) {
					$roles[ $slug ] = array(
						'name'         => isset( $obj->name ) ? $obj->name : $slug,
						'capabilities' => isset( $obj->capabilities ) ? $obj->capabilities : array(),
					);
				}
			}
		}
		$out = array();
		foreach ( $roles as $slug => $role ) {
			$slug = sanitize_key( (string) $slug );
			if ( $slug === '' ) {
				continue;
			}
			$out[ $slug ] = $role;
		}
		return $out;
	}

	/**
	 * @param string $slug
	 * @return string
	 */
	public static function role_label( $slug ) {
		$list = self::role_list();
		$role = $list[ $slug ] ?? null;
		if ( is_array( $role ) && ! empty( $role['name'] ) ) {
			$name = (string) $role['name'];
			return function_exists( 'translate_user_role' ) ? translate_user_role( $name ) : $name;
		}
		if ( is_object( $role ) && ! empty( $role->name ) ) {
			$name = (string) $role->name;
			return function_exists( 'translate_user_role' ) ? translate_user_role( $name ) : $name;
		}
		return $slug;
	}

	public static function maybe_save() {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::is_plugin_page() ) {
			return;
		}
		if ( empty( $_POST['lb_save_roles'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! self::can_manage() ) {
			return;
		}
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
			return;
		}
		$raw = array();
		if ( isset( $_POST['lb_role_access'] ) && is_array( $_POST['lb_role_access'] ) ) {
			$posted = map_deep( wp_unslash( $_POST['lb_role_access'] ), 'sanitize_key' );
			foreach ( (array) $posted as $role => $access ) {
				$raw[ sanitize_key( (string) $role ) ] = sanitize_key( (string) $access );
			}
		}
		self::save( $raw );
		add_settings_error( 'canvasly_lite_roles', 'saved', __( 'Role access saved.', 'canvasly-lite' ), 'updated' );
	}

	public static function screen() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can manage Canvasly roles.', 'canvasly-lite' ) );
		}
		$map = self::all();
		echo '<div class="wrap lb-settings-wrap lb-role-manager">';
		echo '<h1>' . esc_html__( 'Role Manager', 'canvasly-lite' ) . '</h1>';
		settings_errors( 'canvasly_lite_roles' );
		echo '<p class="description">' . esc_html__( 'Choose who can open Canvasly. Content only hides Style and Advanced tabs. Full access is not limited to administrators — it uses a dedicated design capability.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">';
		wp_nonce_field( self::NONCE );
		echo '<table class="widefat striped lb-roles-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Role', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'No Access', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Content Only', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Full Access', 'canvasly-lite' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( self::role_list() as $slug => $_role ) {
			$access = $map[ $slug ] ?? self::default_access( $_role );
			$locked = $slug === 'administrator';
			echo '<tr>';
			echo '<th scope="row">' . esc_html( self::role_label( $slug ) ) . ' <code>' . esc_html( $slug ) . '</code></th>';
			foreach ( array( self::NONE, self::CONTENT, self::FULL ) as $level ) {
				echo '<td><label><input type="radio" name="lb_role_access[' . esc_attr( $slug ) . ']" value="' . esc_attr( $level ) . '"' . checked( $access, $level, false ) . disabled( $locked, true, false ) . '></label></td>';
			}
			if ( $locked ) {
				echo '<input type="hidden" name="lb_role_access[' . esc_attr( $slug ) . ']" value="' . esc_attr( self::FULL ) . '">';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Content only: users can edit widget content and save the page. Style, Advanced, Site Settings, Classes and Variables stay hidden. No access hides the editor, row actions and REST routes.', 'canvasly-lite' ) . '</p>';
		echo '<p class="submit"><button type="submit" class="button button-primary" name="lb_save_roles" value="1">' . esc_html__( 'Save Changes', 'canvasly-lite' ) . '</button></p>';
		echo '</form></div>';
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		register_rest_route(
			$namespace,
			'/roles',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'rest_get' ),
					'permission_callback' => array( self::class, 'rest_can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest_save' ),
					'permission_callback' => array( self::class, 'rest_can_manage' ),
				),
			)
		);
	}

	/**
	 * @param mixed $request
	 * @return true|\WP_Error
	 */
	public static function rest_can_manage( $request = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return self::can_manage() ? true : new \WP_Error( 'forbidden', __( 'Only administrators can manage Canvasly roles.', 'canvasly-lite' ), array( 'status' => 403 ) );
	}

	public static function rest_get() {
		return rest_ensure_response(
			array(
				'access' => self::all(),
				'caps'   => array(
					'edit'   => self::CAP_EDIT,
					'design' => self::CAP_DESIGN,
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $req
	 */
	public static function rest_save( $req ) {
		$d      = is_array( $req->get_json_params() ) ? $req->get_json_params() : array();
		$access = is_array( $d['access'] ?? null ) ? $d['access'] : $d;
		$saved  = self::save( $access );
		return is_wp_error( $saved ) ? $saved : rest_ensure_response( array( 'access' => $saved ) );
	}

	/**
	 * Editor localize payload.
	 *
	 * @return array{edit:bool,design:bool,contentOnly:bool,access:string}
	 */
	public static function editor_caps() {
		return array(
			'edit'        => self::can_edit(),
			'design'      => self::can_design(),
			'contentOnly' => self::is_content_only(),
			'access'      => self::current_access(),
		);
	}
}
