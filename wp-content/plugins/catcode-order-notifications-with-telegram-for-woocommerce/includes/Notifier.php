<?php
/**
 * Event listener + dispatcher: decides what to announce, renders it and pushes
 * it to every configured chat.
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify;

use CatCode\TelegramNotify\Api\Client;
use CatCode\TelegramNotify\Core\Logger;
use CatCode\TelegramNotify\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Notifier {

	public const META_NEW_SENT = '_catcode_tgnotify_new_sent';

	/** One delayed retry is enough for the usual cause: a blip at api.telegram.org. */
	private const RETRY_DELAY = 300;

	public function register_hooks(): void {
		// Classic checkout.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_checkout' ), 20, 1 );
		// Blocks (Store API) checkout.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_checkout' ), 20, 1 );
		// Covers manual orders and payment-gateway callbacks.
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 20, 4 );
		// Stock warnings.
		add_action( 'woocommerce_low_stock', array( $this, 'on_low_stock' ), 20, 1 );
		// Retry.
		add_action( 'catcode_tgnotify_retry_send', array( $this, 'on_retry' ), 10, 3 );
	}

	/**
	 * @param int|\WC_Order $order Order id (classic hook) or WC_Order (Store API hook).
	 */
	public function on_checkout( $order ): void {
		$order = $order instanceof \WC_Order ? $order : wc_get_order( (int) $order );
		if ( ! $order ) {
			return;
		}
		$this->maybe_new_order( $order );
	}

	/**
	 * @param int       $order_id Order id.
	 * @param string    $from     Previous status.
	 * @param string    $to       New status.
	 * @param \WC_Order $order    Order.
	 */
	public function on_status_changed( $order_id, $from, $to, $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( (int) $order_id );
		}
		if ( ! $order ) {
			return;
		}

		// A brand-new order landing in a trigger status announces itself as "new"
		// rather than as a status change — otherwise checkout produces two messages.
		if ( $this->maybe_new_order( $order ) ) {
			return;
		}

		if ( 'failed' === (string) $to && 'yes' === Settings::get( 'notify_failed', 'no' ) ) {
			$this->dispatch_order( $order, 'failed', array( 'order_status_old' => wc_get_order_status_name( (string) $from ) ) );
			return;
		}

		if ( 'yes' !== Settings::get( 'notify_status', 'no' ) ) {
			return;
		}
		if ( ! in_array( (string) $to, self::status_list( 'status_statuses' ), true ) ) {
			return;
		}

		$this->dispatch_order( $order, 'status', array( 'order_status_old' => wc_get_order_status_name( (string) $from ) ) );
	}

	/**
	 * @param \WC_Product $product Product that dropped to its low-stock amount.
	 */
	public function on_low_stock( $product ): void {
		if ( 'yes' !== Settings::get( 'notify_low_stock', 'no' ) || ! Settings::is_configured() ) {
			return;
		}
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		// One warning per product per day: WooCommerce re-fires this hook on every
		// order while the stock stays under the threshold.
		$product_id = $product->get_id();
		if ( Logger::low_stock_recently( $product_id ) ) {
			return;
		}

		$text = Formatter::product( $product, (string) Settings::get( 'template_low_stock', '' ) );
		if ( '' === trim( $text ) ) {
			return;
		}
		$this->broadcast( 0, 'low_stock', $text, false, $product_id );
	}

	/**
	 * Delayed second attempt. Re-renders from the live order so the message
	 * reflects reality at send time rather than at failure time.
	 *
	 * @param int    $order_id Order id.
	 * @param string $event    Event key.
	 * @param string $chat_id  Target chat.
	 */
	public function on_retry( $order_id, $event, $chat_id ): void {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order || ! Settings::is_configured() ) {
			return;
		}

		$template = $this->template_for( (string) $event );
		$text     = Formatter::order( $order, $template );

		foreach ( Settings::chats() as $chat ) {
			if ( (string) $chat['chat_id'] !== (string) $chat_id ) {
				continue;
			}
			$this->send( (int) $order_id, (string) $event . '_retry', $chat, $text, false );
		}
	}

	/**
	 * Announce a new order once, when it first reaches a configured status.
	 *
	 * @return bool Whether the new-order message was sent by this call.
	 */
	private function maybe_new_order( \WC_Order $order ): bool {
		if ( 'yes' !== Settings::get( 'notify_new_order', 'no' ) || ! Settings::is_configured() ) {
			return false;
		}
		if ( '' !== (string) $order->get_meta( self::META_NEW_SENT ) ) {
			return false;
		}
		if ( ! in_array( $order->get_status(), self::status_list( 'new_order_statuses' ), true ) ) {
			return false;
		}

		// Guard against a double fire (checkout hook + status hook in one request).
		$lock = 'catcode_tgnotify_lock_' . $order->get_id();
		if ( get_transient( $lock ) ) {
			return false;
		}
		set_transient( $lock, 1, 30 );

		$order->update_meta_data( self::META_NEW_SENT, current_time( 'mysql' ) );
		$order->save();

		$this->dispatch_order( $order, 'new_order' );
		return true;
	}

	/**
	 * Render once, then fan out to every chat.
	 *
	 * @param array<string,string> $extra Extra placeholder values.
	 */
	private function dispatch_order( \WC_Order $order, string $event, array $extra = array() ): void {
		if ( ! Settings::is_configured() ) {
			return;
		}

		$text = Formatter::order( $order, $this->template_for( $event ), $extra );
		if ( '' === trim( $text ) ) {
			return;
		}

		$this->broadcast( $order->get_id(), $event, $text, true );
	}

	/**
	 * @param bool $retry Whether a failure should schedule a delayed retry.
	 */
	private function broadcast( int $order_id, string $event, string $text, bool $retry, int $product_id = 0 ): void {
		$silent = 'yes' === Settings::get( 'silent', 'no' );

		foreach ( Settings::chats() as $chat ) {
			$this->send( $order_id, $event, $chat, $text, $retry, $silent, $product_id );
		}

		Logger::prune();
	}

	/**
	 * @param array{chat_id:string,thread_id:int} $chat Target.
	 */
	private function send( int $order_id, string $event, array $chat, string $text, bool $retry, bool $silent = false, int $product_id = 0 ): bool {
		$client = new Client();
		$res    = $client->send_message( (string) $chat['chat_id'], $text, (int) $chat['thread_id'], $silent );

		Logger::log(
			$order_id,
			$event,
			(string) $chat['chat_id'],
			$res['status'],
			$res['ok'] ? 'OK' : $res['error'],
			$res['ok'],
			$product_id
		);

		if ( $res['ok'] ) {
			/** Fires after a message actually reached Telegram. */
			do_action( 'catcode_tgnotify_sent', $event, (string) $chat['chat_id'] );
		}

		if ( ! $res['ok'] && $retry && $order_id > 0 ) {
			wp_schedule_single_event(
				time() + self::RETRY_DELAY,
				'catcode_tgnotify_retry_send',
				array( $order_id, $event, (string) $chat['chat_id'] )
			);
		}

		return (bool) $res['ok'];
	}

	private function template_for( string $event ): string {
		switch ( $event ) {
			case 'new_order':
				return (string) Settings::get( 'template_new_order', '' );
			case 'low_stock':
				return (string) Settings::get( 'template_low_stock', '' );
			case 'status':
			case 'failed':
			default:
				return (string) Settings::get( 'template_status', '' );
		}
	}

	/**
	 * @return string[] WC status slugs without the "wc-" prefix.
	 */
	private static function status_list( string $key ): array {
		$statuses = Settings::get( $key, array() );
		if ( ! is_array( $statuses ) ) {
			$statuses = array();
		}
		return array_values( array_filter( array_map( 'strval', $statuses ) ) );
	}
}
