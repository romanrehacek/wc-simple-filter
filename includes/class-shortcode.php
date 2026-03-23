<?php
/**
 * Shortcode [simple_product_filter] and PHP helper simple_product_filter().
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode class.
 *
 * Responsible for:
 * - registering the [simple_product_filter] shortcode
 * - parsing shortcode attributes
 * - loading filters from the database
 * - rendering filters through the template system
 */
class Shortcode {

	/**
	 * Maximum number of values displayed before "Show more".
	 */
	const DEFAULT_VALUES_VISIBLE = 5;

	/**
	 * Initializes the shortcode.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_shortcode( 'simple_product_filter', [ $this, 'render' ] );
		add_shortcode( 'wc_simple_filter', [ $this, 'render' ] );
		add_action( 'spf_render_filters', [ $this, 'render_from_action' ] );
	}

	/**
	 * Callback for the [simple_product_filter] shortcode.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array|string $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'layout'      => 'vertical',
				'filter_ids'  => '',
				'exclude_ids' => '',
				'collapsible' => 'true',
				'collapsed'   => 'true',
			],
			is_array( $atts ) ? $atts : [],
			'simple_product_filter'
		);

		return $this->render_filters( $atts );
	}

	/**
	 * Callback for action spf_render_filters (PHP helper).
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return void
	 */
	public function render_from_action( array $args = [] ): void {
		// Normalize args to the same format as shortcode atts.
		$atts = wp_parse_args(
			$args,
			[
				'layout'      => 'vertical',
				'filter_ids'  => '',
				'exclude_ids' => '',
				'collapsible' => 'true',
				'collapsed'   => 'true',
			]
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_filters( $atts );
	}

	/**
	 * Renders filters: main logic.
	 *
	 * @param array<string, mixed> $atts Attributes.
	 * @return string HTML output.
	 */
	private function render_filters( array $atts ): string {
		// Load filters from the database.
		$filters = $this->get_filters( $atts );

		if ( empty( $filters ) ) {
		/**
		 * Filter for HTML when there are no filters.
		 *
		 * @param string $html Empty HTML output.
		 */
			return (string) apply_filters( 'spf_no_filters_html', '' );
		}

		// Normalize and validate parameters.
		$layout      = in_array( $atts['layout'], [ 'sidebar', 'vertical', 'horizontal' ], true )
			? $atts['layout']
			: 'vertical';
		// 'vertical' is an alias for 'sidebar'.
		if ( 'vertical' === $layout ) {
			$layout = 'sidebar';
		}
		$collapsible = filter_var( $atts['collapsible'], FILTER_VALIDATE_BOOLEAN );
		$collapsed   = filter_var( $atts['collapsed'], FILTER_VALIDATE_BOOLEAN );

		/**
		 * Filter for the list of filters before rendering.
		 *
		 * @param array<int, array<string, mixed>> $filters  List of filters.
		 * @param string                           $layout   Layout type.
		 * @param array<string, mixed>             $atts     Shortcode attributes.
		 */
		$filters = (array) apply_filters( 'spf_render_filters_data', $filters, $layout, $atts );

		// Prepare template args.
		$template_args = [
			'filters'     => $filters,
			'layout'      => $layout,
			'collapsible' => $collapsible,
			'collapsed'   => $collapsed,
			'atts'        => $atts,
		];

		/**
		 * Action before the entire filters block.
		 *
		 * @param array<int, array<string, mixed>> $filters List of filters.
		 * @param string                           $layout  Layout type.
		 */
		do_action( 'spf_before_filters', $filters, $layout );

		$html = Template::get_template( 'filter-wrapper.php', $template_args, true );

		/**
		 * Action after the entire filters block.
		 *
		 * @param array<int, array<string, mixed>> $filters List of filters.
		 * @param string                           $layout  Layout type.
		 */
		do_action( 'spf_after_filters', $filters, $layout );

		return $html;
	}

	/**
	 * Loads filters from the database according to shortcode attributes.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_filters( array $atts ): array {
		$all_filters = Filter_Manager::get_all();

		if ( empty( $all_filters ) ) {
			return [];
		}

		// Filter by filter_ids.
		if ( ! empty( $atts['filter_ids'] ) ) {
			$ids         = array_map( 'absint', explode( ',', $atts['filter_ids'] ) );
			$all_filters = array_filter(
				$all_filters,
				static fn( $f ) => in_array( $f['id'], $ids, true )
			);
		}

		// Exclude by exclude_ids.
		if ( ! empty( $atts['exclude_ids'] ) ) {
			$exclude_ids = array_map( 'absint', explode( ',', $atts['exclude_ids'] ) );
			$all_filters = array_filter(
				$all_filters,
				static fn( $f ) => ! in_array( $f['id'], $exclude_ids, true )
			);
		}

		return array_values( $all_filters );
	}

	/**
	 * Renders one filter item and returns HTML.
	 * Called from template filter-wrapper.php.
	 *
	 * @param array<string, mixed> $filter      Filter data from database.
	 * @param string               $layout      Layout type ('sidebar' | 'horizontal').
	 * @param bool                 $collapsible Whether the filter is collapsible.
	 * @param bool                 $collapsed   Whether the filter is collapsed by default.
	 * @return string HTML output of one filter.
	 */
	public static function render_filter_item(
		array $filter,
		string $layout,
		bool $collapsible,
		bool $collapsed
	): string {
		/**
		 * Action before individual filter.
		 *
		 * @param array<string, mixed> $filter Filter data.
		 */
		do_action( 'spf_before_filter_item', $filter );

		$args = [
			'filter'      => $filter,
			'layout'      => $layout,
			'collapsible' => $collapsible,
			'collapsed'   => $collapsed,
			'values'      => self::get_filter_values( $filter ),
		];

		$html = Template::get_template( 'filter-item.php', $args, true );

		/**
		 * Filter for HTML of one filter.
		 *
		 * @param string               $html   HTML output.
		 * @param array<string, mixed> $filter Filter data.
		 */
		$html = (string) apply_filters( 'spf_filter_item_html', $html, $filter );

		/**
		 * Action after individual filter.
		 *
		 * @param array<string, mixed> $filter Filter data.
		 */
		do_action( 'spf_after_filter_item', $filter );

		return $html;
	}

	/**
	 * Loads and returns filter values (terms, meta values, ranges...).
	 *
	 * @param array<string, mixed> $filter Filter data from database.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_filter_values( array $filter ): array {
		$filter_type  = $filter['filter_type'] ?? '';
		$filter_style = $filter['filter_style'] ?? 'checkbox';
		$config       = $filter['config'] ?? [];
		$values       = [];

		// Ranges (radio/checkbox with manual ranges).
		if ( ! empty( $config['ranges'] ) && is_array( $config['ranges'] ) ) {
			$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
				? get_woocommerce_currency_symbol()
				: '';

			foreach ( $config['ranges'] as $range ) {
				$min_val = isset( $range['min'] ) && '' !== $range['min'] ? (float) $range['min'] : null;
				$max_val = isset( $range['max'] ) && '' !== $range['max'] ? (float) $range['max'] : null;

				// Generate label if not set.
				$label = isset( $range['label'] ) && '' !== trim( $range['label'] )
					? $range['label']
					: self::generate_range_label( $min_val, $max_val, $currency_symbol );

				$values[] = [
					'type'  => 'range',
					'label' => $label,
					'min'   => $min_val,
					'max'   => $max_val,
					'slug'  => 'range_' . ( $min_val ?? '0' ) . '_' . ( $max_val ?? 'inf' ),
				];
			}
			return $values;
		}

		// Slider: return min/max config.
		if ( 'slider' === $filter_style ) {
			return [
				[
					'type' => 'slider',
					'min'  => isset( $config['min'] ) ? (float) $config['min'] : 0,
					'max'  => isset( $config['max'] ) ? (float) $config['max'] : 1000,
					'step' => isset( $config['step'] ) ? (float) $config['step'] : 1,
				],
			];
		}

		// Status filter.
		if ( 'status' === $filter_type ) {
			// Use all values configured in admin, not just hardcoded 3.
			$configured = $config['values'] ?? [];

			if ( ! empty( $configured ) && is_array( $configured ) ) {
				foreach ( $configured as $slug => $status_config ) {
					// Skip if explicitly disabled.
					if ( isset( $status_config['enabled'] ) && ! filter_var( $status_config['enabled'], FILTER_VALIDATE_BOOLEAN ) ) {
						continue;
					}
					$values[] = [
						'type'  => 'status',
						'slug'  => sanitize_key( $slug ),
						'label' => $status_config['label'] ?? $slug,
					];
				}
				return $values;
			}

			// Fallback to default 3 statuses if no config.
			$status_defaults = [
				'instock'     => __( 'In stock', 'simple-product-filter' ),
				'outofstock'  => __( 'Out of stock', 'simple-product-filter' ),
				'onbackorder' => __( 'On backorder', 'simple-product-filter' ),
			];

			foreach ( $status_defaults as $slug => $default_label ) {
				$values[] = [
					'type'  => 'status',
					'slug'  => $slug,
					'label' => $default_label,
				];
			}
			return $values;
		}

		// Sale filter.
		if ( 'sale' === $filter_type ) {
			return [
				[
					'type'  => 'sale',
					'slug'  => 'on_sale',
					'label' => __( 'On sale', 'simple-product-filter' ),
				],
			];
		}

		// Taxonomy term filters (brand, attribute_*).
		if ( 'brand' === $filter_type || str_starts_with( $filter_type, 'attribute_' ) ) {
			$taxonomy = 'brand' === $filter_type
				? 'product_brand'
				: substr( $filter_type, strlen( 'attribute_' ) );

			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => $config['hide_empty'] ?? true,
					'orderby'    => $config['sort_by'] ?? 'name',
					'order'      => strtoupper( $config['sort_dir'] ?? 'ASC' ),
				]
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return [];
			}

			foreach ( $terms as $term ) {
				$values[] = [
					'type'  => 'term',
					'slug'  => $term->slug,
					'label' => $term->name,
					'count' => $term->count,
					'id'    => $term->term_id,
				];
			}

			// If has include_values, filter.
			if ( ! empty( $config['include_values'] ) && is_array( $config['include_values'] ) ) {
				$include = $config['include_values'];
				$values  = array_filter( $values, static fn( $v ) => in_array( $v['slug'], $include, true ) );
			}

			// If has exclude_values, filter.
			if ( ! empty( $config['exclude_values'] ) && is_array( $config['exclude_values'] ) ) {
				$exclude = $config['exclude_values'];
				$values  = array_filter( $values, static fn( $v ) => ! in_array( $v['slug'], $exclude, true ) );
			}

			return array_values( $values );
		}

		// Meta filter.
		if ( str_starts_with( $filter_type, 'meta_' ) ) {
			$meta_key = substr( $filter_type, strlen( 'meta_' ) );
			$meta_key = sanitize_key( $meta_key );

			if ( empty( $meta_key ) ) {
				return [];
			}

			global $wpdb;

			// Try cache first.
			$cache_key = 'spf_meta_values_' . md5( $meta_key );
			$rows      = wp_cache_get( $cache_key );

			if ( false === $rows ) {
				// Load unique values of meta key for products.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT pm.meta_value AS value
						FROM {$wpdb->postmeta} pm
						INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
						WHERE pm.meta_key = %s
						AND pm.meta_value != ''
						AND p.post_type = 'product'
						AND p.post_status = 'publish'
						ORDER BY pm.meta_value ASC",
						$meta_key
					),
					ARRAY_A
				);

				if ( is_array( $rows ) ) {
					wp_cache_set( $cache_key, $rows, '', 3600 );
				} else {
					$rows = [];
				}
			}

			if ( ! is_array( $rows ) || empty( $rows ) ) {
				return [];
			}

			foreach ( $rows as $row ) {
				$values[] = [
					'type'  => 'meta',
					'slug'  => sanitize_text_field( $row['value'] ),
					'label' => sanitize_text_field( $row['value'] ),
				];
			}

			return $values;
		}

		/**
		 * Filter for custom values for unknown filter types.
		 *
		 * @param array<int, array<string, mixed>> $values  Empty array.
		 * @param array<string, mixed>             $filter  Filter data.
		 */
		return (array) apply_filters( 'spf_filter_values', $values, $filter );
	}

	/**
	 * Generates a label for a price range if not manually set.
	 * Examples: "0 – 100 €", "500 €+", "Up to 100 €"
	 *
	 * @param float|null  $min             Minimum value (null = no lower bound).
	 * @param float|null  $max             Maximum value (null = no upper bound).
	 * @param string      $currency_symbol Currency symbol.
	 * @return string
	 */
	private static function generate_range_label( ?float $min, ?float $max, string $currency_symbol ): string {
		$fmt = static function ( float $val ) use ( $currency_symbol ): string {
			return number_format( $val, 0, ',', ' ' ) . ( $currency_symbol ? ' ' . $currency_symbol : '' );
		};

		if ( null === $min && null !== $max ) {
			/* translators: %s: formatted max price */
			return sprintf( __( 'Up to %s', 'simple-product-filter' ), $fmt( $max ) );
		}

		if ( null !== $min && null === $max ) {
			/* translators: %s: formatted min price */
			return sprintf( __( '%s+', 'simple-product-filter' ), $fmt( $min ) );
		}

		if ( null !== $min && null !== $max ) {
			return $fmt( $min ) . ' – ' . $fmt( $max );
		}

		return '';
	}
}
