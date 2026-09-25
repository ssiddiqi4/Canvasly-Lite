<?php
namespace CanvaslyLite\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Filesystem wrappers so Plugin Check does not flag direct PHP file calls.
 */
class Filesystem {
	/**
	 * @return object|null
	 */
	public static function wp() {
		global $wp_filesystem;
		if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) ) {
			return $wp_filesystem;
		}
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			if ( defined( 'ABSPATH' ) ) {
				$file = ABSPATH . 'wp-admin/includes/file.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		}
		if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() ) {
			return ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) ) ? $wp_filesystem : null;
		}
		return null;
	}

	/**
	 * @param string $dir
	 * @return bool
	 */
	public static function mkdir( $dir ) {
		if ( function_exists( 'wp_mkdir_p' ) ) {
			return (bool) wp_mkdir_p( $dir );
		}
		$fs = self::wp();
		return $fs && method_exists( $fs, 'mkdir' ) ? (bool) $fs->mkdir( $dir ) : false;
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public static function is_writable( $path ) {
		if ( function_exists( 'wp_is_writable' ) ) {
			return (bool) wp_is_writable( $path );
		}
		$fs = self::wp();
		return $fs && method_exists( $fs, 'is_writable' ) ? (bool) $fs->is_writable( $path ) : false;
	}

	/**
	 * @param string $path
	 * @return bool True when the file is gone.
	 */
	public static function delete_file( $path ) {
		if ( $path === '' || ! is_file( $path ) ) {
			return true;
		}
		if ( function_exists( 'wp_delete_file' ) ) {
			wp_delete_file( $path );
			return ! is_file( $path );
		}
		$fs = self::wp();
		if ( $fs && method_exists( $fs, 'delete' ) ) {
			$fs->delete( $path, false, 'f' );
			return ! is_file( $path );
		}
		return ! is_file( $path );
	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @return bool
	 */
	public static function move( $source, $destination ) {
		$fs = self::wp();
		if ( $fs && method_exists( $fs, 'move' ) ) {
			return (bool) $fs->move( $source, $destination, true );
		}
		if ( function_exists( 'copy' ) && @copy( $source, $destination ) ) {
			self::delete_file( $source );
			return is_file( $destination );
		}
		return false;
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public static function get_contents( $path ) {
		$fs = self::wp();
		if ( $fs && method_exists( $fs, 'get_contents' ) ) {
			$data = $fs->get_contents( $path );
			return is_string( $data ) ? $data : '';
		}
		if ( is_readable( $path ) ) {
			$data = file_get_contents( $path );
			return is_string( $data ) ? $data : '';
		}
		return '';
	}

	/**
	 * Recursively remove a directory via WP_Filesystem.
	 *
	 * @param string $dir
	 */
	public static function rmdir_tree( $dir ) {
		if ( $dir === '' || ! is_dir( $dir ) ) {
			return;
		}
		$fs = self::wp();
		if ( $fs && method_exists( $fs, 'dirlist' ) && method_exists( $fs, 'delete' ) ) {
			$list = $fs->dirlist( $dir, false, true );
			if ( is_array( $list ) ) {
				foreach ( $list as $name => $entry ) {
					$full = ( function_exists( 'trailingslashit' ) ? trailingslashit( $dir ) : rtrim( $dir, '/\\' ) . '/' ) . $name;
					$type = is_array( $entry ) ? ( $entry['type'] ?? '' ) : '';
					if ( $type === 'd' ) {
						self::rmdir_tree( $full );
					} else {
						$fs->delete( $full, false, 'f' );
					}
				}
			}
			$fs->rmdir( $dir );
			return;
		}
		if ( $fs && method_exists( $fs, 'rmdir' ) ) {
			$fs->rmdir( $dir, true );
		}
	}
}
