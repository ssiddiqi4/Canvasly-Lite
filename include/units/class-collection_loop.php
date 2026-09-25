<?php
namespace CanvaslyLite\Units;

use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Document\Documents;
use CanvaslyLite\Query\Query;
use CanvaslyLite\Rendering\FrontendRenderer;
use CanvaslyLite\Utils\Style;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collection Loop: query posts/CPTs or terms and repeat an item template
 * (inline children or a saved template) with optional pagination.
 */
class CollectionLoop extends Unit {
	/** @var int */
	private static $depth = 0;

	public function type() {
		return 'collection_loop';
	}
	public function scripts( $settings = array() ) {
		$s = is_array( $settings ) ? $settings : array();
		if ( ( $s['pagination'] ?? '' ) === 'load_more' || ( $s['layout'] ?? '' ) === 'carousel' ) {
			return $this->frontend_scripts();
		}
		return array();
	}
	public function uses_button() {
		return true;
	}
	public function title() {
		return __( 'Collection Loop', 'canvasly-lite' );
	}
	public function icon() {
		return '↻';
	}
	public function category() {
		return 'advanced';
	}
	public function keywords() {
		return array( 'loop', 'collection', 'posts', 'query', 'grid', 'archive', 'cpt', 'terms', 'pagination' );
	}
	public function supports_children() {
		return true;
	}

	public function defaults() {
		return array(
			'query_type'       => 'posts',
			'source'           => 'custom',
			'post_type'        => 'post',
			'posts_per_page'   => 6,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'offset'           => 0,
			'ignore_sticky'    => true,
			'exclude_current'  => true,
			'taxonomy'         => 'category',
			'terms'            => '',
			'terms_operator'   => 'IN',
			'author'           => '',
			'date'             => '',
			'search'           => '',
			'include'          => '',
			// phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Widget setting defaults, not a WP_Query.
			'exclude'          => '',
			'meta_key'         => '',
			'meta_value'       => '',
			// phpcs:enable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare'     => '=',
			'hide_empty'       => true,
			'parent'           => '',
			'terms_orderby'    => 'name',
			'item_source'      => 'inline',
			'template_id'      => 0,
			'layout'           => 'grid',
			'columns'          => 3,
			'carousel_show'    => 3,
			'carousel_scroll'  => 1,
			'carousel_nav'     => 'arrows',
			'carousel_autoplay'=> false,
			'carousel_pause'   => true,
			'carousel_interval'=> 5000,
			'carousel_loop'    => true,
			'carousel_speed'   => 500,
			'static_enable'    => false,
			'static_template_id' => 0,
			'static_position'  => 2,
			'static_column_span' => 1,
			'static_repeat'    => 'once',
			'column_gap'       => 20,
			'row_gap'          => 20,
			'equal_height'     => false,
			'pagination'       => 'none',
			'page_limit'       => 0,
			'load_more_text'   => __( 'Load more', 'canvasly-lite' ),
			'prev_text'        => __( 'Previous', 'canvasly-lite' ),
			'next_text'        => __( 'Next', 'canvasly-lite' ),
			'empty_message'    => __( 'No items found.', 'canvasly-lite' ),
			'item_background'  => '',
			'item_padding'     => array(
				'top'    => '0',
				'right'  => '0',
				'bottom' => '0',
				'left'   => '0',
				'linked' => true,
			),
			'item_radius'      => array(
				'top'    => '0',
				'right'  => '0',
				'bottom' => '0',
				'left'   => '0',
				'linked' => true,
			),
			'pag_color'        => '',
			'pag_active_color' => '',
			'pag_background'   => '',
		);
	}

