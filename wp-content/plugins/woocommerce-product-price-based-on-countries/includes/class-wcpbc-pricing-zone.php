<?php
/**
 * Represents a single pricing zone
 *
 * @since   1.7.0
 * @version 1.7.13
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCPBC_Pricing_Zone
 */
class WCPBC_Pricing_Zone {

	/**
	 * Zone data.
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * Constructor for zones.
	 *
	 * @param array $data Pricing zone attributes as array.
	 */
	public function __construct( $data = null ) {
		$this->set_defaults();
		if ( is_array( $data ) && ! empty( $data ) ) {
			$this->set_props( $data );
		}
		add_action( 'updated_postmeta', [ $this, 'maybe_cache_flush' ], 10, 3 );
	}

	/**
	 * Set defaults properties.
	 */
	protected function set_defaults() {
		$this->data = array(
			'zone_id'                => '',
			'enabled'                => 'yes',
			'name'                   => '',
			'countries'              => array(),
			'currency'               => get_option( 'woocommerce_currency' ),
			'exchange_rate'          => '1',
			'auto_exchange_rate'     => 'no',
			'disable_tax_adjustment' => function_exists( 'wc_prices_include_tax' ) && wc_prices_include_tax() ? 'yes' : 'no',
			'order'                  => 9999,
		);
	}

	/**
	 * Get zone data.
	 *
	 * @return array
	 */
	public function get_data() {
		$data = $this->data;
		unset( $data['_cache'] );

		return $data;
	}

	/**
	 * Set a collection of props in one go, collect any errors, and return the result.
	 * Only sets using public methods.
	 *
	 * @since  3.0.0
	 *
	 * @param array $props Key value pairs to set. Key is the prop and should map to a setter function name.
	 *
	 * @return bool|WP_Error
	 */
	public function set_props( $props ) {
		$errors = false;
		$setter = '';

		foreach ( $props as $prop => $value ) {
			try {

				if ( 'zone_id' === $prop ) {
					// Backwards compatibility.
					$setter = 'set_id';
				} else {
					$setter = "set_$prop";
				}

				if ( is_callable( array( $this, $setter ) ) ) {
					$this->{$setter}( $value );
				}
			} catch ( Exception $e ) {
				if ( ! $errors ) {
					$errors = new WP_Error();
				}
				$errors->add( 'invalid_data_' . $setter, $e->getMessage() );
			}
		}

		return $errors && count( $errors->get_error_codes() ) ? $errors : true;
	}

	/**
	 * Gets a prop for a getter method.
	 *
	 * @since 1.7.9
	 * @param  string $prop Name of prop to get.
	 * @return mixed
	 */
	protected function get_prop( $prop ) {
		return isset( $this->data[ $prop ] ) ? $this->data[ $prop ] : false;
	}

	/**
	 * Sets a prop for a setter method.
	 *
	 * @since 1.8.0
	 * @param string $prop Name of prop to set.
	 * @param mixed  $value Value to set.
	 */
	protected function set_prop( $prop, $value ) {
		if ( isset( $this->data[ $prop ] ) ) {
			$this->data[ $prop ] = $value;
			$this->cache_flush();
		}
	}

	/**
	 * Set a cache value for the given post ID.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key    The cache key to use for retrieval later.
	 * @param mixed  $data   The contents to store in the cache.
	 */
	protected function cache_set( $post_id, $key, $data ) {
		if ( ! isset( $this->data['_cache'] ) ) {
			$this->data['_cache'] = [];
		}
		if ( ! isset( $this->data['_cache'][ $post_id ] ) ) {
			$this->data['_cache'][ $post_id ] = [];
		}

		$this->data['_cache'][ $post_id ][ $key ] = $data;
	}

	/**
	 * Get a cached value for the given post ID.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key    The key under which the cache contents are stored.
	 * @return mixed|false The cache contents on success, false on failure to retrieve contents.
	 */
	protected function cache_get( $post_id, $key ) {
		return isset( $this->data['_cache'][ $post_id ][ $key ] ) ? $this->data['_cache'][ $post_id ][ $key ] : false;
	}

	/**
	 * Remove all cache items for the given post ID. All items if post ID is empty.
	 *
	 * @param int $post_id Post ID.
	 */
	protected function cache_flush( $post_id = false ) {
		if ( ! $post_id ) {
			unset( $this->data['_cache'] );
		} else {
			unset( $this->data['_cache'][ $post_id ] );
		}
	}

