<?php
/**
 * Plugin Name: Nova Post for WooCommerce
 * Requires Plugins: woocommerce
 * Description: Nova Post shipping integration for WooCommerce
 * Version: 2.5.0
 * Author: Nova Post
 * Author URI: https://novapost.com/
 * Text Domain: nova-post-for-woocommerce
 * Domain Path: /languages/
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package NovaPostForWoocommerce
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NPFORWC_PLUGIN_VERSION' ) ) {
    define( 'NPFORWC_PLUGIN_VERSION', '2.5.0' );
}

if ( ! defined( 'NPFORWC_PLUGIN_PATH' ) ) {
    define( 'NPFORWC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'NPFORWC_PLUGIN_FILE_PATH' ) ) {
    define( 'NPFORWC_PLUGIN_FILE_PATH', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload_packages.php';
new NovaPostForWoocommerce\Plugin\NovaPostForWoocommercePlugin();
