<?php

declare(strict_types=1);

namespace CatCode\IbanSmartInvoice;

if (!defined('ABSPATH')) { exit; }

/**
 * WooCommerce payment gateway "Оплата на IBAN/картку (Monobank / Privat24)".
 *
 * Logic:
 * 1. Checkout shows our method like any other gateway.
 * 2. process_payment() saves the memo on the order, sets it to on-hold, redirects to Thank You.
 * 3. Thank You renders templates/thankyou-payment.php (QR / Monobank / Privat24 / copy).
 * 4. Reconciliation:
 *    - Free version: shop owner marks the order paid manually from WC → Orders.
 *    - With the Pro add-on installed: Mono Personal API webhook + OrderMatcher flip the
 *      order to processing automatically (see PUBLIC HOOKS below).
 *
 * PUBLIC HOOKS (used by iban-smart-invoice-pro and any third-party extension):
 *   filter catcode_iban_smart_invoice/gateway/pro_form_fields($fields, $settings) — return extra WC settings fields
 *                                                                inserted before display_section.
 *   action catcode_iban_smart_invoice/gateway/webhook_field_html($key, $data)     — render the body of a custom
 *                                                                type='catcode_iban_smart_invoice_webhook' settings field.
 *   filter catcode_iban_smart_invoice/gateway/sync_patch($patch, $gateway_settings) — mutate the patch handed to
 *                                                                SettingsRepository on save.
 *   filter catcode_iban_smart_invoice/thankyou/amount($amount, $order, $expected) — return the amount we should
 *                                                                build the QR / deep-links for
 *                                                                (Pro returns remaining when partial).
 *   filter catcode_iban_smart_invoice/thankyou/partial_summary($current, $order)  — null when nothing partial yet;
 *                                                                an array {received, remaining,
 *                                                                is_partial} when Pro has data.
 *   filter catcode_iban_smart_invoice/thankyou/localize($data, $order)            — augment CATCODE_IBAN_SMART_INVOICE_THANKYOU JS payload.
 */
final class Gateway extends \WC_Payment_Gateway
{
    public const ID = 'isi_bank_transfer';

