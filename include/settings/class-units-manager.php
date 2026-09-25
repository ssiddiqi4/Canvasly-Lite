<?php
namespace CanvaslyLite\Settings;

use CanvaslyLite\Design\CssPrint;
use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Units\UnitRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Units Manager (Roadmap 7.2).
 *
 * Globally disable types, restrict them per WordPress role, and show usage
 * counts from saved documents. Canvasly Pro widgets registered on
 * `canvasly-lite/units/register` appear in the same list. Disabled types
 * stay registered so existing nodes still render and sanitize.
 */
class UnitsManager {
	const OPTION    = 'canvasly_lite_units_manager';
	const TRANSIENT = 'canvasly_lite_unit_usage';
	const PAGE      = 'canvasly-lite-units';
	const NONCE     = 'lb_units_manager';
	const TTL       = 43200;
	const WALK      = 500;

	public static function init() {
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		add_action( 'canvasly-lite/document/after_save', array( self::class, 'invalidate_usage' ), 25, 0 );
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'admin_menu', array( self::class, 'menu' ), 11 );
			add_action( 'admin_init', array( self::class, 'maybe_save' ) );
			add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		}
	}

	/**
	 * Units Manager screen only.
	 *
	 * @param string $hook_suffix
	 */
	public static function enqueue( $hook_suffix = '' ) {
		if ( ! is_string( $hook_suffix ) || strpos( $hook_suffix, self::PAGE ) === false ) {
			return;
		}
		$ver = defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0';
		wp_enqueue_script(
			'canvasly-lite-units-manager',
			CANVASLY_LITE_URL . 'assets/js/units-manager.js',
			array(),
			$ver,
			true
		);
	}

	public static function menu() {
		add_submenu_page(
			'canvasly-lite',
			__( 'Units Manager', 'canvasly-lite' ),
			__( 'Units Manager', 'canvasly-lite' ),
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
	 * @return string[]
	 */
	public static function locked_types() {
		$locked = array( 'container' );
		/**
		 * Types that cannot be disabled (layout would break).
		 *
		 * @param string[] $locked
		 */
		$filtered = apply_filters( 'canvasly-lite/units/locked', $locked );
		$out      = array();
		foreach ( is_array( $filtered ) ? $filtered : $locked as $type ) {
			$type = sanitize_key( (string) $type );
			if ( $type !== '' ) {
				$out[] = $type;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	public static function is_locked( $type ) {
		return in_array( sanitize_key( (string) $type ), self::locked_types(), true );
	}

	/**
	 * @return array{disabled:string[],restricted:array<string,string[]>}
	 */
	public static function get() {
		$raw = get_option( self::OPTION, array() );
		$out = self::sanitize( is_array( $raw ) ? $raw : array() );
		/**
		 * Filter the Units Manager map.
		 *
		 * @param array $out
		 */
		$filtered = apply_filters( 'canvasly-lite/units/manager', $out );
		return is_array( $filtered ) ? self::sanitize( $filtered ) : $out;
	}

	/**
	 * @param mixed $raw
	 * @return array{disabled:string[],restricted:array<string,string[]>}
	 */
	public static function sanitize( $raw ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$known    = self::known_types();
		$disabled = array();
		foreach ( (array) ( $raw['disabled'] ?? array() ) as $type ) {
			$type = sanitize_key( (string) $type );
			if ( $type === '' || self::is_locked( $type ) ) {
				continue;
			}
			if ( $known && ! in_array( $type, $known, true ) ) {
				continue;
			}
			$disabled[] = $type;
		}
		$restricted = array();
		$roles      = class_exists( Roles::class ) ? array_keys( Roles::role_list() ) : array();
		foreach ( (array) ( $raw['restricted'] ?? array() ) as $role => $types ) {
			$role = sanitize_key( (string) $role );
			if ( $role === '' || $role === 'administrator' ) {
				continue;
			}
			if ( $roles && ! in_array( $role, $roles, true ) ) {
				continue;
			}
			$list = array();
			foreach ( (array) $types as $type ) {
				$type = sanitize_key( (string) $type );
				if ( $type === '' || self::is_locked( $type ) ) {
					continue;
				}
				if ( $known && ! in_array( $type, $known, true ) ) {
					continue;
				}
				$list[] = $type;
			}
			$list = array_values( array_unique( $list ) );
			if ( $list ) {
				$restricted[ $role ] = $list;
			}
		}
		return array(
			'disabled'   => array_values( array_unique( $disabled ) ),
			'restricted' => $restricted,
		);
	}

	/**
	 * @param array $data
	 * @return array|\WP_Error
	 */
	public static function save( $data ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'Only administrators can change the unit list.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$clean = self::sanitize( $data );
		update_option( self::OPTION, $clean, false );
		/**
		 * Fires after Units Manager settings are saved.
		 *
		 * @param array $clean
		 */
		do_action( 'canvasly-lite/units/after_save', $clean );
		return $clean;
	}

	/**
	 * Whether the current user may add this type from the panel.
	 *
	 * @param string     $type
	 * @param string[]|null $roles
	 * @return bool
	 */
	public static function is_allowed( $type, $roles = null ) {
		$type = sanitize_key( (string) $type );
		if ( $type === '' ) {
			return false;
		}
		if ( self::is_locked( $type ) ) {
			return true;
		}
		$d = self::get();
		if ( in_array( $type, $d['disabled'], true ) ) {
			return false;
		}
		$ok = true;
		$roles = is_array( $roles ) ? $roles : ( class_exists( Roles::class ) ? Roles::current_roles() : array() );
		$roles = array_values( array_unique( array_map( 'sanitize_key', $roles ) ) );
		if ( ! in_array( 'administrator', $roles, true ) ) {
			$shown  = false;
			$hidden = false;
			foreach ( $roles as $role ) {
				$deny = isset( $d['restricted'][ $role ] ) ? $d['restricted'][ $role ] : array();
				if ( in_array( $type, $deny, true ) ) {
					$hidden = true;
				} else {
					$shown = true;
				}
			}
			if ( $roles && $hidden && ! $shown ) {
				$ok = false;
			}
		}
		/**
		 * Filter whether a type is available in the unit panel.
		 *
		 * @param bool     $ok
		 * @param string   $type
		 * @param string[] $roles
		 */
		return (bool) apply_filters( 'canvasly-lite/units/allowed', $ok, $type, $roles );
	}

	/**
	 * Types the current user may add.
	 *
	 * @param string[]|null $roles
	 * @return string[]
	 */
	public static function allowed_types( $roles = null ) {
		$out = array();
		foreach ( array_keys( self::catalog() ) as $type ) {
			if ( self::is_allowed( $type, $roles ) ) {
				$out[] = $type;
			}
		}
		if ( ! $out ) {
			foreach ( self::known_types() as $type ) {
				if ( self::is_allowed( $type, $roles ) ) {
					$out[] = $type;
				}
			}
		}
		return $out;
	}

	/**
	 * Canvasly Pro is active, so its widgets can be toggled from this screen.
	 *
	 * @return bool
	 */
	public static function pro_active() {
		return defined( 'CANVASLY_PRO_VERSION' ) || class_exists( '\\CanvaslyPro\\Plugin', false );
	}

	/**
	 * Package slug for a widget instance or class name.
	 *
	 * @param object|string $unit
	 * @return string
	 */
	public static function source_slug( $unit ) {
		$slug  = '';
		$class = '';
		if ( is_object( $unit ) ) {
			$class = get_class( $unit );
			if ( method_exists( $unit, 'source' ) ) {
				$slug = sanitize_key( (string) $unit->source() );
			}
		} elseif ( is_string( $unit ) ) {
			$class = $unit;
		}
		if ( $slug === '' ) {
			$class = ltrim( $class, '\\' );
			$slug  = ( strpos( $class, 'CanvaslyPro\\' ) === 0 ) ? 'pro' : 'lite';
		}
		/**
		 * Filter the package slug shown in Units Manager.
		 *
		 * @param string        $slug
		 * @param object|string $unit
		 */
		$filtered = apply_filters( 'canvasly-lite/units/source', $slug, $unit );
		$filtered = sanitize_key( (string) $filtered );
		return $filtered !== '' ? $filtered : 'lite';
	}

	/**
	 * @param string $slug
	 * @return string
	 */
	public static function source_label( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( $slug === 'pro' ) {
			return __( 'Canvasly Pro', 'canvasly-lite' );
		}
		if ( $slug === 'lite' ) {
			return __( 'Canvasly Lite', 'canvasly-lite' );
		}
		return $slug;
	}

	/**
	 * @return array<string,array{type:string,title:string,category:string,locked:bool,source:string}>
	 */
	public static function catalog() {
		$items = array();
		if ( ! class_exists( UnitRegistry::class ) ) {
			return $items;
		}
		foreach ( UnitRegistry::instance()->all() as $el ) {
			if ( ! is_object( $el ) || ! method_exists( $el, 'type' ) ) {
				continue;
			}
			$type = sanitize_key( (string) $el->type() );
			if ( $type === '' ) {
				continue;
			}
			$items[ $type ] = array(
				'type'     => $type,
				'title'    => method_exists( $el, 'title' ) ? (string) $el->title() : $type,
				'category' => method_exists( $el, 'category' ) ? (string) $el->category() : '',
				'locked'   => self::is_locked( $type ),
				'source'   => self::source_slug( $el ),
			);
		}
		return $items;
	}

	/**
	 * Lite widgets first, then Pro, each group by title.
	 *
	 * @return array<string,array{type:string,title:string,category:string,locked:bool,source:string}>
	 */
	public static function screen_rows() {
		$rows = self::catalog();
		uasort(
			$rows,
			static function ( $a, $b ) {
				$sa = ( ( $a['source'] ?? '' ) === 'pro' ) ? 1 : 0;
				$sb = ( ( $b['source'] ?? '' ) === 'pro' ) ? 1 : 0;
				if ( $sa !== $sb ) {
					return $sa - $sb;
				}
				return strcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
			}
		);
		return $rows;
	}

	/**
	 * Types with no saved instances. Locked types are never unused.
	 *
	 * @param array<string,int> $usage
	 * @param array|null        $catalog
	 * @return string[]
	 */
	public static function unused_types( $usage, $catalog = null ) {
		$usage   = is_array( $usage ) ? $usage : array();
		$catalog = is_array( $catalog ) ? $catalog : self::catalog();
		$out     = array();
		foreach ( $catalog as $type => $item ) {
			$type = sanitize_key( (string) $type );
			if ( $type === '' ) {
				continue;
			}
			$locked = is_array( $item ) && ! empty( $item['locked'] );
			if ( $locked || self::is_locked( $type ) ) {
				continue;
			}
			if ( (int) ( $usage[ $type ] ?? 0 ) > 0 ) {
				continue;
			}
			$out[] = $type;
		}
		return $out;
	}

	/**
	 * Add unused types to the disabled list without dropping role restrictions.
	 *
	 * @param array             $map
	 * @param array<string,int> $usage
	 * @param array|null        $catalog
	 * @return array{disabled:string[],restricted:array<string,string[]>}
	 */
	public static function apply_unused( $map, $usage, $catalog = null ) {
		$map               = self::sanitize( is_array( $map ) ? $map : array() );
		$map['disabled']   = array_values( array_unique( array_merge( $map['disabled'], self::unused_types( $usage, $catalog ) ) ) );
		return self::sanitize( $map );
	}

	/**
	 * Recount usage, then turn off every widget that is not used anywhere.
	 *
	 * @return array|\WP_Error
	 */
	public static function disable_unused() {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'Only administrators can change the unit list.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		self::invalidate_usage();
		$usage   = self::recount();
		$catalog = self::catalog();
		if ( ! $catalog ) {
			return new \WP_Error( 'empty', __( 'No units are registered yet.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$current = self::get();
		return self::save( self::apply_unused( $current, $usage, $catalog ) );
	}

	/**
	 * @return string[]
	 */
	public static function known_types() {
		$types = array_keys( self::catalog() );
		return array_values( array_filter( $types ) );
	}

	/**
	 * @param array $nodes
	 * @param array<string,int> $counts
	 * @return array<string,int>
	 */
	public static function count_in_tree( $nodes, &$counts = array() ) {
		foreach ( (array) $nodes as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			$type = sanitize_key( (string) ( $n['type'] ?? '' ) );
			if ( $type !== '' ) {
				$counts[ $type ] = (int) ( $counts[ $type ] ?? 0 ) + 1;
			}
			if ( ! empty( $n['children'] ) && is_array( $n['children'] ) ) {
				self::count_in_tree( $n['children'], $counts );
			}
		}
		return $counts;
	}

	/**
	 * @return array<string,int>
	 */
	public static function usage() {
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$stored = get_option( self::TRANSIENT, array() );
		if ( is_array( $stored ) && isset( $stored['counts'] ) && is_array( $stored['counts'] ) ) {
			return $stored['counts'];
		}
		return self::recount();
	}

	/**
	 * Posts scanned for widget usage: documents, templates, components, and anything an add-on adds.
	 *
	 * @param int $limit
	 * @return int[]
	 */
	public static function usage_ids( $limit = 0 ) {
		$limit = $limit > 0 ? absint( $limit ) : self::WALK;
		$ids   = array();
		if ( class_exists( CssPrint::class ) && method_exists( CssPrint::class, 'document_ids' ) && class_exists( 'WP_Query' ) ) {
			$ids = CssPrint::document_ids( $limit );
		}
		if ( class_exists( 'WP_Query' ) ) {
			$q = new \WP_Query(
				array(
					'post_type'              => array( 'lb_component' ),
					'post_status'            => 'any',
					'posts_per_page'         => $limit,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'orderby'                => 'ID',
					'order'                  => 'DESC',
				)
			);
			foreach ( (array) $q->posts as $id ) {
				$id = absint( is_object( $id ) && isset( $id->ID ) ? $id->ID : $id );
				if ( $id ) {
					$ids[] = $id;
				}
			}
		}
		/**
		 * Document ids scanned for unit usage.
		 *
		 * @param int[] $ids
		 * @param int   $limit
		 */
		$filtered = apply_filters( 'canvasly-lite/units/usage_ids', $ids, $limit );
		$out      = array();
		foreach ( (array) ( is_array( $filtered ) ? $filtered : $ids ) as $id ) {
			$id = absint( $id );
			if ( $id ) {
				$out[ $id ] = $id;
			}
		}
		return array_values( $out );
	}

	public static function recount( $limit = 0 ) {
		$limit  = $limit > 0 ? absint( $limit ) : self::WALK;
		$counts = array();
		$ids    = self::usage_ids( $limit );
		$meta = class_exists( DocumentManager::class ) ? DocumentManager::META : '_lb_document_data';
		foreach ( (array) $ids as $id ) {
			$id = absint( $id );
			if ( ! $id || ! function_exists( 'get_post_meta' ) ) {
				continue;
			}
			$raw = get_post_meta( $id, $meta, true );
			$pt  = function_exists( 'get_post_type' ) ? (string) get_post_type( $id ) : '';
			if ( ! $raw && $pt === 'lb_template' ) {
				$raw = get_post_meta( $id, '_lb_template_data', true );
			}
			if ( ! $raw && $pt === 'lb_component' ) {
				$raw = get_post_meta( $id, '_lb_component_data', true );
			}
			$doc = class_exists( '\\CanvaslyLite\\Utils\\JsonCache' )
				? \CanvaslyLite\Utils\JsonCache::decode( $raw, array() )
				: ( is_array( $raw ) ? $raw : ( is_string( $raw ) ? json_decode( $raw, true ) : array() ) );
			$root = is_array( $doc['root'] ?? null ) ? $doc['root'] : array();
			self::count_in_tree( $root, $counts );
		}
		ksort( $counts );
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::TRANSIENT, $counts, self::TTL );
		}
		update_option( self::TRANSIENT, array( 'counts' => $counts, 'time' => time() ), false );
		return $counts;
	}

	public static function invalidate_usage() {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::TRANSIENT );
		}
		delete_option( self::TRANSIENT );
	}

	public static function maybe_save() {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::is_plugin_page() ) {
			return;
		}
		if ( ! empty( $_POST['lb_recount_units'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! self::can_manage() ) {
				return;
			}
			if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
				return;
			}
			self::invalidate_usage();
			self::recount();
			add_settings_error( 'canvasly_lite_units', 'recounted', __( 'Unit usage counts were rebuilt.', 'canvasly-lite' ), 'updated' );
			return;
		}
		if ( ! empty( $_POST['lb_disable_unused'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! self::can_manage() ) {
				return;
			}
			if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
				return;
			}
			$saved = self::disable_unused();
			if ( is_wp_error( $saved ) ) {
				add_settings_error( 'canvasly_lite_units', 'unused', $saved->get_error_message(), 'error' );
				return;
			}
			add_settings_error(
				'canvasly_lite_units',
				'unused',
				sprintf(
					/* translators: %d: number of disabled units */
					__( 'Unused units were turned off. %d units are now disabled.', 'canvasly-lite' ),
					count( $saved['disabled'] )
				),
				'updated'
			);
			return;
		}
		if ( empty( $_POST['lb_save_units'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! self::can_manage() ) {
			return;
		}
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
			return;
		}
		self::save( self::data_from_post( wp_unslash( $_POST ) ) );
		add_settings_error( 'canvasly_lite_units', 'saved', __( 'Units Manager saved.', 'canvasly-lite' ), 'updated' );
	}

	public static function screen() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can manage Canvasly units.', 'canvasly-lite' ) );
		}
		$d          = self::get();
		$catalog    = self::screen_rows();
		$usage      = self::usage();
		$role_list  = class_exists( Roles::class ) ? Roles::role_list() : array();
		$has_pro    = self::pro_active();
		foreach ( $catalog as $item ) {
			if ( ( $item['source'] ?? '' ) === 'pro' ) {
				$has_pro = true;
				break;
			}
		}
		$all_on = true;
		foreach ( $catalog as $type => $item ) {
			if ( ! empty( $item['locked'] ) ) {
				continue;
			}
			if ( in_array( $type, $d['disabled'], true ) ) {
				$all_on = false;
				break;
			}
		}
		$confirm = __( 'Turn off every unit that is not used on a saved page, template, or component? Required layout units stay on. Content that already uses an unit keeps rendering.', 'canvasly-lite' );
		echo '<div class="wrap lb-settings-wrap lb-units-manager">';
		echo '<h1>' . esc_html__( 'Units Manager', 'canvasly-lite' ) . '</h1>';
		settings_errors( 'canvasly_lite_units' );
		echo '<p class="description">' . esc_html__( 'Turn an unit on or off for the whole site. Off hides it from the editor panel. Restrict it per role to hide it only for that role. Existing instances on saved pages keep rendering.', 'canvasly-lite' ) . '</p>';
		if ( $has_pro ) {
			echo '<p class="description">' . esc_html__( 'Canvasly Pro is active. Its widgets are in this same list and use the same on/off switch.', 'canvasly-lite' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">';
		wp_nonce_field( self::NONCE );
		echo '<p class="lb-units-manager-actions">';
		echo '<button type="submit" class="button button-primary" name="lb_save_units" value="1">' . esc_html__( 'Save Changes', 'canvasly-lite' ) . '</button> ';
		echo '<button type="submit" class="button" name="lb_recount_units" value="1">' . esc_html__( 'Recount usage', 'canvasly-lite' ) . '</button> ';
		echo '<button type="submit" class="button" name="lb_disable_unused" id="lb-disable-unused" value="1" data-confirm="' . esc_attr( $confirm ) . '">' . esc_html__( 'Disable unused', 'canvasly-lite' ) . '</button>';
		echo '<label>' . esc_html__( 'Show', 'canvasly-lite' ) . ' <select id="lb-unit-filter">';
		echo '<option value="all">' . esc_html__( 'All units', 'canvasly-lite' ) . '</option>';
		echo '<option value="lite">' . esc_html__( 'Canvasly Lite', 'canvasly-lite' ) . '</option>';
		if ( $has_pro ) {
			echo '<option value="pro">' . esc_html__( 'Canvasly Pro', 'canvasly-lite' ) . '</option>';
		}
		echo '<option value="unused">' . esc_html__( 'Unused', 'canvasly-lite' ) . '</option>';
		echo '<option value="disabled">' . esc_html__( 'Turned off', 'canvasly-lite' ) . '</option>';
		echo '</select></label>';
		echo '<label class="screen-reader-text" for="lb-unit-search">' . esc_html__( 'Search units', 'canvasly-lite' ) . '</label>';
		echo '<input type="search" id="lb-unit-search" class="lb-unit-search" placeholder="' . esc_attr__( 'Search units', 'canvasly-lite' ) . '">';
		echo '</p>';
		echo '<table class="widefat striped lb-units-table"><thead><tr>';
		echo '<th><label><input type="checkbox" id="lb-units-toggle-visible"' . checked( $all_on, true, false ) . '> ' . esc_html__( 'Enabled', 'canvasly-lite' ) . '</label></th>';
		echo '<th>' . esc_html__( 'Unit', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Plugin', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Category', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Usage', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Hide from roles', 'canvasly-lite' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $catalog ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No units are registered yet.', 'canvasly-lite' ) . '</td></tr>';
		}
		$seen_source = '';
		foreach ( $catalog as $type => $item ) {
			$locked   = ! empty( $item['locked'] );
			$source   = sanitize_key( (string) ( $item['source'] ?? 'lite' ) );
			if ( $source !== $seen_source ) {
				$seen_source = $source;
				echo '<tr class="lb-unit-source-row" data-source="' . esc_attr( $source ) . '"><td colspan="6"><strong>' . esc_html( self::source_label( $source ) ) . '</strong></td></tr>';
			}
			$enabled  = $locked || ! in_array( $type, $d['disabled'], true );
			$count    = (int) ( $usage[ $type ] ?? 0 );
			$source   = sanitize_key( (string) ( $item['source'] ?? 'lite' ) );
			$search   = strtolower( (string) $item['title'] . ' ' . $type . ' ' . (string) $item['category'] );
			echo '<tr data-type="' . esc_attr( $type ) . '" data-source="' . esc_attr( $source ) . '" data-usage="' . esc_attr( (string) $count ) . '" data-enabled="' . ( $enabled ? '1' : '0' ) . '" data-locked="' . ( $locked ? '1' : '0' ) . '" data-search="' . esc_attr( $search ) . '">';
			echo '<td><label><input type="checkbox" name="lb_unit_enabled[]" value="' . esc_attr( $type ) . '"' . checked( $enabled, true, false ) . disabled( $locked, true, false ) . ' class="lb-unit-enabled" data-type="' . esc_attr( $type ) . '">';
			if ( $locked ) {
				echo ' <span class="description">' . esc_html__( 'Required', 'canvasly-lite' ) . '</span>';
			} else {
				echo '<input type="hidden" name="lb_unit_disabled_map[' . esc_attr( $type ) . ']" value="' . ( $enabled ? '0' : '1' ) . '" class="lb-unit-disabled-flag">';
			}
			echo '</label></td>';
			echo '<td><strong>' . esc_html( $item['title'] ) . '</strong> <code>' . esc_html( $type ) . '</code></td>';
			echo '<td><span class="lb-unit-source lb-unit-source-' . esc_attr( $source ) . '">' . esc_html( self::source_label( $source ) ) . '</span></td>';
			echo '<td>' . esc_html( $item['category'] ) . '</td>';
			echo '<td>' . esc_html( (string) $count ) . '</td>';
			echo '<td>';
			if ( $locked ) {
				echo '<span class="description">' . esc_html__( 'Always available.', 'canvasly-lite' ) . '</span>';
			} else {
				echo '<fieldset class="lb-unit-roles">';
				foreach ( $role_list as $slug => $_role ) {
					if ( $slug === 'administrator' ) {
						continue;
					}
					$hide    = isset( $d['restricted'][ $slug ] ) && in_array( $type, $d['restricted'][ $slug ], true );
					$label   = class_exists( Roles::class ) ? Roles::role_label( $slug ) : $slug;
					echo '<label><input type="checkbox" name="lb_unit_restricted[' . esc_attr( $slug ) . '][]" value="' . esc_attr( $type ) . '"' . ( $hide ? ' checked' : '' ) . '> ' . esc_html( $label ) . '</label> ';
				}
				echo '</fieldset>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary" name="lb_save_units" value="1">' . esc_html__( 'Save Changes', 'canvasly-lite' ) . '</button></p>';
		echo '</form></div>';
	}

	/**
	 * Convert enabled checkboxes + disabled flags into a disabled list.
	 *
	 * @param array $post
	 * @return string[]
	 */
	public static function disabled_from_post( $post ) {
		$post = is_array( $post ) ? $post : array();
		if ( isset( $post['lb_unit_disabled'] ) && is_array( $post['lb_unit_disabled'] ) ) {
			return $post['lb_unit_disabled'];
		}
		$map = isset( $post['lb_unit_disabled_map'] ) && is_array( $post['lb_unit_disabled_map'] ) ? $post['lb_unit_disabled_map'] : array();
		$out = array();
		foreach ( $map as $type => $flag ) {
			if ( (string) $flag === '1' ) {
				$out[] = $type;
			}
		}
		return $out;
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		register_rest_route(
			$namespace,
			'/units',
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
		register_rest_route(
			$namespace,
			'/units/usage',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_recount' ),
				'permission_callback' => array( self::class, 'rest_can_manage' ),
			)
		);
		register_rest_route(
			$namespace,
			'/units/disable-unused',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_disable_unused' ),
				'permission_callback' => array( self::class, 'rest_can_manage' ),
			)
		);
	}

	/**
	 * @param mixed $request
	 * @return true|\WP_Error
	 */
	public static function rest_can_manage( $request = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return self::can_manage() ? true : new \WP_Error( 'forbidden', __( 'Only administrators can manage units.', 'canvasly-lite' ), array( 'status' => 403 ) );
	}

	public static function rest_get() {
		$d = self::get();
		return rest_ensure_response(
			array(
				'items'      => array_values( self::catalog() ),
				'disabled'   => $d['disabled'],
				'restricted' => $d['restricted'],
				'usage'      => self::usage(),
				'locked'     => self::locked_types(),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $req
	 */
	public static function rest_save( $req ) {
		$d     = is_array( $req->get_json_params() ) ? $req->get_json_params() : array();
		$saved = self::save( $d );
		return is_wp_error( $saved ) ? $saved : rest_ensure_response( $saved );
	}

	public static function rest_recount() {
		self::invalidate_usage();
		return rest_ensure_response( array( 'usage' => self::recount() ) );
	}

	public static function rest_disable_unused() {
		$saved = self::disable_unused();
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return rest_ensure_response(
			array(
				'disabled'   => $saved['disabled'],
				'restricted' => $saved['restricted'],
				'usage'      => self::usage(),
			)
		);
	}

	/**
	 * Hook maybe_save through the disabled_map posted by the screen.
	 *
	 * @param array $post
	 * @return array
	 */
	public static function data_from_post( $post ) {
		$post = is_array( $post ) ? $post : array();
		return array(
			'disabled'   => self::disabled_from_post( $post ),
			'restricted' => isset( $post['lb_unit_restricted'] ) && is_array( $post['lb_unit_restricted'] ) ? $post['lb_unit_restricted'] : array(),
		);
	}
}
