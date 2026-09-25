<?php
namespace CanvaslyLite\Document;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Document snapshots as WordPress revisions, plus migration from the legacy
 * `_lb_document_revisions` meta array (Roadmap 3.5).
 */
class Revisions {
	const LEGACY     = '_lb_document_revisions';
	const MIGRATED   = '_lb_revisions_migrated';
	const LABEL_META = '_lb_revision_label';
	const AUTOSAVE   = '_lb_autosave_data';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'enable_post_type_support' ), 30 );
		add_action( '_wp_put_post_revision', array( __CLASS__, 'copy_meta_to_revision' ) );
		add_filter( 'wp_save_post_revision_post_has_changed', array( __CLASS__, 'document_has_changed' ), 10, 3 );
		add_filter( 'wp_revisions_to_keep', array( __CLASS__, 'cap_revisions' ), 10, 2 );
	}

	/**
	 * Canvasly documents live in post meta, so enabled types need WP revisions.
	 */
	public static function enable_post_type_support() {
		if ( ! class_exists( Documents::class ) || ! function_exists( 'add_post_type_support' ) ) {
			return;
		}
		foreach ( Documents::enabled() as $type ) {
			add_post_type_support( $type, 'revisions' );
		}
	}

	/**
	 * Copy the parent document onto every WP revision of a Canvasly post.
	 *
	 * @param int $revision_id
	 */
	public static function copy_meta_to_revision( $revision_id ) {
		$parent = function_exists( 'wp_is_post_revision' ) ? wp_is_post_revision( $revision_id ) : 0;
		if ( ! $parent ) {
			return;
		}
		$raw = get_post_meta( $parent, DocumentManager::META, true );
		if ( $raw === '' || $raw === false ) {
			return;
		}
		update_post_meta( (int) $revision_id, DocumentManager::META, $raw );
		$version = get_post_meta( $parent, DocumentManager::VERSION, true );
		if ( $version ) {
			update_post_meta( (int) $revision_id, DocumentManager::VERSION, $version );
		}
	}

	/**
	 * Force a WP revision when the Canvasly document changed even if post_content did not.
	 *
	 * @param bool     $post_has_changed
	 * @param \WP_Post|null $last_revision
	 * @param \WP_Post $post
	 * @return bool
	 */
	public static function document_has_changed( $post_has_changed, $last_revision, $post ) {
		if ( $post_has_changed || ! $post || empty( $post->ID ) ) {
			return $post_has_changed;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ( function_exists( 'wp_doing_autosave' ) && wp_doing_autosave() )
			|| ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && \CanvaslyLite\Admin\AdminContext::is_autosave() ) ) {
			return $post_has_changed;
		}
		if ( ! self::is_document_post( (int) $post->ID ) ) {
			return $post_has_changed;
		}
		$current = (string) get_post_meta( (int) $post->ID, DocumentManager::META, true );
		$prev    = '';
		if ( $last_revision && ! empty( $last_revision->ID ) ) {
			$prev = (string) get_post_meta( (int) $last_revision->ID, DocumentManager::META, true );
		}
		return $current !== $prev;
	}

	/**
	 * Canvasly stores a full document JSON on every revision. Unbounded WP
	 * revision history (the default) exhausts wp_posts / wp_postmeta.
	 *
	 * @param int      $num
	 * @param \WP_Post $post
	 * @return int
	 */
	public static function cap_revisions( $num, $post ) {
		$num = (int) $num;
		if ( $num === 0 ) {
			return 0;
		}
		if ( ! $post || empty( $post->ID ) || ! self::is_document_post( (int) $post->ID ) ) {
			return $num;
		}
		$cap = 20;
		if ( $num > 0 && $num < $cap ) {
			return $num;
		}
		return $cap;
	}

	/**
	 * @param int $post_id
	 * @return bool
	 */
	public static function is_document_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ( isset( $post->post_type ) && $post->post_type === 'revision' ) ) {
			return false;
		}
		if ( class_exists( Documents::class ) ) {
			return Documents::supports( $post->post_type );
		}
		return true;
	}

	/**
	 * Snapshot the current document as a WordPress revision.
	 *
	 * @param int    $post_id
	 * @param string $label
	 * @return int Revision post ID, or 0.
	 */
	public static function record( $post_id, $label = '' ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return 0;
		}
		self::migrate_legacy( $post_id );
		if ( ! function_exists( 'wp_save_post_revision' ) ) {
			return 0;
		}
		$revision_id = wp_save_post_revision( $post_id );
		$revision_id = is_wp_error( $revision_id ) ? 0 : absint( $revision_id );
		if ( $revision_id ) {
			self::copy_meta_to_revision( $revision_id );
			if ( $label !== '' ) {
				update_post_meta( $revision_id, self::LABEL_META, sanitize_text_field( $label ) );
			}
		}
		return $revision_id;
	}

	/**
	 * @param int $post_id
	 * @return array<int,array<string,mixed>>
	 */
	public static function summarize( $post_id ) {
		$post_id = absint( $post_id );
		self::migrate_legacy( $post_id );
		$items = array();
		if ( ! function_exists( 'wp_get_post_revisions' ) ) {
			return $items;
		}
		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		foreach ( (array) $revisions as $rev ) {
			if ( ! $rev || empty( $rev->ID ) ) {
				continue;
			}
			$raw = get_post_meta( (int) $rev->ID, DocumentManager::META, true );
			if ( $raw === '' || $raw === false || $raw === null ) {
				continue;
			}
			$items[] = self::entry( $rev );
		}
		return $items;
	}

	/**
	 * Full document for a revision (preview). Does not write the parent post.
	 *
	 * @param int $post_id
	 * @param int $revision_id
	 * @return array|\WP_Error
	 */
	public static function preview( $post_id, $revision_id ) {
		$owned = self::owned_revision( $post_id, $revision_id );
		if ( is_wp_error( $owned ) ) {
			return $owned;
		}
		$doc = self::document_from_revision( (int) $owned->ID );
		if ( ! $doc ) {
			return new \WP_Error( 'not_found', __( 'Revision not found.', 'canvasly-lite' ) );
		}
		$entry              = self::entry( $owned );
		$entry['document'] = DocumentManager::sanitize( $doc );
		return $entry;
	}

	/**
	 * Restore a WordPress revision onto the live document after snapshotting the current one.
	 *
	 * @param int $post_id
	 * @param int $revision_id
	 * @return array|\WP_Error
	 */
	public static function restore( $post_id, $revision_id ) {
		$post_id     = absint( $post_id );
		$revision_id = absint( $revision_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot edit this document.', 'canvasly-lite' ) );
		}
		$owned = self::owned_revision( $post_id, $revision_id );
		if ( is_wp_error( $owned ) ) {
			return $owned;
		}
		$doc = self::document_from_revision( (int) $owned->ID );
		if ( ! $doc ) {
			return new \WP_Error( 'not_found', __( 'Revision not found.', 'canvasly-lite' ) );
		}
		self::record( $post_id, __( 'Before restore', 'canvasly-lite' ) );
		$clean = DocumentManager::sanitize( $doc );
		update_post_meta( $post_id, DocumentManager::META, wp_json_encode( $clean ) );
		update_post_meta( $post_id, DocumentManager::VERSION, CANVASLY_LITE_VERSION );
		update_post_meta( $post_id, DocumentManager::UPDATED, current_time( 'mysql' ) );
		delete_post_meta( $post_id, DocumentManager::CSS_CACHE );
		self::delete_autosave( $post_id );
		if ( class_exists( 'CanvaslyLite\\Design\\Performance' ) ) {
			\CanvaslyLite\Design\Performance::invalidate( $post_id );
		}
		return $clean;
	}

	/**
	 * Store an in-progress document on the WordPress autosave revision.
	 *
	 * @param int   $post_id
	 * @param array $doc
	 * @return bool
	 */
	public static function autosave( $post_id, $doc ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		$clean = DocumentManager::sanitize( is_array( $doc ) ? $doc : array() );
		$json  = wp_json_encode( $clean );
		$auto_id = 0;
		if ( function_exists( 'wp_get_post_autosave' ) ) {
			$existing = wp_get_post_autosave( $post_id );
			$auto_id  = $existing && ! empty( $existing->ID ) ? (int) $existing->ID : 0;
		}
		if ( $auto_id ) {
			$prev = get_post_meta( $auto_id, DocumentManager::META, true );
			if ( is_string( $prev ) && $prev === $json ) {
				return true;
			}
			update_post_meta( $auto_id, DocumentManager::META, $json );
			update_post_meta( $auto_id, self::LABEL_META, __( 'Autosave', 'canvasly-lite' ) );
			delete_post_meta( $post_id, self::AUTOSAVE );
			return true;
		}
		if ( ! $auto_id && function_exists( 'wp_create_post_autosave' ) ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$payload = array(
					'post_ID'      => $post_id,
					'post_title'   => $post->post_title,
					'post_content' => $post->post_content,
					'post_excerpt' => isset( $post->post_excerpt ) ? $post->post_excerpt : '',
				);
				$created = wp_create_post_autosave( $payload );
				$auto_id = is_wp_error( $created ) ? 0 : absint( $created );
			}
		}
		if ( $auto_id ) {
			update_post_meta( $auto_id, DocumentManager::META, $json );
			update_post_meta( $auto_id, self::LABEL_META, __( 'Autosave', 'canvasly-lite' ) );
			delete_post_meta( $post_id, self::AUTOSAVE );
			return true;
		}
		update_post_meta(
			$post_id,
			self::AUTOSAVE,
			array(
				'time'     => current_time( 'mysql' ),
				'document' => $clean,
			)
		);
		return true;
	}

	/**
	 * @param int $post_id
	 * @return array{id:int,time:string,document:array}|null
	 */
	public static function get_autosave( $post_id ) {
		$post_id = absint( $post_id );
		if ( function_exists( 'wp_get_post_autosave' ) ) {
			$auto = wp_get_post_autosave( $post_id );
			if ( $auto && ! empty( $auto->ID ) ) {
				$doc = self::document_from_revision( (int) $auto->ID );
				if ( $doc ) {
					return array(
						'id'       => (int) $auto->ID,
						'time'     => (string) $auto->post_modified,
						'document' => $doc,
						'autosave' => true,
					);
				}
			}
		}
		$legacy = get_post_meta( $post_id, self::AUTOSAVE, true );
		if ( is_array( $legacy ) && ! empty( $legacy['document'] ) && is_array( $legacy['document'] ) ) {
			return array(
				'id'       => 0,
				'time'     => sanitize_text_field( (string) ( $legacy['time'] ?? '' ) ),
				'document' => $legacy['document'],
				'autosave' => true,
			);
		}
		return null;
	}

	/**
	 * @param int $post_id
	 */
	public static function delete_autosave( $post_id ) {
		$post_id = absint( $post_id );
		delete_post_meta( $post_id, self::AUTOSAVE );
		if ( ! function_exists( 'wp_get_post_autosave' ) ) {
			return;
		}
		$auto = wp_get_post_autosave( $post_id );
		if ( $auto && ! empty( $auto->ID ) && function_exists( 'wp_delete_post' ) ) {
			wp_delete_post( (int) $auto->ID, true );
		}
	}

	/**
	 * Turn the 50-item `_lb_document_revisions` array into WordPress revisions.
	 *
	 * @param int $post_id
	 * @return int Number of revisions created.
	 */
	public static function migrate_legacy( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return 0;
		}
		$legacy = get_post_meta( $post_id, self::LEGACY, true );
		if ( ! is_array( $legacy ) || ! $legacy ) {
			if ( get_post_meta( $post_id, self::MIGRATED, true ) !== '1' ) {
				update_post_meta( $post_id, self::MIGRATED, '1' );
			}
			return 0;
		}
		$count = 0;
		foreach ( $legacy as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$doc = $item['document'] ?? null;
			if ( ! is_array( $doc ) ) {
				continue;
			}
			$id = self::insert_historical( $post_id, $doc, sanitize_text_field( (string) ( $item['time'] ?? '' ) ) );
			if ( $id ) {
				$count++;
			}
		}
		delete_post_meta( $post_id, self::LEGACY );
		update_post_meta( $post_id, self::MIGRATED, '1' );
		return $count;
	}

	/**
	 * Insert a revision with an explicit timestamp (legacy migration).
	 *
	 * @param int    $post_id
	 * @param array  $doc
	 * @param string $time
	 * @return int
	 */
	public static function insert_historical( $post_id, array $doc, $time = '' ) {
		$post = get_post( $post_id );
		if ( ! $post || ! function_exists( 'wp_insert_post' ) ) {
			return 0;
		}
		$clean = DocumentManager::sanitize( $doc );
		$date  = self::normalize_mysql_date( $time );
		$args  = array(
			'post_parent'  => $post_id,
			'post_type'    => 'revision',
			'post_status'  => 'inherit',
			'post_name'    => $post_id . '-revision-v1',
			'post_title'   => $post->post_title,
			'post_content' => isset( $post->post_content ) ? $post->post_content : '',
			'post_author'  => isset( $post->post_author ) ? $post->post_author : get_current_user_id(),
		);
		if ( $date ) {
			$args['post_date']     = $date;
			$args['post_date_gmt'] = $date;
			$args['post_modified'] = $date;
		}
		$id = wp_insert_post( $args, true );
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		update_post_meta( (int) $id, DocumentManager::META, wp_json_encode( $clean ) );
		update_post_meta( (int) $id, self::LABEL_META, __( 'Migrated revision', 'canvasly-lite' ) );
		return (int) $id;
	}

	/**
	 * @param int $post_id
	 * @param int $revision_id
	 * @return \WP_Post|\WP_Error
	 */
	public static function owned_revision( $post_id, $revision_id ) {
		$post_id     = absint( $post_id );
		$revision_id = absint( $revision_id );
		if ( ! $post_id || ! $revision_id ) {
			return new \WP_Error( 'not_found', __( 'Revision not found.', 'canvasly-lite' ) );
		}
		self::migrate_legacy( $post_id );
		$rev = get_post( $revision_id );
		if ( ! $rev || ( isset( $rev->post_type ) && $rev->post_type !== 'revision' ) ) {
			return new \WP_Error( 'not_found', __( 'Revision not found.', 'canvasly-lite' ) );
		}
		$parent = isset( $rev->post_parent ) ? (int) $rev->post_parent : 0;
		if ( function_exists( 'wp_is_post_revision' ) ) {
			$from_wp = wp_is_post_revision( $revision_id );
			if ( $from_wp ) {
				$parent = (int) $from_wp;
			}
		}
		if ( $parent !== $post_id ) {
			return new \WP_Error( 'not_found', __( 'Revision not found.', 'canvasly-lite' ) );
		}
		return $rev;
	}

	/**
	 * @param int $revision_id
	 * @return array|null
	 */
	public static function document_from_revision( $revision_id ) {
		$raw = get_post_meta( (int) $revision_id, DocumentManager::META, true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || $raw === '' ) {
			return null;
		}
		$d = json_decode( $raw, true );
		return is_array( $d ) ? $d : null;
	}

	/**
	 * @param object $rev
	 * @return array<string,mixed>
	 */
	private static function entry( $rev ) {
		$author = '';
		if ( ! empty( $rev->post_author ) && function_exists( 'get_the_author_meta' ) ) {
			$author = (string) get_the_author_meta( 'display_name', (int) $rev->post_author );
		}
		$name = isset( $rev->post_name ) ? (string) $rev->post_name : '';
		return array(
			'id'       => (int) $rev->ID,
			'time'     => sanitize_text_field( (string) ( $rev->post_date ?? '' ) ),
			'modified' => sanitize_text_field( (string) ( $rev->post_modified ?? $rev->post_date ?? '' ) ),
			'author'   => sanitize_text_field( $author ),
			'autosave' => ( strpos( $name, 'autosave' ) !== false ),
			'label'    => sanitize_text_field( (string) get_post_meta( (int) $rev->ID, self::LABEL_META, true ) ),
		);
	}

	/**
	 * @param string $time
	 * @return string
	 */
	private static function normalize_mysql_date( $time ) {
		$time = trim( (string) $time );
		if ( $time === '' ) {
			return '';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $time ) ) {
			return $time;
		}
		$ts = strtotime( $time );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
	}
}
