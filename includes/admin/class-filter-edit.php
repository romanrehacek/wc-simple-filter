<?php
/**
 * Stránka editácie konkrétneho filtra.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Filter_Manager;

/**
 * Trieda Filter_Edit.
 */
class Filter_Edit {

	/**
	 * Renderuje editačný formulár pre daný filter.
	 *
	 * @param int $filter_id ID filtra (0 = nový filter, no aktuálne len editácia).
	 * @return void
	 */
	public function render( int $filter_id ): void {
		if ( $filter_id <= 0 ) {
			wp_safe_redirect( Admin::tab_url() );
			exit;
		}

		$filter = Filter_Manager::get( $filter_id );

		if ( ! $filter ) {
			wp_die(
				esc_html__( 'Filter nebol nájdený.', 'wc-simple-filter' ),
				esc_html__( 'Chyba', 'wc-simple-filter' ),
				[ 'back_link' => true ]
			);
		}

		$price_range      = ( 'price' === $filter['filter_type'] ) ? self::get_price_range() : null;
		$available_values = self::load_available_values( $filter['filter_type'] );

		include WC_SF_PLUGIN_DIR . 'templates/admin/filter-edit.php';
	}

	/**
	 * Načíta dostupné hodnoty pre daný filter_type.
	 *
	 * Vracia prázdne pole pre typy, kde výber hodnôt nie je relevantný
	 * (price, status, fixed styles).
	 *
	 * @param string $filter_type Typ filtra.
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_available_values( string $filter_type ): array {
		if ( 'brand' === $filter_type ) {
			return self::get_term_values( 'product_brand' );
		}

		if ( str_starts_with( $filter_type, 'attribute_' ) ) {
			$taxonomy = substr( $filter_type, strlen( 'attribute_' ) );
			return self::get_term_values( $taxonomy );
		}

		if ( str_starts_with( $filter_type, 'meta_' ) ) {
			$meta_key = substr( $filter_type, strlen( 'meta_' ) );
			return self::get_meta_values( $meta_key );
		}

		// Pre price, status, sale — výber hodnôt sa nerieši tu.
		return [];
	}

	/**
	 * Načíta termy z taxonomie ako pole value/label/count.
	 *
	 * @param string $taxonomy Taxonomia.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_term_values( string $taxonomy ): array {
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
	 * Načíta unikátne hodnoty meta kľúča pre produkty.
	 *
	 * @param string $meta_key Meta kľúč.
	 * @return array<int, array<string, string>>
	 */
	private static function get_meta_values( string $meta_key ): array {
		global $wpdb;

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

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( fn( $row ) => [
			'value' => $row['value'],
			'label' => $row['value'],
		], $rows );
	}

	/**
	 * Vráti min a max cenu publikovaných produktov.
	 *
	 * @return array{min: float, max: float}|null  null ak nie sú žiadne produkty.
	 */
	private static function get_price_range(): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT MIN( CAST( meta_value AS DECIMAL(15,4) ) ) AS min_price,
			        MAX( CAST( meta_value AS DECIMAL(15,4) ) ) AS max_price
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key   = '_price'
			   AND pm.meta_value != ''
			   AND p.post_type   = 'product'
			   AND p.post_status = 'publish'",
			ARRAY_A
		);

		if ( ! $row || null === $row['min_price'] ) {
			return null;
		}

		return [
			'min' => (float) $row['min_price'],
			'max' => (float) $row['max_price'],
		];
	}
}
