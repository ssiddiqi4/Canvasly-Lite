<?php
namespace CanvaslyLite\Ops;

use CanvaslyLite\Design\Kit;
use CanvaslyLite\Settings\AdminSettings;
use CanvaslyLite\Settings\GlobalSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snapshot the current plugin as a ZIP and restore a previous version (Roadmap 7.3).
 *
 * Archives live in uploads/canvasly-lite/rollback. A snapshot is taken automatically
 * before a WordPress plugin update of Canvasly, and can be taken manually.
 */
class Rollback {
	const NOTICE = 'canvasly_lite_rollback_notice';

	public static function init() {
		add_action( 'canvasly-lite/rest/register_routes', array( self::class, 'routes' ) );
		add_filter( 'upgrader_pre_install', array( self::class, 'on_pre_install' ), 10, 2 );
		if ( ! function_exists( 'is_admin' ) || is_admin() ) {
			add_action( 'admin_post_lb_rollback', array( self::class, 'handle_restore' ) );
			add_action( 'admin_post_lb_rollback_snapshot', array( self::class, 'handle_snapshot' ) );
			add_action( 'admin_notices', array( self::class, 'admin_notice' ) );
			add_action( 'canvasly-lite/tools/screen', array( self::class, 'tools_screen' ), 23 );
		}
	}

	public static function can_manage() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * @return string
	 */
	public static function dir() {
		$dir = class_exists( AdminSettings::class ) ? AdminSettings::rollback_dir() : '';
		if ( $dir === '' && function_exists( 'wp_upload_dir' ) ) {
			$up = wp_upload_dir();
			if ( empty( $up['error'] ) && ! empty( $up['basedir'] ) ) {
				$dir = trailingslashit( $up['basedir'] ) . 'canvasly-lite/rollback';
			}
		}
		/**
		 * Filter the rollback archive directory.
		 *
		 * @param string $dir
		 */
		$filtered = apply_filters( 'canvasly-lite/rollback/dir', $dir );
		return is_string( $filtered ) && $filtered !== '' ? untrailingslashit( $filtered ) : untrailingslashit( $dir );
	}

	/**
	 * @return int
	 */
	public static function keep() {
		$g = class_exists( GlobalSettings::class ) ? GlobalSettings::get() : array();
		$n = absint( $g['rollback_keep'] ?? 3 );
		return max( 1, min( 10, $n ? $n : 3 ) );
	}

