<?php
namespace CanvaslyLite\Dynamic;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Built-in dynamic tags registered on Tags::boot().
 */
class Builtin {
	public static function register( Tags $tags ) {
		foreach ( self::definitions() as $def ) {
			$tags->register( $def );
		}
	}

	/** @return array<int,array> */
	public static function definitions() {
		$post_id = static function ( array $ctx ) {
			return absint( $ctx['post_id'] ?? 0 );
		};
		$author_id = static function ( array $ctx ) use ( $post_id ) {
			$id = $post_id( $ctx );
			if ( $id && function_exists( 'get_post_field' ) ) {
				return absint( get_post_field( 'post_author', $id ) );
			}
			$post = $ctx['post'] ?? null;
			return ( $post && isset( $post->post_author ) ) ? absint( $post->post_author ) : 0;
		};

		return array(
			array(
				'name'       => 'post_title',
				'title'      => __( 'Post Title', 'canvasly-lite' ),
				'group'      => 'post',
				'categories' => array( 'text' ),
				'render'     => static function ( $s, $ctx ) use ( $post_id ) {
					$id = $post_id( $ctx );
					if ( ! $id || ! function_exists( 'get_the_title' ) ) {
						return '';
					}
					return (string) get_the_title( $id );
				},
			),
			array(
				'name'       => 'post_excerpt',
				'title'      => __( 'Post Excerpt', 'canvasly-lite' ),
				'group'      => 'post',
				'categories' => array( 'text' ),
				'render'     => static function ( $s, $ctx ) use ( $post_id ) {
					$id = $post_id( $ctx );
					if ( ! $id ) {
						return '';
					}
					if ( class_exists( '\\CanvaslyLite\\Document\\DevMode' ) ) {
						return \CanvaslyLite\Document\DevMode::safe_excerpt( $id );
					}
					if ( ! function_exists( 'get_post_field' ) ) {
						return '';
					}
					$excerpt = trim( (string) get_post_field( 'post_excerpt', $id ) );
					if ( $excerpt === '' ) {
						$excerpt = (string) get_post_field( 'post_content', $id );
					}
					return wp_strip_all_tags( $excerpt );
				},
			),
			array(
				'name'       => 'post_content',
				'title'      => __( 'Post Content', 'canvasly-lite' ),
				'group'      => 'post',
				'categories' => array( 'text', 'html' ),
				'render'     => static function ( $s, $ctx ) use ( $post_id ) {
					if ( ! empty( $ctx['for_canvas'] ) ) {
						return function_exists( '__' ) ? __( 'Post Content', 'canvasly-lite' ) : 'Post Content';
					}
					$id = $post_id( $ctx );
					if ( ! $id || ! function_exists( 'get_post_field' ) ) {
						return '';
					}
					$html = (string) get_post_field( 'post_content', $id );
					return function_exists( 'wp_kses_post' ) ? wp_kses_post( $html ) : $html;
				},
			),
			array(
				'name'       => 'post_date',
				'title'      => __( 'Post Date', 'canvasly-lite' ),
				'group'      => 'post',
				'categories' => array( 'text' ),
				'controls'   => array(
					'format' => array(
						'type'        => 'text',
						'label'       => __( 'Date Format', 'canvasly-lite' ),
						'placeholder' => 'F j, Y',
						'description' => __( 'PHP date format. Leave empty for the site default.', 'canvasly-lite' ),
					),
				),
				'render'     => static function ( $s, $ctx ) use ( $post_id ) {
					$id = $post_id( $ctx );
					if ( ! $id ) {
						return '';
					}
					$format = trim( (string) ( $s['format'] ?? '' ) );
					if ( $format === '' && function_exists( 'get_option' ) ) {
						$format = (string) get_option( 'date_format', 'F j, Y' );
					}
					if ( $format === '' ) {
						$format = 'F j, Y';
					}
					if ( function_exists( 'get_the_date' ) ) {
						return (string) get_the_date( $format, $id );
					}
					$raw = function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_date', $id ) : '';
					$ts  = $raw ? strtotime( $raw ) : false;
					return $ts ? gmdate( $format, $ts ) : $raw;
				},
			),
			array(
				'name'       => 'post_url',
				'title'      => __( 'Post URL', 'canvasly-lite' ),
				'group'      => 'post',
				'categories' => array( 'url', 'text' ),
				'render'     => static function ( $s, $ctx ) use ( $post_id ) {
					$id = $post_id( $ctx );
					if ( ! $id || ! function_exists( 'get_permalink' ) ) {
						return '';
					}
					$url = (string) get_permalink( $id );
					return function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
				},
			),
			array(
				'name'       => 'post_featured_image',
				'title'      => __( 'Featured Image', 'canvasly-lite' ),
				'group'      => 'post',
				'categories' => array( 'image', 'url' ),
				'render'     => static function ( $s, $ctx ) use ( $post_id ) {
					$id = $post_id( $ctx );
					if ( ! $id ) {
						return array( 'id' => 0, 'url' => '' );
					}
					$att = function_exists( 'get_post_thumbnail_id' ) ? absint( get_post_thumbnail_id( $id ) ) : 0;
					$url = '';
					if ( function_exists( 'get_the_post_thumbnail_url' ) ) {
						$url = (string) get_the_post_thumbnail_url( $id, 'full' );
					} elseif ( $att && function_exists( 'wp_get_attachment_image_url' ) ) {
						$url = (string) wp_get_attachment_image_url( $att, 'full' );
					}
					if ( $url && function_exists( 'esc_url_raw' ) ) {
						$url = esc_url_raw( $url );
					}
					return array( 'id' => $att, 'url' => $url );
				},
			),
			array(
				'name'       => 'post_terms',
				'title'      => __( 'Post Terms', 'canvasly-lite' ),
				'group'      => 'post',
				'categories' => array( 'text' ),
				'controls'   => array(
					'taxonomy'  => array(
						'type'        => 'text',
						'label'       => __( 'Taxonomy', 'canvasly-lite' ),
						'placeholder' => 'category',
						'default'     => 'category',
					),
					'separator' => array(
						'type'        => 'text',
						'label'       => __( 'Separator', 'canvasly-lite' ),
						'placeholder' => ', ',
						'default'     => ', ',
					),
				),
				'render'     => static function ( $s, $ctx ) use ( $post_id ) {
					$id = $post_id( $ctx );
					if ( ! $id || ! function_exists( 'get_the_terms' ) ) {
						return '';
					}
					$tax = sanitize_key( (string) ( $s['taxonomy'] ?? 'category' ) );
					if ( $tax === '' ) {
						$tax = 'category';
					}
					$terms = get_the_terms( $id, $tax );
					if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) || ! $terms ) {
						return '';
					}
					$names = array();
					foreach ( $terms as $term ) {
						if ( is_object( $term ) && isset( $term->name ) ) {
							$names[] = (string) $term->name;
						}
					}
					$sep = array_key_exists( 'separator', $s ) ? (string) $s['separator'] : ', ';
					return implode( $sep, $names );
				},
			),
			array(
				'name'       => 'author_name',
				'title'      => __( 'Author Name', 'canvasly-lite' ),
				'group'      => 'author',
				'categories' => array( 'text' ),
				'render'     => static function ( $s, $ctx ) use ( $author_id ) {
					$uid = $author_id( $ctx );
					if ( ! $uid || ! function_exists( 'get_the_author_meta' ) ) {
						return '';
					}
					return (string) get_the_author_meta( 'display_name', $uid );
				},
			),
			array(
				'name'       => 'author_bio',
				'title'      => __( 'Author Bio', 'canvasly-lite' ),
				'group'      => 'author',
				'categories' => array( 'text', 'html' ),
				'render'     => static function ( $s, $ctx ) use ( $author_id ) {
					$uid = $author_id( $ctx );
					if ( ! $uid || ! function_exists( 'get_the_author_meta' ) ) {
						return '';
					}
					$bio = (string) get_the_author_meta( 'description', $uid );
					return function_exists( 'wp_kses_post' ) ? wp_kses_post( $bio ) : wp_strip_all_tags( $bio );
				},
			),
			array(
				'name'       => 'author_email',
				'title'      => __( 'Author Email', 'canvasly-lite' ),
				'group'      => 'author',
				'categories' => array( 'text' ),
				'render'     => static function ( $s, $ctx ) use ( $author_id ) {
					$uid = $author_id( $ctx );
					if ( ! $uid || ! function_exists( 'get_the_author_meta' ) ) {
						return '';
					}
					$email = (string) get_the_author_meta( 'user_email', $uid );
					return function_exists( 'sanitize_email' ) ? sanitize_email( $email ) : $email;
				},
			),
			array(
				'name'       => 'author_url',
				'title'      => __( 'Author URL', 'canvasly-lite' ),
				'group'      => 'author',
				'categories' => array( 'url', 'text' ),
				'render'     => static function ( $s, $ctx ) use ( $author_id ) {
					$uid = $author_id( $ctx );
					if ( ! $uid ) {
						return '';
					}
					$url = function_exists( 'get_author_posts_url' ) ? (string) get_author_posts_url( $uid ) : '';
					return function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
				},
			),
			array(
				'name'       => 'author_avatar',
				'title'      => __( 'Author Avatar', 'canvasly-lite' ),
				'group'      => 'author',
				'categories' => array( 'image', 'url' ),
				'render'     => static function ( $s, $ctx ) use ( $author_id ) {
					$uid = $author_id( $ctx );
					$url = ( $uid && function_exists( 'get_avatar_url' ) ) ? (string) get_avatar_url( $uid ) : '';
					if ( $url && function_exists( 'esc_url_raw' ) ) {
						$url = esc_url_raw( $url );
					}
					return array( 'id' => 0, 'url' => $url );
				},
			),
			array(
				'name'       => 'site_title',
				'title'      => __( 'Site Title', 'canvasly-lite' ),
				'group'      => 'site',
				'categories' => array( 'text' ),
				'render'     => static function () {
					return function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
				},
			),
			array(
				'name'       => 'site_tagline',
				'title'      => __( 'Site Tagline', 'canvasly-lite' ),
				'group'      => 'site',
				'categories' => array( 'text' ),
				'render'     => static function () {
					return function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'description' ) : '';
				},
			),
			array(
				'name'       => 'site_url',
				'title'      => __( 'Site URL', 'canvasly-lite' ),
				'group'      => 'site',
				'categories' => array( 'url', 'text' ),
				'render'     => static function () {
					$url = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
					return function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
				},
			),
			array(
				'name'       => 'site_logo',
				'title'      => __( 'Site Logo', 'canvasly-lite' ),
				'group'      => 'site',
				'categories' => array( 'image', 'url' ),
				'render'     => static function () {
					$id = function_exists( 'get_theme_mod' ) ? absint( get_theme_mod( 'custom_logo' ) ) : 0;
					$url = '';
					if ( $id && function_exists( 'wp_get_attachment_image_url' ) ) {
						$url = (string) wp_get_attachment_image_url( $id, 'full' );
					}
					if ( $url && function_exists( 'esc_url_raw' ) ) {
						$url = esc_url_raw( $url );
					}
					return array( 'id' => $id, 'url' => $url );
				},
			),
			array(
				'name'       => 'current_user_name',
				'title'      => __( 'Current User Name', 'canvasly-lite' ),
				'group'      => 'user',
				'categories' => array( 'text' ),
				'render'     => static function () {
					if ( ! function_exists( 'wp_get_current_user' ) ) {
						return '';
					}
					$user = wp_get_current_user();
					if ( ! $user || ! ( $user->ID ?? 0 ) ) {
						return '';
					}
					return (string) ( $user->display_name ?? $user->user_login ?? '' );
				},
			),
			array(
				'name'       => 'current_user_email',
				'title'      => __( 'Current User Email', 'canvasly-lite' ),
				'group'      => 'user',
				'categories' => array( 'text' ),
				'render'     => static function () {
					if ( ! function_exists( 'wp_get_current_user' ) ) {
						return '';
					}
					$user = wp_get_current_user();
					$email = ( $user && ( $user->ID ?? 0 ) ) ? (string) ( $user->user_email ?? '' ) : '';
					return function_exists( 'sanitize_email' ) ? sanitize_email( $email ) : $email;
				},
			),
			array(
				'name'       => 'archive_title',
				'title'      => __( 'Archive Title', 'canvasly-lite' ),
				'group'      => 'archive',
				'categories' => array( 'text' ),
				'render'     => static function ( $s, $ctx ) {
					if ( ! empty( $ctx['for_canvas'] ) ) {
						if ( function_exists( 'is_singular' ) && is_singular() ) {
							return function_exists( '__' ) ? __( 'Archive Title', 'canvasly-lite' ) : 'Archive Title';
						}
					}
					if ( function_exists( 'get_the_archive_title' ) ) {
						$title = (string) get_the_archive_title();
						return wp_strip_all_tags( $title );
					}
					return '';
				},
			),
			array(
				'name'       => 'term_name',
				'title'      => __( 'Term Name', 'canvasly-lite' ),
				'group'      => 'term',
				'categories' => array( 'text' ),
				'render'     => static function ( $s, $ctx ) {
					$term = self::term_from_context( $ctx );
					return $term ? (string) $term->name : '';
				},
			),
			array(
				'name'       => 'term_description',
				'title'      => __( 'Term Description', 'canvasly-lite' ),
				'group'      => 'term',
				'categories' => array( 'text', 'html' ),
				'render'     => static function ( $s, $ctx ) {
					$term = self::term_from_context( $ctx );
					if ( ! $term ) {
						return '';
					}
					$html = (string) $term->description;
					return function_exists( 'wp_kses_post' ) ? wp_kses_post( $html ) : $html;
				},
			),
			array(
				'name'       => 'term_url',
				'title'      => __( 'Term URL', 'canvasly-lite' ),
				'group'      => 'term',
				'categories' => array( 'url', 'text' ),
				'render'     => static function ( $s, $ctx ) {
					$term = self::term_from_context( $ctx );
					if ( ! $term || ! function_exists( 'get_term_link' ) ) {
						return '';
					}
					$url = get_term_link( $term );
					if ( function_exists( 'is_wp_error' ) && is_wp_error( $url ) ) {
						return '';
					}
					$url = (string) $url;
					return function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
				},
			),
			array(
				'name'       => 'term_count',
				'title'      => __( 'Term Count', 'canvasly-lite' ),
				'group'      => 'term',
				'categories' => array( 'number', 'text' ),
				'render'     => static function ( $s, $ctx ) {
					$term = self::term_from_context( $ctx );
					return $term ? (string) absint( $term->count ) : '';
				},
			),
			array(
				'name'       => 'post_meta',
				'title'      => __( 'Post Meta', 'canvasly-lite' ),
				'group'      => 'advanced',
				'categories' => array( 'text', 'url', 'number', 'color', 'image', 'html' ),
				'controls'   => array(
					'key' => array(
						'type'        => 'text',
						'label'       => __( 'Meta Key', 'canvasly-lite' ),
						'placeholder' => 'custom_field',
					),
				),
				'render'     => static function ( $s, $ctx ) use ( $post_id ) {
					if ( ! empty( $ctx['for_canvas'] ) ) {
						$key = sanitize_key( (string) ( $s['key'] ?? '' ) );
						return $key !== '' ? '{' . $key . '}' : '';
					}
					$id  = $post_id( $ctx );
					$key = sanitize_key( (string) ( $s['key'] ?? '' ) );
					if ( ! $id || $key === '' || ! function_exists( 'get_post_meta' ) ) {
						return '';
					}
					$raw = get_post_meta( $id, $key, true );
					if ( is_array( $raw ) ) {
						if ( isset( $raw['url'] ) || isset( $raw['id'] ) ) {
							return array(
								'id'  => absint( $raw['id'] ?? 0 ),
								'url' => (string) ( $raw['url'] ?? '' ),
							);
						}
						$raw = reset( $raw );
					}
					if ( is_numeric( $raw ) && (int) $raw > 0 && function_exists( 'wp_attachment_is_image' ) && wp_attachment_is_image( (int) $raw ) ) {
						$url = function_exists( 'wp_get_attachment_image_url' ) ? (string) wp_get_attachment_image_url( (int) $raw, 'full' ) : '';
						return array( 'id' => (int) $raw, 'url' => $url );
					}
					if ( is_bool( $raw ) ) {
						return $raw ? '1' : '';
					}
					if ( is_scalar( $raw ) ) {
						return (string) $raw;
					}
					return '';
				},
			),
			array(
				'name'       => 'shortcode',
				'title'      => __( 'Shortcode', 'canvasly-lite' ),
				'group'      => 'advanced',
				'categories' => array( 'text', 'html' ),
				'controls'   => array(
					'shortcode' => array(
						'type'        => 'textarea',
						'label'       => __( 'Shortcode', 'canvasly-lite' ),
						'placeholder' => '[gallery]',
					),
				),
				'render'     => static function ( $s, $ctx ) {
					$code = trim( (string) ( $s['shortcode'] ?? '' ) );
					if ( $code === '' ) {
						return '';
					}
					if ( ! empty( $ctx['for_canvas'] ) || ! function_exists( 'do_shortcode' ) ) {
						return $code;
					}
					$html = (string) do_shortcode( $code );
					return function_exists( 'wp_kses_post' ) ? wp_kses_post( $html ) : $html;
				},
			),
			array(
				'name'       => 'request_parameter',
				'title'      => __( 'Request Parameter', 'canvasly-lite' ),
				'group'      => 'advanced',
				'categories' => array( 'text', 'url', 'number' ),
				'controls'   => array(
					'key'    => array(
						'type'        => 'text',
						'label'       => __( 'Parameter', 'canvasly-lite' ),
						'placeholder' => 'utm_source',
					),
					'source' => array(
						'type'    => 'select',
						'label'   => __( 'Source', 'canvasly-lite' ),
						'options' => array(
							'get'  => __( 'GET', 'canvasly-lite' ),
							'post' => __( 'POST', 'canvasly-lite' ),
						),
						'default' => 'get',
					),
				),
				'render'     => static function ( $s, $ctx ) {
					if ( ! empty( $ctx['for_canvas'] ) ) {
						$key = sanitize_key( (string) ( $s['key'] ?? '' ) );
						return $key !== '' ? '{' . $key . '}' : '';
					}
					$key = sanitize_key( (string) ( $s['key'] ?? '' ) );
					if ( $key === '' ) {
						return '';
					}
					$source = ( ( $s['source'] ?? 'get' ) === 'post' ) ? 'post' : 'get';
					// phpcs:disable WordPress.Security.NonceVerification -- Public dynamic tag reads a named request value for display only.
					$bag    = $source === 'post' ? ( $_POST ?? array() ) : ( $_GET ?? array() );
					// phpcs:enable WordPress.Security.NonceVerification
					if ( ! isset( $bag[ $key ] ) ) {
						return '';
					}
					$raw = $bag[ $key ];
					if ( is_array( $raw ) ) {
						$raw = reset( $raw );
					}
					$val = is_scalar( $raw ) ? (string) $raw : '';
					if ( function_exists( 'wp_unslash' ) ) {
						$val = wp_unslash( $val );
					}
					return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $val ) : wp_strip_all_tags( $val );
				},
			),
		);
	}

	/**
	 * Current term from loop context, or the queried object on a term archive.
	 *
	 * @param array $ctx
	 * @return object|null
	 */
	public static function term_from_context( array $ctx ) {
		if ( isset( $ctx['term'] ) && is_object( $ctx['term'] ) && isset( $ctx['term']->term_id ) ) {
			return $ctx['term'];
		}
		$id = absint( $ctx['term_id'] ?? 0 );
		if ( $id && function_exists( 'get_term' ) ) {
			$term = get_term( $id );
			if ( $term && ! ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
				return $term;
			}
		}
		if ( function_exists( 'is_category' ) && ( is_category() || is_tag() || is_tax() ) && function_exists( 'get_queried_object' ) ) {
			$obj = get_queried_object();
			if ( $obj && is_object( $obj ) && isset( $obj->term_id ) ) {
				return $obj;
			}
		}
		return null;
	}
}
