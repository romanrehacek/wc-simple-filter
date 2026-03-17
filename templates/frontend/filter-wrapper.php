<?php
/**
 * Template: Obal celého bloku filtrov.
 *
 * Premenné dostupné v tejto šablóne:
 * @var array<int, array<string, mixed>> $filters     Pole filtrov z DB.
 * @var string                           $layout      Layout typ: 'sidebar' | 'horizontal'.
 * @var bool                             $collapsible Či sú filtre zbaliteľné.
 * @var bool                             $collapsed   Či sú filtre defaultne zbalené.
 * @var array<string, mixed>             $atts        Shortcode atribúty.
 *
 * Override: skopíruj do {tema}/wc-simple-filter/filter-wrapper.php
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Shortcode;
use WC_Simple_Filter\Template;
?>
<div class="wcsf wcsf--<?php echo esc_attr( $layout ); ?>" data-layout="<?php echo esc_attr( $layout ); ?>">

	<?php if ( 'horizontal' === $layout ) : ?>
		<?php Template::get_template( 'partials/filter-toggle-bar.php', compact( 'filters', 'layout', 'collapsible', 'collapsed' ) ); ?>
	<?php endif; ?>

	<div class="wcsf__filters" role="group" aria-label="<?php esc_attr_e( 'Filtre produktov', 'wc-simple-filter' ); ?>">

		<?php if ( 'sidebar' === $layout ) : ?>
			<?php foreach ( $filters as $filter ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Shortcode::render_filter_item( $filter, $layout, $collapsible, $collapsed );
				?>
			<?php endforeach; ?>
		<?php else : ?>
			<?php foreach ( $filters as $filter ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Shortcode::render_filter_item( $filter, $layout, $collapsible, $collapsed );
				?>
			<?php endforeach; ?>
		<?php endif; ?>

	</div>

	<?php Template::get_template( 'partials/reset-button.php', [] ); ?>

</div>
