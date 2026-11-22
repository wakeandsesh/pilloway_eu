<?php
/**
 * Plugin Name: Pilloway Add to Cart Tracking
 * Description: Publishes successful WooCommerce additions to Google Tag Manager.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'woocommerce_add_to_cart',
	static function ( $key, $product_id, $quantity, $variation_id ) {
		if ( is_admin() && ! wp_doing_ajax() || headers_sent() ) {
			return;
		}
		if ( 'all' !== ( $_COOKIE['pilloway_consent'] ?? '' ) ) {
			return;
		}

		$product = wc_get_product( $variation_id ?: $product_id );
		if ( ! $product ) {
			return;
		}

		$price = (float) wc_get_price_to_display( $product );
		$item = array(
			'item_id'   => (string) $product->get_id(),
			'item_name' => $product->get_name(),
			'price'     => $price,
			'quantity'  => $quantity,
		);
		if ( $variation_id ) {
			$item['item_variant'] = wc_get_formatted_variation( $product, true, false, false );
		}

		// A short-lived cookie works across AJAX and redirects without embedding
		// customer-specific cart data in cacheable page HTML.
		setcookie(
			'pilloway_cart_event_' . wp_generate_uuid4(),
			wp_json_encode( array(
				'currency' => get_woocommerce_currency(),
				'value'    => round( $price * $quantity, wc_get_price_decimals() ),
				'items'    => array( $item ),
			) ),
			array( 'expires' => time() + 60, 'path' => '/', 'secure' => is_ssl(), 'samesite' => 'Lax' )
		);
	},
	20,
	4
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$file = 'pilloway-add-to-cart-tracking.js';
		wp_enqueue_script( 'pilloway-add-to-cart-tracking', WPMU_PLUGIN_URL . '/' . $file, array( 'jquery' ), (string) filemtime( __DIR__ . '/' . $file ), true );
	}
);
