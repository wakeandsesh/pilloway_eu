<?php
/**
 * Different Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Different Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Different extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be different with :field';

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
        $anotherValue = $this->validation->getValue( $field );

        return $value != $anotherValue; // phpcs:ignore
    }
}
