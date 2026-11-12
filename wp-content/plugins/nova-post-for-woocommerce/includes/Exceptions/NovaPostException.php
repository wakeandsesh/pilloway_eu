<?php
/**
 * Base Exception for Nova Post WooCommerce.
 *
 * @package NovaPostForWoocommerce\Exceptions
 */

namespace NovaPostForWoocommerce\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception that carries optional contextual data.
 */
class NovaPostException extends Exception {

    /**
     * Exception data.
     *
     * @var array
     */
    protected array $data = array();

    /**
     * Construct.
     *
     * @param string         $message  Error Message.
     * @param int            $code     HTTP status code.
     * @param array          $data     Optional contextual data. Contains `status` to be used in WP_Error.
     * @param Throwable|null $previous Previous Exception.
     */
    public function __construct( string $message, int $code, array $data = array(), ?Throwable $previous = null ) {
        parent::__construct( $message, $code, $previous );

        $this->data = $data + array( 'status' => $code );
    }

    /**
     * Get exception's contextual data.
     *
     * @return array
     */
    public function get_data(): array {
        return $this->data;
    }
}
