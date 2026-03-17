<?php
/**
 * Template: Dropdown filter typ (single select).
 *
 * Premenné dostupné v tejto šablóne:
 * @var array<string, mixed>             $filter  Dáta filtra z DB.
 * @var array<int, array<string, mixed>> $values  Hodnoty filtra.
 * @var string                           $layout  Layout typ.
 *
 * Override: skopíruj do {tema}/wc-simple-filter/filter-types/dropdown.php
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filter_id   = (int) ( $filter['id'] ?? 0 );
$filter_type = $filter['filter_type'] ?? '';
$label       = $filter['label'] ?? '';
?>
<div class="wcsf__dropdown-wrapper">
	<select
		class="wcsf__dropdown"
		name="wcsf[<?php echo esc_attr( $filter_type ); ?>]"
		aria-label="<?php echo esc_attr( $label ); ?>">
		<option value=""><?php esc_html_e( '— Vybrať —', 'wc-simple-filter' ); ?></option>
		<?php foreach ( $values as $value ) : ?>
			<option value="<?php echo esc_attr( $value['slug'] ?? '' ); ?>">
				<?php echo esc_html( $value['label'] ?? '' ); ?>
				<?php if ( isset( $value['count'] ) ) : ?>
					(<?php echo absint( $value['count'] ); ?>)
				<?php endif; ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
