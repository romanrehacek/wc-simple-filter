<?php
/**
 * Template partial: Reset tlačidlo.
 *
 * Override: skopíruj do {tema}/wc-simple-filter/partials/reset-button.php
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings         = get_option( 'wc_sf_settings', [] );
$show_reset       = $settings['show_reset_button'] ?? true;
$reset_label      = $settings['reset_button_text'] ?? __( 'Zrušiť filtre', 'wc-simple-filter' );

/**
 * Filter na zobrazenie reset tlačidla.
 *
 * @param bool $show_reset Zobraziť reset tlačidlo?
 */
$show_reset = (bool) apply_filters( 'wc_sf_show_reset_button', $show_reset );

if ( ! $show_reset ) {
	return;
}

/**
 * Filter na label reset tlačidla.
 *
 * @param string $reset_label Text tlačidla.
 */
$reset_label = (string) apply_filters( 'wc_sf_reset_button_label', $reset_label );
?>
<div class="wcsf__reset-wrapper">
	<button type="button" class="wcsf__reset-btn">
		<?php echo esc_html( $reset_label ); ?>
	</button>
</div>
