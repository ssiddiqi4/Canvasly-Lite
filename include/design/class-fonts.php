<?php
namespace CanvaslyLite\Design;

use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Settings\GlobalSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weight-aware Google Fonts loading (Roadmap 6.2).
 *
 * Collects used families/weights/styles from the document, global typography and
 * theme style; prints CSS2 URLs with only those variants; honours a `font-display`
 * setting; adds preconnect hints; optionally self-hosts files under
 * `uploads/canvasly-lite/fonts` after an explicit consent setting.
 */
class Fonts {
	const DISPLAY_DEFAULT = 'swap';
	const SUBDIR          = 'canvasly-lite/fonts';
	const LOCAL_CSS       = 'local.css';
	const OPTION_CACHE    = 'canvasly_lite_fonts_cache';
	const HANDLE          = 'canvasly-lite-google-fonts';
	const UA              = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

	/** @var array<string,array{weights:array<int,bool>,italic:array<int,bool>}> */
	private static $queued = array();
	/** @var array<string,array{weights:array<int,bool>,italic:array<int,bool>}> */
	private static $flushed = array();
	/** @var array<string,true>|null */
	private static $catalog = null;
	/** @var array<string,true> */
	private static $system = array();
	/** @var bool */
	private static $booted = false;
	/** @var bool */
	private static $needs_remote = false;
	/** @var bool */
	private static $preconnected = false;
	/** @var int */
	private static $flush_n = 0;
	/** @var array<int,true> */
	private static $seen_docs = array();

