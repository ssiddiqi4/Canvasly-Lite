<?php
/**
 * Coming-soon / maintenance document. Bare HTML with wp_head / wp_footer.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( class_exists( '\CanvaslyLite\Ops\Maintenance' ) ) {
	\CanvaslyLite\Ops\Maintenance::headers();
}
$mode = class_exists( '\CanvaslyLite\Ops\Maintenance' ) ? \CanvaslyLite\Ops\Maintenance::mode() : 'maintenance';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ( $mode === 'maintenance' ) : ?>
		<meta name="robots" content="noindex,nofollow">
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
if ( class_exists( '\CanvaslyLite\Ops\Maintenance' ) ) {
	\CanvaslyLite\Ops\Maintenance::print_content();
}
wp_footer();
?>
</body>
</html>
