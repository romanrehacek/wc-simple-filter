<?php
/**
 * Shortcode [wc_simple_filter] a PHP helper wc_simple_filter().
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trieda Shortcode.
 *
 * Zodpovedá za:
 * - registráciu shortcodu [wc_simple_filter]
 * - parsovanie atribútov shortcodu
 * - načítanie filtrov z DB
 * - renderovanie filtrov cez template systém
 */
class Shortcode {

	/**
	 * Maximálny počet hodnôt zobrazených pred "Zobraziť viac".
	 */
	const DEFAULT_VALUES_VISIBLE = 5;

	/**
	 * Inicializuje shortcode.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_shortcode( 'wc_simple_filter', [ $this, 'render' ] );
		add_action( 'wc_sf_render_filters', [ $this, 'render_from_action' ] );
	}

	/**
	 * Callback pre shortcode [wc_simple_filter].
	 *
	 * @param array<string, string>|string $atts Atribúty shortcodu.
	 * @return string HTML výstup.
	 */
	public function render( array|string $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'layout'      => 'sidebar',
				'filter_ids'  => '',
				'exclude_ids' => '',
				'collapsible' => 'true',
				'collapsed'   => 'false',
			],
			is_array( $atts ) ? $atts : [],
			'wc_simple_filter'
		);

		return $this->render_filters( $atts );
	}

	/**
	 * Callback pre action wc_sf_render_filters (PHP helper).
	 *
	 * @param array<string, mixed> $args Argumenty.
	 * @return void
	 */
	public function render_from_action( array $args = [] ): void {
		// Normalizuj args na rovnaký formát ako shortcode atts.
		$atts = wp_parse_args(
			$args,
			[
				'layout'      => 'sidebar',
				'filter_ids'  => '',
				'exclude_ids' => '',
				'collapsible' => 'true',
				'collapsed'   => 'false',
			]
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_filters( $atts );
	}

	/**
	 * Renderuje filtre — hlavná logika.
	 *
	 * @param array<string, mixed> $atts Atribúty.
	 * @return string HTML výstup.
	 */
	private function render_filters( array $atts ): string {
		// Načítaj filtre z DB.
		$filters = $this->get_filters( $atts );

		if ( empty( $filters ) ) {
			/**
			 * Filter na HTML keď nie sú žiadne filtre.
			 *
			 * @param string $html Prázdny HTML výstup.
			 */
			return (string) apply_filters( 'wc_sf_no_filters_html', '' );
		}

		// Normalizuj a ovaliduj parametre.
		$layout      = in_array( $atts['layout'], [ 'sidebar', 'horizontal' ], true )
			? $atts['layout']
			: 'sidebar';
		$collapsible = filter_var( $atts['collapsible'], FILTER_VALIDATE_BOOLEAN );
		$collapsed   = filter_var( $atts['collapsed'], FILTER_VALIDATE_BOOLEAN );

		/**
		 * Filter na pole filtrov pred renderovaním.
		 *
		 * @param array<int, array<string, mixed>> $filters  Pole filtrov.
		 * @param string                           $layout   Layout typ.
		 * @param array<string, mixed>             $atts     Shortcode atribúty.
		 */
		$filters = (array) apply_filters( 'wc_sf_render_filters_data', $filters, $layout, $atts );

		// Priprav template args.
		$template_args = [
			'filters'     => $filters,
			'layout'      => $layout,
			'collapsible' => $collapsible,
			'collapsed'   => $collapsed,
			'atts'        => $atts,
		];

		/**
		 * Akcia pred celým blokom filtrov.
		 *
		 * @param array<int, array<string, mixed>> $filters Pole filtrov.
		 * @param string                           $layout  Layout typ.
		 */
		do_action( 'wc_sf_before_filters', $filters, $layout );

		$html = Template::get_template( 'filter-wrapper.php', $template_args, true );

		/**
		 * Akcia po celom bloku filtrov.
		 *
		 * @param array<int, array<string, mixed>> $filters Pole filtrov.
		 * @param string                           $layout  Layout typ.
		 */
		do_action( 'wc_sf_after_filters', $filters, $layout );

		return $html;
	}

	/**
	 * Načíta filtre z DB podľa atribútov shortcodu.
	 *
	 * @param array<string, mixed> $atts Atribúty shortcodu.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_filters( array $atts ): array {
		$all_filters = Filter_Manager::get_all();

		if ( empty( $all_filters ) ) {
			return [];
		}

		// Filtruj podľa filter_ids.
		if ( ! empty( $atts['filter_ids'] ) ) {
			$ids         = array_map( 'absint', explode( ',', $atts['filter_ids'] ) );
			$all_filters = array_filter(
				$all_filters,
				static fn( $f ) => in_array( $f['id'], $ids, true )
			);
		}

		// Vylúč podľa exclude_ids.
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
	 * Renderuje jeden filter item a vráti HTML.
	 * Volá sa z template filter-wrapper.php.
	 *
	 * @param array<string, mixed> $filter      Dáta filtra z DB.
	 * @param string               $layout      Layout typ ('sidebar' | 'horizontal').
	 * @param bool                 $collapsible Či je filter zbaliteľný.
	 * @param bool                 $collapsed   Či je filter defaultne zbalený.
	 * @return string HTML výstup jedného filtra.
	 */
	public static function render_filter_item(
		array $filter,
		string $layout,
		bool $collapsible,
		bool $collapsed
	): string {
		/**
		 * Akcia pred jednotlivým filtrom.
		 *
		 * @param array<string, mixed> $filter Dáta filtra.
		 */
		do_action( 'wc_sf_before_filter_item', $filter );

		$args = [
			'filter'      => $filter,
			'layout'      => $layout,
			'collapsible' => $collapsible,
			'collapsed'   => $collapsed,
			'values'      => self::get_filter_values( $filter ),
		];

		$html = Template::get_template( 'filter-item.php', $args, true );

		/**
		 * Filter na HTML jedného filtra.
		 *
		 * @param string               $html   HTML výstup.
		 * @param array<string, mixed> $filter Dáta filtra.
		 */
		$html = (string) apply_filters( 'wc_sf_filter_item_html', $html, $filter );

		/**
		 * Akcia po jednotlivom filtri.
		 *
		 * @param array<string, mixed> $filter Dáta filtra.
		 */
		do_action( 'wc_sf_after_filter_item', $filter );

		return $html;
	}

	/**
	 * Načíta a vráti hodnoty filtra (terms, meta values, rozsahy...).
	 *
	 * @param array<string, mixed> $filter Dáta filtra z DB.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_filter_values( array $filter ): array {
		$filter_type  = $filter['filter_type'] ?? '';
		$filter_style = $filter['filter_style'] ?? 'checkbox';
		$config       = $filter['config'] ?? [];
		$values       = [];

		// Rozsahy (radio/checkbox s manuálnymi rozsahmi).
		if ( ! empty( $config['ranges'] ) && is_array( $config['ranges'] ) ) {
			foreach ( $config['ranges'] as $range ) {
				$values[] = [
					'type'  => 'range',
					'label' => $range['label'] ?? '',
					'min'   => $range['min'] ?? null,
					'max'   => $range['max'] ?? null,
					'slug'  => 'range_' . ( $range['min'] ?? '0' ) . '_' . ( $range['max'] ?? 'inf' ),
				];
			}
			return $values;
		}

		// Slider — vráti min/max konfig.
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
			$status_defaults = [
				'instock'     => __( 'Na sklade', 'wc-simple-filter' ),
				'outofstock'  => __( 'Vypredané', 'wc-simple-filter' ),
				'onbackorder' => __( 'Na objednávku', 'wc-simple-filter' ),
			];

			foreach ( $status_defaults as $slug => $default_label ) {
				$status_config = $config['values'][ $slug ] ?? [];
				if ( isset( $status_config['enabled'] ) && false === $status_config['enabled'] ) {
					continue;
				}
				$values[] = [
					'type'  => 'status',
					'slug'  => $slug,
					'label' => $status_config['label'] ?? $default_label,
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
					'label' => __( 'V akcii', 'wc-simple-filter' ),
				],
			];
		}

		// Taxonomy term filtre (brand, attribute_*).
		if ( 'brand' === $filter_type || str_starts_with( $filter_type, 'attribute_' ) ) {
			$taxonomy = 'brand' === $filter_type
				? 'product_brand'
				: 'pa_' . substr( $filter_type, strlen( 'attribute_' ) );

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

			// Ak má include_values, filtruj.
			if ( ! empty( $config['include_values'] ) && is_array( $config['include_values'] ) ) {
				$include = $config['include_values'];
				$values  = array_filter( $values, static fn( $v ) => in_array( $v['slug'], $include, true ) );
			}

			// Ak má exclude_values, filtruj.
			if ( ! empty( $config['exclude_values'] ) && is_array( $config['exclude_values'] ) ) {
				$exclude = $config['exclude_values'];
				$values  = array_filter( $values, static fn( $v ) => ! in_array( $v['slug'], $exclude, true ) );
			}

			return array_values( $values );
		}

		// Meta filter.
		if ( str_starts_with( $filter_type, 'meta_' ) ) {
			// Pre Fázu 2b (query), teraz vrátime prázdne — meta hodnoty nie sú ešte indexované.
			return [];
		}

		/**
		 * Filter na custom hodnoty pre neznáme typy filtrov.
		 *
		 * @param array<int, array<string, mixed>> $values  Prázdne pole.
		 * @param array<string, mixed>             $filter  Dáta filtra.
		 */
		return (array) apply_filters( 'wc_sf_filter_values', $values, $filter );
	}
}
