<?php
/**
 * RequiredIfFilled Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * RequiredIfFilled Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class RequiredIfFilled extends Required {

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
        $this->params['fields'] = $params;
        return $this;
    }

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( array( 'fields' ) );
        $fields            = $this->parameter( 'fields' );
        $validator         = $this->validation->getValidator();
        $requiredValidator = $validator( 'required' );

        foreach ( $fields as $field ) {
            $anotherValue = $this->getAttribute()->getValue( $field );

            // Check if the other field is "filled" (not empty).
            if ( $requiredValidator->check( $anotherValue, array() ) ) {
                $this->setAttributeAsRequired();
                return $requiredValidator->check( $value, array() );
            }
        }

        return true;
    }
}
