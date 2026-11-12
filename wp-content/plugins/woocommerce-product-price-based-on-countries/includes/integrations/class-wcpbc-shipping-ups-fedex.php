<?php
/**
 * Handle integration with WooCommerce UPS Shipping by WooCommerce.
 *
 * @see https://woocommerce.com/products/ups-shipping-method/
 * @since 2.0.25
 * @package WCPBC/Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCPBC_Shipping_UPS' ) ) :

	/**
	 * * WCPBC_Shipping_UPS Class
	 */
	class WCPBC_Shipping_UPS_Fedex {

		/**
		 * Hook actions and filters
		 */
		public static function init() {
			$prefixes = array();

			if ( class_exists( 'WC_Shipping_UPS_Init' ) ) {
				// UPS Shipping.
				$prefixes[] = 'ups';
			}
			if ( class_exists( 'WC_Shipping_Fedex_Init' ) ) {
				// Fedex Shipping.
				$prefixes[] = 'fedex';
			}
			foreach ( $prefixes as $prefix ) {
				add_filter( "woocommerce_shipping_{$prefix}_check_store_currency", array( __CLASS__, 'shipping_check_store_currency' ), 100, 2 );
				add_filter( "woocommerce_shipping_{$prefix}_rate", array( __CLASS__, 'shipping_rate' ), 100, 2 );
			}
		}

		/**
		 * Does not check the currency if the base currency is equals to the UPS currency, or there is a zone with the UPS currency.
		 *
		 * @param bool   $check Currency check.
		 * @param string $ups_currency Currenty returned by UPS/Fedex.
		 */
		public static function shipping_check_store_currency( $check, $ups_currency = false ) {
			if ( $ups_currency ) {
				if ( wcpbc_get_base_currency() === $ups_currency ) {
					$check = false;
				} else {
					foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
						if ( $ups_currency === $zone->get_currency() ) {
							$check = false;
							break;
						}
					}
				}
			}
			return $check;
		}

		/**
		 * Converts rate to the shop base currency.
		 *
		 * @param array  $rate UPS/Fedex rate data.
		 * @param string $ups_currency UPS/Fedex currency.
		 * @return bool
		 */
		public static function shipping_rate( $rate, $ups_currency ) {
			if ( wcpbc_get_base_currency() !== $ups_currency && ! empty( $rate['cost'] ) ) {
				foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
					if ( $ups_currency === $zone->get_currency() ) {
						$rate['cost'] = $zone->get_base_currency_amount( $rate['cost'] );
						break;
					}
				}
			}
			return $rate;
		}
	}

	WCPBC_Shipping_UPS_Fedex::init();

endif;
