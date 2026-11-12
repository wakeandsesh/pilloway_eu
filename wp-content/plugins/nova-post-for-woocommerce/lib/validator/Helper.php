<?php
/**
 * Helper File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */

namespace NovaPostForWoocommerce\Rakit\Validation;

/**
 * Helper Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */
class Helper {


    /**
     * Determine if a given string matches a given pattern.
     * Adapted from: https://github.com/illuminate/support/blob/v5.3.23/Str.php#L119
     *
     * @param  string $pattern The pattern.
     * @param  string $value   The value.
     * @return bool
     */
    public static function strIs( string $pattern, string $value ): bool {
        if ( $pattern === $value ) {
            return true;
        }

        $pattern = preg_quote( $pattern, '#' );

        // Asterisks are translated into zero-or-more regular expression wildcards
        // to make it convenient to check if the strings starts with the given
        // pattern such as "library/*", making any string check convenient.
        $pattern = str_replace( '\*', '.*', $pattern );

        return (bool) preg_match( '#^' . $pattern . '\z#u', $value );
    }

    /**
     * Check if an item or items exist in an array using "dot" notation.
     * Adapted from: https://github.com/illuminate/support/blob/v5.3.23/Arr.php#L81
     *
     * @param  array  $target The target array.
     * @param  string $key    The key.
     * @return bool
     */
    public static function arrayHas( array $target, string $key ): bool {
        if ( array_key_exists( $key, $target ) ) {
            return true;
        }

        foreach ( explode( '.', $key ) as $segment ) {
            if ( is_array( $target ) && array_key_exists( $segment, $target ) ) {
                $target = $target[ $segment ];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Get an item from an array using "dot" notation.
     * Adapted from: https://github.com/illuminate/support/blob/v5.3.23/Arr.php#L246
     *
     * @param  array       $target        The target array.
     * @param  string|null $key           The key.
     * @param  mixed       $default_value The default value.
     * @return mixed
     */
    public static function arrayGet( array $target, $key, $default_value = null ) {
        if ( is_null( $key ) ) {
            return $target;
        }

        if ( array_key_exists( $key, $target ) ) {
            return $target[ $key ];
        }

        foreach ( explode( '.', $key ) as $segment ) {
            if ( is_array( $target ) && array_key_exists( $segment, $target ) ) {
                $target = $target[ $segment ];
            } else {
                return $default_value;
            }
        }

        return $target;
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     * Adapted from: https://github.com/illuminate/support/blob/v5.3.23/Arr.php#L81
     *
     * @param  array  $target  The target array.
     * @param  string $prepend The prepend string.
     * @return array
     */
    public static function arrayDot( array $target, string $prepend = '' ): array {
        $results = array();

        foreach ( $target as $key => $value ) {
            if ( is_array( $value ) && ! empty( $value ) ) {
                $results = array_merge( $results, static::arrayDot( $value, $prepend . $key . '.' ) );
            } else {
                $results[ $prepend . $key ] = $value;
            }
        }

        return $results;
    }

    /**
     * Set an item on an array or object using dot notation.
     * Adapted from: https://github.com/illuminate/support/blob/v5.3.23/helpers.php#L437
     *
     * @param mixed             $target    The target.
     * @param string|array|null $key       The key.
     * @param mixed             $value     The value.
     * @param bool              $overwrite Whether to overwrite.
     * @return mixed
     */
    public static function arraySet( &$target, $key, $value, $overwrite = true ): array {
        if ( is_null( $key ) ) {
            if ( $overwrite ) {
                $target = array_merge( $target, $value );
                return $target;
            }
            $target = array_merge( $value, $target );
            return $target;
        }

        $segments = is_array( $key ) ? $key : explode( '.', $key );
        $segment  = array_shift( $segments );

        if ( '*' === $segment ) {
            if ( ! is_array( $target ) ) {
                $target = array();
            }

            if ( $segments ) {
                foreach ( $target as &$inner ) {
                    static::arraySet( $inner, $segments, $value, $overwrite );
                }
            } elseif ( $overwrite ) {
                foreach ( $target as &$inner ) {
                    $inner = $value;
                }
            }
        } elseif ( is_array( $target ) ) {
            if ( $segments ) {
                if ( ! array_key_exists( $segment, $target ) ) {
                    $target[ $segment ] = array();
                }

                static::arraySet( $target[ $segment ], $segments, $value, $overwrite );
            } elseif ( $overwrite || ! array_key_exists( $segment, $target ) ) {
                $target[ $segment ] = $value;
            }
        } else {
            $target = array();

            if ( $segments ) {
                static::arraySet( $target[ $segment ], $segments, $value, $overwrite );
            } elseif ( $overwrite ) {
                $target[ $segment ] = $value;
            }
        }

        return $target;
    }

    /**
     * Unset an item on an array or object using dot notation.
     *
     * @param  mixed        $target The target.
     * @param  string|array $key    The key.
     * @return mixed
     */
    public static function arrayUnset( &$target, $key ) {
        if ( ! is_array( $target ) ) {
            return $target;
        }

        $segments = is_array( $key ) ? $key : explode( '.', $key );
        $segment  = array_shift( $segments );

        if ( '*' === $segment ) {
            $target = array();
        } elseif ( $segments ) {
            if ( array_key_exists( $segment, $target ) ) {
                static::arrayUnset( $target[ $segment ], $segments );
            }
        } elseif ( array_key_exists( $segment, $target ) ) {
            unset( $target[ $segment ] );
        }

        return $target;
    }

    /**
     * Get snake_case format from given string
     *
     * @param  string $value     The value.
     * @param  string $delimiter The delimiter.
     * @return string
     */
    public static function snakeCase( string $value, string $delimiter = '_' ): string {
        if ( ! ctype_lower( $value ) ) {
            $value = preg_replace( '/\s+/u', '', ucwords( $value ) );
            $value = strtolower( preg_replace( '/(.)(?=[A-Z])/u', '$1' . $delimiter, $value ) );
        }

        return $value;
    }

    /**
     * Join string[] to string with given $separator and $lastSeparator.
     *
     * @param  array       $pieces        The pieces.
     * @param  string      $separator     The separator.
     * @param  string|null $lastSeparator The last separator.
     * @return string
     */
    public static function join( array $pieces, string $separator, string $lastSeparator = null ): string {
        if ( is_null( $lastSeparator ) ) {
            $lastSeparator = $separator;
        }

        $last = array_pop( $pieces );

        switch ( count( $pieces ) ) {
            case 0:
                return $last ? $last : '';
            case 1:
                return $pieces[0] . $lastSeparator . $last;
            default:
                return implode( $separator, $pieces ) . $lastSeparator . $last;
        }
    }

    /**
     * Wrap string[] by given $prefix and $suffix
     *
     * @param  array       $strings The strings.
     * @param  string      $prefix  The prefix.
     * @param  string|null $suffix  The suffix.
     * @return array
     */
    public static function wraps( array $strings, string $prefix, string $suffix = null ): array {
        if ( is_null( $suffix ) ) {
            $suffix = $prefix;
        }

        return array_map(
            function ( $str ) use ( $prefix, $suffix ) {
                return $prefix . $str . $suffix;
            },
            $strings
        );
    }
}
