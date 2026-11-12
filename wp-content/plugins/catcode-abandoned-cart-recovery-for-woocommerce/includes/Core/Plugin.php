<?php
/**
 * Plugin singleton — wires every component together.
 *
 * @package CatCodeAbandonedCart
 */

namespace CatCode\AbandonedCart\Core;

use CatCode\AbandonedCart\Admin\CartsPage;
use CatCode\AbandonedCart\Admin\SettingsPage;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	/** @var CartsPage|null */
	private $carts_page = null;

	/** @var SettingsPage|null */
	private $settings_page = null;

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

		( new Capture() )->register();
		( new Recovery() )->register();
		( new Cron() )->register();
		( new Rest() )->register();
		( new Privacy() )->register();

		add_action( 'wp_enqueue_scripts', array( $this, 'front_assets' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );

		if ( is_admin() ) {
			$this->carts_page = new CartsPage();
			$this->carts_page->register();

			$this->settings_page = new SettingsPage();
			$this->settings_page->register();

			add_filter( 'plugin_action_links_' . CATCODE_ABANDONED_CART_BASENAME, array( $this, 'action_links' ) );
		}
	}

	/**
	 * Keep the schema and the cron events in step after an update.
	 */
	public function maybe_upgrade(): void {
		$installed = get_option( 'catcode_abandoned_cart_version' );
		if ( CATCODE_ABANDONED_CART_VERSION === $installed ) {
			return;
		}
		Installer::create_table();
		Cron::schedule();
		update_option( 'catcode_abandoned_cart_version', CATCODE_ABANDONED_CART_VERSION, false );
	}

	/**
	 * Front-end capture script — checkout pages only, both classic and block.
	 */
	public function front_assets(): void {
		if ( is_admin() || ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		wp_enqueue_script(
			'catcode-abandoned-cart-capture',
			CATCODE_ABANDONED_CART_URL . 'assets/js/capture.js',
			array(),
			CATCODE_ABANDONED_CART_VERSION,
			true
		);
		wp_localize_script(
			'catcode-abandoned-cart-capture',
			'catcodeAbandonedCart',
			array(
				'endpoint' => esc_url_raw( rest_url( Rest::REST_NAMESPACE . '/capture' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * @param array<int,string> $links Plugin row links.
	 * @return array<int,string>
	 */
	public function action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=' . SettingsPage::SLUG ) ) . '">'
			. esc_html__( 'Settings', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</a>';
		$carts    = '<a href="' . esc_url( admin_url( 'admin.php?page=' . CartsPage::SLUG ) ) . '">'
			. esc_html__( 'Carts', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</a>';

		array_unshift( $links, $settings, $carts );
		return $links;
	}
}
