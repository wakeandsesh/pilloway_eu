<?php
/**
 * NovaPost API Factory
 *
 * This file contains the NovaPost factory class for creating and managing
 * NovaPost API client instances.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Factories
 */

namespace NovaPostForWoocommerce\Factories;

use Exception;
use NovaDigital\NovaPost\DI\ContainerBuilder;
use NovaDigital\NovaPost\NovaPostApiFactory;
use Psr\Http\Client\ClientInterface;
use NovaDigital\NovaPost\DI\Container;
use NovaDigital\NovaPost\Http\NovaPostClient;
use NovaDigital\NovaPost\Http\ResponseValidatorInterface;
use NovaDigital\NovaPost\Http\RetryHandlerInterface;
use NovaDigital\NovaPost\NovaPostApi;
use NovaDigital\NovaPost\Storage\JwtTokenStorageInterface;
use NovaDigital\NovaPost\TokenProviderInterface;
use NovaPostForWoocommerce\Http\Client;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Models\JwtTokenDBStorage;
use NovaPostForWoocommerce\Services\OpensslService as Openssl;
use Psr\Log\LoggerInterface;

/**
 * Class NovaPost
 *
 * Factory class for creating and managing NovaPost API client instances.
 * This class provides a singleton instance of the NovaPost API client.
 */
final class NovaPost {

    /**
     * The NovaPost API client instance.
     *
     * @var NovaPostApi
     */
    private static NovaPostApi $nova_post_api;

    /**
     * The Dependency Injection Container.
     *
     * @var Container
     */
    private static Container $container;

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @return void
     */
    private function __construct() {
        // Private constructor to prevent direct instantiation.
    }

    /**
     * Get the NovaPost API client instance.
     *
     * Creates a new instance if one doesn't exist, otherwise returns the existing one.
     *
     * @return NovaPostApi The NovaPost API client instance.
     * @throws Exception If the API key is not configured or invalid.
     */
    public static function get_client(): NovaPostApi {
        if ( isset( self::$nova_post_api ) ) {
            return self::$nova_post_api;
        }

        self::build_client();

        return self::$nova_post_api;
    }

    /**
     * Get the Dependency Injection Container.
     *
     * @return Container
     */
    public static function get_container(): Container {
        if ( ! isset( self::$container ) ) {
            self::build_client();
        }

        return self::$container;
    }

    /**
     * Fetch and decrypt the NovaPost API key.
     *
     * Retrieves the appropriate API key based on the current environment (sandbox or production).
     *
     * @param bool $use_sandbox Whether to use the sandbox API key.
     * @return string The decrypted API key.
     * @throws Exception If the API key is not configured or decryption fails.
     */
    private static function fetch_api_key( bool $use_sandbox = false ): string {
        $api_key_encrypted = $use_sandbox ? get_option( 'npforwc_shipping_stage_api_key' ) : get_option( 'npforwc_shipping_api_key' );
        if ( ! $api_key_encrypted ) {
            throw new Exception( 'NovaPost API Key is not configured.' );
        }

        $api_key = Openssl::decrypt( $api_key_encrypted );
        if ( ! $api_key ) {
            throw new Exception( 'Cannot decrypt API Key.' );
        }

        return $api_key;
    }

    /**
     * Build NovaPost Api Client.
     */
    private static function build_client() {
        $use_sandbox = (bool) get_option( 'npforwc_shipping_debug' );

        $container_builder = ( new ContainerBuilder() )
            ->bind( LoggerInterface::class, Logger::class )
            ->bind( NovaPostClient::class, Client::class )
            ->bind( JwtTokenStorageInterface::class, JwtTokenDBStorage::class )
            ->setParameter( 'apiKey', self::fetch_api_key( $use_sandbox ) )
            ->setParameter( 'useSandbox', $use_sandbox );

        self::$container = $container_builder->build();

        $nova_post_api = new NovaPostApi( self::$container );

        self::$nova_post_api = $nova_post_api;
    }
}
