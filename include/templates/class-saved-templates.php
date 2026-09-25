<?php
namespace CanvaslyLite\Templates;

use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Rendering\FrontendRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saved Templates CPT, types, categories and admin list table (Roadmap 5.1).
 */
class SavedTemplates {
	const POST_TYPE = 'lb_template';
	const TAXONOMY  = 'lb_template_category';
	const META_DATA = '_lb_template_data';
	const META_TYPE = '_lb_template_type';
	const META_KEY  = '_lb_template_key';
	const NOTICE    = 'canvasly_lite_template_notice';

	public static function init() {
		add_shortcode( 'canvasly_lite_template', array( self::class, 'shortcode' ) );
		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( self::class, 'menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'admin_assets' ), 10, 1 );
		add_action( 'add_meta_boxes', array( self::class, 'metaboxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( self::class, 'save_metabox' ), 10, 2 );
		add_filter( 'use_block_editor_for_post_type', array( self::class, 'disable_block_editor' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( self::class, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'filters' ) );
		add_action( 'pre_get_posts', array( self::class, 'filter_query' ) );
		add_filter( 'post_row_actions', array( self::class, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . self::POST_TYPE, array( self::class, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . self::POST_TYPE, array( self::class, 'handle_bulk' ), 10, 3 );
		add_action( 'admin_post_lb_template_export', array( self::class, 'handle_export' ) );
		add_action( 'admin_post_lb_template_import', array( self::class, 'handle_import' ) );
		add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		add_action( 'load-post-new.php', array( self::class, 'redirect_new' ) );
		add_filter( 'display_post_states', array( self::class, 'post_states' ), 10, 2 );
	}

	public static function register() {
		$labels = array(
			'name'               => __( 'Saved Templates', 'canvasly-lite' ),
			'singular_name'      => __( 'Template', 'canvasly-lite' ),
			'add_new'            => __( 'Add Template', 'canvasly-lite' ),
			'add_new_item'       => __( 'Add Template', 'canvasly-lite' ),
			'edit_item'          => __( 'Edit Template', 'canvasly-lite' ),
			'new_item'           => __( 'New Template', 'canvasly-lite' ),
			'view_item'          => __( 'View Template', 'canvasly-lite' ),
			'search_items'       => __( 'Search Templates', 'canvasly-lite' ),
			'not_found'          => __( 'No templates found.', 'canvasly-lite' ),
			'not_found_in_trash' => __( 'No templates found in Trash.', 'canvasly-lite' ),
			'all_items'          => __( 'Saved Templates', 'canvasly-lite' ),
			'menu_name'          => __( 'Saved Templates', 'canvasly-lite' ),
		);
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'canvasly-lite',
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'thumbnail', 'author' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);

		$tax_labels = array(
			'name'          => __( 'Template Categories', 'canvasly-lite' ),
			'singular_name' => __( 'Template Category', 'canvasly-lite' ),
			'search_items'  => __( 'Search Categories', 'canvasly-lite' ),
			'all_items'     => __( 'All Categories', 'canvasly-lite' ),
			'edit_item'     => __( 'Edit Category', 'canvasly-lite' ),
			'update_item'   => __( 'Update Category', 'canvasly-lite' ),
			'add_new_item'  => __( 'Add Category', 'canvasly-lite' ),
			'new_item_name' => __( 'New Category Name', 'canvasly-lite' ),
			'menu_name'     => __( 'Categories', 'canvasly-lite' ),
		);
		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => $tax_labels,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'hierarchical'      => true,
				'rewrite'           => false,
				'query_var'         => true,
			)
		);
	}

	/**
	 * Template types (page / theme / library). Filterable.
	 *
	 * @return array<string,string> slug => label
	 */
	public static function types() {
		$types = array(
			'page'            => __( 'Page', 'canvasly-lite' ),
			'section'         => __( 'Section', 'canvasly-lite' ),
			'container'       => __( 'Container', 'canvasly-lite' ),
			'header'          => __( 'Header', 'canvasly-lite' ),
			'footer'          => __( 'Footer', 'canvasly-lite' ),
			'single'          => __( 'Single', 'canvasly-lite' ),
			'archive'         => __( 'Archive', 'canvasly-lite' ),
			'loop_item'       => __( 'Loop Item', 'canvasly-lite' ),
			'floating_button' => __( 'Floating Button', 'canvasly-lite' ),
		);
		/**
		 * Filter the saved-template type map.
		 *
		 * @param array<string,string> $types
		 */
		$filtered = apply_filters( 'canvasly-lite/templates/types', $types );
		return is_array( $filtered ) ? $filtered : $types;
	}

	/**
	 * @param mixed $type
	 * @return string
	 */
	public static function normalize_type( $type ) {
		$type = sanitize_key( (string) $type );
		if ( $type === 'block' ) {
			$type = 'container';
		}
		$types = self::types();
		if ( isset( $types[ $type ] ) ) {
			return $type;
		}
		return 'page';
	}

	/**
	 * @param string $type
	 * @return string
	 */
	public static function type_label( $type ) {
		$type   = self::normalize_type( $type );
		$types  = self::types();
		return $types[ $type ] ?? $type;
	}

	public static function is_template( $post ) {
		$id = 0;
		if ( is_numeric( $post ) ) {
			$id = absint( $post );
		} elseif ( is_object( $post ) && ! empty( $post->ID ) ) {
			$id = absint( $post->ID );
		}
		if ( ! $id || ! function_exists( 'get_post_type' ) ) {
			return false;
		}
		return get_post_type( $id ) === self::POST_TYPE;
	}

	public static function can_manage() {
		return current_user_can( 'edit_pages' );
	}

	public static function editor_url( $id ) {
		return admin_url( 'admin.php?page=canvasly-lite&post_id=' . absint( $id ) );
	}

	public static function shortcode_for( $id ) {
		return '[canvasly_lite_template id="' . absint( $id ) . '"]';
	}

	/**
	 * Options for template pickers (unit, block, WP widget).
	 *
	 * @param bool $include_empty
	 * @return array<string,string>
	 */
	public static function select_options( $include_empty = true ) {
		$opts = $include_empty ? array( '0' => __( 'Select a template', 'canvasly-lite' ) ) : array();
		foreach ( self::query( array( 'per_page' => 200, 'light' => true ) ) as $item ) {
			$id = absint( $item['id'] ?? 0 );
			if ( ! $id ) {
				continue;
			}
			$label = (string) ( $item['title'] ?? '' );
			if ( $label === '' ) {
				$label = '#' . $id;
			}
			if ( ! empty( $item['type_label'] ) ) {
				$label .= ' (' . $item['type_label'] . ')';
			}
			$opts[ (string) $id ] = $label;
		}
		return $opts;
	}

	/**
	 * @param int $id
	 * @return array
	 */
	public static function get_document( $id ) {
		$id = absint( $id );
		if ( ! $id || ! self::is_template( $id ) ) {
			return class_exists( DocumentManager::class ) ? DocumentManager::empty() : array( 'version' => '1.0', 'root' => array() );
		}
		if ( class_exists( DocumentManager::class ) ) {
			return DocumentManager::get( $id );
		}
		$raw = get_post_meta( $id, self::META_DATA, true );
		$d   = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		return is_array( $d ) ? $d : array( 'version' => '1.0', 'root' => array() );
	}

	/**
	 * @param int   $id
	 * @param array $doc
	 * @return array|\WP_Error
	 */
	public static function save_document( $id, $doc ) {
		$id = absint( $id );
		if ( ! $id || ! self::is_template( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Template not found', 'canvasly-lite' ), array( 'status' => 404 ) );
		}
		$doc = is_array( $doc ) ? $doc : array();
		if ( class_exists( DocumentManager::class ) ) {
			$clean = DocumentManager::sanitize( $doc );
			if ( method_exists( DocumentManager::class, 'save' ) && current_user_can( 'edit_post', $id ) ) {
				$saved = DocumentManager::save( $id, $doc );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				$clean = is_array( $saved ) ? $saved : $clean;
			}
		} else {
			$clean = $doc;
		}
		update_post_meta( $id, self::META_DATA, wp_json_encode( $clean ) );
		return $clean;
	}

	/**
	 * @param array $args
	 * @return int|\WP_Error
	 */
	public static function create( $args ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'You cannot manage templates.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$args  = is_array( $args ) ? $args : array();
		$title = sanitize_text_field( $args['title'] ?? '' );
		if ( $title === '' ) {
			$title = __( 'Template', 'canvasly-lite' );
		}
		$type = self::normalize_type( $args['type'] ?? 'page' );
		$key  = sanitize_key( $args['key'] ?? '' );
		if ( $key === '' ) {
			$key = sanitize_title( $title );
		}
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => sanitize_title( $args['slug'] ?? $title ),
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return $id ? $id : new \WP_Error( 'create_failed', __( 'Could not create the template.', 'canvasly-lite' ), array( 'status' => 500 ) );
		}
		$id  = absint( $id );
		$doc = is_array( $args['document'] ?? null ) ? $args['document'] : ( class_exists( DocumentManager::class ) ? DocumentManager::empty() : array( 'version' => '1.0', 'root' => array() ) );
		self::save_document( $id, $doc );
		update_post_meta( $id, self::META_TYPE, $type );
		update_post_meta( $id, self::META_KEY, $key );
		if ( ! empty( $args['categories'] ) ) {
			self::set_categories( $id, $args['categories'] );
		}
		$thumb = absint( $args['thumbnail_id'] ?? 0 );
		if ( $thumb && function_exists( 'set_post_thumbnail' ) ) {
			set_post_thumbnail( $id, $thumb );
		}
		return $id;
	}

	public static function create_blank( $title = '' ) {
		return self::create(
			array(
				'title' => $title !== '' ? $title : __( 'Untitled Template', 'canvasly-lite' ),
				'type'  => 'page',
			)
		);
	}

	/**
	 * @param int $id
	 * @return int|\WP_Error
	 */
	public static function duplicate( $id ) {
		$id = absint( $id );
		$p  = function_exists( 'get_post' ) ? get_post( $id ) : null;
		if ( ! $p || $p->post_type !== self::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Template not found', 'canvasly-lite' ), array( 'status' => 404 ) );
		}
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'You cannot manage templates.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$new = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $p->post_title . ' ' . __( 'Copy', 'canvasly-lite' ),
			),
			true
		);
		if ( is_wp_error( $new ) || ! $new ) {
			return $new ? $new : new \WP_Error( 'create_failed', __( 'Could not duplicate the template.', 'canvasly-lite' ) );
		}
		$new = absint( $new );
		$d   = get_post_meta( $id, self::META_DATA, true );
		update_post_meta( $new, self::META_DATA, $d );
		update_post_meta( $new, self::META_TYPE, get_post_meta( $id, self::META_TYPE, true ) ?: 'page' );
		$old_key = (string) get_post_meta( $id, self::META_KEY, true );
		update_post_meta( $new, self::META_KEY, $old_key !== '' ? $old_key . '-copy' : sanitize_title( $p->post_title . '-copy' ) );
		if ( class_exists( DocumentManager::class ) && $d ) {
			$doc = is_string( $d ) ? json_decode( $d, true ) : $d;
			if ( is_array( $doc ) ) {
				update_post_meta( $new, DocumentManager::META, wp_json_encode( $doc ) );
			}
		}
		self::set_categories( $new, self::category_slugs( $id ) );
		$thumb = function_exists( 'get_post_thumbnail_id' ) ? absint( get_post_thumbnail_id( $id ) ) : 0;
		if ( $thumb && function_exists( 'set_post_thumbnail' ) ) {
			set_post_thumbnail( $new, $thumb );
		}
		return $new;
	}

	/**
	 * @param int  $id
	 * @param bool $light Skip the document payload (picker lists).
	 * @return array
	 */
	public static function item( $id, $light = false ) {
		$id = absint( $id );
		$p  = function_exists( 'get_post' ) ? get_post( $id ) : null;
		if ( ! $p || $p->post_type !== self::POST_TYPE ) {
			return array();
		}
		$doc   = $light ? array() : self::get_document( $id );
		$type  = self::normalize_type( get_post_meta( $id, self::META_TYPE, true ) ?: 'page' );
		$thumb = function_exists( 'get_post_thumbnail_id' ) ? absint( get_post_thumbnail_id( $id ) ) : 0;
		$author = '';
		if ( ! empty( $p->post_author ) && function_exists( 'get_the_author_meta' ) ) {
			$author = (string) get_the_author_meta( 'display_name', (int) $p->post_author );
		}
		$row = array(
			'id'           => $id,
			'title'        => $p->post_title,
			'slug'         => $p->post_name,
			'key'          => (string) get_post_meta( $id, self::META_KEY, true ),
			'type'         => $type,
			'type_label'   => self::type_label( $type ),
			'categories'   => self::category_slugs( $id ),
			'thumbnail_id' => $thumb,
			'thumbnail'    => $thumb && function_exists( 'get_the_post_thumbnail_url' ) ? (string) get_the_post_thumbnail_url( $id, 'medium' ) : '',
			'shortcode'    => self::shortcode_for( $id ),
			'author'       => $author,
			'date'         => $p->post_date,
			'edit_url'     => self::editor_url( $id ),
		);
		if ( ! $light ) {
			$row['document'] = $doc;
		}
		if ( class_exists( '\CanvaslyLite\Theme\Locations' ) ) {
			$row = \CanvaslyLite\Theme\Locations::with_export_locations( $row, $id, is_array( $doc ) ? $doc : array() );
		}
		return $row;
	}

	/**
	 * @param array $args
	 * @return array[]
	 */
	public static function query( $args = array() ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}
		$args = is_array( $args ) ? $args : array();
		$q    = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => array( 'publish', 'draft', 'private' ),
			'posts_per_page'         => isset( $args['per_page'] ) ? (int) $args['per_page'] : 100,
			'orderby'                => $args['orderby'] ?? 'title',
			'order'                  => $args['order'] ?? 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'lazy_load_term_meta'    => false,
		);
		$type = self::normalize_type( $args['type'] ?? '' );
		if ( ! empty( $args['type'] ) && $type ) {
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Template library filter by stored template type.
			$q['meta_key']   = self::META_TYPE;
			$q['meta_value'] = $type;
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		$cat = sanitize_title( $args['category'] ?? '' );
		if ( $cat !== '' ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Template library filter by category term.
			$q['tax_query'] = array(
				array(
					'taxonomy' => self::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $cat,
				),
			);
			$q['update_post_term_cache'] = true;
		}
		$light = ! empty( $args['light'] );
		$query = new \WP_Query( $q );
		$out   = array();
		foreach ( (array) $query->posts as $p ) {
			$item = self::item( is_object( $p ) ? $p->ID : absint( $p ), $light );
			if ( $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * @param int $id
	 * @return string[]
	 */
	public static function category_slugs( $id ) {
		$id = absint( $id );
		if ( ! $id || ! function_exists( 'wp_get_post_terms' ) ) {
			return array();
		}
		$terms = wp_get_post_terms( $id, self::TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'sanitize_title', $terms ) ) );
	}

	/**
	 * @return array<string,string> slug => name
	 */
	public static function category_names() {
		if ( ! function_exists( 'get_terms' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			if ( is_object( $t ) && ! empty( $t->slug ) ) {
				$out[ sanitize_title( $t->slug ) ] = (string) $t->name;
			}
		}
		return $out;
	}

	/**
	 * @param int          $id
	 * @param string[]|string $categories slugs or names
	 */
	public static function set_categories( $id, $categories ) {
		$id = absint( $id );
		if ( ! $id || ! function_exists( 'wp_set_object_terms' ) ) {
			return;
		}
		$slugs = array();
		foreach ( (array) $categories as $c ) {
			$c = is_array( $c ) ? ( $c['slug'] ?? $c['name'] ?? '' ) : $c;
			$c = sanitize_text_field( (string) $c );
			if ( $c === '' ) {
				continue;
			}
			$slug = sanitize_title( $c );
			if ( $slug === '' ) {
				continue;
			}
			if ( function_exists( 'term_exists' ) && ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term( $c, self::TAXONOMY, array( 'slug' => $slug ) );
			}
			$slugs[] = $slug;
		}
		wp_set_object_terms( $id, $slugs, self::TAXONOMY, false );
	}

	public static function menu() {
		add_submenu_page(
			'canvasly-lite',
			__( 'Import Templates', 'canvasly-lite' ),
			__( 'Import Templates', 'canvasly-lite' ),
			'edit_pages',
			'canvasly-lite-template-import',
			array( self::class, 'import_screen' )
		);
	}

	public static function admin_assets( $hook_suffix = '' ) {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::should_enqueue( $hook_suffix ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen && isset( $screen->id ) ? (string) $screen->id : (string) $hook_suffix;
		if ( strpos( $id, self::POST_TYPE ) === false && strpos( $id, 'canvasly-lite-template-import' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'canvasly-lite-admin-templates',
			CANVASLY_LITE_URL . 'assets/css/admin-templates.css',
			array(),
			defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0'
		);
	}

	public static function disable_block_editor( $use, $post_type ) {
		if ( $post_type === self::POST_TYPE ) {
			return false;
		}
		return $use;
	}

	public static function redirect_new() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only post-new.php post_type query var.
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $type !== self::POST_TYPE || ! self::can_manage() ) {
			return;
		}
		$id = self::create_blank();
		if ( is_wp_error( $id ) || ! $id ) {
			return;
		}
		wp_safe_redirect( self::editor_url( $id ) );
		exit;
	}

	public static function columns( $cols ) {
		$out = array();
		foreach ( (array) $cols as $key => $label ) {
			if ( $key === 'title' ) {
				$out['lb_thumb'] = __( 'Thumbnail', 'canvasly-lite' );
				$out[ $key ]     = $label;
				$out['lb_type']  = __( 'Type', 'canvasly-lite' );
				continue;
			}
			if ( $key === 'date' ) {
				$out['lb_shortcode'] = __( 'Shortcode', 'canvasly-lite' );
			}
			$out[ $key ] = $label;
		}
		if ( ! isset( $out['lb_thumb'] ) ) {
			$out = array( 'lb_thumb' => __( 'Thumbnail', 'canvasly-lite' ) ) + $out;
		}
		if ( ! isset( $out['lb_type'] ) ) {
			$out['lb_type'] = __( 'Type', 'canvasly-lite' );
		}
		if ( ! isset( $out['lb_shortcode'] ) ) {
			$out['lb_shortcode'] = __( 'Shortcode', 'canvasly-lite' );
		}
		return $out;
	}

	public static function sortable_columns( $cols ) {
		$cols['lb_type'] = 'lb_type';
		return $cols;
	}

	public static function column( $column, $post_id ) {
		$post_id = absint( $post_id );
		if ( $column === 'lb_thumb' ) {
			$thumb = function_exists( 'get_the_post_thumbnail' ) ? get_the_post_thumbnail( $post_id, array( 60, 60 ) ) : '';
			if ( $thumb ) {
				echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				$type = self::normalize_type( get_post_meta( $post_id, self::META_TYPE, true ) ?: 'page' );
				echo '<span class="lb-template-thumb-fallback">' . esc_html( self::type_label( $type ) ) . '</span>';
			}
			return;
		}
		if ( $column === 'lb_type' ) {
			$type = self::normalize_type( get_post_meta( $post_id, self::META_TYPE, true ) ?: 'page' );
			echo '<span class="lb-template-type">' . esc_html( self::type_label( $type ) ) . '</span>';
			return;
		}
		if ( $column === 'lb_shortcode' ) {
			$code = self::shortcode_for( $post_id );
			echo '<code class="lb-template-shortcode" data-lb-copy="' . esc_attr( $code ) . '">' . esc_html( $code ) . '</code>';
		}
	}

	public static function filters( $post_type ) {
		if ( $post_type !== self::POST_TYPE ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
		$current = isset( $_GET['lb_template_type'] ) ? sanitize_key( wp_unslash( $_GET['lb_template_type'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		echo '<select name="lb_template_type" id="lb-filter-template-type">';
		echo '<option value="">' . esc_html__( 'All types', 'canvasly-lite' ) . '</option>';
		foreach ( self::types() as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<a class="button" href="' . esc_url( self::import_url() ) . '">' . esc_html__( 'Import', 'canvasly-lite' ) . '</a>';
	}

	public static function filter_query( $q ) {
		if ( ! is_admin() || ! $q instanceof \WP_Query || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->get( 'post_type' ) !== self::POST_TYPE ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
		$type = isset( $_GET['lb_template_type'] ) ? sanitize_key( wp_unslash( $_GET['lb_template_type'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $type !== '' ) {
			$q->set( 'meta_key', self::META_TYPE );
			$q->set( 'meta_value', self::normalize_type( $type ) );
		}
		if ( $q->get( 'orderby' ) === 'lb_type' ) {
			$q->set( 'meta_key', self::META_TYPE );
			$q->set( 'orderby', 'meta_value' );
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return $actions;
		}
		$id   = (int) $post->ID;
		$edit = '<a href="' . esc_url( self::editor_url( $id ) ) . '">' . esc_html__( 'Edit with Canvasly', 'canvasly-lite' ) . '</a>';
		$exp  = '<a href="' . esc_url( self::export_url( $id ) ) . '">' . esc_html__( 'Export', 'canvasly-lite' ) . '</a>';
		$out  = array();
		if ( isset( $actions['edit'] ) ) {
			$out['edit'] = $actions['edit'];
		}
		$out['lb_edit']   = $edit;
		$out['lb_export'] = $exp;
		foreach ( $actions as $k => $html ) {
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = $html;
			}
		}
		return $out;
	}

	public static function bulk_actions( $actions ) {
		$actions['lb_export'] = __( 'Export', 'canvasly-lite' );
		return $actions;
	}

	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( $action !== 'lb_export' ) {
			return $redirect;
		}
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( ! $ids ) {
			return $redirect;
		}
		if ( class_exists( TemplateIO::class ) ) {
			TemplateIO::stream( $ids );
			exit;
		}
		return $redirect;
	}

	public static function export_url( $id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=lb_template_export&template_id=' . absint( $id ) ),
			'lb_template_export_' . absint( $id )
		);
	}

	public static function handle_export() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You cannot export templates.', 'canvasly-lite' ) );
		}
		$id = absint( $_GET['template_id'] ?? $_POST['template_id'] ?? 0 );
		if ( $id ) {
			check_admin_referer( 'lb_template_export_' . $id );
			if ( class_exists( TemplateIO::class ) ) {
				TemplateIO::stream( array( $id ) );
				exit;
			}
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) );
		exit;
	}

	public static function handle_import() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You cannot import templates.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_template_import' );
		$file = array(
			'name'     => isset( $_FILES['template_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['template_file']['name'] ) ) : '',
			'type'     => isset( $_FILES['template_file']['type'] ) ? sanitize_mime_type( wp_unslash( $_FILES['template_file']['type'] ) ) : '',
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a PHP upload path validated below.
			'tmp_name' => isset( $_FILES['template_file']['tmp_name'] ) ? (string) $_FILES['template_file']['tmp_name'] : '',
			'error'    => isset( $_FILES['template_file']['error'] ) ? absint( $_FILES['template_file']['error'] ) : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $_FILES['template_file']['size'] ) ? absint( $_FILES['template_file']['size'] ) : 0,
		);
		if ( $file['tmp_name'] === '' ) {
			self::store_notice( 'error', __( 'Choose a JSON or ZIP file to import.', 'canvasly-lite' ) );
			wp_safe_redirect( self::import_url() );
			exit;
		}
		$result = class_exists( TemplateIO::class ) ? TemplateIO::import( $file['tmp_name'] ) : new \WP_Error( 'missing', __( 'Importer is unavailable.', 'canvasly-lite' ) );
		if ( is_wp_error( $result ) ) {
			self::store_notice( 'error', $result->get_error_message() );
		} else {
			self::store_notice(
				'success',
				sprintf(
					/* translators: 1: templates imported, 2: media items */
					__( 'Imported %1$d template(s) and %2$d media item(s).', 'canvasly-lite' ),
					(int) ( $result['templates'] ?? 0 ),
					(int) ( $result['media'] ?? 0 )
				)
			);
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) );
		exit;
	}

	public static function import_url() {
		return admin_url( 'admin.php?page=canvasly-lite-template-import' );
	}

	public static function import_screen() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You cannot import templates.', 'canvasly-lite' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Import Templates', 'canvasly-lite' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Import Canvasly templates from a JSON file or a ZIP that includes media. Remote image URLs are downloaded and remapped.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		wp_nonce_field( 'lb_template_import' );
		echo '<input type="hidden" name="action" value="lb_template_import">';
		echo '<table class="form-table"><tbody><tr><th><label for="lb-template-file">' . esc_html__( 'Template file', 'canvasly-lite' ) . '</label></th><td>';
		echo '<input id="lb-template-file" type="file" name="template_file" accept=".json,.zip,application/json,application/zip" required>';
		echo '<p class="description">' . esc_html__( 'Accepts a Canvasly template JSON, a multi-template JSON, or a ZIP with templates.json / kit.json and a media folder.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr></tbody></table>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Import Templates', 'canvasly-lite' ) . '</button> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) ) . '">' . esc_html__( 'Back to templates', 'canvasly-lite' ) . '</a></p>';
		echo '</form></div>';
	}

	public static function metaboxes() {
		add_meta_box(
			'lb-template-details',
			__( 'Template Details', 'canvasly-lite' ),
			array( self::class, 'metabox' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	public static function metabox( $post ) {
		if ( ! $post ) {
			return;
		}
		wp_nonce_field( 'lb_template_meta', 'lb_template_meta_nonce' );
		$type = self::normalize_type( get_post_meta( $post->ID, self::META_TYPE, true ) ?: 'page' );
		echo '<p><label for="lb-template-type"><strong>' . esc_html__( 'Type', 'canvasly-lite' ) . '</strong></label></p>';
		echo '<select id="lb-template-type" name="lb_template_type" style="width:100%">';
		foreach ( self::types() as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $type, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Used by the library, Collection Loop, Template widget, shortcode, and Gutenberg block.', 'canvasly-lite' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Shortcode', 'canvasly-lite' ) . '</strong></p>';
		echo '<code>' . esc_html( self::shortcode_for( $post->ID ) ) . '</code>';
		echo '<p><a class="button button-primary" href="' . esc_url( self::editor_url( $post->ID ) ) . '">' . esc_html__( 'Edit with Canvasly', 'canvasly-lite' ) . '</a></p>';
	}

	public static function save_metabox( $post_id, $post ) {
		if ( ! $post_id || ! $post || $post->post_type !== self::POST_TYPE ) {
			return;
		}
		if ( ! isset( $_POST['lb_template_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lb_template_meta_nonce'] ) ), 'lb_template_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['lb_template_type'] ) ) {
			update_post_meta( $post_id, self::META_TYPE, self::normalize_type( sanitize_key( wp_unslash( $_POST['lb_template_type'] ) ) ) );
		}
	}

	public static function post_states( $states, $post ) {
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return $states;
		}
		$type = self::normalize_type( get_post_meta( $post->ID, self::META_TYPE, true ) ?: 'page' );
		$states['lb_type'] = self::type_label( $type );
		return $states;
	}

	public static function store_notice( $type, $message ) {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		set_transient(
			self::NOTICE . '_' . get_current_user_id(),
			array(
				'type'    => $type === 'success' ? 'success' : 'error',
				'message' => $message,
			),
			120
		);
	}

	public static function admin_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$id = (string) ( $screen->id ?? '' );
		if ( strpos( $id, self::POST_TYPE ) === false && strpos( $id, 'canvasly-lite-template-import' ) === false ) {
			return;
		}
		$n = get_transient( self::NOTICE . '_' . get_current_user_id() );
		if ( ! is_array( $n ) ) {
			return;
		}
		delete_transient( self::NOTICE . '_' . get_current_user_id() );
		$class = ( $n['type'] ?? '' ) === 'success' ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $n['message'] ?? '' ) ) . '</p></div>';
	}

