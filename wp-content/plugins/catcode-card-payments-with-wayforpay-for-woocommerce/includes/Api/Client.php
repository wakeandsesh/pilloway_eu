<?php
/**
 * WayForPay API client + signature helpers.
 *
 * Docs: https://wiki.wayforpay.com/
 * All signatures are HMAC-MD5 over semicolon-joined field values, keyed with
 * the merchant secret key.
 *
 * @package CcWayForPayWC
 */

namespace CatCode\WayForPayWC\Api;

defined( 'ABSPATH' ) || exit;

class Client {

	/** Hosted payment page — purchase form auto-POSTs here. */
	const PAY_URL = 'https://secure.wayforpay.com/pay';
	/** JSON API — transaction status lookups. */
	const API_URL = 'https://api.wayforpay.com/api';

	/** @var string */
	private $account;
	/** @var string */
	private $secret;
	/** @var int */
	private $timeout = 25;

	public function __construct( string $account, string $secret ) {
		$this->account = trim( $account );
		$this->secret  = trim( $secret );
	}

	public function account(): string {
		return $this->account;
	}

	/**
	 * HMAC-MD5 of the given ordered values joined by ";".
	 * Nested arrays (product lists) are flattened in place.
	 */
	public function sign( array $values ): string {
		$flat = array();
		array_walk_recursive(
			$values,
			static function ( $v ) use ( &$flat ) {
				$flat[] = (string) $v;
			}
		);
		return hash_hmac( 'md5', implode( ';', $flat ), $this->secret );
	}

	/**
	 * Product names go into the signature, which WayForPay rebuilds from the
	 * POSTed values by joining them with ";" — a literal ";" inside a name
	 * splits the signature string in two, so it must never reach the field.
	 */
	private static function clean_name( string $name ): string {
		$name = html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES, 'UTF-8' );
		$name = str_replace( ';', ',', $name );
		$name = trim( preg_replace( '/\s+/u', ' ', $name ) );

		return '' !== $name ? mb_substr( $name, 0, 255 ) : __( 'Product', 'catcode-card-payments-with-wayforpay-for-woocommerce' );
	}

	/**
	 * Build the full purchase field set (including merchantSignature) for the
	 * auto-submitting form that redirects the buyer to WayForPay.
	 *
	 * @param array $o reference, amount(float), currency, orderDate(int),
	 *                 domain, returnUrl, serviceUrl, products[], client[].
	 */
	public function purchase_fields( array $o ): array {
		$names  = array();
		$counts = array();
		$prices = array();
		foreach ( (array) $o['products'] as $p ) {
			$names[]  = self::clean_name( (string) $p['name'] );
			$counts[] = (string) (int) $p['count'];
			$prices[] = self::money( (float) $p['price'] );
		}

		$amount   = self::money( (float) $o['amount'] );
		$currency = (string) $o['currency'];
		$fields   = array(
			'merchantAccount'               => $this->account,
			'merchantDomainName'            => (string) $o['domain'],
			'merchantTransactionSecureType' => 'AUTO',
			'merchantTransactionType'       => 'SALE',
			'orderReference'                => (string) $o['reference'],
			'orderDate'                     => (string) (int) $o['orderDate'],
			'amount'                        => $amount,
			'currency'                      => $currency,
			'productName'                   => $names,
			'productCount'                  => $counts,
			'productPrice'                  => $prices,
		);

		if ( ! empty( $o['returnUrl'] ) ) {
			$fields['returnUrl'] = (string) $o['returnUrl'];
		}
		if ( ! empty( $o['serviceUrl'] ) ) {
			$fields['serviceUrl'] = (string) $o['serviceUrl'];
		}
		$c = (array) ( $o['client'] ?? array() );
		foreach (
			array(
				'clientFirstName' => 'firstName',
				'clientLastName'  => 'lastName',
				'clientEmail'     => 'email',
				'clientPhone'     => 'phone',
				'clientCountry'   => 'country',
			) as $wfp => $key
		) {
			if ( ! empty( $c[ $key ] ) ) {
				$fields[ $wfp ] = (string) $c[ $key ];
			}
		}
		if ( ! empty( $o['language'] ) ) {
			$fields['language'] = (string) $o['language'];
		}

		// Signature order is fixed by WayForPay and must match exactly.
		$fields['merchantSignature'] = $this->sign(
			array(
				$this->account,
				$fields['merchantDomainName'],
				$fields['orderReference'],
				$fields['orderDate'],
				$amount,
				$currency,
				$names,
				$counts,
				$prices,
			)
		);

		return $fields;
	}

	/**
	 * Verify the signature WayForPay sends back on a callback / status response.
	 * Sign fields: merchantAccount;orderReference;amount;currency;authCode;cardPan;transactionStatus;reasonCode
	 */
	public function verify_response( array $d ): bool {
		$sig = (string) ( $d['merchantSignature'] ?? '' );
		if ( '' === $sig ) {
			return false;
		}
		$calc = $this->sign(
			array(
				(string) ( $d['merchantAccount'] ?? '' ),
				(string) ( $d['orderReference'] ?? '' ),
				(string) ( $d['amount'] ?? '' ),
				(string) ( $d['currency'] ?? '' ),
				(string) ( $d['authCode'] ?? '' ),
				(string) ( $d['cardPan'] ?? '' ),
				(string) ( $d['transactionStatus'] ?? '' ),
				(string) ( $d['reasonCode'] ?? '' ),
			)
		);
		return hash_equals( $calc, $sig );
	}

	/**
	 * Accept-signature the shop must return to WayForPay so it stops retrying.
	 * Sign fields: orderReference;status;time
	 */
	public function accept_response( string $reference, int $time, string $status = 'accept' ): array {
		return array(
			'orderReference' => $reference,
			'status'         => $status,
			'time'           => $time,
			'signature'      => $this->sign( array( $reference, $status, (string) $time ) ),
		);
	}

	/** CHECK_STATUS — pull the current transaction state for an order. */
	public function check_status( string $reference ): array {
		return $this->api(
			array(
				'transactionType'   => 'CHECK_STATUS',
				'merchantAccount'   => $this->account,
				'orderReference'    => $reference,
				'apiVersion'        => 1,
				'merchantSignature' => $this->sign( array( $this->account, $reference ) ),
			)
		);
	}

	/**
	 * POST a JSON payload to the WayForPay API and normalise the response.
	 *
	 * @return array{success:bool,status:int,data:array,errors:string[]}
	 */
	private function api( array $payload ): array {
		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => $this->timeout,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
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
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : array();

		// WayForPay uses reasonCode 1100 = Ok for API calls.
		$reason = isset( $data['reasonCode'] ) ? (int) $data['reasonCode'] : 0;
		$ok     = $code >= 200 && $code < 300 && ( 0 === $reason || 1100 === $reason || 4100 === $reason );

		if ( ! $ok ) {
			$err = array();
			if ( ! empty( $data['reason'] ) ) {
				$err[] = (string) $data['reason'];
			}
			if ( $reason ) {
				$err[] = 'code ' . $reason;
			}
			if ( ! $err ) {
				$err[] = 'HTTP ' . $code;
			}
			return array(
				'success' => false,
				'status'  => $code,
				'data'    => $data,
				'errors'  => $err,
			);
		}

		return array(
			'success' => true,
			'status'  => $code,
			'data'    => $data,
			'errors'  => array(),
		);
	}

	/** Fixed 2-decimal string so the value signed equals the value sent. */
	public static function money( float $v ): string {
		return number_format( $v, 2, '.', '' );
	}
}
