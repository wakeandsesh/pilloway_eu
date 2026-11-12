<?php
/**
 * Before Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Before Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Before extends Rule {

    use Traits\DateUtilsTrait;

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute must be a date before :date.';

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array( 'date' );

    /**
     * Check the the value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     * @throws \Exception Missing parameter exception.
     */
    public function check( $value ): bool {
        $this->requireParameters( $this->fillableParams );
        $time = $this->parameter( 'date' );

        if ( ! $this->isValidDate( $value ) ) {
            throw $this->throwException( $value ); // phpcs:ignore
        }

        if ( ! $this->isValidDate( $time ) ) {
            throw $this->throwException( $time ); // phpcs:ignore
        }

        return $this->getTimeStamp( $time ) > $this->getTimeStamp( $value );
    }
}
