<?php
/**
 * Activation, deactivation and the hooks that keep the index honest.
 *
 * One product save re-indexes exactly that product and bumps the catalogue
 * version, which retires every cached counter answer without deleting a single
 * transient. The alternative — a nightly cron, or a "rebuild index" button the
 * owner has to remember — is why so many filters quietly serve values that lead
 * to empty pages.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Core;

defined( 'ABSPATH' ) || exit;

class Installer {

	public static function activate(): void {
		Repository::install();

		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', true );
		}

		if ( false === get_option( 'catcode_sf_index_version' ) ) {
			add_option( 'catcode_sf_index_version', 1, '', true );
		}

		update_option( 'catcode_sf_version', CATCODE_SF_VERSION, false );
	}

	/**
	 * Deactivation stops the hooks but keeps the data: facets, merged values and
	 * landing pages take an afternoon to curate, and deactivating a plugin by
	 * accident must not throw that away. Everything goes on uninstall, which is an
	 * explicit act.
	 */
	public static function deactivate(): void {
		Settings::flush_cache();
	}

	public static function init(): void {
		add_action( 'save_post_product', array( __CLASS__, 'on_product_save' ), 20, 3 );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product_update' ), 20 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'on_product_update' ), 20 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_product_delete' ), 20 );
		add_action( 'wp_trash_post', array( __CLASS__, 'on_product_delete' ), 20 );

		// A term added to a filtered attribute after its facet was imported has to
		// show up in the filter without the owner re-importing anything.
		add_action( 'created_term', array( Indexer::class, 'on_term_created' ), 20, 3 );
		add_action( 'edit_terms', array( Indexer::class, 'before_term_edit' ), 20, 2 );
		add_action( 'edited_term', array( Indexer::class, 'on_term_edited' ), 20, 3 );

		// Stock moves without a product save: an order takes the last "L", a
		// cancelled order or a refund puts it back, the owner edits one variation,
		// a CSV import rewrites a hundred of them. Each of these lands here, and the
		// product is re-indexed once at the end of the request.
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_stock_object' ), 20 );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_stock_object' ), 20 );
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_stock_status' ), 20 );
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_stock_status' ), 20 );
		add_action( 'woocommerce_new_product_variation', array( __CLASS__, 'on_stock_status' ), 20 );
		add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'on_stock_status' ), 20 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 20, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 20, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta' ), 20, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 20, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_variation_gone' ), 20, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_variation_gone' ), 20 );
		add_action( 'untrashed_post', array( __CLASS__, 'on_variation_gone' ), 20 );

		add_action( Indexer::REBUILD_HOOK, array( Indexer::class, 'rebuild_tick' ) );

		// Action Scheduler, which carries the background rebuild, is ready from init.
		add_action( 'init', array( Repository::class, 'maybe_upgrade' ), 20 );
	}

	/**
	 * @param \WC_Product $product
	 */
	public static function on_stock_object( $product ): void {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			Indexer::queue_post( (int) $product->get_id() );
		}
	}

	/**
	 * @param int $product_id
	 */
	public static function on_stock_status( $product_id ): void {
		Indexer::queue_post( (int) $product_id );
	}

	/**
	 * Direct meta writes — bulk editors, sync plugins, a stock feed — never call
	 * the WooCommerce actions above, but they all end in update_post_meta().
	 *
	 * @param int|array $meta_id
	 * @param int       $object_id
	 * @param string    $meta_key
	 */
	public static function on_meta( $meta_id, $object_id, $meta_key ): void {
		$meta_key = (string) $meta_key;

		if ( '_stock_status' !== $meta_key && '_price' !== $meta_key && 0 !== strpos( $meta_key, 'attribute_' ) ) {
			return;
		}

		if ( 'product_variation' === get_post_type( (int) $object_id ) ) {
			Indexer::queue_post( (int) $object_id );
		}
	}

	/**
	 * A variation switched on or off in the product editor.
	 *
	 * @param string   $new_status
	 * @param string   $old_status
	 * @param \WP_Post $post
	 */
	public static function on_status( $new_status, $old_status, $post ): void {
		if ( $new_status !== $old_status && $post instanceof \WP_Post && 'product_variation' === $post->post_type ) {
			Indexer::queue( (int) $post->post_parent );
		}
	}

	/**
	 * @param int           $post_id
	 * @param \WP_Post|null $post
	 */
	public static function on_variation_gone( $post_id, $post = null ): void {
		$post = $post instanceof \WP_Post ? $post : get_post( (int) $post_id );

		if ( $post && 'product_variation' === $post->post_type ) {
			Indexer::queue( (int) $post->post_parent );
		}
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 * @param bool     $update
	 */
	public static function on_product_save( $post_id, $post = null, $update = false ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		self::on_product_update( (int) $post_id );
	}

	public static function on_product_update( $product_id ): void {
		if ( ! Settings::is_on( 'reindex_on_save' ) ) {
			return;
		}

		$product_id = (int) $product_id;

		if ( $product_id < 1 || 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		// A failed re-index must never block the save the merchandiser just made:
		// the product is in the catalogue either way, and a full rebuild repairs
		// the index.
		try {
			Indexer::index_product( $product_id );
			Repository::bump_version();
		} catch ( \Throwable $e ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'ccf reindex on save failed: ' . $e->getMessage(), array( 'source' => 'catcode-smart-filter' ) );
			}
		}
	}

	public static function on_product_delete( $post_id ): void {
		$post_id = (int) $post_id;

		if ( $post_id < 1 || 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		try {
			Indexer::delete_product( $post_id );
			Repository::bump_version();
		} catch ( \Throwable $e ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'ccf reindex on delete failed: ' . $e->getMessage(), array( 'source' => 'catcode-smart-filter' ) );
			}
		}
	}
}
