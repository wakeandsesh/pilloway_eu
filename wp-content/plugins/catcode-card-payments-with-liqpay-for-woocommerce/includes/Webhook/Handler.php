<?php
/**
 * LiqPay server_url callback receiver — REST route catcode-liqpay/v1/webhook.
 *
 * LiqPay POSTs two form fields: `data` (base64 JSON) and `signature`. The
 * signature is verified against the RAW received data before anything is
 * decoded, so a forged payload never reaches the order.
 *
 * @package CcLiqPayWC
 */

namespace CatCode\LiqPayWC\Webhook;

use CatCode\LiqPayWC\Gateway\Method;

defined( 'ABSPATH' ) || exit;

class Handler {

	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route(
			'catcode-liqpay/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'receive' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private function gateway(): ?Method {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$gw       = $gateways[ Method::ID ] ?? null;
		return $gw instanceof Method ? $gw : null;
	}

	public function receive( \WP_REST_Request $request ) {
		$gateway = $this->gateway();
		if ( ! $gateway ) {
			return new \WP_REST_Response( array( 'error' => 'gateway unavailable' ), 503 );
		}

		list( $raw_data, $signature ) = $this->parse( $request );
		if ( '' === $raw_data || '' === $signature ) {
			return new \WP_REST_Response( array( 'error' => 'bad payload' ), 400 );
		}

		// Verify BEFORE decoding — the signature covers the raw string.
		if ( ! $gateway->client()->verify_callback( $raw_data, $signature ) ) {
			$gateway->log( 'webhook signature INVALID' );
			return new \WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
		}

		$data = $gateway->client()->decode( $raw_data );
		$gateway->log( 'webhook in: ' . wp_json_encode( $data ) );
		if ( empty( $data['order_id'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'no order_id' ), 400 );
		}

		$order = $this->resolve_order( (string) $data['order_id'] );
		if ( $order && $order->get_payment_method() === Method::ID ) {
			$gateway->apply_status( $order, $data );
		}

		// Always 200 so LiqPay stops retransmitting this callback.
		return new \WP_REST_Response( array( 'status' => 'accepted' ), 200 );
	}

	/**
	 * LiqPay posts form-encoded data/signature; some setups proxy it as JSON.
	 *
	 * @return array{0:string,1:string} raw data, signature
	 */
	private function parse( \WP_REST_Request $request ): array {
		$data      = (string) $request->get_param( 'data' );
		$signature = (string) $request->get_param( 'signature' );

		if ( '' === $data ) {
			$json = json_decode( (string) $request->get_body(), true );
			if ( is_array( $json ) ) {
				$data      = (string) ( $json['data'] ?? '' );
				$signature = (string) ( $json['signature'] ?? '' );
			}
		}

		return array( trim( $data ), trim( $signature ) );
	}

	/** order_id is "{orderId}-{attempt}-{keyTail}" — resolve by the prefix. */
	private function resolve_order( string $reference ): ?\WC_Order {
		$id = (int) strtok( $reference, '-' );
		if ( $id <= 0 ) {
			return null;
		}
		$order = wc_get_order( $id );
		return $order ?: null;
	}
}
