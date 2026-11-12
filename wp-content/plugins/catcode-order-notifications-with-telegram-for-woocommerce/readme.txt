=== CatCode Order Notifications with Telegram for WooCommerce ===
Contributors: catcodestudio
Tags: Телеграм, бот Телеграм, сповіщення про замовлення, інтернет-магазин, telegram
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Instant Telegram notifications for WooCommerce orders and site form leads: new orders, status changes, failed payments, low stock, Contact Form 7.

== Description ==

Get an instant Telegram message the moment something happens on your site. Connect a bot, pick the events you care about, and receive clean, formatted notifications in any chat, group or forum topic.

WooCommerce is optional. With WooCommerce active you get the full set of order events; without it the plugin runs in forms-only mode and delivers Contact Form 7 leads to Telegram, with its settings screen under Settings → Telegram Notifications.

**Events you can enable**

* **New order** — announced once, the first time an order reaches one of the statuses you select (no duplicate messages).
* **Status change** — notify on your own list of order statuses.
* **Failed payment** — a separate alert for orders that land in the "Failed" status.
* **Low stock** — fires when a product reaches the low-stock threshold from WooCommerce → Settings → Products → Inventory, at most once per product per day.
* **Site form leads** — Contact Form 7 submissions out of the box, plus any plugin firing the `catcode_lead_captured` action.

**Flexible delivery**

* Send to **multiple chats** at once — a private chat, a group, or a channel (`@username`).
* Deliver into a **forum topic** by appending the topic id, e.g. `-1001234567890:42`.
* **Silent mode** — deliver messages without a notification sound.

**Custom message templates**

Build your own message with tags: order number, total, items list, item count, customer name, phone, email and note, billing and shipping address, payment and shipping method, order date and status, and an admin link to the order. A line whose tags all resolve to empty is removed automatically, so messages stay tidy even when a field is missing. Telegram markup (`<b>`, `<i>`, `<u>`, `<s>`, `<code>`, `<a href>`) is supported.

**Reliable and secure**

* **Delivery log** — every send is recorded with the chat, HTTP status and result.
* **Automatic retry** — a failed send is retried once after a short delay.
* The **bot token is stored encrypted**.

Telegram and WooCommerce are third-party products. This plugin is an independent integration and is not affiliated with, endorsed by, or sponsored by Telegram or WooCommerce.

= Опис українською =

Сповіщення про замовлення WooCommerce у Телеграм. Плагін надсилає повідомлення у ваш чат, групу або канал одразу, щойно в інтернет-магазині з'являється нове замовлення або змінюється його статус.

* Свій бот Телеграм — токен береться у @BotFather, жодних посередників і комісій.
* Надсилання в особистий чат, у групу, у канал або в окрему тему форуму.
* Власний шаблон повідомлення: номер замовлення, сума, товари, покупець, телефон, спосіб доставки й оплати.
* Вибір статусів замовлення, які викликають сповіщення.
* Кнопка тестового повідомлення, щоб перевірити налаштування без реального замовлення.
* Заявки з форм сайту (Contact Form 7) — окремим повідомленням із власним шаблоном.

Telegram — сторонній сервіс. Цей плагін є незалежною інтеграцією і не афілійований з Telegram.

= Безкоштовна і Pro-версія =

