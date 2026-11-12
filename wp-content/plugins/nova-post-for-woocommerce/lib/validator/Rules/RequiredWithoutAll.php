<?php
/**
 * RequiredWithoutAll Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * RequiredWithoutAll Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class RequiredWithoutAll extends Required {

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
     * Given $params and assign $this->params
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
            if ( $this->validation->hasValue( $field ) ) {
                return true;
            }
        }

        $this->setAttributeAsRequired();
        return $requiredValidator->check( $value, array() );
    }
}
