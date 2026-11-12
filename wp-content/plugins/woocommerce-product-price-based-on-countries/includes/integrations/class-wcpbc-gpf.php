<?php
/**
 * Handle integration with WooCommerce Google Product Feed by Ademti Software Ltd.
 *
 * @see https://woocommerce.com/products/google-product-feed/
 * @since 1.8.15
 * @package WCPBC/Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCPBC_GPF' ) ) :

	/**
	 * * WCPBC_GPF Class
	 */
	class WCPBC_GPF {

		/**
		 * Current country.
		 *
		 * @var string
		 */
		private static $country;

		/**
		 * Hook actions and filters
		 */
		public static function init() {
			// Do not cache the schema data!
			add_filter( 'woocommerce_product_get_woocommerce_gpf_schema_cache', '__return_false' );

			// Bail if no country forced.
			if ( empty( $_GET['wcpbc-manual-country'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return;
			}
			self::$country = wc_clean( wp_unslash( $_GET['wcpbc-manual-country'] ) );// phpcs:ignore WordPress.Security.NonceVerification

			// Add filters.
			add_filter( 'woocommerce_gpf_cache_name', array( __CLASS__, 'granularise_cache_name' ), 10, 1 );
			add_filter( 'woocommerce_gpf_feed_item', array( __CLASS__, 'add_country_arg_to_product_permalinks' ), 10, 2 );
			add_filter( 'woocommerce_gpf_store_info', array( __CLASS__, 'update_store_info' ), 99 );
			add_filter( 'woocommerce_gpf_product_price_calculator_callback', array( __CLASS__, 'set_tax_location' ), 99 );
		}

		/**
		 * Add the country to the cache salt.
		 *
		 * @param string $name Cache salt.
		 * @return string
		 */
		public static function granularise_cache_name( $name ) {
			return $name . '_' . self::$country . wcpbc()->version;
		}

		/**
		 * Add country to the product permalink.
		 *
		 * @param WoocommerceGpfFeedItem $feed_item Feed item.
		 * @param WC_Product             $wc_product Product.
		 * @return mixed
		 */
		public static function add_country_arg_to_product_permalinks( $feed_item, $wc_product ) {

			$feed_item->purchase_link = add_query_arg(
				array(
					'wcpbc-manual-country' => self::$country,
				),
				$feed_item->purchase_link
			);

			return $feed_item;
		}

		/**
		 * Update feed generation store info.
		 *
		 * @param mixed $store_info The store info array.
		 */
		public static function update_store_info( $store_info ) {
			// Set the base country to fix taxes calculation options.
			if ( isset( $store_info->feed_url ) ) {
				$store_info->feed_url = add_query_arg( 'wcpbc-manual-country', self::$country, $store_info->feed_url );
			}
			return $store_info;
		}

		/**
		 * If the country matches a pricing zone, set tax location to the current country.
		 *
		 * @param mixed $value Filter value. No needed.
		 */
		public static function set_tax_location( $value ) {
			if ( ! has_filter( 'woocommerce_get_tax_location', array( __CLASS__, 'get_tax_location' ) ) ) {
				add_filter( 'woocommerce_get_tax_location', array( __CLASS__, 'get_tax_location' ), 30 );
			}
			return $value;
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
	}

	WCPBC_GPF::init();
endif;