	public static function displays() {
		return array( 'auto', 'block', 'swap', 'fallback', 'optional' );
	}

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_filter( 'wp_resource_hints', array( self::class, 'resource_hints' ), 10, 2 );
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		add_action( 'canvasly-lite/document/after_save', array( self::class, 'on_after_save' ), 30, 2 );
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'canvasly-lite/tools/screen', array( self::class, 'screen' ), 6 );
			add_action( 'admin_post_lb_fonts_settings', array( self::class, 'handle_settings' ) );
			add_action( 'admin_post_lb_fonts_download', array( self::class, 'handle_download' ) );
			add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
		}
	}

	public static function reset_runtime() {
		self::$queued        = array();
		self::$flushed       = array();
		self::$needs_remote  = false;
		self::$preconnected  = false;
		self::$flush_n       = 0;
		self::$seen_docs     = array();
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
			'/fonts',
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
			'/fonts/download',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_download' ),
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
		self::save_from( $d );
		return rest_ensure_response( self::status() );
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_download( $req ) {
		$d = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		if ( array_key_exists( 'google_fonts_local', $d ) ) {
			self::save_local( $d['google_fonts_local'] );
		}
		return rest_ensure_response( self::download_site( $d ) );
	}

	/**
	 * Persist font keys present on a settings payload (REST / global-settings).
	 *
	 * @param array $d
	 */
	public static function save_from( $d ) {
		$d = is_array( $d ) ? $d : array();
		if ( array_key_exists( 'font_display', $d ) ) {
			self::save_display( $d['font_display'] );
		}
		if ( array_key_exists( 'google_fonts_local', $d ) ) {
			self::save_local( $d['google_fonts_local'] );
		}
	}

	/**
	 * @return array
	 */
	public static function status() {
		$cache = self::cache();
		return array(
			'font_display'       => self::display(),
			'google_fonts_local' => self::is_local(),
			'dir'                => self::dir(),
			'url'                => self::url( '' ),
			'local_css'          => self::file_exists( self::LOCAL_CSS ),
			'local_hash'         => (string) ( $cache['hash'] ?? '' ),
			'files'              => (int) ( $cache['files'] ?? 0 ),
		);
	}

	/**
	 * @param mixed $v
	 * @return string
	 */
	public static function sanitize_display( $v ) {
		$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $v ) : strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) );
		return in_array( $key, self::displays(), true ) ? $key : self::DISPLAY_DEFAULT;
	}

	/**
	 * @param mixed $v
	 * @return bool
	 */
	public static function sanitize_local( $v ) {
		if ( is_string( $v ) ) {
			$v = strtolower( trim( $v ) );
			return in_array( $v, array( '1', 'true', 'yes', 'on' ), true );
		}
		return ! empty( $v );
	}

	/**
	 * @return string
	 */
	public static function display() {
		$raw = '';
		if ( class_exists( GlobalSettings::class ) ) {
			$g   = get_option( GlobalSettings::KEY, array() );
			$raw = is_array( $g ) ? (string) ( $g['font_display'] ?? '' ) : '';
		}
		if ( $raw === '' ) {
			$raw = (string) get_option( 'canvasly_lite_font_display', '' );
		}
		$display = self::sanitize_display( $raw !== '' ? $raw : self::DISPLAY_DEFAULT );
		/**
		 * Filter the CSS font-display strategy used for Google Fonts.
		 *
		 * @param string $display auto|block|swap|fallback|optional
		 */
		$filtered = apply_filters( 'canvasly-lite/fonts/display', $display );
		return self::sanitize_display( is_string( $filtered ) ? $filtered : $display );
	}

	/**
	 * @return bool
	 */
	public static function is_local() {
		$raw = null;
		if ( class_exists( GlobalSettings::class ) ) {
			$g = get_option( GlobalSettings::KEY, array() );
			if ( is_array( $g ) && array_key_exists( 'google_fonts_local', $g ) ) {
				$raw = $g['google_fonts_local'];
			}
		}
		if ( $raw === null ) {
			$raw = get_option( 'canvasly_lite_google_fonts_local', false );
		}
		$local = self::sanitize_local( $raw );
		/**
		 * Filter whether Google Fonts are served from local uploads.
		 *
		 * @param bool $local
		 */
		$filtered = apply_filters( 'canvasly-lite/fonts/local', $local );
		return ! empty( $filtered );
	}

	/**
	 * @param mixed $display
	 * @return string
	 */
	public static function save_display( $display ) {
		$display = self::sanitize_display( $display );
		if ( class_exists( GlobalSettings::class ) ) {
			$g                   = GlobalSettings::get();
			$g['font_display']   = $display;
			update_option( GlobalSettings::KEY, $g, false );
		} else {
			update_option( 'canvasly_lite_font_display', $display, false );
		}
		return $display;
	}

	/**
	 * @param mixed $local
	 * @return bool
	 */
	public static function save_local( $local ) {
		$local = self::sanitize_local( $local );
		if ( class_exists( GlobalSettings::class ) ) {
			$g                       = GlobalSettings::get();
			$g['google_fonts_local'] = $local;
			update_option( GlobalSettings::KEY, $g, false );
		} else {
			update_option( 'canvasly_lite_google_fonts_local', $local ? 1 : 0, false );
		}
		return $local;
	}

	/**
	 * Map a CSS weight keyword or number onto 100–900.
	 *
	 * @param mixed $w
	 * @return int 0 when empty/invalid
	 */
	public static function normalize_weight( $w ) {
		$w = strtolower( trim( (string) $w ) );
		if ( $w === '' ) {
			return 0;
		}
		if ( $w === 'normal' ) {
			return 400;
		}
		if ( $w === 'bold' || $w === 'bolder' ) {
			return 700;
		}
		if ( $w === 'lighter' ) {
			return 300;
		}
		if ( preg_match( '/^[1-9]00$/', $w ) ) {
			return (int) $w;
		}
		if ( preg_match( '/^\d+$/', $w ) ) {
			$n = (int) $w;
			if ( $n >= 100 && $n <= 900 ) {
				$rounded = (int) ( round( $n / 100 ) * 100 );
				return max( 100, min( 900, $rounded ) );
			}
		}
		return 0;
	}

	/**
	 * @param mixed $family
	 * @return string
	 */
	public static function normalize_family( $family ) {
		$family = trim( (string) $family );
		if ( $family === '' ) {
			return '';
		}
		if ( strpos( $family, '{{' ) !== false || strpos( $family, 'var(' ) !== false ) {
			return '';
		}
		if ( preg_match( '/[,\'"]/', $family ) ) {
			return '';
		}
		return $family;
	}

	/**
	 * Web-safe / generic families that must not be requested from Google.
	 *
	 * @param string $family
	 * @return bool
	 */
	public static function is_system_font( $family ) {
		$family = strtolower( trim( (string) $family ) );
		if ( $family === '' ) {
			return true;
		}
		if ( ! self::$system ) {
			$list = array(
				'arial', 'arial black', 'helvetica', 'helvetica neue', 'times', 'times new roman',
				'courier', 'courier new', 'verdana', 'georgia', 'palatino', 'garamond', 'bookman',
				'tahoma', 'trebuchet ms', 'impact', 'comic sans ms', 'lucida console',
				'lucida sans unicode', 'lucida grande', 'geneva', 'monaco', 'menlo', 'consolas',
				'andale mono', 'century gothic', 'franklin gothic medium', 'system-ui',
				'ui-sans-serif', 'ui-serif', 'ui-monospace', 'ui-rounded', 'sans-serif', 'serif',
				'monospace', 'cursive', 'fantasy', 'emoji', 'math', 'fangsong', 'inherit',
				'initial', 'unset', 'revert', 'default', '-apple-system', 'blinkmacsystemfont',
			);
			self::$system = array_fill_keys( $list, true );
		}
		return isset( self::$system[ $family ] );
	}

	/**
	 * @param string $family
	 * @return bool
	 */
	public static function is_google_font( $family ) {
		$family = self::normalize_family( $family );
		if ( $family === '' || self::is_system_font( $family ) ) {
			return false;
		}
		$catalog = self::catalog();
		if ( $catalog ) {
			return isset( $catalog[ strtolower( $family ) ] );
		}
		return true;
	}

	/**
	 * @return array<string,true>
	 */
	public static function catalog() {
		if ( self::$catalog !== null ) {
			return self::$catalog;
		}
		self::$catalog = array();
		$file          = ( defined( 'CANVASLY_LITE_PATH' ) ? CANVASLY_LITE_PATH : '' ) . 'assets/data/google-fonts.json';
		if ( $file === 'assets/data/google-fonts.json' ) {
			return self::$catalog;
		}
		$data = class_exists( '\\CanvaslyLite\\Utils\\JsonCache' )
			? \CanvaslyLite\Utils\JsonCache::read( $file )
			: array();
		if ( ! $data && is_readable( $file ) ) {
			$decoded = json_decode( (string) file_get_contents( $file ), true );
			$data    = is_array( $decoded ) ? $decoded : array();
		}
		foreach ( $data as $name ) {
			if ( is_string( $name ) && $name !== '' ) {
				self::$catalog[ strtolower( $name ) ] = true;
			}
		}
		return self::$catalog;
	}

	/**
	 * Collect used Google Fonts (family => weights/italic) from a node tree plus globals.
	 *
	 * @param array $nodes
	 * @param bool  $include_globals
	 * @return array<string,array{weights:int[],italic:int[]}>
	 */
	public static function collect( $nodes = array(), $include_globals = true ) {
		$usage = array();
		self::collect_from_value( $nodes, $usage, 0 );
		if ( $include_globals ) {
			if ( class_exists( Variables::class ) && method_exists( Variables::class, 'used_fonts' ) ) {
				foreach ( (array) Variables::used_fonts() as $item ) {
					self::take( $usage, $item['font_family'] ?? '', $item['font_weight'] ?? '', $item['font_style'] ?? '' );
				}
			} elseif ( class_exists( Variables::class ) && method_exists( Variables::class, 'used_font_families' ) ) {
				foreach ( (array) Variables::used_font_families() as $f ) {
					self::take( $usage, $f, '', '' );
				}
			}
			if ( class_exists( ThemeStyle::class ) && method_exists( ThemeStyle::class, 'used_fonts' ) ) {
				foreach ( (array) ThemeStyle::used_fonts() as $item ) {
					self::take( $usage, $item['font_family'] ?? '', $item['font_weight'] ?? '', $item['font_style'] ?? '' );
				}
			} elseif ( class_exists( ThemeStyle::class ) && method_exists( ThemeStyle::class, 'used_font_families' ) ) {
				foreach ( (array) ThemeStyle::used_font_families() as $f ) {
					self::take( $usage, $f, '', '' );
				}
			}
		}
		$out = self::finalize( $usage );
		/**
		 * Filter collected Google Font usage.
		 *
		 * @param array $out   family => {weights,italic}
		 * @param array $nodes
		 */
		$filtered = apply_filters( 'canvasly-lite/fonts/usage', $out, $nodes );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * @param array $usage
	 * @return array<string,array{weights:int[],italic:int[]}>
	 */
	public static function finalize( $usage ) {
		$out = array();
		foreach ( (array) $usage as $family => $data ) {
			$family = self::normalize_family( $family );
			if ( $family === '' || ! self::is_google_font( $family ) ) {
				continue;
			}
			$weights = array_keys( is_array( $data['weights'] ?? null ) ? $data['weights'] : array() );
			$italic  = array_keys( is_array( $data['italic'] ?? null ) ? $data['italic'] : array() );
			$weights = array_values( array_unique( array_filter( array_map( 'intval', $weights ) ) ) );
			$italic  = array_values( array_unique( array_filter( array_map( 'intval', $italic ) ) ) );
			sort( $weights, SORT_NUMERIC );
			sort( $italic, SORT_NUMERIC );
			if ( ! $weights && ! $italic ) {
				$weights = array( 400 );
			}
			if ( ! $weights ) {
				$weights = $italic;
			}
			$out[ $family ] = array(
				'weights' => $weights,
				'italic'  => $italic,
			);
		}
		ksort( $out );
		return $out;
	}

	/**
	 * CSS2 URL list for a usage map (chunked if the query string would be long).
	 *
	 * @param array  $usage
	 * @param string $display
	 * @return string[]
	 */
	public static function css2_urls( $usage, $display = '' ) {
		$display = self::sanitize_display( $display !== '' ? $display : self::display() );
		$parts   = array();
		foreach ( (array) $usage as $family => $data ) {
			$spec = self::family_spec( $family, is_array( $data ) ? $data : array() );
			if ( $spec !== '' ) {
				$parts[] = $spec;
			}
		}
		if ( ! $parts ) {
			return array();
		}
		$urls  = array();
		$chunk = array();
		$len   = 0;
		foreach ( $parts as $p ) {
			$add = strlen( $p ) + 1;
			if ( $chunk && ( $len + $add ) > 1800 ) {
				$urls[] = self::join_css2( $chunk, $display );
				$chunk  = array();
				$len    = 0;
			}
			$chunk[] = $p;
			$len    += $add;
		}
		if ( $chunk ) {
			$urls[] = self::join_css2( $chunk, $display );
		}
		return $urls;
	}

	/**
	 * Single CSS2 URL (first chunk). Empty string when usage is empty.
	 *
	 * @param array  $usage
	 * @param string $display
	 * @return string
	 */
	public static function css2_url( $usage, $display = '' ) {
		$urls = self::css2_urls( $usage, $display );
		return $urls ? $urls[0] : '';
	}

	/**
	 * Enqueue Google Fonts (remote or local) used by a node tree.
	 *
	 * @param array $nodes
	 */
	public static function enqueue( $nodes ) {
		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		$usage         = self::collect( is_array( $nodes ) ? $nodes : array() );
		self::$queued  = self::merge_usage( self::$queued, $usage );
		self::flush();
	}

	/**
	 * @return bool
	 */
	public static function needs_remote() {
		return self::$needs_remote;
	}

	/**
	 * Preconnect hints for Google Fonts CDNs when remote stylesheets are used.
	 *
	 * @param array  $urls
	 * @param string $relation
	 * @return array
	 */
	public static function resource_hints( $urls, $relation ) {
		if ( $relation !== 'preconnect' || self::is_local() || ! self::$needs_remote ) {
			return is_array( $urls ) ? $urls : array();
		}
		$urls = is_array( $urls ) ? $urls : array();
		$have_g = false;
		$have_s = false;
		foreach ( $urls as $u ) {
			$href = is_array( $u ) ? (string) ( $u['href'] ?? '' ) : (string) $u;
			if ( strpos( $href, 'fonts.googleapis.com' ) !== false ) {
				$have_g = true;
			}
			if ( strpos( $href, 'fonts.gstatic.com' ) !== false ) {
				$have_s = true;
			}
		}
		if ( ! $have_g ) {
			$urls[] = 'https://fonts.googleapis.com';
		}
		if ( ! $have_s ) {
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}
		self::$preconnected = true;
		return $urls;
	}

	/**
	 * Download used fonts for the whole site into uploads.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function download_site( $args = array() ) {
		$args  = is_array( $args ) ? $args : array();
		$usage = self::collect_site( $args );
		$out   = self::ensure_local( $usage, true );
		$out['usage'] = array_keys( $usage );
		return $out;
	}

	/**
	 * @param array $args
	 * @return array<string,array{weights:int[],italic:int[]}>
	 */
	public static function collect_site( $args = array() ) {
		$usage = self::collect( array(), true );
		$ids   = array();
		if ( isset( $args['ids'] ) && is_array( $args['ids'] ) ) {
			foreach ( $args['ids'] as $id ) {
				$id = absint( $id );
				if ( $id ) {
					$ids[] = $id;
				}
			}
		} elseif ( class_exists( CssPrint::class ) && method_exists( CssPrint::class, 'document_ids' ) ) {
			$ids = CssPrint::document_ids( isset( $args['limit'] ) ? absint( $args['limit'] ) : 200 );
		}
		foreach ( $ids as $id ) {
			$doc = self::document_root( $id );
			if ( $doc ) {
				$usage = self::merge_final( $usage, self::collect( $doc, false ) );
			}
		}
		return $usage;
	}

	/**
	 * Write local CSS + font files for a usage map. Returns a report.
	 *
	 * @param array $usage
	 * @param bool  $force
	 * @return array
	 */
	public static function ensure_local( $usage, $force = false ) {
		$usage = self::finalize( is_array( $usage ) ? $usage : array() );
		$report = array(
			'ok'      => false,
			'local'   => true,
			'written' => 0,
			'files'   => 0,
			'failed'  => 0,
			'hash'    => '',
			'css'     => false,
			'message' => '',
		);
		if ( ! $usage ) {
			$report['ok']      = true;
			$report['message'] = 'empty';
			return $report;
		}
		$display   = self::display();
		$signature = self::signature( $usage, $display );
		$cache     = self::cache();
		if ( ! $force && ( $cache['signature'] ?? '' ) === $signature && self::file_exists( self::LOCAL_CSS ) ) {
			$report['ok']    = true;
			$report['hash']  = (string) ( $cache['hash'] ?? '' );
			$report['files'] = (int) ( $cache['files'] ?? 0 );
			$report['css']   = true;
			return $report;
		}
		$css = '';
		foreach ( self::css2_urls( $usage, $display ) as $url ) {
			$body = self::http_body( $url, false );
			if ( $body === '' ) {
				$report['failed']++;
				continue;
			}
			$rewritten = self::rewrite_css( $body, $report );
			if ( $rewritten !== '' ) {
				$css .= $rewritten . "\n";
			}
		}
		if ( $css === '' ) {
			$report['message'] = 'download-failed';
			return $report;
		}
		if ( class_exists( Performance::class ) && method_exists( Performance::class, 'minify_css' ) ) {
			$css = Performance::minify_css( $css );
		}
		if ( ! self::write_file( self::LOCAL_CSS, $css ) ) {
			$report['message'] = 'write-failed';
			return $report;
		}
		$hash = substr( md5( $css ), 0, 12 );
		self::save_cache(
			array(
				'signature' => $signature,
				'hash'      => $hash,
				'files'     => (int) $report['written'],
			)
		);
		$report['ok']     = true;
		$report['css']    = true;
		$report['hash']   = $hash;
		$report['files']  = (int) $report['written'];
		return $report;
	}

	/**
	 * Rewrite Google Fonts CSS so @font-face src urls point at local files.
	 *
	 * @param string $css
	 * @param array  $report
	 * @return string
	 */
	public static function rewrite_css( $css, &$report = null ) {
		$css = (string) $css;
		if ( $report === null ) {
			$report = array( 'written' => 0, 'failed' => 0 );
		}
		$display = self::display();
		$css     = preg_replace( '/font-display\s*:\s*[a-z]+/i', 'font-display:' . $display, $css );
		$rewritten = preg_replace_callback(
			'#url\(\s*([\'"]?)(https://fonts\.gstatic\.com/[^)\'"\s]+)\1\s*\)#i',
			function ( $m ) use ( &$report ) {
				$remote = $m[2];
				$local  = self::download_file( $remote );
				if ( $local === '' ) {
					$report['failed'] = (int) ( $report['failed'] ?? 0 ) + 1;
					return $m[0];
				}
				$report['written'] = (int) ( $report['written'] ?? 0 ) + 1;
				return 'url(' . $local . ')';
			},
			$css
		);
		return is_string( $rewritten ) ? $rewritten : $css;
	}

	/**
	 * Public URL of the generated local stylesheet, or empty.
	 *
	 * @return string
	 */
	public static function local_css_url() {
		if ( ! self::file_exists( self::LOCAL_CSS ) ) {
			return '';
		}
		$url  = self::url( self::LOCAL_CSS );
		$hash = (string) ( self::cache()['hash'] ?? '' );
		if ( $hash !== '' && $url !== '' ) {
			$url = add_query_arg( 'ver', $hash, $url );
		}
		return $url;
	}

	/**
	 * After a document save, refresh local font files when self-hosting is on.
	 *
	 * @param int   $id
	 * @param array $doc
	 */
	public static function on_after_save( $id, $doc = array() ) {
		if ( ! self::is_local() ) {
			return;
		}
		$root = is_array( $doc ) ? ( $doc['root'] ?? array() ) : array();
		self::ensure_local( self::collect( is_array( $root ) ? $root : array() ), false );
	}

	public static function tools_url() {
		if ( class_exists( '\\CanvaslyLite\\Settings\\AdminSettings' ) ) {
			return \CanvaslyLite\Settings\AdminSettings::tools_or_settings_url();
		}
		return admin_url( 'admin.php?page=canvasly-lite-tools' );
	}

	public static function handle_settings() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can change font loading settings.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_fonts_settings' );
		$display = self::save_display( sanitize_text_field( wp_unslash( $_POST['font_display'] ?? self::DISPLAY_DEFAULT ) ) );
		$local   = self::save_local( ! empty( $_POST['google_fonts_local'] ) );
		if ( $local ) {
			self::download_site( array() );
		}
		self::store_notice(
			'success',
			sprintf(
				/* translators: %s: font-display value */
				__( 'Font loading saved. font-display is %s.', 'canvasly-lite' ),
				$display
			)
		);
		wp_safe_redirect( self::tools_url() );
		exit;
	}

	public static function handle_download() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can download Google Fonts.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_fonts_download' );
		if ( ! self::is_local() ) {
			self::save_local( true );
		}
		$out = self::download_site( array() );
		$ttl = defined( 'MINUTE_IN_SECONDS' ) ? 10 * MINUTE_IN_SECONDS : 600;
		if ( function_exists( 'set_transient' ) ) {
			set_transient( 'canvasly_lite_fonts_report_' . get_current_user_id(), $out, $ttl );
		}
		self::store_notice(
			! empty( $out['ok'] ) ? 'success' : 'error',
			! empty( $out['ok'] )
				? sprintf(
					/* translators: 1: font files written */
					__( 'Downloaded Google Fonts locally: %d files written.', 'canvasly-lite' ),
					(int) ( $out['written'] ?? 0 )
				)
				: __( 'Could not download Google Fonts. Check that the site can reach fonts.googleapis.com.', 'canvasly-lite' )
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
		$n = get_transient( 'canvasly_lite_fonts_notice_' . get_current_user_id() );
		if ( ! is_array( $n ) ) {
			return;
		}
		delete_transient( 'canvasly_lite_fonts_notice_' . get_current_user_id() );
		$class = ( $n['type'] ?? '' ) === 'success' ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $n['message'] ?? '' ) ) . '</p></div>';
	}

	public static function screen() {
		if ( ! self::can_manage() ) {
			return;
		}
		$display = self::display();
		$local   = self::is_local();
		$report  = function_exists( 'get_transient' ) ? get_transient( 'canvasly_lite_fonts_report_' . get_current_user_id() ) : null;
		$labels  = array(
			'auto'     => __( 'Auto', 'canvasly-lite' ),
			'block'    => __( 'Block', 'canvasly-lite' ),
			'swap'     => __( 'Swap (recommended)', 'canvasly-lite' ),
			'fallback' => __( 'Fallback', 'canvasly-lite' ),
			'optional' => __( 'Optional', 'canvasly-lite' ),
		);

		echo '<hr><h2>' . esc_html__( 'Font loading', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Google Fonts are requested with only the weights and styles a document actually uses. font-display controls how text renders while a webfont loads. Self-hosting downloads those files into uploads/canvasly-lite/fonts so visitors never connect to Google.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_fonts_settings' );
		echo '<input type="hidden" name="action" value="lb_fonts_settings">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'font-display', 'canvasly-lite' ) . '</th><td><select name="font_display">';
		foreach ( $labels as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . ( $display === $k ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Self-host Google Fonts', 'canvasly-lite' ) . '</th><td>';
		echo '<label><input type="checkbox" name="google_fonts_local" value="1"' . ( $local ? ' checked' : '' ) . '> ' . esc_html__( 'Download used Google Fonts to this site and serve them locally. Enable only if you agree to host the font files on your server (visitors will not connect to Google).', 'canvasly-lite' ) . '</label>';
		echo '</td></tr></tbody></table>';
		echo '<p><button class="button" type="submit">' . esc_html__( 'Save font loading', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lb_fonts_download' );
		echo '<input type="hidden" name="action" value="lb_fonts_download">';
		echo '<p><button class="button" type="submit">' . esc_html__( 'Download Google Fonts', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';

		if ( is_array( $report ) ) {
			echo '<p><strong>' . esc_html__( 'Last download', 'canvasly-lite' ) . '</strong> ';
			echo esc_html(
				sprintf(
					/* translators: 1: files written, 2: failures */
					__( '%1$d files written, %2$d failed.', 'canvasly-lite' ),
					(int) ( $report['written'] ?? 0 ),
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
		$ttl = defined( 'MINUTE_IN_SECONDS' ) ? 10 * MINUTE_IN_SECONDS : 600;
		set_transient(
			'canvasly_lite_fonts_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			$ttl
		);
	}

	private static function flush() {
		$usage = self::finalize( self::$queued );
		if ( ! $usage ) {
			return;
		}
		if ( self::is_local() ) {
			self::flush_local( $usage );
			return;
		}
		$delta = self::delta( $usage, self::finalize( self::$flushed ) );
		if ( ! $delta ) {
			return;
		}
		$display = self::display();
		foreach ( self::css2_urls( $delta, $display ) as $url ) {
			/**
			 * Filter a Google Fonts CSS2 URL before enqueue.
			 *
			 * @param string $url
			 * @param array  $delta
			 */
			$filtered = apply_filters( 'canvasly-lite/fonts/url', $url, $delta );
			$url      = is_string( $filtered ) && $filtered !== '' ? $filtered : $url;
			self::$flush_n++;
			$handle  = self::HANDLE . ( self::$flush_n === 1 ? '' : '-' . self::$flush_n );
			$version = defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '1.0.0';
			wp_enqueue_style( $handle, $url, array(), $version );
			self::$needs_remote = true;
		}
		self::$flushed = self::merge_usage( self::$flushed, $delta );
	}

	/**
	 * @param array $usage
	 */
	private static function flush_local( $usage ) {
		$report = self::ensure_local( $usage, false );
		$url    = self::url( self::LOCAL_CSS );
		if ( $url === '' || empty( $report['ok'] ) || ! self::file_exists( self::LOCAL_CSS ) ) {
			return;
		}
		$hash = (string) ( $report['hash'] ?? ( self::cache()['hash'] ?? '' ) );
		$ver  = $hash !== '' ? $hash : ( defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0' );
		wp_enqueue_style( self::HANDLE, $url, array(), $ver );
		self::$flushed = self::merge_usage( self::$flushed, $usage );
	}

	/**
	 * @param mixed  $v
	 * @param array  $usage
	 * @param int    $depth
	 */
	private static function collect_from_value( $v, &$usage, $depth ) {
		if ( $depth > 16 || ! is_array( $v ) ) {
			return;
		}
		$family = $v['font_family'] ?? '';
		$weight = $v['font_weight'] ?? ( $v['weight'] ?? '' );
		$style  = $v['font_style'] ?? '';
		if ( is_string( $family ) && $family !== '' ) {
			self::take( $usage, $family, $weight, $style );
		}
		foreach ( $v as $key => $item ) {
			if ( $key === 'font_family' || $key === 'font_weight' || $key === 'weight' || $key === 'font_style' ) {
				continue;
			}
			if ( $key === 'children' && is_array( $item ) ) {
				self::collect_from_value( $item, $usage, $depth + 1 );
				continue;
			}
			if ( $key === 'settings' && is_array( $item ) ) {
				$type = (string) ( $v['type'] ?? '' );
				$tid = 0;
				if ( 'template' === $type ) {
					$tid = absint( $item['template_id'] ?? 0 );
				} elseif ( 'collection_loop' === $type && class_exists( '\\CanvaslyLite\\Units\\CollectionLoop' ) ) {
					$tid = \CanvaslyLite\Units\CollectionLoop::embedded_template_id( $item );
				}
				if ( $tid ) {
					$embed = self::document_root( $tid );
					if ( $embed ) {
						self::collect_from_value( $embed, $usage, $depth + 1 );
					}
				}
				self::collect_from_value( $item, $usage, $depth + 1 );
				continue;
			}
			if ( is_array( $item ) ) {
				self::collect_from_value( $item, $usage, $depth + 1 );
			}
		}
	}

	/**
	 * @param array  $usage
	 * @param mixed  $family
	 * @param mixed  $weight
	 * @param mixed  $style
	 */
	private static function take( &$usage, $family, $weight, $style ) {
		$family = self::normalize_family( $family );
		if ( $family === '' || ! self::is_google_font( $family ) ) {
			return;
		}
		if ( ! isset( $usage[ $family ] ) ) {
			$usage[ $family ] = array(
				'weights' => array(),
				'italic'  => array(),
			);
		}
		$w     = self::normalize_weight( $weight );
		$style = strtolower( trim( (string) $style ) );
		$ital  = ( $style === 'italic' || $style === 'oblique' );
		if ( $w ) {
			$usage[ $family ]['weights'][ $w ] = true;
			if ( $ital ) {
				$usage[ $family ]['italic'][ $w ] = true;
			}
		} elseif ( $ital ) {
			$usage[ $family ]['italic'][ 400 ]  = true;
			$usage[ $family ]['weights'][ 400 ] = true;
		}
	}

	/**
	 * @param array $a
	 * @param array $b
	 * @return array
	 */
	private static function merge_usage( $a, $b ) {
		$out = is_array( $a ) ? $a : array();
		foreach ( (array) $b as $family => $data ) {
			if ( ! isset( $out[ $family ] ) ) {
				$out[ $family ] = array(
					'weights' => array(),
					'italic'  => array(),
				);
			}
			foreach ( array( 'weights', 'italic' ) as $k ) {
				$vals = $data[ $k ] ?? array();
				if ( isset( $vals[0] ) || $vals === array() ) {
					foreach ( (array) $vals as $w ) {
						$w = (int) $w;
						if ( $w ) {
							$out[ $family ][ $k ][ $w ] = true;
						}
					}
				} else {
					foreach ( (array) $vals as $w => $flag ) {
						if ( $flag ) {
							$out[ $family ][ $k ][ (int) $w ] = true;
						}
					}
				}
			}
		}
		return $out;
	}

	/**
	 * @param array $a finalized
	 * @param array $b finalized
	 * @return array
	 */
	private static function merge_final( $a, $b ) {
		return self::finalize( self::merge_usage( self::to_sets( $a ), self::to_sets( $b ) ) );
	}

	/**
	 * @param array $usage finalized
	 * @return array
	 */
	private static function to_sets( $usage ) {
		$out = array();
		foreach ( (array) $usage as $family => $data ) {
			$out[ $family ] = array(
				'weights' => array(),
				'italic'  => array(),
			);
			foreach ( (array) ( $data['weights'] ?? array() ) as $w ) {
				$out[ $family ]['weights'][ (int) $w ] = true;
			}
			foreach ( (array) ( $data['italic'] ?? array() ) as $w ) {
				$out[ $family ]['italic'][ (int) $w ] = true;
			}
		}
		return $out;
	}

	/**
	 * Families/variants in $full that are not yet in $have.
	 *
	 * @param array $full
	 * @param array $have
	 * @return array
	 */
	private static function delta( $full, $have ) {
		$out = array();
		foreach ( $full as $family => $data ) {
			if ( ! isset( $have[ $family ] ) ) {
				$out[ $family ] = $data;
				continue;
			}
			$w = array_values( array_diff( $data['weights'], $have[ $family ]['weights'] ) );
			$i = array_values( array_diff( $data['italic'], $have[ $family ]['italic'] ) );
			if ( $w || $i ) {
				$out[ $family ] = array(
					'weights' => $w ? $w : $data['weights'],
					'italic'  => $i,
				);
			}
		}
		return $out;
	}

	/**
	 * @param string $family
	 * @param array  $data
	 * @return string
	 */
	private static function family_spec( $family, $data ) {
		$family = self::normalize_family( $family );
		if ( $family === '' || ! self::is_google_font( $family ) ) {
			return '';
		}
		$weights = array_values( array_unique( array_map( 'intval', (array) ( $data['weights'] ?? array() ) ) ) );
		$italic  = array_values( array_unique( array_map( 'intval', (array) ( $data['italic'] ?? array() ) ) ) );
		sort( $weights, SORT_NUMERIC );
		sort( $italic, SORT_NUMERIC );
		if ( ! $weights ) {
			$weights = array( 400 );
		}
		$enc = str_replace( '%20', '+', rawurlencode( $family ) );
		if ( $italic ) {
			$pairs = array();
			foreach ( $weights as $w ) {
				$pairs[] = '0,' . $w;
			}
			foreach ( $italic as $w ) {
				$pairs[] = '1,' . $w;
			}
			$pairs = array_values( array_unique( $pairs ) );
			return 'family=' . $enc . ':ital,wght@' . implode( ';', $pairs );
		}
		return 'family=' . $enc . ':wght@' . implode( ';', $weights );
	}

	/**
	 * @param string[] $parts
	 * @param string   $display
	 * @return string
	 */
	private static function join_css2( $parts, $display ) {
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $parts ) . '&display=' . self::sanitize_display( $display );
	}

	/**
	 * @param array  $usage
	 * @param string $display
	 * @return string
	 */
	private static function signature( $usage, $display ) {
		return md5( wp_json_encode( array( $usage, self::sanitize_display( $display ) ) ) );
	}

	/**
	 * @param int $id
	 * @return array
	 */
	private static function document_root( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return array();
		}
		if ( isset( self::$seen_docs[ $id ] ) ) {
			return array();
		}
		self::$seen_docs[ $id ] = true;
		if ( class_exists( '\\CanvaslyLite\\Templates\\TemplateEmbed' ) ) {
			$doc = \CanvaslyLite\Templates\TemplateEmbed::document( $id );
			if ( is_array( $doc ) && ! empty( $doc['root'] ) && is_array( $doc['root'] ) ) {
				return $doc['root'];
			}
		}
		if ( class_exists( DocumentManager::class ) ) {
			$doc = DocumentManager::get( $id );
			if ( is_array( $doc ) && ! empty( $doc['root'] ) && is_array( $doc['root'] ) ) {
				return $doc['root'];
			}
		}
		$raw = get_post_meta( $id, '_lb_document_data', true );
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $raw ) && ! empty( $raw['root'] ) && is_array( $raw['root'] ) ? $raw['root'] : array();
	}

	/**
	 * @return array
	 */
	private static function cache() {
		$c = get_option( self::OPTION_CACHE, array() );
		return is_array( $c ) ? $c : array();
	}

	/**
	 * @param array $c
	 */
	private static function save_cache( $c ) {
		update_option( self::OPTION_CACHE, is_array( $c ) ? $c : array(), false );
	}

	/**
	 * @param string $url
	 * @param bool   $binary
	 * @return string
	 */
	private static function http_body( $url, $binary = false ) {
		$args = array(
			'timeout'    => $binary ? 20 : 15,
			'user-agent' => self::UA,
			'headers'    => array(
				'Accept' => $binary ? '*/*' : 'text/css,*/*;q=0.1',
			),
		);
		$pre = apply_filters( 'canvasly-lite/fonts/remote_get', null, $url, $args );
		if ( is_array( $pre ) ) {
			$code = (int) ( $pre['response']['code'] ?? ( $pre['code'] ?? 0 ) );
			$body = (string) ( $pre['body'] ?? '' );
			return ( $code === 200 || ( $code === 0 && $body !== '' ) ) ? $body : '';
		}
		if ( is_wp_error( $pre ) ) {
			return '';
		}
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return '';
		}
		$res = wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) ) {
			return '';
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code !== 200 ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $res );
	}

	/**
	 * Download a fonts.gstatic.com file and return its public URL.
	 *
	 * @param string $remote
	 * @return string
	 */
	private static function download_file( $remote ) {
		$remote = (string) $remote;
		$parts  = wp_parse_url( $remote );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( $host !== 'fonts.gstatic.com' ) {
			return '';
		}
		$ext = strtolower( pathinfo( (string) ( $parts['path'] ?? '' ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'woff2', 'woff', 'ttf', 'otf' ), true ) ) {
			$ext = 'woff2';
		}
		$rel = 'files/' . substr( md5( $remote ), 0, 16 ) . '.' . $ext;
		if ( ! self::file_exists( $rel ) ) {
			$body = self::http_body( $remote, true );
			if ( $body === '' ) {
				return '';
			}
			if ( ! self::write_file( $rel, $body ) ) {
				return '';
			}
		}
		return self::url( $rel );
	}

	/**
	 * @return string
	 */
	public static function dir() {
		$u    = self::uploads();
		$base = (string) ( $u['basedir'] ?? '' );
		if ( $base === '' ) {
			return '';
		}
		return rtrim( str_replace( '\\', '/', $base ), '/' ) . '/' . self::SUBDIR;
	}

	/**
	 * @param string $file
	 * @return string
	 */
	public static function url( $file = '' ) {
		$u    = self::uploads();
		$base = (string) ( $u['baseurl'] ?? '' );
		if ( $base === '' ) {
			return '';
		}
		$url  = rtrim( $base, '/' ) . '/' . self::SUBDIR;
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
		$files = $dir . '/files';
		if ( ! is_dir( $files ) ) {
			wp_mkdir_p( $files );
		}
		foreach ( array( $dir, $files ) as $d ) {
			$index = $d . '/index.php';
			if ( is_dir( $d ) && ! is_file( $index ) ) {
				@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
			}
		}
		return is_dir( $dir );
	}

	/**
	 * @param string $file
	 * @param string $contents
	 * @return bool
	 */
	private static function write_file( $file, $contents ) {
		if ( ! self::ensure_dir() ) {
			return false;
		}
		$path = self::path( $file );
		if ( $path === '' ) {
			return false;
		}
		$parent = dirname( $path );
		if ( ! is_dir( $parent ) ) {
			wp_mkdir_p( $parent );
		}
		$ok = @file_put_contents( $path, (string) $contents );
		return $ok !== false;
	}
}
