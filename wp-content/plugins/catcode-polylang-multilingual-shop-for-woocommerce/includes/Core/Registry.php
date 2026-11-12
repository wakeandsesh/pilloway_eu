<?php
/**
 * Tells Polylang what in WooCommerce is translatable and what is not, and keeps
 * Polylang's own "copy / synchronise custom fields" away from WooCommerce data —
 * that data goes through the WooCommerce CRUD layer here (lookup tables, caches,
 * translated term and product IDs), never as raw post meta.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Core;

defined( 'ABSPATH' ) || exit;

class Registry {

	/** Post types that must never get a language. */
	private const NEVER_POST_TYPES = array( 'product_variation', 'shop_order', 'shop_order_refund', 'shop_order_placehold', 'shop_coupon', 'shop_webhook' );

	/** WooCommerce taxonomies that stay shared by all languages. */
	private const NEVER_TAXONOMIES = array( 'product_type', 'product_visibility', 'product_shipping_class' );

	/** Product meta handled by this plugin (Product\Fields), not by Polylang's meta copy. */
	private const WC_META = array(
		'_sku',
		'_global_unique_id',
		'_regular_price',
		'_sale_price',
		'_sale_price_dates_from',
		'_sale_price_dates_to',
		'_price',
		'_tax_status',
		'_tax_class',
		'_manage_stock',
		'_backorders',
		'_low_stock_amount',
		'_sold_individually',
		'_weight',
		'_length',
		'_width',
		'_height',
		'_upsell_ids',
		'_crosssell_ids',
		'_default_attributes',
		'_product_attributes',
		'_virtual',
		'_downloadable',
		'_downloadable_files',
		'_download_limit',
		'_download_expiry',
		'_product_image_gallery',
		'_thumbnail_id',
		'_stock',
		'_stock_status',
		'_children',
		'_product_url',
		'_wc_average_rating',
		'_wc_rating_count',
		'_wc_review_count',
		'_product_version',
		'total_sales',
	);

	public static function register(): void {
		add_filter( 'pll_get_post_types', array( __CLASS__, 'post_types' ), 20, 2 );
		add_filter( 'pll_get_taxonomies', array( __CLASS__, 'taxonomies' ), 20, 2 );
		add_filter( 'pll_copy_post_metas', array( __CLASS__, 'copy_post_metas' ), 20, 3 );
		add_filter( 'pll_copy_taxonomies', array( __CLASS__, 'copy_taxonomies' ), 20, 3 );
		add_filter( 'wp_insert_post_parent', array( __CLASS__, 'variation_parent' ), 20, 4 );
	}

	/**
	 * Polylang moves a post's parent into the post's own language. A variation has no
	 * language of its own — but one that started as a simple product (the CSV importer
	 * creates such placeholders) keeps the default language, and Polylang would then
	 * hang the variation of a translated product under the default-language product.
	 * A variation keeps exactly the parent it is saved with.
	 *
	 * @param int                 $parent      Parent after earlier filters.
	 * @param int                 $post_id     Post ID.
	 * @param array<string,mixed> $new_postarr Post data being saved.
	 * @param array<string,mixed> $postarr     Raw data passed to wp_insert_post().
	 * @return int
	 */
	public static function variation_parent( $parent, $post_id = 0, $new_postarr = array(), $postarr = array() ) {
		unset( $post_id, $new_postarr );
		if ( is_array( $postarr ) && isset( $postarr['post_type'], $postarr['post_parent'] ) && 'product_variation' === $postarr['post_type'] ) {
			return (int) $postarr['post_parent'];
		}
		return $parent;
	}

	/**
	 * @param string[] $types       Post types.
	 * @param bool     $is_settings Listing for the Polylang settings screen.
	 * @return string[]
	 */
	public static function post_types( $types, $is_settings ) {
		$types = is_array( $types ) ? $types : array();
		// Added in both calls: Polylang then shows "Products" as ticked and locked.
		$types['product'] = 'product';
		foreach ( self::NEVER_POST_TYPES as $type ) {
			unset( $types[ $type ] );
		}
		return $types;
	}

	/**
	 * @param string[] $taxonomies  Taxonomies.
	 * @param bool     $is_settings Listing for the Polylang settings screen.
	 * @return string[]
	 */
	public static function taxonomies( $taxonomies, $is_settings ) {
		$taxonomies = is_array( $taxonomies ) ? $taxonomies : array();
		foreach ( self::translated_taxonomies() as $tax ) {
			$taxonomies[ $tax ] = $tax;
		}
		foreach ( self::NEVER_TAXONOMIES as $tax ) {
			unset( $taxonomies[ $tax ] );
		}
		return $taxonomies;
	}

	/** @return string[] product_cat, product_tag and every global attribute (pa_*). */
	public static function translated_taxonomies(): array {
		$list = array( 'product_cat', 'product_tag' );
		if ( function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
			$list = array_merge( $list, (array) wc_get_attribute_taxonomy_names() );
		}
		return array_values( array_unique( array_map( 'strval', $list ) ) );
	}

	/**
	 * Other taxonomies of products (WooCommerce brands, a theme's own ones…): they are not
	 * part of the product object, so the plugin carries them to translations itself.
	 *
	 * @return string[]
	 */
	public static function extra_taxonomies(): array {
		$skip = array_merge( self::translated_taxonomies(), self::NEVER_TAXONOMIES, array( 'language', 'post_translations' ) );
		return array_values( array_diff( array_map( 'strval', get_object_taxonomies( 'product' ) ), $skip ) );
	}

	/** @return string[] Taxonomies whose terms get a copy in every language: ours plus the extra ones Polylang translates. */
	public static function bulk_taxonomies(): array {
		$list = self::translated_taxonomies();
		foreach ( self::extra_taxonomies() as $taxonomy ) {
			if ( Lang::is_translated_taxonomy( $taxonomy ) ) {
				$list[] = $taxonomy;
			}
		}
		return $list;
	}

	/**
	 * @param string[] $keys Meta keys Polylang is about to copy.
	 * @param bool     $sync True when synchronising, false when copying for a new translation.
	 * @param int      $from Source post.
	 * @return string[]
	 */
	public static function copy_post_metas( $keys, $sync, $from ) {
		if ( ! is_array( $keys ) || ! self::is_product( (int) $from ) ) {
			return $keys;
		}
		return array_values(
			array_filter(
				$keys,
				static function ( $key ) {
					return ! in_array( (string) $key, self::WC_META, true ) && 0 !== strpos( (string) $key, '_catcode_plms_' );
				}
			)
		);
	}

	/**
	 * @param string[] $taxonomies Taxonomies Polylang is about to copy.
	 * @param bool     $sync       True when synchronising.
	 * @param int      $from       Source post.
	 * @return string[]
	 */
	public static function copy_taxonomies( $taxonomies, $sync, $from ) {
		if ( ! is_array( $taxonomies ) || ! self::is_product( (int) $from ) ) {
			return $taxonomies;
		}
		$own = array_merge( self::translated_taxonomies(), self::NEVER_TAXONOMIES );
		return array_values(
			array_filter(
				$taxonomies,
				static function ( $tax ) use ( $own ) {
					return ! in_array( (string) $tax, $own, true );
				}
			)
		);
	}

	private static function is_product( int $post_id ): bool {
		return $post_id > 0 && 'product' === get_post_type( $post_id );
	}
}
