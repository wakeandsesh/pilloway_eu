<?php
/**
 * Size Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\MB;
use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Size Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Size extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be :size';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'size' );

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );

        $size = (float) $this->parameter( 'size' );
        $this->setParameterText( 'size', (string) $size );

        if ( is_int( $value ) || is_float( $value ) || is_numeric( $value ) ) {
            return (float) $value === (float) $size;
        } elseif ( is_string( $value ) ) {
            return MB::strlen( $value, 'UTF-8' ) === (int) $size;
        } elseif ( is_array( $value ) ) {
            return count( $value ) === (int) $size;
        }

        return false;
    }
}
