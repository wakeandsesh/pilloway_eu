<?php
/**
 * Required Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\MB;
use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Required Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Required extends Rule {

    use Traits\FileTrait;

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
    protected $message = 'The :attribute is required';

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->setAttributeAsRequired();

        if ( $this->attribute && $this->attribute->hasRule( 'uploaded_file' ) ) {
            return $this->isValueFromUploadedFiles( $value ) && UPLOAD_ERR_NO_FILE !== $value['error'];
        }

        if ( is_string( $value ) ) {
            return MB::strlen( trim( $value ), 'UTF-8' ) > 0;
        }
        if ( is_array( $value ) ) {
            return count( $value ) > 0;
        }
        return ! is_null( $value );
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
