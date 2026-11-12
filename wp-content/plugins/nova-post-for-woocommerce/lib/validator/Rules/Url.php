<?php
/**
 * Url Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Url Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Url extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute is not valid url';

    /**
     * Given $params and assign $this->params
     *
     * @param array $params The parameters.
     * @return self
     */
    public function fillParameters( array $params ): Rule {
        if ( 1 === count( $params ) && is_array( $params[0] ) ) {
            $params = $params[0];
        }
        return $this->forScheme( $params );
    }

    /**
     * Given $schemes and assign $this->params
     *
     * @param array $schemes The schemes.
     * @return self
     */
    public function forScheme( $schemes ): Rule {
        $this->params['schemes'] = (array) $schemes;
        return $this;
    }

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $schemes = $this->parameter( 'schemes' );

        if ( ! $schemes ) {
            return $this->validateCommonScheme( $value );
        } else {
            foreach ( $schemes as $scheme ) {
                $method = 'validate' . ucfirst( $scheme ) . 'Scheme';
                if ( method_exists( $this, $method ) ) {
                    if ( $this->{$method}( $value ) ) {
                        return true;
                    }
                } elseif ( $this->validateCommonScheme( $value, $scheme ) ) {
                    return true;
                }
            }

            return false;
        }
    }

    /**
     * Validate $value is valid URL format
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function validateBasic( $value ): bool {
        return filter_var( $value, FILTER_VALIDATE_URL ) !== false;
    }

    /**
     * Validate $value is correct $scheme format
     *
     * @param mixed $value  The value to check.
     * @param null  $scheme The scheme.
     * @return bool
     */
    public function validateCommonScheme( $value, $scheme = null ): bool {
        if ( ! $scheme ) {
            return $this->validateBasic( $value ) && (bool) preg_match( '/^\w+:\/\//i', $value );
        } else {
            return $this->validateBasic( $value ) && (bool) preg_match( "/^{$scheme}:\/\//", $value );
        }
    }

    /**
     * Validate the $value is mailto scheme format
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function validateMailtoScheme( $value ): bool {
        return $this->validateBasic( $value ) && preg_match( '/^mailto:/', $value );
    }

    /**
     * Validate the $value is jdbc scheme format
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function validateJdbcScheme( $value ): bool {
        return (bool) preg_match( '/^jdbc:\w+:\/\//', $value );
    }
}
