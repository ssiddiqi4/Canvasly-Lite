<?php
namespace CanvaslyLite\Units;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jump target plus an optional site menu.
 *
 * In the header, footer, and canvas the Content tab lists menus from
 * Appearance → Menus and Navigation posts from the Site Editor.
 */
class MenuAnchor extends Unit {
	/**
	 * @var array<int,array{id:string,name:string,source:string,items:array<int,array{id:int,title:string,url:string,parent:int}>}>|null
	 */
	private static $test_catalog = null;

	/**
	 * @param array<int,array<string,mixed>>|null $catalog
	 * @return void
	 */
	public static function set_catalog_for_tests( $catalog ) {
		self::$test_catalog = is_array( $catalog ) ? $catalog : null;
	}

	/**
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$test_catalog = null;
	}

	public function type() {
		return 'menu_anchor';
	}

	public function title() {
		return __( 'Menu Anchor', 'canvasly-lite' );
	}

	public function icon() {
		return '⚑';
	}

	public function keywords() {
		return array( 'menu', 'anchor', 'navigation', 'nav', 'header' );
	}

	const STYLE_HANDLE  = 'canvasly-lite-menu-anchor';
	const SCRIPT_HANDLE = 'canvasly-lite-menu-anchor';
	const SHORTCODE     = 'canvasly_anchor';

	/**
	 * Shortcode, menu injection, and asset registration.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_discovered' ), 20 );
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
		add_filter( 'wp_nav_menu_items', array( self::class, 'filter_nav_items' ), 10, 2 );
	}

	public function defaults() {
		return array(
			'anchor' => 'section-1',
			'menu'   => '',
		);
	}

	public function controls() {
		$section = __( 'Menu Anchor', 'canvasly-lite' );
		return array(
			'menu'   => 'select',
			'anchor' => $this->ctrl(
				'text',
				__( 'Anchor ID', 'canvasly-lite' ),
				'content',
				$section,
				array(
					'placeholder' => 'contact-us',
					'description' => __( 'Drop this just above the section. Then set a menu item, button, or text link to # plus this ID, for example #contact-us.', 'canvasly-lite' ),
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
	 * Menus for the editor dropdown.
	 *
	 * @return array<int,array{id:string,name:string,source:string,items:array<int,array{id:int,title:string,url:string,parent:int}>}>
	 */
	public static function catalog() {
		if ( is_array( self::$test_catalog ) ) {
			return self::$test_catalog;
		}
		$out = array();
		if ( function_exists( 'wp_get_nav_menus' ) ) {
			$menus = wp_get_nav_menus();
			if ( is_array( $menus ) ) {
				foreach ( $menus as $menu ) {
					if ( ! is_object( $menu ) ) {
						continue;
					}
					$id = (int) ( $menu->term_id ?? 0 );
					if ( $id < 1 ) {
						continue;
					}
					$name = (string) ( $menu->name ?? '' );
					$out[] = array(
						'id'     => 'menu:' . $id,
						'name'   => $name !== '' ? $name : (string) $id,
						'source' => 'menu',
						'items'  => self::classic_items( $id ),
					);
				}
			}
		}
		if ( function_exists( 'get_posts' ) ) {
			$posts = get_posts(
				array(
					'post_type'   => 'wp_navigation',
					'post_status' => array( 'publish', 'draft' ),
					'numberposts' => 100,
					'orderby'     => 'title',
					'order'       => 'ASC',
				)
			);
			foreach ( (array) $posts as $post ) {
				if ( ! is_object( $post ) ) {
					continue;
				}
				$id = (int) ( $post->ID ?? 0 );
				if ( $id < 1 ) {
					continue;
				}
				$title = trim( wp_strip_all_tags( (string) ( $post->post_title ?? '' ) ) );
				$out[] = array(
					'id'     => 'nav:' . $id,
					'name'   => $title !== '' ? $title : (string) $id,
					'source' => 'nav',
					'items'  => self::block_items( $post ),
				);
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Stored setting.
	 * @return array{source:string,id:int}|null
	 */
	public static function parse_ref( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return null;
		}
		if ( preg_match( '/^(menu|nav|classic|block):(\d+)$/', $value, $m ) ) {
			$source = ( 'nav' === $m[1] || 'block' === $m[1] ) ? 'nav' : 'menu';
			$id     = (int) $m[2];
			return $id > 0 ? array( 'source' => $source, 'id' => $id ) : null;
		}
		if ( ctype_digit( $value ) && (int) $value > 0 ) {
			return array( 'source' => 'menu', 'id' => (int) $value );
		}
		return null;
	}

	public function render( $s, $children = '' ) {
		unset( $children );
		$anchor = sanitize_title( (string) ( $s['anchor'] ?? 'section-1' ) );
		if ( $anchor === '' ) {
			$anchor = 'section-1';
		}
		$html = '<span id="' . esc_attr( $anchor ) . '" class="lb-menu-anchor"></span>';
		$nav  = self::render_menu( $s['menu'] ?? '' );
		if ( $nav !== '' ) {
			$html .= $nav;
		}
		return $html;
	}

	/**
	 * Slug safe for an HTML id and a `#` href.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function slug( $value ) {
		$slug = sanitize_title( (string) $value );
		return is_string( $slug ) ? $slug : '';
	}

	/**
	 * Destination wrapper. The slug becomes the element id (`contact-us`).
	 *
	 * @param mixed  $slug
	 * @param string $content Inner HTML. Caller sanitizes rich content.
	 * @param array  $args    Optional `class` (extra class names).
	 * @return string
	 */
	public static function section( $slug, $content = '', $args = array() ) {
		$id    = self::slug( $slug );
		$id    = $id !== '' ? $id : 'section';
		$class = 'lb-anchor-section';
		$extra = is_array( $args ) ? (string) ( $args['class'] ?? '' ) : '';
		foreach ( preg_split( '/\s+/', trim( $extra ) ) ?: array() as $part ) {
			$part = sanitize_html_class( $part );
			if ( $part !== '' ) {
				$class .= ' ' . $part;
			}
		}
		return '<section id="' . esc_attr( $id ) . '" class="' . esc_attr( $class ) . '">' . $content . '</section>';
	}

	/**
	 * Trigger link. Hash-only hrefs stay unescaped by `esc_url`, which drops them.
	 *
	 * @param mixed  $slug
	 * @param string $label
	 * @param array  $args Optional `class`.
	 * @return string
	 */
	public static function link( $slug, $label, $args = array() ) {
		$id    = self::slug( $slug );
		$id    = $id !== '' ? $id : 'section';
		$class = 'lb-anchor-link';
		$extra = is_array( $args ) ? (string) ( $args['class'] ?? '' ) : '';
		foreach ( preg_split( '/\s+/', trim( $extra ) ) ?: array() as $part ) {
			$part = sanitize_html_class( $part );
			if ( $part !== '' ) {
				$class .= ' ' . $part;
			}
		}
		return '<a class="' . esc_attr( $class ) . '" href="#' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * One `wp_nav_menu` list item pointing at a destination slug.
	 *
	 * @param mixed  $slug
	 * @param string $label
	 * @return string
	 */
	public static function nav_item( $slug, $label ) {
		return '<li class="menu-item lb-anchor-menu-item">' . self::link( $slug, $label ) . '</li>';
	}

	/**
	 * Append anchor links supplied by `canvasly-lite/menu_anchor/links`.
	 *
	 * Each item is `[ 'id' => 'contact-us', 'label' => 'Contact' ]`.
	 *
	 * @param string $items
	 * @param mixed  $args
	 * @return string
	 */
	public static function filter_nav_items( $items, $args ) {
		$links = apply_filters( 'canvasly-lite/menu_anchor/links', array(), $args );
		if ( ! is_array( $links ) || ! $links ) {
			return $items;
		}
		$html = '';
		foreach ( $links as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$slug  = (string) ( $link['id'] ?? ( $link['slug'] ?? '' ) );
			$label = (string) ( $link['label'] ?? '' );
			if ( self::slug( $slug ) === '' || $label === '' ) {
				continue;
			}
			$html .= self::nav_item( $slug, $label );
		}
		if ( $html === '' ) {
			return $items;
		}
		self::enqueue();
		return $items . $html;
	}

	/**
	 * [canvasly_anchor id="contact-us"]…[/canvasly_anchor]
	 *
	 * @param array|string $atts
	 * @param string|null  $content
	 * @return string
	 */
	public static function shortcode( $atts, $content = '' ) {
		$atts = function_exists( 'shortcode_atts' )
			? shortcode_atts(
				array(
					'id'    => '',
					'class' => '',
				),
				is_array( $atts ) ? $atts : array(),
				self::SHORTCODE
			)
			: array_merge(
				array(
					'id'    => '',
					'class' => '',
				),
				is_array( $atts ) ? $atts : array()
			);
		self::enqueue();
		$inner = (string) $content;
		if ( function_exists( 'do_shortcode' ) ) {
			$inner = do_shortcode( $inner );
		}
		return self::section( $atts['id'], $inner, array( 'class' => (string) $atts['class'] ) );
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		if ( ! defined( 'CANVASLY_LITE_PATH' ) || ! defined( 'CANVASLY_LITE_URL' ) || ! function_exists( 'wp_register_style' ) ) {
			return;
		}
		$css = CANVASLY_LITE_PATH . 'assets/css/menu-anchor.css';
		$js  = CANVASLY_LITE_PATH . 'assets/js/menu-anchor.js';
		$ver = defined( 'CANVASLY_LITE_VERSION' ) ? (string) CANVASLY_LITE_VERSION : '1';
		wp_register_style( self::STYLE_HANDLE, CANVASLY_LITE_URL . 'assets/css/menu-anchor.css', array(), $ver . '-' . ( file_exists( $css ) ? filemtime( $css ) : $ver ) );
		if ( function_exists( 'wp_register_script' ) ) {
			wp_register_script( self::SCRIPT_HANDLE, CANVASLY_LITE_URL . 'assets/js/menu-anchor.js', array(), $ver . '-' . ( file_exists( $js ) ? filemtime( $js ) : $ver ), true );
		}
	}

	/**
	 * Enqueue when a shortcode or injected menu link is already known at head time.
	 * The unit itself enqueues through FrontendAssets via styles()/scripts().
	 *
	 * @return void
	 */
	public static function enqueue_discovered() {
		self::register_assets();
		$links = apply_filters( 'canvasly-lite/menu_anchor/links', array(), null );
		if ( is_array( $links ) && $links ) {
			self::enqueue();
			return;
		}
		if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! function_exists( 'get_post' ) ) {
			return;
		}
		$post = get_post();
		if ( ! is_object( $post ) || ! function_exists( 'has_shortcode' ) ) {
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
	 * @param mixed $value
	 * @return string
	 */
	private static function render_menu( $value ) {
		$ref = self::parse_ref( $value );
		if ( ! $ref ) {
			return '';
		}
		$key = $ref['source'] . ':' . $ref['id'];
		if ( is_array( self::$test_catalog ) ) {
			$entry = self::find_entry( $key );
			return self::render_items( $entry['items'] ?? array(), (string) ( $entry['name'] ?? '' ) );
		}
		if ( 'menu' === $ref['source'] && function_exists( 'wp_nav_menu' ) ) {
			$html = wp_nav_menu(
				array(
					'menu'            => $ref['id'],
					'echo'            => false,
					'container'       => 'nav',
					'container_class' => 'lb-anchor-menu',
					'menu_class'      => 'lb-anchor-menu-list',
					'fallback_cb'     => '__return_empty_string',
					'depth'           => 0,
				)
			);
			if ( is_string( $html ) && trim( $html ) !== '' ) {
				return $html;
			}
		}
		if ( 'nav' === $ref['source'] && function_exists( 'do_blocks' ) ) {
			$html = do_blocks( '<!-- wp:navigation {"ref":' . (int) $ref['id'] . ',"overlayMenu":"never"} /-->' );
			$html = is_string( $html ) ? trim( $html ) : '';
			if ( $html !== '' ) {
				return $html;
			}
		}
		$entry = self::find_entry( $key );
		return self::render_items( $entry['items'] ?? array(), (string) ( $entry['name'] ?? '' ) );
	}

	/**
	 * @param string $id menu:1 or nav:1
	 * @return array{id:string,name:string,source:string,items:array<int,array{id:int,title:string,url:string,parent:int}>}
	 */
	private static function find_entry( $id ) {
		foreach ( self::catalog() as $entry ) {
			if ( isset( $entry['id'] ) && (string) $entry['id'] === (string) $id ) {
				return $entry;
			}
		}
		return array(
			'id'     => (string) $id,
			'name'   => '',
			'source' => '',
			'items'  => array(),
		);
	}

	/**
	 * @param array<int,array{id:int,title:string,url:string,parent:int}> $items
	 * @param string                                                      $label
	 * @return string
	 */
	private static function render_items( $items, $label ) {
		$tree = self::branch( self::by_parent( $items ), 0, true );
		if ( $tree === '' ) {
			return '';
		}
		$aria = $label !== '' ? ' aria-label="' . esc_attr( $label ) . '"' : '';
		return '<nav class="lb-anchor-menu"' . $aria . '>' . $tree . '</nav>';
	}

	/**
	 * @param array<int,array{id:int,title:string,url:string,parent:int}> $items
	 * @return array<int,array<int,array{id:int,title:string,url:string,parent:int}>>
	 */
	private static function by_parent( $items ) {
		$by = array();
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$parent = (int) ( $item['parent'] ?? 0 );
			$by[ $parent ][] = $item;
		}
		return $by;
	}

	/**
	 * @param array<int,array<int,array{id:int,title:string,url:string,parent:int}>> $by
	 * @param int                                                                    $parent
	 * @param bool                                                                   $root
	 * @return string
	 */
	private static function branch( $by, $parent, $root ) {
		if ( empty( $by[ $parent ] ) ) {
			return '';
		}
		$html = '<ul class="' . ( $root ? 'lb-anchor-menu-list' : 'lb-anchor-menu-sub' ) . '">';
		foreach ( $by[ $parent ] as $item ) {
			$id    = (int) ( $item['id'] ?? 0 );
			$url   = (string) ( $item['url'] ?? '' );
			$title = (string) ( $item['title'] ?? '' );
			$href  = $url !== '' ? Unit::link_href( $url ) : '#';
			$html .= '<li class="lb-anchor-menu-item"><a href="' . $href . '">' . esc_html( $title ) . '</a>';
			$html .= self::branch( $by, $id, false );
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * @param int $menu_id
	 * @return array<int,array{id:int,title:string,url:string,parent:int}>
	 */
	private static function classic_items( $menu_id ) {
		if ( ! function_exists( 'wp_get_nav_menu_items' ) ) {
			return array();
		}
		$raw = wp_get_nav_menu_items( $menu_id );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}
			$out[] = array(
				'id'     => (int) ( $item->ID ?? 0 ),
				'title'  => wp_strip_all_tags( (string) ( $item->title ?? '' ) ),
				'url'    => (string) ( $item->url ?? '' ),
				'parent' => (int) ( $item->menu_item_parent ?? 0 ),
			);
			if ( count( $out ) >= 200 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param object $post
	 * @return array<int,array{id:int,title:string,url:string,parent:int}>
	 */
	private static function block_items( $post ) {
		$content = (string) ( $post->post_content ?? '' );
		if ( $content === '' || ! function_exists( 'parse_blocks' ) ) {
			return array();
		}
		$items = array();
		$seq   = 0;
		self::walk_nav_blocks( parse_blocks( $content ), 0, $items, $seq );
		return $items;
	}

	/**
	 * @param array<int,mixed>                                            $blocks
	 * @param int                                                         $parent
	 * @param array<int,array{id:int,title:string,url:string,parent:int}> $items
	 * @param int                                                         $seq
	 * @return void
	 */
	private static function walk_nav_blocks( $blocks, $parent, array &$items, &$seq ) {
		foreach ( (array) $blocks as $block ) {
			if ( count( $items ) >= 200 ) {
				return;
			}
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name  = (string) ( $block['blockName'] ?? '' );
			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
			if ( in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu', 'core/home-link' ), true ) ) {
				++$seq;
				$id    = $seq;
				$label = (string) ( $attrs['label'] ?? ( $attrs['title'] ?? '' ) );
				if ( 'core/home-link' === $name && $label === '' ) {
					$label = __( 'Home', 'canvasly-lite' );
				}
				$items[] = array(
					'id'     => $id,
					'title'  => wp_strip_all_tags( $label ),
					'url'    => (string) ( $attrs['url'] ?? '' ),
					'parent' => (int) $parent,
				);
				if ( $inner ) {
					self::walk_nav_blocks( $inner, $id, $items, $seq );
				}
				continue;
			}
			if ( $inner ) {
				self::walk_nav_blocks( $inner, $parent, $items, $seq );
			}
		}
	}
}
