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
