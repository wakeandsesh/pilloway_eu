<?php
/**
 * Settings repository. Transparently encrypts the bot token at rest.
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify\Core;

defined( 'ABSPATH' ) || exit;

class Settings {

	private const SECRET_KEYS = array( 'bot_token' );

	private const ARRAY_KEYS = array( 'new_order_statuses', 'status_statuses' );

	/** @var array|null */
	private static $cache = null;

	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$raw = get_option( 'catcode_tgnotify_settings', Installer::default_settings() );
		if ( ! is_array( $raw ) ) {
			$raw = Installer::default_settings();
		}
		$raw = wp_parse_args( $raw, Installer::default_settings() );

		foreach ( self::SECRET_KEYS as $secret ) {
			if ( isset( $raw[ $secret ] ) && '' !== $raw[ $secret ] ) {
				$raw[ $secret ] = Crypto::decrypt( (string) $raw[ $secret ] );
			}
		}

		foreach ( self::ARRAY_KEYS as $key ) {
			if ( ! is_array( $raw[ $key ] ) ) {
				$raw[ $key ] = Installer::default_settings()[ $key ];
			}
		}

		self::$cache = $raw;
		return $raw;
	}

	public static function get( string $key, $default = null ) {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}

	public static function save( array $values ): void {
		foreach ( self::SECRET_KEYS as $secret ) {
			if ( isset( $values[ $secret ] ) && '' !== $values[ $secret ] ) {
				$values[ $secret ] = Crypto::encrypt( (string) $values[ $secret ] );
			}
		}
		update_option( 'catcode_tgnotify_settings', $values, true );
		self::$cache = null;
	}

	public static function is_configured(): bool {
		return '' !== (string) self::get( 'bot_token', '' ) && array() !== self::chats();
	}

	/**
	 * Parse the chat list. One target per line: a numeric chat id, an @channel
	 * username, optionally suffixed with a forum topic id after a colon
	 * (e.g. "-1001234567890:42"). Blank lines and "#" comments are ignored.
	 *
	 * @return array<int,array{chat_id:string,thread_id:int}>
	 */
	public static function chats(): array {
		$raw  = (string) self::get( 'chat_ids', '' );
		$out  = array();
		$seen = array();

		foreach ( preg_split( '/[\r\n,]+/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || 0 === strncmp( $line, '#', 1 ) ) {
				continue;
			}

			$thread = 0;
			// Split off a trailing ":<digits>" topic id; @usernames never contain colons.
			if ( preg_match( '/^(.+):(\d+)$/', $line, $m ) ) {
				$line   = trim( $m[1] );
				$thread = (int) $m[2];
			}
			if ( '' === $line || isset( $seen[ $line . ':' . $thread ] ) ) {
				continue;
			}
			$seen[ $line . ':' . $thread ] = true;

			$out[] = array(
				'chat_id'   => $line,
				'thread_id' => $thread,
			);
		}

		return $out;
	}
}
