<?php
/**
 * Validation functionality using Rakit Validation.
 *
 * @package NovaPostForWoocommerce\Validation
 */

namespace NovaPostForWoocommerce\Validation;

use NovaPostForWoocommerce\Validation\Rules\ApiKeyValidationRule;
use NovaPostForWoocommerce\Validation\Rules\AtLeastOneDivisionFilterRule;
use NovaPostForWoocommerce\Validation\Rules\ExistsInCustomTableRule;
use NovaPostForWoocommerce\Validation\Rules\ExistingWooOrderRule;
use NovaPostForWoocommerce\Rakit\Validation\Validator as RakitValidator;

/**
 * Validator class for handling form validation.
 *
 * Wraps the Rakit Validator for use in WordPress.
 */
class Validator {

    /**
     * The Rakit Validator instance.
     *
     * @var RakitValidator
     */
    private static ?RakitValidator $validator = null;

    /**
     * Get the validator instance.
     *
     * @return RakitValidator
     */
    public static function get_instance() {
        if ( null === self::$validator ) {
            $messages        = require NPFORWC_PLUGIN_PATH . 'data/validator-messages.php';
            self::$validator = new RakitValidator( $messages );
            // Allow overriding rules to prevent RuleQuashException in tests or multiple calls.
            self::$validator->allowRuleOverride( true );

            self::$validator->addValidator( 'api_key_production', fn() => new ApiKeyValidationRule( false ) );
            self::$validator->addValidator( 'api_key_sandbox', fn() => new ApiKeyValidationRule( true ) );
            self::$validator->addValidator( 'at_least_one_division', fn() => new AtLeastOneDivisionFilterRule() );
            self::$validator->addValidator( 'exists_in_custom_table', fn() => new ExistsInCustomTableRule() );
            self::$validator->addValidator( 'existing_woo_order', fn() => new ExistingWooOrderRule() );
        }

        return self::$validator;
    }

    /**
     * Create a new validator instance and automatically validate.
     *
     * This method mimics Laravel's Validator::make() behavior by automatically
     * running validation. This ensures backward compatibility with existing code.
     *
     * @param array $data     The data to validate.
     * @param array $rules    The validation rules.
     * @param array $messages Custom error messages.
     * @return \Rakit\Validation\Validation
     */
    public static function validate( $data, $rules, $messages = array() ) {
        return self::get_instance()->validate( $data, $rules, $messages );
    }
}
