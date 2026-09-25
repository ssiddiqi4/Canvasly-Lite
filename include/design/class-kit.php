<?php
namespace CanvaslyLite\Design;

use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Document\Documents;
use CanvaslyLite\Settings\GlobalSettings;
use CanvaslyLite\Settings\KitSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full site kit export/import (Roadmap 2.4).
 *
 * ZIP layout:
 *   manifest.json  — type, schema, counts, include flags
 *   kit.json       — site settings, tokens, theme style, classes, components, templates, content
 *   media.json     — attachment index (id, file, url, mime, title, alt)
 *   media/{file}   — copied attachment files
 */
class Kit {
	const SCHEMA      = '1.0';
	const TYPE        = 'canvasly-lite-kit';
	const TRANSIENT   = 'canvasly_lite_kit_export_';
	const NOTICE      = 'canvasly_lite_kit_notice';
	const SOURCE_META = '_lb_kit_source';
	const MAX_CONTENT = 80;
	const MAX_MEDIA   = 200;

	public static function init() {
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
			add_action( 'admin_post_lb_kit_export', array( self::class, 'handle_export' ) );
			add_action( 'admin_post_lb_kit_import', array( self::class, 'handle_import' ) );
			add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		}
	}

	public static function menu() {
		add_submenu_page(
			'canvasly-lite',
			__( 'Tools', 'canvasly-lite' ),
			__( 'Tools', 'canvasly-lite' ),
			'manage_options',
			'canvasly-lite-tools',
			array( self::class, 'screen' )
		);
	}

	public static function can_manage() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * JSON kit payload (no binary files). Used by ZIP export and tests.
	 *
	 * @param array $args {
	 *   @type bool  $include_templates
	 *   @type bool  $include_content
	 *   @type int[] $content_ids
	 *   @type bool  $include_media
	 * }
	 * @return array
	 */
	public static function payload( $args = array() ) {
		$args = self::normalize_args( $args );
		$ds   = class_exists( DesignSystem::class ) ? DesignSystem::export() : array();
		if ( ! $args['include_templates'] ) {
			$ds['templates'] = array();
		}
		$content = array();
		if ( $args['include_content'] ) {
			$content = self::export_content( $args['content_ids'] );
		}

		$kit = array(
			'schema'          => DesignSystem::SCHEMA,
			'type'            => self::TYPE,
			'exported_at'     => function_exists( 'current_time' ) ? current_time( 'c' ) : gmdate( 'c' ),
			'generator'       => defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0',
			'variables'       => isset( $ds['variables'] ) && is_array( $ds['variables'] ) ? $ds['variables'] : array(),
			'theme_style'     => isset( $ds['theme_style'] ) && is_array( $ds['theme_style'] ) ? $ds['theme_style'] : array(),
			'kit_settings'    => isset( $ds['kit_settings'] ) && is_array( $ds['kit_settings'] ) ? $ds['kit_settings'] : array(),
			'classes'         => isset( $ds['classes'] ) && is_array( $ds['classes'] ) ? $ds['classes'] : array(),
			'components'      => isset( $ds['components'] ) && is_array( $ds['components'] ) ? $ds['components'] : array(),
			'global_settings' => isset( $ds['global_settings'] ) && is_array( $ds['global_settings'] ) ? $ds['global_settings'] : array(),
			'atomic'          => isset( $ds['atomic'] ) && is_array( $ds['atomic'] ) ? $ds['atomic'] : array(),
			'templates'       => isset( $ds['templates'] ) && is_array( $ds['templates'] ) ? $ds['templates'] : array(),
			'content'         => $content,
		);

		/**
		 * Filter the kit JSON payload before it is packed.
		 *
		 * @param array $kit
		 * @param array $args
		 */
		$filtered = apply_filters( 'canvasly-lite/kit/export_payload', $kit, $args );
		return is_array( $filtered ) ? $filtered : $kit;
	}

	/**
	 * Manifest written to the ZIP root.
	 *
	 * @param array $kit
	 * @param array $media
	 * @param array $args
	 * @return array
	 */
	public static function manifest( $kit, $media = array(), $args = array() ) {
		$args = self::normalize_args( $args );
		$site = array(
			'title' => function_exists( 'get_option' ) ? (string) get_option( 'blogname', '' ) : '',
			'url'   => function_exists( 'home_url' ) ? (string) home_url( '/' ) : '',
		);
		return array(
			'schema'      => self::SCHEMA,
			'type'        => self::TYPE,
			'generator'   => defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0',
			'exported_at' => $kit['exported_at'] ?? ( function_exists( 'current_time' ) ? current_time( 'c' ) : gmdate( 'c' ) ),
			'site'        => $site,
			'includes'    => array(
				'site_settings'  => true,
				'design_tokens'  => true,
				'templates'      => ! empty( $args['include_templates'] ),
				'content'        => ! empty( $args['include_content'] ),
				'media'          => ! empty( $args['include_media'] ),
			),
			'counts'      => array(
				'templates'  => count( (array) ( $kit['templates'] ?? array() ) ),
				'components' => count( (array) ( $kit['components'] ?? array() ) ),
				'content'    => count( (array) ( $kit['content'] ?? array() ) ),
				'classes'    => count( (array) ( $kit['classes'] ?? array() ) ),
				'media'      => count( $media ),
			),
		);
	}

	/**
	 * Lightweight summary for the Tools UI and editor.
	 *
	 * @return array
	 */
	public static function summary() {
		$ds = class_exists( DesignSystem::class ) ? DesignSystem::export() : array();
		return array(
			'schema'          => self::SCHEMA,
			'type'            => self::TYPE,
			'templates'       => count( (array) ( $ds['templates'] ?? array() ) ),
			'components'      => count( (array) ( $ds['components'] ?? array() ) ),
			'classes'         => count( (array) ( $ds['classes'] ?? array() ) ),
			'pages'           => self::content_candidates(),
			'zip_available'   => self::zip_available(),
		);
	}

	public static function normalize_args( $args ) {
		$args = is_array( $args ) ? $args : array();
		$ids  = array();
		foreach ( (array) ( $args['content_ids'] ?? array() ) as $id ) {
			$id = absint( $id );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		$include_content = ! empty( $args['include_content'] ) || ! empty( $ids );
		$mode            = $args['mode'] ?? 'merge';
		return array(
			'include_templates' => array_key_exists( 'include_templates', $args ) ? ! empty( $args['include_templates'] ) : true,
			'include_content'   => $include_content,
			'include_media'     => array_key_exists( 'include_media', $args ) ? ! empty( $args['include_media'] ) : true,
			'content_ids'       => array_values( array_unique( $ids ) ),
			'mode'              => in_array( $mode, array( 'merge', 'replace' ), true ) ? $mode : 'merge',
		);
	}

	public static function templates() {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}
		$q   = new \WP_Query(
			array(
				'post_type'      => 'lb_template',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( (array) $q->posts as $p ) {
			$d = get_post_meta( $p->ID, '_lb_template_data', true );
			$d = is_string( $d ) ? json_decode( $d, true ) : $d;
			$key = (string) get_post_meta( $p->ID, '_lb_template_key', true );
			if ( $key === '' ) {
				$key = sanitize_title( $p->post_title );
			}
			$thumb = function_exists( 'get_post_thumbnail_id' ) ? absint( get_post_thumbnail_id( $p->ID ) ) : 0;
			$cats  = array();
			if ( class_exists( '\\CanvaslyLite\\Templates\\SavedTemplates' ) ) {
				$cats = \CanvaslyLite\Templates\SavedTemplates::category_slugs( $p->ID );
			}
			$row = array(
				'id'           => (int) $p->ID,
				'title'        => $p->post_title,
				'slug'         => $p->post_name,
				'key'          => $key,
				'type'         => get_post_meta( $p->ID, '_lb_template_type', true ) ?: 'page',
				'categories'   => $cats,
				'thumbnail_id' => $thumb,
				'document'     => is_array( $d ) ? $d : array(),
			);
			if ( class_exists( '\CanvaslyLite\Theme\Locations' ) ) {
				$row = \CanvaslyLite\Theme\Locations::with_export_locations( $row, (int) $p->ID, is_array( $d ) ? $d : array() );
			}
			$out[] = $row;
		}
		return $out;
	}

	public static function content_candidates( $limit = 50 ) {
		if ( class_exists( SiteNavigation::class ) ) {
			return SiteNavigation::pages();
		}
		return array();
	}

	public static function export_content( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( ! $ids ) {
			$ids = array();
			foreach ( self::content_candidates( self::MAX_CONTENT ) as $row ) {
				$id = absint( $row['id'] ?? 0 );
				if ( $id ) {
					$ids[] = $id;
				}
				if ( count( $ids ) >= self::MAX_CONTENT ) {
					break;
				}
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			$item = self::export_content_item( $id );
			if ( $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	public static function export_content_item( $id ) {
		$id = absint( $id );
		if ( ! $id || ! function_exists( 'get_post' ) ) {
			return null;
		}
		$p = get_post( $id );
		if ( ! $p || ! empty( $p->post_type ) && class_exists( Documents::class ) && ! Documents::supports( $p->post_type ) ) {
			return null;
		}
		if ( ! $p ) {
			return null;
		}
		$doc = class_exists( DocumentManager::class ) ? DocumentManager::get( $id ) : array();
		$thumb = function_exists( 'get_post_thumbnail_id' ) ? absint( get_post_thumbnail_id( $id ) ) : 0;
		$row = array(
			'id'                => $id,
			'title'             => $p->post_title,
			'slug'              => $p->post_name,
			'status'            => 'draft',
			'post_type'         => $p->post_type,
			'excerpt'           => (string) $p->post_excerpt,
			'featured_image_id' => $thumb,
			'document'          => is_array( $doc ) ? $doc : array(),
		);
		if ( class_exists( '\CanvaslyLite\Theme\Locations' ) ) {
			$row = \CanvaslyLite\Theme\Locations::with_export_locations( $row, $id, is_array( $doc ) ? $doc : array() );
		}
		return $row;
	}

	/**
	 * Collect attachment IDs referenced by a kit payload.
	 *
	 * @param array $kit
	 * @return int[]
	 */
	public static function collect_media_ids( $kit ) {
		$ids = array();
		self::walk_collect_ids( $kit, $ids );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		sort( $ids );
		return $ids;
	}

	private static function walk_collect_ids( $data, array &$ids ) {
		if ( ! is_array( $data ) ) {
			return;
		}
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && self::is_attachment_id_key( $key ) ) {
				if ( is_numeric( $value ) ) {
					$id = absint( $value );
					if ( $id ) {
						$ids[] = $id;
					}
				} elseif ( is_string( $value ) && $value !== '' ) {
					foreach ( preg_split( '/[,\s]+/', $value ) as $one ) {
						$id = absint( $one );
						if ( $id ) {
							$ids[] = $id;
						}
					}
				} elseif ( is_array( $value ) ) {
					foreach ( $value as $one ) {
						$id = absint( $one );
						if ( $id ) {
							$ids[] = $id;
						}
					}
				}
			} elseif ( is_string( $key ) && $key === 'ids' ) {
				foreach ( preg_split( '/[,\s]+/', is_array( $value ) ? implode( ',', $value ) : (string) $value ) as $one ) {
					$id = absint( $one );
					if ( $id ) {
						$ids[] = $id;
					}
				}
			}
			if ( is_array( $value ) ) {
				self::walk_collect_ids( $value, $ids );
			}
		}
	}

	public static function is_attachment_id_key( $key ) {
		$key = (string) $key;
		if ( $key === 'ids' || $key === 'featured_image_id' ) {
			return true;
		}
		if ( substr( $key, -3 ) !== '_id' ) {
			return false;
		}
		// Document/unit ids and template/component post ids are not media.
		if ( in_array( $key, array( 'id', 'post_id', 'component_id' ), true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Build a media index (and optional file copies) from attachment IDs.
	 *
	 * @param int[] $ids
	 * @return array<int,array>
	 */
	public static function media_index( $ids ) {
		$out   = array();
		$count = 0;
		foreach ( (array) $ids as $id ) {
			$id = absint( $id );
			if ( ! $id || $count >= self::MAX_MEDIA ) {
				continue;
			}
			$item = self::media_item( $id );
			if ( $item ) {
				$out[ $id ] = $item;
				$count++;
			}
		}
		return $out;
	}

	public static function media_item( $id ) {
		$id = absint( $id );
		if ( ! $id || ! function_exists( 'get_post_type' ) || get_post_type( $id ) !== 'attachment' ) {
			return null;
		}
		$file = function_exists( 'get_attached_file' ) ? get_attached_file( $id ) : '';
		$url  = function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $id ) : '';
		$name = $file && file_exists( $file ) ? basename( $file ) : ( $url ? basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) : '' );
		if ( $name === '' ) {
			$name = $id . '.bin';
		}
		$safe = preg_replace( '/[^a-zA-Z0-9._-]/', '_', $name );
		return array(
			'id'    => $id,
			'file'  => $id . '-' . $safe,
			'url'   => $url ? esc_url_raw( $url ) : '',
			'mime'  => function_exists( 'get_post_mime_type' ) ? (string) get_post_mime_type( $id ) : '',
			'title' => function_exists( 'get_the_title' ) ? (string) get_the_title( $id ) : '',
			'alt'   => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'path'  => ( $file && file_exists( $file ) ) ? $file : '',
		);
	}

	/**
	 * Remap attachment IDs and URLs throughout a kit payload or document.
	 *
	 * @param mixed $data
	 * @param array<int,int>    $id_map
	 * @param array<string,string> $url_map
	 * @return mixed
	 */
	public static function remap( $data, $id_map, $url_map = array() ) {
		if ( is_string( $data ) ) {
			if ( $url_map ) {
				$data = strtr( $data, $url_map );
			}
			return $data;
		}
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && self::is_attachment_id_key( $key ) ) {
				$out[ $key ] = self::remap_id_value( $value, $id_map );
			} elseif ( is_string( $key ) && $key === 'ids' ) {
				$out[ $key ] = self::remap_ids_list( $value, $id_map );
			} else {
				$out[ $key ] = self::remap( $value, $id_map, $url_map );
			}
		}
		return $out;
	}

	public static function remap_component_ids( $data, $id_map ) {
		if ( ! is_array( $data ) || ! $id_map ) {
			return $data;
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( $key === 'component_id' && ( is_numeric( $value ) || ( is_string( $value ) && $value !== '' && ctype_digit( $value ) ) ) ) {
				$old           = (int) $value;
				$out[ $key ] = isset( $id_map[ $old ] ) ? (int) $id_map[ $old ] : $old;
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = self::remap_component_ids( $value, $id_map );
			} else {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	public static function remap_id_value( $value, $id_map ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::remap_id_value( $v, $id_map );
			}
			return $out;
		}
		if ( is_numeric( $value ) ) {
			$old = (int) $value;
			if ( $old && isset( $id_map[ $old ] ) ) {
				return (int) $id_map[ $old ];
			}
			return $old;
		}
		if ( is_string( $value ) && $value !== '' && preg_match( '/^\d+(?:\s*,\s*\d+)*$/', $value ) ) {
			return self::remap_ids_list( $value, $id_map );
		}
		return $value;
	}

	public static function remap_ids_list( $value, $id_map ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $one ) {
				$old   = absint( $one );
				$out[] = ( $old && isset( $id_map[ $old ] ) ) ? (int) $id_map[ $old ] : $old;
			}
			return $out;
		}
		$parts = array();
		foreach ( preg_split( '/[,\s]+/', (string) $value ) as $one ) {
			if ( $one === '' ) {
				continue;
			}
			$old     = absint( $one );
			$parts[] = (string) ( ( $old && isset( $id_map[ $old ] ) ) ? (int) $id_map[ $old ] : $old );
		}
		return implode( ',', $parts );
	}

	/**
	 * Write a ZIP to a temp file.
	 *
	 * @param array $args
	 * @return array|\WP_Error {path, filename, manifest, bytes}
	 */
	public static function zip_available() {
		return true;
	}

	public static function write_zip( $args = array() ) {
		$args = self::normalize_args( $args );
		/** Fires before a kit ZIP is built. @param array $args */
		do_action( 'canvasly-lite/kit/before_export', $args );

		$kit   = self::payload( $args );
		$media = array();
		if ( $args['include_media'] ) {
			$media = self::media_index( self::collect_media_ids( $kit ) );
		}
		$manifest = self::manifest( $kit, $media, $args );
		$slug     = function_exists( 'sanitize_title' ) ? sanitize_title( (string) ( $manifest['site']['title'] ?? 'site' ) ) : 'site';
		if ( $slug === '' ) {
			$slug = 'site';
		}
		$filename = 'canvasly-lite-kit-' . $slug . '-' . gmdate( 'Ymd' ) . '.zip';
		$dir      = self::temp_dir( 'lb-kit-ex' );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		$path    = $dir . '/kit.zip';
		$entries = array(
			array( 'name' => 'manifest.json', 'data' => wp_json_encode( $manifest ) ),
			array( 'name' => 'kit.json', 'data' => wp_json_encode( $kit ) ),
		);
		$index = array();
		foreach ( $media as $item ) {
			$entry = $item;
			unset( $entry['path'] );
			$index[] = $entry;
			if ( ! empty( $item['path'] ) && is_readable( $item['path'] ) ) {
				$entries[] = array(
					'name' => 'media/' . $item['file'],
					'file' => $item['path'],
				);
			}
		}
		$entries[] = array( 'name' => 'media.json', 'data' => wp_json_encode( $index ) );
		$written   = self::zip_write( $path, $entries );
		if ( is_wp_error( $written ) ) {
			self::rmdir_tree( $dir );
			return $written;
		}

		if ( ! file_exists( $path ) ) {
			self::rmdir_tree( $dir );
			return new \WP_Error( 'zip_create', __( 'Could not create the kit ZIP.', 'canvasly-lite' ), array( 'status' => 500 ) );
		}
		return array(
			'path'     => $path,
			'dir'      => $dir,
			'filename' => $filename,
			'manifest' => $manifest,
			'bytes'    => filesize( $path ),
		);
	}

	/**
	 * Store a built ZIP for a short-lived REST download.
	 *
	 * @param array $args
	 * @return array|\WP_Error
	 */
	public static function publish_export( $args = array() ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'Only administrators can export a kit.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$built = self::write_zip( $args );
		if ( is_wp_error( $built ) ) {
			return $built;
		}
		$dest_dir = self::exports_dir();
		if ( is_wp_error( $dest_dir ) ) {
			self::cleanup_built( $built );
			return $dest_dir;
		}
		$token    = self::random_token();
		$dest     = $dest_dir . '/' . $token . '.zip';
		if ( ! \CanvaslyLite\Utils\Filesystem::move( $built['path'], $dest ) && ! @copy( $built['path'], $dest ) ) {
			self::cleanup_built( $built );
			return new \WP_Error( 'zip_store', __( 'Could not store the kit ZIP.', 'canvasly-lite' ), array( 'status' => 500 ) );
		}
		self::cleanup_built( $built );
		set_transient(
			self::TRANSIENT . $token,
			array(
				'path'     => $dest,
				'filename' => $built['filename'],
				'user'     => get_current_user_id(),
			),
			defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600
		);
		return array(
			'token'    => $token,
			'filename' => $built['filename'],
			'bytes'    => filesize( $dest ),
			'manifest' => $built['manifest'],
			'url'      => rest_url( 'canvasly-lite/v1/kit/download/' . $token ),
		);
	}

	public static function consume_export( $token ) {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token );
		if ( $token === '' ) {
			return null;
		}
		$data = get_transient( self::TRANSIENT . $token );
		if ( ! is_array( $data ) || empty( $data['path'] ) || ! is_readable( $data['path'] ) ) {
			return null;
		}
		if ( (int) ( $data['user'] ?? 0 ) !== (int) get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		return $data;
	}

	public static function stream_file( $path, $filename ) {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . self::safe_filename( $filename ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		if ( function_exists( 'ob_get_level' ) ) {
			while ( ob_get_level() ) {
				ob_end_clean();
			}
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary ZIP download of a locally generated kit.
		echo \CanvaslyLite\Utils\Filesystem::get_contents( $path );
	}

	/**
	 * Import a ZIP path or a kit.json / design-system JSON array.
	 *
	 * @param string|array $source Path, or already-decoded payload.
	 * @param string       $mode   merge|replace
	 * @param array        $args   {include_content:bool}
	 * @return array|\WP_Error
	 */
	public static function import( $source, $mode = 'merge', $args = array() ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'Only administrators can import a kit.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$mode = in_array( $mode, array( 'merge', 'replace' ), true ) ? $mode : 'merge';
		$args = is_array( $args ) ? $args : array();
		$include_content = array_key_exists( 'include_content', $args ) ? ! empty( $args['include_content'] ) : true;

		if ( is_array( $source ) ) {
			$result = self::import_payload( $source, $mode, $include_content );
		} elseif ( is_string( $source ) && is_readable( $source ) ) {
			$result = self::import_file( $source, $mode, $include_content );
		} else {
			return new \WP_Error( 'invalid_kit', __( 'The kit file could not be read.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		if ( ! is_wp_error( $result ) && class_exists( GlobalSettings::class ) ) {
			GlobalSettings::invalidate_css_cache();
		}
		/** Fires after a kit import attempt. @param array|\WP_Error $result @param string $mode */
		do_action( 'canvasly-lite/kit/after_import', $result, $mode );
		return $result;
	}

	public static function import_file( $path, $mode, $include_content ) {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( $ext === 'json' ) {
			$raw = file_get_contents( $path );
			$d   = json_decode( (string) $raw, true );
			if ( ! is_array( $d ) ) {
				return new \WP_Error( 'invalid_kit', __( 'The kit JSON is not valid.', 'canvasly-lite' ), array( 'status' => 400 ) );
			}
			return self::import_payload( $d, $mode, $include_content );
		}
		$files = self::zip_read( $path );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$dir = self::temp_dir( 'lb-kit-im' );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		foreach ( $files as $name => $contents ) {
			if ( ! self::safe_zip_name( $name ) ) {
				continue;
			}
			$target = $dir . '/' . str_replace( array( '\\', '/' ), '/', $name );
			$target_dir = dirname( $target );
			if ( ! is_dir( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}
			if ( substr( $name, -1 ) === '/' ) {
				continue;
			}
			file_put_contents( $target, $contents );
		}

		$manifest = self::read_json( $dir . '/manifest.json' );
		$kit      = self::read_json( $dir . '/kit.json' );
		if ( ! $kit ) {
			$kit = self::read_json( $dir . '/design-system.json' );
		}
		if ( ! $kit ) {
			self::rmdir_tree( $dir );
			return new \WP_Error( 'invalid_kit', __( 'The kit ZIP is missing kit.json.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		if ( $manifest && ( $manifest['type'] ?? '' ) !== '' && ( $manifest['type'] ?? '' ) !== self::TYPE ) {
			self::rmdir_tree( $dir );
			return new \WP_Error( 'invalid_kit', __( 'This ZIP is not a Canvasly kit.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$media = self::read_json( $dir . '/media.json' );
		if ( ! is_array( $media ) ) {
			$media = array();
		}
		$result = self::import_payload( $kit, $mode, $include_content, $media, $dir . '/media' );
		self::rmdir_tree( $dir );
		return $result;
	}

	/**
	 * @param array       $kit
	 * @param string      $mode
	 * @param bool        $include_content
	 * @param array       $media
	 * @param string|null $media_dir
	 * @return array|\WP_Error
	 */
	public static function import_payload( $kit, $mode = 'merge', $include_content = true, $media = array(), $media_dir = null ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'Only administrators can import a kit.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$kit  = is_array( $kit ) ? $kit : array();
		$mode = in_array( $mode, array( 'merge', 'replace' ), true ) ? $mode : 'merge';
		if ( isset( $kit['type'] ) && $kit['type'] !== self::TYPE && $kit['type'] !== 'canvasly-lite-design-system' && ! isset( $kit['variables'] ) && ! isset( $kit['schema'] ) ) {
			return new \WP_Error( 'invalid_kit', __( 'This file is not a Canvasly kit.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}

		$id_map  = array();
		$url_map = array();
		if ( $media ) {
			$imported = self::import_media( $media, $media_dir );
			if ( is_wp_error( $imported ) ) {
				return $imported;
			}
			$id_map  = $imported['ids'];
			$url_map = $imported['urls'];
		}
		if ( $id_map || $url_map ) {
			$kit = self::remap( $kit, $id_map, $url_map );
		}

		$components = isset( $kit['components'] ) && is_array( $kit['components'] ) ? $kit['components'] : array();
		$templates  = isset( $kit['templates'] ) && is_array( $kit['templates'] ) ? $kit['templates'] : array();
		$content    = isset( $kit['content'] ) && is_array( $kit['content'] ) ? $kit['content'] : array();
		unset( $kit['components'], $kit['templates'], $kit['content'] );

		if ( class_exists( DesignSystem::class ) ) {
			DesignSystem::import( $kit, $mode );
		}

		$comp_map = self::import_components( $components, $mode );
		if ( $comp_map ) {
			$templates = self::remap_component_ids( $templates, $comp_map );
			$content   = self::remap_component_ids( $content, $comp_map );
		}
		$tpl_count = self::import_templates( $templates, $mode );
		$pg_count  = 0;
		if ( $include_content && $content ) {
			$pg_count = self::import_content( $content, $mode );
		}

		$exported = class_exists( DesignSystem::class ) ? DesignSystem::export() : $kit;
		return array(
			'success'    => true,
			'mode'       => $mode,
			'media'      => count( $id_map ),
			'templates'  => $tpl_count,
			'components' => count( $comp_map ),
			'content'    => $pg_count,
			'kit'        => $exported,
		);
	}

	public static function import_media( $items, $media_dir ) {
		$ids  = array();
		$urls = array();
		if ( ! is_array( $items ) ) {
			return array(
				'ids'  => $ids,
				'urls' => $urls,
			);
		}
		self::require_media_includes();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$old = absint( $item['id'] ?? 0 );
			$new = self::sideload_media( $item, $media_dir );
			if ( ! $new ) {
				continue;
			}
			if ( $old ) {
				$ids[ $old ] = $new;
			}
			$old_url = esc_url_raw( (string) ( $item['url'] ?? '' ) );
			$new_url = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $new ) : '';
			if ( $old_url && $new_url ) {
				$urls[ $old_url ] = $new_url;
				if ( function_exists( 'untrailingslashit' ) ) {
					$urls[ untrailingslashit( $old_url ) ] = untrailingslashit( $new_url );
				}
			}
		}
		return array(
			'ids'  => $ids,
			'urls' => $urls,
		);
	}

	public static function sideload_media( $item, $media_dir ) {
		$file = (string) ( $item['file'] ?? '' );
		$path = '';
		if ( $file !== '' && $media_dir && is_readable( $media_dir . '/' . basename( $file ) ) ) {
			$path = $media_dir . '/' . basename( $file );
		}
		if ( $path === '' && $file !== '' && $media_dir && is_readable( $media_dir . '/' . $file ) ) {
			$path = $media_dir . '/' . $file;
		}
		if ( $path === '' && ! empty( $item['url'] ) && function_exists( 'download_url' ) ) {
			$tmp = download_url( esc_url_raw( $item['url'] ) );
			if ( ! is_wp_error( $tmp ) && is_readable( $tmp ) ) {
				$path = $tmp;
			}
		}
		if ( $path === '' || ! is_readable( $path ) ) {
			return 0;
		}
		$filename = basename( $file !== '' ? $file : $path );
		$filename = preg_replace( '/^\d+-/', '', $filename );
		$filename = sanitize_file_name( $filename );
		if ( $filename === '' ) {
			$filename = 'kit-media.bin';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			return 0;
		}
		$tmp = function_exists( 'wp_tempnam' ) ? wp_tempnam( $filename ) : ( $path . '.tmp' );
		if ( ! @copy( $path, $tmp ) ) {
			return 0;
		}
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file_array, 0, sanitize_text_field( (string) ( $item['title'] ?? '' ) ) );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}
		$id = absint( $id );
		if ( $id && ! empty( $item['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $item['alt'] ) );
		}
		if ( $id && ! empty( $item['id'] ) ) {
			update_post_meta( $id, self::SOURCE_META, absint( $item['id'] ) );
		}
		return $id;
	}

	public static function import_components( $items, $mode ) {
		$map = array();
		if ( ! is_array( $items ) || ! class_exists( Components::class ) ) {
			return $map;
		}
		if ( $mode === 'replace' && current_user_can( 'edit_pages' ) ) {
			// DesignSystem::import already deleted components on replace.
		}
		foreach ( $items as $c ) {
			if ( ! is_array( $c ) || empty( $c['title'] ) || empty( $c['document'] ) ) {
				continue;
			}
			$old = absint( $c['id'] ?? 0 );
			$id  = Components::save( $c['title'], $c['document'], $c['exposed'] ?? array(), 0, $c['key'] ?? '' );
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}
			$id = absint( $id );
			if ( $old ) {
				$map[ $old ] = $id;
			}
		}
		if ( $map ) {
			foreach ( $items as $c ) {
				if ( ! is_array( $c ) || empty( $c['document'] ) ) {
					continue;
				}
				$old = absint( $c['id'] ?? 0 );
				$new = $old && isset( $map[ $old ] ) ? $map[ $old ] : 0;
				if ( ! $new ) {
					continue;
				}
				$doc = self::remap_component_ids( $c['document'], $map );
				Components::save( $c['title'], $doc, $c['exposed'] ?? array(), $new, $c['key'] ?? '' );
			}
		}
		return $map;
	}

	public static function import_templates( $items, $mode ) {
		if ( ! is_array( $items ) ) {
			return 0;
		}
		if ( $mode === 'replace' && current_user_can( 'edit_pages' ) ) {
			self::delete_templates_for_replace();
		}
		$n = 0;
		foreach ( $items as $t ) {
			if ( ! is_array( $t ) || empty( $t['title'] ) ) {
				continue;
			}
			if ( self::save_template( $t ) ) {
				$n++;
			}
		}
		return $n;
	}

	public static function save_template( $item ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return 0;
		}
		$title = sanitize_text_field( $item['title'] ?? '' );
		if ( $title === '' ) {
			return 0;
		}
		$key  = sanitize_key( $item['key'] ?? '' );
		if ( $key === '' ) {
			$key = sanitize_title( $title );
		}
		$type = sanitize_key( $item['type'] ?? 'page' );
		$doc  = is_array( $item['document'] ?? null ) ? $item['document'] : array();
		$id   = 0;
		if ( $key && class_exists( '\WP_Query' ) ) {
			$q = new \WP_Query(
				array(
					'post_type'      => 'lb_template',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Kit import reuses an existing template by its stored key.
					'meta_key'       => '_lb_template_key',
					'meta_value'     => $key,
					// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( ! empty( $q->posts ) ) {
				$id = absint( $q->posts[0] );
			}
		}
		if ( $id ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $title,
				)
			);
		} else {
			$id = wp_insert_post(
				array(
					'post_type'   => 'lb_template',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_name'   => sanitize_title( $item['slug'] ?? $title ),
				)
			);
		}
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		update_post_meta( $id, '_lb_template_data', wp_json_encode( $doc ) );
		if ( class_exists( '\\CanvaslyLite\\Templates\\SavedTemplates' ) ) {
			$type = \CanvaslyLite\Templates\SavedTemplates::normalize_type( $type ?: 'page' );
		}
		update_post_meta( $id, '_lb_template_type', $type ?: 'page' );
		update_post_meta( $id, '_lb_template_key', $key );
		/** Fires after a kit template row is written. @param int $id @param array $item */
		do_action( 'canvasly-lite/kit/template_saved', absint( $id ), is_array( $item ) ? $item : array() );
		if ( class_exists( '\\CanvaslyLite\\Templates\\SavedTemplates' ) && ! empty( $item['categories'] ) ) {
			\CanvaslyLite\Templates\SavedTemplates::set_categories( $id, $item['categories'] );
		}
		$thumb = absint( $item['thumbnail_id'] ?? $item['featured_image_id'] ?? 0 );
		if ( $thumb && function_exists( 'set_post_thumbnail' ) ) {
			set_post_thumbnail( $id, $thumb );
		}
		return absint( $id );
	}

	public static function import_content( $items, $mode ) {
		if ( ! is_array( $items ) ) {
			return 0;
		}
		$n = 0;
		foreach ( $items as $item ) {
			if ( self::save_content_item( $item, $mode ) ) {
				$n++;
			}
		}
		return $n;
	}

	public static function save_content_item( $item, $mode ) {
		if ( ! is_array( $item ) ) {
			return 0;
		}
		$title = sanitize_text_field( $item['title'] ?? '' );
		if ( $title === '' ) {
			return 0;
		}
		$type = sanitize_key( $item['post_type'] ?? 'page' );
		if ( class_exists( Documents::class ) && ! Documents::supports( $type ) ) {
			$type = Documents::fallback();
		}
		if ( $type === '' ) {
			$type = 'page';
		}
		if ( ! current_user_can( class_exists( Documents::class ) ? Documents::edit_cap( $type ) : 'edit_pages' ) ) {
			return 0;
		}
		$slug = sanitize_title( $item['slug'] ?? $title );
		$id   = 0;
		if ( $mode === 'replace' && $slug && function_exists( 'get_page_by_path' ) ) {
			$existing = get_page_by_path( $slug, OBJECT, $type );
			if ( $existing ) {
				$id = (int) $existing->ID;
			}
		}
		if ( ! $id && $slug ) {
			$q = new \WP_Query(
				array(
					'post_type'      => $type,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Kit import matches a page by the kit source slug stored in post meta.
					'meta_key'       => self::SOURCE_META,
					'meta_value'     => $slug,
					// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( ! empty( $q->posts ) ) {
				$id = absint( $q->posts[0] );
			}
		}
		$postarr = array(
			'post_type'    => $type,
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_excerpt' => sanitize_textarea_field( $item['excerpt'] ?? '' ),
		);
		if ( $id ) {
			$postarr['ID'] = $id;
			wp_update_post( $postarr );
		} else {
			$id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		$id  = absint( $id );
		$doc = is_array( $item['document'] ?? null ) ? $item['document'] : array();
		if ( class_exists( DocumentManager::class ) && $doc ) {
			DocumentManager::save( $id, $doc );
		}
		update_post_meta( $id, self::SOURCE_META, $slug );
		$thumb = absint( $item['featured_image_id'] ?? 0 );
		if ( $thumb && function_exists( 'set_post_thumbnail' ) ) {
			set_post_thumbnail( $id, $thumb );
		}
		return $id;
	}

	/**
	 * Delete templates during a replace import.
	 *
	 * The default is every `lb_template`. `canvasly-lite/kit/replace_template_ids`
	 * can narrow that set. Ids outside the queried set are ignored, so the
	 * filter cannot delete pages or other post types.
	 */
	public static function delete_templates_for_replace() {
		if ( ! class_exists( '\WP_Query' ) ) {
			return;
		}
		$q   = new \WP_Query(
			array(
				'post_type'      => 'lb_template',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$ids = array();
		foreach ( (array) $q->posts as $post ) {
			$id = is_object( $post ) ? absint( $post->ID ?? 0 ) : absint( $post );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		/**
		 * Template ids a replace import may delete.
		 *
		 * @param int[] $ids All lb_template ids.
		 */
		$filtered = apply_filters( 'canvasly-lite/kit/replace_template_ids', $ids );
		if ( ! is_array( $filtered ) ) {
			$filtered = $ids;
		}
		$allowed = array_fill_keys( $ids, true );
		foreach ( $filtered as $post ) {
			$id = is_object( $post ) ? absint( $post->ID ?? 0 ) : absint( $post );
			if ( $id && isset( $allowed[ $id ] ) ) {
				wp_delete_post( $id, true );
			}
		}
	}

	public static function delete_all_of_type( $post_type ) {
		if ( ! class_exists( '\WP_Query' ) ) {
			return;
		}
		$q = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( (array) $q->posts as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public static function handle_export() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can export a kit.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_kit_export' );
		$ids = array();
		if ( isset( $_POST['content_ids'] ) ) {
			$ids = array_map( 'absint', (array) wp_unslash( $_POST['content_ids'] ) );
		}
		$built = self::write_zip(
			array(
				'include_templates' => ! empty( $_POST['include_templates'] ),
				'include_content'   => ! empty( $_POST['include_content'] ),
				'include_media'     => ! empty( $_POST['include_media'] ),
				'content_ids'       => $ids,
			)
		);
		if ( is_wp_error( $built ) ) {
			self::store_notice( 'error', $built->get_error_message() );
			wp_safe_redirect( self::tools_url() );
			exit;
		}
		self::stream_file( $built['path'], $built['filename'] );
		self::cleanup_built( $built );
		exit;
	}

	public static function handle_import() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can import a kit.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_kit_import' );
		$file = array(
			'name'     => isset( $_FILES['kit']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['kit']['name'] ) ) : '',
			'type'     => isset( $_FILES['kit']['type'] ) ? sanitize_mime_type( wp_unslash( $_FILES['kit']['type'] ) ) : '',
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a PHP upload path validated with is_uploaded_file().
			'tmp_name' => isset( $_FILES['kit']['tmp_name'] ) ? (string) $_FILES['kit']['tmp_name'] : '',
			'error'    => isset( $_FILES['kit']['error'] ) ? absint( $_FILES['kit']['error'] ) : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $_FILES['kit']['size'] ) ? absint( $_FILES['kit']['size'] ) : 0,
		);
		if ( $file['tmp_name'] === '' || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::store_notice( 'error', __( 'Choose a kit ZIP or JSON file to import.', 'canvasly-lite' ) );
			wp_safe_redirect( self::tools_url() );
			exit;
		}
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'merge' ) );
		$result = self::import(
			$file['tmp_name'],
			$mode,
			array( 'include_content' => ! empty( $_POST['include_content'] ) )
		);
		if ( is_wp_error( $result ) ) {
			self::store_notice( 'error', $result->get_error_message() );
		} else {
			self::store_notice(
				'success',
				sprintf(
					/* translators: 1: templates, 2: media items, 3: pages */
					__( 'Kit imported. %1$d templates, %2$d media items, %3$d pages.', 'canvasly-lite' ),
					(int) ( $result['templates'] ?? 0 ),
					(int) ( $result['media'] ?? 0 ),
					(int) ( $result['content'] ?? 0 )
				)
			);
		}
		wp_safe_redirect( self::tools_url() );
		exit;
	}

	public static function store_notice( $type, $message ) {
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
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) ) {
			if ( ! \CanvaslyLite\Settings\AdminSettings::is_ops_screen( $screen ) ) {
				return;
			}
		} elseif ( ! $screen || ( $screen->id ?? '' ) !== 'canvasly-lite_page_canvasly-lite-tools' ) {
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

	public static function tools_url() {
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) ) {
			return \CanvaslyLite\Settings\AdminSettings::tools_or_settings_url();
		}
		return admin_url( 'admin.php?page=canvasly-lite-tools' );
	}

	public static function render_forms() {
		$pages = self::content_candidates();
		$tab   = '';
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) && isset( $_GET['page'] ) && sanitize_key( wp_unslash( $_GET['page'] ) ) === \CanvaslyLite\Settings\AdminSettings::PAGE ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = 'tools';
		}

		echo '<h2>' . esc_html__( 'Export Kit', 'canvasly-lite' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_kit_export' );
		echo '<input type="hidden" name="action" value="lb_kit_export">';
		if ( $tab !== '' && class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) ) {
			\CanvaslyLite\Settings\AdminSettings::echo_return_tab( $tab );
		}
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Include', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="include_templates" value="1" checked> ' . esc_html__( 'Saved templates', 'canvasly-lite' ) . '</label><br>';
		echo '<label><input type="checkbox" name="include_media" value="1" checked> ' . esc_html__( 'Media files', 'canvasly-lite' ) . '</label><br>';
		echo '<label><input type="checkbox" name="include_content" value="1" id="lb-kit-include-content"> ' . esc_html__( 'Selected pages and posts', 'canvasly-lite' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Content', 'canvasly-lite' ) . '</th><td>';
		if ( $pages ) {
			echo '<fieldset style="max-height:220px;overflow:auto;border:1px solid #dcdcde;padding:8px 12px;max-width:480px">';
			foreach ( $pages as $p ) {
				echo '<label style="display:block;margin:3px 0;"><input type="checkbox" name="content_ids[]" value="' . esc_attr( (string) ( $p['id'] ?? 0 ) ) . '"> ' . esc_html( ( $p['title'] ?? '' ) . ' (' . ( $p['type'] ?? 'page' ) . ')' ) . '</label>';
			}
			echo '</fieldset>';
			echo '<p class="description">' . esc_html__( 'Leave unchecked to skip content, or tick Include selected pages and choose items. Imported pages are created as drafts.', 'canvasly-lite' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'No Canvasly pages were found.', 'canvasly-lite' ) . '</p>';
		}
		echo '</td></tr></tbody></table>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Download Kit ZIP', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';

		echo '<hr><h2>' . esc_html__( 'Import Kit', 'canvasly-lite' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		wp_nonce_field( 'lb_kit_import' );
		echo '<input type="hidden" name="action" value="lb_kit_import">';
		if ( $tab !== '' && class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) ) {
			\CanvaslyLite\Settings\AdminSettings::echo_return_tab( $tab );
		}
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="lb-kit-file">' . esc_html__( 'Kit file', 'canvasly-lite' ) . '</label></th><td>';
		echo '<input id="lb-kit-file" type="file" name="kit" accept=".zip,.json,application/zip,application/json" required>';
		echo '<p class="description">' . esc_html__( 'Accepts a Canvasly kit ZIP or a design-system JSON file.', 'canvasly-lite' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Conflict mode', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="radio" name="mode" value="merge" checked> ' . esc_html__( 'Merge — keep existing tokens and add incoming ones', 'canvasly-lite' ) . '</label><br>';
		echo '<label><input type="radio" name="mode" value="replace"> ' . esc_html__( 'Replace — overwrite site settings, tokens, classes, components and templates', 'canvasly-lite' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Content', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="include_content" value="1" checked> ' . esc_html__( 'Import pages included in the kit (as drafts)', 'canvasly-lite' ) . '</label>';
		echo '</td></tr></tbody></table>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Import Kit', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';
	}

	public static function screen() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can manage kits.', 'canvasly-lite' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Canvasly Tools', 'canvasly-lite' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Export or import a site kit: settings, design tokens, templates and optional content with media.', 'canvasly-lite' ) . '</p>';
		self::render_forms();
		/**
		 * Extra Tools sections (CSS print / Regenerate CSS, Replace URL, layout converter, …).
		 */
		do_action( 'canvasly-lite/tools/screen' );
		echo '</div>';
	}

	private static function read_json( $path ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$d = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $d ) ? $d : null;
	}

	private static function safe_zip_name( $name ) {
		$name = str_replace( '\\', '/', (string) $name );
		if ( $name === '' || strpos( $name, '..' ) !== false || isset( $name[0] ) && $name[0] === '/' ) {
			return false;
		}
		if ( ! preg_match( '/^(manifest\.json|kit\.json|design-system\.json|media\.json|media\/[^\/]+)$/', $name ) ) {
			return false;
		}
		return true;
	}

	private static function safe_filename( $name ) {
		$name = preg_replace( '/[^a-zA-Z0-9._-]/', '-', (string) $name );
		return $name !== '' ? $name : 'canvasly-lite-kit.zip';
	}

	private static function random_token() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 20, false, false );
		}
		return bin2hex( random_bytes( 10 ) );
	}

	private static function temp_dir( $prefix ) {
		$base = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
		$dir  = trailingslashit( $base ) . $prefix . '-' . self::random_token();
		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) ) {
			return new \WP_Error( 'temp_dir', __( 'Could not create a temporary folder for the kit.', 'canvasly-lite' ) );
		}
		return $dir;
	}

	private static function exports_dir() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return self::temp_dir( 'lb-kit-pub' );
		}
		$u = wp_upload_dir();
		if ( ! empty( $u['error'] ) ) {
			return new \WP_Error( 'upload_dir', (string) $u['error'] );
		}
		$dir = trailingslashit( $u['basedir'] ) . 'canvasly-lite/kits';
		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	public static function discard_export( $built ) {
		self::cleanup_built( $built );
	}

	private static function cleanup_built( $built ) {
		if ( ! empty( $built['path'] ) && file_exists( $built['path'] ) ) {
			wp_delete_file( $built['path'] );
		}
		if ( ! empty( $built['dir'] ) ) {
			self::rmdir_tree( $built['dir'] );
		}
	}

	private static function rmdir_tree( $dir ) {
		\CanvaslyLite\Utils\Filesystem::rmdir_tree( $dir );
	}

	/**
	 * Write a ZIP (ZipArchive when present, stored-method fallback otherwise).
	 *
	 * @param string $path
	 * @param array  $entries [ ['name'=>, 'data'=>] | ['name'=>, 'file'=>] ]
	 * @return true|\WP_Error
	 */
	public static function zip_write( $path, $entries ) {
		if ( class_exists( '\ZipArchive' ) ) {
			$zip = new \ZipArchive();
			if ( $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
				return new \WP_Error( 'zip_create', __( 'Could not create the kit ZIP.', 'canvasly-lite' ), array( 'status' => 500 ) );
			}
			foreach ( $entries as $entry ) {
				$name = (string) ( $entry['name'] ?? '' );
				if ( $name === '' ) {
					continue;
				}
				if ( isset( $entry['file'] ) && is_readable( $entry['file'] ) ) {
					$zip->addFile( $entry['file'], $name );
				} else {
					$zip->addFromString( $name, (string) ( $entry['data'] ?? '' ) );
				}
			}
			$zip->close();
			return true;
		}
		$records = array();
		$offset  = 0;
		$body    = '';
		foreach ( $entries as $entry ) {
			$name = str_replace( '\\', '/', (string) ( $entry['name'] ?? '' ) );
			if ( $name === '' ) {
				continue;
			}
			$data = isset( $entry['file'] ) && is_readable( $entry['file'] ) ? (string) file_get_contents( $entry['file'] ) : (string) ( $entry['data'] ?? '' );
			$crc  = crc32( $data ) & 0xffffffff;
			$len  = strlen( $data );
			$nlen = strlen( $name );
			$local = pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $len, $len, $nlen, 0 ) . $name . $data;
			$body .= $local;
			$records[] = array(
				'name'   => $name,
				'crc'    => $crc,
				'len'    => $len,
				'offset' => $offset,
			);
			$offset += strlen( $local );
		}
		$central = '';
		foreach ( $records as $r ) {
			$nlen     = strlen( $r['name'] );
			$central .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $r['crc'], $r['len'], $r['len'], $nlen, 0, 0, 0, 0, 0, $r['offset'] ) . $r['name'];
		}
		$eocd = pack( 'VvvvvVVv', 0x06054b50, 0, 0, count( $records ), count( $records ), strlen( $central ), $offset, 0 );
		if ( file_put_contents( $path, $body . $central . $eocd ) === false ) {
			return new \WP_Error( 'zip_create', __( 'Could not create the kit ZIP.', 'canvasly-lite' ), array( 'status' => 500 ) );
		}
		return true;
	}

	/**
	 * @param string $path
	 * @return array<string,string>|\WP_Error
	 */
	public static function zip_read( $path ) {
		if ( ! is_readable( $path ) ) {
			return new \WP_Error( 'invalid_kit', __( 'The kit ZIP could not be opened.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		if ( class_exists( '\ZipArchive' ) ) {
			$zip = new \ZipArchive();
			if ( $zip->open( $path ) !== true ) {
				return new \WP_Error( 'invalid_kit', __( 'The kit ZIP could not be opened.', 'canvasly-lite' ), array( 'status' => 400 ) );
			}
			$out = array();
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name = $zip->getNameIndex( $i );
				if ( $name === false || substr( $name, -1 ) === '/' ) {
					continue;
				}
				$data = $zip->getFromIndex( $i );
				if ( $data !== false ) {
					$out[ $name ] = $data;
				}
			}
			$zip->close();
			return $out;
		}
		$raw = file_get_contents( $path );
		if ( $raw === false ) {
			return new \WP_Error( 'invalid_kit', __( 'The kit ZIP could not be opened.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$out = array();
		$pos = 0;
		$len = strlen( $raw );
		while ( $pos + 30 <= $len ) {
			$sig = unpack( 'Vsig', substr( $raw, $pos, 4 ) );
			if ( ( $sig['sig'] ?? 0 ) !== 0x04034b50 ) {
				break;
			}
			$hdr = unpack( 'vver/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnamelen/vextralen', substr( $raw, $pos + 4, 26 ) );
			if ( ! $hdr ) {
				break;
			}
			$name_pos = $pos + 30;
			$name     = substr( $raw, $name_pos, $hdr['namelen'] );
			$data_pos = $name_pos + $hdr['namelen'] + $hdr['extralen'];
			$data     = substr( $raw, $data_pos, $hdr['csize'] );
			if ( (int) $hdr['method'] === 8 && function_exists( 'gzinflate' ) ) {
				$inflated = @gzinflate( $data );
				if ( $inflated !== false ) {
					$data = $inflated;
				}
			} elseif ( (int) $hdr['method'] !== 0 ) {
				$pos = $data_pos + $hdr['csize'];
				continue;
			}
			if ( $name !== '' && substr( $name, -1 ) !== '/' ) {
				$out[ $name ] = $data;
			}
			$pos = $data_pos + $hdr['csize'];
			if ( $hdr['flag'] & 8 ) {
				if ( $pos + 16 <= $len && unpack( 'Vsig', substr( $raw, $pos, 4 ) )['sig'] === 0x08074b50 ) {
					$pos += 16;
				} else {
					$pos += 12;
				}
			}
		}
		if ( ! $out ) {
			return new \WP_Error( 'invalid_kit', __( 'The kit ZIP could not be opened.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		return $out;
	}

	private static function require_media_includes() {
		if ( ! defined( 'ABSPATH' ) ) {
			return;
		}
		foreach ( array( 'wp-admin/includes/file.php', 'wp-admin/includes/media.php', 'wp-admin/includes/image.php' ) as $rel ) {
			$file = ABSPATH . $rel;
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	}
}
