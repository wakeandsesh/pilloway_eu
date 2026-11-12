<?php
/**
 * Digits Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Digits Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Digits extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be numeric and must have an exact length of :length';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'length' );

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );

        $length = (int) $this->parameter( 'length' );

        return ! preg_match( '/[^0-9]/', $value )
                    && strlen( (string) $value ) === $length;
    }
}
