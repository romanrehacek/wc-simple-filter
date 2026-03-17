<?php
/**
 * Template partial: Horizontal layout — toggle bar s pill-buttonmi.
 *
 * Zobrazuje filtre ako "pill" dropdown tlačidlá (horizontálny layout).
 *
 * Premenné dostupné v tejto šablóne:
 * @var array<int, array<string, mixed>> $filters     Pole filtrov z DB.
 * @var string                           $layout      Layout typ.
 * @var bool                             $collapsible Či sú filtre zbaliteľné.
 * @var bool                             $collapsed   Či sú filtre defaultne zbalené.
 *
 * Override: skopíruj do {tema}/wc-simple-filter/partials/filter-toggle-bar.php
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wcsf__toggle-bar" role="group" aria-label="<?php esc_attr_e( 'Filter produktov', 'wc-simple-filter' ); ?>">
	<?php foreach ( $filters as $filter ) : ?>
		<?php
		$filter_id    = (int) ( $filter['id'] ?? 0 );
		$label        = esc_html( $filter['label'] ?? '' );
		$filter_type  = esc_attr( $filter['filter_type'] ?? '' );
		$target_id    = 'wcsf-filter-body-' . $filter_id;
		?>
		<button type="button"
				class="wcsf__toggle-pill"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( $target_id ); ?>"
				data-filter-id="<?php echo esc_attr( $filter_id ); ?>"
				data-filter-type="<?php echo $filter_type; ?>">
			<span class="wcsf__toggle-pill-label"><?php echo $label; ?></span>
			<span class="wcsf__toggle-pill-icon" aria-hidden="true"></span>
		</button>
	<?php endforeach; ?>
</div>

