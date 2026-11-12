<?php
/**
 * Facet definitions — the shop owner's view of the filter.
 *
 * A facet is one block in the sidebar: a source (a product attribute taxonomy,
 * any other product taxonomy, or a meta key), a widget, and a set of values with
 * their own labels, numeric value and swatch.
 *
 * Why the plugin keeps its own copy of the values instead of reading terms live:
 * a value needs things a term does not carry — a number for range filtering
 * (`1.5 мм` sorts before `10 мм`), a merge target so `12 В`, `12в` and `12 V`
 * become one checkbox, and a stable sort. All of that is decided once, at index
 * time, instead of on every request.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Core;

defined( 'ABSPATH' ) || exit;

class Facets {

	public const WIDGETS = array( 'checkbox', 'radio', 'select', 'range', 'swatch_color', 'swatch_image', 'toggle' );

	/**
	 * Term slugs a merge swallowed, per facet: [facet_id => [term slug => value_id]].
	 *
	 * A merge deletes the source value, but the products keep their own term. Without
	 * this map the next save of such a product would drop it from the merged value,
	 * and the automatic value sync would bring the swallowed value straight back.
	 */
	public const ALIASES_OPTION = 'catcode_sf_value_aliases';

	/**
	 * @return array<int,object>
	 */
	public static function all( bool $only_active = false ): array {
		global $wpdb;

		$table = Repository::facets_table();
		$where = $only_active ? 'WHERE status = 1' : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		return (array) $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, label ASC, facet_id ASC" );
	}

	public static function get( int $facet_id ): ?object {
		global $wpdb;

		$table = Repository::facets_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE facet_id = %d", $facet_id ) );

		return $row ?: null;
	}

	public static function by_key( string $facet_key ): ?object {
		global $wpdb;

		$table = Repository::facets_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE facet_key = %s", $facet_key ) );

		return $row ?: null;
	}

	/**
	 * Active facets with their values, ready for the widget.
	 *
	 * @return array<int,object>
	 */
	public static function active_with_values(): array {
		$facets = self::all( true );

		if ( ! $facets ) {
			return array();
		}

		$ids = wp_list_pluck( $facets, 'facet_id' );

		$values = self::values_for( array_map( 'intval', $ids ) );

		foreach ( $facets as $facet ) {
			$facet->values = isset( $values[ (int) $facet->facet_id ] ) ? $values[ (int) $facet->facet_id ] : array();
		}

		return $facets;
	}

	/**
	 * @param array<int,int> $facet_ids
	 * @return array<int,array<int,object>>
	 */
	public static function values_for( array $facet_ids ): array {
		global $wpdb;

		$facet_ids = array_values( array_filter( array_map( 'intval', $facet_ids ) ) );

		if ( ! $facet_ids ) {
			return array();
		}

		$table = Repository::values_table();
		$in    = implode( ',', $facet_ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = (array) $wpdb->get_results(
			"SELECT * FROM {$table} WHERE facet_id IN ({$in}) ORDER BY sort_order ASC, num_value ASC, label ASC"
		);

		$out = array();

		foreach ( $rows as $row ) {
			$out[ (int) $row->facet_id ][] = $row;
		}

		return $out;
	}

	/**
	 * @return array<int,object>
	 */
	public static function values( int $facet_id ): array {
		$all = self::values_for( array( $facet_id ) );

		return isset( $all[ $facet_id ] ) ? $all[ $facet_id ] : array();
	}

	/**
	 * Create or update a facet. The key is what appears in the URL, so it is
	 * derived from the source once and then left alone — changing it later would
	 * break every link already indexed by Google.
	 */
	public static function save( array $data ): int {
		global $wpdb;

		$table = Repository::facets_table();

		$facet_id = isset( $data['facet_id'] ) ? (int) $data['facet_id'] : 0;

		$fields = array(
			'facet_key'     => self::clean_key( (string) ( $data['facet_key'] ?? '' ) ),
			'source'        => in_array( ( $data['source'] ?? '' ), array( Settings::SRC_ATTRIBUTE, Settings::SRC_TAXONOMY, Settings::SRC_META ), true ) ? $data['source'] : Settings::SRC_ATTRIBUTE,
			'source_key'    => sanitize_text_field( (string) ( $data['source_key'] ?? '' ) ),
			'label'         => sanitize_text_field( (string) ( $data['label'] ?? '' ) ),
			'unit'          => sanitize_text_field( (string) ( $data['unit'] ?? '' ) ),
			'hint'          => sanitize_text_field( (string) ( $data['hint'] ?? '' ) ),
			'widget'        => in_array( ( $data['widget'] ?? '' ), self::WIDGETS, true ) ? $data['widget'] : 'checkbox',
			'logic'         => 'and' === ( $data['logic'] ?? 'or' ) ? 'and' : 'or',
			'decimals'      => (int) ( $data['decimals'] ?? 0 ),
			'collapsed'     => empty( $data['collapsed'] ) ? 0 : 1,
			'visible_limit' => (int) ( $data['visible_limit'] ?? 0 ),
			'search_box'    => empty( $data['search_box'] ) ? 0 : 1,
			'sort_order'    => (int) ( $data['sort_order'] ?? 0 ),
			'status'        => empty( $data['status'] ) ? 0 : 1,
		);

		if ( '' === $fields['facet_key'] ) {
			$fields['facet_key'] = self::clean_key( $fields['source_key'] ?: $fields['label'] );
		}

		if ( $facet_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $fields, array( 'facet_id' => $facet_id ) );

			Repository::bump_version();

			return $facet_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $table, $fields );

		Repository::bump_version();

		return (int) $wpdb->insert_id;
	}

	public static function delete( int $facet_id ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Repository::index_table(), array( 'facet_id' => $facet_id ) );
		$wpdb->delete( Repository::values_table(), array( 'facet_id' => $facet_id ) );
		$wpdb->delete( Repository::facets_table(), array( 'facet_id' => $facet_id ) );
		// phpcs:enable

		$aliases = self::all_aliases();

		if ( isset( $aliases[ $facet_id ] ) ) {
			unset( $aliases[ $facet_id ] );
			update_option( self::ALIASES_OPTION, $aliases, false );
		}

		Repository::bump_version();
	}

	/**
	 * @return array<int,array<string,int>>
	 */
	private static function all_aliases(): array {
		$stored = get_option( self::ALIASES_OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Swallowed term slugs of one facet and the value each of them now lands on.
	 *
	 * @return array<string,int>
	 */
	public static function aliases( int $facet_id ): array {
		$all = self::all_aliases();

		return isset( $all[ $facet_id ] ) && is_array( $all[ $facet_id ] ) ? array_map( 'intval', $all[ $facet_id ] ) : array();
	}

	/** One value row by id. */
	public static function get_value( int $value_id ): ?object {
		global $wpdb;

		$table = Repository::values_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE value_id = %d", $value_id ) );

		return $row ?: null;
	}

	/**
	 * Add or update one value. Values imported from a taxonomy carry their source
	 * value (the term slug), so a second import edits the same row instead of
	 * adding a duplicate.
	 */
	public static function save_value( int $facet_id, array $data ): int {
		global $wpdb;

		$table = Repository::values_table();

		$slug = self::clean_slug( (string) ( $data['value_slug'] ?? ( $data['source_value'] ?? $data['label'] ?? '' ) ) );

		if ( '' === $slug ) {
			return 0;
		}

		$fields = array(
			'facet_id'     => $facet_id,
			'value_slug'   => $slug,
			// Not sanitize_text_field(): it strips `%xx` octets, and source_value holds
			// the raw term slug WordPress stored — which for a Cyrillic term is nothing
			// but `%xx` octets. Sanitising it leaves an empty string, the index builder
			// then matches no term relationship at all, and the filter silently returns
			// an empty catalogue.
			'source_value' => self::raw_value( (string) ( $data['source_value'] ?? '' ) ),
			'label'        => sanitize_text_field( (string) ( $data['label'] ?? '' ) ),
			'num_value'    => ( isset( $data['num_value'] ) && '' !== $data['num_value'] && null !== $data['num_value'] ) ? (float) $data['num_value'] : null,
			'color'        => sanitize_text_field( (string) ( $data['color'] ?? '' ) ),
			'image_id'     => (int) ( $data['image_id'] ?? 0 ),
			'sort_order'   => (int) ( $data['sort_order'] ?? 0 ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT value_id FROM {$table} WHERE facet_id = %d AND value_slug = %s", $facet_id, $slug )
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $fields, array( 'value_id' => $existing ) );

			Repository::bump_version();

			return $existing;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $table, $fields );

		Repository::bump_version();

		return (int) $wpdb->insert_id;
	}

	/** The source value as the platform stored it, only stripped of markup. */
	public static function raw_value( string $raw ): string {
		$out = trim( wp_strip_all_tags( $raw ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $out, 0, 190 ) : substr( $out, 0, 190 );
	}

	/**
	 * A URL-safe value slug that stays readable in Cyrillic.
	 *
	 * sanitize_title() is the obvious call here and the wrong one: it percent-encodes
	 * every non-Latin character, so `зелений` becomes `%d0%b7%d0%b5…` and the filter
	 * link a shopper copies out of the address bar is forty characters of noise. The
	 * URL only needs the separators and whitespace gone — letters of any script are
	 * legal in a query string and every browser renders them as letters.
	 */
	public static function clean_slug( string $raw ): string {
		$slug = rawurldecode( trim( $raw ) );

		if ( function_exists( 'mb_strtolower' ) ) {
			$slug = mb_strtolower( $slug, 'UTF-8' );
		} else {
			$slug = strtolower( $slug );
		}

		$slug = preg_replace( '/[\s_]+/u', '-', $slug );
		$slug = preg_replace( '/[^\p{L}\p{N}\-\.]+/u', '', (string) $slug );
		$slug = trim( preg_replace( '/-{2,}/', '-', (string) $slug ), '-' );

		return function_exists( 'mb_substr' ) ? mb_substr( $slug, 0, 190 ) : substr( $slug, 0, 190 );
	}

	public static function delete_value( int $value_id ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Repository::index_table(), array( 'value_id' => $value_id ) );
		$wpdb->delete( Repository::values_table(), array( 'value_id' => $value_id ) );
		// phpcs:enable

		Repository::bump_version();
	}

	/**
	 * Merge one value into another: index rows move, then the source dies.
	 *
	 * This is the answer to the mess every attribute-driven filter shows — `12 В`,
	 * `12в` and `12 V` become one checkbox while the products keep their own terms.
	 * IGNORE matters: a product that already carries the target value would
	 * otherwise collide on the primary key.
	 */
	public static function merge_values( int $from, int $into ): void {
		global $wpdb;

		if ( $from === $into || ! $from || ! $into ) {
			return;
		}

		$source = self::get_value( $from );
		$target = self::get_value( $into );

		if ( $source && $target && (int) $source->facet_id === (int) $target->facet_id ) {
			$facet_id = (int) $source->facet_id;
			$all      = self::all_aliases();
			$map      = isset( $all[ $facet_id ] ) && is_array( $all[ $facet_id ] ) ? $all[ $facet_id ] : array();

			// Slugs that already landed on the dying value follow it to the target.
			foreach ( $map as $slug => $value_id ) {
				if ( (int) $value_id === $from ) {
					$map[ $slug ] = $into;
				}
			}

			foreach ( array_unique( array_filter( array( (string) $source->source_value, (string) $source->value_slug ) ) ) as $slug ) {
				$map[ $slug ] = $into;
			}

			$all[ $facet_id ] = $map;
			update_option( self::ALIASES_OPTION, $all, false );

			Indexer::flush_maps();
		}

		$index = Repository::index_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$wpdb->query( $wpdb->prepare( "UPDATE IGNORE {$index} SET value_id = %d WHERE value_id = %d", $into, $from ) );

		self::delete_value( $from );
	}

	public static function clean_key( string $key ): string {
		$key = strtolower( trim( $key ) );
		$key = preg_replace( '/[^a-z0-9_\-]+/', '-', $key );
		$key = trim( (string) $key, '-' );

		return substr( $key, 0, 64 );
	}

	/**
	 * The number hidden inside a label, so `1.5 мм` ranges and sorts as 1.5.
	 */
	public static function numeric( string $text ): ?float {
		$text = str_replace( ',', '.', self::normalize( $text ) );

		if ( preg_match( '/-?\d+(\.\d+)?/', $text, $match ) ) {
			return (float) $match[0];
		}

		return null;
	}

	/**
	 * The key that decides whether two labels are the same value: lower-cased,
	 * whitespace collapsed, NBSP folded, decimal comma turned into a dot.
	 */
	public static function normalize( string $text ): string {
		$text = wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
		$text = str_replace( array( "\xC2\xA0", "\xE2\x80\x89", "\xE2\x80\xAF" ), ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( (string) $text );
		$text = preg_replace( '/(\d),(\d)/u', '$1.$2', $text );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
	}
}
