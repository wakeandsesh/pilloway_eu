<?php
/**
 * AlphaNum Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * AlphaNum Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class AlphaNum extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute only allows alphabet and numeric';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
            return false;
        }

        return preg_match( '/^[\pL\pM\pN]+$/u', $value ) > 0;
    }
}
