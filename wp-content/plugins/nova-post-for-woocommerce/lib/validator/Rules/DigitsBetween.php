<?php
/**
 * DigitsBetween Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * DigitsBetween Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class DigitsBetween extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must have a length between the given :min and :max';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'min', 'max' );

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );

        $min = (int) $this->parameter( 'min' );
        $max = (int) $this->parameter( 'max' );

        $length = strlen( (string) $value );

        return ! preg_match( '/[^0-9]/', $value )
                    && $length >= $min && $length <= $max;
    }
}