    public function __construct()
    {
        $this->id                 = self::ID;
        $this->has_fields         = false;
        $this->method_title       = __('IBAN Smart Invoice', 'catcode-iban-smart-invoice');
        $this->method_description = __('Pay to IBAN with a QR code and Monobank/Privat24 deep links. Automatic payment detection is available via the IBAN Smart Invoice — Pro add-on.', 'catcode-iban-smart-invoice');
        $this->supports           = ['products'];
        $this->icon               = '';

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = (string) $this->get_option('title');
        $this->description = (string) $this->get_option('description');
        $this->enabled     = (string) $this->get_option('enabled', 'yes');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [self::class, 'syncGatewaySettings'], 20);
        add_action('woocommerce_thankyou_' . $this->id, [self::class, 'renderThankYou']);
        add_action('woocommerce_email_before_order_table', [self::class, 'renderEmailInstructions'], 10, 4);
    }

    public function init_form_fields(): void
    {
        $settings = SettingsRepository::all();

        $fields = [
            'enabled' => [
                'title'   => __('Enable', 'catcode-iban-smart-invoice'),
                'type'    => 'checkbox',
                'label'   => __('Show on the checkout page', 'catcode-iban-smart-invoice'),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __('Payment method title', 'catcode-iban-smart-invoice'),
                'type'        => 'text',
                'description' => __('What the customer sees in the list of payment methods.', 'catcode-iban-smart-invoice'),
                'default'     => __('Bank transfer to IBAN / card (Monobank / Privat24)', 'catcode-iban-smart-invoice'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Description under the title', 'catcode-iban-smart-invoice'),
                'type'        => 'textarea',
                'description' => __('Shown to the customer after they choose this payment method.', 'catcode-iban-smart-invoice'),
                'default'     => __('A QR code and Monobank/Privat24 buttons are on the next page. We will confirm your payment manually as soon as it arrives.', 'catcode-iban-smart-invoice'),
                'desc_tip'    => true,
            ],

            'requisites_section' => [
                'title' => __('Beneficiary details', 'catcode-iban-smart-invoice'),
                'type'  => 'title',
                'description' => __('Your sole trader / company details. They are used in the QR code and the email instructions.', 'catcode-iban-smart-invoice'),
            ],
            'iban' => [
                'title'       => __('IBAN', 'catcode-iban-smart-invoice'),
                'type'        => 'text',
                'default'     => $settings['iban'],
                'placeholder' => 'UA000000000000000000000000000',
                'description' => __('29 characters, starts with UA. The checksum is validated per ISO 13616.', 'catcode-iban-smart-invoice'),
                'desc_tip'    => true,
                'css'         => 'min-width:340px;',
            ],
            'beneficiary_name' => [
                'title'       => __('Beneficiary', 'catcode-iban-smart-invoice'),
                'type'        => 'text',
                'default'     => $settings['beneficiary_name'],
                'description' => __('Sole trader / company name exactly as registered with your bank.', 'catcode-iban-smart-invoice'),
                'desc_tip'    => true,
                'css'         => 'min-width:340px;',
            ],
            'beneficiary_edrpou' => [
                'title'       => __('EDRPOU / tax number', 'catcode-iban-smart-invoice'),
                'type'        => 'text',
                'default'     => $settings['beneficiary_edrpou'],
                'description' => __('Optional. Some banks require it in the payment reference.', 'catcode-iban-smart-invoice'),
                'desc_tip'    => true,
            ],
            'memo_template' => [
                'title'       => __('Payment reference', 'catcode-iban-smart-invoice'),
                'type'        => 'text',
                'default'     => $settings['memo_template'] === Memo::DEFAULT_TEMPLATE
                    ? Memo::defaultTemplate()
                    : $settings['memo_template'],
                'placeholder' => Memo::defaultTemplate(),
                'description' => __('What the customer sees in the "Payment reference" field of their bank. <code>{order_id}</code> is replaced with the order number (for example "Payment for order #42"). Keep this token — the Pro add-on looks for it in the Monobank statement to match a payment against an order.', 'catcode-iban-smart-invoice'),
            ],

            'display_section' => [
                'title' => __('What to show the customer on the Thank You page', 'catcode-iban-smart-invoice'),
                'type'  => 'title',
            ],
            'show_qr' => [
                'title'   => __('QR code', 'catcode-iban-smart-invoice'),
                'type'    => 'checkbox',
                'label'   => __('Show the QR code (the customer scans it in their banking app and the amount and details are filled in automatically)', 'catcode-iban-smart-invoice'),
                'default' => $settings['show_qr'],
            ],
            'show_monobank' => [
                'title'   => __('"Open in Monobank" button', 'catcode-iban-smart-invoice'),
                'type'    => 'checkbox',
                'label'   => __('Show the quick transfer button for the Monobank app', 'catcode-iban-smart-invoice'),
                'default' => $settings['show_monobank'],
            ],
            'monobank_send_link' => [
                'title'       => __('Link to your Monobank jar', 'catcode-iban-smart-invoice'),
                'type'        => 'text',
                'default'     => $settings['monobank_send_link'],
                'placeholder' => 'https://send.monobank.ua/jar/...',
                'description' => __('If you have a <strong>Monobank jar</strong> (savings account), paste its link here, for example <code>https://send.monobank.ua/jar/abc123</code>. The button opens Monobank with the amount and reference already filled in. Leave the field empty to hide the button.', 'catcode-iban-smart-invoice'),
                'css'         => 'min-width:340px;',
            ],
            'show_privat24' => [
                'title'   => __('"Open in Privat24" button', 'catcode-iban-smart-invoice'),
                'type'    => 'checkbox',
                'label'   => __('Show the quick transfer button for Privat24', 'catcode-iban-smart-invoice'),
                'default' => $settings['show_privat24'],
            ],
            'privat24_link' => [
                'title'       => __('Link to your Privat24 template', 'catcode-iban-smart-invoice'),
                'type'        => 'text',
                'default'     => $settings['privat24_link'],
                'placeholder' => 'https://next.privat24.ua/payments/form/...',
                'description' => __('If you have a <strong>Privat24 transfer template</strong>, paste its link here. Leave the field empty to hide the button.', 'catcode-iban-smart-invoice'),
                'css'         => 'min-width:340px;',
            ],
        ];

        // Extension point: Pro add-on (or any other plugin) returns extra fields
        // here, to be inserted before display_section. If nothing hooks in, we
        // show a single promo block pointing at the Pro upgrade.
        $proFields = (array) apply_filters('catcode_iban_smart_invoice/gateway/pro_form_fields', [], $settings);

        if (!empty($proFields)) {
            $fields = self::insertBefore($fields, 'display_section', $proFields);
        } else {
            $fields = self::insertBefore($fields, 'display_section', [
                'pro_promo' => [
                    'title' => __('Automatic payment detection — Pro add-on', 'catcode-iban-smart-invoice'),
                    'type'  => 'title',
                    'description' => __('In the free version the customer pays with the QR code and you confirm the payment manually in WooCommerce → Orders. A separate add-on adds a Monobank Personal API webhook, so an order moves to "Processing" automatically about 3 seconds after payment. See the developer website for details.', 'catcode-iban-smart-invoice'),
                ],
            ]);
        }

        $this->form_fields = $fields;
    }

    /**
     * @param array<string,array<string,mixed>> $fields
     * @param string $beforeKey
     * @param array<string,array<string,mixed>> $insert
     * @return array<string,array<string,mixed>>
     */
    private static function insertBefore(array $fields, string $beforeKey, array $insert): array
    {
        $out = [];
        foreach ($fields as $k => $v) {
            if ($k === $beforeKey) {
                foreach ($insert as $ik => $iv) {
                    $out[$ik] = $iv;
                }
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /**
     * Custom field renderer for type='catcode_iban_smart_invoice_webhook'. Empty by default — Pro
     * add-on hooks into `catcode_iban_smart_invoice/gateway/webhook_field_html` to print the
     * "Connect Monobank" UI. WC::generate_settings_html looks for a
     * generate_<type>_html method on the gateway, so we have to declare it
     * here even though the body is contributed externally.
     */
    public function generate_catcode_iban_smart_invoice_webhook_html(string $key, array $data): string
    {
        ob_start();
        do_action('catcode_iban_smart_invoice/gateway/webhook_field_html', $key, $data);
        return (string) ob_get_clean();
    }

    /**
     * Project gateway settings onto the shared `catcode_iban_smart_invoice_settings` option, where
     * the rest of the plugin reads them. The Pro add-on hooks `catcode_iban_smart_invoice/gateway/sync_patch`
     * to add an encrypted mono_token to the patch and scrub the plaintext.
     */
    public static function syncGatewaySettings(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $gw = get_option('woocommerce_' . self::ID . '_settings');
        if (!is_array($gw)) {
            return;
        }

        $iban = trim((string) ($gw['iban'] ?? ''));
        if ($iban !== '' && !SettingsRepository::isIbanValid($iban)) {
            // Keep the last known-good IBAN in our own settings; WooCommerce keeps
            // whatever the merchant typed in its gateway option and shows the error
            // below, so they can correct it. We never write back to WooCommerce's
            // `woocommerce_{id}_settings` store — that belongs to WooCommerce core.
            \WC_Admin_Settings::add_error(__('The IBAN failed the checksum check. The other settings were saved, the IBAN was not.', 'catcode-iban-smart-invoice'));
            $iban = (string) SettingsRepository::get('iban', '');
        }

        $patch = self::patchFromGateway($gw, $iban);

        // Pro add-on hook: add encrypted mono_token to the patch, scrub plaintext from $gw.
        $patch = (array) apply_filters('catcode_iban_smart_invoice/gateway/sync_patch', $patch, $gw);

        SettingsRepository::update($patch);
    }

    private static function sanitizeMemo(string $raw): string
    {
        $raw = sanitize_text_field($raw);
        if ($raw === '') {
            return Memo::DEFAULT_TEMPLATE;
        }
        if (!str_contains($raw, '{order_id}')) {
            $raw .= ' #{order_id}';
        }
        return $raw;
    }

    /**
     * Hide the method on checkout when no valid IBAN is configured —
     * rendering the Thank You page without an IBAN would just be confusing.
     */
    public function is_available(): bool
    {
        if (!parent::is_available()) {
            return false;
        }
        return self::ibanReady();
    }

    /**
     * Чи є валідний IBAN, з яким можна показувати спосіб оплати.
     *
     * Реквізити живуть у двох місцях: у налаштуваннях способу оплати
     * WooCommerce (їх показує форма) і в нашій опції (її читає решта
     * плагіна). Видалення плагіна прибирає нашу опцію, а опцію WooCommerce
     * лишає — після повторного встановлення форма показує збережений IBAN,
     * а спосіб оплати зникав з чекауту, доки налаштування не збережуть ще раз.
     * Тому, якщо в нашій опції IBAN нема, а у WooCommerce лежить валідний,
     * переносимо реквізити до себе один раз і працюємо далі.
     *
     * Цю ж перевірку використовує інтеграція з блоковим чекаутом, щоб
     * класичний і блоковий чекаути показували метод за однакових умов.
     */
    public static function ibanReady(): bool
    {
        $iban = (string) SettingsRepository::get('iban', '');
        if ($iban !== '' && SettingsRepository::isIbanValid($iban)) {
            return true;
        }

        $gw = get_option('woocommerce_' . self::ID . '_settings');
        if (!is_array($gw)) {
            return false;
        }
        $gwIban = strtoupper((string) preg_replace('/\s+/', '', (string) ($gw['iban'] ?? '')));
        if ($gwIban === '' || !SettingsRepository::isIbanValid($gwIban)) {
            return false;
        }

        SettingsRepository::update(self::patchFromGateway($gw, $gwIban));
        return true;
    }

    /**
     * Реквізити й параметри показу з опції способу оплати WooCommerce у
     * форматі нашої опції. Секрети сюди не входять.
     *
     * @param array<string,mixed> $gw
     * @return array<string,string>
     */
    private static function patchFromGateway(array $gw, string $iban): array
    {
        return [
            'iban'               => $iban !== '' ? strtoupper((string) preg_replace('/\s+/', '', $iban)) : '',
            'beneficiary_name'   => sanitize_text_field((string) ($gw['beneficiary_name'] ?? '')),
            'beneficiary_edrpou' => sanitize_text_field((string) ($gw['beneficiary_edrpou'] ?? '')),
            'memo_template'      => self::sanitizeMemo((string) ($gw['memo_template'] ?? Memo::DEFAULT_TEMPLATE)),
            'show_qr'            => ($gw['show_qr'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'show_monobank'      => ($gw['show_monobank'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'show_privat24'      => ($gw['show_privat24'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'monobank_send_link' => esc_url_raw(trim((string) ($gw['monobank_send_link'] ?? '')), ['http', 'https']),
            'privat24_link'      => esc_url_raw(trim((string) ($gw['privat24_link'] ?? '')), ['http', 'https']),
        ];
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return ['result' => 'failure'];
        }

        $memo = Memo::forOrder($order);
        $order->update_meta_data(Memo::ORDER_META_KEY, $memo);
        $order->save();

        $order->update_status('on-hold', sprintf(
            /* translators: %s: payment reference */
            __('Awaiting payment to IBAN. Payment reference: %s', 'catcode-iban-smart-invoice'),
            $memo
        ));

        wc_reduce_stock_levels($order_id);

        if (function_exists('WC') && WC()->cart instanceof \WC_Cart) {
            WC()->cart->empty_cart();
        }

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    public static function register(): void
    {
        add_filter('woocommerce_payment_gateways', static function (array $gateways): array {
            $gateways[] = self::class;
            return $gateways;
        });
    }

    public static function renderThankYou(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return;
        }
        if ($order->get_payment_method() !== self::ID) {
            return;
        }
        if ($order->is_paid()) {
            self::renderPaidNotice($order);
            return;
        }

        $memo = (string) $order->get_meta(Memo::ORDER_META_KEY);
        if ($memo === '') {
            $memo = Memo::forOrder($order);
        }

        $settings = SettingsRepository::all();
        $expected = (float) $order->get_total();
        $currency = $order->get_currency();

        // Extension point: Pro returns the remaining amount when partial payments
        // have already arrived. Without Pro, $amount === $expected and there is no
        // partial state.
        $amount  = (float) apply_filters('catcode_iban_smart_invoice/thankyou/amount', $expected, $order, $expected);
        $partial = apply_filters('catcode_iban_smart_invoice/thankyou/partial_summary', null, $order);

        $received  = is_array($partial) ? (float) ($partial['received']  ?? 0.0) : 0.0;
        $isPartial = is_array($partial) ? (bool)  ($partial['is_partial'] ?? false) : false;

        $qrDataUri      = '';
        $nbuPaymentUrl  = '';
        if ($settings['iban'] !== '') {
            $nbuPaymentUrl = QrGenerator::payload(
                (string) $settings['iban'],
                (string) $settings['beneficiary_name'],
                $amount,
                $memo,
                $currency,
                (string) ($settings['beneficiary_edrpou'] ?? '')
            );
            if (($settings['show_qr'] ?? 'no') === 'yes') {
                $qrDataUri = QrGenerator::dataUri($nbuPaymentUrl);
            }
        }

        $monoLink   = Deeplinks::monobank((string) $settings['monobank_send_link'], $amount, $memo);
        $privatLink = Deeplinks::privat24((string) $settings['privat24_link'], $amount, $memo);

        self::enqueueThankYouAssets($order);

        $template = CATCODE_IBAN_SMART_INVOICE_PLUGIN_DIR . 'templates/thankyou-payment.php';
        if (is_readable($template)) {
            include $template;
        }
    }

    /**
     * Already-paid orders that overpaid: show a customer-facing note about
     * the difference so the missing money doesn't look "lost".
     */
    private static function renderPaidNotice(\WC_Order $order): void
    {
        $overpayment = (float) $order->get_meta('_catcode_iban_smart_invoice_overpayment');
        if ($overpayment <= 0.0) {
            return;
        }
        $currency = $order->get_currency();
        ?>
        <section class="isi-pay isi-pay--paid-overpaid" aria-live="polite">
            <div class="isi-pay__partial-notice">
                <strong>
                    <?php esc_html_e('Order paid. Thank you!', 'catcode-iban-smart-invoice'); ?>
                </strong>
                <span>
                    <?php
                    printf(
                        /* translators: %s: overpayment amount */
                        esc_html__('You overpaid by %s — we will contact you to refund the difference or credit it towards your next order.', 'catcode-iban-smart-invoice'),
                        wp_kses_post(wc_price($overpayment, ['currency' => $currency]))
                    );
                    ?>
                </span>
            </div>
        </section>
        <?php
        wp_enqueue_style(
            'catcode-iban-smart-invoice-thankyou',
            CATCODE_IBAN_SMART_INVOICE_PLUGIN_URL . 'assets/css/thankyou.css',
            [],
            CATCODE_IBAN_SMART_INVOICE_VERSION
        );
    }

    private static function enqueueThankYouAssets(\WC_Order $order): void
    {
        wp_enqueue_style(
            'catcode-iban-smart-invoice-thankyou',
            CATCODE_IBAN_SMART_INVOICE_PLUGIN_URL . 'assets/css/thankyou.css',
            [],
            CATCODE_IBAN_SMART_INVOICE_VERSION
        );
        wp_enqueue_script(
            'catcode-iban-smart-invoice-thankyou',
            CATCODE_IBAN_SMART_INVOICE_PLUGIN_URL . 'assets/js/thankyou.js',
            [],
            CATCODE_IBAN_SMART_INVOICE_VERSION,
            true
        );
        $localize = [
            'i18n' => [
                'copied'     => __('Copied', 'catcode-iban-smart-invoice'),
                'copyFailed' => __('Copy failed', 'catcode-iban-smart-invoice'),
                'paid'       => __('Payment received — refreshing the page…', 'catcode-iban-smart-invoice'),
                'partial'    => __('Received {received} ₴ of {expected} ₴. Pay the remaining {remaining} ₴ to complete the order.', 'catcode-iban-smart-invoice'),
            ],
        ];
        $localize = (array) apply_filters('catcode_iban_smart_invoice/thankyou/localize', $localize, $order);
        wp_localize_script('catcode-iban-smart-invoice-thankyou', 'CATCODE_IBAN_SMART_INVOICE_THANKYOU', $localize);
    }

    /**
     * On-hold / invoice email body: paste payment requisites so the customer
     * can finish paying without coming back to Thank You.
     */
    public static function renderEmailInstructions($order, bool $sent_to_admin = false, bool $plain_text = false, $email = null): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }
        if ($order->get_payment_method() !== self::ID) {
            return;
        }
        if ($sent_to_admin || $order->is_paid()) {
            return;
        }

        $settings = SettingsRepository::all();
        $memo = (string) $order->get_meta(Memo::ORDER_META_KEY);
        if ($memo === '') {
            $memo = Memo::forOrder($order);
        }

        $iban        = (string) $settings['iban'];
        $beneficiary = (string) $settings['beneficiary_name'];
        $edrpou      = (string) $settings['beneficiary_edrpou'];
        $total       = $order->get_total();

        if ($plain_text) {
            echo "\n" . esc_html__('Payment details:', 'catcode-iban-smart-invoice') . "\n";
            echo 'IBAN: ' . esc_html($iban) . "\n";
            if ($beneficiary !== '') {
                echo esc_html__('Beneficiary', 'catcode-iban-smart-invoice') . ': ' . esc_html($beneficiary) . "\n";
            }
            if ($edrpou !== '') {
                echo esc_html__('EDRPOU / tax number', 'catcode-iban-smart-invoice') . ': ' . esc_html($edrpou) . "\n";
            }
            echo esc_html__('Amount', 'catcode-iban-smart-invoice') . ': ' . esc_html(wp_strip_all_tags(wc_price($total, ['currency' => $order->get_currency()]))) . "\n";
            echo esc_html__('Payment reference', 'catcode-iban-smart-invoice') . ': ' . esc_html($memo) . "\n\n";
            return;
        }

        ?>
        <h2><?php esc_html_e('Payment details', 'catcode-iban-smart-invoice'); ?></h2>
        <table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;border-collapse:collapse;">
            <tr><th align="left">IBAN</th><td><code><?php echo esc_html($iban); ?></code></td></tr>
            <?php if ($beneficiary !== '') : ?>
                <tr><th align="left"><?php esc_html_e('Beneficiary', 'catcode-iban-smart-invoice'); ?></th><td><?php echo esc_html($beneficiary); ?></td></tr>
            <?php endif; ?>
            <?php if ($edrpou !== '') : ?>
                <tr><th align="left"><?php esc_html_e('EDRPOU / tax number', 'catcode-iban-smart-invoice'); ?></th><td><?php echo esc_html($edrpou); ?></td></tr>
            <?php endif; ?>
            <tr><th align="left"><?php esc_html_e('Amount', 'catcode-iban-smart-invoice'); ?></th><td><?php echo wp_kses_post(wc_price($total, ['currency' => $order->get_currency()])); ?></td></tr>
            <tr><th align="left"><?php esc_html_e('Payment reference', 'catcode-iban-smart-invoice'); ?></th><td><code><?php echo esc_html($memo); ?></code></td></tr>
        </table>
        <p><?php esc_html_e('The shop will confirm your order once the funds arrive.', 'catcode-iban-smart-invoice'); ?></p>
        <?php
    }
}
