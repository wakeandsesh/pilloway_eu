<?php
/**
 * Min Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Min Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Min extends Rule {

    use Traits\SizeTrait;

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute minimum is :min';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'min' );

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );

        $min       = $this->getBytesSize( $this->parameter( 'min' ) );
        $valueSize = $this->getValueSize( $value );

        if ( ! is_numeric( $valueSize ) ) {
            return false;
        }

        return $valueSize >= $min;
    }
}
