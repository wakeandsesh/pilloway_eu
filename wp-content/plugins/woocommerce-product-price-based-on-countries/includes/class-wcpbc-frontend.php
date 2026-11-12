<?php
/**
 * WooCommerce Price Based Country Front-End
 *
 * @version 1.8.10
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WCPBC_Frontend Class
 */
class WCPBC_Frontend {

	/**
	 * Hook actions and filters
	 */
	public static function init() {
		add_filter( 'woocommerce_customer_default_location', array( __CLASS__, 'allowed_countries_filter' ) );
		add_filter( 'woocommerce_customer_default_location_array', array( __CLASS__, 'test_default_location' ) );
		add_filter( 'woocommerce_geolocate_ip', array( __CLASS__, 'fix_real_ip' ), 5 );
		add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'update_order_review_fragments' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'load_scripts' ), 1 );
		add_action( 'wc_price_based_country_before_frontend_init', array( __CLASS__, 'set_customer_country' ), 20 );
		add_action( 'wc_price_based_country_frontend_init', array( __CLASS__, 'frontend_init' ) );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'order_refunded' ), 10, 2 );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_calculate_totals' ), 11 );
		add_action( 'wp_footer', array( __CLASS__, 'test_store_message' ) );
		add_action( 'wcpbc_manual_country_selector', array( __CLASS__, 'output_country_selector' ) );
		add_shortcode( 'wcpbc_country_selector', array( __CLASS__, 'shortcode_country_selector' ) );
	}

	/**
	 * Add the allowed countries filter.
	 *
	 * @param mixed $value Value.
	 */
	public static function allowed_countries_filter( $value ) {
		add_filter( 'pre_option_woocommerce_allowed_countries', array( __CLASS__, 'allowed_countries' ) );
		return $value;
	}

	/**
	 * Returns 'all' for allowed countries countries option in the function wc_get_customer_default_location.
	 *
	 * @param string $value Option value.
	 */
	public static function allowed_countries( $value ) {
		return 'all';
	}

	/**
	 * Returns the test country as default location.
	 *
	 * @param array $location Country and state default location.
	 * @return array
	 */
	public static function test_default_location( $location ) {
		if ( get_option( 'wc_price_based_country_test_mode', 'no' ) === 'yes' && get_option( 'wc_price_based_country_test_country' ) ) {
			$location = wc_format_country_state_string( get_option( 'wc_price_based_country_test_country' ) );
		}
		remove_filter( 'pre_option_woocommerce_allowed_countries', array( __CLASS__, 'allowed_countries' ) );
		return $location;
	}

	/**
	 * Replaces the HTTP_X_REAL_IP server variable for the geolocation function works appropriately on some hosting.
	 * Run on woocommerce_geolocate_ip filter (before WooCommerce geolocates the IP).
	 *
	 * @since 3.1.0
	 * @param string $value Value to return by the filter.
	 */
	public static function fix_real_ip( $value ) {
		$sucuri_client_ip = empty( $_SERVER['HTTP_X_SUCURI_CLIENTIP'] ) ? false : filter_var( wc_clean( wp_unslash( $_SERVER['HTTP_X_SUCURI_CLIENTIP'] ) ), FILTER_VALIDATE_IP );
		$remote_addr      = empty( $_SERVER['REMOTE_ADDR'] ) ? false : filter_var( wc_clean( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), FILTER_VALIDATE_IP );

		if ( $sucuri_client_ip ) {
			$_SERVER['HTTP_X_REAL_IP'] = $sucuri_client_ip;
		} elseif ( defined( 'WCPBC_USE_REMOTE_ADDR' ) && WCPBC_USE_REMOTE_ADDR && $remote_addr ) {
			$_SERVER['HTTP_X_REAL_IP'] = $remote_addr;
		}

		return $value;
	}

	/**
	 * Add the mini cart to the update order review fragments.
	 *
	 * @param array $fragments Order review fragments.
	 */
	public static function update_order_review_fragments( $fragments ) {
		$cart_hash = isset( WC()->cart ) && is_callable( array( WC()->cart, 'get_cart_hash' ) ) ? WC()->cart->get_cart_hash() : '-1';

		if ( ! empty( $_COOKIE['woocommerce_cart_hash'] ) && wc_clean( wp_unslash( $_COOKIE['woocommerce_cart_hash'] ) ) !== $cart_hash ) {
			ob_start();

			woocommerce_mini_cart();

			$mini_cart = ob_get_clean();

			$fragments = array_merge(
				$fragments,
				apply_filters(
					'woocommerce_add_to_cart_fragments',
					array(
						'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
					)
				)
			);
		}

		return $fragments;
	}

	/**
	 * Register/queue frontend scripts.
	 */
	public static function load_scripts() {

		if ( ! did_action( 'before_woocommerce_init' ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Scripts.
		$deps = array( 'jquery' );
		if ( wcpbc_is_pro() ) {
			$deps[] = 'wc-price-based-country-pro-frontend';
		}

		wp_register_script( 'wc-price-based-country-ajax-geo', WCPBC()->plugin_url() . 'assets/js/ajax-geolocation' . $suffix . '.js', $deps, WCPBC()->version, true );
		wp_localize_script(
			'wc-price-based-country-ajax-geo',
			'wc_price_based_country_ajax_geo_params',
			array(
				'wc_ajax_url' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			)
		);

		// Styles.
		wp_register_style( 'wc-price-based-country-frontend', WCPBC()->plugin_url() . 'assets/css/frontend' . $suffix . '.css', array(), WCPBC()->version );

		// Enqueue.
		if ( WCPBC_Ajax_Geolocation::is_enabled() && ! ( is_cart() || is_account_page() || is_checkout() || is_customize_preview() || apply_filters( 'wc_price_based_country_dequeue_script', false ) ) ) {

			do_action( 'wc_price_based_country_before_enqueue_script' );

			wp_enqueue_script( 'wc-price-based-country-ajax-geo' );
			wp_enqueue_style( 'wc-price-based-country-frontend' );

		}
	}

	/**
	 * Set the customer country before the frontend pricing is loaded
	 *
	 * @since 1.7.8
	 */
	public static function set_customer_country() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			// Pay for order page.
			self::pay_for_order_country( wc_clean( wp_unslash( $_GET['key'] ) ) );

		} elseif ( ! empty( $_REQUEST['wcpbc-manual-country'] ) ) {
			// Request param.
			wcpbc_set_woocommerce_country( wc_clean( wp_unslash( $_REQUEST['wcpbc-manual-country'] ) ) );
			wc_nocache_headers();
			add_action( 'wp', array( __CLASS__, 'init_session' ), 100 );

		} elseif ( defined( 'WC_DOING_AJAX' ) && WC_DOING_AJAX && isset( $_GET['wc-ajax'] ) && 'update_order_review' === $_GET['wc-ajax'] ) {
			// Checkout page.
			self::checkout_country();

		} elseif ( ! empty( $_POST['calc_shipping_country'] ) && ! empty( $_POST['calc_shipping'] ) && self::verify_shipping_calculator_nonce() ) {
			// Shipping calculator.
			self::calculate_shipping_country();
		}
		// phpcs:enable
	}

	/**
	 * Set customer country when customer arrives to the pay for order page
	 *
	 * @param string $order_key Key of the order.
	 * @since 1.7.8
	 */
	private static function pay_for_order_country( $order_key ) {
		$order    = false;
		$order_id = wc_get_order_id_by_order_key( $order_key );

		if ( ! $order_id ) {
			return;
		}

		$billing_country  = wcpbc_get_order_country( $order_id, 'billing' );
		$shipping_country = wcpbc_get_order_country( $order_id, 'shipping' );

		if ( $billing_country ) {
			WC()->customer->set_billing_country( $billing_country );
			WC()->customer->set_shipping_country( $billing_country );
		}
		if ( $shipping_country ) {
			WC()->customer->set_shipping_country( $shipping_country );
		}

		add_action( 'wp_loaded', array( __CLASS__, 'init_session' ), 20 );
	}

	/**
	 * Update WooCommerce Customer country on checkout
	 */
	private static function checkout_country() {
		check_ajax_referer( 'update-order-review', 'security' );

		$countries = WC()->countries->get_countries();
		$postdata  = [
			'country'   => false,
			's_country' => false,
		];

		foreach ( array_keys( $postdata ) as $key ) {

			$value = isset( $_POST[ $key ] ) ? wc_clean( wp_unslash( $_POST[ $key ] ) ) : false;

			if ( $value && isset( $countries[ $value ] ) ) {

				$postdata[ $key ] = $value;

			} elseif ( false !== $value ) {

				/**
				 * Invalid country! Set to null to WooCommerce does not update the customer country with 'undefined'
				 * In a beautiful world, this should not happen, but the WooCommerce Javascript developers are cool, so they change jQuery.ajax() with fetch because jQuery is not cool :P, and they have broken it!
				 * https://github.com/woocommerce/woocommerce/pull/36275
				 */
				$_POST[ $key ] = null;
			}
		}

		if ( $postdata['country'] ) {
			WC()->customer->set_billing_country( $postdata['country'] );
			WC()->customer->set_shipping_country( $postdata['country'] );
		}

		if ( ! wc_ship_to_billing_address_only() && $postdata['s_country'] ) {
			WC()->customer->set_shipping_country( $postdata['s_country'] );
		}
	}

	/**
	 * Verify the shipping calculator nonce
	 *
	 * @since 1.7.6
	 * @return boolan
	 */
	private static function verify_shipping_calculator_nonce() {

		$nonce_value = ! empty( $_REQUEST['woocommerce-shipping-calculator-nonce'] ) ? $_REQUEST['woocommerce-shipping-calculator-nonce'] : ''; // @codingStandardsIgnoreLine.
		if ( empty( $nonce_value ) && ! empty( $_REQUEST['_wpnonce'] ) ) {
			$nonce_value = $_REQUEST['_wpnonce']; // @codingStandardsIgnoreLine.
		}
		return wp_verify_nonce( $nonce_value, 'woocommerce-shipping-calculator' ) || wp_verify_nonce( $nonce_value, 'woocommerce-cart' );
	}

	/**
	 * Updates the customer country on calculate shipping.
	 *
	 * @see WC_Shortcode_Cart::calculate_shipping
	 */
	private static function calculate_shipping_country() {

		$country  = ! empty( $_POST['calc_shipping_country'] ) ? wc_clean( wp_unslash( $_POST['calc_shipping_country'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$postcode = ! empty( $_POST['calc_shipping_postcode'] ) ? wc_format_postcode( wc_clean( wp_unslash( $_POST['calc_shipping_postcode'] ) ), $country ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $postcode && $country && ! WC_Validation::is_postcode( $postcode, $country ) ) {
			return;
		}

		if ( $country ) {

			if ( ! WC()->customer->get_billing_first_name() || apply_filters( 'wc_price_based_country_calculate_shipping_billing_country_update', true ) ) {
				WC()->customer->set_billing_country( $country );
			}
			WC()->customer->set_shipping_country( $country );
		}
	}

	/**
	 * Maybe Calculate totals.
	 *
	 * @since 3.4.2
	 */
	public static function maybe_calculate_totals() {
		if ( ! did_action( 'woocommerce_cart_loaded_from_session' ) || did_action( 'woocommerce_after_calculate_totals' ) ) {
			// Nothing to do.
			return;
		}

		if ( WC()->cart->is_empty() ) {
			// Cart is empty.
			return;
		}

		$cart_hash = empty( $_COOKIE['woocommerce_cart_hash'] ) ? false : wc_clean( wp_unslash( $_COOKIE['woocommerce_cart_hash'] ) );

		if ( WC()->cart->get_cart_hash() !== $cart_hash ) {
			// Cart content changed. Calculate totals.
			WC()->cart->calculate_totals();
		}
	}

	/**
	 * Init customer session and refresh cart totals
	 *
	 * @since 1.7.0
	 * @version 3.4.0 Use the get_cart_hash to update the cart_hash cookie.
	 * @access public
	 */
	public static function init_session() {
		if ( headers_sent() ) {
			return;
		}

		if ( isset( WC()->session ) && is_callable( [ WC()->session, 'has_session' ] ) && ! WC()->session->has_session() ) {
			do_action( 'woocommerce_set_cart_cookies', true );
		}

		WC()->customer->save();

		if ( WC()->cart->is_empty() ) {
			// The cart is empty. Update the cart's hash to trigger get_refreshed_fragments.
			wc_setcookie( 'woocommerce_cart_hash', WC()->cart->get_cart_hash() );
		}

		if ( isset( $_POST['redirect'] ) && '1' === $_POST['redirect'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			global $wp;
			$args        = wc_clean( array_intersect_key( $_GET, array_flip( [ 'p', 'page_id', 'product', 'post_type' ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$current_url = add_query_arg( $args, trailingslashit( home_url( $wp->request ) ) );
			wp_safe_redirect( $current_url );
			exit;
		}
	}

	/**
	 * Init frontend hooks.
	 *
	 * @since 2.1.0
	 */
	public static function frontend_init() {
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'update_order_meta' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'update_order_meta' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_checkout' ), 10, 2 );
	}

	/**
	 * Add zone data to order meta
	 *
	 * @since 2.1.0
	 * @version 3.2.0 Compatible with HPOS.
	 * @param WC_Order $order Order instance.
	 */
	public static function update_order_meta( $order ) {
		if ( ! ( wcpbc_the_zone() && is_a( $order, 'WC_Order' ) ) ) {
			return;
		}

		$base_exchange_rate = wcpbc_the_zone()->get_base_currency_amount( 1 );
		$pricing_zone_data  = wcpbc_the_zone()->get_data();

		// Update metadata.
		$order->update_meta_data( '_wcpbc_base_exchange_rate', $base_exchange_rate );
		$order->update_meta_data( '_wcpbc_pricing_zone', $pricing_zone_data );
	}

	/**
	 * Copy the order metadata to the refund order.
	 *
	 * @since 2.2.2
	 * @param int $order_id Order ID.
	 * @param int $refund_id Refund order ID.
	 */
	public static function order_refunded( $order_id, $refund_id ) {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );

		if ( ! ( $order && $refund ) ) {
			return;
		}

		$base_exchange_rate = $order->get_meta( '_wcpbc_base_exchange_rate' );
		$pricing_zone_data  = $order->get_meta( '_wcpbc_pricing_zone' );

		$refund->update_meta_data( '_wcpbc_base_exchange_rate', $base_exchange_rate );
		$refund->update_meta_data( '_wcpbc_pricing_zone', $pricing_zone_data );
		$refund->save();
	}

	/**
	 * Print test store message
	 */
	public static function test_store_message() {
		if ( 'no' === get_option( 'wc_price_based_country_test_mode', 'no' ) ) {
			return;
		}

		$test_country = get_option( 'wc_price_based_country_test_country' );
		$countries    = WC()->countries->countries;

		if ( is_string( $test_country ) && $test_country && ! empty( $countries[ $test_country ] ) ) {
			$country = WC()->countries->countries[ $test_country ];
			// translators: HTML tags.
			echo wp_kses_post( '<p class="demo_store">' . sprintf( __( '%1$sPrice Based Country%2$s test mode enabled for testing %3$s. You should do tests on private browsing mode. Browse in private with %4$sFirefox%7$s, %5$sChrome%7$s and %6$sSafari%7$s', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>', $country, '<a style="display:inline;float:none;text-decoration:underline;" target="_blank" href="https://support.mozilla.org/en-US/kb/private-browsing-use-firefox-without-history">', '<a style="display:inline;float:none;text-decoration:underline;" target="_blank" href="https://support.google.com/chrome/answer/95464?hl=en">', '<a style="display:inline;float:none;text-decoration:underline;" target="_blank" href="https://support.apple.com/kb/ph19216?locale=en_US">', '</a>' ) . '</p>' );
		}
	}

	/**
	 * Validates that the pricing zone against the order.
	 *
	 * @since  4.2.0
	 * @param  array    $data   An array of posted data.
	 * @param  WP_Error $errors Validation errors.
	 */
	public static function validate_checkout( $data, $errors ) {

		if ( ! ( empty( $errors->errors ) && apply_filters( 'wc_price_based_country_validate_checkout', true, $data ) ) ) {
			return;
		}

		$zone_id         = wcpbc_the_zone() ? wcpbc_the_zone()->get_id() : false;
		$country_zone    = wcpbc_get_zone_by_country();
		$country_zone_id = $country_zone ? $country_zone->get_id() : false;

		if ( $zone_id !== $country_zone_id ) {

			WC()->session->set( 'refresh_totals', true );

			$debug_data = [
				'loaded'   => $zone_id,
				'checkout' => $country_zone_id,
			];

			$errors->add( 'pricing_zone', __( 'We were unable to process your order, please try again.', 'woocommerce-product-price-based-on-countries' ) );

			if ( current_user_can( 'manage_woocommerce' ) ) {
				$errors->add(
					'pricing_zone_debug',
					__( 'The loaded pricing zone does not match the pricing zone for the checkout billing/shipping country. This message is visible only to admins.', 'woocommerce-product-price-based-on-countries' )
				);
				$errors->add( 'pricing_zone_debug_data', wp_json_encode( $debug_data ) );
			}

			if ( function_exists( 'wc_log_order_step' ) ) {
				wc_log_order_step(
					'[Shortcode] Pricing zone validation error. Refresh total and try again.',
					[
						'price_based_on_country' => $debug_data,
					]
				);
			}
		}
	}

	/**
	 * Output manual country select form
	 *
	 * @param string $other_countries_text Other countries text.
	 */
	public static function output_country_selector( $other_countries_text = '' ) {
		$atts = array();

		if ( ! empty( $other_countries_text ) ) {
			$atts = array(
				'other_countries_text' => $other_countries_text,
			);
		}

		echo self::shortcode_country_selector( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Return the country select form
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_country_selector( $atts ) {

		$atts = shortcode_atts(
			array(
				'remove_other_countries' => false,
				'flag'                   => 0,
				'other_countries_text'   => apply_filters( 'wcpbc_other_countries_text', __( 'Other countries', 'woocommerce-product-price-based-on-countries' ) ),
				'title'                  => '',
			),
			$atts,
			'wcpbc_country_selector'
		);

		ob_start();

		the_widget(
			'WCPBC_Widget_Country_Selector',
			$atts,
			array(
				'before_widget' => '',
				'after_widget'  => '',
			)
		);

		return ob_get_clean();
	}

}
