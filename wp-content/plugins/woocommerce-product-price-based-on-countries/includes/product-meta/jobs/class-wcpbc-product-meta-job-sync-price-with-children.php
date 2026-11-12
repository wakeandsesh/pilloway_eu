<?php
/**
 * Sync product price with children.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Job_Sync_Price_With_Children class.
 */
class WCPBC_Product_Meta_Job_Sync_Price_With_Children extends WCPBC_Product_Meta_Job {

	/**
	 * Runs the job.
	 */
	public function run_job() {

		$zone = isset( $this->args['zone_id'] ) ? WCPBC_Pricing_Zones::get_zone( $this->args['zone_id'] ) : false;

		if ( ! $zone ) {
			return;
		}

		$product_ids = isset( $this->args['product_ids'] ) && is_array( $this->args['product_ids'] ) ? $this->args['product_ids'] : [];
		$product_ids = $this->filter_variable_products( $product_ids );

		if ( ! $product_ids ) {
			return;
		}

		$this->sync_price( $zone, $product_ids );
	}

	/**
	 * Sync price with children
	 *
	 * @param WCPBC_Pricing_Zone $zone Pricing zone instance.
	 * @param array              $product_ids Array of product Ids to sync.
	 */
	protected function sync_price( $zone, $product_ids ) {
		$in_product_ids = sprintf( '(%s)', implode( ',', $product_ids ) );

		$this->db()->query(
			$this->db()->prepare(
				"INSERT INTO `{$this->table->postmeta}` (post_id, meta_key, meta_value)
				SELECT DISTINCT product_id, %s, 'manual'
				FROM `{$this->table->product_meta_lookup}` product_meta_lookup
				WHERE product_meta_lookup.product_id IN {$in_product_ids}
				AND NOT EXISTS (
					SELECT 1
					FROM `{$this->table->postmeta}` pm
					WHERE pm.post_id = product_meta_lookup.product_id AND pm.meta_key = %s
				)",
				$zone->get_postmetakey( '_price_method' ),
				$zone->get_postmetakey( '_price_method' )
			)
		);

		$this->db()->query(
			$this->db()->prepare(
				"UPDATE `{$this->table->postmeta}` pm SET meta_value = 'manual'
				WHERE pm.post_id IN {$in_product_ids} AND pm.meta_key = %s AND pm.meta_value != 'manual'",
				$zone->get_postmetakey( '_price_method' )
			)
		);

		$this->db()->query(
			$this->db()->prepare(
				"DELETE FROM `{$this->table->postmeta}` WHERE post_id IN {$in_product_ids} AND meta_key = %s",
				$zone->get_postmetakey( '_price' )
			)
		);

		$exclude_out_of_stock = '';

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$exclude_out_of_stock = "INNER JOIN `{$this->table->prefix}wc_product_meta_lookup` product_meta_lookup ON product_meta_lookup.product_id = v.ID AND product_meta_lookup.stock_status <> 'outofstock'";
		}

		$this->db()->query(
			$this->db()->prepare(
				"INSERT INTO `{$this->table->postmeta}` (post_id, meta_key, meta_value)
				SELECT DISTINCT base.post_parent, %s AS meta_key,
					IF(multiplier.min_or_max = 'min', base.min_price, base.max_price) AS meta_value
				FROM (
					SELECT
						v.post_parent,
						MIN(CAST(pm_price.meta_value AS DECIMAL(18,%d))) AS min_price,
						MAX(CAST(pm_price.meta_value AS DECIMAL(18,%d))) AS max_price
					FROM `{$this->table->posts}` v
					INNER JOIN `{$this->table->postmeta}` pm_price ON v.ID = pm_price.post_id
					{$exclude_out_of_stock}
					WHERE v.post_parent IN {$in_product_ids}
					AND v.post_status NOT IN ('trash', 'auto-draft')
					AND v.post_type = 'product_variation'
					AND pm_price.meta_key = %s
					AND pm_price.meta_value != '' AND NOT pm_price.meta_value IS NULL
					GROUP BY v.post_parent
				) AS base
				CROSS JOIN (
					SELECT 'min' AS min_or_max
					UNION ALL
					SELECT 'max' AS min_or_max
				) AS multiplier",
				$zone->get_postmetakey( '_price' ),
				$zone->get_rounding_precision(),
				$zone->get_rounding_precision(),
				$zone->get_postmetakey( '_price' )
			)
		);
	}

	/**
	 * Returns variable products ID.
	 *
	 * @param array $product_ids Product IDs.
	 */
	protected function filter_variable_products( $product_ids ) {
		return get_posts(
			[
				'fields'                 => 'ids',
				'post__in'               => $product_ids,
				'posts_per_page'         => -1,
				'post_type'              => 'product',
				'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => [ // phpcs:ignore
					[
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => wcpbc_wrapper_product_types(),
						'operator' => 'IN',
					],
				],
			]
		);
	}
}
