<?php
/**
 * Core Functions
 *
 * @package WCPBC
 * @version 1.8.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main function for returning the current Pricing Zone instance.
 * Alias of WCPBC()->current_zone.
 *
 * @since 1.8.0
 *
 * @return WCPBC_Pricing_Zone|WCPBC_Pricing_Zone_Pro
 */
function wcpbc_the_zone() {

	if ( wcpbc()->current_zone && is_a( wcpbc()->current_zone, 'WCPBC_Pricing_Zone' ) ) {
		return wcpbc()->current_zone;
	}
	return false;
}

/**
 * Returns the shop base currency.
 *
 * @return string
 */
function wcpbc_get_base_currency() {
	return get_option( 'woocommerce_currency' );
}

/**
 * Check is WooCommerce frontend
 *
 * @since 1.6.6
 * @return bool
 */
function wcpbc_is_woocommerce_frontend() {
	return function_exists( 'WC' ) && ! empty( WC()->customer );
}

/**
 * Is the value exchange rate?
 *
 * @since 1.7.15
 * @param string $value Value to check.
 * @return bool
 */
function wcpbc_is_exchange_rate( $value = false ) {
	return 'manual' !== $value;
}

/**
 * Get WooCommerce customer country
 *
 * @return string
 */
function wcpbc_get_woocommerce_country() {

	$country = false;

	if ( wcpbc_is_woocommerce_frontend() ) {

		$country          = wc()->customer->get_billing_country();
		$shipping_country = wc()->customer->get_shipping_country();
		if ( ! empty( $shipping_country ) && $country !== $shipping_country && 'shipping' === get_option( 'wc_price_based_country_based_on', 'billing' ) ) {
			$country = $shipping_country;
		}
	}

	return $country;
}

/**
 * Set WooCommerce customer country
 *
 * @param string $country Customer country.
 */
function wcpbc_set_woocommerce_country( $country ) {

	if ( ! wcpbc_is_woocommerce_frontend() || ! in_array( $country, array_keys( wc()->countries->countries ), true ) ) {
		return;
	}

	$billing_country = wc()->customer->get_billing_country();
	$shipping_county = wc()->customer->get_shipping_country();

	if ( $billing_country !== $shipping_county && 'shipping' === get_option( 'wc_price_based_country_based_on', 'shipping' ) && apply_filters( 'woocommerce_ship_to_different_address_checked', get_option( 'woocommerce_ship_to_destination' ) === 'shipping' ? 1 : 0 ) ) {
		wc()->customer->set_shipping_country( $country );
	} else {
		wc()->customer->set_billing_country( $country );
		wc()->customer->set_shipping_country( $country );
	}
}

/**
 * Alias of WCPBC_Pricing_Zones::get_zone_by_country
 *
 * @since 1.7.0
 * @param string $country The country.
 * @return WCPBC_Pricing_Zone
 */
function wcpbc_get_zone_by_country( $country = '' ) {
	if ( ! class_exists( 'WCPBC_Pricing_Zones' ) ) {
		return false;
	}
	$country = empty( $country ) ? wcpbc_get_woocommerce_country() : $country;
	return WCPBC_Pricing_Zones::get_zone_by_country( $country );
}

/**
 * Saves the product pricing for a zone.
 *
 * @since 1.8.0
 * @param int                $post_id Post ID.
 * @param WCPBC_Pricing_Zone $zone Pricig zone instance.
 * @param array              $data The product pricing.
 */
