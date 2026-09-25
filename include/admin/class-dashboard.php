<?php
namespace CanvaslyLite\Admin;

use CanvaslyLite\Settings\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canvasly admin menu hub.
 *
 * One screen under Canvasly lists every other item in that menu. Canvasly Pro
 * uses this same screen and adds its own menu items through the submenu and
 * the canvasly-lite/dashboard/items filter.
 */
class Dashboard {
	const PAGE          = 'canvasly-lite-dashboard';
	const PARENT        = 'canvasly-lite';
	const DOCUMENTS_URL = 'https://canvasly.pro';
	const SUPPORT_URL   = 'https://canvasly.pro/support.html';

	public static function init() {
		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return;
		}
		// After the parent menu (priority 10). Registering earlier makes WordPress
		// print the slug as a bare href, which leaves wp-admin.
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		// Import Templates registers at 25. This keeps Documents immediately after it.
		add_action( 'admin_menu', array( self::class, 'documents_menu' ), 26 );
		add_action( 'admin_menu', array( self::class, 'promote' ), 1001 );
	}

	public static function menu() {
		$cap = class_exists( Roles::class ) ? Roles::CAP_EDIT : 'edit_posts';
		add_submenu_page(
			self::PARENT,
			__( 'Dashboard', 'canvasly-lite' ),
			__( 'Dashboard', 'canvasly-lite' ),
			$cap,
			self::PAGE,
			array( self::class, 'screen' )
		);
	}

	/**
	 * External documentation link, placed after Import Templates.
	 */
	public static function documents_menu() {
		global $submenu;
		if ( ! isset( $submenu[ self::PARENT ] ) || ! is_array( $submenu[ self::PARENT ] ) ) {
			$submenu[ self::PARENT ] = array();
		}
		foreach ( $submenu[ self::PARENT ] as $row ) {
			if ( is_array( $row ) && isset( $row[2] ) && self::DOCUMENTS_URL === $row[2] ) {
				return;
			}
		}
		$cap  = class_exists( Roles::class ) ? Roles::CAP_EDIT : 'edit_posts';
		$item = array(
			__( 'Documents', 'canvasly-lite' ),
			$cap,
			self::DOCUMENTS_URL,
			__( 'Documents', 'canvasly-lite' ),
		);
		$insert_at = count( $submenu[ self::PARENT ] );
		foreach ( $submenu[ self::PARENT ] as $i => $row ) {
			if ( is_array( $row ) && isset( $row[2] ) && 'canvasly-lite-template-import' === $row[2] ) {
				$insert_at = $i + 1;
				break;
			}
		}
		array_splice( $submenu[ self::PARENT ], $insert_at, 0, array( $item ) );
	}

	/**
	 * Open Canvasly on this screen, and keep the visual builder in the menu.
	 */
	public static function promote() {
		global $submenu;
		if ( empty( $submenu[ self::PARENT ] ) || ! is_array( $submenu[ self::PARENT ] ) ) {
			return;
		}
		$dash = null;
		$rest = array();
		foreach ( $submenu[ self::PARENT ] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( isset( $item[2] ) && self::PAGE === $item[2] ) {
				$dash = $item;
				continue;
			}
			if ( isset( $item[2] ) && self::PARENT === $item[2] ) {
				$item[0] = __( 'Editor', 'canvasly-lite' );
				if ( isset( $item[3] ) ) {
					$item[3] = __( 'Editor', 'canvasly-lite' );
				}
			}
			$rest[] = $item;
		}
		if ( ! $dash ) {
			return;
		}
		$submenu[ self::PARENT ] = array_merge( array( $dash ), $rest );
	}

	/**
	 * Menu items the current user can open, excluding this dashboard.
	 *
	 * @return array<int,array{slug:string,title:string,description:string,url:string}>
	 */
	public static function items() {
		global $submenu;
		$rows = array();
		if ( ! empty( $submenu[ self::PARENT ] ) && is_array( $submenu[ self::PARENT ] ) ) {
			$rows = $submenu[ self::PARENT ];
		}
		$items = array();
		$seen  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = isset( $row[2] ) ? (string) $row[2] : '';
			if ( $slug === '' || $slug === self::PAGE || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$cap = isset( $row[1] ) ? (string) $row[1] : '';
			if ( $cap !== '' && function_exists( 'current_user_can' ) && ! current_user_can( $cap ) ) {
				continue;
			}
			$title = isset( $row[0] ) ? wp_strip_all_tags( (string) $row[0] ) : '';
			if ( $title === '' ) {
				continue;
			}
			$seen[ $slug ] = true;
			$items[]       = array(
				'slug'        => $slug,
				'title'       => $title,
				'description' => self::description( $slug ),
				'url'         => self::url( $slug ),
			);
		}
		/**
		 * Filter dashboard cards. Canvasly Pro appends its own admin screens here.
		 *
		 * @param array $items
		 */
		$filtered = apply_filters( 'canvasly-lite/dashboard/items', $items );
		if ( ! is_array( $filtered ) ) {
			return $items;
		}
		$clean = array();
		$seen  = array();
		foreach ( $filtered as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$slug = isset( $item['slug'] ) ? (string) $item['slug'] : '';
			$title = isset( $item['title'] ) ? wp_strip_all_tags( (string) $item['title'] ) : '';
			if ( $slug === '' || $slug === self::PAGE || $title === '' || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$clean[]       = array(
				'slug'        => $slug,
				'title'       => $title,
				'description' => isset( $item['description'] ) ? wp_strip_all_tags( (string) $item['description'] ) : '',
				'url'         => isset( $item['url'] ) && is_string( $item['url'] ) && $item['url'] !== '' ? $item['url'] : self::url( $slug ),
			);
		}
		return $clean;
	}

	public static function screen() {
		$cap = class_exists( Roles::class ) ? Roles::CAP_EDIT : 'edit_posts';
		if ( function_exists( 'current_user_can' ) && ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to view the Canvasly dashboard.', 'canvasly-lite' ) );
		}
		$by     = array();
		foreach ( self::items() as $item ) {
			$by[ $item['slug'] ] = $item;
		}
		$editor = isset( $by[ self::PARENT ] ) ? $by[ self::PARENT ]['url'] : self::url( self::PARENT );
		$editor = add_query_arg(
			array(
				'post_type' => 'page',
				'new_page'  => '1',
			),
			$editor
		);
		$templates = '';
		foreach ( array( 'canvasly-lite-template-import', 'canvasly-lite-tools' ) as $slug ) {
			if ( isset( $by[ $slug ] ) ) {
				$templates = $by[ $slug ]['url'];
				break;
			}
		}
		$tabs = array( self::PAGE => __( 'Dashboard', 'canvasly-lite' ) );
		foreach ( array( 'canvasly-lite-settings', 'canvasly-lite-global', 'canvasly-lite-tools', 'canvasly-pro-theme', 'canvasly-pro-licensing' ) as $slug ) {
			if ( isset( $by[ $slug ] ) ) {
				$tabs[ $slug ] = $by[ $slug ]['title'];
			}
		}
		$quick_slugs = array( 'canvasly-lite-units', 'canvasly-lite-roles', 'canvasly-lite-global', 'canvasly-lite-settings', 'canvasly-pro-licensing' );
		$quick       = array();
		foreach ( $quick_slugs as $slug ) {
			if ( isset( $by[ $slug ] ) ) {
				$quick[] = $by[ $slug ];
			}
			if ( count( $quick ) === 4 ) {
				break;
			}
		}
		$shown = array( self::PARENT => true, self::PAGE => true );
		foreach ( array_keys( $tabs ) as $slug ) {
			$shown[ $slug ] = true;
		}
		foreach ( $quick as $item ) {
			$shown[ $item['slug'] ] = true;
		}
		if ( $templates !== '' ) {
			foreach ( array( 'canvasly-lite-template-import', 'canvasly-lite-tools' ) as $slug ) {
				if ( isset( $by[ $slug ] ) && $by[ $slug ]['url'] === $templates ) {
					$shown[ $slug ] = true;
					break;
				}
			}
		}
		$shown[ self::DOCUMENTS_URL ] = true;
		$shown[ self::SUPPORT_URL ]   = true;
		$access = array();
		foreach ( $by as $slug => $item ) {
			if ( empty( $shown[ $slug ] ) ) {
				$access[] = $item;
			}
		}
		$icons = array(
			'canvasly-lite-units'       => 'dashicons-screenoptions',
			'canvasly-lite-roles'       => 'dashicons-groups',
			'canvasly-lite-global'      => 'dashicons-art',
			'canvasly-lite-settings'    => 'dashicons-admin-generic',
			self::DOCUMENTS_URL         => 'dashicons-media-document',
			self::SUPPORT_URL           => 'dashicons-sos',
			'canvasly-pro-licensing'    => 'dashicons-admin-network',
			'canvasly-lite-tools'       => 'dashicons-admin-tools',
			'canvasly-lite-system-info' => 'dashicons-info',
		);
		echo '<div class="wrap lb-dash">';
		echo '<h1 class="screen-reader-text">' . esc_html__( 'Dashboard', 'canvasly-lite' ) . '</h1>';
		echo '<div class="lb-dash-header">';
		echo '<div class="lb-dash-brand"><span class="lb-dash-logo" aria-hidden="true">C</span><strong>Canvasly</strong></div>';
		echo '<nav class="lb-dash-tabs" aria-label="' . esc_attr__( 'Canvasly', 'canvasly-lite' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			if ( $slug === self::PAGE ) {
				echo '<span class="is-current" aria-current="page">' . esc_html( $label ) . '</span>';
				continue;
			}
			$url = isset( $by[ $slug ] ) ? $by[ $slug ]['url'] : self::url( $slug );
			echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav></div>';
		echo '<div class="lb-dash-body"><div class="lb-dash-main">';
		echo '<section class="lb-dash-card lb-dash-welcome">';
		echo '<div class="lb-dash-hello">';
		echo '<h2>' . esc_html__( 'Hello,', 'canvasly-lite' ) . '</h2>';
		echo '<p>' . esc_html__( 'Design pages inside WordPress with Canvasly. Start a page, or open any Canvasly screen from this dashboard.', 'canvasly-lite' ) . '</p>';
		echo '<p class="lb-dash-actions">';
		echo '<a class="button button-primary" href="' . esc_url( $editor ) . '">' . esc_html__( 'Create New Page', 'canvasly-lite' ) . '</a>';
		if ( $templates !== '' ) {
			echo '<a class="button lb-dash-button-soft" href="' . esc_url( $templates ) . '">' . esc_html__( 'Explore Templates', 'canvasly-lite' ) . '</a>';
		}
		echo '</p></div>';
		echo '<div class="lb-dash-promo">';
		echo '<strong>' . esc_html__( 'Welcome to Canvasly', 'canvasly-lite' ) . '</strong>';
		echo '<span>' . esc_html__( 'Build in the visual editor', 'canvasly-lite' ) . '</span>';
		echo '<a class="lb-dash-play" href="' . esc_url( $editor ) . '">' . esc_html__( 'Start', 'canvasly-lite' ) . '</a>';
		echo '</div></section>';
		echo '<section class="lb-dash-block"><h2>' . esc_html__( 'Quick Settings', 'canvasly-lite' ) . '</h2>';
		echo '<div class="lb-dash-quick">';
		$documents_placed = false;
		foreach ( $quick as $item ) {
			$icon = isset( $icons[ $item['slug'] ] ) ? $icons[ $item['slug'] ] : 'dashicons-admin-generic';
			echo '<a class="lb-dash-setting" href="' . esc_url( $item['url'] ) . '">';
			echo '<span class="lb-dash-setting-icon dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
			echo '<strong>' . esc_html( $item['title'] ) . '</strong>';
			echo '<span>' . esc_html__( 'Configure', 'canvasly-lite' ) . '</span>';
			echo '</a>';
			if ( 'canvasly-lite-settings' === $item['slug'] ) {
				self::render_documents_card();
				self::render_support_card();
				$documents_placed = true;
			}
		}
		if ( ! $documents_placed ) {
			self::render_documents_card();
			self::render_support_card();
		}
		echo '</div></section>';
		echo '<section class="lb-dash-block"><div class="lb-dash-block-head"><h2>' . esc_html__( 'Get Started', 'canvasly-lite' ) . '</h2></div>';
		echo '<div class="lb-dash-lessons">';
		$lessons = array(
			array( $editor, __( 'Create a page', 'canvasly-lite' ) ),
		);
		if ( isset( $by['canvasly-lite-units'] ) ) {
			$lessons[] = array( $by['canvasly-lite-units']['url'], __( 'Choose units', 'canvasly-lite' ) );
		}
		if ( isset( $by['canvasly-lite-settings'] ) ) {
			$lessons[] = array( $by['canvasly-lite-settings']['url'], __( 'Site settings', 'canvasly-lite' ) );
		}
		foreach ( $lessons as $lesson ) {
			echo '<a class="lb-dash-lesson" href="' . esc_url( $lesson[0] ) . '"><span>' . esc_html( $lesson[1] ) . '</span><i aria-hidden="true"></i></a>';
		}
		echo '</div></section>';
		echo '</div><aside class="lb-dash-side">';
		self::render_comparison();
		self::render_pro_pricing();
		if ( $templates !== '' ) {
			echo '<section class="lb-dash-card lb-dash-templates">';
			echo '<div class="lb-dash-sheets" aria-hidden="true"><span></span><span></span><span></span></div>';
			echo '<h2>' . esc_html__( 'Build pages faster with templates', 'canvasly-lite' ) . '</h2>';
			echo '<p>' . esc_html__( 'Start from a saved template, then change it in the editor.', 'canvasly-lite' ) . '</p>';
			echo '<a class="button button-primary" href="' . esc_url( $templates ) . '">' . esc_html__( 'Explore Templates', 'canvasly-lite' ) . '</a>';
			echo '</section>';
		}
		if ( $access ) {
			echo '<section class="lb-dash-card lb-dash-access"><h2>' . esc_html__( 'Quick Access', 'canvasly-lite' ) . '</h2><ul>';
			foreach ( $access as $item ) {
				$icon = isset( $icons[ $item['slug'] ] ) ? $icons[ $item['slug'] ] : 'dashicons-admin-links';
				echo '<li><a href="' . esc_url( $item['url'] ) . '"><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>' . esc_html( $item['title'] ) . '</a></li>';
			}
			echo '</ul></section>';
		}
		echo '</aside></div></div>';
	}

	/**
	 * Documentation card. Rendered in Quick Settings, immediately after Settings.
	 */
	private static function render_documents_card() {
		echo '<a class="lb-dash-setting" href="' . esc_url( self::DOCUMENTS_URL ) . '" target="_blank" rel="noopener noreferrer">';
		echo '<span class="lb-dash-setting-icon dashicons dashicons-media-document" aria-hidden="true"></span>';
		echo '<strong>' . esc_html__( 'Documents', 'canvasly-lite' ) . '</strong>';
		echo '<span>' . esc_html__( 'Open', 'canvasly-lite' ) . '</span>';
		echo '</a>';
	}

	/**
	 * Support card. Rendered in Quick Settings, immediately after Documents.
	 */
	private static function render_support_card() {
		echo '<div class="lb-dash-support">';
		echo '<a class="lb-dash-setting" href="' . esc_url( self::SUPPORT_URL ) . '" target="_blank" rel="noopener noreferrer">';
		echo '<span class="lb-dash-setting-icon dashicons dashicons-sos" aria-hidden="true"></span>';
		echo '<strong>' . esc_html__( 'Support', 'canvasly-lite' ) . '</strong>';
		echo '<span class="screen-reader-text">' . esc_html__( '(opens in a new tab)', 'canvasly-lite' ) . '</span>';
		echo '<span>' . esc_html__( 'Open', 'canvasly-lite' ) . '</span>';
		echo '</a>';
		echo '<p class="description">' . esc_html__( 'E-Mail support is provided only to Canvasly Pro licensed users.', 'canvasly-lite' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Lite vs Pro chart. Sits in the dashboard sidebar.
	 */
	public static function render_comparison() {
		$pro_price = __( 'From $59 / year', 'canvasly-lite' );
		if ( class_exists( '\CanvaslyPro\License' ) && method_exists( '\CanvaslyPro\License', 'plans' ) ) {
			$prices = array();
			foreach ( \CanvaslyPro\License::plans() as $plan ) {
				if ( isset( $plan['price_usd'] ) ) {
					$prices[] = (int) $plan['price_usd'];
				}
			}
			if ( $prices ) {
				$pro_price = sprintf(
					/* translators: %d: lowest Canvasly Pro annual price in US dollars. */
					__( 'From $%d / year', 'canvasly-lite' ),
					min( $prices )
				);
			}
		}
		echo '<section class="lb-dash-card lb-dash-compare">';
		echo '<table class="lb-dash-compare-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Canvasly Lite versus Canvasly Pro', 'canvasly-lite' ) . '</caption>';
		echo '<thead><tr class="lb-dash-compare-banner">';
		echo '<th scope="col"><strong>' . esc_html__( 'Canvasly', 'canvasly-lite' ) . '</strong>';
		echo '<span>' . esc_html__( 'Feature Comparison', 'canvasly-lite' ) . '</span>';
		echo '<em>' . esc_html__( 'Two plugins. One document model.', 'canvasly-lite' ) . '</em></th>';
		echo '<th scope="col"><strong>' . esc_html__( 'Canvasly Lite', 'canvasly-lite' ) . '</strong>';
		echo '<span>' . esc_html__( 'Free', 'canvasly-lite' ) . '</span>';
		echo '<em>' . esc_html__( 'Visual page builder', 'canvasly-lite' ) . '</em></th>';
		echo '<th scope="col"><strong>' . esc_html__( 'Canvasly Pro', 'canvasly-lite' ) . '</strong>';
		echo '<span>' . esc_html( $pro_price ) . '</span>';
		echo '<em>' . esc_html__( 'Theme, shop, and payments', 'canvasly-lite' ) . '</em></th>';
		echo '</tr><tr class="lb-dash-compare-cols">';
		echo '<th scope="col">' . esc_html__( 'Feature', 'canvasly-lite' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Lite', 'canvasly-lite' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Pro', 'canvasly-lite' ) . '</th>';
		echo '</tr></thead><tbody>';
		$group = '';
		foreach ( self::comparison_rows() as $row ) {
			if ( $row['group'] !== $group ) {
				$group = $row['group'];
				echo '<tr class="lb-dash-compare-group"><th colspan="3" scope="colgroup">' . esc_html( $group ) . '</th></tr>';
			}
			echo '<tr>';
			echo '<th scope="row"><strong>' . esc_html( $row['name'] ) . '</strong>';
			if ( $row['detail'] !== '' ) {
				echo '<span>' . esc_html( $row['detail'] ) . '</span>';
			}
			echo '</th>';
			echo '<td>' . wp_kses_post( self::comparison_mark( $row['lite'], $row['lite_note'] ) ) . '</td>';
			echo '<td>' . wp_kses_post( self::comparison_mark( $row['pro'], $row['pro_note'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody><tfoot><tr>';
		echo '<th scope="row">' . esc_html__( 'Price', 'canvasly-lite' ) . '</th>';
		echo '<td><strong>' . esc_html__( 'Free', 'canvasly-lite' ) . '</strong><span>' . esc_html__( 'Start building', 'canvasly-lite' ) . '</span></td>';
		echo '<td><strong>' . esc_html( $pro_price ) . '</strong><span>' . esc_html__( 'Same features on every plan', 'canvasly-lite' ) . '</span></td>';
		echo '</tr></tfoot></table>';
		echo '<p class="lb-dash-compare-note">' . esc_html__( 'Pro loads only when Lite is active and at least version 0.12.73. WooCommerce elements stay unloaded without WooCommerce. Without a valid Pro key, new Pro elements are dropped on save.', 'canvasly-lite' ) . '</p>';
		echo '</section>';
	}

	/**
	 * Pro annual plans under the comparison chart.
	 *
	 * Rendered from Lite so the cards stay visible when Canvasly Pro is not
	 * installed or its license is not active. Prices match License::plans().
	 */
	public static function render_pro_pricing() {
		echo '<section class="lb-dash-pricing">';
		echo '<h2>' . esc_html__( 'Licensing', 'canvasly-lite' ) . '</h2>';
		echo '<p>' . esc_html__( 'Choose a Canvasly Pro annual plan. Checkout and license-key delivery are handled on the Canvasly license site.', 'canvasly-lite' ) . '</p>';
		echo '<div class="canvasly-pricing-grid">';
		foreach ( self::pro_plans() as $code => $plan ) {
			$sites      = isset( $plan['sites_allowed'] ) ? (int) $plan['sites_allowed'] : 1;
			$price      = isset( $plan['price_usd'] ) ? (int) $plan['price_usd'] : 0;
			$name       = isset( $plan['name'] ) ? (string) $plan['name'] : '';
			$site_label = sprintf(
				/* translators: %d: number of sites included in the plan. */
				_n( '%d site included', '%d sites included', $sites, 'canvasly-lite' ),
				$sites
			);
			echo '<div class="canvasly-pricing-card">';
			echo '<h3>' . esc_html( $name ) . '</h3>';
			echo '<p class="canvasly-pricing-price">$' . esc_html( (string) $price ) . ' <span>' . esc_html__( '/ year', 'canvasly-lite' ) . '</span></p>';
			echo '<p class="canvasly-pricing-meta">' . esc_html( $site_label ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( self::pro_plan_url( (string) $code ) ) . '" target="_blank" rel="noopener noreferrer">';
			echo esc_html(
				sprintf(
					/* translators: %s: license plan name. */
					__( 'Purchase %s', 'canvasly-lite' ),
					$name
				)
			);
			echo '</a></p></div>';
		}
		echo '</div></section>';
	}

	/**
	 * @return array<string,array{name:string,price_usd:int,sites_allowed:int}>
	 */
	private static function pro_plans() {
		if ( class_exists( '\CanvaslyPro\License' ) && method_exists( '\CanvaslyPro\License', 'plans' ) ) {
			$plans = \CanvaslyPro\License::plans();
			if ( is_array( $plans ) && $plans ) {
				return $plans;
			}
		}
		return array(
			'pro_personal'  => array(
				'name'          => 'Pro Personal',
				'price_usd'     => 59,
				'sites_allowed' => 1,
			),
			'pro_business'  => array(
				'name'          => 'Pro Business',
				'price_usd'     => 79,
				'sites_allowed' => 5,
			),
			'pro_agency'    => array(
				'name'          => 'Pro Agency',
				'price_usd'     => 129,
				'sites_allowed' => 25,
			),
			'pro_unlimited' => array(
				'name'          => 'Pro Unlimited',
				'price_usd'     => 199,
				'sites_allowed' => 100,
			),
		);
	}

	/**
	 * Checkout URL on the Canvasly license site, with the plan preselected.
	 *
	 * @param string $code
	 * @return string
	 */
	private static function pro_plan_url( $code ) {
		if ( class_exists( '\CanvaslyPro\License' ) && method_exists( '\CanvaslyPro\License', 'plan_purchase_url' ) ) {
			$url = \CanvaslyPro\License::plan_purchase_url( $code );
			if ( is_string( $url ) && $url !== '' && strpos( $url, 'plan=' ) !== false ) {
				return $url;
			}
		}
		return 'https://license.canvasly.pro/?plan=' . rawurlencode( $code );
	}

	/**
	 * @param string $state yes, no, or note.
	 * @param string $note
	 * @return string
	 */
	private static function comparison_mark( $state, $note ) {
		if ( 'no' === $state ) {
			return '<span class="lb-dash-mark lb-dash-mark-no" aria-label="' . esc_attr( __( 'Not included', 'canvasly-lite' ) ) . '">&#10007;</span>';
		}
		if ( 'yes' === $state && $note === '' ) {
			return '<span class="lb-dash-mark lb-dash-mark-yes" aria-label="' . esc_attr( __( 'Included', 'canvasly-lite' ) ) . '">&#10003;</span>';
		}
		$html = '';
		if ( 'yes' === $state ) {
			$html .= '<span class="lb-dash-mark lb-dash-mark-yes" aria-hidden="true">&#10003;</span>';
		}
		if ( $note !== '' ) {
			$html .= '<span class="lb-dash-mark-text">' . esc_html( $note ) . '</span>';
		}
		return $html;
	}

	/**
	 * @return array<int,array{group:string,name:string,detail:string,lite:string,lite_note:string,pro:string,pro_note:string}>
	 */
	private static function comparison_rows() {
		return array(
			array(
				'group'     => __( 'Builder', 'canvasly-lite' ),
				'name'      => __( 'Visual editor', 'canvasly-lite' ),
				'detail'    => __( 'Drag-and-drop canvas, nested containers, CSS Grid, desktop, tablet, and mobile.', 'canvasly-lite' ),
				'lite'      => 'yes',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Builder', 'canvasly-lite' ),
				'name'      => __( 'Core elements', 'canvasly-lite' ),
				'detail'    => __( 'Heading, text, image, button, gallery, video, tabs, form, price table, collection loop, and the rest of the Lite library.', 'canvasly-lite' ),
				'lite'      => 'yes',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Builder', 'canvasly-lite' ),
				'name'      => __( 'Design system', 'canvasly-lite' ),
				'detail'    => __( 'Global colors, typography, variables, classes, components, and site-kit import and export.', 'canvasly-lite' ),
				'lite'      => 'yes',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => __( 'Kit also carries Pro theme templates', 'canvasly-lite' ),
			),
			array(
				'group'     => __( 'Builder', 'canvasly-lite' ),
				'name'      => __( 'Style and custom CSS', 'canvasly-lite' ),
				'detail'    => __( 'Typography, spacing, borders, shadows, visibility, ARIA, and CSS on the element or the page.', 'canvasly-lite' ),
				'lite'      => 'yes',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Builder', 'canvasly-lite' ),
				'name'      => __( 'Entrance and exit motion', 'canvasly-lite' ),
				'detail'    => __( 'CSS presets, custom keyframes, and viewport, load, hover, click, and scroll triggers.', 'canvasly-lite' ),
				'lite'      => 'yes',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Builder', 'canvasly-lite' ),
				'name'      => __( 'Sticky, scroll, and page transitions', 'canvasly-lite' ),
				'detail'    => __( 'Stick to top or bottom, scroll opacity, slide, and scale, scroll snap, and page transitions.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Content', 'canvasly-lite' ),
				'name'      => __( 'Saved templates', 'canvasly-lite' ),
				'detail'    => __( 'Shortcode, Gutenberg block, template widget, or sidebar widget.', 'canvasly-lite' ),
				'lite'      => 'note',
				'lite_note' => __( 'Page templates', 'canvasly-lite' ),
				'pro'       => 'note',
				'pro_note'  => __( 'Also header, footer, popup, loop item, section', 'canvasly-lite' ),
			),
			array(
				'group'     => __( 'Content', 'canvasly-lite' ),
				'name'      => __( 'Collection loop', 'canvasly-lite' ),
				'detail'    => __( 'Query posts or terms, with numbered, previous-next, or load-more pagination.', 'canvasly-lite' ),
				'lite'      => 'yes',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => __( 'Loop-item templates and a taxonomy filter', 'canvasly-lite' ),
			),
			array(
				'group'     => __( 'Content', 'canvasly-lite' ),
				'name'      => __( 'Dynamic tags', 'canvasly-lite' ),
				'detail'    => __( 'Values that resolve in the editor and on the front end.', 'canvasly-lite' ),
				'lite'      => 'note',
				'lite_note' => __( 'Post, author, site, user, archive, term', 'canvasly-lite' ),
				'pro'       => 'note',
				'pro_note'  => __( 'Plus request, custom fields, ACF, product price and SKU', 'canvasly-lite' ),
			),
			array(
				'group'     => __( 'Content', 'canvasly-lite' ),
				'name'      => __( 'Forms', 'canvasly-lite' ),
				'detail'    => __( 'Lite keeps the form element. Pro extends fields and what happens after submit.', 'canvasly-lite' ),
				'lite'      => 'note',
				'lite_note' => __( 'One email', 'canvasly-lite' ),
				'pro'       => 'note',
				'pro_note'  => __( 'Email, redirect, webhook, submissions log, CSV', 'canvasly-lite' ),
			),
			array(
				'group'     => __( 'Content', 'canvasly-lite' ),
				'name'      => __( 'Extra form fields', 'canvasly-lite' ),
				'detail'    => __( 'Number, date, radio, acceptance, and file upload.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Content', 'canvasly-lite' ),
				'name'      => __( 'Pro elements', 'canvasly-lite' ),
				'detail'    => __( 'Call to action, countdown, carousels, hotspot, price list, off-canvas, Lottie, video playlist, and the rest of the Pro set.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Theme', 'canvasly-lite' ),
				'name'      => __( 'Theme Builder', 'canvasly-lite' ),
				'detail'    => __( 'Header, footer, single, archive, search, 404, and section, with display rules.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Theme', 'canvasly-lite' ),
				'name'      => __( 'Theme elements', 'canvasly-lite' ),
				'detail'    => __( 'Site identity, the current post, archives, author, comments, breadcrumbs, search, and a sitemap.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Theme', 'canvasly-lite' ),
				'name'      => __( 'Popups', 'canvasly-lite' ),
				'detail'    => __( 'Load, scroll, click, exit, and inactivity triggers. A link can open one.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Theme', 'canvasly-lite' ),
				'name'      => __( 'Display conditions', 'canvasly-lite' ),
				'detail'    => __( 'Hide one element by role, login, date, author, taxonomy, or URL parameter.', 'canvasly-lite' ),
				'lite'      => 'note',
				'lite_note' => __( 'Device visibility only', 'canvasly-lite' ),
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Theme', 'canvasly-lite' ),
				'name'      => __( 'Menus', 'canvasly-lite' ),
				'detail'    => __( 'WordPress menus with dropdowns, and a mega menu from a section template.', 'canvasly-lite' ),
				'lite'      => 'note',
				'lite_note' => __( 'Site navigation element', 'canvasly-lite' ),
				'pro'       => 'note',
				'pro_note'  => __( 'Nav Menu and mega menu', 'canvasly-lite' ),
			),
			array(
				'group'     => __( 'Theme', 'canvasly-lite' ),
				'name'      => __( 'Custom code, fonts, and icons', 'canvasly-lite' ),
				'detail'    => __( 'Site-wide snippets, uploaded font files, and extra icon sets.', 'canvasly-lite' ),
				'lite'      => 'note',
				'lite_note' => __( 'Per-element CSS, Google Fonts, icon manager', 'canvasly-lite' ),
				'pro'       => 'note',
				'pro_note'  => __( 'Site snippets, uploaded fonts, custom icon sets', 'canvasly-lite' ),
			),
			array(
				'group'     => __( 'Shop', 'canvasly-lite' ),
				'name'      => __( 'WooCommerce templates', 'canvasly-lite' ),
				'detail'    => __( 'Product and product-archive locations. Unloaded without WooCommerce.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'note',
				'pro_note'  => __( 'When WooCommerce is active', 'canvasly-lite' ),
			),
			array(
				'group'     => __( 'Shop', 'canvasly-lite' ),
				'name'      => __( 'Product and cart elements', 'canvasly-lite' ),
				'detail'    => __( 'Product parts, menu cart, and notices. Cart, checkout, and my account print WooCommerce forms.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'note',
				'pro_note'  => __( 'When WooCommerce is active', 'canvasly-lite' ),
			),
			array(
				'group'     => __( 'Shop', 'canvasly-lite' ),
				'name'      => __( 'Hosted payments', 'canvasly-lite' ),
				'detail'    => __( 'Stripe, PayPal, Square, Razorpay, Mollie, and Authorize.net. Card data stays on the gateway.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Platform', 'canvasly-lite' ),
				'name'      => __( 'Editor notes', 'canvasly-lite' ),
				'detail'    => __( 'Notes on a node for people who can edit. Not public comments.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Platform', 'canvasly-lite' ),
				'name'      => __( 'AI connection', 'canvasly-lite' ),
				'detail'    => __( 'AI connection, MCP host, and the layout-schema API.', 'canvasly-lite' ),
				'lite'      => 'no',
				'lite_note' => '',
				'pro'       => 'yes',
				'pro_note'  => '',
			),
			array(
				'group'     => __( 'Platform', 'canvasly-lite' ),
				'name'      => __( 'License', 'canvasly-lite' ),
				'detail'    => __( 'An inactive key blocks new Pro elements and Pro REST routes.', 'canvasly-lite' ),
				'lite'      => 'note',
				'lite_note' => __( 'No license', 'canvasly-lite' ),
				'pro'       => 'note',
				'pro_note'  => __( 'Annual key, same features on every plan', 'canvasly-lite' ),
			),
		);
	}

	/**
	 * @param string $slug
	 * @return string
	 */
	private static function description( $slug ) {
		$map = array(
			'canvasly-lite'                  => __( 'Open the visual builder.', 'canvasly-lite' ),
			'canvasly-lite-units'            => __( 'Enable units and limit them by role.', 'canvasly-lite' ),
			'canvasly-lite-roles'            => __( 'Choose which roles can edit and design.', 'canvasly-lite' ),
			'canvasly-lite-settings'         => __( 'Site options, integrations, performance, and tools.', 'canvasly-lite' ),
			'canvasly-lite-global'           => __( 'Colors, fonts, and global design tokens.', 'canvasly-lite' ),
			'canvasly-lite-tools'            => __( 'Regenerate CSS, replace URLs, and import a kit.', 'canvasly-lite' ),
			'canvasly-lite-system-info'      => __( 'Environment report for support.', 'canvasly-lite' ),
			'canvasly-lite-template-import'  => __( 'Import saved templates.', 'canvasly-lite' ),
			self::DOCUMENTS_URL              => __( 'Canvasly documentation.', 'canvasly-lite' ),
			self::SUPPORT_URL                => __( 'E-Mail support is provided only to Canvasly Pro licensed users.', 'canvasly-lite' ),
		);
		return isset( $map[ $slug ] ) ? $map[ $slug ] : '';
	}

	/**
	 * @param string $slug
	 * @return string
	 */
	private static function url( $slug ) {
		if ( preg_match( '#^https?://#i', $slug ) ) {
			return $slug;
		}
		if ( function_exists( 'menu_page_url' ) ) {
			$url = menu_page_url( $slug, false );
			if ( is_string( $url ) && $url !== '' ) {
				return $url;
			}
		}
		if ( strpos( $slug, '.php' ) !== false ) {
			return admin_url( $slug );
		}
		return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
	}
}
