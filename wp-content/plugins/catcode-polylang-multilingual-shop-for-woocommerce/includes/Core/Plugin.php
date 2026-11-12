<?php
/**
 * Plugin singleton — wires every component together once WooCommerce and
 * Polylang are known to be there.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Core;

use CatCode\PolylangShop\Admin\PagesTool;
use CatCode\PolylangShop\Admin\ProductAdmin;
use CatCode\PolylangShop\Admin\SettingsPage;
use CatCode\PolylangShop\Front\Cart;
use CatCode\PolylangShop\Front\Pages;
use CatCode\PolylangShop\Front\ScriptTranslations;
use CatCode\PolylangShop\Front\Strings;
use CatCode\PolylangShop\Order\Coupons;
use CatCode\PolylangShop\Order\Emails;
use CatCode\PolylangShop\Order\OrderLanguage;
use CatCode\PolylangShop\Product\Sku;
use CatCode\PolylangShop\Product\Stock;
use CatCode\PolylangShop\Product\Sync;
use CatCode\PolylangShop\Admin\Ajax;
use CatCode\PolylangShop\Pro\Csv;
use CatCode\PolylangShop\Pro\License;
use CatCode\PolylangShop\Pro\Texts;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Bundled translations win over an empty language pack from translate.wordpress.org.
		add_action( 'init', array( $this, 'load_translations' ), 1 );

		if ( is_admin() ) {
			( new SettingsPage() )->register();
			add_filter( 'plugin_action_links_' . CATCODE_PLMS_BASENAME, array( $this, 'action_links' ) );
			add_action( 'admin_notices', array( $this, 'environment_notice' ) );
		}

		if ( ! Environment::ok() ) {
			return;
		}

		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );

		( new Sync() )->register();
		( new Stock() )->register();
		( new Sku() )->register();
		( new Pages() )->register();
		( new Cart() )->register();
		( new Strings() )->register();
		( new ScriptTranslations() )->register();
		( new OrderLanguage() )->register();
		( new Emails() )->register();
		( new Coupons() )->register();

		add_action( 'catcode_plms_license_check', array( License::class, 'verify' ) );
		add_action( 'init', array( $this, 'schedule_license_check' ) );
		if ( License::is_pro() ) {
			( new Csv() )->register();
			if ( Settings::is_on( 'texts' ) ) {
				( new Texts() )->register();
			}
		}

		if ( is_admin() ) {
			( new ProductAdmin() )->register();
			( new PagesTool() )->register();
			Ajax::register();
		}
	}

	public function maybe_upgrade(): void {
		if ( CATCODE_PLMS_VERSION === get_option( 'catcode_plms_version' ) ) {
			return;
		}
		update_option( 'catcode_plms_version', CATCODE_PLMS_VERSION, false );
	}

	public function environment_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$problems = Environment::problems();
		if ( empty( $problems ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Polylang Multilingual Shop is paused:', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</strong> ' . esc_html( implode( ' ', $problems ) ) . '</p></div>';
	}


	public function load_translations(): void {
		$locale = determine_locale();
		$file   = CATCODE_PLMS_DIR . 'languages/catcode-polylang-multilingual-shop-for-woocommerce-' . $locale . '.mo';
		if ( ! is_readable( $file ) && false !== strpos( $locale, '_' ) ) {
			// uk_UA → uk: the bundled Ukrainian file carries the short code.
			$file = CATCODE_PLMS_DIR . 'languages/catcode-polylang-multilingual-shop-for-woocommerce-' . substr( $locale, 0, (int) strpos( $locale, '_' ) ) . '.mo';
		}
		if ( is_readable( $file ) ) {
			unload_textdomain( 'catcode-polylang-multilingual-shop-for-woocommerce' );
			load_textdomain( 'catcode-polylang-multilingual-shop-for-woocommerce', $file );
		}
	}

	/** Daily licence re-check. A free install with no key never talks to our server. */
	public function schedule_license_check(): void {
		$next = wp_next_scheduled( 'catcode_plms_license_check' );
		if ( License::has_license() && ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'catcode_plms_license_check' );
		} elseif ( ! License::has_license() && $next ) {
			wp_unschedule_event( $next, 'catcode_plms_license_check' );
		}
	}


	/**
	 * @param array<int,string> $links Plugin row links.
	 * @return array<int,string>
	 */
	public function action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		array_unshift(
			$links,
			'<a href="' . esc_url( SettingsPage::url() ) . '">' . esc_html__( 'Settings', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</a>'
		);
		return $links;
	}
}
