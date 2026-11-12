<?php
/**
 * A coupon limited to (or excluding) a product or category covers that product
 * or category in every language — the shopper's cart holds the translation.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Order;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Product\Variations;

defined( 'ABSPATH' ) || exit;

class Coupons {

	public function register(): void {
		// WooCommerce applies these getter filters in the "view" context only — the coupon editor keeps the raw lists.
		add_filter( 'woocommerce_coupon_get_product_ids', array( $this, 'products' ), 20 );
		add_filter( 'woocommerce_coupon_get_excluded_product_ids', array( $this, 'products' ), 20 );
		add_filter( 'woocommerce_coupon_get_product_categories', array( $this, 'categories' ), 20 );
		add_filter( 'woocommerce_coupon_get_excluded_product_categories', array( $this, 'categories' ), 20 );
	}

	/**
	 * @param int[] $ids Product / variation IDs.
	 * @return int[]
	 */
	public function products( $ids ) {
		if ( ! is_array( $ids ) || empty( $ids ) || ! Lang::ready() ) {
			return $ids;
		}
		$out = array();
		foreach ( $ids as $id ) {
			$id    = (int) $id;
			$out[] = $id;
			if ( 'product_variation' === get_post_type( $id ) ) {
				$out = array_merge( $out, array_values( Variations::siblings( $id ) ) );
			} else {
				$out = array_merge( $out, array_values( Lang::post_translations( $id ) ) );
			}
		}
		return array_values( array_unique( array_map( 'intval', $out ) ) );
	}

	/**
	 * @param int[] $ids Category term IDs.
	 * @return int[]
	 */
	public function categories( $ids ) {
		if ( ! is_array( $ids ) || empty( $ids ) || ! Lang::ready() ) {
			return $ids;
		}
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = (int) $id;
			$out   = array_merge( $out, array_values( Lang::term_translations( (int) $id ) ) );
		}
		return array_values( array_unique( array_map( 'intval', $out ) ) );
	}
}
