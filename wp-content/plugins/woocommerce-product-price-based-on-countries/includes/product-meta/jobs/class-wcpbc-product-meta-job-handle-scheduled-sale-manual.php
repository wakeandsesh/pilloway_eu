<?php
/**
 * Start of scheduled sales for manual dates.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Job_Starting_Sales_Manual class.
 */
class WCPBC_Product_Meta_Job_Handle_Scheduled_Sale_Manual extends WCPBC_Product_Meta_Job_Handle_Scheduled_Sale {

	/**
	 * Runs the job.
	 */
	public function run_job() {
		$this->starting_sales();
		$this->ending_sales();
	}

	/**
	 * Starting sales.
	 */
	protected function starting_sales() {
		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			$post_ids = $this->db()->get_col(
				$this->db()->prepare(
					"SELECT p_lookup.product_id
					FROM `{$this->table->product_meta_lookup}` p_lookup
					INNER JOIN `{$this->table->postmeta}` pm_dates ON p_lookup.product_id = pm_dates.post_id
						AND pm_dates.meta_key = %s
					INNER JOIN `{$this->table->postmeta}` pm_from ON p_lookup.product_id = pm_from.post_id
						AND pm_from.meta_key = %s
					INNER JOIN `{$this->table->postmeta}` pm_to ON p_lookup.product_id = pm_to.post_id
						AND pm_to.meta_key = %s
					INNER JOIN `{$this->table->postmeta}` pm_sale_price ON p_lookup.product_id = pm_sale_price.post_id
						AND pm_sale_price.meta_key = %s
					INNER JOIN `{$this->table->postmeta}` pm_price ON p_lookup.product_id = pm_price.post_id
						AND pm_price.meta_key = %s
					WHERE pm_dates.meta_value = 'manual'
					AND pm_from.meta_value > 0 AND pm_from.meta_value != '' AND pm_from.meta_value < %d
					AND (pm_to.meta_value > %d OR pm_to.meta_value = '0' OR pm_to.meta_value = '' )
					AND pm_sale_price.meta_value != '' AND pm_sale_price.meta_value != pm_price.meta_value",
					$zone->get_postmetakey( '_sale_price_dates' ),
					$zone->get_postmetakey( '_sale_price_dates_from' ),
					$zone->get_postmetakey( '_sale_price_dates_to' ),
					$zone->get_postmetakey( '_sale_price' ),
					$zone->get_postmetakey( '_price' ),
					time(),
					time()
				)
			);

			foreach ( $post_ids as $post_id ) {
				$this->set_sale_price( $post_id, $zone );
			}
		}
	}

	/**
	 * Ending sales.
	 */
	protected function ending_sales() {
		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			$post_ids = $this->db()->get_col(
				$this->db()->prepare(
					"SELECT p_lookup.product_id
					FROM `{$this->table->product_meta_lookup}` p_lookup
					INNER JOIN `{$this->table->postmeta}` pm_dates ON p_lookup.product_id = pm_dates.post_id
						AND pm_dates.meta_key = %s
					INNER JOIN `{$this->table->postmeta}` pm_to ON p_lookup.product_id = pm_to.post_id
						AND pm_to.meta_key = %s
					INNER JOIN `{$this->table->postmeta}` pm_regular_price ON p_lookup.product_id = pm_regular_price.post_id
						AND pm_regular_price.meta_key = %s
					INNER JOIN `{$this->table->postmeta}` pm_price ON p_lookup.product_id = pm_price.post_id
						AND pm_price.meta_key = %s
					WHERE pm_dates.meta_value = 'manual'
					AND pm_to.meta_value < %d AND pm_to.meta_value > 0 AND pm_to.meta_value != ''
					AND pm_regular_price.meta_value != '' AND pm_regular_price.meta_value != pm_price.meta_value",
					$zone->get_postmetakey( '_sale_price_dates' ),
					$zone->get_postmetakey( '_sale_price_dates_to' ),
					$zone->get_postmetakey( '_regular_price' ),
					$zone->get_postmetakey( '_price' ),
					time()
				)
			);

			foreach ( $post_ids as $post_id ) {
				$this->remove_sale_price( $post_id, $zone );
			}
		}
	}
}
