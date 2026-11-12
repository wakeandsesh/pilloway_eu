<?php
/**
 * Boolean Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Boolean Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Boolean extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be a boolean';

    /**
     * Check the value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     * @throws \Exception Missing parameter exception.
     */
    public function check( $value ): bool {
        return in_array( $value, array( true, false, 'true', 'false', 1, 0, '0', '1', 'y', 'n' ), true );
    }
}
