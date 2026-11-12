<?php
/**
 * Plugin Name:       CatCode IBAN Smart Invoice
 * Plugin URI:        https://catcode.com.ua/modules/iban-smart-invoice
 * Description:       QR code (NBU v002) + Monobank/Privat24 deep-link buttons on the WooCommerce Thank You page. Optional Pro add-on adds automatic payment detection via Monobank Personal API webhook.
 * Version:           0.2.8
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * Author:            CatCode
 * Author URI:        https://catcode.com.ua
 * License:           GPL-2.0-or-later
 * Text Domain:       catcode-iban-smart-invoice
 * Domain Path:       /languages
 *
 * WC requires at least: 10.0
 * WC tested up to:      11.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * The full version (sold on catcode.com.ua) lives in its own folder and ships the same
 * classes and settings. While it is loaded or active this copy stays out of the way, so
 * the two never collide and the full version keeps working on the same data.
 */
if (
	( static function () {
		if ( defined( 'ISIP_PLUGIN_FILE' ) ) {
			return true;
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $active as $plugin ) {
			if ( 'iban-smart-invoice.php' === basename( (string) $plugin ) && file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
				return true;
			}
		}
		return false;
	} )()
) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-info"><p>'
				. esc_html__( 'The full version of this plugin from catcode.com.ua is active, so this copy is switched off and the full version runs on the same settings. You can deactivate or delete "CatCode IBAN Smart Invoice": its settings and data stay while the full version is installed.', 'catcode-iban-smart-invoice' )
				. '</p></div>';
		}
	);
	// Still declared while switched off, or WooCommerce lists this copy as incompatible.
	add_action(
		'before_woocommerce_init',
		static function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			}
		}
	);
	return;
}

define('CATCODE_IBAN_SMART_INVOICE_VERSION', '0.2.8');
define('CATCODE_IBAN_SMART_INVOICE_PLUGIN_FILE', __FILE__);
define('CATCODE_IBAN_SMART_INVOICE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CATCODE_IBAN_SMART_INVOICE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CATCODE_IBAN_SMART_INVOICE_DB_VERSION', '1');

if (file_exists(CATCODE_IBAN_SMART_INVOICE_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once CATCODE_IBAN_SMART_INVOICE_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'CatCode\\IbanSmartInvoice\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $path = CATCODE_IBAN_SMART_INVOICE_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';
        if (is_readable($path)) {
            require_once $path;
        }
    });
}

register_activation_hook(__FILE__, [\CatCode\IbanSmartInvoice\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\CatCode\IbanSmartInvoice\Activator::class, 'deactivate']);

// translate.wordpress.org lags behind the code: the language pack it serves is
// missing the strings added after the last import, and WP loads only that file
// for wordpress.org plugins — so those strings showed up in English on an
// otherwise Ukrainian admin. Load the bundled catalogue first; the language
// pack is merged over it later (just-in-time), so community translations still
// win wherever they exist and the bundle only fills the gaps.
add_action('init', static function (): void {
    $domain = 'catcode-iban-smart-invoice';
    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $mofile = CATCODE_IBAN_SMART_INVOICE_PLUGIN_DIR . 'languages/' . $domain . '-' . $locale . '.mo';

    if (is_readable($mofile)) {
        load_textdomain($domain, $mofile);
    }
}, 1);

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            CATCODE_IBAN_SMART_INVOICE_PLUGIN_FILE,
            true
        );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            CATCODE_IBAN_SMART_INVOICE_PLUGIN_FILE,
            true
        );
    }
});

add_action('woocommerce_blocks_loaded', static function (): void {
    if (!class_exists(\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class)) {
        return;
    }
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        static function ($registry): void {
            $registry->register(new \CatCode\IbanSmartInvoice\Blocks\IsiBlockSupport());
        }
    );
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), static function (array $links): array {
    $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . \CatCode\IbanSmartInvoice\Gateway::ID);
    $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'catcode-iban-smart-invoice') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

add_action('plugins_loaded', static function (): void {
    // WP 4.6+ loads translations by slug automatically.
    // (PluginCheck flags it as discouraged when invoked from the plugin itself).

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('IBAN Smart Invoice requires WooCommerce to be active.', 'catcode-iban-smart-invoice');
            echo '</p></div>';
        });
        return;
    }

    \CatCode\IbanSmartInvoice\Plugin::instance()->boot();
});