function wcpbc_update_product_pricing( $post_id, $zone, $data = array() ) {

	if ( empty( $data ) ) {
		return;
	}

	if ( empty( $data['_price_method'] ) ) {
		$data['_price_method'] = $zone->get_postmeta( $post_id, '_price_method' ); // Preserve the price method.
	}

	if ( 'manual' !== $data['_price_method'] ) {
		// Exchange rate.
		$zone->set_postmeta(
			$post_id,
			'_price',
			$zone->get_exchange_rate_price_by_post( $post_id, '_price' )
		);

		$zone->delete_postmeta( $post_id, '_price_method' );
		$zone->delete_postmeta( $post_id, '_regular_price' );
		$zone->delete_postmeta( $post_id, '_sale_price' );
		$zone->delete_postmeta( $post_id, '_sale_price_dates' );
		$zone->delete_postmeta( $post_id, '_sale_price_dates_from' );
		$zone->delete_postmeta( $post_id, '_sale_price_dates_to' );

	} else {
		// Manual.
		$data['_price_method']     = 'manual';
		$data['_regular_price']    = isset( $data['_regular_price'] ) ? $data['_regular_price'] : $zone->get_postmeta( $post_id, '_regular_price' );
		$data['_sale_price']       = isset( $data['_sale_price'] ) ? $data['_sale_price'] : $zone->get_postmeta( $post_id, '_sale_price' );
		$data['_sale_price_dates'] = isset( $data['_sale_price_dates'] ) ? $data['_sale_price_dates'] : $zone->get_postmeta( $post_id, '_sale_price_dates' );

		// Sale Dates.
		if ( 'manual' !== $data['_sale_price_dates'] ) {
			$data['_sale_price_dates']      = 'default';
			$data['_sale_price_dates_from'] = get_post_meta( $post_id, '_sale_price_dates_from', true );
			$data['_sale_price_dates_to']   = get_post_meta( $post_id, '_sale_price_dates_to', true );
		} else {

			// Force date from to beginning of day.
			if ( isset( $data['_sale_price_dates_from'] ) ) {
				if ( ! empty( $data['_sale_price_dates_from'] ) ) {
					$data['_sale_price_dates_from'] = wcpbc_string_to_timestamp( gmdate( 'Y-m-d 00:00:00', strtotime( $data['_sale_price_dates_from'] ) ) );
				}
			} else {
				$data['_sale_price_dates_from'] = $zone->get_postmeta( $post_id, '_sale_price_dates_from' );
			}

			// Force date to to the end of the day.
			if ( isset( $data['_sale_price_dates_to'] ) ) {
				if ( ! empty( $data['_sale_price_dates_to'] ) ) {
					$data['_sale_price_dates_to'] = wcpbc_string_to_timestamp( gmdate( 'Y-m-d 23:59:59', strtotime( $data['_sale_price_dates_to'] ) ) );
				}
			} else {
				$data['_sale_price_dates_to'] = $zone->get_postmeta( $post_id, '_sale_price_dates_to' );
			}
		}

		$data['_regular_price'] = wc_format_decimal( $data['_regular_price'] );
		$data['_sale_price']    = wc_format_decimal( $data['_sale_price'] );

		// Update price if on sale.
		$current_time = time();

		if ( ! empty( $data['_sale_price_dates_to'] ) && empty( $data['_sale_price_dates_from'] ) ) {
			$data['_sale_price_dates_from'] = $current_time;
		}

		if ( ! wcpbc_empty_nozero( $data['_sale_price'] ) && empty( $data['_sale_price_dates_to'] ) && empty( $data['_sale_price_dates_from'] ) ) {
			$data['_price'] = $data['_sale_price'];
		} elseif ( ! wcpbc_empty_nozero( $data['_sale_price'] ) && $data['_sale_price_dates_from'] && $data['_sale_price_dates_from'] <= $current_time ) {
			$data['_price'] = $data['_sale_price'];
		} else {
			$data['_price'] = $data['_regular_price'];
		}

		if ( $data['_sale_price_dates_to'] && $data['_sale_price_dates_to'] < $current_time ) {
			$data['_price'] = $data['_regular_price'];
		}

		// Save metadata.
		$zone->set_postmeta( $post_id, '_price_method', $data['_price_method'] );
		$zone->set_postmeta( $post_id, '_regular_price', $data['_regular_price'] );
		$zone->set_postmeta( $post_id, '_sale_price', $data['_sale_price'] );
		$zone->set_postmeta( $post_id, '_price', $data['_price'] );
		$zone->set_postmeta( $post_id, '_sale_price_dates', $data['_sale_price_dates'] );
		$zone->set_postmeta( $post_id, '_sale_price_dates_from', $data['_sale_price_dates_from'] );
		$zone->set_postmeta( $post_id, '_sale_price_dates_to', $data['_sale_price_dates_to'] );
	}
}

/**
 * Get rounding precision for internal calculations.
 * Will return the value of max pricing zone decimals increased by 4 decimals, with WC_ROUNDING_PRECISION being the minimum.
 *
 * @since 4.0.0
 * @return int
 */
