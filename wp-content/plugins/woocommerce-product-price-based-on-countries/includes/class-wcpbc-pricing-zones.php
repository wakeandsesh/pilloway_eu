<?php
/**
 * Handles storage and retrieval of pricing zones
 *
 * @version 1.8.0
 * @since   1.7.0
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pricing zones class.
 */
class WCPBC_Pricing_Zones {

	/**
	 * Return the pricing zone class.
	 *
	 * @return string
	 */
	private static function get_pricing_zone_class_name() {
		$classname = 'WCPBC_Pricing_Zone';
		if ( wcpbc_is_pro() && class_exists( 'WCPBC_Pricing_Zone_Pro' ) ) {
			$classname = 'WCPBC_Pricing_Zone_Pro';
		}

		return $classname;
	}

	/**
	 * Return a empty pricing zone object.
	 *
	 * @return WCPBC_Pricing_Zone
	 */
	public static function create() {
		$classname = self::get_pricing_zone_class_name();
		return new $classname();
	}

	/**
	 * Save a zone.
	 *
	 * @since 1.8.0
	 * @param WCPBC_Pricing_Zone $zone Zone instance.
	 * @return int|WP_error Object's ID or WP_error if failure.
	 */
	public static function save( $zone ) {
		static $doing_save = false;

		if ( $doing_save ) {
			return false;
		}

		$doing_save = true;

		$zones = (array) get_option( 'wc_price_based_country_regions', array() );

		if ( ! $zone->get_id() ) {
			$zone_id = self::get_unique_slug( sanitize_key( sanitize_title( $zone->get_name() ) ), array_keys( $zones ) );
			$zone->set_id( $zone_id );
		} else {
			$zone_id = $zone->get_id();
		}

		$error = false;

		if ( wcpbc_is_pro() ) {
			$error = WCPBC_Update_Exchange_Rates::update_exchange_rate( $zone );
		}

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		// Save the zone.
		$zone_data = $zone->get_data();
		unset( $zone_data['zone_id'] );

		$zones[ $zone_id ] = $zone_data;

		update_option( 'wc_price_based_country_regions', $zones );

		$doing_save = false;

		return $zone_id;
	}

	/**
	 * Save a group zones.
	 *
	 * @version 2.3.0 Include override param.
	 * @since 1.8.0
	 * @param array $zones Array of pricing zones.
	 * @param bool  $override Should the function override the current pricing zones?.
	 */
	public static function bulk_save( $zones, $override = false ) {
		$azones = $override ? array() : (array) get_option( 'wc_price_based_country_regions', array() );

		foreach ( $zones as $zone ) {

			if ( ! $zone->get_id() ) {
				$zone_id = self::get_unique_slug( sanitize_key( sanitize_title( $zone->get_name() ) ), array_keys( $azones ) );
				$zone->set_id( $zone_id );
			} else {
				$zone_id = $zone->get_id();
			}

			$zone_data = $zone->get_data();
			unset( $zone_data['zone_id'] );

			$azones[ $zone_id ] = $zone_data;
		}

		update_option( 'wc_price_based_country_regions', $azones );
	}

	/**
	 * Get a unique slug that indentify a zone
	 *
	 * @since 1.8.0
	 * @param string $new_slug New slug.
	 * @param array  $slugs All IDs of the zones.
	 * @return array
	 */
	private static function get_unique_slug( $new_slug, $slugs ) {

		$new_slug = 'new' === $new_slug ? 'new_' : $new_slug; // "new" is invalid for ID.

		$seq = 0;

		foreach ( $slugs as $slug ) {
			$parts = explode( $new_slug . '-', $slug );

			if ( isset( $parts[1] ) && absint( $parts[1] ) > $seq ) {
				$seq = absint( $parts[1] ) + 1;
			} elseif ( $parts[0] === $new_slug && $seq < 1 ) {
				$seq = 1;
			}
		}

		if ( $seq > 0 ) {
			$new_slug = $new_slug . '-' . $seq;
		}

		return $new_slug;
	}

	/**
	 * Delete a zone.
	 *
	 * @since 1.8.0
	 * @param WCPBC_Pricing_Zone $zone Zone instance.
	 */
	public static function delete( $zone ) {
		global $wpdb;

		$zones = (array) get_option( 'wc_price_based_country_regions', array() );

		if ( isset( $zones[ $zone->get_id() ] ) ) {
			unset( $zones[ $zone->get_id() ] );
			update_option( 'wc_price_based_country_regions', $zones );
		}
	}

	/**
	 * Get pricing zones.
	 *
	 * @param array $zone_ids Array of IDs of Pricing zones to filter the result. Optional. False return all.
	 * @return array Array of WCPBC_Pricing_Zone instances.
	 */
	public static function get_zones( $zone_ids = false ) {
		$classname = self::get_pricing_zone_class_name();
		$zones     = array();

		foreach ( (array) get_option( 'wc_price_based_country_regions', array() ) as $id => $data ) {
			if ( ! empty( $zone_ids ) && is_array( $zone_ids ) && ! in_array( $id, $zone_ids, true ) ) {
				continue;
			}

			$zones[ $id ] = new $classname();
			$zones[ $id ]->set_props( array_merge( $data, array( 'id' => $id ) ) );
		}

		uasort( $zones, array( __CLASS__, 'sort_callback' ) );

		return $zones;
	}

