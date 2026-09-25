<?php
namespace CanvaslyLite\Ops;

use CanvaslyLite\Design\CssPrint;
use CanvaslyLite\Rendering\FrontendRenderer;
use CanvaslyLite\Settings\AdminSettings;
use CanvaslyLite\Templates\TemplateEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coming-soon / maintenance document for visitors (Roadmap 7.3).
 *
 * Roles listed in settings still see the live site. Login, admin, REST, cron
 * and AJAX are never intercepted. Maintenance sends HTTP 503; coming soon
 * stays 200 so the landing page can be indexed.
 */
class Maintenance {
	const PREVIEW = 'lb_maintenance_preview';

	/** @var bool */
	private static $headers_sent = false;

	public static function init() {
		add_action( 'template_redirect', array( self::class, 'template_redirect' ), 0 );
		add_filter( 'template_include', array( self::class, 'template_include' ), 999 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 25 );
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar' ), 80 );
		add_action( 'canvasly-lite/tools/screen', array( self::class, 'tools_screen' ), 21 );
	}

	/**
	 * @return array{mode:string,template:int,exclude_roles:string[]}
	 */
	public static function settings() {
		$d = class_exists( AdminSettings::class )
			? AdminSettings::maintenance()
			: array(
				'mode'          => 'off',
				'template'      => 0,
				'exclude_roles' => array( 'administrator' ),
			);
		/**
		 * Filter maintenance / coming-soon settings.
		 *
		 * @param array $d
		 */
		$filtered = apply_filters( 'canvasly-lite/maintenance/settings', $d );
		if ( ! is_array( $filtered ) ) {
			$filtered = $d;
		}
		$mode = sanitize_key( (string) ( $filtered['mode'] ?? 'off' ) );
		if ( ! in_array( $mode, array( 'off', 'coming_soon', 'maintenance' ), true ) ) {
			$mode = 'off';
		}
		$roles = isset( $filtered['exclude_roles'] ) && is_array( $filtered['exclude_roles'] )
			? $filtered['exclude_roles']
			: array();
		return array(
			'mode'          => $mode,
			'template'      => absint( $filtered['template'] ?? 0 ),
			'exclude_roles' => array_values( array_filter( array_map( 'sanitize_key', $roles ) ) ),
		);
	}

	/**
	 * @return string off|coming_soon|maintenance
	 */
	public static function mode() {
		return self::settings()['mode'];
	}

	/**
	 * Whether visitors currently see the maintenance / coming-soon document.
	 *
	 * @param array|null $context  Synthetic request context (tests).
	 * @param array|null $settings Synthetic settings (tests).
	 * @return bool
	 */
	public static function should_apply( $context = null, $settings = null ) {
		$s = is_array( $settings ) ? $settings : self::settings();
		$mode = sanitize_key( (string) ( $s['mode'] ?? 'off' ) );
		if ( $mode === 'off' || $mode === '' ) {
			return false;
		}
		$ctx = is_array( $context ) ? $context : self::context();

		$preview = ! empty( $ctx['preview'] );
		if ( $preview && ! empty( $ctx['can_manage'] ) ) {
			$apply = true;
		} else {
			if ( ! empty( $ctx['is_admin'] ) || ! empty( $ctx['is_login'] ) || ! empty( $ctx['is_cli'] )
				|| ! empty( $ctx['doing_ajax'] ) || ! empty( $ctx['doing_cron'] ) || ! empty( $ctx['is_rest'] )
				|| ! empty( $ctx['is_preview'] ) || ! empty( $ctx['is_customize'] ) ) {
				return false;
			}
			if ( $mode === 'coming_soon' && ! empty( $ctx['is_feed'] ) ) {
				return false;
			}
			$roles   = isset( $ctx['user_roles'] ) && is_array( $ctx['user_roles'] ) ? $ctx['user_roles'] : array();
			$exclude = isset( $s['exclude_roles'] ) && is_array( $s['exclude_roles'] ) ? $s['exclude_roles'] : array();
			if ( $roles && array_intersect( $roles, $exclude ) ) {
				return false;
			}
			$apply = true;
		}

		/**
		 * Filter whether maintenance / coming-soon output should run for this request.
		 *
		 * @param bool  $apply
		 * @param array $s
		 * @param array $ctx
		 */
		return (bool) apply_filters( 'canvasly-lite/maintenance/apply', $apply, $s, $ctx );
	}

