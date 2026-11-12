=== CatCode Smart Filter for WooCommerce ===
Contributors: catcodestudio
Tags: woocommerce, product filter, faceted search, attribute filter, price filter
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A faceted product filter that stays fast on a large catalogue: its own index table, honest counts and no reload on every click.

== Description ==

Most WooCommerce filters ask the database the same expensive question on every click — join the attribute tables, join the meta tables, count. On a catalogue of a few thousand products that question takes seconds, and the shopper leaves before the answer arrives.

This plugin keeps its own flat index of "which product carries which value", rebuilt in the background and kept current by every product save. Filtering reads that one table, so the query cost stops growing with the number of attributes.

= What it does =

* Facets from product attributes, from any product taxonomy (categories, tags, brands) and from a custom field.
* Checkboxes, radio buttons, a select, a range slider or a toggle — per facet.
* Price slider in the shop's display currency, an "in stock / out of stock" facet and a rating facet.
* Honest counts next to every value: a count tells you what you will get, and the value you already picked keeps showing its siblings instead of collapsing to itself.
* Values with no products are hidden — or shown greyed out, if you prefer the shopper to see the whole range.
* Numeric values sort as numbers: `1.5 / 2 / 10 / 12.5`, not the alphabetical `1.5 / 10 / 12.5 / 2` every term-sorted filter shows.
* Applying a filter does not reload the page: the listing and the filter block are swapped in place and the URL goes into the browser history, so Back works and the link can be copied and shared.
* Mobile drawer with a "Show N products" button, so the count is visible before the tap.
* Chips for what is currently applied, with a one-click reset.
* Readable URLs: `?ccf=pa_color:червоний;p:100-2500` — one state has exactly one URL, so nothing turns into a duplicate.
* Crawl control out of the box: a filtered view is `noindex,follow` with a canonical back to the clean archive, and the shop keeps its crawl budget.
* Placement as a widget or through the `[catcode_filter]` shortcode.
* Works with the theme's own product grid — the swapped selector is a setting, `ul.products` by default.
* Merging duplicate values: `12 В`, `12в` and `12 V` become one checkbox while the products keep their own terms.
* As many facets as your catalogue needs — nothing here is capped or time-limited.
* HPOS (custom order tables) compatible.

= Requirements =

* WooCommerce 6.0 or newer
* WordPress 6.2 or newer
* PHP 7.4+

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/catcode-smart-filter/`
2. Activate it in the "Plugins" menu
3. Go to WooCommerce → Smart Filter → Facets and import the attributes you want to filter by
4. Open the "Index" tab and press "Rebuild the index" once
5. Place the block: the "CatCode: Smart Filter" widget, or the `[catcode_filter]` shortcode

== Frequently Asked Questions ==

= The filter block does not appear =

It renders only where a product listing does — the shop, a product category or tag archive, and product search results. Check that a facet is imported and active, and that the index has been built at least once (WooCommerce → Smart Filter → Index).

= Clicking a value reloads the whole page =

The AJAX swap needs to know which element holds the product grid. Most themes use `ul.products`, which is the default; if yours does not, put its selector into "Product grid selector" in the settings.

= The counts do not match the listing =

Press "Rebuild the index". A count and a listing can only disagree when the index is behind the catalogue — after a bulk import that wrote to the database directly, for example.

= Does it work with a page cache? =

Yes. Filtered views carry a query string, so they are served fresh; the clean archive stays cacheable. The counts have their own cache with a lifetime you set.

= Will Google index thousands of filter URLs? =

No. A filtered view is `noindex,follow` with a canonical pointing at the clean archive. Every filtered combination stays out of the index.

== Screenshots ==

1. The filter block on a shop page
2. The mobile drawer with the "Show N products" button
3. Facets in the admin: source, widget, logic and order
4. The index tab

== Changelog ==

= 1.0.3 =
* Fix: the facet screen labelled the column of value names and the value counts with the counting word ("values"), which also read as broken grammar in Ukrainian ("4 значень"). The column is now titled "Values", and counts read as "Values: 7" and "products: 39".

= 1.0.2 =
* Fix: after an AJAX filter every copy of the result count and the pagination is refreshed. Storefront prints them twice, above and below the product grid, and the lower pair kept showing the previous result ("Showing 1–12 of 25" with 2 products left).
* Fix: the pagination comes back when a filter is removed after landing on a single page of results, and an empty result shows WooCommerce's "No products were found" notice instead of a blank area. Before, a block missing on the page that was open had nowhere to be swapped into.
* Fix: a filter applied in the first two seconds after the page opened no longer gets its result count overwritten with the old one by WooCommerce's own script.
* Fix: changing the sort order after an AJAX filter keeps the filter. The sorting form used to keep the query string of the page as it was first opened.

= 1.0.1 =
* Fix: a term added to an attribute (or category, tag, brand) after its facet was imported now appears in the filter by itself. Before, the index only knew the values present at import time, so a new colour stayed invisible even after "Rebuild index".
* "Rebuild index" first picks up terms the facets do not know yet, and saving a product that carries such a term adds it on the spot.
* "Import from catalogue" can be run again for an already imported attribute: it adds only the new values and keeps labels, colours and order set by hand.
* A renamed term keeps its filter value (and URL); the label follows the new name unless it was changed by hand.
* Merged values stay merged: products carrying a swallowed term keep landing on the merged value after they are saved or the index is rebuilt.
* The "Last full build" time updates as soon as a rebuild finishes, without reloading the page.

= 1.0.0 =
* First release.
