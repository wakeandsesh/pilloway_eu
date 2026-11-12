<?php
/**
 * One-click product translation: a full copy of the product in the target
 * language — texts to translate, everything else already in place (prices,
 * stock, SKU, images, translated categories / tags / attributes, variations).
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Product;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Translator {

	/**
	 * @param int    $product_id Source product.
	 * @param string $lang       Target language slug.
	 * @param string $status     Post status of the copy: draft | source (same as the original).
	 * @return int|\WP_Error New (or already existing) translation ID.
	 */
	public static function translate( int $product_id, string $lang, string $status = '' ) {
		if ( ! Lang::ready() ) {
			return new \WP_Error( 'catcode_plms_no_polylang', __( 'Polylang is not active or has no languages.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) );
		}
		if ( ! Lang::is_valid( $lang ) ) {
			return new \WP_Error( 'catcode_plms_bad_lang', __( 'Unknown language.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) );
		}
		$src = wc_get_product( $product_id );
		if ( ! $src || $src->is_type( 'variation' ) ) {
			return new \WP_Error( 'catcode_plms_no_product', __( 'Product not found.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) );
		}

		$src_lang = Lang::post_lang( $product_id );
		if ( '' === $src_lang ) {
			// A product from before the store was multilingual belongs to the default language.
			$src_lang = Lang::default();
			pll_set_post_language( $product_id, $src_lang );
		}
		if ( $src_lang === $lang ) {
			return new \WP_Error( 'catcode_plms_same_lang', __( 'The product is already in this language.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) );
		}
		$existing = Lang::post_in( $product_id, $lang );
		if ( $existing > 0 ) {
			return $existing;
		}

		if ( '' === $status ) {
			$status = (string) Settings::get( 'new_status', 'draft' );
		}
		$post_status = 'source' === $status ? $src->get_status( 'edit' ) : 'draft';
		$groups      = Fields::active();

		Sync::pause();
		try {
			$copy = clone $src;
			$copy->set_id( 0 );
			$copy->set_date_created( null );
			$copy->set_slug( sanitize_title( $src->get_slug( 'edit' ) . '-' . $lang ) );
			$copy->set_status( $post_status );
			$copy->set_total_sales( 0 );
			$copy->set_rating_counts( array() );
			$copy->set_average_rating( 0 );
			$copy->set_review_count( 0 );
			$copy->set_sku( '' );
			if ( is_callable( array( $copy, 'set_global_unique_id' ) ) ) {
				$copy->set_global_unique_id( '' );
			}
			if ( $copy->is_type( 'variable' ) ) {
				$copy->set_children( array() );
			}
			// IDs (terms, images, linked products) always need mapping on a fresh copy, whatever is shared later.
			Fields::copy_product( $src, $copy, $lang, array_values( array_unique( array_merge( $groups, array( 'media', 'taxonomies', 'linked' ) ) ) ), true );

			/**
			 * Adjust the copy before it is first saved.
			 *
			 * @param \WC_Product $copy Translation being created.
			 * @param \WC_Product $src  Source product.
			 * @param string      $lang Target language.
			 */
			do_action( 'catcode_plms_before_translation_save', $copy, $src, $lang );

			$copy->save();
			$new_id = $copy->get_id();
			if ( $new_id <= 0 ) {
				return new \WP_Error( 'catcode_plms_save_failed', __( 'The translation could not be saved.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) );
			}
			Lang::link_post( $product_id, $new_id, $lang );

			// Categories and tags are written again now that the post has its language:
			// Polylang may have attached default-language terms while the post had none.
			$copy->set_category_ids( Terms::translate_ids( (array) $src->get_category_ids( 'edit' ), 'product_cat', $lang ) );
			$copy->set_tag_ids( Terms::translate_ids( (array) $src->get_tag_ids( 'edit' ), 'product_tag', $lang ) );
			if ( in_array( 'sku', $groups, true ) ) {
				// Only now: the uniqueness check needs to see the copy inside the translation group.
				Fields::copy_codes( $src, $copy );
			}
			$copy->save();
			Terms::write_own( $copy );
			Terms::copy_extra( $product_id, $new_id, $lang );

			if ( $src->is_type( 'variable' ) ) {
				foreach ( $src->get_children() as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( $child instanceof \WC_Product_Variation ) {
						Variations::create_counterpart( $child, $new_id, $lang, $groups );
					}
				}
				\WC_Product_Variable::sync( $new_id );
			}
		} finally {
			Sync::resume();
		}

		wc_delete_product_transients( $new_id );

		/**
		 * Fires after a product translation was created.
		 *
		 * @param int    $new_id     Translation ID.
		 * @param int    $product_id Source product ID.
		 * @param string $lang       Language of the translation.
		 */
		do_action( 'catcode_plms_product_translated', $new_id, $product_id, $lang );
		return $new_id;
	}
}
