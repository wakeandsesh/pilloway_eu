<?php
/**
 * Render per-language SEO metadata stored on pages and translated taxonomy archives.
 * Product metadata remains handled by the catalog bridge; custom landing templates
 * render their own tags and read these same page fields as their source of truth.
 */
function pilloway_catalog_entity_seo_page_id(): int {
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : (int) get_option( 'woocommerce_shop_page_id' );
		if ( $page_id > 0 && function_exists( 'pll_get_post' ) && function_exists( 'pll_current_language' ) ) {
			$language = (string) pll_current_language( 'slug' );
			if ( '' !== $language ) {
				$translated_id = (int) pll_get_post( $page_id, $language );
				if ( $translated_id > 0 ) {
					$page_id = $translated_id;
				}
			}
		}
		return $page_id;
	}
	return is_page() ? (int) get_queried_object_id() : 0;
}

function pilloway_catalog_entity_document_title( string $title ): string {
	if ( pilloway_catalog_external_seo_plugin_active() || is_admin() ) {
		return $title;
	}
	$page_id = pilloway_catalog_entity_seo_page_id();
	if ( $page_id > 0 ) {
		$seo_title = trim( (string) get_post_meta( $page_id, '_pilloway_seo_title', true ) );
		return '' !== $seo_title ? $seo_title : $title;
	}
	if ( is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$seo_title = trim( (string) get_term_meta( $term->term_id, '_pilloway_seo_title', true ) );
			return '' !== $seo_title ? $seo_title : $title;
		}
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'pilloway_catalog_entity_document_title', 31 );

function pilloway_catalog_entity_meta_description(): void {
	if ( pilloway_catalog_external_seo_plugin_active() || is_admin() || ( function_exists( 'is_product' ) && is_product() ) ) {
		return;
	}
	$description = '';
	$page_id     = pilloway_catalog_entity_seo_page_id();
	if ( $page_id > 0 ) {
		$template = (string) get_post_meta( $page_id, '_wp_page_template', true );
		if ( in_array( $template, array( 'page-beauty-balance.php', 'page-correct-shape.php', 'page-family-dream.php' ), true ) ) {
			return;
		}
		$description = trim( (string) get_post_meta( $page_id, '_pilloway_seo_description', true ) );
	} elseif ( is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$description = trim( (string) get_term_meta( $term->term_id, '_pilloway_seo_description', true ) );
		}
	}
	if ( '' !== $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'pilloway_catalog_entity_meta_description', 2 );


function pilloway_catalog_entity_term_canonical(): void {
	if ( pilloway_catalog_external_seo_plugin_active() || is_admin() || ! is_tax() ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term ) {
		return;
	}
	$page      = max( 1, absint( get_query_var( 'paged' ) ) );
	$canonical = get_pagenum_link( $page );
	if ( ! is_string( $canonical ) || '' === $canonical ) {
		$canonical = get_term_link( $term );
	}
	if ( ! is_wp_error( $canonical ) && '' !== $canonical ) {
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'pilloway_catalog_entity_term_canonical', 1 );
