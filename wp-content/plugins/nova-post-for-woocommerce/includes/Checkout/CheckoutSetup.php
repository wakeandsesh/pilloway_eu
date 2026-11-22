<?php
/**
 * Checkout Setup.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Checkout;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use NovaPostForWoocommerce\Adapters\ProductAdapter;
use NovaPostForWoocommerce\Helpers\MeasurementUnitConstants;
use NovaPostForWoocommerce\Validation\CheckoutPageValidator;
use NovaPostForWoocommerce\Widget\NovaPostWidgetIntegration;
use NovaPostForWoocommerce\Services\OrderService;
use NovaPostForWoocommerce\Models\UserStorage;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Services\ClassicCheckoutStateService;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodAddress;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodDivision;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutSetup.
 */
class CheckoutSetup {
    /**
     * Maximum parcel longest side in millimeters.
     */
    private const MAX_LONGEST_SIDE_MM = 3000;

    /**
     * Maximum parcel secondary sides in millimeters.
     */
    private const MAX_SECONDARY_SIDE_MM = 1700;

    /**
     * Construct.
     */
    public function __construct() {
        add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'register_checkout_block' ) );

        /**
         * Register cart extension data.
         *
         * @since WooCommerce 8.5.0
         */

        add_action( 'woocommerce_blocks_loaded', array( $this, 'register_endpoint_data' ) );

        if ( did_action( 'woocommerce_blocks_loaded' ) ) {
            $this->register_endpoint_data();
        }

        /**
         * Save division to order for old checkout
         *
         * @since WooCommerce 4.3.0
         */
        add_action( 'woocommerce_checkout_order_created', array( OrderService::class, 'add_division_to_order' ) );

        /**
         * Save division to order
         *
         * @since WooCommerce 7.2.0
         */
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( OrderService::class, 'add_division_to_order' ) );

        /**
         * Change shipping phone field configuration for old checkout
         *
         * @since WooCommerce 3.0.0
         */
        add_filter( 'woocommerce_checkout_fields', array( $this, 'change_checkout_fields' ) );

        /**
         * Validate checkout order before processed for old checkout
         *
         * @since WooCommerce 4.3.0
         */
        add_action( 'woocommerce_checkout_order_created', array( $this, 'validate_checkout_order_processed' ), 10, 3 );

        /**
         * Validate checkout order before processed
         *
         * @since WooCommerce 7.2.0
         */
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'validate_checkout_order_processed' ), 10, 3 );

        /**
         * Validate checkout order before processed
         *
         * @since WooCommerce 9.9.0
         */
        add_action( 'woocommerce_checkout_validate_order_before_payment', array( $this, 'validate_checkout_order_before_payment' ), 10, 3 );

        /**
         * Inject Nova Post state into classic checkout fragments.
         */
        add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'inject_checkout_fragments' ), 99 );

        add_filter( 'woocommerce_package_rates', array( $this, 'filter_package_rates' ), 100, 2 );

        add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'add_locale_to_shipping_packages' ) );
    }

    /**
     * Register cart extension data.
     */
    public function register_endpoint_data() {
        if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
            return;
        }

        woocommerce_store_api_register_endpoint_data(
            array(
                'endpoint'        => CartSchema::IDENTIFIER,
                'namespace'       => 'npforwc_widget_data',
                'data_callback'   => array( $this, 'get_cart_data' ),
                'schema_callback' => array( $this, 'get_cart_schema' ),
                'schema_type'     => ARRAY_A,
            )
        );
    }

    /**
     * Get cart extension data.
     *
     * @return array
     */
    public function get_cart_data() {
        $user_storage   = UserStorage::get_instance();
        $settings       = NovaPostSettings::get_instance();
        $sender_country = $settings->sender_country;

        $division                   = $user_storage->get( 'division' );
        $division_id                = $user_storage->get( 'division_id' );
        $division_type              = $user_storage->get( 'division_type' );
        $error_calculation_address  = $user_storage->get( 'error_calculation_address' );
        $error_calculation_division = $user_storage->get( 'error_calculation_division' );

        $data = array(
            'division'                      => $division ?? null,
            'division_id'                   => $division_id ?? null,
            'division_type'                 => $division_type ?? null,
            'error_calculation_address'     => $error_calculation_address ?? null,
            'error_calculation_division'    => $error_calculation_division ?? null,
            'sender_country'                => ! empty( $sender_country ) ? (string) $sender_country : null,
            'divisions_filter_postomat'     => (bool) $settings->divisions_filter_postomat,
            'divisions_filter_post_branch'  => (bool) $settings->divisions_filter_post_branch,
            'divisions_filter_cargo_branch' => (bool) $settings->divisions_filter_cargo_branch,
            'divisions_filter_pudo'         => (bool) $settings->divisions_filter_pudo,
        );

        return $data;
    }

    /**
     * Get cart extension data schema.
     *
     * @return array
     */
    public function get_cart_schema() {
        return array(
            'division'                      => array(
                'description' => 'Selected division for warehouse delivery',
                'type'        => array( 'object', 'null' ),
                'readonly'    => true,
            ),
            'division_id'                   => array(
                'description' => 'Selected division ID for warehouse delivery',
                'type'        => array( 'integer', 'null' ),
                'readonly'    => true,
            ),
            'division_type'                 => array(
                'description' => 'Selected division type for warehouse delivery',
                'type'        => array( 'string', 'null' ),
                'readonly'    => true,
            ),
            'sender_country'                => array(
                'description' => 'Sender country configured for Nova Post shipments',
                'type'        => array( 'string', 'null' ),
                'readonly'    => true,
            ),
            'error_calculation_address'     => array(
                'description' => 'Address delivery calculation error',
                'type'        => array( 'boolean', 'null' ),
                'readonly'    => true,
            ),
            'error_calculation_division'    => array(
                'description' => 'Division delivery calculation error',
                'type'        => array( 'boolean', 'null' ),
                'readonly'    => true,
            ),
            'divisions_filter_postomat'     => array(
                'description' => 'Enable Postomat divisions filter',
                'type'        => 'boolean',
                'readonly'    => true,
            ),
            'divisions_filter_post_branch'  => array(
                'description' => 'Enable Post Branch divisions filter',
                'type'        => 'boolean',
                'readonly'    => true,
            ),
            'divisions_filter_cargo_branch' => array(
                'description' => 'Enable Freight branch divisions filter',
                'type'        => 'boolean',
                'readonly'    => true,
            ),
            'divisions_filter_pudo'         => array(
                'description' => 'Enable PUDO divisions filter',
                'type'        => 'boolean',
                'readonly'    => true,
            ),
        );
    }

    /**
     * Inject Nova Post state into classic checkout fragments.
     *
     * @param array $fragments WooCommerce checkout fragments.
     * @return array
     */
    public function inject_checkout_fragments( $fragments ) {
        $service = new ClassicCheckoutStateService();

        $fragments['nova_post_state'] = $service->get_state();

        return $fragments;
    }

    /**
     * Filter WooCommerce package rates.
     *
     * @param array $rates   Shipping rates.
     * @param array $package WooCommerce shipping package.
     * @return array
     */
    public function filter_package_rates( $rates, $package ) {
        if ( ! is_array( $rates ) || ! is_array( $package ) ) {
            return $rates;
        }

        if ( ! $this->package_exceeds_limits( $package ) ) {
            return $rates;
        }

        $user_storage = UserStorage::get_instance();
        $user_storage->set( 'error_calculation_division', true );
        $user_storage->set( 'error_calculation_address', true );

        foreach ( $rates as $rate_key => $rate ) {
            if ( $this->is_nova_post_rate( $rate, (string) $rate_key ) ) {
                unset( $rates[ $rate_key ] );
            }
        }

        return $rates;
    }

    /**
     * Validate Checkout Order.
     *
     * @param \WC_Order $order WooCommerce Order.
     * @return void
     * @throws RouteException Throws Route Exception.
     */
    public function validate_checkout_order_processed( $order ) {
        $validator = $this->validate_order_data( $order );

        if ( $validator->fails() ) {
            throw new RouteException(
                'nova-post-for-woocommerce_validate_checkout_order_processed',
                esc_html( $validator->errors()->firstMessage() ),
                400
            );
        }
    }

    /**
     * Validate Block Checkout
     *
     * @param \WC_Order $order WooCommerce Order.
     * @param \WP_Error $errors WooCommerce Errors.
     * @return void
     */
    public function validate_checkout_order_before_payment( $order, $errors ) {
        $validator = $this->validate_order_data( $order );

        if ( $validator->fails() ) {
            $errors->add( 'validation', esc_html( $validator->errors()->firstMessage() ) );
        }
    }

    /**
     * Validate order data.
     *
     * @param \WC_Order $order WooCommerce Order.
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validate_order_data( $order ) {
        $data = array(
            'phone'           => OrderService::get_order_shipping_phone( $order ),
            'division'        => OrderService::get_order_division_id( $order ),
            'shipping_method' => OrderService::get_order_shipping_method_id( $order ),
        );

        return CheckoutPageValidator::validate( $data );
    }

    /**
     * Check whether rate belongs to Nova Post shipping methods.
     *
     * @param mixed  $rate     WooCommerce shipping rate.
     * @param string $rate_key Shipping rate array key.
     * @return bool
     */
    private function is_nova_post_rate( $rate, string $rate_key ): bool {
        $method_id = '';

        if ( is_object( $rate ) && method_exists( $rate, 'get_method_id' ) ) {
            $method_id = (string) $rate->get_method_id();
        } elseif ( is_object( $rate ) && isset( $rate->method_id ) ) {
            $method_id = (string) $rate->method_id;
        } elseif ( is_array( $rate ) && isset( $rate['method_id'] ) ) {
            $method_id = (string) $rate['method_id'];
        }

        return $this->is_nova_post_method_id( $method_id )
            || str_starts_with( $rate_key, NovaPostShippingMethodDivision::METHOD_ID )
            || str_starts_with( $rate_key, NovaPostShippingMethodAddress::METHOD_ID );
    }

    /**
     * Check whether method ID belongs to Nova Post shipping methods.
     *
     * @param string|null $method_id Shipping method ID.
     * @return bool
     */
    private function is_nova_post_method_id( ?string $method_id ): bool {
        return in_array(
            $method_id,
            array(
                NovaPostShippingMethodDivision::METHOD_ID,
                NovaPostShippingMethodAddress::METHOD_ID,
            ),
            true
        );
    }

    /**
     * Check whether package exceeds currently supported Nova Post limits.
     *
     * @param array $package WooCommerce shipping package.
     * @return bool
     */
    private function package_exceeds_limits( array $package ): bool {
        foreach ( $package['contents'] ?? array() as $item ) {
            $product = $item['data'] ?? null;

            if ( ! $product instanceof \WC_Product ) {
                continue;
            }

            if ( method_exists( $product, 'needs_shipping' ) && ! $product->needs_shipping() ) {
                continue;
            }

            $adapter = new ProductAdapter( $product );

            if ( $adapter->get_weight_in_grams() > MeasurementUnitConstants::UPPER_WEIGHT_LIMIT ) {
                return true;
            }

            $dimensions = array(
                (int) $adapter->get_length_in_mm(),
                (int) $adapter->get_width_in_mm(),
                (int) $adapter->get_height_in_mm(),
            );

            rsort( $dimensions, SORT_NUMERIC );

            if (
                $dimensions[0] > self::MAX_LONGEST_SIDE_MM
                || $dimensions[1] > self::MAX_SECONDARY_SIDE_MM
                || $dimensions[2] > self::MAX_SECONDARY_SIDE_MM
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Change checkout fields.
     *
     * @param array $fields Fields array.
     * @return array
     */
    public function change_checkout_fields( $fields ) {
        if ( isset( $fields['shipping']['shipping_phone'] ) ) {
            $fields['shipping']['shipping_phone']['required'] = true;
        }

        if ( isset( $fields['billing']['billing_phone'] ) ) {
            $fields['billing']['billing_phone']['required'] = true;
        }

        return $fields;
    }

    /**
     * Add current locale to shipping packages to prevent cross-language shipping cache collisions.
     *
     * @param array $packages Cart shipping packages.
     * @return array
     */
    public function add_locale_to_shipping_packages( array $packages ): array {
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

        foreach ( $packages as $key => $package ) {
            $packages[ $key ]['nova_post_locale'] = $locale;
        }

        return $packages;
    }

    /**
     * Register Checkout Block.
     *
     * @param mixed $integration_registry Integration Registry.
     * @return void
     */
    public function register_checkout_block( $integration_registry ) {
        $integration_registry->register( new NovaPostWidgetIntegration() );
    }
}
