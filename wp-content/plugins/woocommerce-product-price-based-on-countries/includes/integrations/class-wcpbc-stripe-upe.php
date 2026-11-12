<?php
/**
 * Handle integration with the Stipe UPE payment method (WooCommerce Stripe Payment Gateway)
 *
 * @see https://wordpress.org/plugins/woocommerce-gateway-stripe/
 *
 * @since 3.4.7
 * @package WCPBC/Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCPBC_Stripe_UPE' ) ) :

	/**
	 * WCPBC_Stripe_UPE class.
	 */
	class WCPBC_Stripe_UPE {

		/**
		 * Init integration
		 */
		public static function init() {
			add_action( 'admin_notices', [ __CLASS__, 'add_supported_currencies_filter' ], 0 );
			add_action( 'admin_notices', [ __CLASS__, 'remove_supported_currencies_filter' ], 20 );
			add_action( 'wp_footer', [ __CLASS__, 'enqueue_scripts' ], 0 );
			add_filter( 'wc_stripe_upe_params', [ __CLASS__, 'stripe_upe_params' ] );
			add_filter( 'woocommerce_update_order_review_fragments', [ __CLASS__, 'update_order_review_fragments' ] );
		}

		/**
		 * Returns the Stripe main gateway.
		 *
		 * @return WC_Stripe_UPE_Payment_Gateway|bool
		 */
		private static function get_main_stripe_gateway() {
			$main_gateway = null;
			$stripe       = function_exists( 'woocommerce_gateway_stripe' ) ? woocommerce_gateway_stripe() : null;

			if ( is_callable( [ $stripe, 'get_main_stripe_gateway' ] ) ) {
				$main_gateway = woocommerce_gateway_stripe()->get_main_stripe_gateway();
			}

			if ( is_a( $main_gateway, 'WC_Stripe_UPE_Payment_Gateway' ) ) {
				return $main_gateway;
			} else {
				return false;
			}
		}

		/**
		 * Do not display the "it requires store currency" if the required currency is in a pricing zone.
		 */
		public static function add_supported_currencies_filter() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			self::supported_currencies_filter( 'add' );
		}

		/**
		 * Removes the supported currencies filter.
		 */
		public static function remove_supported_currencies_filter() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			self::supported_currencies_filter( 'remove' );
		}

		/**
		 * Add/remove supported currencies filter.
		 *
		 * @param string $add_or_remove Add or remove filters flag.
		 */
		private static function supported_currencies_filter( $add_or_remove = 'add' ) {

			$main_gateway = self::get_main_stripe_gateway();
			$callback     = 'add' === $add_or_remove ? 'add_filter' : 'remove_filter';

			if ( ! $main_gateway ) {
				return;
			}

			foreach ( $main_gateway->get_upe_enabled_payment_method_ids() as $payment_method_id ) {

				if ( 'card' === $payment_method_id ) {
					continue;
				}
				call_user_func( $callback, "wc_stripe_{$payment_method_id}_upe_supported_currencies", [ __CLASS__, 'supported_currencies' ], 9999 );
			}

			call_user_func( $callback, 'wc_stripe_multibanco_supported_currencies', [ __CLASS__, 'supported_currencies' ], 9999 );
		}

		/**
		 * Include the base currency in the supported currencies to do not display the alert if the supported currency is in a pricing zone.
		 *
		 * @param array $supported_currencies Supported currencies.
		 */
		public static function supported_currencies( $supported_currencies ) {
			if ( empty( $supported_currencies ) || in_array( wcpbc_get_base_currency(), $supported_currencies, true ) ) {
				return $supported_currencies;
			}

			static $all_currencies = false;

			if ( false === $all_currencies ) {
				$all_currencies = self::get_available_currencies();
			}

			if ( count( array_intersect( $supported_currencies, $all_currencies ) ) ) {
				$supported_currencies[] = wcpbc_get_base_currency();
			}

			return $supported_currencies;
		}

		/**
		 * Enqueue scripts
		 */
		public static function enqueue_scripts() {
			if ( ! ( is_checkout() && self::get_main_stripe_gateway() && self::get_main_stripe_gateway()->is_available() ) ) {
				return;
			}

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_script(
				'wcpbc-stripe-upe-compatibility',
				WCPBC()->plugin_url() . 'assets/js/stripe-upe-compatibility' . $suffix . '.js',
				[ 'jquery' ],
				WCPBC()->version,
				true
			);
		}

		/**
		 * Update the fragments with the payment method config.
		 *
		 * @param array $fragments Array of fragments to return in the AJAX call update_order_review.
		 * @return array
		 */
		public static function update_order_review_fragments( $fragments ) {

			if ( ! is_callable( [ 'WC_Stripe_Helper', 'get_stripe_amount' ] ) ) {
				return $fragments;
			}

			if ( ! is_array( $fragments ) ) {
				$fragments = array();
			}

			$cart_total = ( WC()->cart ? WC()->cart->get_total( '' ) : 0 );
			$currency   = get_woocommerce_currency();

			$fragments['wcpbc_stripe_upe'] = [
				'currency'  => $currency,
				'cartTotal' => WC_Stripe_Helper::get_stripe_amount( $cart_total, strtolower( $currency ) ),
			];

			return $fragments;
		}

		/**
		 * Add all enabled payment methods to the JavaScript configuration object.
		 *
		 * @param array $params JavaScript configuration object.
		 */
		public static function stripe_upe_params( $params ) {
			if ( ! ( is_checkout() && ! is_checkout_pay_page() && isset( $params['paymentMethodsConfig'] ) ) ) {
				return $params;
			}

			$available_currencies = self::get_available_currencies();
			$main_gateway         = self::get_main_stripe_gateway();

			if ( ! $main_gateway || count( $available_currencies ) < 2 ) {
				return $params;
			}

			$payment_methods_config = $params['paymentMethodsConfig'];

			foreach ( $available_currencies as $currency ) {

				$filter = ( function( $value ) use ( $currency ) {
					return $currency;
				} );

				add_filter( 'woocommerce_currency', $filter, 999999 );

				$js_params = $main_gateway->javascript_params();

				remove_filter( 'woocommerce_currency', $filter, 999999 );

				if ( ! is_array( $js_params['paymentMethodsConfig'] ) ) {
					continue;
				}

				$payment_methods_config = array_merge( $payment_methods_config, $js_params['paymentMethodsConfig'] );
			}

			/*
			Remove country restrictions to prevent "is null" errors when hiding/showing the payment method.
			The update_order_review action already does the country restriction check!
			*/
			$payment_methods_config = array_map(
				function( $config ) {
					$config['countries'] = [];
					return $config;
				},
				$payment_methods_config
			);

			$params['paymentMethodsConfig'] = $payment_methods_config;

			return $params;
		}

		/**
		 * Returns all available currencies.
		 *
		 * @return array
		 */
		private static function get_available_currencies() {
			$currencies = [ wcpbc_get_base_currency() ];

			foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
				if ( ! $zone->get_enabled() ) {
					continue;
				}

				$currencies[] = $zone->get_currency();
			}

			return array_unique( $currencies );
		}

	}

	WCPBC_Stripe_UPE::init();

endif;
