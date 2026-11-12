<?php
/**
 * Alpha Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Alpha Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Alpha extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute only allows alphabet characters';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        return is_string( $value ) && preg_match( '/^[\pL\pM]+$/u', $value );
    }
}
