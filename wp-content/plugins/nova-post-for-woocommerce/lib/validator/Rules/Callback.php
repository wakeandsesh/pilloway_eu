<?php
/**
 * Callback Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;
use InvalidArgumentException;
use Closure;

/**
 * Callback Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Callback extends Rule {

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute is not valid';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'callback' );

    /**
     * Set the Callback closure
     *
     * @param Closure $callback The callback.
     * @return self
     */
    public function setCallback( Closure $callback ): Rule {
        return $this->setParameter( 'callback', $callback );
    }

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     * @throws InvalidArgumentException Invalid argument exception.
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );

        $callback = $this->parameter( 'callback' );
        if ( false === $callback instanceof Closure ) {
            $key = $this->attribute->getKey();
            throw new InvalidArgumentException( sprintf( "Callback rule for '%s' is not callable.", $key ) ); // phpcs:ignore
        }

        $callback       = $callback->bindTo( $this );
        $invalidMessage = $callback( $value );

        if ( is_string( $invalidMessage ) ) {
            $this->setMessage( $invalidMessage );
            return false;
        } elseif ( false === $invalidMessage ) {
            return false;
        }

        return true;
    }
}
