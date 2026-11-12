<?php
/**
 * Settings Page Validator for Rakit Validation
 *
 * @package NovaPostForWoocommerce\Validation
 */

namespace NovaPostForWoocommerce\Validation;

/**
 * Validates settings page form data.
 */
class SettingsPageValidator {

    /**
     * Make a new validator instance.
     *
     * @param array $data The data to validate.
     *
     * @return \Rakit\Validation\Validation
     */
    public static function validate( $data ) {
        return Validator::validate( $data, static::rules(), self::messages() );
    }

    /**
     * Get the validation rules.
     * Based on Rakit Validation syntax
     *
     * @return array
     */
    public static function rules() {
        return array(
            'api_key'                                => 'required_if:debug,false|api_key_production',
            'stage_api_key'                          => 'required_if:debug,true|api_key_sandbox',
            'debug'                                  => 'required|boolean',

            'default_box_for'                        => 'nullable|string',
            'packaging_type'                         => 'required|string',

            'np_custom_boxes'                        => 'required_if:packaging_type,custom_boxes',
            'np_custom_boxes.*.width'                => 'nullable|required_if:packaging_type,custom_boxes|numeric|min:0.1|max:170',
            'np_custom_boxes.*.length'               => 'nullable|required_if:packaging_type,custom_boxes|numeric|min:0.1|max:300',
            'np_custom_boxes.*.height'               => 'nullable|required_if:packaging_type,custom_boxes|numeric|min:0.1|max:170',

            'default_height_product'                 => 'required|numeric|min:0.1|max:170',
            'default_width_product'                  => 'required|numeric|min:0.1|max:170',
            'default_length_product'                 => 'required|numeric|min:0.1|max:300',
            'default_weight'                         => 'required|numeric|min:0.01|max:1000',

            'default_height'                         => 'required_if:packaging_type,one_box|numeric|min:0.1|max:170',
            'default_width'                          => 'required_if:packaging_type,one_box|numeric|min:0.1|max:170',
            'default_length'                         => 'required_if:packaging_type,one_box|numeric|min:0.1|max:300',

            'sender_first_name'                      => 'required|string|min:1|max:300',
            'sender_last_name'                       => 'required|string|min:1|max:300',
            'sender_phone'                           => 'required|string|min:1|max:15',
            'sender_email'                           => 'required|email',

            'sender_city'                            => 'required_if:address_type,address|string|min:1|max:300',
            'sender_country'                         => 'required|string|size:2',

            'sender_postal_code'                     => 'required_if:address_type,address|string|min:1|max:12',
            'sender_street'                          => 'required_if:address_type,address|string|min:1|max:300',
            'sender_building'                        => 'required_if:address_type,address|string|min:1|max:50|regex:/^[\p{L}\p{N}\s.\/-]+$/u',

            'sender_division_id'                     => 'required_if:address_type,division|numeric|min:1',
            'sender_division_latitude'               => 'required_if:address_type,division|numeric|between:-90,90',
            'sender_division_longitude'              => 'required_if:address_type,division|numeric|between:-180,180',
            'customs_fee_payer'                      => 'required|string|in:Sender,Recipient',
            'delivery_tariff'                        => 'required|string|in:standard,express',

            'shipping_division_flat_rate'            => 'nullable|boolean',
            'shipping_division_flat_rate_amount'     => 'required_if:shipping_division_flat_rate,true|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'shipping_division_free_delivery'        => 'nullable|boolean',
            'shipping_division_free_delivery_amount' => 'required_if:shipping_division_free_delivery,true|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',

            'shipping_address_flat_rate'             => 'nullable|boolean',
            'shipping_address_flat_rate_amount'      => 'required_if:shipping_address_flat_rate,true|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'shipping_address_free_delivery'         => 'nullable|boolean',
            'shipping_address_free_delivery_amount'  => 'required_if:shipping_address_free_delivery,true|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',

            'default_hs_tariff_code'                 => 'nullable|numeric|digits_between:8,10',

            'order_workflow_auto_complete_orders'    => 'nullable|boolean',

            'divisions_filter_postomat'              => 'nullable|boolean|at_least_one_division',
            'divisions_filter_post_branch'           => 'nullable|boolean',
            'divisions_filter_cargo_branch'          => 'nullable|boolean',
            'divisions_filter_pudo'                  => 'nullable|boolean',
        );
    }