Ця версія плагіна повністю робоча, без обмежень за часом і без реклами в адмінці.
Pro — окремий продукт із розширеними можливостями та підтримкою: https://catcode.com.ua/modules/telegram-order-notifications-for-woocommerce/

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it from your WordPress admin under Plugins → Add New.
2. Activate the plugin. WooCommerce is optional — with it you get the order events, without it the plugin sends site form leads only.
3. In Telegram, create a bot via [@BotFather](https://t.me/BotFather) with `/newbot` and copy the token it gives you.
4. Go to WooCommerce → Telegram Notifications (or Settings → Telegram Notifications when WooCommerce is not active) and paste the bot token.
5. Add one or more chat ids (one per line). Use the "Find chat_id" button after writing any message to your bot, or after adding it to a group.
6. Choose the events to notify about, adjust the templates if needed, and save.
7. Click "Send a test message" to confirm everything works.

== Frequently Asked Questions ==

= How do I get a bot token? =

Open [@BotFather](https://t.me/BotFather) in Telegram, send `/newbot`, follow the prompts, and copy the token. Paste it on the plugin settings page — it is stored encrypted.

= How do I find my chat id? =

Write any message to your bot (or add the bot to your group), then open the plugin settings and click "Find chat_id". The plugin reads pending updates and shows the chat ids it sees. Note: if your bot uses a webhook, getUpdates will not return anything.

= Can I send notifications to a group topic (forum)? =

Yes. Add the chat id followed by the topic id, separated by a colon — for example `-1001234567890:42`.

= Which order fields can I use in a message? =

Order number and id, total, current and previous status, order date, items list and item count, customer name, phone, email and note, billing and shipping address, payment and shipping method, shipping total, an admin link to the order, and the store name. Low-stock messages also expose the product name, SKU and remaining quantity.

== External services ==

This plugin connects to the Telegram Bot API (`https://api.telegram.org`) to deliver your notifications. This is required for the plugin to work — without it, no messages can be sent.

**What data is sent and when:** a request is made only when an enabled event occurs (a new order, an order status change, a failed payment, or a low-stock product), and only to the chats you configure. Each request includes your bot token, the target chat id (and optional topic id), and the message text built from your template. Depending on the template you choose, that text may include the order number and total, the list of purchased items, and the customer's name, phone, email and address.

Telegram is a third-party service. By using this plugin you agree to Telegram's terms.

* Terms of Service: https://telegram.org/tos
* Privacy Policy: https://telegram.org/privacy

== Screenshots ==

1. Connection: bot token and the chats that receive notifications.
2. Events: which order statuses and store events trigger a message.
3. Message templates with the available placeholders.

== Changelog ==

= 1.3.1 =
* Fixed the link to the module page in the admin notice.
* The notice can now be dismissed for good: closing it no longer brings it back on the next page load.

= 1.3.0 =
* Contact Form 7 leads: each field in the Telegram message is now labelled with the caption written next to it in the form, then its placeholder, and only then the raw field name (`your-name` no longer shows up as a label).
* Select and radio fields no longer print their first option as the field label.
* A caption that carries a live value after a colon (for example "Area: 500 m2") keeps only the caption itself.

= 1.2.0 =
* WooCommerce is now optional — on a site without a shop the plugin runs in forms-only mode and sends Contact Form 7 leads to Telegram.
* Without WooCommerce the settings screen lives under Settings → Telegram Notifications, and the order events and their templates are hidden instead of sitting there dead.
* Saving, the test message and "Find chat_id" no longer fail with an "Insufficient permissions" error on a site without WooCommerce.
* Order settings stored earlier are preserved when saving on a shop-less site, so nothing is lost if WooCommerce is added later.

= 1.1.2 =
* A "Settings" link in the plugins list — after activation the only trace of the plugin was a submenu buried in the WooCommerce menu, so it looked like nothing had installed.
* The default message templates are translatable and are no longer written into the database on activation: a Ukrainian store used to get English Telegram messages out of the box.

= 1.1.1 =
* Ukrainian admin was partly in English: WordPress serves only the language pack from translate.wordpress.org, and that pack is missing the strings added in 1.1.0 (site form leads, review notice). The bundled Ukrainian catalogue is now loaded as a fallback under the language pack.

= 1.1.0 =
* New event: leads from site forms — Contact Form 7 out of the box, and any plugin firing the `catcode_lead_captured` action, with its own message template.
* Low-stock warnings are limited to one per product per day; previously every order repeated the same warning.
* The order link in the delivery log now works both with and without HPOS.
* One unobtrusive admin notice, shown only after the first message is actually delivered and never again once dismissed.
* Running a second copy of the same plugin no longer ends in a fatal error — the second copy simply stands down.

= 1.0.3 =
* Ukrainian translation bundled — it was missing from the published build.
* readme: Ukrainian description and keywords.

= 1.0.2 =
* Unified all global identifiers under a single distinct prefix to avoid conflicts.
* Removed bundled translation files; translations are handled via WordPress.org.

= 1.0.0 =
* First public release.
