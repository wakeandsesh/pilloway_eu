<?php
/**
 * Integrations
 *
 * Handle integrations between PBC and 3rd-Party plugins
 *
 * @version 1.6.14
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCPBC_Integrations
 */
class WCPBC_Integrations {

	/**
	 * Integrations array.
	 *
	 * @var array
	 */
	private static $integrations = false;

	/**
	 * Add built-in integrations
	 */
	public static function init() {

		self::$integrations = [
			'woogle_get_container'                 => dirname( __FILE__ ) . '/integrations/class-wcpbc-google-listing-and-ads.php',
			'wcpay_init'                           => dirname( __FILE__ ) . '/integrations/class-wcpbc-wc-payments.php',
			'Sitepress'                            => dirname( __FILE__ ) . '/integrations/class-wcpbc-wpml.php',
			'Polylang'                             => dirname( __FILE__ ) . '/integrations/class-wcpbc-polylang.php',
			'AngellEYE_Gateway_Paypal'             => dirname( __FILE__ ) . '/integrations/class-wcpbc-paypal-express-angelleye.php',
			'WC_Gateway_Twocheckout'               => dirname( __FILE__ ) . '/integrations/class-wcpbc-gateway-2checkout.php',
			'WC_Product_Addons'                    => dirname( __FILE__ ) . '/integrations/class-wcpbc-product-addons-basic.php',
			'WC_Dynamic_Pricing'                   => dirname( __FILE__ ) . '/integrations/class-wcpbc-dynamic-pricing-basic.php',
			'WoocommerceGpfCommon'                 => dirname( __FILE__ ) . '/integrations/class-wcpbc-gpf.php',
			'\Ademti\WoocommerceProductFeeds\Main' => dirname( __FILE__ ) . '/integrations/class-wcpbc-gpf.php',
			'WCS_ATT_Abstract_Module'              => dirname( __FILE__ ) . '/integrations/class-wcpbc-wcs-att.php',
			'WC_Gateway_PPEC_Plugin'               => dirname( __FILE__ ) . '/integrations/class-wcpbc-gateway-paypal-express-checkout.php',
			'RP_WCDPD'                             => dirname( __FILE__ ) . '/integrations/class-wcpbc-rightpress-product-price-shop.php',
			'woocommerce_payu_add_gateway'         => dirname( __FILE__ ) . '/integrations/class-wcpbc-payu-payment-gateway.php',
			'\Elementor\Plugin'                    => dirname( __FILE__ ) . '/integrations/class-wcpbc-elementor.php',
			'WC_Shipping_UPS_Init'                 => dirname( __FILE__ ) . '/integrations/class-wcpbc-shipping-ups-fedex.php',
			'WC_Shipping_Fedex_Init'               => dirname( __FILE__ ) . '/integrations/class-wcpbc-shipping-ups-fedex.php',
			'Aelia\WC\EU_VAT_Assistant\WC_Aelia_EU_VAT_Assistant' => dirname( __FILE__ ) . '/integrations/class-wcpbc-eu-vat-assistant.php',
			'Cartflows_Pro_Loader'                 => dirname( __FILE__ ) . '/integrations/class-wcpbc-cartflows.php',
			'Woo_Variation_Swatches_Pro'           => dirname( __FILE__ ) . '/integrations/class-wcpbc-variation-swatches-emran-ahmed.php',
			'Flexible_Shipping_Plugin'             => dirname( __FILE__ ) . '/integrations/class-wcpbc-flexible-shipping.php',
			'DevOwl\RealCookieBanner\Core'         => dirname( __FILE__ ) . '/integrations/class-wcpbc-real-cookie-banner.php',
			'woocommerce_gateway_stripe'           => dirname( __FILE__ ) . '/integrations/class-wcpbc-stripe-upe.php',
			'PMWI_Plugin'                          => dirname( __FILE__ ) . '/integrations/class-wcpbc-wpallimport-ad.php',
			'learndash_woocommerce'                => dirname( __FILE__ ) . '/integrations/class-wcpbc-learndash-course.php',
		];
	}

