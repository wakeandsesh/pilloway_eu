<?php
/**
 * Plugin Name: PILLOWAY Catalog Integration
 * Description: Places the CatCode Smart Filter on WooCommerce archives and connects it to Polylang-aware catalogue data.
 * Version: 1.1.3
 * Requires Plugins: woocommerce, catcode-smart-filter, catcode-polylang-multilingual-shop-for-woocommerce
 * Text Domain: pilloway-catalog-bridge
 */

defined( 'ABSPATH' ) || exit;

/** Load bundled strings for the active WordPress locale. */
function pilloway_catalog_load_textdomain(): void {
	load_plugin_textdomain( 'pilloway-catalog-bridge', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'pilloway_catalog_load_textdomain', 1 );

/**
 * Keep Pilloway's private catalogue facets under Polylang's native translation model.
 * Their set is driven by the configured filter taxonomy option, not by language-specific code.
 */
function pilloway_catalog_polylang_translatable_filter_taxonomies( array $taxonomies, $is_settings ): array {
	$configured = (array) get_option( 'pilloway_filter_taxonomy_labels', array() );

	foreach ( array_keys( $configured ) as $taxonomy ) {
		if ( ! is_string( $taxonomy ) || 0 !== strpos( $taxonomy, 'pilloway_filter_' ) ) {
			continue;
		}

		if ( $is_settings ) {
			unset( $taxonomies[ $taxonomy ] );
			continue;
		}

		$taxonomies[ $taxonomy ] = $taxonomy;
	}

	return $taxonomies;
}
add_filter( 'pll_get_taxonomies', 'pilloway_catalog_polylang_translatable_filter_taxonomies', 20, 2 );

/** Register configured private catalogue facets without language-specific label arrays. */
function pilloway_catalog_register_prom_filter_taxonomies(): void {
	$configured = (array) get_option( 'pilloway_filter_taxonomy_labels', array() );

	foreach ( array_keys( $configured ) as $taxonomy ) {
		if ( ! is_string( $taxonomy ) || 0 !== strpos( $taxonomy, 'pilloway_filter_' ) || taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$attribute_taxonomy = 'pa_' . substr( $taxonomy, strlen( 'pilloway_filter_' ) );
		$label              = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $attribute_taxonomy ) : '';
		if ( '' === trim( $label ) ) {
			$label = $taxonomy;
		}

		register_taxonomy(
			$taxonomy,
			array( 'product' ),
			array(
				'label'                 => $label,
				'labels'                => array( 'name' => $label, 'singular_name' => $label ),
				'public'                => false,
				'publicly_queryable'    => false,
				'show_ui'               => false,
				'show_in_rest'          => false,
				'hierarchical'          => false,
				'query_var'             => false,
				'rewrite'               => false,
				'show_admin_column'     => false,
				'update_count_callback' => '_update_post_term_count',
			)
		);
	}
}
add_action( 'init', 'pilloway_catalog_register_prom_filter_taxonomies', 5 );

/** Match Prom's filter order: price, availability, sale, then its seven facets. */
function pilloway_catalog_order_filter_blocks( array $blocks ): array {
	$order = array(
		'p'                   => 0,
		's'                   => 1,
		'o'                   => 2,
		'pa_pillow_support'   => 3,
		'pa_filler'           => 4,
		'pa_firmness'         => 5,
		'pa_cover_material'   => 6,
		'pa_pregnancy_use'    => 7,
		'pa_age_group'        => 8,
		'pa_pillow_set'       => 9,
	);
	$position = 0;
	foreach ( $blocks as &$block ) {
		$block['_pilloway_order'] = $order[ (string) ( $block['key'] ?? '' ) ] ?? ( 100 + $position );
		++$position;
	}
	unset( $block );
	usort(
		$blocks,
		static function ( $left, $right ) {
			return (int) ( $left['_pilloway_order'] ?? 0 ) <=> (int) ( $right['_pilloway_order'] ?? 0 );
		}
	);
	foreach ( $blocks as &$block ) {
		unset( $block['_pilloway_order'] );
	}
	unset( $block );
	return $blocks;
}
add_filter( 'catcode_sf_render_facet_blocks', 'pilloway_catalog_order_filter_blocks', 20 );

/** Add the real CatCode filter above the primary WooCommerce loop. */
function pilloway_catalog_render_filter(): void {
	if ( ! function_exists( 'is_shop' ) || ! shortcode_exists( 'catcode_filter' ) ) {
		return;
	}
	global $wp_query;
	if ( $wp_query instanceof WP_Query && ! $wp_query->is_main_query() ) {
		return;
	}
	if ( ! is_shop() && ! is_product_taxonomy() && ! ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) {
		return;
	}
	$filter_html = do_shortcode( '[catcode_filter]' );
	echo pilloway_catalog_translate_filter_heading_markup( $filter_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CatCode renders escaped markup; only heading labels are translated below.
}
add_action( 'woocommerce_before_shop_loop', 'pilloway_catalog_render_filter', 5 );

/** Translate facet headings using the external map even if a filter request omits its locale. */
function pilloway_catalog_translate_filter_heading_markup( string $markup ): string {
	$lang = pilloway_catalog_current_language();
	if ( '' === $lang || 'uk' === $lang || false === strpos( $markup, 'ccf__facet-name' ) ) {
		return $markup;
	}

	$maps = array_merge(
		(array) get_option( 'pilloway_attribute_labels', array() ),
		(array) get_option( 'pilloway_filter_taxonomy_labels', array() )
	);
	if ( ! $maps ) {
		return $markup;
	}

	$translated = preg_replace_callback(
		'/(<span class="ccf__facet-name">)(.*?)(<\/span>)/s',
		static function ( $matches ) use ( $maps, $lang ) {
			$current = html_entity_decode( trim( wp_strip_all_tags( $matches[2] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			foreach ( $maps as $translations ) {
				if ( ! is_array( $translations ) || empty( $translations['uk'] ) || empty( $translations[ $lang ] ) ) {
					continue;
				}
				$source = html_entity_decode( (string) $translations['uk'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( $current === $source ) {
					return $matches[1] . esc_html( (string) $translations[ $lang ] ) . $matches[3];
				}
			}
			return $matches[0];
		},
		$markup
	);

	return is_string( $translated ) ? $translated : $markup;
}

/** Read the active language exclusively from Polylang. */
function pilloway_catalog_current_language(): string {
	if ( ! function_exists( 'pll_current_language' ) ) {
		return '';
	}
	$lang = pll_current_language( 'slug' );
	return is_string( $lang ) ? $lang : '';
}

/** Keep the filter's shopper-facing text in language files outside CatCode. */
/** Render translated shop pages as product archives while keeping their clean URLs. */
function pilloway_catalog_route_translated_shop_pages( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_page() || ! function_exists( 'wc_get_page_id' ) ) {
		return;
	}

	$shop_id  = absint( wc_get_page_id( 'shop' ) );
	$shop_ids = $shop_id ? array( $shop_id ) : array();
	if ( $shop_id && function_exists( 'pll_get_post_translations' ) ) {
		$shop_ids = array_values( array_unique( array_map( 'absint', pll_get_post_translations( $shop_id ) ) ) );
	}

	$page_id = absint( $query->get( 'page_id' ) );
	if ( ! $page_id ) {
		$page_id = absint( $query->get( 'p' ) );
	}
	if ( ! $page_id ) {
		$page_path = trim( (string) $query->get( 'pagename' ), '/' );
		if ( '' !== $page_path ) {
			$page = get_page_by_path( $page_path, OBJECT, 'page' );
			$page_id = $page ? absint( $page->ID ) : 0;
		}
	}

	if ( ! $page_id || ! in_array( $page_id, $shop_ids, true ) ) {
		return;
	}

	$query->set( 'post_type', 'product' );
	$query->set( 'page_id', 0 );
	$query->set( 'p', 0 );
	$query->set( 'pagename', '' );
	$query->set( 'name', '' );
	$query->is_page             = false;
	$query->is_singular         = false;
	$query->is_home             = false;
	$query->is_front_page       = false;
	$query->is_archive          = true;
	$query->is_post_type_archive = true;
}
add_action( 'pre_get_posts', 'pilloway_catalog_route_translated_shop_pages', 1 );

function pilloway_catalog_filter_gettext( $translation, $text, $domain ) {
	static $catalogues = array();

	if ( 'catcode-smart-filter' !== $domain || ! is_string( $text ) ) {
		return $translation;
	}
	$lang = pilloway_catalog_current_language();
	if ( '' === $lang ) {
		return $translation;
	}
	if ( function_exists( 'pll_translate_string' ) ) {
		$polylang_translation = pll_translate_string( $text, $lang );
		if ( is_string( $polylang_translation ) && $text !== $polylang_translation ) {
			return $polylang_translation;
		}
	}
	if ( ! array_key_exists( $lang, $catalogues ) ) {
		$file = __DIR__ . '/languages/catcode-smart-filter-' . sanitize_key( $lang ) . '.php';
		$catalogues[ $lang ] = is_readable( $file ) ? require $file : array();
	}
	$strings = $catalogues[ $lang ];
	return is_array( $strings ) && isset( $strings[ $text ] ) ? (string) $strings[ $text ] : $translation;
}
add_filter( 'gettext', 'pilloway_catalog_filter_gettext', 20, 3 );

/** Localize WooCommerce's hard-coded server-rendered mini-cart close label. */
function pilloway_catalog_localize_mini_cart_close_label( $block_content, $block ) {
	unset( $block );
	if ( ! is_string( $block_content ) || false === strpos( $block_content, 'wc-block-components-drawer__close' ) || false === strpos( $block_content, 'aria-label="Close"' ) ) {
		return $block_content;
	}

	$translation = translate( 'Close', 'woocommerce' );
	if ( ! is_string( $translation ) || 'Close' === $translation ) {
		return $block_content;
	}

	return preg_replace( '/aria-label="Close"/', 'aria-label="' . esc_attr( $translation ) . '"', $block_content, 1 );
}
add_filter( 'render_block_woocommerce/mini-cart-contents', 'pilloway_catalog_localize_mini_cart_close_label', 20, 2 );


/** Translate configured payment titles and descriptions without changing gateway settings. */
function pilloway_catalog_localize_gateway_text( $text, $gateway_id, $field ) {
	$lang = pilloway_catalog_current_language();
	if ( '' === $lang || 'uk' === $lang || ! is_string( $text ) ) {
		return $text;
	}

	static $labels = null;
	if ( null === $labels ) {
		$file   = __DIR__ . '/languages/woocommerce-gateway-labels.php';
		$labels = is_readable( $file ) ? require $file : array();
	}

	$entry = $labels[ $gateway_id ][ $field ] ?? array();
	if ( ! is_array( $entry ) || ! isset( $entry['source'], $entry[ $lang ] ) || $entry['source'] !== $text ) {
		return $text;
	}
	return (string) $entry[ $lang ];
}
function pilloway_catalog_localize_gateway_title( $title, $gateway_id ) {
	return pilloway_catalog_localize_gateway_text( $title, $gateway_id, 'title' );
}
function pilloway_catalog_localize_gateway_description( $description, $gateway_id ) {
	return pilloway_catalog_localize_gateway_text( $description, $gateway_id, 'description' );
}
add_filter( 'woocommerce_gateway_title', 'pilloway_catalog_localize_gateway_title', 20, 2 );
add_filter( 'woocommerce_gateway_description', 'pilloway_catalog_localize_gateway_description', 20, 2 );

/** Register the source strings so store owners may override file defaults in Polylang. */
function pilloway_catalog_register_filter_strings(): void {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}
	$file = __DIR__ . '/languages/catcode-smart-filter-uk.php';
	if ( ! is_readable( $file ) ) {
		return;
	}
	$strings = require $file;
	if ( ! is_array( $strings ) ) {
		return;
	}
	foreach ( array_keys( $strings ) as $source ) {
		pll_register_string( 'catcode_filter_' . md5( (string) $source ), (string) $source, 'PILLOWAY: CatCode Smart Filter' );
	}
}
add_action( 'init', 'pilloway_catalog_register_filter_strings', 20 );

/** Keep imported, human-edited Woo attribute labels in the database translation map. */
function pilloway_catalog_attribute_label( $label, $name = '', $product = null ) {
	unset( $product );
	$lang   = pilloway_catalog_current_language();
	$labels = get_option( 'pilloway_attribute_labels', array() );
	$name   = sanitize_key( (string) $name );
	if ( '' !== $lang && is_string( $label ) && isset( $labels[ $name ][ $lang ] ) && is_string( $labels[ $name ][ $lang ] ) ) {
		return $labels[ $name ][ $lang ];
	}
	return $label;
}
add_filter( 'woocommerce_attribute_label', 'pilloway_catalog_attribute_label', 100, 3 );

/** Seed the small set of non-attribute labels only once; later edits stay intact. */
function pilloway_catalog_seed_taxonomy_labels(): void {
	$defaults = array(
		'product_brand'                 => array( 'uk' => 'Бренд', 'ru' => 'Бренд', 'en' => 'Brand', 'pl' => 'Marka' ),
		'product_cat'                   => array( 'uk' => 'Категорія', 'ru' => 'Категория', 'en' => 'Category', 'pl' => 'Kategoria' ),
		'pilloway_filter_pillow_support' => array( 'uk' => 'Подушка для підтримки', 'ru' => 'Подушка для поддержки' ),
		'pilloway_filter_filler'         => array( 'uk' => 'Наповнювач', 'ru' => 'Наполнитель' ),
		'pilloway_filter_firmness'       => array( 'uk' => 'Жорсткість подушки', 'ru' => 'Жесткость подушки' ),
		'pilloway_filter_cover_material' => array( 'uk' => 'Тканина наволочки', 'ru' => 'Ткань наволочки' ),
		'pilloway_filter_pregnancy_use'  => array( 'uk' => 'Для вагітних', 'ru' => 'Для беременных' ),
		'pilloway_filter_age_group'      => array( 'uk' => 'Для дітей', 'ru' => 'Для детей' ),
		'pilloway_filter_pillow_set'     => array( 'uk' => 'Комплект подушок', 'ru' => 'Комплект подушек' ),
	);
	$existing = get_option( 'pilloway_filter_taxonomy_labels', array() );
	update_option(
		'pilloway_filter_taxonomy_labels',
		array_merge( $defaults, is_array( $existing ) ? $existing : array() ),
		false
	);
}
add_action( 'plugins_loaded', 'pilloway_catalog_seed_taxonomy_labels', 20 );

/** Return Polylang's actual translation set for the current page or archive. */
function pilloway_catalog_current_translation_ids(): array {
	if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) && function_exists( 'pll_get_post_translations' ) ) {
		$shop_id = absint( wc_get_page_id( 'shop' ) );
		return $shop_id ? array_filter( array_map( 'absint', (array) pll_get_post_translations( $shop_id ) ) ) : array();
	}

	if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() && function_exists( 'pll_get_term_translations' ) ) {
		$term = get_queried_object();
		return $term instanceof WP_Term ? array_filter( array_map( 'absint', (array) pll_get_term_translations( (int) $term->term_id ) ) ) : array();
	}

	if ( function_exists( 'is_singular' ) && is_singular() && function_exists( 'pll_get_post_translations' ) ) {
		$post_id = absint( get_queried_object_id() );
		return $post_id ? array_filter( array_map( 'absint', (array) pll_get_post_translations( $post_id ) ) ) : array();
	}

	return array();
}

/** Keep the switcher on real translations instead of sending shoppers to a fallback home page. */
function pilloway_catalog_filter_switcher_translations( array $languages ): array {
	$translations = pilloway_catalog_current_translation_ids();
	if ( ! $translations ) {
		unset( $languages['fr'] );
		return $languages;
	}

	foreach ( $languages as $slug => $language ) {
		if ( ! isset( $translations[ $slug ] ) ) {
			unset( $languages[ $slug ] );
		}
	}

	return $languages;
}

/** Remove hreflang targets that are only language-home fallbacks, not page translations. */
function pilloway_catalog_filter_hreflang_translations( array $hreflangs ): array {
	$translations = pilloway_catalog_current_translation_ids();
	if ( ! $translations ) {
		foreach ( array_keys( $hreflangs ) as $language ) {
			if ( preg_match( '/^fr(?:[-_]|$)/i', (string) $language ) ) {
				unset( $hreflangs[ $language ] );
			}
		}
		return $hreflangs;
	}

	if (
		function_exists( 'pilloway_catalog_is_collections_home' )
		&& pilloway_catalog_is_collections_home()
		&& function_exists( 'pll_home_url' )
	) {
		$home_hreflangs = array();
		foreach ( $hreflangs as $language => $url ) {
			if ( 'x-default' === $language ) {
				$default_language = function_exists( 'pll_default_language' ) ? pll_default_language( 'slug' ) : '';
				if ( '' !== $default_language && isset( $translations[ $default_language ] ) ) {
					$home_hreflangs[ $language ] = pll_home_url( $default_language );
				}
				continue;
			}

			$slug = explode( '-', sanitize_key( (string) $language ) )[0];
			if ( isset( $translations[ $slug ] ) ) {
				$home_hreflangs[ $language ] = pll_home_url( $slug );
			}
		}

		if ( $home_hreflangs ) {
			return $home_hreflangs;
		}
	}

	$translation_urls = array();
	foreach ( $translations as $post_id ) {
		$url = get_permalink( (int) $post_id );
		if ( is_string( $url ) && '' !== $url ) {
			$parts = wp_parse_url( $url );
			$path  = isset( $parts['path'] ) ? '/' . ltrim( $parts['path'], '/' ) : '/';
			$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
			$translation_urls[ untrailingslashit( $path ) . $query ] = true;
		}
	}

	$filtered = array();
	foreach ( $hreflangs as $language => $url ) {
		if ( 'x-default' === $language ) {
			$default_language = function_exists( 'pll_default_language' ) ? pll_default_language( 'slug' ) : '';
			if ( '' !== $default_language && isset( $translations[ $default_language ] ) ) {
				$filtered[ $language ] = $url;
			}
			continue;
		}

		$parts = wp_parse_url( (string) $url );
		$path  = isset( $parts['path'] ) ? '/' . ltrim( $parts['path'], '/' ) : '/';
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		if ( isset( $translation_urls[ untrailingslashit( $path ) . $query ] ) ) {
			$filtered[ $language ] = $url;
		}
	}

	return $filtered ? $filtered : $hreflangs;
}
add_filter( 'pll_rel_hreflang_attributes', 'pilloway_catalog_filter_hreflang_translations', 20 );

/** Build a text-only Polylang language menu for the site header. */
function pilloway_catalog_language_switcher(): string {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return '';
	}

	$languages = pll_the_languages(
		array(
			'raw'                    => 1,
			'hide_if_no_translation' => 0,
			'hide_current'           => 0,
		)
	);
	if ( is_array( $languages ) ) {
		$languages = pilloway_catalog_filter_switcher_translations( $languages );
	}
	if ( ! is_array( $languages ) || count( $languages ) < 2 ) {
		return '';
	}

	$labels = array(
		'uk' => array( 'name' => 'Українська', 'short' => 'UA' ),
		'ru' => array( 'name' => 'Русский', 'short' => 'RU' ),
		'en' => array( 'name' => 'English', 'short' => 'EN' ),
		'pl' => array( 'name' => 'Polski', 'short' => 'PL' ),
		'fr' => array( 'name' => 'Français', 'short' => 'FR' ),
	);
	$current_slug        = pilloway_catalog_current_language();
	$current_label       = '';
	$current_short_label = '';
	$items               = '';
	$instance            = wp_unique_id( 'pilloway-language-' );
	$current_translations = pilloway_catalog_current_translation_ids();

	foreach ( $languages as $language ) {
		if ( ! is_array( $language ) ) {
			continue;
		}
		$slug  = isset( $language['slug'] ) ? sanitize_key( (string) $language['slug'] ) : '';
		$url   = isset( $language['url'] ) ? (string) $language['url'] : '';
		$label       = isset( $labels[ $slug ]['name'] ) ? $labels[ $slug ]['name'] : ( isset( $language['name'] ) ? (string) $language['name'] : strtoupper( $slug ) );
		$short_label = isset( $labels[ $slug ]['short'] ) ? $labels[ $slug ]['short'] : strtoupper( $slug );
		if ( '' === $slug || '' === $url ) {
			continue;
		}

		if (
			function_exists( 'pilloway_catalog_is_collections_home' )
			&& pilloway_catalog_is_collections_home()
			&& function_exists( 'pll_home_url' )
		) {
			$home_url = pll_home_url( $slug );
			if ( is_string( $home_url ) && '' !== $home_url ) {
				$url = $home_url;
			}
		}

		$is_current = $slug === $current_slug;
		if ( $is_current ) {
			$current_label       = $label;
			$current_short_label = $short_label;
		}
		if ( $current_translations && empty( $current_translations[ $slug ] ) ) {
			continue;
		}
		$items .= sprintf(
			'<li class="pilloway-language-switcher__item"><a class="pilloway-language-switcher__link" href="%1$s" lang="%2$s" hreflang="%2$s" aria-label="%4$s"%3$s><span class="pilloway-language-switcher__code" aria-hidden="true">%5$s</span>%6$s</a></li>',
			esc_url( $url ),
			esc_attr( $slug ),
			$is_current ? ' aria-current="page"' : '',
			esc_attr( $label ),
			esc_html( $short_label ),
			$is_current ? '<span class="pilloway-language-switcher__check" aria-hidden="true">✓</span>' : ''
		);
	}

	if ( '' === $items || '' === $current_label ) {
		return '';
	}

	$aria_labels = array( 'uk' => 'Обрати мову', 'ru' => 'Выбрать язык', 'en' => 'Choose language', 'pl' => 'Wybierz język', 'fr' => 'Choisir la langue' );
	$aria_label  = $aria_labels[ $current_slug ] ?? 'Choose language';
	$button_aria_label = $aria_label . ': ' . $current_label;
	return sprintf(
		'<div class="pilloway-language-switcher" data-pilloway-language-switcher><button class="pilloway-language-switcher__button" type="button" aria-expanded="false" aria-controls="%1$s" aria-label="%2$s"><span aria-hidden="true">%3$s</span><svg class="pilloway-language-switcher__chevron" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></button><ul class="pilloway-language-switcher__menu" id="%1$s" aria-label="%4$s" hidden>%5$s</ul></div>',
		esc_attr( $instance ),
		esc_attr( $button_aria_label ),
		esc_html( $current_short_label ),
		esc_attr( $aria_label ),
		$items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each link component above is escaped.
	);
}

/** Add the language menu alongside the primary block navigation, once. */
function pilloway_catalog_add_language_switcher_to_header( $content, $block ) {
	static $added = false;
	if ( $added || is_admin() || ! is_array( $block ) || 'core/navigation' !== ( $block['blockName'] ?? '' ) ) {
		return $content;
	}
	$switcher = pilloway_catalog_language_switcher();
	if ( '' === $switcher ) {
		return $content;
	}
	$added = true;
	return $content . $switcher;
}
add_filter( 'render_block', 'pilloway_catalog_add_language_switcher_to_header', 20, 2 );

/** Load the small, self-contained header switcher assets. */
function pilloway_catalog_enqueue_language_switcher_assets(): void {
	wp_enqueue_style(
		'pilloway-language-switcher',
		plugins_url( 'assets/language-switcher.css', __FILE__ ),
		array(),
		(string) filemtime( __DIR__ . '/assets/language-switcher.css' )
	);
	wp_enqueue_script(
		'pilloway-language-switcher',
		plugins_url( 'assets/language-switcher.js', __FILE__ ),
		array(),
		(string) filemtime( __DIR__ . '/assets/language-switcher.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'pilloway_catalog_enqueue_language_switcher_assets' );

/** Keep the desktop CatCode filter in a compact sidebar beside the product grid. */
function pilloway_catalog_enqueue_archive_layout(): void {
	if ( ! function_exists( 'is_shop' ) ) {
		return;
	}
	$is_product_search  = is_search() && 'product' === get_query_var( 'post_type' );
	$is_collections_home = function_exists( 'pilloway_catalog_is_collections_home' ) && pilloway_catalog_is_collections_home();
	if ( ! is_shop() && ! is_product_taxonomy() && ! $is_product_search && ! $is_collections_home ) {
		return;
	}

	wp_enqueue_style(
		'pilloway-catalog-archive',
		plugins_url( 'assets/catalog-archive.css', __FILE__ ),
		array(),
		(string) filemtime( __DIR__ . '/assets/catalog-archive.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'pilloway_catalog_enqueue_archive_layout' );

/** Return published members of the current language's source product group. */
function pilloway_catalog_variant_group_products( int $product_id ): array {
	$group_id = trim( (string) get_post_meta( $product_id, '_pilloway_variant_group_id', true ) );
	if ( '' === $group_id ) {
		return array();
	}
	$lang = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $product_id, 'slug' ) : '';
	$query = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'          => -1,
			'fields'                  => 'ids',
			'no_found_rows'           => true,
			'update_post_meta_cache'  => true,
			'update_post_term_cache'  => false,
			'meta_key'                => '_pilloway_variant_position',
			'orderby'                 => 'meta_value_num',
			'order'                   => 'ASC',
			'meta_query'              => array(
				array(
					'key'   => '_pilloway_variant_group_id',
					'value' => $group_id,
				),
			),
			'lang'                   => $lang,
		)
	);
	$product_ids = array_map( 'intval', (array) $query->posts );
	if ( '' !== $lang && function_exists( 'pll_get_post_language' ) ) {
		$product_ids = array_values(
			array_filter(
				$product_ids,
				static function ( $candidate_id ) use ( $lang ) {
					return $lang === pll_get_post_language( (int) $candidate_id, 'slug' );
				}
			)
		);
	}
	return $product_ids;
}

/** Map Prom's localized variant-axis labels to Schema.org properties Google supports. */
function pilloway_catalog_schema_variant_axis( string $label ): string {
	$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $label ), 'UTF-8' ) : strtolower( trim( $label ) );
	$map = array(
		'колір' => 'color', 'цвет' => 'color', 'color' => 'color',
		'розмір' => 'size', 'размер' => 'size', 'size' => 'size',
		'матеріал' => 'material', 'материал' => 'material', 'material' => 'material',
		'візерунок' => 'pattern', 'узор' => 'pattern', 'pattern' => 'pattern',
		'стать' => 'suggestedGender', 'пол' => 'suggestedGender', 'gender' => 'suggestedGender',
		'вік' => 'suggestedAge', 'возраст' => 'suggestedAge', 'age' => 'suggestedAge',
	);
	return (string) ( $map[ $key ] ?? '' );
}

/** Extract recognized, localized variant-axis values stored by the importer. */
function pilloway_catalog_variant_schema_values( int $product_id ): array {
	$attributes = get_post_meta( $product_id, '_pilloway_variant_attributes', true );
	$values     = array();
	foreach ( (array) $attributes as $attribute ) {
		$name  = trim( (string) ( $attribute['name'] ?? '' ) );
		$value = trim( (string) ( $attribute['value'] ?? '' ) );
		$axis  = pilloway_catalog_schema_variant_axis( $name );
		if ( '' !== $axis && '' !== $value ) {
			$values[ $axis ] = $value;
		}
	}

	// Prefer the product's localized WooCommerce color term over the imported
	// variant metadata, which can retain the source-language color label.
	if ( taxonomy_exists( 'pa_color' ) ) {
		$color_terms = wp_get_post_terms( $product_id, 'pa_color', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $color_terms ) && $color_terms ) {
			$color_terms = array_values( array_filter( array_map( 'trim', $color_terms ) ) );
			if ( $color_terms ) {
				$values['color'] = implode( ', ', $color_terms );
			}
		}
	}

	return $values;
}

/** Register the variant-picker labels in Polylang's string table. */
function pilloway_catalog_register_variant_picker_strings(): void {
    if ( ! function_exists( 'pll_register_string' ) ) {
        return;
    }

    pll_register_string( 'pilloway_variant_picker_color', 'Колір', 'PILLOWAY: Product variants' );
    pll_register_string( 'pilloway_variant_picker_option', 'Варіант', 'PILLOWAY: Product variants' );
}
add_action( 'admin_init', 'pilloway_catalog_register_variant_picker_strings' );

/** Expose Prom color/size siblings in a crawlable dropdown on each product page. */
function pilloway_catalog_render_variant_links(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$product_id = (int) $product->get_id();
	$group_ids  = pilloway_catalog_variant_group_products( $product_id );
	if ( count( $group_ids ) < 2 ) {
		return;
	}
	$current_values = pilloway_catalog_variant_schema_values( $product_id );
	$has_color      = array_key_exists( 'color', $current_values );
	$lang           = pilloway_catalog_current_language();
	$heading_source = $has_color ? 'Колір' : 'Варіант';
	$heading        = function_exists( 'pll_translate_string' )
		? (string) pll_translate_string( $heading_source, $lang )
		: $heading_source;
	if ( '' === trim( $heading ) ) {
		$heading = $heading_source;
	}
	$items          = '';
	$current_label = '';
	foreach ( $group_ids as $variant_id ) {
		$variant = wc_get_product( $variant_id );
		if ( ! $variant instanceof WC_Product ) {
			continue;
		}
		$variant_values = pilloway_catalog_variant_schema_values( $variant_id );
		$label_values   = array();
		foreach ( $variant_values as $value ) {
			$label_values[] = $value;
		}
		$label = $label_values ? implode( ' · ', $label_values ) : $variant->get_name();
		if ( $variant_id === $product_id ) {
			$current_label = $label;
			$items .= '<li><span class="pilloway-variant-picker__option" aria-current="true">' . esc_html( $label ) . '</span></li>';
		} else {
			$items .= '<li><a class="pilloway-variant-picker__option" href="' . esc_url( get_permalink( $variant_id ) ) . '">' . esc_html( $label ) . '</a></li>';
		}
	}
	if ( '' === $items ) {
		return;
	}
	if ( '' === $current_label ) {
		$current_label = $product->get_name();
	}
	echo '<nav class="pilloway-variant-picker" aria-label="' . esc_attr( $heading ) . '"><span class="pilloway-variant-picker__heading">' . esc_html( $heading ) . '</span><details class="pilloway-variant-picker__dropdown"><summary class="pilloway-variant-picker__trigger"><span>' . esc_html( $current_label ) . '</span><span class="pilloway-variant-picker__chevron" aria-hidden="true"></span></summary><ul class="pilloway-variant-picker__list">' . $items . '</ul></details></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all dynamic values are escaped above.
}
add_action( 'woocommerce_single_product_summary', 'pilloway_catalog_render_variant_links', 7 );

/** Make each standalone color page a member of the same Google ProductGroup. */
function pilloway_catalog_add_variant_group_to_product_schema( array $markup, $schema_product ): array {
	if ( ! $schema_product instanceof WC_Product ) {
		return $markup;
	}
	$product_id = (int) $schema_product->get_id();
	$group_id   = trim( (string) get_post_meta( $product_id, '_pilloway_variant_group_id', true ) );
	$group_ids  = pilloway_catalog_variant_group_products( $product_id );
	if ( '' === $group_id || count( $group_ids ) < 2 ) {
		return $markup;
	}
	$source_group_id = trim( (string) get_post_meta( $product_id, '_pilloway_variant_group_id', true ) );
	$group_entity_id = rtrim( get_permalink( $product_id ), '/' ) . '#pilloway-group-' . rawurlencode( $source_group_id );
	$group_sku       = 'PILLOWAY-' . $source_group_id;
	$markup['isVariantOf']           = array( '@id' => $group_entity_id );
	$markup['inProductGroupWithID'] = $group_sku;
	foreach ( pilloway_catalog_variant_schema_values( $product_id ) as $axis => $value ) {
		$markup[ $axis ] = $value;
	}
	return $markup;
}
add_filter( 'woocommerce_structured_data_product', 'pilloway_catalog_add_variant_group_to_product_schema', 20, 2 );

/** Include ProductGroup nodes in WooCommerce's JSON-LD output on product pages. */
function pilloway_catalog_add_product_group_schema_type( array $types ): array {
	if ( function_exists( 'is_product' ) && is_product() ) {
		$types[] = 'productgroup';
	}
	return array_values( array_unique( $types ) );
}
add_filter( 'woocommerce_structured_data_type_for_page', 'pilloway_catalog_add_product_group_schema_type', 20 );

/** Add a per-page ProductGroup plus linked sibling URLs to WooCommerce's JSON-LD graph. */
function pilloway_catalog_add_product_group_schema(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'WC' ) || ! WC()->structured_data ) {
		return;
	}
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$product_id = (int) $product->get_id();
	$group_id   = trim( (string) get_post_meta( $product_id, '_pilloway_variant_group_id', true ) );
	$group_ids  = pilloway_catalog_variant_group_products( $product_id );
	if ( '' === $group_id || count( $group_ids ) < 2 ) {
		return;
	}
	$permalink       = get_permalink( $product_id );
	$group_entity_id = rtrim( $permalink, '/' ) . '#pilloway-group-' . rawurlencode( $group_id );
	$variants        = array();
	$varies_by       = array();
	foreach ( $group_ids as $variant_id ) {
		$variant = wc_get_product( $variant_id );
		if ( ! $variant instanceof WC_Product ) {
			continue;
		}
		if ( $variant_id !== $product_id ) {
			$variants[] = array( '@type' => 'Product', 'url' => get_permalink( $variant_id ) );
			foreach ( pilloway_catalog_variant_schema_values( $variant_id ) as $axis => $unused_value ) {
				$varies_by[ $axis ] = 'https://schema.org/' . $axis;
			}
			continue;
		}
		$variant_entity = array(
			'@type' => 'Product',
			'@id'   => $permalink . '#product',
			'name'  => $variant->get_name(),
			'url'   => $permalink,
			'sku'   => $variant->get_sku() ? $variant->get_sku() : (string) $variant_id,
			'offers' => array(
				'@type'         => 'Offer',
				'url'           => $permalink,
				'priceCurrency' => get_woocommerce_currency(),
				'price'         => wc_format_decimal( $variant->get_price(), wc_get_price_decimals() ),
				'availability'  => 'https://schema.org/' . ( $variant->is_in_stock() ? 'InStock' : 'OutOfStock' ),
			),
		);
		$image = wp_get_attachment_url( (int) $variant->get_image_id() );
		if ( $image ) {
			$variant_entity['image'] = $image;
		}
		$variant_axes = pilloway_catalog_variant_schema_values( $variant_id );
		foreach ( $variant_axes as $axis => $value ) {
			$variant_entity[ $axis ] = $value;
			$varies_by[ $axis ]      = 'https://schema.org/' . $axis;
		}
		$variants[] = $variant_entity;
	}
	if ( count( $variants ) < 2 ) {
		return;
	}
	$group_title = trim( (string) get_post_meta( $product_id, '_pilloway_variant_group_title', true ) );
	if ( '' === $group_title ) {
		$group_title = $product->get_name();
	}
	$group = array(
		'@type'         => 'ProductGroup',
		'@id'           => $group_entity_id,
		'name'          => $group_title,
		'productGroupID' => 'PILLOWAY-' . $group_id,
		'variesBy'      => array_values( $varies_by ),
		'hasVariant'    => $variants,
	);
	$brand_terms = wp_get_post_terms( $product_id, 'product_brand', array( 'fields' => 'names' ) );
	if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms[0] ) ) {
		$group['brand'] = array( '@type' => 'Brand', 'name' => (string) $brand_terms[0] );
	}
	WC()->structured_data->set_data( $group );
}
add_action( 'woocommerce_single_product_summary', 'pilloway_catalog_add_product_group_schema', 59 );

