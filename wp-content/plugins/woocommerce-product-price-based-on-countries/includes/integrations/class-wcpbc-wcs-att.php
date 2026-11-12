<?php
/**
 * Handle integration with All Products for WooCommerce Subscriptions Developed by SomewhereWarm.
 *
 * @see https://woocommerce.com/products/all-products-for-woocommerce-subscriptions/
 * @since 1.8.15
 * @package WCPBC/Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCPBC_WCS_ATT' ) ) :

	/**
	 * * WCPBC_WCS_ATT Class
	 */
	class WCPBC_WCS_ATT {

		/**
		 * Flag to control the subscription option wrapper.
		 *
		 * @var bool
		 */
		private static $wrapper_start = false;

		/**
		 * Scheme data for metaboxes.
		 *
		 * @var array
		 */
		private static $scheme_data = array();

		/**
		 * Init hooks.
		 */
		public static function init() {
			add_action( 'wc_price_based_country_frontend_princing_init', array( __CLASS__, 'frontend_init' ), 100 );
			add_action( 'wc_price_based_country_frontend_princing_unset', array( __CLASS__, 'frontend_unset' ), 100 );
			add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'product_data_panel' ), 20 );
			add_action( 'wcsatt_subscription_scheme_product_content', array( __CLASS__, 'subscription_scheme_product_content' ), 20, 3 );
			add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_data' ) );
			if ( is_callable( array( 'WCPBC_Ajax_Geolocation', 'is_enabled' ) ) && WCPBC_Ajax_Geolocation::is_enabled() ) {
				add_action( 'woocommerce_before_template_part', array( __CLASS__, 'subscription_options_wrapper_start' ), 0, 4 );
				add_action( 'woocommerce_after_template_part', array( __CLASS__, 'subscription_options_wrapper_end' ), 0 );
				add_filter( 'wc_price_based_country_ajax_geolocation_wcsatt_content', array( __CLASS__, 'ajax_geolocation_wcsatt_content' ), 10, 2 );
				add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html_wrapper' ), 10000, 2 );
				add_filter( 'wc_price_based_country_ajax_geolocation_product_data', array( __CLASS__, 'ajax_geolocation_product_data' ), 20, 3 );
			}
		}

		/**
		 * Frontend princing actions and filters
		 */
		public static function frontend_init() {
			add_filter( 'wc_price_based_country_should_filter_property', array( __CLASS__, 'should_filter_property' ), 10, 3 );
			add_filter( 'woocommerce_product_get__wcsatt_schemes', array( __CLASS__, 'get_wcsatt_schemes' ), 10, 2 );
		}

		/**
		 * Frontend princing unset
		 */
		public static function frontend_unset() {
			remove_filter( 'wc_price_based_country_should_filter_property', array( __CLASS__, 'should_filter_property' ), 10, 3 );
			remove_filter( 'woocommerce_product_get__wcsatt_schemes', array( __CLASS__, 'get_wcsatt_schemes' ), 10, 2 );
		}

		/**
		 * Do not filter the price properties is the product has a subscription override pricing mode.
		 *
		 * @param boolean    $value True or False.
		 * @param WC_Product $product Product instance.
		 * @param string     $prop The property that will be filtered.
		 * @return mixed
		 */
		public static function should_filter_property( $value, $product, $prop ) {
			if ( false !== strpos( $prop, 'price' ) && is_callable( [ 'WCS_ATT_Product', 'is_subscription' ] ) && WCS_ATT_Product::is_subscription( $product ) ) {
				$subscription_scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $product, 'object' );
				if ( ! empty( $subscription_scheme ) && $subscription_scheme->has_price_filter() ) {
					$pricing_mode = $subscription_scheme->get_pricing_mode();
					// Check the pricing mode.
					if ( 'override' === $pricing_mode ) {
						$value = false;
					}
				}
			}
			return $value;
		}

		/**
		 * Filters the meta wcsatt_schemes.
		 *
		 * @param mixed      $value Property value.
		 * @param WC_Product $product Product instance.
		 * @return mixed
		 */
		public static function get_wcsatt_schemes( $value, $product ) {
			if ( ! is_array( $value ) ) {
				return $value;
			}
			$schemes_data = wcpbc_the_zone()->get_postmeta( $product->get_id(), '_wcpbc_att_schemes' );
			foreach ( $value as $index => $data ) {
				$subscription_pricing_method = empty( $data['subscription_pricing_method'] ) ? false : $data['subscription_pricing_method'];
				if ( 'override' !== $subscription_pricing_method ) {
					continue;
				}

				if ( isset( $schemes_data[ $index ]['price_method'] ) && ! wcpbc_is_exchange_rate( $schemes_data[ $index ]['price_method'] ) ) {
					$value[ $index ]['subscription_regular_price'] = isset( $schemes_data[ $index ]['regular_price'] ) ? $schemes_data[ $index ]['regular_price'] : '';
					$value[ $index ]['subscription_sale_price']    = isset( $schemes_data[ $index ]['sale_price'] ) ? $schemes_data[ $index ]['sale_price'] : '';
				} else {
					$value[ $index ]['subscription_regular_price'] = wcpbc_the_zone()->get_exchange_rate_price( $data['subscription_regular_price'], true, 'product' );
					$value[ $index ]['subscription_sale_price']    = wcpbc_the_zone()->get_exchange_rate_price( $data['subscription_sale_price'], true, 'product' );
				}
			}
			return $value;
		}

		/**
		 * Set the product price to the pricing zone price for the product in the single page.
		 */
		public static function adjust_product_price() {
			global $product;
			WCPBC_Frontend_Pricing::adjust_product_price( $product );
		}

		/**
		 * Adds a wrapper for ajax geolocation.
		 *
		 * @since 2.1.13
		 * @param string $template_name Template name.
		 * @param string $template_path Template path. (default: '').
		 * @param string $located       Path. (default: '').
		 * @param array  $args          Arguments. (default: array).
		 */
		public static function subscription_options_wrapper_start( $template_name, $template_path, $located, $args ) {
			self::$wrapper_start = (
				'single-product/product-subscription-options.php' === $template_name
				&& false !== strpos( $located, 'woocommerce-all-products-for-subscriptions' )
				&& ! empty( $args['product'] )
				&& is_callable( array( $args['product'], 'is_type' ) )
				&& ! $args['product']->is_type( 'variation' )
			);

			if ( self::$wrapper_start ) {
				$product_id = $args['product']->get_id();

				self::$wrapper_start = true;
				printf( '<div class="wc-price-based-country-refresh-area" data-area="wcsatt" data-id="%s" data-options="%s">', esc_attr( $product_id ), esc_attr( wp_json_encode( array( 'product_id' => $product_id ) ) ) );
			}
		}

		/**
		 * Wrapper close.
		 *
		 * @since 2.1.13
		 * */
		public static function subscription_options_wrapper_end() {
			if ( self::$wrapper_start ) {
				echo '</div><!-- .wc-price-based-country-refresh-area -->';

				self::$wrapper_start = false;

				remove_action( 'woocommerce_before_template_part', array( __CLASS__, 'subscription_options_wrapper_start' ), 0, 4 );
				remove_action( 'woocommerce_after_template_part', array( __CLASS__, 'subscription_options_wrapper_end' ), 0 );
			}
		}

		/**
		 * Return the subscriptions options.
		 *
		 * @since 2.1.13
		 * @param string $content HTML content to return.
		 * @param array  $data Addon data.
		 * @return string
		 */
		public static function ajax_geolocation_wcsatt_content( $content, $data ) {

			$product_id = ! empty( $data['product_id'] ) ? absint( $data['product_id'] ) : false;

			if ( $product_id && is_callable( array( 'WCS_ATT_Display_Product', 'get_subscription_options_content' ) ) ) {
				$_product = wc_get_product( $product_id );
				if ( $_product ) {
					$content = WCS_ATT_Display_Product::get_subscription_options_content( $_product );
				}
			}

			return $content;
		}

		/**
		 * Add the wrapper to the price.
		 *
		 * @param string     $value Price HTML string.
		 * @param WC_Product $product Product instance.
		 * @return mixed
		 */
		public static function price_html_wrapper( $value, $product ) {
			if ( WCS_ATT_Product_Schemes::has_subscription_schemes( $product ) && ! WCS_ATT_Product::is_subscription( $product ) ) {
				$value = WCPBC_Ajax_Geolocation::wrapper_price( $product, $value );
			}
			return $value;
		}


		/**
		 * Ajax geolocation supports variations.
		 *
		 * @param array      $data Array of product data.
		 * @param WC_Product $variation_product Product instance.
		 * @param bool       $is_single Is single page?.
		 * @return array
		 */
		public static function ajax_geolocation_product_data( $data, $variation_product, $is_single ) {
			if ( ! $is_single || ! is_a( $variation_product, 'WC_Product_Variation' ) || ! is_callable( [ 'WCS_ATT_Display_Product', 'add_subscription_options_to_variation_data' ] ) ) {
				return $data;
			}

			$variable_product = wc_get_product( $variation_product->get_parent_id() );
			if ( ! $variable_product ) {
				return $data;
			}

			// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
			global $product;
			$global_product = $product;

			$product = $variable_product;

			$_data = WCS_ATT_Display_Product::add_subscription_options_to_variation_data( $data, $variable_product, $variation_product );

			if ( $data['price_html'] !== $_data['price_html'] ) {
				$data['price_html'] = $_data['price_html'];
			}

			$product = $global_product;
			// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals

			return $data;
		}

		/**
		 * Ouput CSS and init the scheme_data.
		 */
		public static function product_data_panel() {
			// Init private var.
			self::$scheme_data = array();
			?>
			<style>
				#wcsatt_data .wcpbc_pricing::after{
					content: " ";
					display: table;
					clear: both;
				}
				#wcsatt_data .wcpbc_pricing label {
					margin-left: 0;
					float: none;
					width: 100%
				}

				#wcsatt_data .wcpbc_pricing ul.wc-radios {
					width: 100%;
				}

				#wcsatt_data .wcpbc_pricing ul.wc-radios li {
					display: inline;
					margin-right: 15px;
				}
			</style>
			<?php
		}

		/**
		 * Product-specific subscription scheme options.
		 *
		 * @param int   $index Schema index.
		 * @param array $scheme_data Scheme data.
		 * @param int   $post_id Post ID.
		 */
		public static function subscription_scheme_product_content( $index, $scheme_data, $post_id ) {
			echo '<div class="subscription_pricing_method subscription_pricing_method_override" style="width:100%">';
			foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
				if ( ! isset( self::$scheme_data[ $zone->get_id() ] ) ) {
					self::$scheme_data[ $zone->get_id() ] = $zone->get_postmeta( $post_id, '_wcpbc_att_schemes' );
				}
				$_scheme_data = self::$scheme_data[ $zone->get_id() ];

				wcpbc_pricing_input(
					array(
						'name'   => "_wcpbc_att_schemes[$index][price_method]",
						'value'  => isset( $_scheme_data[ $index ]['price_method'] ) ? $_scheme_data[ $index ]['price_method'] : '',
						'fields' => array(
							'_regular_price' => array(
								'name'  => "_wcpbc_att_schemes[$index][regular_price]",
								// Translators: currency symbol.
								'label' => __( 'Regular price (%s)', 'woocommerce-product-price-based-on-countries' ),
								'value' => isset( $_scheme_data[ $index ]['regular_price'] ) ? $_scheme_data[ $index ]['regular_price'] : '',
							),
							'_sale_price'    => array(
								'name'  => "_wcpbc_att_schemes[$index][sale_price]",
								// Translators: currency symbol.
								'label' => __( 'Sale price (%s)', 'woocommerce-product-price-based-on-countries' ),
								'class' => 'wcpbc_sale_price',
								'value' => isset( $_scheme_data[ $index ]['sale_price'] ) ? $_scheme_data[ $index ]['sale_price'] : '',
							),
						),
					),
					$zone
				);
			}
			echo '</div>';
		}

		/**
		 * Save the data.
		 *
		 * @param WC_Product $product Product object.
		 */
		public static function save_data( $product ) {
			foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
				$scheme_data = $zone->get_input_var( '_wcpbc_att_schemes' );
				if ( empty( $scheme_data ) || ! is_array( $scheme_data ) ) {
					$zone->delete_postmeta( $product->get_id(), '_wcpbc_att_schemes' );
				} else {
					$data = array();
					foreach ( $scheme_data as $index => $scheme ) {
						$data[ $index ] = array(
							'price_method'  => $scheme['price_method'],
							'regular_price' => wc_format_decimal( $scheme['regular_price'] ),
							'sale_price'    => wc_format_decimal( $scheme['sale_price'] ),
						);
					}
					$zone->set_postmeta( $product->get_id(), '_wcpbc_att_schemes', $data );
				}
			}
		}

		/**
		 * Checks the environment for compatibility problems.
		 *
		 * @return boolean
		 */
		public static function check_environment() {
			$version = class_exists( 'WCS_ATT' ) && ! empty( WCS_ATT::VERSION ) ? WCS_ATT::VERSION : false;
			if ( ! $version || version_compare( $version, '3.1.21', '<' ) ) {
				add_action( 'admin_notices', array( __CLASS__, 'min_version_notice' ) );
				return false;
			}
			return true;
		}

		/**
		 * Display admin minimun version required
		 */
		public static function min_version_notice() {
			// Translators: 1, 2: HTML tags.
			$notice = sprintf( __( '%1$sPrice Based on Country Pro & All Products For Subscriptions%2$s compatibility requires All Products For Subscriptions +3.1.21.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>' );
			echo '<div id="message" class="error"><p>' . wp_kses_post( $notice ) . '</p></div>';
		}
	}

	if ( WCPBC_WCS_ATT::check_environment() ) {
		WCPBC_WCS_ATT::init();
	}

endif;
