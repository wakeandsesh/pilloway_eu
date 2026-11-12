<?php
/**
 * Update column with exchange rate.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Job_Update_Exchange_Rate_Price class.
 */
class WCPBC_Product_Meta_Job_Update_Exchange_Rate_Price extends WCPBC_Product_Meta_Job {

	/**
	 * Runs the job.
	 */
	public function run_job() {
		$zone = isset( $this->args['zone_id'] ) ? WCPBC_Pricing_Zones::get_zone( $this->args['zone_id'] ) : false;

		if ( ! $zone ) {
			return;
		}

		$zone               = WCPBC_Pricing_Zones::get_zone( $this->args['zone_id'] );
		$exchange_rate      = $zone->get_exchange_rate();
		$rounding_precision = $zone->get_rounding_precision();
		$epsilon            = pow( 10, $rounding_precision * -1 );
		$batch_size         = $this->get_batch_size();

		$rows = $this->db()->get_results(
			$this->db()->prepare(
				"SELECT pm_price.meta_id, product_meta_lookup.product_id, posts.post_type, posts.post_parent
				FROM `{$this->table->product_meta_lookup}` product_meta_lookup
				INNER JOIN `{$this->table->postmeta}` pm_price ON pm_price.post_id = product_meta_lookup.product_id
				INNER JOIN `{$this->table->posts}` posts  ON posts.ID = product_meta_lookup.product_id
					AND pm_price.meta_key = %s
				WHERE {$this->get_post_filter()}
					AND NOT product_meta_lookup.min_price IS NULL
					AND ABS(CAST(COALESCE(pm_price.meta_value, 0) AS DECIMAL(18, %d)) - ROUND(product_meta_lookup.min_price * %s, %d)) > %s+0
					AND NOT EXISTS (
						SELECT 1
						FROM `{$this->table->postmeta}` AS pm_manual
						WHERE pm_manual.post_id = product_meta_lookup.product_id
						AND pm_manual.meta_key = %s
						AND pm_manual.meta_value = 'manual'
					)
				LIMIT %d",
				$zone->get_postmetakey( '_price' ),
				$rounding_precision,
				$exchange_rate,
				$rounding_precision,
				$epsilon,
				$zone->get_postmetakey( '_price_method' ),
				$batch_size
			)
		);

		if ( $rows ) {

			$meta_ids_array = [];
			foreach ( $rows as $row ) {
				$meta_ids_array[] = absint( $row->meta_id );
			}

			$meta_ids = sprintf( 'pm_price.meta_id IN (%s)', implode( ',', $meta_ids_array ) );

			$rows_affected = $this->db()->query(
				$this->db()->prepare(
					"UPDATE `{$this->table->postmeta}` pm_price
					INNER JOIN `{$this->table->product_meta_lookup}` product_meta_lookup ON product_meta_lookup.product_id = pm_price.post_id
					SET pm_price.meta_value = ROUND(product_meta_lookup.min_price * %s, %d)
					WHERE pm_price.meta_key = %s AND {$meta_ids}
						AND NOT product_meta_lookup.min_price IS NULL",
					$exchange_rate,
					$rounding_precision,
					$zone->get_postmetakey( '_price' )
				)
			);

			if ( $rows_affected > 0 ) {

				$this->clear_cache = true;

				$parent_ids = array_unique(
					array_filter(
						array_map(
							function ( $row ) {
								return 'product_variation' === $row->post_type && $row->post_parent ? $row->post_parent : false;
							},
							$rows
						)
					)
				);

				if ( $parent_ids ) {
					WCPBC_Product_Meta_Job::create(
						'Sync_Price_With_Children',
						[
							'zone_id'     => $this->args['zone_id'],
							'product_ids' => $parent_ids,
						]
					)->run();
				}
			}

			if ( $this->clear_cache && wp_using_ext_object_cache() ) {
				wp_cache_delete_multiple( wc_list_pluck( $rows, 'product_id' ), 'post_meta' );
			}

			if ( ! ( $rows_affected < $batch_size ) ) {
				// Process next queque.
				$this->run_async();
			}
		}
	}
}
