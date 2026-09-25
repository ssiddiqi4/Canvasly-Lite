<?php
namespace CanvaslyLite\Templates;

use CanvaslyLite\Design\Kit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saved-template JSON / ZIP export and import with media download and URL remap.
 */
class TemplateIO {
	const SCHEMA = '1.0';
	const TYPE   = 'canvasly-lite-template';
	const MAX_MEDIA = 80;

	/**
	 * Build an export payload for one or more templates.
	 *
	 * @param int[] $ids
	 * @return array
	 */
	public static function payload( $ids ) {
		$ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		$tpls = array();
		foreach ( $ids as $id ) {
			$item = class_exists( SavedTemplates::class ) ? SavedTemplates::item( $id ) : array();
			if ( ! $item ) {
				continue;
			}
			unset( $item['edit_url'] );
			$tpls[] = $item;
		}
		$kit_like = array( 'templates' => $tpls );
		$media    = array();
		if ( class_exists( Kit::class ) ) {
			$media = Kit::media_index( Kit::collect_media_ids( $kit_like ) );
			foreach ( $media as &$item ) {
				unset( $item['path'] );
			}
			unset( $item );
		}
		$payload = array(
			'schema'      => self::SCHEMA,
			'type'        => self::TYPE,
			'generator'   => defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0',
			'exported_at' => function_exists( 'current_time' ) ? current_time( 'c' ) : gmdate( 'c' ),
			'site'        => array(
				'title' => function_exists( 'get_option' ) ? (string) get_option( 'blogname', '' ) : '',
				'url'   => function_exists( 'home_url' ) ? (string) home_url( '/' ) : '',
			),
			'templates'   => $tpls,
			'media'       => array_values( $media ),
		);
		/**
		 * Filter a template export payload.
		 *
		 * @param array $payload
		 * @param int[] $ids
		 */
		$filtered = apply_filters( 'canvasly-lite/templates/export_payload', $payload, $ids );
		return is_array( $filtered ) ? $filtered : $payload;
	}

	/**
	 * Stream a JSON or ZIP download for the given template ids.
	 *
	 * @param int[] $ids
	 */
	public static function stream( $ids ) {
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		$payload = self::payload( $ids );
		$media   = isset( $payload['media'] ) && is_array( $payload['media'] ) ? $payload['media'] : array();
		$has_files = false;
		if ( $media && class_exists( Kit::class ) ) {
			foreach ( $media as $item ) {
				$full = class_exists( Kit::class ) ? Kit::media_item( absint( $item['id'] ?? 0 ) ) : null;
				if ( $full && ! empty( $full['path'] ) && is_readable( $full['path'] ) ) {
					$has_files = true;
					break;
				}
			}
		}
		$slug = 'templates';
		if ( count( $ids ) === 1 && ! empty( $payload['templates'][0]['slug'] ) ) {
			$slug = sanitize_title( $payload['templates'][0]['slug'] );
		}
		if ( $slug === '' ) {
			$slug = 'templates';
		}

		if ( ! $has_files || ! class_exists( Kit::class ) ) {
			self::stream_json( $payload, 'canvasly-lite-template-' . $slug . '.json' );
			return;
		}

		$entries = array(
			array( 'name' => 'templates.json', 'data' => wp_json_encode( $payload ) ),
			array( 'name' => 'manifest.json', 'data' => wp_json_encode( self::manifest( $payload ) ) ),
		);
		$index   = array();
		foreach ( $media as $item ) {
			$full = Kit::media_item( absint( $item['id'] ?? 0 ) );
			if ( ! $full ) {
				continue;
			}
			$entry = $full;
			unset( $entry['path'] );
			$index[] = $entry;
			if ( ! empty( $full['path'] ) && is_readable( $full['path'] ) ) {
				$entries[] = array(
					'name' => 'media/' . $full['file'],
					'file' => $full['path'],
				);
			}
		}
		$entries[] = array( 'name' => 'media.json', 'data' => wp_json_encode( $index ) );
		$dir       = self::temp_dir( 'lb-tpl-ex' );
		if ( is_wp_error( $dir ) ) {
			self::stream_json( $payload, 'canvasly-lite-template-' . $slug . '.json' );
			return;
		}
		$path    = $dir . '/templates.zip';
		$written = Kit::zip_write( $path, $entries );
		if ( is_wp_error( $written ) || ! file_exists( $path ) ) {
			self::rmdir_tree( $dir );
			self::stream_json( $payload, 'canvasly-lite-template-' . $slug . '.json' );
			return;
		}
		$filename = 'canvasly-lite-template-' . $slug . '.zip';
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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary ZIP download of a locally generated template.
		echo \CanvaslyLite\Utils\Filesystem::get_contents( $path );
		self::rmdir_tree( $dir );
	}

