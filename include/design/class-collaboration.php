<?php
namespace CanvaslyLite\Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Document editing lock via WordPress `_edit_lock` and Heartbeat.
 *
 * Replaces the custom `_lb_lock_{id}` meta used before Roadmap 7.5. The REST
 * `/lock/{id}` endpoint and editor Lock button keep the same JSON shape:
 * `{locked, user, name, time, lock}`.
 */
class Collaboration {
	const META   = '_edit_lock';
	const LEGACY = '_lb_lock_';
	const WINDOW = 150;

	private static $booted = false;

	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_filter( 'heartbeat_received', array( self::class, 'on_heartbeat' ), 11, 2 );
		add_filter( 'heartbeat_nopriv_received', array( self::class, 'on_heartbeat' ), 11, 2 );
	}

	/**
	 * Seconds a lock stays valid. Matches `wp_check_post_lock_window` (150).
	 *
	 * @return int
	 */
	public static function window() {
		$n = self::WINDOW;
		if ( function_exists( 'apply_filters' ) ) {
			$n = (int) apply_filters( 'wp_check_post_lock_window', $n ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core post-lock window.
		}
		return max( 30, $n );
	}

	/**
	 * User id holding the lock if it is someone else, else 0.
	 *
	 * @param int $post_id
	 * @return int
	 */
	public static function check( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return 0;
		}
		if ( function_exists( 'wp_check_post_lock' ) ) {
			$other = wp_check_post_lock( $post_id );
			return $other ? absint( $other ) : 0;
		}
		$parsed = self::parse( self::read( $post_id ) );
		if ( ! $parsed ) {
			$parsed = self::legacy( $post_id );
		}
		if ( ! $parsed ) {
			return 0;
		}
		if ( ( time() - (int) $parsed['time'] ) >= self::window() ) {
			return 0;
		}
		$me = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return ( (int) $parsed['user'] !== $me ) ? (int) $parsed['user'] : 0;
	}

	/**
	 * @param int $post_id
	 * @return array{locked:bool,user:int,name:string,time:int,lock:string}
	 */
	public static function status( $post_id ) {
		$post_id = absint( $post_id );
		$other   = self::check( $post_id );
		$parsed  = self::parse( self::read( $post_id ) );
		if ( ! $parsed ) {
			$parsed = self::legacy( $post_id );
		}
		$me   = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$name = '';
		$user = $other ? $other : ( $parsed ? (int) $parsed['user'] : $me );
		if ( $user && function_exists( 'get_userdata' ) ) {
			$obj = get_userdata( $user );
			if ( $obj && ! empty( $obj->display_name ) ) {
				$name = sanitize_text_field( (string) $obj->display_name );
			}
		}
		if ( $name === '' && $parsed && ! empty( $parsed['name'] ) ) {
			$name = sanitize_text_field( (string) $parsed['name'] );
		}
		return array(
			'locked' => $other > 0,
			'user'   => $user,
			'name'   => $name,
			'time'   => $parsed ? (int) $parsed['time'] : 0,
			'lock'   => self::read( $post_id ),
		);
	}

	/**
	 * Take (or refresh) the lock for the current user.
	 *
	 * @param int  $post_id
	 * @param bool $force   Take over another user's lock.
	 * @return array|false Status array, or false when another user holds it.
	 */
	public static function acquire( $post_id, $force = false ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}
		self::drop_legacy( $post_id );
		if ( ! $force && self::check( $post_id ) ) {
			return false;
		}
		if ( function_exists( 'wp_set_post_lock' ) ) {
			wp_set_post_lock( $post_id );
		} else {
			self::write( $post_id );
		}
		return self::status( $post_id );
	}

	/**
	 * Refresh the lock when free; return status otherwise.
	 *
	 * @param int  $post_id
	 * @param bool $force
	 * @return array
	 */
	public static function heartbeat( $post_id, $force = false ) {
		$s = self::status( $post_id );
		if ( empty( $s['locked'] ) || $force ) {
			$got = self::acquire( $post_id, $force );
			if ( is_array( $got ) ) {
				return $got;
			}
		}
		return self::status( $post_id );
	}

	/**
	 * Heartbeat payload `canvasly-lite-lock`: `{post_id, takeover}`.
	 *
	 * @param array $response
	 * @param array $data
	 * @return array
	 */
	public static function on_heartbeat( $response, $data ) {
		$response = is_array( $response ) ? $response : array();
		$data     = is_array( $data ) ? $data : array();
		if ( empty( $data['canvasly-lite-lock'] ) || ! is_array( $data['canvasly-lite-lock'] ) ) {
			return $response;
		}
		$post_id  = absint( $data['canvasly-lite-lock']['post_id'] ?? 0 );
		$takeover = ! empty( $data['canvasly-lite-lock']['takeover'] );
		if ( ! $post_id ) {
			return $response;
		}
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return $response;
		}
		$response['canvasly-lite-lock'] = self::heartbeat( $post_id, $takeover );
		return $response;
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	public static function token( $post_id ) {
		return self::read( absint( $post_id ) );
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	private static function read( $post_id ) {
		$v = get_post_meta( $post_id, self::META, true );
		return is_string( $v ) ? $v : '';
	}

	/**
	 * @param int $post_id
	 * @return array{time:int,user:int}|false
	 */
	private static function write( $post_id ) {
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( ! $user ) {
			return false;
		}
		$now = time();
		update_post_meta( $post_id, self::META, $now . ':' . $user );
		return array(
			'time' => $now,
			'user' => $user,
		);
	}

	/**
	 * @param string $raw
	 * @return array{time:int,user:int,name?:string}|null
	 */
	public static function parse( $raw ) {
		if ( is_array( $raw ) ) {
			$user = absint( $raw['user'] ?? 0 );
			$time = absint( $raw['time'] ?? 0 );
			if ( ! $user || ! $time ) {
				return null;
			}
			$out = array(
				'time' => $time,
				'user' => $user,
			);
			if ( ! empty( $raw['name'] ) ) {
				$out['name'] = sanitize_text_field( (string) $raw['name'] );
			}
			return $out;
		}
		$s = trim( (string) $raw );
		if ( $s === '' || strpos( $s, ':' ) === false ) {
			return null;
		}
		$parts = explode( ':', $s, 2 );
		$time  = absint( $parts[0] );
		$user  = absint( $parts[1] ?? 0 );
		if ( ! $time || ! $user ) {
			return null;
		}
		return array(
			'time' => $time,
			'user' => $user,
		);
	}

	/**
	 * Read a still-valid legacy `_lb_lock_{id}` and migrate it to `_edit_lock`.
	 *
	 * @param int $post_id
	 * @return array{time:int,user:int,name?:string}|null
	 */
	private static function legacy( $post_id ) {
		$key = self::LEGACY . $post_id;
		$old = get_post_meta( $post_id, $key, true );
		$p   = self::parse( $old );
		if ( ! $p ) {
			return null;
		}
		if ( ( time() - (int) $p['time'] ) >= self::window() ) {
			delete_post_meta( $post_id, $key );
			return null;
		}
		update_post_meta( $post_id, self::META, (int) $p['time'] . ':' . (int) $p['user'] );
		delete_post_meta( $post_id, $key );
		return $p;
	}

	/**
	 * @param int $post_id
	 */
	public static function drop_legacy( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}
		delete_post_meta( $post_id, self::LEGACY . $post_id );
	}
}
