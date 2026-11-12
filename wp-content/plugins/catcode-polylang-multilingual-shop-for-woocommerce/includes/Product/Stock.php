<?php
/**
 * One stock for all languages: when an order (or anything else going through
 * wc_update_product_stock()) changes the stock of a product or variation, its
 * translations get the same quantity. The copies written here do not raise their
 * own low / out of stock notifications: the product that changed already did.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Product;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class Stock {

	/** @var bool Translations are being brought to the new quantity right now. */
	private static $copying = false;

	public function register(): void {
		add_action( 'woocommerce_product_set_stock', array( $this, 'stock_changed' ), 20 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'stock_changed' ), 20 );
		add_filter( 'woocommerce_should_send_low_stock_notification', array( $this, 'notify' ), 20 );
		add_filter( 'woocommerce_should_send_no_stock_notification', array( $this, 'notify' ), 20 );
	}

	/**
	 * One notification per product, not one per language.
	 *
	 * @param bool $send Send the notification.
	 * @return bool
	 */
	public function notify( $send ) {
		return self::$copying ? false : $send;
	}

	/**
	 * @param \WC_Product $product Product whose stock was just written.
	 */
	public function stock_changed( $product ): void {
		if ( Sync::paused() || ! Lang::ready() || ! $product instanceof \WC_Product || ! Fields::shares( 'stock' ) ) {
			return;
		}
		if ( ! $product->get_manage_stock( 'edit' ) ) {
			return;
		}
		$id = $product->get_id();
		// Fresh from the database: the object may carry a value from before a concurrent order.
		$qty = wc_stock_amount( get_post_meta( $id, '_stock', true ) );

		$siblings = $product->is_type( 'variation' ) ? Variations::siblings( $id ) : Lang::other_translations( $id );
		if ( empty( $siblings ) ) {
			return;
		}
		Sync::pause();
		self::$copying = true;
		try {
			foreach ( $siblings as $sibling_id ) {
				$sibling = wc_get_product( $sibling_id );
				if ( ! $sibling || true !== $sibling->get_manage_stock( 'edit' ) ) {
					continue;
				}
				if ( wc_stock_amount( $sibling->get_stock_quantity( 'edit' ) ) === $qty ) {
					continue;
				}
				wc_update_product_stock( $sibling, $qty, 'set' );
			}
		} finally {
			self::$copying = false;
			Sync::resume();
		}
	}
}
