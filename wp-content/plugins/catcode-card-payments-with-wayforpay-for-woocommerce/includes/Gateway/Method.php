<?php
/**
 * WooCommerce payment gateway — WayForPay.
 *
 * @package CcWayForPayWC
 */

namespace CatCode\WayForPayWC\Gateway;

use CatCode\WayForPayWC\Api\Client;

defined( 'ABSPATH' ) || exit;

class Method extends \WC_Payment_Gateway {

	const ID = 'wayforpay';

	/** Public test merchant — works without a contract. */
	const TEST_ACCOUNT = 'test_merch_n1';
	const TEST_SECRET  = 'flk3409refn54t54t*FNJRET';

	/** Currencies WayForPay settles in. */
	const CCY = array( 'UAH', 'USD', 'EUR' );

	public function __construct() {
		$this->id                 = self::ID;
		$this->method_title       = __( 'WayForPay', 'catcode-card-payments-with-wayforpay-for-woocommerce' );
		$this->method_description = __( 'Accept card payments via WayForPay. The customer is redirected to the secure payment page and the order is confirmed automatically through the serviceUrl webhook.', 'catcode-card-payments-with-wayforpay-for-woocommerce' );
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
		$webhook = rest_url( 'catcode-wayforpay/v1/webhook' );

		$this->form_fields = array(
			'enabled'          => array(
				'title'   => __( 'Enable', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable WayForPay card payments', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'default' => 'no',
			),
			'title'            => array(
				'title'       => __( 'Title', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The payment method name the customer sees at checkout.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'default'     => __( 'Card payment (WayForPay)', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'      => array(
				'title'   => __( 'Description', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Visa/Mastercard, Apple Pay and Google Pay via the secure WayForPay page.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
			),
			'testmode'         => array(
				'title'       => __( 'Test mode', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Use the public test merchant', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'description' => __( 'Payments go through the public WayForPay test merchant (test_merch_n1). No real money is charged.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'default'     => 'yes',
			),
			'merchant_account' => array(
				'title'       => __( 'Merchant login', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Merchant identifier from your WayForPay account (for live payments).', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'default'     => '',
			),
			'merchant_secret'  => array(
				'title'       => __( 'Merchant secret key', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Merchant secret key from your WayForPay account. Used for signing only — it is never exposed to the customer.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'default'     => '',
			),
			'complete_status'  => array(
				'title'   => __( 'Status after payment', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'processing' => __( 'Processing', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
					'completed'  => __( 'Completed', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				),
				'default' => 'processing',
			),
			'debug'            => array(
				'title'   => __( 'Logging', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Write requests and responses to the WooCommerce log', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'default' => 'no',
			),
			'webhook_info'     => array(
				'title'       => __( 'Webhook URL (serviceUrl)', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: webhook URL */
					__( 'Sent automatically with every payment:<br><code>%s</code>', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
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
		return $this->is_test() ? self::TEST_ACCOUNT : trim( (string) $this->get_option( 'merchant_account' ) );
	}

	public function get_secret(): string {
		return $this->is_test() ? self::TEST_SECRET : trim( (string) $this->get_option( 'merchant_secret' ) );
	}

	public function client(): Client {
		return new Client( $this->get_account(), $this->get_secret() );
	}

	public function log( string $message ) {
		if ( 'yes' !== $this->get_option( 'debug' ) ) {
			return;
		}
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'wayforpay' ) );
		}
	}

	/**
	 * WooCommerce redirect target — the order-pay (receipt) page where the
	 * auto-submitting WayForPay form is rendered.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}
		$order->update_status( 'pending', __( 'Awaiting WayForPay payment.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) );
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/** Build the item list sent to WayForPay (name / count / price). */
	private function build_products( \WC_Order $order ): array {
		$products = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$qty        = max( 1, (int) $item->get_quantity() );
			$total      = (float) $item->get_total() + (float) $item->get_total_tax();
			$products[] = array(
				'name'  => mb_substr( $item->get_name(), 0, 255 ),
				'count' => $qty,
				'price' => round( $total / $qty, 2 ),
			);
		}
		// Shipping as a separate line so the sum of price*count equals amount.
		$ship = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
		if ( $ship > 0 ) {
			$products[] = array(
				'name'  => __( 'Shipping', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'count' => 1,
				'price' => round( $ship, 2 ),
			);
		}
		// Fees / discounts remainder — reconcile any rounding gap into one line.
		$sum = 0.0;
		foreach ( $products as $p ) {
			$sum += $p['price'] * $p['count'];
		}
		$diff = round( (float) $order->get_total() - $sum, 2 );
		if ( abs( $diff ) >= 0.01 ) {
			$products[] = array(
				'name'  => $diff > 0 ? __( 'Other', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) : __( 'Discount', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
				'count' => 1,
				'price' => $diff,
			);
		}
		if ( ! $products ) {
			$products[] = array(
				'name'  => sprintf(
					/* translators: %s: order number */
					__( 'Order #%s', 'catcode-card-payments-with-wayforpay-for-woocommerce' ),
					$order->get_order_number()
				),
				'count' => 1,
				'price' => round( (float) $order->get_total(), 2 ),
			);
		}
		return $products;
	}

	/** Generate (and persist) a unique WayForPay orderReference for this attempt. */
	private function make_reference( \WC_Order $order ): string {
		// Unique per attempt: WayForPay rejects a reused orderReference with
		// "(1112) Duplicate Order ID", so bump a counter on every retry.
		$attempt = (int) $order->get_meta( '_catcode_wayforpay_attempt' ) + 1;
		$order->update_meta_data( '_catcode_wayforpay_attempt', $attempt );
		$ref = $order->get_id() . '-' . $attempt . '-' . substr( $order->get_order_key(), -6 );
		$order->update_meta_data( '_catcode_wayforpay_order_ref', $ref );
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
		$lang      = strtoupper( substr( get_locale(), 0, 2 ) );
		$fields    = $this->client()->purchase_fields(
			array(
				'reference'  => $reference,
				'amount'     => (float) $order->get_total(),
				'currency'   => $order->get_currency(),
				'orderDate'  => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time(),
				'domain'     => wp_parse_url( home_url(), PHP_URL_HOST ),
				// Query form, not /wc-api/<id>/: language plugins with a prefix for every
				// language (Polylang) drop the root wc-api rewrite rule and the pretty
				// URL lands on a 404 after payment.
				'returnUrl'  => add_query_arg(
					array(
						'wc-api'                  => self::ID,
						'catcode_wayforpay_order' => $order->get_id(),
					),
					home_url( '/' )
				),
				'serviceUrl' => rest_url( 'catcode-wayforpay/v1/webhook' ),
				'products'   => $this->build_products( $order ),
				'language'   => in_array( $lang, array( 'UA', 'EN' ), true ) ? $lang : 'UA',
				'client'     => array(
					'firstName' => $order->get_billing_first_name(),
					'lastName'  => $order->get_billing_last_name(),
					'email'     => $order->get_billing_email(),
					'phone'     => preg_replace( '/\D/', '', (string) $order->get_billing_phone() ),
					'country'   => $order->get_billing_country() ? $this->alpha3( $order->get_billing_country() ) : '',
				),
			)
		);

		$this->log( 'purchase form order #' . $order->get_id() . ': ' . wp_json_encode( $fields ) );

		echo '<p>' . esc_html__( 'Redirecting you to the secure WayForPay payment page…', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) . '</p>';
		echo '<form id="catcode-wayforpay-form" method="post" action="' . esc_url( Client::PAY_URL ) . '" accept-charset="utf-8">';
		foreach ( $fields as $name => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $v ) {
					echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $v ) . '"/>';
				}
			} else {
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"/>';
			}
		}
		echo '<button type="submit" class="button">' . esc_html__( 'Proceed to payment', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) . '</button>';
		echo '</form>';

		wp_enqueue_script(
			'catcode-wayforpay-redirect',
			CATCODE_WAYFORPAY_URL . 'assets/js/redirect.js',
			array(),
			CATCODE_WAYFORPAY_VERSION,
			true
		);
	}

	/** Map an ISO-3166 alpha-2 country to alpha-3 (WayForPay clientCountry). */
	private function alpha3( string $a2 ): string {
		$map = array(
			'UA' => 'UKR',
			'PL' => 'POL',
			'DE' => 'DEU',
			'US' => 'USA',
			'GB' => 'GBR',
		);
		return $map[ strtoupper( $a2 ) ] ?? '';
	}

	/**
	 * Buyer returns from WayForPay (returnUrl). Reconcile via CHECK_STATUS,
	 * then route to the thank-you page only when actually paid.
	 */
	public function handle_return() {
		// The return URL is opened by the payment provider, so no nonce can be
		// carried here; the order state is taken from the signed WayForPay API
		// response, never from the request itself.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['catcode_wayforpay_order'] ) ? absint( wp_unslash( $_GET['catcode_wayforpay_order'] ) ) : 0;
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
			wc_add_notice( __( 'The payment was not completed. Please try again or choose another payment method.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ), 'error' );
		}

		wp_safe_redirect( $order ? $order->get_checkout_payment_url() : wc_get_checkout_url() );
		exit;
	}

	/** Pull the current transaction status from WayForPay and reconcile. */
	public function sync_order( \WC_Order $order ): bool {
		$reference = (string) $order->get_meta( '_catcode_wayforpay_order_ref' );
		if ( '' === $reference ) {
			return false;
		}
		$resp = $this->client()->check_status( $reference );
		$this->log( 'check_status #' . $order->get_id() . ': ' . wp_json_encode( $resp ) );
		if ( empty( $resp['success'] ) || empty( $resp['data']['transactionStatus'] ) ) {
			return false;
		}
		return $this->apply_status( $order, (array) $resp['data'] );
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
	 * Apply a WayForPay payload to the order under a cross-request lock.
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
		$lock = '_wfp_apply_' . $order->get_id();

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
	 * Map a WayForPay transaction payload onto the WooCommerce order. Idempotent.
	 *
	 * @return bool True if the order state advanced.
	 */
	private function apply_status_locked( \WC_Order $order, array $data ): bool {
		$status = (string) ( $data['transactionStatus'] ?? '' );
		$order->update_meta_data( '_catcode_wayforpay_status', $status );
		foreach ( array( 'authCode', 'cardPan', 'cardType', 'paymentSystem' ) as $k ) {
			if ( ! empty( $data[ $k ] ) ) {
				$order->update_meta_data( '_catcode_wayforpay_' . $k, (string) $data[ $k ] );
			}
		}
		if ( isset( $data['amount'] ) ) {
			$order->update_meta_data( '_catcode_wayforpay_amount', (string) $data['amount'] );
		}

		switch ( $status ) {
			case 'Approved':
				if ( $order->is_paid() ) {
					$order->save();
					return false;
				}
				// Guard against tampering: paid amount must cover the order total,
				// and the currency must match — a cheaper currency is not payment.
				$paid = (float) ( $data['amount'] ?? 0 );
				$due  = round( (float) $order->get_total(), 2 );
				$ccy  = (string) ( $data['currency'] ?? '' );
				if ( ( $paid > 0 && $paid + 0.01 < $due ) || ( '' !== $ccy && $ccy !== $order->get_currency() ) ) {
					$order->update_status( 'on-hold', __( 'WayForPay: the paid amount or currency does not match the order — manual review required.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) );
					$order->save();
					return false;
				}
				$order->payment_complete( (string) ( $data['orderReference'] ?? '' ) );
				if ( 'completed' === $this->get_option( 'complete_status', 'processing' ) && ! $order->has_status( 'completed' ) ) {
					$order->update_status( 'completed' );
				}
				$order->add_order_note( __( 'WayForPay: payment received.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) );
				$order->save();
				return true;

			case 'WaitingAuthComplete':
				if ( ! $order->has_status( 'on-hold' ) ) {
					$order->update_status( 'on-hold', __( 'WayForPay: funds are held, awaiting confirmation.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) );
				}
				$order->save();
				return true;

			case 'Pending':
			case 'InProcessing':
			case 'Created':
				$order->save();
				return false;

			case 'Declined':
				if ( ! $order->is_paid() ) {
					$reason = (string) ( $data['reason'] ?? '' );
					$order->update_status( 'failed', __( 'WayForPay: payment declined. ', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) . $reason );
				}
				$order->save();
				return true;

			case 'Expired':
				if ( ! $order->is_paid() && ! $order->has_status( array( 'cancelled', 'completed', 'processing' ) ) ) {
					$order->update_status( 'cancelled', __( 'WayForPay: the payment has expired.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) );
				}
				$order->save();
				return true;

			case 'Refunded':
			case 'Voided':
				if ( ! $order->has_status( 'refunded' ) ) {
					$order->update_status( 'refunded', __( 'WayForPay: payment refunded.', 'catcode-card-payments-with-wayforpay-for-woocommerce' ) );
				}
				$order->save();
				return true;
		}

		$order->save();
		return false;
	}
}
