<?php
namespace CanvaslyLite\Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rotating file logger (Roadmap 7.4).
 *
 * Writes `uploads/canvasly-lite/logs/canvasly-lite.log` with debug/info/notice/warning/error
 * levels. Files rotate at 1 MB and the last five archives are kept.
 */
class Logger {
	const DEBUG   = 'debug';
	const INFO    = 'info';
	const NOTICE  = 'notice';
	const WARNING = 'warning';
	const ERROR   = 'error';

	const FILE      = 'canvasly-lite.log';
	const SUBDIR    = 'canvasly-lite/logs';
	const MAX_BYTES = 1048576;
	const KEEP      = 5;

	/**
	 * @return array<string,int>
	 */
	public static function levels() {
		return array(
			self::DEBUG   => 0,
			self::INFO    => 1,
			self::NOTICE  => 2,
			self::WARNING => 3,
			self::ERROR   => 4,
		);
	}

	/**
	 * Minimum level that is written. `WP_DEBUG` defaults to debug; otherwise info.
	 *
	 * @return string
	 */
	public static function threshold() {
		$default = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? self::DEBUG : self::INFO;
		if ( defined( 'CANVASLY_LITE_LOG_LEVEL' ) && is_string( CANVASLY_LITE_LOG_LEVEL ) ) {
			$default = strtolower( CANVASLY_LITE_LOG_LEVEL );
		}
		/**
		 * Filter the minimum log level (`debug|info|notice|warning|error`).
		 *
		 * @param string $default
		 */
		$level = apply_filters( 'canvasly-lite/logger/level', $default );
		$level = is_string( $level ) ? strtolower( $level ) : $default;
		$all   = self::levels();
		return isset( $all[ $level ] ) ? $level : self::INFO;
	}

	/**
	 * @param string $level
	 * @return bool
	 */
	public static function should_log( $level ) {
		$all   = self::levels();
		$level = strtolower( (string) $level );
		if ( ! isset( $all[ $level ] ) ) {
			return false;
		}
		return $all[ $level ] >= $all[ self::threshold() ];
	}

	/**
	 * Absolute directory for log files.
	 *
	 * @return string
	 */
	public static function dir() {
		$dir = '';
		if ( function_exists( 'wp_upload_dir' ) ) {
			$up = wp_upload_dir();
			if ( empty( $up['error'] ) && ! empty( $up['basedir'] ) ) {
				$dir = trailingslashit( $up['basedir'] ) . self::SUBDIR;
			}
		}
		/**
		 * Filter the log directory.
		 *
		 * @param string $dir
		 */
		$filtered = apply_filters( 'canvasly-lite/logger/dir', $dir );
		return is_string( $filtered ) && $filtered !== '' ? untrailingslashit( $filtered ) : untrailingslashit( $dir );
	}

	/**
	 * @return string
	 */
	public static function path() {
		$dir = self::dir();
		return $dir === '' ? '' : $dir . '/' . self::FILE;
	}

	/**
	 * @return int
	 */
	public static function keep() {
		$n = self::KEEP;
		/**
		 * Filter how many rotated log files to keep.
		 *
		 * @param int $n
		 */
		$filtered = apply_filters( 'canvasly-lite/logger/keep', $n );
		return max( 1, min( 20, absint( $filtered ) ) );
	}

	/**
	 * @return int
	 */
	public static function max_bytes() {
		$n = self::MAX_BYTES;
		/**
		 * Filter the log rotation size in bytes.
		 *
		 * @param int $n
		 */
		$filtered = apply_filters( 'canvasly-lite/logger/max_bytes', $n );
		return max( 1024, absint( $filtered ) );
	}

