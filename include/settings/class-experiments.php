<?php
namespace CanvaslyLite\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature experiments (Roadmap 7.1): alpha / beta / stable flags with per-feature toggles.
 */
class Experiments {
	const ACTIVE   = 'active';
	const INACTIVE = 'inactive';
	const ALPHA    = 'alpha';
	const BETA     = 'beta';
	const STABLE   = 'stable';

	/**
	 * Built-in catalog. `default` is the state when the site has not toggled the feature.
	 *
	 * @return array<string,array{title:string,description:string,status:string,default:string}>
	 */
	public static function builtins() {
		return array(
			'nested_units'   => array(
				'title'       => __( 'Nested Tabs, Accordion and Toggle', 'canvasly-lite' ),
				'description' => __( 'Panels that accept any child unit. Legacy text-only widgets stay available.', 'canvasly-lite' ),
				'status'      => self::STABLE,
				'default'     => self::ACTIVE,
			),
			'collection_loop'   => array(
				'title'       => __( 'Collection Loop', 'canvasly-lite' ),
				'description' => __( 'Query builder for posts, custom types and terms with item templates and pagination.', 'canvasly-lite' ),
				'status'      => self::STABLE,
				'default'     => self::ACTIVE,
			),
			'interactions_v2'   => array(
				'title'       => __( 'Interactions 2.0', 'canvasly-lite' ),
				'description' => __( 'Entrance and exit presets, custom keyframes, and scroll-progress triggers.', 'canvasly-lite' ),
				'status'      => self::STABLE,
				'default'     => self::ACTIVE,
			),
			'code_editor'       => array(
				'title'       => __( 'Code editor control', 'canvasly-lite' ),
				'description' => __( 'CodeMirror via WordPress for HTML, CSS, JavaScript and Custom CSS.', 'canvasly-lite' ),
				'status'      => self::STABLE,
				'default'     => self::ACTIVE,
			),
			'grid_container'    => array(
				'title'       => __( 'Grid container', 'canvasly-lite' ),
				'description' => __( 'CSS Grid layout with tracks, gaps, auto-flow and per-child placement.', 'canvasly-lite' ),
				'status'      => self::STABLE,
				'default'     => self::ACTIVE,
			),
			'kit_export'        => array(
				'title'       => __( 'Site kit export and import', 'canvasly-lite' ),
				'description' => __( 'ZIP of site settings, design tokens, templates and optional content with media.', 'canvasly-lite' ),
				'status'      => self::STABLE,
				'default'     => self::ACTIVE,
			),
			'form_recaptcha'    => array(
				'title'       => __( 'Form reCAPTCHA', 'canvasly-lite' ),
				'description' => __( 'Verify Form submissions with Google reCAPTCHA v2 or v3 when keys are set under Integrations.', 'canvasly-lite' ),
				'status'      => self::BETA,
				'default'     => self::INACTIVE,
			),
			'google_maps_embed' => array(
				'title'       => __( 'Google Maps Embed API', 'canvasly-lite' ),
				'description' => __( 'Use the Maps Embed API (requires an API key) instead of the public iframe embed.', 'canvasly-lite' ),
				'status'      => self::BETA,
				'default'     => self::ACTIVE,
			),
			'atomic_classes'    => array(
				'title'       => __( 'Atomic utility classes', 'canvasly-lite' ),
				'description' => __( 'Per-unit utility class generation for spacing and display.', 'canvasly-lite' ),
				'status'      => self::BETA,
				'default'     => self::INACTIVE,
			),
			'editor_top_bar'    => array(
				'title'       => __( 'Editor top bar', 'canvasly-lite' ),
				'description' => __( 'Compact editor chrome. Incomplete; leave inactive unless you are testing.', 'canvasly-lite' ),
				'status'      => self::ALPHA,
				'default'     => self::INACTIVE,
			),
		);
	}

	/**
	 * @return array<string,array{id:string,title:string,description:string,status:string,default:string}>
	 */
	public static function catalog() {
		$out = array();
		foreach ( self::builtins() as $id => $item ) {
			$id = sanitize_key( $id );
			if ( $id === '' ) {
				continue;
			}
			$out[ $id ] = self::normalize_item( $id, $item );
		}
		/**
		 * Register extra experiments. Return the full catalog (id => item).
		 *
		 * @param array $out
		 */
		$filtered = apply_filters( 'canvasly-lite/experiments/register', $out );
		if ( ! is_array( $filtered ) ) {
			return $out;
		}
		$clean = array();
		foreach ( $filtered as $id => $item ) {
			$id = sanitize_key( is_string( $id ) ? $id : ( is_array( $item ) ? ( $item['id'] ?? '' ) : '' ) );
			if ( $id === '' ) {
				continue;
			}
			$clean[ $id ] = self::normalize_item( $id, is_array( $item ) ? $item : array() );
		}
		return $clean;
	}

