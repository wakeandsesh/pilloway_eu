<?php
/**
 * Admin screen: settings, facets, index and diagnostics.
 *
 * Everything the owner does here goes through admin-ajax with a nonce and a
 * capability check; the page itself only renders state.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Admin;

use CatCode\SmartFilter\Core\Facets;
use CatCode\SmartFilter\Core\Indexer;
use CatCode\SmartFilter\Core\Params;
use CatCode\SmartFilter\Core\Query;
use CatCode\SmartFilter\Core\Repository;
use CatCode\SmartFilter\Core\Settings;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	public const SLUG = 'catcode-smart-filter';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		foreach ( array( 'save', 'facets', 'save_facets', 'delete_facet', 'merge_values', 'discover', 'import', 'index_start', 'index_step', 'prune', 'diagnostics', 'drop_data' ) as $action ) {
			add_action( 'wp_ajax_catcode_sf_' . $action, array( __CLASS__, 'route' ) );
		}
	}

	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Smart Filter', 'catcode-smart-filter' ),
			__( 'Smart Filter', 'catcode-smart-filter' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'catcode-sf-admin', CATCODE_SF_URL . 'assets/css/admin.css', array(), Settings::asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'catcode-sf-admin', CATCODE_SF_URL . 'assets/js/admin.js', array(), Settings::asset_version( 'assets/js/admin.js' ), true );

		wp_localize_script(
			'catcode-sf-admin',
			'catcodeSfAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'catcode_sf_admin' ),
				'i18n'  => array(
					'saved'    => __( 'Saved.', 'catcode-smart-filter' ),
					'failed'   => __( 'The request failed.', 'catcode-smart-filter' ),
					'indexing' => __( 'Indexing…', 'catcode-smart-filter' ),
					'done'     => __( 'Done.', 'catcode-smart-filter' ),
					'valueCol' => __( 'Values', 'catcode-smart-filter' ),
					'merge'    => __( 'Merge into…', 'catcode-smart-filter' ),
					'products' => __( 'products', 'catcode-smart-filter' ),
					'confirm'  => __( 'Delete all filter data?', 'catcode-smart-filter' ),
					'diag'     => array(
						'index_rows'   => __( 'Rows in the index', 'catcode-smart-filter' ),
						'products'     => __( 'Products', 'catcode-smart-filter' ),
						'facets'       => __( 'Facets', 'catcode-smart-filter' ),
						'orphans'      => __( 'Orphaned rows', 'catcode-smart-filter' ),
						'unindexed'    => __( 'Products missing from the index', 'catcode-smart-filter' ),
						'empty_values' => __( 'Values with no products', 'catcode-smart-filter' ),
						'lookup_table' => __( 'WooCommerce lookup table', 'catcode-smart-filter' ),
						'version'      => __( 'Catalogue version', 'catcode-smart-filter' ),
						'ok'           => __( 'ok', 'catcode-smart-filter' ),
						'missing'      => __( 'MISSING', 'catcode-smart-filter' ),
					),
				),
			)
		);
	}

	public static function render(): void {
		$settings = Settings::all();

		$stats = array(
			'facets'   => count( Facets::all() ),
			'rows'     => Repository::index_rows(),
			'products' => Repository::total_products(),
			'built_at' => (string) Settings::get( 'index_built_at', '' ),
		);

		include CATCODE_SF_DIR . 'templates/admin.php';
	}

	// -----------------------------------------------------------------------
	// AJAX
	// -----------------------------------------------------------------------

	public static function route(): void {
		check_ajax_referer( 'catcode_sf_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'catcode-smart-filter' ) ), 403 );
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$method = str_replace( 'catcode_sf_', 'do_', $action );

		if ( ! method_exists( __CLASS__, $method ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown action.', 'catcode-smart-filter' ) ), 400 );
		}

		call_user_func( array( __CLASS__, $method ) );
	}

	private static function payload(): array {
		$raw = file_get_contents( 'php://input' );

		if ( is_string( $raw ) && '' !== $raw && 0 === strpos( ltrim( $raw ), '{' ) ) {
			$decoded = json_decode( $raw, true );

			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in route().
	}

	private static function do_save(): void {
		$posted = self::payload();

		$values = array();

		foreach ( Settings::defaults() as $key => $default ) {
			if ( in_array( $key, Settings::server_owned(), true ) ) {
				continue; // index bookkeeping is not part of the form
			}

			if ( ! array_key_exists( $key, $posted ) ) {
				continue;
			}

			$value = $posted[ $key ];

			$values[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $default;
		}

		Settings::set( $values );

		// Settings decide what the counters mean, so cached answers cannot survive
		// a save.
		Repository::bump_version();

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'catcode-smart-filter' ) ) );
	}

	private static function do_facets(): void {
		$out = array();

		foreach ( Facets::active_with_values() as $facet ) {
			$values = array();

			foreach ( $facet->values as $value ) {
				$values[] = array(
					'value_id'   => (int) $value->value_id,
					'label'      => (string) $value->label,
					'slug'       => (string) $value->value_slug,
					'num_value'  => null === $value->num_value ? '' : (string) $value->num_value,
					'color'      => (string) $value->color,
					'sort_order' => (int) $value->sort_order,
				);
			}

			$out[] = array(
				'facet_id'      => (int) $facet->facet_id,
				'facet_key'     => (string) $facet->facet_key,
				'label'         => (string) $facet->label,
				'source'        => (string) $facet->source,
				'source_key'    => (string) $facet->source_key,
				'widget'        => (string) $facet->widget,
				'logic'         => (string) $facet->logic,
				'sort_order'    => (int) $facet->sort_order,
				'status'        => (int) $facet->status,
				'visible_limit' => (int) $facet->visible_limit,
				'values'        => $values,
			);
		}

		wp_send_json_success( array( 'facets' => $out ) );
	}

	private static function do_save_facets(): void {
		$payload = self::payload();

		$saved = 0;

		foreach ( (array) ( $payload['facets'] ?? array() ) as $row ) {
			$facet_id = (int) ( $row['facet_id'] ?? 0 );

			if ( $facet_id < 1 ) {
				continue;
			}

			$existing = Facets::get( $facet_id );

			if ( ! $existing ) {
				continue;
			}

			Facets::save(
				array(
					'facet_id'      => $facet_id,
					'facet_key'     => $existing->facet_key,
					'source'        => $existing->source,
					'source_key'    => $existing->source_key,
					'label'         => $row['label'] ?? $existing->label,
					'unit'          => $row['unit'] ?? $existing->unit,
					'hint'          => $existing->hint,
					'widget'        => $row['widget'] ?? $existing->widget,
					'logic'         => $row['logic'] ?? $existing->logic,
					'decimals'      => $row['decimals'] ?? $existing->decimals,
					'collapsed'     => $row['collapsed'] ?? $existing->collapsed,
					'visible_limit' => $row['visible_limit'] ?? $existing->visible_limit,
					'search_box'    => $row['search_box'] ?? $existing->search_box,
					'sort_order'    => $row['sort_order'] ?? $existing->sort_order,
					'status'        => $row['status'] ?? $existing->status,
				)
			);

			foreach ( (array) ( $row['values'] ?? array() ) as $value ) {
				if ( empty( $value['value_id'] ) ) {
					continue;
				}

				Facets::save_value(
					$facet_id,
					array(
						'value_slug' => $value['slug'] ?? '',
						'label'      => $value['label'] ?? '',
						'num_value'  => $value['num_value'] ?? '',
						'color'      => $value['color'] ?? '',
						'sort_order' => $value['sort_order'] ?? 0,
					)
				);
			}

			++$saved;
		}

		Repository::bump_version();

		wp_send_json_success(
			array(
				/* translators: %d: number of facets saved. */
				'message' => sprintf( __( 'Facets saved: %d.', 'catcode-smart-filter' ), $saved ),
			)
		);
	}

	private static function do_delete_facet(): void {
		$payload = self::payload();

		$facet_id = (int) ( $payload['facet_id'] ?? 0 );

		if ( $facet_id > 0 ) {
			Facets::delete( $facet_id );
			Repository::bump_version();
		}

		wp_send_json_success( array( 'message' => __( 'Facet deleted.', 'catcode-smart-filter' ) ) );
	}

	private static function do_merge_values(): void {
		$payload = self::payload();

		Facets::merge_values( (int) ( $payload['from'] ?? 0 ), (int) ( $payload['into'] ?? 0 ) );

		Repository::bump_version();

		wp_send_json_success( array( 'message' => __( 'Values merged.', 'catcode-smart-filter' ) ) );
	}

	private static function do_discover(): void {
		wp_send_json_success( array( 'sources' => Indexer::discover() ) );
	}

	private static function do_import(): void {
		$payload = self::payload();

		$imported = 0;
		$values   = 0;

		foreach ( (array) ( $payload['sources'] ?? array() ) as $source ) {
			$result = Indexer::import_taxonomy(
				sanitize_key( (string) ( $source['source'] ?? 'attribute' ) ),
				sanitize_key( (string) ( $source['source_key'] ?? '' ) ),
				sanitize_key( (string) ( $source['widget'] ?? 'checkbox' ) )
			);

			if ( $result['facet_id'] ) {
				++$imported;
				$values += (int) $result['values'];
			}
		}

		Repository::bump_version();

		wp_send_json_success(
			array(
				/* translators: 1: number of sources, 2: number of values. */
				'message'  => sprintf( __( 'Imported sources: %1$d, values: %2$d.', 'catcode-smart-filter' ), $imported, $values ),
				'imported' => $imported,
				'values'   => $values,
			)
		);
	}

	private static function do_index_start(): void {
		// Terms created since the facets were imported become values first,
		// otherwise the rebuild would skip them exactly as before.
		$added = Indexer::sync_all();

		Indexer::truncate();

		wp_send_json_success(
			array(
				'added'  => $added,
				'cursor' => 0,
				'total'  => Repository::total_products(),
				'batch'  => Settings::batch_size(),
			)
		);
	}

	private static function do_index_step(): void {
		$payload = self::payload();

		$result = Indexer::build_batch( (int) ( $payload['cursor'] ?? 0 ), Settings::batch_size() );

		if ( $result['done'] ) {
			Settings::set(
				array(
					'index_built_at' => current_time( 'mysql' ),
					'index_rows'     => Repository::index_rows(),
				)
			);

			Repository::bump_version();
		}

		$result['rows_total'] = Repository::index_rows();
		$result['built_at']   = (string) Settings::get( 'index_built_at', '' );

		wp_send_json_success( $result );
	}

	private static function do_prune(): void {
		$removed = Indexer::prune_orphans();

		Repository::bump_version();

		wp_send_json_success(
			array(
				/* translators: %d: number of stale rows removed. */
				'message' => sprintf( __( 'Stale rows removed: %d.', 'catcode-smart-filter' ), $removed ),
				'empty'   => Indexer::empty_values(),
			)
		);
	}


	/**
	 * Self-check, because the two ways this plugin can fail are both invisible.
	 *
	 * If the query filter stops applying, the listing quietly returns everything;
	 * if the index is stale, the counters promise products that are not there.
	 * Both are checked against the live database rather than assumed.
	 */
	private static function do_diagnostics(): void {
		global $wpdb;

		$index = Repository::index_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$orphans = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$index} i
				LEFT JOIN {$wpdb->posts} p ON p.ID = i.product_id AND p.post_type = 'product' AND p.post_status = 'publish'
				WHERE p.ID IS NULL"
		);

		$unindexed = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
				LEFT JOIN {$index} i ON i.product_id = p.ID
				WHERE i.product_id IS NULL AND p.post_type = 'product' AND p.post_status = 'publish'"
		);
		// phpcs:enable

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$has_lookup = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $lookup ) );

		wp_send_json_success(
			array(
				'index_rows'   => Repository::index_rows(),
				'products'     => Repository::total_products(),
				'facets'       => count( Facets::all() ),
				'orphans'      => $orphans,
				'unindexed'    => $unindexed,
				'empty_values' => count( Indexer::empty_values() ),
				'lookup_table' => $has_lookup,
				'built_at'     => (string) Settings::get( 'index_built_at', '' ),
				'version'      => Repository::version(),
				'cache_ttl'    => Settings::cache_ttl(),
			)
		);
	}

	/** The one destructive action, never part of deactivation. */
	private static function do_drop_data(): void {
		global $wpdb;

		foreach ( array( Repository::index_table(), Repository::values_table(), Repository::facets_table() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}

		Repository::bump_version();

		wp_send_json_success( array( 'message' => __( 'Facets, values and the index have been deleted.', 'catcode-smart-filter' ) ) );
	}
}
