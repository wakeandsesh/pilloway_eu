<?php
/**
 * Telegram Bot API client.
 *
 * All calls are plain JSON POSTs to https://api.telegram.org/bot<token>/<method>.
 * Telegram answers {"ok":true,"result":…} or {"ok":false,"description":…,
 * "error_code":N} — HTTP status alone is not enough, so both are inspected.
 *
 * Docs: https://core.telegram.org/bots/api
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify\Api;

use CatCode\TelegramNotify\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Client {

	/** Telegram hard-caps a message at 4096 UTF-8 chars. */
	private const MAX_LEN = 4096;

	/** @var string */
	private $token;

	public function __construct( ?string $token = null ) {
		$this->token = null !== $token ? trim( $token ) : trim( (string) Settings::get( 'bot_token', '' ) );
	}

	/**
	 * Cheap authenticated read used by the "test connection" button.
	 *
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	public function get_me(): array {
		return $this->request( 'getMe' );
	}

	/**
	 * Pull pending updates so the admin can discover a chat id without leaving
	 * WP: the user writes any message to the bot / adds it to a group, then hits
	 * "Find chat_id".
	 *
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	public function get_updates(): array {
		return $this->request( 'getUpdates', array( 'limit' => 20 ) );
	}

	/**
	 * @param string $chat_id   Numeric id or @username.
	 * @param string $text      Message body (HTML parse mode).
	 * @param int    $thread_id Forum topic id, 0 for none.
	 * @param bool   $silent    Deliver without a notification sound.
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	public function send_message( string $chat_id, string $text, int $thread_id = 0, bool $silent = false ): array {
		$payload = array(
			'chat_id'    => $chat_id,
			'text'       => self::truncate( $text ),
			'parse_mode' => 'HTML',
			// Order links would otherwise render a fat preview card under every message.
			'link_preview_options' => array( 'is_disabled' => true ),
		);
		if ( $thread_id > 0 ) {
			$payload['message_thread_id'] = $thread_id;
		}
		if ( $silent ) {
			$payload['disable_notification'] = true;
		}

		return $this->request( 'sendMessage', $payload );
	}

	/**
	 * Cut to Telegram's limit on a character boundary, leaving a marker so a
	 * clipped message is obvious rather than mysteriously short.
	 */
	public static function truncate( string $text ): string {
		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ) ) <= self::MAX_LEN ) {
			return $text;
		}
		return ( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_LEN - 2 ) : substr( $text, 0, self::MAX_LEN - 2 ) ) . '…';
	}

	/**
	 * @param string $method Bot API method name.
	 * @param array  $body   JSON body.
	 * @return array{ok:bool,status:int,body:string,json:?array,error:string}
	 */
	private function request( string $method, array $body = array() ): array {
		if ( '' === $this->token ) {
			return array(
				'ok'     => false,
				'status' => 0,
				'body'   => '',
				'json'   => null,
				'error'  => __( 'Bot token is not set.', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			);
		}

		$response = wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $this->token ) . '/' . $method,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'status' => 0,
				'body'   => '',
				'json'   => null,
				'error'  => $response->get_error_message(),
			);
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		$json    = is_array( $decoded ) ? $decoded : null;

		$ok = ( $status >= 200 && $status < 300 ) && is_array( $json ) && ! empty( $json['ok'] );

		$error = '';
		if ( ! $ok ) {
			if ( is_array( $json ) && ! empty( $json['description'] ) ) {
				$error = (string) $json['description'];
			} else {
				$error = 'HTTP ' . $status;
			}
		}

		return array(
			'ok'     => $ok,
			'status' => $status,
			'body'   => $raw,
			'json'   => $json,
			'error'  => $error,
		);
	}
}
