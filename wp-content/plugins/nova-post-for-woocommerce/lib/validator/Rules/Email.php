<?php
/**
 * Email Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Email Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Email extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute is not valid email';

    /**
     * Check $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        return filter_var( $value, FILTER_VALIDATE_EMAIL ) !== false;
    }
}