function wcpbc_get_rounding_precision() {
	static $precision = false;

	if ( false !== $precision ) {
		return $precision;
	}

	$num_decimals = wc_get_price_decimals();

	if ( wcpbc_is_pro() ) {
		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			if ( is_callable( [ $zone, 'get_price_num_decimals' ] ) && $num_decimals < $zone->get_price_num_decimals() ) {
				$num_decimals = $zone->get_price_num_decimals();
			}
		}
	}

	$precision = $num_decimals + 3;

	return $precision;
}

/**
 * Is Pro version
 *
 * @since 1.6.11
 * return boolean
 */
function wcpbc_is_pro() {
	return class_exists( 'WC_Product_Price_Based_Country_Pro' ) &&
		class_exists( 'WCPBC_Frontend_Currency' ) &&
		class_exists( 'WCPBC_Integrations_Pro' );
}

/**
 * Returns the wrapper product types (variable, grouped, ...). Wrapper types that cannot be purchased.
 *
 * @since 4.0.0
 * @return array
 */
function wcpbc_wrapper_product_types() {
	static $types = false;

	if ( false === $types ) {
		$types = array_unique(
			array_merge(
				[ 'variable', 'grouped' ],
				apply_filters(
					'wc_price_based_country_parent_product_types',
					[]
				)
			)
		);
	}

	return $types;
}

/*
|--------------------------------------------------------------------------
| Utils Functions
|--------------------------------------------------------------------------
*/

/**
 * Return is a value is empty and no-zero
 *
 * @since 1.7.0
 * @param string $value The value to check.
 * @return bool
 */
function wcpbc_empty_nozero( $value ) {
	return ( empty( $value ) && ! ( is_numeric( $value ) && 0 === absint( $value ) ) ); // WPCS: loose comparison ok.
}

/**
 * Sort a array with locale-sensitive
 *
 * @since 1.6.0
 * @param array $arr Array to sort.
 * @return true
 */
function wcpbc_maybe_asort_locale( &$arr ) {

	try {
		$coll = function_exists( 'collator_create' ) ? collator_create( get_locale() ) : false;
		if ( $coll ) {
			return collator_asort( $coll, $arr );
		} else {
			return asort( $arr );
		}
	} catch ( Exception $e ) {
		return asort( $arr );
	}
}

/**
 * Convert a float to a string without locale formatting which PHP adds when changing floats to strings. Remove scientific notation.
 *
 * @since 2.0.21
 * @version 3.0 Added $display param.
 *
 * @param float $value   Float value to format.
 * @param bool  $display Should format value for display?.
 * @return string
 */
function wcpbc_float_to_string( $value, $display = false ) {
	if ( ! is_float( $value ) ) {
		return $value;
	}

	$string = strtoupper( strval( $value ) );
	$locale = localeconv();
	$string = str_replace( array( $locale['decimal_point'], $locale['mon_decimal_point'] ), '.', $string );

	$e_pos = strpos( $string, 'E' );
	if ( false !== $e_pos ) {
		// Remove scientific notation.
		$dp          = intval( substr( $string, $e_pos + 1 ) );
		$decimal_pos = strpos( $string, '.' );
		if ( false !== $decimal_pos ) {
			$dp -= strlen( substr( $string, $decimal_pos + 1, $e_pos - $decimal_pos - 1 ) );
		}
		$string = number_format( $value, $dp * ( -1 ), '.', '' );
	}

	if ( $display ) {
		$wc_decimal_separator = wc_get_price_decimal_separator();
		$decimal_point        = empty( $wc_decimal_separator ) ? $locale['decimal_point'] : $wc_decimal_separator;

		if ( '.' !== $decimal_point ) {
			$string = str_replace( '.', $decimal_point, $string );
		}
	}
	return $string;
}

/**
 * Return timestamp from a date string.
 *
 * @since 1.8.18
 * @param string $value Date as string.
 * @return int
 */
function wcpbc_string_to_timestamp( $value ) {
	if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
		$timestamp = wc_string_to_timestamp( get_gmt_from_date( gmdate( 'Y-m-d H:i:s', wc_string_to_timestamp( $value ) ) ) );
	} else {
		$timestamp = strtotime( $value );
	}
	return $timestamp;
}

