<?php
/**
 * Sync pricing zone product metadata.
 *
 * @package WCPBC
 * @since 1.9.0
 * @version 4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Product_Sync Class
 */
class WCPBC_Product_Sync {

	/**
	 * Returns the parent product types (variable, grouped).
	 *
	 * @return array
	 */
	public static function get_parent_product_types() {
		wc_deprecated_function( __METHOD__, '4.0.0', 'wcpbc_wrapper_product_types' );
		return wcpbc_wrapper_product_types();
	}

	/**
	 * Sync the price of the parent products.
	 *
	 * @param array $args Array of arguments.
	 * @param int   $limit Max number or records to be processed.
	 */
	public static function parent_product_price_sync( $args = array(), $limit = 100 ) {
		wc_deprecated_function( __METHOD__, '4.0.0' );
	}
}

