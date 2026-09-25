<?php
namespace CanvaslyLite\Settings;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-user editor preferences (Roadmap 3.6).
 */
class UserPreferences {
	const KEY = 'canvasly_lite_user_preferences';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'ui_theme'           => 'auto',
			'panel_width'        => 330,
			'panel_width_left'   => 250,
			'navigator_default'  => 'open',
			'show_handles'       => true,
			'editor_lightbox'    => true,
			'autosave'           => true,
			'autosave_interval'  => 15,
			'tips'               => true,
			'confirm_delete'     => true,
		);
	}

	/**
	 * @param int $user_id
	 * @return array<string,mixed>
	 */
	public static function get( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$stored  = $user_id ? get_user_meta( $user_id, self::KEY, true ) : array();
		$clean   = self::sanitize( is_array( $stored ) ? $stored : array() );
		/**
		 * Filter the current user's editor preferences.
		 *
		 * @param array<string,mixed> $clean
		 * @param int                 $user_id
		 */
		$filtered = apply_filters( 'canvasly-lite/user_preferences', $clean, $user_id );
		return is_array( $filtered ) ? self::sanitize( $filtered ) : $clean;
	}

	/**
	 * @param array<string,mixed> $data
	 * @param int                 $user_id
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function save( $data, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return new \WP_Error( 'forbidden', __( 'You must be logged in to save preferences.', 'canvasly-lite' ), array( 'status' => 403 ) );
		}
		$clean = self::sanitize( is_array( $data ) ? $data : array() );
		update_user_meta( $user_id, self::KEY, $clean );
		return $clean;
	}

	/**
	 * @param mixed $data
	 * @return array<string,mixed>
	 */
	public static function sanitize( $data ) {
		$d    = self::defaults();
		$data = is_array( $data ) ? $data : array();

		$theme = sanitize_key( (string) ( $data['ui_theme'] ?? $d['ui_theme'] ) );
		if ( ! in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ) {
			$theme = $d['ui_theme'];
		}

		$nav = sanitize_key( (string) ( $data['navigator_default'] ?? $d['navigator_default'] ) );
		if ( ! in_array( $nav, array( 'open', 'closed' ), true ) ) {
			$nav = $d['navigator_default'];
		}

		return array(
			'ui_theme'          => $theme,
			'panel_width'       => self::clamp_width( $data['panel_width'] ?? $d['panel_width'], $d['panel_width'] ),
			'panel_width_left'  => self::clamp_width( $data['panel_width_left'] ?? $d['panel_width_left'], $d['panel_width_left'] ),
			'navigator_default' => $nav,
			'show_handles'      => self::to_bool( $data['show_handles'] ?? $d['show_handles'] ),
			'editor_lightbox'   => self::to_bool( $data['editor_lightbox'] ?? $d['editor_lightbox'] ),
			'autosave'          => self::to_bool( $data['autosave'] ?? $d['autosave'] ),
			'autosave_interval' => self::clamp_int( $data['autosave_interval'] ?? $d['autosave_interval'], 5, 60, $d['autosave_interval'] ),
			'tips'              => self::to_bool( $data['tips'] ?? $d['tips'] ),
			'confirm_delete'    => self::to_bool( $data['confirm_delete'] ?? $d['confirm_delete'] ),
		);
	}

	/**
	 * @param mixed $v
	 * @param int   $fallback
	 * @return int
	 */
	public static function clamp_width( $v, $fallback = 330 ) {
		return self::clamp_int( $v, 190, 520, $fallback );
	}

	/**
	 * @param mixed $v
	 * @param int   $min
	 * @param int   $max
	 * @param int   $fallback
	 * @return int
	 */
	public static function clamp_int( $v, $min, $max, $fallback ) {
		if ( $v === '' || $v === null || ! is_numeric( $v ) ) {
			$n = (int) $fallback;
		} else {
			$n = (int) $v;
		}
		if ( $n < $min ) {
			return $min;
		}
		if ( $n > $max ) {
			return $max;
		}
		return $n;
	}

	/**
	 * @param mixed $v
	 * @return bool
	 */
	public static function to_bool( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( is_numeric( $v ) ) {
			return (int) $v !== 0;
		}
		$s = strtolower( trim( (string) $v ) );
		if ( in_array( $s, array( '0', 'false', 'no', 'off', '' ), true ) ) {
			return false;
		}
		return true;
	}
}
