<?php
/**
 * WooCommerce product CSV export / import with two extra columns: "Language"
 * (Polylang slug) and "Translation group" (the same value on every language
 * version of one product). Import them back and the products come in already in
 * their languages and linked as translations — variations included.
 *
 * WooCommerce's importer identifies a product by its SKU, and translations share
 * one. So the export leaves SKU / GTIN empty on the rows of the other languages
 * (the default-language row keeps them); after import the translation is linked
 * by its group and takes the codes from the default-language product again.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Pro;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Product\Fields;
use CatCode\PolylangShop\Product\Sync;
use CatCode\PolylangShop\Product\Terms;
use CatCode\PolylangShop\Product\Variations;

defined( 'ABSPATH' ) || exit;

class Csv {

	public const COL_LANG  = 'catcode_plms_lang';
	public const COL_GROUP = 'catcode_plms_group';

	/** Product meta holding the imported group key, so later batches can find the other languages. */
	public const META_GROUP = '_catcode_plms_import_group';

	/** @var bool Sync is paused for the rest of this import request. */
	private $import_mode = false;

	public function register(): void {
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'export_columns' ), 20 );
		add_filter( 'woocommerce_product_export_product_column_' . self::COL_LANG, array( $this, 'export_lang' ), 20, 2 );
		add_filter( 'woocommerce_product_export_product_column_' . self::COL_GROUP, array( $this, 'export_group' ), 20, 2 );
		add_filter( 'woocommerce_product_export_product_query_args', array( $this, 'export_all_languages' ), 20 );
		add_filter( 'woocommerce_product_export_row_data', array( $this, 'export_row' ), 20, 2 );

		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'import_options' ), 20 );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'import_defaults' ), 20 );
		add_action( 'woocommerce_product_importer_before_set_parsed_data', array( $this, 'import_started' ), 10, 2 );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'before_insert' ), 20, 2 );
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'after_insert' ), 20, 2 );
	}

	/**
	 * @param array<string,string> $columns Columns.
	 * @return array<string,string>
	 */
	public function export_columns( $columns ) {
		if ( ! is_array( $columns ) || ! Lang::ready() ) {
			return $columns;
		}
		$columns[ self::COL_LANG ]  = __( 'Language', 'catcode-polylang-multilingual-shop-for-woocommerce' );
		$columns[ self::COL_GROUP ] = __( 'Translation group', 'catcode-polylang-multilingual-shop-for-woocommerce' );
		return $columns;
	}

	/**
	 * Every language, whatever language filter the admin bar has.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<string,mixed>
	 */
	public function export_all_languages( $args ) {
		if ( is_array( $args ) && Lang::ready() ) {
			$args['lang'] = '';
		}
		return $args;
	}

	/**
	 * Translation rows go out without the codes they share with the default language.
	 *
	 * @param array<string,mixed> $row     Row.
	 * @param \WC_Product         $product Product.
	 * @return array<string,mixed>
	 */
	public function export_row( $row, $product ) {
		if ( ! is_array( $row ) || ! $product instanceof \WC_Product || ! Lang::ready() ) {
			return $row;
		}
		$source = self::default_member( $product );
		if ( ! $source ) {
			return $row;
		}
		if ( isset( $row['sku'] ) && '' !== (string) $row['sku'] && $source->get_sku( 'edit' ) === $product->get_sku( 'edit' ) ) {
			$row['sku'] = '';
		}
		if ( isset( $row['global_unique_id'] ) && '' !== (string) $row['global_unique_id'] && is_callable( array( $source, 'get_global_unique_id' ) ) && $source->get_global_unique_id( 'edit' ) === $product->get_global_unique_id( 'edit' ) ) {
			$row['global_unique_id'] = '';
		}
		return $row;
	}

	/** The same product (or variation) in the default language, when $product is a translation of it. */
	private static function default_member( \WC_Product $product ): ?\WC_Product {
		$default = Lang::default();
		if ( $product->is_type( 'variation' ) ) {
			if ( Lang::post_lang( $product->get_parent_id() ) === $default ) {
				return null;
			}
			$id = Variations::in( $product->get_id(), $default );
		} else {
			if ( Lang::post_lang( $product->get_id() ) === $default ) {
				return null;
			}
			$id = Lang::post_in( $product->get_id(), $default );
		}
		$found = $id > 0 ? wc_get_product( $id ) : null;
		return $found ? $found : null;
	}

	/**
	 * @param string      $value   Value.
	 * @param \WC_Product $product Product.
	 */
	public function export_lang( $value, $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return $value;
		}
		$id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		return Lang::post_lang( (int) $id );
	}

	/**
	 * @param string      $value   Value.
	 * @param \WC_Product $product Product.
	 */
	public function export_group( $value, $product ) {
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variation' ) ) {
			return '';
		}
		$id    = $product->get_id();
		$terms = wp_get_object_terms( $id, 'post_translations', array( 'fields' => 'names' ) );
		if ( is_array( $terms ) && ! empty( $terms[0] ) ) {
			return (string) $terms[0];
		}
		$imported = (string) get_post_meta( $id, self::META_GROUP, true );
		return '' !== $imported ? $imported : 'product-' . $id;
	}

	/**
	 * @param array<string,string> $options Mapping options.
	 * @return array<string,string>
	 */
	public function import_options( $options ) {
		if ( ! is_array( $options ) ) {
			return $options;
		}
		$options[ self::COL_LANG ]  = __( 'Language (Polylang)', 'catcode-polylang-multilingual-shop-for-woocommerce' );
		$options[ self::COL_GROUP ] = __( 'Translation group (Polylang)', 'catcode-polylang-multilingual-shop-for-woocommerce' );
		return $options;
	}

	/**
	 * Map the columns automatically when the file came from our export (any admin language).
	 *
	 * @param array<string,string> $columns Header => field.
	 * @return array<string,string>
	 */
	public function import_defaults( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		$columns['Language']          = self::COL_LANG;
		$columns['Translation group'] = self::COL_GROUP;
		$columns[ __( 'Language', 'catcode-polylang-multilingual-shop-for-woocommerce' ) ]          = self::COL_LANG;
		$columns[ __( 'Translation group', 'catcode-polylang-multilingual-shop-for-woocommerce' ) ] = self::COL_GROUP;
		return $columns;
	}

	/**
	 * A file with our language column carries every language version itself: nothing
	 * may be pushed to (or created in) the other languages while it is imported —
	 * not even by the saves WooCommerce's importer makes between rows (the parent of
	 * a variation is saved before the variation). The pause lasts for the whole
	 * import request.
	 *
	 * @param array<int,string> $row         Raw row.
	 * @param array<int,string> $mapped_keys Field of each column.
	 */
	public function import_started( $row, $mapped_keys ): void {
		if ( $this->import_mode || ! is_array( $mapped_keys ) || ! in_array( self::COL_LANG, $mapped_keys, true ) ) {
			return;
		}
		$this->import_mode = true;
		Sync::pause();
	}

	/**
	 * Same guard when the importer was built without parsing through the hook above.
	 *
	 * @param \WC_Product          $object Product about to be saved.
	 * @param array<string,mixed> $data   Row.
	 * @return \WC_Product
	 */
	public function before_insert( $object, $data ) {
		if ( is_array( $data ) && ! empty( $data[ self::COL_LANG ] ) && ! $this->import_mode ) {
			$this->import_mode = true;
			Sync::pause();
		}
		// A variation that began as an import placeholder (a simple product) got the default
		// language from Polylang; variations have none.
		if ( $object instanceof \WC_Product && $object->is_type( 'variation' ) && $object->get_id() > 0 ) {
			wp_delete_object_term_relationships( $object->get_id(), array( 'language', 'post_translations' ) );
		}
		// The language goes on before the importer's save: Polylang keeps the terms of a
		// post in the post's language, and a placeholder post starts in the default one.
		if ( $object instanceof \WC_Product && ! $object->is_type( 'variation' ) && $object->get_id() > 0 && is_array( $data ) ) {
			$lang = isset( $data[ self::COL_LANG ] ) ? sanitize_key( (string) $data[ self::COL_LANG ] ) : '';
			if ( Lang::is_valid( $lang ) ) {
				pll_set_post_language( $object->get_id(), $lang );
			}
		}
		return $object;
	}

	/**
	 * @param \WC_Product          $object Saved product.
	 * @param array<string,mixed> $data   Row.
	 */
	public function after_insert( $object, $data ): void {
		$this->link( $object, $data );
	}

	/**
	 * @param \WC_Product         $object Saved product.
	 * @param array<string,mixed> $data   Row.
	 */
	private function link( $object, $data ): void {
		if ( ! $object instanceof \WC_Product || ! is_array( $data ) || ! Lang::ready() ) {
			return;
		}
		if ( $object->is_type( 'variation' ) ) {
			$lang = Lang::post_lang( $object->get_parent_id() );
			if ( '' !== $lang && ! empty( $data[ self::COL_LANG ] ) ) {
				// The importer finds attribute terms by name, in whatever language comes first.
				$object->set_attributes( Fields::variation_attributes( (array) $object->get_attributes( 'edit' ), (array) $object->get_attributes( 'edit' ), $lang, true ) );
				$object->save();
			}
			$this->link_variations( $object->get_parent_id() );
			return;
		}
		$lang  = isset( $data[ self::COL_LANG ] ) ? sanitize_key( (string) $data[ self::COL_LANG ] ) : '';
		$group = isset( $data[ self::COL_GROUP ] ) ? sanitize_text_field( (string) $data[ self::COL_GROUP ] ) : '';
		if ( ! Lang::is_valid( $lang ) ) {
			return;
		}
		$id = $object->get_id();
		pll_set_post_language( $id, $lang );

		// Categories, tags and attribute values were matched by name — make sure they are the ones of this language.
		$object->set_category_ids( Terms::translate_ids( (array) $object->get_category_ids( 'edit' ), 'product_cat', $lang ) );
		$object->set_tag_ids( Terms::translate_ids( (array) $object->get_tag_ids( 'edit' ), 'product_tag', $lang ) );
		$object->set_attributes( Fields::product_attributes( $object, $object, $lang, true ) );
		$object->set_default_attributes( Fields::variation_attributes( (array) $object->get_default_attributes( 'edit' ), array(), $lang, true ) );
		$object->save();
		// WooCommerce writes terms only when the value changed on the object; write them
		// again now that the post is in its language.
		wp_set_object_terms( $id, array_map( 'intval', (array) $object->get_category_ids( 'edit' ) ), 'product_cat' );
		wp_set_object_terms( $id, array_map( 'intval', (array) $object->get_tag_ids( 'edit' ) ), 'product_tag' );
		foreach ( (array) $object->get_attributes( 'edit' ) as $attr ) {
			if ( $attr instanceof \WC_Product_Attribute && $attr->is_taxonomy() ) {
				wp_set_object_terms( $id, array_map( 'intval', (array) $attr->get_options() ), $attr->get_name() );
			}
		}
		wc_delete_product_transients( $id );

		if ( '' === $group ) {
			return;
		}
		update_post_meta( $id, self::META_GROUP, $group );

		$members = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'any',
				'posts_per_page'   => 50,
				'fields'           => 'ids',
				'lang'             => '',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- import only.
				'meta_query'       => array(
					array(
						'key'   => self::META_GROUP,
						'value' => $group,
					),
				),
			)
		);
		$translations = array();
		foreach ( $members as $member ) {
			$member_lang = Lang::post_lang( (int) $member );
			if ( '' !== $member_lang ) {
				$translations[ $member_lang ] = (int) $member;
			}
		}
		$translations[ $lang ] = $id;
		if ( count( $translations ) > 1 ) {
			pll_save_post_translations( $translations );
			self::take_codes( $object );
			$this->link_variations( $id );
		}
	}

	/** A translation imported without SKU / GTIN takes them from the default-language product. */
	private static function take_codes( \WC_Product $product ): void {
		if ( ! Fields::shares( 'sku' ) || '' !== $product->get_sku( 'edit' ) ) {
			return;
		}
		$source = self::default_member( $product );
		if ( ! $source ) {
			return;
		}
		Sync::pause();
		try {
			Fields::copy_codes( $source, $product );
			$product->save();
		} finally {
			Sync::resume();
		}
	}

	/** Variations of the language versions of $product_id find their counterparts by attributes. */
	private function link_variations( int $product_id ): void {
		$group = Lang::post_translations( $product_id );
		if ( count( $group ) < 2 ) {
			return;
		}
		foreach ( $group as $member ) {
			// Children lists are cached; rows of this very request may have added some.
			wc_delete_product_transients( (int) $member );
		}
		$default = Lang::default();
		$source  = isset( $group[ $default ] ) ? $group[ $default ] : (int) reset( $group );
		foreach ( $group as $lang => $id ) {
			if ( $id === $source ) {
				continue;
			}
			Variations::link_existing( $source, $id, (string) $lang );
			$parent = wc_get_product( $id );
			foreach ( $parent ? $parent->get_children() : array() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child ) {
					self::take_codes( $child );
				}
			}
		}
	}
}
