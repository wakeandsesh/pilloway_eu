<?php
/**
 * Populate the post meta table with the _price meta key.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Job_Add_Zone class.
 */
class WCPBC_Product_Meta_Job_Add_Zone extends WCPBC_Product_Meta_Job {

	/**
	 * Runs the job.
	 */
	public function run_job() {

		$zone = isset( $this->args['zone_id'] ) ? WCPBC_Pricing_Zones::get_zone( $this->args['zone_id'] ) : false;

		if ( ! $zone ) {
			return;
		}

		$batch_size = $this->get_batch_size();

		$rows_affected = $this->db()->query(
			$this->db()->prepare(
				"INSERT INTO `{$this->table->postmeta}` (post_id, meta_key, meta_value)
				SELECT DISTINCT ids.product_id  AS post_id, %s AS meta_key,
				IF(multiplier.min_or_max = 'min',
					ROUND( ids.min_price * %s, %d),
					ROUND( ids.max_price * %s, %d)
				) AS meta_value
				FROM (
					SELECT product_meta_lookup.product_id, product_meta_lookup.min_price, product_meta_lookup.max_price
					FROM `{$this->table->product_meta_lookup}` product_meta_lookup
					INNER JOIN `{$this->table->posts}` posts ON posts.ID = product_meta_lookup.product_id
					WHERE {$this->get_post_filter()} AND NOT product_meta_lookup.min_price IS NULL
					AND NOT EXISTS (
						SELECT 1 FROM `{$this->table->postmeta}` pm WHERE pm.post_id = product_meta_lookup.product_id
							AND pm.meta_key = %s
					) LIMIT %d
				) as ids
				CROSS JOIN (
					SELECT 'min' AS min_or_max
					UNION ALL
					SELECT 'max' AS min_or_max
				) AS multiplier",
				$zone->get_postmetakey( '_price' ),
				$zone->get_exchange_rate(),
				$zone->get_rounding_precision(),
				$zone->get_exchange_rate(),
				$zone->get_rounding_precision(),
				$zone->get_postmetakey( '_price' ),
				$batch_size
			)
		);

		// Insert price method for variable products.
		$product_type_clauses = (
			new WP_Tax_Query(
				[
					[
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => wcpbc_wrapper_product_types(),
						'operator' => 'IN',
					],
				]
			)
		)->get_sql( 'product_meta_lookup', 'product_id' );

		$product_type_clauses['join'] = str_replace( 'LEFT', 'INNER', $product_type_clauses['join'] ) . ' ';

		$rows_affected += $this->db()->query(
			$this->db()->prepare(
				"INSERT INTO `{$this->table->postmeta}` (post_id, meta_key, meta_value)
				SELECT DISTINCT product_meta_lookup.product_id  AS post_id, %s AS meta_key, 'manual' as meta_value
				FROM `{$this->table->product_meta_lookup}` product_meta_lookup
					INNER JOIN `{$this->table->posts}` posts ON posts.ID = product_meta_lookup.product_id
					{$product_type_clauses['join']}
				WHERE posts.post_type = 'product' AND posts.post_status NOT IN ('trash', 'auto-draft') {$product_type_clauses['where']} AND NOT EXISTS (
					SELECT 1 FROM `{$this->table->postmeta}` pm WHERE pm.post_id = product_meta_lookup.product_id
						AND pm.meta_key = %s
				) LIMIT %d",
				$zone->get_postmetakey( '_price_method' ),
				$zone->get_postmetakey( '_price_method' ),
				$batch_size
			)
		);

		if ( $rows_affected > 0 ) {
			// Re-enqueue the job to process the next batch.
			$this->run_async();
		}
	}
}
