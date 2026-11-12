<?php
/**
 * Present Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Present Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Present extends Rule {

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
    protected $message = 'The :attribute must be present';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->setAttributeAsRequired();

        return $this->validation->hasValue( $this->attribute->getKey() );
    }

    /**
     * Set attribute is required if $this->attribute is set
     *
     * @return void
     */
    protected function setAttributeAsRequired() {
        if ( $this->attribute ) {
            $this->attribute->setRequired( true );
        }
    }
}
