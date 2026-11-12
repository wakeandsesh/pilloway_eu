<?php
/**
 * Dynamic properties for WC_Data objects using meta.
 *
 * Allows to store additional properties on WC_Data objects that expires on the object destroy.
 *
 * @since 4.2.0
 * @package WC_BOGOF
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Runtime_Meta class
 */
class WCPBC_Runtime_Meta {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_filter( 'add_post_metadata', [ __CLASS__, 'reject_metadata' ], 9999, 3 );
	}

	/**
	 * Do not update the runtime meta.
	 *
	 * @param null|bool $check      Whether to allow adding metadata for the given type.
	 * @param int       $post_id    ID of the object metadata is for.
	 * @param string    $meta_key   Metadata key.
	 */
	public static function reject_metadata( $check, $post_id, $meta_key ) {
		if ( __CLASS__ === $meta_key ) {
			$check = true;
		}
		return $check;
	}

	/**
	 * Reads the meta data.
	 *
	 * @param WC_Data $data WC_Data object.
	 * @return array
	 */
	private static function read( $data ) {
		$meta = is_callable( [ $data, 'get_meta' ] ) ? $data->get_meta( __CLASS__, true, 'edit' ) : [];
		$meta = is_array( $meta ) ? $meta : [];

		return $meta;
	}

	/**
	 * Updates the meta data.
	 *
	 * @param WC_Data $data WC_Data object.
	 * @param array   $meta Data to save.
	 */
	private static function update( $data, $meta ) {
		$data->update_meta_data(
			__CLASS__,
			$meta
		);
	}

	/**
	 * Returns a property by key.
	 *
	 * @param WC_Data $data WC_Data object.
	 * @param string  $key Property name.
	 * @return mixed
	 */
	public static function get( $data, $key ) {
		$meta = self::read( $data );
		return isset( $meta[ $key ] ) ? $meta[ $key ] : false;
	}

	/**
	 * Sets a property by key.
	 *
	 * @param WC_Data $data WC_Data object.
	 * @param string  $key Property name.
	 * @param mixed   $value Value.
	 */
	public static function set( $data, $key, $value ) {
		$meta         = self::read( $data );
		$meta[ $key ] = $value;
		self::update( $data, $meta );
	}

	/**
	 * Unsets a property by key.
	 *
	 * @param WC_Data $data WC_Data object.
	 * @param string  $key Property name.
	 */
	public static function unset( $data, $key ) {
		$meta = self::read( $data );
		unset( $meta[ $key ] );
		self::update( $data, $meta );
	}

	/**
	 * Check if a propery is set.
	 *
	 * @param WC_Data $data WC_Data object.
	 * @param string  $key Property name.
	 */
	public static function isset( $data, $key ) {
		$meta = self::read( $data );
		return isset( $meta[ $key ] );
	}
}