	/**
	 * Flush cache on update post meta.
	 *
	 * @param int    $meta_id    ID of updated metadata entry.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Metadata key.
	 */
	public function maybe_cache_flush( $meta_id, $object_id, $meta_key ) {
		if ( isset( $this->data['_cache'][ $object_id ] ) ) {
			$this->cache_flush( $object_id );
		}
	}

	/**
	 * Set zone id.
	 *
	 * @throws Exception Throws exception when invalid data is found.
	 * @param string $id Zone ID.
	 */
	public function set_id( $id ) {
		if ( 'new' === $id ) {
			throw new Exception( esc_html__( 'Invalid value for the zone ID. Use a different value.', 'woocommerce-product-price-based-on-countries' ) );
		}
		$this->set_prop( 'zone_id', $id );
	}

	/**
	 * Get zone id.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->get_prop( 'zone_id' );
	}

	/**
	 * Get Enabled property.
	 *
	 * @return string
	 */
	public function get_enabled() {
		return 'yes' === $this->get_prop( 'enabled' );
	}

	/**
	 * Set Enabled property.
	 *
	 * @param string $value Enabled? yes/no.
	 */
	public function set_enabled( $value ) {
		$this->set_prop( 'enabled', ( 'yes' === $value ? 'yes' : 'no' ) );
	}

	/**
	 * Get zone name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->get_prop( 'name' );
	}

	/**
	 * Set the zone name.
	 *
	 * @throws Exception Throws exception when invalid data is found.
	 * @param string $name Zone name.
	 */
	public function set_name( $name ) {
		if ( empty( $name ) ) {
			throw new Exception( esc_html__( 'Name is required.', 'woocommerce-product-price-based-on-countries' ) );
		}
		$this->set_prop( 'name', trim( $name ) );
	}

	/**
	 * Get countries.
	 *
	 * @return array
	 */
	public function get_countries() {
		return $this->get_prop( 'countries' );
	}

	/**
	 * Set countries of the zone.
	 *
	 * @throws Exception Throws exception when invalid data is found.
	 * @param array $countries Countries.
	 */
	public function set_countries( $countries ) {
		if ( empty( $countries ) || ! is_array( $countries ) ) {
			throw new Exception( esc_html__( 'Add at least one country to the zone.', 'woocommerce-product-price-based-on-countries' ) );
		}
		$this->set_prop( 'countries', $countries );
	}

	/**
	 * Get zone currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->get_prop( 'currency' );
	}

	/**
	 * Set the zone currency.
	 *
	 * @throws Exception Throws exception when invalid data is found.
	 * @param string $currency Zone currency.
	 */
	public function set_currency( $currency ) {
		if ( empty( $currency ) ) {
			throw new Exception( esc_html__( 'A valid currency is required.', 'woocommerce-product-price-based-on-countries' ) );
		}
		$this->set_prop( 'currency', $currency );
	}

	/**
	 * Get exchange rate.
	 *
	 * @return float
	 */
	public function get_exchange_rate() {
		return floatval( $this->get_prop( 'exchange_rate' ) );
	}

	/**
	 * Get exchange rate.
	 *
	 * @since 1.9.0
	 * @return float
	 */
	public function get_real_exchange_rate() {
		return $this->get_currency() === wcpbc_get_base_currency() ? 1 : $this->get_exchange_rate();
	}

	/**
	 * Set the zone exchange rate.
	 *
	 * @throws Exception Throws exception when invalid data is found.
	 * @param float $exchange_rate Zone exchange_rate.
	 */
	public function set_exchange_rate( $exchange_rate ) {
		if ( empty( $exchange_rate ) ) {
			throw new Exception( esc_html__( 'The exchange rate must be nonzero.', 'woocommerce-product-price-based-on-countries' ) );
		}
		$this->set_prop( 'exchange_rate', is_float( $exchange_rate ) ? wcpbc_float_to_string( $exchange_rate ) : wc_format_decimal( $exchange_rate ) );
	}

	/**
	 * Get disable tax adjustment.
	 *
	 * @return bool
	 */
	public function get_disable_tax_adjustment() {
		return 'yes' === $this->get_prop( 'disable_tax_adjustment' ) && wc_prices_include_tax();
	}

