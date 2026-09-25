<?php
namespace CanvaslyLite\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Roadmap 7.5 — boot multilingual, SEO, cache, Heartbeat locks and theme support.
 *
 * Safe to call more than once and on native Gutenberg screens (SEO analysis
 * runs there). Each submodule has its own `$booted` guard.
 */
class Compat {
	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		if ( class_exists( ImportExport::class ) ) {
			ImportExport::init();
		}
		if ( class_exists( Duplicate::class ) ) {
			Duplicate::init();
		}
		if ( class_exists( Multilingual::class ) ) {
			Multilingual::init();
		}
		if ( class_exists( Seo::class ) ) {
			Seo::init();
		}
		if ( class_exists( Cache::class ) ) {
			Cache::init();
		}
		if ( class_exists( ThemeSupport::class ) ) {
			ThemeSupport::init();
		}
		if ( class_exists( '\\CanvaslyLite\\Design\\Collaboration' ) ) {
			\CanvaslyLite\Design\Collaboration::init();
		}
	}
}
