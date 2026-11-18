<?php
/**
 * Plugin Name: Pilloway Meta Pixel
 * Description: Adds the Meta Pixel base code to the Ukrainian Pilloway storefront.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

function pilloway_meta_pixel_id() {
	$id = defined( 'PILLOWAY_META_PIXEL_ID' ) ? PILLOWAY_META_PIXEL_ID : get_option( 'pilloway_meta_pixel_id', '' );
	return preg_match( '/^[0-9]{5,20}$/D', (string) $id ) ? (string) $id : '';
}

require_once __DIR__ . '/conversions-api.php';

add_action( 'wp_head', 'pilloway_ua_meta_pixel_output', 1 );
add_action( 'wp_footer', 'pilloway_ua_meta_pixel_ecommerce_events', 30 );
add_action( 'woocommerce_add_to_cart', 'pilloway_ua_meta_pixel_queue_cart_event', 10, 4 );

/**
 * Print Meta's base pixel code in the document head.
 *
 * @return void
 */
function pilloway_ua_meta_pixel_output() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() ) {
		return;
	}
	$pixel_id = pilloway_meta_pixel_id();
	if ( ! $pixel_id ) {
		return;
	}
	?>
	<script>
	(function () {
		var id = <?php echo wp_json_encode( $pixel_id ); ?>;
		var started = false;
		function allowed() {
			return /(?:^|;\s*)pilloway_consent=all(?:;|$)/.test(document.cookie);
		}
		function start() {
			if (started || !allowed()) return;
			started = true;
			if (!window.fbq) {
				var fbq = window.fbq = function () {
					if (fbq.callMethod) fbq.callMethod.apply(fbq, arguments);
					else fbq.queue.push(arguments);
				};
				window._fbq = fbq;
				fbq.push = fbq;
				fbq.loaded = true;
				fbq.version = '2.0';
				fbq.queue = [];
				var script = document.createElement('script');
				script.async = true;
				script.src = 'https://connect.facebook.net/en_US/fbevents.js';
				document.head.appendChild(script);
			}
			window.fbq('init', id);
			window.fbq('trackSingle', id, 'PageView');
			window.dispatchEvent(new Event('pilloway:meta-ready'));
		}
		window.pillowayMetaTrack = function (event, params, options) {
			if (!started || !allowed()) return false;
			window.fbq('trackSingle', id, event, params, options);
			return true;
		};
		window.addEventListener('pilloway:consent', start);
		start();
	})();
	</script>
	<?php
}
/**
 * Emit WooCommerce events with amounts calculated by WooCommerce itself.
 *
 * @return void
 */
