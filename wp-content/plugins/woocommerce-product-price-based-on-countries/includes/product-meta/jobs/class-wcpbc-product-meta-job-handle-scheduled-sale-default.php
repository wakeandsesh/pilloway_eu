<?php
/**
 * Start of scheduled sales.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Job_Starting_Sales_Default class.
 */
class WCPBC_Product_Meta_Job_Handle_Scheduled_Sale_Default extends WCPBC_Product_Meta_Job_Handle_Scheduled_Sale {

	/**
	 * Runs the job.
	 */
	public function run_job() {

		$this->args = wp_parse_args(
			$this->args,
			[
				'mode'        => '',
				'product_ids' => [],
			]
		);

		$product_ids = is_array( $this->args['product_ids'] ) ? array_map( 'absint', $this->args['product_ids'] ) : false;

		if ( ! $product_ids ) {
			return;
		}

		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			foreach ( $product_ids as $product_id ) {
				if ( 'manual' === $zone->get_postmeta( $product_id, '_price_method' ) && 'manual' !== $zone->get_postmeta( $product_id, '_sale_price_dates' ) ) {

					if ( 'start' === $this->args['mode'] ) {
						$this->set_sale_price( $product_id, $zone );

					} elseif ( 'end' === $this->args['mode'] ) {
						$this->remove_sale_price( $product_id, $zone );
					}
				}
			}
		}
	}
}

