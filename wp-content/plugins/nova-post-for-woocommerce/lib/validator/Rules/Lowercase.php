<?php
/**
 * Lowercase Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\MB;
use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Lowercase Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Lowercase extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be lowercase';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        return MB::strtolower( $value ) === $value;
    }
}
