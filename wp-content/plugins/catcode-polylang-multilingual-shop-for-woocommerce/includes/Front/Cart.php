<?php
/**
 * The cart follows the shopper's language: after a language switch every line
 * points at the translation of its product (and variation, with translated
 * attribute values), so names, links and the future order are in that language.
 *
 * Block cart / checkout talk to the Store API (/wp-json/wc/store/…), which has no
 * language in its URL; a tiny apiFetch middleware adds ?lang= to those requests,
 * which Polylang reads for every REST request.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Front;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Product\Fields;
use CatCode\PolylangShop\Product\Variations;

defined( 'ABSPATH' ) || exit;

class Cart {

	/** Cart item keys WooCommerce derives from the product, not extra data from plugins. */
	private const CORE_KEYS = array( 'key', 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'data_hash', 'line_tax_data', 'line_subtotal', 'line_subtotal_tax', 'line_total', 'line_tax' );

	/** @var string Language the cart was last moved to in this request. */
	private $done = '';

	public function register(): void {
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'on_loaded' ), 5 );
		add_action( 'pll_language_defined', array( $this, 'on_language' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'store_api_language' ), 20 );
		add_action( 'pll_language_defined', array( $this, 'store_api_locale' ), 30 );
	}

	/**
	 * @param \WC_Cart $cart Cart.
	 */
	public function on_loaded( $cart ): void {
		if ( $cart instanceof \WC_Cart ) {
			$this->translate( $cart, false );
		}
	}

	/** Language decided after the cart was loaded ("language set from content" mode). */
	public function on_language(): void {
		if ( did_action( 'woocommerce_cart_loaded_from_session' ) && function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ) {
			$this->translate( WC()->cart, true );
		}
	}

	public function translate( \WC_Cart $cart, bool $recalculate ): void {
		$lang = Lang::current();
		if ( '' === $lang || $lang === $this->done ) {
			return;
		}
		$this->done = $lang;
		$contents   = $cart->get_cart_contents();
		if ( empty( $contents ) ) {
			return;
		}

		$changed = false;
		$out     = array();
		foreach ( $contents as $key => $item ) {
			$moved = $this->move_item( $item, $lang );
			if ( null === $moved ) {
				$out[ $key ] = $item;
				continue;
			}
			$changed  = true;
			$extra    = array_diff_key( $moved, array_flip( self::CORE_KEYS ) );
			$new_key  = $cart->generate_cart_id( $moved['product_id'], $moved['variation_id'], $moved['variation'], $extra );
			$moved['key'] = $new_key;
			if ( isset( $out[ $new_key ] ) ) {
				// The same product was in the cart in both languages: one line now.
				$out[ $new_key ]['quantity'] += $moved['quantity'];
				continue;
			}
			$out[ $new_key ] = $moved;
		}
		if ( ! $changed ) {
			return;
		}
		$cart->set_cart_contents( $out );
		if ( $recalculate || did_action( 'woocommerce_cart_loaded_from_session' ) ) {
			$cart->calculate_totals();
		}
	}

	/**
	 * @param array<string,mixed> $item Cart item.
	 * @return array<string,mixed>|null The item moved to $lang, or null when it stays.
	 */
	private function move_item( array $item, string $lang ): ?array {
		$product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
		$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
		if ( $product_id <= 0 ) {
			return null;
		}
		$own = Lang::post_lang( $product_id );
		if ( '' === $own || $own === $lang ) {
			return null;
		}
		$tr_product = Lang::post_in( $product_id, $lang );
		if ( $tr_product <= 0 || 'publish' !== get_post_status( $tr_product ) ) {
			return null;
		}
		$tr_variation = 0;
		if ( $variation_id > 0 ) {
			$tr_variation = Variations::in( $variation_id, $lang );
			if ( $tr_variation <= 0 ) {
				return null;
			}
		}
		$data = wc_get_product( $tr_variation > 0 ? $tr_variation : $tr_product );
		if ( ! $data || ! $data->is_purchasable() ) {
			return null;
		}

		$variation = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();
		if ( ! empty( $variation ) ) {
			$target    = $tr_variation > 0 ? (array) wc_get_product_variation_attributes( $tr_variation ) : array();
			$translated = array();
			foreach ( $variation as $name => $value ) {
				$taxonomy = Fields::attribute_taxonomy( (string) $name );
				if ( '' !== $taxonomy && '' !== (string) $value ) {
					$slug                 = \CatCode\PolylangShop\Product\Terms::translate_slug( (string) $value, $taxonomy, $lang, false );
					$translated[ $name ] = '' !== $slug ? $slug : $value;
				} elseif ( isset( $target[ $name ] ) && '' !== (string) $target[ $name ] ) {
					// A fixed custom value of the translated variation ("Rot" instead of "Red").
					$translated[ $name ] = $target[ $name ];
				} else {
					$translated[ $name ] = $value;
				}
			}
			$variation = $translated;
		}

		$item['product_id']   = $tr_product;
		$item['variation_id'] = $tr_variation;
		$item['variation']    = $variation;
		$item['data']         = $data;
		$item['data_hash']    = function_exists( 'wc_get_cart_item_data_hash' ) ? wc_get_cart_item_data_hash( $data ) : '';
		return $item;
	}

	/** Store API requests of the block cart / checkout / mini-cart carry the page language. */
	public function store_api_language(): void {
		$lang = Lang::current();
		if ( '' === $lang ) {
			return;
		}
		$script = '( function () {'
			. 'if ( ! window.wp || ! window.wp.apiFetch || ! window.wp.apiFetch.use ) { return; }'
			. 'const lang = ' . wp_json_encode( $lang ) . ';'
			. 'window.wp.apiFetch.use( function ( options, next ) {'
			. 'const key = options.path ? "path" : ( options.url ? "url" : "" );'
			. 'if ( key && options[ key ].indexOf( "wc/store" ) !== -1 && ! /[?&]lang=/.test( options[ key ] ) ) {'
			. 'const copy = Object.assign( {}, options );'
			. 'copy[ key ] = options[ key ] + ( options[ key ].indexOf( "?" ) === -1 ? "?" : "&" ) + "lang=" + encodeURIComponent( lang );'
			. 'return next( copy );'
			. '}'
			. 'return next( options );'
			. '} );'
			. '}() );';
		// Attached to WordPress's own apiFetch: it only runs on pages that load it (block cart, checkout, mini-cart).
		wp_add_inline_script( 'wp-api-fetch', $script, 'after' );
	}

	/**
	 * Polylang sets the language of a REST request from ?lang= but leaves gettext in the
	 * site language; Store API answers (notices, method names) should match the page.
	 */
	public function store_api_locale(): void {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || Lang::switched() ) {
			return;
		}
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		if ( 0 !== strpos( ltrim( $route, '/' ), 'wc/store' ) ) {
			return;
		}
		$locale = Lang::locale( Lang::current() );
		if ( '' !== $locale && determine_locale() !== $locale ) {
			switch_to_locale( $locale );
		}
	}
}
