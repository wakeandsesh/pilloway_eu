<?php
/**
 * The selection turned into SQL.
 *
 * Every condition is an EXISTS or a scalar sub-query on the product row, and
 * nothing joins the posts table. That is deliberate: WooCommerce's own layered
 * nav adds a term-relationship join per attribute, which multiplies rows, needs
 * DISTINCT to survive it, and turns a five-facet selection into a five-way
 * fan-out that MySQL sorts on disk. With EXISTS the row count never grows, each
 * sub-query stops at the first hit, and the plan does not change with how much
 * the shopper selected.
 *
 * It also means the module never rewrites the FROM clause of the main query — it
 * only appends to WHERE through `posts_clauses`, so sorting, pagination and every
 * other plugin's filters keep working.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Core;

defined( 'ABSPATH' ) || exit;

class Query {

	/** The language of the current archive, including Polylang directory URLs. */
	public static function current_language(): string {
		$available = function_exists( 'pll_languages_list' ) ? pll_languages_list( array( 'fields' => 'slug' ) ) : array();
		$candidates = array( get_query_var( 'lang' ) );
		if ( isset( $_GET['lang'] ) && is_string( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only language selector.
			$candidates[] = sanitize_key( wp_unslash( $_GET['lang'] ) );
		}
		foreach ( $candidates as $candidate ) {
			$candidate = is_string( $candidate ) ? sanitize_key( $candidate ) : '';
			if ( '' !== $candidate && in_array( $candidate, (array) $available, true ) ) {
				return $candidate;
			}
		}
		$lang = function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : '';
		return is_string( $lang ) ? $lang : '';
	}

	/**
	 * @param array<string,array<int,string>> $selection
	 */
	public static function where( array $selection ): string {
		global $wpdb;

		$parts = array();

		foreach ( $selection as $key => $values ) {
			switch ( $key ) {
				case 'p':
					$condition = self::price_condition( (string) ( $values[0] ?? '' ) );
					break;

				case 's':
					$condition = self::stock_condition( (string) ( $values[0] ?? '' ) );
					break;

				case 'r':
					$condition = self::rating_condition( (int) ( $values[0] ?? 0 ) );
					break;

				case 'o':
					$condition = self::onsale_condition();
					break;

				case 'c':
					$condition = self::term_condition( 'product_cat', $values );
					break;

				default:
					$condition = self::facet_condition( $key, $values );
					break;
			}

			if ( '' !== $condition ) {
				$parts[] = $condition;
			}
		}

		return $parts ? implode( ' AND ', $parts ) : '';
	}

	/**
	 * A facet condition. `or` (the default) means "any of the ticked values",
	 * which is how a shopper reads a checkbox list. `and` means "all of them" and
	 * is what a tag-like facet needs — one EXISTS per value.
	 *
	 * @param array<int,string> $values
	 */
	private static function facet_condition( string $facet_key, array $values ): string {
		global $wpdb;

		$facet = Facets::by_key( $facet_key );

		if ( ! $facet || ! $values ) {
			return '';
		}

		$index    = Repository::index_table();
		$values_t = Repository::values_table();
		$facet_id = (int) $facet->facet_id;

		$first = (string) $values[0];

		if ( Params::is_range( $first ) ) {
			$range = Params::range_parts( $first );

			if ( ! $range ) {
				return '';
			}

			return $wpdb->prepare(
				"EXISTS (SELECT 1 FROM {$index} cfi
					WHERE cfi.product_id = {$wpdb->posts}.ID
						AND cfi.facet_id = %d
						AND cfi.num_value BETWEEN %f AND %f)",
				$facet_id,
				$range['from'],
				$range['to']
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );

		if ( 'and' === $facet->logic ) {
			$parts = array();

			foreach ( $values as $slug ) {
				$parts[] = $wpdb->prepare(
					"EXISTS (SELECT 1 FROM {$index} cfi
						INNER JOIN {$values_t} cfv ON cfv.value_id = cfi.value_id
						WHERE cfi.product_id = {$wpdb->posts}.ID AND cfi.facet_id = %d AND cfv.value_slug = %s)",
					$facet_id,
					$slug
				);
			}

			return '(' . implode( ' AND ', $parts ) . ')';
		}

		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$index} cfi
				INNER JOIN {$values_t} cfv ON cfv.value_id = cfi.value_id
				WHERE cfi.product_id = {$wpdb->posts}.ID AND cfi.facet_id = %d AND cfv.value_slug IN ({$placeholders}))",
			array_merge( array( $facet_id ), $values )
		);
	}

	/**
	 * Price, read from WooCommerce's own lookup table so the comparison happens on
	 * the number the shopper actually sees — sale price included, tax display
	 * respected by Woo itself.
	 */
	private static function price_condition( string $range ): string {
		global $wpdb;

		$parts = Params::range_parts( $range );

		if ( ! $parts ) {
			return '';
		}

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$lookup} cfp
				WHERE cfp.product_id = {$wpdb->posts}.ID
					AND cfp.min_price <= %f AND cfp.max_price >= %f)",
			$parts['to'],
			$parts['from']
		);
	}

	private static function stock_condition( string $value ): string {
		global $wpdb;

		if ( ! in_array( $value, array( 'in', 'out' ), true ) ) {
			return '';
		}

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$lookup} cfs WHERE cfs.product_id = {$wpdb->posts}.ID AND cfs.stock_status = %s)",
			'in' === $value ? 'instock' : 'outofstock'
		);
	}

	private static function rating_condition( int $minimum ): string {
		global $wpdb;

		if ( $minimum < 1 || $minimum > 5 ) {
			return '';
		}

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$lookup} cfr WHERE cfr.product_id = {$wpdb->posts}.ID AND cfr.average_rating >= %f)",
			(float) $minimum
		);
	}

	private static function onsale_condition(): string {
		global $wpdb;

		$ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();

		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );

		if ( ! $ids ) {
			return '1=0';
		}

		return "{$wpdb->posts}.ID IN (" . implode( ',', $ids ) . ')';
	}

	/**
	 * @param array<int,string> $term_ids
	 */
	private static function term_condition( string $taxonomy, array $term_ids ): string {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );

		if ( ! $ids ) {
			return '';
		}

		$in = implode( ',', $ids );

		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$wpdb->term_relationships} cftr
				INNER JOIN {$wpdb->term_taxonomy} cftt ON cftt.term_taxonomy_id = cftr.term_taxonomy_id
				WHERE cftr.object_id = {$wpdb->posts}.ID AND cftt.taxonomy = %s AND cftt.term_id IN ({$in}))",
			$taxonomy
		);
	}

	// -----------------------------------------------------------------------
	// Base set — what the counters count over
	// -----------------------------------------------------------------------

	/**
	 * The product set as SQL, ready to be used as a derived table.
	 *
	 * The counters need this rather than a list of ids: on a 40 000-product
	 * archive, pulling every id into PHP just to send it back as an IN (…) list is
	 * what makes filters feel slow, and MySQL plans the join far better than it
	 * plans a 40 000-element IN.
	 *
	 * @param array<string,array<int,string>> $selection
	 * @param array<string,mixed>             $scope archive scope: taxonomy + term id, or search
	 */
	public static function base_sql( array $selection, array $scope = array() ): string {
		global $wpdb;

		$sql = "SELECT {$wpdb->posts}.ID AS product_id FROM {$wpdb->posts}";

		$where = array(
			"{$wpdb->posts}.post_type = 'product'",
			"{$wpdb->posts}.post_status = 'publish'",
		);

		// The index contains every translation. Keep counter queries in the same
		// Polylang language as the archive, just like Polylang keeps the product
		// loop itself in that language.
		$lang = self::current_language();
		if ( is_string( $lang ) && '' !== $lang && taxonomy_exists( 'language' ) ) {
			$where[] = $wpdb->prepare(
				"EXISTS (SELECT 1 FROM {$wpdb->term_relationships} cflr
					INNER JOIN {$wpdb->term_taxonomy} cflt ON cflt.term_taxonomy_id = cflr.term_taxonomy_id
					INNER JOIN {$wpdb->terms} cfl ON cfl.term_id = cflt.term_id
					WHERE cflr.object_id = {$wpdb->posts}.ID AND cflt.taxonomy = 'language' AND cfl.slug = %s)",
				$lang
			);
		}

		if ( ! empty( $scope['taxonomy'] ) && ! empty( $scope['term_id'] ) ) {
			$where[] = self::scope_condition( (string) $scope['taxonomy'], (int) $scope['term_id'] );
		}

		if ( ! empty( $scope['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $scope['search'] ) . '%';
			$where[] = $wpdb->prepare( "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s)", $like, $like );
		}

		// Products hidden from the catalogue must not be counted — otherwise the
		// sidebar promises items the listing will never show.
		$where[] = self::visibility_condition();

		$filter = self::where( $selection );

		if ( '' !== $filter ) {
			$where[] = $filter;
		}

		return $sql . ' WHERE ' . implode( ' AND ', $where );
	}

	private static function scope_condition( string $taxonomy, int $term_id ): string {
		global $wpdb;

		// Children count too: a category page lists the whole subtree, so the
		// counters have to agree with it.
		$term_ids = array( $term_id );

		$children = get_term_children( $term_id, $taxonomy );

		if ( ! is_wp_error( $children ) && $children ) {
			$term_ids = array_merge( $term_ids, array_map( 'intval', $children ) );
		}

		$in = implode( ',', array_unique( array_map( 'intval', $term_ids ) ) );

		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$wpdb->term_relationships} cfsr
				INNER JOIN {$wpdb->term_taxonomy} cfst ON cfst.term_taxonomy_id = cfsr.term_taxonomy_id
				WHERE cfsr.object_id = {$wpdb->posts}.ID AND cfst.taxonomy = %s AND cfst.term_id IN ({$in}))",
			$taxonomy
		);
	}

	private static function visibility_condition(): string {
		global $wpdb;

		$term = get_term_by( 'name', 'exclude-from-catalog', 'product_visibility' );

		if ( ! $term ) {
			return '1=1';
		}

		return $wpdb->prepare(
			"NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} cfvr
				WHERE cfvr.object_id = {$wpdb->posts}.ID AND cfvr.term_taxonomy_id = %d)",
			(int) $term->term_taxonomy_id
		);
	}

	/**
	 * @param array<string,array<int,string>> $selection
	 * @param array<string,mixed>             $scope
	 */
	public static function total( array $selection, array $scope = array() ): int {
		global $wpdb;

		$base = self::base_sql( $selection, $scope );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$base}) cfb" );
	}

	/**
	 * Lowest and highest price the slider should span, under the rest of the
	 * selection with the price part itself released — pick a brand and the handles
	 * narrow to that brand instead of staying pinned to the whole archive.
	 *
	 * @return array{min:float,max:float}
	 */
	public static function price_bounds( array $selection, array $scope = array() ): array {
		global $wpdb;

		unset( $selection['p'] );

		$base   = self::base_sql( $selection, $scope );
		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$row = $wpdb->get_row(
			"SELECT MIN(l.min_price) AS min_price, MAX(l.max_price) AS max_price
				FROM {$lookup} l
				INNER JOIN ({$base}) cfb ON cfb.product_id = l.product_id"
		);

		return array(
			'min' => $row ? (float) floor( (float) $row->min_price ) : 0.0,
			'max' => $row ? (float) ceil( (float) $row->max_price ) : 0.0,
		);
	}
}
