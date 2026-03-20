<?php
/**
 * Query Builder: translates sanitized filter parameters to WP_Query args.
 *
 * This class is purely static and triggers no hooks on its own.
 * It uses apply_filters() exclusively so external behavior can be modified
 * without needing to override the entire class.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query_Builder class.
 *
 * Input: sanitized array of active filter parameters from URL ($_GET['wcsf']).
 * Output: array of WP_Query args ready to merge into the main query.
 */
class Query_Builder {

	/**
	 * Allowed filter_type keys (input validation).
	 * Dynamic types (attribute_*, meta_*) are validated via prefix checking.
	 */
	private const STATIC_TYPES = [ 'brand', 'status', 'sale', 'price' ];

	/**
	 * Allowed WooCommerce orderby values.
	 */
	public const ALLOWED_ORDERBY = [
		'menu_order',
		'popularity',
		'rating',
		'date',
		'price',
		'price-desc',
		'title',
	];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Loads, sanitizes, and returns active filter parameters from $_GET.
	 *
	 * This method is the only place where $_GET['wcsf'] is read — everywhere else
	 * we work with the output of this method.
	 *
	 * @return array<string, mixed>  Sanitized parameters, for example:
	 *   [
	 *     'brand'              => ['nike', 'adidas'],
	 *     'attribute_pa_color' => ['red'],
	 *     'status'             => ['instock'],
	 *     'sale'               => '1',
	 *     'price'              => ['min' => 100.0, 'max' => 500.0],
	 *   ]
	 */
	public static function get_active_params(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['wcsf'] ) || ! is_array( $_GET['wcsf'] ) ) {
			return [];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw    = (array) wp_unslash( $_GET['wcsf'] );
		$params = [];

		foreach ( $raw as $raw_key => $raw_value ) {
			$key = sanitize_key( (string) $raw_key );

			if ( empty( $key ) ) {
				continue;
			}

			// Validation: only known types + attribute_* + meta_* are allowed.
			if ( ! self::is_valid_filter_key( $key ) ) {
				continue;
			}

			// Slider — price has sub-keys min/max (wcsf[price][min], wcsf[price][max]).
			// Range slugs (checkbox/radio) come as wcsf[price][] and have no
			// min/max keys — in that case, let the values pass to the generic
			// array handler below.
			if ( 'price' === $key && is_array( $raw_value ) && ( isset( $raw_value['min'] ) || isset( $raw_value['max'] ) ) ) {
				$min = isset( $raw_value['min'] ) && '' !== $raw_value['min']
					? (float) $raw_value['min']
					: null;
				$max = isset( $raw_value['max'] ) && '' !== $raw_value['max']
					? (float) $raw_value['max']
					: null;

				if ( null !== $min || null !== $max ) {
					$params['price'] = array_filter(
						[ 'min' => $min, 'max' => $max ],
						static fn( $v ) => null !== $v
					);
				}
				continue;
			}

			// Attribute/meta slider — same pattern: wcsf[attribute_pa_xxx][min].
			if (
				is_array( $raw_value )
				&& isset( $raw_value['min'], $raw_value['max'] )
				&& ( str_starts_with( $key, 'attribute_' ) || str_starts_with( $key, 'meta_' ) )
			) {
				$min = '' !== $raw_value['min'] ? (float) $raw_value['min'] : null;
				$max = '' !== $raw_value['max'] ? (float) $raw_value['max'] : null;

				if ( null !== $min || null !== $max ) {
					$params[ $key ] = array_filter(
						[ 'min' => $min, 'max' => $max ],
						static fn( $v ) => null !== $v
					);
				}
				continue;
			}

			// Scalar value (e.g. sale=1, radio without range).
			if ( ! is_array( $raw_value ) ) {
				$value = sanitize_text_field( (string) $raw_value );
				if ( '' !== $value ) {
					$params[ $key ] = $value;
				}
				continue;
			}

			// Array of values (checkbox multi-select, multi-dropdown).
			$sanitized = [];
			foreach ( $raw_value as $v ) {
				$s = sanitize_text_field( (string) $v );
				if ( '' !== $s ) {
					$sanitized[] = $s;
				}
			}

			if ( ! empty( $sanitized ) ) {
				$params[ $key ] = $sanitized;
			}
		}

		return $params;
	}

	/**
	 * Builds WP_Query args fragment from active filter parameters.
	 *
	 * Returns only keys that are relevant (tax_query, meta_query, post__in).
	 * The caller must merge them into their own WP_Query args array.
	 *
	 * @param  array<string, mixed>             $params         Output from get_active_params().
	 * @param  array<int, array<string, mixed>> $filter_configs Filters from DB (for reading config['logic']).
	 * @return array<string, mixed>  WP_Query args fragment.
	 */
	public static function build( array $params, array $filter_configs = [] ): array {
		if ( empty( $params ) ) {
			return [];
		}

		// Index config by filter_type for O(1) lookup.
		$config_index = [];
		foreach ( $filter_configs as $fc ) {
			$ft = $fc['filter_type'] ?? '';
			if ( $ft ) {
				$config_index[ $ft ] = $fc['config'] ?? [];
			}
		}

		$tax_query  = [];
		$meta_query = [];
		$post__in   = null;   // null = do not apply; [] = no results

		foreach ( $params as $filter_type => $values ) {
			$config = $config_index[ $filter_type ] ?? [];

			// -----------------------------------------------------------------
			// brand / attribute_* → tax_query
			// -----------------------------------------------------------------
			if ( 'brand' === $filter_type || str_starts_with( $filter_type, 'attribute_' ) ) {
				$tax_clause = self::build_tax_clause( $filter_type, $values, $config );
				if ( ! empty( $tax_clause ) ) {
					$tax_query[] = $tax_clause;
				}
				continue;
			}

			// -----------------------------------------------------------------
			// status → meta_query on _stock_status
			// -----------------------------------------------------------------
			if ( 'status' === $filter_type ) {
				$clause = self::build_status_clause( $values );
				if ( ! empty( $clause ) ) {
					$meta_query[] = $clause;
				}
				continue;
			}

			// -----------------------------------------------------------------
			// sale → post__in
			// -----------------------------------------------------------------
			if ( 'sale' === $filter_type ) {
				$sale_ids = self::build_sale_post_ids( $values );
				if ( null !== $post__in ) {
					// Multiple post__in conditions → intersection.
					$post__in = array_intersect( $post__in, $sale_ids );
				} else {
					$post__in = $sale_ids;
				}
				continue;
			}

			// -----------------------------------------------------------------
			// price → meta_query on _price
			// -----------------------------------------------------------------
			if ( 'price' === $filter_type ) {
				$clause = self::build_price_clause( $values );
				if ( ! empty( $clause ) ) {
					$meta_query[] = $clause;
				}
				continue;
			}

			// -----------------------------------------------------------------
			// meta_* → meta_query on custom meta key
			// -----------------------------------------------------------------
			if ( str_starts_with( $filter_type, 'meta_' ) ) {
				$clause = self::build_meta_clause( $filter_type, $values, $config );
				if ( ! empty( $clause ) ) {
					$meta_query[] = $clause;
				}
				continue;
			}
		}

		// Build the final args array.
		$args = [];

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
			/**
			 * Filter to modify tax_query before applying.
			 *
			 * @param array<mixed>         $tax_query    Built tax_query.
			 * @param array<string, mixed> $params       Active filter parameters.
			 * @param array<mixed>         $config_index Index of filter configurations.
			 */
			$args['tax_query'] = (array) apply_filters( 'spf_tax_query', $tax_query, $params, $config_index ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			/**
			 * Filter to modify meta_query before applying.
			 *
			 * @param array<mixed>         $meta_query   Built meta_query.
			 * @param array<string, mixed> $params       Active filter parameters.
			 * @param array<mixed>         $config_index Index of filter configurations.
			 */
			$args['meta_query'] = (array) apply_filters( 'spf_meta_query', $meta_query, $params, $config_index ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( null !== $post__in ) {
			// Empty post__in → no products match the condition.
			$args['post__in'] = ! empty( $post__in ) ? array_values( $post__in ) : [ 0 ];
		}

		/**
		 * Filter to modify all WP_Query args before returning.
		 *
		 * @param array<string, mixed> $args    Built args.
		 * @param array<string, mixed> $params  Active filter parameters.
		 */
		return (array) apply_filters( 'spf_query_args', $args, $params );
	}

	// -------------------------------------------------------------------------
	// Private builder methods
	// -------------------------------------------------------------------------

	/**
	 * Builds tax_query clause for brand or attribute_* filter.
	 *
	 * @param  string              $filter_type  Filter type.
	 * @param  mixed               $values       Array of slugs or scalar.
	 * @param  array<string,mixed> $config       Filter config from DB.
	 * @return array<string, mixed>
	 */
	private static function build_tax_clause( string $filter_type, mixed $values, array $config ): array {
		$taxonomy = 'brand' === $filter_type
			? 'product_brand'
			: 'pa_' . substr( $filter_type, strlen( 'attribute_' ) );

		// Normalize to array.
		$slugs = is_array( $values ) ? $values : [ $values ];
		$slugs = array_filter( array_map( 'sanitize_text_field', $slugs ) );

		if ( empty( $slugs ) ) {
			return [];
		}

		// AND = product must have ALL selected values; OR = at least one.
		$logic    = strtolower( $config['logic'] ?? 'or' );
		$operator = ( 'and' === $logic ) ? 'AND' : 'IN';

		$clause = [
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => array_values( $slugs ),
			'operator' => $operator,
		];

		/**
		 * Filter to modify individual tax_query clause.
		 *
		 * @param array<string,mixed> $clause      Clause.
		 * @param string              $filter_type Filter type.
		 * @param array<string>       $slugs       Selected values.
		 * @param array<string,mixed> $config      Filter config.
		 */
		return (array) apply_filters( 'spf_tax_query_clause', $clause, $filter_type, $slugs, $config );
	}

	/**
	 * Builds meta_query clause for status filter.
	 *
	 * @param  mixed $values  Array of stock status slugs or scalar.
	 * @return array<string, mixed>
	 */
	private static function build_status_clause( mixed $values ): array {
		$statuses = is_array( $values ) ? $values : [ $values ];
		$statuses = array_filter( array_map( 'sanitize_key', $statuses ) );

		if ( empty( $statuses ) ) {
			return [];
		}

		// Validation: only allowed WC stock status values.
		$allowed  = array_keys( wc_get_product_stock_status_options() );
		$statuses = array_values( array_intersect( $statuses, $allowed ) );

		if ( empty( $statuses ) ) {
			return [];
		}

		return [
			'key'     => '_stock_status',
			'value'   => $statuses,
			'compare' => 'IN',
		];
	}

	/**
	 * Returns array of product IDs for sale filter.
	 * Empty collection (no products on sale) → [0] so WP_Query returns 0 results.
	 *
	 * @param  mixed $values  '1' if filter is active.
	 * @return array<int>
	 */
	private static function build_sale_post_ids( mixed $values ): array {
		// Filter is active if values is '1' or ['on_sale'].
		$is_active = '1' === $values
			|| ( is_array( $values ) && in_array( 'on_sale', $values, true ) );

		if ( ! $is_active ) {
			return [];
		}

		$ids = wc_get_product_ids_on_sale();

		return ! empty( $ids ) ? array_map( 'absint', $ids ) : [ 0 ];
	}

	/**
	 * Builds meta_query clause for price filter.
	 *
	 * Supports:
	 *  - ['min' => x, 'max' => y] — slider (BETWEEN)
	 *  - ['min' => x]             — >= min
	 *  - ['max' => y]             — <= max
	 *  - array of range slugs     — OR of multiple BETWEEN conditions
	 *
	 * @param  mixed $values  Array with min/max keys, array of range slugs, or scalar range slug.
	 * @return array<string, mixed>
	 */
	private static function build_price_clause( mixed $values ): array {
		// Slider or simple min/max.
		if ( is_array( $values ) && ( isset( $values['min'] ) || isset( $values['max'] ) ) ) {
			$min = isset( $values['min'] ) ? (float) $values['min'] : null;
			$max = isset( $values['max'] ) ? (float) $values['max'] : null;

			return self::numeric_range_clause( '_price', $min, $max );
		}

		// Scalar range slug from radio input: wcsf[price]=range_200_500.
		if ( is_string( $values ) && str_starts_with( $values, 'range_' ) ) {
			[ $min, $max ] = self::parse_range_slug( $values );

			return self::numeric_range_clause( '_price', $min, $max );
		}

		// Array of range slugs e.g. ['range_0_100', 'range_200_500'].
		if ( is_array( $values ) && ! empty( $values ) ) {
			$clauses = [];

			foreach ( $values as $slug ) {
				[ $min, $max ] = self::parse_range_slug( (string) $slug );
				$clause        = self::numeric_range_clause( '_price', $min, $max );

				if ( ! empty( $clause ) ) {
					$clauses[] = $clause;
				}
			}

			if ( empty( $clauses ) ) {
				return [];
			}

			// Multiple ranges = OR (product must fall into at least one).
			if ( 1 === count( $clauses ) ) {
				return $clauses[0];
			}

			return array_merge( [ 'relation' => 'OR' ], $clauses );
		}

		return [];
	}

	/**
	 * Builds meta_query clause for meta_* filter.
	 *
	 * @param  string              $filter_type  Filter type (e.g. 'meta_weight').
	 * @param  mixed               $values       Array of values or slider min/max.
	 * @param  array<string,mixed> $config       Filter config from DB.
	 * @return array<string, mixed>
	 */
	private static function build_meta_clause( string $filter_type, mixed $values, array $config ): array {
		$meta_key = substr( $filter_type, strlen( 'meta_' ) );
		$meta_key = sanitize_key( $meta_key );

		if ( empty( $meta_key ) ) {
			return [];
		}

		// Slider — min/max.
		if ( is_array( $values ) && ( isset( $values['min'] ) || isset( $values['max'] ) ) ) {
			$min = isset( $values['min'] ) ? (float) $values['min'] : null;
			$max = isset( $values['max'] ) ? (float) $values['max'] : null;

			$clause = self::numeric_range_clause( $meta_key, $min, $max );

			/**
			 * Filter to modify meta_query clause for meta_* type.
			 *
			 * @param array<string,mixed> $clause      Clause.
			 * @param string              $filter_type Filter type.
			 * @param mixed               $values      Active values.
			 * @param array<string,mixed> $config      Filter config.
			 */
			return (array) apply_filters( 'spf_meta_query_clause', $clause, $filter_type, $values, $config );
		}

		// Array of range slugs.
		if ( is_array( $values ) && ! empty( $values ) && str_starts_with( (string) reset( $values ), 'range_' ) ) {
			$clauses = [];

			foreach ( $values as $slug ) {
				[ $min, $max ] = self::parse_range_slug( (string) $slug );
				$clause        = self::numeric_range_clause( $meta_key, $min, $max );

				if ( ! empty( $clause ) ) {
					$clauses[] = $clause;
				}
			}

			if ( empty( $clauses ) ) {
				return [];
			}

			$result = 1 === count( $clauses )
				? $clauses[0]
				: array_merge( [ 'relation' => 'OR' ], $clauses );

			return (array) apply_filters( 'spf_meta_query_clause', $result, $filter_type, $values, $config );
		}

		// Array of text values.
		$vals = is_array( $values ) ? $values : [ $values ];
		$vals = array_filter( array_map( 'sanitize_text_field', $vals ) );

		if ( empty( $vals ) ) {
			return [];
		}

		$logic  = strtolower( $config['logic'] ?? 'or' );
		$clause = [
			'key'     => $meta_key,
			'value'   => array_values( $vals ),
			'compare' => ( 'and' === $logic ) ? 'AND' : 'IN',
		];

		return (array) apply_filters( 'spf_meta_query_clause', $clause, $filter_type, $values, $config );
	}

	// -------------------------------------------------------------------------
	// Helper methods
	// -------------------------------------------------------------------------

	/**
	 * Builds a numeric range meta_query clause.
	 *
	 * @param  string     $meta_key Meta key.
	 * @param  float|null $min      Minimum value (null = no lower bound).
	 * @param  float|null $max      Maximum value (null = no upper bound).
	 * @return array<string, mixed>
	 */
	private static function numeric_range_clause( string $meta_key, ?float $min, ?float $max ): array {
		if ( null === $min && null === $max ) {
			return [];
		}

		$base = [
			'key'    => $meta_key,
			'type'   => 'NUMERIC',
		];

		if ( null !== $min && null !== $max ) {
			return array_merge( $base, [
				'value'   => [ $min, $max ],
				'compare' => 'BETWEEN',
			] );
		}

		if ( null !== $min ) {
			return array_merge( $base, [
				'value'   => $min,
				'compare' => '>=',
			] );
		}

		return array_merge( $base, [
			'value'   => $max,
			'compare' => '<=',
		] );
	}

	/**
	 * Parses a range slug into [min, max].
	 * Format: 'range_{min}_{max}' where max can be 'inf' for unlimited upper bound.
	 * Examples: 'range_0_100' → [0.0, 100.0], 'range_500_inf' → [500.0, null].
	 *
	 * @param  string $slug  Range slug.
	 * @return array{0: float|null, 1: float|null}
	 */
	private static function parse_range_slug( string $slug ): array {
		if ( ! str_starts_with( $slug, 'range_' ) ) {
			return [ null, null ];
		}

		// Remove 'range_' prefix.
		$rest  = substr( $slug, 6 );
		// Split into max 2 parts from right — last is max, second-to-last is min.
		$parts = explode( '_', $rest );

		if ( count( $parts ) < 2 ) {
			return [ null, null ];
		}

		$max_raw = array_pop( $parts );
		$min_raw = implode( '_', $parts );

		$min = (float) $min_raw;
		$max = ( 'inf' === $max_raw || '' === $max_raw ) ? null : (float) $max_raw;

		return [ $min, $max ];
	}

	/**
	 * Validates a filter key — only known types are allowed.
	 *
	 * @param  string $key  Sanitized key.
	 * @return bool
	 */
	private static function is_valid_filter_key( string $key ): bool {
		if ( in_array( $key, self::STATIC_TYPES, true ) ) {
			return true;
		}

		if ( str_starts_with( $key, 'attribute_' ) ) {
			// attribute_ must be followed by a non-empty string.
			return strlen( $key ) > strlen( 'attribute_' );
		}

		if ( str_starts_with( $key, 'meta_' ) ) {
			return strlen( $key ) > strlen( 'meta_' );
		}

		return false;
	}
}
