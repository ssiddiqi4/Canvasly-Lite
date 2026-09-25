<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'lb-safe-theme' ); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
}
wp_footer();
?>
</body>
</html>
