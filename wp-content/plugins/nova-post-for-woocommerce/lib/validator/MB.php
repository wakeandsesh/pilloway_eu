<?php
/**
 * MB Helper File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */

namespace NovaPostForWoocommerce\Rakit\Validation;

/**
 * MB Helper Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */
class MB {

    /**
     * Get string length
     *
     * @param string      $str      The string.
     * @param string|null $encoding The encoding.
     * @return int
     */
    public static function strlen( $str, $encoding = null ) {
        if ( function_exists( 'mb_strlen' ) ) {
            return mb_strlen( $str, $encoding ? $encoding : 'UTF-8' );
        }
        return strlen( $str );
    }

    /**
     * Make a string lowercase
     *
     * @param string      $str      The string.
     * @param string|null $encoding The encoding.
     * @return string
     */
    public static function strtolower( $str, $encoding = null ) {
        if ( function_exists( 'mb_strtolower' ) ) {
            return mb_strtolower( $str, $encoding ? $encoding : mb_detect_encoding( $str ) );
        }
        return strtolower( $str );
    }

    /**
     * Make a string uppercase
     *
     * @param string      $str      The string.
     * @param string|null $encoding The encoding.
     * @return string
     */
    public static function strtoupper( $str, $encoding = null ) {
        if ( function_exists( 'mb_strtoupper' ) ) {
            return mb_strtoupper( $str, $encoding ? $encoding : mb_detect_encoding( $str ) );
        }
        return strtoupper( $str );
    }
}
