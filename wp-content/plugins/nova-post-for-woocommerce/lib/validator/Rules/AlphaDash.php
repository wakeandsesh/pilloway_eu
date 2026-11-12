<?php
/**
 * AlphaDash Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * AlphaDash Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class AlphaDash extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute only allows a-z, 0-9, _ and -';

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

        return preg_match( '/^[\pL\pM\pN_-]+$/u', $value ) > 0;
    }
}
