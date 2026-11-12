<?php
/**
 * Max Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Max Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Max extends Rule {
    use Traits\SizeTrait;

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute maximum is :max';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'max' );

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );

        $max       = $this->getBytesSize( $this->parameter( 'max' ) );
        $valueSize = $this->getValueSize( $value );

        if ( ! is_numeric( $valueSize ) ) {
            return false;
        }

        return $valueSize <= $max;
    }
}
