<?php
/**
 * Variations are not translated posts in Polylang — each translated variable
 * product has its own children. A shared key in variation meta ("group") tells
 * which child in each language is the same variation, so price, stock and SKU
 * can follow it and the cart can move to it when the shopper changes language.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Product;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class Variations {

	public const META = '_catcode_plms_vgroup';

	public static function group_of( int $variation_id ): string {
		return $variation_id > 0 ? (string) get_post_meta( $variation_id, self::META, true ) : '';
	}

	/** The group key of a variation, created on first use. */
	public static function ensure_group( int $variation_id ): string {
		$group = self::group_of( $variation_id );
		if ( '' === $group && $variation_id > 0 ) {
			$group = wp_generate_uuid4();
			update_post_meta( $variation_id, self::META, $group );
		}
		return $group;
	}

	/** Child of $parent_id in the group, 0 when there is none. */
	public static function find( int $parent_id, string $group ): int {
		if ( $parent_id <= 0 || '' === $group ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'        => 'product_variation',
				'post_parent'      => $parent_id,
				'post_status'      => array( 'publish', 'private' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one parent, a handful of children.
				'meta_query'       => array(
					array(
						'key'   => self::META,
						'value' => $group,
					),
				),
			)
		);
		return empty( $ids ) ? 0 : (int) $ids[0];
	}

	/**
	 * The same variation in the other languages.
	 *
	 * @return array<string,int> lang => variation id (without $variation_id).
	 */
	public static function siblings( int $variation_id ): array {
		$parent = (int) wp_get_post_parent_id( $variation_id );
		$group  = self::group_of( $variation_id );
		if ( $parent <= 0 || '' === $group ) {
			return array();
		}
		$out = array();
		foreach ( Lang::other_translations( $parent ) as $lang => $tr_parent ) {
			$id = self::find( $tr_parent, $group );
			if ( $id > 0 && $id !== $variation_id ) {
				$out[ $lang ] = $id;
			}
		}
		return $out;
	}

	/** Counterpart of $variation_id in $lang (the variation itself when already there). */
	public static function in( int $variation_id, string $lang ): int {
		$parent = (int) wp_get_post_parent_id( $variation_id );
		if ( $parent <= 0 ) {
			return 0;
		}
		if ( Lang::post_lang( $parent ) === $lang ) {
			return $variation_id;
		}
		$tr_parent = Lang::post_in( $parent, $lang );
		if ( $tr_parent <= 0 ) {
			return 0;
		}
		return self::find( $tr_parent, self::group_of( $variation_id ) );
	}

	/**
	 * Children of $tr_parent that are not linked yet get linked to the source child
	 * with the same (translated) attribute combination — translations made before
	 * this plugin, or by hand.
	 */
	public static function link_existing( int $src_parent, int $tr_parent, string $lang ): void {
		$src = wc_get_product( $src_parent );
		$tr  = wc_get_product( $tr_parent );
		if ( ! $src || ! $tr || ! $src->is_type( 'variable' ) || ! $tr->is_type( 'variable' ) ) {
			return;
		}
		$free = array();
		foreach ( $tr->get_children() as $child_id ) {
			if ( '' === self::group_of( (int) $child_id ) ) {
				$child = wc_get_product( $child_id );
				if ( $child ) {
					$free[ (int) $child_id ] = self::signature( (array) $child->get_attributes( 'edit' ) );
				}
			}
		}
		if ( empty( $free ) ) {
			return;
		}
		foreach ( $src->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $child ) {
				continue;
			}
			$group = self::group_of( (int) $child_id );
			if ( '' !== $group && self::find( $tr_parent, $group ) > 0 ) {
				continue;
			}
			$want  = self::signature( Fields::variation_attributes( (array) $child->get_attributes( 'edit' ), array(), $lang, true ) );
			$match = array_search( $want, $free, true );
			if ( false !== $match ) {
				update_post_meta( (int) $match, self::META, self::ensure_group( (int) $child_id ) );
				unset( $free[ $match ] );
			}
		}
	}

	/**
	 * Create the counterpart of $src under $tr_parent (in $lang).
	 *
	 * @param string[] $groups Shared groups (see Fields).
	 */
	public static function create_counterpart( \WC_Product_Variation $src, int $tr_parent, string $lang, array $groups ): int {
		$group = self::ensure_group( $src->get_id() );
		$have  = self::find( $tr_parent, $group );
		if ( $have > 0 ) {
			return $have;
		}

		$copy = clone $src;
		$copy->set_id( 0 );
		$copy->set_parent_id( $tr_parent );
		$copy->set_date_created( null );
		$copy->set_slug( '' );
		$copy->set_sku( '' );
		if ( is_callable( array( $copy, 'set_global_unique_id' ) ) ) {
			$copy->set_global_unique_id( '' );
		}
		$copy->delete_meta_data( self::META );
		Fields::copy_variation( $src, $copy, $lang, array_values( array_unique( array_merge( $groups, array( 'media' ) ) ) ), true );
		$copy->update_meta_data( self::META, $group );
		$copy->save();

		if ( in_array( 'sku', $groups, true ) && ( '' !== $src->get_sku( 'edit' ) || ( is_callable( array( $src, 'get_global_unique_id' ) ) && '' !== $src->get_global_unique_id( 'edit' ) ) ) ) {
			// Only now: the uniqueness check needs to see the copy inside the group.
			Fields::copy_codes( $src, $copy );
			$copy->save();
		}

		/**
		 * Fires after a variation was created in a translated product.
		 *
		 * @param int                   $id   New variation ID.
		 * @param \WC_Product_Variation $src  Source variation.
		 * @param string                $lang Language of the new variation's parent.
		 */
		do_action( 'catcode_plms_variation_created', $copy->get_id(), $src, $lang );
		return $copy->get_id();
	}

	/**
	 * Canonical string of an attribute combination.
	 *
	 * @param array<string,string> $attrs Attributes.
	 */
	private static function signature( array $attrs ): string {
		$clean = array();
		foreach ( $attrs as $name => $value ) {
			$clean[ (string) $name ] = strtolower( (string) $value );
		}
		ksort( $clean );
		return (string) wp_json_encode( $clean );
	}
}
