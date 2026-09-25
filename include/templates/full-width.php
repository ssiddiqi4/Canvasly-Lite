<?php
/**
 * Full Width page template: theme header and footer, no theme content container.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
while ( have_posts() ) {
	the_post();
	the_content();
}
get_footer();
