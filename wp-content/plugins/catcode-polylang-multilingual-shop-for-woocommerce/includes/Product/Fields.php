<?php
/**
 * Which product data is shared by all translations and how it is carried over.
 *
 * Shared data is copied through the WooCommerce CRUD setters, with product,
 * term and image IDs mapped to the target language. Texts (name, descriptions,
 * purchase note, button text, custom attribute values) stay per language.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Product;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Core\Settings;
use CatCode\PolylangShop\Pro\License;

defined( 'ABSPATH' ) || exit;

class Fields {

	/** @return array<string,string> Group key => label. */
	public static function groups(): array {
		return array(
			'price'      => __( 'Prices and sale dates', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'stock'      => __( 'Stock quantity, stock status and backorders', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'sku'        => __( 'SKU and GTIN', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'shipping'   => __( 'Weight, dimensions and shipping class', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'tax'        => __( 'Tax status and tax class', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'media'      => __( 'Product image and gallery', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'taxonomies' => __( 'Categories, tags, attributes and default variation', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'linked'     => __( 'Upsells, cross-sells and grouped products', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'other'      => __( 'Virtual / downloadable, files, featured, catalogue visibility, menu order, reviews, external URL', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
		);
	}

	/** @return string[] Groups shared by the translations of every product. */
	public static function active(): array {
		$all = array_keys( self::groups() );
		if ( License::is_pro() ) {
			$chosen = Settings::get( 'sync_groups', array() );
			if ( is_array( $chosen ) && ! empty( $chosen ) ) {
				return array_values(
					array_filter(
						$all,
						static function ( $group ) use ( $chosen ) {
							return ! isset( $chosen[ $group ] ) || 'no' !== $chosen[ $group ];
						}
					)
				);
			}
		}
		return $all;
	}

	public static function shares( string $group ): bool {
		return in_array( $group, self::active(), true );
	}

	/**
	 * Copy the shared data of $from onto $to (a translation in $lang).
	 *
	 * @param string[] $groups   Groups to copy.
	 * @param bool     $creating True while $to is being created as a copy of $from.
	 */
	public static function copy_product( \WC_Product $from, \WC_Product $to, string $lang, array $groups, bool $creating ): void {
		$has = static function ( $group ) use ( $groups ) {
			return in_array( $group, $groups, true );
		};
		$type = $to->get_type();

		if ( $has( 'price' ) && ! in_array( $type, array( 'variable', 'grouped' ), true ) ) {
			$to->set_regular_price( $from->get_regular_price( 'edit' ) );
			$to->set_sale_price( $from->get_sale_price( 'edit' ) );
			$to->set_date_on_sale_from( $from->get_date_on_sale_from( 'edit' ) );
			$to->set_date_on_sale_to( $from->get_date_on_sale_to( 'edit' ) );
		}

		if ( $has( 'stock' ) ) {
			$to->set_manage_stock( $from->get_manage_stock( 'edit' ) );
			if ( $from->get_manage_stock( 'edit' ) ) {
				$to->set_stock_quantity( $from->get_stock_quantity( 'edit' ) );
			}
			if ( 'variable' !== $type ) {
				$to->set_stock_status( $from->get_stock_status( 'edit' ) );
			}
			$to->set_backorders( $from->get_backorders( 'edit' ) );
			$to->set_low_stock_amount( $from->get_low_stock_amount( 'edit' ) );
			$to->set_sold_individually( $from->get_sold_individually( 'edit' ) );
		}

		if ( $has( 'sku' ) && ! $creating ) {
			self::copy_codes( $from, $to );
		}

		if ( $has( 'shipping' ) ) {
			$to->set_weight( $from->get_weight( 'edit' ) );
			$to->set_length( $from->get_length( 'edit' ) );
			$to->set_width( $from->get_width( 'edit' ) );
			$to->set_height( $from->get_height( 'edit' ) );
			$to->set_shipping_class_id( $from->get_shipping_class_id( 'edit' ) );
		}

		if ( $has( 'tax' ) ) {
			$to->set_tax_status( $from->get_tax_status( 'edit' ) );
			$to->set_tax_class( $from->get_tax_class( 'edit' ) );
		}

		if ( $has( 'media' ) ) {
			$to->set_image_id( self::media( (int) $from->get_image_id( 'edit' ), $lang ) );
			$to->set_gallery_image_ids(
				array_values(
					array_filter(
						array_map(
							static function ( $id ) use ( $lang ) {
								return self::media( (int) $id, $lang );
							},
							(array) $from->get_gallery_image_ids( 'edit' )
						)
					)
				)
			);
		}

		if ( $has( 'taxonomies' ) ) {
			$to->set_category_ids( Terms::translate_ids( (array) $from->get_category_ids( 'edit' ), 'product_cat', $lang ) );
			$to->set_tag_ids( Terms::translate_ids( (array) $from->get_tag_ids( 'edit' ), 'product_tag', $lang ) );
			$to->set_attributes( self::product_attributes( $from, $to, $lang, $creating ) );
			$to->set_default_attributes( self::variation_attributes( (array) $from->get_default_attributes( 'edit' ), (array) $to->get_default_attributes( 'edit' ), $lang, $creating ) );
		}

		if ( $has( 'linked' ) ) {
			$to->set_upsell_ids( self::products( (array) $from->get_upsell_ids( 'edit' ), $lang ) );
			$to->set_cross_sell_ids( self::products( (array) $from->get_cross_sell_ids( 'edit' ), $lang ) );
			if ( 'grouped' === $type && is_callable( array( $from, 'get_children' ) ) ) {
				$to->set_children( self::products( (array) $from->get_children( 'edit' ), $lang ) );
			}
		}

		if ( $has( 'other' ) ) {
			$to->set_virtual( $from->get_virtual( 'edit' ) );
			$to->set_downloadable( $from->get_downloadable( 'edit' ) );
			$to->set_downloads( $from->get_downloads( 'edit' ) );
			$to->set_download_limit( $from->get_download_limit( 'edit' ) );
			$to->set_download_expiry( $from->get_download_expiry( 'edit' ) );
			$to->set_featured( $from->get_featured( 'edit' ) );
			$to->set_catalog_visibility( $from->get_catalog_visibility( 'edit' ) );
			$to->set_menu_order( $from->get_menu_order( 'edit' ) );
			$to->set_reviews_allowed( $from->get_reviews_allowed( 'edit' ) );
			if ( 'external' === $type && is_callable( array( $from, 'get_product_url' ) ) ) {
				$to->set_product_url( $from->get_product_url( 'edit' ) );
			}
		}

		/**
		 * Fires after the shared data of a product was copied onto its translation (not saved yet).
		 *
		 * @param \WC_Product $to       Translation.
		 * @param \WC_Product $from     Source.
		 * @param string      $lang     Language of the translation.
		 * @param string[]    $groups   Shared groups.
		 * @param bool        $creating The translation is being created.
		 */
		do_action( 'catcode_plms_copy_product', $to, $from, $lang, $groups, $creating );
	}

	/**
	 * Same for a variation onto its counterpart in the translated parent.
	 *
	 * @param string[] $groups Groups to copy.
	 */
	public static function copy_variation( \WC_Product_Variation $from, \WC_Product_Variation $to, string $lang, array $groups, bool $creating ): void {
		$has = static function ( $group ) use ( $groups ) {
			return in_array( $group, $groups, true );
		};

		// The attribute combination is what makes the two variations "the same" — always kept in step.
		$to->set_attributes( self::variation_attributes( (array) $from->get_attributes( 'edit' ), (array) $to->get_attributes( 'edit' ), $lang, $creating ) );

		if ( $has( 'price' ) ) {
			$to->set_regular_price( $from->get_regular_price( 'edit' ) );
			$to->set_sale_price( $from->get_sale_price( 'edit' ) );
			$to->set_date_on_sale_from( $from->get_date_on_sale_from( 'edit' ) );
			$to->set_date_on_sale_to( $from->get_date_on_sale_to( 'edit' ) );
		}
		if ( $has( 'stock' ) ) {
			$to->set_manage_stock( $from->get_manage_stock( 'edit' ) );
			if ( true === $from->get_manage_stock( 'edit' ) ) {
				$to->set_stock_quantity( $from->get_stock_quantity( 'edit' ) );
			}
			$to->set_stock_status( $from->get_stock_status( 'edit' ) );
			$to->set_backorders( $from->get_backorders( 'edit' ) );
			$to->set_low_stock_amount( $from->get_low_stock_amount( 'edit' ) );
		}
		if ( $has( 'sku' ) && ! $creating ) {
			self::copy_codes( $from, $to );
		}
		if ( $has( 'shipping' ) ) {
			$to->set_weight( $from->get_weight( 'edit' ) );
			$to->set_length( $from->get_length( 'edit' ) );
			$to->set_width( $from->get_width( 'edit' ) );
			$to->set_height( $from->get_height( 'edit' ) );
			$to->set_shipping_class_id( $from->get_shipping_class_id( 'edit' ) );
		}
		if ( $has( 'tax' ) ) {
			$to->set_tax_class( $from->get_tax_class( 'edit' ) );
		}
		if ( $has( 'media' ) ) {
			$to->set_image_id( self::media( (int) $from->get_image_id( 'edit' ), $lang ) );
		}
		if ( $has( 'other' ) ) {
			$to->set_status( $from->get_status( 'edit' ) );
			$to->set_virtual( $from->get_virtual( 'edit' ) );
			$to->set_downloadable( $from->get_downloadable( 'edit' ) );
			$to->set_downloads( $from->get_downloads( 'edit' ) );
			$to->set_download_limit( $from->get_download_limit( 'edit' ) );
			$to->set_download_expiry( $from->get_download_expiry( 'edit' ) );
			$to->set_menu_order( $from->get_menu_order( 'edit' ) );
		}

		/** This action is documented in includes/Product/Fields.php */
		do_action( 'catcode_plms_copy_variation', $to, $from, $lang, $groups, $creating );
	}

	/**
	 * SKU and GTIN. They are unique per store in WooCommerce; translations of one
	 * product may share them (see Product\Sku). A value WooCommerce still rejects
	 * is skipped rather than breaking the whole save.
	 */
	public static function copy_codes( \WC_Product $from, \WC_Product $to ): void {
		try {
			$to->set_sku( $from->get_sku( 'edit' ) );
		} catch ( \WC_Data_Exception $e ) {
			unset( $e );
		}
		if ( is_callable( array( $from, 'get_global_unique_id' ) ) && is_callable( array( $to, 'set_global_unique_id' ) ) ) {
			try {
				$to->set_global_unique_id( $from->get_global_unique_id( 'edit' ) );
			} catch ( \WC_Data_Exception $e ) {
				unset( $e );
			}
		}
	}

	/**
	 * Global (taxonomy) attributes come from the source with translated terms; custom
	 * attributes are text, so an existing translation keeps its own.
	 *
	 * @return \WC_Product_Attribute[]
	 */
	public static function product_attributes( \WC_Product $from, \WC_Product $to, string $lang, bool $creating ): array {
		$out = array();
		foreach ( (array) $from->get_attributes( 'edit' ) as $key => $attr ) {
			if ( ! $attr instanceof \WC_Product_Attribute ) {
				continue;
			}
			if ( $attr->is_taxonomy() ) {
				$copy = clone $attr;
				$copy->set_options( Terms::translate_ids( Terms::option_ids( (array) $attr->get_options(), $attr->get_name(), $lang ), $attr->get_name(), $lang ) );
				$out[ $key ] = $copy;
			} elseif ( $creating ) {
				$out[ $key ] = clone $attr;
			}
		}
		if ( ! $creating ) {
			foreach ( (array) $to->get_attributes( 'edit' ) as $key => $attr ) {
				if ( $attr instanceof \WC_Product_Attribute && ! $attr->is_taxonomy() && ! isset( $out[ $key ] ) ) {
					$out[ $key ] = $attr;
				}
			}
		}
		uasort(
			$out,
			static function ( $a, $b ) {
				return (int) $a->get_position() <=> (int) $b->get_position();
			}
		);
		return $out;
	}

	/**
	 * Variation attributes / default attributes: attribute_pa_* term slugs are
	 * translated, custom values stay as the target has them (copied on creation).
	 *
	 * @param array<string,string> $source Source values.
	 * @param array<string,string> $target Current values of the target.
	 * @return array<string,string>
	 */
	public static function variation_attributes( array $source, array $target, string $lang, bool $creating ): array {
		$out = array();
		foreach ( $source as $name => $value ) {
			$name     = (string) $name;
			$value    = (string) $value;
			$taxonomy = self::attribute_taxonomy( $name );
			if ( '' !== $taxonomy ) {
				$out[ $name ] = '' === $value ? '' : Terms::translate_slug( $value, $taxonomy, $lang );
			} elseif ( $creating || ! array_key_exists( $name, $target ) ) {
				$out[ $name ] = $value;
			}
		}
		if ( ! $creating ) {
			foreach ( $target as $name => $value ) {
				if ( '' === self::attribute_taxonomy( (string) $name ) && ! array_key_exists( $name, $out ) ) {
					$out[ $name ] = $value;
				}
			}
		}
		return $out;
	}

	/** "pa_color" or "attribute_pa_color" → "pa_color"; '' for a custom attribute. */
	public static function attribute_taxonomy( string $name ): string {
		if ( 0 === strpos( $name, 'attribute_' ) ) {
			$name = substr( $name, 10 );
		}
		return ( 0 === strpos( $name, 'pa_' ) && taxonomy_exists( $name ) ) ? $name : '';
	}

	/**
	 * @param int[] $ids Product IDs.
	 * @return int[] Their translations into $lang (products without one are left out).
	 */
	public static function products( array $ids, string $lang ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$tr = Lang::post_in( $id, $lang );
			if ( $tr > 0 ) {
				$out[] = $tr;
			} elseif ( '' === Lang::post_lang( $id ) ) {
				// Not in any language (yet) — the same product is the closest match.
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Image in $lang when Polylang translates media, otherwise the same file. */
	public static function media( int $attachment_id, string $lang ): int {
		if ( $attachment_id <= 0 ) {
			return 0;
		}
		if ( function_exists( 'pll_is_translated_post_type' ) && pll_is_translated_post_type( 'attachment' ) ) {
			$tr = Lang::post_in( $attachment_id, $lang );
			if ( $tr > 0 ) {
				return $tr;
			}
		}
		return $attachment_id;
	}
}
