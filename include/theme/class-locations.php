<?php
namespace CanvaslyLite\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme location registry and document display-rule slot (Step 0.4).
 *
 * Add-ons register location ids on `canvasly-lite/theme/locations`.
 * Lite stores that registry and does not print a header, footer, or
 * other theme template. Kit and template export rows gain a `locations`
 * key only when `canvasly-lite/document/locations` returns rules.
 */
class Locations {
	/** @var self|null */
	private static $instance = null;

	/** @var bool */
	private static $hooked = false;

	/** @var bool */
	private $fired = false;

	/** @var array<string,array> */
	private $locations = array();

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Fire the registry action once on `wp`, after the main query exists.
	 */
	public static function init() {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		if ( function_exists( 'did_action' ) && did_action( 'wp' ) ) {
			self::instance()->fire();
			return;
		}
		add_action( 'wp', array( self::class, 'on_wp' ), 1 );
	}

	public static function on_wp() {
		self::instance()->fire();
	}

	/**
	 * @return void
	 */
	public function fire() {
		if ( $this->fired ) {
			return;
		}
		$this->fired = true;
		/**
		 * Register theme locations. Lite does not render them.
		 *
		 * @param Locations $locations
		 */
		do_action( 'canvasly-lite/theme/locations', $this );
	}

	/**
	 * @param string $id   Location id (`header`, `footer`, …).
	 * @param array  $args Optional label and flags. Stored, not printed.
	 * @return bool
	 */
	public function register( $id, $args = array() ) {
		$id = sanitize_key( (string) $id );
		if ( '' === $id ) {
			return false;
		}
		$args                   = is_array( $args ) ? $args : array();
		$args['id']             = $id;
		$this->locations[ $id ] = $args;
		return true;
	}

	/**
	 * @param string $id
	 */
	public function unregister( $id ) {
		unset( $this->locations[ sanitize_key( (string) $id ) ] );
	}

	/**
	 * @param string $id
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->locations[ sanitize_key( (string) $id ) ] );
	}

	/**
	 * @param string $id
	 * @return array
	 */
	public function get( $id ) {
		$id = sanitize_key( (string) $id );
		return isset( $this->locations[ $id ] ) ? $this->locations[ $id ] : array();
	}

	/**
	 * @return array<string,array>
	 */
	public function all() {
		return $this->locations;
	}

	/**
	 * Display rules attached to a document. Empty unless an add-on returns some.
	 *
	 * @param int   $post_id
	 * @param array $document
	 * @return array
	 */
	public static function for_document( $post_id, $document = array() ) {
		$post_id  = absint( $post_id );
		$document = is_array( $document ) ? $document : array();
		$filtered = apply_filters( 'canvasly-lite/document/locations', array(), $post_id, $document );
		return is_array( $filtered ) ? $filtered : array();
	}

	/**
	 * Copy export rows through unchanged when no location rules are attached.
	 *
	 * @param array $row
	 * @param int   $post_id
	 * @param array $document
	 * @return array
	 */
	public static function with_export_locations( array $row, $post_id, $document = array() ) {
		$locations = self::for_document( $post_id, $document );
		if ( $locations ) {
			$row['locations'] = $locations;
		}
		return $row;
	}

	public static function reset_for_tests() {
		self::$instance = null;
		self::$hooked   = false;
	}
}
