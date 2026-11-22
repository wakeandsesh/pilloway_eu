<?php
/**
 * Shipment Exception.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Exceptions;

use Throwable;

/**
 * Custom exception class for shipment-related errors.
 */
class ShipmentException extends NovaPostException {
    public const NOT_FOUND = 'shipment_not_found';

    /**
     * Construct.
     *
     * @param string         $message  Error Message.
     * @param int            $code     HTTP status code.
     * @param array          $data     Optional contextual data.
     * @param Throwable|null $previous Previous Exception.
     */
    public function __construct( string $message, int $code = 400, array $data = array(), ?Throwable $previous = null ) {
        $masked_data = self::mask_sensitive_data( $data );
        parent::__construct( $message, $code, $masked_data, $previous );
    }

    /**
     * Create a ShipmentException indicating a missing shipment.
     *
     * @param array $data Optional additional contextual data.
     * @return self
     */
    public static function notFound( array $data = array() ): self {
        return new self( __( 'Shipment not found for the order', 'nova-post-for-woocommerce' ), 404, $data );
    }

    /**
     * Recursively mask sensitive fields (IBAN, tax identification number) in arrays.
     *
     * @param array $data Data to mask.
     * @return array Masked data.
     */
    public static function mask_sensitive_data( array $data ): array {
        $sensitive_keys = array(
            'bankAccountId',
            'bankAccountName',
            'cod_bank_account_id',
            'cod_bank_account_name',
        );

        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                $data[ $key ] = self::mask_sensitive_data( $value );
            } elseif ( is_string( $value ) && in_array( $key, $sensitive_keys, true ) ) {
                $data[ $key ] = self::mask_string( $value );
            }
        }

        return $data;
    }

    /**
     * Mask a sensitive string value.
     *
     * @param string $value Value to mask.
     * @return string
     */
    public static function mask_string( string $value ): string {
        $len = strlen( $value );
        if ( $len <= 6 ) {
            return str_repeat( '*', $len );
        }

        return substr( $value, 0, 2 ) . str_repeat( '*', max( 4, $len - 6 ) ) . substr( $value, -4 );
    }
}
