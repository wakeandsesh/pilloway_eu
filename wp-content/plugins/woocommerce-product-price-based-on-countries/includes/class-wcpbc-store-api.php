<?php
/**
 * Handle the Store API requests.
 *
 * @package WCPBC
 * @since 3.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WCPBC_Store_API' ) ) {
	return;
}

/**
 * WCPBC_Debug_Logger Class
 */
final class WCPBC_Store_API {

	/**
	 * Single instances of the class.
	 *
	 * @var WCPBC_Store_API
	 */
	private static $instance = null;

	/**
	 * Main instance.
	 *
	 * Ensures only one instance of the class is loaded.
	 *
	 * @return WCPBC_Store_API Class instance.
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {

		$path = $this->get_path();

		if ( ! in_array( $path, array( 'cart/update-customer' ), true ) ) {
			// Products block support. Needs customer info.
			$this->initialize_customer();
		} else {
			// Add the cart/checkout filter and actions.
			$this->init_store_api_cart_hooks();
		}

		if ( 'products/collection-data' === $path && ( isset( $_GET['min_price'] ) || isset( $_GET['max_price'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			add_filter( 'query', array( $this, 'price_filter_sql' ) );
		}
	}

	/**
	 * Init hooks.
	 */
	protected function init_store_api_cart_hooks() {
		add_action( 'wc_price_based_country_stop_pricing', '__return_true', 9999 ); // make sure frontend pricing does not run on cart/checkout requests.
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'store_api_cart_update_customer' ) );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'store_api_cart_update_customer' ) );
	}

	/**
	 * Modify the min/max price SQL query.
	 *
	 * @param string $query The SQL Query.
	 * @see Automattic\WooCommerce\StoreApi\Utilities\get_filtered_price
	 */
	public function price_filter_sql( $query ) {
		global $wpdb;

		if ( wcpbc_the_zone() && 'select min( min_price ) as min_price, max( max_price ) as max_price' === strtolower( substr( trim( $query ), 0, 67 ) ) ) {
			$query = WCPBC_Frontend_Pricing::price_filter_sql( $query );
		}

		return $query;
	}

	/**
	 * Init the frontend pricing after store API updated customer address.
	 */
	public function store_api_cart_update_customer() {
		wcpbc()->frontend_pricing_init();
	}

	/**
	 * Returns the store API path.
	 *
	 * @return string
	 */
	protected function get_path() {
		$path = false;
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return $path;
		}

		$uri_path = trailingslashit( wp_parse_url( wc_clean( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) );
		$pattern  = '/' . str_replace( '/', '\/', trailingslashit( rest_get_url_prefix() ) . 'wc/store/v[0-9]+/(.+)/' ) . '/';
		$matches  = array();

		if ( 1 === preg_match( $pattern, $uri_path, $matches ) && isset( $matches[1] ) ) {
			$path = $matches[1];
		}

		if ( 'batch' === $path ) {
			$path = $this->get_path_from_batch_request();
		}

		return $path;
	}

	/**
	 * Returns the store API path.
	 *
	 * @return string
	 */
	protected function get_path_from_batch_request() {
		$path = false;
		if ( ! is_callable( [ 'WP_REST_Server', 'get_raw_data' ] ) ) {
			return $path;
		}

		$raw_data = json_decode( WP_REST_Server::get_raw_data() );

		if ( isset( $raw_data->requests ) && is_array( $raw_data->requests ) && isset( $raw_data->requests[0]->path ) ) {
			$matches = array();
			$pattern = '/\/wc\/store\/v[0-9]+\/(.+)/';

			if ( 1 === preg_match( $pattern, $raw_data->requests[0]->path, $matches ) && isset( $matches[1] ) ) {
				$path = $matches[1];
			}
		}

		return $path;
	}

	/**
	 * Initialize the customer object.
	 */
	protected function initialize_customer() {
		if ( did_action( 'before_woocommerce_init' ) && ! doing_action( 'before_woocommerce_init' ) && function_exists( 'WC' ) && is_callable( array( WC(), 'initialize_session' ) ) && ( is_null( WC()->customer ) || ! WC()->customer instanceof WC_Customer ) ) {

			if ( ! function_exists( 'wc_get_chosen_shipping_method_ids' ) ) {
				// Frontend includes.
				WC()->frontend_includes();
			}

			WC()->initialize_session();
			WC()->customer = new WC_Customer( get_current_user_id(), true );
		}
	}

	/**
	 * Prevent object cloning
	 */
	protected function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce-product-price-based-on-countries' ), '4.6' );
		die();
	}
}
