<?php
/**
 * Site Menu.
 *
 * Renders a WordPress menu with wp_nav_menu() and Site_Nav_Walker so the
 * markup stays semantic: <nav>, <ul>, <li>, <a>. Two layouts share one
 * stylesheet: horizontal (desktop headers) and vertical (sidebars). Below
 * the breakpoint the list collapses and a hamburger button opens it.
 *
 * Canvasly unit
 * -------------
 * Registered automatically from Plugin::register_units() as type `site_nav`
 * ("Site Menu" in the unit inserter). Drop it in a header container or a
 * sidebar column and pick a menu from Appearance → Menus.
 *
 * Theme (functions.php + header.php / sidebar.php)
 * ------------------------------------------------
 * Assets register on `wp_enqueue_scripts` when the plugin boots. Call the
 * template tag where the menu should print:
 *
 *   canvasly_lite_nav_menu( array(
 *       'theme_location' => 'primary',
 *       'layout'         => 'horizontal', // or 'vertical'
 *       'breakpoint'     => 782,
 *   ) );
 *
 * Shortcode (page content, a Custom HTML block, or a text widget)
 * ---------------------------------------------------------------
 *   [canvasly_nav menu="primary" layout="vertical" breakpoint="782"]
 *   [canvasly_nav menu="12" layout="horizontal"]
 *
 * `menu` accepts a theme location slug, a classic menu id, or `menu:12`.
 *
 * @package CanvaslyLite
 */

