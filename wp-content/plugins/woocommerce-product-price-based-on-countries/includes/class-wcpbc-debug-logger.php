<?php
/**
 * Debug logger tool.
 *
 * @package WCPBC
 * @since 2.3.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WCPBC_Debug_Logger' ) ) {
	return;
}

/**
 * WCPBC_Debug_Logger Class
 */
class WCPBC_Debug_Logger {

	/**
	 * WooCommerce logger class instance.
	 *
	 * @var WC_Logger
	 */
	private static $logger = null;

	/**
	 * Register a service.
	 */
	public static function init() {
		if ( function_exists( 'wc_get_logger' ) ) {
			self::$logger = wc_get_logger();
		}
	}

	/**
	 * Log an error.
	 *
	 * @param string $message Log message.
	 * @param string $method Method name that logs the message.
	 */
	public static function log_error( string $message, string $method ) {
		self::log( $message, $method, WC_Log_Levels::ERROR );
	}

	/**
	 * Log a JSON response.
	 *
	 * @param mixed  $response Response as object/array.
	 * @param string $method Method name that logs the message.
	 */
	public static function log_response( $response, string $method ) {
		$message = wp_json_encode( $response, JSON_PRETTY_PRINT );
		self::log( $message, $method );
	}

	/**
	 * Log a generic note.
	 *
	 * @param string $message Log message.
	 * @param string $method Method name that logs the message.
	 */
	public static function log_message( string $message, string $method ) {
		self::log( $message, $method );
	}

	/**
	 * Log a message as a debug log entry.
	 *
	 * @param string $message Log message.
	 * @param string $method Method name that logs the message.
	 * @param string $level Log level.
	 */
	private static function log( string $message, string $method, string $level = WC_Log_Levels::DEBUG ) {
		if ( ! self::$logger ) {
			return;
		}

		self::$logger->log(
			$level,
			sprintf( '%s %s', $method, $message ),
			[
				'source' => 'wc-price-based-country',
			]
		);
	}
}
