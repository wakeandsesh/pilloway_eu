<?php

declare(strict_types=1);

namespace CatCode\IbanSmartInvoice;

if (!defined('ABSPATH')) { exit; }

/**
 * Free-plugin bootstrap. Registers the gateway and the admin-notice for
 * a missing IBAN, then fires `catcode_iban_smart_invoice/plugin/boot` so the Pro add-on (or any
 * third-party extension) can attach automatic-detection features.
 */
final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $this->maybeUpgradeDb();

        Gateway::register();

        if (is_admin()) {
            add_action('admin_notices', [self::class, 'maybeShowIbanMissingNotice']);
        }

        // Extension point — boots Pro add-on and anything else that wants to
        // layer features on top of the free gateway.
        do_action('catcode_iban_smart_invoice/plugin/boot');
    }

    public static function maybeShowIbanMissingNotice(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $iban = (string) SettingsRepository::get('iban', '');
        if ($iban !== '') {
            return;
        }
        // WooCommerce only writes woocommerce_<id>_settings after the gateway
        // settings form is saved for the first time. Until then the option does
        // not exist at all, while the gateway itself already defaults to
        // enabled — so a missing option means enabled, not disabled. Reading it
        // as 'no' is what kept this notice from ever reaching a fresh install,
        // which is exactly the install that needs it.
        $gateways = get_option('woocommerce_' . Gateway::ID . '_settings');
        $enabled  = is_array($gateways) ? ($gateways['enabled'] ?? 'yes') : 'yes';
        if ($enabled !== 'yes') {
            return;
        }
        $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . Gateway::ID);
        echo '<div class="notice notice-warning"><p>';
        printf(
            /* translators: %s: link to the settings page */
            esc_html__('IBAN Smart Invoice is enabled but no IBAN is set. The payment method stays hidden from customers until %s.', 'catcode-iban-smart-invoice'),
            '<a href="' . esc_url($url) . '">' . esc_html__('the settings are filled in', 'catcode-iban-smart-invoice') . '</a>'
        );
        echo '</p></div>';
    }

    private function maybeUpgradeDb(): void
    {
        $stored = (string) get_option('catcode_iban_smart_invoice_db_version', '');
        if ($stored === CATCODE_IBAN_SMART_INVOICE_DB_VERSION) {
            return;
        }
        Activator::activate();
    }

    private function __construct() {}
    private function __clone() {}
}
