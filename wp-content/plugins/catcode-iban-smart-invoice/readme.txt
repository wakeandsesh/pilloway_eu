=== CatCode IBAN Smart Invoice ===
Contributors: catcodestudio
Tags: рахунок на оплату, реквізити, рахунок-фактура, ФОП, Монобанк
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 0.2.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show a payment QR code and Monobank or Privat24 buttons on the WooCommerce Thank You page, then auto-detect incoming payments via Monobank API.

== Description ==

Instead of typing IBAN by hand, the buyer scans a QR code in their banking app — the IBAN, amount and payment reference are filled automatically. The store owner sees the transfer in their bank app and confirms the order in WooCommerce → Orders.

= Key features =

* NBU v002 QR code recognised by Monobank, Privat24 and other Ukrainian banking apps
* "Open in Monobank" and "Open in Privat24" deep-link buttons
* Copy buttons for IBAN, amount and payment reference
* On-hold / invoice emails include the same requisites so customers can finish paying without coming back to Thank You
* HPOS-compatible (custom order tables)
* WooCommerce Blocks Checkout support
* Full Ukrainian translation included

= How it works =

1. The buyer picks the "Pay to IBAN / card" method on checkout.
2. On the Thank You page they see a QR code, deep-link buttons and the payment requisites.
3. They scan the QR in their banking app — the transfer is pre-filled automatically.
4. They confirm the transfer in the bank.
5. When you see the credit in your bank app, you mark the order as Processing in WooCommerce → Orders.

Step 5 can be automated with the separate "IBAN Smart Invoice — Pro" add-on (Monobank Personal API webhook + automatic matching by reference and amount). The Pro add-on is hosted by the author and is not part of this plugin.

= Requirements =

* WooCommerce 10.0+
* PHP 8.0+
* A valid IBAN (UA format)

= Ukrainian =

The plugin interface is fully translated into Ukrainian. / Інтерфейс плагіна повністю перекладено українською.

= Free and Pro =

This version is fully functional — no time limit and no ads in the admin.
Pro is a separate product with extra features and support: https://catcode.com.ua/modules/iban-smart-invoice/

= Other CatCode plugins for Ukrainian stores =

