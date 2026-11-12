<?php
/**
 * Ipv6 Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Ipv6 Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Ipv6 extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute is not valid IPv6 Address';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        return filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) !== false;
    }
}
