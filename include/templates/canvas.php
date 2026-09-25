<?php
/**
 * Canvas page template.
 *
 * When the active theme has a header and a footer, this template inherits them.
 * Otherwise it is a blank document (wp_head / wp_footer only) and the editor
 * provides Header and Footer areas.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$canvasly_lite_inherit_theme = class_exists( '\CanvaslyLite\Templates\ThemeChrome' ) && \CanvaslyLite\Templates\ThemeChrome::provides();

if ( $canvasly_lite_inherit_theme && \CanvaslyLite\Templates\ThemeChrome::is_block_theme() ) {
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php
	if ( function_exists( 'wp_body_open' ) ) {
		wp_body_open();
	}
	if ( ! ( class_exists( '\CanvaslyLite\Templates\ThemeChromeEdits' ) && \CanvaslyLite\Templates\ThemeChromeEdits::echo_part( 'header' ) ) && function_exists( 'block_header_area' ) ) {
		block_header_area();
	}
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	if ( ! ( class_exists( '\CanvaslyLite\Templates\ThemeChromeEdits' ) && \CanvaslyLite\Templates\ThemeChromeEdits::echo_part( 'footer' ) ) && function_exists( 'block_footer_area' ) ) {
		block_footer_area();
	}
	wp_footer();
	?>
</body>
</html>
	<?php
	return;
}

if ( $canvasly_lite_inherit_theme ) {
	$canvasly_lite_saved_header = class_exists( '\CanvaslyLite\Templates\ThemeChromeEdits' ) && \CanvaslyLite\Templates\ThemeChromeEdits::has( 'header' );
	$canvasly_lite_saved_footer = class_exists( '\CanvaslyLite\Templates\ThemeChromeEdits' ) && \CanvaslyLite\Templates\ThemeChromeEdits::has( 'footer' );
	if ( ! $canvasly_lite_saved_header && ! $canvasly_lite_saved_footer ) {
		get_header();
		while ( have_posts() ) {
			the_post();
			the_content();
		}
		get_footer();
		return;
	}
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php
	if ( function_exists( 'wp_body_open' ) ) {
		wp_body_open();
	}
	if ( ! \CanvaslyLite\Templates\ThemeChromeEdits::echo_part( 'header' ) ) {
		echo \CanvaslyLite\Templates\ThemeChrome::markup( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fragment() strips active content.
	}
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	if ( ! \CanvaslyLite\Templates\ThemeChromeEdits::echo_part( 'footer' ) ) {
		echo \CanvaslyLite\Templates\ThemeChrome::markup( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fragment() strips active content.
	}
	wp_footer();
	?>
</body>
</html>
	<?php
	return;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
while ( have_posts() ) {
	the_post();
	the_content();
}
wp_footer();
?>
</body>
</html>
