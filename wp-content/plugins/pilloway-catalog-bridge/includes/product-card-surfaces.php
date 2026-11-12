<?php
/**
 * Keep product-card surfaces white across WooCommerce pages.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_catalog_enqueue_product_card_surfaces(): void {
	$asset_path = dirname( __DIR__ ) . '/assets/product-card-surfaces.css';
	if ( ! is_readable( $asset_path ) ) {
		return;
	}

	wp_enqueue_style(
		'pilloway-product-card-surfaces',
		plugins_url( 'assets/product-card-surfaces.css', dirname( __DIR__ ) . '/pilloway-catalog-bridge.php' ),
		array(),
		(string) filemtime( $asset_path )
	);
}
add_action( 'wp_enqueue_scripts', 'pilloway_catalog_enqueue_product_card_surfaces', 30 );
