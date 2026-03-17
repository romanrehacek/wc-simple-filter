<?php
/**
 * "Help" tab — developer documentation.
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Help_Tab class.
 */
class Help_Tab {

	/**
	 * Renders the tab content.
	 *
	 * @return void
	 */
	public function render(): void {
		include WC_SF_PLUGIN_DIR . 'templates/admin/help-tab.php';
	}
}
