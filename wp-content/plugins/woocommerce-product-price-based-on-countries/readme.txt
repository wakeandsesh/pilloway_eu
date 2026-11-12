=== Price Based on Country for WooCommerce ===
Contributors: oscargare
Tags:  woocommerce, country-based pricing, price by country, geolocation, multi currency
Requires at least: 5.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.3.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Product Pricing and Currency based on Shopper's Country for WooCommerce with multi-currency support and geolocation to boost international sales.

== Description ==

[Documentation](https://www.pricebasedcountry.com/docs/?utm_medium=banner&utm_campaign=upgrade-pro&utm_source=wordpress) | [Upgrade to Pro](https://www.pricebasedcountry.com/pricing/?utm_medium=banner&utm_campaign=upgrade-pro&utm_source=wordpress)

[Price Based on Country for WooCommerce](https://www.pricebasedcountry.com/?utm_medium=banner&utm_campaign=upgrade-pro&utm_source=wordpress) allows you to define country-specific prices for your WooCommerce products, either in a single currency or across multiple currencies.

The plugin is designed for stores that sell internationally and need greater control over how prices are presented to customers across regions. You can adapt your pricing strategy to reflect local markets and currencies.

== KEY FEATURES ==

The core plugin is 100% free. It provides all functionality needed to sell internationally with localized pricing.

✔️ **Multi-Currency**

Add multi-currency support to WooCommerce to enable selling products in multiple currencies and reduce checkout friction.

✔️ **Geolocation**

The plugin detects the country of the website visitor automatically through the geolocation feature included in WooCommerce and displays the price and currency you have defined previously for this country. Compatible with cache plugins.

✔️  **Manual Fixed Price**

Leave the price to be calculated using the exchange rate, or set a fixed price manually for each country or region.

✔️  **Country Switcher Widget**

Add a country switcher to your store to let users manually change their country.

✔️  **Multilingual Ready**

Compatible with WPML and Polylang workflows (sync data between product translations).

== How It Works ==

Prices are organized into pricing zones, each one associated with one or more countries. For each zone, you can choose a currency.

You may rely on automatic currency conversion using exchange rates, or define fixed prices manually for full control.

Once configured, prices are displayed dynamically based on the customer's country, while remaining compatible with caching and performance plugins.

== Use Cases ==

* **Boost international sales** – Display price in local currency to reduce checkout friction and cart abandonment.
* **Regional strategy** – Adjust prices based on regional purchasing power.
* **Absorb shipping costs** – Set higher prices for countries with expensive shipping without scaring away local customers.
* **Keeping profit** – Maintain consistent margins across international markets.

== Price Based on Country PRO ==

The [PRO version](https://www.pricebasedcountry.com/pricing/?utm_medium=banner&utm_campaign=upgrade-pro&utm_source=wordpress) extends the core functionality with features that save time, automate tasks, and provide integration with professional plugins.

* **Automatic Exchange Rate Updates** – The daily automatic update of the exchange rate allows prices to stay in sync without manual intervention.
* **Pretty prices** – Converted prices can be rounded to clean values, helping maintain a professional appearance across currencies (e.g., 19.99 instead of 19.43).
* **Custom Currency Symbol** – Display the prices as USD 99.99, US$ 99.99, ...
* **Bulk Updates from file** – Save hours of manual work by updating the product prices in bulk using the CSV import or "WP All Import" integration.
* **Support for Manual Orders** – Update the order with the regional price from the administration panel.
* **Additional Shortcodes and Widgets** – Display custom content based on the user's country or the price of a specific product in a pricing table with the PRO shortcodes.
* **Exclusive Compatibilities and Integrations** – The PRO version adds compatibility with several WooCommerce.com extensions, including *WooCommerce Subscriptions by Woo*, *Product Bundles for WooCommerce by Woo*, *Product Add-Ons for WooCommerce by Woo*, and other professional extensions like *German Market by Marketpress* or *WP All Import*.

[**Free vs PRO full comparison**](https://www.pricebasedcountry.com/product-tour/free-vs-pro/?utm_medium=banner&utm_campaign=upgrade-pro&utm_source=wordpress)

== Installation ==

= Minimum Requirements =

* PHP version 7.0 or greater.
* MySQL version 5.6 or greater or MariaDB version 10.0 or greater.
* WooCommerce 4.0+
* If you want to receive payments in more than one currency, a payment gateway that supports them.

= Manual Installation =

1. Download the plugin by clicking on the "Download" button on this page.
1. In your WordPress admin, go to Plugins > Add New.
1. Click "Upload Plugin" > "Browse" and select the zip file that you downloaded
1. Click "Install Now" > Activate.

= Automatic installation =

Using the WordPress Admin is the most straightforward option. Log in to your WordPress dashboard, navigate to the Plugins menu, and click "Add New."

In the search field, type "Price Based on Country for WooCommerce," then click "Search Plugins." Once you've found us, you can install it by clicking "Install Now," and WordPress will take it from there.

== Frequently Asked Questions ==

= Does the plugin automatically detect the country? =
Yes. WooCommerce includes a native geolocation feature that detects the customer's location via IP. Price Based on Country uses this feature to automatically show the correct local price.

= Is it compatible with caching plugins (WP Rocket, W3 Total Cache)? =
Yes! The plugin is designed to work with caching. It loads prices dynamically to ensure the correct price is shown.

= Can I set the price manually for each product? =
Absolutely. You can let the plugin automatically calculate the exchange rate, or manually enter a fixed price for each product in each zone (e.g., Product A is $10 in the US and €12 in Spain).

= What happens if I need to update 500 products? =
The Free version requires manual editing. The [PRO Version](https://www.pricebasedcountry.com/pricing/?utm_medium=banner&utm_campaign=upgrade-pro&utm_source=wordpress) includes CSV import/export support, saving you hours of data entry.

= Does it work with Stripe and PayPal? =
Yes! The plugin requires a payment gateway that supports the currencies you want to work with. e.g., PayPal or Stripe.

= Can I use the plugin to set different prices based on State, zip code, province, or city? =
This plugin is specialized strictly for **Country-based** pricing strategies. It does not support regional (State/Zip) pricing within a single country.

= Where can I find the plugin documentation? =
Need help setting up? Check out our [documentation](https://www.pricebasedcountry.com/docs/?utm_medium=banner&utm_campaign=upgrade-pro&utm_source=wordpress). Have a bug to report? Please use the Support Forum.

= How can I test the geolocation? =
Please review [How to test the pricing zones](https://www.pricebasedcountry.com/docs/getting-started/testing/?utm_medium=banner&utm_campaign=upgrade-pro&utm_source=wordpress) in our docs to learn how to simulate visits from other countries.

== Screenshots ==

1. Simple to get started with the Geolocation setup wizard.
2. Unlimited price zones.
3. Pricing zone properties.
4. Pricing zone properties (2).
5. Plugin settings.
6. Set the price manually or calculate it by the exchange rate.
7. Includes a country selector widget.

== Changelog ==

= 4.3.6 (2026-10-14) =
* Added: Compatible with WooCommerce 11.2+.
* Fixed: coupon_invalid_minimum_amount error during currency conversion of coupon spend limits.

= 4.3.5 (2026-09-30) =
* Fixed: Reorder pricing zones.

= 4.3.4 (2026-09-08) =
* Added: Compatible with WooCommerce 11.1+.
* Added: Tested up WordPress 7.1+.
* Fixed: Error in the "Google for WooCommerce" product sync. Thanks @johnespindola

= 4.3.3 (2026-07-14) =
* Added: Compatible with WooCommerce 10.9+.
* Fix: The p, page_id, and product query vars are removed after changing the country using the switcher.

= 4.3.2 (2026-05-14) =
* Tweak: Improve the performance of the recurring actions schedule.

= 4.3.1 (2026-05-07) =
* Fixed: PHP Error when specific integrations are loaded.

= 4.3.0 (2026-05-07) =
* Added: Tested up WordPress 7.0+.
* Added: Compatible with WooCommerce 10.7+.
* Added: Compatibility with the "course" product type of the LearnDash plugin.
* Added: Compatibility with the LearnDash Group Registration plugin.
* Fixed: Double conversion issue with "Advanced Dynamic Pricing and Discount Rules for WooCommerce" by AlgolPlus.
* Fixed: Conflict with "Variation Swatches for WooCommerce - Pro" plugin.
* Fixed: Error on activating "Jetpack Boost" plugin when the "Jetpack 15.8-a.3 (Pressable managed hosting)" plugin is active.
* Fixed: Update of EUR countries in the "Select Eurozone" button tool.
* Fixed: The "scheduled sales" query causes a timeout error when run against large `postmeta` tables.
* Tweak: Improve the performance of the database queries.
* Tweak: Handle scheduled sales in a dedicated Action Scheduler hook.
* Tweak: Send no cache headers when `wcpbc-manual-country` parameter is set.

[See changelog for all versions](https://plugins.svn.wordpress.org/woocommerce-product-price-based-on-countries/trunk/changelog.txt).

== Upgrade Notice ==

= 4.2 =
<strong>4.2 is a major update</strong>. We recommend that you backup your website before updating it.