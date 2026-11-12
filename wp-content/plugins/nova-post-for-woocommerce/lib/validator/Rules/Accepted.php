<?php
/**
 * Accepted Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Accepted Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Accepted extends Rule {

    /**
     * Implicit rule
     *
     * @var bool
     */
    protected $implicit = true;

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be accepted';

    /**
     * Check the $value is accepted
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $acceptables = array( 'yes', 'on', '1', 1, true, 'true' );
        return in_array( $value, $acceptables, true );
    }
}
