<?php
/**
 * Plugin Name: Canvasly Safe Mode
 * Description: Restricts other plugins and the theme while a Canvasly Safe Mode session is active. Managed by Canvasly — do not edit.
 * Version: 1.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$canvasly_lite_safe_boot = get_option( 'canvasly_lite_safe_mode_boot', array() );
if ( ! is_array( $canvasly_lite_safe_boot ) || empty( $canvasly_lite_safe_boot['loader'] ) || ! is_readable( $canvasly_lite_safe_boot['loader'] ) ) {
	return;
}
require_once $canvasly_lite_safe_boot['loader'];
if ( function_exists( 'canvasly_lite_safe_mode_start' ) ) {
	canvasly_lite_safe_mode_start();
}
