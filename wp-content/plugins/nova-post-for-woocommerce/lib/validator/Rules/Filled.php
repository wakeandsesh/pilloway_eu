<?php
/**
 * Filled Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

/**
 * Filled Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Filled extends Required {

    /**
     * Whether the rule is implicit
     *
     * @var bool
     */
    protected $implicit = true;

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must have a value';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->setAttributeAsRequired();

        if ( ! $this->validation->hasValue( $this->attribute->getKey() ) ) {
            return true;
        }

        return parent::check( $value );
    }
}
