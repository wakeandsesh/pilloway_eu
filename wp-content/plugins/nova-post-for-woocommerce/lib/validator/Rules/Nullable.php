<?php
/**
 * Nullable Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Nullable Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Nullable extends Rule {

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        return true;
    }
}
