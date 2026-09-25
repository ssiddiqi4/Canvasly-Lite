<?php
/**
 * Registry passed to `canvasly-lite/icons/register`.
 *
 * Add-ons call register() with an icon array (`id`, `title`, `svg`). The same
 * SVG allow-list as IconLibrary::save() is applied here.
 *
 * @package CanvaslyLite
 */

namespace CanvaslyLite\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IconSets {
	/**
	 * @var array<string,array>
	 */
	private $icons = array();

	/**
	 * @param mixed $icon
	 * @return void
	 */
	public function register( $icon ) {
		$clean = self::sanitize( $icon );
		if ( ! $clean ) {
			return;
		}
		$this->icons[ $clean['id'] ] = $clean;
	}

	/**
	 * @return array<int,array>
	 */
	public function all() {
		return array_values( $this->icons );
	}

	/**
	 * @param mixed $icon
	 * @return array{id:string,title:string,category:string,family:string,svg:string}|null
	 */
	public static function sanitize( $icon ) {
		if ( ! is_array( $icon ) ) {
			return null;
		}
		$id = sanitize_key( (string) ( $icon['id'] ?? '' ) );
		if ( '' === $id ) {
			$id = sanitize_key( sanitize_title( (string) ( $icon['title'] ?? '' ) ) );
		}
		if ( '' === $id ) {
			return null;
		}
		$svg = self::svg( (string) ( $icon['svg'] ?? '' ) );
		if ( '' === $svg || false === stripos( $svg, '<svg' ) ) {
			return null;
		}
		$title = sanitize_text_field( (string) ( $icon['title'] ?? $id ) );
		if ( '' === $title ) {
			$title = $id;
		}
		$category = sanitize_text_field( (string) ( $icon['category'] ?? 'Custom' ) );
		if ( '' === $category ) {
			$category = 'Custom';
		}
		return array(
			'id'       => $id,
			'title'    => $title,
			'category' => $category,
			'family'   => 'custom',
			'svg'      => $svg,
		);
	}

	/**
	 * @param string $svg
	 * @return string
	 */
	private static function svg( $svg ) {
		$svg = str_replace( "\0", '', $svg );
		if ( function_exists( 'wp_kses' ) ) {
			$svg = wp_kses(
				$svg,
				array(
					'svg'  => array(
						'viewBox'      => true,
						'viewbox'      => true,
						'aria-hidden'  => true,
						'role'         => true,
						'xmlns'        => true,
						'width'        => true,
						'height'       => true,
						'class'        => true,
					),
					'path' => array(
						'd'            => true,
						'fill'         => true,
						'stroke'       => true,
						'stroke-width' => true,
						'fill-rule'    => true,
						'clip-rule'    => true,
					),
				)
			);
		}
		return trim( (string) $svg );
	}
}
