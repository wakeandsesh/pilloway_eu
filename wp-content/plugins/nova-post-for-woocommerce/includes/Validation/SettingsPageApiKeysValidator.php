<?php
/**
 * Settings Page Api Keys Validator for Rakit Validation
 *
 * @package NovaPostForWoocommerce\Validation
 */

namespace NovaPostForWoocommerce\Validation;

use NovaPostForWoocommerce\Validation\Validator;

/**
 * Validates settings page form data.
 */
class SettingsPageApiKeysValidator {

    /**
     * Make a new validator instance.
     *
     * @param array $data The data to validate.
     *
     * @return \Rakit\Validation\Validation
     */
    public static function validate( $data ) {
        return Validator::validate( $data, self::rules(), self::messages() );
    }

    /**
     * Get the validation rules.
     * Based on Rakit Validation syntax
     *
     * @return array
     */
    public static function rules() {
        return array(
            'api_key'       => 'required_if:debug,false|api_key_production',
            'stage_api_key' => 'required_if:debug,true|api_key_sandbox',
            'debug'         => 'required|boolean',
        );
    }

    /**
     * Get the validation messages.
     * Example: 'api_key.required' => 'API key is required'
     *
     * @return array
     */
    public static function messages() {
        return array(
            'api_key.required_if'           => __( 'API key is required', 'nova-post-for-woocommerce' ),
            'api_key.api_key_production'    => __( 'The provided API key is invalid or failed to authenticate.', 'nova-post-for-woocommerce' ),

            'stage_api_key.required_if'     => __( 'Stage API key is required', 'nova-post-for-woocommerce' ),
            'stage_api_key.api_key_sandbox' => __( 'The provided stage API key is invalid or failed to authenticate.', 'nova-post-for-woocommerce' ),
        );
    }
}
