<?php
/**
 * Handle integration with LearnDash WooCommerce.
 *
 * @see http://www.learndash.com/work/woocommerce/
 * @version 1.0.0
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCPBC_LearnDash_Course' ) ) :

	/**
	 * WCPBC_LearnDash_Course Class
	 */
	class WCPBC_LearnDash_Course {

		/**
		 * Hook actions and filters
		 */
		public static function init() {
			add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_data_options' ), 5 );
			add_action( 'woocommerce_process_product_meta_course', array( 'WCPBC_Admin_Meta_Boxes', 'process_product_meta' ) );
			add_filter( 'wc_price_based_country_product_types_overriden', array( __CLASS__, 'product_types_overriden' ) );
			if ( defined( 'LD_GROUP_REGISTRATION_VERSION' ) && version_compare( LD_GROUP_REGISTRATION_VERSION, '4.3', '>' ) ) {
				// LearnDash Group Registration support.
				add_action( 'wc_price_based_country_frontend_princing_init', array( __CLASS__, 'add_price_filters' ) );
				add_action( 'wc_price_based_country_frontend_princing_unset', array( __CLASS__, 'remove_price_filters' ) );
				add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'get_cart_item_from_session' ), 5 );
				if ( WCPBC_Ajax_Geolocation::is_enabled() ) {
					add_filter( 'wc_price_based_country_ajax_geolocation_ldgr_unlimited_members_price_content', array( __CLASS__, 'ajax_geolocation_ldgr_unlimited_members_price' ), 10, 2 );
					add_filter( 'wc_price_based_country_ajax_geolocation_product_data', array( __CLASS__, 'ajax_geolocation_product_data' ), 10, 3 );
					add_action( 'ldgr_action_before_template', array( __CLASS__, 'before_template' ), 5, 2 );
					add_action( 'wp_footer', array( __CLASS__, 'print_scripts' ), 0, 2 );
				}
			}
		}

		/**
		 * Adds show_if_course class to the pricing div.
		 *
		 * @since 1.0.0
		 */
		public static function product_data_options() {
			?>
			<script type="text/javascript">
				jQuery( function($) {
					$( 'div.options_group.wcpbc_pricing' ).addClass( 'show_if_course' );
					$( 'input#_downloadable' ).triggerHandler('change'); //call to show_and_hide_panels WooCommerce function
				} );
			</script>
			<?php
		}

		/**
		 * Add course product type to the handled product types.
		 *
		 * @param array $types Array of product types.
		 */
		public static function product_types_overriden( $types ) {
			array_push( $types, 'course' );
			return $types;
		}

		/**
		 * Add price filters
		 */
		public static function add_price_filters() {
			add_filter( 'get_post_metadata', array( __CLASS__, 'get_ldgr_unlimited_member_price' ), 10, 4 );
		}

		/**
		 * Remove price filters
		 */
		public static function remove_price_filters() {
			remove_filter( 'get_post_metadata', array( __CLASS__, 'get_ldgr_unlimited_member_price' ), 10, 4 );
		}

		/**
		 * Filter the ldgr_unlimited_member_price metadata
		 *
		 * @param null|array|string $meta_value The value get_metadata() should return - a single metadata value or an array of values.
		 * @param int               $object_id Object ID.
		 * @param string            $meta_key Meta key.
		 * @param bool              $single Whether to return only the first value of the specified $meta_key.
		 */
		public static function get_ldgr_unlimited_member_price( $meta_value, $object_id, $meta_key, $single ) {
			static $avoid_recursion = false;

			if ( $avoid_recursion || ! ( $single && 'ldgr_unlimited_members_option_price' === $meta_key && wcpbc_the_zone() ) ) {
				return $meta_value;
			}

			$avoid_recursion = true;
			$value           = wcpbc_the_zone()->get_exchange_rate_price_by_post( $object_id, 'ldgr_unlimited_members_option_price' );
			$avoid_recursion = false;

			return $value;
		}
		/**
		 * Fixes LearnDash Group Registration price override
		 *
		 * @param array $session_data Data for an item in the cart.
		 * @return array
		 */
		public static function get_cart_item_from_session( $session_data ) {
			if ( isset( $session_data['ldgr_unlimited_member_price'], $session_data['data'] ) && is_callable( [ $session_data['data'], 'get_id' ] ) ) {
				$session_data['ldgr_unlimited_member_price'] = get_post_meta( $session_data['data']->get_id(), 'ldgr_unlimited_members_option_price', true );
			}

			if ( isset( $session_data['ldgr_new_price'], $session_data['data'] ) && is_callable( [ $session_data['data'], 'get_price' ] ) ) {
				$session_data['ldgr_new_price'] = $session_data['data']->get_price();
			}
			return $session_data;
		}

		/**
		 * Before LearnDash Group Registration template
		 *
		 * @param array  $args          Template arguments.
		 * @param string $template_path Template path.
		 */
		public static function before_template( $args, $template_path ) {
			if ( 'ldgr-single-product-unlimited-members.template.php' === substr( $template_path, -50 ) ) {
				add_filter( 'wc_price', array( __CLASS__, 'price_wrapper' ), 1 );
				add_action( 'ldgr_action_after_template', array( __CLASS__, 'after_template' ), 5 );
			}
		}

		/**
		 * Wrapper the ldgr_unlimited_member_price string.
		 *
		 * @param string $return Price HTML markup.
		 */
		public static function price_wrapper( $return ) {
			global $product;
			if ( ! is_a( $product, 'WC_Product' ) ) {
				return $return;
			}

			return sprintf(
				'<span class="wc-price-based-country-refresh-area" data-area="ldgr_unlimited_members_price" data-id="%s" data-options="%s">%s</span>',
				'ldgr-unlimited-members-price-' . $product->get_id(),
				esc_attr( wp_json_encode( [ 'product_id' => $product->get_id() ] ) ),
				$return
			);
		}

		/**
		 * After LearnDash Group Registration template
		 */
		public static function after_template() {
			remove_filter( 'wc_price', array( __CLASS__, 'price_wrapper' ), 1 );
		}

		/**
		 * Refresh ldgr_unlimited_members_price via AJAX.
		 *
		 * @param string $content Content string.
		 * @param array  $data    Content data.
		 */
		public static function ajax_geolocation_ldgr_unlimited_members_price( $content, $data ) {
			$product_id = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
			if ( $product_id ) {
				$price = get_post_meta( $product_id, 'ldgr_unlimited_members_option_price', true );
				if ( $price ) {
					$content = sprintf(
						'<span class="wc-price-based-country-refresh-area" data-area="ldgr_unlimited_members_price">%s</span>',
						wc_price( $price )
					);
				}
			}
			return $content;
		}

		/**
		 * Add the ldgr_unlimited_members_price to the AJAX geo product data array.
		 *
		 * @param array      $data Product data.
		 * @param WC_Product $product Product object.
		 * @param bool       $single Is single product page?.
		 */
		public static function ajax_geolocation_product_data( $data, $product, $single ) {
			if ( ! $single ) {
				return $data;
			}

			$product_id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
			if ( $product_id ) {
				$unlimited_price              = get_post_meta( $product_id, 'ldgr_unlimited_members_option_price', true );
				$data['ldgr_unlimited_price'] = $unlimited_price ? floatval( $unlimited_price ) : 0;
			}

			return $data;
		}

		/**
		 * Refresh the wdm_single_product_gr_js and wdm_single_product_functions_js data
		 */
		public static function print_scripts() {
			global $product;
			if ( ! ( wp_script_is( 'wdm_single_product_gr_js', 'enqueued' ) && is_a( $product, 'WC_Product' ) ) ) {
				return;
			}
			?>
			<script type="text/javascript">
				(function($) {
					const productId = <?php echo absint( $product->get_id() ); ?>;
					$(document.body).on('wc_price_based_country_set_product_price', function(e, data ) {
						if ( 'undefined' !== typeof wdm_gr_data && 'undefined' !== typeof data[productId] ) {
							wdm_gr_data.price = data[productId].display_price;
							wdm_gr_data.ldgr_unlimited_price = data[productId].ldgr_unlimited_price;
						}
					});

					$(document.body).on('wc_price_based_country_set_currency_params', function(e, data ) {
						if ( 'undefined' !== typeof wdm_functions_data ) {
							wdm_functions_data.thousand_separator = data.thousand_sep;
							wdm_functions_data.decimal_separator = data.decimal_sep;
							wdm_functions_data.decimals = data.num_decimals;
							wdm_functions_data.price_format = data.price_format;
							wdm_functions_data.currency_symbol = data.symbol_decode;
						}
					});
				})(jQuery);
			</script>
			<?php

		}
	}

	WCPBC_LearnDash_Course::init();

endif;
