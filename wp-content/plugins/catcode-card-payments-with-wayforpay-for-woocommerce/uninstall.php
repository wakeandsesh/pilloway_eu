<?php
/**
 * Uninstall — remove plugin settings.
 *
 * @package CcWayForPayWC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The full version from catcode.com.ua uses the same settings: leave them while it is installed.
if ( glob( WP_PLUGIN_DIR . '/*/wayforpay-for-woocommerce.php' ) ) {
	return;
}

delete_option( 'woocommerce_wayforpay_settings' );
