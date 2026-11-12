<?php
/**
 * LiqPay API client + signature helpers.
 *
 * Docs: https://www.liqpay.ua/doc
 *
 * Every LiqPay exchange carries two fields: `data` (base64 of a JSON payload)
 * and `signature` — base64(sha1(private_key + data + private_key)). The same
 * pair is used for the checkout form, the server-to-server API and the
 * callback we receive, so one signing routine covers all three directions.
 *
 * @package CcLiqPayWC
 */

namespace CatCode\LiqPayWC\Api;

defined( 'ABSPATH' ) || exit;

class Client {

	/** Hosted payment page — the checkout form auto-POSTs here. */
	const CHECKOUT_URL = 'https://www.liqpay.ua/api/3/checkout';
	/** Server-to-server JSON API — status / refund / hold_completion. */
	const API_URL = 'https://www.liqpay.ua/api/request';
	/** API version pinned by LiqPay for the current protocol. */
	const API_VERSION = 3;

	/** @var string */
	private $public_key;
	/** @var string */
	private $private_key;
	/** @var bool */
	private $sandbox;
	/** @var int */
	private $timeout = 25;

	public function __construct( string $public_key, string $private_key, bool $sandbox = false ) {
		$this->public_key  = trim( $public_key );
		$this->private_key = trim( $private_key );
		$this->sandbox     = $sandbox;
	}

	public function public_key(): string {
		return $this->public_key;
	}

	public function is_sandbox(): bool {
		return $this->sandbox;
	}

	/** base64 of the JSON payload — the exact bytes that get signed. */
	public function encode( array $params ): string {
		return base64_encode( (string) wp_json_encode( $params, JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * base64(sha1(private_key + data + private_key)) over the RAW data string.
	 * Signing anything other than the transmitted `data` breaks verification.
	 */
	public function sign( string $data ): string {
		return base64_encode( sha1( $this->private_key . $data . $this->private_key, true ) );
	}

	/**
	 * Build the data+signature pair for the auto-submitting checkout form.
	 *
	 * @param array $o order_id, amount(float), currency, description,
	 *                 result_url, server_url, language, action, product[].
	 * @return array{url:string,data:string,signature:string}
	 */
	public function checkout_fields( array $o ): array {
		$params = array(
			'version'     => self::API_VERSION,
			'public_key'  => $this->public_key,
			'action'      => (string) ( $o['action'] ?? 'pay' ),
			'amount'      => self::money( (float) $o['amount'] ),
			'currency'    => (string) $o['currency'],
			'description' => (string) $o['description'],
			'order_id'    => (string) $o['order_id'],
		);

		foreach ( array( 'result_url', 'server_url', 'language', 'product_name', 'product_description', 'customer' ) as $key ) {
			if ( ! empty( $o[ $key ] ) ) {
				$params[ $key ] = (string) $o[ $key ];
			}
		}

		// Sandbox keys already isolate test traffic, but the flag makes the
		// test intent explicit on the payment page and in the callback status.
		if ( $this->sandbox ) {
			$params['sandbox'] = '1';
		}

		$data = $this->encode( $params );

		return array(
			'url'       => self::CHECKOUT_URL,
			'data'      => $data,
			'signature' => $this->sign( $data ),
		);
	}

	/**
	 * Verify a callback / result POST. LiqPay signs the same `data` string it
	 * sends, so we re-sign the received bytes and compare in constant time.
	 */
	public function verify_callback( string $data, string $signature ): bool {
		if ( '' === $data || '' === $signature ) {
			return false;
		}
		return hash_equals( $this->sign( $data ), $signature );
	}

	/** Decode the base64 JSON payload of a verified callback. */
	public function decode( string $data ): array {
		$json = json_decode( (string) base64_decode( $data, true ), true );
		return is_array( $json ) ? $json : array();
	}

	/** Current state of a payment. */
	public function status( string $order_id ): array {
		return $this->api(
			array(
				'action'   => 'status',
				'order_id' => $order_id,
			)
		);
	}

	/**
	 * POST a signed payload to the LiqPay API and normalise the answer.
	 *
	 * @return array{success:bool,status:int,data:array,errors:string[]}
	 */
	private function api( array $params ): array {
		$params['version']    = self::API_VERSION;
		$params['public_key'] = $this->public_key;

		$data = $this->encode( $params );

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => $this->timeout,
				'body'    => array(
					'data'      => $data,
					'signature' => $this->sign( $data ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'status'  => 0,
				'data'    => array(),
				'errors'  => array( $response->get_error_message() ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();

		// LiqPay answers HTTP 200 even for rejects — `result`/`status` decide.
		$result = (string) ( $body['result'] ?? '' );
		$status = (string) ( $body['status'] ?? '' );
		$ok     = $code >= 200 && $code < 300 && 'error' !== $result && ! in_array( $status, array( 'error', 'failure' ), true );

		if ( ! $ok ) {
			$err = array();
			if ( ! empty( $body['err_description'] ) ) {
				$err[] = (string) $body['err_description'];
			}
			if ( ! empty( $body['err_code'] ) ) {
				$err[] = (string) $body['err_code'];
			}
			if ( ! $err ) {
				$err[] = 'HTTP ' . $code;
			}
			return array(
				'success' => false,
				'status'  => $code,
				'data'    => $body,
				'errors'  => $err,
			);
		}

		return array(
			'success' => true,
			'status'  => $code,
			'data'    => $body,
			'errors'  => array(),
		);
	}

	/**
	 * Payment states that mean the money is secured and the order may proceed.
	 * `sandbox` appears only for test keys; `wait_accept` means LiqPay took the
	 * money but has not settled it to the merchant yet.
	 */
	public static function is_paid_status( string $status ): bool {
		return in_array( $status, array( 'success', 'sandbox', 'wait_accept' ), true );
	}

	/** States where LiqPay is still waiting on the buyer — do not fail these. */
	public static function is_pending_status( string $status ): bool {
		return in_array(
			$status,
			array( 'processing', 'prepared', 'wait_secure', 'wait_accept', 'wait_card', 'wait_qr', 'wait_sender', 'cash_wait', 'hold_wait', '3ds_verify', 'otp_verify', 'receiver_verify', 'sender_verify', 'password_verify', 'phone_verify', 'pin_verify', 'ivr_verify', 'captcha_verify', 'invoice_wait', 'subscribed', 'unsubscribed' ),
			true
		);
	}

	/** Fixed 2-decimal string so the signed value equals the sent value. */
	public static function money( float $v ): string {
		return number_format( $v, 2, '.', '' );
	}
}
