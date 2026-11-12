<?php
/**
 * Product screens: Polylang's "+" (add translation) next to a product creates
 * a full copy in that language instead of an empty product, and the edit screen
 * of a translated product says which data is shared with the other languages.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Admin;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Product\Fields;
use CatCode\PolylangShop\Product\Translator;

defined( 'ABSPATH' ) || exit;

class ProductAdmin {

	public const ACTION = 'catcode_plms_translate';

	public function register(): void {
		add_filter( 'pll_get_new_post_translation_link', array( $this, 'new_translation_link' ), 20, 3 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'edit_form_top', array( $this, 'shared_notice' ) );
	}

	public static function link( int $product_id, string $lang ): string {
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'product'  => $product_id,
				'lang'     => $lang,
				'_wpnonce' => wp_create_nonce( self::ACTION . '_' . $product_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * @param string        $link     Polylang's link (post-new.php?from_post=…).
	 * @param \PLL_Language $language Target language.
	 * @param int           $post_id  Source post.
	 */
	public function new_translation_link( $link, $language, $post_id ) {
		if ( 'product' !== get_post_type( (int) $post_id ) || ! is_object( $language ) || empty( $language->slug ) ) {
			return $link;
		}
		return self::link( (int) $post_id, (string) $language->slug );
	}

	public function handle(): void {
		$product_id = isset( $_GET['product'] ) ? absint( wp_unslash( $_GET['product'] ) ) : 0;
		check_admin_referer( self::ACTION . '_' . $product_id );
		if ( $product_id <= 0 || ! current_user_can( 'edit_post', $product_id ) || ! current_user_can( 'edit_products' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) );
		}
		$lang   = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
		$result = Translator::translate( $product_id, $lang );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'back_link' => true ) );
		}
		wp_safe_redirect( add_query_arg( 'catcode_plms_created', '1', (string) get_edit_post_link( (int) $result, 'url' ) ) );
		exit;
	}

	/**
	 * @param \WP_Post $post Post being edited.
	 */
	public function shared_notice( $post ): void {
		if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type || ! Lang::ready() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		if ( isset( $_GET['catcode_plms_created'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Translation created as a copy of the original: prices, stock, SKU, images, categories, attributes and variations are already in place. Translate the name, descriptions and custom attribute values, then publish.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p></div>';
		}
		$others = Lang::other_translations( (int) $post->ID );
		if ( empty( $others ) ) {
			return;
		}
		$labels = Fields::groups();
		$shared = array();
		foreach ( Fields::active() as $group ) {
			if ( isset( $labels[ $group ] ) ) {
				$shared[] = $labels[ $group ];
			}
		}
		$names = array_map( array( Lang::class, 'name' ), array_keys( $others ) );
		echo '<div class="notice notice-info"><p>' . esc_html(
			sprintf(
				/* translators: 1: list of languages, 2: list of shared data. */
				__( 'This product is also sold in: %1$s. Shared with those translations (a change here updates them too): %2$s. Texts stay per language.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
				implode( ', ', $names ),
				implode( '; ', $shared )
			)
		) . '</p></div>';
	}
}
