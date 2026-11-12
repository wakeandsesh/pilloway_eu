<?php
/**
 * WooCommerce pages per language: shop, cart, checkout, my account and terms
 * resolve to their translation, the translated shop page lists products, the
 * language switcher links shop ↔ shop, and WooCommerce's own AJAX endpoint
 * (cart fragments, classic checkout) carries the shopper's language.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Front;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class Pages {

	public const PAGES = array( 'shop', 'cart', 'checkout', 'myaccount', 'terms' );

	public function register(): void {
		foreach ( self::PAGES as $page ) {
			add_filter( 'woocommerce_get_' . $page . '_page_id', array( $this, 'page_id' ), 20 );
		}
		add_action( 'pre_get_posts', array( $this, 'shop_query' ), 5 );
		add_filter( 'post_type_archive_link', array( $this, 'archive_link' ), 20, 2 );
		add_filter( 'pll_translation_url', array( $this, 'switcher_url' ), 20, 2 );
		add_filter( 'woocommerce_ajax_get_endpoint', array( $this, 'ajax_endpoint' ), 20, 2 );
	}

	/**
	 * @param int|string $id Page ID from the WooCommerce settings.
	 * @return int|string
	 */
	public function page_id( $id ) {
		// The product post type takes its archive slug from the shop page while it is
		// registered — that must stay the default page, or the rewrite rules would follow
		// whichever language happened to flush them.
		if ( ! did_action( 'woocommerce_after_register_post_type' ) ) {
			return $id;
		}
		$lang = Lang::current();
		if ( '' === $lang || (int) $id <= 0 ) {
			return $id;
		}
		$tr = Lang::post_in( (int) $id, $lang );
		if ( $tr > 0 && $tr !== (int) $id && 'publish' === get_post_status( $tr ) ) {
			return $tr;
		}
		return $id;
	}

	/**
	 * The translated shop page is a page for WordPress — turn its main query into
	 * the product archive, the way WooCommerce does for the default shop page.
	 *
	 * @param \WP_Query $q Query.
	 */
	public function shop_query( $q ): void {
		if ( is_admin() || ! $q instanceof \WP_Query || ! $q->is_main_query() || ! $q->is_page() || ! Lang::ready() ) {
			return;
		}
		$shop = (int) get_option( 'woocommerce_shop_page_id' );
		if ( $shop <= 0 ) {
			return;
		}
		$page_id = (int) $q->get( 'page_id' );
		if ( $page_id <= 0 && '' !== (string) $q->get( 'pagename' ) ) {
			$page    = get_page_by_path( (string) $q->get( 'pagename' ) );
			$page_id = $page instanceof \WP_Post ? (int) $page->ID : 0;
		}
		if ( $page_id <= 0 || $page_id === $shop ) {
			return;
		}
		if ( ! in_array( $page_id, Lang::post_translations( $shop ), true ) ) {
			return;
		}
		// A shop set as the static front page is handled by WooCommerce itself.
		if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $page_id ) {
			return;
		}

		$q->set( 'post_type', 'product' );
		$q->set( 'page_id', '' );
		$q->set( 'pagename', '' );
		if ( isset( $q->query['paged'] ) ) {
			$q->set( 'paged', $q->query['paged'] );
		}
		$lang = Lang::post_lang( $page_id );
		if ( '' !== $lang && '' === (string) $q->get( 'lang' ) ) {
			$q->set( 'lang', $lang );
		}
		$q->is_page              = false;
		$q->is_singular          = false;
		$q->is_post_type_archive = true;
		$q->is_archive           = true;
		$q->queried_object       = null;
		$q->queried_object_id    = null;
	}

	/**
	 * @param string $link      Archive link.
	 * @param string $post_type Post type.
	 */
	public function archive_link( $link, $post_type ) {
		if ( 'product' !== $post_type || '' === Lang::current() || ! function_exists( 'wc_get_page_id' ) || self::plain_permalinks() ) {
			return $link;
		}
		$page = (int) wc_get_page_id( 'shop' );
		if ( $page <= 0 || ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $page ) ) {
			return $link;
		}
		$url = get_permalink( $page );
		return $url ? $url : $link;
	}

	/**
	 * Language switcher on the shop: link to the shop page of that language.
	 *
	 * @param string $url  Translation URL Polylang computed.
	 * @param string $lang Target language.
	 */
	public function switcher_url( $url, $lang ) {
		if ( ! function_exists( 'is_shop' ) || ! is_shop() || is_search() || self::plain_permalinks() ) {
			return $url;
		}
		$shop = (int) get_option( 'woocommerce_shop_page_id' );
		$tr   = Lang::post_in( $shop, (string) $lang );
		if ( $tr <= 0 || 'publish' !== get_post_status( $tr ) ) {
			return $url;
		}
		$link = get_permalink( $tr );
		return $link ? $link : $url;
	}

	/**
	 * Plain permalinks: WooCommerce itself sends ?page_id=<shop page> to the product archive
	 * (?post_type=product), which Polylang keeps in the language (&lang=…). Pointing the archive
	 * back at the translated shop page there would send the shopper round in a redirect loop.
	 */
	private static function plain_permalinks(): bool {
		return '' === (string) get_option( 'permalink_structure' );
	}

	/**
	 * /?wc-ajax=… carries no language, so Polylang would answer those requests in the
	 * default language (product names in the checkout review, shipping and payment
	 * titles, and the order language). Point them at the language's home instead.
	 *
	 * @param string $url     Endpoint URL.
	 * @param string $request Endpoint name (or the %%endpoint%% placeholder).
	 */
	public function ajax_endpoint( $url, $request ) {
		unset( $request );
		$lang = Lang::current();
		if ( '' === $lang || is_admin() || ! function_exists( 'pll_home_url' ) ) {
			return $url;
		}
		$home = (string) pll_home_url( $lang );
		if ( '' === $home ) {
			return $url;
		}
		$query = (string) wp_parse_url( (string) $url, PHP_URL_QUERY );
		return $home . ( false === strpos( $home, '?' ) ? '?' : '&' ) . $query;
	}
}