/**
 * Check if Maxmind GeoIP database exists
 *
 * @since 1.7.12
 * @return boolean
 */
function wcpbc_geoipdb_exists() {
	$exists = ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) || ! empty( $_SERVER['GEOIP_COUNTRY_CODE'] ) || ! empty( $_SERVER['HTTP_X_COUNTRY_CODE'] );
	if ( ! $exists ) {
		if ( version_compare( WC_VERSION, '3.9', '<' ) && is_callable( array( 'WC_Geolocation', 'get_local_database_path' ) ) ) {
			$database = WC_Geolocation::get_local_database_path();
			$exists   = file_exists( $database );
		} else {
			$maxmind_geolocation = WC()->integrations->get_integration( 'maxmind_geolocation' );
			if ( $maxmind_geolocation && is_callable( array( $maxmind_geolocation, 'get_database_service' ) ) ) {
				$database = $maxmind_geolocation->get_database_service()->get_database_path();
				$exists   = file_exists( $database );
			}
		}
	}
	return $exists;
}

/**
 * Queue JavaScript code to be output in the footer.
 *
 * @param string $code Code.
 * @since 4.1.0
 */
function wcpbc_enqueue_js( $code ) {
	$handle = 'wc-price-based-country-inline';
	if ( ! wp_script_is( $handle, 'enqueued' ) ) {
		wp_register_script( $handle, '', [ 'jquery' ], WCPBC()->version, true );
		wp_enqueue_script( $handle );
	}

	wp_add_inline_script( $handle, $code );
}

/*
|--------------------------------------------------------------------------
| HPOS compatiblity Functions
|--------------------------------------------------------------------------
*/

/**
 * Returns order country. Compatible with HPOS. Allows get the billing or shipping country before the WC_Order class is init.
 *
 * @param int    $order_id Order ID.
 * @param string $type Address type. billing or shipping.
 * @since 3.2.1
 */
function wcpbc_get_order_country( $order_id, $type = 'billing' ) {
	global $wpdb;

	static $is_hpos_enabled = null;
	if ( is_null( $is_hpos_enabled ) ) {
		$is_hpos_enabled = is_callable( [ '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ] ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	$type     = 'billing' === $type ? 'billing' : 'shipping';
	$order_id = absint( $order_id );
	$country  = false;

	if ( $is_hpos_enabled ) {

		$cache_group = 'orders';
		$cache_key   = WC_Cache_Helper::get_cache_prefix( $cache_group ) . WC_Cache_Helper::get_cache_prefix( 'object_' . $order_id ) . 'wcpbc_address';
		$cached_meta = wp_cache_get( $cache_key, $cache_group );

		if ( ! is_array( $cached_meta ) ) {

			$cached_meta = [
				'billing'  => '',
				'shipping' => '',
			];

			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}wc_order_addresses WHERE order_id = %d",
					$order_id
				)
			);

			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( ! isset( $row->address_type, $row->country ) ) {
						continue;
					}
					$cached_meta[ $row->address_type ] = $row->country;
				}
			}

			wp_cache_set( $cache_key, $cached_meta, $cache_group );
		}

		$country = isset( $cached_meta[ $type ] ) ? $cached_meta[ $type ] : false;

	} else {

		$key     = "_{$type}_country";
		$country = get_post_meta( $order_id, $key, true );
	}

	return $country;
}

/*
|--------------------------------------------------------------------------
| Product Meta Box Functions
|--------------------------------------------------------------------------
*/

/**
 * Return the price method options.
 *
 * @since 1.8.0
 * @return array
 */
function wcpbc_price_method_options() {
	return array(
		'exchange_rate' => __( 'Calculate prices by the exchange rate', 'woocommerce-product-price-based-on-countries' ),
		'manual'        => __( 'Set prices manually', 'woocommerce-product-price-based-on-countries' ),
	);
}

/**
 * Return price method label.
 *
 * @since 1.8.0
 * @param string             $text Text to construct the price method label.
 * @param WCPBC_Pricing_Zone $zone Pricing zone instance.
 * @return string
 */
function wcpbc_price_method_label( $text, $zone ) {
	return $text . ' ' . str_replace( ' ', '&nbsp;', sprintf( '%s (%s)', $zone->get_name(), get_woocommerce_currency_symbol( $zone->get_currency() ) ) );
}

