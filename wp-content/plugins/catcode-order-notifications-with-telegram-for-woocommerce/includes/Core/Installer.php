<?php
/**
 * Installer. Creates the log table and default options.
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify\Core;

defined( 'ABSPATH' ) || exit;

class Installer {

	/** Settings whose default value is a translated message template. */
	public const TEMPLATE_KEYS = array(
		'template_new_order',
		'template_status',
		'template_low_stock',
		'template_form_lead',
	);

	public static function activate(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$log = "CREATE TABLE {$prefix}catcode_tgnotify_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			event VARCHAR(32) NOT NULL,
			chat_id VARCHAR(64) NOT NULL DEFAULT '',
			http_status SMALLINT UNSIGNED DEFAULT NULL,
			message TEXT NULL,
			success TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY product_id (product_id),
			KEY event (event)
		) {$charset};";

		dbDelta( $log );

		if ( false === get_option( 'catcode_tgnotify_settings' ) ) {
			// The message templates are deliberately left out of the stored row.
			// Activation can run before the plugin's translations are available,
			// so persisting them here would freeze the English wording into the
			// database of a Ukrainian store. Settings::all() merges them in at
			// runtime instead, by which point the .mo file is loaded.
			$stored = self::default_settings();
			foreach ( self::TEMPLATE_KEYS as $key ) {
				unset( $stored[ $key ] );
			}
			add_option( 'catcode_tgnotify_settings', $stored, '', true );
		}
		update_option( 'catcode_tgnotify_version', CATCODE_TGNOTIFY_VERSION, false );
	}

	public static function deactivate(): void {
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( 'catcode_tgnotify_retry_send' );
		}
	}

	public static function default_settings(): array {
		return array(
			'bot_token'           => '',
			'chat_ids'            => '',
			'notify_new_order'    => 'yes',
			'new_order_statuses'  => array( 'processing', 'on-hold' ),
			'notify_status'       => 'yes',
			'status_statuses'     => array( 'completed', 'cancelled', 'refunded' ),
			'notify_low_stock'    => 'no',
			'notify_failed'       => 'yes',
			'notify_forms'        => 'yes',
			'silent'              => 'no',
			'template_new_order'  => self::default_template( 'new_order' ),
			'template_status'     => self::default_template( 'status' ),
			'template_low_stock'  => self::default_template( 'low_stock' ),
			'template_form_lead'  => self::default_template( 'form_lead' ),
		);
	}

	/**
	 * Message templates use HTML parse mode — only <b>, <i>, <u>, <s>, <code>,
	 * <pre> and <a href> survive Telegram's parser, so keep markup to those.
	 */
	public static function default_template( string $which ): string {
		switch ( $which ) {
			case 'status':
				/* translators: Telegram message template. Keep the {tags} as-is — they are replaced with order data. */
				return __(
					"🔄 <b>Order {order_number}</b>
Status: {order_status_old} → <b>{order_status}</b>
Total: {order_total}
{admin_url}",
					'catcode-order-notifications-with-telegram-for-woocommerce'
				);

			case 'low_stock':
				/* translators: Telegram message template. Keep the {tags} as-is — they are replaced with product data. */
				return __(
					"⚠️ <b>Low stock</b>
{product_name}
SKU: {product_sku}
Remaining: <b>{stock_qty}</b>",
					'catcode-order-notifications-with-telegram-for-woocommerce'
				);

			case 'form_lead':
				/* translators: Telegram message template. Keep the {tags} as-is — they are replaced with form data. */
				return __(
					"📩 <b>{type}</b>
👤 {name}
📞 {phone}
✉️ {email}
📦 {product}
🔖 SKU: {product_sku}
📝 {comment}
{extra}
{page_url}",
					'catcode-order-notifications-with-telegram-for-woocommerce'
				);

			case 'new_order':
			default:
				/* translators: Telegram message template. Keep the {tags} as-is — they are replaced with order data. */
				return __(
					"🛒 <b>New order {order_number}</b>
👤 {customer_name}
📞 {customer_phone}
💳 {payment_method}
🚚 {shipping_method}
📍 {shipping_address}

{items}
💰 Total: <b>{order_total}</b>
📝 {customer_note}
{admin_url}",
					'catcode-order-notifications-with-telegram-for-woocommerce'
				);
		}
	}
}
