<?php
/**
 * Plugin Name: CatCode Smart Filter for WooCommerce
 * Plugin URI: https://catcode.com.ua/modules/smart-filter-woocommerce/
 * Description: Faceted product filter that stays fast on a large catalogue: its own index table instead of attribute meta queries, honest counters, merging of duplicate values ("12 V" / "12v") and numeric sorting. Applies without a page reload, with a mobile drawer and a price slider.
 * Version: 1.0.3
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: CatCode
 * Author URI: https://catcode.com.ua
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: catcode-smart-filter
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 10.7
 *
 * @package CatCodeSmartFilter
 */

defined( 'ABSPATH' ) || exit;

// Per-constant guards keep WP's activation sandbox-scrape (which includes this
// file twice) from emitting "already defined" warnings — without ever skipping
// the include/hook registration below.
defined( 'CATCODE_SF_VERSION' ) || define( 'CATCODE_SF_VERSION', '1.0.3' );
defined( 'CATCODE_SF_FILE' ) || define( 'CATCODE_SF_FILE', __FILE__ );
defined( 'CATCODE_SF_DIR' ) || define( 'CATCODE_SF_DIR', plugin_dir_path( __FILE__ ) );
defined( 'CATCODE_SF_URL' ) || define( 'CATCODE_SF_URL', plugin_dir_url( __FILE__ ) );
defined( 'CATCODE_SF_BASENAME' ) || define( 'CATCODE_SF_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Explicit, dependency-ordered includes — deterministic and fast for a small
 * class set, and immune to the autoloader quirks some hosts show across SAPIs.
 *
 * @var string[] $catcode_sf_includes
 */
$catcode_sf_includes = array(
	'includes/Core/Settings.php',
	'includes/Core/Params.php',
	'includes/Core/Repository.php',
	'includes/Core/Facets.php',
	'includes/Core/Query.php',
	'includes/Core/Counter.php',
	'includes/Core/Indexer.php',
	'includes/Core/Runtime.php',
	'includes/Core/Installer.php',
	'includes/Frontend/Urls.php',
	'includes/Frontend/Renderer.php',
	'includes/Frontend/QueryFilter.php',
	'includes/Frontend/Display.php',
	'includes/Frontend/Ajax.php',
	'includes/Frontend/FilterWidget.php',
	'includes/Admin/SettingsPage.php',
);

foreach ( $catcode_sf_includes as $catcode_sf_inc ) {
	require_once CATCODE_SF_DIR . $catcode_sf_inc;
}

unset( $catcode_sf_includes, $catcode_sf_inc );

register_activation_hook(
	__FILE__,
	static function () {
		\CatCode\SmartFilter\Core\Installer::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		\CatCode\SmartFilter\Core\Installer::deactivate();
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CATCODE_SF_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					esc_html_e( 'CatCode Smart Filter needs WooCommerce to be active.', 'catcode-smart-filter' );
					echo '</p></div>';
				}
			);

			return;
		}

		\CatCode\SmartFilter\Core\Installer::init();
		\CatCode\SmartFilter\Frontend\QueryFilter::init();
		\CatCode\SmartFilter\Frontend\Display::init();
		\CatCode\SmartFilter\Frontend\Ajax::init();

		if ( is_admin() ) {
			\CatCode\SmartFilter\Admin\SettingsPage::init();
		}
	}
);

add_action(
	'widgets_init',
	static function () {
		\CatCode\SmartFilter\Frontend\FilterWidget::register();
	}
);
