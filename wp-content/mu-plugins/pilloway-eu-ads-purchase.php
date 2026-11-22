<?php
/**
 * Plugin Name: Pilloway EU Ads Purchase
 * Description: Reports paid WooCommerce orders to the existing Google Ads purchase action.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_footer',
	static function () {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! in_array( $host, array( 'pilloway.eu', 'www.pilloway.eu' ), true ) ||
			! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$order = wc_get_order( absint( get_query_var( 'order-received' ) ) );
		$key   = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? wp_unslash( $_GET['key'] ) : '';
		if ( ! $order || ! $order->get_date_paid() || ! $order->is_paid() ||
			'quick' === $order->get_meta( '_pilloway_checkout_type' ) ||
			! $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return;
		}

		$conversion = array(
			'send_to'        => 'AW-18585303888/GOwtCPix47sdEND2lJ5F',
			'transaction_id' => 'pilloway-eu-' . $order->get_id(),
			'value'          => (float) $order->get_total(),
			'currency'       => $order->get_currency(),
		);
		?>
		<script>
		(function () {
			const conversion = <?php echo wp_json_encode( $conversion, JSON_HEX_TAG ); ?>;
			const key = 'pilloway-ads-purchase:' + conversion.transaction_id;
			let sent = false;
			function report() {
				if (sent || typeof window.gtag !== 'function' ||
					!/(?:^|;\s*)pilloway_consent=all(?:;|$)/.test(document.cookie)) return;
				try {
					if (localStorage.getItem(key)) return;
				} catch {}
				window.gtag('event', 'conversion', conversion);
				sent = true;
				try {
					localStorage.setItem(key, '1');
				} catch {}
			}
			window.addEventListener('pilloway:consent', report);
			report();
		})();
		</script>
		<?php
	},
	35
);
