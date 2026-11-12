<?php
/**
 * In Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Helper;
use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * In Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class In extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute only allows :allowed_values';

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

        $this->params['allowed_values'] = $params;
        return $this;
    }

    /**
     * Set strict value
     *
     * @param bool $strict The strict value.
     * @return void
     */
    public function strict( bool $strict = true ) {
        $this->strict = $strict;
    }

    /**
     * Check $value is existed
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $this->requireParameters( array( 'allowed_values' ) );

        $allowedValues = $this->parameter( 'allowed_values' );

        $or                = $this->validation ? $this->validation->getTranslation( 'or' ) : 'or';
        $allowedValuesText = Helper::join( Helper::wraps( $allowedValues, "'" ), ', ', ", {$or} " );
        $this->setParameterText( 'allowed_values', $allowedValuesText );

        // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
        return in_array( $value, $allowedValues, (bool) $this->strict );
    }
}
