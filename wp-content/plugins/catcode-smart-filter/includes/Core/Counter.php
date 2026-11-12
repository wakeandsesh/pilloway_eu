<?php
/**
 * How many products sit behind every value the shopper can still click.
 *
 * Two rules make the numbers mean what people read into them:
 *
 *  1. A facet's own selection is released when counting that facet. Tick "Apple"
 *     and the brand list still shows Samsung with its count, because the question
 *     a shopper is asking there is "what if I picked Samsung instead" — not "how
 *     many Samsungs are also Apples" (zero, always).
 *  2. Every other facet's selection stays applied, so the numbers are reachable.
 *     A count that leads to an empty page is worse than no count at all.
 *
 * The batching is what keeps this cheap. Facets the shopper has not touched share
 * one base set, so they are counted in a single grouped query over the index;
 * only the facets carrying a selection need one query each. Twenty facets with
 * two of them selected cost three queries, not twenty-one.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Core;

defined( 'ABSPATH' ) || exit;

class Counter {

	/**
	 * @param array<int,object>               $facets
	 * @param array<string,array<int,string>> $selection
	 * @param array<string,mixed>             $scope
	 *
	 * @return array{total:int,values:array<string,array<string,int>>,price:array{min:float,max:float}}
	 */
	public static function counts( array $facets, array $selection, array $scope = array() ): array {
		global $wpdb;

		$key = self::cache_key( $facets, $selection, $scope );

		if ( '' !== $key ) {
			$cached = get_transient( $key );

			if ( is_array( $cached ) && isset( $cached['total'], $cached['values'] ) ) {
				return $cached;
			}
		}

		$out = array(
			'total'  => Query::total( $selection, $scope ),
			'values' => array(),
			'price'  => Query::price_bounds( $selection, $scope ),
		);

		$index    = Repository::index_table();
		$values_t = Repository::values_table();

		$untouched = array();

		foreach ( $facets as $facet ) {
			if ( ! isset( $selection[ $facet->facet_key ] ) ) {
				$untouched[] = (int) $facet->facet_id;
			}
		}

		if ( $untouched ) {
			$base = Query::base_sql( $selection, $scope );
			$in   = implode( ',', $untouched );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$rows = (array) $wpdb->get_results(
				"SELECT cfi.facet_id, cfv.value_slug, COUNT(DISTINCT cfi.product_id) AS total
					FROM {$index} cfi
					INNER JOIN {$values_t} cfv ON cfv.value_id = cfi.value_id
					INNER JOIN ({$base}) cfb ON cfb.product_id = cfi.product_id
					WHERE cfi.facet_id IN ({$in})
					GROUP BY cfi.facet_id, cfv.value_slug"
			);

			$by_id = array();

			foreach ( $facets as $facet ) {
				$by_id[ (int) $facet->facet_id ] = $facet->facet_key;
			}

			foreach ( $rows as $row ) {
				$facet_key = $by_id[ (int) $row->facet_id ] ?? '';

				if ( '' !== $facet_key ) {
					$out['values'][ $facet_key ][ (string) $row->value_slug ] = (int) $row->total;
				}
			}
		}

		foreach ( $facets as $facet ) {
			if ( ! isset( $selection[ $facet->facet_key ] ) ) {
				continue;
			}

			$released = $selection;

			unset( $released[ $facet->facet_key ] );

			$base = Query::base_sql( $released, $scope );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT cfv.value_slug, COUNT(DISTINCT cfi.product_id) AS total
						FROM {$index} cfi
						INNER JOIN {$values_t} cfv ON cfv.value_id = cfi.value_id
						INNER JOIN ({$base}) cfb ON cfb.product_id = cfi.product_id
						WHERE cfi.facet_id = %d
						GROUP BY cfv.value_slug",
					(int) $facet->facet_id
				)
			);

			foreach ( $rows as $row ) {
				$out['values'][ $facet->facet_key ][ (string) $row->value_slug ] = (int) $row->total;
			}
		}

		if ( Settings::is_on( 'facet_stock' ) ) {
			$out['values']['s'] = self::stock_counts( $selection, $scope );
		}

		if ( Settings::is_on( 'facet_rating' ) ) {
			$out['values']['r'] = self::rating_counts( $selection, $scope );
		}

		if ( Settings::is_on( 'facet_onsale' ) ) {
			$out['values']['o'] = self::onsale_counts( $selection, $scope );
		}

		if ( '' !== $key ) {
			set_transient( $key, $out, Settings::cache_ttl() );
		}

		return $out;
	}

	/**
	 * @return array<string,int>
	 */
	private static function stock_counts( array $selection, array $scope ): array {
		global $wpdb;

		unset( $selection['s'] );

		$base   = Query::base_sql( $selection, $scope );
		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$row = $wpdb->get_row(
			"SELECT
					SUM(l.stock_status = 'instock') AS in_stock,
					SUM(l.stock_status <> 'instock') AS out_stock
				FROM {$lookup} l
				INNER JOIN ({$base}) cfb ON cfb.product_id = l.product_id"
		);

		return array(
			'in'  => $row ? (int) $row->in_stock : 0,
			'out' => $row ? (int) $row->out_stock : 0,
		);
	}

	/**
	 * "4 stars and up" is cumulative, so the per-product averages are rolled up
	 * here — GROUP BY cannot express both at once.
	 *
	 * @return array<string,int>
	 */
	private static function rating_counts( array $selection, array $scope ): array {
		global $wpdb;

		unset( $selection['r'] );

		$base   = Query::base_sql( $selection, $scope );
		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = (array) $wpdb->get_results(
			"SELECT FLOOR(l.average_rating) AS stars, COUNT(*) AS total
				FROM {$lookup} l
				INNER JOIN ({$base}) cfb ON cfb.product_id = l.product_id
				WHERE l.average_rating > 0
				GROUP BY FLOOR(l.average_rating)"
		);

		$per_star = array();

		foreach ( $rows as $row ) {
			$stars = (int) $row->stars;

			if ( $stars >= 1 && $stars <= 5 ) {
				$per_star[ $stars ] = (int) $row->total;
			}
		}

		$out = array();

		for ( $stars = 1; $stars <= 5; $stars++ ) {
			$total = 0;

			for ( $above = $stars; $above <= 5; $above++ ) {
				$total += isset( $per_star[ $above ] ) ? $per_star[ $above ] : 0;
			}

			if ( $total > 0 ) {
				$out[ (string) $stars ] = $total;
			}
		}

		return $out;
	}

	/** @return array<string,int> */
	private static function onsale_counts( array $selection, array $scope ): array {
		global $wpdb;

		unset( $selection['o'] );
		$base = Query::base_sql( $selection, $scope );
		$ids  = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();
		$ids  = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );

		if ( ! $ids ) {
			return array( '1' => 0 );
		}

		$in = implode( ',', $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- IDs are normalized to positive integers above.
		$count = $wpdb->get_var( "SELECT COUNT(DISTINCT cfb.product_id) FROM ({$base}) cfb WHERE cfb.product_id IN ({$in})" );

		return array( '1' => (int) $count );
	}

	/**
	 * The cache key carries everything that can change an answer: the catalogue
	 * version (bumped by any index write), the archive scope, the language and the
	 * currency. Leaving currency out would serve a EUR visitor the UAH counts on a
	 * multi-currency shop.
	 */
	/**
	 * @param array<int,object> $facets
	 */
	private static function cache_key( array $facets, array $selection, array $scope ): string {
		if ( Settings::cache_ttl() < 1 ) {
			return '';
		}

		// The facet set belongs in the key. A cached entry built for one set of facets
		// is not an answer for another, and the two differ routinely: the free tier
		// renders the first three while the admin screen counts them all, and a newly
		// imported facet would otherwise stay countless until the cache expired.
		$ids = array();

		foreach ( $facets as $facet ) {
			$ids[] = (int) $facet->facet_id;
		}

		sort( $ids );

		$parts = array(
			'v' . Repository::version(),
			'f' . implode( '.', $ids ),
			'b' . implode( '.', array_map( static function ( $setting ) {
				return Settings::is_on( $setting ) ? '1' : '0';
			}, array( 'facet_price', 'facet_stock', 'facet_rating', 'facet_onsale', 'facet_category' ) ) ),
			'sc' . wp_json_encode( $scope ),
			'l' . Query::current_language(),
			'c' . ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' ),
			Params::encode( $selection ),
		);

		return 'catcode_sf_c_' . md5( implode( '|', $parts ) );
	}
}
