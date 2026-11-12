=== Polylang Multilingual Shop for WooCommerce ===
Contributors: catcodestudio
Tags: polylang, multilingual, translation, woocommerce, bilingual
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multilingual WooCommerce on free Polylang: translated products and variations, shared stock, cart and e-mails in the shopper's language.

== Description ==

Polylang translates posts and pages. This plugin teaches it WooCommerce: products and their variations, categories, tags and attributes, the shop / cart / checkout / account pages, the cart, the order and the customer e-mails — so a shopper who reads the store in German buys in German and gets German e-mails, while the warehouse still sees one product with one stock.

**Products**

* Products, categories, tags and global attributes (with their values) become translatable in Polylang.
* One-click translation: Polylang's "+" next to a product creates a full copy in that language — prices, stock, SKU, images, translated categories, tags and attribute values, upsells, and every variation. You only translate the texts.
* Shared data stays in step: price, sale price and dates, stock and stock status, SKU and GTIN, weight and dimensions, shipping and tax class, images, categories, attributes, upsells / cross-sells and more. Edit any language — the others follow. Names, descriptions, purchase notes and custom attribute values stay per language.
* Variations are matched across languages: add or delete one in any language and the translations follow; price, stock and SKU are shared per variation.
* One stock for all languages: an order in any language reduces the stock of every translation.
* Translations may share the same SKU and GTIN (WooCommerce would otherwise refuse the duplicate).

**Store**

* Shop, cart, checkout, my account and terms pages per language — create the missing translations of these pages in one click (a copy with the cart / checkout block or shortcode, not an empty page).
* The translated shop page lists the products of its language; the language switcher on the shop leads to the shop of the other language.
* The cart follows the language switch: every line moves to the translation of its product and variation.
* Classic and block cart / checkout, including the mini-cart: block requests (Store API) carry the page language, WooCommerce's own AJAX requests too.
* Coupons limited to a product or category apply to its translations.
* Attribute names and the e-mail subjects, headings, additional content and footer you typed in WooCommerce are translated in Languages → Translations.

**Orders and e-mails**

* The order keeps the language it was placed in (HPOS and legacy order storage). A Language column in the orders list, and a language selector on the order screen.
* Customer e-mails are sent in the order language — also when the status changes later in the admin, by a payment callback or by a cron. Admin e-mails sent during a shopper's visit use the store's default language.

* Interface in English, Ukrainian, Russian, German, Spanish, Polish and Romanian.

**Pro** (7-day free trial, no card)

* Translate the whole catalogue in one run: all missing categories, tags, attribute terms and products into a language, in batches, safe to restart.
* Choose what each language keeps separately: its own price, stock, images, SKU, shipping data…
* Payment and shipping texts per language: payment method titles, descriptions and instructions (bank details, cash on delivery notes — on the thank-you page and in e-mails), shipping method titles, the store notice and the checkout privacy / terms texts.
* Product CSV export and import with "Language" and "Translation group" columns: products and variations come in already in their languages and linked.

== External services ==

This build checks its Pro licence key on catcode.com.ua. Only when a key is entered or the 7-day trial is requested, and then once a day, it sends the key (or the e-mail for the trial), the site address and the module name to `https://catcode.com.ua/wp-json/catcode/v1/license` and `/trial`. Nothing is sent from a store without a key. Terms: https://catcode.com.ua/terms/ · Privacy: https://catcode.com.ua/privacy/

Polylang is a third-party product by WP SYNTEX, and WooCommerce is a trademark of Automattic. This is an independent integration, not affiliated with or endorsed by either.

== Installation ==

1. Install and activate WooCommerce and Polylang (free, from wordpress.org, version 3.7 or newer).
2. Add your languages in Languages → Languages. "The language is set from the directory name in pretty permalinks" is the recommended URL setting.
3. Install and activate this plugin.
4. Open WooCommerce → Polylang Shop and click "Create the missing page translations" — the shop, cart, checkout and account pages get their copies in every language.
5. In Products, click "+" in a language column to translate a product. Translate categories and attributes in Products → Categories / Attributes the same way, or let the plugin create them as copies when a product is translated.
6. Translate attribute names and e-mail texts in Languages → Translations.

== Frequently Asked Questions ==

= Do I need Polylang Pro? =

No. The free Polylang from wordpress.org is all it needs.

= Can I use it together with "Polylang for WooCommerce" or "Hyyan WooCommerce Polylang Integration"? =

No — they do the same job. When one of them is active this plugin stays paused and says so on the Plugins screen.

= I used Hyyan WooCommerce Polylang Integration before. What happens to my translations? =

They stay: product and term translations are Polylang's own data. Variations of already translated products are matched by their attribute values the first time the product is saved.

= Which data is shared between the languages of a product? =

Everything that is not text: prices and sale dates, stock, SKU / GTIN, weight and dimensions, shipping and tax class, images, categories, tags, attributes, default variation, upsells / cross-sells, grouped products, virtual / downloadable, files, featured, catalogue visibility, menu order, reviews and the external URL.

= Why is a product not in the cart after I switched the language? =

It is — as its translation, if that translation is published. A product that has no published translation in the new language stays in the cart as it was.

= My products existed before I installed Polylang. =

Polylang puts content without a language into the default language from its Languages screen. After that, translate products with "+".

= Does it work with the block checkout? =

Yes — block cart, block checkout and the mini-cart, as well as the classic shortcode pages.

= What happens to my data if I delete the plugin? =

Products, translations and pages always stay — they are Polylang content. The plugin's settings, the links between variations and the language stored in orders are removed only if you tick "Delete data on uninstall".

== Changelog ==

= 1.0.2 =
* Fix: a product translated in one click (or by the bulk tool) kept the categories, tags and attribute values of the default language — Polylang swapped them while the copy had no language yet, and WooCommerce did not write them again. They are now written in the language of the translation.
* New: WooCommerce brands and other product taxonomies outside the product object are carried to translations — translated terms when Polylang translates the taxonomy (created as copies with their logo when missing), the same terms otherwise — and kept in sync when the product is saved. The bulk tool creates their term copies too.
* New: updates of this build come to Dashboard → Updates from catcode.com.ua; the new version is downloaded for a licence that covers updates and is activated on the site.

= 1.0.1 =
* Block checkout: the order keeps the shopper's language even when the Store API request arrives without the language parameter — it is taken from the last shop page the shopper viewed, or from the language of the ordered products. Customer e-mails follow it.

= 1.0.0 =
* First release.
