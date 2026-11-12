<?php
/**
 * RequiredIf Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * RequiredIf Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class RequiredIf extends Required {

    /**
     * Implicit rule
     *
     * @var bool
     */
    protected $implicit = true;

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute is required';

    /**
     * Given $params and assign the $this->params
     *
     * @param array $params The parameters.
     * @return self
     */
    public function fillParameters( array $params ): Rule {
        $this->params['field']  = array_shift( $params );
        $this->params['values'] = $params;
        return $this;
    }

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( array( 'field', 'values' ) );

        $anotherAttribute = $this->parameter( 'field' );
        $definedValues    = $this->parameter( 'values' );

        // Convert "true" and "false" strings to booleans.
        $definedValues = array_map(
            function ( $val ) {
                if ( 'true' === $val ) {
                    return true;
                }
                if ( 'false' === $val ) {
                    return false;
                }
                return $val;
            },
            $definedValues
        );

        $anotherValue = $this->getAttribute()->getValue( $anotherAttribute );

        $validator         = $this->validation->getValidator();
        $requiredValidator = $validator( 'required' );

        if ( in_array( $anotherValue, $definedValues ) ) { // phpcs:ignore
            $this->setAttributeAsRequired();
            return $requiredValidator->check( $value, array() );
        }

        return true;
    }
}
