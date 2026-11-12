<?php
/**
 * Consistent image fallbacks for Pilloway editorial and product cards.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_catalog_media_fallback_asset_url( string $asset ): string {
	return plugins_url( 'assets/' . $asset, dirname( __DIR__ ) . '/pilloway-catalog-bridge.php' );
}

function pilloway_catalog_product_placeholder_src( $src, $size = 'woocommerce_thumbnail' ): string {
	unset( $size );
	if ( is_admin() ) {
		return (string) $src;
	}
	return pilloway_catalog_media_fallback_asset_url( 'pilloway-product-placeholder.svg' );
}
add_filter( 'woocommerce_placeholder_img_src', 'pilloway_catalog_product_placeholder_src', 10, 2 );

function pilloway_catalog_blog_thumbnail_fallback( $html, $post_id, $thumbnail_id, $size, $attr ): string {
	unset( $size, $attr );
	if ( is_admin() || 'post' !== get_post_type( $post_id ) ) {
		return (string) $html;
	}

	$thumbnail_id = absint( $thumbnail_id );
	if ( $thumbnail_id ) {
		$attached_file = get_attached_file( $thumbnail_id );
		if ( '' !== (string) $html && ( ! $attached_file || is_file( $attached_file ) ) ) {
			return (string) $html;
		}
	} elseif ( '' !== (string) $html ) {
		return (string) $html;
	}

	return sprintf(
		'<img src="%1$s" class="wp-post-image pilloway-blog-image-fallback" alt="%2$s" loading="lazy" decoding="async" />',
		esc_url( pilloway_catalog_media_fallback_asset_url( 'pilloway-image-fallback.webp' ) ),
		esc_attr( get_the_title( $post_id ) )
	);
}
add_filter( 'post_thumbnail_html', 'pilloway_catalog_blog_thumbnail_fallback', 20, 5 );

function pilloway_catalog_enqueue_media_fallback_assets(): void {
	$plugin_dir = dirname( __DIR__ );
	$css_path   = $plugin_dir . '/assets/media-fallback.css';
	$js_path    = $plugin_dir . '/assets/media-fallback.js';

	if ( is_readable( $css_path ) ) {
		wp_enqueue_style(
			'pilloway-media-fallback',
			pilloway_catalog_media_fallback_asset_url( 'media-fallback.css' ),
			array( 'pilloway-product-card-surfaces' ),
			(string) filemtime( $css_path )
		);
	}
	if ( is_readable( $js_path ) ) {
		wp_enqueue_script(
			'pilloway-media-fallback',
			pilloway_catalog_media_fallback_asset_url( 'media-fallback.js' ),
			array(),
			(string) filemtime( $js_path ),
			true
		);
		wp_localize_script(
			'pilloway-media-fallback',
			'PillowayMediaFallback',
			array(
				'product' => pilloway_catalog_media_fallback_asset_url( 'pilloway-product-placeholder.svg' ),
				'blog'    => pilloway_catalog_media_fallback_asset_url( 'pilloway-image-fallback.webp' ),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'pilloway_catalog_enqueue_media_fallback_assets', 40 );