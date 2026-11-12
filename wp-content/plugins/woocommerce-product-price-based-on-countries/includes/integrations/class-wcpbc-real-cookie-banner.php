<?php
/**
 * Handle integration with Real Cookie Banner by devowl.io.
 *
 * This plugin breaks the WooCommerce geolocation feature, generating tickets.
 * This file restores the "Default customer address" option.
 *
 * @see https://wordpress.org/plugins/real-cookie-banner/
 *
 * @since 3.4.3
 * @package WCPBC/Integrations
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Real_Cookie_Banner class.
 */
class WCPBC_Real_Cookie_Banner {

	/**
	 * Hook actions and filters
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}

		$default_customer_address = get_option( 'woocommerce_default_customer_address' );

		add_filter(
			'option_woocommerce_default_customer_address',
			function ( $value ) use ( $default_customer_address ) {
				return $default_customer_address;
			},
			9999999999
		);

	}
}
WCPBC_Real_Cookie_Banner::init();
