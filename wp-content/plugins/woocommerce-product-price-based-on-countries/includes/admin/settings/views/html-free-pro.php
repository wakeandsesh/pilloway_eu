<?php
/**
 * Free vs Pro view.
 *
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

$features = array(
	array(
		'label'     => __( 'Multiple pricing zones', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Each pricing zone allows you to sell the products at a different price and currency for a group of countries.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'yes', 'yes' ),
	),
	array(
		'label'     => __( 'Country switcher widget', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Display a country switcher in your store using a widget or a shortcode.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'yes', 'yes' ),
	),
	array(
		'label'     => __( 'Automatic updates of exchange rates', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Update daily the exchange rates automatically.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		'label'     => __( 'Custom currency symbol', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Display the prices as USD 99.99, US$ 99.99, ...', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		'label'     => __( 'Currency options per zone', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Custom currency symbol, thousand separator, decimal separator and number of decimals.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		'label'     => __( 'Support for manual orders', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Allow changing the order (or subscription) pricing and currency from the administration panel manually.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		'label'     => __( 'Bulk update from CSV file', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Includes support for the WooCommerce products CSV importer and exporter.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		'label'     => __( 'GeoTarget shortcode', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( "Display custom content based on the user's pricing zone.", 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		'label'     => __( 'Product price shortcode', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Display the price of a specific product on any page using a shortcode.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		// Translators: "Subscriptions" is a product name. Do not translate it.
		'label'     => __( 'Compatible with Subscriptions by WooCommerce', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Includes compatibility with the "WooCommerce Subscriptions" plugin developed by WooCommerce.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		// Translators: "Product Bundles" is a product name. Do not translate it.
		'label'     => __( 'Compatible with Product Bundles by WooCommerce', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Includes compatibility with the "Product Bundles" plugin developed by WooCommerce.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		// Translators: "Bookings" is a product name. Do not translate it.
		'label'     => __( 'Compatible with Bookings by WooCommerce', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Includes compatibility with the "WooCommerce Bookings" plugin developed by WooCommerce.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		// Translators: "Product Add-Ons" is a product name. Do not translate it.
		'label'     => __( 'Compatible with Product Add-Ons by WooCommerce', 'woocommerce-product-price-based-on-countries' ),
		'desc'      => __( 'Includes compatibility with the "Product Add-Ons" plugin developed by WooCommerce.', 'woocommerce-product-price-based-on-countries' ),
		'indicator' => array( 'no-alt', 'yes' ),
	),
	array(
		// Translators: Link.
		'label'     => sprintf( __( '%1$sSee all PRO features and compatibilities%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="external noreferrer noopener" href="' . esc_url( wcpbc_home_url( 'free-vs-pro', 'product-tour/free-vs-pro' ) ) . '">', '<span class="dashicons dashicons-external"></span></a>' ),
		'indicator' => array(),
	),
);

wcpbc_enqueue_js(
	";jQuery(document).ready(function($){
		$('.feature-wrap [data-tip-info]').tipTip( {
			attribute: 'data-tip-info',
			fadeIn: 50,
			fadeOut: 50,
			delay: 200,
			keepAlive: false,
			defaultPosition: 'right',
		} );

		$('#tiptip_holder').css('max-width', '400px');
	});"
);
?>
<div id="wcpbc-free-vs-pro" class="wcpbc-content-wrap">
	<div class="container content">
		<table class="card table">
			<tbody class="table-body">
				<tr class="table-head">
					<th class="large"></th>
					<th class="indicator">Free</th>
					<th class="indicator">Pro</th>
				</tr>
				<?php foreach ( $features as $feature ) : ?>
				<tr class="feature-row">
					<td class="large">
						<div class="feature-wrap">
							<h4><?php echo wp_kses_post( $feature['label'] ); ?></h4>
							<?php if ( ! empty( $feature['desc'] ) ) : ?>
							<span class="dashicon dashicons dashicons-info" data-tip-info="<?php echo esc_attr( $feature['desc'] ); ?>"></span>
							<?php endif; ?>
						</div>
					</td>
					<?php foreach ( $feature['indicator'] as $indicator ) : ?>
					<td class="indicator -<?php echo esc_attr( $indicator ); ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( $indicator ); ?>"></span>
					</td>
					<?php endforeach; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div class="upsell">
			<p>
				<?php esc_html_e( 'Get access to all Pro features and power-up your store', 'woocommerce-product-price-based-on-countries' ); ?>
			</p>
			<a target="_blank" rel="external noreferrer noopener" href="<?php echo esc_url( wcpbc_home_url( 'free-vs-pro' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Get Price Based on Country Pro now', 'woocommerce-product-price-based-on-countries' ); ?>
			</a>
		</div>
	</div>
</div>
