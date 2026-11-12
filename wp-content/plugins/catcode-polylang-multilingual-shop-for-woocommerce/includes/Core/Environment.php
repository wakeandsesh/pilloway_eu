<?php
/**
 * What must be in place before the plugin does anything: WooCommerce, Polylang
 * 3.7+ with at least one language, and no second WooCommerce ↔ Polylang bridge
 * (two bridges would both rewrite the cart and both sync stock).
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Core;

defined( 'ABSPATH' ) || exit;

class Environment {

	public const MIN_POLYLANG = '3.7';

	/** @var string[]|null */
	private static $problems = null;

	/** @return string[] Human-readable reasons the plugin is idle; empty = all good. */
	public static function problems(): array {
		if ( null !== self::$problems ) {
			return self::$problems;
		}
		$out = array();
		if ( ! class_exists( 'WooCommerce' ) ) {
			$out[] = __( 'WooCommerce is not active.', 'catcode-polylang-multilingual-shop-for-woocommerce' );
		}
		if ( ! defined( 'POLYLANG_VERSION' ) || ! function_exists( 'pll_languages_list' ) ) {
			$out[] = __( 'Polylang is not active. Install and activate the free Polylang plugin from wordpress.org.', 'catcode-polylang-multilingual-shop-for-woocommerce' );
		} elseif ( version_compare( (string) POLYLANG_VERSION, self::MIN_POLYLANG, '<' ) ) {
			$out[] = sprintf(
				/* translators: 1: required Polylang version, 2: installed version. */
				__( 'Polylang %1$s or newer is required, %2$s is installed.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
				self::MIN_POLYLANG,
				(string) POLYLANG_VERSION
			);
		}
		if ( defined( 'PLLWC_VERSION' ) ) {
			$out[] = __( 'Polylang for WooCommerce is active. It already connects WooCommerce with Polylang — keep only one of the two plugins.', 'catcode-polylang-multilingual-shop-for-woocommerce' );
		}
		if ( defined( 'Hyyan_WPI_DIR' ) ) {
			$out[] = __( 'Hyyan WooCommerce Polylang Integration is active. Deactivate it: both plugins would translate the cart and synchronise stock at the same time.', 'catcode-polylang-multilingual-shop-for-woocommerce' );
		}
		self::$problems = $out;
		return $out;
	}

	public static function ok(): bool {
		return array() === self::problems();
	}

	/** Polylang is fine but no language has been added yet. */
	public static function needs_languages(): bool {
		return self::ok() && ! Lang::ready();
	}
}