	/**
	 * Set disable tax adjustment.
	 *
	 * @param string $disable Yes or No.
	 */
	public function set_disable_tax_adjustment( $disable ) {
		$this->set_prop( 'disable_tax_adjustment', ( 'yes' === $disable ? 'yes' : 'no' ) );
	}

	/**
	 * Get the zone's order.
	 *
	 * @return int
	 */
	public function get_order() {
		return $this->get_prop( 'order' );
	}

	/**
	 * Set the zone order.
	 *
	 * @param int $value Order.
	 */
	public function set_order( $value ) {
		$this->set_prop( 'order', absint( $value ) );
	}

	/**
	 * Save the object.
	 *
	 * @since 2.3.0
	 * @return int|WP_error Object's ID or WP_error if failure.
	 */
	public function save() {
		return WCPBC_Pricing_Zones::save( $this );
	}

	/**
	 * Get a meta key based on zone ID
	 *
	 * @param string $meta_key Metadata key.
	 * @return string
	 */
	public function get_postmetakey( $meta_key = '' ) {
		return esc_attr( '_' . $this->get_id() . $meta_key );
	}

	/**
	 * Get a meta value based on zone ID
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @return mixed
	 */
	public function get_postmeta( $post_id, $meta_key = false ) {
		if ( $meta_key ) {
			$value = get_post_meta( $post_id, $this->get_postmetakey( $meta_key ), true );
		} else {
			// Reads all metadata.
			$value            = [];
			$meta_prefix      = $this->get_postmetakey();
			$post_meta_values = get_post_meta( $post_id );

			if ( $post_meta_values && is_array( $post_meta_values ) ) {

				foreach ( $post_meta_values as $post_meta_key => $post_meta_value ) {
					if ( ! isset( $post_meta_value[0] ) || substr( $post_meta_key, 0, strlen( $meta_prefix ) ) !== $meta_prefix ) {
						continue;
					}

					$key           = substr( $post_meta_key, strlen( $meta_prefix ) );
					$value[ $key ] = maybe_unserialize( $post_meta_value[0] );
				}
			}
		}

		return $value;
	}

	/**
	 * Add meta data field to a post
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @param string $meta_value Metadata value.
	 * @return int|bool
	 */
	public function add_postmeta( $post_id, $meta_key, $meta_value ) {
		wc_doing_it_wrong( __METHOD__, __( 'Adding metadata is not supported.', 'woocommerce-product-price-based-on-countries' ), '4.0' );
		return false;
	}

	/**
	 * Update meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @param string $meta_value Metadata value.
	 * @param bool   $force Force the meta data update. Prevents issues with object cache plugins.
	 * @return int|bool
	 */
	public function set_postmeta( $post_id, $meta_key, $meta_value, $force = false ) {

		if ( is_float( $meta_value ) ) {
			$meta_value = wc_float_to_string( $meta_value );
		}

		$meta_id = update_post_meta( $post_id, $this->get_postmetakey( $meta_key ), $meta_value );

		$cache_delete = $force && false === $meta_id && wp_using_ext_object_cache();

		if ( $cache_delete ) {
			wp_cache_delete( $post_id, 'post_meta' );
		}

		if ( $meta_id || $cache_delete ) {
			$this->updated_meta( $post_id, $meta_key );
		}

		return $meta_id;
	}

	/**
	 * Remove metadata from a post
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @return bool True on success, false on failure.
	 */
	public function delete_postmeta( $post_id, $meta_key ) {
		$deleted = delete_post_meta( $post_id, $this->get_postmetakey( $meta_key ) );
		if ( $deleted ) {
			$this->updated_meta( $post_id, $meta_key );
		}
		return $deleted;
	}

	/**
	 * Run actions after updated a meta key.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 */
	protected function updated_meta( $post_id, $meta_key ) {
		WCPBC_Product_Meta_Data::maybe_enqueue_multilang_sync( $post_id, $this->get_id() );
		if ( '_price' === $meta_key ) {
			WCPBC_Product_Meta_Data::maybe_enqueue_children_sync( $post_id, $this->get_id() );
			WCPBC_Product_Meta_Data::delete_product_transients( $post_id );
		}
	}

