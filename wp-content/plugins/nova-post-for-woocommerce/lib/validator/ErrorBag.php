<?php
/**
 * ErrorBag File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */

namespace NovaPostForWoocommerce\Rakit\Validation;

/**
 * ErrorBag Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */
class ErrorBag {


    /**
     * Error messages
     *
     * @var array
     */
    protected $messages = array();

    /**
     * Constructor
     *
     * @param array $messages Initial messages.
     * @return void
     */
    public function __construct( array $messages = array() ) {
        $this->messages = $messages;
    }

    /**
     * Add message for given key and rule
     *
     * @param string $key     The key.
     * @param string $rule    The rule.
     * @param string $message The message.
     * @return void
     */
    public function add( string $key, string $rule, string $message ) {
        if ( ! isset( $this->messages[ $key ] ) ) {
            $this->messages[ $key ] = array();
        }

        $this->messages[ $key ][ $rule ] = $message;
    }

    /**
     * Get messages count
     *
     * @return int
     */
    public function count(): int {
        return count( $this->all() );
    }

    /**
     * Check given key is existed
     *
     * @param string $key The key to check.
     * @return bool
     */
    public function has( string $key ): bool {
        list($key, $ruleName) = $this->parsekey( $key );
        if ( $this->isWildcardKey( $key ) ) {
            $messages = $this->filterMessagesForWildcardKey( $key, $ruleName );
            return count( Helper::arrayDot( $messages ) ) > 0;
        } else {
            $messages = isset( $this->messages[ $key ] ) ? $this->messages[ $key ] : null;

            if ( ! $ruleName ) {
                return ! empty( $messages );
            } else {
                return ! empty( $messages ) && isset( $messages[ $ruleName ] );
            }
        }
    }

    /**
     * Get the first value of array
     *
     * @param string $key The key.
     * @return mixed
     */
    public function first( string $key ) {
        list($key, $ruleName) = $this->parsekey( $key );
        if ( $this->isWildcardKey( $key ) ) {
            $messages        = $this->filterMessagesForWildcardKey( $key, $ruleName );
            $flattenMessages = Helper::arrayDot( $messages );
            return array_shift( $flattenMessages );
        } else {
            $keyMessages = isset( $this->messages[ $key ] ) ? $this->messages[ $key ] : array();

            if ( empty( $keyMessages ) ) {
                return null;
            }

            if ( $ruleName ) {
                return isset( $keyMessages[ $ruleName ] ) ? $keyMessages[ $ruleName ] : null;
            } else {
                return array_shift( $keyMessages );
            }
        }
    }

    /**
     * Get messages from given key, can be use custom format
     *
     * @param string $key    The key.
     * @param string $format The format.
     * @return array
     */
    public function get( string $key, string $format = ':message' ): array {
        list($key, $ruleName) = $this->parsekey( $key );
        $results              = array();
        if ( $this->isWildcardKey( $key ) ) {
            $messages = $this->filterMessagesForWildcardKey( $key, $ruleName );
            foreach ( $messages as $explicitKey => $keyMessages ) {
                foreach ( $keyMessages as $rule => $message ) {
                    $results[ $explicitKey ][ $rule ] = $this->formatMessage( $message, $format );
                }
            }
        } else {
            $keyMessages = isset( $this->messages[ $key ] ) ? $this->messages[ $key ] : array();
            foreach ( $keyMessages as $rule => $message ) {
                if ( $ruleName && $ruleName !== $rule ) {
                    continue;
                }
                $results[ $rule ] = $this->formatMessage( $message, $format );
            }
        }

        return $results;
    }

    /**
     * Get all messages
     *
     * @param string $format The format.
     * @return array
     */
    public function all( string $format = ':message' ): array {
        $messages = $this->messages;
        $results  = array();
        foreach ( $messages as $key => $keyMessages ) {
            foreach ( $keyMessages as $message ) {
                $results[] = $this->formatMessage( $message, $format );
            }
        }
        return $results;
    }

    /**
     * Get the first message from existing keys
     *
     * @param string  $format      The format.
     * @param boolean $dotNotation Whether to use dot notation.
     * @return array
     */
    public function firstOfAll( string $format = ':message', bool $dotNotation = false ): array {
        $messages = $this->messages;
        $results  = array();
        foreach ( $messages as $key => $keyMessages ) {
            if ( $dotNotation ) {
                $results[ $key ] = $this->formatMessage( array_shift( $messages[ $key ] ), $format );
            } else {
                Helper::arraySet( $results, $key, $this->formatMessage( array_shift( $messages[ $key ] ), $format ) );
            }
        }
        return $results;
    }

    /**
     * Get the first message from all messages
     *
     * @param string $format The format.
     * @return string|null
     */
    public function firstMessage( string $format = ':message' ) {
        $all = $this->all( $format );
        return $all[0] ?? null;
    }

    /**
     * Get all messages as flat array
     *
     * @param string $format The format.
     * @return array
     */
    public function allMessages( string $format = ':message' ): array {
        $results = array();

        foreach ( $this->messages as $fieldName => $keyMessages ) {
            foreach ( $keyMessages as $message ) {
                if ( ! isset( $results[ $fieldName ] ) ) {
                    $results[ $fieldName ] = array();
                }

                $results[ $fieldName ][] = $this->formatMessage( $message, $format );
            }
        }

        return $results;
    }

    /**
     * Get plain array messages
     *
     * @return array
     */
    public function toArray(): array {
        return $this->messages;
    }

    /**
     * Parse $key to get the array of $key and $ruleName
     *
     * @param string $key The key.
     * @return array
     */
    protected function parseKey( string $key ): array {
        $expl     = explode( ':', $key, 2 );
        $key      = $expl[0];
        $ruleName = isset( $expl[1] ) ? $expl[1] : null;
        return array( $key, $ruleName );
    }

    /**
     * Check the $key is wildcard
     *
     * @param string $key The key.
     * @return bool
     */
    protected function isWildcardKey( string $key ): bool {
        return false !== strpos( $key, '*' );
    }

    /**
     * Filter messages with wildcard key
     *
     * @param string      $key      The key.
     * @param string|null $ruleName The rule name.
     * @return array
     */
    protected function filterMessagesForWildcardKey( string $key, $ruleName = null ): array {
        $messages = $this->messages;
        $pattern  = preg_quote( $key, '#' );
        $pattern  = str_replace( '\*', '.*', $pattern );

        $filteredMessages = array();

        foreach ( $messages as $k => $keyMessages ) {
            if ( (bool) preg_match( '#^' . $pattern . '\z#u', $k ) === false ) {
                continue;
            }

            foreach ( $keyMessages as $rule => $message ) {
                if ( $ruleName && $rule !== $ruleName ) {
                    continue;
                }
                $filteredMessages[ $k ][ $rule ] = $message;
            }
        }

        return $filteredMessages;
    }

    /**
     * Get formatted message
     *
     * @param string $message The message.
     * @param string $format  The format.
     * @return string
     */
    protected function formatMessage( string $message, string $format ): string {
        return str_replace( ':message', $message, $format );
    }
}