namespace CanvaslyLite\Units {

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteNav extends Unit {
	const STYLE_HANDLE  = 'canvasly-lite-site-nav';
	const SCRIPT_HANDLE = 'canvasly-lite-site-nav';
	const SHORTCODE     = 'canvasly_nav';
	const DEFAULT_BP    = 782;

	/**
	 * @var array<int,array<string,mixed>>|null
	 */
	private static $test_items = null;

	/**
	 * @var int
	 */
	private static $seq = 0;

	/**
	 * @param array<int,array<string,mixed>>|null $items
	 * @return void
	 */
	public static function set_items_for_tests( $items ) {
		self::$test_items = is_array( $items ) ? $items : null;
	}

	/**
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$test_items = null;
		self::$seq        = 0;
	}

	public function type() {
		return 'site_nav';
	}

	public function title() {
		return __( 'Site Menu', 'canvasly-lite' );
	}

	public function icon() {
		return '☰';
	}

	public function category() {
		return 'content';
	}

	public function keywords() {
		return array( 'menu', 'nav', 'navigation', 'header', 'sidebar', 'hamburger' );
	}

	public function defaults() {
		return array(
			'menu'         => '',
			'display_name' => '',
			'layout'       => 'horizontal',
			'breakpoint'   => self::DEFAULT_BP,
			'label'        => '',
			'color'       => '',
			'hover_color' => '',
			'background'  => '',
		);
	}

	public function controls() {
		$section = __( 'Site Menu', 'canvasly-lite' );
		return array(
			'menu'         => $this->ctrl(
				'select',
				__( 'Menu', 'canvasly-lite' ),
				'content',
				$section,
				array(
					'description' => __( 'Classic menus from Appearance → Menus, or a theme location slug.', 'canvasly-lite' ),
				)
			),
			'display_name' => $this->ctrl(
				'text',
				__( 'Display name', 'canvasly-lite' ),
				'content',
				$section,
				array(
					'placeholder' => __( 'Menu name', 'canvasly-lite' ),
					'description' => __( 'Text shown on the button. Opening it still lists the selected menu. Leave blank to use the menu name.', 'canvasly-lite' ),
					'condition'   => array( 'menu!' => '' ),
				)
			),
			'layout'       => $this->ctrl(
				'select',
				__( 'Layout', 'canvasly-lite' ),
				'content',
				$section,
				array(
					'options' => array(
						'horizontal' => __( 'Horizontal', 'canvasly-lite' ),
						'vertical'   => __( 'Vertical', 'canvasly-lite' ),
						'dropdown'   => __( 'Dropdown', 'canvasly-lite' ),
					),
					'default' => 'horizontal',
				)
			),
			'breakpoint'  => $this->ctrl(
				'slider',
				__( 'Mobile breakpoint', 'canvasly-lite' ),
				'content',
				$section,
				array(
					'units'       => array( 'px' ),
					'range'       => array(
						'min'  => 320,
						'max'  => 1400,
						'step' => 1,
					),
					'default'     => self::DEFAULT_BP,
					'description' => __( 'The menu collapses into a hamburger button below this width.', 'canvasly-lite' ),
				)
			),
			'label'       => $this->ctrl(
				'text',
				__( 'ARIA label', 'canvasly-lite' ),
				'content',
				$section,
				array(
					'placeholder' => __( 'Primary', 'canvasly-lite' ),
				)
			),
			'color'       => $this->ctrl(
				'color',
				__( 'Text', 'canvasly-lite' ),
				'style',
				$section,
				array(
					'selectors' => array(
						'{{WRAPPER}}' => '--lb-nav-color: {{VALUE}};',
					),
				)
			),
			'hover_color' => $this->ctrl(
				'color',
				__( 'Hover', 'canvasly-lite' ),
				'style',
				$section,
				array(
					'selectors' => array(
						'{{WRAPPER}}' => '--lb-nav-hover: {{VALUE}};',
					),
				)
			),
			'background'  => $this->ctrl(
				'color',
				__( 'Background', 'canvasly-lite' ),
				'style',
				$section,
				array(
					'description' => __( 'Colors the menu button only.', 'canvasly-lite' ),
					'selectors'   => array(
						'{{WRAPPER}}' => '--lb-nav-bg: {{VALUE}};',
					),
				)
			),
		);
	}

	/**
	 * @param array $settings
	 * @return string[]
	 */
	public function styles( $settings = array() ) {
		unset( $settings );
		return array( self::STYLE_HANDLE );
	}

	/**
	 * @param array $settings
	 * @return string[]
	 */
	public function scripts( $settings = array() ) {
		unset( $settings );
		return array( self::SCRIPT_HANDLE );
	}

	/**
	 * Shortcode, asset registration. Called from Plugin::register_units().
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_discovered' ), 20 );
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		if ( ! defined( 'CANVASLY_LITE_PATH' ) || ! defined( 'CANVASLY_LITE_URL' ) || ! function_exists( 'wp_register_style' ) ) {
			return;
		}
		$css = CANVASLY_LITE_PATH . 'assets/css/site-nav.css';
		$js  = CANVASLY_LITE_PATH . 'assets/js/site-nav.js';
		$ver = defined( 'CANVASLY_LITE_VERSION' ) ? (string) CANVASLY_LITE_VERSION : '1';
		wp_register_style( self::STYLE_HANDLE, CANVASLY_LITE_URL . 'assets/css/site-nav.css', array(), $ver . '-' . ( file_exists( $css ) ? filemtime( $css ) : $ver ) );
		if ( function_exists( 'wp_register_script' ) ) {
			wp_register_script( self::SCRIPT_HANDLE, CANVASLY_LITE_URL . 'assets/js/site-nav.js', array(), $ver . '-' . ( file_exists( $js ) ? filemtime( $js ) : $ver ), true );
		}
	}

	/**
	 * Head-time enqueue when the shortcode is already in the post content.
	 * The Canvasly unit enqueues through FrontendAssets via styles()/scripts().
	 *
	 * @return void
	 */
	public static function enqueue_discovered() {
		self::register_assets();
		if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! function_exists( 'get_post' ) || ! function_exists( 'has_shortcode' ) ) {
			return;
		}
		$post = get_post();
		if ( ! is_object( $post ) ) {
			return;
		}
		if ( has_shortcode( (string) ( $post->post_content ?? '' ), self::SHORTCODE ) ) {
			self::enqueue();
		}
	}

