=== CatCode Card Payments with LiqPay for WooCommerce ===
Contributors: catcodestudio
Tags: ЛікПей, оплата карткою, еквайринг, Приват24, платіжний модуль
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept card payments in WooCommerce via LiqPay: hosted checkout page, signed webhooks and automatic status reconciliation.

== Description ==

An independent LiqPay payment gateway for WooCommerce. Customers are redirected to the secure LiqPay payment page and the order is confirmed automatically once LiqPay calls back.

LiqPay is a third-party product of PrivatBank. This plugin is an independent integration and is not affiliated with, endorsed by, or sponsored by LiqPay or PrivatBank. You need your own LiqPay merchant account to accept live payments.

Features:

* Card payments (Visa, Mastercard), Apple Pay and Google Pay through the hosted LiqPay page — card data never touches your site.
* Automatic order confirmation via the serviceUrl webhook, verified with an HMAC-MD5 signature.
* Transaction status reconciliation when the customer returns to the shop, so a payment is not lost if the webhook is delayed.
* The paid amount and currency are checked against the order before it is marked as paid.
* Test mode with sandbox keys — place a full test order before signing a contract.
* Supports both the classic and the block-based (Gutenberg) checkout.
* Currencies: UAH, USD, EUR.
* The interface is fully translated into Ukrainian. / Інтерфейс плагіна повністю перекладено українською.

= Опис українською =

Незалежний платіжний модуль LiqPay для WooCommerce. Покупець переходить на захищену сторінку оплати ЛікПей, вводить дані картки там, а замовлення підтверджується автоматично, щойно LiqPay викличе ваш сайт.

* Оплата карткою Visa і Mastercard, а також Apple Pay і Google Pay через хостовану сторінку LiqPay — дані картки не потрапляють на ваш сайт.
* Автоматичне підтвердження замовлення через вебхук serviceUrl з перевіркою підпису HMAC-MD5.
* Звірка статусу транзакції, коли покупець повертається в магазин, щоб оплата не загубилася при затримці вебхука.
* Сума і валюта платежу звіряються із замовленням перед тим, як воно стане оплаченим.
* Тестовий режим із sandbox-ключами — можна провести повне тестове замовлення до підписання договору.
* Працює і з класичним оформленням замовлення, і з блоковим (Gutenberg).
* Валюти: гривня, долар, євро.

LiqPay — сервіс ПриватБанку (Приват24). Цей плагін є незалежною інтеграцією і не афілійований з LiqPay чи ПриватБанком. Для приймання реальних платежів потрібен власний мерчант-акаунт LiqPay.

= Безкоштовна і Pro-версія =

Ця версія плагіна повністю робоча, без обмежень за часом і без реклами в адмінці.
Pro — окремий продукт із розширеними можливостями та підтримкою: https://catcode.com.ua/modules/catcode-card-payments-with-liqpay-for-woocommerce/

= Інші плагіни CatCode для українських магазинів =

