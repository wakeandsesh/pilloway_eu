<?php
/**
 * JWT Token Repository
 *
 * Handles storage and retrieval of JWT tokens for the NovaPost API.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Models
 */

namespace NovaPostForWoocommerce\Models;

/**
 * Class JwtTokenRepository
 *
 * Manages the storage and retrieval of JWT tokens used for API authentication.
 */
class JwtTokenRepository {

    /**
     * Option name for storing JWT token data.
     */
    public const JWT_TOKEN_OPTION_NAME = 'npforwc_jwt_token_data';

    /**
     * Fetch the stored JWT token.
     *
     * @return string|null The JWT token if available, null otherwise.
     */
    public function fetch(): ?string {
        $value = get_option( self::JWT_TOKEN_OPTION_NAME, null );

        if ( null === $value || '' === $value ) {
            return null;
        }

        // Backward compatibility.
        if ( is_array( $value ) ) {
            return $value['jwt'] ?? null;
        }

        // New format.
        return is_string( $value ) ? $value : null;
    }

    /**
     * Save a JWT token.
     *
     * @param string $token The JWT token to save.
     * @return void
     */
    public function save( string $token ): void {
        update_option( self::JWT_TOKEN_OPTION_NAME, $token );
    }

    /**
     * Delete the stored JWT token.
     *
     * @return void
     */
    public function delete(): void {
        delete_option( self::JWT_TOKEN_OPTION_NAME );
    }
}
