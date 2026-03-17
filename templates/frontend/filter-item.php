<?php
/**
 * Template: Obal jedného filtra (label + obsah).
 *
 * Premenné dostupné v tejto šablóne:
 * @var array<string, mixed>             $filter      Dáta filtra z DB.
 * @var string                           $layout      Layout typ: 'sidebar' | 'horizontal'.
 * @var bool                             $collapsible Či je filter zbaliteľný.
 * @var bool                             $collapsed   Či je filter defaultne zbalený.
 * @var array<int, array<string, mixed>> $values      Hodnoty filtra.
 *
 * Override: skopíruj do {tema}/wc-simple-filter/filter-item.php
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Template;

$filter_id    = (int) ( $filter['id'] ?? 0 );
$filter_style = $filter['filter_style'] ?? 'checkbox';
$filter_type  = $filter['filter_type'] ?? '';
$show_label   = (bool) ( $filter['show_label'] ?? true );
$label        = $filter['label'] ?? '';

// CSS triedy pre filter wrapper.
$css_classes = [ 'wcsf__filter' ];
$css_classes[] = 'wcsf__filter--' . esc_attr( $filter_style );
$css_classes[] = 'wcsf__filter--type-' . esc_attr( str_replace( '_', '-', $filter_type ) );

if ( $collapsible ) {
	$css_classes[] = 'wcsf__filter--collapsible';
}
if ( $collapsible && $collapsed ) {
	$css_classes[] = 'wcsf__filter--collapsed';
}

$css_class_str = implode( ' ', $css_classes );
$body_id       = 'wcsf-filter-body-' . $filter_id;
?>
<div class="<?php echo esc_attr( $css_class_str ); ?>"
	 data-filter-id="<?php echo esc_attr( $filter_id ); ?>"
	 data-filter-type="<?php echo esc_attr( $filter_type ); ?>"
	 data-filter-style="<?php echo esc_attr( $filter_style ); ?>">

	<?php if ( $show_label && '' !== $label ) : ?>
		<?php if ( $collapsible ) : ?>
			<button type="button"
					class="wcsf__filter-toggle"
					aria-expanded="<?php echo $collapsed ? 'false' : 'true'; ?>"
					aria-controls="<?php echo esc_attr( $body_id ); ?>">
				<span class="wcsf__filter-title"><?php echo esc_html( $label ); ?></span>
				<span class="wcsf__filter-toggle-icon" aria-hidden="true"></span>
			</button>
		<?php else : ?>
			<div class="wcsf__filter-header">
				<span class="wcsf__filter-title"><?php echo esc_html( $label ); ?></span>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="wcsf__filter-body"
		 id="<?php echo esc_attr( $body_id ); ?>"
		 <?php if ( $collapsible && $collapsed ) : ?>aria-hidden="true"<?php endif; ?>>

		<?php if ( ! empty( $values ) ) : ?>
			<?php
			// Načítaj správny type template.
			$type_template = 'filter-types/' . $filter_style . '.php';
			Template::get_template( $type_template, compact( 'filter', 'values', 'layout' ) );
			?>
		<?php else : ?>
			<p class="wcsf__filter-empty"><?php esc_html_e( 'Žiadne hodnoty', 'wc-simple-filter' ); ?></p>
		<?php endif; ?>

	</div>

</div>
