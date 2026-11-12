<?php
/**
 * Settings repository — a single option holding the whole configuration.
 *
 * @package CatCodeAbandonedCart
 */

namespace CatCode\AbandonedCart\Core;

defined( 'ABSPATH' ) || exit;

class Settings {

	public const OPTION = 'catcode_abandoned_cart_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults(): array {
		return array(
			// Capture / lifecycle.
			'abandon_after'   => 60,   // Minutes of inactivity before a cart counts as abandoned.
			'token_lifetime'  => 7,    // Days a recovery link stays valid.
			'retention_days'  => 90,   // Days before a finished cart row is deleted.
			'email_cooldown'  => 3,    // Days before the same address may be mailed again.

			// Reminder e-mail.
			'email_1_delay'   => 60,   // Minutes after abandonment.
			'email_1_subject' => 'You left something behind at {store_name}',
			'email_1_body'    => self::default_body(),
		);
	}

	public static function default_body(): string {
		return "Hi {customer_name},\n\n"
			. "You added a few things to your cart at {store_name} but did not complete the order:\n\n"
			. "{cart_items}\n\n"
			. "Your cart is saved — one click takes you straight back to it: {recovery_link}\n\n"
			. '— {store_name}';
	}

	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		self::$cache = wp_parse_args( $raw, self::defaults() );
		return self::$cache;
	}

	/**
	 * @param string $key     Setting name.
	 * @param mixed  $default Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function get_int( string $key, int $default = 0 ): int {
		return (int) self::get( $key, $default );
	}

	public static function is_on( string $key ): bool {
		return 'yes' === self::get( $key, 'no' );
	}

	public static function save( array $values ): void {
		update_option( self::OPTION, wp_parse_args( $values, self::defaults() ), false );
		self::$cache = null;
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * The configured reminder step — a single e-mail.
	 *
	 * @return array<int,array{step:int,delay:int,subject:string,body:string}>
	 */
	public static function email_steps(): array {
		return array(
			array(
				'step'    => 1,
				'delay'   => max( 1, self::get_int( 'email_1_delay', 60 ) ),
				'subject' => (string) self::get( 'email_1_subject', '' ),
				'body'    => (string) self::get( 'email_1_body', '' ),
			),
		);
	}
}
