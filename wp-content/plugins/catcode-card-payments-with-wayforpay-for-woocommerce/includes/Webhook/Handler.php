<?php
/**
 * WayForPay serviceUrl callback receiver — REST route catcode-wayforpay/v1/webhook.
 *
 * Verifies the merchantSignature (HMAC-MD5), reconciles the referenced order,
 * then answers with the signed accept-response WayForPay expects (otherwise it
 * retries the callback for up to 4 days).
 *
 * @package CcWayForPayWC
 */

namespace CatCode\WayForPayWC\Webhook;

use CatCode\WayForPayWC\Gateway\Method;

defined( 'ABSPATH' ) || exit;

class Handler {

	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route(
			'catcode-wayforpay/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				// Public by design: WayForPay cannot authenticate as a WordPress
				// user. Every payload is rejected unless its HMAC-MD5 signature,
				// made with the merchant secret key, verifies.
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

		$data = $this->parse( $request );
		if ( ! is_array( $data ) || empty( $data['orderReference'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'bad payload' ), 400 );
		}
		$gateway->log( 'webhook in: ' . wp_json_encode( $data ) );

		if ( ! $gateway->client()->verify_response( $data ) ) {
			$gateway->log( 'webhook signature INVALID' );
			return new \WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
		}

		$order = $this->resolve_order( (string) $data['orderReference'] );
		if ( $order && $order->get_payment_method() === Method::ID ) {
			$gateway->apply_status( $order, $data );
		}

		// Always acknowledge so WayForPay stops retransmitting.
		$accept = $gateway->client()->accept_response( (string) $data['orderReference'], time() );
		return new \WP_REST_Response( $accept, 200 );
	}

	/** WayForPay may post the payload as raw JSON or as form-encoded fields. */
	private function parse( \WP_REST_Request $request ): array {
		$body = $request->get_body();
		if ( '' !== $body ) {
			$json = json_decode( $body, true );
			if ( is_array( $json ) ) {
				return $json;
			}
		}
		$params = $request->get_params();
		return is_array( $params ) ? $params : array();
	}

	/** orderReference is "{orderId}-{attempt}-{keyTail}" — resolve by the prefix. */
	private function resolve_order( string $reference ): ?\WC_Order {
		$id = (int) strtok( $reference, '-' );
		if ( $id <= 0 ) {
			return null;
		}
		$order = wc_get_order( $id );
		return $order instanceof \WC_Order ? $order : null;
	}
}
