<?php
/**
 * Delete a pricing zone from the post meta table.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Job_Delete_Zone class.
 */
class WCPBC_Product_Meta_Job_Delete_Zone extends WCPBC_Product_Meta_Job {

	/**
	 * Metakeys to delete.
	 *
	 * @var array
	 */
	private const META_KEYS = [
		'_price',
		'_regular_price',
		'_sale_price',
		'_price_method',
		'_sale_price_dates',
		'_sale_price_dates_from',
		'_sale_price_dates_to',
		'coupon_amount',
	];

	/**
	 * Delete a postmeta key for the zone.
	 *
	 * @param array $post_ids            Post IDs to process.
	 * @param array $meta_keys_to_delete Meta keys to delete for these posts.
	 * @return int
	 */
	protected function delete_postmeta_for_posts( $post_ids, $meta_keys_to_delete ) {
		$where_keys = $this->prepare_in( 'AND meta_key IN (%s) ', $meta_keys_to_delete );
		$where_ids  = sprintf( 'AND post_id IN (%s)', implode( ',', $post_ids ) );

		return $this->db()->query(
			"DELETE FROM `{$this->table->postmeta}` WHERE 1=1 {$where_keys} {$where_ids}"
		);
	}

	/**
	 * Runs the job.
	 */
	public function run_job() {

		$zone_id = isset( $this->args['zone_id'] ) ? $this->args['zone_id'] : false;

		if ( ! $zone_id || WCPBC_Pricing_Zones::get_zone( $zone_id ) ) {
			return;
		}

		$meta_keys_to_delete = [];
		$zone                = new WCPBC_Pricing_Zone(
			[
				'id' => $zone_id,
			]
		);

		foreach ( self::META_KEYS as $meta_key ) {
			$meta_keys_to_delete[] = $zone->get_postmetakey( $meta_key );
		}

		if ( $meta_keys_to_delete ) {

			$where_keys = $this->prepare_in( '(%s) ', $meta_keys_to_delete );

			$post_ids = $this->db()->get_col(
				$this->db()->prepare(
					"SELECT p.ID FROM `{$this->table->posts}` p
					WHERE p.post_type IN ('product', 'product_variation', 'shop_coupon' )
					AND p.post_status IN ('publish', 'draft', 'pending', 'private')
					AND EXISTS (
						SELECT 1 FROM `{$this->table->postmeta}` pm WHERE pm.post_id = p.ID AND pm.meta_key IN {$where_keys}
					) LIMIT %d",
					$this->get_batch_size()
				)
			);

			if ( ! $post_ids ) {
				return;
			}

			$this->delete_postmeta_for_posts( $post_ids, $meta_keys_to_delete );

			if ( wp_using_ext_object_cache() && function_exists( 'wp_cache_delete_multiple' ) ) {
				wp_cache_delete_multiple( $post_ids, 'post_meta' );
			}

			$this->run_async();
		}
	}
}
