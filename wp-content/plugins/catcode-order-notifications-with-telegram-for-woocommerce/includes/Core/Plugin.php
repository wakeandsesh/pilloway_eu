<?php
/**
 * Plugin singleton.
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify\Core;

defined( 'ABSPATH' ) || exit;

class Plugin {

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

	/**
	 * Order events need WooCommerce; everything else does not.
	 */
	public static function has_woocommerce(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Any lead source we can listen to on a shop-less site.
	 */
	public static function has_forms_source(): bool {
		return class_exists( 'WPCF7' ) || has_action( 'catcode_lead_captured' );
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'maybe_upgrade' ) );

		if ( self::has_woocommerce() ) {
			( new \CatCode\TelegramNotify\Notifier() )->register_hooks();
		}
		( new \CatCode\TelegramNotify\Forms() )->register_hooks();

		// Registered on the front end too: the first successful send usually
		// happens during a checkout request, not in wp-admin.
		\CatCode\TelegramNotify\Admin\Notice::register();

		if ( is_admin() ) {
			new \CatCode\TelegramNotify\Admin\SettingsPage();
		}
	}

	public function maybe_upgrade(): void {
		$installed = get_option( 'catcode_tgnotify_version' );
		if ( CATCODE_TGNOTIFY_VERSION === $installed ) {
			return;
		}
		Installer::activate();

		$cfg = get_option( 'catcode_tgnotify_settings' );
		if ( is_array( $cfg ) ) {
			$cfg = wp_parse_args( $cfg, Installer::default_settings() );
			update_option( 'catcode_tgnotify_settings', $cfg, true );
		}

		update_option( 'catcode_tgnotify_version', CATCODE_TGNOTIFY_VERSION, false );
	}
}