/** Enqueue a compact selector style only on product detail pages. */
function pilloway_catalog_enqueue_variant_picker(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$asset_path = __DIR__ . '/assets/variant-picker.css';
	wp_enqueue_style(
		'pilloway-variant-picker',
		plugins_url( 'assets/variant-picker.css', __FILE__ ),
		array(),
		is_readable( $asset_path ) ? (string) filemtime( $asset_path ) : '1.0.5'
	);
	$script_path = __DIR__ . '/assets/variant-picker.js';
	wp_enqueue_script(
		'pilloway-variant-picker',
		plugins_url( 'assets/variant-picker.js', __FILE__ ),
		array(),
		is_readable( $script_path ) ? (string) filemtime( $script_path ) : '1.0.0',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'pilloway_catalog_enqueue_variant_picker' );

/** Use imported search snippets when no dedicated SEO plugin owns these tags. */
function pilloway_catalog_external_seo_plugin_active(): bool {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' );
}

function pilloway_catalog_product_document_title( string $title ): string {
	if ( pilloway_catalog_external_seo_plugin_active() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return $title;
	}
	$seo_title = trim( (string) get_post_meta( get_queried_object_id(), '_pilloway_seo_title', true ) );
	return '' !== $seo_title ? $seo_title : $title;
}
add_filter( 'pre_get_document_title', 'pilloway_catalog_product_document_title', 30 );

function pilloway_catalog_product_meta_description(): void {
	if ( pilloway_catalog_external_seo_plugin_active() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$description = trim( (string) get_post_meta( get_queried_object_id(), '_pilloway_seo_description', true ) );
	if ( '' === $description ) {
		return;
	}
	echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
}
add_action( 'wp_head', 'pilloway_catalog_product_meta_description', 2 );

/** Show the source variant family and translated sibling edit links in product admin. */
function pilloway_catalog_add_variant_admin_box(): void {
	add_meta_box(
		'pilloway-variant-family',
		__( 'PILLOWAY variant family', 'pilloway-catalog-bridge' ),
		'pilloway_catalog_render_variant_admin_box',
		'product',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_product', 'pilloway_catalog_add_variant_admin_box' );

function pilloway_catalog_render_variant_admin_box( WP_Post $post ): void {
	$lang = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post->ID, 'slug' ) : '';
	$ru   = 'ru' === $lang;
	$group_id = trim( (string) get_post_meta( $post->ID, '_pilloway_variant_group_id', true ) );
	if ( '' === $group_id ) {
		echo '<p>' . esc_html( $ru ? 'Связанных вариантов Prom нет.' : 'Пов’язаних варіантів Prom немає.' ) . '</p>';
		return;
	}
	$group_title = trim( (string) get_post_meta( $post->ID, '_pilloway_variant_group_title', true ) );
	echo '<p><strong>' . esc_html( $group_title ?: ( $ru ? 'Группа вариантов' : 'Група варіантів' ) ) . '</strong><br><code>' . esc_html( $group_id ) . '</code></p>';
	$variant_ids = pilloway_catalog_variant_group_products( (int) $post->ID );
	if ( ! $variant_ids ) {
		return;
	}
	echo '<ul>';
	foreach ( $variant_ids as $variant_id ) {
		$variant = wc_get_product( $variant_id );
		if ( ! $variant instanceof WC_Product ) {
			continue;
		}
		$label = implode( ' · ', array_values( pilloway_catalog_variant_schema_values( $variant_id ) ) );
		if ( '' === $label ) {
			$label = $variant->get_name();
		}
		$edit_link = get_edit_post_link( $variant_id );
		$title     = $variant_id === (int) $post->ID ? ( $ru ? ' — текущий' : ' — поточний' ) : '';
		echo '<li><a href="' . esc_url( $edit_link ) . '">' . esc_html( $label . $title ) . '</a></li>';
	}
	echo '</ul>';
}

/** Preserve source product and category URLs when this WordPress site takes over the domain. */
function pilloway_catalog_redirect_legacy_prom_urls(): void {
	if ( is_admin() || wp_doing_ajax() || ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	if ( preg_match( '#^/(ua/)?p(\d+)-[^/]+\.html$#', $path, $product_match ) ) {
		$language = ! empty( $product_match[1] ) ? 'uk' : 'ru';
		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_pilloway_source_id',
				'meta_value'     => (string) $product_match[2],
				'lang'           => $language,
			)
		);
		if ( ! empty( $product_ids[0] ) ) {
			wp_safe_redirect( get_permalink( (int) $product_ids[0] ), 301, 'PILLOWAY Catalog Bridge' );
			exit;
		}
	}
	if ( preg_match( '#^/(ua/)?g(\d+)-[^/]+/?$#', $path, $category_match ) ) {
		$language = ! empty( $category_match[1] ) ? 'uk' : 'ru';
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 1,
				'lang'       => $language,
				'meta_query' => array(
					array(
						'key'   => '_pilloway_source_group_id',
						'value' => (string) $category_match[2],
					),
				),
			)
		);
		if ( ! is_wp_error( $terms ) && ! empty( $terms[0] ) ) {
			$term_language = function_exists( 'pll_get_term_language' ) ? (string) pll_get_term_language( $terms[0]->term_id, 'slug' ) : $language;
			if ( $language === $term_language ) {
				$term_url = get_term_link( $terms[0] );
				if ( ! is_wp_error( $term_url ) ) {
					wp_safe_redirect( $term_url, 301, 'PILLOWAY Catalog Bridge' );
					exit;
				}
			}
		}
	}
}
add_action( 'template_redirect', 'pilloway_catalog_redirect_legacy_prom_urls', 0 );
require_once __DIR__ . '/includes/brand-landing-cards.php';
require_once __DIR__ . '/includes/home-reviews.php';
require_once __DIR__ . '/includes/product-card-surfaces.php';
require_once __DIR__ . '/includes/media-fallback.php';