	/**
	 * Zones sort callback.
	 *
	 * @param WCPBC_Pricing_Zone $zone_a Zone to compare.
	 * @param WCPBC_Pricing_Zone $zone_b Zone to compare.
	 * @return int
	 */
	private static function sort_callback( $zone_a, $zone_b ) {
		if ( $zone_a->get_order() === $zone_b->get_order() ) {
			return 0;
		}

		return ( $zone_a->get_order() < $zone_b->get_order() ? -1 : 1 );
	}

	/**
	 * Get a pricing zone.
	 *
	 * @param mixed $the_zone WC_Pricing_Zone|array|string|bool Pricing zone instance, array of pricing zone properties, pricing zone ID, or false to return the current pricing zone.
	 * @return WCPBC_Pricing_Zone
	 */
	public static function get_zone( $the_zone = false ) {
		$zone      = false;
		$classname = self::get_pricing_zone_class_name();

		if ( is_object( $the_zone ) && in_array( get_class( $the_zone ), array( 'WCPBC_Pricing_Zone', 'WCPBC_Pricing_Zone_Pro' ), true ) ) {
			$zone = $the_zone;
		} elseif ( is_array( $the_zone ) ) {
			$zone = new $classname( $the_zone );
		} elseif ( ! $the_zone ) {
			$zone = WCPBC()->current_zone;
		} else {
			$zone = self::get_zone_by_id( $the_zone );
		}

		return $zone;
	}

	/**
	 * Get pricing zone by an ID.
	 *
	 * @param string $id Pricing zone ID.
	 * @return WCPBC_Pricing_Zone
	 */
	public static function get_zone_by_id( $id ) {
		$zone      = null;
		$zones     = (array) get_option( 'wc_price_based_country_regions', array() );
		$classname = self::get_pricing_zone_class_name();

		if ( ! empty( $zones[ $id ] ) ) {
			$zone = new $classname( array_merge( $zones[ $id ], array( 'id' => $id ) ) );
		}

		return $zone;
	}

	/**
	 * Get pricing zone by country.
	 *
	 * @param string $country Country code.
	 * @param bool   $skip_disabled Skip disable zones. Default true.
	 * @return WCPBC_Pricing_Zone
	 */
	public static function get_zone_by_country( $country, $skip_disabled = true ) {

		$zone      = null;
		$zones     = (array) get_option( 'wc_price_based_country_regions', array() );
		$classname = self::get_pricing_zone_class_name();

		foreach ( $zones as $key => $zone_data ) {

			$enabled = isset( $zone_data['enabled'] ) ? $zone_data['enabled'] : 'yes';
			if ( 'yes' !== $enabled && $skip_disabled ) {
				// Skip disabled.
				continue;
			}

			if ( in_array( $country, $zone_data['countries'], true ) ) {
				$zone = new $classname( array_merge( $zone_data, array( 'id' => $key ) ) );
				break;
			}
		}
		return $zone;
	}

	/**
	 * Return a pricing zone from an order.
	 *
	 * @param mixed $value WC_Order|int Order instance or order ID.
	 * @return WCPBC_Pricing_Zone
	 */
	public static function get_zone_from_order( $value ) {
		$zone  = false;
		$order = false;

		if ( is_numeric( $value ) ) {
			$order = wc_get_order( $value );
		} elseif ( is_a( $value, 'WC_Order' ) ) {
			$order = $value;
		} elseif ( ! empty( $value->ID ) ) {
			$order = wc_get_order( $value->ID );
		}

		if ( $order ) {
			$data = $order->get_meta( '_wcpbc_pricing_zone' );

			if ( $data ) {
				$zone = self::get_zone( $data );
			} else {
				// Find zone by order country.
				if ( 'billing' === get_option( 'wc_price_based_country_based_on', 'billing' ) ) {
					$country = $order->get_billing_country();
				} else {
					$country = $order->get_shipping_country();
				}

				$zone = self::get_zone_by_country( $country, false );
			}
		}
		return $zone;
	}

	/**
	 * Return the allowed countries for a zone.
	 *
	 * @param WCPBC_Pricing_Zone $zone Zone instance.
	 * @return array
	 */
	public static function get_allowed_countries( $zone ) {
		$not_allowed_countries = [];
		$all_countries         = WC()->countries->get_countries();
		foreach ( self::get_zones() as $_zone ) {
			foreach ( array_diff( $_zone->get_countries(), $zone->get_countries() ) as $country ) {
				$not_allowed_countries[ $country ] = $all_countries[ $country ];
			}
		}

		return array_diff_key( $all_countries, $not_allowed_countries );
	}