* [Нова Пошта для WooCommerce](https://wordpress.org/plugins/catcode-delivery-with-nova-poshta-for-woocommerce/) — вибір відділення й поштомата прямо в кошику, тарифи та ТТН (експрес-накладна) з кабінету НП.
* [пРРО і фіскальний чек](https://wordpress.org/plugins/catcode-prro-connector/) — касовий чек ДПС для кожного замовлення через Вчасно.Касу або Checkbox, з контролем зміни.
* [Сповіщення про замовлення в Телеграм](https://wordpress.org/plugins/catcode-order-notifications-with-telegram-for-woocommerce/) — бот Телеграм пише про нові замовлення, зміну статусу й невдалі оплати.
* [Синхронізація замовлень з KeyCRM](https://wordpress.org/plugins/catcode-order-sync-with-keycrm-for-woocommerce/) — автоматичний експорт замовлень у СРМ одразу після оформлення, з повторами і журналом.
* [IBAN Smart Invoice](https://wordpress.org/plugins/catcode-iban-smart-invoice/) — рахунок на оплату з QR-кодом і реквізитами ФОП, кнопки Монобанк і Приват24, автопошук платежу.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` and activate it.
2. Go to WooCommerce → Settings → Payments → LiqPay.
3. To try it out, leave "Test mode (sandbox)" enabled and paste your `sandbox_` keys. For live payments turn it off and fill in the production public and private keys from your LiqPay account.
4. There is no need to set serviceUrl manually in the LiqPay account — the plugin sends it with every payment.

== External services ==

This plugin connects to the LiqPay payment API (a service of PrivatBank) to process payments. This connection is required for the plugin to work — without it no payment can be created or verified.

What is sent, and when:

* When a customer places an order with this gateway, the plugin sends the order number, the amount, the currency, the order description, your public key and the return/callback URLs of your site to `https://www.liqpay.ua/api/3/checkout`. The customer then enters card details on LiqPay's own page — card data never reaches your site.
* When the customer returns from the payment page, and when LiqPay calls your site back, the plugin requests the current state of that transaction from `https://www.liqpay.ua/api/request` (action `status`) to confirm whether the order is paid.

Every request is signed with your private key, which never leaves your server. No data is sent to LiqPay unless a customer starts a payment or returns from the payment page.

Service provider: LiqPay / PrivatBank.
Terms of service: https://www.liqpay.ua/en/information/user
Privacy policy: https://privatbank.ua/terms

== Frequently Asked Questions ==

= Do I need a LiqPay contract to test the plugin? =

No. Enable test mode and use the `sandbox_` key pair from your LiqPay account (Settings → API → Test), so you can place a full test order before signing anything.

= Why is the order still pending after a successful payment? =

The order is confirmed by the serviceUrl webhook. Make sure your site is reachable from the internet — LiqPay cannot reach `localhost` or a site behind HTTP authentication. As a fallback the plugin also re-checks the status when the customer returns to the shop.

= Which currencies are supported? =

UAH, USD and EUR, as allowed by your LiqPay merchant account.

= Does it work with the block checkout? =

Yes, both the classic shortcode checkout and the block-based checkout are supported.

= Where is the card data processed? =

Entirely on the LiqPay side. The customer is redirected to LiqPay's own payment page, so no card details are entered on or stored by your site.

== Screenshots ==

1. LiqPay gateway settings in WooCommerce (test mode with sandbox keys) and the time of the last LiqPay notification.
2. LiqPay payment method on the checkout page.
3. The hosted LiqPay payment page the customer is redirected to.
4. Order received page after a successful payment.
5. Order notes: the webhook confirms the payment — "Payment via LiqPay" with the transaction, the LiqPay note with the masked card.

== Changelog ==

= 1.0.5 =
* Fixed: when the buyer cancels on the LiqPay page, the order note now says so ("the buyer cancelled the payment, no payment was created") instead of "payment declined. Payment not found", and the buyer sees "The payment was cancelled".
* Fixed: a bank refusal gives its reason in brackets, and the buyer sees "The bank declined the payment".
* Fixed: the WooCommerce payment note reads "Payment via LiqPay (transaction)" instead of repeating the method title; the LiqPay note shows the masked card.
* New: the settings show when the last notification from LiqPay arrived, so a server that blocks LiqPay is noticed at once.

= 1.0.4 =
* Fixed: on sites where every language has a URL prefix (Polylang), the buyer came back from LiqPay to a "Page not found" page instead of the thank-you page.

= 1.0.3 =
* Fixed a double "payment received": the LiqPay webhook and the buyer's return could both complete the same order, leaving two payments and a double stock reduction. They are serialised now, and the loser re-reads the order — including from the HPOS order cache.
* The buyer's return no longer says "payment not completed" on an order the webhook has just paid.

= 1.0.2 =
* Ukrainian translation added: the previous build shipped no translation files at all, although the description promised a translated interface.
* Added the missing Domain Path header, without which a bundled translation is never loaded.
* readme: Ukrainian description and keywords.

= 1.0.1 =
* Removed bundled translation files; translations are now served by translate.wordpress.org.

= 1.0.0 =
* First release.
