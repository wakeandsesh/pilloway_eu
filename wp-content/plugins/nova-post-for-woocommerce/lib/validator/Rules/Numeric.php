<?php
/**
 * Numeric Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Numeric Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Numeric extends Rule { // phpcs:ignore

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be numeric';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        return is_numeric( $value );
    }
}