/**
 * Output a product pricing input control.
 *
 * @since 1.8.15
 * @param array              $field Field arguments.
 * @param WCPBC_Pricing_Zone $zone Pricig zone instance.
 * @param int                $post_id Post ID.
 */
function wcpbc_pricing_input( $field, $zone, $post_id = false ) {
	global $thepostid, $post;

	if ( ! $post_id ) {
		$post_id = empty( $thepostid ) ? $post->ID : $thepostid;
	}

	$field['name']           = empty( $field['name'] ) ? '_price_method' : $field['name'];
	$field['metakey']        = empty( $field['metakey'] ) ? $field['name'] : $field['metakey'];
	$field['id']             = empty( $field['id'] ) ? str_replace( array( '[', ']' ), array( '_', '' ), $field['name'] ) : $field['id'];
	$field['value']          = empty( $field['value'] ) ? $zone->get_postmeta( $post_id, $field['metakey'] ) : $field['value'];
	$field['label']          = empty( $field['label'] ) ? __( 'Price for', 'woocommerce-product-price-based-on-countries' ) : $field['label'];
	$field['fields']         = isset( $field['fields'] ) && is_array( $field['fields'] ) ? $field['fields'] : array();
	$field['wrapper']        = isset( $field['wrapper'] ) ? $field['wrapper'] : true;
	$field['wrapper_class']  = empty( $field['wrapper_class'] ) ? '' : $field['wrapper_class'];
	$field['wrapper_class'] .= ' wcpbc_pricing wcpbc_pricing_' . $zone->get_id();

	if ( $field['wrapper'] ) {
		echo '<div class="' . esc_attr( $field['wrapper_class'] ) . '">';
	}

	woocommerce_wp_radio(
		array(
			'id'            => $zone->get_postmetakey( $field['id'] ),
			'name'          => $zone->get_postmetakey( $field['name'] ),
			'value'         => wcpbc_is_exchange_rate( $field['value'] ) ? 'exchange_rate' : 'manual',
			'class'         => 'wcpbc_price_method',
			'label'         => wcpbc_price_method_label( $field['label'], $zone ),
			'wrapper_class' => '_price_method_wcpbc_field',
			'options'       => wcpbc_price_method_options(),
		)
	);

	foreach ( $field['fields'] as $child_field ) {
		$child_field['name']              = empty( $child_field['name'] ) ? '' : $child_field['name'];
		$child_field['metakey']           = empty( $child_field['metakey'] ) ? $child_field['name'] : $child_field['metakey'];
		$child_field['id']                = empty( $child_field['id'] ) ? str_replace( array( '[', ']' ), array( '_', '' ), $child_field['name'] ) : $child_field['id'];
		$child_field['label']             = empty( $child_field['label'] ) ? '' : sprintf( $child_field['label'], get_woocommerce_currency_symbol( $zone->get_currency() ) );
		$child_field['value']             = isset( $child_field['value'] ) ? $child_field['value'] : $zone->get_postmeta( $post_id, $child_field['metakey'] );
		$child_field['type']              = empty( $child_field['type'] ) ? 'text' : $child_field['type'];
		$child_field['data_type']         = isset( $child_field['data_type'] ) ? $child_field['data_type'] : '';
		$child_field['custom_attributes'] = isset( $child_field['custom_attributes'] ) && is_array( $child_field['custom_attributes'] ) ? $child_field['custom_attributes'] : array();
		$child_field['wrapper_class']     = empty( $child_field['wrapper_class'] ) ? ' ' : $child_field['wrapper_class'] . ' ';
		$child_field['wrapper_class']    .= $child_field['id'] . '_wcpbc_field wcpbc_show_if_manual';

		if ( empty( $child_field['data_type'] ) && 'text' === $child_field['type'] ) {
			$child_field['data_type'] = 'price';
		}
		if ( 'price' === $child_field['data_type'] && wcpbc_is_exchange_rate( $field['value'] ) ) {
			$child_field['value'] = $zone->get_exchange_rate_price_by_post( $post_id, $child_field['metakey'] );
		}
		if ( empty( $child_field['value'] ) && isset( $child_field['default_value'] ) ) {
			$child_field['value'] = $child_field['default_value'];
		}

		if ( 'date' === $child_field['data_type'] ) {
			$child_field['custom_attributes']['maxlength'] = '10';
			$child_field['custom_attributes']['pattern']   = apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' );
			$child_field['value']                          = empty( $child_field['value'] ) ? '' : date_i18n( 'Y-m-d', $child_field['value'] + ( floatval( get_option( 'gmt_offset', 0 ) ) * HOUR_IN_SECONDS ) );
		}

		$child_field['id']   = $zone->get_postmetakey( $child_field['id'] );
		$child_field['name'] = $zone->get_postmetakey( $child_field['name'] );

		// Output the field.
		if ( 'radio' === $child_field['type'] ) {
			woocommerce_wp_radio( $child_field );
		} else {
			woocommerce_wp_text_input( $child_field );
		}
	}

	if ( $field['wrapper'] ) {
		echo '</div>';
	}
}

