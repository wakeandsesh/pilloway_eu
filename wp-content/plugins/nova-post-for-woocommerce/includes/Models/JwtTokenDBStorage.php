<?php
/**
 * JWT Token Database Storage
 *
 * Implements JwtTokenStorageInterface to store and retrieve JWT tokens
 * from the WordPress options table using JwtTokenRepository.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Models
 */

namespace NovaPostForWoocommerce\Models;

use NovaDigital\NovaPost\Storage\JwtTokenStorageInterface;

/**
 * Class JwtTokenDBStorage
 *
 * Provides database storage implementation for JWT tokens
 * by implementing JwtTokenStorageInterface.
 */
class JwtTokenDBStorage implements JwtTokenStorageInterface {
    /**
     * Repository for JWT token operations.
     *
     * @var JwtTokenRepository
     */
    private JwtTokenRepository $jwt_token_repository;

    /**
     * Constructor.
     *
     * Initializes the JWT token repository.
     */
    public function __construct() {
        $this->jwt_token_repository = new JwtTokenRepository();
    }

    /**
     * Retrieves the stored JWT token.
     *
     * @return string|null The JWT token if available, null otherwise.
     */
    public function get(): ?string {
        return $this->jwt_token_repository->fetch();
    }

    /**
     * Saves the JWT token.
     *
     * @param string $token The JWT token to save.
     * @return void
     */
    public function save( string $token ): void {
        $this->jwt_token_repository->save( $token );
    }

    /**
     * Deletes the stored JWT token.
     *
     * @return void
     */
    public function delete(): void {
        $this->jwt_token_repository->delete();
    }
}