	/**
	 * @param string $mode
	 * @return int
	 */
	public static function status_code( $mode = '' ) {
		$mode = $mode !== '' ? sanitize_key( $mode ) : self::mode();
		return $mode === 'maintenance' ? 503 : 200;
	}

	public static function template_redirect() {
		if ( ! self::should_apply() ) {
			return;
		}
		$s = self::settings();
		if ( $s['mode'] === 'maintenance' && ! empty( self::context()['is_feed'] ) ) {
			self::headers();
			exit;
		}
		self::headers();
	}

	/**
	 * @param string $template
	 * @return string
	 */
	public static function template_include( $template ) {
		if ( ! self::should_apply() ) {
			return $template;
		}
		$file = CANVASLY_LITE_PATH . 'includes/templates/maintenance.php';
		return is_readable( $file ) ? $file : $template;
	}

	public static function headers() {
		if ( self::$headers_sent ) {
			return;
		}
		self::$headers_sent = true;
		$mode = self::mode();
		$code = self::status_code( $mode );
		if ( function_exists( 'status_header' ) ) {
			status_header( $code );
		} elseif ( ! headers_sent() ) {
			header( 'HTTP/1.1 ' . (int) $code );
		}
		if ( $mode === 'maintenance' && ! headers_sent() ) {
			header( 'Retry-After: 3600' );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}
		if ( ! self::should_apply() ) {
			return $classes;
		}
		$mode      = self::mode();
		$classes[] = 'lb-maintenance-page';
		$classes[] = 'lb-mode-' . $mode;
		if ( $mode === 'coming_soon' ) {
			$classes[] = 'lb-coming-soon';
		} else {
			$classes[] = 'lb-maintenance';
		}
		return array_values( array_unique( $classes ) );
	}

	/**
	 * @param array $robots
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( ! is_array( $robots ) ) {
			$robots = array();
		}
		if ( self::should_apply() && self::mode() === 'maintenance' ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	public static function enqueue() {
		if ( ! self::should_apply() ) {
			return;
		}
		$id = absint( self::settings()['template'] );
		if ( $id && class_exists( TemplateEmbed::class ) ) {
			$doc = TemplateEmbed::document( $id );
			if ( $doc && class_exists( FrontendRenderer::class ) ) {
				FrontendRenderer::enqueue_document_assets( $doc, $id );
			}
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'canvasly-lite-frontend' );
		}
		if ( $id && class_exists( TemplateEmbed::class ) && method_exists( TemplateEmbed::class, 'document_css' ) && function_exists( 'wp_add_inline_style' ) ) {
			$css = TemplateEmbed::document_css( $id );
			if ( is_string( $css ) && $css !== '' ) {
				wp_add_inline_style( 'canvasly-lite-frontend', function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $css ) : $css );
			}
		}
		if ( class_exists( CssPrint::class ) ) {
			CssPrint::enqueue_global();
		}
	}

	public static function print_content() {
		echo self::content_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @return string
	 */
	public static function content_html() {
		$s  = self::settings();
		$id = absint( $s['template'] );
		if ( $id ) {
			$html = '';
			if ( class_exists( TemplateEmbed::class ) ) {
				$doc = TemplateEmbed::document( $id );
				if ( $doc && ! empty( $doc['root'] ) && is_array( $doc['root'] ) && class_exists( FrontendRenderer::class ) && method_exists( FrontendRenderer::class, 'render_nodes' ) ) {
					if ( class_exists( TemplateEmbed::class ) && method_exists( TemplateEmbed::class, 'begin' ) && ! TemplateEmbed::begin( $id ) ) {
						$html = '';
					} else {
						try {
							$html = FrontendRenderer::render_nodes( $doc['root'], $id, array(), 'maint' );
						} finally {
							if ( method_exists( TemplateEmbed::class, 'end' ) ) {
								TemplateEmbed::end( $id );
							}
						}
					}
				}
			}
			/**
			 * Filter the HTML of the maintenance / coming-soon document.
			 *
			 * @param string $html
			 * @param int    $id
			 * @param array  $s
			 */
			$html = (string) apply_filters( 'canvasly-lite/maintenance/html', is_string( $html ) ? $html : '', $id, $s );
			if ( trim( $html ) !== '' ) {
				return $html;
			}
		}
		return self::fallback_html( $s['mode'] );
	}

