<?php
/**
 * WooCommerce payment gateway — LiqPay.
 *
 * @package CcLiqPayWC
 */

namespace CatCode\LiqPayWC\Gateway;

use CatCode\LiqPayWC\Api\Client;

defined( 'ABSPATH' ) || exit;

class Method extends \WC_Payment_Gateway {

	const ID = 'liqpay';

	/** Currencies LiqPay settles in. */
	const CCY = array( 'UAH', 'USD', 'EUR' );

	public function __construct() {
		$this->id                 = self::ID;
		$this->method_title       = __( 'LiqPay', 'catcode-card-payments-with-liqpay-for-woocommerce' );
		$this->method_description = __( 'Accept card payments via LiqPay. The customer is redirected to the secure payment page and the order is confirmed automatically through the serviceUrl webhook.', 'catcode-card-payments-with-liqpay-for-woocommerce' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_return' ) );
	}

	public function init_form_fields() {
		$webhook = rest_url( 'catcode-liqpay/v1/webhook' );

		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable LiqPay card payments', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'default' => 'no',
			),
			'title'           => array(
				'title'       => __( 'Title', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The payment method name the customer sees at checkout.', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'default'     => __( 'Card payment (LiqPay)', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'   => __( 'Description', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Visa/Mastercard, Apple Pay and Google Pay via the secure LiqPay page.', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
			),
			'testmode'        => array(
				'title'       => __( 'Test mode (sandbox)', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Use the test keys', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'description' => __( 'LiqPay issues a separate key pair prefixed with <code>sandbox_</code> (LiqPay cabinet → Settings → API → Test). No real money is charged.', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'default'     => 'yes',
			),
			'public_key'      => array(
				'title'       => __( 'Public key (public_key)', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Live public key from your LiqPay cabinet.', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'default'     => '',
			),
			'private_key'     => array(
				'title'       => __( 'Private key (private_key)', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Live private key. Used for signing only — it is never exposed to the customer.', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'default'     => '',
			),
			'sandbox_public_key'  => array(
				'title'       => __( 'Test public key', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Starts with <code>sandbox_</code>.', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'default'     => '',
			),
			'sandbox_private_key' => array(
				'title'       => __( 'Test private key', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Starts with <code>sandbox_</code>.', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'default'     => '',
			),
			'complete_status' => array(
				'title'   => __( 'Status after payment', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'processing' => __( 'Processing', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
					'completed'  => __( 'Completed', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				),
				'default' => 'processing',
			),
			'debug'           => array(
				'title'   => __( 'Logging', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Write requests and responses to the WooCommerce log', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'default' => 'no',
			),
			'webhook_info'    => array(
				'title'       => __( 'Webhook URL (serviceUrl)', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: webhook URL */
					__( 'Sent automatically with every payment:<br><code>%s</code>', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
					esc_html( $webhook )
				),
			),
		);
	}

	public function is_available() {
		if ( 'yes' !== $this->get_option( 'enabled' ) ) {
			return false;
		}
		if ( '' === $this->get_account() || '' === $this->get_secret() ) {
			return false;
		}
		if ( ! in_array( get_woocommerce_currency(), self::CCY, true ) ) {
			return false;
		}
		return parent::is_available();
	}

	private function is_test(): bool {
		return 'yes' === $this->get_option( 'testmode' );
	}

	public function get_account(): string {
		return trim( (string) $this->get_option( $this->is_test() ? 'sandbox_public_key' : 'public_key' ) );
	}

	public function get_secret(): string {
		return trim( (string) $this->get_option( $this->is_test() ? 'sandbox_private_key' : 'private_key' ) );
	}

	public function client(): Client {
		return new Client( $this->get_account(), $this->get_secret(), $this->is_test() );
	}

	public function log( string $message ) {
		if ( 'yes' !== $this->get_option( 'debug' ) ) {
			return;
		}
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'liqpay' ) );
		}
	}

	/**
	 * WooCommerce redirect target — the order-pay (receipt) page where the
	 * auto-submitting LiqPay form is rendered.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}
		$order->update_status( 'pending', __( 'Awaiting LiqPay payment.', 'catcode-card-payments-with-liqpay-for-woocommerce' ) );
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/** Generate (and persist) a unique LiqPay orderReference for this attempt. */
	private function make_reference( \WC_Order $order ): string {
		// Unique per attempt: LiqPay rejects a reused orderReference with
		// "(1112) Duplicate Order ID", so bump a counter on every retry.
		$attempt = (int) $order->get_meta( '_catcode_liqpay_attempt' ) + 1;
		$order->update_meta_data( '_catcode_liqpay_attempt', $attempt );
		$ref = $order->get_id() . '-' . $attempt . '-' . substr( $order->get_order_key(), -6 );
		$order->update_meta_data( '_catcode_liqpay_order_ref', $ref );
		$order->save();
		return $ref;
	}

	/**
	 * Render the auto-submitting purchase form on the order-pay page.
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$reference = $this->make_reference( $order );
		$lang      = strtolower( substr( get_locale(), 0, 2 ) );
		$fields    = $this->client()->checkout_fields(
			array(
				'order_id'    => $reference,
				'amount'      => (float) $order->get_total(),
				'currency'    => $order->get_currency(),
				'description' => sprintf(
					/* translators: 1: order number, 2: shop name */
					__( 'Order #%1$s, %2$s', 'catcode-card-payments-with-liqpay-for-woocommerce' ),
					$order->get_order_number(),
					wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
				),
				'action'      => 'pay',
				// Query form, not /wc-api/<id>/: language plugins with a prefix for every
				// language (Polylang) drop the root wc-api rewrite rule and the pretty
				// URL lands on a 404.
				'result_url'  => add_query_arg(
					array(
						'wc-api'               => self::ID,
						'catcode_liqpay_order' => $order->get_id(),
					),
					home_url( '/' )
				),
				'server_url'  => rest_url( 'catcode-liqpay/v1/webhook' ),
				'language'    => in_array( $lang, array( 'uk', 'en' ), true ) ? $lang : 'uk',
			)
		);

		$this->log( 'checkout form order #' . $order->get_id() . ': ' . wp_json_encode( $fields ) );

		echo '<p>' . esc_html__( 'Redirecting you to the secure LiqPay payment page…', 'catcode-card-payments-with-liqpay-for-woocommerce' ) . '</p>';
		echo '<form id="catcode-liqpay-form" method="post" action="' . esc_url( $fields['url'] ) . '" accept-charset="utf-8">';
		echo '<input type="hidden" name="data" value="' . esc_attr( $fields['data'] ) . '"/>';
		echo '<input type="hidden" name="signature" value="' . esc_attr( $fields['signature'] ) . '"/>';
		echo '<button type="submit" class="button">' . esc_html__( 'Proceed to payment', 'catcode-card-payments-with-liqpay-for-woocommerce' ) . '</button>';
		echo '</form>';

		wp_enqueue_script(
			'catcode-liqpay-redirect',
			CATCODE_LIQPAY_URL . 'assets/js/redirect.js',
			array(),
			CATCODE_LIQPAY_VERSION,
			true
		);
	}

	/**
	 * Buyer returns from LiqPay (result_url). Reconcile via the status API,
	 * then route to the thank-you page only when actually paid.
	 */
	public function handle_return() {
		$order_id = isset( $_GET['catcode_liqpay_order'] ) ? absint( wp_unslash( $_GET['catcode_liqpay_order'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		if ( $order ) {
			$this->sync_order( $order );

			// The callback may have written the payment while the buyer was
			// coming back: apply_status() works on its own fresh copy under the
			// lock, so this object still holds the pre-payment state and would
			// tell a paying customer that the payment failed.
			$order = self::reload_order( $order_id, $order );

			if ( $order->is_paid() || $order->has_status( 'on-hold' ) ) {
				wp_safe_redirect( $this->get_return_url( $order ) );
				exit;
			}
			wc_add_notice( __( 'The payment was not completed. Please try again or choose another payment method.', 'catcode-card-payments-with-liqpay-for-woocommerce' ), 'error' );
		}

		wp_safe_redirect( $order ? $order->get_checkout_payment_url() : wc_get_checkout_url() );
		exit;
	}

	/** Pull the current transaction status from LiqPay and reconcile. */
	public function sync_order( \WC_Order $order ): bool {
		$reference = (string) $order->get_meta( '_catcode_liqpay_order_ref' );
		if ( '' === $reference ) {
			return false;
		}
		$resp = $this->client()->status( $reference );
		$this->log( 'status #' . $order->get_id() . ': ' . wp_json_encode( $resp ) );
		// A declined payment answers with result=error, yet still carries the
		// status we need — so the payload is applied either way when present.
		$data = isset( $resp['data'] ) && is_array( $resp['data'] ) ? $resp['data'] : array();
		if ( empty( $data['status'] ) ) {
			return false;
		}
		return $this->apply_status( $order, $data );
	}

	/** Seconds a held lock stays valid before the next caller takes it over. */
	private const LOCK_TTL = 30;

	/**
	 * Re-read an order, bypassing the per-request object cache.
	 *
	 * wc_get_order() answers from WooCommerce's in-request cache, so a request
	 * that already touched the order keeps seeing its pre-callback state even
	 * after another process wrote the payment. Without dropping the cache the
	 * lock is pointless: both requests still decide on stale data and the order
	 * ends up with two "payment received" notes.
	 *
	 * With HPOS — the default for new stores — orders are NOT served from the
	 * wp_cache 'orders' group at all: WooCommerce keeps its own OrderCache
	 * object, and clearing only the classic caches leaves the stale order in
	 * place: concurrent callbacks serialise on the lock and still each re-read
	 * their own pre-payment copy.
	 *
	 * @param int       $order_id Order to re-read.
	 * @param \WC_Order $fallback Returned when the re-read fails.
	 */
	private static function reload_order( int $order_id, \WC_Order $fallback ): \WC_Order {
		wp_cache_delete( $order_id, 'orders' );
		wp_cache_delete( $order_id, 'order-items' );
		clean_post_cache( $order_id );

		if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Caches\OrderCache' ) ) {
			try {
				wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class )->remove( $order_id );
			} catch ( \Throwable $e ) {
				// Older WooCommerce without the order cache in its container —
				// the classic invalidation above is all there is, and on those
				// versions it is enough.
				unset( $e );
			}
		}

		$fresh = wc_get_order( $order_id );

		return $fresh instanceof \WC_Order ? $fresh : $fallback;
	}

	/**
	 * Apply a LiqPay payload to the order under a cross-request lock.
	 *
	 * The server callback and the buyer's return land at the same millisecond:
	 * both read "no status yet", both call payment_complete(), and the order
	 * ends up with two "payment received" notes (and two stock reductions).
	 * The loser waits for the winner, then re-reads the order so it decides on
	 * the state the winner already wrote.
	 *
	 * @return bool True if the order state advanced.
	 */
	public function apply_status( \WC_Order $order, array $data ): bool {
		$lock = '_catcode_liqpay_apply_' . $order->get_id();

		for ( $i = 0; $i < 40; $i++ ) {
			if ( $this->lock_acquire( $lock ) ) {
				break;
			}
			usleep( 100000 );
		}

		// The winner may have written while we waited — decide on fresh state.
		$order = self::reload_order( $order->get_id(), $order );

		try {
			return $this->apply_status_locked( $order, $data );
		} finally {
			$this->lock_release( $lock );
		}
	}

	/**
	 * Take the lock, or fail if somebody else holds it.
	 *
	 * A raw INSERT is the atomic part: the unique index on option_name makes
	 * exactly one caller win. add_option() cannot be used here — WordPress runs
	 * it as INSERT ... ON DUPLICATE KEY UPDATE, so it reports success even when
	 * the row already exists and both requests think they hold the lock.
	 */
	private function lock_acquire( string $name ): bool {
		global $wpdb;

		// A request that died mid-flight must not block the gateway forever.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $name, time() - self::LOCK_TTL ) ); // phpcs:ignore WordPress.DB

		$rows = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) time() ) ); // phpcs:ignore WordPress.DB

		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		return (int) $rows > 0;
	}

	private function lock_release( string $name ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Map a LiqPay transaction payload onto the WooCommerce order. Idempotent.
	 *
	 * @return bool True if the order state advanced.
	 */
	private function apply_status_locked( \WC_Order $order, array $data ): bool {
		$status = (string) ( $data['status'] ?? '' );
		$order->update_meta_data( '_catcode_liqpay_status', $status );
		foreach ( array( 'payment_id', 'transaction_id', 'sender_card_mask2', 'sender_card_type', 'paytype', 'liqpay_order_id' ) as $k ) {
			if ( ! empty( $data[ $k ] ) ) {
				$order->update_meta_data( '_catcode_liqpay_' . $k, (string) $data[ $k ] );
			}
		}
		if ( isset( $data['amount'] ) ) {
			$order->update_meta_data( '_catcode_liqpay_amount', (string) $data['amount'] );
		}

		// Money secured — success, or `sandbox` when test keys are in play.
		if ( Client::is_paid_status( $status ) && 'wait_accept' !== $status ) {
			if ( $order->is_paid() ) {
				$order->save();
				return false;
			}
			// Guard against tampering: the paid amount must cover the order,
			// and the currency must match — a cheaper currency is not payment.
			$paid = (float) ( $data['amount'] ?? 0 );
			$due  = round( (float) $order->get_total(), 2 );
			$ccy  = (string) ( $data['currency'] ?? '' );
			if ( ( $paid > 0 && $paid + 0.01 < $due ) || ( '' !== $ccy && $ccy !== $order->get_currency() ) ) {
				$order->update_status( 'on-hold', __( 'LiqPay: the paid amount or currency does not match the order — manual review required.', 'catcode-card-payments-with-liqpay-for-woocommerce' ) );
				$order->save();
				return false;
			}
			$order->payment_complete( (string) ( $data['payment_id'] ?? $data['transaction_id'] ?? '' ) );
			if ( 'completed' === $this->get_option( 'complete_status', 'processing' ) && ! $order->has_status( 'completed' ) ) {
				$order->update_status( 'completed' );
			}
			$order->add_order_note(
				'sandbox' === $status
					? __( 'LiqPay: test payment received (sandbox).', 'catcode-card-payments-with-liqpay-for-woocommerce' )
					: __( 'LiqPay: payment received.', 'catcode-card-payments-with-liqpay-for-woocommerce' )
			);
			$order->save();
			return true;
		}

		switch ( $status ) {
			// Money is held on the card (action=hold) or taken but not settled.
			case 'hold_wait':
			case 'wait_accept':
				if ( ! $order->has_status( 'on-hold' ) ) {
					$order->update_status( 'on-hold', __( 'LiqPay: funds are held, awaiting confirmation.', 'catcode-card-payments-with-liqpay-for-woocommerce' ) );
				}
				$order->save();
				return true;

			case 'failure':
			case 'error':
				if ( ! $order->is_paid() ) {
					$reason = (string) ( $data['err_description'] ?? $data['err_code'] ?? '' );
					$order->update_status( 'failed', __( 'LiqPay: payment declined. ', 'catcode-card-payments-with-liqpay-for-woocommerce' ) . $reason );
				}
				$order->save();
				return true;

			case 'reversed':
				if ( ! $order->has_status( 'refunded' ) ) {
					$order->update_status( 'refunded', __( 'LiqPay: payment refunded.', 'catcode-card-payments-with-liqpay-for-woocommerce' ) );
				}
				$order->save();
				return true;
		}

		// Everything else is a "still waiting on the buyer" state (3ds_verify,
		// otp_verify, cash_wait, …) — leave the order pending and do not fail it.
		$order->save();
		return false;
	}

}
