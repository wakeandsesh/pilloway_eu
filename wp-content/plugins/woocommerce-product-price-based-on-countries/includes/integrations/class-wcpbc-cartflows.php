<?php
/**
 * Handle integration with CartFlows by CartFlows Inc.
 *
 * @see https://cartflows.com/
 *
 * @since 3.1.1
 * @package WCPBC/Integrations
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_CartFlows class.
 */
class WCPBC_CartFlows {

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
		$plugin_version = defined( 'CARTFLOWS_PRO_VER' ) ? CARTFLOWS_PRO_VER : 'unknown';
		$min_version    = '1.11.9';

		if ( 'unknown' === $plugin_version || version_compare( $plugin_version, $min_version, '<' ) ) {
			// translators: 1: HTML tag, 2: HTML tag, 3: Google Listings and Ads.
			self::$notice = sprintf( __( '%1$sPrice Based on Country Pro & CartFlows%2$s compatibility %1$srequires%2$s CartFlows %1$s+%4$s%2$s. You are running CartFlows %3$s.', 'woocommerce-product-price-based-on-countries' ), '<strong>', '</strong>', $plugin_version, $min_version );
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

		add_filter( 'cartflows_filter_display_price', [ __CLASS__, 'filter_display_price' ], 10, 3 );
		add_filter( 'woocommerce_add_cart_item', [ __CLASS__, 'add_cart_item' ] );
		add_filter( 'woocommerce_get_cart_item_from_session', [ __CLASS__, 'get_cart_item_from_session' ] );
		add_filter( 'woocommerce_update_order_review_fragments', [ __CLASS__, 'update_order_review_fragments' ] );
	}

	/**
	 * Returns the product price.
	 *
	 * @param float  $price price.
	 * @param int    $product_id current product ID.
	 * @param string $context context of action.
	 */
	public static function filter_display_price( $price, $product_id, $context = 'convert' ) {
		if ( 'original' !== $context && $product_id && wcpbc_the_zone() ) {
			return wcpbc_the_zone()->get_post_price( $product_id, '_price' );
		}
		return $price;
	}