    /**
     * Get the validation messages.
     * Based on Rakit Validation message format
     * Example: 'api_key.required' => 'API key is required'
     *
     * @return array
     */
    public static function messages() {
        return array(
            'api_key.required_if'                          => __( 'API key is required', 'nova-post-for-woocommerce' ),
            'api_key.api_key_production'                   => __( 'The provided API key is invalid or failed to authenticate.', 'nova-post-for-woocommerce' ),

            'stage_api_key.required_if'                    => __( 'Stage API key is required', 'nova-post-for-woocommerce' ),
            'stage_api_key.api_key_sandbox'                => __( 'The provided stage API key is invalid or failed to authenticate.', 'nova-post-for-woocommerce' ),

            'default_height.required_if'                   => __( 'Default height is required', 'nova-post-for-woocommerce' ),
            'default_height.required'                      => __( 'Default height is required', 'nova-post-for-woocommerce' ),
            'default_height.numeric'                       => __( 'Default height must be a number', 'nova-post-for-woocommerce' ),
            'default_height.min'                           => __( 'Default height must be at least :min', 'nova-post-for-woocommerce' ),
            'default_height.max'                           => __( 'Default height must be at most :max', 'nova-post-for-woocommerce' ),

            'default_width.required_if'                    => __( 'Default width is required', 'nova-post-for-woocommerce' ),
            'default_width.required'                       => __( 'Default width is required', 'nova-post-for-woocommerce' ),
            'default_width.numeric'                        => __( 'Default width must be a number', 'nova-post-for-woocommerce' ),
            'default_width.min'                            => __( 'Default width must be at least :min', 'nova-post-for-woocommerce' ),
            'default_width.max'                            => __( 'Default width must be at most :max', 'nova-post-for-woocommerce' ),

            'default_length.required_if'                   => __( 'Default length is required', 'nova-post-for-woocommerce' ),
            'default_length.required'                      => __( 'Default length is required', 'nova-post-for-woocommerce' ),
            'default_length.numeric'                       => __( 'Default length must be a number', 'nova-post-for-woocommerce' ),
            'default_length.min'                           => __( 'Default length must be at least :min', 'nova-post-for-woocommerce' ),
            'default_length.max'                           => __( 'Default length must be at most :max', 'nova-post-for-woocommerce' ),

            'np_custom_boxes.array'                        => __( 'Custom boxes must be an array', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.required'                     => __( 'All dimensions are required for each box and must be positive numbers.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.required_if'                  => __( 'All dimensions are required for each box and must be positive numbers.', 'nova-post-for-woocommerce' ),

            'np_custom_boxes.*.width.required'             => __( 'Custom box #{0} width is required.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.*.width.required_if'          => __( 'Custom box #{0} width is required.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.*.width.min'                  => __( 'Custom box #{0} width field must not be lower than :min.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.*.width.max'                  => __( 'Custom box #{0} width field must not be greater than :max.', 'nova-post-for-woocommerce' ),

            'np_custom_boxes.*.length.required'            => __( 'Custom box #{0} length is required.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.*.length.required_if'         => __( 'Custom box #{0} length is required.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.*.length.min'                 => __( 'Custom box #{0} length field must not be lower than :min.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.*.length.max'                 => __( 'Custom box #{0} length field must not be greater than :max.', 'nova-post-for-woocommerce' ),

            'np_custom_boxes.*.height.required'            => __( 'Custom box #{0} height is required.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.*.height.required_if'         => __( 'Custom box #{0} height is required.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.*.height.min'                 => __( 'Custom box #{0} height field must not be lower than :min.', 'nova-post-for-woocommerce' ),
            'np_custom_boxes.*.height.max'                 => __( 'Custom box #{0} height field must not be greater than :max.', 'nova-post-for-woocommerce' ),

            'default_height_product.required'              => __( 'Default product height is required', 'nova-post-for-woocommerce' ),
            'default_height_product.numeric'               => __( 'Default product height must be a number', 'nova-post-for-woocommerce' ),
            'default_height_product.min'                   => __( 'Default product height must be at least :min', 'nova-post-for-woocommerce' ),
            'default_height_product.max'                   => __( 'Default product height must be at most :max', 'nova-post-for-woocommerce' ),

            'default_width_product.required'               => __( 'Default product width is required', 'nova-post-for-woocommerce' ),
            'default_width_product.numeric'                => __( 'Default product width must be a number', 'nova-post-for-woocommerce' ),
            'default_width_product.min'                    => __( 'Default product width must be at least :min', 'nova-post-for-woocommerce' ),
            'default_width_product.max'                    => __( 'Default product width must be at most :max', 'nova-post-for-woocommerce' ),

            'default_length_product.required'              => __( 'Default product length is required', 'nova-post-for-woocommerce' ),
            'default_length_product.numeric'               => __( 'Default product length must be a number', 'nova-post-for-woocommerce' ),
            'default_length_product.min'                   => __( 'Default product length must be at least :min', 'nova-post-for-woocommerce' ),
            'default_length_product.max'                   => __( 'Default product length must be at most :max', 'nova-post-for-woocommerce' ),

            'default_weight.required'                      => __( 'Default product weight is required', 'nova-post-for-woocommerce' ),
            'default_weight.numeric'                       => __( 'Default product weight must be a number', 'nova-post-for-woocommerce' ),
            'default_weight.min'                           => __( 'Default product weight must be at least :min', 'nova-post-for-woocommerce' ),
            'default_weight.max'                           => __( 'Default product weight must be at most :max', 'nova-post-for-woocommerce' ),

            'sender_first_name.required'                   => __( 'Sender\'s first name is required', 'nova-post-for-woocommerce' ),
            'sender_first_name.min'                        => __( 'Sender\'s first name must be at least :min character', 'nova-post-for-woocommerce' ),
            'sender_first_name.max'                        => __( 'Sender\'s first name must not exceed :max characters', 'nova-post-for-woocommerce' ),

            'sender_last_name.required'                    => __( 'Sender\'s last name is required', 'nova-post-for-woocommerce' ),
            'sender_last_name.min'                         => __( 'Sender\'s last name must be at least :min character', 'nova-post-for-woocommerce' ),
            'sender_last_name.max'                         => __( 'Sender\'s last name must not exceed :max characters', 'nova-post-for-woocommerce' ),

            'sender_phone.required'                        => __( 'Sender\'s phone is required', 'nova-post-for-woocommerce' ),
            'sender_phone.min'                             => __( 'Sender\'s phone must be at least :min character', 'nova-post-for-woocommerce' ),
            'sender_phone.max'                             => __( 'Sender\'s phone must not exceed :max characters', 'nova-post-for-woocommerce' ),

            'sender_email.required'                        => __( 'Sender\'s email is required', 'nova-post-for-woocommerce' ),
            'sender_email.email'                           => __( 'Sender\'s email must be a valid email address', 'nova-post-for-woocommerce' ),

            'sender_city.required_if'                      => __( 'Sender\'s city is required when address type is address', 'nova-post-for-woocommerce' ),
            'sender_city.min'                              => __( 'Sender\'s city must be at least :min character', 'nova-post-for-woocommerce' ),
            'sender_city.max'                              => __( 'Sender\'s city must not exceed :max characters', 'nova-post-for-woocommerce' ),

            'sender_country.required'                      => __( 'Sender\'s country is required', 'nova-post-for-woocommerce' ),
            'sender_country.size'                          => __( 'Sender\'s country must be :size characters long', 'nova-post-for-woocommerce' ),

            'sender_postal_code.required_if'               => __( 'Sender\'s postal code is required when address type is address', 'nova-post-for-woocommerce' ),
            'sender_postal_code.min'                       => __( 'Sender\'s postal code must be at least :min character', 'nova-post-for-woocommerce' ),
            'sender_postal_code.max'                       => __( 'Sender\'s postal code must not exceed :max characters', 'nova-post-for-woocommerce' ),

            'sender_street.required_if'                    => __( 'Sender\'s street is required when address type is address', 'nova-post-for-woocommerce' ),
            'sender_street.min'                            => __( 'Sender\'s street must be at least :min character', 'nova-post-for-woocommerce' ),
            'sender_street.max'                            => __( 'Sender\'s street must not exceed :max characters', 'nova-post-for-woocommerce' ),

            'sender_building.required_if'                  => __( 'Sender\'s building is required when address type is address', 'nova-post-for-woocommerce' ),
            'sender_building.min'                          => __( 'Sender\'s building must be at least :min character', 'nova-post-for-woocommerce' ),
            'sender_building.max'                          => __( 'Sender\'s building must not exceed :max characters', 'nova-post-for-woocommerce' ),
            'sender_building.regex'                        => __( 'Building number must contain only letters, numbers, spaces, dots, slashes and hyphens', 'nova-post-for-woocommerce' ),

            'sender_division_id.required_if'               => __( 'Sender\'s division ID is required when address type is division', 'nova-post-for-woocommerce' ),
            'sender_division_id.numeric'                   => __( 'Sender\'s division ID must be a number', 'nova-post-for-woocommerce' ),
            'sender_division_id.min'                       => __( 'Sender\'s division ID must be at least :min', 'nova-post-for-woocommerce' ),

            'sender_division_latitude.required_if'         => __( 'Sender\'s division latitude is required when address type is division', 'nova-post-for-woocommerce' ),
            'sender_division_latitude.numeric'             => __( 'Sender\'s division latitude must be a number', 'nova-post-for-woocommerce' ),
            'sender_division_latitude.between'             => __( 'Sender\'s division latitude must be between :min and :max', 'nova-post-for-woocommerce' ),

            'sender_division_longitude.required_if'        => __( 'Sender\'s division longitude is required when address type is division', 'nova-post-for-woocommerce' ),
            'sender_division_longitude.numeric'            => __( 'Sender\'s division longitude must be a number', 'nova-post-for-woocommerce' ),
            'sender_division_longitude.between'            => __( 'Sender\'s division longitude must be between :min and :max', 'nova-post-for-woocommerce' ),

            'customs_fee_payer.required'                   => __( 'Customs fee payer is required', 'nova-post-for-woocommerce' ),
            'customs_fee_payer.in'                         => __( 'Customs fee payer must be Sender or Recipient', 'nova-post-for-woocommerce' ),

            'delivery_tariff.required'                     => __( 'Delivery tariff is required', 'nova-post-for-woocommerce' ),
            'delivery_tariff.in'                           => __( 'Delivery tariff must be standard or express', 'nova-post-for-woocommerce' ),

            'shipping_division_flat_rate_amount.required_if' => __( 'Shipping flat rate is required when free delivery for this division is enabled', 'nova-post-for-woocommerce' ),
            'shipping_division_flat_rate_amount.regex'     => __( 'Shipping flat rate must be a positive number with up to 2 decimal places', 'nova-post-for-woocommerce' ),
            'shipping_division_flat_rate_amount.min'       => __( 'Shipping flat rate must be :min or higher', 'nova-post-for-woocommerce' ),

            'shipping_division_free_delivery_amount.required_if' => __( 'Free delivery amount is required when free delivery for this division is enabled', 'nova-post-for-woocommerce' ),
            'shipping_division_free_delivery_amount.regex' => __( 'Free delivery amount must be a positive number with maximum 2 decimal places', 'nova-post-for-woocommerce' ),
            'shipping_division_free_delivery_amount.min'   => __( 'Free delivery amount must be :min or higher', 'nova-post-for-woocommerce' ),

            'shipping_address_flat_rate_amount.required_if' => __( 'Shipping flat rate is required when free delivery for this address is enabled', 'nova-post-for-woocommerce' ),
            'shipping_address_flat_rate_amount.regex'      => __( 'Shipping flat rate must be a positive number with up to 2 decimal places', 'nova-post-for-woocommerce' ),
            'shipping_address_flat_rate_amount.min'        => __( 'Shipping flat rate must be :min or higher', 'nova-post-for-woocommerce' ),

            'shipping_address_free_delivery_amount.required_if' => __( 'Free delivery amount is required when free delivery for this address is enabled', 'nova-post-for-woocommerce' ),
            'shipping_address_free_delivery_amount.regex'  => __( 'Free delivery amount must be a positive number with up to 2 decimal places', 'nova-post-for-woocommerce' ),
            'shipping_address_free_delivery_amount.min'    => __( 'Free delivery amount must be :min or higher', 'nova-post-for-woocommerce' ),

            'default_hs_tariff_code.numeric'               => __( 'Default HS tariff code must be a number', 'nova-post-for-woocommerce' ),
            'default_hs_tariff_code.digits_between'        => __( 'Default HS tariff code must be between :min and :max digits', 'nova-post-for-woocommerce' ),

            'divisions_filter_postomat.at_least_one_division' => __( 'At least one division type must be enabled.', 'nova-post-for-woocommerce' ),
        );
    }
}
