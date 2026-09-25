<?php
/**
 * Plugin Name: Canvasly - Visual Page Builder
 * Description: A lightweight, independent visual page builder for WordPress.
 * Version: 0.12.84
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Canvasly
 * License: GPL-2.0-or-later
 * Text Domain: canvasly-lite
 * Domain Path: /languages
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'CANVASLY_LITE_FILE' ) ) {
	define( 'CANVASLY_LITE_FILE', __FILE__ );
}
if ( ! defined( 'CANVASLY_LITE_PATH' ) ) {
	define( 'CANVASLY_LITE_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CANVASLY_LITE_URL' ) ) {
	define( 'CANVASLY_LITE_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CANVASLY_LITE_VERSION' ) ) {
	define( 'CANVASLY_LITE_VERSION', '0.12.84' );
}

/**
 * PHP and WordPress versions this plugin can run. WordPress also reads the
 * plugin header, and this notice explains the stop when those are too old.
 */
function canvasly_lite_requirements_met() {
	global $wp_version;
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		return false;
	}
	if ( isset( $wp_version ) && is_string( $wp_version ) && version_compare( $wp_version, '6.9', '<' ) ) {
		return false;
	}
	return true;
}

function canvasly_lite_requirements_notice() {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Canvasly needs PHP 7.4 or newer and WordPress 6.9 or newer. It stays inactive until this site meets those versions.', 'canvasly-lite' ) . '</p></div>';
}

if ( ! canvasly_lite_requirements_met() ) {
	add_action( 'admin_notices', 'canvasly_lite_requirements_notice' );
	return;
}

$canvasly_lite_documents_file = CANVASLY_LITE_PATH . 'includes/document/class-documents.php';
if ( is_readable( $canvasly_lite_documents_file ) ) {
	require_once $canvasly_lite_documents_file;
}
$canvasly_lite_admin_context = CANVASLY_LITE_PATH . 'includes/admin/class-admin-context.php';
if ( is_readable( $canvasly_lite_admin_context ) ) {
	require_once $canvasly_lite_admin_context;
}

/**
 * Whether Canvasly may edit this post type (enabled public types).
 *
 * @param string $post_type
 * @return bool
 */
function canvasly_lite_supports_post_type( $post_type ) {
	if ( class_exists( '\CanvaslyLite\Document\Documents', false ) ) {
		return \CanvaslyLite\Document\Documents::supports( $post_type );
	}
	return in_array( sanitize_key( $post_type ), array( 'post', 'page' ), true );
}

/**
 * Capability required to create a post of this type.
 *
 * @param string $post_type
 * @return string
 */
function canvasly_lite_post_type_edit_cap( $post_type ) {
	if ( class_exists( '\CanvaslyLite\Document\Documents', false ) ) {
		return \CanvaslyLite\Document\Documents::edit_cap( $post_type );
	}
	return ( 'page' === $post_type ) ? 'edit_pages' : 'edit_posts';
}

/**
 * Native Gutenberg zero-bootstrap guard.
 *
 * On WordPress editor requests for enabled post types, Canvasly contributes
 * no editor/runtime hooks, REST routes, or CPT registration. The Template
 * Gutenberg block is registered before this guard so it still appears in
 * post.php / post-new.php.
 */
function canvasly_lite_native_editor_zero_bootstrap() {
    // During normal plugin loading, $GLOBALS['pagenow'] may not yet be
    // populated because wp-settings.php loads active plugins before the
    // admin.php screen bootstrap completes. Use the actual executing script
    // first, then fall back to $pagenow when it is already available.
    $script = '';
    if ( isset( $_SERVER['SCRIPT_NAME'] ) ) {
        $script = basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) );
    } elseif ( isset( $_SERVER['PHP_SELF'] ) ) {
        $script = basename( sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) );
    }
    $pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : $script;
    if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
        return false;
    }
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only WordPress editor screen routing.
    if ( 'post-new.php' === $pagenow ) {
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
        return canvasly_lite_supports_post_type( $post_type );
    }
    $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    if ( ! $post_id ) {
        return false;
    }
    $post = get_post( $post_id );
    return $post && canvasly_lite_supports_post_type( $post->post_type );
}

/**
 * The template shortcode/block must register even on native Gutenberg screens,
 * where the rest of Canvasly deliberately does not boot.
 */
$canvasly_lite_template_block = CANVASLY_LITE_PATH . 'includes/templates/class-template-block.php';
if ( is_readable( $canvasly_lite_template_block ) ) {
	require_once $canvasly_lite_template_block;
	if ( class_exists( '\CanvaslyLite\Templates\TemplateBlock', false ) ) {
		\CanvaslyLite\Templates\TemplateBlock::bootstrap();
	}
}

/**
 * Portability and third-party compatibility must run even when the editor
 * runtime does not boot (native Gutenberg screens, Tools → Import). SEO
 * analysis plugins read post content from the block editor.
 */
