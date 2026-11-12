<?php
/**
 * Json Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Json Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Json extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be a valid JSON string';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        if ( ! is_string( $value ) || empty( $value ) ) {
            return false;
        }

        json_decode( $value );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return false;
        }

        return true;
    }
}
