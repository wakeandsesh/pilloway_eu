<?php
/**
 * Handle scheduled sales.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Meta_Job_Handle_Scheduled_Sale class.
 */
abstract class WCPBC_Product_Meta_Job_Handle_Scheduled_Sale extends WCPBC_Product_Meta_Job {

	/**
	 * Starts the sale.
	 *
	 * @param int                $product_id product ID to set the sale price.
	 * @param WCPBC_Pricing_Zone $zone Pricing zone instance.
	 */
	protected function set_sale_price( $product_id, $zone ) {
		$sale_price = $zone->get_postmeta( $product_id, '_sale_price' );
		$price      = $zone->get_postmeta( $product_id, '_price' );

		if ( ! $sale_price || floatval( $price ) === floatval( $sale_price ) ) {
			return;
		}

		$zone->set_postmeta( $product_id, '_price', $sale_price );
	}

	/**
	 * Ends the sale.
	 *
	 * @param int                $product_id product ID to remove the sale price.
	 * @param WCPBC_Pricing_Zone $zone Pricing zone instance.
	 */
	protected function remove_sale_price( $product_id, $zone ) {
		$regular_price = $zone->get_postmeta( $product_id, '_regular_price' );
		$zone->set_postmeta( $product_id, '_price', $regular_price );
	}
}
