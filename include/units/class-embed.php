<?php
namespace CanvaslyLite\Units;

use CanvaslyLite\Embed\OEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generic oEmbed: paste any allow-listed URL (Twitter, Spotify, TED, …).
 * YouTube/Vimeo/self-hosted video stay on the Video widget; files stay on Audio.
 */
class Embed extends Unit {
	public function type() {
		return 'embed';
	}
	public function title() {
		return __( 'Embed', 'canvasly-lite' );
	}
	public function icon() {
		return '⧉';
	}
	public function category() {
		return 'media';
	}
	public function keywords() {
		return array( 'embed', 'oembed', 'url', 'iframe', 'twitter', 'spotify', 'tiktok', 'ted' );
	}
	public function defaults() {
		return array(
			'url'          => '',
			'aspect_ratio' => '',
			'max_width'    => '',
		);
	}
	public function controls() {
		$emb = __( 'Embed', 'canvasly-lite' );
		$ratios = array(
			''     => __( 'Auto', 'canvasly-lite' ),
			'16:9' => '16:9',
			'21:9' => '21:9',
			'4:3'  => '4:3',
			'1:1'  => '1:1',
			'9:16' => '9:16',
		);
		return array(
			'url'          => $this->ctrl( 'url', __( 'URL', 'canvasly-lite' ), 'content', $emb, array( 'dynamic' => true ) ),
			'aspect_ratio' => $this->ctrl( 'select', __( 'Aspect Ratio', 'canvasly-lite' ), 'style', $emb, array( 'options' => $ratios ) ),
			'max_width'    => $this->ctrl( 'slider', __( 'Max Width', 'canvasly-lite' ), 'style', $emb, array(
				'units'     => array( 'px', '%', 'vw' ),
				'range'     => array( 'min' => 0, 'max' => 1200 ),
				'selectors' => array( '{{WRAPPER}} .lb-embed' => 'max-width: {{VALUE}};' ),
			) ),
		);
	}
	public function render( $s, $children = '' ) {
		$s   = is_array( $s ) ? $s : array();
		$url = trim( (string) ( $s['url'] ?? '' ) );
		if ( $url === '' ) {
			return '<div class="' . $this->cls( $s ) . ' lb-embed-placeholder">' . esc_html__( 'Paste a URL to embed', 'canvasly-lite' ) . '</div>';
		}
		$html = class_exists( OEmbed::class ) ? OEmbed::html( $url ) : '';
		if ( $html === '' ) {
			return '<div class="' . $this->cls( $s ) . ' lb-embed-placeholder">' . esc_html__( 'This URL could not be embedded', 'canvasly-lite' ) . '</div>';
		}
		$ratio = self::ratio_value( $s['aspect_ratio'] ?? '' );
		$max   = $this->unit( $s['max_width'] ?? '' );
		$inner = class_exists( OEmbed::class ) ? OEmbed::wrap( $html, 'lb-embed', array( 'ratio' => $ratio, 'max_width' => $max ) ) : $html;
		return '<div class="' . $this->cls( $s ) . ' lb-embed-wrap">' . $inner . '</div>';
	}
	public static function ratio_value( $v ) {
		$map = array( '16:9' => '16 / 9', '21:9' => '21 / 9', '4:3' => '4 / 3', '1:1' => '1 / 1', '9:16' => '9 / 16' );
		$v   = (string) $v;
		return $map[ $v ] ?? '';
	}
}
