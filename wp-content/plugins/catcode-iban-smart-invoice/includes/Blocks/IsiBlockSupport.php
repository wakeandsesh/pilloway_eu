<?php

declare(strict_types=1);

namespace CatCode\IbanSmartInvoice\Blocks;

if (!defined('ABSPATH')) { exit; }

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use CatCode\IbanSmartInvoice\Gateway;
use CatCode\IbanSmartInvoice\SettingsRepository;

/**
 * WC Blocks integration: реєструє наш payment method у блочному checkout.
 *
 * Legacy [woocommerce_checkout] shortcode підхоплює gateway сам через
 * `woocommerce_payment_gateways` filter. Block checkout (WC 8.3+) — окремий
 * пайплайн через `AbstractPaymentMethodType` + client-side React-реєстрацію.
 */
final class IsiBlockSupport extends AbstractPaymentMethodType
{
    protected $name = Gateway::ID;

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_' . Gateway::ID . '_settings', []);
    }

    public function is_active(): bool
    {
        // Типове значення — як у поля «Активувати» способу оплати ('yes'):
        // поки налаштування WooCommerce не збережені, класичний чекаут уже
        // показує метод, і блоковий мусить поводитись так само.
        $enabled = ($this->settings['enabled'] ?? 'yes') === 'yes';
        if (!$enabled) {
            return false;
        }
        return Gateway::ibanReady();
    }

    public function get_payment_method_script_handles(): array
    {
        // Handle convention `wc-payment-method-<id>` мірорить core WC Bacs (`wc-payment-method-bacs`).
        $handle = 'wc-payment-method-catcode-iban-smart-invoice';

        wp_register_script(
            $handle,
            CATCODE_IBAN_SMART_INVOICE_PLUGIN_URL . 'assets/js/blocks.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'],
            CATCODE_IBAN_SMART_INVOICE_VERSION,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations($handle, 'catcode-iban-smart-invoice');
        }

        return [$handle];
    }

    public function get_payment_method_data(): array
    {
        return [
            'title'       => apply_filters(
                'woocommerce_gateway_title',
                (string) ($this->settings['title'] ?? __('Bank transfer to IBAN / card (Monobank / Privat24)', 'catcode-iban-smart-invoice')),
                Gateway::ID
            ),
            'description' => apply_filters(
                'woocommerce_gateway_description',
                (string) ($this->settings['description'] ?? ''),
                Gateway::ID
            ),
            'supports'    => ['products'],
        ];
    }
}
