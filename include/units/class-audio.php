<?php
namespace CanvaslyLite\Units;

use CanvaslyLite\Embed\OEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Audio extends Unit {
	public function type() {
		return 'audio';
	}
	public function title() {
		return __( 'Audio', 'canvasly-lite' );
	}
	public function icon() {
		return '♫';
	}
	public function category() {
		return 'media';
	}
	public function keywords() {
		return array( 'audio', 'mp3', 'podcast', 'sound', 'oembed', 'spotify' );
	}
	public function defaults() {
		return array( 'url' => '', 'preload' => 'metadata', 'autoplay' => false, 'loop' => false, 'controls' => true );
	}
	public function controls() {
		return array( 'url' => 'url', 'preload' => 'select', 'autoplay' => 'switch', 'loop' => 'switch', 'controls' => 'switch' );
	}
	public static function is_file( $url ) {
		return (bool) preg_match( '/\.(mp3|wav|ogg|oga|opus|m4a|aac|flac|wma)(\?|#|$)/i', (string) $url );
	}
	public function render( $s, $children = '' ) {
		$s   = is_array( $s ) ? $s : array();
		$url = trim( (string) ( $s['url'] ?? '' ) );
		if ( $url === '' ) {
			return '<div class="' . $this->cls( $s ) . ' lb-embed-placeholder">' . esc_html__( 'Add an audio file URL', 'canvasly-lite' ) . '</div>';
		}
		if ( ! self::is_file( $url ) ) {
			$html = class_exists( OEmbed::class ) ? OEmbed::html( $url ) : '';
			if ( $html !== '' ) {
				return '<div class="' . $this->cls( $s ) . ' lb-audio-embed">' . $html . '</div>';
			}
			return '<div class="' . $this->cls( $s ) . ' lb-embed-placeholder">' . esc_html__( 'This audio URL could not be embedded', 'canvasly-lite' ) . '</div>';
		}
		$preload = $s['preload'] ?? 'metadata';
		$preload = in_array( $preload, array( 'none', 'metadata', 'auto' ), true ) ? $preload : 'metadata';
		$a       = ' class="' . $this->cls( $s ) . ' lb-audio" preload="' . esc_attr( $preload ) . '"';
		if ( ! empty( $s['controls'] ) ) {
			$a .= ' controls';
		}
		if ( ! empty( $s['autoplay'] ) ) {
			$a .= ' autoplay';
		}
		if ( ! empty( $s['loop'] ) ) {
			$a .= ' loop';
		}
		return '<audio' . $a . '><source src="' . esc_url( $url ) . '"></audio>';
	}
}
