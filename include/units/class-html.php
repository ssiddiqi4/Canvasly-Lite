<?php
namespace CanvaslyLite\Units;

use CanvaslyLite\Embed\OEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Html extends Unit {
	public function type() {
		return 'html';
	}
	public function title() {
		return __( 'HTML', 'canvasly-lite' );
	}
	public function icon() {
		return '<>';
	}
	public function category() {
		return 'basic';
	}
	public function defaults() {
		return array( 'html' => '' );
	}
	public function controls() {
		return array( 'html' => 'code' );
	}
	public function render( $s, $children = '' ) {
		$raw  = (string) ( $s['html'] ?? '' );
		$trim = trim( $raw );
		if ( $trim !== '' && class_exists( OEmbed::class ) && OEmbed::is_url( $trim ) ) {
			$html = OEmbed::html( $trim );
			if ( $html !== '' ) {
				return '<div class="' . $this->cls( $s ) . ' lb-html lb-html-embed">' . $html . '</div>';
			}
		}
		return '<div class="' . $this->cls( $s ) . ' lb-html">' . wp_kses_post( $raw ) . '</div>';
	}
}
