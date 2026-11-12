<?php
/**
 * Same Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Same Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Same extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be same with :field';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'field' );

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );

        $field        = $this->parameter( 'field' );
        $anotherValue = $this->getAttribute()->getValue( $field );

        return $value == $anotherValue; // phpcs:ignore
    }
}