$canvasly_lite_compat = CANVASLY_LITE_PATH . 'includes/compatibility/';
foreach ( array( 'meta', 'import-export', 'duplicate', 'multilingual', 'seo', 'cache', 'theme-support', 'compat' ) as $canvasly_lite_compat_file ) {
	$canvasly_lite_compat_path = $canvasly_lite_compat . 'class-' . $canvasly_lite_compat_file . '.php';
	if ( is_readable( $canvasly_lite_compat_path ) ) {
		require_once $canvasly_lite_compat_path;
	}
}
$canvasly_lite_collab = CANVASLY_LITE_PATH . 'includes/design/class-collaboration.php';
if ( is_readable( $canvasly_lite_collab ) ) {
	require_once $canvasly_lite_collab;
}
if ( class_exists( '\CanvaslyLite\Compatibility\Compat', false ) ) {
	\CanvaslyLite\Compatibility\Compat::init();
} else {
	if ( class_exists( '\CanvaslyLite\Compatibility\ImportExport', false ) ) {
		\CanvaslyLite\Compatibility\ImportExport::init();
	}
	if ( class_exists( '\CanvaslyLite\Compatibility\Duplicate', false ) ) {
		\CanvaslyLite\Compatibility\Duplicate::init();
	}
}

$canvasly_lite_admin_bar = CANVASLY_LITE_PATH . 'includes/admin/class-admin-bar.php';
if ( is_readable( $canvasly_lite_admin_bar ) ) {
	require_once $canvasly_lite_admin_bar;
	if ( class_exists( '\CanvaslyLite\Admin\AdminBar', false ) ) {
		\CanvaslyLite\Admin\AdminBar::init();
	}
}

if ( canvasly_lite_native_editor_zero_bootstrap() ) {
    // Native Gutenberg integration: launcher plus the Template block.
    // No Canvasly editor/runtime assets or normal hooks are initialized here.
    add_action( 'admin_enqueue_scripts', 'canvasly_lite_native_editor_launcher' );
    return;
}

function canvasly_lite_native_editor_launcher() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only WordPress editor screen routing.
    $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
    $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    if ( $post_id ) {
        $post = get_post( $post_id );
        if ( $post ) {
            $post_type = $post->post_type;
        }
    }
    if ( ! canvasly_lite_supports_post_type( $post_type ) ) {
        return;
    }
    if ( ! current_user_can( canvasly_lite_post_type_edit_cap( $post_type ) ) ) {
        return;
    }
    $roles_file = CANVASLY_LITE_PATH . 'includes/settings/class-roles.php';
    if ( is_readable( $roles_file ) ) {
        require_once $roles_file;
        if ( class_exists( '\CanvaslyLite\Settings\Roles', false ) ) {
            if ( method_exists( '\CanvaslyLite\Settings\Roles', 'maybe_sync' ) ) {
                \CanvaslyLite\Settings\Roles::maybe_sync();
            }
            if ( ! \CanvaslyLite\Settings\Roles::can_edit() ) {
                return;
            }
        }
    }
    $builder_url = admin_url( 'admin.php?page=canvasly-lite' );
    $ver         = defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0';
    wp_register_style( 'canvasly-lite-native-editor', CANVASLY_LITE_URL . 'assets/css/native-editor-launcher.css', array(), $ver );
    wp_enqueue_style( 'canvasly-lite-native-editor' );
    wp_register_script( 'canvasly-lite-native-editor', CANVASLY_LITE_URL . 'assets/js/native-editor-launcher.js', array(), $ver, true );
    wp_enqueue_script( 'canvasly-lite-native-editor' );
    wp_add_inline_script(
        'canvasly-lite-native-editor',
        'window.canvaslyLiteNativeEditor=' . wp_json_encode(
            array(
                'url'    => $builder_url,
                'postId' => $post_id,
                'label'  => __( 'Edit with Canvasly', 'canvasly-lite' ),
            )
        ) . ';',
        'before'
    );
}
if ( ! defined( 'CANVASLY_LITE_VERSION' ) ) {
	define( 'CANVASLY_LITE_VERSION', '0.12.84' );
}
if ( ! defined( 'CANVASLY_LITE_FILE' ) ) {
	define( 'CANVASLY_LITE_FILE', __FILE__ );
}
if ( ! defined( 'CANVASLY_LITE_PATH' ) ) {
	define( 'CANVASLY_LITE_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CANVASLY_LITE_URL' ) ) {
	define( 'CANVASLY_LITE_URL', plugin_dir_url( __FILE__ ) );
}
/**
 * Back development mode: sanitize AI-generated layout code and dynamic tokens
 * before they reach the editor canvas. Optional override in wp-config.php,
 * before `require_once ABSPATH . 'wp-settings.php';`:
 *   if ( ! defined( 'CANVASLY_LITE_DEV_MODE' ) ) {
 *       define( 'CANVASLY_LITE_DEV_MODE', true );
 *   }
 * The plugin never defines this constant (a second define() is a PHP 9 error).
 * When it is unset, DevMode::enabled() turns on under WP_DEBUG or a
 * local/development environment type.
 */
require_once CANVASLY_LITE_PATH . 'includes/bootstrap/class-autoloader.php';
\CanvaslyLite\Bootstrap\Autoloader::register();
$canvasly_lite_controls_file = CANVASLY_LITE_PATH . 'includes/controls/class-controls.php';
if ( is_readable( $canvasly_lite_controls_file ) ) {
	require_once $canvasly_lite_controls_file;
}
\CanvaslyLite\Bootstrap\Plugin::instance();
register_activation_hook( __FILE__, array( '\CanvaslyLite\Bootstrap\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\CanvaslyLite\Bootstrap\Plugin', 'deactivate' ) );
