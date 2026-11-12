<?php
/**
 * Checkout page validation rules and messages.
 *
 * @package NovaPostForWoocommerce\Validation
 */

namespace NovaPostForWoocommerce\Validation;

use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodDivision;
use NovaPostForWoocommerce\Shipping\NovaPostShippingMethodAddress;

/**
 * Handles validation rules and messages for the checkout page.
 */
class CheckoutPageValidator {

    /**
     * Create a new validator instance with the given data.
     *
     * @param array $data The data to validate.
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public static function validate( $data ) {
        return Validator::validate( $data, self::rules(), self::messages() );
    }

    /**
     * Get the validation rules for checkout.
     *
     * @return array
     */
    public static function rules() {
        return array(
            'phone'    => 'required_if:shipping_method,' . NovaPostShippingMethodDivision::METHOD_ID . ',' . NovaPostShippingMethodAddress::METHOD_ID,
            'division' => 'required_if:shipping_method,' . NovaPostShippingMethodDivision::METHOD_ID,
        );
    }

    /**
     * Get the validation error messages.
     *
     * @return array
     */
    public static function messages() {
        return array(
            'phone.required_if'    => __( 'Please ensure your phone number is filled.', 'nova-post-for-woocommerce' ),
            'division.required_if' => __( 'Please ensure your division is selected.', 'nova-post-for-woocommerce' ),
        );
    }
}
