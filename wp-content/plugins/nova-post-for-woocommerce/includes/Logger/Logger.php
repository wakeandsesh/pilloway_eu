<?php
/**
 * Logging Service.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Logger;

use Psr\Log\AbstractLogger;

/**
 * Class responsible for logging.
 */
class Logger extends AbstractLogger {

    private const LOG_FILE_NAME = 'nova-post-shipping';

    /**
     * Log errors and debugging messages.
     *
     * @param string $level   The level of the message.
     * @param string $message The message to be logged.
     * @param array  $context The context of the error.
     */
    public function log( $level, $message, $context = array() ): void {
        self::write_log( $level, $message, $context );
    }

    /**
     * Log errors and debugging messages.
     *
     * @param string $level   The level of the message.
     * @param string $message The message to be logged.
     * @param array  $context The context of the error.
     */
    public static function write_log( $level, $message, $context = array() ): void {
        if ( ! function_exists( 'did_action' ) || ! did_action( 'woocommerce_loaded' ) > 0 ) {
            return;
        }

        $logger      = wc_get_logger();
        $handler     = array( 'source' => self::LOG_FILE_NAME );
        $context_str = wp_json_encode( $context );
        $info        = "[{$level}]: {$message} {$context_str}";
        $logger->log( $level, $info, $handler );
    }
}
