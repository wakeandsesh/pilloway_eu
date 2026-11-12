<?php
/**
 * Handle integration with WooCommerce Payments.
 * This plugin enables, by default, a multi-currency feature that makes conflicts with Price Based on Country.
 *
 * @see https://woocommerce.com/payments/
 *
 * @since 2.3.0
 * @package WCPBC/Integrations
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WCPBC_WC_Payments' ) ) :

	/**
	 * WCPBC_Google_Listing_and_Ads class.
	 */
	class WCPBC_WC_Payments {

		/**
		 * Hook actions and filters
		 */
		public static function init() {
			add_action( 'add_option_wc_price_based_country_regions', array( __CLASS__, 'deferred_deactivate_multicurrency_feature' ), 0 );
			add_action( 'update_option_wc_price_based_country_regions', array( __CLASS__, 'deferred_deactivate_multicurrency_feature' ), 0 );
			add_action( 'update_option__wcpay_feature_customer_multi_currency', array( __CLASS__, 'deferred_deactivate_multicurrency_feature' ), 0 );
		}

		/**
		 * Queued the multicurency deactivate to do it on shutdown.
		 */
		public static function deferred_deactivate_multicurrency_feature() {
			if ( ! has_action( 'shutdown', array( __CLASS__, 'deactivate_multicurrency_feature' ) ) ) {
				add_action( 'shutdown', array( __CLASS__, 'deactivate_multicurrency_feature' ), 0 );
			}
		}

		/**
		 * Deactivate the WooCommerce Payments Multicurrency feature.
		 */
		public static function deactivate_multicurrency_feature() {

			remove_action( 'update_option_wc_price_based_country_regions', array( __CLASS__, 'deferred_deactivate_multicurrency_feature' ), 0 );
			remove_action( 'update_option__wcpay_feature_customer_multi_currency', array( __CLASS__, 'deferred_deactivate_multicurrency_feature' ), 0 );

			$wcpay_feature_multi_currency = ( '1' === get_option( '_wcpay_feature_customer_multi_currency', '1' ) );

			if ( ! $wcpay_feature_multi_currency ) {
				return;
			}

			$wcpay_currencies       = get_option( 'wcpay_multi_currency_enabled_currencies', [] );
			$wcpay_currencies_count = is_array( $wcpay_currencies ) ? count( $wcpay_currencies ) : 0;

			if ( $wcpay_currencies_count < 2 ) {
				// Only one currency. Deactivate.
				update_option( '_wcpay_feature_customer_multi_currency', '0' );
				return;
			}

			$currencies = array_keys( WCPBC_Pricing_Zones::get_currency_rates() );

			if ( count( $currencies ) > 0 ) {
				// Pricing zones handles the multicurrency. Deactivate WC_Pay feature and display a notice.
				update_option( '_wcpay_feature_customer_multi_currency', '0' );

				if ( $wcpay_currencies_count > 1 ) {
					WCPBC_Admin_Notices::add_notice( 'wc_payments_multi_currency_disabled' );
				}
			}
		}

	}

	// Init integration.
	WCPBC_WC_Payments::init();

endif;
