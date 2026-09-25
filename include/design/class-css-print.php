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
 * External / inline CSS delivery (Roadmap 6.1).
 *
 * Writes `uploads/canvasly-lite/css/global.css` and `post-{id}.css`. Cache busting
 * uses the first 12 characters of an MD5 of the minified CSS as the enqueue `ver`.
 */
class CssPrint {
	const METHOD_EXTERNAL = 'external';
	const METHOD_INLINE   = 'inline';
	const SUBDIR          = 'canvasly-lite/css';
	const META_HASH       = '_lb_css_hash';
	const GLOBAL_HASH     = 'canvasly_lite_global_css_hash';
	const HANDLE_GLOBAL   = 'canvasly-lite-global';
	const MAX_REGENERATE  = 500;

	private static $booted            = false;
	private static $enqueued_global   = false;
	private static $enqueued_file     = false;
	private static $enqueued_posts    = array();
	private static $inlined_global    = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		add_action( 'canvasly-lite/document/after_save', array( self::class, 'on_after_save' ), 20, 1 );
		add_action( 'deleted_post', array( self::class, 'on_deleted_post' ) );
		add_filter( 'canvasly-lite/replace_url/report', array( self::class, 'on_replace_url' ), 10, 2 );
		foreach ( self::option_keys() as $key ) {
			add_action( 'update_option_' . $key, array( self::class, 'invalidate_global' ), 20 );
			add_action( 'add_option_' . $key, array( self::class, 'invalidate_global' ), 20 );
		}
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'canvasly-lite/tools/screen', array( self::class, 'screen' ), 4 );
			add_action( 'admin_post_lb_css_print', array( self::class, 'handle_method' ) );
			add_action( 'admin_post_lb_regenerate_css', array( self::class, 'handle_regenerate' ) );
			add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		}
	}

	/**
	 * Option keys whose change invalidates compiled global CSS.
	 *
	 * @return string[]
	 */
	public static function option_keys() {
		return array(
			'canvasly_lite_variables',
			'canvasly_lite_theme_style',
			'canvasly_lite_kit_settings',
			'canvasly_lite_global_classes',
			'canvasly_lite_global_settings',
		);
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		$ns = $namespace !== '' ? $namespace : 'canvasly-lite/v1';
		register_rest_route(
			$ns,
			'/css',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'rest_get' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest_save' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			$ns,
			'/css/regenerate',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_regenerate' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
	}

	public static function rest_get() {
		return rest_ensure_response( self::status() );
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_save( $req ) {
		$d = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		if ( array_key_exists( 'css_print_method', $d ) ) {
			self::save_method( $d['css_print_method'] );
		}
		return rest_ensure_response( self::status() );
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_regenerate( $req ) {
		$d = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		return rest_ensure_response( self::regenerate( $d ) );
	}

	/**
	 * @return array
	 */
	public static function status() {
		$dir = self::dir();
		return array(
			'css_print_method' => self::method(),
			'dir'              => $dir,
			'url'              => self::url( '' ),
			'global'           => self::file_exists( 'global.css' ),
			'global_hash'      => (string) get_option( self::GLOBAL_HASH, '' ),
		);
	}

	/**
	 * @return string external|inline
	 */
	public static function method() {
		$raw = '';
		if ( class_exists( GlobalSettings::class ) ) {
			$g   = GlobalSettings::get();
			$raw = (string) ( $g['css_print_method'] ?? '' );
		}
		if ( $raw === '' ) {
			$raw = (string) get_option( 'canvasly_lite_css_print_method', '' );
		}
		$method = self::sanitize_method( $raw !== '' ? $raw : self::METHOD_EXTERNAL );
		/**
		 * Filter the CSS print method.
		 *
		 * @param string $method external|inline
		 */
		$filtered = apply_filters( 'canvasly-lite/css/print_method', $method );
		return self::sanitize_method( is_string( $filtered ) ? $filtered : $method );
	}

	public static function is_external() {
		return self::method() === self::METHOD_EXTERNAL;
	}

	/**
	 * @param mixed $method
	 * @return string
	 */
	public static function sanitize_method( $method ) {
		$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $method ) : strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $method ) );
		return $key === self::METHOD_INLINE ? self::METHOD_INLINE : self::METHOD_EXTERNAL;
	}

	/**
	 * @param mixed $method
	 * @return string
	 */
	public static function save_method( $method ) {
		$method = self::sanitize_method( $method );
		if ( class_exists( GlobalSettings::class ) ) {
			$g                      = GlobalSettings::get();
			$g['css_print_method']  = $method;
			update_option( GlobalSettings::KEY, $g, false );
		} else {
			update_option( 'canvasly_lite_css_print_method', $method, false );
		}
		return $method;
	}

	/**
	 * First 12 characters of an MD5 of the CSS contents.
	 *
	 * @param string $css
	 * @return string
	 */
	public static function hash( $css ) {
		return substr( md5( (string) $css ), 0, 12 );
	}

	/**
	 * Minify via Performance::minify_css().
	 *
	 * @param string $css
	 * @return string
	 */
	public static function minify( $css ) {
		$css = (string) $css;
		if ( $css === '' ) {
			return '';
		}
		if ( class_exists( Performance::class ) && method_exists( Performance::class, 'minify_css' ) ) {
			return Performance::minify_css( $css );
		}
		return trim( $css );
	}

	/**
	 * Absolute directory for generated CSS files.
	 *
	 * @return string
	 */
	public static function dir() {
		$u = self::uploads();
		$base = (string) ( $u['basedir'] ?? '' );
		if ( $base === '' ) {
			return '';
		}
		return rtrim( str_replace( '\\', '/', $base ), '/' ) . '/' . self::SUBDIR;
	}

	/**
	 * Public URL for a file in the CSS directory (`$file` empty = directory URL).
	 *
	 * @param string $file
	 * @return string
	 */
	public static function url( $file = '' ) {
		$u    = self::uploads();
		$base = (string) ( $u['baseurl'] ?? '' );
		if ( $base === '' ) {
			return '';
		}
		$url = rtrim( $base, '/' ) . '/' . self::SUBDIR;
		$file = ltrim( str_replace( '\\', '/', (string) $file ), '/' );
		if ( $file !== '' && strpos( $file, '..' ) === false ) {
			$url .= '/' . $file;
		}
		return $url;
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public static function file_exists( $file ) {
		$path = self::path( $file );
		return $path !== '' && is_file( $path );
	}

	/**
	 * @param string $file
	 * @return string
	 */
	public static function path( $file = '' ) {
		$dir = self::dir();
		if ( $dir === '' ) {
			return '';
		}
		$file = ltrim( str_replace( '\\', '/', (string) $file ), '/' );
		if ( $file === '' ) {
			return $dir;
		}
		if ( strpos( $file, '..' ) !== false ) {
			return '';
		}
		return $dir . '/' . $file;
	}

	public static function post_filename( $id ) {
		return 'post-' . absint( $id ) . '.css';
	}

	/**
	 * Compiled, minified global CSS (tokens, theme style, kit, classes, interactions).
	 *
	 * @return string
	 */
	public static function global_css() {
		$css = '';
		if ( class_exists( Variables::class ) ) {
			$css .= Variables::css();
		}
		if ( class_exists( ThemeStyle::class ) ) {
			$css .= ThemeStyle::css();
		}
		if ( class_exists( KitSettings::class ) ) {
			$css .= KitSettings::css();
		}
		if ( class_exists( GlobalClasses::class ) ) {
			$css .= GlobalClasses::css();
		}
		if ( class_exists( Interactions::class ) ) {
			$css .= Interactions::css();
		}
		$css = self::minify( $css );
		/**
		 * Filter compiled global CSS before it is written or printed.
		 *
		 * @param string $css
		 */
		$filtered = apply_filters( 'canvasly-lite/css/global', $css );
		return is_string( $filtered ) ? $filtered : $css;
	}

	/**
	 * Compiled, minified per-post CSS.
	 *
	 * @param int $id
	 * @return string
	 */
	public static function post_css( $id ) {
		$id  = absint( $id );
		$css = '';
		if ( ! $id ) {
			return '';
		}
		if ( class_exists( DocumentManager::class ) && method_exists( DocumentManager::class, 'compiled_css' ) ) {
			$css = (string) DocumentManager::compiled_css( $id );
		} else {
			$css = (string) get_post_meta( $id, '_lb_css_cache', true );
		}
		$css = self::minify( $css );
		/**
		 * Filter compiled per-post CSS before it is written or printed.
		 *
		 * @param string $css
		 * @param int    $id
		 */
		$filtered = apply_filters( 'canvasly-lite/css/post', $css, $id );
		return is_string( $filtered ) ? $filtered : $css;
	}

	/**
	 * Write `global.css`. Returns the content hash, or empty string on failure.
	 *
	 * @param bool $force
	 * @return string
	 */
	public static function write_global( $force = false ) {
		$css = self::global_css();
		if ( $css === '' ) {
			self::delete_file( 'global.css' );
			delete_option( self::GLOBAL_HASH );
			return '';
		}
		$hash = self::hash( $css );
		$stored = (string) get_option( self::GLOBAL_HASH, '' );
		if ( ! $force && $stored === $hash && self::file_exists( 'global.css' ) ) {
			return $hash;
		}
		if ( ! self::write_file( 'global.css', $css ) ) {
			return '';
		}
		update_option( self::GLOBAL_HASH, $hash, false );
		return $hash;
	}

	/**
	 * Write `post-{id}.css`. Returns the content hash, or empty string on failure.
	 *
	 * @param int  $id
	 * @param bool $force
	 * @return string
	 */
	public static function write_post( $id, $force = false ) {
		$id = absint( $id );
		if ( ! $id ) {
			return '';
		}
		$css = self::post_css( $id );
		$file = self::post_filename( $id );
		if ( $css === '' ) {
			self::delete_file( $file );
			delete_post_meta( $id, self::META_HASH );
			return '';
		}
		$hash   = self::hash( $css );
		$stored = (string) get_post_meta( $id, self::META_HASH, true );
		if ( ! $force && $stored === $hash && self::file_exists( $file ) ) {
			return $hash;
		}
		if ( ! self::write_file( $file, $css ) ) {
			return '';
		}
		update_post_meta( $id, self::META_HASH, $hash );
		return $hash;
	}

	/**
	 * Enqueue or inline global CSS.
	 */
	public static function enqueue_global() {
		if ( self::$enqueued_global ) {
			return;
		}
		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		$css = self::global_css();
		if ( $css === '' ) {
			self::$enqueued_global = true;
			return;
		}
		wp_enqueue_style( 'canvasly-lite-frontend' );
		if ( self::is_external() ) {
			$hash = self::write_global();
			$src  = self::url( 'global.css' );
			if ( $hash !== '' && $src !== '' && self::file_exists( 'global.css' ) ) {
				wp_enqueue_style( self::HANDLE_GLOBAL, $src, array( 'canvasly-lite-frontend' ), $hash );
				self::$enqueued_global = true;
				self::$enqueued_file   = true;
				return;
			}
		}
		if ( function_exists( 'wp_add_inline_style' ) && ! self::$inlined_global ) {
			wp_add_inline_style( 'canvasly-lite-frontend', $css );
			self::$inlined_global = true;
		}
		self::$enqueued_global = true;
	}

	/**
	 * Enqueue or inline per-post CSS. Always ensures global CSS is present first.
	 *
	 * @param int $id
	 */
	public static function enqueue_post( $id ) {
		$id = absint( $id );
		if ( ! $id || isset( self::$enqueued_posts[ $id ] ) ) {
			return;
		}
		self::enqueue_global();
		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		$css = self::post_css( $id );
		if ( $css === '' ) {
			self::$enqueued_posts[ $id ] = true;
			return;
		}
		wp_enqueue_style( 'canvasly-lite-frontend' );
		if ( self::is_external() ) {
			$hash = self::write_post( $id );
			$src  = self::url( self::post_filename( $id ) );
			if ( $hash !== '' && $src !== '' && self::file_exists( self::post_filename( $id ) ) ) {
				$deps = array( 'canvasly-lite-frontend' );
				if ( self::$enqueued_file ) {
					$deps[] = self::HANDLE_GLOBAL;
				}
				wp_enqueue_style( 'canvasly-lite-post-' . $id, $src, $deps, $hash );
				self::$enqueued_posts[ $id ] = true;
				return;
			}
		}
		if ( function_exists( 'wp_add_inline_style' ) ) {
			wp_add_inline_style( 'canvasly-lite-frontend', $css );
		}
		self::$enqueued_posts[ $id ] = true;
	}

	/**
	 * Global + post CSS for a document (singular frontend or template page).
	 *
	 * @param int $id
	 */
	public static function enqueue_for_document( $id ) {
		self::enqueue_global();
		self::enqueue_post( $id );
	}

	/**
	 * wp_head fallback when enqueue did not already print global CSS.
	 */
	public static function print_head_fallback() {
		if ( self::$enqueued_global || self::$inlined_global ) {
			return;
		}
		if ( function_exists( 'is_singular' ) && ! is_singular() ) {
			return;
		}
		$id = function_exists( 'get_the_ID' ) ? absint( get_the_ID() ) : 0;
		if ( ! $id || ! class_exists( DocumentManager::class ) || ! DocumentManager::has( $id ) ) {
			return;
		}
		$css = self::global_css();
		if ( $css === '' ) {
			return;
		}
		if ( self::is_external() ) {
			$hash = self::write_global();
			$src  = self::url( 'global.css' );
			if ( $hash !== '' && $src !== '' && self::file_exists( 'global.css' ) && function_exists( 'wp_enqueue_style' ) ) {
				wp_enqueue_style( 'canvasly-lite-frontend' );
				wp_enqueue_style( self::HANDLE_GLOBAL, $src, array( 'canvasly-lite-frontend' ), $hash );
				self::$enqueued_global = true;
				self::$enqueued_file   = true;
				return;
			}
		}
		if ( function_exists( 'wp_register_style' ) ) {
			wp_register_style( 'canvasly-lite-frontend', false, array(), defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : null );
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'canvasly-lite-frontend' );
		}
		if ( function_exists( 'wp_add_inline_style' ) ) {
			wp_add_inline_style( 'canvasly-lite-frontend', wp_strip_all_tags( $css ) );
		}
		self::$inlined_global  = true;
		self::$enqueued_global = true;
	}

	/**
	 * Rebuild global.css and per-post files.
	 *
	 * @param array $args {
	 *   @type string $scope all|global|post
	 *   @type int    $id    Post id when scope is post.
	 *   @type int[]  $ids   Explicit document ids (skips the query).
	 *   @type int    $limit Max posts (default 500).
	 * }
	 * @return array
	 */
	public static function regenerate( $args = array() ) {
		$args  = is_array( $args ) ? $args : array();
		$scope = function_exists( 'sanitize_key' ) ? sanitize_key( (string) ( $args['scope'] ?? 'all' ) ) : (string) ( $args['scope'] ?? 'all' );
		if ( ! in_array( $scope, array( 'all', 'global', 'post' ), true ) ) {
			$scope = 'all';
		}
		$limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : self::MAX_REGENERATE;
		if ( $limit < 1 ) {
			$limit = self::MAX_REGENERATE;
		}
		$report = array(
			'scope'   => $scope,
			'method'  => self::method(),
			'global'  => false,
			'posts'   => 0,
			'written' => 0,
			'failed'  => 0,
			'dir'     => self::dir(),
			'items'   => array(),
		);

		if ( $scope === 'all' || $scope === 'global' ) {
			$hash = self::write_global( true );
			$report['global'] = $hash !== '' || self::global_css() === '';
			if ( $hash === '' && self::global_css() !== '' ) {
				$report['failed']++;
			} elseif ( $hash !== '' ) {
				$report['written']++;
			}
		}

		if ( $scope === 'global' ) {
			return self::filter_report( $report, $args );
		}

		$ids = array();
		if ( isset( $args['ids'] ) && is_array( $args['ids'] ) ) {
			foreach ( $args['ids'] as $id ) {
				$id = absint( $id );
				if ( $id ) {
					$ids[] = $id;
				}
			}
			$ids = array_values( array_unique( $ids ) );
		} elseif ( $scope === 'post' ) {
			$id = absint( $args['id'] ?? 0 );
			if ( $id ) {
				$ids = array( $id );
			}
		} else {
			$ids = self::document_ids( $limit );
		}
		if ( count( $ids ) > $limit ) {
			$ids = array_slice( $ids, 0, $limit );
		}

		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( ! $id ) {
				continue;
			}
			if ( class_exists( DocumentManager::class ) ) {
				delete_post_meta( $id, DocumentManager::CSS_CACHE );
			}
			$hash = self::write_post( $id, true );
			$ok   = $hash !== '' || self::post_css( $id ) === '';
			$report['posts']++;
			if ( $ok && $hash !== '' ) {
				$report['written']++;
			} elseif ( ! $ok ) {
				$report['failed']++;
			}
			$report['items'][] = array(
				'id'   => $id,
				'ok'   => $ok,
				'hash' => $hash,
			);
		}

		return self::filter_report( $report, $args );
	}

	/**
	 * Posts that store a Canvasly document (pages/posts + saved templates).
	 *
	 * @param int $limit
	 * @return int[]
	 */
	public static function document_ids( $limit = 0 ) {
		$limit = absint( $limit );
		if ( $limit < 1 ) {
			$limit = self::MAX_REGENERATE;
		}
		$types = class_exists( Documents::class ) ? Documents::enabled() : array( 'post', 'page' );
		$types = is_array( $types ) ? $types : array( 'post', 'page' );
		$types[] = 'lb_template';
		$types = array_values( array_unique( array_filter( $types ) ) );
		$meta  = class_exists( DocumentManager::class ) ? DocumentManager::META : '_lb_document_data';
		$q     = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'any',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CSS regen targets posts that store Canvasly document JSON.
				'meta_key'               => $meta,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
			)
		);
		$ids = array();
		foreach ( (array) $q->posts as $id ) {
			$id = absint( is_object( $id ) && isset( $id->ID ) ? $id->ID : $id );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		/**
		 * Filter document ids walked by Regenerate CSS.
		 *
		 * @param int[] $ids
		 * @param int   $limit
		 */
		$filtered = apply_filters( 'canvasly-lite/css/document_ids', $ids, $limit );
		return is_array( $filtered ) ? array_values( array_unique( array_map( 'absint', $filtered ) ) ) : $ids;
	}

	/**
	 * Drop the compiled file and hash for one post.
	 *
	 * @param int $id
	 */
	public static function invalidate_post( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return;
		}
		self::delete_file( self::post_filename( $id ) );
		delete_post_meta( $id, self::META_HASH );
		unset( self::$enqueued_posts[ $id ] );
	}

	/**
	 * Drop global.css and its stored hash.
	 */
	public static function invalidate_global() {
		self::delete_file( 'global.css' );
		delete_option( self::GLOBAL_HASH );
		self::$enqueued_global = false;
		self::$enqueued_file   = false;
		self::$inlined_global  = false;
	}

	/**
	 * Drop every generated CSS file and hash meta.
	 */
	public static function invalidate_all() {
		self::invalidate_global();
		$dir = self::dir();
		if ( $dir !== '' && is_dir( $dir ) ) {
			$files = glob( $dir . '/post-*.css' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						wp_delete_file( $file );
					}
				}
			}
		}
		if ( function_exists( 'delete_post_meta_by_key' ) ) {
			delete_post_meta_by_key( self::META_HASH );
		}
		self::$enqueued_posts = array();
	}

	/**
	 * @param int $id
	 */
	public static function on_after_save( $id ) {
		$id = absint( $id );
		if ( ! $id || ! self::is_external() ) {
			return;
		}
		self::write_post( $id, true );
	}

	/**
	 * @param int $id
	 */
	public static function on_deleted_post( $id ) {
		self::invalidate_post( $id );
	}

	/**
	 * After a Replace URL write, drop generated files so the next view rebuilds them.
	 *
	 * @param array $report
	 * @param array $args
	 * @return array
	 */
	public static function on_replace_url( $report, $args = array() ) {
		$report = is_array( $report ) ? $report : array();
		$args   = is_array( $args ) ? $args : array();
		if ( empty( $report['dry_run'] ) && empty( $args['dry_run'] ) ) {
			self::invalidate_all();
		}
		return $report;
	}

	public static function tools_url() {
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) ) {
			return \CanvaslyLite\Settings\AdminSettings::tools_or_settings_url();
		}
		return admin_url( 'admin.php?page=canvasly-lite-tools' );
	}

	public static function handle_method() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can change the CSS print method.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_css_print' );
		$method = self::save_method( sanitize_key( wp_unslash( $_POST['css_print_method'] ?? self::METHOD_EXTERNAL ) ) );
		self::store_notice(
			'success',
			$method === self::METHOD_INLINE
				? __( 'CSS will be printed inline.', 'canvasly-lite' )
				: __( 'CSS will be printed as external files.', 'canvasly-lite' )
		);
		wp_safe_redirect( self::tools_url() );
		exit;
	}

	public static function handle_regenerate() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can regenerate CSS.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_regenerate_css' );
		$out = self::regenerate( array( 'scope' => 'all' ) );
		$ttl = defined( 'MINUTE_IN_SECONDS' ) ? 10 * MINUTE_IN_SECONDS : 600;
		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'canvasly_lite_css_report_' . get_current_user_id(), $out, $ttl );
		}
		self::store_notice(
			(int) ( $out['failed'] ?? 0 ) > 0 ? 'error' : 'success',
			sprintf(
				/* translators: 1: files written, 2: posts processed */
				__( 'Regenerated CSS: %1$d files written across %2$d documents.', 'canvasly-lite' ),
				(int) ( $out['written'] ?? 0 ),
				(int) ( $out['posts'] ?? 0 )
			)
		);
		wp_safe_redirect( self::tools_url() );
		exit;
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
		$n = get_transient( 'canvasly_lite_css_notice_' . get_current_user_id() );
		if ( ! is_array( $n ) ) {
			return;
		}
		delete_transient( 'canvasly_lite_css_notice_' . get_current_user_id() );
		$class = ( $n['type'] ?? '' ) === 'success' ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $n['message'] ?? '' ) ) . '</p></div>';
	}

	public static function screen() {
		if ( ! self::can_manage() ) {
			return;
		}
		$method = self::method();
		$report = function_exists( 'get_transient' ) ? get_transient( 'canvasly_lite_css_report_' . get_current_user_id() ) : null;

		echo '<hr><h2>' . esc_html__( 'CSS print method', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'External files are written to uploads/canvasly-lite/css (global.css and post-{id}.css) with a content-hash query string for cache busting. Inline keeps compiled CSS in the document. Both paths minify CSS.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_css_print' );
		echo '<input type="hidden" name="action" value="lb_css_print">';
		echo '<table class="form-table"><tbody><tr><th>' . esc_html__( 'Print method', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="radio" name="css_print_method" value="external"' . ( $method === self::METHOD_EXTERNAL ? ' checked' : '' ) . '> ' . esc_html__( 'External files', 'canvasly-lite' ) . '</label><br>';
		echo '<label><input type="radio" name="css_print_method" value="inline"' . ( $method === self::METHOD_INLINE ? ' checked' : '' ) . '> ' . esc_html__( 'Internal embedding', 'canvasly-lite' ) . '</label>';
		echo '</td></tr></tbody></table>';
		echo '<p><button class="button" type="submit">' . esc_html__( 'Save print method', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';

		echo '<h2>' . esc_html__( 'Regenerate CSS', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Rebuild the global stylesheet and every document CSS file. Use this after a migration, a breakpoint change, or if styles look stale.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_regenerate_css' );
		echo '<input type="hidden" name="action" value="lb_regenerate_css">';
		echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Regenerate CSS', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';

		if ( is_array( $report ) ) {
			echo '<p><strong>' . esc_html__( 'Last regeneration', 'canvasly-lite' ) . '</strong> ';
			echo esc_html(
				sprintf(
					/* translators: 1: files written, 2: documents, 3: failures */
					__( '%1$d files written, %2$d documents, %3$d failed.', 'canvasly-lite' ),
					(int) ( $report['written'] ?? 0 ),
					(int) ( $report['posts'] ?? 0 ),
					(int) ( $report['failed'] ?? 0 )
				)
			);
			echo '</p>';
		}
	}

	/**
	 * @param string $type
	 * @param string $message
	 */
	private static function store_notice( $type, $message ) {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		set_transient(
			'canvasly_lite_css_notice_' . get_current_user_id(),
			array(
				'type'    => $type === 'success' ? 'success' : 'error',
				'message' => $message,
			),
			120
		);
	}

	/**
	 * Clear per-request enqueue flags (tests and regenerate).
	 */
	public static function reset_runtime() {
		self::$enqueued_global = false;
		self::$enqueued_file   = false;
		self::$enqueued_posts  = array();
		self::$inlined_global  = false;
	}

	/**
	 * @param array $report
	 * @param array $args
	 * @return array
	 */
	private static function filter_report( $report, $args ) {
		/**
		 * Filter the Regenerate CSS report.
		 *
		 * @param array $report
		 * @param array $args
		 */
		$filtered = apply_filters( 'canvasly-lite/css/regenerate', $report, $args );
		return is_array( $filtered ) ? $filtered : $report;
	}

	/**
	 * @return array{basedir:string,baseurl:string}
	 */
	private static function uploads() {
		if ( function_exists( 'wp_upload_dir' ) ) {
			$u = wp_upload_dir();
			if ( is_array( $u ) && empty( $u['error'] ) ) {
				return $u;
			}
		}
		return array(
			'basedir' => '',
			'baseurl' => '',
		);
	}

	/**
	 * @return bool
	 */
	private static function ensure_dir() {
		$dir = self::dir();
		if ( $dir === '' ) {
			return false;
		}
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$index = $dir . '/index.php';
		if ( ! is_file( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		return is_dir( $dir );
	}

	/**
	 * @param string $file
	 * @param string $css
	 * @return bool
	 */
	private static function write_file( $file, $css ) {
		if ( ! self::ensure_dir() ) {
			return false;
		}
		$path = self::path( $file );
		if ( $path === '' ) {
			return false;
		}
		$ok = @file_put_contents( $path, (string) $css );
		return $ok !== false;
	}

	/**
	 * @param string $file
	 */
	private static function delete_file( $file ) {
		$path = self::path( $file );
		if ( $path !== '' && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
