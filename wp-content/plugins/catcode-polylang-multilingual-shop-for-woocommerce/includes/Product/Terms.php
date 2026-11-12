<?php
/**
 * Categories, tags and attribute terms across languages: find the translation of
 * a term, or create it as a copy when it is missing (setting "copy_terms").
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Product;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Core\Registry;
use CatCode\PolylangShop\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Terms {

	/** Term meta copied to a new term translation (category image, order, display type, swatches…). */
	private const COPY_META = array( 'thumbnail_id', 'order', 'display_type' );

	/** @var int Guards against a parent loop in broken term trees. */
	private static $depth = 0;

	public static function may_create(): bool {
		return Settings::is_on( 'copy_terms' );
	}

	/**
	 * @param int[] $ids Term IDs.
	 * @return int[] Their translations into $lang; terms without one are dropped (or created).
	 */
	public static function translate_ids( array $ids, string $taxonomy, string $lang, ?bool $create = null ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$tr = self::translate_id( (int) $id, $taxonomy, $lang, $create );
			if ( $tr > 0 ) {
				$out[] = $tr;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Translation of one term; 0 when there is none and it may not / could not be created. */
	public static function translate_id( int $term_id, string $taxonomy, string $lang, ?bool $create = null ): int {
		if ( $term_id <= 0 ) {
			return 0;
		}
		if ( ! Lang::is_translated_taxonomy( $taxonomy ) ) {
			return $term_id;
		}
		$own = Lang::term_lang( $term_id );
		if ( '' === $own ) {
			// A term from before the store was multilingual: it belongs to the default language.
			$own = Lang::default();
			if ( '' !== $own ) {
				pll_set_term_language( $term_id, $own );
			}
		}
		if ( $own === $lang ) {
			return $term_id;
		}
		$tr = Lang::term_in( $term_id, $lang );
		if ( $tr > 0 ) {
			return $tr;
		}
		if ( null === $create ) {
			$create = self::may_create();
		}
		return $create ? self::create_copy( $term_id, $taxonomy, $lang ) : 0;
	}

	/**
	 * Options of a taxonomy attribute as term IDs. They are IDs once the product was
	 * read from the database; a product built in code (the CSV importer) may still
	 * carry term names — those are looked up, in $lang first.
	 *
	 * @param array<int,int|string> $options Options.
	 * @return int[]
	 */
	public static function option_ids( array $options, string $taxonomy, string $lang ): array {
		$out = array();
		foreach ( $options as $option ) {
			if ( is_int( $option ) || ( is_string( $option ) && ctype_digit( $option ) ) ) {
				$out[] = (int) $option;
				continue;
			}
			$name = trim( (string) $option );
			if ( '' === $name ) {
				continue;
			}
			foreach ( array( $lang, '' ) as $in ) {
				$found = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'name'       => $name,
						'hide_empty' => false,
						'number'     => 1,
						'fields'     => 'ids',
						'lang'       => $in,
					)
				);
				if ( is_array( $found ) && ! empty( $found ) ) {
					$out[] = (int) $found[0];
					break;
				}
			}
		}
		return $out;
	}

	/** Attribute value (term slug) → slug of its translation; '' when there is none. */
	public static function translate_slug( string $slug, string $taxonomy, string $lang, ?bool $create = null ): string {
		if ( '' === $slug || ! taxonomy_exists( $taxonomy ) ) {
			return $slug;
		}
		if ( ! Lang::is_translated_taxonomy( $taxonomy ) ) {
			return $slug;
		}
		$term = self::by_slug( $slug, $taxonomy );
		if ( null === $term ) {
			return '';
		}
		$tr = self::translate_id( (int) $term->term_id, $taxonomy, $lang, $create );
		if ( $tr <= 0 ) {
			return '';
		}
		$tr_term = get_term( $tr, $taxonomy );
		return $tr_term instanceof \WP_Term ? (string) $tr_term->slug : '';
	}

	/** Term by slug in any language (Polylang would otherwise filter by the current one). */
	public static function by_slug( string $slug, string $taxonomy ): ?\WP_Term {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'slug'       => $slug,
				'hide_empty' => false,
				'number'     => 1,
				'lang'       => '',
			)
		);
		if ( is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof \WP_Term ) {
			return $terms[0];
		}
		return null;
	}

	/**
	 * New term in $lang with the same name, the translated parent and the display
	 * meta of the source, linked into the source's translation group.
	 */
	public static function create_copy( int $term_id, string $taxonomy, string $lang ): int {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof \WP_Term || ! Lang::is_valid( $lang ) || ! function_exists( 'pll_insert_term' ) ) {
			return 0;
		}
		if ( self::$depth > 20 ) {
			return 0;
		}
		++self::$depth;
		$parent = 0;
		if ( (int) $term->parent > 0 ) {
			$parent = self::translate_id( (int) $term->parent, $taxonomy, $lang, true );
		}
		--self::$depth;

		$result = pll_insert_term(
			(string) $term->name,
			$taxonomy,
			$lang,
			array(
				'slug'        => sanitize_title( $term->slug . '-' . $lang ),
				'parent'      => $parent,
				'description' => (string) $term->description,
			)
		);
		if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
			return 0;
		}
		$new = (int) $result['term_id'];

		$group = Lang::term_translations( $term_id );
		if ( empty( $group ) ) {
			$group[ Lang::term_lang( $term_id ) ] = $term_id;
		}
		$group[ $lang ] = $new;
		pll_save_term_translations( array_filter( $group ) );

		foreach ( (array) get_term_meta( $term_id ) as $key => $values ) {
			$key = (string) $key;
			if ( ! self::copy_meta_key( $key ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				add_term_meta( $new, $key, maybe_unserialize( $value ) );
			}
		}

		/**
		 * Fires after a missing term translation was created as a copy.
		 *
		 * @param int    $new      New term ID.
		 * @param int    $term_id  Source term ID.
		 * @param string $taxonomy Taxonomy.
		 * @param string $lang     Language of the new term.
		 */
		do_action( 'catcode_plms_term_copied', $new, $term_id, $taxonomy, $lang );
		return $new;
	}

	/**
	 * Writes the categories, tags and attribute terms of the product object straight to
	 * the database. WooCommerce writes only the ones whose value changed on the object,
	 * and a fresh copy is saved once before it has a language — Polylang then swaps its
	 * terms for the default-language ones.
	 */
	public static function write_own( \WC_Product $product ): void {
		$id = $product->get_id();
		if ( $id <= 0 ) {
			return;
		}
		wp_set_object_terms( $id, array_map( 'intval', (array) $product->get_category_ids( 'edit' ) ), 'product_cat' );
		wp_set_object_terms( $id, array_map( 'intval', (array) $product->get_tag_ids( 'edit' ) ), 'product_tag' );
		foreach ( (array) $product->get_attributes( 'edit' ) as $attr ) {
			if ( $attr instanceof \WC_Product_Attribute && $attr->is_taxonomy() ) {
				wp_set_object_terms( $id, array_map( 'intval', (array) $attr->get_options() ), $attr->get_name() );
			}
		}
	}

	/**
	 * Brands and other taxonomies outside the product object: the translation gets the
	 * terms of the source in its language, or the very same terms when the taxonomy is
	 * not translated.
	 */
	public static function copy_extra( int $from, int $to, string $lang ): void {
		foreach ( Registry::extra_taxonomies() as $taxonomy ) {
			$ids = wp_get_object_terms(
				$from,
				$taxonomy,
				array(
					'fields' => 'ids',
					'lang'   => '',
				)
			);
			if ( is_wp_error( $ids ) ) {
				continue;
			}
			$ids = array_map( 'intval', (array) $ids );
			if ( Lang::is_translated_taxonomy( $taxonomy ) ) {
				$ids = self::translate_ids( $ids, $taxonomy, $lang );
			}
			wp_set_object_terms( $to, $ids, $taxonomy );
		}
	}

	private static function copy_meta_key( string $key ): bool {
		if ( in_array( $key, self::COPY_META, true ) ) {
			return true;
		}
		// Attribute swatches and other display data keyed by WooCommerce or its attribute UI.
		return 0 === strpos( $key, 'wc_' ) || 0 === strpos( $key, '_wc_' );
	}
}
