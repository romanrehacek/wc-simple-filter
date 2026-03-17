<?php
/**
 * Template partial: Aktívny filter chip/tag.
 *
 * Premenné dostupné v tejto šablóne:
 * @var string $label      Zobrazovaný label chipa.
 * @var string $value_slug Hodnota filtra (slug).
 * @var string $filter_type Typ filtra.
 *
 * Override: skopíruj do {tema}/wc-simple-filter/partials/active-chip.php
 *
 * Poznámka: Plne funkčné iba v Fáze 2b (filtrovanie).
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<span class="wcsf__active-chip"
	  data-filter-type="<?php echo esc_attr( $filter_type ?? '' ); ?>"
	  data-value="<?php echo esc_attr( $value_slug ?? '' ); ?>">
	<span class="wcsf__active-chip-label"><?php echo esc_html( $label ?? '' ); ?></span>
	<button type="button"
			class="wcsf__active-chip-remove"
			aria-label="<?php echo esc_attr( sprintf( __( 'Odstrániť filter: %s', 'wc-simple-filter' ), $label ?? '' ) ); ?>">
		<span aria-hidden="true">&times;</span>
	</button>
</span>
