=== CatCode Card Payments with WayForPay for WooCommerce ===
Contributors: catcodestudio
Tags: WayForPay, оплата карткою, еквайринг, платіжний модуль, інтернет-магазин
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept card payments in WooCommerce via WayForPay: hosted checkout page, signed webhooks and automatic status reconciliation.

== Description ==

An independent WayForPay payment gateway for WooCommerce. Customers are redirected to the secure WayForPay payment page and the order is confirmed automatically once WayForPay calls back.

WayForPay is a third-party product; this is an independent integration, not affiliated with or endorsed by WayForPay. You need your own WayForPay merchant account to accept live payments.

Features:

* Card payments (Visa, Mastercard), Apple Pay and Google Pay through the hosted WayForPay page — card data never touches your site.
* Automatic order confirmation via the serviceUrl webhook, verified with an HMAC-MD5 signature.
* Transaction status reconciliation when the customer returns to the shop, so a payment is not lost if the webhook is delayed.
* The paid amount and currency are checked against the order before it is marked as paid.
* Test mode using the public WayForPay test merchant — place a full test order before signing a contract.
* The order is sent as an itemised basket (products, shipping, discounts), so the merchant sees what was bought.
* Supports both the classic and the block-based (Gutenberg) checkout.
* Currencies: UAH, USD, EUR.
* The interface is in English and ships with a complete Ukrainian translation.

= Опис українською =

Незалежний платіжний модуль WayForPay для WooCommerce. Оплата карткою відбувається на захищеній сторінці WayForPay, а замовлення в інтернет-магазині підтверджується автоматично за підписаним відповіддю сервісу.

* Оплата карткою Visa і Mastercard через хостовану сторінку — дані картки не потрапляють на ваш сайт.
* Автоматичне підтвердження замовлення через serviceUrl з перевіркою підпису HMAC-MD5.
* Звірка суми і валюти платежу із замовленням перед тим, як воно стане оплаченим.
* Тестовий режим для повного тестового замовлення до підписання договору на еквайринг.
* Працює і з класичним оформленням замовлення, і з блоковим (Gutenberg).

WayForPay — сторонній сервіс. Цей плагін є незалежною інтеграцією і не афілійований з WayForPay. Для приймання реальних платежів потрібен власний мерчант-акаунт.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` and activate it.
2. Go to WooCommerce → Settings → Payments → WayForPay.
3. To try it out, leave "Test mode" enabled — payments then go through the public WayForPay test merchant and no real money is charged. For live payments turn it off and fill in the merchant login and merchant secret key from your WayForPay account.
4. There is no need to set serviceUrl manually in the WayForPay account — the plugin sends it with every payment.

== External services ==

This plugin connects to the WayForPay payment service to process payments. This connection is required for the plugin to work — without it no payment can be created or verified.

What is sent, and when:

* When a customer places an order with this gateway and opens the payment page, the plugin builds a signed form that is submitted to `https://secure.wayforpay.com/pay`. That form carries your merchant login, the order reference, the order date, the amount, the currency, your shop domain, the list of purchased items (name, quantity, price), the return and callback URLs of your site, and — when available — the customer's first name, last name, email address, phone number and country. The customer then enters card details on WayForPay's own page — card data never reaches your site.
* When the customer returns from the payment page, and when the plugin needs to confirm an order, it sends a signed `CHECK_STATUS` request containing your merchant login and the order reference to `https://api.wayforpay.com/api` to find out whether that transaction is paid.
* WayForPay calls your site back on its own (the serviceUrl webhook). The plugin only verifies that callback's signature and answers with a signed acknowledgement — no additional data is sent.

Every request is signed with your merchant secret key, which never leaves your server. No data is sent to WayForPay unless a customer starts a payment or returns from the payment page.

Service provider: WayForPay.
Terms of service: https://wayforpay.com/en/terms
Privacy policy: https://help.wayforpay.com/view/755229227

== Frequently Asked Questions ==

= Do I need a WayForPay contract to test the plugin? =

No. Leave test mode enabled and the plugin uses the public WayForPay test merchant, so you can place a full test order before signing anything.

= Why is the order still pending after a successful payment? =

The order is confirmed by the serviceUrl webhook. Make sure your site is reachable from the internet — WayForPay cannot reach `localhost` or a site behind HTTP authentication. As a fallback the plugin also re-checks the transaction status when the customer returns to the shop.

= Which currencies are supported? =

UAH, USD and EUR, as allowed by your WayForPay merchant account.

= Does it work with the block checkout? =

Yes, both the classic shortcode checkout and the block-based checkout are supported.

= Where is the card data processed? =

Entirely on the WayForPay side. The customer is redirected to WayForPay's own payment page, so no card details are entered on or stored by your site.

= Is the plugin translated? =

The interface is in English and ships with a complete Ukrainian translation.

== Screenshots ==

1. WayForPay gateway settings in WooCommerce.

== Changelog ==

= 1.0.5 =
* Fixed: after a declined card or "Cancel payment" on the WayForPay page the buyer now sees why on the "Pay for order" page ("The bank declined the card payment…" / "The payment was cancelled…"). The notice used to be lost because WayForPay sends the buyer back with a cross-site request that carries no shop session.
* Fixed: a declined payment moves the order to "Failed" even when no webhook arrives — WayForPay's answer about a declined payment is no longer taken for a failed status lookup. An order the buyer cancelled is "Failed" too, not "Cancelled", so it can still be paid.
* Order notes give the decline reason in plain words and appear once, not once from the webhook and again from the buyer's return.
* The item list on the WayForPay page always adds up to the order total; shipping and fees get lines of their own.
* The "Pay for order" page after the return is only opened for the buyer who holds the order key.

= 1.0.4 =
* Fixed: on sites where every language has a URL prefix (Polylang), the buyer came back from WayForPay to a "Page not found" page instead of the thank-you page.

= 1.0.3 =
* Fixed: while the full version from catcode.com.ua is installed, deleting this copy keeps the shared settings, so the full version goes on working with them. Without the full version, deleting still removes everything.
* Changed: while the full version is active, this copy switches itself off instead of loading next to it.

= 1.0.2 =
* Fixed a signature mismatch ("Invalid signature") on orders where a product name contains a semicolon or an HTML-escaped ampersand — product names are now normalised before signing.

= 1.0.1 =
* Fixed a double "payment received": the WayForPay webhook and the buyer's return could both complete the same order, leaving two payments and a double stock reduction. They are serialised now, and the loser re-reads the order — including from the HPOS order cache.
* The buyer's return no longer says "payment not completed" on an order the webhook has just paid.
* Translations are now handled through translate.wordpress.org; bundled language files removed.

= 1.0.0 =
* First release.
