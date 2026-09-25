<?php
namespace CanvaslyLite\Templates;

use CanvaslyLite\Settings\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saved edits to an inherited theme header or footer.
 *
 * A page can keep its own copy. Otherwise the site-wide theme copy is used.
 * Empty HTML means "use the theme file" for that part.
 */
class ThemeChromeEdits {
	const OPTION = 'canvasly_theme_chrome_html';
	const META   = '_canvasly_theme_chrome_html';
	const SCOPE  = '_canvasly_theme_chrome_scope';

	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
		add_filter( 'render_block', array( self::class, 'filter_block' ), 20, 2 );
	}

	public static function register_route() {
		register_rest_route(
			'canvasly-lite/v1',
			'/theme-chrome',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_save' ),
				'permission_callback' => array( self::class, 'can_save' ),
			)
		);
	}

	/**
	 * @param mixed $request
	 * @return bool
	 */
	public static function can_save( $request ) {
		if ( ! ThemeChrome::can_read() ) {
			return false;
		}
		$scope = self::param_string( $request, 'scope' );
		if ( 'theme' === $scope && ! self::can_publish_theme() ) {
			return false;
		}
		$post_id = self::param_int( $request, 'post_id' );
		if ( $post_id && function_exists( 'current_user_can' ) ) {
			$can = current_user_can( 'edit_post', $post_id ) || current_user_can( 'edit_page', $post_id ) || current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
			if ( ! $can ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public static function can_publish_theme() {
		if ( class_exists( Roles::class ) && Roles::can_design() ) {
			return true;
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_theme_options' );
	}

	/**
	 * @param mixed $request
	 * @return \WP_REST_Response|\WP_Error|array
	 */
	public static function rest_save( $request ) {
		$post_id = self::param_int( $request, 'post_id' );
		$scope   = self::param_string( $request, 'scope' );
		$parts   = self::param_array( $request, 'parts' );
		$saved   = self::save( $post_id, $scope, $parts );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $saved ) ) {
			return $saved;
		}
		$payload = self::payload( $post_id );
		return function_exists( 'rest_ensure_response' ) ? rest_ensure_response( $payload ) : $payload;
	}

	/**
	 * HTML the editor should show, plus the live theme and both saved copies.
	 *
	 * @param int $post_id
	 * @return array
	 */
	public static function payload( $post_id ) {
		$post_id = absint( $post_id );
		$live    = array(
			'header' => ThemeChrome::provides() ? ThemeChrome::markup( 'header' ) : '',
			'footer' => ThemeChrome::provides() ? ThemeChrome::markup( 'footer' ) : '',
		);
		$page    = self::page_bundle( $post_id );
		$global  = self::global_bundle();
		return array(
			'inherit' => ThemeChrome::provides(),
			'header'  => self::prefer( $page['header'], $global['header'], $live['header'] ),
			'footer'  => self::prefer( $page['footer'], $global['footer'], $live['footer'] ),
			'live'    => $live,
			'page'    => $page,
			'global'  => $global,
			'scope'   => self::scope( $post_id ),
			'styles'  => ThemeChrome::editor_styles(),
			'home'    => function_exists( 'home_url' ) ? home_url( '/' ) : '',
		);
	}

	/**
	 * @param int    $post_id
	 * @param string $scope page|theme
	 * @param array  $parts header/footer HTML. Omitted keys are left unchanged.
	 * @return true|\WP_Error
	 */
	public static function save( $post_id, $scope, $parts ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return new \WP_Error( 'canvasly_theme_chrome', 'Missing page.', array( 'status' => 400 ) );
		}
		$scope = 'theme' === $scope ? 'theme' : 'page';
		if ( 'theme' === $scope && ! self::can_publish_theme() ) {
			return new \WP_Error( 'canvasly_theme_chrome_scope', 'You cannot change the theme header and footer.', array( 'status' => 403 ) );
		}
		$parts  = is_array( $parts ) ? $parts : array();
		$bundle = 'theme' === $scope ? self::global_bundle() : self::page_bundle( $post_id );
		foreach ( array( 'header', 'footer' ) as $part ) {
			if ( ! array_key_exists( $part, $parts ) ) {
				continue;
			}
			$bundle[ $part ] = self::store_html( $part, $parts[ $part ] );
			if ( 'theme' === $scope ) {
				self::clear_page_part( $post_id, $part );
			}
		}
		if ( 'theme' === $scope ) {
			update_option( self::OPTION, $bundle, false );
		} else {
			update_post_meta( $post_id, self::META, $bundle );
		}
		update_post_meta( $post_id, self::SCOPE, $scope );
		return true;
	}

	/**
	 * Saved HTML for this request, or an empty string to keep the theme file.
	 *
	 * @param int    $post_id
	 * @param string $part
	 * @return string
	 */
	public static function html( $post_id, $part ) {
		$part = 'footer' === $part ? 'footer' : 'header';
		$page = self::page_bundle( absint( $post_id ) );
		if ( '' !== $page[ $part ] ) {
			return $page[ $part ];
		}
		$global = self::global_bundle();
		return $global[ $part ];
	}

	/**
	 * @param string $part
	 * @return bool
	 */
	public static function has( $part ) {
		return '' !== self::html( self::current_post_id(), $part );
	}

	/**
	 * Print a saved part. False means the caller should print the theme file.
	 *
	 * @param string $part
	 * @return bool
	 */
	public static function echo_part( $part ) {
		$html = self::html( self::current_post_id(), $part );
		if ( '' === $html ) {
			return false;
		}
		echo self::expand_units( $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized in clean(), units rendered by the unit renderer.
		return true;
	}

	/**
	 * Replace editor placeholders with the live unit markup.
	 *
	 * The placeholder stores a copy of the unit so a theme-wide header still
	 * renders on pages that do not have the unit in their own document. The
	 * current page's document copy wins when it has the same id.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function expand_units( $html ) {
		$html = (string) $html;
		if ( false === strpos( $html, 'data-lb-unit' ) ) {
			return $html;
		}
		$out = preg_replace_callback(
			'/<div\b(?=[^>]*\bdata-lb-unit=)[^>]*>\s*<\/div>/i',
			array( self::class, 'expand_unit_tag' ),
			$html
		);
		return is_string( $out ) ? $out : $html;
	}

	/**
	 * @param array $match
	 * @return string
	 */
	public static function expand_unit_tag( $match ) {
		$tag  = isset( $match[0] ) ? (string) $match[0] : '';
		$id   = '';
		$json = '';
		if ( preg_match( '/\bdata-lb-unit=(["\'])([^"\']+)\1/', $tag, $id_match ) ) {
			$id = (string) $id_match[2];
		}
		if ( preg_match( '/\bdata-lb-unit-json=(["\'])([^"\']+)\1/', $tag, $json_match ) ) {
			$json = (string) $json_match[2];
		}
		$node = self::unit_node( $id, $json );
		if ( ! is_array( $node ) || empty( $node['type'] ) ) {
			return '';
		}
		if ( ! class_exists( '\\CanvaslyLite\\Rendering\\FrontendRenderer' ) ) {
			return '';
		}
		return \CanvaslyLite\Rendering\FrontendRenderer::render_nodes( array( $node ), self::current_post_id() );
	}

	/**
	 * @param string $id
	 * @param string $json Base64 of a URI-encoded node.
	 * @return array|null
	 */
	public static function unit_node( $id, $json ) {
		$id  = (string) $id;
		$doc = array();
		if ( class_exists( '\\CanvaslyLite\\Document\\DocumentManager' ) ) {
			$loaded = \CanvaslyLite\Document\DocumentManager::get( self::current_post_id() );
			$doc    = is_array( $loaded ) ? $loaded : array();
		}
		foreach ( array( 'header', 'footer', 'root' ) as $part ) {
			$nodes = isset( $doc[ $part ] ) && is_array( $doc[ $part ] ) ? $doc[ $part ] : array();
			$found = self::find_node( $nodes, $id );
			if ( $found ) {
				return $found;
			}
		}
		$decoded = self::decode_unit( $json );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * @param array  $nodes
	 * @param string $id
	 * @return array|null
	 */
	private static function find_node( $nodes, $id ) {
		if ( '' === $id ) {
			return null;
		}
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( (string) ( $node['id'] ?? '' ) === $id ) {
				return $node;
			}
			$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
			$found    = self::find_node( $children, $id );
			if ( $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * @param string $json
	 * @return array|null
	 */
	public static function decode_unit( $json ) {
		$json = (string) $json;
		if ( '' === $json ) {
			return null;
		}
		$raw = base64_decode( $json, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Replace a theme template-part block when this page or the theme has an edit.
	 *
	 * @param string $html
	 * @param mixed  $block
	 * @return string
	 */
	public static function filter_block( $html, $block ) {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return $html;
		}
		if ( ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== 'core/template-part' ) {
			return $html;
		}
		$slug = isset( $block['attrs']['slug'] ) ? (string) $block['attrs']['slug'] : '';
		if ( 'header' !== $slug && 'footer' !== $slug ) {
			return $html;
		}
		$custom = self::html( self::current_post_id(), $slug );
		return self::expand_units( '' !== $custom ? $custom : $html );
	}

	/**
	 * @param int $post_id
	 * @return string page|theme
	 */
	public static function scope( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! function_exists( 'get_post_meta' ) ) {
			return 'page';
		}
		$value = get_post_meta( $post_id, self::SCOPE, true );
		return 'theme' === $value ? 'theme' : 'page';
	}

	/**
	 * @param string $part
	 * @param mixed  $html
	 * @return string
	 */
	private static function store_html( $part, $html ) {
		$html = self::clean( $html );
		$live = ThemeChrome::provides() ? self::clean( ThemeChrome::markup( $part ) ) : '';
		if ( self::norm( $html ) === self::norm( $live ) ) {
			return '';
		}
		return $html;
	}

	/**
	 * @param mixed $html
	 * @return string
	 */
	public static function clean( $html ) {
		$html = ThemeChrome::safe_html( (string) $html );
		$html = preg_replace( '/\scontenteditable\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html );
		$html = preg_replace( '/\sspellcheck\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $html );
		$html = (string) $html;
		if ( strlen( $html ) > ThemeChrome::MAX_HTML ) {
			$html = substr( $html, 0, ThemeChrome::MAX_HTML );
		}
		return trim( $html );
	}

	/**
	 * @param int    $post_id
	 * @param string $part
	 */
	private static function clear_page_part( $post_id, $part ) {
		$bundle = self::page_bundle( $post_id );
		if ( '' === $bundle['header'] && '' === $bundle['footer'] ) {
			return;
		}
		$bundle[ $part ] = '';
		if ( '' === $bundle['header'] && '' === $bundle['footer'] ) {
			delete_post_meta( $post_id, self::META );
			return;
		}
		update_post_meta( $post_id, self::META, $bundle );
	}

	/**
	 * @param int $post_id
	 * @return array{header:string,footer:string}
	 */
	private static function page_bundle( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! function_exists( 'get_post_meta' ) ) {
			return self::bundle( array() );
		}
		return self::bundle( get_post_meta( $post_id, self::META, true ) );
	}

	/**
	 * @return array{header:string,footer:string}
	 */
	private static function global_bundle() {
		if ( ! function_exists( 'get_option' ) ) {
			return self::bundle( array() );
		}
		return self::bundle( get_option( self::OPTION, array() ) );
	}

	/**
	 * @param mixed $raw
	 * @return array{header:string,footer:string}
	 */
	private static function bundle( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'header' => self::clean( $raw['header'] ?? '' ),
			'footer' => self::clean( $raw['footer'] ?? '' ),
		);
	}

	/**
	 * @param string $page
	 * @param string $global
	 * @param string $live
	 * @return string
	 */
	private static function prefer( $page, $global, $live ) {
		if ( '' !== $page ) {
			return $page;
		}
		if ( '' !== $global ) {
			return $global;
		}
		return (string) $live;
	}

	/**
	 * @return int
	 */
	private static function current_post_id() {
		if ( function_exists( 'get_queried_object_id' ) ) {
			$id = absint( get_queried_object_id() );
			if ( $id ) {
				return $id;
			}
		}
		if ( function_exists( 'get_the_ID' ) ) {
			return absint( get_the_ID() );
		}
		return 0;
	}

	/**
	 * @param string $html
	 * @return string
	 */
	private static function norm( $html ) {
		return trim( preg_replace( '/\s+/', ' ', (string) $html ) );
	}

	/**
	 * @param mixed  $request
	 * @param string $key
	 * @return int
	 */
	private static function param_int( $request, $key ) {
		return absint( self::param( $request, $key, 0 ) );
	}

	/**
	 * @param mixed  $request
	 * @param string $key
	 * @return string
	 */
	private static function param_string( $request, $key ) {
		$value = self::param( $request, $key, '' );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * @param mixed  $request
	 * @param string $key
	 * @return array
	 */
	private static function param_array( $request, $key ) {
		$value = self::param( $request, $key, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * @param mixed  $request
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	private static function param( $request, $key, $default ) {
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$value = $request->get_param( $key );
			return null === $value ? $default : $value;
		}
		if ( is_array( $request ) && array_key_exists( $key, $request ) ) {
			return $request[ $key ];
		}
		return $default;
	}
}
