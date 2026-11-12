<?php
/**
 * Defaults Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;
use NovaPostForWoocommerce\Rakit\Validation\Rules\Interfaces\ModifyValue;

/**
 * Defaults Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Defaults extends Rule implements ModifyValue {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute default is :default';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'default' );

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );

        $default = $this->parameter( 'default' );
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @param mixed $value The value to modify.
     */
    public function modifyValue( $value ) {
        return $this->isEmptyValue( $value ) ? $this->parameter( 'default' ) : $value;
    }

    /**
     * Check $value is empty value
     *
     * @param mixed $value The value to check.
     * @return boolean
     */
    protected function isEmptyValue( $value ): bool {
        $requiredValidator = new Required();
        return false === $requiredValidator->check( $value, array() );
    }
}