	/**
	 * Product price by exchange rate?
	 *
	 * @param \WC_Data|int $data Object instance or Post ID.
	 * @return bool
	 */
	public function is_exchange_rate_price( $data ) {
		$post_id = is_callable( [ $data, 'get_id' ] ) ? $data->get_id() : absint( $data );
		$cache   = $this->cache_get( $post_id, __FUNCTION__ );

		if ( false !== $cache ) {
			return 'true' === $cache;
		}

		$price_method     = $this->get_postmeta( $post_id, '_price_method' );
		$product_type     = is_callable( [ $data, 'get_type' ] ) ? $data->get_type() : WC_Product_Factory::get_product_type( $post_id );
		$is_exchange_rate = wcpbc_is_exchange_rate( $price_method ) && ! in_array( $product_type, wcpbc_wrapper_product_types(), true );

		$this->cache_set( $post_id, __FUNCTION__, ( $is_exchange_rate ? 'true' : 'false' ) );

		return $is_exchange_rate;
	}

	/**
	 * Return product price calculate by exchange rate
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @param bool   $deprecated Must be the price round?.
	 * @return float
	 */
	public function get_exchange_rate_price_by_post( $post_id, $meta_key, $deprecated = false ) {
		if ( $deprecated ) {
			wc_deprecated_argument( 'round', '2.0.0', 'Use WCPBC_Pricing_Zone::get_post_price method instead' );
		}
		$base_price = get_post_meta( $post_id, $meta_key, true );
		return $this->get_exchange_rate_price( $base_price, false );
	}

	/**
	 * Return a price calculate by exchange rate
	 *
	 * @param float  $price The base price to convert.
	 * @param bool   $round Must be the price round?.
	 * @param string $context What the value is for?. Default "generic".
	 * @param mixed  $data Source of the price.
	 * @return float
	 */
	public function get_exchange_rate_price( $price, $round = true, $context = 'generic', $data = null ) {
		if ( empty( $price ) ) {
			$value = $price;
		} else {
			$value = $this->by_exchange_rate( $price, $context );
			if ( $round ) {
				$value = $this->round( $value, '', $context, $data );
			} else {
				// Round to round precision.
				$value = round( $value, $this->get_rounding_precision() );
			}
		}

		return $value;
	}

	/**
	 * Get rounding precision.
	 *
	 * @since 4.3.0
	 * @return int
	 */
	public function get_rounding_precision() {
		$num_decimals = wc_get_price_decimals();

		if ( is_callable( [ $this, 'get_price_num_decimals' ] ) && '' !== $this->get_price_num_decimals() ) {
			$num_decimals = $this->get_price_num_decimals();
		}

		return $num_decimals + 3;
	}

	/**
	 * Apply the exchange rate to an amount
	 *
	 * @param float  $amount Amount to apply the exchange rate.
	 * @param string $context What the value is for?. Default "generic".
	 * @return float
	 */
	protected function by_exchange_rate( $amount, $context = 'generic' ) {
		return floatval( $amount ) * $this->get_exchange_rate();
	}

	/**
	 * Round a price
	 *
	 * @param float  $price Amount to round.
	 * @param float  $num_decimals Number of decimals.
	 * @param string $context What the value is for?. Default "generic".
	 * @param mixed  $data Source of the price.
	 * @return float
	 */
	protected function round( $price, $num_decimals = '', $context = 'generic', $data = null ) {
		if ( wcpbc_empty_nozero( $num_decimals ) ) {
			$num_decimals = wc_get_price_decimals();
		}

		$value = $price;

		if ( ! empty( $value ) ) {
			$value = round( $value, $num_decimals );
		}
		return $value;
	}

	/**
	 * Maybe update an exchange rate price.
	 *
	 * @since 4.0.0
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @param float  $compare Price to compare. Optional.
	 */
	protected function maybe_update_price( $post_id, $meta_key, $compare = false ) {
		if ( '_price' !== $meta_key ) {
			return;
		}

		$compare = false === $compare ? $this->get_exchange_rate_price_by_post( $post_id, $meta_key ) : $compare;
		$price   = $this->get_postmeta( $post_id, $meta_key );

		if ( empty( $compare ) && ! is_numeric( $compare ) && ! empty( $price ) ) {
			$this->set_postmeta( $post_id, $meta_key, '', true );
			return;
		}

		$epsilon = pow( 10, -1 * $this->get_rounding_precision() );

		if ( abs( floatval( $compare ) - floatval( $price ) ) > $epsilon ) {
			$this->set_postmeta( $post_id, $meta_key, strval( $compare ), true );
		}
	}

