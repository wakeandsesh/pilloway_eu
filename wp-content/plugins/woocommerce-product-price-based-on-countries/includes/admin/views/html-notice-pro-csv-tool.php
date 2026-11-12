<?php
/**
 * Admin View: Notice - Upgrade to Pro to import/export tool support
 *
 * @package WCPBC/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="notice notice-info notice-pbc pbc-is-dismissible pbc-request-review">
	<p class="rate-description">
	<?php
		// translators: 1: Import|Export text; 2,3: HTML tags.
		printf( esc_html( __( 'Hi! Do you need to %1$s the Pricing Zone fields? %2$sUpgrade to Price Based on Country Pro%3$s and get support to the WooCommerce import and export tool.', 'woocommerce-product-price-based-on-countries' ) ), esc_html( $import_export ), '<a href="' . esc_url( wcpbc_home_url( 'import-export-tool' ) ) . '" target="_blank" rel="noopener noreferrer">', '</a>' );
	?>
	</p>
	<a class="notice-dismiss pbc-hide-notice" data-nonce="<?php echo esc_attr( wp_create_nonce( 'pbc-hide-notice' ) ); ?>" data-notice="pro_csv_tool" href="#"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'woocommerce-product-price-based-on-countries' ); ?></span></a>
</div>
