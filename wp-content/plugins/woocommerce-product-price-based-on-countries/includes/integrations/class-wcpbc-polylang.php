<?php
/**
 * Handle compatibility with Polylang
 *
 * @package WCPBC
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Polylang Class
 */
class WCPBC_Polylang implements WCPBC_Multilang_Interface {

	use WCPBC_Multilang_Trait;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	protected function __construct() {
		add_action( 'pll_copy_post_metas', [ $this, 'copy_metas' ], 10, 4 );
		add_action( 'pll_save_post', [ $this, 'pll_save_post' ], 20, 2 );
	}

	/**
	 * Returns the translation post IDs for the give post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	protected function get_translations( $post_id ) {
		$translation_post_ids = [];

		if ( function_exists( 'pll_get_post_translations' ) ) {

			$translation_post_ids = pll_get_post_translations( $post_id );
			$translation_post_ids = array_diff( $translation_post_ids, [ $post_id ] ); // Just remove this post from the list.
		}

		return $translation_post_ids;
	}

	/**
	 * Copy metas.
	 *
	 * @param string[] $keys List of custom fields names.
	 * @param bool     $sync True if it is synchronization, false if it is a copy.
	 * @param int      $from Id of the post from which we copy information.
	 * @param int      $to   Id of the post to which we paste information.
	 */
	public function copy_metas( $keys, $sync, $from, $to ) {
		if ( in_array( get_post_type( $from ), [ 'product', 'product_variation' ], true ) && ! $sync ) {
			foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
				$this->sync_metadata( $from, [ $to ], $zone );
			}
		}
		return $keys;
	}

	/**
	 * Enqueues a product for multilang price sync after PLL Saved the post.
	 *
	 * @param int     $post_id  Post id.
	 * @param WP_Post $post     Post object.
	 */
	public function pll_save_post( $post_id, $post ) {
		if ( ! in_array( get_post_type( $post ), [ 'product', 'product_variation' ], true ) ) {
			return;
		}

		$this->enqueue( $post_id );
	}
}
return WCPBC_Polylang::instance();
