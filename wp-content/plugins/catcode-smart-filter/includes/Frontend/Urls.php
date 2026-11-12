<?php
/**
 * Links, and what search engines are told about them.
 *
 * A filter is the classic way to wreck a shop's crawl budget: every combination
 * is a URL, every URL is a near-duplicate of the archive, and Google will happily
 * crawl a few hundred thousand of them. So a filtered view carries
 * `noindex,follow` and a canonical back to the clean archive, and only the views
 * the owner deliberately built a landing page for are indexable.
 *
 * Every link is built from the same sorted, canonical selection string, so one
 * state has exactly one URL — a filter that can spell the same page four ways
 * hands Google four duplicates of it.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Frontend;

use CatCode\SmartFilter\Core\Params;
use CatCode\SmartFilter\Core\Query;
use CatCode\SmartFilter\Core\Runtime;
use CatCode\SmartFilter\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Urls {

	/** Query args that describe the listing and must survive a filter click. */
	private const KEEP = array( 'orderby', 'order', 'per_page', 's', 'post_type', 'product_cat', 'product_tag', 'lang' );

	/**
	 * The archive URL without any filter state.
	 */
	public static function base(): string {
		$scope = Runtime::scope();

		if ( ! empty( $scope['taxonomy'] ) && ! empty( $scope['term_id'] ) ) {
			$link = get_term_link( (int) $scope['term_id'], (string) $scope['taxonomy'] );

			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}

		if ( ! empty( $scope['search'] ) ) {
			return add_query_arg(
				array(
					's'         => rawurlencode( (string) $scope['search'] ),
					'post_type' => 'product',
				),
				home_url( '/' )
			);
		}

		// In this local WordPress build the Woo shop page itself is a plain page;
		// the reliable product loop is the product archive query. Keep filter links
		// on that archive instead of combining a page path with post_type=product.
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$lang = Query::current_language();
			$home = self::language_home( $lang );
			return add_query_arg( 'post_type', 'product', $home );
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop = wc_get_page_permalink( 'shop' );

			if ( $shop ) {
				return $shop;
			}
		}

		return home_url( '/' );
	}

	/**
	 * @param array<string,array<int,string>> $selection
	 * @param array<string,mixed>             $overrides
	 */
	public static function link( array $selection, array $overrides = array() ): string {
		$url = self::base();

		$args = array();

		foreach ( self::KEEP as $key ) {
			if ( 'post_type' === $key && function_exists( 'is_shop' ) && is_shop() ) {
				continue;
			}
			if ( 'lang' === $key && self::uses_language_directory() ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only listing state.
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		foreach ( $overrides as $key => $value ) {
			if ( null === $value ) {
				unset( $args[ $key ] );
			} else {
				$args[ $key ] = $value;
			}
		}

		$encoded = Params::encode( $selection );

		if ( '' !== $encoded ) {
			$args[ Settings::PARAM ] = $encoded;
		}

		if ( ! $args ) {
			return $url;
		}

		// The separators stay literal on purpose: `:` `,` `;` are legal in a query
		// string, and percent-encoding them makes every filter link unreadable in
		// Search Console for no benefit.
		$query = array();

		foreach ( $args as $key => $value ) {
			$query[] = rawurlencode( (string) $key ) . '=' . str_replace(
				array( '%3A', '%2C', '%3B' ),
				array( ':', ',', ';' ),
				rawurlencode( (string) $value )
			);
		}

		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $query );
	}

	private static function uses_language_directory(): bool {
		$options = get_option( 'polylang', array() );
		return is_array( $options ) && ! empty( $options['rewrite'] ) && '' !== (string) get_option( 'permalink_structure' );
	}

	private static function language_home( string $lang ): string {
		$options = get_option( 'polylang', array() );
		$default = function_exists( 'pll_default_language' ) ? pll_default_language( 'slug' ) : '';
		if ( self::uses_language_directory() && '' !== $lang && ( $lang !== $default || empty( $options['hide_default'] ) ) ) {
			return trailingslashit( home_url( '/' . rawurlencode( $lang ) . '/' ) );
		}
		return function_exists( 'pll_home_url' ) && '' !== $lang ? pll_home_url( $lang ) : home_url( '/' );
	}

	/**
	 * The URL that results from clicking one value.
	 *
	 * A filter click always returns to page one: page 3 of the old result set is
	 * meaningless once the set changed, and landing on an empty page is the single
	 * most common complaint about filters that keep the page number.
	 */
	public static function toggle( array $selection, string $key, string $value ): string {
		return self::link( Params::toggle( $selection, $key, $value ), array( 'paged' => null ) );
	}

	public static function remove( array $selection, string $key, ?string $value = null ): string {
		return self::link( Params::without( $selection, $key, $value ), array( 'paged' => null ) );
	}

	public static function clear(): string {
		return self::link( array(), array( 'paged' => null ) );
	}

	/**
	 * `archive` (the default) points every filtered view at the clean archive: one
	 * page collects the authority instead of a thousand near-duplicates. `self` is
	 * for shops that build filter landing pages on purpose.
	 */
	public static function canonical( array $selection, bool $has_landing_page = false ): string {
		if ( ! $selection ) {
			return self::base();
		}

		if ( $has_landing_page || 'self' === Settings::get( 'canonical_mode' ) ) {
			return self::link( $selection, array( 'paged' => null, 'orderby' => null, 'order' => null ) );
		}

		return self::base();
	}

	public static function robots( array $selection, bool $has_landing_page = false ): string {
		if ( ! $selection ) {
			return '';
		}

		if ( $has_landing_page || Params::depth( $selection ) <= 1 ) {
			return (string) Settings::get( 'robots_single', 'index,follow' );
		}

		return (string) Settings::get( 'robots_multi', 'noindex,follow' );
	}
}
