<?php
/**
 * Nova Post Session Management.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Models;

use WC_Session;
use WC_Session_Handler;
use NovaPostForWoocommerce\Models\WcSession;

/**
 * Class UserStorage
 *
 * A singleton wrapper for managing Nova Post shipping data in the WC session.
 * Ensures data is always fresh and avoids issues with direct session handling.
 */
final class UserStorage {

    /**
     * The key used to store the data in the WooCommerce session.
     */
    private const SESSION_KEY = 'npforwc_shipping_session_data';

    /**
     * The single instance of the class.
     *
     * @var UserStorage|null
     */
    private static ?UserStorage $instance = null;

    /**
     * The WooCommerce session handler.
     *
     * @var WC_Session|WC_Session_Handler|null
     */
    private $session;

    /**
     * UserStorage constructor.
     * Made private to prevent direct instantiation.
     */
    private function __construct() {
        $this->session = WcSession::init_and_get();
    }

    /**
     * Gets the single instance of the class.
     *
     * @return UserStorage
     */
    public static function get_instance(): UserStorage {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get all Nova Post session data.
     *
     * @param array $default_params Default value to return if no data is found.
     * @return array
     */
    public function all( array $default_params = array() ): array {
        if ( ! $this->session ) {
            return $default_params;
        }
        return $this->session->get( self::SESSION_KEY, $default_params ) ?? $default_params;
    }

    /**
     * Get a specific value from the session by key.
     *
     * @param string     $key The key of the data to retrieve.
     * @param mixed|null $default_params The default value to return if the key is not found.
     * @return mixed
     */
    public function get( string $key, $default_params = null ) {
        $data = $this->all();
        return $data[ $key ] ?? $default_params;
    }

    /**
     * Set a specific key-value pair in the session.
     * This will overwrite any existing value for the given key.
     *
     * @param string $key The key to set.
     * @param mixed  $value The value to store.
     * @return void
     */
    public function set( string $key, $value ): void {
        if ( ! $this->session ) {
            return;
        }
        $data         = $this->all();
        $data[ $key ] = $value;
        $this->session->set( self::SESSION_KEY, $data );
    }

    /**
     * Update session data by merging it with new data.
     *
     * @param array $new_data The new data to merge with the existing session data.
     * @return void
     */
    public function update( array $new_data ): void {
        if ( ! $this->session ) {
            return;
        }
        $old_data    = $this->all();
        $merged_data = array_merge( $old_data, $new_data );
        $this->session->set( self::SESSION_KEY, $merged_data );
    }

    /**
     * Check if a key exists in the session data.
     *
     * @param string $key The key to check.
     * @return bool
     */
    public function has( string $key ): bool {
        $data = $this->all();
        return isset( $data[ $key ] );
    }

    /**
     * Clear all Nova Post session data.
     *
     * @return void
     */
    public function clear(): void {
        if ( ! $this->session ) {
            return;
        }
        $this->session->set( self::SESSION_KEY, array() );
    }

    /**
     * Prevents cloning of the instance.
     */
    private function __clone() {
    }

    /**
     * Prevents unserialization of the instance.
     */
    public function __wakeup() {
    }
}
