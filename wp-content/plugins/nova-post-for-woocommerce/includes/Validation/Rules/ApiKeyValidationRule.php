<?php
/**
 * API Key Validation Rule for Rakit Validation
 *
 * This file contains the validation rule for NovaPost API key validation.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Validation
 */

namespace NovaPostForWoocommerce\Validation\Rules;

use NovaDigital\NovaPost\DI\ContainerBuilder;
use NovaDigital\NovaPost\Exception\ApiException;
use NovaDigital\NovaPost\Http\AuthClient;
use NovaPostForWoocommerce\Rakit\Validation\Rule;
use NovaDigital\NovaPost\Http\NovaPostClient;
use NovaDigital\NovaPost\Storage\JwtTokenStorageInterface;
use NovaPostForWoocommerce\Http\Client;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Models\JwtTokenDBStorage;
use Psr\Log\LoggerInterface;

/**
 * Class ApiKeyValidationRule
 *
 * Validates NovaPost API keys by attempting to authenticate with the NovaPost API.
 */
class ApiKeyValidationRule extends Rule {

    /**
     * Whether to use the sandbox environment.
     *
     * @var bool
     */
    private bool $use_sandbox;

    /**
     * Create a new rule instance.
     *
     * @param bool $use_sandbox Whether to use the sandbox environment.
     */
    public function __construct( bool $use_sandbox = false ) {
        $this->use_sandbox = $use_sandbox;
    }

    /**
     * Check the value is valid.
     *
     * @param mixed $value The value to validate.
     * @return bool
     */
    public function check( $value ): bool {
        try {
            $container_builder = ( new ContainerBuilder() )
                ->bind( LoggerInterface::class, Logger::class )
                ->bind( NovaPostClient::class, Client::class )
                ->bind( JwtTokenStorageInterface::class, JwtTokenDBStorage::class )
                ->setParameter( 'apiKey', $value )
                ->setParameter( 'useSandbox', $this->use_sandbox );

            $container   = $container_builder->build();
            $auth_client = $container->get( AuthClient::class );

            $token = $auth_client->getToken(); // Attempt to get a token.
            return true; // Success.
        } catch ( \Throwable $e ) {
            return false; // Failure.
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return __( 'The provided API key is invalid or failed to authenticate.', 'nova-post-for-woocommerce' );
    }
}
