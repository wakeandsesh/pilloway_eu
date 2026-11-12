<?php
/**
 * TypeArray Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * TypeArray Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class TypeArray extends Rule {


    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be array';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        return is_array( $value );
    }
}
