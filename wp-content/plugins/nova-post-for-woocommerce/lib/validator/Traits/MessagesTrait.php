<?php
/**
 * MessagesTrait File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Traits
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Traits;

/**
 * MessagesTrait
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Traits
 */
trait MessagesTrait {

    /**
     * The messages
     *
     * @var array
     */
    protected $messages = array();

    /**
     * Given $key and $message to set message
     *
     * @param string $key     The key.
     * @param string $message The message.
     * @return void
     */
    public function setMessage( string $key, string $message ) {
        $this->messages[ $key ] = $message;
    }

    /**
     * Given $messages and set multiple messages
     *
     * @param array $messages The messages.
     * @return void
     */
    public function setMessages( array $messages ) {
        $this->messages = array_merge( $this->messages, $messages );
    }

    /**
     * Given message from given $key
     *
     * @param string $key The key.
     * @return string
     */
    public function getMessage( string $key ): string {
        return array_key_exists( $key, $this->messages ) ? $this->messages[ $key ] : $key;
    }

    /**
     * Get all $messages
     *
     * @return array
     */
    public function getMessages(): array {
        return $this->messages;
    }
}
