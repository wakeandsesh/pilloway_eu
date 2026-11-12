<?php
/**
 * Openssl Service.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services;

/**
 * Class Openssl.
 */
class OpensslService {

    private const CIPHER_METHOD = 'AES-256-CBC';

    /**
     * Method encrypts data.
     *
     * @param string $data Data to encrypt.
     * @return string
     */
    public static function encrypt( $data ) {
        if ( empty( $data ) ) {
            return '';
        }

        $key            = self::get_encryption_key();
        $iv_length      = openssl_cipher_iv_length( self::CIPHER_METHOD );
        $iv             = openssl_random_pseudo_bytes( $iv_length );
        $encrypted_data = openssl_encrypt( $data, self::CIPHER_METHOD, $key, 0, $iv );

        return base64_encode( $iv . $encrypted_data ); // phpcs:ignore
    }

    /**
     * Method decrypts data.
     *
     * @param string $data Data to decrypt.
     * @return false|string
     */
    public static function decrypt( $data ) {
        if ( empty( $data ) ) {
            return '';
        }

        $key            = self::get_encryption_key();
        $decoded_data   = base64_decode( $data ); // phpcs:ignore
        $iv_length      = openssl_cipher_iv_length( self::CIPHER_METHOD );
        $iv             = substr( $decoded_data, 0, $iv_length );
        $encrypted_data = substr( $decoded_data, $iv_length );

        return openssl_decrypt( $encrypted_data, self::CIPHER_METHOD, $key, 0, $iv );
    }

    /**
     * Method returns decryption key.
     *
     * @return string
     */
    public static function get_encryption_key() {
        $key_source = AUTH_KEY . NONCE_KEY . LOGGED_IN_SALT;

        return hash( 'sha256', $key_source );
    }
}
