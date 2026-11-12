=== Nova Post for WooCommerce ===
Contributors: Nova Post
Tags: woocommerce, shipping, nova post, parcel tracking, shipping rates
Stable tag: 2.5.0
Requires Plugins: woocommerce
Requires PHP: 8.0
Requires at least: 6.6
Tested up to: 7.1
WC requires at least: 9.8
WC tested up to: 10.4
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Official Nova Post shipping plugin for WooCommerce. Create shipments, calculate rates, print labels and track deliveries across Europe and Ukraine.

== Description ==

Nova Post for WooCommerce is an official shipping integration plugin that allows WooCommerce store owners to offer Nova Post (Nova Poshta) delivery services directly at checkout.

The plugin enables automatic shipment creation, label and document generation, live shipping rate calculation, pickup branch selection, and real-time parcel tracking. It helps merchants simplify cross-border logistics, reduce manual errors, and deliver orders across Europe and Ukraine.

Powered by Nova Post.

== Features ==

1. Create Nova Post Shipments with automated label and document generation.
2. Let customers select pickup branch at checkout page.
3. Get live shipping rates at checkout.
4. Track shipments - tracking numbers auto-synced to orders.

== Installation & Configuration ==

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce > Settings > Shipping > Nova Post to configure your plugin settings.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
Yes, this plugin is a WooCommerce add-on and requires WooCommerce to be installed and active.

= How do I get a Nova Post API key? =
You can obtain an API key from your Nova Post account. Please contact Nova Post support for more information.

= Does the plugin support shipping across Europe and Ukraine? =
Yes, the plugin supports Nova Post deliveries across multiple European countries and Ukraine.

== Screenshots ==

1. Customers select Nova Post pickup branch, parcel locker, or address delivery at checkout
2. Live shipping rate calculation on the checkout page
3. Shipment creation and delivery tracking on the WooCommerce order page
4. Printing Nova Post shipping labels and invoices

== Changelog ==
= 2.5.0 =
* Added: Support for International shipping for sellers located in Ukraine.
* Improved: Replaced the checkout branch selector with the official Nova Post Widget.
* Fixed: Filtered sender and recipient branch searches.
* Improved: Currency conversion of declared item value for destination countries.

= 2.4.1 =
* Fixed: Synchronization issue on classic checkout.

= 2.4.0 =
* Improved: Delegated currency conversions (customs & insurance) directly to Nova Post using the store's base currency.
* Fixed: Restored USD currency support in the admin panel.
* Fixed: Resolved layout and plugin conflicts on the orders page.
* Fixed: Calculation error where combined COD and shipping fees exceeded declared value.

= 2.3.0 =
* Added: Automatic shipment status updates via Nova Post tracking webhook
* Improved: Settings form fields now show inline validation error
* Improved: Clearer shipment status labels, grouped with status icons and colors
* Improved: Bulk label/invoice printing now handles up to 200 orders and returns a single downloadable PDF
* Improved: More reliable branch/parcel-machine selection on classic checkout (division resets when the address changes)
* Fixed: Pickup — human-readable error messages
* Fixed: Default product dimensions and box-packing calculation issues
* Fixed: Translation and packaging settings UI fixes

= 2.2.1 =
* Fixed: Shipment invoice currency now matches sender's country instead of recipient's

= 2.2.0 =
* Map-based branch selection on checkout page
* Advanced dynamic package calculation system

= 2.1.0 =
* Improved validation logistics
* Updated translations
* UI enhancements

= 2.0.0 =
* Added bulk actions (create shipments, print labels, print invoices)
* Order pickup from address
* Support for CZ, DE locales
* Bugfixes and improvements

= 1.0.0 =
* Initial release
