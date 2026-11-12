<?php
/**
 * WooCommerce allows one product per SKU (and per GTIN). The translations of a
 * product are one product for the warehouse, so they may share the code; any
 * other product with it is still refused as before.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Product;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class Sku {

	public function register(): void {
		add_filter( 'wc_product_pre_has_unique_sku', array( $this, 'unique_sku' ), 20, 3 );
		add_filter( 'wc_product_pre_has_global_unique_id', array( $this, 'unique_gtin' ), 20, 3 );
		add_filter( 'woocommerce_get_product_id_by_sku', array( $this, 'id_by_sku' ), 20, 2 );
	}

	/**
	 * @param bool|null $unique     Earlier verdict.
	 * @param int       $product_id Product being saved.
	 * @param string    $sku        SKU.
	 * @return bool|null
	 */
	public function unique_sku( $unique, $product_id, $sku ) {
		if ( null !== $unique ) {
			return $unique;
		}
		return self::verdict( (int) $product_id, (string) $sku, '_sku' );
	}

	/**
	 * @param bool|null $unique     Earlier verdict.
	 * @param int       $product_id Product being saved.
	 * @param string    $gtin       GTIN / UPC / EAN / ISBN.
	 * @return bool|null
	 */
	public function unique_gtin( $unique, $product_id, $gtin ) {
		if ( null !== $unique ) {
			return $unique;
		}
		return self::verdict( (int) $product_id, (string) $gtin, '_global_unique_id' );
	}

	/**
	 * true = unique (every other holder is a translation of this product), null = let
	 * WooCommerce decide as usual.
	 */
	private static function verdict( int $product_id, string $code, string $meta_key ): ?bool {
		if ( $product_id <= 0 || '' === $code || ! Lang::ready() ) {
			return null;
		}
		$holders = self::holders( $code, $meta_key, $product_id );
		if ( empty( $holders ) ) {
			return null;
		}
		$family = self::family( $product_id );
		foreach ( $holders as $id ) {
			if ( ! in_array( $id, $family, true ) ) {
				return null;
			}
		}
		return true;
	}

	/** @return int[] The product itself and the same product in the other languages. */
	private static function family( int $product_id ): array {
		if ( 'product_variation' === get_post_type( $product_id ) ) {
			return array_values( array_map( 'intval', array_merge( array( $product_id ), Variations::siblings( $product_id ) ) ) );
		}
		$list = Lang::post_translations( $product_id );
		$list = array_values( array_map( 'intval', $list ) );
		$list[] = $product_id;
		return array_values( array_unique( $list ) );
	}

	/** @return int[] Other products / variations (not in the trash) holding this code. */
	private static function holders( string $code, string $meta_key, int $exclude ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- same lookup WooCommerce runs for its own uniqueness check.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT posts.ID FROM {$wpdb->posts} AS posts
				INNER JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				WHERE posts.post_type IN ( 'product', 'product_variation' )
				AND posts.post_status != 'trash'
				AND meta.meta_key = %s AND meta.meta_value = %s
				AND posts.ID <> %d",
				$meta_key,
				$code,
				$exclude
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Several products now share a SKU; the one in the shopper's language wins.
	 *
	 * @param int    $id  Product found by WooCommerce.
	 * @param string $sku SKU.
	 */
	public function id_by_sku( $id, $sku ) {
		$id = (int) $id;
		// Storefront only: imports, the REST API and WP-CLI look a SKU up to find *the*
		// product they write to — swapping it for a translation there would write to the
		// wrong language.
		if ( $id <= 0 || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return $id;
		}
		$lang = Lang::current();
		if ( '' === $lang ) {
			return $id;
		}
		if ( 'product_variation' === get_post_type( $id ) ) {
			$tr = Variations::in( $id, $lang );
		} else {
			$tr = Lang::post_in( $id, $lang );
		}
		return $tr > 0 ? $tr : $id;
	}
}
