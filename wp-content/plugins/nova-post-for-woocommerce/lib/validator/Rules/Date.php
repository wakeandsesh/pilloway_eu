<?php
/**
 * Date Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Date Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Date extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute is not valid date format';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'format' );

    /**
     * The rule parameters
     *
     * @var array
     */
    protected $params = array();

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $format = $this->parameter( 'format' );

        if ( $format ) {
            return date_create_from_format( $format, $value ) !== false;
        }

        return strtotime( $value ) !== false;
    }
}
