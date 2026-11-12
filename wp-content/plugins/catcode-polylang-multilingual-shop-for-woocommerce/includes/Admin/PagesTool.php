<?php
/**
 * WooCommerce pages in every language. Polylang's own "+" creates an empty page,
 * and an empty checkout translation is a checkout that does not work — here the
 * missing translations are created as copies (the cart / checkout block or
 * shortcode included), published and linked, in one click.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Admin;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class PagesTool {

	public const ACTION = 'catcode_plms_pages';

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/** @return array<string,string> WooCommerce page key => label. */
	public static function pages(): array {
		return array(
			'shop'      => __( 'Shop', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'cart'      => __( 'Cart', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'checkout'  => __( 'Checkout', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'myaccount' => __( 'My account', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'terms'     => __( 'Terms and conditions', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
		);
	}

	/** Page set in WooCommerce → Settings → Advanced (never the translated one). */
	public static function page_id( string $key ): int {
		return (int) get_option( 'woocommerce_' . $key . '_page_id', 0 );
	}

	/**
	 * @return array<string,array<string,int>> page key => lang => page id (0 = missing).
	 */
	public static function status(): array {
		$out = array();
		foreach ( array_keys( self::pages() ) as $key ) {
			$id = self::page_id( $key );
			if ( $id <= 0 || ! get_post( $id ) ) {
				continue;
			}
			$row = array();
			foreach ( Lang::slugs() as $lang ) {
				$tr           = Lang::post_in( $id, $lang );
				$row[ $lang ] = ( $tr > 0 && 'trash' !== get_post_status( $tr ) ) ? $tr : 0;
			}
			if ( '' === Lang::post_lang( $id ) ) {
				// Not in any language yet — will be put in the default one.
				$row[ Lang::default() ] = $id;
			}
			$out[ $key ] = $row;
		}
		return $out;
	}

	public static function missing_count(): int {
		$n = 0;
		foreach ( self::status() as $row ) {
			foreach ( $row as $id ) {
				if ( $id <= 0 ) {
					++$n;
				}
			}
		}
		return $n;
	}

	/** @return int Number of pages created. */
	public static function create_missing(): int {
		if ( ! Lang::ready() || ! function_exists( 'pll_insert_post' ) ) {
			return 0;
		}
		$created = 0;
		foreach ( array_keys( self::pages() ) as $key ) {
			$id   = self::page_id( $key );
			$page = $id > 0 ? get_post( $id ) : null;
			if ( ! $page instanceof \WP_Post ) {
				continue;
			}
			if ( '' === Lang::post_lang( $id ) ) {
				pll_set_post_language( $id, Lang::default() );
			}
			foreach ( Lang::slugs() as $lang ) {
				if ( Lang::post_in( $id, $lang ) > 0 ) {
					continue;
				}
				$parent = 0;
				if ( $page->post_parent > 0 ) {
					$parent = Lang::post_in( (int) $page->post_parent, $lang );
				}
				$new = pll_insert_post(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'post_title'     => $page->post_title,
						'post_content'   => $page->post_content,
						'post_name'      => sanitize_title( $page->post_name . '-' . $lang ),
						'post_parent'    => $parent,
						'comment_status' => $page->comment_status,
						'menu_order'     => $page->menu_order,
					),
					$lang
				);
				if ( is_wp_error( $new ) || (int) $new <= 0 ) {
					continue;
				}
				Lang::link_post( $id, (int) $new, $lang );
				$template = get_post_meta( $id, '_wp_page_template', true );
				if ( is_string( $template ) && '' !== $template ) {
					update_post_meta( (int) $new, '_wp_page_template', $template );
				}
				++$created;
			}
		}
		return $created;
	}

	public function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) );
		}
		$created = self::create_missing();
		wp_safe_redirect( SettingsPage::url( array( 'catcode_plms_pages' => $created ) ) );
		exit;
	}
}