	/**
	 * @param string $id
	 * @param array  $item
	 * @return array{id:string,title:string,description:string,status:string,default:string}
	 */
	public static function normalize_item( $id, $item ) {
		$item   = is_array( $item ) ? $item : array();
		$status = self::sanitize_status( $item['status'] ?? self::BETA );
		$def    = self::sanitize_state( $item['default'] ?? ( $status === self::STABLE ? self::ACTIVE : self::INACTIVE ) );
		return array(
			'id'          => sanitize_key( $id ),
			'title'       => sanitize_text_field( (string) ( $item['title'] ?? $id ) ),
			'description' => sanitize_text_field( (string) ( $item['description'] ?? '' ) ),
			'status'      => $status,
			'default'     => $def,
		);
	}

	/**
	 * Stored id => active|inactive map.
	 *
	 * @param mixed $raw
	 * @return array<string,string>
	 */
	public static function sanitize( $raw ) {
		$catalog = self::catalog();
		$raw     = is_array( $raw ) ? $raw : array();
		$out     = array();
		foreach ( $catalog as $id => $item ) {
			if ( ! array_key_exists( $id, $raw ) ) {
				continue;
			}
			$out[ $id ] = self::sanitize_state( $raw[ $id ] );
		}
		return $out;
	}

	/**
	 * Catalog rows with the current state filled in.
	 *
	 * @param array<string,string> $stored
	 * @return array<int,array{id:string,title:string,description:string,status:string,default:string,state:string}>
	 */
	public static function all( $stored = null ) {
		if ( ! is_array( $stored ) ) {
			$stored = self::stored();
		}
		$out = array();
		foreach ( self::catalog() as $id => $item ) {
			$state   = isset( $stored[ $id ] ) ? self::sanitize_state( $stored[ $id ] ) : $item['default'];
			$out[]   = array_merge( $item, array( 'state' => $state ) );
		}
		return $out;
	}

	/**
	 * @return array<string,string>
	 */
	public static function stored() {
		$key = class_exists( GlobalSettings::class ) ? GlobalSettings::KEY : 'canvasly_lite_global_settings';
		$g   = (array) get_option( $key, array() );
		$ex  = $g['experiments'] ?? array();
		return is_array( $ex ) ? $ex : array();
	}

	/**
	 * @param string $id
	 * @return bool
	 */
	public static function is_active( $id ) {
		$id      = sanitize_key( (string) $id );
		$catalog = self::catalog();
		if ( $id === '' || ! isset( $catalog[ $id ] ) ) {
			return false;
		}
		$stored = self::stored();
		$state  = isset( $stored[ $id ] ) ? self::sanitize_state( $stored[ $id ] ) : $catalog[ $id ]['default'];
		$on     = $state === self::ACTIVE;
		/**
		 * Filter whether an experiment is active.
		 *
		 * @param bool   $on
		 * @param string $id
		 */
		$filtered = apply_filters( 'canvasly-lite/experiments/active', $on, $id );
		return (bool) $filtered;
	}

	/**
	 * @param mixed $state
	 * @return string
	 */
	public static function sanitize_state( $state ) {
		$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $state ) : strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $state ) );
		if ( in_array( $key, array( '1', 'true', 'on', 'yes', 'enabled' ), true ) ) {
			return self::ACTIVE;
		}
		if ( in_array( $key, array( '0', 'false', 'off', 'no', 'disabled' ), true ) ) {
			return self::INACTIVE;
		}
		return $key === self::ACTIVE ? self::ACTIVE : self::INACTIVE;
	}

	/**
	 * @param mixed $status
	 * @return string
	 */
	public static function sanitize_status( $status ) {
		$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $status ) : strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $status ) );
		if ( in_array( $key, array( self::ALPHA, self::BETA, self::STABLE ), true ) ) {
			return $key;
		}
		return self::BETA;
	}

	/**
	 * @param string $status
	 * @return string
	 */
	public static function status_label( $status ) {
		$map = array(
			self::ALPHA  => __( 'Alpha', 'canvasly-lite' ),
			self::BETA   => __( 'Beta', 'canvasly-lite' ),
			self::STABLE => __( 'Stable', 'canvasly-lite' ),
		);
		$key = self::sanitize_status( $status );
		return $map[ $key ] ?? $map[ self::BETA ];
	}
}
