<?php
/**
 * Formatter service for PickupExceptions into WP_Error objects.
 *
 * @package    NovaPostWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Exceptions\PickupException;
use WP_Error;

/**
 * Converts PickupException into a frontend-friendly WP_Error.
 *
 * Known Nova Post API error codes (field-suffix:code) are mapped to localized
 * human-readable messages. Unrecognized pairs fall back to the raw "{key}: {value}" string
 * so that new/unmapped errors remain visible to developers and store operators.
 */
class PickupErrorFormatter {

    /**
     * Format a PickupException into a WP_Error.
     *
     * Each entry in PickupException::get_data()['errors'] is examined:
     * - If the entry key is a string and the (field-suffix, error-code) pair is in the
     *   known mapping, the localized message replaces the raw "{key}: {value}" string.
     * - Otherwise the raw "{key}: {value}" format is preserved (fallback).
     * The WP_Error message is taken from the first resolved errors entry, then from
     * PickupException::getMessage(), then from a static 'Validation error' default.
     *
     * @param PickupException $e            The exception to format.
     * @param string          $default_code Optional WP_Error code.
     * @return WP_Error The formatted WP_Error with localized messages where applicable.
     */
    public static function format( PickupException $e, string $default_code = PickupException::PICKUP_CREATION_FAILED ): WP_Error {
        $data   = $e->get_data();
        $errors = array();

        if ( ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
            foreach ( $data['errors'] as $key => $value ) {
                if ( is_string( $key ) ) {
                    $localized = self::match_error_message( $key, (string) $value );
                    $errors[]  = null !== $localized ? $localized : "{$key}: {$value}";
                } else {
                    $errors[] = (string) $value;
                }
            }
        }

        $message = $errors[0] ?? $e->getMessage() ?? 'Validation error';

        return new WP_Error(
            $default_code,
            $message,
            array(
                'errors' => $errors,
                'status' => $data['status'] ?? 422,
            )
        );
    }

    /**
     * Return a localized message for the given Nova Post API error key/value pair, or null
     * when the pair is not in the known mapping.
     *
     * The lookup key is built from the field suffix (substring after the last `::` in $key)
     * combined with the error code $value, separated by a colon.
     *
     * @param string $key   The full Nova Post API error key (e.g. "TimeIntervalFindService::city").
     * @param string $value The error code value (e.g. "validation.invalid_address").
     * @return string|null  Localized message, or null when no mapping is found.
     */
    private static function match_error_message( string $key, string $value ): ?string {
        $separator_pos = strrpos( $key, '::' );
        $field_suffix  = false !== $separator_pos ? substr( $key, $separator_pos + 2 ) : $key;
        $lookup_key    = "{$field_suffix}:{$value}";
        $map           = self::get_error_message_map();

        return $map[ $lookup_key ] ?? null;
    }

    /**
     * Return the map of known Nova Post API error (field-suffix:code) pairs to localized messages.
     *
     * Keys follow the pattern "<field-suffix>:<error-code>", where the field suffix is the part
     * of the API error key after the last `::`. All three city-validation codes are mapped to
     * the same "sender's city not recognized" message.
     *
     * @return array<string, string> Map of lookup keys to translated message strings.
     */
    private static function get_error_message_map(): array {
        $city_message = __( "The sender's city specified in Nova Post settings is not recognized. Try entering the city name in the local language.", 'nova-post-for-woocommerce' );

        return array(
            'city:validation.invalid_address' => $city_message,
        );
    }
}
