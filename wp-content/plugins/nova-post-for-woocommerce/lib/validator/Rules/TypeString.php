<?php
/**
 * TypeString Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * TypeString Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class TypeString extends Rule {


    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be string';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        return is_string( $value );
    }
}
