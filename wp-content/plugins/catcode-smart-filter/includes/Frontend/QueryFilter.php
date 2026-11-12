<?php
/**
 * Narrowing the product listing.
 *
 * The core builds its own query exactly as it always does, and this class only
 * appends `AND (…)` to the WHERE through `posts_clauses`. Nothing in the FROM
 * clause is touched, so sorting, pagination, the product-visibility rules and
 * every other plugin's filters keep working, and there is no template override
 * to break on a theme update.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Frontend;

use CatCode\SmartFilter\Core\Query;
use CatCode\SmartFilter\Core\Runtime;
use CatCode\SmartFilter\Core\Settings;

defined( 'ABSPATH' ) || exit;

class QueryFilter {

	public static function init(): void {
		add_filter( 'posts_clauses', array( __CLASS__, 'apply' ), 20, 2 );
	}

	/**
	 * @param array<string,string> $clauses
	 * @param \WP_Query            $query
	 * @return array<string,string>
	 */
	public static function apply( array $clauses, $query ): array {
		if ( ! self::should_filter( $query ) ) {
			return $clauses;
		}

		Runtime::boot();

		if ( ! Runtime::is_active() ) {
			return $clauses;
		}

		$where = Query::where( Runtime::selection() );

		if ( '' === $where ) {
			return $clauses;
		}

		$clauses['where'] .= ' AND (' . $where . ')';

		Runtime::mark_applied( 'applied' );

		return $clauses;
	}

	/**
	 * Only the listing the shopper is filtering may be narrowed.
	 *
	 * One page renders several product queries — the archive, but also "related",
	 * "upsells" and any shortcode block. A cross-sell row that silently obeys the
	 * sidebar reads as a broken theme, so everything except the main query is left
	 * alone. The AJAX endpoint sets its own flag because there `is_main_query()`
	 * is false by construction.
	 *
	 * @param \WP_Query $query
	 */
	private static function should_filter( $query ): bool {
		if ( ! Settings::is_on( 'enabled' ) ) {
			return false;
		}

		if ( ! $query instanceof \WP_Query ) {
			return false;
		}

		if ( $query->get( 'catcode_sf_filtered' ) ) {
			return true;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		if ( ! $query->is_main_query() ) {
			return false;
		}

		$post_type = $query->get( 'post_type' );

		$is_product_query = 'product' === $post_type
			|| ( is_array( $post_type ) && in_array( 'product', $post_type, true ) )
			|| ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) )
			|| ( $query->is_search() && ( 'product' === $post_type ) );

		return (bool) $is_product_query;
	}
}