	public static function manifest( $payload ) {
		return array(
			'schema'      => self::SCHEMA,
			'type'        => self::TYPE,
			'generator'   => $payload['generator'] ?? ( defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0' ),
			'exported_at' => $payload['exported_at'] ?? '',
			'counts'      => array(
				'templates' => count( (array) ( $payload['templates'] ?? array() ) ),
				'media'     => count( (array) ( $payload['media'] ?? array() ) ),
			),
		);
	}

	/**
	 * Import from a file path (JSON or ZIP).
	 *
	 * @param string $path
	 * @return array|\WP_Error
	 */
	public static function import( $path ) {
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			return new \WP_Error( 'invalid', __( 'The template file could not be read.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( $ext === 'json' ) {
			$d = json_decode( (string) file_get_contents( $path ), true );
			if ( ! is_array( $d ) ) {
				return new \WP_Error( 'invalid', __( 'The template JSON is not valid.', 'canvasly-lite' ), array( 'status' => 400 ) );
			}
			return self::import_payload( $d );
		}
		if ( ! class_exists( Kit::class ) ) {
			return new \WP_Error( 'invalid', __( 'ZIP import requires the kit importer.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$files = Kit::zip_read( $path );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$payload = null;
		foreach ( array( 'templates.json', 'kit.json', 'design-system.json' ) as $name ) {
			if ( isset( $files[ $name ] ) ) {
				$payload = json_decode( (string) $files[ $name ], true );
				if ( is_array( $payload ) ) {
					break;
				}
			}
		}
		if ( ! is_array( $payload ) ) {
			foreach ( $files as $name => $raw ) {
				if ( substr( (string) $name, -5 ) !== '.json' ) {
					continue;
				}
				$try = json_decode( (string) $raw, true );
				if ( is_array( $try ) && ( isset( $try['templates'] ) || isset( $try['document'] ) || ( $try['type'] ?? '' ) === self::TYPE ) ) {
					$payload = $try;
					break;
				}
			}
		}
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'invalid', __( 'The ZIP did not contain a Canvasly template file.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$media = isset( $payload['media'] ) && is_array( $payload['media'] ) ? $payload['media'] : array();
		if ( isset( $files['media.json'] ) ) {
			$listed = json_decode( (string) $files['media.json'], true );
			if ( is_array( $listed ) ) {
				$media = $listed;
			}
		}
		$media_dir = self::extract_media_dir( $files );
		return self::import_payload( $payload, $media, $media_dir );
	}

	/**
	 * @param array       $payload
	 * @param array       $media
	 * @param string|null $media_dir
	 * @return array|\WP_Error
	 */
	public static function import_payload( $payload, $media = array(), $media_dir = null ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot import templates.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$payload = self::normalize_incoming( $payload );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		if ( ! $media && isset( $payload['media'] ) && is_array( $payload['media'] ) ) {
			$media = $payload['media'];
		}

		$id_map  = array();
		$url_map = self::site_url_map( $payload['site']['url'] ?? '' );

		if ( class_exists( Kit::class ) ) {
			if ( $media ) {
				$imported = Kit::import_media( $media, $media_dir );
				if ( ! is_wp_error( $imported ) ) {
					$id_map  = $imported['ids'] ?? array();
					$url_map = array_merge( $url_map, $imported['urls'] ?? array() );
				}
			}
			$missing = self::collect_unmapped_urls( $payload['templates'], $url_map );
			if ( $missing ) {
				$downloaded = self::download_urls( $missing );
				$url_map    = array_merge( $url_map, $downloaded['urls'] );
				$id_map     = $id_map + $downloaded['ids'];
			}
			if ( $id_map || $url_map ) {
				$payload = Kit::remap( $payload, $id_map, $url_map );
			}
		}

		$n = 0;
		$created = array();
		foreach ( (array) ( $payload['templates'] ?? array() ) as $item ) {
			$id = self::save_item( $item );
			if ( $id ) {
				$n++;
				$created[] = $id;
			}
		}

		$result = array(
			'success'   => true,
			'templates' => $n,
			'media'     => count( $id_map ),
			'ids'       => $created,
		);
		/** Fires after templates are imported. @param array $result @param array $payload */
		do_action( 'canvasly-lite/templates/after_import', $result, $payload );
		return $result;
	}

	/**
	 * Accept single-template, multi-template, or kit-shaped JSON.
	 *
	 * @param array $d
	 * @return array|\WP_Error
	 */
	public static function normalize_incoming( $d ) {
		if ( ! is_array( $d ) ) {
			return new \WP_Error( 'invalid', __( 'The template JSON is not valid.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		if ( isset( $d['templates'] ) && is_array( $d['templates'] ) ) {
			return $d;
		}
		if ( isset( $d['title'] ) && ( isset( $d['document'] ) || isset( $d['root'] ) ) ) {
			$item = $d;
			if ( ! isset( $item['document'] ) && isset( $item['root'] ) ) {
				$item['document'] = array(
					'version'  => $item['version'] ?? '2.1',
					'root'     => $item['root'],
					'settings' => $item['settings'] ?? array(),
				);
			}
			return array(
				'schema'    => $d['schema'] ?? self::SCHEMA,
				'type'      => self::TYPE,
				'site'      => $d['site'] ?? array(),
				'templates' => array( $item ),
				'media'     => $d['media'] ?? array(),
			);
		}
		if ( isset( $d['root'] ) && is_array( $d['root'] ) ) {
			return array(
				'schema'    => self::SCHEMA,
				'type'      => self::TYPE,
				'templates' => array(
					array(
						'title'    => sanitize_text_field( $d['title'] ?? __( 'Imported Template', 'canvasly-lite' ) ),
						'type'     => $d['type'] ?? 'page',
						'document' => $d,
					),
				),
			);
		}
		return new \WP_Error( 'invalid', __( 'This file is not a Canvasly template.', 'canvasly-lite' ), array( 'status' => 400 ) );
	}

	/**
	 * @param array $item
	 * @return int
	 */
	public static function save_item( $item ) {
		if ( ! is_array( $item ) || empty( $item['title'] ) ) {
			return 0;
		}
		if ( class_exists( Kit::class ) ) {
			$id = Kit::save_template( $item );
			if ( $id && class_exists( SavedTemplates::class ) ) {
				if ( ! empty( $item['categories'] ) ) {
					SavedTemplates::set_categories( $id, $item['categories'] );
				}
				$thumb = absint( $item['thumbnail_id'] ?? $item['featured_image_id'] ?? 0 );
				if ( $thumb && function_exists( 'set_post_thumbnail' ) ) {
					set_post_thumbnail( $id, $thumb );
				}
			}
			return absint( $id );
		}
		if ( class_exists( SavedTemplates::class ) ) {
			$id = SavedTemplates::create( $item );
			return is_wp_error( $id ) ? 0 : absint( $id );
		}
		return 0;
	}

	public static function site_url_map( $old ) {
		$old = esc_url_raw( (string) $old );
		$new = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		if ( $old === '' || $new === '' || $old === $new ) {
			return array();
		}
		$map = array( $old => $new );
		if ( function_exists( 'untrailingslashit' ) ) {
			$map[ untrailingslashit( $old ) ] = untrailingslashit( $new );
		}
		return $map;
	}

	/**
	 * HTTP(S) URLs in the payload that were not remapped from the media index.
	 *
	 * @param mixed $data
	 * @param array $url_map
	 * @return string[]
	 */
	public static function collect_unmapped_urls( $data, $url_map = array() ) {
		$found = self::collect_urls( $data );
		$out   = array();
		$mapped = array_keys( $url_map );
		foreach ( $found as $url ) {
			if ( in_array( $url, $mapped, true ) ) {
				continue;
			}
			if ( ! self::looks_like_media_url( $url ) ) {
				continue;
			}
			$out[] = $url;
		}
		return array_values( array_unique( $out ) );
	}

	public static function collect_urls( $data ) {
		$out = array();
		if ( is_string( $data ) ) {
			if ( preg_match_all( '#https?://[^\s"\'<>\\\\]+#', $data, $m ) ) {
				foreach ( $m[0] as $u ) {
					$u = esc_url_raw( rtrim( $u, '.,);' ) );
					if ( $u !== '' ) {
						$out[] = $u;
					}
				}
			}
			return $out;
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $v ) {
				$out = array_merge( $out, self::collect_urls( $v ) );
			}
		}
		return $out;
	}

	public static function looks_like_media_url( $url ) {
		$path = (string) ( wp_parse_url( $url, PHP_URL_PATH ) );
		return (bool) preg_match( '/\.(png|jpe?g|gif|webp|svg|avif|mp4|webm|mp3|wav|pdf)(\?|$)/i', $path );
	}

	/**
	 * Download remote media URLs and sideload them.
	 *
	 * @param string[] $urls
	 * @return array{ids: array<int,int>, urls: array<string,string>}
	 */
	public static function download_urls( $urls ) {
		$ids  = array();
		$map  = array();
		$n    = 0;
		if ( ! function_exists( 'download_url' ) || ! class_exists( Kit::class ) ) {
			return array(
				'ids'  => $ids,
				'urls' => $map,
			);
		}
		foreach ( (array) $urls as $url ) {
			if ( $n >= self::MAX_MEDIA ) {
				break;
			}
			$url = esc_url_raw( (string) $url );
			if ( $url === '' ) {
				continue;
			}
			$item = array(
				'url'  => $url,
				'file' => basename( (string) ( wp_parse_url( $url, PHP_URL_PATH ) ) ),
			);
			$new  = Kit::sideload_media( $item, null );
			if ( ! $new ) {
				continue;
			}
			$new_url = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $new ) : '';
			if ( $new_url ) {
				$map[ $url ] = $new_url;
				if ( function_exists( 'untrailingslashit' ) ) {
					$map[ untrailingslashit( $url ) ] = untrailingslashit( $new_url );
				}
			}
			$n++;
		}
		return array(
			'ids'  => $ids,
			'urls' => $map,
		);
	}

	/**
	 * Persist a canvas PNG/JPEG data URL as an attachment.
	 *
	 * @param string $data_url
	 * @param int    $parent
	 * @return int|\WP_Error
	 */
	public static function save_data_url_image( $data_url, $parent = 0 ) {
		$data_url = (string) $data_url;
		if ( ! preg_match( '#^data:image/(png|jpe?g|webp);base64,(.+)$#s', $data_url, $m ) ) {
			return new \WP_Error( 'invalid', __( 'The thumbnail image is not a valid PNG or JPEG.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$ext  = strtolower( $m[1] ) === 'jpeg' ? 'jpg' : strtolower( $m[1] );
		$bin  = base64_decode( $m[2], true );
		if ( $bin === false || strlen( $bin ) < 32 ) {
			return new \WP_Error( 'invalid', __( 'The thumbnail image could not be decoded.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		if ( strlen( $bin ) > 2 * 1024 * 1024 ) {
			return new \WP_Error( 'invalid', __( 'The thumbnail is too large.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$filename = 'lb-template-thumb-' . absint( $parent ) . '-' . gmdate( 'YmdHis' ) . '.' . $ext;
		if ( function_exists( 'wp_upload_bits' ) ) {
			$upload = wp_upload_bits( $filename, null, $bin );
			if ( ! empty( $upload['error'] ) ) {
				return new \WP_Error( 'upload', (string) $upload['error'], array( 'status' => 500 ) );
			}
			$file = $upload['file'];
			$url  = $upload['url'];
		} else {
			return new \WP_Error( 'upload', __( 'Could not store the thumbnail.', 'canvasly-lite' ), array( 'status' => 500 ) );
		}
		$mime = $ext === 'png' ? 'image/png' : ( $ext === 'webp' ? 'image/webp' : 'image/jpeg' );
		$id   = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => sanitize_file_name( $filename ),
				'post_status'    => 'inherit',
				'guid'           => $url,
			),
			$file,
			absint( $parent )
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return $id ? $id : new \WP_Error( 'upload', __( 'Could not store the thumbnail.', 'canvasly-lite' ), array( 'status' => 500 ) );
		}
		if ( function_exists( 'wp_generate_attachment_metadata' ) && function_exists( 'wp_update_attachment_metadata' ) ) {
			if ( ! function_exists( 'wp_read_image_metadata' ) ) {
				foreach ( array( 'wp-admin/includes/file.php', 'wp-admin/includes/media.php', 'wp-admin/includes/image.php' ) as $rel ) {
					$inc = defined( 'ABSPATH' ) ? ABSPATH . $rel : '';
					if ( $inc && is_readable( $inc ) ) {
						require_once $inc;
					}
				}
			}
			$meta = wp_generate_attachment_metadata( $id, $file );
			if ( is_array( $meta ) ) {
				wp_update_attachment_metadata( $id, $meta );
			}
		}
		return absint( $id );
	}

	private static function stream_json( $payload, $filename ) {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . self::safe_filename( $filename ) . '"' );
		if ( function_exists( 'ob_get_level' ) ) {
			while ( ob_get_level() ) {
				ob_end_clean();
			}
		}
		echo wp_json_encode( $payload );
	}

	private static function extract_media_dir( $files ) {
		$dir = self::temp_dir( 'lb-tpl-media' );
		if ( is_wp_error( $dir ) ) {
			return null;
		}
		$n = 0;
		foreach ( $files as $name => $data ) {
			$name = str_replace( '\\', '/', (string) $name );
			if ( strpos( $name, 'media/' ) !== 0 || strpos( $name, '..' ) !== false ) {
				continue;
			}
			$base = basename( $name );
			if ( $base === '' || $base === 'media.json' ) {
				continue;
			}
			file_put_contents( $dir . '/' . $base, $data );
			$n++;
		}
		return $n ? $dir : null;
	}

	private static function temp_dir( $prefix ) {
		$base = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 8, false, false ) : bin2hex( random_bytes( 4 ) );
		$dir   = trailingslashit( $base ) . $prefix . '-' . $token;
		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $dir );
		} else {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) ) {
			return new \WP_Error( 'temp_dir', __( 'Could not create a temporary folder.', 'canvasly-lite' ) );
		}
		return $dir;
	}

	private static function rmdir_tree( $dir ) {
		\CanvaslyLite\Utils\Filesystem::rmdir_tree( $dir );
	}

	private static function safe_filename( $name ) {
		$name = preg_replace( '/[^a-zA-Z0-9._-]/', '-', (string) $name );
		return $name !== '' ? $name : 'canvasly-lite-template.json';
	}
}
