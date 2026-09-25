<?php
namespace CanvaslyLite\Embed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cached, allow-listed oEmbed via wp_oembed_get (Roadmap 6.5).
 *
 * Known provider-specific Video/Audio/SoundCloud paths stay in those units.
 * This class is the fallback for unrecognised URLs and the generic Embed widget.
 */
class OEmbed {
	const TRANSIENT_PREFIX = 'lb_oe_';
	const TTL_DEFAULT      = 604800;
	const TTL_MISS         = 3600;

	/** @var bool */
	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
	}

	/**
	 * Hostnames allowed to be fetched. Subdomains match (vimeo.com → player.vimeo.com).
	 *
	 * @return string[]
	 */
	public static function providers() {
		$hosts = array(
			'youtube.com',
			'youtu.be',
			'youtube-nocookie.com',
			'vimeo.com',
			'dailymotion.com',
			'dai.ly',
			'videopress.com',
			'video.wordpress.com',
			'wordpress.tv',
			'soundcloud.com',
			'spotify.com',
			'spotify.link',
			'twitter.com',
			'x.com',
			'tiktok.com',
			'flickr.com',
			'imgur.com', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- oEmbed provider hostname allow-list, not a remote plugin asset.
			'mixcloud.com',
			'ted.com',
			'kickstarter.com',
			'reddit.com',
			'issuu.com',
			'scribd.com',
			'slideshare.net',
			'codepen.io',
			'animoto.com',
			'cloudup.com',
			'tumblr.com',
			'reverbnation.com',
			'crowdsignal.com',
			'anchor.fm',
			'podcasts.apple.com',
			'megaphone.fm',
			'amazon.com',
			'amazon.co.uk',
			'amazon.de',
			'wolframalpha.com',
		);
		/**
		 * Filter oEmbed provider hostnames (no scheme, no www).
		 *
		 * @param string[] $hosts
		 */
		$filtered = apply_filters( 'canvasly-lite/oembed/providers', $hosts );
		return is_array( $filtered ) ? array_values( array_filter( array_map( 'strval', $filtered ) ) ) : $hosts;
	}

	/**
	 * True when `$value` is a single http(s) URL with no markup.
	 *
	 * @param mixed $value
	 */
	public static function is_url( $value ) {
		$v = trim( (string) $value );
		if ( $v === '' || strpos( $v, '<' ) !== false || preg_match( '/\s/', $v ) ) {
			return false;
		}
		if ( ! preg_match( '#^https?://#i', $v ) ) {
			return false;
		}
		if ( function_exists( 'filter_var' ) && ! filter_var( $v, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $url
	 * @return string Host without leading www.
	 */
	public static function host( $url ) {
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		return (string) preg_replace( '/^www\./', '', $host );
	}

	/**
	 * @param string $url
	 */
	public static function allowed( $url ) {
		$host = self::host( $url );
		$ok   = false;
		if ( $host !== '' ) {
			foreach ( self::providers() as $p ) {
				$p = strtolower( (string) preg_replace( '/^www\./', '', trim( (string) $p ) ) );
				if ( $p === '' ) {
					continue;
				}
				if ( $host === $p || substr( $host, -strlen( '.' . $p ) ) === '.' . $p ) {
					$ok = true;
					break;
				}
			}
		}
		/**
		 * Filter whether a URL may be oEmbedded.
		 *
		 * @param bool   $ok
		 * @param string $url
		 * @param string $host
		 */
		return (bool) apply_filters( 'canvasly-lite/oembed/allowed', $ok, (string) $url, $host );
	}

	/**
	 * Sanitized oEmbed HTML, or empty string.
	 *
	 * @param string $url
	 * @param array  $args Passed to wp_oembed_get (width, height, discover).
	 * @return string
	 */
	public static function html( $url, $args = array() ) {
		$url = self::normalize_url( $url );
		if ( $url === '' || ! self::allowed( $url ) ) {
			return '';
		}
		$args = self::normalize_args( $args );
		$key  = self::cache_key( $url, $args );
		$hit  = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
		if ( is_array( $hit ) ) {
			return ! empty( $hit['ok'] ) ? (string) ( $hit['html'] ?? '' ) : '';
		}
		$raw = '';
		if ( function_exists( 'wp_oembed_get' ) ) {
			$got = wp_oembed_get( $url, $args );
			$raw = is_string( $got ) ? $got : '';
		}
		$html = $raw !== '' ? self::kses( $raw ) : '';
		if ( function_exists( 'set_transient' ) ) {
			set_transient(
				$key,
				array(
					'ok'   => $html !== '',
					'html' => $html,
				),
				$html !== '' ? self::ttl() : self::miss_ttl()
			);
		}
		return $html;
	}

	/**
	 * Wrapper used by Embed / HTML. Video keeps its own frame.
	 *
	 * @param string $html
	 * @param string $class
	 * @param array  $extra ratio, max_width
	 */
	public static function wrap( $html, $class = 'lb-embed', $extra = array() ) {
		$html = is_string( $html ) ? $html : '';
		if ( $html === '' ) {
			return '';
		}
		$extra = is_array( $extra ) ? $extra : array();
		$ratio = trim( (string) ( $extra['ratio'] ?? '' ) );
		$max   = trim( (string) ( $extra['max_width'] ?? '' ) );
		$cls   = trim( (string) $class . ( $ratio !== '' ? ' lb-embed-has-ratio' : '' ) );
		$style = array();
		if ( $ratio !== '' ) {
			$style[] = '--lb-embed-ratio:' . $ratio;
		}
		if ( $max !== '' ) {
			$style[] = 'max-width:' . $max;
		}
		$attr = $style ? ' style="' . esc_attr( implode( ';', $style ) ) . '"' : '';
		return '<div class="' . esc_attr( $cls ) . '"' . $attr . '>' . $html . '</div>';
	}

	/**
	 * @param string $url
	 * @param array  $args
	 */
	public static function cache_key( $url, $args = array() ) {
		$json    = function_exists( 'wp_json_encode' ) ? wp_json_encode( self::normalize_args( $args ) ) : json_encode( self::normalize_args( $args ) );
		$payload = strtolower( self::normalize_url( $url ) ) . '|' . ( is_string( $json ) ? $json : '' );
		return self::TRANSIENT_PREFIX . md5( $payload );
	}

	public static function ttl() {
		$default = defined( 'WEEK_IN_SECONDS' ) ? WEEK_IN_SECONDS : self::TTL_DEFAULT;
		$ttl     = (int) apply_filters( 'canvasly-lite/oembed/ttl', $default );
		return $ttl > 0 ? $ttl : $default;
	}

	public static function miss_ttl() {
		$default = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : self::TTL_MISS;
		$ttl     = (int) apply_filters( 'canvasly-lite/oembed/miss_ttl', $default );
		return $ttl > 0 ? $ttl : $default;
	}

	/**
	 * @param string $html
	 */
	public static function kses( $html ) {
		$html = (string) $html;
		if ( $html === '' ) {
			return '';
		}
		$allowed = array(
			'iframe'     => array(
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'frameborder'     => true,
				'allow'           => true,
				'allowfullscreen' => true,
				'loading'         => true,
				'title'           => true,
				'style'           => true,
				'class'           => true,
				'referrerpolicy'  => true,
				'sandbox'         => true,
				'scrolling'       => true,
				'name'            => true,
			),
			'blockquote' => array( 'class' => true, 'style' => true, 'cite' => true, 'lang' => true ),
			'a'          => array( 'href' => true, 'class' => true, 'target' => true, 'rel' => true, 'title' => true ),
			'p'          => array( 'class' => true, 'style' => true, 'lang' => true ),
			'div'        => array( 'class' => true, 'style' => true, 'id' => true ),
			'span'       => array( 'class' => true, 'style' => true ),
			'img'        => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true, 'loading' => true ),
			'audio'      => array( 'src' => true, 'controls' => true, 'preload' => true, 'class' => true ),
			'source'     => array( 'src' => true, 'type' => true ),
			'video'      => array( 'src' => true, 'controls' => true, 'preload' => true, 'poster' => true, 'width' => true, 'height' => true, 'class' => true ),
			'cite'       => array( 'class' => true ),
			'strong'     => array(),
			'em'         => array(),
			'br'         => array(),
		);
		/**
		 * Filter allowed HTML for oEmbed markup.
		 *
		 * @param array  $allowed
		 * @param string $html
		 */
		$allowed = apply_filters( 'canvasly-lite/oembed/kses', $allowed, $html );
		if ( function_exists( 'wp_kses' ) ) {
			return wp_kses( $html, is_array( $allowed ) ? $allowed : array() );
		}
		return $html;
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		$ns = $namespace !== '' ? $namespace : 'canvasly-lite/v1';
		register_rest_route(
			$ns,
			'/oembed',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'rest_get' ),
				'permission_callback' => array( self::class, 'can_preview' ),
				'args'                => array(
					'url' => array(
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $req
	 */
	public static function rest_get( $req ) {
		$url = '';
		if ( is_object( $req ) && method_exists( $req, 'get_param' ) ) {
			$url = (string) $req->get_param( 'url' );
		}
		$html = self::html( $url );
		$body = array(
			'url'     => $url,
			'allowed' => self::allowed( $url ),
			'html'    => $html,
		);
		return function_exists( 'rest_ensure_response' ) ? rest_ensure_response( $body ) : $body;
	}

	public static function can_preview() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * @param mixed $url
	 */
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' || ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		$clean = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
		return is_string( $clean ) ? $clean : '';
	}

	/**
	 * @param mixed $args
	 * @return array
	 */
	public static function normalize_args( $args ) {
		$args = is_array( $args ) ? $args : array();
		$out  = array();
		if ( isset( $args['width'] ) ) {
			$out['width'] = absint( $args['width'] );
		}
		if ( isset( $args['height'] ) ) {
			$out['height'] = absint( $args['height'] );
		}
		$out['discover'] = array_key_exists( 'discover', $args ) ? ! empty( $args['discover'] ) : true;
		ksort( $out );
		return $out;
	}
}