	/**
	 * @param string $mode
	 * @return string
	 */
	public static function fallback_html( $mode ) {
		$coming = $mode === 'coming_soon';
		$title  = $coming
			? __( 'Coming Soon', 'canvasly-lite' )
			: __( 'Site under maintenance', 'canvasly-lite' );
		$msg    = $coming
			? __( 'This site is not public yet. Please check back later.', 'canvasly-lite' )
			: __( 'We are performing scheduled maintenance. Please try again shortly.', 'canvasly-lite' );
		return '<div class="lb-maintenance-fallback" style="max-width:40rem;margin:15vh auto;padding:2rem;text-align:center;font-family:system-ui,sans-serif">'
			. '<h1>' . esc_html( $title ) . '</h1>'
			. '<p>' . esc_html( $msg ) . '</p>'
			. '</div>';
	}

	/**
	 * @return string
	 */
	public static function preview_url() {
		$home = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg( self::PREVIEW, '1', $home );
		}
		$sep = strpos( $home, '?' ) !== false ? '&' : '?';
		return $home . $sep . self::PREVIEW . '=1';
	}

	public static function tools_screen() {
		if ( class_exists( AdminSettings::class ) && ! AdminSettings::can_manage() ) {
			return;
		}
		$s    = self::settings();
		$mode = $s['mode'];
		echo '<hr><h2>' . esc_html__( 'Maintenance mode', 'canvasly-lite' ) . '</h2>';
		if ( $mode === 'off' ) {
			echo '<p>' . esc_html__( 'Visitors currently see the live site.', 'canvasly-lite' ) . '</p>';
		} else {
			$label = $mode === 'coming_soon' ? __( 'Coming soon', 'canvasly-lite' ) : __( 'Maintenance', 'canvasly-lite' );
			echo '<p><span class="lb-ops-status lb-ops-on">' . esc_html( $label ) . '</span></p>';
			echo '<p><a class="button" href="' . esc_url( self::preview_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Preview', 'canvasly-lite' ) . '</a></p>';
		}
		if ( class_exists( AdminSettings::class ) ) {
			echo '<p><a class="button" href="' . esc_url( AdminSettings::url( 'tools' ) ) . '">' . esc_html__( 'Configure', 'canvasly-lite' ) . '</a></p>';
		}
	}

	/**
	 * @param \WP_Admin_Bar $bar
	 */
	public static function admin_bar( $bar ) {
		if ( ! is_object( $bar ) || ! method_exists( $bar, 'add_node' ) ) {
			return;
		}
		if ( ! class_exists( AdminSettings::class ) || ! AdminSettings::can_manage() ) {
			return;
		}
		if ( self::mode() === 'off' ) {
			return;
		}
		$mode  = self::mode();
		$title = $mode === 'coming_soon'
			? __( 'Coming Soon is on', 'canvasly-lite' )
			: __( 'Maintenance is on', 'canvasly-lite' );
		$bar->add_node(
			array(
				'id'    => 'canvasly-lite-maintenance',
				'title' => $title,
				'href'  => class_exists( AdminSettings::class ) ? AdminSettings::url( 'tools' ) : admin_url( 'admin.php?page=canvasly-lite-tools' ),
				'meta'  => array( 'class' => 'lb-ab-maintenance' ),
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function context() {
		$roles = array();
		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			if ( is_object( $user ) && ! empty( $user->roles ) && is_array( $user->roles ) ) {
				$roles = array_map( 'sanitize_key', $user->roles );
			}
		}
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
		$login  = ( function_exists( 'is_login' ) && is_login() )
			|| ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' )
			|| strpos( $script, 'wp-login.php' ) !== false;
		$rest   = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| strpos( $uri, '/wp-json/' ) !== false
			|| ( isset( $_GET['rest_route'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$preview_q = ! empty( $_GET[ self::PREVIEW ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array(
			'is_admin'     => function_exists( 'is_admin' ) && is_admin(),
			'is_login'     => $login,
			'is_cli'       => defined( 'WP_CLI' ) && WP_CLI,
			'doing_ajax'   => function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX ),
			'doing_cron'   => function_exists( 'wp_doing_cron' ) ? wp_doing_cron() : ( defined( 'DOING_CRON' ) && DOING_CRON ),
			'is_rest'      => $rest,
			'is_feed'      => function_exists( 'is_feed' ) && is_feed(),
			'is_preview'   => function_exists( 'is_preview' ) && is_preview(),
			'is_customize' => function_exists( 'is_customize_preview' ) && is_customize_preview(),
			'user_roles'   => $roles,
			'preview'      => $preview_q,
			'can_manage'   => class_exists( AdminSettings::class ) ? AdminSettings::can_manage() : ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ),
		);
	}

	public static function reset_for_tests() {
		self::$headers_sent = false;
	}
}
