<?php
/**
 * Notification event log storage helpers.
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify\Core;

defined( 'ABSPATH' ) || exit;

class Logger {

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'catcode_tgnotify_log';
	}

	/**
	 * @param int $product_id Non-zero only for low-stock rows — that is what the
	 *                        24h throttle in Notifier reads back.
	 */
	public static function log( int $order_id, string $event, string $chat_id, ?int $http, string $message, bool $success, int $product_id = 0 ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$wpdb->insert(
			self::table(),
			array(
				'order_id'    => $order_id,
				'product_id'  => $product_id,
				'event'       => $event,
				'chat_id'     => function_exists( 'mb_substr' ) ? mb_substr( $chat_id, 0, 64 ) : substr( $chat_id, 0, 64 ),
				'http_status' => $http,
				'message'     => function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 2000 ) : substr( $message, 0, 2000 ),
				'success'     => $success ? 1 : 0,
				'created_at'  => current_time( 'mysql' ),
			)
		);
	}

	public static function latest( int $limit = 100 ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY id DESC LIMIT %d',
				self::table(),
				max( 1, $limit )
			),
			ARRAY_A
		);
		return $rows ?: array();
	}

	/**
	 * Has this product already produced a low-stock message recently? WooCommerce
	 * fires `woocommerce_low_stock` on every order that keeps the product at or
	 * below the threshold, so without this one slow-selling item would spam the
	 * chat on each purchase.
	 */
	public static function low_stock_recently( int $product_id, int $hours = 24 ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE product_id = %d AND event = %s AND success = 1 AND created_at > %s LIMIT 1',
				self::table(),
				$product_id,
				'low_stock',
				gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - max( 1, $hours ) * HOUR_IN_SECONDS ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- log rows are stored in site time.
			)
		);

		return null !== $found;
	}

	/**
	 * Trim the log so it never grows unbounded on busy shops.
	 */
	public static function prune( int $keep = 500 ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$cutoff = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i ORDER BY id DESC LIMIT 1 OFFSET %d', self::table(), max( 1, $keep ) )
		);
		if ( $cutoff > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id <= %d', self::table(), $cutoff ) );
		}
	}
}
