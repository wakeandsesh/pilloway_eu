<?php
/**
 * After Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * After Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class After extends Rule {

    use Traits\DateUtilsTrait;

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be a date after :date.';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'date' );

    /**
     * Check the value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     * @throws \Exception Missing parameter exception.
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );
        $time = $this->parameter( 'date' );

        if ( ! $this->isValidDate( $time ) ) {
            if ( $this->validation && $this->validation->hasValue( $time ) ) {
                $time = $this->validation->getValue( $time );
            }
        }

        if ( ! $this->isValidDate( $value ) ) {
            throw $this->throwException( $value ); // phpcs:ignore
        }

        if ( ! $this->isValidDate( $time ) ) {
            throw $this->throwException( $this->parameter( 'date' ) ); // phpcs:ignore
        }

        return $this->getTimeStamp( $time ) < $this->getTimeStamp( $value );
    }
}
