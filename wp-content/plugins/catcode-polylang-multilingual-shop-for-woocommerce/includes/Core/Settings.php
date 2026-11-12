<?php
/**
 * Settings repository — one option holds the store-wide configuration.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Core;

defined( 'ABSPATH' ) || exit;

class Settings {

	public const OPTION = 'catcode_plms_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults(): array {
		return array(
			// Status of a product translation created with one click: draft | source (same as the original).
			'new_status'         => 'draft',
			// Create a missing category / tag / attribute term in the target language as a copy.
			'copy_terms'         => 'yes',
			// Remove the plugin's own data (settings, variation links, order language) on uninstall.
			'delete_data'        => 'no',
			// Groups of product data shared by all translations (see Product\Fields::groups()).
			'sync_groups'        => array(),
			// Translate payment / shipping method texts and checkout texts through Polylang strings.
			'texts'              => 'yes',
			// Licensing (see Pro\License — the CatCode licence-server client).
			'license_key'        => '',
			'license_status'     => '',
			'license_checked_at' => '',
			'license_expires_at' => '',
			'license_data'       => '',
			// trial | purchase — written from the server's answer.
			'license_kind'       => '',
			// '1' once the server confirmed a purchased key: Pro then outlives the term.
			'license_owned'      => '',
			// 0 = the owner has never started the trial. Only an explicit click writes it.
			'trial_started'      => 0,
		);
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
	 * @param string $key      Setting name.
	 * @param mixed  $fallback Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function is_on( string $key ): bool {
		return 'yes' === self::get( $key, 'no' );
	}

	/**
	 * Write a few keys without touching the rest.
	 *
	 * @param array<string,mixed> $patch Keys to overwrite.
	 */
	public static function update( array $patch ): void {
		// Straight from the option: the static cache may predate a write made
		// earlier in this same request.
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		update_option( self::OPTION, array_merge( self::defaults(), $raw, $patch ), false );
		self::$cache = null;
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}
}
