<?php
/**
 * Polylang-aware product category links for the storefront header.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_catalog_get_current_language_header_categories(): array {
	if ( ! function_exists( 'pll_current_language' ) || ! function_exists( 'pll_get_term_language' ) || ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}

	$language = pll_current_language( 'slug' );
	if ( ! is_string( $language ) || '' === $language ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'lang'       => $language,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$terms,
			static function ( $term ) use ( $language ): bool {
				return $term instanceof WP_Term && $language === pll_get_term_language( $term->term_id, 'slug' );
			}
		)
	);
}

function pilloway_catalog_render_header_category_menu_item(): string {
	$terms = pilloway_catalog_get_current_language_header_categories();
	if ( empty( $terms ) ) {
		return '';
	}

	$taxonomy = get_taxonomy( 'product_cat' );
	if ( ! $taxonomy || empty( $taxonomy->labels->menu_name ) ) {
		return '';
	}

	$items = array();
	foreach ( $terms as $term ) {
		$url = get_term_link( $term, 'product_cat' );
		if ( is_wp_error( $url ) ) {
			continue;
		}

		$items[] = sprintf(
			'<li class="wp-block-navigation-item"><a class="wp-block-navigation-item__content" href="%1$s"><span class="wp-block-navigation-item__label">%2$s</span></a></li>',
			esc_url( $url ),
			esc_html( $term->name )
		);
	}
	if ( empty( $items ) ) {
		return '';
	}

	return sprintf(
		'<li class="wp-block-navigation-item pilloway-nav-categories"><details class="pilloway-category-disclosure"><summary class="wp-block-navigation-item__content pilloway-category-summary"><span class="wp-block-navigation-item__label">%1$s</span></summary><ul class="pilloway-nav-categories-list">%2$s</ul></details></li>',
		esc_html( $taxonomy->labels->menu_name ),
		implode( '', $items )
	);
}

/**
 * The header pattern retains this block for backwards compatibility. The category
 * item is inserted into the classic menu by the filter below, so avoid a duplicate.
 */
function pilloway_catalog_render_header_categories(): string {
	return '';
}

/**
 * Insert the language-aware category disclosure after the second top-level item.
 *
 * @param string   $items Menu item HTML.
 * @param stdClass $args  Menu arguments.
 * @return string
 */
function pilloway_catalog_insert_header_categories_after_second_menu_item( $items, $args ): string {
	if ( ! is_object( $args ) || 'pilloway-primary' !== ( $args->theme_location ?? '' ) ) {
		return $items;
	}
	if ( false !== strpos( $items, 'pilloway-nav-categories' ) ) {
		return $items;
	}

	$category_item = pilloway_catalog_render_header_category_menu_item();
	if ( '' === $category_item ) {
		return $items;
	}

	if ( ! preg_match_all( '/<li\b[^>]*>|<\/li\s*>/i', $items, $tags, PREG_OFFSET_CAPTURE ) ) {
		return $items;
	}

	$depth           = 0;
	$top_level_count = 0;
	$insert_at       = null;

	foreach ( $tags[0] as $tag_match ) {
		$tag    = $tag_match[0];
		$offset = $tag_match[1];

		if ( 0 === stripos( $tag, '</li' ) ) {
			if ( $depth > 0 ) {
				--$depth;
			}
			if ( 0 === $depth ) {
				++$top_level_count;
				if ( 2 === $top_level_count ) {
					$insert_at = $offset + strlen( $tag );
					break;
				}
			}
		} else {
			++$depth;
		}
	}

	if ( null === $insert_at ) {
		return $items;
	}

	return substr_replace( $items, $category_item, $insert_at, 0 );
}
add_filter( 'wp_nav_menu_items', 'pilloway_catalog_insert_header_categories_after_second_menu_item', 20, 2 );

function pilloway_catalog_render_header_category_strip(): string {
	$terms = pilloway_catalog_get_current_language_header_categories();
	if ( empty( $terms ) ) {
		return '';
	}

	$items = array();
	foreach ( $terms as $term ) {
		$url = get_term_link( $term, 'product_cat' );
		if ( is_wp_error( $url ) ) {
			continue;
		}

		$items[] = sprintf(
			'<li class="pilloway-category-strip__item"><a class="pilloway-category-strip__link" href="%1$s">%2$s</a></li>',
			esc_url( $url ),
			esc_html( $term->name )
		);
	}
	if ( empty( $items ) ) {
		return '';
	}

	return sprintf(
		'<section class="pilloway-category-strip alignwide"><ul class="pilloway-category-strip__list">%s</ul></section>',
		implode( '', $items )
	);
}

function pilloway_catalog_register_header_categories_block(): void {
	register_block_type(
		'pilloway/header-categories',
		array(
			'render_callback' => 'pilloway_catalog_render_header_categories',
		)
	);
	register_block_type(
		'pilloway/header-category-strip',
		array(
			'render_callback' => 'pilloway_catalog_render_header_category_strip',
		)
	);
}
add_action( 'init', 'pilloway_catalog_register_header_categories_block', 20 );
