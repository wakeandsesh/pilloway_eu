<?php
/**
 * Tables: facets, their values and the product index.
 *
 * The index is the whole point of this plugin. WooCommerce filters products by
 * walking taxonomy relationships and postmeta, which is why a shop feels fine at
 * 500 products and crawls at 20 000: every facet adds another join over
 * term_relationships, and every count is its own query. Here each product/value
 * pair is one row in one flat table with a numeric column alongside, so a facet
 * condition is an EXISTS on a covering index and a whole sidebar of counts is
 * two or three grouped queries.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Core;

defined( 'ABSPATH' ) || exit;

class Repository {

	public static function facets_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'catcode_sf_facets';
	}

	public static function values_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'catcode_sf_values';
	}

	public static function index_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'catcode_sf_index';
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$facets = self::facets_table();
		$values = self::values_table();
		$index  = self::index_table();

		dbDelta(
			"CREATE TABLE {$facets} (
				facet_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				facet_key VARCHAR(64) NOT NULL DEFAULT '',
				source VARCHAR(16) NOT NULL DEFAULT 'attribute',
				source_key VARCHAR(190) NOT NULL DEFAULT '',
				label VARCHAR(190) NOT NULL DEFAULT '',
				unit VARCHAR(32) NOT NULL DEFAULT '',
				hint VARCHAR(255) NOT NULL DEFAULT '',
				widget VARCHAR(16) NOT NULL DEFAULT 'checkbox',
				logic VARCHAR(4) NOT NULL DEFAULT 'or',
				decimals TINYINT(1) NOT NULL DEFAULT 0,
				collapsed TINYINT(1) NOT NULL DEFAULT 0,
				visible_limit INT NOT NULL DEFAULT 0,
				search_box TINYINT(1) NOT NULL DEFAULT 0,
				sort_order INT NOT NULL DEFAULT 0,
				status TINYINT(1) NOT NULL DEFAULT 1,
				PRIMARY KEY (facet_id),
				UNIQUE KEY facet_key (facet_key),
				KEY source (source, source_key(64)),
				KEY status_sort (status, sort_order)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$values} (
				value_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				facet_id BIGINT UNSIGNED NOT NULL,
				value_slug VARCHAR(190) NOT NULL DEFAULT '',
				source_value VARCHAR(190) NOT NULL DEFAULT '',
				label VARCHAR(190) NOT NULL DEFAULT '',
				num_value DECIMAL(15,4) NULL DEFAULT NULL,
				color VARCHAR(32) NOT NULL DEFAULT '',
				image_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				sort_order INT NOT NULL DEFAULT 0,
				PRIMARY KEY (value_id),
				UNIQUE KEY facet_value (facet_id, value_slug),
				KEY facet_sort (facet_id, sort_order),
				KEY source_value (facet_id, source_value(64))
			) {$charset};"
		);

		// The read path. The primary key makes a re-index idempotent; the two
		// secondary keys are what let a facet be counted without ever touching
		// wp_posts or the term tables.
		dbDelta(
			"CREATE TABLE {$index} (
				product_id BIGINT UNSIGNED NOT NULL,
				facet_id BIGINT UNSIGNED NOT NULL,
				value_id BIGINT UNSIGNED NOT NULL,
				num_value DECIMAL(15,4) NULL DEFAULT NULL,
				PRIMARY KEY (product_id, facet_id, value_id),
				KEY facet_value (facet_id, value_id),
				KEY facet_num (facet_id, num_value),
				KEY value_id (value_id)
			) {$charset};"
		);
	}

	public static function drop(): void {
		global $wpdb;

		foreach ( array( self::index_table(), self::values_table(), self::facets_table() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// Merge aliases point at value ids that no longer exist once the tables go.
		delete_option( Facets::ALIASES_OPTION );
	}

	/**
	 * Catalogue version — the number every cached counter answer hangs off.
	 *
	 * Counters are expensive and highly cacheable, but they go stale the moment a
	 * product changes. Flushing the whole cache on every save turns each edit into
	 * a stampede; bumping one integer that is part of the cache key retires the old
	 * answers instead and lets them expire on their own.
	 */
	public static function version(): int {
		return (int) get_option( 'catcode_sf_index_version', 1 );
	}

	public static function bump_version(): void {
		update_option( 'catcode_sf_index_version', self::version() + 1, true );
	}

	public static function index_rows(): int {
		global $wpdb;

		$table = self::index_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function total_products(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				'product',
				'publish'
			)
		);
	}
}
