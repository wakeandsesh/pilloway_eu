<?php
/**
 * Plugin Name: CatCode Shipping with Ukrposhta for WooCommerce
 * Plugin URI: https://catcode.com.ua/modules/ukrposhta-shipping-for-woocommerce/
 * Description: Ukrposhta delivery for WooCommerce: the customer picks region, city and post office at checkout from the official Address Classifier, and the delivery price is quoted live. Shipment (barcode) creation and sticker printing come in a later update.
 * Version: 1.2.7
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: CatCode
 * Author URI: https://catcode.com.ua
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: catcode-shipping-with-ukrposhta-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 10.7
 *
 * @package CcUkrposhtaWC
 */

defined( 'ABSPATH' ) || exit;

/*
 * The full version (sold on catcode.com.ua) lives in its own folder and ships the same
 * classes and settings. While it is loaded or active this copy stays out of the way, so
 * the two never collide and the full version keeps working on the same data.
 */
if (
	( static function () {
		if ( defined( 'UPWC_FILE' ) && __FILE__ !== UPWC_FILE ) {
			return true;
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $active as $plugin ) {
			if ( 'ukrposhta-shipping-for-woocommerce.php' === basename( (string) $plugin ) && file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
				return true;
			}
		}
		return false;
	} )()
) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-info"><p>'
				. esc_html__( 'The full version of this plugin from catcode.com.ua is active, so this copy is switched off and the full version runs on the same settings. You can deactivate or delete "CatCode Shipping with Ukrposhta for WooCommerce": its settings and data stay while the full version is installed.', 'catcode-shipping-with-ukrposhta-for-woocommerce' )
				. '</p></div>';
		}
	);
	// Still declared while switched off, or WooCommerce lists this copy as incompatible.
	add_action(
		'before_woocommerce_init',
		static function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			}
		}
	);
	return;
}

define( 'UPWC_VERSION', '1.2.7' );
define( 'UPWC_FILE', __FILE__ );
define( 'UPWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'UPWC_URL', plugin_dir_url( __FILE__ ) );
define( 'UPWC_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'CatCode\\UkrposhtaWC\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = UPWC_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\CatCode\\UkrposhtaWC\\Core\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\CatCode\\UkrposhtaWC\\Core\\Installer', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
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
					echo '<div class="notice notice-error"><p>' . esc_html__( 'CatCode Shipping with Ukrposhta for WooCommerce requires an active WooCommerce installation.', 'catcode-shipping-with-ukrposhta-for-woocommerce' ) . '</p></div>';
				}
			);
			return;
		}
		\CatCode\UkrposhtaWC\Core\Plugin::instance()->boot();
	}
);
