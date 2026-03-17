<?php
/**
 * Template: "Help" tab.
 *
 * @package WC_Simple_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wc-sf-wrap wc-sf-help-wrap">

	<h2><?php esc_html_e( 'How to use WC Simple Filter', 'wc-simple-filter' ); ?></h2>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Inserting via shortcode', 'wc-simple-filter' ); ?></h3>
		<p>
			<?php esc_html_e( 'Insert the shortcode on any page or in a widget:', 'wc-simple-filter' ); ?>
		</p>
		<pre class="wc-sf-code"><code>[wc_simple_filter]</code></pre>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Inserting directly in PHP template', 'wc-simple-filter' ); ?></h3>
		<p>
			<?php esc_html_e( 'If you prefer inserting directly in the theme PHP file:', 'wc-simple-filter' ); ?>
		</p>
		<pre class="wc-sf-code"><code>&lt;?php wc_simple_filter(); ?&gt;</code></pre>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Filter types', 'wc-simple-filter' ); ?></h3>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'wc-simple-filter' ); ?></th>
					<th><?php esc_html_e( 'Description', 'wc-simple-filter' ); ?></th>
					<th><?php esc_html_e( 'Available styles', 'wc-simple-filter' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>brand</code></td>
					<td><?php esc_html_e( 'Filter by brand (taxonomy product_brand)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, dropdown, multi-dropdown', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>status</code></td>
					<td><?php esc_html_e( 'Stock status (in stock, out of stock, on backorder)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox (fixed)', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>sale</code></td>
					<td><?php esc_html_e( 'Sale products (on sale / not on sale)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox (fixed)', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>price</code></td>
					<td><?php esc_html_e( 'Price filter — ranges or slider', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, slider', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>attribute_{slug}</code></td>
					<td><?php esc_html_e( 'WooCommerce product attribute (e.g., attribute_pa_color)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, dropdown, multi-dropdown, slider', 'wc-simple-filter' ); ?></td>
				</tr>
				<tr>
					<td><code>meta_{key}</code></td>
					<td><?php esc_html_e( 'Custom meta field (configured by admin — e.g., meta_weight)', 'wc-simple-filter' ); ?></td>
					<td><?php esc_html_e( 'checkbox, radio, dropdown, multi-dropdown, slider', 'wc-simple-filter' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Value index (hide-empty)', 'wc-simple-filter' ); ?></h3>
		<p>
			<?php esc_html_e( 'The plugin maintains an internal index of product counts for each filter value. This index is automatically updated when a product is saved. If you see outdated values, you can manually recalculate it in the Settings tab.', 'wc-simple-filter' ); ?>
		</p>
	</div>

	<div class="wc-sf-help-section">
		<h3><?php esc_html_e( 'Planned features (Phase 2)', 'wc-simple-filter' ); ?></h3>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'AJAX product filtering without page reload', 'wc-simple-filter' ); ?></li>
			<li><?php esc_html_e( 'URL state — sharing and back/forward navigation', 'wc-simple-filter' ); ?></li>
			<li><?php esc_html_e( 'Frontend CSS styling', 'wc-simple-filter' ); ?></li>
			<li><?php esc_html_e( 'WooCommerce query integration', 'wc-simple-filter' ); ?></li>
		</ul>
	</div>

</div>
