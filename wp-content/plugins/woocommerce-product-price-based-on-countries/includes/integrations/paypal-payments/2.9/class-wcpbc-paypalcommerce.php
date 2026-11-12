<?php
/**
 * Handle integration with PayPal Payments 2.9+.
 *
 * The "WooCommerce PayPal Payments" plugin instances all modules on wp_loaded, when the woocommerce_get_currency filter has not been added yet.
 * This class store the currency in the session to add the filter before "WooCommerce PayPal Payments" loads its modules.
 *
 * @package WCPBC\Integrations\PayPalCommerce
 */

if ( ! class_exists( 'WCPBC_PayPalCommerce' ) ) :

	/**
	 * WCPBC_PPC
	 */
	class WCPBC_PayPalCommerce {

		/**
		 * Singleton instance.
		 *
		 * @var WCPBC_PayPalCommerce
		 */
		private static $instance = null;

		/**
		 * Returns the Singleton instance.
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Cookie name.
		 *
		 * @var string
		 */
		protected $cookie;

		/**
		 * Currency code.
		 *
		 * @var string
		 */
		protected $currency;

		/**
		 * Constructor for the session class.
		 */
		private function __construct() {
			$this->cookie = 'wcpbc_paypalcommerce_session_' . COOKIEHASH;
			$this->init_cookie();

			add_action( 'wp', [ $this, 'maybe_set_cookie_value' ], 100 );
			add_action( 'woocommerce_set_cart_cookies', [ $this, 'maybe_set_cookie_value' ], 30 );
		}

		/**
		 * Setup the cookie and the currency filter.
		 */
		private function init_cookie() {

			if ( did_action( 'wp_loaded' ) ) {
				return;
			}

			$cookie = $this->get_cookie();

			if ( ! $cookie ) {
				return;
			}

			$zone = WCPBC_Pricing_Zones::get_zone( $cookie );

			if ( ! $zone ) {
				return;
			}

			$this->currency = $zone->get_currency();

			if ( get_woocommerce_currency() !== $this->currency ) {
				add_filter( 'woocommerce_currency', [ $this, 'get_currency' ], 99 );
			}
		}

		/**
		 * Get the session cookie, if set. Otherwise return false.
		 *
		 * @return bool|array
		 */
		private function get_cookie() {
			$cookie_value = isset( $_COOKIE[ $this->cookie ] ) ? wp_unslash( $_COOKIE[ $this->cookie ] ) : false; // @codingStandardsIgnoreLine.

			if ( empty( $cookie_value ) || ! is_string( $cookie_value ) ) {
				return false;
			}

			$parsed_cookie = explode( '||', $cookie_value );

			if ( count( $parsed_cookie ) < 2 ) {
				return false;
			}

			list( $value, $cookie_hash ) = $parsed_cookie;

			if ( empty( $value ) || empty( $cookie_hash ) ) {
				return false;
			}

			// Validate hash.
			$hash = hash_hmac( 'md5', $value, wp_hash( $value ) );

			if ( ! hash_equals( $hash, $cookie_hash ) ) {
				return false;
			}

			return $value;
		}

		/**
		 * Set the cookie value.
		 *
		 * @param string $value Cookie value.
		 */
		private function set_cookie( $value ) {
			if ( false === $value ) {

				if ( isset( $_COOKIE[ $this->cookie ] ) ) {
					wc_setcookie( $this->cookie, 0, time() - HOUR_IN_SECONDS );
					unset( $_COOKIE[ $this->cookie ] );
				}
			} else {

				$cookie_hash  = hash_hmac( 'md5', $value, wp_hash( $value ) );
				$cookie_value = "{$value}||{$cookie_hash}";

				if ( ! isset( $_COOKIE[ $this->cookie ] ) || $_COOKIE[ $this->cookie ] !== $cookie_value ) {

					wc_setcookie(
						$this->cookie,
						$cookie_value,
						time() + ( DAY_IN_SECONDS * 2 ),
						wc_site_is_https() && is_ssl(),
						true
					);
				}
			}
		}

		/**
		 * Sets or unset the cookie.
		 */
		public function maybe_set_cookie_value() {
			if ( headers_sent() || ! did_action( 'wp_loaded' ) || ! ( function_exists( 'WC' ) && isset( WC()->session ) && is_callable( [ WC()->session, 'has_session' ] ) ) ) {
				return;
			}

			if ( WC()->session->has_session() ) {

				$value = wcpbc_the_zone() ? wcpbc_the_zone()->get_id() : false;
				$this->set_cookie( $value );

			} else {
				$this->set_cookie( false );
			}
		}

		/**
		 * Returns the currency.
		 *
		 * @param string $currency WooCommerce currency.
		 * @return string
		 */
		public function get_currency( $currency ) {
			if ( did_action( 'wp_loaded' ) ) {
				return $currency;
			}

			return $this->currency;
		}
	}

endif;

return WCPBC_PayPalCommerce::instance();