	/**
	 * Add the current pricing zone ID to cart item data.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @return array
	 */
	public static function add_cart_item( $cart_item_data ) {
		global $post;

		if ( isset( $cart_item_data['custom_price'] ) ) {

			$checkout_id = isset( $_GET['wcf_checkout_id'] ) ? absint( $_GET['wcf_checkout_id'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification

			if ( ! $checkout_id && isset( $post->ID ) ) {
				$checkout_id = $post->ID;
			}

			$unique_id = isset( $cart_item_data['wcf_product_data'], $cart_item_data['wcf_product_data']['unique_id'] ) ? $cart_item_data['wcf_product_data']['unique_id'] : false;

			if ( ! $unique_id && isset( $cart_item_data['option'], $cart_item_data['option']['unique_id'] ) ) {
				$unique_id = $cart_item_data['option']['unique_id'];
			}

			if ( $checkout_id && $unique_id ) {

				$cart_item_data['wcpbc_cartflow_data'] = [
					'zone_id'     => wcpbc_the_zone() ? wcpbc_the_zone()->get_id() : '',
					'checkout_id' => $checkout_id,
					'unique_id'   => $unique_id,
				];
			}
		}

		return $cart_item_data;
	}

	/**
	 * Update the cartflows custom price.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @return array
	 */
	public static function get_cart_item_from_session( $cart_item_data ) {

		if ( ! (
			isset( $cart_item_data['wcpbc_cartflow_data'], $cart_item_data['custom_price'] ) &&
			did_action( 'wc_price_based_country_frontend_init' ) &&
			function_exists( 'wcf' ) &&
			isset( wcf()->utils ) &&
			is_callable( [ wcf()->utils, 'get_selected_checkout_products' ] ) &&
			class_exists( 'Cartflows_Checkout_Markup' ) &&
			is_callable( [ 'Cartflows_Checkout_Markup', 'get_instance' ] ) &&
			is_callable( [ Cartflows_Checkout_Markup::get_instance(), 'calculate_discount' ] )
		) ) {
			return $cart_item_data;
		}

		$zone_id = wcpbc_the_zone() ? wcpbc_the_zone()->get_id() : '';

		if ( $cart_item_data['wcpbc_cartflow_data']['zone_id'] === $zone_id ) {
			return $cart_item_data;
		}

		$cart_item_data['wcpbc_cartflow_data']['zone_id'] = $zone_id;

		$checkout_id = absint( $cart_item_data['wcpbc_cartflow_data']['checkout_id'] );
		$unique_id   = $cart_item_data['wcpbc_cartflow_data']['unique_id'];
		$products    = wcf()->utils->get_selected_checkout_products( $checkout_id );

		if ( empty( $products ) || ! is_array( $products ) ) {
			return $cart_item_data;
		}

		foreach ( $products as $data ) {
			if ( isset( $data['unique_id'] ) && $data['unique_id'] === $unique_id ) {

				$discount_type  = isset( $data['discount_type'] ) ? $data['discount_type'] : '';
				$discount_value = ! empty( $data['discount_value'] ) ? $data['discount_value'] : '';
				$_product_price = $cart_item_data['data']->get_price();

				$custom_price = Cartflows_Checkout_Markup::get_instance()->calculate_discount( '', $discount_type, $discount_value, $_product_price );

				$cart_item_data['custom_price'] = $custom_price;
				break;
			}
		}

		return $cart_item_data;
	}

	/**
	 * Add "your product options" to the update order review fragments.
	 *
	 * @param array $fragments Fragments.
	 */
	public static function update_order_review_fragments( $fragments ) {
		$checkout_id = empty( $_GET['wcf_checkout_id'] ) ? false : absint( wc_clean( wp_unslash( $_GET['wcf_checkout_id'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! (
			$checkout_id &&
			function_exists( 'wcf' ) &&
			isset( wcf()->options ) &&
			is_callable( [ wcf()->options, 'get_checkout_meta_value' ] ) &&
			'yes' === wcf()->options->get_checkout_meta_value( $checkout_id, 'wcf-enable-product-options' ) &&
			class_exists( 'Cartflows_Pro_Product_Options' ) &&
			is_callable( [ 'Cartflows_Pro_Product_Options', 'get_instance' ] ) &&
			is_callable( [ Cartflows_Pro_Product_Options::get_instance(), 'get_all_main_products' ] ) &&
			is_callable( [ Cartflows_Pro_Product_Options::get_instance(), 'your_product_price' ] ) &&
			is_callable( [ Cartflows_Pro_Product_Options::get_instance(), 'calculate_input_discount_data' ] ) &&
			defined( 'CARTFLOWS_PRO_CHECKOUT_DIR' ) &&
			file_exists( CARTFLOWS_PRO_CHECKOUT_DIR . 'templates/your-product/item-price.php' )
		) ) {
			return $fragments;
		}

		$products    = Cartflows_Pro_Product_Options::get_instance()->get_all_main_products( $checkout_id );
		$product_sel = [];
		foreach ( $products as $data ) {
			$current_product = wc_get_product( $data['product'] );
			if ( ! $current_product ) {
				continue;
			}

			if ( is_a( $current_product, 'WC_Product_Variable' ) ) {
				foreach ( $current_product->get_children() as $var_index => $variation_id ) {
					$single_variation = new WC_Product_Variation( $variation_id );

					$price_data = Cartflows_Pro_Product_Options::get_instance()->your_product_price( $current_product, $data, $single_variation );

					$product_sel[ $variation_id ] = [
						'original_price'   => $price_data['sel_data']['original_price'],
						'discounted_price' => $price_data['sel_data']['discounted_price'],
						'quantity'         => 1,
					];
				}
			} else {
				$price_data = Cartflows_Pro_Product_Options::get_instance()->your_product_price( $current_product, $data, 0 );

				$product_sel[ $current_product->get_id() ] = [
					'original_price'   => $price_data['sel_data']['original_price'],
					'discounted_price' => $price_data['sel_data']['discounted_price'],
					'quantity'         => 1,
				];
			}
		}

		foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
			if ( isset( $product_sel[ $cart_item['data']->get_id() ] ) ) {
				$product_sel[ $cart_item['data']->get_id() ]['quantity'] = $cart_item['quantity'];
			}
		}

		foreach ( $product_sel as $product_id => $data ) {
			$display_discount_data = Cartflows_Pro_Product_Options::get_instance()->calculate_input_discount_data(
				$data['original_price'],
				$data['discounted_price'],
				$data['quantity']
			);
			$fragment_key          = ".wcf-qty-options .wcf-qty-row-{$product_id} .wcf-price";
			$price_data            = [
				'original_price' => $display_discount_data['display_price'],
			];

			ob_start();
			require CARTFLOWS_PRO_CHECKOUT_DIR . 'templates/your-product/item-price.php';
			$fragments[ $fragment_key ] = ob_get_clean();
		}

		return $fragments;
	}
}

WCPBC_CartFlows::init();