* [Nova Poshta for WooCommerce](https://wordpress.org/plugins/catcode-delivery-with-nova-poshta-for-woocommerce/) — branch and parcel-locker picker in the cart, rates and waybills from the Nova Poshta account.
* [PRRO fiscal receipts](https://wordpress.org/plugins/catcode-prro-connector/) — a tax-service receipt for every order through Vchasno.Kasa or Checkbox, with shift control.
* [Card payments with LiqPay](https://wordpress.org/plugins/catcode-card-payments-with-liqpay-for-woocommerce/) — LiqPay and Privat24 acquiring with Apple Pay, Google Pay and status reconciliation.
* [Order notifications in Telegram](https://wordpress.org/plugins/catcode-order-notifications-with-telegram-for-woocommerce/) — a Telegram bot reports new orders, status changes and failed payments.
* [Order sync with KeyCRM](https://wordpress.org/plugins/catcode-order-sync-with-keycrm-for-woocommerce/) — automatic export of orders to the CRM right after checkout, with retries and a log.

== Installation ==

1. Upload the plugin archive to `wp-content/plugins/`.
2. Activate it from the Plugins screen.
3. Configure under WooCommerce → Settings → Payments → IBAN Smart Invoice.
4. Fill in IBAN, beneficiary name, and the reference template.

== Frequently Asked Questions ==

= Does the customer need the Monobank app to pay? =

No. The QR code on the Thank You page is the official NBU v002 format and is recognised by Monobank, Privat24, PUMB, Sense, Raiffeisen, Oschad and 20+ other Ukrainian banking apps. The "Open in Monobank" / "Open in Privat24" buttons are convenience shortcuts for users of those two apps.

= Do I need a Monobank account on the receiving side? =

No. Any Ukrainian IBAN works. The Monobank "send to jar" link is only used to power the optional "Open in Monobank" button — if you don't have a jar, leave the field empty and the button is hidden.

= How do I confirm that an order has been paid? =

Open WooCommerce → Orders, find the order in "On hold", check that the credit landed on your bank account, and switch the status to "Processing" or "Completed". The order's reference (e.g. "Order #42") is the same string the customer typed in their bank, so they're easy to match.

= Can payment confirmation be automated? =

Yes, via the separate "IBAN Smart Invoice — Pro" add-on. It consumes the Monobank Personal API webhook, matches incoming credits against pending orders by reference and amount, and flips the order to Processing automatically. The add-on is sold by the plugin author and is not part of this .org submission.

= What format is the QR code? =

NBU v002 (Resolution 97, 2025-08-19), the standard recognised by NBU as the universal Ukrainian payment QR. The payload uses Universal Link `https://bank.gov.ua/qr/...` so a single QR opens whichever banking app the customer has installed.

== Screenshots ==

1. Gateway settings: recipient details, QR and bank buttons.
2. Thank You page: payment QR code, IBAN, amount and reference with copy buttons.
3. The payment method as the customer sees it at checkout.

== Changelog ==

= 0.2.7 =
* Fixed: activating this copy while the full version is active no longer fails with a fatal error.
* Fixed: while the full version from catcode.com.ua is installed, deleting this copy keeps the shared settings, so the full version goes on working with them. Without the full version, deleting still removes everything.
* Changed: while the full version is active, this copy switches itself off instead of loading next to it.

= 0.2.6 =
* Fixed: the payment method did not appear in the block (Cart & Checkout blocks) checkout until the gateway settings had been saved at least once, while the classic checkout already showed it.
* Fixed: after the plugin was deleted and installed again, the settings form still showed the saved IBAN, but the payment method was hidden at checkout until the settings were saved again. The saved details are now picked up automatically.
* Tested with WordPress 7.1 and WooCommerce 11.1.

= 0.2.5 =
* The "no IBAN set" warning never appeared on a fresh install — the check read WooCommerce's gateway option, which does not exist until the settings are saved once, and treated its absence as "gateway disabled". A new install therefore showed nothing at all: no notice, and no payment method at checkout, because the method hides itself until an IBAN is entered.
* Ukrainian settings screen now addresses the shop owner formally.

= 0.2.4 =
* readme: Ukrainian description and keywords.

= 0.2.3 =
* Internationalisation fix: every user-facing string is now written in English in the source, as required for a WordPress.org plugin. Ukrainian is shipped as a proper translation (bundled uk .po/.mo, and available on translate.wordpress.org), so the plugin now reads correctly in English and in Ukrainian instead of being Ukrainian-only.
* The default payment reference ("Payment for order #{order_id}") and the EDRPOU / tax number labels in the order emails are translatable too — previously they were hardcoded Ukrainian and ignored the site language.

= 0.2.2 =
* Complete Ukrainian translation bundled (114 strings, uk locale .po/.mo in /languages). No code changes.

= 0.2.1 =
* Renamed the plugin to match its assigned directory slug `catcode-iban-smart-invoice`: updated the display name to "CatCode IBAN Smart Invoice", the text domain to `catcode-iban-smart-invoice`, the main plugin file and the bundled language files, and moved every constant/option/hook/meta prefix to `catcode_iban_smart_invoice_` / `CATCODE_IBAN_SMART_INVOICE_`. Classes keep the `CatCode\IbanSmartInvoice` namespace.

= 0.2.0 =
* Plugin review compliance: consolidated all procedural identifiers onto the plugin's own `iban_smart_invoice_` prefix. The `isipay_` prefix used for option keys, action/filter hook names, the order meta key, the WooCommerce Blocks script handle and the Thank You JS global has been renamed. This is a private-API change for theme/extension authors — recompile after upgrade. Classes keep the `CatCode\IbanSmartInvoice` namespace; public BEM CSS class names (`.isi-pay__*`) are unchanged.
* Stopped writing back to WooCommerce's own `woocommerce_{id}_settings` option when an invalid IBAN is entered — the last known-good IBAN is now kept only in the plugin's own settings, leaving WooCommerce core's option store untouched.

= 0.1.9 =
* Removed an internal development note (`_dev/`) that was accidentally included in the distributed package. No code changes.

= 0.1.8 =
* Fixed an invalid Privacy Policy URL in the External services section (PrivatBank link updated to a working page).
* Corrected the Contributors username to `catcodestudio`.
* No functional code changes since 0.1.6.

= 0.1.6 =
* Compliance pass for the WordPress.org plugin review queue:
  * Renamed the three-letter `isi_` prefix to the four-plus-letter `isipay_` across every option key, action / filter hook, AJAX handler, asset handle and JS global. This is a private-API change for theme / extension authors — recompile after upgrade. Public BEM CSS class names (`.isi-pay__*`) are unchanged.
  * Pinned `chillerlan/php-qrcode` to the latest 6.x release in `composer.lock` (the 0.1.4 build still shipped 5.x in `vendor/` despite the changelog claim).
  * Added an explicit `== External services ==` section to the readme documenting that the free plugin makes no outbound HTTP calls, plus the NBU / Monobank / Privat24 deep-link payload destinations.
  * Extended `.distignore` so vendor `LICENSE-ASL-2.0`, `NOTICE`, `CHANGELOG.md` and other auxiliary files no longer ship inside the production ZIP.

= 0.1.4 =
* Free / Pro split: automatic payment detection (Monobank Personal API webhook + Order Matcher + admin IBAN Payments journal + Thank-You polling) has been moved to a separate "IBAN Smart Invoice — Pro" add-on hosted off the .org repo. The free version stays fully functional — QR + IBAN + deep-link buttons + email instructions + manual confirmation in WooCommerce → Orders.
* Upgraded `chillerlan/php-qrcode` to ^6.0 (was ^5.0). Output API switched to the new `outputInterface` style (`QRGdImagePNG::class`); no visible change for store owners.
* Build hygiene: `.distignore` extended to drop vendor LICENSE-ASL/NOTICE duplicates, CHANGELOG, examples, tests and other artefacts so the published archive contains plugin code only.

= 0.1.3 =
* Webhook now responds to Monobank within the 5-second SLA by queuing the matcher as an asynchronous job (Action Scheduler when available, WP-Cron fallback). Order detection and CRUD no longer run inside the HTTP request from Mono, so a slow database or third-party plugin can no longer make Mono disable the webhook.
* Added a `queued` status for incoming payments to make the async pipeline visible in the IBAN Payments admin journal.

= 0.1.2 =
* Audit hardening pass against WC 10.7 + Plugin Check 2026 standards: documented webhook auth model (`X-ISI-Token` header alongside `?token=`), explicit `permission_callback` rationale on REST routes, `wc-payment-method-isi` Blocks handle naming aligned with core convention, version constant synced with plugin header.

= 0.1.1 =
* Switch QR payload to official NBU v002 standard (Resolution 97, 2025-08-19). Scans now work across Monobank, Privat24, PUMB, Sense, Raiffeisen, Oschad and 20+ other Ukrainian banks via Universal Link `https://bank.gov.ua/qr/`.
* Unified plugin settings into a single WooCommerce gateway editor (no more separate settings page).
* WooCommerce Cart/Checkout Blocks support (`AbstractPaymentMethodType`).
* Partial payments: customer-facing notice + dynamic QR with remaining amount; matcher accumulates multiple partial transfers into one order.
* Overpayments: customer-facing thank-you note + admin flag for manual refund.
* Ukrainian admin journal status labels with colour coding.
* `Requires Plugins: woocommerce` header.
* Reduced polling interval to 10 seconds.

= 0.1.0 =
* First release: Gateway, Settings, Thank You UX, Webhook, Order Matcher, Admin journal.

== Upgrade Notice ==

= 0.2.4 =
Ukrainian interface and description.

= 0.2.1 =
Text domain and all internal prefixes renamed to `catcode_iban_smart_invoice_` to match the plugin's directory slug. Update any custom theme/extension that hooks into our filters. CSS classes are unchanged.

= 0.2.0 =
Internal hook/option prefix renamed from `isipay_` to `iban_smart_invoice_`. If you wrote a custom theme or extension that hooks into our filters, update the names. CSS classes are unchanged.

= 0.1.9 =
Packaging cleanup only. Non-breaking.

= 0.1.8 =
Readme metadata fixes (valid policy URL, contributor username). Non-breaking.

= 0.1.6 =
WordPress.org compliance pass: option / hook prefix changed from `isi_` to `isipay_`. If you wrote a custom theme or extension that hooks into our filters, update the names. CSS classes are unchanged.

= 0.1.4 =
Auto-detection moved into a separate Pro add-on. The free version keeps QR + deep-links + manual reconciliation and is now fully .org-compliant. Recommended upgrade.

= 0.1.3 =
Webhook responds inside Mono's 5-second SLA. Recommended for live stores.

= 0.1.2 =
Audit hardening pass — non-breaking. Recommended.

= 0.1.1 =
NBU v002 QR + Blocks checkout + partial payment accumulation. Recommended upgrade.

= 0.1.0 =
First public release.
