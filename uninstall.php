<?php
/**
 * Plugin cleanup on uninstall.
 *
 * Runs when the user deletes the plugin via WP Admin.
 * Deletes database tables and options only if the delete_on_uninstall option is set.
 *
 * @package Simple_Product_Filter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'spf_settings', [] );

if ( ! empty( $settings['delete_on_uninstall'] ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-filter-manager.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-index-manager.php';

	Simple_Product_Filter\Index_Manager::uninstall();
	Simple_Product_Filter\Filter_Manager::uninstall();
}
