<?php
/**
 * Handle integration with PayU Payment Gateway.
 *
 * @since 2.0.13
 * @package WCPBC/Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCPBC_Payu_Payment_Gateway' ) ) :

	/**
	 * WCPBC_Payu_Payment_Gateway class.
	 */
	class WCPBC_Payu_Payment_Gateway {

		/**
		 * Hook actions and filters
		 */
		public static function init() {
			add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'init_multicurrency' ), 100 );
			add_filter( 'wcpbc_payu_currency_codes', array( __CLASS__, 'unset_multicurrency' ), 10, 2 );
		}

		/**
		 * Init the "WooCommerce Multilingual" impersonator.
		 *
		 * @param array $methods Array of payment methods.
		 */
		public static function init_multicurrency( $methods ) {
			global $woocommerce_wpml;

			if ( in_array( 'WC_Gateway_PayU', $methods, true ) && empty( $woocommerce_wpml ) ) {
				$woocommerce_wpml = wcpbc_payu_woo_multilingual();
			}

			return $methods;
		}

		/**
		 * Return the currency codes and unset the global variable.
		 *
		 * @param array $currencies Array of currencies.
		 * @param int   $count Number of times the function was callled.
		 */
		public static function unset_multicurrency( $currencies, $count ) {
			global $woocommerce_wpml;
			if ( 3 === $count ) {
				// unset the global variable after return the currencies 3 times.
				unset( $woocommerce_wpml );
			}
			return $currencies;
		}
	}

	// phpcs:disable

	/**
	 * Returns an instance that impersonates "WooCommerce Multilingual" to fool PayU code.
	 */
	function wcpbc_payu_woo_multilingual() {
		if ( ! class_exists( 'WCPBC_Payu_Woo_Multilingual' ) ) {

			/**
			 * The Multicurrency property of the "WooCommerce Multilingual" impersonator
			 */
			class WCPBC_Payu_Woo_Multilingual_Multi_Currency {

				/**
				 * Call number counter.
				 */
				protected $count = 0;

				/**
				 * Array of currencies.
				 */
				protected $currencies = array();

				/**
				 * Constructor
				 */
				public function __construct() {
					$this->currencies = array_unique( array_merge( array( wcpbc_get_base_currency() ), array_keys( WCPBC_Pricing_Zones::get_currency_rates() ) ) );
				}

				/**
				 * Return the currencies codes.
				 */
				public function get_currency_codes() {
					$this->count++; // increment the count.

					return apply_filters( 'wcpbc_payu_currency_codes', $this->currencies, $this->count );
				}
			}

			/**
			 * "WooCommerce Multilingual" impersonator class.
			 */
			class WCPBC_Payu_Woo_Multilingual {
				public $multi_currency;

				function __construct() {
					$this->multi_currency = new WCPBC_Payu_Woo_Multilingual_Multi_Currency();
				}
			}
		}

		return new WCPBC_Payu_Woo_Multilingual();
	}

	// phpcs:enable
	if ( apply_filters( 'wc_price_based_country_payu_integration_enable', true ) ) {
		WCPBC_Payu_Payment_Gateway::init();
	}
endif;
