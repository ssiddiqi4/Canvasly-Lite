<?php
namespace CanvaslyLite\Dynamic;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One dynamic tag. Add-ons pass an array to Tags::register(); core built-ins do the same.
 *
 *   [
 *     'name'       => 'post_title',
 *     'title'      => 'Post Title',
 *     'group'      => 'post',
 *     'categories' => ['text'],
 *     'controls'   => [ 'format' => ['type'=>'text','label'=>'Format'] ],
 *     'render'     => function ( array $settings, array $context ) { return get_the_title( $context['post_id'] ); },
 *   ]
 */
class Tag {
	protected $name = '';
	protected $title = '';
	protected $group = 'post';
	protected $categories = array();
	protected $controls = array();
	/** @var callable|null */
	protected $render_cb = null;
	/** @var callable|null */
	protected $preview_cb = null;

	public function __construct( array $args = array() ) {
		$name = isset( $args['name'] ) ? (string) $args['name'] : '';
		$this->name = function_exists( 'sanitize_key' ) ? sanitize_key( $name ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', $name ) );
		$this->title = isset( $args['title'] ) && is_string( $args['title'] ) ? $args['title'] : $this->name;
		$group = isset( $args['group'] ) ? (string) $args['group'] : 'post';
		$this->group = function_exists( 'sanitize_key' ) ? sanitize_key( $group ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', $group ) );
		if ( $this->group === '' ) {
			$this->group = 'post';
		}
		$cats = isset( $args['categories'] ) ? (array) $args['categories'] : array( 'text' );
		$allowed = Tags::CATEGORIES;
		$out = array();
		foreach ( $cats as $c ) {
			$c = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $c ) : strtolower( (string) $c );
			if ( in_array( $c, $allowed, true ) ) {
				$out[] = $c;
			}
		}
		$this->categories = $out ? array_values( array_unique( $out ) ) : array( 'text' );
		$this->controls = is_array( $args['controls'] ?? null ) ? $args['controls'] : array();
		$this->render_cb = is_callable( $args['render'] ?? null ) ? $args['render'] : null;
		$this->preview_cb = is_callable( $args['preview'] ?? null ) ? $args['preview'] : null;
	}

	public function name() {
		return $this->name;
	}

	public function title() {
		return $this->title;
	}

	public function group() {
		return $this->group;
	}

	/** @return string[] */
	public function categories() {
		return $this->categories;
	}

	/** Extra settings beyond before/after/fallback. */
	public function controls() {
		return $this->controls;
	}

	/**
	 * @param array $settings Tag settings (before/after/fallback plus tag-specific keys).
	 * @param array $context  Resolver context (post_id, post, for_canvas, …).
	 * @return mixed Scalar, or ['id'=>int,'url'=>string] for image tags.
	 */
	public function render( array $settings, array $context ) {
		if ( ! is_callable( $this->render_cb ) ) {
			return '';
		}
		return call_user_func( $this->render_cb, $settings, $context );
	}

	public function preview( array $settings, array $context ) {
		if ( is_callable( $this->preview_cb ) ) {
			return call_user_func( $this->preview_cb, $settings, $context );
		}
		return $this->render( $settings, $context );
	}

	/** JSON for the editor picker. */
	public function export() {
		$controls = array();
		foreach ( $this->controls as $key => $def ) {
			$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $key ) : strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $key ) );
			if ( $key === '' ) {
				continue;
			}
			if ( is_string( $def ) ) {
				$def = array( 'type' => $def );
			}
			if ( ! is_array( $def ) ) {
				continue;
			}
			$controls[ $key ] = array(
				'type'        => isset( $def['type'] ) ? (string) $def['type'] : 'text',
				'label'       => isset( $def['label'] ) ? (string) $def['label'] : $key,
				'options'     => isset( $def['options'] ) && is_array( $def['options'] ) ? $def['options'] : array(),
				'placeholder' => isset( $def['placeholder'] ) ? (string) $def['placeholder'] : '',
				'default'     => $def['default'] ?? '',
				'description' => isset( $def['description'] ) ? (string) $def['description'] : '',
			);
		}
		return array(
			'name'       => $this->name(),
			'title'      => $this->title(),
			'group'      => $this->group(),
			'categories' => $this->categories(),
			'controls'   => $controls,
		);
	}
}
