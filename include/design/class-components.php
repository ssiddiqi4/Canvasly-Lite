<?php
namespace CanvaslyLite\Design;

use CanvaslyLite\Utils\JsonCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Components {
	private static function query_flags( array $args ) {
		$args['no_found_rows']          = true;
		$args['update_post_term_cache'] = false;
		$args['lazy_load_term_meta']    = false;
		if ( ! isset( $args['update_post_meta_cache'] ) ) {
			$args['update_post_meta_cache'] = true;
		}
		return $args;
	}

	private static function hydrate( $p ) {
		if ( ! is_object( $p ) || empty( $p->ID ) ) {
			return null;
		}
		$id  = absint( $p->ID );
		$raw = get_post_meta( $id, '_lb_component_data', true );
		$d   = class_exists( JsonCache::class ) ? JsonCache::decode( $raw, array() ) : ( is_string( $raw ) ? json_decode( $raw, true ) : $raw );
		$key = (string) get_post_meta( $id, '_lb_component_key', true );
		return array(
			'id'       => $id,
			'title'    => $p->post_title,
			'key'      => $key !== '' ? $key : self::key_for_title( $p->post_title ),
			'document' => is_array( $d ) ? $d : array(),
			'version'  => (int) get_post_meta( $id, '_lb_component_version', true ),
			'exposed'  => self::normalize_exposed( get_post_meta( $id, '_lb_component_exposed', true ) ),
		);
	}

	private static function normalize_exposed( $items ) {
		$out = array();
		foreach ( (array) $items as $x ) {
			if ( is_string( $x ) ) {
				$name = sanitize_key( $x );
				if ( $name ) {
					$out[] = array( 'name' => $name, 'path' => '', 'setting' => $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'text' );
				}
				continue;
			}
			if ( ! is_array( $x ) ) {
				continue;
			}
			$name = sanitize_key( $x['name'] ?? $x['key'] ?? '' );
			if ( ! $name ) {
				continue;
			}
			$out[] = array(
				'name'    => $name,
				'path'    => sanitize_text_field( $x['path'] ?? '' ),
				'setting' => sanitize_key( $x['setting'] ?? $x['key'] ?? '' ),
				'label'   => sanitize_text_field( $x['label'] ?? ucwords( str_replace( '_', ' ', $name ) ) ),
				'type'    => sanitize_key( $x['type'] ?? 'text' ),
			);
		}
		return $out;
	}

	public static function key_for_title( $title ) {
		return sanitize_title( $title );
	}

	public static function find_by_key( $key ) {
		$key = sanitize_key( $key );
		if ( ! $key || ! class_exists( '\WP_Query' ) ) {
			return null;
		}
		$q = new \WP_Query(
			self::query_flags(
				array(
					'post_type'      => 'lb_component',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Lookup of a single published component by its unique key.
					'meta_query'     => array(
						array(
							'key'   => '_lb_component_key',
							'value' => $key,
						),
					),
				)
			)
		);
		if ( empty( $q->posts ) ) {
			return null;
		}
		return self::hydrate( $q->posts[0] );
	}

	public static function all() {
		if ( ! class_exists( '\WP_Query' ) ) {
			return array();
		}
		$q   = new \WP_Query(
			self::query_flags(
				array(
					'post_type'      => 'lb_component',
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			)
		);
		$out = array();
		foreach ( (array) $q->posts as $p ) {
			$row = self::hydrate( $p );
			if ( $row ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	public static function get( $id ) {
		$id = absint( $id );
		if ( ! $id || ! function_exists( 'get_post' ) ) {
			return null;
		}
		$p = get_post( $id );
		if ( ! $p || ( $p->post_type ?? '' ) !== 'lb_component' || ( $p->post_status ?? '' ) !== 'publish' ) {
			return null;
		}
		return self::hydrate( $p );
	}

	public static function save( $title, $document, $exposed = array(), $id = 0, $key = '' ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot manage components.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$title = sanitize_text_field( $title );
		if ( ! $title ) {
			return new \WP_Error( 'invalid', __( 'Component title required.', 'canvasly-lite' ), array( 'status' => 400 ) );
		}
		$id  = absint( $id );
		$key = sanitize_key( $key ?: self::key_for_title( $title ) );
		if ( ! $id ) {
			$existing = self::find_by_key( $key );
			if ( $existing ) {
				$id = (int) $existing['id'];
			}
		}
		if ( $id && get_post_type( $id ) === 'lb_component' ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
		} else {
			$id = wp_insert_post( array( 'post_type' => 'lb_component', 'post_status' => 'publish', 'post_title' => $title ) );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		}
		$version = (int) get_post_meta( $id, '_lb_component_version', true ) + 1;
		update_post_meta( $id, '_lb_component_key', $key );
		update_post_meta( $id, '_lb_component_data', wp_json_encode( $document ) );
		update_post_meta( $id, '_lb_component_exposed', self::normalize_exposed( $exposed ) );
		update_post_meta( $id, '_lb_component_version', $version );
		return $id;
	}

	public static function update_propagation_marker( $id ) {
		$c = self::get( $id );
		if ( ! $c ) {
			return false;
		}
		update_post_meta( $id, '_lb_component_propagated_at', current_time( 'mysql' ) );
		return true;
	}

	public static function delete( $id ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return false;
		}
		return (bool) wp_delete_post( absint( $id ), true );
	}

	public static function duplicate( $id ) {
		$c = self::get( $id );
		if ( ! $c ) {
			return 0;
		}
		return self::save( $c['title'] . ' Copy', $c['document'], $c['exposed'] );
	}

	public static function apply_overrides($document,$overrides,$exposed=[]){$d=$document;$map=[];foreach(self::normalize_exposed($exposed) as $x)$map[$x['name']]=$x;$apply=function(&$nodes)use(&$apply,$overrides,$map){foreach($nodes as &$node){$path='';if(!empty($node['_lb_component_path']))$path=$node['_lb_component_path'];if(!empty($node['children']))$apply($node['children']);}unset($node);};
  // New exposed mapping is resolved against paths from the saved component document.
  foreach($map as $name=>$x){if(!array_key_exists($name,(array)$overrides)||empty($x['path'])||empty($x['setting']))continue;$parts=array_values(array_filter(explode('/',$x['path']),'strlen'));$ref=&$d['root'];$node=null;foreach($parts as $part){$idx=(int)$part;if(!isset($ref[$idx])){$node=null;break;}$node=&$ref[$idx];$ref=&$node['children'];}if(is_array($node))$node['settings'][$x['setting']]=is_array($overrides[$name])?$overrides[$name]:sanitize_text_field((string)$overrides[$name]);}
  // Backward-compatible path => settings overrides.
  foreach((array)$overrides as $path=>$vals){if(isset($map[$path]))continue;$parts=array_values(array_filter(explode('/',$path),'strlen'));if(!$parts)continue;$ref=&$d['root'];$node=null;foreach($parts as $part){$idx=(int)$part;if(!isset($ref[$idx])){$node=null;break;}$node=&$ref[$idx];$ref=&$node['children'];}if(is_array($node)&&is_array($vals))foreach($vals as $k=>$v){$k=sanitize_key($k);if($k)$node['settings'][$k]=$v;}}
  return $d;
 }
}
