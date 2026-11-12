<?php
/**
 * The order remembers the language it was placed in (classic and block checkout,
 * stored through the order CRUD — HPOS and legacy storage alike). The admin sees
 * it in the orders list and can change it on the order screen.
 *
 * The block checkout places the order through the Store API, where the language
 * comes only from the ?lang= its requests carry. When that parameter is lost on the
 * way (a JS optimizer dropping the inline middleware, a custom or headless checkout),
 * the order still gets the language of the last shop page the shopper viewed, kept
 * in the WooCommerce session, or else the language of the products in the order.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Order;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class OrderLanguage {

	public const META = '_catcode_plms_lang';

	/** WooCommerce session key: language of the last shop page the shopper viewed. */
	public const SESSION = 'catcode_plms_lang';

	public function register(): void {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'from_checkout' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'from_checkout' ), 20 );
		add_action( 'template_redirect', array( $this, 'remember_page_language' ), 20 );

		if ( is_admin() ) {
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'columns' ), 20 );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'column_hpos' ), 20, 2 );
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'columns' ), 20 );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'column_legacy' ), 20, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'column_style' ) );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'order_field' ), 20 );
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_field' ), 60, 2 );
		}
	}

	public static function get( \WC_Order $order ): string {
		$lang = (string) $order->get_meta( self::META, true );
		return Lang::is_valid( $lang ) ? $lang : '';
	}

	/**
	 * @param \WC_Order $order Order being created (classic) or updated (Store API).
	 */
	public function from_checkout( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$lang = Lang::current();
		if ( '' === $lang ) {
			$lang = self::session_language();
		}
		if ( '' === $lang ) {
			$lang = self::items_language( $order );
		}
		if ( '' === $lang ) {
			return;
		}
		$order->update_meta_data( self::META, $lang );
		// The Store API hook runs after the order was saved; the classic one before.
		if ( doing_action( 'woocommerce_store_api_checkout_update_order_meta' ) ) {
			$order->save_meta_data();
		}
	}

	/** Keep the language of each shop page view for requests that do not carry one. */
	public function remember_page_language(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! function_exists( 'WC' ) ) {
			return;
		}
		$session = WC()->session;
		// Only for shoppers who already have a session (a cart): no session is started here.
		if ( ! $session instanceof \WC_Session || ! $session->has_session() ) {
			return;
		}
		$lang = Lang::current();
		if ( Lang::is_valid( $lang ) && $lang !== (string) $session->get( self::SESSION, '' ) ) {
			$session->set( self::SESSION, $lang );
		}
	}

	private static function session_language(): string {
		$session = function_exists( 'WC' ) ? WC()->session : null;
		if ( ! $session instanceof \WC_Session ) {
			return '';
		}
		$lang = (string) $session->get( self::SESSION, '' );
		return Lang::is_valid( $lang ) ? $lang : '';
	}

	/** The language shared by all products of the order; '' when they differ or have none. */
	private static function items_language( \WC_Order $order ): string {
		$found = '';
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$lang = Lang::post_lang( (int) $item->get_product_id() );
			if ( '' === $lang || ( '' !== $found && $lang !== $found ) ) {
				return '';
			}
			$found = $lang;
		}
		return Lang::is_valid( $found ) ? $found : '';
	}

	/**
	 * @param array<string,string> $columns Columns.
	 * @return array<string,string>
	 */
	public function columns( $columns ) {
		if ( ! is_array( $columns ) || ! Lang::ready() ) {
			return $columns;
		}
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out['catcode_plms_lang'] = __( 'Language', 'catcode-polylang-multilingual-shop-for-woocommerce' );
			}
		}
		if ( ! isset( $out['catcode_plms_lang'] ) ) {
			$out['catcode_plms_lang'] = __( 'Language', 'catcode-polylang-multilingual-shop-for-woocommerce' );
		}
		return $out;
	}

	/**
	 * Between the mobile layout (782px) and ~960px the orders table has no room for one
	 * more column: it would push the page sideways. The language stays in the order itself
	 * and comes back in the mobile row details and on wider screens.
	 */
	public function column_style(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) ) {
			return;
		}
		wp_register_style( 'catcode-plms-orders', false, array(), CATCODE_PLMS_VERSION );
		wp_enqueue_style( 'catcode-plms-orders' );
		wp_add_inline_style( 'catcode-plms-orders', '@media screen and (min-width:783px) and (max-width:960px){.wp-list-table .column-catcode_plms_lang{display:none}}' );
	}

	/**
	 * @param string    $column Column.
	 * @param \WC_Order $order  Order.
	 */
	public function column_hpos( $column, $order ): void {
		if ( 'catcode_plms_lang' === $column && $order instanceof \WC_Order ) {
			$this->print_lang( $order );
		}
	}

	/**
	 * @param string $column  Column.
	 * @param int    $post_id Order ID.
	 */
	public function column_legacy( $column, $post_id ): void {
		if ( 'catcode_plms_lang' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order instanceof \WC_Order ) {
			$this->print_lang( $order );
		}
	}

	private function print_lang( \WC_Order $order ): void {
		$lang = self::get( $order );
		echo '' === $lang ? '&ndash;' : esc_html( Lang::name( $lang ) );
	}

	/**
	 * @param \WC_Order $order Order.
	 */
	public function order_field( $order ): void {
		if ( ! $order instanceof \WC_Order || ! Lang::ready() ) {
			return;
		}
		$current = self::get( $order );
		echo '<p class="form-field form-field-wide catcode-plms-order-lang"><label for="catcode_plms_order_lang">' . esc_html__( 'Order language (customer e-mails):', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</label>';
		echo '<select id="catcode_plms_order_lang" name="catcode_plms_order_lang">';
		echo '<option value="">' . esc_html__( 'Not set — store default', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</option>';
		foreach ( Lang::slugs() as $slug ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( Lang::name( $slug ) ) . '</option>';
		}
		echo '</select></p>';
	}

	/**
	 * @param int            $order_id Order ID.
	 * @param \WC_Order|null $order    Order (HPOS passes it).
	 */
	public function save_field( $order_id, $order = null ): void {
		if ( ! isset( $_POST['catcode_plms_order_lang'], $_POST['woocommerce_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$lang  = sanitize_key( wp_unslash( $_POST['catcode_plms_order_lang'] ) );
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		if ( '' === $lang ) {
			$order->delete_meta_data( self::META );
		} elseif ( Lang::is_valid( $lang ) ) {
			$order->update_meta_data( self::META, $lang );
		} else {
			return;
		}
		$order->save_meta_data();
	}
}
