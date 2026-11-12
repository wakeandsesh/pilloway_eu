<?php
/**
 * Contains the query functions for alter the WooCommerce frontend queries.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Query class.
 */
class WCPBC_Product_Meta_Query {

	/**
	 * Pricing zone.
	 *
	 * @var WCPBC_Pricing_Zone
	 */
	protected $zone;

	/**
	 * Constructor.
	 *
	 * @param WCPBC_Pricing_Zone $zone Pricing zone instance.
	 */
	public function __construct( $zone ) {

		if ( ! is_a( $zone, 'WCPBC_Pricing_Zone' ) ) {
			wc_doing_it_wrong( __FUNCTION__, __( '"zone" argument must be a WCPBC_Pricing_Zone instance.', 'woocommerce-product-price-based-on-countries' ), '4.0' );
			return false;
		}

		$this->zone = $zone;
	}

	/**
	 * Returns the Min Max price query to use it on replacements.
	 *
	 * @param string $context Query context. Accepts 'filter' or 'min_max'.
	 * @return string
	 */
	public function get_min_max_price_query( $context = 'filter' ) {
		global $wpdb;

		$fields        = [];
		$fields['min'] = $wpdb->prepare(
			'IFNULL(manual_price_query.min_price, product_meta_lookup.min_price * (%s+0))',
			$this->zone->get_exchange_rate()
		);
		$fields['max'] = $wpdb->prepare(
			'IFNULL(manual_price_query.max_price, product_meta_lookup.max_price * (%s+0))',
			$this->zone->get_exchange_rate()
		);

		if ( 'filter' !== $context ) {
			$fields['min'] = "min({$fields['min']})";
			$fields['max'] = "max({$fields['max']})";
		} else {
			$fields[] = 'product_meta_lookup.product_id';
		}

		$fields['min'] = $fields['min'] . ' AS min_price';
		$fields['max'] = $fields['max'] . ' AS max_price';

		return 'SELECT ' . implode( ', ', $fields ) . $wpdb->prepare(
			" FROM `{$wpdb->wc_product_meta_lookup}` product_meta_lookup
			LEFT JOIN (
			SELECT
				meta.post_id,
				min(meta.meta_value+0) AS min_price,
				max(meta.meta_value+0) AS max_price
			FROM `{$wpdb->postmeta}` meta
			INNER JOIN `{$wpdb->postmeta}` meta2
				ON meta.post_id = meta2.post_id
				AND meta2.meta_key = %s
				AND meta2.meta_value = 'manual'
			WHERE meta.meta_key = %s
			GROUP BY meta.post_id) AS manual_price_query ON manual_price_query.post_id = product_meta_lookup.product_id
			",
			$this->zone->get_postmetakey( '_price_method' ),
			$this->zone->get_postmetakey( '_price' )
		);
	}

	/**
	 * Returns an array containing the IDs of the products that are on sale.
	 *
	 * @return array
	 */
	public function get_on_sale_product_ids() {

		// Manual price product IDs.

		$meta_query = [
			'key'   => $this->zone->get_postmetakey( '_price_method' ),
			'value' => 'manual',
		];

		$post_args = [
			'cache_results'    => false,
			'posts_per_page'   => -1,
			'suppress_filters' => false,
			'fields'           => 'id=>parent',
			'post_type'        => [ 'product', 'product_variation' ],
			'post_status'      => 'publish',
			'meta_query'       => [ $meta_query ], // phpcs:ignore
		];

		$product_visibility_term_ids = wc_get_product_visibility_term_ids();

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && $product_visibility_term_ids['outofstock'] ) {
			$post_args['tax_query'] = [ // phpcs:ignore
				[
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => [ $product_visibility_term_ids['outofstock'] ],
					'operator' => 'NOT IN',
				],
			];
		}

		add_filter( 'posts_clauses', [ $this, 'manual_price_on_sale_clauses' ] );

		$post_ids = get_posts( $post_args );

		remove_filter( 'posts_clauses', [ $this, 'manual_price_on_sale_clauses' ] );

		$product_ids = array_keys( $post_ids );
		$parent_ids  = array_values( array_filter( $post_ids ) );

		$on_sale_product_ids = array_merge( $product_ids, $parent_ids );

		// Exchange rate price product IDs.

		$meta_query['compare']   = 'NOT EXISTS';
		$post_args['meta_query'] = [ $meta_query ]; // phpcs:ignore

		add_filter( 'posts_clauses', [ $this, 'exchage_rate_price_on_sale_clauses' ] );

		$post_ids = get_posts( $post_args );

		remove_filter( 'posts_clauses', [ $this, 'exchage_rate_price_on_sale_clauses' ] );

		$product_ids = array_keys( $post_ids );
		$parent_ids  = array_values( array_filter( $post_ids ) );

		$on_sale_product_ids = array_merge( $on_sale_product_ids, $product_ids, $parent_ids );

		return array_unique( $on_sale_product_ids );
	}

	/**
	 * Add on sale clauses.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function manual_price_on_sale_clauses( $args ) {
		global $wpdb;

		$args['join'] .= $wpdb->prepare(
			" INNER JOIN {$wpdb->postmeta} meta__price ON {$wpdb->posts}.ID = meta__price.post_id AND meta__price.meta_key = %s
			INNER JOIN {$wpdb->postmeta} meta__sale_price ON {$wpdb->posts}.ID = meta__sale_price.post_id AND meta__sale_price.meta_key = %s ",
			$this->zone->get_postmetakey( '_price' ),
			$this->zone->get_postmetakey( '_sale_price' )
		);

		$args['where'] .= " AND (meta__price.meta_value != '' AND meta__price.meta_value = meta__sale_price.meta_value) ";
		return $args;
	}

	/**
	 * Exchage rate price on sale clauses.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function exchage_rate_price_on_sale_clauses( $args ) {
		global $wpdb;
		$pos            = strpos( $args['join'], ' LEFT JOIN ' );
		$args['join']   = substr( $args['join'], 0, $pos ) . "INNER JOIN {$wpdb->wc_product_meta_lookup} AS lookup ON {$wpdb->posts}.ID = lookup.product_id " . substr( $args['join'], $pos );
		$args['where'] .= ' AND (lookup.onsale = 1) ';
		return $args;
	}
}
