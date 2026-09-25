<?php
namespace CanvaslyLite\Bootstrap;
if ( ! defined( 'ABSPATH' ) ) exit;
class Autoloader {
 public static function register() { spl_autoload_register( array( __CLASS__, 'load' ) ); }
 public static function load( $class ) {
  $prefix = 'CanvaslyLite\\';
  if ( strpos( $class, $prefix ) !== 0 ) return;
  $relative = substr( $class, strlen( $prefix ) );
  $parts = explode( '\\', $relative );
  $name = array_pop( $parts );
  $dir = strtolower( implode( '/', $parts ) );
  $root = defined( 'CANVASLY_LITE_PATH' ) ? CANVASLY_LITE_PATH : dirname( __DIR__, 2 ) . '/';
  $base = $root . 'includes/' . ( $dir !== '' ? $dir . '/' : '' );
  $slugs = array(
   strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $name ) ),
   strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $name ) ),
   strtolower( $name ),
  );
  foreach ( array_unique( $slugs ) as $slug ) {
   $file = $base . 'class-' . $slug . '.php';
   if ( is_readable( $file ) ) {
    require_once $file;
    return;
   }
  }
 }
}