	/**
	 * @return array<int,array{name:string,size:int,version:string,time:int,path:string,current:bool}>
	 */
	public static function versions() {
		$dir = self::dir();
		if ( $dir === '' || ! is_dir( $dir ) ) {
			return array();
		}
		$files = glob( $dir . '/*.zip' );
		if ( ! is_array( $files ) ) {
			return array();
		}
		$current = defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '';
		$out     = array();
		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}
			$parsed  = self::parse_name( basename( $file ) );
			$out[]   = array(
				'name'    => basename( $file ),
				'size'    => (int) filesize( $file ),
				'version' => $parsed['version'],
				'time'    => $parsed['time'],
				'path'    => $file,
				'current' => $parsed['version'] !== '' && $parsed['version'] === $current,
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return ( (int) $b['time'] ) <=> ( (int) $a['time'] );
			}
		);
		return $out;
	}

	/**
	 * @param string $name
	 * @return array{version:string,time:int}
	 */
	public static function parse_name( $name ) {
		$name = (string) $name;
		if ( preg_match( '/^canvasly-lite-(.+)-(\d{8}-\d{6})\.zip$/', $name, $m ) ) {
			$ts = \DateTime::createFromFormat( 'Ymd-His', $m[2], new \DateTimeZone( 'UTC' ) );
			return array(
				'version' => (string) $m[1],
				'time'    => $ts ? $ts->getTimestamp() : 0,
			);
		}
		return array(
			'version' => '',
			'time'    => is_file( $name ) ? (int) filemtime( $name ) : 0,
		);
	}

	/**
	 * @param array $args {
	 *   @type string $source Plugin directory to archive.
	 *   @type string $version
	 * }
	 * @return array{file:string,name:string,version:string}|\WP_Error
	 */
	public static function snapshot( $args = array() ) {
		$args    = is_array( $args ) ? $args : array();
		$source  = isset( $args['source'] ) && is_string( $args['source'] ) && $args['source'] !== ''
			? untrailingslashit( $args['source'] )
			: untrailingslashit( CANVASLY_LITE_PATH );
		$version = isset( $args['version'] ) && is_string( $args['version'] ) && $args['version'] !== ''
			? self::sanitize_version( $args['version'] )
			: self::sanitize_version( defined( 'CANVASLY_LITE_VERSION' ) ? CANVASLY_LITE_VERSION : '0' );
		if ( $source === '' || ! is_dir( $source ) ) {
			return new \WP_Error( 'rollback_source', __( 'The plugin directory could not be read.', 'canvasly-lite' ) );
		}
		$dir = self::dir();
		if ( $dir === '' ) {
			return new \WP_Error( 'rollback_dir', __( 'The rollback directory is not available.', 'canvasly-lite' ) );
		}
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
			return new \WP_Error( 'rollback_dir', __( 'The rollback directory is not writable.', 'canvasly-lite' ) );
		}
		$stamp = gmdate( 'Ymd-His' );
		$name  = 'canvasly-lite-' . $version . '-' . $stamp . '.zip';
		$path  = $dir . '/' . $name;
		$slug  = basename( $source );
		if ( $slug === '' || $slug === '.' || $slug === '..' ) {
			$slug = 'canvasly-lite';
		}
		$entries = self::collect_entries( $source, $slug );
		if ( ! $entries ) {
			return new \WP_Error( 'rollback_empty', __( 'Nothing was found to archive.', 'canvasly-lite' ) );
		}
		$written = self::zip_write( $path, $entries );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		self::prune( self::keep() );
		/**
		 * Fires after a plugin ZIP is stored for rollback.
		 *
		 * @param string $path
		 * @param string $version
		 */
		do_action( 'canvasly-lite/rollback/snapshot', $path, $version );
		return array(
			'file'    => $path,
			'name'    => $name,
			'version' => $version,
		);
	}

	/**
	 * @param int $keep
	 * @return int Number of files removed.
	 */
	public static function prune( $keep = 0 ) {
		$keep  = $keep ? absint( $keep ) : self::keep();
		$keep  = max( 1, min( 10, $keep ) );
		$list  = self::versions();
		$gone  = 0;
		if ( count( $list ) <= $keep ) {
			return 0;
		}
		$drop = array_slice( $list, $keep );
		foreach ( $drop as $row ) {
			$path = (string) ( $row['path'] ?? '' );
			if ( $path !== '' && is_file( $path ) && self::is_inside_dir( $path, self::dir() ) ) {
				if ( \CanvaslyLite\Utils\Filesystem::delete_file( $path ) ) {
					$gone++;
				}
			}
		}
		return $gone;
	}

	/**
	 * @param string $name Zip basename.
	 * @param array  $args {
	 *   @type string $dest Plugin directory to overwrite.
	 *   @type bool   $snapshot Snapshot the current files first.
	 * }
	 * @return array{restored:string,version:string}|\WP_Error
	 */
	public static function restore( $name, $args = array() ) {
		$args = is_array( $args ) ? $args : array();
		$file = self::resolve_zip( $name );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$dest = isset( $args['dest'] ) && is_string( $args['dest'] ) && $args['dest'] !== ''
			? untrailingslashit( $args['dest'] )
			: untrailingslashit( CANVASLY_LITE_PATH );
		if ( $dest === '' || ! is_dir( $dest ) ) {
			return new \WP_Error( 'rollback_dest', __( 'The plugin directory could not be written.', 'canvasly-lite' ) );
		}
		if ( ! array_key_exists( 'snapshot', $args ) || $args['snapshot'] ) {
			$snap = self::snapshot( array( 'source' => $dest ) );
			if ( is_wp_error( $snap ) ) {
				return $snap;
			}
		}
		$extracted = self::extract_plugin_root( $file );
		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}
		$ok = self::copy_tree( $extracted['root'], $dest );
		self::rmdir_tree( $extracted['tmp'] );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$parsed = self::parse_name( basename( $file ) );
		/**
		 * Fires after a stored ZIP is restored over the plugin directory.
		 *
		 * @param string $file
		 * @param string $dest
		 */
		do_action( 'canvasly-lite/rollback/restore', $file, $dest );
		return array(
			'restored' => basename( $file ),
			'version'  => $parsed['version'],
		);
	}

	/**
	 * @param mixed $response
	 * @param array $hook_extra
	 * @return mixed
	 */
	public static function on_pre_install( $response, $hook_extra ) {
		$plugin = '';
		if ( is_array( $hook_extra ) ) {
			$plugin = (string) ( $hook_extra['plugin'] ?? '' );
			if ( $plugin === '' && ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
				$plugin = (string) reset( $hook_extra['plugins'] );
			}
		}
		if ( $plugin !== '' && self::is_self( $plugin ) ) {
			self::snapshot( array( 'reason' => 'upgrade' ) );
		}
		return $response;
	}

	/**
	 * @param string $plugin plugin_basename
	 * @return bool
	 */
	public static function is_self( $plugin ) {
		$self = function_exists( 'plugin_basename' ) && defined( 'CANVASLY_LITE_FILE' )
			? plugin_basename( CANVASLY_LITE_FILE )
			: 'canvasly-lite/canvasly-lite.php';
		return $plugin === $self || basename( (string) $plugin ) === 'canvasly-lite.php';
	}

	public static function handle_restore() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can roll back Canvasly.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_rollback' );
		$name   = isset( $_POST['zip'] ) ? sanitize_file_name( wp_unslash( $_POST['zip'] ) ) : '';
		$result = self::restore( $name );
		if ( is_wp_error( $result ) ) {
			self::store_notice( 'error', $result->get_error_message() );
		} else {
			self::store_notice(
				'updated',
				sprintf(
					/* translators: %s: plugin version */
					__( 'Canvasly was restored to version %s. Reload this page.', 'canvasly-lite' ),
					$result['version'] !== '' ? $result['version'] : $result['restored']
				)
			);
		}
		self::redirect_back();
	}

	public static function handle_snapshot() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only administrators can roll back Canvasly.', 'canvasly-lite' ) );
		}
		check_admin_referer( 'lb_rollback_snapshot' );
		$result = self::snapshot();
		if ( is_wp_error( $result ) ) {
			self::store_notice( 'error', $result->get_error_message() );
		} else {
			self::store_notice(
				'updated',
				sprintf(
					/* translators: %s: zip file name */
					__( 'Stored current version as %s.', 'canvasly-lite' ),
					$result['name']
				)
			);
		}
		self::redirect_back();
	}

	public static function tools_screen() {
		if ( ! self::can_manage() ) {
			return;
		}
		echo '<hr><h2>' . esc_html__( 'Version rollback', 'canvasly-lite' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Store a ZIP of the current plugin and restore a previous version. A snapshot is also taken automatically before WordPress updates Canvasly.', 'canvasly-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:12px">';
		wp_nonce_field( 'lb_rollback_snapshot' );
		echo '<input type="hidden" name="action" value="lb_rollback_snapshot">';
		if ( class_exists( AdminSettings::class ) ) {
			AdminSettings::echo_return_tab( 'tools' );
		}
		echo '<p><button class="button" type="submit">' . esc_html__( 'Store current version', 'canvasly-lite' ) . '</button></p>';
		echo '</form>';
		$list = self::versions();
		if ( ! $list ) {
			echo '<p>' . esc_html__( 'No stored plugin versions yet.', 'canvasly-lite' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped lb-ops-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Version', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Stored', 'canvasly-lite' ) . '</th>';
		echo '<th>' . esc_html__( 'Size', 'canvasly-lite' ) . '</th>';
		echo '<th></th></tr></thead><tbody>';
		foreach ( $list as $row ) {
			$when = ! empty( $row['time'] ) && function_exists( 'wp_date' )
				? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $row['time'] )
				: ( ! empty( $row['time'] ) ? gmdate( 'Y-m-d H:i:s', (int) $row['time'] ) : '' );
			echo '<tr>';
			echo '<td><code>' . esc_html( $row['version'] !== '' ? $row['version'] : $row['name'] ) . '</code>';
			if ( ! empty( $row['current'] ) ) {
				echo ' <span class="lb-ops-status">' . esc_html__( 'current', 'canvasly-lite' ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( (string) $when ) . '</td>';
			echo '<td>' . esc_html( function_exists( 'size_format' ) ? (string) size_format( (int) $row['size'] ) : (string) $row['size'] ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(' . esc_attr( wp_json_encode( __( 'Replace the installed plugin with this stored version?', 'canvasly-lite' ) ) ) . ');">';
			wp_nonce_field( 'lb_rollback' );
			echo '<input type="hidden" name="action" value="lb_rollback">';
			echo '<input type="hidden" name="zip" value="' . esc_attr( $row['name'] ) . '">';
			if ( class_exists( AdminSettings::class ) ) {
				AdminSettings::echo_return_tab( 'tools' );
			}
			echo '<button class="button" type="submit">' . esc_html__( 'Restore', 'canvasly-lite' ) . '</button>';
			echo '</form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param string $namespace
	 */
	public static function routes( $namespace ) {
		$ns = $namespace !== '' ? $namespace : 'canvasly-lite/v1';
		register_rest_route(
			$ns,
			'/rollback',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'rest_list' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest_restore' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			$ns,
			'/rollback/snapshot',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_snapshot' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
	}

	public static function rest_list() {
		$list = self::versions();
		foreach ( $list as &$row ) {
			unset( $row['path'] );
		}
		unset( $row );
		return rest_ensure_response(
			array(
				'versions' => $list,
				'keep'     => self::keep(),
			)
		);
	}

	public static function rest_snapshot() {
		$out = self::snapshot();
		return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_restore( $req ) {
		$d    = is_object( $req ) && method_exists( $req, 'get_json_params' ) ? (array) $req->get_json_params() : array();
		$name = sanitize_file_name( (string) ( $d['zip'] ?? $d['name'] ?? '' ) );
		$out  = self::restore( $name );
		return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
	}

	public static function admin_notice() {
		if ( class_exists( '\\CanvaslyLite\\Admin\\AdminContext' ) && ! \CanvaslyLite\Admin\AdminContext::is_plugin_page() ) {
			return;
		}
		$key  = self::NOTICE . '_' . get_current_user_id();
		$data = function_exists( 'get_transient' ) ? get_transient( $key ) : null;
		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$class = ( $data['type'] ?? 'updated' ) === 'error' ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) $data['message'] ) . '</p></div>';
	}

	/**
	 * @param string $raw
	 * @return string
	 */
	public static function sanitize_version( $raw ) {
		$s = preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $raw );
		return $s !== '' ? substr( $s, 0, 32 ) : '0';
	}

	/**
	 * @param string $name
	 * @return string|\WP_Error Absolute path.
	 */
	public static function resolve_zip( $name ) {
		$name = basename( str_replace( '\\', '/', (string) $name ) );
		if ( $name === '' || strtolower( substr( $name, -4 ) ) !== '.zip' || strpos( $name, '..' ) !== false ) {
			return new \WP_Error( 'rollback_zip', __( 'That rollback archive was not found.', 'canvasly-lite' ) );
		}
		$dir  = self::dir();
		$path = $dir . '/' . $name;
		if ( ! is_readable( $path ) || ! self::is_inside_dir( $path, $dir ) ) {
			return new \WP_Error( 'rollback_zip', __( 'That rollback archive was not found.', 'canvasly-lite' ) );
		}
		return $path;
	}

	/**
	 * @param string $path
	 * @param string $dir
	 * @return bool
	 */
	public static function is_inside_dir( $path, $dir ) {
		$real_path = realpath( $path );
		$real_dir  = realpath( $dir );
		if ( ! $real_path || ! $real_dir ) {
			return false;
		}
		$real_dir = rtrim( str_replace( '\\', '/', $real_dir ), '/' ) . '/';
		$real_path = str_replace( '\\', '/', $real_path );
		return strpos( $real_path, $real_dir ) === 0 || $real_path === rtrim( $real_dir, '/' );
	}

	/**
	 * @param string $source
	 * @param string $prefix
	 * @return array<int,array{name:string,file:string}>
	 */
	public static function collect_entries( $source, $prefix ) {
		$skip = array( 'node_modules', '.git', '.cursor', 'terminals', 'rollback' );
		$out  = array();
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS )
		);
		$source_n = rtrim( str_replace( '\\', '/', $source ), '/' );
		foreach ( $iter as $file ) {
			if ( ! $file instanceof \SplFileInfo || ! $file->isFile() ) {
				continue;
			}
			$full = str_replace( '\\', '/', $file->getPathname() );
			$rel  = ltrim( substr( $full, strlen( $source_n ) ), '/' );
			$parts = explode( '/', $rel );
			if ( array_intersect( $parts, $skip ) ) {
				continue;
			}
			if ( strtolower( substr( $rel, -4 ) ) === '.zip' ) {
				continue;
			}
			$out[] = array(
				'name' => $prefix . '/' . $rel,
				'file' => $full,
			);
		}
		return $out;
	}

	/**
	 * @param string $path
	 * @param array  $entries
	 * @return true|\WP_Error
	 */
	public static function zip_write( $path, $entries ) {
		if ( class_exists( Kit::class ) && method_exists( Kit::class, 'zip_write' ) ) {
			$ok = Kit::zip_write( $path, $entries );
			if ( is_wp_error( $ok ) ) {
				return new \WP_Error( 'zip_create', __( 'Could not create the rollback ZIP.', 'canvasly-lite' ) );
			}
			return true;
		}
		if ( class_exists( '\ZipArchive' ) ) {
			$zip = new \ZipArchive();
			if ( $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
				return new \WP_Error( 'zip_create', __( 'Could not create the rollback ZIP.', 'canvasly-lite' ) );
			}
			foreach ( $entries as $entry ) {
				$name = (string) ( $entry['name'] ?? '' );
				if ( $name === '' ) {
					continue;
				}
				if ( isset( $entry['file'] ) && is_readable( $entry['file'] ) ) {
					$zip->addFile( $entry['file'], $name );
				} else {
					$zip->addFromString( $name, (string) ( $entry['data'] ?? '' ) );
				}
			}
			$zip->close();
			return true;
		}
		$records = array();
		$offset  = 0;
		$body    = '';
		foreach ( $entries as $entry ) {
			$name = str_replace( '\\', '/', (string) ( $entry['name'] ?? '' ) );
			if ( $name === '' ) {
				continue;
			}
			$data = isset( $entry['file'] ) && is_readable( $entry['file'] ) ? (string) file_get_contents( $entry['file'] ) : (string) ( $entry['data'] ?? '' );
			$crc  = crc32( $data ) & 0xffffffff;
			$len  = strlen( $data );
			$nlen = strlen( $name );
			$local = pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $len, $len, $nlen, 0 ) . $name . $data;
			$body .= $local;
			$records[] = array(
				'name'   => $name,
				'crc'    => $crc,
				'len'    => $len,
				'offset' => $offset,
			);
			$offset += strlen( $local );
		}
		$central = '';
		foreach ( $records as $r ) {
			$nlen     = strlen( $r['name'] );
			$central .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $r['crc'], $r['len'], $r['len'], $nlen, 0, 0, 0, 0, 0, $r['offset'] ) . $r['name'];
		}
		$eocd = pack( 'VvvvvVVv', 0x06054b50, 0, 0, count( $records ), count( $records ), strlen( $central ), $offset, 0 );
		if ( file_put_contents( $path, $body . $central . $eocd ) === false ) {
			return new \WP_Error( 'zip_create', __( 'Could not create the rollback ZIP.', 'canvasly-lite' ) );
		}
		return true;
	}

	/**
	 * @param string $zip
	 * @return array{tmp:string,root:string}|\WP_Error
	 */
	public static function extract_plugin_root( $zip ) {
		$base = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 8, false, false ) : bin2hex( random_bytes( 4 ) );
		$tmp   = trailingslashit( $base ) . 'lb-rollback-' . $token;
		if ( function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $tmp );
		} else {
			wp_mkdir_p( $tmp );
		}
		if ( ! is_dir( $tmp ) ) {
			return new \WP_Error( 'rollback_tmp', __( 'Could not create a temporary folder for the rollback.', 'canvasly-lite' ) );
		}
		$ok = self::zip_extract( $zip, $tmp );
		if ( is_wp_error( $ok ) ) {
			self::rmdir_tree( $tmp );
			return $ok;
		}
		$root = self::find_plugin_root( $tmp );
		if ( $root === '' ) {
			self::rmdir_tree( $tmp );
			return new \WP_Error( 'rollback_plugin', __( 'The archive does not contain a Canvasly plugin.', 'canvasly-lite' ) );
		}
		return array(
			'tmp'  => $tmp,
			'root' => $root,
		);
	}

	/**
	 * @param string $zip
	 * @param string $dest
	 * @return true|\WP_Error
	 */
	public static function zip_extract( $zip, $dest ) {
		$dest = untrailingslashit( $dest );
		if ( class_exists( '\ZipArchive' ) ) {
			$za = new \ZipArchive();
			if ( $za->open( $zip ) !== true ) {
				return new \WP_Error( 'rollback_zip', __( 'The rollback archive could not be opened.', 'canvasly-lite' ) );
			}
			for ( $i = 0; $i < $za->numFiles; $i++ ) {
				$name = $za->getNameIndex( $i );
				if ( ! self::safe_entry_name( $name ) ) {
					continue;
				}
				$target = $dest . '/' . str_replace( '\\', '/', $name );
				if ( substr( $name, -1 ) === '/' ) {
					if ( function_exists( 'wp_mkdir_p' ) ) {
						wp_mkdir_p( $target );
					} else {
						wp_mkdir_p( $target );
					}
					continue;
				}
				$dir = dirname( $target );
				if ( ! is_dir( $dir ) ) {
					if ( function_exists( 'wp_mkdir_p' ) ) {
						wp_mkdir_p( $dir );
					} else {
						wp_mkdir_p( $dir );
					}
				}
				$data = $za->getFromIndex( $i );
				if ( $data === false ) {
					continue;
				}
				file_put_contents( $target, $data );
			}
			$za->close();
			return true;
		}
		$entries = self::zip_read_stored( $zip );
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}
		foreach ( $entries as $name => $data ) {
			if ( ! self::safe_entry_name( $name ) ) {
				continue;
			}
			$target = $dest . '/' . str_replace( '\\', '/', $name );
			$dir    = dirname( $target );
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			file_put_contents( $target, $data );
		}
		return true;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public static function safe_entry_name( $name ) {
		$name = str_replace( '\\', '/', (string) $name );
		if ( $name === '' || isset( $name[0] ) && $name[0] === '/' || strpos( $name, '..' ) !== false ) {
			return false;
		}
		if ( preg_match( '/^[A-Za-z]:/', $name ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Stored-method ZIP reader used when ZipArchive is missing.
	 *
	 * @param string $path
	 * @return array<string,string>|\WP_Error
	 */
	public static function zip_read_stored( $path ) {
		$bin = is_readable( $path ) ? file_get_contents( $path ) : false;
		if ( $bin === false ) {
			return new \WP_Error( 'rollback_zip', __( 'The rollback archive could not be opened.', 'canvasly-lite' ) );
		}
		$out    = array();
		$offset = 0;
		$len    = strlen( $bin );
		while ( $offset + 30 <= $len ) {
			$sig = unpack( 'Vsig', substr( $bin, $offset, 4 ) );
			if ( ! $sig || (int) $sig['sig'] !== 0x04034b50 ) {
				break;
			}
			$hdr  = unpack( 'vver/vflag/vmethod/vtime/vdate/Vcrc/Vcomp/Vsize/vnamelen/vexlen', substr( $bin, $offset + 4, 26 ) );
			if ( ! $hdr ) {
				break;
			}
			$name = substr( $bin, $offset + 30, $hdr['namelen'] );
			$data_off = $offset + 30 + $hdr['namelen'] + $hdr['exlen'];
			$data     = substr( $bin, $data_off, $hdr['comp'] );
			if ( (int) $hdr['method'] === 0 ) {
				$out[ $name ] = $data;
			} elseif ( function_exists( 'gzinflate' ) && (int) $hdr['method'] === 8 ) {
				$plain = @gzinflate( $data );
				if ( is_string( $plain ) ) {
					$out[ $name ] = $plain;
				}
			}
			$offset = $data_off + $hdr['comp'];
		}
		return $out;
	}

	/**
	 * @param string $zip
	 * @return array{name:string,version:string}|\WP_Error
	 */
	public static function zip_plugin_header( $zip ) {
		$extracted = self::extract_plugin_root( $zip );
		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}
		$file = $extracted['root'] . '/canvasly-lite.php';
		$data = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
		self::rmdir_tree( $extracted['tmp'] );
		if ( $data === '' || stripos( $data, 'Plugin Name:' ) === false || stripos( $data, 'Canvasly' ) === false ) {
			return new \WP_Error( 'rollback_plugin', __( 'The archive does not contain a Canvasly plugin.', 'canvasly-lite' ) );
		}
		$version = '';
		if ( preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', $data, $m ) ) {
			$version = trim( $m[1] );
		}
		return array(
			'name'    => 'Canvasly',
			'version' => $version,
		);
	}

	/**
	 * @param string $dir
	 * @return string
	 */
	public static function find_plugin_root( $dir ) {
		$dir = untrailingslashit( $dir );
		if ( self::is_plugin_file( $dir . '/canvasly-lite.php' ) ) {
			return $dir;
		}
		$items = @scandir( $dir );
		if ( ! is_array( $items ) ) {
			return '';
		}
		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) && self::is_plugin_file( $path . '/canvasly-lite.php' ) ) {
				return $path;
			}
		}
		return '';
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public static function is_plugin_file( $file ) {
		if ( ! is_readable( $file ) ) {
			return false;
		}
		$head = (string) file_get_contents( $file, false, null, 0, 8192 );
		return stripos( $head, 'Plugin Name:' ) !== false && stripos( $head, 'Canvasly' ) !== false;
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @return true|\WP_Error
	 */
	public static function copy_tree( $from, $to ) {
		$from = untrailingslashit( $from );
		$to   = untrailingslashit( $to );
		if ( ! is_dir( $from ) || ! is_dir( $to ) ) {
			return new \WP_Error( 'rollback_copy', __( 'Could not copy plugin files.', 'canvasly-lite' ) );
		}
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $from, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		$from_n = rtrim( str_replace( '\\', '/', $from ), '/' );
		foreach ( $iter as $file ) {
			if ( ! $file instanceof \SplFileInfo ) {
				continue;
			}
			$rel  = ltrim( str_replace( '\\', '/', substr( str_replace( '\\', '/', $file->getPathname() ), strlen( $from_n ) ) ), '/' );
			$dest = $to . '/' . $rel;
			if ( $file->isDir() ) {
				if ( ! is_dir( $dest ) ) {
					wp_mkdir_p( $dest );
				}
				continue;
			}
			$dir = dirname( $dest );
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			if ( ! @copy( $file->getPathname(), $dest ) ) {
				return new \WP_Error( 'rollback_copy', __( 'Could not copy plugin files.', 'canvasly-lite' ) );
			}
		}
		return true;
	}

	/**
	 * @param string $dir
	 */
	public static function rmdir_tree( $dir ) {
		\CanvaslyLite\Utils\Filesystem::rmdir_tree( $dir );
	}

	private static function redirect_back() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Callers verify their action nonce before redirecting.
		$tab = '';
		if ( ! empty( $_POST['lb_settings_tab'] ) ) {
			$tab = sanitize_key( wp_unslash( $_POST['lb_settings_tab'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( $tab !== '' && class_exists( AdminSettings::class ) ) {
			wp_safe_redirect( AdminSettings::url( $tab ) );
			exit;
		}
		$ref = wp_get_referer();
		wp_safe_redirect( $ref ? $ref : admin_url( 'admin.php?page=canvasly-lite-tools' ) );
		exit;
	}

	/**
	 * @param string $type
	 * @param string $message
	 */
	public static function store_notice( $type, $message ) {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		$ttl = defined( 'MINUTE_IN_SECONDS' ) ? 5 * MINUTE_IN_SECONDS : 300;
		set_transient(
			self::NOTICE . '_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			$ttl
		);
	}
}
