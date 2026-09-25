<?php
namespace CanvaslyLite\Cli;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `wp canvasly-lite` subcommands.
 *
 * Methods are named for WP-CLI (underscores become hyphens).
 */
class Command {

	/**
	 * Rebuild compiled CSS files (global.css and per-post files).
	 *
	 * ## OPTIONS
	 *
	 * [--scope=<scope>]
	 * : What to rebuild.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - global
	 *   - post
	 * ---
	 *
	 * [--id=<id>]
	 * : Post ID when --scope=post.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated post IDs to rebuild.
	 *
	 * [--limit=<limit>]
	 * : Max posts when rebuilding everything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp canvasly-lite regenerate-css
	 *     wp canvasly-lite regenerate-css --scope=global
	 *     wp canvasly-lite regenerate-css --scope=post --id=12
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function regenerate_css( $args, $assoc_args ) {
		$assoc_args = is_array( $assoc_args ) ? $assoc_args : array();
		$params     = array(
			'scope' => $assoc_args['scope'] ?? 'all',
		);
		if ( ! empty( $assoc_args['id'] ) ) {
			$params['id'] = $assoc_args['id'];
		}
		if ( ! empty( $assoc_args['ids'] ) ) {
			$params['ids'] = $assoc_args['ids'];
		}
		if ( ! empty( $assoc_args['limit'] ) ) {
			$params['limit'] = $assoc_args['limit'];
		}
		$out = Cli::regenerate_css( $params );
		if ( is_wp_error( $out ) ) {
			self::fail( $out->get_error_message() );
			return;
		}
		$written = (int) ( $out['written'] ?? 0 );
		$failed  = (int) ( $out['failed'] ?? 0 );
		$posts   = (int) ( $out['posts'] ?? 0 );
		self::ok(
			sprintf(
				/* translators: 1: files written, 2: posts processed, 3: failures */
				__( 'Regenerated CSS (%1$d written, %2$d posts, %3$d failed).', 'canvasly-lite' ),
				$written,
				$posts,
				$failed
			)
		);
	}

	/**
	 * Flush the unit fragment cache and purge page-cache plugins.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<id>]
	 * : Limit the flush to one post. Omit for a site-wide flush.
	 *
	 * ## EXAMPLES
	 *
	 *     wp canvasly-lite flush-cache
	 *     wp canvasly-lite flush-cache --id=12
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function flush_cache( $args, $assoc_args ) {
		$assoc_args = is_array( $assoc_args ) ? $assoc_args : array();
		$out        = Cli::flush_cache( array( 'id' => $assoc_args['id'] ?? 0 ) );
		$id         = (int) ( $out['id'] ?? 0 );
		if ( $id ) {
			self::ok(
				sprintf(
					/* translators: %d: post ID */
					__( 'Flushed Canvasly caches for post %d.', 'canvasly-lite' ),
					$id
				)
			);
			return;
		}
		self::ok( __( 'Flushed Canvasly caches site-wide.', 'canvasly-lite' ) );
	}

	/**
	 * Rewrite a site URL inside document JSON and compiled CSS cache.
	 *
	 * ## OPTIONS
	 *
	 * <from>
	 * : Old URL, including the protocol.
	 *
	 * <to>
	 * : New URL, including the protocol.
	 *
	 * [--dry-run]
	 * : Count matches without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp canvasly-lite replace-url https://staging.example.com https://www.example.com
	 *     wp canvasly-lite replace-url https://old.test https://new.test --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function replace_url( $args, $assoc_args ) {
		$args       = is_array( $args ) ? $args : array();
		$assoc_args = is_array( $assoc_args ) ? $assoc_args : array();
		$from       = isset( $args[0] ) ? (string) $args[0] : '';
		$to         = isset( $args[1] ) ? (string) $args[1] : '';
		$out        = Cli::replace_url(
			$from,
			$to,
			array( 'dry_run' => ! empty( $assoc_args['dry-run'] ) )
		);
		if ( is_wp_error( $out ) ) {
			self::fail( $out->get_error_message() );
			return;
		}
		$count = (int) ( $out['replacements'] ?? 0 );
		$posts = (int) ( $out['posts'] ?? 0 );
		if ( ! empty( $out['dry_run'] ) ) {
			self::ok(
				sprintf(
					/* translators: 1: replacement count, 2: post count */
					_n(
						'Dry run: %1$d replacement across %2$d item.',
						'Dry run: %1$d replacements across %2$d items.',
						$count,
						'canvasly-lite'
					),
					$count,
					$posts
				)
			);
			return;
		}
		self::ok(
			sprintf(
				/* translators: 1: replacement count, 2: post count */
				_n(
					'Replaced %1$d occurrence across %2$d item.',
					'Replaced %1$d occurrences across %2$d items.',
					$count,
					'canvasly-lite'
				),
				$count,
				$posts
			)
		);
	}

	/**
	 * Import a kit ZIP or JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a .zip or .json kit.
	 *
	 * [--mode=<mode>]
	 * : Conflict handling.
	 * ---
	 * default: merge
	 * options:
	 *   - merge
	 *   - replace
	 * ---
	 *
	 * [--include-content]
	 * : Import pages/posts bundled in the kit. Default: true.
	 *
	 * [--skip-content]
	 * : Skip bundled pages/posts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp canvasly-lite import ./canvasly-lite-kit.zip
	 *     wp canvasly-lite import ./kit.json --mode=replace --skip-content
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function import( $args, $assoc_args ) {
		$args       = is_array( $args ) ? $args : array();
		$assoc_args = is_array( $assoc_args ) ? $assoc_args : array();
		$file       = isset( $args[0] ) ? (string) $args[0] : '';
		$include    = empty( $assoc_args['skip-content'] );
		if ( array_key_exists( 'include-content', $assoc_args ) ) {
			$include = ! empty( $assoc_args['include-content'] );
		}
		$out = Cli::import(
			$file,
			array(
				'mode'            => $assoc_args['mode'] ?? 'merge',
				'include_content' => $include,
			)
		);
		if ( is_wp_error( $out ) ) {
			self::fail( $out->get_error_message() );
			return;
		}
		self::ok(
			sprintf(
				/* translators: 1: templates, 2: content items, 3: mode */
				__( 'Imported kit (%1$d templates, %2$d content, mode: %3$s).', 'canvasly-lite' ),
				(int) ( $out['templates'] ?? 0 ),
				(int) ( $out['content'] ?? 0 ),
				(string) ( $out['mode'] ?? 'merge' )
			)
		);
	}

	/**
	 * Export a site kit ZIP.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Destination file or directory. Defaults to a generated name in the current directory.
	 *
	 * [--templates]
	 * : Include saved templates. Default: true.
	 *
	 * [--skip-templates]
	 * : Omit saved templates.
	 *
	 * [--content]
	 * : Include selected pages/posts.
	 *
	 * [--content-ids=<ids>]
	 * : Comma-separated post IDs to include as content.
	 *
	 * [--media]
	 * : Copy referenced media into the ZIP. Default: true.
	 *
	 * [--skip-media]
	 * : Omit media files.
	 *
	 * ## EXAMPLES
	 *
	 *     wp canvasly-lite export
	 *     wp canvasly-lite export ./kit.zip --content --content-ids=12,15
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function export( $args, $assoc_args ) {
		$args       = is_array( $args ) ? $args : array();
		$assoc_args = is_array( $assoc_args ) ? $assoc_args : array();
		$dest       = isset( $args[0] ) ? (string) $args[0] : '';
		$templates  = empty( $assoc_args['skip-templates'] );
		$media      = empty( $assoc_args['skip-media'] );
		$out        = Cli::export(
			$dest,
			array(
				'include_templates' => $templates,
				'include_content'   => ! empty( $assoc_args['content'] ) || ! empty( $assoc_args['content-ids'] ),
				'include_media'     => $media,
				'content_ids'       => Cli::ids_from( $assoc_args['content-ids'] ?? array() ),
			)
		);
		if ( is_wp_error( $out ) ) {
			self::fail( $out->get_error_message() );
			return;
		}
		self::ok(
			sprintf(
				/* translators: %s: file path */
				__( 'Exported kit to %s.', 'canvasly-lite' ),
				(string) ( $out['path'] ?? '' )
			)
		);
	}

	/**
	 * Convert stored third-party builder JSON into Canvasly documents.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<ids>]
	 * : Comma-separated post IDs. Omit to convert every candidate.
	 *
	 * [--dry-run]
	 * : Report mapping without saving.
	 *
	 * [--force]
	 * : Overwrite an existing Canvasly document on the same post.
	 *
	 * ## EXAMPLES
	 *
	 *     wp canvasly-lite convert --dry-run
	 *     wp canvasly-lite convert --ids=12,15 --force
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function convert( $args, $assoc_args ) {
		$assoc_args = is_array( $assoc_args ) ? $assoc_args : array();
		$out        = Cli::convert(
			array(
				'ids'     => $assoc_args['ids'] ?? array(),
				'dry_run' => ! empty( $assoc_args['dry-run'] ),
				'force'   => ! empty( $assoc_args['force'] ),
			)
		);
		if ( is_wp_error( $out ) ) {
			self::fail( $out->get_error_message() );
			return;
		}
		$count = (int) ( $out['converted'] ?? 0 );
		if ( ! empty( $out['dry_run'] ) ) {
			self::ok(
				sprintf(
					/* translators: %d: number of posts */
					_n( 'Dry run finished for %d item.', 'Dry run finished for %d items.', $count, 'canvasly-lite' ),
					$count
				)
			);
			return;
		}
		self::ok(
			sprintf(
				/* translators: %d: number of converted posts */
				_n( 'Converted %d item.', 'Converted %d items.', $count, 'canvasly-lite' ),
				$count
			)
		);
	}

	/**
	 * @param string $message
	 */
	private static function ok( $message ) {
		if ( class_exists( '\WP_CLI' ) ) {
			\WP_CLI::success( $message );
		}
	}

	/**
	 * @param string $message
	 */
	private static function fail( $message ) {
		if ( class_exists( '\WP_CLI' ) ) {
			\WP_CLI::error( $message );
		}
	}
}