	/**
	 * Get a price metada from a post ID.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @param string $context What the value is for?. Default "product".
	 * @return mixed
	 */
	public function get_post_price( $post_id, $meta_key, $context = 'product' ) {

		if ( $this->is_exchange_rate_price( $post_id ) ) {

			$cache = $this->cache_get( $post_id, $meta_key );
			if ( false !== $cache ) {
				return $cache;
			}

			$price = $this->get_exchange_rate_price_by_post( $post_id, $meta_key );

			$this->maybe_update_price( $post_id, $meta_key, $price );

			$price = $this->round( $price, '', $context );

			$this->cache_set( $post_id, $meta_key, $price );

		} else {
			$price = $this->get_postmeta( $post_id, $meta_key );
		}

		return $price;
	}

	/**
	 * Get a price property.
	 *
	 * @since 1.9
	 *
	 * @param WC_Data $data Object instance.
	 * @param mixed   $value Original value of the propery.
	 * @param string  $meta_key Metadata key.
	 * @param string  $context What the value is for?. Default "product".
	 * @return mixed
	 */
	public function get_price_prop( $data, $value, $meta_key, $context = 'product' ) {
		if ( ! ( is_object( $data ) && is_callable( array( $data, 'get_id' ) ) ) ) {
			return $value;
		}

		if ( WCPBC_Runtime_Meta::get( $data, $meta_key ) ) {
			return $value;
		}

		if ( $this->is_exchange_rate_price( $data ) ) {

			$cache_key = "{$meta_key}_{$value}";
			$cache     = $this->cache_get( $data->get_id(), $cache_key );

			if ( false !== $cache ) {
				return $cache;
			}

			$price = $this->get_exchange_rate_price( $value, true, $context, $data );

			$this->cache_set( $data->get_id(), $cache_key, $price );

			$this->maybe_update_price( $data->get_id(), $meta_key );

		} else {
			$price = $this->get_postmeta( $data->get_id(), $meta_key );
		}

		return $price;
	}

	/**
	 * Return a date property.
	 *
	 * @since 1.9
	 *
	 * @param WC_Data $data Object instance.
	 * @param mixed   $value Original value of the propery.
	 * @param string  $meta_key Metadata key.
	 * @return WC_DateTime
	 */
	public function get_date_prop( $data, $value, $meta_key ) {
		if ( ! ( is_object( $data ) && is_callable( array( $data, 'get_id' ) ) ) ) {
			return $value;
		}

		if ( 'manual' === $this->get_postmeta( $data->get_id(), '_sale_price_dates' ) && ! $this->is_exchange_rate_price( $data ) ) {
			try {

				$metadata = $this->get_postmeta( $data->get_id(), $meta_key );

				if ( is_numeric( $metadata ) ) {
					$datetime = new WC_DateTime( "@{$metadata}", new DateTimeZone( 'UTC' ) );

					// Set local timezone or offset.
					if ( get_option( 'timezone_string' ) ) {
						$datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
					} else {
						$datetime->set_utc_offset( wc_timezone_offset() );
					}

					$value = $datetime;

				} else {
					$value = '';
				}
			} catch ( Exception $e ) {} // @codingStandardsIgnoreLine.
		}

		return $value;
	}

	/**
	 * Return an amount in the shop base currency
	 *
	 * @since 1.7.4
	 * @version 1.9.0 Use real exchange rate to calculate the amont.
	 *
	 * @param float $amount Amount to convert to base currency.
	 * @return float
	 */
	public function get_base_currency_amount( $amount ) {
		$amount = floatval( $amount );
		return ( $amount / $this->get_real_exchange_rate() );
	}

	/**
	 * Helper function that return the value of a $_POST variable.
	 *
	 * @since 1.8.0
	 * @param string $key POST parameter name.
	 * @param int    $index If the POST value is a array, the index array to return.
	 * @return mixed
	 */
	public function get_input_var( $key, $index = false ) {
		$metakey = $this->get_postmetakey( $key );
		$value   = null;

		// phpcs:disable WordPress.Security.NonceVerification
		if ( false !== $index && isset( $_POST[ $metakey ][ $index ] ) ) {
			$value = wc_clean( wp_unslash( $_POST[ $metakey ][ $index ] ) );
		} elseif ( isset( $_POST[ $metakey ] ) ) {
			$value = wc_clean( wp_unslash( $_POST[ $metakey ] ) );
		}
		// phpcs:enable
		return $value;
	}
}