	/**
	 * Render `[canvasly_lite_template id=""]`. Enqueues CSS/JS for the embedded template.
	 *
	 * @param array $atts
	 * @return string
	 */
	public static function shortcode( $atts ) {
		if ( class_exists( TemplateEmbed::class ) ) {
			return TemplateEmbed::shortcode( $atts );
		}
		$atts = is_array( $atts ) ? $atts : array();
		$id   = absint( $atts['id'] ?? 0 );
		if ( ! $id || ! self::is_template( $id ) ) {
			return '';
		}
		$post = get_post( $id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return '';
		}
		static $stack = array();
		if ( isset( $stack[ $id ] ) ) {
			return '';
		}
		$stack[ $id ] = true;
		$doc          = self::get_document( $id );
		$html         = '';
		if ( class_exists( FrontendRenderer::class ) ) {
			if ( function_exists( 'wp_enqueue_style' ) ) {
				wp_enqueue_style( 'canvasly-lite-frontend' );
			}
			if ( class_exists( '\\CanvaslyLite\\Design\\CssPrint' ) ) {
				\CanvaslyLite\Design\CssPrint::enqueue_for_document( $id );
			} elseif ( class_exists( DocumentManager::class ) ) {
				$css = DocumentManager::compiled_css( $id );
				if ( $css && function_exists( 'wp_add_inline_style' ) ) {
					wp_add_inline_style( 'canvasly-lite-frontend', $css );
				}
			}
			$html = FrontendRenderer::render_document( $doc, $id );
		}
		unset( $stack[ $id ] );
		return is_string( $html ) ? $html : '';
	}

