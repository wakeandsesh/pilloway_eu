<?php
/**
 * Handle integration with Google Listings and Ads by WooCommerce.
 *
 * @see https://wordpress.org/plugins/google-listings-and-ads/
 *
 * @since 2.3.0
 * @package WCPBC/Integrations
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Google_Listing_and_Ads class.
 */
class WCPBC_Google_Listing_And_Ads {

	/**
	 * Target country.
	 *
	 * @var string
	 */
	private static $country;

	/**
	 * Admin notice.
	 *
	 * @var string
	 */
	private static $notice = '';

	/**
	 * Checks the environment for compatibility problems.
	 *
	 * @return boolean
	 */
	public static function check_environment() {

		$compatible     = true;
		$plugin_version = defined( 'WC_GLA_VERSION' ) ? WC_GLA_VERSION : 'unknown';
		$min_version    = '2.3.1';

		if ( 'unknown' === $plugin_version || version_compare( $plugin_version, $min_version, '<' ) ) {
			// translators: 1: HTML tag, 2: HTML tag, 3: Google for WooCommerce.
			self::$notice = sprintf( __( '%1$sPrice Based on Country Pro & Google for WooCommerce%2$s compatibility %1$srequires%2$s Google for WooCommerce %1$s+%4$s%2$s. You are running Google for WooCommerce %3$s.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>', $plugin_version, $min_version );
			add_action( 'admin_notices', array( __CLASS__, 'min_version_notice' ) );

			$compatible = false;
		}

		return $compatible;
	}

	/**
	 * Display admin minimun version required
	 */
	public static function min_version_notice() {
		echo '<div id="message" class="error"><p>' . wp_kses_post( self::$notice ) . '</p></div>';
	}

	/**
	 * Hook actions and filters
	 */
	public static function init() {

		if ( ! self::check_environment() ) {
			return;
		}

		add_action( 'update_option_wc_price_based_country_gla_integration', array( __CLASS__, 'start_products_sync' ) );
		add_action( 'gla/jobs/resubmit_expiring_products/start', array( __CLASS__, 'init_pricing' ), 0 );
		add_action( 'gla/jobs/update_all_products/process_item', array( __CLASS__, 'init_pricing' ), 0 );
		add_action( 'gla/jobs/update_products/process_item', array( __CLASS__, 'init_pricing' ), 0 );
	}

	/**
	 * Returns the options for plugin settings.
	 */
	public static function get_setting_options() {
		return array(
			array(
				'id'      => 'wc_price_based_country_gla_integration_mode',
				'name'    => 'wc_price_based_country_gla_integration[mode]',
				'label'   => __( 'Google for WooCommerce compatibility', 'woocommerce-product-price-based-on-countries' ),
				'desc'    => __( 'Enable the "Google for WooCommerce" compatibility. The plugin will use the prices for the country you select when it syncs the products with your Google Merchant account.', 'woocommerce-product-price-based-on-countries' ),
				'type'    => 'select',
				'options' => array(
					// Translators: country ISO code.
					'gla_country' => sprintf( __( '"Google for WooCommerce" main target country (%s)', 'woocommerce-product-price-based-on-countries' ), self::get_gla_target_country() ),
					'specific'    => __( 'Specific country', 'woocommerce-product-price-based-on-countries' ),
					''            => __( 'Deactivate. Use the default WooCommerce prices, and do not add the country to the product URL.', 'woocommerce-product-price-based-on-countries' ),
				),
			),

			array(
				'id'      => 'wc_price_based_country_gla_integration_country',
				'name'    => 'wc_price_based_country_gla_integration[country]',
				'label'   => __( 'Country for "Google for WooCommerce" compatibility', 'woocommerce-product-price-based-on-countries' ),
				'type'    => 'country-select',
				'options' => WC()->countries->countries,
				'show-if' => array(
					array(
						'field'    => 'wc_price_based_country_gla_integration_mode',
						'operator' => '=',
						'value'    => 'specific',
					),
				),
			),
		);
	}

	/**
	 * Sync all products.
	 */
	public static function start_products_sync() {
		try {

			if ( ! function_exists( 'woogle_get_container' ) ) {
				return;
			}

			$job_repository = woogle_get_container()->get( 'Automattic\WooCommerce\GoogleListingsAndAds\Jobs\JobRepository' );

			$update = $job_repository->get( 'Automattic\WooCommerce\GoogleListingsAndAds\Jobs\UpdateAllProducts' );
			$update->schedule_delayed( 900 ); // 15 minutes

		} catch ( Exception $e ) {
			WCPBC_Debug_Logger::log_error( $e->getMessage(), __METHOD__ );
		}
	}

	/**
	 * Returns the target country.
	 */
	public static function get_gla_target_country() {
		$target_country = false;

		try {

			if ( ! function_exists( 'woogle_get_container' ) ) {
				return false;
			}

			$target_audience = woogle_get_container()->get( 'Automattic\WooCommerce\GoogleListingsAndAds\MerchantCenter\TargetAudience' );
			$target_country  = $target_audience->get_main_target_country();

		} catch ( Exception $e ) {

			WCPBC_Debug_Logger::log_error( $e->getMessage(), __METHOD__ );

			$target_audience = false;
		}
		return $target_country;
	}

	/**
	 * Init frontend pricing for the target country.
	 */
	public static function init_pricing() {
		static $done = false;
		if ( $done ) {
			// do only once.
			return;
		}

		$settings = wp_parse_args(
			get_option(
				'wc_price_based_country_gla_integration',
				array()
			),
			array(
				'mode'    => 'gla_country',
				'country' => '',
			)
		);

		self::$country = false;

		if ( 'gla_country' === $settings['mode'] ) {
			self::$country = self::get_gla_target_country();

		} elseif ( 'specific' === $settings['mode'] ) {
			self::$country = $settings['country'];

		}

		if ( self::$country ) {

			wcpbc()->current_zone = wcpbc_get_zone_by_country( self::$country );

			// Init frontend pricing.
			WCPBC_Frontend_Pricing::init();

			add_filter( 'woocommerce_get_tax_location', array( __CLASS__, 'get_tax_location' ), 30 );
			add_filter( 'woocommerce_gla_product_attribute_values', array( __CLASS__, 'product_attribute_values' ), 30, 3 );
		}

		$done = true;
	}

	/**
	 * Returns the current tax location.
	 *
	 * @param array $address Tax location.
	 */
	public static function get_tax_location( $address ) {
		return array(
			self::$country,
			'',
			'',
			'',
		);
	}

	/**
	 * Get link from WCProductAdapter instance.
	 *
	 * @param Automattic\WooCommerce\GoogleListingsAndAds\Product\WCProductAdapter $product_adapter The Adapted Google product object.
	 * @return string
	 */
	private static function get_link_from_product_adapter( $product_adapter ) {
		$link = '';

		if ( is_callable( [ $product_adapter, 'getLink' ] ) ) {

			$link = $product_adapter->getLink();

		} elseif ( is_callable( [ $product_adapter, 'get_product_input' ] ) ) {
			// 3.9 support.

			$product_input = $product_adapter->get_product_input();
			$attributes    = is_callable( [ $product_input, 'get_attributes' ] ) ? $product_input->get_attributes() : [];
			$link          = isset( $attributes['link'] ) ? $attributes['link'] : '';
		}

		return $link;
	}

	/**
	 * Update the product link.
	 *
	 * @param array            $attributes An array of values for the product properties.
	 * @param WC_Product       $wc_product The WooCommerce product object.
	 * @param WCProductAdapter $product_adapter The Adapted Google product object.
	 * @return array
	 */
	public static function product_attribute_values( $attributes, $wc_product, $product_adapter ) {
		$link = self::get_link_from_product_adapter( $product_adapter );
		if ( $link ) {
			$attributes['link'] = add_query_arg( 'wcpbc-manual-country', self::$country, $link );
		}
		return $attributes;
	}
}

WCPBC_Google_Listing_And_Ads::init();