	/**
	 * @return void
	 */
	public static function enqueue() {
		self::register_assets();
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}
	}

	/**
	 * [canvasly_nav menu="primary" layout="horizontal" breakpoint="782" label="Primary" display_name="Tickets"]
	 *
	 * @param array<string,mixed>|string $atts
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'menu'         => '',
				'layout'       => 'horizontal',
				'breakpoint'   => self::DEFAULT_BP,
				'label'        => '',
				'display_name' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);
		self::enqueue();
		$ref = self::resolve_menu( $atts['menu'] );
		return self::render_menu(
			array(
				'menu'           => $ref['menu'],
				'theme_location' => $ref['theme_location'],
				'layout'         => $atts['layout'],
				'breakpoint'     => $atts['breakpoint'],
				'label'          => $atts['label'],
				'display_name'   => $atts['display_name'],
				'echo'           => false,
			)
		);
	}

	public function render( $s, $children = '' ) {
		unset( $children );
		$s   = is_array( $s ) ? $s : array();
		$ref = self::resolve_menu( $s['menu'] ?? '' );
		if ( '' === $ref['menu'] && '' === $ref['theme_location'] && ! is_array( self::$test_items ) ) {
			return '<nav class="' . esc_attr( $this->cls( $s ) . ' lb-site-nav' ) . '" aria-label="' . esc_attr( __( 'Menu', 'canvasly-lite' ) ) . '"><p class="lb-embed-placeholder">' . esc_html__( 'Choose a menu', 'canvasly-lite' ) . '</p></nav>';
		}
		return self::render_menu(
			array(
				'menu'           => $ref['menu'],
				'theme_location' => $ref['theme_location'],
				'layout'         => $s['layout'] ?? 'horizontal',
				'breakpoint'     => $s['breakpoint'] ?? self::DEFAULT_BP,
				'label'          => $s['label'] ?? '',
				'display_name'   => $s['display_name'] ?? '',
				'color'          => $s['color'] ?? '',
				'hover_color'    => $s['hover_color'] ?? '',
				'background'     => $s['background'] ?? '',
				'class'          => $this->cls( $s ),
				'echo'           => false,
			)
		);
	}

	/**
	 * Print or return the menu.
	 *
	 * Arguments:
	 * - menu (int|string)          Classic menu id. Empty when theme_location is set.
	 * - theme_location (string)    Registered theme location.
	 * - layout (string)            `horizontal`, `vertical`, or `dropdown`.
	 * - breakpoint (int)           Collapse width in pixels. Default 782.
	 * - label (string)             Accessible name for the <nav>.
	 * - display_name (string)      Button text. Blank uses the menu name.
	 * - echo (bool)                True prints the markup. Default true for the template tag.
	 *
	 * @param array<string,mixed> $args
	 * @return string
	 */
	public static function render_menu( $args = array() ) {
		$args = apply_filters( 'canvasly-lite/site_nav/args', is_array( $args ) ? $args : array() );
		$args = self::args( is_array( $args ) ? $args : array() );

		$list = '';
		if ( is_array( self::$test_items ) ) {
			$list = self::items_markup( self::$test_items, $args['menu_id'] );
		} elseif ( function_exists( 'wp_nav_menu' ) && ( '' !== (string) $args['menu'] || '' !== $args['theme_location'] ) ) {
			$menu_args = array(
				'echo'            => false,
				'container'       => false,
				'menu_class'      => 'lb-site-nav__list',
				'menu_id'         => $args['menu_id'],
				'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
				'fallback_cb'     => '__return_empty_string',
				'depth'           => 0,
			);
			if ( '' !== (string) $args['menu'] ) {
				$menu_args['menu'] = $args['menu'];
			}
			if ( '' !== $args['theme_location'] ) {
				$menu_args['theme_location'] = $args['theme_location'];
			}
			if ( class_exists( __NAMESPACE__ . '\\Site_Nav_Walker' ) ) {
				$menu_args['walker'] = new Site_Nav_Walker();
			}
			$html = wp_nav_menu( $menu_args );
			$list = is_string( $html ) ? $html : '';
		}

		$markup = self::wrap( $list, $args );
		if ( ! empty( $args['echo'] ) ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Assembled from escaped fragments in wrap() and the walker.
		}
		return $markup;
	}

	/**
	 * Template-tag entry. Echoes by default, matching wp_nav_menu().
	 *
	 * @param array<string,mixed> $args
	 * @return string
	 */
	public static function display( $args = array() ) {
		if ( ! isset( $args['echo'] ) ) {
			$args['echo'] = true;
		}
		self::enqueue();
		return self::render_menu( $args );
	}

	/**
	 * menu:12, a numeric id, location:primary, or a theme location slug.
	 *
	 * @param mixed $value
	 * @return array{menu:int|string,theme_location:string}
	 */
	public static function resolve_menu( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return array(
				'menu'           => '',
				'theme_location' => '',
			);
		}
		if ( preg_match( '/^(menu|classic):(\d+)$/', $value, $m ) ) {
			return array(
				'menu'           => (int) $m[2],
				'theme_location' => '',
			);
		}
		if ( ctype_digit( $value ) ) {
			return array(
				'menu'           => (int) $value,
				'theme_location' => '',
			);
		}
		if ( preg_match( '/^location:(.+)$/', $value, $m ) ) {
			$value = $m[1];
		}
		return array(
			'menu'           => '',
			'theme_location' => sanitize_key( $value ),
		);
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array{menu:int|string,theme_location:string,layout:string,breakpoint:int,label:string,display_name:string,color:string,hover_color:string,background:string,class:string,menu_id:string,echo:bool}
	 */
	private static function args( $args ) {
		$args = is_array( $args ) ? $args : array();
		$layout = 'horizontal';
		if ( isset( $args['layout'] ) && in_array( $args['layout'], array( 'vertical', 'dropdown' ), true ) ) {
			$layout = $args['layout'];
		}
		$bp     = self::breakpoint_of( $args['breakpoint'] ?? self::DEFAULT_BP );
		++self::$seq;
		$id = 'lb-site-nav-' . self::$seq;
		if ( function_exists( 'wp_unique_id' ) && empty( $args['menu_id'] ) ) {
			$id = wp_unique_id( 'lb-site-nav-' );
		} elseif ( ! empty( $args['menu_id'] ) ) {
			$id = sanitize_html_class( (string) $args['menu_id'] );
		}
		return array(
			'menu'           => isset( $args['menu'] ) ? $args['menu'] : '',
			'theme_location' => sanitize_key( (string) ( $args['theme_location'] ?? '' ) ),
			'layout'         => $layout,
			'breakpoint'     => $bp,
			'label'          => sanitize_text_field( (string) ( $args['label'] ?? '' ) ),
			'display_name'   => sanitize_text_field( (string) ( $args['display_name'] ?? '' ) ),
			'color'          => self::color_of( $args['color'] ?? '' ),
			'hover_color'    => self::color_of( $args['hover_color'] ?? '' ),
			'background'     => self::color_of( $args['background'] ?? '' ),
			'class'          => self::extra_class( $args['class'] ?? '' ),
			'menu_id'        => $id . '-list',
			'nav_id'         => $id,
			'echo'           => ! empty( $args['echo'] ),
		);
	}

	/**
	 * @param mixed $value
	 * @return int
	 */
	public static function breakpoint_of( $value ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['size'] ) ) {
				$value = $value['size'];
			} elseif ( isset( $value['desktop'] ) ) {
				$value = $value['desktop'];
				if ( is_array( $value ) ) {
					$value = $value['size'] ?? self::DEFAULT_BP;
				}
			} else {
				$value = self::DEFAULT_BP;
			}
		}
		$n = absint( $value );
		if ( $n < 320 || $n > 1600 ) {
			return self::DEFAULT_BP;
		}
		return $n;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function color_of( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( function_exists( 'sanitize_hex_color' ) ) {
			$hex = sanitize_hex_color( $value );
			return is_string( $hex ) ? $hex : '';
		}
		return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value ) ? $value : '';
	}

	/**
	 * @param string $class
	 * @return string
	 */
	private static function extra_class( $class ) {
		$out = array();
		foreach ( preg_split( '/\s+/', trim( (string) $class ) ) as $part ) {
			$safe = sanitize_html_class( $part );
			if ( '' !== $safe ) {
				$out[] = $safe;
			}
		}
		return implode( ' ', $out );
	}

	/**
	 * Name of the selected classic menu, when WordPress can resolve it.
	 *
	 * @param array<string,mixed> $args Normalized args.
	 * @return string
	 */
	private static function menu_title( $args ) {
		$menu = null;
		if ( ! empty( $args['menu'] ) && function_exists( 'wp_get_nav_menu_object' ) ) {
			$found = wp_get_nav_menu_object( $args['menu'] );
			if ( is_object( $found ) && ! is_wp_error( $found ) ) {
				$menu = $found;
			}
		}
		if ( ! $menu && ! empty( $args['theme_location'] ) && function_exists( 'get_nav_menu_locations' ) && function_exists( 'wp_get_nav_menu_object' ) ) {
			$locations = get_nav_menu_locations();
			$id        = isset( $locations[ $args['theme_location'] ] ) ? (int) $locations[ $args['theme_location'] ] : 0;
			if ( $id ) {
				$found = wp_get_nav_menu_object( $id );
				if ( is_object( $found ) && ! is_wp_error( $found ) ) {
					$menu = $found;
				}
			}
		}
		if ( ! $menu || empty( $menu->name ) ) {
			return '';
		}
		return sanitize_text_field( (string) $menu->name );
	}

	/**
	 * @param string              $list Markup from wp_nav_menu() or items_markup().
	 * @param array<string,mixed> $args Normalized args.
	 * @return string
	 */
	private static function wrap( $list, $args ) {
		$layout = 'horizontal';
		if ( in_array( $args['layout'] ?? '', array( 'vertical', 'dropdown' ), true ) ) {
			$layout = (string) $args['layout'];
		}
		$bp     = (int) ( $args['breakpoint'] ?? self::DEFAULT_BP );
		$nav_id = (string) ( $args['nav_id'] ?? 'lb-site-nav' );
		$list_id = (string) ( $args['menu_id'] ?? $nav_id . '-list' );
		$classes = array(
			'lb-site-nav',
			'lb-site-nav--' . $layout,
		);
		if ( self::DEFAULT_BP !== $bp ) {
			$classes[] = 'lb-site-nav--custom-bp';
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = (string) $args['class'];
		}
		$label = (string) ( $args['label'] ?? '' );
		if ( '' === $label ) {
			$label = __( 'Menu', 'canvasly-lite' );
		}
		$style = self::inline_vars( $args );
		$html  = '<nav id="' . esc_attr( $nav_id ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '" data-breakpoint="' . esc_attr( (string) $bp ) . '"' . $style . ' aria-label="' . esc_attr( $label ) . '">';
		if ( 'dropdown' === $layout ) {
			$html .= '<div class="lb-site-nav__drop">';
		}
		$html .= '<button type="button" class="lb-site-nav__toggle" aria-expanded="false" aria-controls="' . esc_attr( $list_id ) . '">';
		$html .= '<span class="lb-site-nav__burger" aria-hidden="true"></span>';
		$toggle = (string) ( $args['display_name'] ?? '' );
		if ( '' === $toggle ) {
			$toggle = self::menu_title( $args );
		}
		if ( '' === $toggle ) {
			$toggle = __( 'Menu', 'canvasly-lite' );
		}
		$html .= '<span class="lb-site-nav__toggle-text">' . esc_html( $toggle ) . '</span>';
		$html .= '<span class="lb-site-nav__caret" aria-hidden="true">▾</span>';
		$html .= '</button>';
		if ( trim( $list ) === '' ) {
			$html .= '<p class="lb-embed-placeholder">' . esc_html__( 'Choose a menu', 'canvasly-lite' ) . '</p>';
		} else {
			$html .= $list;
		}
		if ( 'dropdown' === $layout ) {
			$html .= '</div>';
		}
		$html .= '</nav>';
		return $html;
	}

	/**
	 * @param array<string,mixed> $args
	 * @return string
	 */
	private static function inline_vars( $args ) {
		$map = array(
			'color'       => '--lb-nav-color',
			'hover_color' => '--lb-nav-hover',
			'background'  => '--lb-nav-bg',
		);
		$bits = array();
		foreach ( $map as $key => $var ) {
			if ( ! empty( $args[ $key ] ) ) {
				$bits[] = $var . ':' . $args[ $key ];
			}
		}
		if ( ! $bits ) {
			return '';
		}
		return ' style="' . esc_attr( implode( ';', $bits ) ) . '"';
	}

	/**
	 * List markup for the test catalog. Live requests use wp_nav_menu() and the walker.
	 *
	 * @param array<int,array<string,mixed>> $items
	 * @param string                         $menu_id
	 * @return string
	 */
	public static function items_markup( $items, $menu_id = '' ) {
		$by = array();
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$parent             = (int) ( $item['parent'] ?? 0 );
			$by[ $parent ][]    = $item;
		}
		return self::branch( $by, 0, true, $menu_id );
	}

	/**
	 * @param array<int,array<int,array<string,mixed>>> $by
	 * @param int                                       $parent
	 * @param bool                                      $root
	 * @param string                                    $menu_id
	 * @return string
	 */
	private static function branch( $by, $parent, $root, $menu_id ) {
		if ( empty( $by[ $parent ] ) ) {
			return '';
		}
		$id_attr = ( $root && '' !== $menu_id ) ? ' id="' . esc_attr( $menu_id ) . '"' : '';
		$class   = $root ? 'lb-site-nav__list' : 'lb-site-nav__sub';
		$html    = '<ul' . $id_attr . ' class="' . esc_attr( $class ) . '">';
		foreach ( $by[ $parent ] as $item ) {
			$id   = (int) ( $item['id'] ?? 0 );
			$kids = ! empty( $by[ $id ] );
			$html .= self::item_open( $item, $kids );
			$html .= self::branch( $by, $id, false, '' );
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Opening `<li><a>` shared by the walker and the test renderer.
	 *
	 * @param array<string,mixed>|object $item
	 * @param bool                       $has_children
	 * @return string
	 */
	public static function item_open( $item, $has_children ) {
		$fields = self::item_fields( $item, $has_children );
		$classes = array( 'lb-site-nav__item' );
		if ( $fields['children'] ) {
			$classes[] = 'lb-site-nav__item--has-children';
		}
		if ( $fields['current'] ) {
			$classes[] = 'lb-site-nav__item--current';
		}
		$href = Unit::link_href( $fields['url'] );
		if ( '' === $href ) {
			$href = '#';
		}
		$html  = '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= '<a href="' . $href . '"';
		if ( $fields['current'] ) {
			$html .= ' aria-current="page"';
		}
		$rel = $fields['rel'];
		if ( '_blank' === $fields['target'] ) {
			$html .= ' target="_blank"';
			if ( false === stripos( $rel, 'noopener' ) ) {
				$rel = trim( $rel . ' noopener noreferrer' );
			}
		} elseif ( '' !== $fields['target'] ) {
			$html .= ' target="' . esc_attr( $fields['target'] ) . '"';
		}
		if ( '' !== $rel ) {
			$html .= ' rel="' . esc_attr( $rel ) . '"';
		}
		$title = apply_filters( 'the_title', $fields['title'], $fields['id'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core title filter for menu items.
		$html .= '>' . esc_html( (string) $title ) . '</a>';
		return $html;
	}

	/**
	 * @param array<string,mixed>|object $item
	 * @param bool                       $has_children
	 * @return array{id:int,title:string,url:string,current:bool,children:bool,target:string,rel:string}
	 */
	private static function item_fields( $item, $has_children ) {
		if ( is_object( $item ) ) {
			$classes = isset( $item->classes ) && is_array( $item->classes ) ? $item->classes : array();
			$current = in_array( 'current-menu-item', $classes, true ) || ! empty( $item->current );
			$kids    = $has_children || in_array( 'menu-item-has-children', $classes, true );
			return array(
				'id'       => (int) ( $item->ID ?? 0 ),
				'title'    => wp_strip_all_tags( (string) ( $item->title ?? '' ) ),
				'url'      => (string) ( $item->url ?? '' ),
				'current'  => $current,
				'children' => $kids,
				'target'   => sanitize_key( (string) ( $item->target ?? '' ) ),
				'rel'      => sanitize_text_field( (string) ( $item->xfn ?? '' ) ),
			);
		}
		$item = is_array( $item ) ? $item : array();
		return array(
			'id'       => (int) ( $item['id'] ?? 0 ),
			'title'    => wp_strip_all_tags( (string) ( $item['title'] ?? '' ) ),
			'url'      => (string) ( $item['url'] ?? '' ),
			'current'  => ! empty( $item['current'] ),
			'children' => (bool) $has_children,
			'target'   => sanitize_key( (string) ( $item['target'] ?? '' ) ),
			'rel'      => sanitize_text_field( (string) ( $item['rel'] ?? '' ) ),
		);
	}
}

if ( ! class_exists( __NAMESPACE__ . '\\Site_Nav_Walker', false ) && class_exists( '\Walker_Nav_Menu' ) ) {
	/**
	 * Strips the default menu-item wrapper classes and container divs.
	 * wp_nav_menu() is called with `container => false`; this walker emits
	 * `<li class="lb-site-nav__item"><a>…</a>` and `<ul class="lb-site-nav__sub">`.
	 */
	class Site_Nav_Walker extends \Walker_Nav_Menu {
		/**
		 * @param string   $output
		 * @param int      $depth
		 * @param \stdClass|null $args
		 * @return void
		 */
		public function start_lvl( &$output, $depth = 0, $args = null ) {
			unset( $depth, $args );
			$output .= '<ul class="lb-site-nav__sub">';
		}

		/**
		 * @param string   $output
		 * @param int      $depth
		 * @param \stdClass|null $args
		 * @return void
		 */
		public function end_lvl( &$output, $depth = 0, $args = null ) {
			unset( $depth, $args );
			$output .= '</ul>';
		}

		/**
		 * @param string         $output
		 * @param object         $item
		 * @param int            $depth
		 * @param \stdClass|null $args
		 * @param int            $id
		 * @return void
		 */
		public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
			unset( $depth, $args, $id );
			$classes = ( isset( $item->classes ) && is_array( $item->classes ) ) ? $item->classes : array();
			$kids    = in_array( 'menu-item-has-children', $classes, true );
			$output .= SiteNav::item_open( $item, $kids );
		}

		/**
		 * @param string         $output
		 * @param object         $item
		 * @param int            $depth
		 * @param \stdClass|null $args
		 * @return void
		 */
		public function end_el( &$output, $item, $depth = 0, $args = null ) {
			unset( $item, $depth, $args );
			$output .= '</li>';
		}
	}
}

}

namespace {
	if ( ! function_exists( 'canvasly_lite_nav_menu' ) ) {
		/**
		 * Print a Site Menu from a theme template.
		 *
		 * header.php:
		 *   canvasly_lite_nav_menu( array( 'theme_location' => 'primary', 'layout' => 'horizontal' ) );
		 *
		 * sidebar.php:
		 *   canvasly_lite_nav_menu( array( 'theme_location' => 'primary', 'layout' => 'vertical' ) );
		 *
		 * Pass `'echo' => false` to capture the markup. The plugin registers
		 * the stylesheet and script; this tag enqueues them.
		 *
		 * @param array<string,mixed> $args menu, theme_location, layout, breakpoint, label, echo.
		 * @return string
		 */
		function canvasly_lite_nav_menu( $args = array() ) {
			$args = is_array( $args ) ? $args : array();
			return \CanvaslyLite\Units\SiteNav::display( $args );
		}
	}
}
