<?php
/**
 * Handle compatibility with the WooCommerce Admin
 *
 * @version 1.9.0
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCPBC_Admin_Analytics' ) ) :

	/**
	 * WCPBC_Admin_Analytics Class
	 */
	class WCPBC_Admin_Analytics {

		/**
		 * Replacements by context
		 *
		 * @var array
		 */
		protected static $context_replacements = false;

		/**
		 * Init hooks
		 */
		public static function init() {
			$contexts = static::get_contexts_replacements();
			foreach ( array_keys( $contexts ) as $context ) {
				add_filter( 'woocommerce_analytics_clauses_select_' . $context, array( __CLASS__, 'select_orders_subquery' ) );
				add_filter( 'woocommerce_analytics_clauses_join_' . $context, array( __CLASS__, 'from_orders_subquery' ), 100 );
			}
		}

		/**
		 * Return the contexts and the columns to replacement.
		 *
		 * @param string $context The data store context.
		 * @return array;
		 */
		protected static function get_contexts_replacements( $context = false ) {

			if ( empty( static::$context_replacements ) ) {
				// Fill the array.

				$table_name = static::get_db_table_name( 'wc_order_stats' );

				static::$context_replacements = array(
					'orders_stats_total'       => array(
						$table_name . '.net_total'      => false,
						$table_name . '.total_sales'    => false,
						$table_name . '.tax_total'      => false,
						$table_name . '.shipping_total' => false,
						'discount_amount'               => false,
					),
					'orders_stats_interval'    => array(
						$table_name . '.net_total'      => false,
						$table_name . '.total_sales'    => false,
						$table_name . '.tax_total'      => false,
						$table_name . '.shipping_total' => false,
						'discount_amount'               => false,
					),
					'orders_subquery'          => array(
						$table_name . '.net_total'   => 'net_total',
						$table_name . '.total_sales' => 'total_sales',
						'discount_amount'            => false,
					),
					'coupons_subquery'         => array(
						'discount_amount' => false,
					),
					'coupons_stats_total'      => array(
						'discount_amount' => false,
					),
					'coupons_stats_interval'   => array(
						'discount_amount' => false,
					),
					'products_stats_interval'  => array(
						'product_net_revenue' => false,
					),
					'products_stats_total'     => array(
						'product_net_revenue' => false,
					),
					'products_subquery'        => array(
						'product_net_revenue' => false,
					),
					'categories_subquery'      => array(
						'product_net_revenue' => false,
					),
					'taxes_stats_total'        => array(
						'(total_tax)'    => false,
						'(shipping_tax)' => false,
						'(order_tax)'    => false,
					),
					'taxes_stats_interval'     => array(
						'(total_tax)'    => false,
						'(shipping_tax)' => false,
						'(order_tax)'    => false,
					),
					'taxes_subquery'           => array(
						'(total_tax)'    => false,
						'(shipping_tax)' => false,
						'(order_tax)'    => false,
					),
					'customers_subquery'       => array(
						'total_sales' => false,
					),
					'customers_stats_subquery' => array(
						'total_sales' => false,
					),
				);
			}

			$context_replacements = static::$context_replacements;
			if ( $context ) {
				$context_replacements = isset( static::$context_replacements[ $context ] ) ? static::$context_replacements[ $context ] : array();
			}
			return $context_replacements;
		}

		/**
		 * Get table name from database class.
		 *
		 * @param string $table_name Table name.
		 */
		protected static function get_db_table_name( $table_name ) {
			global $wpdb;
			return $wpdb->prefix . $table_name;
		}

		/**
		 * Is the High-Performance Order Storage enabled?
		 *
		 * @return bool
		 */
		protected static function is_hpos_enabled() {
			static $is_hpos_enabled = null;
			if ( is_null( $is_hpos_enabled ) ) {
				$is_hpos_enabled = is_callable( [ '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ] ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			}
			return $is_hpos_enabled;
		}

		/**
		 * Get currency column name.
		 *
		 * @since 3.2
		 */
		protected static function get_currency_column_name() {
			return static::is_hpos_enabled() ? 'meta__order_currency.currency' : 'meta__order_currency.meta_value';
		}

		/**
		 * Returns join clauses with the currency rates union table.
		 */
		protected static function get_rates_query() {
			$rates         = WCPBC_Pricing_Zones::get_currency_rates();
			$base_currency = wcpbc_get_base_currency();
			$query         = array();

			$query[] = "SELECT '{$base_currency}' as currency, 1 as base_rate";

			foreach ( $rates as $currency => $rate ) {
				if ( $currency === $base_currency ) {
					continue;
				}
				$query[] = "SELECT '{$currency}' as currency, 1/{$rate} as base_rate";
			}
			return implode( ' UNION ', $query );
		}

		/**
		 * Replace a column by the base currency expression and return the result.
		 *
		 * @param string $clause The select clause.
		 * @param string $column The column to replace.
		 * @param string $alias Alias of the column.
		 */
		protected static function replace_column( $clause, $column, $alias = false ) {
			$base_currency   = wcpbc_get_base_currency();
			$currency_column = static::get_currency_column_name();
			$format          = '((%1$s * CASE WHEN %2$s = \'%3$s\' THEN 1 ELSE COALESCE(CAST(meta__wcpbc_base_exchange_rate.meta_value as decimal(20, 15)), COALESCE(wcpbc_rates.base_rate, 1)) END ))';
			$column_base     = sprintf( $format, $column, $currency_column, $base_currency );
			if ( $alias ) {
				$column_base .= ' as ' . $alias;
			}
			return str_replace( $column, $column_base, $clause );
		}

		/**
		 * Return a post meta join clause
		 *
		 * @param string $metakey Meta key.
		 */
		protected static function join_meta( $metakey ) {
			if ( static::is_hpos_enabled() && '_order_currency' === $metakey ) {
				return static::join_hpos_order_table();
			}
			$metatable      = static::is_hpos_enabled() ? static::get_db_table_name( 'wc_orders_meta' ) : static::get_db_table_name( 'postmeta' );
			$foreignkey     = static::is_hpos_enabled() ? 'order_id' : 'post_id';
			$wc_order_stats = static::get_db_table_name( 'wc_order_stats' );

			return "LEFT JOIN {$metatable} AS meta_{$metakey} ON ( {$wc_order_stats}.order_id = meta_{$metakey}.{$foreignkey} AND meta_{$metakey}.meta_key = '{$metakey}' )";
		}

		/**
		 * Return a post meta join clause
		 */
		protected static function join_hpos_order_table() {
			$metakey        = '_order_currency';
			$ordertable     = static::get_db_table_name( 'wc_orders' );
			$wc_order_stats = static::get_db_table_name( 'wc_order_stats' );

			return "LEFT JOIN {$ordertable} AS meta_{$metakey} ON ( {$wc_order_stats}.order_id = meta_{$metakey}.id )";
		}

		/**
		 * Are there possible replacements in the clause?
		 *
		 * @param array  $clauses Array of clauses.
		 * @param string $context The data store context.
		 */
		protected static function has_replacements( $clauses, $context ) {
			$has_replacements = false;
			$replacements     = static::get_contexts_replacements( $context );
			$total_clauses    = count( $clauses );

			for ( $i = 0; $i < $total_clauses && ! $has_replacements; $i++ ) {
				$fields       = array_keys( $replacements );
				$total_fields = count( $fields );
				for ( $j = 0; $j < $total_fields && ! $has_replacements; $j++ ) {
					$has_replacements = strpos( $clauses[ $i ], $fields[ $j ] ) !== false;
				}
			}
			return $has_replacements;
		}

		/**
		 * Replaces totals amount to apply the exchange rates.
		 *
		 * @param array $clauses Array of clauses.
		 */
		public static function select_orders_subquery( $clauses ) {
			$context = str_replace( 'woocommerce_analytics_clauses_select_', '', current_filter() );

			if ( static::has_replacements( $clauses, $context ) ) {
				foreach ( $clauses as $index => $clause ) {
					foreach ( static::get_contexts_replacements( $context ) as $column => $alias ) {
						$clause = static::replace_column( $clause, $column, $alias );
					}
					$clauses[ $index ] = $clause;
				}
			}
			return $clauses;
		}

		/**
		 * Adds join to the query.
		 *
		 * @param array $clauses Array of clauses.
		 */
		public static function from_orders_subquery( $clauses ) {

			$clauses[] = static::join_meta( '_order_currency' );
			$clauses[] = static::join_meta( '_wcpbc_base_exchange_rate' );
			$clauses[] = 'LEFT JOIN (' . static::get_rates_query() . ') wcpbc_rates ON convert(wcpbc_rates.currency using utf8) = convert(' . static::get_currency_column_name() . ' using utf8)';

			return $clauses;
		}
	}

endif;
