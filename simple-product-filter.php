<?php
/**
 * Plugin Name: Simple Product Filter
 * Plugin URI:  https://github.com/romanrehacek/simple-product-filter
 * Description: Configurable product filters for WooCommerce shop. Insert via shortcode [simple_product_filter] or PHP function simple_product_filter().
 * Version:     0.1.0
 * Author:      Roman Rehacek
 * Author URI:  https://github.com/romanrehacek
 * Text Domain: simple-product-filter
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      9.x
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPF_VERSION', '0.1.0' );
define( 'SPF_PLUGIN_FILE', __FILE__ );
define( 'SPF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active.
 */
function spf_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function spf_woocommerce_missing_notice(): void {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Simple Product Filter requires an active WooCommerce plugin.', 'simple-product-filter' );
	echo '</p></div>';
}

/**
 * Main plugin initialization.
 */
function spf_init(): void {
	if ( ! spf_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'spf_woocommerce_missing_notice' );
		return;
	}

	require_once SPF_PLUGIN_DIR . 'includes/class-plugin.php';
	Simple_Product_Filter\Plugin::get_instance()->init();
}

add_action( 'plugins_loaded', 'spf_init' );

/**
 * Declare compatibility with WooCommerce features (HPOS, Cart/Checkout Blocks).
 */
add_action(
	'before_woocommerce_init',
	function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Activation hook — creates database tables.
 */
function spf_activate(): void {
	if ( ! spf_is_woocommerce_active() ) {
		deactivate_plugins( SPF_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Simple Product Filter requires an active WooCommerce plugin.', 'simple-product-filter' ),
			esc_html__( 'Activation Error', 'simple-product-filter' ),
			[ 'back_link' => true ]
		);
	}

	require_once SPF_PLUGIN_DIR . 'includes/class-filter-manager.php';
	Simple_Product_Filter\Filter_Manager::install();
}

register_activation_hook( __FILE__, 'spf_activate' );

/**
 * Deactivation hook.
 */
function spf_deactivate(): void {
	// Tables remain. No data changes on deactivation.
}

register_deactivation_hook( __FILE__, 'spf_deactivate' );

/**
 * PHP helper function for inserting filters into template.
 *
 * @param array<string, mixed> $args Arguments (reserved for Phase 2).
 */
function simple_product_filter( array $args = [] ): void {
	// Phase 2 — frontend rendering.
	if ( ! spf_is_woocommerce_active() ) {
		return;
	}
	do_action( 'spf_render_filters', $args );
}
