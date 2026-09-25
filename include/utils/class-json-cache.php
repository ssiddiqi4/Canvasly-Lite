<?php
namespace CanvaslyLite\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cached JSON helpers: plugin data files and stored document strings.
 *
 * File reads use an in-request map, then the persistent object cache when
 * one is available, then a transient for small files. Disk is only hit on
 * a miss or when the file mtime/size change.
 */
class JsonCache {
	const GROUP            = 'canvasly_lite_json';
	const TRANSIENT_PREFIX = 'lb_json_';
	const TRANSIENT_MAX    = 262144;

	/** @var array<string,array> */
	private static $files = array();

	/**
	 * Decode a JSON string or pass through an already-decoded array.
	 *
	 * @param mixed $raw
	 * @param mixed $default
	 * @return mixed
	 */
	public static function decode( $raw, $default = array() ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || $raw === '' ) {
			return $default;
		}
		$decoded = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			return $default;
		}
		return $decoded;
	}

	/**
	 * Read and decode a local JSON file.
	 *
	 * @param string $path Absolute path.
	 * @return array
	 */
	public static function read( $path ) {
		$path = self::normalize_path( $path );
		if ( $path === '' ) {
			return array();
		}
		if ( array_key_exists( $path, self::$files ) ) {
			return self::$files[ $path ];
		}

		$stat = self::stat( $path );
		if ( ! $stat ) {
			self::$files[ $path ] = array();
			return array();
		}

		$cached = self::cached_payload( $path );
		if ( is_array( $cached ) && self::payload_fresh( $cached, $stat ) ) {
			$data                 = is_array( $cached['data'] ?? null ) ? $cached['data'] : array();
			self::$files[ $path ] = $data;
			return $data;
		}

		$data = self::decode_file( $path );
		self::$files[ $path ] = $data;
		self::store_payload(
			$path,
			array(
				'mtime' => $stat['mtime'],
				'size'  => $stat['size'],
				'data'  => $data,
			),
			$stat['size']
		);
		return $data;
	}

	/**
	 * Drop runtime and persistent file caches.
	 *
	 * @param string $path Empty to flush everything.
	 */
	public static function flush( $path = '' ) {
		$path = self::normalize_path( $path );
		if ( $path === '' ) {
			self::$files = array();
			return;
		}
		unset( self::$files[ $path ] );
		$key = self::cache_key( $path );
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $key, self::GROUP );
		}
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::TRANSIENT_PREFIX . $key );
		}
	}

	/** Drop the in-request file map only. */
	public static function flush_runtime() {
		self::$files = array();
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private static function normalize_path( $path ) {
		$path = (string) $path;
		if ( $path === '' ) {
			return '';
		}
		return str_replace( '\\', '/', $path );
	}

	/**
	 * @param string $path
	 * @return array{mtime:int,size:int}|null
	 */
	private static function stat( $path ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$mtime = @filemtime( $path );
		$size  = @filesize( $path );
		if ( $mtime === false || $size === false ) {
			return null;
		}
		return array(
			'mtime' => (int) $mtime,
			'size'  => (int) $size,
		);
	}

	/**
	 * @param string $path
	 * @return array
	 */
	private static function decode_file( $path ) {
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin data file
		if ( ! is_string( $raw ) || $raw === '' ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			return array();
		}
		return $decoded;
	}

	/**
	 * @param string $path
	 * @return array|null
	 */
	private static function cached_payload( $path ) {
		$key = self::cache_key( $path );
		if ( self::using_object_cache() && function_exists( 'wp_cache_get' ) ) {
			$hit = wp_cache_get( $key, self::GROUP );
			if ( is_array( $hit ) ) {
				return $hit;
			}
			return null;
		}
		if ( function_exists( 'get_transient' ) ) {
			$hit = get_transient( self::TRANSIENT_PREFIX . $key );
			if ( is_array( $hit ) ) {
				return $hit;
			}
		}
		return null;
	}

	/**
	 * @param string $path
	 * @param array  $payload
	 * @param int    $size
	 */
	private static function store_payload( $path, array $payload, $size ) {
		$key = self::cache_key( $path );
		$ttl = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		if ( self::using_object_cache() && function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( $key, $payload, self::GROUP, $ttl );
			return;
		}
		if ( (int) $size > self::TRANSIENT_MAX ) {
			return;
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::TRANSIENT_PREFIX . $key, $payload, $ttl );
		}
	}

	/**
	 * @param array $payload
	 * @param array $stat
	 * @return bool
	 */
	private static function payload_fresh( array $payload, array $stat ) {
		return (int) ( $payload['mtime'] ?? 0 ) === (int) $stat['mtime']
			&& (int) ( $payload['size'] ?? -1 ) === (int) $stat['size']
			&& is_array( $payload['data'] ?? null );
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private static function cache_key( $path ) {
		$ver = defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '';
		return substr( md5( $ver . '|' . $path ), 0, 32 );
	}

	/** @return bool */
	private static function using_object_cache() {
		return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
	}
}