	public function controls() {
		$query = __( 'Query', 'canvasly-lite' );
		$item  = __( 'Item Template', 'canvasly-lite' );
		$lay   = __( 'Layout', 'canvasly-lite' );
		$pag   = __( 'Pagination', 'canvasly-lite' );
		$items = __( 'Items', 'canvasly-lite' );
		$w     = '{{WRAPPER}}';
		$grid  = $w . ' .lb-loop-items';
		$card  = $w . ' .lb-loop-item';
		$nav   = $w . ' .lb-loop-pagination';

		return array(
			'query_type'      => $this->ctrl( 'select', __( 'Query Type', 'canvasly-lite' ), 'content', $query, array(
				'options' => array(
					'posts' => __( 'Posts / CPT', 'canvasly-lite' ),
					'terms' => __( 'Terms', 'canvasly-lite' ),
				),
			) ),
			'source'          => $this->ctrl( 'select', __( 'Source', 'canvasly-lite' ), 'content', $query, array(
				'options'   => array(
					'custom'  => __( 'Custom query', 'canvasly-lite' ),
					'current' => __( 'Current query', 'canvasly-lite' ),
					'related' => __( 'Related', 'canvasly-lite' ),
					'manual'  => __( 'Manual selection', 'canvasly-lite' ),
				),
				'condition' => array( 'query_type' => 'posts' ),
			) ),
			'post_type'       => $this->ctrl( 'select', __( 'Post Type', 'canvasly-lite' ), 'content', $query, array(
				'options'   => self::post_type_options(),
				'condition' => array( 'query_type' => 'posts', 'source' => array( 'custom', 'related', 'manual' ) ),
			) ),
			'posts_per_page'  => $this->ctrl( 'number', __( 'Items Per Page', 'canvasly-lite' ), 'content', $query, array(
				'range' => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
			) ),
			'orderby'         => $this->ctrl( 'select', __( 'Order By', 'canvasly-lite' ), 'content', $query, array(
				'options'   => array(
					'date'           => __( 'Date', 'canvasly-lite' ),
					'title'          => __( 'Title', 'canvasly-lite' ),
					'menu_order'     => __( 'Menu order', 'canvasly-lite' ),
					'modified'       => __( 'Last modified', 'canvasly-lite' ),
					'comment_count'  => __( 'Comment count', 'canvasly-lite' ),
					'rand'           => __( 'Random', 'canvasly-lite' ),
					'ID'             => __( 'ID', 'canvasly-lite' ),
					// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- WP_Query orderby option labels, not a meta query.
					'meta_value'     => __( 'Meta value', 'canvasly-lite' ),
					'meta_value_num' => __( 'Meta value (numeric)', 'canvasly-lite' ),
					// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				),
				'condition' => array( 'query_type' => 'posts', 'source!' => 'manual' ),
			) ),
			'order'           => $this->ctrl( 'select', __( 'Order', 'canvasly-lite' ), 'content', $query, array(
				'options'   => array(
					'DESC' => __( 'Descending', 'canvasly-lite' ),
					'ASC'  => __( 'Ascending', 'canvasly-lite' ),
				),
				'condition' => array( 'source!' => 'manual' ),
			) ),
			'offset'          => $this->ctrl( 'number', __( 'Offset', 'canvasly-lite' ), 'content', $query, array(
				'range'     => array( 'min' => 0, 'max' => 200, 'step' => 1 ),
				'condition' => array( 'query_type' => 'posts', 'source' => 'custom' ),
			) ),
			'ignore_sticky'   => $this->ctrl( 'switch', __( 'Ignore Sticky Posts', 'canvasly-lite' ), 'content', $query, array(
				'condition' => array( 'query_type' => 'posts', 'source' => 'custom' ),
			) ),
			'exclude_current' => $this->ctrl( 'switch', __( 'Exclude Current Post', 'canvasly-lite' ), 'content', $query, array(
				'condition' => array( 'query_type' => 'posts', 'source' => 'custom' ),
			) ),
			'taxonomy'        => $this->ctrl( 'select', __( 'Taxonomy', 'canvasly-lite' ), 'content', $query, array(
				'options' => self::taxonomy_options(),
			) ),
			'terms'           => $this->ctrl( 'text', __( 'Terms', 'canvasly-lite' ), 'content', $query, array(
				'placeholder' => __( 'IDs or slugs, comma separated', 'canvasly-lite' ),
				'condition'   => array( 'query_type' => 'posts', 'source' => array( 'custom', 'related' ) ),
				'description' => __( 'Filter posts by these terms. Related uses the current post terms in this taxonomy.', 'canvasly-lite' ),
			) ),
			'terms_operator'  => $this->ctrl( 'select', __( 'Terms Operator', 'canvasly-lite' ), 'content', $query, array(
				'options'   => array(
					'IN'     => __( 'In', 'canvasly-lite' ),
					'NOT IN' => __( 'Not in', 'canvasly-lite' ),
					'AND'    => __( 'And', 'canvasly-lite' ),
				),
				'condition' => array( 'query_type' => 'posts', 'source' => 'custom' ),
			) ),
			'author'          => $this->ctrl( 'text', __( 'Author', 'canvasly-lite' ), 'content', $query, array(
				'placeholder' => __( 'User ID or “current”', 'canvasly-lite' ),
				'condition'   => array( 'query_type' => 'posts', 'source' => 'custom' ),
			) ),
			'date'            => $this->ctrl( 'select', __( 'Date', 'canvasly-lite' ), 'content', $query, array(
				'options'   => array(
					''      => __( 'Any time', 'canvasly-lite' ),
					'today' => __( 'Today', 'canvasly-lite' ),
					'week'  => __( 'This week', 'canvasly-lite' ),
					'month' => __( 'This month', 'canvasly-lite' ),
					'year'  => __( 'This year', 'canvasly-lite' ),
				),
				'condition' => array( 'query_type' => 'posts', 'source' => 'custom' ),
			) ),
			'search'          => $this->ctrl( 'text', __( 'Search', 'canvasly-lite' ), 'content', $query, array(
				'condition' => array( 'query_type' => 'posts', 'source' => 'custom' ),
				'dynamic'   => true,
			) ),
			'include'         => $this->ctrl( 'text', __( 'Include IDs', 'canvasly-lite' ), 'content', $query, array(
				'placeholder' => '12, 34, 56',
			) ),
			// phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Control names for Collection Loop settings, not a WP_Query.
			'exclude'         => $this->ctrl( 'text', __( 'Exclude IDs', 'canvasly-lite' ), 'content', $query, array(
				'placeholder' => '12, 34',
				'condition'   => array( 'source!' => 'manual' ),
			) ),
			'meta_key'        => $this->ctrl( 'text', __( 'Meta Key', 'canvasly-lite' ), 'content', $query, array(
				'condition' => array( 'query_type' => 'posts', 'source' => 'custom' ),
			) ),
			'meta_value'      => $this->ctrl( 'text', __( 'Meta Value', 'canvasly-lite' ), 'content', $query, array(
				'condition' => array( 'query_type' => 'posts', 'source' => 'custom', 'meta_key!' => '' ),
			) ),
			// phpcs:enable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare'    => $this->ctrl( 'select', __( 'Meta Compare', 'canvasly-lite' ), 'content', $query, array(
				'options'   => array(
					'='           => '=',
					'!='          => '!=',
					'>'           => '>',
					'>='          => '>=',
					'<'           => '<',
					'<='          => '<=',
					'LIKE'        => 'LIKE',
					'NOT LIKE'    => 'NOT LIKE',
					'IN'          => 'IN',
					'NOT IN'      => 'NOT IN',
					'EXISTS'      => 'EXISTS',
					'NOT EXISTS'  => 'NOT EXISTS',
				),
				'condition' => array( 'query_type' => 'posts', 'source' => 'custom', 'meta_key!' => '' ),
			) ),
			'hide_empty'      => $this->ctrl( 'switch', __( 'Hide Empty Terms', 'canvasly-lite' ), 'content', $query, array(
				'condition' => array( 'query_type' => 'terms' ),
			) ),
			'parent'          => $this->ctrl( 'number', __( 'Parent Term', 'canvasly-lite' ), 'content', $query, array(
				'condition' => array( 'query_type' => 'terms' ),
				'description' => __( 'Only direct children of this term ID. Leave empty for all.', 'canvasly-lite' ),
			) ),
			'terms_orderby'   => $this->ctrl( 'select', __( 'Terms Order By', 'canvasly-lite' ), 'content', $query, array(
				'options'   => array(
					'name'    => __( 'Name', 'canvasly-lite' ),
					'slug'    => __( 'Slug', 'canvasly-lite' ),
					'count'   => __( 'Count', 'canvasly-lite' ),
					'term_id' => __( 'ID', 'canvasly-lite' ),
				),
				'condition' => array( 'query_type' => 'terms' ),
			) ),
			'item_source'     => $this->ctrl( 'select', __( 'Item Template', 'canvasly-lite' ), 'content', $item, array(
				'options' => array(
					'inline'   => __( 'Inline children', 'canvasly-lite' ),
					'template' => __( 'Saved template', 'canvasly-lite' ),
				),
				'description' => __( 'Inline: drop heading, image and other widgets into the loop. Bind dynamic tags to post or term fields.', 'canvasly-lite' ),
			) ),
			'template_id'     => $this->ctrl( 'select', __( 'Saved Template', 'canvasly-lite' ), 'content', $item, array(
				'options'   => self::template_options(),
				'condition' => array( 'item_source' => 'template' ),
			) ),
			'layout'          => $this->ctrl( 'choose', __( 'Layout', 'canvasly-lite' ), 'content', $lay, array(
				'options' => array(
					'grid'     => __( 'Grid', 'canvasly-lite' ),
					'list'     => __( 'List', 'canvasly-lite' ),
					'carousel' => __( 'Carousel', 'canvasly-lite' ),
				),
			) ),
			'carousel_show'   => $this->ctrl( 'slider', __( 'Slides to Show', 'canvasly-lite' ), 'content', $lay, array(
				'responsive'  => true,
				'units'       => array(),
				'range'       => array( 'min' => 1, 'max' => 8, 'step' => 1 ),
				'condition'   => array( 'layout' => 'carousel' ),
				'description' => __( 'Loop items slide inside this widget. Arrows do not open the saved template or reload the page.', 'canvasly-lite' ),
			) ),
			'carousel_scroll' => $this->ctrl( 'slider', __( 'Slides to Scroll', 'canvasly-lite' ), 'content', $lay, array(
				'units'     => array(),
				'range'     => array( 'min' => 1, 'max' => 8, 'step' => 1 ),
				'condition' => array( 'layout' => 'carousel' ),
			) ),
			'carousel_nav'    => $this->ctrl( 'select', __( 'Navigation', 'canvasly-lite' ), 'content', $lay, array(
				'options'   => array(
					'arrows' => __( 'Arrows', 'canvasly-lite' ),
					'dots'   => __( 'Dots', 'canvasly-lite' ),
					'both'   => __( 'Arrows and dots', 'canvasly-lite' ),
					'none'   => __( 'None', 'canvasly-lite' ),
				),
				'condition' => array( 'layout' => 'carousel' ),
			) ),
			'carousel_loop'   => $this->ctrl( 'switch', __( 'Infinite Loop', 'canvasly-lite' ), 'content', $lay, array(
				'condition' => array( 'layout' => 'carousel' ),
			) ),
			'carousel_autoplay' => $this->ctrl( 'switch', __( 'Autoplay', 'canvasly-lite' ), 'content', $lay, array(
				'condition' => array( 'layout' => 'carousel' ),
			) ),
			'carousel_pause'  => $this->ctrl( 'switch', __( 'Pause on Hover', 'canvasly-lite' ), 'content', $lay, array(
				'condition' => array( 'layout' => 'carousel', 'carousel_autoplay' => true ),
			) ),
			'carousel_interval' => $this->ctrl( 'number', __( 'Autoplay Speed', 'canvasly-lite' ), 'content', $lay, array(
				'range'     => array( 'min' => 500, 'max' => 15000, 'step' => 100 ),
				'condition' => array( 'layout' => 'carousel', 'carousel_autoplay' => true ),
			) ),
			'carousel_speed'  => $this->ctrl( 'number', __( 'Transition Speed', 'canvasly-lite' ), 'content', $lay, array(
				'range'     => array( 'min' => 0, 'max' => 3000, 'step' => 50 ),
				'condition' => array( 'layout' => 'carousel' ),
			) ),
			'static_enable'   => $this->ctrl( 'switch', __( 'Static Item Position', 'canvasly-lite' ), 'content', __( 'Static Item', 'canvasly-lite' ), array(
				'description' => __( 'Insert a saved template into the loop at a fixed position. The post that was in that cell moves to the next cell.', 'canvasly-lite' ),
			) ),
			'static_template_id' => $this->ctrl( 'select', __( 'Static Template', 'canvasly-lite' ), 'content', __( 'Static Item', 'canvasly-lite' ), array(
				'options'   => self::template_options(),
				'condition' => array( 'static_enable' => true ),
			) ),
			'static_position' => $this->ctrl( 'number', __( 'Position', 'canvasly-lite' ), 'content', __( 'Static Item', 'canvasly-lite' ), array(
				'range'       => array( 'min' => 1, 'max' => 50, 'step' => 1 ),
				'condition'   => array( 'static_enable' => true ),
				'description' => __( '1 is the first cell. Position 2 places the static item second and shifts that post forward.', 'canvasly-lite' ),
			) ),
			'static_column_span' => $this->ctrl( 'slider', __( 'Column Span', 'canvasly-lite' ), 'content', __( 'Static Item', 'canvasly-lite' ), array(
				'units'     => array(),
				'range'     => array( 'min' => 1, 'max' => 8, 'step' => 1 ),
				'condition' => array( 'static_enable' => true, 'layout' => 'grid' ),
			) ),
			'static_repeat'   => $this->ctrl( 'select', __( 'Repeat', 'canvasly-lite' ), 'content', __( 'Static Item', 'canvasly-lite' ), array(
				'options'   => array(
					'once'   => __( 'Once', 'canvasly-lite' ),
					'repeat' => __( 'Repeat at this position', 'canvasly-lite' ),
				),
				'condition' => array( 'static_enable' => true ),
			) ),
			'columns'         => $this->ctrl( 'slider', __( 'Columns', 'canvasly-lite' ), 'content', $lay, array(
				'responsive' => true,
				'units'      => array(),
				'range'      => array( 'min' => 1, 'max' => 8, 'step' => 1 ),
				'condition'  => array( 'layout' => 'grid' ),
				'selectors'  => array( $grid => '--lb-loop-cols: {{SIZE}};' ),
			) ),
			'column_gap'      => $this->ctrl( 'slider', __( 'Column Gap', 'canvasly-lite' ), 'style', $lay, array(
				'responsive' => true,
				'units'      => array( 'px', 'em', 'rem', '%' ),
				'range'      => array( 'min' => 0, 'max' => 80 ),
				'selectors'  => array( $grid => 'column-gap: {{SIZE}}{{UNIT}};' ),
			) ),
			'row_gap'         => $this->ctrl( 'slider', __( 'Row Gap', 'canvasly-lite' ), 'style', $lay, array(
				'responsive' => true,
				'units'      => array( 'px', 'em', 'rem' ),
				'range'      => array( 'min' => 0, 'max' => 80 ),
				'selectors'  => array( $grid => 'row-gap: {{SIZE}}{{UNIT}};' ),
			) ),
			'equal_height'    => $this->ctrl( 'switch', __( 'Equal Height', 'canvasly-lite' ), 'style', $lay, array(
				'condition' => array( 'layout' => 'grid' ),
			) ),
			'pagination'      => $this->ctrl( 'select', __( 'Pagination', 'canvasly-lite' ), 'content', $pag, array(
				'options' => array(
					'none'      => __( 'None', 'canvasly-lite' ),
					'numbers'   => __( 'Numbers', 'canvasly-lite' ),
					'prev_next' => __( 'Previous / Next', 'canvasly-lite' ),
					'load_more' => __( 'Load more', 'canvasly-lite' ),
				),
			) ),
			'page_limit'      => $this->ctrl( 'number', __( 'Page Limit', 'canvasly-lite' ), 'content', $pag, array(
				'range'       => array( 'min' => 0, 'max' => 50, 'step' => 1 ),
				'condition'   => array( 'pagination' => array( 'numbers', 'prev_next', 'load_more' ) ),
				'description' => __( '0 = no limit.', 'canvasly-lite' ),
			) ),
			'load_more_text'  => $this->ctrl( 'text', __( 'Load More Text', 'canvasly-lite' ), 'content', $pag, array(
				'condition' => array( 'pagination' => 'load_more' ),
			) ),
			'prev_text'       => $this->ctrl( 'text', __( 'Previous Label', 'canvasly-lite' ), 'content', $pag, array(
				'condition' => array( 'pagination' => array( 'numbers', 'prev_next' ) ),
			) ),
			'next_text'       => $this->ctrl( 'text', __( 'Next Label', 'canvasly-lite' ), 'content', $pag, array(
				'condition' => array( 'pagination' => array( 'numbers', 'prev_next' ) ),
			) ),
			'empty_message'   => $this->ctrl( 'textarea', __( 'Nothing Found Message', 'canvasly-lite' ), 'content', $pag, array() ),
			'item_background' => $this->ctrl( 'color', __( 'Item Background', 'canvasly-lite' ), 'style', $items, array(
				'selectors' => array( $card => 'background-color: {{VALUE}};' ),
			) ),
			'item_padding'    => $this->ctrl( 'dimensions', __( 'Item Padding', 'canvasly-lite' ), 'style', $items, array(
				'selectors' => array( $card => 'padding: {{VALUE}};' ),
			) ),
			'item_radius'     => $this->ctrl( 'dimensions', __( 'Item Radius', 'canvasly-lite' ), 'style', $items, array(
				'selectors' => array( $card => 'border-radius: {{VALUE}};' ),
			) ),
			'pag_color'       => $this->ctrl( 'color', __( 'Color', 'canvasly-lite' ), 'style', $pag, array(
				'selectors' => array( $nav . ' a,' . $nav . ' span,' . $nav . ' button' => 'color: {{VALUE}};' ),
			) ),
			'pag_active_color'=> $this->ctrl( 'color', __( 'Active Color', 'canvasly-lite' ), 'style', $pag, array(
				'selectors' => array( $nav . ' .is-current' => 'color: {{VALUE}};' ),
			) ),
			'pag_background'  => $this->ctrl( 'color', __( 'Background', 'canvasly-lite' ), 'style', $pag, array(
				'selectors' => array( $nav . ' a,' . $nav . ' span,' . $nav . ' button' => 'background-color: {{VALUE}};' ),
			) ),
		);
	}

	public function render( $s, $children = '' ) {
		return '<div class="' . $this->cls( $s ) . ' lb-loop-placeholder">' . esc_html__( 'Collection Loop', 'canvasly-lite' ) . '</div>';
	}

	/**
	 * Render the loop: query items and repeat the item template with per-item dynamic context.
	 *
	 * @param array $s
	 * @param array $node
	 * @param int   $document_id
	 * @param int   $page Override (AJAX). 0 reads the request.
	 * @param bool  $items_only Skip wrapper/pagination (load-more fragments).
	 * @return string
	 */
	public function render_collection( $s, $node, $document_id = 0, $page = 0, $items_only = false ) {
		if ( self::$depth > 2 ) {
			return '';
		}
		self::$depth++;
		try {
			$s           = is_array( $s ) ? $s : array();
			$node        = is_array( $node ) ? $node : array();
			$document_id = absint( $document_id );
			$node_id     = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $node['id'] ?? '' ) );
			if ( $page < 1 ) {
				$page = Query::requested_page( $node_id );
			}
			$result = Query::run( $s, $page, $document_id );
			$limit  = absint( $s['page_limit'] ?? 0 );
			if ( $limit > 0 ) {
				$result['max_pages'] = max( 1, min( (int) $result['max_pages'], $limit ) );
			}
			$template = $this->item_nodes( $s, $node );
			$items    = '';
			$index    = 0;
			foreach ( $this->display_rows( $s, $result['items'] ) as $row ) {
				if ( ! empty( $row['static'] ) ) {
					$items .= $this->render_static_item( $s, $row['span'] );
					continue;
				}
				$items .= $this->render_item( $s, $template, $row['item'], $result['kind'], $node_id, $page, $index );
				$index++;
			}
			if ( $items_only ) {
				return $items;
			}
			if ( $items === '' ) {
				$msg = trim( (string) ( $s['empty_message'] ?? '' ) );
				$body = $msg !== ''
					? '<div class="lb-loop-empty">' . wp_kses_post( $msg ) . '</div>'
					: '<div class="lb-loop-empty">' . esc_html__( 'No items found.', 'canvasly-lite' ) . '</div>';
			} else {
				$body = '<div class="lb-loop-items">' . $items . '</div>';
			}
			$layout = sanitize_key( (string) ( $s['layout'] ?? 'grid' ) );
			if ( ! in_array( $layout, array( 'grid', 'list', 'carousel' ), true ) ) {
				$layout = 'grid';
			}
			$equal  = ! empty( $s['equal_height'] ) ? ' lb-loop-equal' : '';
			$cols   = $this->scalar( $s['columns'] ?? 3, 3 );
			$cols   = max( 1, min( 8, absint( $cols ) ?: 3 ) );
			$show   = $this->carousel_show( $s );
			$speed  = max( 0, min( 3000, absint( $s['carousel_speed'] ?? 500 ) ) );
			$pag    = $this->pagination_html( $s, $result, $node_id, $document_id );
			$attrs  = $this->style_attr(
				array(
					'--lb-loop-cols'  => (string) $cols,
					'--lb-loop-show'  => (string) $show,
					'--lb-loop-speed' => $speed . 'ms',
				)
			);
			$carousel = '';
			if ( 'carousel' === $layout && $items !== '' ) {
				$body     = $this->carousel_shell( $s, $items, $show );
				$carousel = $this->carousel_attrs( $s, $show );
			}
			return '<div class="' . $this->cls( $s ) . ' lb-loop lb-loop-' . $layout . $equal . '" data-lb-loop="1" data-lb-document="' . esc_attr( (string) $document_id ) . '" data-lb-node="' . esc_attr( $node_id ) . '" data-kind="' . esc_attr( $result['kind'] ) . '"' . $carousel . $attrs . '>' . $body . $pag . '</div>';
		} finally {
			self::$depth--;
		}
	}

	public function style_css( $id, $settings ) {
		$template_id = self::embedded_template_id( is_array( $settings ) ? $settings : array() );
		if ( ! $template_id ) {
			return '';
		}
		$doc = self::template_document( $template_id );
		if ( ! $doc || empty( $doc['root'] ) || ! class_exists( Style::class ) ) {
			return '';
		}
		$css = Style::nodes_css( $doc['root'] );
		if ( $css === '' ) {
			return '';
		}
		$safe = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $id );
		return (string) preg_replace( '/#lb-node-([a-zA-Z0-9_-]+)/', '#lb-node-' . $safe . ' .lb-src-$1', $css );
	}

	/** Locate a node by id in a tree. */
	public static function find_node( array $nodes, $id ) {
		$id = (string) $id;
		foreach ( $nodes as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			if ( (string) ( $n['id'] ?? '' ) === $id ) {
				return $n;
			}
			if ( ! empty( $n['children'] ) && is_array( $n['children'] ) ) {
				$found = self::find_node( $n['children'], $id );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/** @return array|null */
	public static function template_document( $id ) {
		$id = absint( $id );
		if ( ! $id || ! function_exists( 'get_post_type' ) || get_post_type( $id ) !== 'lb_template' ) {
			return null;
		}
		$raw = get_post_meta( $id, '_lb_template_data', true );
		$doc = class_exists( '\\CanvaslyLite\\Utils\\JsonCache' ) ? \CanvaslyLite\Utils\JsonCache::decode( $raw, null ) : ( is_string( $raw ) ? json_decode( $raw, true ) : $raw );
		if ( ! is_array( $doc ) ) {
			return null;
		}
		if ( class_exists( DocumentManager::class ) ) {
			$doc = DocumentManager::migrate( $doc );
		}
		return $doc;
	}

	/**
	 * Saved template this loop repeats.
	 *
	 * Inline loops return 0. `item_source` `template` returns `template_id`.
	 * `canvasly-lite/loop/template_id` can point the same slot at a loop-item
	 * template without a second renderer.
	 *
	 * @param array $settings
	 * @return int
	 */
	public static function embedded_template_id( array $settings ) {
		$id = 0;
		if ( ( $settings['item_source'] ?? '' ) === 'template' ) {
			$id = absint( $settings['template_id'] ?? 0 );
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'canvasly-lite/loop/template_id', $id, $settings );
			if ( is_numeric( $filtered ) ) {
				$id = absint( $filtered );
			}
		}
		return $id;
	}

	private function item_nodes( array $s, array $node ) {
		$template_id = self::embedded_template_id( $s );
		if ( $template_id ) {
			$doc = null;
			if ( class_exists( '\\CanvaslyLite\\Templates\\TemplateEmbed' ) ) {
				$doc = \CanvaslyLite\Templates\TemplateEmbed::document( $template_id );
			}
			if ( ! is_array( $doc ) ) {
				$doc = self::template_document( $template_id );
			}
			return ( is_array( $doc ) && ! empty( $doc['root'] ) && is_array( $doc['root'] ) ) ? $doc['root'] : array();
		}
		return is_array( $node['children'] ?? null ) ? $node['children'] : array();
	}

	private function render_item( array $s, array $template, $item, $kind, $node_id, $page, $index ) {
		if ( function_exists( 'do_action' ) ) {
			/** Fires before one loop item renders. Pro pushes the loop post here. @param mixed $item Post or term. @param string $kind posts|terms */
			do_action( 'canvasly-lite/loop/before_item', $item, $kind );
		}
		try {
			return $this->render_item_markup( $s, $template, $item, $kind, $node_id, $page, $index );
		} finally {
			if ( function_exists( 'do_action' ) ) {
				/** Fires after one loop item renders. @param mixed $item @param string $kind */
				do_action( 'canvasly-lite/loop/after_item', $item, $kind );
			}
		}
	}

	private function render_item_markup( array $s, array $template, $item, $kind, $node_id, $page, $index ) {
		if ( ! $template ) {
			return '<article class="lb-loop-item"><div class="lb-embed-placeholder">' . esc_html__( 'Add an item template', 'canvasly-lite' ) . '</div></article>';
		}
		$suffix = $node_id . '-p' . absint( $page ) . 'i' . absint( $index );
		$html   = '';
		$meta   = '';
		if ( $kind === 'terms' && is_object( $item ) ) {
			$term_id = absint( $item->term_id ?? 0 );
			$ctx     = array(
				'post_id' => 0,
				'post'    => null,
				'term'    => $item,
				'term_id' => $term_id,
			);
			$html    = FrontendRenderer::render_nodes( $template, 0, $ctx, $suffix );
			$meta    = ' data-lb-term="' . esc_attr( (string) $term_id ) . '"';
		} else {
			$post = is_object( $item ) ? $item : null;
			$id   = $post && isset( $post->ID ) ? absint( $post->ID ) : absint( $item );
			$html = $this->with_post(
				$post,
				$id,
				static function () use ( $template, $id, $suffix ) {
					return FrontendRenderer::render_nodes( $template, $id, array(), $suffix );
				}
			);
			$meta = ' data-lb-post="' . esc_attr( (string) $id ) . '"';
		}
		return '<article class="lb-loop-item"' . $meta . '>' . $html . '</article>';
	}

	private function with_post( $post, $id, callable $fn ) {
		if ( ! $post && $id && function_exists( 'get_post' ) ) {
			$post = get_post( $id );
		}
		$prev = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		if ( $post ) {
			$GLOBALS['post'] = $post;
			if ( function_exists( 'setup_postdata' ) ) {
				setup_postdata( $post );
			}
		}
		try {
			return $fn();
		} finally {
			if ( function_exists( 'wp_reset_postdata' ) ) {
				wp_reset_postdata();
			}
			if ( $prev ) {
				$GLOBALS['post'] = $prev;
			} else {
				unset( $GLOBALS['post'] );
			}
		}
	}

	/**
	 * Query items plus an optional static template at a fixed cell.
	 *
	 * @param array $s
	 * @param array $items
	 * @return array<int,array>
	 */
	private function display_rows( array $s, array $items ) {
		$static   = ! empty( $s['static_enable'] ) && absint( $s['static_template_id'] ?? 0 ) > 0;
		$position = max( 1, absint( $s['static_position'] ?? 2 ) );
		$repeat   = ( $s['static_repeat'] ?? 'once' ) === 'repeat' && $position > 1;
		$span     = max( 1, min( 8, absint( $this->scalar( $s['static_column_span'] ?? 1, 1 ) ) ?: 1 ) );
		if ( ! $static ) {
			$rows = array();
			foreach ( $items as $item ) {
				$rows[] = array( 'item' => $item );
			}
			return $rows;
		}
		$rows  = array();
		$slot  = 1;
		$i     = 0;
		$count = count( $items );
		$guard = 0;
		while ( $i < $count && $guard < $count + 40 ) {
			$guard++;
			$hit = $slot === $position || ( $repeat && $slot > $position && ( ( $slot - $position ) % $position ) === 0 );
			if ( $hit ) {
				$rows[] = array(
					'static' => true,
					'span'   => $span,
				);
				$slot++;
				continue;
			}
			$rows[] = array( 'item' => $items[ $i ] );
			$i++;
			$slot++;
		}
		return $rows;
	}

	/**
	 * @param array $s
	 * @param int   $span
	 * @return string
	 */
	private function render_static_item( array $s, $span ) {
		$id    = absint( $s['static_template_id'] ?? 0 );
		$doc   = null;
		if ( $id && class_exists( '\\CanvaslyLite\\Templates\\TemplateEmbed' ) ) {
			$doc = \CanvaslyLite\Templates\TemplateEmbed::document( $id );
		}
		if ( ! is_array( $doc ) ) {
			$doc = self::template_document( $id );
		}
		$nodes = ( is_array( $doc ) && ! empty( $doc['root'] ) && is_array( $doc['root'] ) ) ? $doc['root'] : array();
		$html  = ( $nodes && class_exists( FrontendRenderer::class ) ) ? FrontendRenderer::render_nodes( $nodes, 0, array(), 'static' ) : '';
		if ( $html === '' ) {
			return '';
		}
		$span = max( 1, min( 8, absint( $span ) ) );
		return '<article class="lb-loop-item lb-loop-static" style="--lb-loop-span:' . esc_attr( (string) $span ) . '" data-lb-static="1">' . $html . '</article>';
	}

	/**
	 * @param array $s
	 * @return int
	 */
	private function carousel_show( array $s ) {
		$n = absint( $this->scalar( $s['carousel_show'] ?? 3, 3 ) );
		if ( $n < 1 ) {
			$n = 3;
		}
		return min( 8, $n );
	}

	/**
	 * @param array $s
	 * @param int   $show
	 * @return string
	 */
	private function carousel_attrs( array $s, $show ) {
		$scroll = absint( $this->scalar( $s['carousel_scroll'] ?? 1, 1 ) );
		if ( $scroll < 1 ) {
			$scroll = 1;
		}
		$scroll = min( $show, $scroll );
		$nav    = sanitize_key( (string) ( $s['carousel_nav'] ?? 'arrows' ) );
		if ( ! in_array( $nav, array( 'arrows', 'dots', 'both', 'none' ), true ) ) {
			$nav = 'arrows';
		}
		$tablet = $show;
		$mobile = 1;
		$raw    = $s['carousel_show'] ?? $show;
		if ( is_array( $raw ) ) {
			if ( isset( $raw['tablet'] ) && '' !== $raw['tablet'] && null !== $raw['tablet'] ) {
				$tablet = max( 1, min( 8, absint( is_array( $raw['tablet'] ) ? ( $raw['tablet']['size'] ?? $show ) : $raw['tablet'] ) ) );
			}
			if ( isset( $raw['mobile'] ) && '' !== $raw['mobile'] && null !== $raw['mobile'] ) {
				$mobile = max( 1, min( 8, absint( is_array( $raw['mobile'] ) ? ( $raw['mobile']['size'] ?? 1 ) : $raw['mobile'] ) ) );
			}
		}
		return ' data-lb-loop-carousel="1" data-show="' . esc_attr( (string) $show ) . '" data-show-tablet="' . esc_attr( (string) $tablet ) . '" data-show-mobile="' . esc_attr( (string) $mobile ) . '" data-scroll="' . esc_attr( (string) $scroll ) . '" data-nav="' . esc_attr( $nav ) . '" data-loop="' . ( empty( $s['carousel_loop'] ) ? '0' : '1' ) . '" data-autoplay="' . ( empty( $s['carousel_autoplay'] ) ? '0' : '1' ) . '" data-interval="' . esc_attr( (string) max( 500, absint( $s['carousel_interval'] ?? 5000 ) ) ) . '" data-pause-hover="' . ( empty( $s['carousel_pause'] ) ? '0' : '1' ) . '"';
	}

	/**
	 * @param array  $s
	 * @param string $items
	 * @param int    $show
	 * @return string
	 */
	private function carousel_shell( array $s, $items, $show ) {
		$nav    = sanitize_key( (string) ( $s['carousel_nav'] ?? 'arrows' ) );
		$scroll = max( 1, min( $show, absint( $this->scalar( $s['carousel_scroll'] ?? 1, 1 ) ) ?: 1 ) );
		$buttons = '';
		if ( in_array( $nav, array( 'arrows', 'both' ), true ) ) {
			$buttons = '<button type="button" class="lb-loop-arrow lb-loop-prev" data-lb-loop-dir="-1" aria-label="' . esc_attr__( 'Previous slide', 'canvasly-lite' ) . '">&lsaquo;</button>'
				. '<button type="button" class="lb-loop-arrow lb-loop-next" data-lb-loop-dir="1" aria-label="' . esc_attr__( 'Next slide', 'canvasly-lite' ) . '">&rsaquo;</button>';
		}
		$dots = '';
		if ( in_array( $nav, array( 'dots', 'both' ), true ) ) {
			$count = preg_match_all( '/<article class="lb-loop-item/', $items );
			$pages = max( 1, (int) ceil( max( 0, (int) $count - $show ) / $scroll ) + 1 );
			$dots  = '<div class="lb-loop-dots">';
			for ( $i = 0; $i < $pages; $i++ ) {
				$dots .= '<button type="button" class="lb-loop-dot' . ( 0 === $i ? ' is-active' : '' ) . '" data-lb-loop-page="' . esc_attr( (string) $i ) . '" aria-label="' . esc_attr( sprintf( /* translators: %d: slide number */ __( 'Go to slide %d', 'canvasly-lite' ), $i + 1 ) ) . '"></button>';
			}
			$dots .= '</div>';
		}
		return '<div class="lb-loop-viewport"><div class="lb-loop-track">' . $items . '</div></div>' . $buttons . $dots;
	}

	private function pagination_html( array $s, array $result, $node_id, $document_id ) {
		$type = sanitize_key( (string) ( $s['pagination'] ?? 'none' ) );
		if ( ! in_array( $type, array( 'numbers', 'prev_next', 'load_more' ), true ) ) {
			return '';
		}
		$max  = max( 1, absint( $result['max_pages'] ) );
		$page = max( 1, absint( $result['page'] ) );
		if ( $max < 2 && $type !== 'load_more' ) {
			return '';
		}
		if ( $type === 'load_more' ) {
			if ( $page >= $max ) {
				return '';
			}
			$label = trim( (string) ( $s['load_more_text'] ?? '' ) );
			if ( $label === '' ) {
				$label = __( 'Load more', 'canvasly-lite' );
			}
			$rest = function_exists( 'rest_url' ) ? rest_url( 'canvasly-lite/v1/loop' ) : '';
			return '<div class="lb-loop-pagination lb-loop-pagination-more"><button type="button" class="lb-loop-more" data-lb-loop-more="1" data-document="' . esc_attr( (string) $document_id ) . '" data-node="' . esc_attr( $node_id ) . '" data-page="' . esc_attr( (string) $page ) . '" data-max="' . esc_attr( (string) $max ) . '" data-rest="' . esc_url( $rest ) . '">' . esc_html( $label ) . '</button></div>';
		}
		$key  = Query::page_key( $node_id );
		$prev = trim( (string) ( $s['prev_text'] ?? '' ) );
		$next = trim( (string) ( $s['next_text'] ?? '' ) );
		if ( $prev === '' ) {
			$prev = __( 'Previous', 'canvasly-lite' );
		}
		if ( $next === '' ) {
			$next = __( 'Next', 'canvasly-lite' );
		}
		$out = '<nav class="lb-loop-pagination lb-loop-pagination-' . esc_attr( $type ) . '" aria-label="' . esc_attr__( 'Collection pagination', 'canvasly-lite' ) . '">';
		if ( $page > 1 ) {
			$out .= '<a class="lb-loop-page lb-loop-prev" href="' . esc_url( $this->page_url( $key, $page - 1 ) ) . '">' . esc_html( $prev ) . '</a>';
		} else {
			$out .= '<span class="lb-loop-page lb-loop-prev is-disabled" aria-disabled="true">' . esc_html( $prev ) . '</span>';
		}
		if ( $type === 'numbers' ) {
			for ( $i = 1; $i <= $max; $i++ ) {
				if ( $i === $page ) {
					$out .= '<span class="lb-loop-page is-current" aria-current="page">' . esc_html( (string) $i ) . '</span>';
				} else {
					$out .= '<a class="lb-loop-page" href="' . esc_url( $this->page_url( $key, $i ) ) . '">' . esc_html( (string) $i ) . '</a>';
				}
			}
		}
		if ( $page < $max ) {
			$out .= '<a class="lb-loop-page lb-loop-next" href="' . esc_url( $this->page_url( $key, $page + 1 ) ) . '">' . esc_html( $next ) . '</a>';
		} else {
			$out .= '<span class="lb-loop-page lb-loop-next is-disabled" aria-disabled="true">' . esc_html( $next ) . '</span>';
		}
		return $out . '</nav>';
	}

	private function page_url( $key, $page ) {
		$page = absint( $page );
		if ( ! function_exists( 'add_query_arg' ) ) {
			return '?' . rawurlencode( $key ) . '=' . $page;
		}
		if ( $page <= 1 ) {
			return remove_query_arg( $key );
		}
		return add_query_arg( $key, $page );
	}

	private static function post_type_options() {
		$opts = array(
			'post' => __( 'Posts', 'canvasly-lite' ),
			'page' => __( 'Pages', 'canvasly-lite' ),
			'any'  => __( 'Any public type', 'canvasly-lite' ),
		);
		if ( ! function_exists( 'get_post_types' ) ) {
			return $opts;
		}
		$excluded = class_exists( Documents::class ) ? Documents::excluded() : array( 'attachment', 'lb_template', 'lb_component' );
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( ! $type || in_array( $type->name, $excluded, true ) ) {
				continue;
			}
			$label = '';
			if ( isset( $type->labels ) && is_object( $type->labels ) && ! empty( $type->labels->name ) ) {
				$label = (string) $type->labels->name;
			} elseif ( ! empty( $type->label ) ) {
				$label = (string) $type->label;
			}
			$opts[ $type->name ] = $label !== '' ? $label : $type->name;
		}
		return $opts;
	}

	private static function taxonomy_options() {
		$opts = array(
			'category' => __( 'Categories', 'canvasly-lite' ),
			'post_tag' => __( 'Tags', 'canvasly-lite' ),
		);
		if ( ! function_exists( 'get_taxonomies' ) ) {
			return $opts;
		}
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			if ( ! $tax ) {
				continue;
			}
			$label = '';
			if ( isset( $tax->labels ) && is_object( $tax->labels ) && ! empty( $tax->labels->name ) ) {
				$label = (string) $tax->labels->name;
			} elseif ( ! empty( $tax->label ) ) {
				$label = (string) $tax->label;
			}
			$opts[ $tax->name ] = $label !== '' ? $label : $tax->name;
		}
		return $opts;
	}

	private static function template_options() {
		if ( class_exists( '\\CanvaslyLite\\Templates\\SavedTemplates' ) && method_exists( '\\CanvaslyLite\\Templates\\SavedTemplates', 'select_options' ) ) {
			return \CanvaslyLite\Templates\SavedTemplates::select_options();
		}
		$opts = array( '0' => __( 'Select a template', 'canvasly-lite' ) );
		if ( ! function_exists( 'get_posts' ) ) {
			return $opts;
		}
		$posts = get_posts(
			array(
				'post_type'              => 'lb_template',
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'lazy_load_term_meta'    => false,
			)
		);
		foreach ( $posts as $p ) {
			$type = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $p->ID, '_lb_template_type', true ) : '';
			$label = $p->post_title !== '' ? $p->post_title : ( '#' . $p->ID );
			if ( $type !== '' ) {
				if ( class_exists( '\\CanvaslyLite\\Templates\\SavedTemplates' ) ) {
					$type = \CanvaslyLite\Templates\SavedTemplates::type_label( $type );
				}
				$label .= ' (' . $type . ')';
			}
			$opts[ (string) $p->ID ] = $label;
		}
		return $opts;
	}
}
