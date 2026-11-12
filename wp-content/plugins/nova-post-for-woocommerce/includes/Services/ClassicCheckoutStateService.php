<?php
/**
 * Classic Checkout State Service.
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Models\UserStorage;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodDivision;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns a normalized snapshot of classic checkout state for the Nova Post widget.
 */
class ClassicCheckoutStateService {

    /**
     * Get classic checkout state.
     *
     * @return array
     */
    public function get_state(): array {
        $settings                 = NovaPostSettings::get_instance();
        $user_storage             = UserStorage::get_instance();
        $sender_country           = $settings->sender_country;
        $selected_rate_id         = $this->get_selected_shipping_rate_id();
        $selected_method_id       = $this->normalize_shipping_method_id( $selected_rate_id );
        $is_np_to_division_method = $selected_method_id && str_starts_with( $selected_method_id, NovaPostShippingMethodDivision::METHOD_ID );

        return array(
            'checkoutMode'             => 'classic',
            'selectedShippingRateId'   => $selected_rate_id,
            'selectedShippingMethodId' => $selected_method_id,
            'isNovaPostDivisionActive' => $is_np_to_division_method,
            'shippingAddress'          => $this->get_shipping_address(),
            'senderCountry'            => ! empty( $sender_country ) ? (string) $sender_country : null,
            'divisionData'             => array(
                'division'      => $user_storage->get( 'division' ),
                'division_id'   => $user_storage->get( 'division_id' ),
                'division_type' => $user_storage->get( 'division_type' ),
            ),
            'divisionFilters'          => array(
                'postomat'    => (bool) $settings->divisions_filter_postomat,
                'postBranch'  => (bool) $settings->divisions_filter_post_branch,
                'cargoBranch' => (bool) $settings->divisions_filter_cargo_branch,
                'pudo'        => (bool) $settings->divisions_filter_pudo,
            ),
            'errorCalculation'         => array(
                'division' => (bool) $user_storage->get( 'error_calculation_division' ),
                'address'  => (bool) $user_storage->get( 'error_calculation_address' ),
            ),
            'meta'                     => array(
                'source'      => 'classic-checkout-session',
                'refreshedAt' => time(),
            ),
        );
    }

    /**
     * Get selected shipping rate id from the WooCommerce session.
     *
     * @return string|null
     */
    private function get_selected_shipping_rate_id() {
        $chosen_shipping_methods = \WC()->session ? \WC()->session->get( 'chosen_shipping_methods', array() ) : array();

        if ( empty( $chosen_shipping_methods ) || ! is_array( $chosen_shipping_methods ) ) {
            return null;
        }

        $selected_rate_id = reset( $chosen_shipping_methods );

        return is_string( $selected_rate_id ) ? $selected_rate_id : null;
    }

    /**
     * Normalize shipping method id from a selected shipping rate id.
     *
     * @param string|null $selected_rate_id Selected shipping rate id.
     * @return string|null
     */
    private function normalize_shipping_method_id( ?string $selected_rate_id ): ?string {
        if ( empty( $selected_rate_id ) ) {
            return null;
        }

        $parts = explode( ':', $selected_rate_id, 2 );

        return ! empty( $parts[0] ) ? $parts[0] : null;
    }

    /**
     * Return the effective shipping address from the WooCommerce customer.
     *
     * Falls back to raw WC session customer data when WC()->customer is not
     * yet initialized (e.g. REST API context during classic checkout).
     *
     * @return array<string,string>
     */
    private function get_shipping_address(): array {
        if ( ! \WC()->customer ) {
            return $this->get_shipping_address_from_session();
        }

        $shipping_address = array(
            'country'   => (string) \WC()->customer->get_shipping_country(),
            'state'     => (string) \WC()->customer->get_shipping_state(),
            'postcode'  => (string) \WC()->customer->get_shipping_postcode(),
            'city'      => (string) \WC()->customer->get_shipping_city(),
            'address_1' => (string) \WC()->customer->get_shipping_address_1(),
            'address_2' => (string) \WC()->customer->get_shipping_address_2(),
        );

        if ( $this->has_shipping_address( $shipping_address ) ) {
            return $shipping_address;
        }

        return array(
            'country'   => (string) \WC()->customer->get_billing_country(),
            'state'     => (string) \WC()->customer->get_billing_state(),
            'postcode'  => (string) \WC()->customer->get_billing_postcode(),
            'city'      => (string) \WC()->customer->get_billing_city(),
            'address_1' => (string) \WC()->customer->get_billing_address_1(),
            'address_2' => (string) \WC()->customer->get_billing_address_2(),
        );
    }

    /**
     * Read shipping address from WC session customer data.
     *
     * WooCommerce stores customer address in the session under the 'customer' key
     * using keys like shipping_country, shipping_city, etc.
     *
     * @return array<string,string>
     */
    private function get_shipping_address_from_session(): array {
        if ( ! \WC()->session ) {
            return $this->empty_shipping_address();
        }

        $customer_data = \WC()->session->get( 'customer', array() );

        if ( ! is_array( $customer_data ) || empty( $customer_data ) ) {
            return $this->empty_shipping_address();
        }

        $shipping_address = array(
            'country'   => (string) ( $customer_data['shipping_country'] ?? '' ),
            'state'     => (string) ( $customer_data['shipping_state'] ?? '' ),
            'postcode'  => (string) ( $customer_data['shipping_postcode'] ?? '' ),
            'city'      => (string) ( $customer_data['shipping_city'] ?? '' ),
            'address_1' => (string) ( $customer_data['shipping_address'] ?? '' ),
            'address_2' => (string) ( $customer_data['shipping_address_2'] ?? '' ),
        );

        if ( $this->has_shipping_address( $shipping_address ) ) {
            return $shipping_address;
        }

        return array(
            'country'   => (string) ( $customer_data['country'] ?? '' ),
            'state'     => (string) ( $customer_data['state'] ?? '' ),
            'postcode'  => (string) ( $customer_data['postcode'] ?? '' ),
            'city'      => (string) ( $customer_data['city'] ?? '' ),
            'address_1' => (string) ( $customer_data['address'] ?? '' ),
            'address_2' => (string) ( $customer_data['address_2'] ?? '' ),
        );
    }

    /**
     * Check whether the normalized shipping address contains meaningful values.
     *
     * @param array<string,string> $shipping_address Shipping address.
     * @return bool
     */
    private function has_shipping_address( array $shipping_address ): bool {
        foreach ( $shipping_address as $value ) {
            if ( '' !== $value ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Empty shipping address shape.
     *
     * @return array<string,string>
     */
    private function empty_shipping_address(): array {
        return array(
            'country'   => '',
            'state'     => '',
            'postcode'  => '',
            'city'      => '',
            'address_1' => '',
            'address_2' => '',
        );
    }
}