	/**
	 * @return bool
	 */
	public static function ensure_dir() {
		$dir = self::dir();
		if ( $dir === '' ) {
			return false;
		}
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
			return false;
		}
		$index = $dir . '/index.php';
		if ( ! is_file( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		$deny = $dir . '/.htaccess';
		if ( ! is_file( $deny ) ) {
			@file_put_contents(
				$deny,
				"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
			);
		}
		return true;
	}

	/**
	 * @param string               $level
	 * @param string               $message
	 * @param array<string,mixed>  $context
	 * @return bool
	 */
	public static function log( $level, $message, $context = array() ) {
		$level   = strtolower( (string) $level );
		$message = trim( (string) $message );
		if ( $message === '' || ! self::should_log( $level ) ) {
			return false;
		}
		/**
		 * Filter whether a log line should be written.
		 *
		 * @param bool   $write
		 * @param string $level
		 * @param string $message
		 * @param array  $context
		 */
		$write = apply_filters( 'canvasly-lite/logger/write', true, $level, $message, $context );
		if ( ! $write ) {
			return false;
		}
		if ( ! self::ensure_dir() ) {
			return false;
		}
		$path = self::path();
		if ( $path === '' ) {
			return false;
		}
		$line = self::format_line( $level, $message, is_array( $context ) ? $context : array() );
		self::rotate_if_needed( $path, strlen( $line ) );
		$ok = @file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
		return $ok !== false;
	}

	public static function debug( $message, $context = array() ) {
		return self::log( self::DEBUG, $message, $context );
	}

	public static function info( $message, $context = array() ) {
		return self::log( self::INFO, $message, $context );
	}

	public static function notice( $message, $context = array() ) {
		return self::log( self::NOTICE, $message, $context );
	}

	public static function warning( $message, $context = array() ) {
		return self::log( self::WARNING, $message, $context );
	}

	public static function error( $message, $context = array() ) {
		return self::log( self::ERROR, $message, $context );
	}

	/**
	 * Last N lines of the current log (and the newest rotate file if needed).
	 *
	 * @param int $lines
	 * @return string
	 */
	public static function tail( $lines = 200 ) {
		$lines = max( 1, min( 2000, absint( $lines ) ) );
		$path  = self::path();
		$text  = '';
		if ( $path !== '' && is_readable( $path ) ) {
			$text = self::read_tail_bytes( $path );
		}
		if ( $text === '' ) {
			$prev = self::dir() . '/canvasly-lite-1.log';
			if ( is_readable( $prev ) ) {
				$text = self::read_tail_bytes( $prev );
			}
		}
		if ( $text === '' ) {
			return '';
		}
		$parts = preg_split( "/\r\n|\n|\r/", $text );
		if ( ! is_array( $parts ) ) {
			return $text;
		}
		while ( $parts && $parts[ count( $parts ) - 1 ] === '' ) {
			array_pop( $parts );
		}
		if ( count( $parts ) > $lines ) {
			$parts = array_slice( $parts, -$lines );
		}
		return implode( "\n", $parts );
	}

	/**
	 * @return bool
	 */
	public static function clear() {
		$dir = self::dir();
		if ( $dir === '' || ! is_dir( $dir ) ) {
			return true;
		}
		$files = glob( $dir . '/canvasly-lite*.log' );
		if ( ! is_array( $files ) ) {
			return true;
		}
		$ok = true;
		foreach ( $files as $file ) {
			if ( is_file( $file ) && ! \CanvaslyLite\Utils\Filesystem::delete_file( $file ) ) {
				$ok = false;
			}
		}
		return $ok;
	}

	/**
	 * @return int
	 */
	public static function size() {
		$path = self::path();
		if ( $path === '' || ! is_file( $path ) ) {
			return 0;
		}
		$n = @filesize( $path );
		return $n ? (int) $n : 0;
	}

	/**
	 * @param string               $level
	 * @param string               $message
	 * @param array<string,mixed>  $context
	 * @return string
	 */
	public static function format_line( $level, $message, $context = array() ) {
		$time = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		$line = '[' . $time . '] ' . strtoupper( (string) $level ) . ' ' . self::one_line( $message );
		if ( $context ) {
			$json = self::encode_context( $context );
			if ( $json !== '' ) {
				$line .= ' ' . $json;
			}
		}
		return $line . "\n";
	}

	/**
	 * @param string $path
	 * @param int    $extra
	 */
	public static function rotate_if_needed( $path, $extra = 0 ) {
		if ( $path === '' || ! is_file( $path ) ) {
			return;
		}
		$size = (int) @filesize( $path );
		if ( $size + absint( $extra ) < self::max_bytes() ) {
			return;
		}
		self::rotate();
	}

	public static function rotate() {
		$dir = self::dir();
		if ( $dir === '' ) {
			return;
		}
		$keep = self::keep();
		$base = $dir . '/canvasly-lite';
		$last = $base . '-' . $keep . '.log';
		if ( is_file( $last ) ) {
			wp_delete_file( $last );
		}
		for ( $i = $keep - 1; $i >= 1; $i-- ) {
			$src = $base . '-' . $i . '.log';
			$dst = $base . '-' . ( $i + 1 ) . '.log';
			if ( is_file( $src ) ) {
				\CanvaslyLite\Utils\Filesystem::move( $src, $dst );
			}
		}
		$current = $base . '.log';
		if ( is_file( $current ) ) {
			\CanvaslyLite\Utils\Filesystem::move( $current, $base . '-1.log' );
		}
	}

	/**
	 * @param string $message
	 * @return string
	 */
	private static function one_line( $message ) {
		$s = preg_replace( '/\s+/', ' ', (string) $message );
		return is_string( $s ) ? $s : (string) $message;
	}

	/**
	 * @param array<string,mixed> $context
	 * @return string
	 */
	private static function encode_context( $context ) {
		$clean = array();
		foreach ( $context as $k => $v ) {
			$key = is_string( $k ) ? $k : (string) $k;
			if ( is_scalar( $v ) || $v === null ) {
				$clean[ $key ] = $v;
			} elseif ( is_array( $v ) ) {
				$clean[ $key ] = $v;
			} else {
				$clean[ $key ] = gettype( $v );
			}
		}
		$flags = JSON_UNESCAPED_SLASHES;
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $clean, $flags ) : json_encode( $clean, $flags );
		if ( ! is_string( $json ) ) {
			return '';
		}
		if ( strlen( $json ) > 2048 ) {
			$json = substr( $json, 0, 2045 ) . '...';
		}
		return $json;
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private static function read_tail_bytes( $path ) {
		$size = (int) @filesize( $path );
		if ( $size < 1 ) {
			return '';
		}
		$all = \CanvaslyLite\Utils\Filesystem::get_contents( $path );
		if ( $all === '' ) {
			return '';
		}
		$read = min( $size, 65536 );
		return substr( $all, -$read );
	}
}
