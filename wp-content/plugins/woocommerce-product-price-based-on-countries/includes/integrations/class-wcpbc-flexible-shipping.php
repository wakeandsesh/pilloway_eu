<?php
/**
 * Handle integration with Flexible Shipping by Octolize.
 *
 * @see https://wordpress.org/plugins/flexible-shipping/
 *
 * @since 3.4.0
 * @package WCPBC/Integrations
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flexible_Shipping class.
 */
class WCPBC_Flexible_Shipping {

	/**
	 * Hook actions and filters
	 */
	public static function init() {
		if ( 'yes' === get_option( 'wc_price_based_country_shipping_exchange_rate', 'no' ) ) {
			add_filter( 'flexible_shipping_value_in_currency', [ __CLASS__, 'value_in_currency' ] );
		}
	}

	/**
	 * Returns the amount conver to the current currency.
	 *
	 * @param float $amount price.
	 */
	public static function value_in_currency( $amount ) {
		if ( wcpbc_the_zone() ) {
			return wcpbc_the_zone()->get_exchange_rate_price( $amount, false, 'shipping' );
		}
		return $amount;
	}
}

WCPBC_Flexible_Shipping::init();
