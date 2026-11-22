<?php
/**
 * Formatter service for Shipment API errors.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

/**
 * Maps Nova Post API shipment creation error codes to human-readable localized messages.
 */
class ShipmentErrorFormatter {

    /**
     * Format an array of API errors.
     *
     * @param array $errors Raw API errors (key => code, or list of codes).
     * @return array Formatted localized error messages.
     */
    public static function format_errors( array $errors ): array {
        $formatted = array();

        foreach ( $errors as $key => $code ) {
            $formatted[] = self::match_error_message( (string) $key, (string) $code );
        }

        return array_values( array_unique( $formatted ) );
    }

    /**
     * Match a single error key and code to a localized message.
     *
     * @param string $key  The error key/field path.
     * @param string $code The error code.
     * @return string Localized message or fallback string.
     */
    public static function match_error_message( string $key, string $code ): string {
        // ERR-10: PUDO location when recipient pays in cash.
        if (
            str_contains( $code, 'validation.condition.cannot_create_shipment_to_PUDO_location_when_the_recipient_pays_in_cash' )
            || str_contains( $key, 'conditionId_59' )
        ) {
            return __( 'Cash on delivery is not available for Pick Up Drop Off points. Change the delivery point in the order or the payment method.', 'nova-post-for-woocommerce' );
        }

        // ERR-11: IBAN not linked to company profile.
        if ( str_contains( $key, 'bankAccountName' ) && 'validation.invalid' === $code ) {
            return __( 'The IBAN is not linked to your company profile at Nova Post. Check the IBAN in the COD settings or contact Nova Post support.', 'nova-post-for-woocommerce' );
        }

        // ERR-12: Required / filled bankAccountName -> ERR-09.
        if ( str_contains( $key, 'bankAccountName' ) && in_array( $code, array( 'validation.required', 'validation.filled' ), true ) ) {
            return __( 'IBAN is required for cash on delivery. Fill it in the COD settings.', 'nova-post-for-woocommerce' );
        }

        // ERR-12: Required / filled bankAccountId -> ERR-08.
        if ( str_contains( $key, 'bankAccountId' ) && in_array( $code, array( 'validation.required', 'validation.filled' ), true ) ) {
            return __( 'Bank Account Id (tax identification number) is required for cash on delivery. Fill it in the COD settings.', 'nova-post-for-woocommerce' );
        }

        // ERR-13: Amount exceeds insurance or incorrect amount (BR-32).
        if (
            'validation.incorrect_amount' === $code
            || 'validation.cod.amount_exceeds_insurance_total_cost' === $code
            || str_contains( $code, 'amount_exceeds_insurance_total_cost' )
        ) {
            return __( 'The cash on delivery amount exceeds the declared value of the shipment due to a difference in currency exchange rates. Try creating the shipment again later.', 'nova-post-for-woocommerce' );
        }

        // ERR-14: Payer contract number errors.
        if (
            str_contains( $key, 'payerContractNumber' )
            || str_contains( $code, 'payer.contract.wrong_company' )
        ) {
            return __( 'The contract number is invalid or does not belong to your company. Check Contract Numbers in Nova Post Settings.', 'nova-post-for-woocommerce' );
        }

        // ERR-15: COD currency code not supported.
        if ( str_contains( $key, 'currencyCode' ) && 'validation.exists' === $code ) {
            return __( 'The COD currency is not supported by Nova Post. Select another currency in the COD settings.', 'nova-post-for-woocommerce' );
        }

        // Fallback for unrecognized errors.
        return ! empty( $key ) && ! is_numeric( $key ) ? "{$key}: {$code}" : $code;
    }
}
