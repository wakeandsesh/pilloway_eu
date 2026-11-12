<?php
/**
 * Plugin Name: Pilloway GA4 Event Additions
 * Description: Adds missing GA4 view_cart and select_item events to the free WooCommerce integration.
 * Version: 1.2.0
 * Requires Plugins: woocommerce, woocommerce-google-analytics-integration
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'pilloway_ga4_view_cart_enqueue', 100 );

/**
 * Add the recommended view_cart event on the Cart page.
 * The official free integration handles the other enabled ecommerce events.
 */
function pilloway_ga4_view_cart_enqueue() {
	if (
		! function_exists( 'WC' )
		|| ! class_exists( 'WC_Google_Analytics_Integration' )
		|| ! class_exists( 'WC_Abstract_Google_Analytics_JS' )
		|| ! function_exists( 'is_cart' )
		|| ! is_cart()
	) {
		return;
	}

	$cart = WC()->cart;
	if ( ! $cart || $cart->is_empty() ) {
		return;
	}

	$commerce_data = pilloway_ga4_view_cart_payload( $cart );
	if ( empty( $commerce_data['items'] ) ) {
		return;
	}

	$tracker = apply_filters( 'woocommerce_gtag_tracker_variable', 'gtag' );
	if ( ! is_string( $tracker ) || '' === $tracker ) {
		$tracker = 'gtag';
	}

	$script = sprintf(
		'(function(config){var tracker=window[config.tracker];if(typeof tracker!=="function"){return;}tracker("event","view_cart",config.commerce);})(%s);',
		wp_json_encode(
			array(
				'tracker'  => $tracker,
				'commerce' => $commerce_data,
			),
			JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
		)
	);

	wp_add_inline_script( 'woocommerce-google-analytics-integration', $script, 'after' );
}

/**
 * Format cart data using GA4 ecommerce item parameters.
 * Item value excludes tax and shipping and reflects line discounts.
 *
 * @param WC_Cart $cart Cart instance.
 * @return array
 */
function pilloway_ga4_view_cart_payload( $cart ): array {
	$items          = array();
	$value          = 0.0;
	$price_decimals = wc_get_price_decimals();

	foreach ( $cart->get_cart() as $cart_item ) {
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof WC_Product || ! $product->exists() ) {
			continue;
		}

		$quantity  = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );
		$line_total = isset( $cart_item['line_total'] )
			? (float) $cart_item['line_total']
			: ( (float) $product->get_price() * $quantity );

		if ( is_callable( array( 'WC_Abstract_Google_Analytics_JS', 'get_product_identifier' ) ) ) {
			$item_id = WC_Abstract_Google_Analytics_JS::get_product_identifier( $product );
		} else {
			$item_id = (string) $product->get_id();
		}

		$item = array(
			'item_id'   => (string) $item_id,
			'item_name' => $product->get_name(),
			'price'     => (float) wc_format_decimal( $line_total / $quantity, $price_decimals ),
			'quantity'  => $quantity,
		);

		$parent_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$category_ids = wc_get_product_cat_ids( $parent_id );
		if ( ! empty( $category_ids ) ) {
			$category = get_term( (int) $category_ids[0], 'product_cat' );
			if ( $category instanceof WP_Term ) {
				$item['item_category'] = $category->name;
			}
		}

		$items[] = $item;
		$value  += $line_total;
	}

	$payload = array(
		'currency' => get_woocommerce_currency(),
		'value'    => (float) wc_format_decimal( $value, $price_decimals ),
		'items'    => $items,
	);

	$coupons = array_filter( array_map( 'sanitize_text_field', (array) $cart->get_applied_coupons() ) );
	if ( $coupons ) {
		$payload['coupon'] = implode( ', ', $coupons );
	}

	return $payload;
}


add_action( 'wp_enqueue_scripts', 'pilloway_ga4_select_item_enqueue', 110 );

/**
 * Add GA4's recommended select_item ecommerce event alongside the free integration's
 * legacy select_content product-click event. The integration supplies the identifier,
 * honors its event toggle, and owns the gtag/consent configuration.
 *
 * @return void
 */
function pilloway_ga4_select_item_enqueue() {
	if (
		! class_exists( 'WC_Google_Analytics_Integration' )
		|| ! class_exists( 'WC_Abstract_Google_Analytics_JS' )
	) {
		return;
	}

	$tracker = apply_filters( 'woocommerce_gtag_tracker_variable', 'gtag' );
	if ( ! is_string( $tracker ) || '' === $tracker ) {
		$tracker = 'gtag';
	}

	$script = sprintf(
		'(function(config){var tracker=window[config.tracker];if(typeof tracker!=="function"||tracker.__pillowaySelectItemPatched){return;}var wrapped=function(){if(arguments[0]==="event"&&arguments[1]==="select_content"){var params=arguments[2]||{};var settings=window.ga4w&&window.ga4w.settings||{};var events=settings.events||[];if(params.content_type==="product"&&params.content_id&&Array.isArray(events)&&events.indexOf("select_content")!==-1&&events.indexOf("select_item")===-1){var payload={items:[{item_id:String(params.content_id)}]};var listName=window.ga4w&&window.ga4w.data&&window.ga4w.data.list_name;if(listName){payload.item_list_name=String(listName);}tracker("event","select_item",payload);}}return tracker.apply(this,arguments);};wrapped.__pillowaySelectItemPatched=true;window[config.tracker]=wrapped;})(%s);',
		wp_json_encode(
			array( 'tracker' => $tracker ),
			JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
		)
	);

	wp_add_inline_script( 'woocommerce-google-analytics-integration', $script, 'after' );
}