	public static function rest_picker( $req ) {
		$args = array(
			'per_page' => 200,
			'light'    => true,
		);
		if ( $req && $req->get_param( 'type' ) ) {
			$args['type'] = $req->get_param( 'type' );
		}
		if ( $req && $req->get_param( 'category' ) ) {
			$args['category'] = $req->get_param( 'category' );
		}
		return rest_ensure_response( self::query( $args ) );
	}

	public static function rest_list( $req ) {
		$args = array();
		if ( $req ) {
			if ( $req->get_param( 'type' ) ) {
				$args['type'] = $req->get_param( 'type' );
			}
			if ( $req->get_param( 'category' ) ) {
				$args['category'] = $req->get_param( 'category' );
			}
		}
		return rest_ensure_response( self::query( $args ) );
	}

	public static function rest_save( $req ) {
		$d     = is_array( $req->get_json_params() ) ? $req->get_json_params() : array();
		$title = sanitize_text_field( $d['title'] ?? '' );
		if ( $title === '' ) {
			return new \WP_Error( 'invalid', __( 'Template title required', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$id = self::create(
			array(
				'title'        => $title,
				'type'         => $d['type'] ?? 'page',
				'document'     => is_array( $d['document'] ?? null ) ? $d['document'] : array(),
				'categories'   => $d['categories'] ?? array(),
				'thumbnail_id' => absint( $d['thumbnail_id'] ?? 0 ),
				'key'          => $d['key'] ?? '',
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return rest_ensure_response(
			array(
				'success'  => true,
				'id'       => (int) $id,
				'template' => self::item( $id ),
			)
		);
	}

	public static function rest_duplicate( $req ) {
		$id = self::duplicate( absint( $req['id'] ?? 0 ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => (int) $id,
			)
		);
	}

	public static function rest_export( $req ) {
		$id = absint( $req['id'] ?? 0 );
		if ( ! $id || ! self::is_template( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Template not found', 'canvasly-lite' ), array( 'status' => 404 ) );
		}
		if ( ! class_exists( TemplateIO::class ) ) {
			return new \WP_Error( 'missing', __( 'Exporter is unavailable.', 'canvasly-lite' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( TemplateIO::payload( array( $id ) ) );
	}

	public static function rest_export_bulk( $req ) {
		$d   = is_array( $req->get_json_params() ) ? $req->get_json_params() : array();
		$ids = $req->get_param( 'ids' );
		if ( is_string( $ids ) ) {
			$ids = preg_split( '/[,\s]+/', $ids );
		}
		if ( ! $ids ) {
			$ids = $d['ids'] ?? array();
		}
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( ! $ids ) {
			return new \WP_Error( 'invalid', __( 'Select at least one template to export.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( TemplateIO::class ) ) {
			return new \WP_Error( 'missing', __( 'Exporter is unavailable.', 'canvasly-lite' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( TemplateIO::payload( $ids ) );
	}

	public static function rest_import( $req ) {
		if ( ! class_exists( TemplateIO::class ) ) {
			return new \WP_Error( 'missing', __( 'Importer is unavailable.', 'canvasly-lite' ), array( 'status' => 500 ) );
		}
		$files = $req->get_file_params();
		$file  = $files['file'] ?? ( $files['template'] ?? null );
		if ( is_array( $file ) && ! empty( $file['tmp_name'] ) ) {
			$r = TemplateIO::import( $file['tmp_name'] );
			return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
		}
		$d = $req->get_json_params();
		if ( ! is_array( $d ) ) {
			return new \WP_Error( 'invalid', __( 'Upload a JSON or ZIP file, or send template JSON.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$r = TemplateIO::import_payload( $d );
		return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
	}

	public static function rest_thumbnail( $req ) {
		$id = absint( $req['id'] ?? 0 );
		if ( ! $id || ! self::is_template( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Template not found', 'canvasly-lite' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $id ) && ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'You cannot update this template.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$d        = is_array( $req->get_json_params() ) ? $req->get_json_params() : array();
		$media_id = absint( $d['thumbnail_id'] ?? $d['attachment_id'] ?? 0 );
		$data_url = (string) ( $d['image'] ?? $d['data'] ?? '' );
		if ( $media_id && function_exists( 'get_post_type' ) && get_post_type( $media_id ) === 'attachment' && function_exists( 'set_post_thumbnail' ) ) {
			set_post_thumbnail( $id, $media_id );
			return rest_ensure_response(
				array(
					'success'      => true,
					'thumbnail_id' => $media_id,
					'thumbnail'    => function_exists( 'get_the_post_thumbnail_url' ) ? (string) get_the_post_thumbnail_url( $id, 'medium' ) : '',
				)
			);
		}
		if ( $data_url !== '' && class_exists( TemplateIO::class ) ) {
			$media_id = TemplateIO::save_data_url_image( $data_url, $id );
			if ( is_wp_error( $media_id ) ) {
				return $media_id;
			}
			if ( $media_id && function_exists( 'set_post_thumbnail' ) ) {
				set_post_thumbnail( $id, $media_id );
			}
			return rest_ensure_response(
				array(
					'success'      => true,
					'thumbnail_id' => (int) $media_id,
					'thumbnail'    => function_exists( 'get_the_post_thumbnail_url' ) ? (string) get_the_post_thumbnail_url( $id, 'medium' ) : '',
				)
			);
		}
		return new \WP_Error( 'invalid', __( 'Send a thumbnail image or media id.', 'canvasly-lite' ), array( 'status' => 400 ) );
	}

	public static function rest_types() {
		$cats = array();
		foreach ( self::category_names() as $slug => $name ) {
			$cats[] = array(
				'slug' => $slug,
				'name' => $name,
			);
		}
		$types = array();
		foreach ( self::types() as $slug => $label ) {
			$types[] = array(
				'slug'  => $slug,
				'label' => $label,
			);
		}
		return rest_ensure_response(
			array(
				'types'      => $types,
				'categories' => $cats,
			)
		);
	}
}
