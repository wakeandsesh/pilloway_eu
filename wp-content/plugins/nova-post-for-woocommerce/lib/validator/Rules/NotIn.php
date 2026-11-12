<?php
/**
 * NotIn Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Helper;
use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * NotIn Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class NotIn extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute is not allowing :disallowed_values';

    /**
     * Whether to use strict comparison
     *
     * @var bool
     */
    protected $strict = false;

    /**
     * Given $params and assign the $this->params
     *
     * @param array $params The parameters.
     * @return self
     */
    public function fillParameters( array $params ): Rule {
        if ( count( $params ) === 1 && is_array( $params[0] ) ) {
            $params = $params[0];
        }

        // Convert "true" and "false" strings to booleans.
        $params = array_map(
            function ( $val ) {
                if ( 'true' === $val ) {
                    return true;
                }
                if ( 'false' === $val ) {
                    return false;
                }
                return $val;
            },
            $params
        );

        $this->params['disallowed_values'] = $params;
        return $this;
    }

    /**
     * Set strict value
     *
     * @param bool $strict The strict value.
     * @return void
     */
    public function strict( $strict = true ) {
        $this->strict = $strict;
    }

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( array( 'disallowed_values' ) );

        $disallowedValues = (array) $this->parameter( 'disallowed_values' );

        $and                  = $this->validation ? $this->validation->getTranslation( 'and' ) : 'and';
        $disallowedValuesText = Helper::join( Helper::wraps( $disallowedValues, "'" ), ', ', ", {$and} " );
        $this->setParameterText( 'disallowed_values', $disallowedValuesText );

        // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
        return ! in_array( $value, $disallowedValues, (bool) $this->strict );
    }
}