/*
|--------------------------------------------------------------------------
| Admin Functions
|--------------------------------------------------------------------------
*/

/**
 * Get all WooCommerce screen ids.
 *
 * @since 2.0.11
 * @return array
 */
function wcpbc_get_screen_ids() {
	return apply_filters( 'wc_price_based_country_screen_ids', wc_get_screen_ids() );
}

/**
 * Returns a pricebasedcountry.com URL
 *
 * @since 2.2.0
 * @param string $utm_source UTM Source.
 * @param string $path       Path.
 * @return string
 */
function wcpbc_home_url( $utm_source, $path = 'pricing' ) {
	return add_query_arg(
		array(
			'utm_source'   => $utm_source,
			'utm_medium'   => 'banner',
			'utm_campaign' => 'upgrade-pro',
		),
		"https://www.pricebasedcountry.com/{$path}/"
	);
}

/**
 * Return an array of product type supported
 *
 * @since 1.7.0
 * @param string $source basic|pro|third-party.
 * @param string $context Context to use the function.
 * @return boolean
 */
function wcpbc_product_types_supported( $source = '', $context = '' ) {

	$types = array(
		'basic' => array(
			'simple'   => 'Simple product',
			'grouped'  => 'Grouped product',
			'external' => 'External/Affiliate product',
			'variable' => 'Variable product',
		),
		'pro'   => array(
			'bundle'                => 'WooCommerce Product Bundles',
			'booking'               => 'WooCommerce Bookings',
			'accommodation-booking' => 'WooCommerce Accommodation Bookings',
			'nyp-wcpbc'             => 'WooCommerce Name Your Price',
			'job_package'           => 'Listing Payments by Astoundify',
		),
	);

	if ( 'product-data' !== $context || ! class_exists( 'WCPBC_Subscriptions' ) ) {
		$types['pro']['subscription']          = 'WooCommerce Subscriptions';
		$types['pro']['variable-subscription'] = 'WooCommerce Subscriptions';
	}

	if ( class_exists( 'WC_Composite_Products' ) ) {
		$types['pro']['composite'] = 'WooCommerce Composite Products';
	}

	if ( class_exists( 'learndash_woocommerce' ) ) {
		$types['basic']['course'] = 'Course';
	}

	$types['third-party'] = apply_filters( 'wc_price_based_country_third_party_product_types', array() );

	if ( empty( $source ) ) {
		$types = array_merge( $types['basic'], $types['pro'], $types['third-party'] );
	} elseif ( 'pro' === $source ) {
		$types = $types['pro'];
	} elseif ( 'basic' === $source ) {
		$types = $types['basic'];
	} else {
		$types = $types['third-party'];
	}

	return $types;
}

/**
 * Return an array with the countries that use a specific currency.
 *
 * @version 4.3.0
 * @param string $currency_code Currency code.
 * @return array
 */
function wcpbc_get_currencies_countries( $currency_code ) {

	if ( ! ( function_exists( 'WC' ) && is_callable( [ WC(), 'plugin_path' ] ) && file_exists( WC()->plugin_path() . '/i18n/locale-info.php' ) ) ) {
		return [];
	}

	$locale_info = include WC()->plugin_path() . '/i18n/locale-info.php';
	$countries   = [];
	foreach ( $locale_info as $country => $info ) {
		if ( isset( $info['currency_code'] ) && $info['currency_code'] === $currency_code ) {
			$countries[] = $country;
		}
	}
	return $countries;
}