	/**
	 * Add built-in integrations
	 */
	public static function add() {

		if ( ! self::$integrations ) {
			return;
		}

		foreach ( self::$integrations as $class => $integration_file ) {

			if ( class_exists( $class ) || function_exists( $class ) ) {

				include_once $integration_file;
			}
		}

		/**
		 * Woo Discount Rules by Flycart.
		 *
		 * @since 2.0.11
		 * @version 2.2.1 Moved to "wp" hook
		 * @see https://wordpress.org/plugins/woo-discount-rules/
		 */
		if ( defined( 'WDR_SLUG' ) && 'woo_discount_rules' === WDR_SLUG && WCPBC_Ajax_Geolocation::is_enabled() ) {
			add_action( 'wp', array( __CLASS__, 'woo_discount_rules_fixes' ) );
		}

		/**
		 * Advanced Dynamic Pricing for WooCommerce by AlgolPlus
		 *
		 * @since 3.2.2
		 * @see https://wordpress.org/plugins/advanced-dynamic-pricing-for-woocommerce/
		 */
		if ( defined( 'WC_ADP_PLUGIN_FILE' ) && 'advanced-dynamic-pricing-for-woocommerce.php' === WC_ADP_PLUGIN_FILE && WCPBC_Ajax_Geolocation::is_enabled() ) {
			add_action( 'wp', array( __CLASS__, 'adp_algolplus_compatibility' ) );
		}

		/**
		 * WP Rocket JS delay exclusions.
		 *
		 * @since 3.4
		 */
		if ( function_exists( 'rocket_init' ) ) {
			add_filter( 'rocket_delay_js_exclusions', [ __CLASS__, 'rocket_delay_js_exclusions' ] );
		}

		/**
		 * SiteGround Optimizer JS exclusions.
		 *
		 * @since 3.4
		 */
		if ( class_exists( 'SiteGround_Optimizer\Loader\Loader' ) ) {
			add_filter( 'sgo_javascript_combine_exclude', [ __CLASS__, 'sgo_javascript_exclude' ] );
		}

		/**
		 * Automattic Jetpack Boost JS exclusions.
		 *
		 * @since 3.4
		 */
		if ( defined( 'JETPACK_BOOST_VERSION' ) ) {
			add_filter( 'js_do_concat', [ __CLASS__, 'jetpack_boost_js_do_concat' ], 9999, 2 );
		}

		/**
		 * PayPal Payments integration.
		 */
		self::add_paypal_payments_integration();

	}

	/**
	 * Is a plugin is active?
	 *
	 * @param string $plugin Plugin to check.
	 * @return bool
	 */
	private static function is_plugin_active( $plugin ) {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) || self::is_plugin_active_for_network( $plugin );
	}

	/**
	 * Is a plugin active for network?
	 *
	 * @param string $plugin Plugin to check.
	 * @return bool
	 */
	private static function is_plugin_active_for_network( $plugin ) {
		if ( ! is_multisite() ) {
			return false;
		}

		$plugins = get_site_option( 'active_sitewide_plugins' );
		if ( isset( $plugins[ $plugin ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Include the PayPal Payments plugin integration.
	 */
	public static function add_paypal_payments_integration() {
		if ( self::is_plugin_active( 'woocommerce-paypal-payments/woocommerce-paypal-payments.php' ) ) {
			include dirname( __FILE__ ) . '/integrations/class-wcpbc-paypal-payments.php';
		}
	}

	/**
	 * Woo Discount Rules by Flycart is a very aggresive plugin. It removes the price filter of the others hooks. We need to add the filters again.
	 */
	public static function woo_discount_rules_fixes() {
		// For AJAX Geolocation increment the filter priority.
		remove_filter( 'woocommerce_get_price_html', array( 'WCPBC_Ajax_Geolocation', 'price_html_wrapper' ), 0, 2 );
		add_filter( 'woocommerce_get_price_html', array( 'WCPBC_Ajax_Geolocation', 'price_html_wrapper' ), 9999, 2 );
	}

	/**
	 * Change priority of AJAX geolocation wrapper.
	 */
	public static function adp_algolplus_compatibility() {
		// This plugin uses PHP_INT_MAX -1 !.
		remove_filter( 'woocommerce_get_price_html', array( 'WCPBC_Ajax_Geolocation', 'price_html_wrapper' ), 0, 2 );
		add_filter( 'woocommerce_get_price_html', array( 'WCPBC_Ajax_Geolocation', 'price_html_wrapper' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Filters the WP Rocket delay JS exclusions array.
	 *
	 * @since 3.4
	 * @param array $excluded Array of excluded patterns.
	 */
	public static function rocket_delay_js_exclusions( $excluded ) {
		if ( ! is_array( $excluded ) ) {
			$excluded = [];
		}

		$excluded[] = '/wp-includes/js/jquery/jquery(.min)?.js';
		$excluded[] = '/wp-content/plugins/woocommerce-product-price-based-on-countries/assets/js/ajax-geolocation(.min)?.js';
		$excluded[] = '/wp-content/plugins/woocommerce-price-based-country-pro-addon/assets/js/frontend(.min)?.js';

		return $excluded;
	}

	/**
	 * Filters the SG JS exclusions array.
	 *
	 * @since 3.4
	 * @param array $excluded Array of javascript handles.
	 */
	public static function sgo_javascript_exclude( $excluded ) {
		if ( ! is_array( $excluded ) ) {
			$excluded = [];
		}

		$excluded[] = 'wc-price-based-country-pro-frontend';
		$excluded[] = 'wc-price-based-country-ajax-geo';

		return $excluded;
	}

	/**
	 * Filters the jetpack_boost JS exclusions array.
	 *
	 * @since 3.4
	 * @param bool   $do_concat Returns false for exclude.
	 * @param string $handle Javascript handle.
	 */
	public static function jetpack_boost_js_do_concat( $do_concat, $handle ) {
		if ( in_array( $handle, self::sgo_javascript_exclude( [] ), true ) ) {
			$do_concat = false;
		}
		return $do_concat;
	}

}
