<?php
/**
 * AJAX handler — processes all admin AJAX requests for the plugin.
 *
 * @package Simple_Product_Filter
 */

namespace Simple_Product_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ajax_Handler.
 */
class Ajax_Handler {

	/**
	 * Registers AJAX hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Admin AJAX endpoints.
		add_action( 'wp_ajax_spf_save_filter',     [ $this, 'save_filter' ] );
		add_action( 'wp_ajax_spf_delete_filter',   [ $this, 'delete_filter' ] );
		add_action( 'wp_ajax_spf_reorder_filters', [ $this, 'reorder_filters' ] );
		add_action( 'wp_ajax_spf_reindex',         [ $this, 'reindex' ] );
		add_action( 'wp_ajax_spf_get_type_values', [ $this, 'get_type_values' ] );
		add_action( 'wp_ajax_spf_save_settings',   [ $this, 'save_settings' ] );

		// Frontend AJAX endpoint — available to both authenticated and unauthenticated users.
		add_action( 'wp_ajax_spf_filter_products',        [ $this, 'filter_products' ] );
		add_action( 'wp_ajax_nopriv_spf_filter_products', [ $this, 'filter_products' ] );
	}

	/**
	 * Verifies nonce and capability. Exits with error on failure.
	 *
	 * @return void
	 */
	private function verify(): void {
		check_ajax_referer( 'spf_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission.', 'simple-product-filter' ) ], 403 );
		}
	}

	/**
	 * Saves a new or updates an existing filter.
	 *
	 * @return void
	 */
	public function save_filter(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		// Pass sanitized request array to helper to avoid reading globals inside helper.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce and capability already verified above in $this->verify()
		$data   = $this->extract_filter_data( isset( $_POST ) ? (array) wp_unslash( $_POST ) : [] );

		// Debug log — only in debug mode; prefer WC logger when available.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// Use WooCommerce logger if present.
			if ( function_exists( 'wc_get_logger' ) ) {
				$logger  = wc_get_logger();
				$context = [ 'source' => 'simple-product-filter' ];
				$logger->debug( 'save_filter: ' . wp_json_encode( $data ), $context );
			} else {
				error_log( 'WC_SF save_filter: ' . wp_json_encode( $data ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		if ( empty( $data['filter_type'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Filter type is required.', 'simple-product-filter' ) ] );
		}

		if ( $id > 0 ) {
			$success = Filter_Manager::update( $id, $data );
			$filter  = Filter_Manager::get( $id );
		} else {
			$new_id  = Filter_Manager::create( $data );
			$success = false !== $new_id;
			$filter  = $success ? Filter_Manager::get( $new_id ) : null;
		}

		if ( ! $success || ! $filter ) {
			wp_send_json_error( [ 'message' => __( 'Error saving filter.', 'simple-product-filter' ) ] );
		}

		wp_send_json_success( [ 'filter' => $filter ] );
	}

	/**
	 * Deletes a filter.
	 *
	 * @return void
	 */
	public function delete_filter(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = absint( $_POST['id'] ?? 0 );

		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid filter ID.', 'simple-product-filter' ) ] );
		}

		$success = Filter_Manager::delete( $id );

		if ( ! $success ) {
			wp_send_json_error( [ 'message' => __( 'Error deleting filter.', 'simple-product-filter' ) ] );
		}

		wp_send_json_success();
	}

	/**
	 * Saves new filter order after drag & drop.
	 *
	 * @return void
	 */
	public function reorder_filters(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_order = isset( $_POST['order'] ) ? (array) wp_unslash( $_POST['order'] ) : [];
		$order     = array_map( 'absint', $raw_order );
		$order     = array_filter( $order );

		if ( empty( $order ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid order.', 'simple-product-filter' ) ] );
		}

		Filter_Manager::reorder( $order );
		wp_send_json_success();
	}

	/**
	 * Triggers a full rebuild of the index.
	 *
	 * @return void
	 */
	public function reindex(): void {
		$this->verify();

		$filters = Filter_Manager::get_all();

		if ( empty( $filters ) ) {
			wp_send_json_success( [ 'count' => 0, 'message' => __( 'No filters to index.', 'simple-product-filter' ) ] );
		}

		$filter_types = array_unique( array_column( $filters, 'filter_type' ) );
		$total        = Index_Manager::rebuild( $filter_types );

		wp_send_json_success( [
			'count'   => $total,
			'message' => sprintf(
				/* translators: %d: number of records */
				__( 'Index rebuilt. Processed: %d records.', 'simple-product-filter' ),
				$total
			),
		] );
	}

	/**
	 * Returns available values for a given filter_type (for values picker in edit page).
	 *
	 * @return void
	 */
	public function get_type_values(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$filter_type = sanitize_text_field( wp_unslash( $_POST['filter_type'] ?? '' ) );

		if ( empty( $filter_type ) ) {
			wp_send_json_error( [ 'message' => __( 'Filter type is missing.', 'simple-product-filter' ) ] );
		}

		$values = $this->load_available_values( $filter_type );
		wp_send_json_success( [ 'values' => $values ] );
	}

	/**
	 * Saves general plugin settings.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$posted = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : [];

		$current  = get_option( 'spf_settings', Filter_Manager::default_settings() );
		$allowed  = array_keys( Filter_Manager::default_settings() );
		$settings = $current;

		foreach ( $allowed as $key ) {
			if ( ! isset( $posted[ $key ] ) ) {
				// Checkboxes only send values when checked.
				if ( in_array( $key, [ 'show_reset_button', 'hide_empty', 'delete_on_uninstall' ], true ) ) {
					$settings[ $key ] = false;
				}

				continue;
			}

			$settings[ $key ] = match ( $key ) {
				'filter_mode'         => in_array( sanitize_text_field( $posted[ $key ] ), [ 'ajax', 'submit', 'reload' ], true ) ? sanitize_text_field( $posted[ $key ] ) : 'ajax', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via sanitize_text_field()
				'show_reset_button',
				'hide_empty',
				'delete_on_uninstall' => (bool) $posted[ $key ], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- safe to cast to bool
				default               => sanitize_text_field( $posted[ $key ] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via sanitize_text_field()
			};
		}

		update_option( 'spf_settings', $settings );
		wp_send_json_success( [ 'message' => __( 'Settings saved.', 'simple-product-filter' ) ] );
	}

	/**
	 * Loads available values for a given filter_type from WooCommerce.
	 *
	 * @param string $filter_type Filter type.
	 * @return array<int, array<string, mixed>>
	 */
	private function load_available_values( string $filter_type ): array {
		if ( 'brand' === $filter_type ) {
			return $this->get_term_values( 'product_brand' );
		}

		if ( str_starts_with( $filter_type, 'attribute_' ) ) {
			$taxonomy = substr( $filter_type, strlen( 'attribute_' ) );
			return $this->get_term_values( $taxonomy );
		}

		if ( 'status' === $filter_type ) {
			// Load all registered statuses including custom ones (via woocommerce_product_stock_status_options).
			$stock_statuses = wc_get_product_stock_status_options();
			$values         = [];

			foreach ( $stock_statuses as $slug => $label ) {
				$values[] = [ 'value' => $slug, 'label' => $label ];
			}

			return $values;
		}

		if ( 'sale' === $filter_type ) {
			return [
				[ 'value' => 'on_sale',  'label' => __( 'On sale', 'simple-product-filter' ) ],
				[ 'value' => 'off_sale', 'label' => __( 'No discount', 'simple-product-filter' ) ],
			];
		}

		if ( str_starts_with( $filter_type, 'meta_' ) ) {
			$meta_key = substr( $filter_type, strlen( 'meta_' ) );
			return $this->get_meta_values( $meta_key );
		}

		return [];
	}

	/**
	 * Loads terms from a taxonomy as value/label array.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<int, array<string, string>>
	 */
	private function get_term_values( string $taxonomy ): array {
		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$values = [];

		foreach ( $terms as $term ) {
			$values[] = [
				'value' => $term->slug,
				'label' => $term->name,
				'count' => (int) $term->count,
			];
		}

		return $values;
	}

	/**
	 * Loads unique values for a meta key.
	 *
	 * @param string $meta_key Meta key.
	 * @return array<int, array<string, string>>
	 */
	private function get_meta_values( string $meta_key ): array {
		global $wpdb;

		// Try to get from cache first.
		$cache_key = 'spf_meta_values_' . md5( $meta_key );
		$rows      = wp_cache_get( $cache_key );

		if ( false === $rows ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_value as value
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

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( fn( $row ) => [
			'value' => $row['value'],
			'label' => $row['value'],
		], $rows );
	}

	/**
	 * AJAX endpoint for filtering products (frontend).
	 *
	 * Available to both authenticated and unauthenticated users.
	 * Verification: frontend nonce (spf_frontend_nonce).
	 *
	 * Input (POST):
	 *  - nonce    string  Verification nonce.
	 *  - wcsf     array   Sanitized filter parameters (same structure as $_GET['wcsf']).
	 *  - paged    int     Page number (default 1).
	 *  - orderby  string  WC orderby value (optional).
	 *
	 * Output (JSON success):
	 *  - html         string  HTML product loop.
	 *  - pagination   string  HTML pagination.
	 *  - found_posts  int     Total number of found products.
	 *  - max_pages    int     Total number of pages.
	 *  - paged        int     Current page.
	 *
	 * @return void
	 */
	public function filter_products(): void {
		// Verify frontend nonce — CSRF protection.
		// Capability check not needed (endpoint is public, read-only).
		check_ajax_referer( 'spf_frontend_nonce', 'nonce' );

		// --- Input sanitization ---

		// Filter parameters — same mechanism as for $_GET, but from $_POST.
		// Temporarily move wcsf from POST to GET so Query_Builder::get_active_params() works,
		// but prefer direct sanitization code to avoid modifying globals.
		$raw_wcsf = isset( $_POST['wcsf'] ) && is_array( $_POST['wcsf'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- values sanitized in sanitize_frontend_params()
			? (array) wp_unslash( $_POST['wcsf'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via sanitize_frontend_params()
			: [];

		$params = $this->sanitize_frontend_params( $raw_wcsf );

		// Pagination.
		$paged    = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;

		// Sorting — validate against allowed WC values.
		$raw_orderby = isset( $_POST['orderby'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['orderby'] ) )
			: '';
		$orderby = in_array( $raw_orderby, Query_Builder::ALLOWED_ORDERBY, true )
			? $raw_orderby
			: get_option( 'woocommerce_default_catalog_orderby', 'menu_order' );

		// --- Build WP_Query args ---

		$filter_configs = Filter_Manager::get_all();
		$filter_args    = Query_Builder::build( $params, $filter_configs );

		/**
		 * Number of products per page for AJAX endpoint.
		 * Default: WooCommerce setting.
		 *
		 * @param int $per_page Number of products.
		 */
		$per_page = (int) apply_filters(
			'spf_ajax_per_page',
			(int) get_option( 'posts_per_page', 12 )
		);

		$query_args = array_merge(
			[
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'posts_per_page'      => $per_page,
				'paged'               => $paged,
				'ignore_sticky_posts' => true,
			],
			$filter_args
		);

		// Apply WC sorting.
		$query_args = $this->apply_orderby( $query_args, $orderby );

		/**
		 * Filter to modify WP_Query args for AJAX filtering.
		 *
		 * @param array<string, mixed> $query_args    Built args.
		 * @param array<string, mixed> $params        Active filter parameters.
		 * @param int                  $paged         Page number.
		 * @param string               $orderby       Sorting.
		 */
		$query_args = (array) apply_filters( 'spf_ajax_query_args', $query_args, $params, $paged, $orderby );

		// --- Run query and render ---

		$query = new \WP_Query( $query_args );

		// Render product loop HTML.
		ob_start();

		if ( $query->have_posts() ) {
			wc_set_loop_prop( 'current_page', $paged );
			wc_set_loop_prop( 'total_pages', $query->max_num_pages );
			wc_set_loop_prop( 'is_shortcode', false );

			woocommerce_product_loop_start();

			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}

			woocommerce_product_loop_end();
		} else {
			// Empty state — wrap in the same wrapper as loop-start.php,
			// so JS can find [data-wcsf-products] even on empty results.
			echo '<div class="child-category__products" data-wcsf-products>';
			wc_get_template( 'loop/no-products-found.php' );
			echo '</div>';
		}

		$products_html = (string) ob_get_clean();
		wp_reset_postdata();

		// Render pagination HTML.
		// Call wc_get_template() directly instead of woocommerce_pagination() because
		// theme override of woocommerce_pagination() calls woocommerce_products_will_display()
		// which returns false in AJAX context (outside standard WC archive loop) and blocks output.
		// Always render wrapper so JS can consistently replace existing element in DOM.
		ob_start();

		if ( $query->max_num_pages > 1 ) {
			$pagination_base = esc_url_raw(
				str_replace(
					999999999,
					'%#%',
					remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) )
				)
			);

			wc_get_template(
				'loop/pagination.php',
				[
					'total'   => $query->max_num_pages,
					'current' => $paged,
					'base'    => $pagination_base,
					'format'  => '',
				]
			);
		} else {
			// Empty wrapper — keep element in DOM, JS can replace it.
			echo '<div class="child-category__pagination"></div>';
		}

		$pagination_html = (string) ob_get_clean();

		/**
		 * Action after rendering products (e.g., re-init WC JS hooks).
		 *
		 * @param \WP_Query            $query   WP_Query object.
		 * @param array<string, mixed> $params  Active filter parameters.
		 */
		do_action( 'spf_after_products_render', $query, $params );

		wp_send_json_success( [
			'html'        => $products_html,
			'pagination'  => $pagination_html,
			'found_posts' => (int) $query->found_posts,
			'max_pages'   => (int) $query->max_num_pages,
			'paged'       => $paged,
		] );
	}

	/**
	 * Sanitizes an array of frontend filter parameters (from AJAX POST).
	 *
	 * Replicates the logic of Query_Builder::get_active_params() but reads from an array
	 * instead of directly from $_GET, so we don't modify global superglobals.
	 *
	 * @param  array<mixed> $raw  Unsanitized input (from wp_unslash()).
	 * @return array<string, mixed>
	 */
	private function sanitize_frontend_params( array $raw ): array {
		$params = [];

		foreach ( $raw as $raw_key => $raw_value ) {
			$key = sanitize_key( (string) $raw_key );

			if ( empty( $key ) ) {
				continue;
			}

			// Validácia kľúča.
			if ( ! $this->is_valid_filter_key( $key ) ) {
				continue;
			}

			// Slider — price má podkľúče min/max. Range slugy (checkbox/radio)
			// nemajú tieto kľúče a musia prejsť na generický handler nižšie.
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

			// Attribute/meta slider.
			if (
				is_array( $raw_value )
				&& array_key_exists( 'min', $raw_value )
				&& array_key_exists( 'max', $raw_value )
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

			// Skalárna hodnota.
			if ( ! is_array( $raw_value ) ) {
				$value = sanitize_text_field( (string) $raw_value );
				if ( '' !== $value ) {
					$params[ $key ] = $value;
				}
				continue;
			}

			// Pole hodnôt.
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
	 * Validates a filter key — same logic as in Query_Builder.
	 *
	 * @param  string $key  Sanitized key.
	 * @return bool
	 */
	private function is_valid_filter_key( string $key ): bool {
		$static = [ 'brand', 'status', 'sale', 'price' ];

		if ( in_array( $key, $static, true ) ) {
			return true;
		}

		if ( str_starts_with( $key, 'attribute_' ) ) {
			return strlen( $key ) > strlen( 'attribute_' );
		}

		if ( str_starts_with( $key, 'meta_' ) ) {
			return strlen( $key ) > strlen( 'meta_' );
		}

		return false;
	}

	/**
	 * Applies WooCommerce orderby to WP_Query args.
	 *
	 * @param  array<string, mixed> $args     WP_Query args.
	 * @param  string               $orderby  WC orderby value.
	 * @return array<string, mixed>
	 */
	private function apply_orderby( array $args, string $orderby ): array {
		switch ( $orderby ) {
			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;

			case 'price':
				$args['orderby']  = 'meta_value_num'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_orderby
				$args['order']    = 'ASC';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'price-desc':
				$args['orderby']  = 'meta_value_num'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_orderby
				$args['order']    = 'DESC';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'popularity':
				$args['orderby']  = 'meta_value_num'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_orderby
				$args['order']    = 'DESC';
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'rating':
				$args['orderby']  = 'meta_value_num'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_orderby
				$args['order']    = 'DESC';
				$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;

			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;

			case 'menu_order':
			default:
				$args['orderby'] = 'menu_order title';
				$args['order']   = 'ASC';
				break;
		}

		return $args;
	}
	/**
	 * Extracts and sanitizes filter data from a request array.
	 *
	 * Callers must verify nonce and capability before passing user input here.
	 * If $request is null, falls back to reading $_POST (for backward compatibility).
	 *
	 * @param array<mixed>|null $request Unslashed request data (preferred).
	 * @return array<string, mixed>
	 */
	private function extract_filter_data( ?array $request = null ): array {
		// Prefer explicit request parameter to avoid reading globals in helper.
		if ( null === $request ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- fallback for backward compatibility; callers should pass $request
			$request = isset( $_POST ) ? (array) wp_unslash( $_POST ) : [];
		}

		$raw_config  = isset( $request['config'] ) ? (array) ( $request['config'] ) : []; // already unslashed by caller
		$filter_type = sanitize_text_field( (string) ( $request['filter_type'] ?? '' ) );
		$label       = sanitize_text_field( (string) ( $request['label'] ?? '' ) );

		// If label is not provided, generate a nice default name.
		if ( '' === $label ) {
			$label = self::default_label_for_type( $filter_type );
		}

		// If it came as string (JSON), decode it. If as array, take it directly.
		// When from serialize() form, $raw_config will be array.
		if ( is_string( $raw_config ) ) {
			$config = json_decode( $raw_config, true ) ?? [];
		} else {
			$config = (array) $raw_config;
		}

		// Recursively sanitize config.
		$config = self::sanitize_config( $config );

		// For status filter — explicitly set `enabled = false` for statuses that weren't sent.
		if ( 'status' === $filter_type ) {
			$config = self::process_status_config( $config );
		}

		return [
			'filter_type'  => $filter_type,
			'filter_style' => sanitize_text_field( (string) ( $request['filter_style'] ?? 'checkbox' ) ),
			'label'        => $label,
			'show_label'   => isset( $request['show_label'] ) ? 1 : 0,
			'config'       => $config,
		];
	}

	/**
	 * Processes config for status filter — sets `enabled = false`
	 * for statuses that weren't sent.
	 *
	 * @param array<string, mixed> $config Config array.
	 * @return array<string, mixed>
	 */
	private static function process_status_config( array $config ): array {
		$stock_statuses = wc_get_product_stock_status_options();

		// Initialize all statuses with enabled = false.
		$values = [];
		foreach ( $stock_statuses as $status_key => $label ) {
			$values[ $status_key ] = [
				'enabled' => false,
				'label'   => $label,
			];
		}

		// Merge with submitted values — those sent will have enabled = 1.
		if ( isset( $config['values'] ) && is_array( $config['values'] ) ) {
			foreach ( $config['values'] as $status_key => $data ) {
				if ( isset( $values[ $status_key ] ) ) {
					$values[ $status_key ] = array_merge(
						$values[ $status_key ],
						(array) $data
					);
				}
			}
		}

		$config['values'] = $values;
		return $config;
	}

	/**
	 * Sanitizes config array recursively.
	 *
	 * @param mixed $data Unsanitized data.
	 * @return mixed
	 */
	private static function sanitize_config( $data ) {
		if ( is_array( $data ) ) {
			return array_map( [ self::class, 'sanitize_config' ], $data );
		}

		if ( is_string( $data ) ) {
			return sanitize_text_field( $data );
		}

		return $data;
	}

	/**
	 * Returns a nice default name for a filter of a given filter_type.
	 *
	 * Used when admin hasn't provided a custom name.
	 *
	 * @param string $filter_type Filter type (e.g., 'brand', 'price', 'attribute_pa_color').
	 * @return string
	 */
	public static function default_label_for_type( string $filter_type ): string {
		// Basic types.
		$labels = [
			'brand'  => __( 'Brand', 'simple-product-filter' ),
			'price'  => __( 'Price', 'simple-product-filter' ),
			'status' => __( 'Availability', 'simple-product-filter' ),
			'sale'   => __( 'Sale', 'simple-product-filter' ),
		];

		if ( isset( $labels[ $filter_type ] ) ) {
			return $labels[ $filter_type ];
		}

		// WC attributes: attribute_pa_color → load label from WC.
		if ( str_starts_with( $filter_type, 'attribute_pa_' ) ) {
			$attr_name = substr( $filter_type, strlen( 'attribute_pa_' ) );
			$taxonomy  = wc_attribute_taxonomy_name( $attr_name );
			$attribute = wc_get_attribute( wc_attribute_taxonomy_id_by_name( $taxonomy ) );

			if ( $attribute && ! empty( $attribute->name ) ) {
				return $attribute->name;
			}

			// Fallback: capitalize slug.
			return ucfirst( str_replace( [ '_', '-' ], ' ', $attr_name ) );
		}

		// Custom meta: meta_my_key → "My key".
		if ( str_starts_with( $filter_type, 'meta_' ) ) {
			$key = substr( $filter_type, strlen( 'meta_' ) );
			return ucfirst( str_replace( [ '_', '-' ], ' ', $key ) );
		}

		// Last fallback.
		return ucfirst( str_replace( [ '_', '-' ], ' ', $filter_type ) );
	}
}
