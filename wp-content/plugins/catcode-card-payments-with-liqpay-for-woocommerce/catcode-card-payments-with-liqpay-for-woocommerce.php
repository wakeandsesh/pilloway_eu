<?php
/**
 * Plugin Name: CatCode Card Payments with LiqPay for WooCommerce
 * Plugin URI: https://catcode.com.ua/modules/catcode-card-payments-with-liqpay-for-woocommerce/
 * Description: Accept card payments in WooCommerce via LiqPay: redirect to the secure payment page, automatic order confirmation through the signed serviceUrl webhook, status reconciliation from the admin.
 * Version: 1.0.4
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: CatCode
 * Author URI: https://catcode.com.ua
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: catcode-card-payments-with-liqpay-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 10.7
 *
 * @package CcLiqPayWC
 */

defined( 'ABSPATH' ) || exit;

define( 'CATCODE_LIQPAY_VERSION', '1.0.4' );
define( 'CATCODE_LIQPAY_FILE', __FILE__ );
define( 'CATCODE_LIQPAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'CATCODE_LIQPAY_URL', plugin_dir_url( __FILE__ ) );
define( 'CATCODE_LIQPAY_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'CatCode\\LiqPayWC\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = CATCODE_LIQPAY_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( '\\CatCode\\LiqPayWC\\Core\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\CatCode\\LiqPayWC\\Core\\Installer', 'deactivate' ) );

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
					echo '<div class="notice notice-error"><p>' . esc_html__( 'CatCode Card Payments with LiqPay for WooCommerce requires an active WooCommerce installation.', 'catcode-card-payments-with-liqpay-for-woocommerce' ) . '</p></div>';
				}
			);
			return;
		}
		\CatCode\LiqPayWC\Core\Plugin::instance()->boot();
	}
);
