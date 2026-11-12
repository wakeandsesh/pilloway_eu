<?php
/**
 * Plugin Name: CatCode Order Notifications with Telegram for WooCommerce
 * Plugin URI: https://catcode.com.ua/modules/telegram-order-notifications-for-woocommerce/
 * Description: Instant Telegram notifications for WooCommerce orders and site form leads: new orders, status changes, failed payments, low stock, Contact Form 7. WooCommerce is optional. Flexible message templates, multiple chats and topics, and a delivery log.
 * Version: 1.3.1
 * Requires at least: 6.2
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Author: CatCode
 * Author URI: https://catcode.com.ua
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: catcode-order-notifications-with-telegram-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 10.7
 *
 * @package CatCodeTelegramNotify
 */

defined( 'ABSPATH' ) || exit;

// Per-constant guards keep WP's activation sandbox-scrape (which includes this
// file twice) from emitting "already defined" warnings — without ever skipping
// the include/hook registration below.
defined( 'CATCODE_TGNOTIFY_VERSION' ) || define( 'CATCODE_TGNOTIFY_VERSION', '1.3.1' );
defined( 'CATCODE_TGNOTIFY_FILE' ) || define( 'CATCODE_TGNOTIFY_FILE', __FILE__ );
defined( 'CATCODE_TGNOTIFY_DIR' ) || define( 'CATCODE_TGNOTIFY_DIR', plugin_dir_path( __FILE__ ) );
defined( 'CATCODE_TGNOTIFY_URL' ) || define( 'CATCODE_TGNOTIFY_URL', plugin_dir_url( __FILE__ ) );
defined( 'CATCODE_TGNOTIFY_BASENAME' ) || define( 'CATCODE_TGNOTIFY_BASENAME', plugin_basename( __FILE__ ) );

// The wordpress.org build ships the same classes under the same namespace, so
// running both copies at once used to end in "Cannot declare class … already in
// use". Stand down instead of taking the site with us.
if ( class_exists( '\CatCode\TelegramNotify\Core\Crypto', false ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Two copies of Telegram Notifications are active. Keep only one of them.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</p></div>';
		}
	);
	return;
}

// Explicit, dependency-ordered includes. (An spl_autoload_register closure
// proved unreliable across SAPIs on some hosts, so we load the small class set
// directly — deterministic and fast.)
foreach (
	array(
		'includes/Core/Crypto.php',
		'includes/Core/Logger.php',
		'includes/Core/Settings.php',
		'includes/Core/Installer.php',
		'includes/Api/Client.php',
		'includes/Formatter.php',
		'includes/Notifier.php',
		'includes/Forms.php',
		'includes/Admin/SettingsPage.php',
		'includes/Admin/Notice.php',
		'includes/Core/Plugin.php',
	) as $catcode_tgnotify_inc
) {
	require_once CATCODE_TGNOTIFY_DIR . $catcode_tgnotify_inc;
}
unset( $catcode_tgnotify_inc );

register_activation_hook(
	__FILE__,
	static function () {
		require_once __DIR__ . '/includes/Core/Installer.php';
		\CatCode\TelegramNotify\Core\Installer::activate();
	}
);
register_deactivation_hook(
	__FILE__,
	static function () {
		require_once __DIR__ . '/includes/Core/Installer.php';
		\CatCode\TelegramNotify\Core\Installer::deactivate();
	}
);

// translate.wordpress.org lags behind the code: the language pack it serves is
// missing the strings added after the last import, and WP loads only that file
// for wordpress.org plugins — so those strings showed up in English on an
// otherwise Ukrainian admin. Load the bundled catalogue first; the language
// pack is merged over it later (just-in-time), so community translations still
// win wherever they exist and the bundle only fills the gaps.
add_action(
	'init',
	static function () {
		$domain = 'catcode-order-notifications-with-telegram-for-woocommerce';
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$mofile = CATCODE_TGNOTIFY_DIR . 'languages/' . $domain . '-' . $locale . '.mo';

		if ( is_readable( $mofile ) ) {
			load_textdomain( $domain, $mofile );
		}
	},
	1
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		// WooCommerce is optional. Without it the plugin runs in forms-only mode:
		// site leads (Contact Form 7 / catcode_lead_captured) still go to Telegram
		// and the order events simply stay out of the way.
		if ( ! \CatCode\TelegramNotify\Core\Plugin::has_woocommerce() && ! \CatCode\TelegramNotify\Core\Plugin::has_forms_source() ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-warning"><p>' . esc_html__( 'Telegram Notifications: neither WooCommerce nor a form source (Contact Form 7) was found, so nothing will be sent yet.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</p></div>';
				}
			);
		}
		\CatCode\TelegramNotify\Core\Plugin::instance()->boot();
	}
);
