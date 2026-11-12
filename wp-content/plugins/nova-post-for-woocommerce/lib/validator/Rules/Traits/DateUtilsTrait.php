<?php
/**
 * DateUtilsTrait File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules\Traits
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules\Traits;

use Exception;

/**
 * DateUtilsTrait
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules\Traits
 */
trait DateUtilsTrait {

    /**
     * Check the $date is valid
     *
     * @param string $date The date.
     * @return bool
     */
    protected function isValidDate( string $date ): bool {
        return ( strtotime( $date ) !== false );
    }

    /**
     * Throw exception
     *
     * @param string $value The value.
     * @return Exception
     */
    protected function throwException( string $value ) {
        // phpcs:ignore
        return new Exception("Expected a valid date, got '{$value}' instead. 2016-12-08, 2016-12-02 14:58, tomorrow are considered valid dates");
    }

    /**
     * Given $date and get the time stamp
     *
     * @param mixed $date The date.
     * @return int
     */
    protected function getTimeStamp( $date ): int {
        return strtotime( $date );
    }
}
