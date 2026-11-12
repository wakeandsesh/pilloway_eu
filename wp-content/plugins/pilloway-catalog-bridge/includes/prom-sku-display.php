<?php
/**
 * Show the original Prom product code on product pages.
 * WooCommerce keeps its unique internal SKU for order and inventory lookup.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_catalog_disable_internal_sku_on_product_page( $enabled ): bool {
	return function_exists( 'is_product' ) && is_product() ? false : (bool) $enabled;
}
add_filter( 'wc_product_sku_enabled', 'pilloway_catalog_disable_internal_sku_on_product_page', 20 );

function pilloway_catalog_render_prom_sku_block( string $block_content, array $block ): string {
	if ( ! function_exists( 'is_product' ) || ! is_product() || 'woocommerce/product-sku' !== ( $block['blockName'] ?? '' ) ) {
		return $block_content;
	}

	$product_id = absint( get_queried_object_id() );
	$prom_sku   = $product_id ? trim( (string) get_post_meta( $product_id, '_pilloway_prom_sku', true ) ) : '';
	if ( '' === $prom_sku ) {
		return '';
	}

	$replaced = 0;
	$content  = preg_replace_callback(
		'~(<span\\b[^>]*class="[^"]*\\bsku\\b[^"]*"[^>]*>).*?(</span>)~s',
		static function ( $matches ) use ( $prom_sku ) {
			return $matches[1] . esc_html( $prom_sku ) . $matches[2];
		},
		$block_content,
		1,
		$replaced
	);

	return 1 === $replaced && is_string( $content ) ? $content : '';
}
add_filter( 'render_block', 'pilloway_catalog_render_prom_sku_block', 20, 2 );