function pilloway_ua_meta_pixel_ecommerce_events() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ! pilloway_meta_pixel_id() || ! function_exists( 'WC' ) || ! WC() ) {
		return;
	}

	$event = null;
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_queried_object_id() );
		if ( $product && $product->is_purchasable() ) {
			$event = array(
				'name'   => 'ViewContent',
				'params' => array(
					'content_ids'  => array( (string) $product->get_id() ),
					'content_type' => 'product',
					'value'        => (float) wc_get_price_to_display( $product ),
					'currency'     => get_woocommerce_currency(),
				),
			);
		}
	} elseif ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && WC()->cart && ! WC()->cart->is_empty() ) {
		$content_ids = array();
		$item_count  = 0;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id    = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			$content_ids[] = (string) $product_id;
			$item_count   += (int) $cart_item['quantity'];
		}
		$event = array(
			'name'   => 'InitiateCheckout',
			'params' => array(
				'content_ids'  => array_values( array_unique( $content_ids ) ),
				'content_type' => 'product',
				'value'        => (float) WC()->cart->get_total( 'edit' ),
				'currency'     => get_woocommerce_currency(),
				'num_items'    => $item_count,
			),
		);
	} elseif ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		$order_id  = absint( get_query_var( 'order-received' ) );
		$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		$order     = $order_id ? wc_get_order( $order_id ) : false;
		$purchase_confirmed = pilloway_meta_purchase_confirmed( $order );
		if ( $purchase_confirmed && $order_key && hash_equals( $order->get_order_key(), $order_key ) ) {
			$content_ids = array();
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( $product ) {
					$content_ids[] = (string) $product->get_id();
				}
			}
			$event = array(
				'name'   => 'Purchase',
				'id'     => pilloway_meta_purchase_event_id( $order ),
				'params' => array(
					'content_ids'  => array_values( array_unique( $content_ids ) ),
					'content_type' => 'product',
					'value'        => (float) $order->get_total(),
					'currency'     => $order->get_currency(),
					'num_items'    => (int) $order->get_item_count(),
				),
			);
		}
	}

	// A normal form submission may return to the product, homepage or checkout.
	// Emit the queued addition on whichever storefront page renders next.
	$added_item = WC()->session ? WC()->session->get( 'pilloway_meta_added_item' ) : null;
	if ( is_array( $added_item ) ) {
		WC()->session->__unset( 'pilloway_meta_added_item' );
	}
	?>
	<script>
	(function () {
		var added = <?php echo wp_json_encode( $added_item ); ?>;
		var event = <?php echo wp_json_encode( $event ); ?>;
		function trackPageEvents() {
			var track = window.pillowayMetaTrack;
			if (!track) return;
			if (added && track('AddToCart', added)) added = null;
			if (!event) return;
			var store, key, options;
			try {
				if (event.name === 'Purchase') {
					store = localStorage;
					key = 'pilloway-meta-' + event.id;
					options = {eventID: event.id};
				} else if (event.name === 'InitiateCheckout') {
					store = sessionStorage;
					key = 'pilloway-meta-checkout-' + <?php echo wp_json_encode( WC()->cart ? WC()->cart->get_cart_hash() : '' ); ?>;
				}
				if (store && store.getItem(key)) return;
			} catch (e) {}
			if (track(event.name, event.params, options)) {
				try { if (store) store.setItem(key, '1'); } catch (e) {}
				event = null;
			}
		}
		window.addEventListener('pilloway:meta-ready', trackPageEvents);
		trackPageEvents();
	})();
	if (window.jQuery) {
		jQuery(document.body).on('added_to_cart', function (e, fragments, cartHash, button) {
			var productId = button && button.data('product_id');
			if (!productId || !window.pillowayMetaTrack) return;
			var quantity = parseInt(button.data('quantity') || 1, 10);
			var value = parseFloat(button.data('pilloway_value'));
			var params = {
				content_ids: [String(productId)],
				content_type: 'product',
				currency: <?php echo wp_json_encode( get_woocommerce_currency() ); ?>,
				num_items: quantity
			};
			if (Number.isFinite(value)) params.value = value * quantity;
			window.pillowayMetaTrack('AddToCart', params);
		});
	}
	</script>
	<?php
}

/**
 * Queue non-AJAX additions for the following cart page.
 *
 * @param string $cart_item_key Cart item key.
 * @param int    $product_id    Product ID.
 * @param int    $quantity      Quantity added.
 * @param int    $variation_id  Variation ID.
 * @return void
 */
function pilloway_ua_meta_pixel_queue_cart_event( $cart_item_key, $product_id, $quantity, $variation_id = 0 ) {
	if ( wp_doing_ajax() || ( defined( 'WC_DOING_AJAX' ) && WC_DOING_AJAX ) || ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	$product = wc_get_product( $variation_id ? $variation_id : $product_id );
	if ( ! $product ) {
		return;
	}
	WC()->session->set(
		'pilloway_meta_added_item',
		array(
			'content_ids'  => array( (string) $product->get_id() ),
			'content_type' => 'product',
			'currency'     => get_woocommerce_currency(),
			'num_items'    => (int) $quantity,
			'value'        => (float) wc_get_price_to_display( $product ) * (int) $quantity,
		)
	);
}

/**
 * Add the displayed price to archive add-to-cart buttons for AJAX events.
 *
 * @param array      $args    Button arguments.
 * @param WC_Product $product Product.
 * @return array
 */
function pilloway_ua_meta_pixel_loop_button_price( $args, $product ) {
	$args['attributes']['data-pilloway_value'] = (string) wc_get_price_to_display( $product );
	return $args;
}
add_filter( 'woocommerce_loop_add_to_cart_args', 'pilloway_ua_meta_pixel_loop_button_price', 10, 2 );

