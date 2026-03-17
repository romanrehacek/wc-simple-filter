<?php
/**
 * Loader šablón s WooCommerce-style override systémom.
 *
 * Šablónu je možné prebiť z témy:
 *   {theme}/wc-simple-filter/{template}.php
 *   alebo z plugin adresára:
 *   {plugin}/templates/frontend/{template}.php
 *
 * @package WC_Simple_Filter
 */

namespace WC_Simple_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trieda Template.
 *
 * Zodpovedá za vyhľadávanie a načítanie frontend šablón
 * s podporou override z témy (ako WooCommerce templates/).
 */
class Template {

	/**
	 * Adresár šablón v téme (relatívne ku koreňu témy).
	 */
	const THEME_DIR = 'wc-simple-filter';

	/**
	 * Adresár šablón v plugine (relatívne ku koreňu pluginu).
	 */
	const PLUGIN_DIR = 'templates/frontend/';

	/**
	 * Vráti cestu k šablóne s lookuom: téma → child-téma → plugin.
	 *
	 * @param string $template_name Relatívna cesta k šablóne, napr. 'filter-item.php'.
	 * @return string Absolútna cesta k šablóne.
	 */
	public static function locate( string $template_name ): string {
		$template = '';

		// Hľadaj v aktuálnej téme a child-téme.
		$theme_locations = [
			get_stylesheet_directory() . '/' . self::THEME_DIR . '/' . $template_name,
			get_template_directory() . '/' . self::THEME_DIR . '/' . $template_name,
		];

		foreach ( $theme_locations as $location ) {
			if ( file_exists( $location ) ) {
				$template = $location;
				break;
			}
		}

		// Fallback na plugin šablónu.
		if ( ! $template ) {
			$plugin_template = WC_SF_PLUGIN_DIR . self::PLUGIN_DIR . $template_name;
			if ( file_exists( $plugin_template ) ) {
				$template = $plugin_template;
			}
		}

		/**
		 * Filter na override cesty šablóny.
		 *
		 * @param string $template      Absolútna cesta k šablóne.
		 * @param string $template_name Relatívna cesta šablóny.
		 */
		return (string) apply_filters( 'wc_sf_locate_template', $template, $template_name );
	}

	/**
	 * Načíta a zobrazí šablónu.
	 *
	 * @param string               $template_name Relatívna cesta k šablóne, napr. 'filter-item.php'.
	 * @param array<string, mixed> $args          Premenné dostupné v šablóne.
	 * @param bool                 $return        Ak true, vráti HTML ako string namiesto echo.
	 * @return string HTML výstup (iba ak $return === true).
	 */
	public static function get_template( string $template_name, array $args = [], bool $return = false ): string {
		$template = self::locate( $template_name );

		if ( ! $template ) {
			return '';
		}

		/**
		 * Akcia pred načítaním šablóny.
		 *
		 * @param string               $template_name Relatívna cesta šablóny.
		 * @param array<string, mixed> $args          Premenné šablóny.
		 */
		do_action( 'wc_sf_before_template', $template_name, $args );

		if ( $return ) {
			ob_start();
		}

		// Bezpečný extract — premenné dostupné v šablóne.
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template;

		/**
		 * Akcia po načítaní šablóny.
		 *
		 * @param string               $template_name Relatívna cesta šablóny.
		 * @param array<string, mixed> $args          Premenné šablóny.
		 */
		do_action( 'wc_sf_after_template', $template_name, $args );

		if ( $return ) {
			return (string) ob_get_clean();
		}

		return '';
	}

	/**
	 * Vráti URL adresára šablón v téme (pre dokumentáciu / debug).
	 *
	 * @return string URL adresára šablón v téme.
	 */
	public static function get_theme_template_dir_url(): string {
		return get_stylesheet_directory_uri() . '/' . self::THEME_DIR . '/';
	}

	/**
	 * Vráti URL adresára šablón v plugine.
	 *
	 * @return string URL adresára šablón v plugine.
	 */
	public static function get_plugin_template_dir_url(): string {
		return WC_SF_PLUGIN_URL . self::PLUGIN_DIR;
	}
}
