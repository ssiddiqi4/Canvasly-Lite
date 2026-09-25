<?php
namespace CanvaslyLite\Cli;

use CanvaslyLite\Compatibility\Cache;
use CanvaslyLite\Convert\Converter;
use CanvaslyLite\Design\CssPrint;
use CanvaslyLite\Design\Kit;
use CanvaslyLite\Design\Optimize;
use CanvaslyLite\Tools\ReplaceUrl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI operations (Roadmap 7.6).
 *
 * Callable from tests without WP-CLI. The Command class prints results.
 */
class Cli {
	private static $booted      = false;
	private static $registered  = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		if ( function_exists( 'add_action' ) ) {
			add_action( 'cli_init', array( self::class, 'register' ) );
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			self::register();
		}
	}

	public static function register() {
		if ( self::$registered ) {
			return;
		}
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) ) {
			return;
		}
		self::$registered = true;
		\WP_CLI::add_command( 'canvasly-lite', Command::class );
		/**
		 * Fires after the `wp canvasly-lite` command is registered.
		 *
		 * Add-ons can attach extra subcommands with WP_CLI::add_command().
		 */
		do_action( 'canvasly-lite/cli/register' );
	}

	/**
	 * @return bool
	 */
	public static function registered() {
		return self::$registered;
	}

	public static function reset_for_tests() {
		self::$booted     = false;
		self::$registered = false;
	}

	/**
	 * Rebuild compiled CSS files.
	 *
	 * @param array $args {
	 *   @type string     $scope all|global|post
	 *   @type int        $id
	 *   @type int[]|string $ids
	 *   @type int        $limit
	 * }
	 * @return array|\WP_Error
	 */
	public static function regenerate_css( $args = array() ) {
		if ( ! class_exists( CssPrint::class ) ) {
			return new \WP_Error( 'unavailable', __( 'CSS regeneration is not available.', 'canvasly-lite' ) );
		}
		$args  = is_array( $args ) ? $args : array();
		$scope = function_exists( 'sanitize_key' ) ? sanitize_key( (string) ( $args['scope'] ?? 'all' ) ) : (string) ( $args['scope'] ?? 'all' );
		if ( ! in_array( $scope, array( 'all', 'global', 'post' ), true ) ) {
			$scope = 'all';
		}
		$params = array( 'scope' => $scope );
		if ( ! empty( $args['limit'] ) ) {
			$params['limit'] = absint( $args['limit'] );
		}
		if ( ! empty( $args['id'] ) ) {
			$params['id'] = absint( $args['id'] );
		}
		if ( ! empty( $args['ids'] ) ) {
			$params['ids'] = self::ids_from( $args['ids'] );
		}
		if ( $scope === 'post' && empty( $params['id'] ) && empty( $params['ids'] ) ) {
			return new \WP_Error(
				'missing_id',
				__( 'Pass --id=<post_id> when scope is post.', 'canvasly-lite' )
			);
		}
		$report = CssPrint::regenerate( $params );
		/**
		 * Filter the regenerate-css CLI report.
		 *
		 * @param array $report
		 * @param array $params
		 */
		$filtered = apply_filters( 'canvasly-lite/cli/regenerate_css', $report, $params );
		return is_array( $filtered ) ? $filtered : $report;
	}

	/**
	 * Drop fragment cache and ask cache plugins to purge.
	 *
	 * @param array $args {
	 *   @type int $id Optional post id. Empty = site-wide.
	 * }
	 * @return array
	 */
	public static function flush_cache( $args = array() ) {
		$args = is_array( $args ) ? $args : array();
		$id   = absint( $args['id'] ?? 0 );
		$report = array(
			'id'       => $id,
			'fragment' => false,
			'plugins'  => false,
		);
		if ( $id ) {
			if ( class_exists( Optimize::class ) ) {
				Optimize::invalidate_post( $id );
				$report['fragment'] = true;
			}
			if ( class_exists( Cache::class ) ) {
				Cache::purge( $id );
				$report['plugins'] = true;
			}
		} else {
			if ( class_exists( Optimize::class ) ) {
				Optimize::flush_all();
				$report['fragment'] = true;
			}
			if ( class_exists( Cache::class ) ) {
				Cache::purge_all();
				$report['plugins'] = true;
			}
		}
		/**
		 * Filter the flush-cache CLI report.
		 *
		 * @param array $report
		 * @param array $args
		 */
		$filtered = apply_filters( 'canvasly-lite/cli/flush_cache', $report, $args );
		return is_array( $filtered ) ? $filtered : $report;
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @param array  $args {dry_run:bool}
	 * @return array|\WP_Error
	 */
	public static function replace_url( $from, $to, $args = array() ) {
		if ( ! class_exists( ReplaceUrl::class ) ) {
			return new \WP_Error( 'unavailable', __( 'Replace URL is not available.', 'canvasly-lite' ) );
		}
		$args = is_array( $args ) ? $args : array();
		$out  = ReplaceUrl::replace(
			(string) $from,
			(string) $to,
			array( 'dry_run' => ! empty( $args['dry_run'] ) )
		);
		/**
		 * Filter the replace-url CLI result.
		 *
		 * @param array|\WP_Error $out
		 * @param string          $from
		 * @param string          $to
		 * @param array           $args
		 */
		return apply_filters( 'canvasly-lite/cli/replace_url', $out, $from, $to, $args );
	}

	/**
	 * @param string $path ZIP or JSON on disk.
	 * @param array  $args {mode:string, include_content:bool}
	 * @return array|\WP_Error
	 */
	public static function import( $path, $args = array() ) {
		if ( ! class_exists( Kit::class ) ) {
			return new \WP_Error( 'unavailable', __( 'Kit import is not available.', 'canvasly-lite' ) );
		}
		$args = is_array( $args ) ? $args : array();
		$path = self::resolve_path( $path );
		if ( $path === '' || ! is_readable( $path ) ) {
			return new \WP_Error( 'invalid_kit', __( 'The kit file could not be read.', 'canvasly-lite' ) );
		}
		$mode = function_exists( 'sanitize_key' ) ? sanitize_key( (string) ( $args['mode'] ?? 'merge' ) ) : (string) ( $args['mode'] ?? 'merge' );
		if ( ! in_array( $mode, array( 'merge', 'replace' ), true ) ) {
			$mode = 'merge';
		}
		$include_content = array_key_exists( 'include_content', $args ) ? ! empty( $args['include_content'] ) : true;
		$result          = Kit::import( $path, $mode, array( 'include_content' => $include_content ) );
		/**
		 * Filter the import CLI result.
		 *
		 * @param array|\WP_Error $result
		 * @param string          $path
		 * @param array           $args
		 */
		return apply_filters( 'canvasly-lite/cli/import', $result, $path, $args );
	}

	/**
	 * Write a kit ZIP to $dest (file or directory). Empty dest uses the generated filename in cwd.
	 *
	 * @param string $dest
	 * @param array  $args Kit::normalize_args keys.
	 * @return array|\WP_Error
	 */
	public static function export( $dest = '', $args = array() ) {
		if ( ! class_exists( Kit::class ) ) {
			return new \WP_Error( 'unavailable', __( 'Kit export is not available.', 'canvasly-lite' ) );
		}
		$args  = is_array( $args ) ? $args : array();
		$built = Kit::write_zip( $args );
		if ( is_wp_error( $built ) ) {
			return $built;
		}
		$filename = (string) ( $built['filename'] ?? 'canvasly-lite-kit.zip' );
		$dest     = trim( (string) $dest );
		if ( $dest === '' ) {
			$cwd  = function_exists( 'getcwd' ) ? getcwd() : '';
			$dest = ( $cwd !== '' ? rtrim( $cwd, '/\\' ) . DIRECTORY_SEPARATOR : '' ) . $filename;
		} else {
			$dest = self::resolve_path( $dest );
			if ( is_dir( $dest ) ) {
				$dest = rtrim( $dest, '/\\' ) . DIRECTORY_SEPARATOR . $filename;
			}
		}
		$dir = dirname( $dest );
		if ( $dir !== '' && $dir !== '.' && ! is_dir( $dir ) ) {
			Kit::discard_export( $built );
			return new \WP_Error( 'export_dir', __( 'The export directory does not exist.', 'canvasly-lite' ) );
		}
		$copied = @copy( $built['path'], $dest );
		Kit::discard_export( $built );
		if ( ! $copied || ! file_exists( $dest ) ) {
			return new \WP_Error( 'export_write', __( 'Could not write the kit ZIP.', 'canvasly-lite' ) );
		}
		$out = array(
			'path'     => $dest,
			'filename' => basename( $dest ),
			'bytes'    => filesize( $dest ),
			'manifest' => is_array( $built['manifest'] ?? null ) ? $built['manifest'] : array(),
		);
		/**
		 * Filter the export CLI result.
		 *
		 * @param array $out
		 * @param array $args
		 */
		$filtered = apply_filters( 'canvasly-lite/cli/export', $out, $args );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * Convert stored third-party builder JSON.
	 *
	 * @param array $args {
	 *   @type int[]|string $ids
	 *   @type bool         $dry_run
	 *   @type bool         $force
	 * }
	 * @return array|\WP_Error
	 */
	public static function convert( $args = array() ) {
		if ( ! class_exists( Converter::class ) ) {
			return new \WP_Error( 'unavailable', __( 'The converter is not available.', 'canvasly-lite' ) );
		}
		$args = is_array( $args ) ? $args : array();
		$ids  = self::ids_from( $args['ids'] ?? array() );
		if ( ! $ids ) {
			foreach ( Converter::candidates() as $row ) {
				$ids[] = absint( is_array( $row ) ? ( $row['id'] ?? 0 ) : $row );
			}
			$ids = array_values( array_filter( $ids ) );
		}
		$conv   = new Converter();
		$report = $conv->convert_posts(
			$ids,
			array(
				'dry_run' => ! empty( $args['dry_run'] ),
				'force'   => ! empty( $args['force'] ),
			)
		);
		/**
		 * Filter the convert CLI report.
		 *
		 * @param array $report
		 * @param int[] $ids
		 * @param array $args
		 */
		$filtered = apply_filters( 'canvasly-lite/cli/convert', $report, $ids, $args );
		return is_array( $filtered ) ? $filtered : $report;
	}

	/**
	 * @param mixed $raw Comma/space list, array, or single id.
	 * @return int[]
	 */
	public static function ids_from( $raw ) {
		if ( is_array( $raw ) ) {
			$parts = $raw;
		} else {
			$parts = preg_split( '/[\s,]+/', trim( (string) $raw ), -1, PREG_SPLIT_NO_EMPTY );
			if ( ! is_array( $parts ) ) {
				$parts = array();
			}
		}
		$ids = array();
		foreach ( $parts as $id ) {
			$id = absint( $id );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Resolve a CLI path relative to the current working directory.
	 *
	 * @param string $path
	 * @return string
	 */
	public static function resolve_path( $path ) {
		$path = trim( (string) $path );
		if ( $path === '' ) {
			return '';
		}
		if ( function_exists( 'wp_normalize_path' ) ) {
			$path = wp_normalize_path( $path );
		}
		$absolute = ( isset( $path[0] ) && ( $path[0] === '/' || $path[0] === '\\' ) )
			|| preg_match( '#^[A-Za-z]:[\\\\/]#', $path );
		if ( ! $absolute ) {
			$cwd = function_exists( 'getcwd' ) ? getcwd() : '';
			if ( $cwd !== '' ) {
				$path = rtrim( $cwd, '/\\' ) . DIRECTORY_SEPARATOR . $path;
			}
		}
		return $path;
	}
}