require_once __DIR__ . '/includes/home-featured-products.php';
require_once __DIR__ . '/includes/header-category-menu.php';
require_once __DIR__ . '/includes/prom-sku-display.php';

require_once __DIR__ . '/includes/seo-meta.php';

/**
 * Make synchronized Gutenberg patterns translatable with Polylang.
 *
 * Footer content remains editable in WordPress as reusable blocks. The current
 * language's linked block is rendered from its Polylang translation set.
 */
function pilloway_catalog_polylang_translatable_block_types( $post_types, $is_settings ) {
	$post_types   = (array) $post_types;
	$post_types[] = 'wp_block';
	return array_values( array_unique( $post_types ) );
}
add_filter( 'pll_get_post_types', 'pilloway_catalog_polylang_translatable_block_types', 10, 2 );

/**
 * Render a translated synchronized block only when explicitly marked.
 *
 * Missing translations intentionally render nothing; the source language is
 * never used as a fallback on another language's page.
 */
function pilloway_catalog_render_polylang_synchronized_block( $pre_render, $parsed_block, $parent_block ) {
	unset( $parent_block );

	if ( is_admin() || ! is_array( $parsed_block ) || 'core/block' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $pre_render;
	}

	$attributes = (array) ( $parsed_block['attrs'] ?? array() );
	$classes    = ' ' . (string) ( $attributes['className'] ?? '' ) . ' ';
	if ( false === strpos( $classes, ' pilloway-polylang-content ' ) ) {
		return $pre_render;
	}

	if ( ! function_exists( 'pll_current_language' ) || ! function_exists( 'pll_get_post' ) ) {
		return '';
	}

	$reference_id = absint( $attributes['ref'] ?? 0 );
	$language     = pll_current_language( 'slug' );
	if ( ! $reference_id || ! is_string( $language ) || '' === $language ) {
		return '';
	}

	$translation_id = absint( pll_get_post( $reference_id, $language ) );
	if ( ! $translation_id ) {
		return '';
	}

	$translated_block = get_post( $translation_id );
	if ( ! $translated_block instanceof WP_Post || 'wp_block' !== $translated_block->post_type || 'publish' !== $translated_block->post_status ) {
		return '';
	}

	return do_shortcode( do_blocks( $translated_block->post_content ) );
}
add_filter( 'pre_render_block', 'pilloway_catalog_render_polylang_synchronized_block', 10, 3 );



/**
 * Put individual Beauty Balance pillows first in the sleep-wrinkle category.
 * The per-product rank is stored in _pilloway_sleep_wrinkle_order and is set
 * for both the Ukrainian category and its Russian translation.
 */
function pilloway_catalog_order_sleep_wrinkle_category( array $args, string $orderby, string $order ): array {
    unset( $order );

    if (
        'menu_order' !== $orderby
        || ! function_exists( 'is_product_category' )
        || ! is_product_category( function_exists('pll_get_term_translations') ? array_values(pll_get_term_translations(47)) : array(47) )
    ) {
        return $args;
    }

    $args['orderby']  = 'meta_value_num';
    $args['order']    = 'ASC';
    $args['meta_key'] = '_pilloway_sleep_wrinkle_order';

    return $args;
}
add_filter( 'woocommerce_get_catalog_ordering_args', 'pilloway_catalog_order_sleep_wrinkle_category', 20, 3 );

/** Provide the current year to editable WordPress shortcode blocks. */
function pilloway_catalog_current_year_shortcode(): string {
	return esc_html( wp_date( 'Y' ) );
}
add_shortcode( 'pilloway_current_year', 'pilloway_catalog_current_year_shortcode' );
