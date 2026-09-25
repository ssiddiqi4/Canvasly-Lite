<?php
namespace CanvaslyLite\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Importer / Exporter compatibility for Canvasly JSON meta.
 *
 * WXR exports raw database values. On import, WordPress unslashes then
 * `add_post_meta()` unslashes again. JSON documents with escaped quotes must
 * be repaired so they still parse after a round trip.
 */
class ImportExport {
	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_filter( 'wxr_export_skip_postmeta', array( self::class, 'skip_export' ), 10, 2 );
		add_filter( 'wp_import_post_meta', array( self::class, 'filter_import_meta' ) );
		add_filter( 'wxr_importer.pre_process.post_meta', array( self::class, 'wxr_pre_process' ) );
		add_action( 'import_post_meta', array( self::class, 'after_import_meta' ), 10, 3 );
	}

	/**
	 * Drop regenerable / session meta from Tools → Export.
	 *
	 * @param bool   $skip
	 * @param string $meta_key
	 * @return bool
	 */
	public static function skip_export( $skip, $meta_key ) {
		if ( class_exists( Meta::class ) && Meta::is_ephemeral( $meta_key ) ) {
			return true;
		}
		return (bool) $skip;
	}

	/**
	 * Classic WordPress Importer: repair JSON and drop ephemeral keys.
	 *
	 * @param array $postmeta
	 * @return array
	 */
	public static function filter_import_meta( $postmeta ) {
		if ( ! is_array( $postmeta ) ) {
			return $postmeta;
		}
		$out = array();
		foreach ( $postmeta as $meta ) {
			if ( ! is_array( $meta ) ) {
				continue;
			}
			$key = (string) ( $meta['key'] ?? '' );
			if ( $key === '' || ( class_exists( Meta::class ) && Meta::is_ephemeral( $key ) ) ) {
				continue;
			}
			if ( class_exists( Meta::class ) && Meta::is_json_key( $key ) ) {
				$meta['value'] = Meta::repair_json( $meta['value'] ?? '' );
			}
			$out[] = $meta;
		}
		return $out;
	}

	/**
	 * WXR Importer 2.0 / WP-CLI: repair or skip one meta row.
	 *
	 * @param array|false $meta
	 * @return array|false
	 */
	public static function wxr_pre_process( $meta ) {
		if ( $meta === false || ! is_array( $meta ) ) {
			return $meta;
		}
		$key = (string) ( $meta['key'] ?? '' );
		if ( $key !== '' && class_exists( Meta::class ) && Meta::is_ephemeral( $key ) ) {
			return false;
		}
		if ( $key !== '' && class_exists( Meta::class ) && Meta::is_json_key( $key ) ) {
			$meta['value'] = Meta::repair_json( $meta['value'] ?? '' );
		}
		return $meta;
	}

	/**
	 * After a meta row is inserted, re-read and repair JSON if slashing broke it.
	 *
	 * @param int    $post_id
	 * @param string $key
	 * @param mixed  $value
	 */
	public static function after_import_meta( $post_id, $key, $value ) {
		$post_id = absint( $post_id );
		$key     = (string) $key;
		if ( ! $post_id || $key === '' || ! class_exists( Meta::class ) ) {
			return;
		}
		if ( Meta::is_ephemeral( $key ) ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		if ( ! Meta::is_json_key( $key ) ) {
			return;
		}
		$stored = get_post_meta( $post_id, $key, true );
		$fixed  = Meta::repair_json( $stored !== '' && $stored !== null && $stored !== false ? $stored : $value );
		if ( $fixed !== $stored ) {
			Meta::write( $post_id, $key, $fixed );
		}
	}
}
