<?php
/**
 * Template: Color swatch filter typ.
 *
 * Premenné dostupné v tejto šablóne:
 * @var array<string, mixed>             $filter  Dáta filtra z DB.
 * @var array<int, array<string, mixed>> $values  Hodnoty filtra.
 * @var string                           $layout  Layout typ.
 *
 * Override: skopíruj do {tema}/wc-simple-filter/filter-types/color.php
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filter_id   = (int) ( $filter['id'] ?? 0 );
$filter_type = $filter['filter_type'] ?? '';
?>
<div class="wcsf__color-grid" role="group">
	<?php foreach ( $values as $value ) : ?>
		<?php
		$value_slug = $value['slug'] ?? '';
		$label      = $value['label'] ?? $value_slug;
		$color      = $value['color'] ?? '';
		$input_id   = 'wcsf-color-' . $filter_id . '-' . $value_slug;
		?>
		<label class="wcsf__color-item"
			   for="<?php echo esc_attr( $input_id ); ?>"
			   title="<?php echo esc_attr( $label ); ?>"
			   data-tooltip="<?php echo esc_attr( $label ); ?>">
			<input
				type="checkbox"
				class="wcsf__color-input sr-only"
				id="<?php echo esc_attr( $input_id ); ?>"
				name="wcsf[<?php echo esc_attr( $filter_type ); ?>][]"
				value="<?php echo esc_attr( $value_slug ); ?>"
			>
			<span
				class="wcsf__color-swatch"
				style="background-color: <?php echo esc_attr( $color ); ?>;"
				aria-label="<?php echo esc_attr( $label ); ?>">
			</span>
		</label>
	<?php endforeach; ?>
</div>
