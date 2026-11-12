<?php
/**
 * Replaces the default shop archive on the storefront with translated collection links.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_catalog_is_collections_home(): bool {
	if ( ! function_exists( 'is_front_page' ) || ! is_front_page() || is_search() || is_paged() ) {
		return false;
	}

	// Keep filtered product requests on the shop archive, not the localized home page.
	if ( isset( $_GET['ccf'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state keeps the promotions archive intact.
		return false;
	}

	return true;
}

function pilloway_catalog_current_front_page_meta( string $key ): string {
	$page_id = function_exists( 'get_queried_object_id' ) ? absint( get_queried_object_id() ) : 0;
	$page    = $page_id ? get_post( $page_id ) : null;
	if ( ! $page || 'page' !== $page->post_type ) {
		return '';
	}

	$value = get_post_meta( $page_id, $key, true );
	return is_string( $value ) ? trim( $value ) : '';
}

function pilloway_catalog_collections_language(): string {
	if ( ! function_exists( 'pll_current_language' ) ) {
		return '';
	}

	$language = pll_current_language( 'slug' );
	return is_string( $language ) ? $language : '';
}
function pilloway_catalog_collections_document_title( array $parts ): array {
	if ( pilloway_catalog_is_collections_home() ) {
		$title = pilloway_catalog_current_front_page_meta( '_pilloway_seo_title' );
		if ( '' !== $title ) {
			$parts['title'] = $title;
		}
	}

	return $parts;
}
add_filter( 'document_title_parts', 'pilloway_catalog_collections_document_title', 20 );


function pilloway_catalog_collections_canonical_url(): string {
	if ( function_exists( 'pll_home_url' ) ) {
		$url = pll_home_url( pilloway_catalog_collections_language() );
	} else {
		$url = home_url( '/' );
	}

	if ( ! is_string( $url ) || '' === $url ) {
		$url = home_url( '/' );
	}

	return esc_url_raw( $url );
}

function pilloway_catalog_render_collections_seo_tags(): void {
	if ( ! pilloway_catalog_is_collections_home() ) {
		return;
	}

	$canonical   = pilloway_catalog_collections_canonical_url();
	$title       = pilloway_catalog_current_front_page_meta( '_pilloway_seo_title' );
	$description = pilloway_catalog_current_front_page_meta( '_pilloway_seo_description' );
	$image_alt   = pilloway_catalog_current_front_page_meta( '_pilloway_home_og_alt' );
	if ( '' === $title ) {
		$title = wp_get_document_title();
	}
	$uploads = wp_upload_dir();
	$image   = '';

	if ( ! empty( $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) ) {
		$image_path = trailingslashit( $uploads['basedir'] ) . 'pilloway-brand-collections/beauty-balance-desktop.webp';
		if ( is_readable( $image_path ) ) {
			$image = trailingslashit( $uploads['baseurl'] ) . 'pilloway-brand-collections/beauty-balance-desktop.webp';
		}
	}

	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( '' !== $description ) {
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
	if ( '' !== $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
		if ( '' !== $image_alt ) {
			echo '<meta property="og:image:alt" content="' . esc_attr( $image_alt ) . '">' . "\n";
		}
		echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( '' !== $description ) {
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'pilloway_catalog_render_collections_seo_tags', 1 );

function pilloway_catalog_render_collections_home(): string {
	if ( ! pilloway_catalog_is_collections_home() ) {
		return '';
	}

	$uploads = wp_upload_dir();
	if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
		return '';
	}

	$asset_directory = trailingslashit( $uploads['basedir'] ) . 'pilloway-brand-collections/';
	$upload_url_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
	if ( ! is_string( $upload_url_path ) || '' === $upload_url_path ) {
		return '';
	}
	$asset_url = trailingslashit( $upload_url_path ) . 'pilloway-brand-collections/';

	// The sequence follows the store's requested homepage order.
	$brands = array(
		array( 'slug' => 'beauty-balance', 'brand' => 'Beauty Balance', 'key' => 'beauty-balance' ),
		array( 'slug' => 'correct-shape', 'brand' => 'Correct Shape', 'key' => 'correct-shape' ),
		array( 'slug' => 'family-dream', 'brand' => 'Family Dream', 'key' => 'family-dream' ),
	);
	$language = pilloway_catalog_collections_language();
	$cards    = array();

	foreach ( $brands as $brand ) {
		$source_page = get_page_by_path( $brand['slug'], OBJECT, 'page' );
		if ( ! $source_page ) {
			return '';
		}

		if ( ! function_exists( 'pll_get_post' ) ) {
			return '';
		}
		$target_id = absint( pll_get_post( (int) $source_page->ID, $language ) );
		if ( ! $target_id ) {
			return '';
		}

		$target_page = get_post( $target_id );
		if ( ! $target_page || 'publish' !== $target_page->post_status || $language !== pll_get_post_language( $target_id, 'slug' ) ) {
			return '';
		}

		$description = trim( (string) get_post_meta( $target_id, '_pilloway_home_card_description', true ) );
		$alt         = trim( (string) get_post_meta( $target_id, '_pilloway_home_card_alt', true ) );
		if ( '' === $description || '' === $alt ) {
			return '';
		}

		$desktop_file = $brand['key'] . '-desktop.webp';
		$mobile_file  = $brand['key'] . '-mobile.webp';
		if ( ! is_readable( $asset_directory . $desktop_file ) || ! is_readable( $asset_directory . $mobile_file ) ) {
			return '';
		}

		$url = get_permalink( $target_id );
		if ( ! $url ) {
			return '';
		}

		$cards[] = array(
			'brand'       => $brand['brand'],
			'description' => $description,
			'alt'         => $alt,
			'key'         => $brand['key'],
			'url'         => $url,
			'desktop'     => $asset_url . $desktop_file,
			'mobile'      => $asset_url . $mobile_file,
		);
	}

	if ( 3 !== count( $cards ) ) {
		return '';
	}

	ob_start();

	echo '<section class="pilloway-collections alignfull" aria-labelledby="pilloway-collections-heading">';
	echo '<div class="pilloway-collections__list">';

	foreach ( $cards as $index => $card ) {
		$loading       = 0 === $index ? 'eager' : 'lazy';
		$fetchpriority = 0 === $index ? 'high' : 'auto';
		$heading_tag  = 0 === $index ? 'h1' : 'h2';
		$heading_id   = 0 === $index ? ' id="pilloway-collections-heading"' : '';

		printf(
			'<a class="pilloway-collection-card" data-brand="%1$s" href="%2$s"><div class="pilloway-collection-card__content"><div class="pilloway-collection-card__title-row"><%10$s%11$s class="pilloway-collection-card__brand">%7$s</%10$s><span class="pilloway-collection-card__arrow" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M5 12h14M12 5l7 7-7 7"></path></svg></span></div><span class="pilloway-collection-card__description">%8$s</span></div><span class="pilloway-collection-card__media"><picture><source type="image/webp" media="(max-width: 699px)" srcset="%3$s" width="800" height="800"><img src="%4$s" alt="%9$s" width="1280" height="400" loading="%5$s" decoding="async" fetchpriority="%6$s"></picture></span></a>',
			esc_attr( $card['key'] ),
			esc_url( $card['url'] ),
			esc_url( $card['mobile'] ),
			esc_url( $card['desktop'] ),
			esc_attr( $loading ),
			esc_attr( $fetchpriority ),
			esc_html( $card['brand'] ),
			esc_html( $card['description'] ),
			esc_attr( $card['alt'] ),
			esc_attr( $heading_tag ),
			esc_attr( $heading_id )
		);
	}

	echo '</div></section>';
	return (string) ob_get_clean();
}
add_shortcode( 'pilloway_collections_home', 'pilloway_catalog_render_collections_home' );

function pilloway_catalog_group_contains_shop_controls( array $block ): bool {
	$direct_blocks  = array();

	foreach ( $block['innerBlocks'] ?? array() as $inner_block ) {
		$direct_blocks[] = $inner_block['blockName'] ?? '';
	}

	return in_array( 'woocommerce/product-results-count', $direct_blocks, true )
		&& in_array( 'woocommerce/catalog-sorting', $direct_blocks, true );
}

function pilloway_catalog_render_collections_instead_of_shop( string $block_content, array $block ): string {
	if ( ! pilloway_catalog_is_collections_home() ) {
		return $block_content;
	}

	$block_name = $block['blockName'] ?? '';
	if ( 'core/query-title' === $block_name || 'woocommerce/store-notices' === $block_name ) {
		return '';
	}

	if ( 'core/group' === $block_name && pilloway_catalog_group_contains_shop_controls( $block ) ) {
		return '';
	}

	if ( 'woocommerce/product-collection' === $block_name ) {
		$collections = pilloway_catalog_render_collections_home();
		return '' !== $collections ? $collections : $block_content;
	}

	return $block_content;
}
add_filter( 'render_block', 'pilloway_catalog_render_collections_instead_of_shop', 10, 2 );
