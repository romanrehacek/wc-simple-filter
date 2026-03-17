<?php
/**
 * "Filters" tab — list of filters with repeater.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Simple_Filter\Filter_Manager;

/**
 * Filters_Tab class.
 */
class Filters_Tab {

	/**
	 * Renders the tab content.
	 *
	 * @return void
	 */
	public function render(): void {
		$filters = Filter_Manager::get_all();
		include WC_SF_PLUGIN_DIR . 'templates/admin/filters-tab.php';
	}
}
