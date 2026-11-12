<?php
/**
 * Settings Page input normalizer.
 *
 * @package NovaPostForWoocommerce\Normalization
 */

namespace NovaPostForWoocommerce\Normalization;

/**
 * Normalizes settings page input values before validation.
 */
class SettingsPageNormalizer {

    /**
     * Decimal precision rules for each numeric field.
     *
     * @var array<string,int>
     */
    private static array $decimal_precision = array(
        'default_weight'                         => 3,
        'default_height'                         => 1,
        'default_width'                          => 1,
        'default_length'                         => 1,
        'default_height_product'                 => 1,
        'default_width_product'                  => 1,
        'default_length_product'                 => 1,
        'shipping_division_flat_rate_amount'     => 2,
        'shipping_division_free_delivery_amount' => 2,
        'shipping_address_flat_rate_amount'      => 2,
        'shipping_address_free_delivery_amount'  => 2,
    );

    /**
     * Normalize request data before validation.
     *
     * @param array $data Raw request data.
     * @return array Normalized request data.
     */
    public static function normalize( array $data ): array {
        $data = self::normalize_standard_fields( $data );

        return $data;
    }

    /**
     * Normalizes standard top-level fields defined in precision map.
     *
     * @param array<string,mixed> $data Raw settings data.
     * @return array<string,mixed> Normalized data with formatted numeric values.
     */
    private static function normalize_standard_fields( array $data ): array {
        foreach ( self::$decimal_precision as $field => $precision ) {
            if ( ! isset( $data[ $field ] ) || '' === $data[ $field ] ) {
                continue;
            }

            $data[ $field ] = self::format_value( $data[ $field ], $precision );
        }

        return $data;
    }

    /**
     * Sorts and normalizes custom boxes by volume.
     *
     * @param array<string,mixed> $data Data array containing 'np_custom_boxes'.
     * @return array<string,mixed> Normalized data with sorted custom boxes.
     */
    public static function normalize_custom_boxes( array $data ): array {
        if ( empty( $data['np_custom_boxes'] ) || ! is_array( $data['np_custom_boxes'] ) ) {
            return $data;
        }

        foreach ( $data['np_custom_boxes'] as &$box ) {
            $box['width']  = self::format_value( $box['width'] ?? 0, 1 );
            $box['length'] = self::format_value( $box['length'] ?? 0, 1 );
            $box['height'] = self::format_value( $box['height'] ?? 0, 1 );
        }
        unset( $box );

        usort(
            $data['np_custom_boxes'],
            function ( $a, $b ) {
                $volume_a = (float) $a['width'] * (float) $a['length'] * (float) $a['height'];
                $volume_b = (float) $b['width'] * (float) $b['length'] * (float) $b['height'];

                return $volume_a <=> $volume_b;
            }
        );

        return $data;
    }

    /**
     * Helper to format a single numeric value.
     *
     * @param mixed $value     The value to format.
     * @param int   $precision Number of decimal places to format the value to.
     * @return string Formatted numeric string.
     */
    private static function format_value( $value, int $precision ): string {
        $value = str_replace( ',', '.', (string) $value );

        if ( ! is_numeric( $value ) ) {
            return (string) $value;
        }

        $rounded = round( (float) $value, $precision, PHP_ROUND_HALF_UP );

        return number_format( $rounded, $precision, '.', '' );
    }
}
