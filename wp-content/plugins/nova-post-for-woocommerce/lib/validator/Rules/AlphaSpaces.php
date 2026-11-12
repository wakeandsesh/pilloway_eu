<?php
/**
 * AlphaSpaces Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * AlphaSpaces Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class AlphaSpaces extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute may only allows alphabet and spaces';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        if ( ! is_string( $value ) ) {
            return false;
        }

        return preg_match( '/^[\pL\pM\s]+$/u', $value ) > 0;
    }
}