	/**
	 * Return currency exchange rates to convert to base currency.
	 *
	 * @return array
	 */
	public static function get_currency_rates() {
		$rates         = array();
		$base_currency = wcpbc_get_base_currency();

		foreach ( self::get_zones() as $zone ) {
			if ( $base_currency !== $zone->get_currency() ) {
				$rates[ $zone->get_currency() ] = $zone->get_real_exchange_rate();
			}
		}

		return $rates;
	}

	/**
	 * Is there is pricing zones?
	 *
	 * @return bool
	 */
	public static function has_zones() {
		$zones = (array) get_option( 'wc_price_based_country_regions', array() );
		return count( $zones );
	}

	/**
	 * Set the properties of a pricing zone with the values of an non-sanitize data array.
	 *
	 * @since 2.3.0
	 * @param WCPBC_Pricing_Zone $zone Pricing zone.
	 * @param array              $postdata Key value data array.
	 * @return bool|WP_Error
	 */
	public static function populate( $zone, $postdata ) {

		$settings = include dirname( __FILE__ ) . '/admin/settings/wcpbc-settings-zone.php';
		$props    = array();

		foreach ( $settings as $section ) {

			if ( ! empty( $section['fields'] ) ) {

				foreach ( $section['fields'] as $field ) {

					$is_pro_option  = ! empty( $field['is_pro'] );
					$include_option = isset( $field['id'], $field['value'], $field['type'] ) &&
						( ( $is_pro_option && wcpbc_is_pro() ) || ( ! $is_pro_option ) );

					if ( ! $include_option ) {
						continue;
					}

					$raw_value = isset( $postdata[ $field['id'] ] ) ? $postdata[ $field['id'] ] : null;

					if ( is_null( $raw_value ) ) {

						// Set a default value.
						switch ( $field['type'] ) {
							case 'true-false':
								$value = 'no';
								break;
							case 'country-select':
								$value = array();
								break;
							default:
								$value = '';
								break;
						}
					} else {

						$sanitize_callback = 'sanitize_' . $field['id'];
						$value             = is_callable( array( __CLASS__, $sanitize_callback ) ) ? self::{$sanitize_callback}( $raw_value, $field ) : wc_clean( $raw_value );
					}

					$props[ $field['id'] ] = $value;
				}
			}
		}

		return $zone->set_props( $props );
	}

	/**
	 * Sanitize countries property.
	 *
	 * @param string $raw_value Raw value.
	 * @return array
	 */
	private static function sanitize_countries( $raw_value ) {
		if ( ! is_array( $raw_value ) ) {
			$raw_value = array_map( 'trim', explode( ',', strval( $raw_value ) ) );
		}

		return array_intersect( array_keys( wc()->countries->get_countries() ), $raw_value );
	}

	/**
	 * Sanitize currency.
	 *
	 * @param string $raw_value Raw value.
	 * @return array
	 */
	private static function sanitize_currency( $raw_value ) {
		if ( in_array( $raw_value, array_keys( get_woocommerce_currencies() ), true ) ) {
			return wc_clean( $raw_value );
		}

		return '';
	}

	/**
	 * Sanitize exchange_rate.
	 *
	 * @param string $raw_value Raw value.
	 * @return array
	 */
	private static function sanitize_exchange_rate( $raw_value ) {
		if ( wcpbc_empty_nozero( $raw_value ) ) {
			return '1';
		}

		return wc_clean( $raw_value );
	}

	/**
	 * Sanitize currency_format
	 *
	 * @param string $raw_value Raw value.
	 * @return string
	 */
	private static function sanitize_currency_format( $raw_value ) {
		if ( is_callable( array( 'WCPBC_Admin_Pro', 'sanitize_currency_format' ) ) ) {
			return WCPBC_Admin_Pro::sanitize_currency_format( $raw_value );
		}
		return '';
	}

	/**
	 * Sanitize price_thousand_sep
	 *
	 * @param string $raw_value Raw value.
	 * @return string
	 */
	private static function sanitize_price_thousand_sep( $raw_value ) {
		$raw_value = preg_replace( '/\s+/', ' ', $raw_value );
		$raw_value = ' ' === $raw_value ? '&nbsp;' : $raw_value;
		return wc_clean( $raw_value );
	}

	/**
	 * Sanitize price_thousand_sep
	 *
	 * @param string $raw_value Raw value.
	 * @return string
	 */
	private static function sanitize_price_decimal_sep( $raw_value ) {
		return self::sanitize_price_thousand_sep( $raw_value );
	}

	/**
	 * Sanitize round_nearest
	 *
	 * @param string $raw_value Raw value.
	 * @param array  $field Array of field properties.
	 * @return string
	 */
	private static function sanitize_round_nearest( $raw_value, $field ) {
		$raw_value = empty( $raw_value ) ? '' : $raw_value;
		if ( ! in_array( strval( $raw_value ), array_map( 'strval', array_keys( $field['options'] ) ), true ) ) {
			return '';
		}
		return wc_clean( $raw_value );
	}
}
