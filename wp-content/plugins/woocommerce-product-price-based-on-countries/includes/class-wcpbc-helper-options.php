<?php
/**
 * An interface to the wc_price_based_country_helper_data entry in the wp_options table.
 *
 * @since 3.1.0
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Helper_Options Class
 */
class WCPBC_Helper_Options {

	/**
	 * The option name used to store the helper data.
	 *
	 * @var string
	 */
	private static $option_name = 'wc_price_based_country_helper_data';

	/**
	 * Reads the option array.
	 *
	 * @return array
	 */
	private static function read() {
		$options = get_option( self::$option_name, [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}
		return $options;
	}

	/**
	 * Update an option by key
	 *
	 * All helper options are grouped in a single options entry. This method
	 * is not thread-safe, use with caution.
	 *
	 * @param string $key The key to update.
	 * @param mixed  $value The new option value.
	 *
	 * @return bool True if the option has been updated.
	 */
	public static function update( $key, $value ) {
		$options         = self::read();
		$options[ $key ] = $value;
		return update_option( self::$option_name, $options, true );
	}

	/**
	 * Delete an option by key
	 *
	 * All helper options are grouped in a single options entry. This method
	 * is not thread-safe, use with caution.
	 *
	 * @param string $key The key to update.
	 *
	 * @return bool True if the option was deleted.
	 */
	public static function delete( $key ) {
		$options = self::read();
		unset( $options[ $key ] );
		return update_option( self::$option_name, $options, true );
	}

	/**
	 * Get an option by key
	 *
	 * @see self::update
	 *
	 * @param string $key The key to fetch.
	 * @param mixed  $default The default option to return if the key does not exist.
	 *
	 * @return mixed An option or the default.
	 */
	public static function get( $key, $default = false ) {
		$options = self::read();
		if ( is_array( $options ) && array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		return $default;
	}
}
