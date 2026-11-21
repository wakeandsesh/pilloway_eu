<?php
/**
 * Plugin Name: Pilloway Quick Checkout
 * Description: Collects a callback request as a WooCommerce order from the existing mini-cart.
 * Version: 1.0.0
 * Requires Plugins: woocommerce
 * Text Domain: pilloway-quick-checkout
 */

defined( 'ABSPATH' ) || exit;

add_action( 'before_woocommerce_init', static function () {
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
} );

final class Pilloway_Quick_Checkout {
	private const VIA = 'pilloway-quick';
	private const NONCE = 'pilloway_quick_checkout';

	public static function init(): void {
		add_filter( 'render_block_woocommerce/mini-cart-cart-button-block', '__return_empty_string' );
		add_filter( 'render_block_data', [ self::class, 'checkout_label' ] );
		add_filter( 'render_block_woocommerce/mini-cart-footer-block', [ self::class, 'footer' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'wc_ajax_pilloway_quick_preview', [ self::class, 'preview' ] );
		add_action( 'wc_ajax_pilloway_quick_submit', [ self::class, 'submit' ] );
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ self::class, 'admin' ] );
	}

	private static function language(): string {
		$locale = function_exists( 'pll_current_language' ) ? pll_current_language() : '';
		return $locale ?: substr( determine_locale(), 0, 2 );
	}

	private static function labels(): array {
		static $labels;
		$labels ??= json_decode( file_get_contents( __DIR__ . '/labels.json' ), true, 512, JSON_THROW_ON_ERROR );
		return $labels[ self::language() ] ?? $labels['en'];
	}

	public static function checkout_label( array $block ): array {
		if ( 'woocommerce/mini-cart-checkout-button-block' === $block['blockName'] ) {
			$block['attrs']['checkoutButtonLabel'] = self::labels()['checkout'];
		}
		return $block;
	}

	public static function footer( string $html ): string {
		$labels = self::labels();
		$html = preg_replace( '~<div class="wc-block-components-totals-item__description">[^<]*</div>~', '', $html, 1 ) ?? $html;
		$quick = '<button type="button" class="pilloway-qc-open"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="currentColor"><path d="M13 2 3 14h7l-1 8 12-12h-8l1-8z"/></svg><span>' . esc_html( $labels['open'] ) . '</span></button>';
		$quick .= '<p class="pilloway-qc-caption">' . esc_html( $labels['caption'] ) . '</p>';
		return str_replace( '<div class="wc-block-mini-cart__footer-actions">', '<div class="wc-block-mini-cart__footer-actions">' . $quick, $html );
	}

	public static function assets(): void {
		foreach ( [ 'css' => 'style', 'js' => 'script' ] as $ext => $type ) {
			$file = 'checkout.' . $ext;
			$url  = plugins_url( $file, __FILE__ );
			$version = (string) filemtime( __DIR__ . '/' . $file );
			if ( 'style' === $type ) {
				wp_enqueue_style( 'pilloway-quick-checkout', $url, [], $version );
			} else {
				wp_enqueue_script( 'pilloway-quick-checkout', $url, [], $version, true );
			}
		}
		wp_localize_script( 'pilloway-quick-checkout', 'pillowayQuickCheckout', [
			'lang'    => self::language(),
			'preview' => WC_AJAX::get_endpoint( 'pilloway_quick_preview' ),
			'submit'  => WC_AJAX::get_endpoint( 'pilloway_quick_submit' ),
			'privacy' => get_privacy_policy_url(),
			'labels'  => self::labels(),
		] );
	}

	private static function field( string $key ): string {
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] )
			? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	private static function sign( string $value ): string {
		return hash_hmac( 'sha256', WC()->session->get_customer_id() . '|' . $value, wp_salt( 'nonce' ) );
	}

	private static function clock(): int {
		return (int) floor( microtime( true ) * 1000 );
	}

	private static function error( string $code, int $status = 400 ): void {
		wp_send_json_error( [ 'code' => $code ], $status );
	}

	private static function subtotal(): float {
		$cart = WC()->cart;
		return (float) $cart->get_cart_contents_total() + (float) $cart->get_cart_contents_tax();
	}

	private static function cart_signature(): string {
		$cart = WC()->cart;
		return self::sign( $cart->get_cart_hash() . '|' . self::subtotal() . '|' . get_woocommerce_currency() );
	}

	private static function money( float $value ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $value ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	public static function preview(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			self::error( 'retry', 405 );
		}
		if ( WC()->cart->is_empty() ) {
			self::error( 'empty' );
		}
		WC()->cart->calculate_totals();
		$rows = [];
		foreach ( WC()->cart->get_cart() as $line ) {
			$product = $line['data'];
			$rows[] = [
				'name'       => $product->get_name(),
				'image'      => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src( 'woocommerce_thumbnail' ),
				'quantity'   => $line['quantity'],
				'price'      => self::money( (float) $line['line_total'] + (float) $line['line_tax'] ),
			];
		}
		$token = self::clock() . '.' . wp_generate_password( 32, false );
		wp_send_json_success( [
			'items'     => $rows,
			'discount'  => self::money( (float) WC()->cart->get_discount_total() + (float) WC()->cart->get_discount_tax() ),
			'coupons'   => array_values( WC()->cart->get_applied_coupons() ),
			'total'     => self::money( self::subtotal() ),
			'cart'      => self::cart_signature(),
			'token'     => $token,
			'proof'     => self::sign( self::NONCE . '|' . $token ),
		] );
	}

	private static function attributes( array $line ): array {
		$result = [];
		foreach ( $line['variation'] ?? [] as $key => $value ) {
			$name = substr( $key, strlen( 'attribute_' ) );
			$term = taxonomy_exists( $name ) ? get_term_by( 'slug', $value, $name ) : false;
			$result[] = [ 'key' => wc_attribute_label( $name, $line['data'] ), 'value' => $term ? $term->name : $value ];
		}
		if ( [] === $result ) {
			foreach ( $line['data']->get_attributes() as $attribute ) {
				if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_visible() ) {
					continue;
				}
				$name = $attribute->get_name();
				$value = $attribute->is_taxonomy()
					? wc_get_product_terms( $line['product_id'], $name, [ 'fields' => 'names' ] )
					: $attribute->get_options();
				if ( $value ) {
					$result[] = [ 'key' => wc_attribute_label( $name, $line['data'] ), 'value' => implode( ', ', $value ) ];
				}
			}
		}
		return $result;
	}

	public static function submit(): void {
		global $wpdb;
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			self::error( 'retry', 405 );
		}
		$token = self::field( 'token' );
		if ( ! preg_match( '/^(\d{13})\.[A-Za-z0-9]{32}$/D', $token, $parts )
			|| abs( self::clock() - (int) $parts[1] ) > HOUR_IN_SECONDS * 1000
			|| ! hash_equals( self::sign( self::NONCE . '|' . $token ), self::field( 'proof' ) ) ) {
			self::error( 'expired', 403 );
		}
		$key  = 'wc_order_' . substr( self::sign( 'order|' . $token ), 0, 13 );
		$lock = 'pilloway_qc_' . substr( self::sign( 'lock' ), 0, 40 );

		// Serialize requests from the same cart, including requests from separate tabs.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock ) ) ) {
			self::error( 'busy', 409 );
		}

		try {
			$result = self::create( $key, (int) $parts[1] );
		} catch ( Throwable $exception ) {
			wc_get_logger()->error( 'Quick checkout failed: ' . get_class( $exception ), [ 'source' => self::VIA ] );
			$result = [ 'error' => 'retry', 'status' => 500 ];
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}

		if ( isset( $result['error'] ) ) {
			self::error( $result['error'], $result['status'] );
		}
		wp_send_json_success( $result );
	}

	private static function create( string $key, int $issued ): array {
		$existing = wc_get_order( wc_get_order_id_by_order_key( $key ) );
		if ( $existing && self::VIA === $existing->get_created_via() ) {
			if ( $existing->has_status( 'cancelled' ) ) {
				return [ 'error' => 'stock', 'status' => 409 ];
			}
			return self::finish( $existing, self::cart_signature() === $existing->get_meta( '_pilloway_cart_signature' ) );
		}
		$cart = WC()->cart;
		$last_order_key = 'pilloway_qc_last_' . substr( self::sign( 'last-order' ), 0, 35 );
		if ( $issued <= (int) get_transient( $last_order_key ) ) {
			return [ 'error' => 'changed', 'status' => 409 ];
		}
		$name = self::field( 'name' );
		$phone = preg_replace( '/[\s().-]+/u', '', self::field( 'phone' ) );
		$contact = self::field( 'contact' );
		if ( '' === $name || mb_strlen( $name ) > 100 || mb_strlen( $contact ) > 150 ) {
			return [ 'error' => 'invalid', 'status' => 422 ];
		}
		if ( ! preg_match( '/^(?:\+|00)?[0-9]{7,15}$/D', $phone ) || preg_match( '/^(?:\+|00)?(\d)\1+$/D', $phone ) ) {
			return [ 'error' => 'phone_error', 'status' => 422 ];
		}
		$rate_key = 'pilloway_qc_' . substr( hash_hmac( 'sha256', WC_Geolocation::get_ip_address(), wp_salt( 'nonce' ) ), 0, 35 );
		$attempts = (int) get_transient( $rate_key );
		if ( $attempts >= 8 || '' !== self::field( 'website' ) ) {
			return [ 'error' => 'limited', 'status' => 429 ];
		}
		set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
		if ( $cart->is_empty() ) {
			return [ 'error' => 'empty', 'status' => 409 ];
		}
		$cart->calculate_totals();
		if ( ! hash_equals( self::cart_signature(), self::field( 'cart' ) ) ) {
			return [ 'error' => 'changed', 'status' => 409 ];
		}

		foreach ( $cart->get_cart() as $line ) {
			$product = wc_get_product( $line['variation_id'] ?: $line['product_id'] );
			if ( ! $product || $product->is_type( 'variable' ) || ! $product->is_purchasable() || ! $product->is_in_stock()
				|| ( $line['variation_id'] && ( ! $product->is_type( 'variation' ) || (int) $product->get_parent_id() !== (int) $line['product_id'] ) ) ) {
				return [ 'error' => 'stock', 'status' => 409 ];
			}
			if ( $product->is_type( 'variation' ) ) {
				foreach ( $product->get_variation_attributes() as $attribute => $expected ) {
					$selected = $line['variation'][ $attribute ] ?? '';
					if ( '' === $selected || ( '' !== $expected && $expected !== $selected ) ) {
						return [ 'error' => 'stock', 'status' => 409 ];
					}
				}
			}
		}
		if ( is_wp_error( $cart->check_cart_item_stock() ) || is_wp_error( $cart->check_cart_item_validity() ) ) {
			return [ 'error' => 'stock', 'status' => 409 ];
		}

		$order = new WC_Order();
		$order->set_order_key( $key );
		$order->set_created_via( self::VIA );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_prices_include_tax( wc_prices_include_tax() );
		$order->set_customer_id( get_current_user_id() );
		$order->set_billing_first_name( $name );
		$order->set_billing_phone( str_starts_with( $phone, '00' ) ? '+' . substr( $phone, 2 ) : $phone );
		$order->set_discount_total( $cart->get_discount_total() );
		$order->set_discount_tax( $cart->get_discount_tax() );
		foreach ( [
			'_pilloway_checkout_type'       => 'quick',
			'_pilloway_delivery_pending'    => 'yes',
			'_pilloway_contact_confirmed'   => 'no',
			'_pilloway_alternative_contact' => $contact,
			'_pilloway_checkout_language'   => in_array( self::field( 'lang' ), [ 'uk', 'en', 'pl', 'fr', 'de', 'ru' ], true ) ? self::field( 'lang' ) : self::language(),
			'_pilloway_cart_signature'      => self::cart_signature(),
		] as $meta => $value ) {
			$order->update_meta_data( $meta, $value );
		}

		$checkout = WC()->checkout();
		$checkout->create_order_line_items( $order, $cart );
		$checkout->create_order_coupon_lines( $order, $cart );
		foreach ( array_map( null, array_values( $cart->get_cart() ), array_values( $order->get_items() ) ) as [ $line, $item ] ) {
			if ( ! $line['variation_id'] ) {
				foreach ( self::attributes( $line ) as $attribute ) {
					$item->add_meta_data( $attribute['key'], $attribute['value'], true );
				}
			}
		}
		$order->set_total( self::subtotal() );
		$order->update_taxes();
		$order->save();
		try {
			wc_reserve_stock_for_order( $order );
		} catch ( \Automattic\WooCommerce\Checkout\Helpers\ReserveStockException $exception ) {
			$order->update_status( 'cancelled', 'Quick checkout stock reservation failed.' );
			return [ 'error' => 'stock', 'status' => 409 ];
		}
		set_transient( $last_order_key, self::clock(), HOUR_IN_SECONDS );
		return self::finish( $order, true );
	}

	private static function finish( WC_Order $order, bool $clear ): array {
		if ( $order->has_status( 'pending' ) ) {
			$order->update_status( 'on-hold', 'Quick checkout: awaiting contact, delivery and payment confirmation.' );
		}
		if ( $clear ) {
			WC()->cart->empty_cart();
			WC()->session->save_data();
		}
		return [
			'number'         => $order->get_order_number(),
			'transaction_id' => 'quick-' . $order->get_id(),
		];
	}

	public static function admin( WC_Order $order ): void {
		if ( 'quick' !== $order->get_meta( '_pilloway_checkout_type' ) ) {
			return;
		}
		echo '<p><strong>' . esc_html( 'Тип оформлення: Швидке замовлення' ) . '</strong></p>';
		if ( $order->get_meta( '_pilloway_alternative_contact' ) ) {
			echo '<p>' . esc_html( 'Додатковий контакт: ' . $order->get_meta( '_pilloway_alternative_contact' ) ) . '</p>';
		}
		if ( 'yes' === $order->get_meta( '_pilloway_delivery_pending' ) && ! $order->get_shipping_methods() ) {
			echo '<p>' . esc_html( 'Доставка: не погоджена' ) . '</p>';
		}
		if ( ! $order->is_paid() ) {
			echo '<p>' . esc_html( 'Оплата: не оплачено' ) . '</p>';
		}
		echo '<p>' . esc_html( 'Мова: ' . $order->get_meta( '_pilloway_checkout_language' ) ) . '</p>';
	}
}

add_action( 'woocommerce_init', [ Pilloway_Quick_Checkout::class, 'init' ] );
