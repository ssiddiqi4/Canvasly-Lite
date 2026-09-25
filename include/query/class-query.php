<?php
namespace CanvaslyLite\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collection query builder: posts / CPTs and terms, including current-query,
 * related, and manual ID lists. Results are filterable for add-ons.
 */
class Query {
	/**
	 * Run a query described by Collection Loop settings.
	 *
	 * @param array $settings Unit settings.
	 * @param int   $page     1-based page.
	 * @param int   $context_post_id Host document / current post.
	 * @return array{kind:string,items:array,found:int,max_pages:int,page:int,per_page:int}
	 */
	public static function run( array $settings, $page = 1, $context_post_id = 0 ) {
		$page            = max( 1, absint( $page ) );
		$context_post_id = absint( $context_post_id );
		$kind            = self::kind( $settings );
		if ( $kind === 'terms' ) {
			$result = self::run_terms( $settings, $page );
		} else {
			$result = self::run_posts( $settings, $page, $context_post_id );
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'canvasly-lite/query/results', $result, $settings, $page, $context_post_id );
			if ( is_array( $filtered ) ) {
				$result = $filtered;
			}
		}
		return self::normalize_result( $result, $kind, $page );
	}

	/** @param array $settings */
	public static function kind( array $settings ) {
		return ( ( $settings['query_type'] ?? 'posts' ) === 'terms' ) ? 'terms' : 'posts';
	}

	/** Query arg used for this loop's numbered pagination. */
	public static function page_key( $node_id ) {
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $node_id );
		return $id !== '' ? 'lb_page_' . $id : 'lb_page';
	}

	/** Current page from the request for a loop node. */
	public static function requested_page( $node_id ) {
		$key = self::page_key( $node_id );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public numbered pagination query var.
		if ( isset( $_GET[ $key ] ) ) {
			return max( 1, absint( wp_unslash( $_GET[ $key ] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return 1;
	}

	/**
	 * WP_Query arguments for a posts query (does not run it).
	 *
	 * @param array $settings
	 * @param int   $page
	 * @param int   $context_post_id
	 * @return array
	 */
	public static function posts_args( array $settings, $page = 1, $context_post_id = 0 ) {
		$page            = max( 1, absint( $page ) );
		$context_post_id = absint( $context_post_id );
		$source          = self::source( $settings );
		$per_page        = self::per_page( $settings );
		$orderby         = self::orderby( $settings );
		$order           = strtoupper( (string) ( $settings['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';

		$args = array(
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => $orderby,
			'order'                  => $order,
			'ignore_sticky_posts'    => ! empty( $settings['ignore_sticky'] ),
			'no_found_rows'          => self::skip_found_rows( $settings ),
			'update_post_meta_cache' => self::needs_meta_cache( $settings ),
			'update_post_term_cache' => self::needs_term_cache( $settings ),
			'lazy_load_term_meta'    => false,
		);

		$post_type = sanitize_key( (string) ( $settings['post_type'] ?? 'post' ) );
		if ( $post_type === '' ) {
			$post_type = 'post';
		}
		$args['post_type'] = ( $post_type === 'any' ) ? 'any' : self::allowed_post_type( $post_type );

		if ( $source === 'current' ) {
			$args = self::current_query_args( $args, $page, $context_post_id );
		} elseif ( $source === 'related' ) {
			$args = self::related_args( $args, $settings, $context_post_id );
		} elseif ( $source === 'manual' ) {
			$ids = self::parse_ids( $settings['include'] ?? '' );
			$args['post__in']       = $ids ? $ids : array( 0 );
			$args['orderby']        = 'post__in';
			$args['posts_per_page'] = max( count( $ids ), 1 );
			unset( $args['paged'] );
		} else {
			$args = self::apply_custom_filters( $args, $settings, $context_post_id );
		}

		$offset = absint( $settings['offset'] ?? 0 );
		if ( $offset && $source !== 'manual' ) {
			$args['offset'] = $offset + ( ( $page - 1 ) * $per_page );
			unset( $args['paged'] );
		}

		$args = self::apply_request_tax( $args, $settings );

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'canvasly-lite/query/args', $args, $settings, $page, $context_post_id );
			if ( is_array( $filtered ) ) {
				$args = $filtered;
			}
		}
		return $args;
	}

	/**
	 * get_terms arguments for a terms query.
	 *
	 * @param array $settings
	 * @return array
	 */
	public static function terms_args( array $settings ) {
		$tax = sanitize_key( (string) ( $settings['taxonomy'] ?? 'category' ) );
		if ( $tax === '' ) {
			$tax = 'category';
		}
		$orderby = sanitize_key( (string) ( $settings['terms_orderby'] ?? 'name' ) );
		$allowed = array( 'name', 'slug', 'count', 'term_id', 'id', 'parent' );
		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'name';
		}
		$args    = array(
			'taxonomy'   => $tax,
			'hide_empty' => ! isset( $settings['hide_empty'] ) || ! empty( $settings['hide_empty'] ),
			'orderby'    => $orderby,
			'order'      => strtoupper( (string) ( $settings['order'] ?? 'ASC' ) ) === 'DESC' ? 'DESC' : 'ASC',
		);
		$parent  = $settings['parent'] ?? '';
		if ( $parent !== '' && $parent !== null ) {
			$args['parent'] = absint( $parent );
		}
		$include = self::parse_ids( $settings['include'] ?? '' );
		if ( $include ) {
			$args['include'] = $include;
		}
		$exclude = self::parse_ids( $settings['exclude'] ?? '' );
		if ( $exclude ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Optional term-query exclusion from the Collection Loop widget.
			$args['exclude'] = $exclude;
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'canvasly-lite/query/term_args', $args, $settings );
			if ( is_array( $filtered ) ) {
				$args = $filtered;
			}
		}
		return $args;
	}

	/**
	 * Copy an optional taxonomy constraint onto loop settings.
	 *
	 * The loop REST route sets this from `taxonomy` and `terms`. It is not a
	 * WP_Query argument. `posts_args()` turns it into `tax_query` before
	 * `canvasly-lite/query/args` runs, so add-ons still see one query.
	 *
	 * @param array  $settings
	 * @param mixed  $taxonomy
	 * @param mixed  $terms    Term ids or slugs, comma separated.
	 * @return array
	 */
	public static function with_request_tax( array $settings, $taxonomy, $terms ) {
		$taxonomy = sanitize_key( (string) $taxonomy );
		$terms    = trim( (string) ( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $terms ) : wp_strip_all_tags( (string) $terms ) ) );
		if ( '' === $taxonomy || '' === $terms ) {
			return $settings;
		}
		$settings['lb_request_taxonomy'] = $taxonomy;
		$settings['lb_request_terms']    = $terms;
		return $settings;
	}

	/** Comma / space / pipe separated positive integers. */
	public static function parse_ids( $value ) {
		$out = array();
		foreach ( preg_split( '/[,\s|]+/', (string) $value ) as $part ) {
			$id = absint( $part );
			if ( $id ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function run_posts( array $settings, $page, $context_post_id ) {
		$empty = self::empty_result( 'posts', $page, self::per_page( $settings ) );
		if ( ! class_exists( '\\WP_Query' ) ) {
			return $empty;
		}
		$q = new \WP_Query( self::posts_args( $settings, $page, $context_post_id ) );
		$found    = absint( $q->found_posts );
		$per_page = max( 1, absint( $q->get( 'posts_per_page' ) ?: self::per_page( $settings ) ) );
		$max      = max( 1, (int) $q->max_num_pages );
		if ( $found && $per_page ) {
			$max = max( 1, (int) ceil( $found / $per_page ) );
		}
		return array(
			'kind'      => 'posts',
			'items'     => is_array( $q->posts ) ? $q->posts : array(),
			'found'     => $found,
			'max_pages' => $max,
			'page'      => $page,
			'per_page'  => $per_page,
		);
	}

	private static function run_terms( array $settings, $page ) {
		$per_page = self::per_page( $settings );
		$empty    = self::empty_result( 'terms', $page, $per_page );
		if ( ! function_exists( 'get_terms' ) ) {
			return $empty;
		}
		$args = self::terms_args( $settings );
		if ( function_exists( 'wp_count_terms' ) ) {
			$count_args = $args;
			unset( $count_args['number'], $count_args['offset'], $count_args['paged'] );
			$found = wp_count_terms( $count_args );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $found ) ) {
				return $empty;
			}
			$found = absint( $found );
			$max   = max( 1, (int) ceil( max( 1, $found ) / $per_page ) );
			if ( $found === 0 ) {
				return self::empty_result( 'terms', $page, $per_page );
			}
			$args['number'] = $per_page;
			$args['offset'] = ( $page - 1 ) * $per_page;
			$terms          = get_terms( $args );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) {
				return $empty;
			}
			$terms = is_array( $terms ) ? array_values( $terms ) : array();
			return array(
				'kind'      => 'terms',
				'items'     => $terms,
				'found'     => $found,
				'max_pages' => $max,
				'page'      => min( $page, $max ),
				'per_page'  => $per_page,
			);
		}
		$terms = get_terms( $args );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) {
			return $empty;
		}
		$terms    = is_array( $terms ) ? array_values( $terms ) : array();
		$found    = count( $terms );
		$max      = max( 1, (int) ceil( $found / $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$slice    = array_slice( $terms, $offset, $per_page );
		return array(
			'kind'      => 'terms',
			'items'     => $slice,
			'found'     => $found,
			'max_pages' => $max,
			'page'      => min( $page, $max ),
			'per_page'  => $per_page,
		);
	}

	/**
	 * AND an optional request taxonomy onto the loop query.
	 *
	 * @param array $args
	 * @param array $settings
	 * @return array
	 */
	private static function apply_request_tax( array $args, array $settings ) {
		$tax = sanitize_key( (string) ( $settings['lb_request_taxonomy'] ?? '' ) );
		$raw = trim( (string) ( $settings['lb_request_terms'] ?? '' ) );
		if ( '' === $tax || '' === $raw ) {
			return $args;
		}
		if ( function_exists( 'taxonomy_exists' ) && ! taxonomy_exists( $tax ) ) {
			return $args;
		}
		if ( function_exists( 'is_taxonomy_viewable' ) && function_exists( 'get_taxonomy' ) ) {
			$object = get_taxonomy( $tax );
			if ( ! $object || ! is_taxonomy_viewable( $object ) ) {
				return $args;
			}
		}
		$clause = self::request_term_clause( $tax, $raw );
		if ( ! $clause ) {
			return $args;
		}
		$args['update_post_term_cache'] = true;
		if ( empty( $args['tax_query'] ) || ! is_array( $args['tax_query'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Optional loop REST taxonomy filter.
			$args['tax_query'] = array( $clause );
			return $args;
		}
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Keep the loop's own tax query and AND the request filter.
		$args['tax_query'] = array(
			'relation' => 'AND',
			$args['tax_query'],
			$clause,
		);
		return $args;
	}

	/**
	 * @param string $tax
	 * @param string $raw
	 * @return array|null
	 */
	private static function request_term_clause( $tax, $raw ) {
		$ids   = self::parse_ids( $raw );
		$slugs = array();
		foreach ( preg_split( '/[,\s|]+/', (string) $raw ) as $part ) {
			$part = sanitize_title( (string) $part );
			if ( '' !== $part && ! ctype_digit( $part ) ) {
				$slugs[] = $part;
			}
		}
		$clause = array(
			'taxonomy' => $tax,
			'operator' => 'IN',
		);
		if ( $ids ) {
			$clause['field'] = 'term_id';
			$clause['terms'] = $ids;
		} else {
			$clause['field'] = 'slug';
			$clause['terms'] = $slugs;
		}
		if ( empty( $clause['terms'] ) ) {
			return null;
		}
		return $clause;
	}

	private static function apply_custom_filters( array $args, array $settings, $context_post_id ) {
		$include = self::parse_ids( $settings['include'] ?? '' );
		if ( $include ) {
			$args['post__in'] = $include;
		}
		$exclude = self::parse_ids( $settings['exclude'] ?? '' );
		if ( ! empty( $settings['exclude_current'] ) && $context_post_id ) {
			$exclude[] = $context_post_id;
		}
		$exclude = array_values( array_unique( array_filter( $exclude ) ) );
		if ( $exclude ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Optional Collection Loop exclusion of selected/current posts.
			$args['post__not_in'] = $exclude;
		}

		$tax   = sanitize_key( (string) ( $settings['taxonomy'] ?? '' ) );
		$terms = trim( (string) ( $settings['terms'] ?? '' ) );
		if ( $tax !== '' && $terms !== '' ) {
			$ids    = self::parse_ids( $terms );
			$slugs  = array();
			foreach ( preg_split( '/[,\s|]+/', $terms ) as $part ) {
				$part = sanitize_title( (string) $part );
				if ( $part !== '' && ! ctype_digit( $part ) ) {
					$slugs[] = $part;
				}
			}
			$op = strtoupper( (string) ( $settings['terms_operator'] ?? 'IN' ) );
			if ( ! in_array( $op, array( 'IN', 'NOT IN', 'AND' ), true ) ) {
				$op = 'IN';
			}
			$tq = array(
				'taxonomy' => $tax,
				'operator' => $op,
			);
			if ( $ids ) {
				$tq['field']  = 'term_id';
				$tq['terms']  = $ids;
			} else {
				$tq['field'] = 'slug';
				$tq['terms'] = $slugs;
			}
			if ( ! empty( $tq['terms'] ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Optional Collection Loop term filter.
				$args['tax_query'] = array( $tq );
			}
		}

		$author = trim( (string) ( $settings['author'] ?? '' ) );
		if ( $author === 'current' && function_exists( 'get_current_user_id' ) ) {
			$uid = absint( get_current_user_id() );
			if ( $uid ) {
				$args['author'] = $uid;
			}
		} elseif ( $author !== '' ) {
			$args['author'] = absint( $author );
		}

		$search = trim( (string) ( $settings['search'] ?? '' ) );
		if ( $search !== '' ) {
			$args['s'] = sanitize_text_field( $search );
		}

		$date = sanitize_key( (string) ( $settings['date'] ?? '' ) );
		if ( $date && $date !== 'none' ) {
			$map = array(
				'today' => '1 day ago',
				'week'  => '1 week ago',
				'month' => '1 month ago',
				'year'  => '1 year ago',
			);
			if ( isset( $map[ $date ] ) ) {
				$args['date_query'] = array(
					array(
						'after'     => $map[ $date ],
						'inclusive' => true,
					),
				);
			}
		}

		$meta_key = sanitize_key( (string) ( $settings['meta_key'] ?? '' ) );
		if ( $meta_key !== '' ) {
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Optional Collection Loop meta filter.
			$args['meta_key'] = $meta_key;
			if ( array_key_exists( 'meta_value', $settings ) && $settings['meta_value'] !== '' ) {
				$args['meta_value'] = sanitize_text_field( (string) $settings['meta_value'] );
			}
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			$compare = strtoupper( (string) ( $settings['meta_compare'] ?? '=' ) );
			$ok      = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS' );
			if ( in_array( $compare, $ok, true ) ) {
				$args['meta_compare'] = $compare;
			}
		}

		return $args;
	}

	private static function current_query_args( array $args, $page, $context_post_id ) {
		global $wp_query;
		if ( isset( $wp_query ) && is_object( $wp_query ) && ! empty( $wp_query->query_vars ) && function_exists( 'is_singular' ) && ! is_singular() ) {
			$vars          = $wp_query->query_vars;
			$vars['paged'] = $page;
			unset( $vars['pagename'], $vars['name'], $vars['page_id'], $vars['p'] );
			if ( empty( $vars['post_status'] ) ) {
				$vars['post_status'] = 'publish';
			}
			return $vars;
		}
		if ( $context_post_id && function_exists( 'get_post_type' ) ) {
			$type = get_post_type( $context_post_id );
			if ( $type ) {
				$args['post_type'] = self::allowed_post_type( $type );
			}
		}
		return $args;
	}

	private static function related_args( array $args, array $settings, $context_post_id ) {
		if ( ! $context_post_id || ! function_exists( 'wp_get_post_terms' ) ) {
			$args['post__in'] = array( 0 );
			return $args;
		}
		$tax = sanitize_key( (string) ( $settings['taxonomy'] ?? 'category' ) );
		if ( $tax === '' ) {
			$tax = 'category';
		}
		$terms = wp_get_post_terms( $context_post_id, $tax, array( 'fields' => 'ids' ) );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) {
			$terms = array();
		}
		$terms = array_filter( array_map( 'absint', (array) $terms ) );
		if ( ! $terms ) {
			$args['post__in'] = array( 0 );
			return $args;
		}
		$args['post__not_in'] = array( $context_post_id ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Related posts must omit the current post.
		$args['tax_query']    = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Related posts share taxonomy terms with the current post.
			array(
				'taxonomy' => $tax,
				'field'    => 'term_id',
				'terms'    => $terms,
			),
		);
		return $args;
	}

	private static function allowed_post_type( $type ) {
		$type = sanitize_key( (string) $type );
		if ( $type === '' || $type === 'any' ) {
			return $type === 'any' ? 'any' : 'post';
		}
		if ( class_exists( '\\CanvaslyLite\\Document\\Documents' ) ) {
			$excluded = \CanvaslyLite\Document\Documents::excluded();
			if ( in_array( $type, $excluded, true ) ) {
				return 'post';
			}
		}
		if ( function_exists( 'post_type_exists' ) && ! post_type_exists( $type ) ) {
			return 'post';
		}
		return $type;
	}

	private static function skip_found_rows( array $settings ) {
		$pag = sanitize_key( (string) ( $settings['pagination'] ?? 'none' ) );
		return $pag === '' || $pag === 'none';
	}

	private static function needs_meta_cache( array $settings ) {
		if ( sanitize_key( (string) ( $settings['meta_key'] ?? '' ) ) !== '' ) {
			return true;
		}
		$orderby = self::orderby( $settings );
		return in_array( $orderby, array( 'meta_value', 'meta_value_num' ), true );
	}

	private static function needs_term_cache( array $settings ) {
		if ( self::source( $settings ) === 'related' ) {
			return true;
		}
		$tax   = sanitize_key( (string) ( $settings['taxonomy'] ?? '' ) );
		$terms = trim( (string) ( $settings['terms'] ?? '' ) );
		return $tax !== '' && $terms !== '';
	}

	private static function source( array $settings ) {
		$s = sanitize_key( (string) ( $settings['source'] ?? 'custom' ) );
		return in_array( $s, array( 'custom', 'current', 'related', 'manual' ), true ) ? $s : 'custom';
	}

	private static function per_page( array $settings ) {
		$n = absint( $settings['posts_per_page'] ?? 6 );
		return max( 1, min( 100, $n ? $n : 6 ) );
	}

	private static function orderby( array $settings ) {
		$o       = sanitize_key( (string) ( $settings['orderby'] ?? 'date' ) );
		$allowed = array( 'date', 'title', 'menu_order', 'rand', 'modified', 'comment_count', 'meta_value', 'meta_value_num', 'ID' );
		return in_array( $o, $allowed, true ) ? $o : 'date';
	}

	private static function empty_result( $kind, $page, $per_page ) {
		return array(
			'kind'      => $kind,
			'items'     => array(),
			'found'     => 0,
			'max_pages' => 1,
			'page'      => max( 1, absint( $page ) ),
			'per_page'  => max( 1, absint( $per_page ) ),
		);
	}

	private static function normalize_result( $result, $kind, $page ) {
		if ( ! is_array( $result ) ) {
			return self::empty_result( $kind, $page, 6 );
		}
		$items = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
		$found = absint( $result['found'] ?? count( $items ) );
		$per   = max( 1, absint( $result['per_page'] ?? 6 ) );
		$max   = max( 1, absint( $result['max_pages'] ?? 1 ) );
		return array(
			'kind'      => ( ( $result['kind'] ?? $kind ) === 'terms' ) ? 'terms' : 'posts',
			'items'     => $items,
			'found'     => $found,
			'max_pages' => $max,
			'page'      => max( 1, absint( $result['page'] ?? $page ) ),
			'per_page'  => $per,
		);
	}
}
