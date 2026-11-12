<?php
/**
 * WooCommerce Price Based on Country legacy reports
 *
 * @package WCPBC
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Admin_Legacy_Reports Class
 */
class WCPBC_Admin_Legacy_Reports {

	/**
	 * Init hooks
	 */
	public static function init() {
		add_filter( 'woocommerce_reports_get_order_report_query', [ __CLASS__, 'reports_get_order_report_query' ] );
	}

	/**
	 * Built a SELECT CASE expression to uses in order querys
	 *
	 * @since 1.8.0
	 * @param string $field Field name to multiply by exchange rate.
	 * @param array  $rates Array of currency exchange rates.
	 * @return string
	 */
	public static function built_query_case( $field, $rates ) {

		$case_ex = ' CASE meta__order_currency.meta_value ';
		foreach ( $rates as $currency => $rate ) {
			$case_ex .= "WHEN '{$currency}' THEN ( {$field} / ({$rate})) ";
		}
		$case_ex .= "ELSE {$field} END ";

		return $case_ex;
	}

	/**
	 * Built a JOIN query expression to uses in order querys.
	 *
	 * @since 1.8.0
	 * @param string $from_table From table name.
	 * @param string $join_type Join type (INNER, LEFT or RIGHT).
	 * @param string $id_field Order ID field. Default "ID".
	 * @return string
	 */
	public static function built_join_meta_currency( $from_table = false, $join_type = 'INNER', $id_field = 'ID' ) {
		global $wpdb;

		$from_table = $from_table ? $from_table : 'posts';
		return ' ' . $join_type . " JOIN {$wpdb->postmeta} AS meta__order_currency ON ( {$from_table}.{$id_field} = meta__order_currency.post_id AND meta__order_currency.meta_key = '_order_currency' ) ";
	}

	/**
	 * Replace report line item totals amount in report query.
	 *
	 * @since 2.0.12
	 * @param array $query Report query.
	 * @return array
	 */
	public static function reports_get_order_report_query( $query ) {

		$rates = WCPBC_Pricing_Zones::get_currency_rates();

		if ( ! empty( $rates ) ) {
			$change = false;
			$fields = array(
				' meta__order_total.meta_value',
				' meta__order_shipping.meta_value',
				' meta__order_tax.meta_value',
				' meta__order_shipping_tax.meta_value',
				' meta__refund_amount.meta_value ',
				' order_item_meta_discount_amount.meta_value',
				' order_item_meta__line_total.meta_value',
				'parent_meta__order_total.meta_value',
				'parent_meta__order_shipping.meta_value',
				'parent_meta__order_tax.meta_value',
				'parent_meta__order_shipping_tax.meta_value',
			);

			foreach ( $fields as $field ) {
				if ( false !== strpos( $query['select'], $field ) ) {
					$query['select'] = str_replace( $field, self::built_query_case( $field, $rates ), $query['select'] );
					$change          = true;
				}
			}
			if ( $change ) {
				// Add the meta_order_currency table to the join.
				$query['join'] .= self::built_join_meta_currency();
			}
		}

		return $query;
	}
}
