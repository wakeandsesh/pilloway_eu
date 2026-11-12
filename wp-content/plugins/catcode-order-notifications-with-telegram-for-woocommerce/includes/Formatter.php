<?php
/**
 * Turns an order (or a low-stock product) plus an admin-authored template into
 * the final Telegram message.
 *
 * Template markup is trusted (only an admin can edit it) and passed through as
 * HTML; every substituted *value* is escaped, so a customer cannot inject tags
 * and break Telegram's parser — an unbalanced tag makes the API reject the
 * whole message with "can't parse entities".
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify;

defined( 'ABSPATH' ) || exit;

class Formatter {

	/**
	 * Render a template against an order.
	 *
	 * @param \WC_Order $order    Order.
	 * @param string    $template Raw template with {placeholders}.
	 * @param array     $extra    Extra placeholder values (unescaped).
	 */
	public static function order( \WC_Order $order, string $template, array $extra = array() ): string {
		$values = array_merge( self::order_values( $order ), $extra );
		return self::render( $template, $values );
	}

	/**
	 * Render a template against a product (low-stock notice).
	 *
	 * @param \WC_Product $product  Product.
	 * @param string      $template Raw template.
	 */
	public static function product( \WC_Product $product, string $template ): string {
		$stock = $product->get_stock_quantity();

		return self::render(
			$template,
			array(
				'site_name'   => get_bloginfo( 'name' ),
				'product_name' => $product->get_name(),
				'product_sku' => (string) $product->get_sku(),
				'stock_qty'   => null === $stock ? '' : (string) $stock,
				'product_url' => self::link( get_permalink( $product->get_id() ), __( 'Open product', 'catcode-order-notifications-with-telegram-for-woocommerce' ) ),
				'admin_url'   => self::link(
					admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' ),
					__( 'Edit product', 'catcode-order-notifications-with-telegram-for-woocommerce' )
				),
			)
		);
	}

	/**
	 * Render a template against a site-form lead.
	 *
	 * @param array<string,mixed> $lead     Normalised lead (see Forms::normalise()).
	 * @param string              $template Raw template.
	 */
	public static function lead( array $lead, string $template ): string {
		$extra = array();
		foreach ( (array) $lead['extra'] as $label => $value ) {
			$extra[] = '<b>' . self::esc( (string) $label ) . ':</b> ' . self::esc( (string) $value );
		}

		return self::render(
			$template,
			array(
				'site_name'   => get_bloginfo( 'name' ),
				'type'        => (string) $lead['type'],
				'form_title'  => (string) $lead['form_title'],
				'name'        => (string) $lead['name'],
				'phone'       => (string) $lead['phone'],
				'email'       => (string) $lead['email'],
				'comment'     => (string) $lead['comment'],
				'product'     => (string) $lead['product'],
				'product_sku' => (string) $lead['product_sku'],
				// Pre-built markup, see $raw_html in render().
				'extra'       => implode( "\n", $extra ),
				'page_url'    => self::link( (string) $lead['page_url'], __( 'Lead page', 'catcode-order-notifications-with-telegram-for-woocommerce' ) ),
			)
		);
	}

	/**
	 * All order-derived placeholder values, still unescaped.
	 *
	 * @return array<string,string>
	 */
	private static function order_values( \WC_Order $order ): array {
		$name = trim( $order->get_formatted_billing_full_name() );
		if ( '' === $name ) {
			$name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		}

		$shipping_method = $order->get_shipping_method();

		return array(
			'site_name'        => get_bloginfo( 'name' ),
			'order_number'     => '#' . $order->get_order_number(),
			'order_id'         => (string) $order->get_id(),
			'order_total'      => self::money( $order->get_total(), $order ),
			'order_status'     => wc_get_order_status_name( $order->get_status() ),
			'order_date'       => $order->get_date_created() ? wc_format_datetime( $order->get_date_created(), 'd.m.Y H:i' ) : '',
			'payment_method'   => $order->get_payment_method_title(),
			'shipping_method'  => is_string( $shipping_method ) ? $shipping_method : '',
			'shipping_total'   => self::money( $order->get_shipping_total(), $order ),
			'customer_name'    => $name,
			'customer_phone'   => (string) $order->get_billing_phone(),
			'customer_email'   => (string) $order->get_billing_email(),
			'customer_note'    => (string) $order->get_customer_note(),
			'billing_address'  => self::flatten( $order->get_formatted_billing_address() ),
			'shipping_address' => self::flatten( $order->get_formatted_shipping_address() ),
			'items'            => self::items( $order ),
			'items_count'      => (string) $order->get_item_count(),
			'admin_url'        => self::link( $order->get_edit_order_url(), __( 'Open order', 'catcode-order-notifications-with-telegram-for-woocommerce' ) ),
		);
	}

	/**
	 * One line per order item: "• Name × 2 — 500,00 ₴".
	 */
	private static function items( \WC_Order $order ): string {
		$lines = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			$sku     = $product ? (string) $product->get_sku() : '';

			$line = '• ' . self::esc( $item->get_name() );
			if ( '' !== $sku ) {
				$line .= ' (' . self::esc( $sku ) . ')';
			}
			$line .= ' × ' . (int) $item->get_quantity();
			$line .= ' — ' . self::esc( self::money( $item->get_total(), $order ) );

			$lines[] = $line;
		}
		// Already escaped piecewise — mark so render() does not double-escape.
		return implode( "\n", $lines );
	}

	/**
	 * Substitute placeholders, escaping values, then drop any line whose
	 * placeholders all resolved to empty (e.g. a missing customer note).
	 *
	 * @param array<string,string> $values Raw values.
	 */
	private static function render( string $template, array $values ): string {
		// `items` and `*_url` are pre-built markup, everything else is escaped.
		$raw_html = array( 'items', 'admin_url', 'product_url', 'page_url', 'extra' );

		$out = array();
		foreach ( explode( "\n", $template ) as $line ) {
			if ( ! preg_match_all( '/\{([a-z0-9_]+)\}/', $line, $m ) ) {
				$out[] = $line;
				continue;
			}

			$known = 0;
			$empty = 0;
			foreach ( $m[1] as $key ) {
				if ( ! array_key_exists( $key, $values ) ) {
					continue;
				}
				++$known;
				$value = (string) $values[ $key ];
				if ( '' === trim( $value ) ) {
					++$empty;
				}
				$line = str_replace(
					'{' . $key . '}',
					in_array( $key, $raw_html, true ) ? $value : self::esc( $value ),
					$line
				);
			}

			// Line existed only to carry now-empty data — skip it.
			if ( $known > 0 && $known === $empty ) {
				continue;
			}
			$out[] = $line;
		}

		$text = implode( "\n", $out );
		// Unknown placeholders stay visible on purpose: a typo in the template
		// should be noticeable, not silently swallowed.
		return trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	private static function money( $amount, \WC_Order $order ): string {
		return html_entity_decode(
			wp_strip_all_tags( wc_price( (float) $amount, array( 'currency' => $order->get_currency() ) ) ),
			ENT_QUOTES,
			'UTF-8'
		);
	}

	private static function flatten( string $address ): string {
		return trim( preg_replace( '/\s*(<br\s*\/?>|\n)\s*/i', ', ', $address ), ', ' );
	}

	private static function link( string $url, string $label ): string {
		if ( '' === $url ) {
			return '';
		}
		// esc_url() would encode "&" as "&#038;", which Telegram passes through
		// literally — the resulting admin link loses its query args. Sanitize
		// without display-encoding, then escape for Telegram's parser.
		return '<a href="' . self::esc( esc_url_raw( $url ) ) . '">' . self::esc( $label ) . '</a>';
	}

	/**
	 * Telegram's HTML mode needs &, < and > escaped — and nothing else.
	 * esc_html() is wrong here: it also turns quotes into &#039;/&quot;, which
	 * Telegram prints literally.
	 */
	public static function esc( string $value ): string {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $value );
	}
}
