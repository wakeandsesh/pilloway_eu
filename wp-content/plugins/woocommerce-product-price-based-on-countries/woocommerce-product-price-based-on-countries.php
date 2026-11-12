<?php
/**
 * Plugin Name: WooCommerce Price Based on Country
 * Plugin URI: https://wordpress.org/plugins/woocommerce-product-price-based-on-countries/
 * Description: Product Pricing and Currency based on Shopper’s Country for WooCommerce.
 * Author: Oscar Gare
 * Version: 4.3.6
 * Author URI: https://oscargare.com/
 * Text Domain: woocommerce-product-price-based-on-countries
 * Domain Path: /languages
 *
 * WC requires at least: 4.0
 * WC tested up to: 11.2
 * License: GPLv2
 *
 * @package WCPBC
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define WCPBC_PRO_PLUGIN_FILE.
if ( ! defined( 'WCPBC_PLUGIN_FILE' ) ) {
	define( 'WCPBC_PLUGIN_FILE', __FILE__ );
}

// Include the main Price Based on Country class.
if ( ! class_exists( 'WC_Product_Price_Based_Country' ) ) {
	include_once __DIR__ . '/includes/class-wc-product-price-based-country.php';
}

/**
 * Declare plugin compatible with the WooCommerce HPOS feature.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Returns the main instance of WC_Product_Price_Based_Country to prevent the need to use globals.
 *
 * @since  1.3.0
 * @return WC_Product_Price_Based_Country
 */
function wcpbc() {
	return WC_Product_Price_Based_Country::instance();
}
wcpbc();
