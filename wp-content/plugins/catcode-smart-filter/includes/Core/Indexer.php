<?php
/**
 * Discovery, import and the index itself.
 *
 * Two ways in: a full rebuild batched over AJAX and resumable from a cursor, and
 * a single product on save so the index never drifts. The cursor is a product id
 * rather than an OFFSET — a shop that adds or deletes a product mid-run would
 * otherwise skip or double-count rows around the seam.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Core;

defined( 'ABSPATH' ) || exit;

class Indexer {

	/** @var array<int,array<string,array{value_id:int,num_value:float|null}>> value lookup per facet */
	private static $maps = array();

	/** @var array<int,bool> facets whose values were synced with their taxonomy in this request */
	private static $synced = array();

	/** @var array<int,array{slug:string,name:string}> term state captured right before an edit */
	private static $before_edit = array();

	/**
	 * What the catalogue offers to filter by, with the number of products behind
	 * each source. The count is what makes the import honest: the owner sees
	 * "Діаметр — 412 товарів" instead of a bare list, so dead attributes never end
	 * up in the sidebar.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function discover(): array {
		$out = array(
			Settings::SRC_ATTRIBUTE => array(),
			Settings::SRC_TAXONOMY  => array(),
		);

		$existing = array();

		foreach ( Facets::all() as $facet ) {
			$existing[ $facet->source . ':' . $facet->source_key ] = true;
		}

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( wc_get_attribute_taxonomies() as $attribute ) {
				$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$out[ Settings::SRC_ATTRIBUTE ][] = array(
					'source_key' => $taxonomy,
					'label'      => $attribute->attribute_label,
					'terms'      => (int) wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ),
					'products'   => self::products_in_taxonomy( $taxonomy ),
					'imported'   => isset( $existing[ Settings::SRC_ATTRIBUTE . ':' . $taxonomy ] ),
				);
			}
		}

		foreach ( array( 'product_cat', 'product_tag', 'product_brand' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$object = get_taxonomy( $taxonomy );

			$out[ Settings::SRC_TAXONOMY ][] = array(
				'source_key' => $taxonomy,
				'label'      => $object && isset( $object->labels->singular_name ) ? $object->labels->singular_name : $taxonomy,
				'terms'      => (int) wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ),
				'products'   => self::products_in_taxonomy( $taxonomy ),
				'imported'   => isset( $existing[ Settings::SRC_TAXONOMY . ':' . $taxonomy ] ),
			);
		}

		usort(
			$out[ Settings::SRC_ATTRIBUTE ],
			static function ( $a, $b ) {
				return $b['products'] <=> $a['products'];
			}
		);

		return $out;
	}

	private static function products_in_taxonomy( string $taxonomy ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT tr.object_id)
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'product' AND p.post_status = 'publish'
					WHERE tt.taxonomy = %s",
				$taxonomy
			)
		);
	}

	/**
	 * Create (or refresh) a facet from a taxonomy and fill in its values.
	 *
	 * A re-import is a data refresh, not a reset: the owner's widget, logic, sort
	 * order and merged values survive it.
	 *
	 * @return array{facet_id:int,values:int,created:bool}
	 */
	public static function import_taxonomy( string $source, string $taxonomy, string $widget = 'checkbox' ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array(
				'facet_id' => 0,
				'values'   => 0,
				'created'  => false,
			);
		}

		$existing = null;

		foreach ( Facets::all() as $facet ) {
			if ( $facet->source === $source && $facet->source_key === $taxonomy ) {
				$existing = $facet;
				break;
			}
		}

		$object = get_taxonomy( $taxonomy );
		$label  = $object && isset( $object->labels->singular_name ) ? $object->labels->singular_name : $taxonomy;

		if ( 0 === strpos( $taxonomy, 'pa_' ) && function_exists( 'wc_attribute_label' ) ) {
			$label = wc_attribute_label( $taxonomy );
		}

		$data = array(
			'facet_id'      => $existing ? (int) $existing->facet_id : 0,
			'facet_key'     => $existing ? $existing->facet_key : Facets::clean_key( $taxonomy ),
			'source'        => $source,
			'source_key'    => $taxonomy,
			'label'         => $existing ? $existing->label : $label,
			'widget'        => $existing ? $existing->widget : $widget,
			'logic'         => $existing ? $existing->logic : 'or',
			'decimals'      => $existing ? (int) $existing->decimals : 0,
			'collapsed'     => $existing ? (int) $existing->collapsed : 0,
			'visible_limit' => $existing ? (int) $existing->visible_limit : 0,
			'search_box'    => $existing ? (int) $existing->search_box : 0,
			'sort_order'    => $existing ? (int) $existing->sort_order : 0,
			'status'        => $existing ? (int) $existing->status : 1,
			'unit'          => $existing ? $existing->unit : '',
			'hint'          => $existing ? $existing->hint : '',
		);

		$facet_id = Facets::save( $data );

		// A re-import only adds what the catalogue gained since the last one. The
		// values already here keep the owner's labels, swatches and order — writing
		// the whole term list over them again would throw that work away.
		if ( $existing ) {
			$facet = Facets::get( $facet_id );

			return array(
				'facet_id' => $facet_id,
				'values'   => $facet ? self::sync_values( $facet ) : 0,
				'created'  => false,
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array(
				'facet_id' => $facet_id,
				'values'   => 0,
				'created'  => ! $existing,
			);
		}

		$rows = array();

		foreach ( $terms as $term ) {
			// WordPress percent-encodes a non-Latin term slug, so `Зелений` is stored
			// as `%d0%b7%d0%b5…`. Kept as-is it would travel through the URL as the
			// unreadable `%25d0%25b7…`, so the facet value carries the decoded form
			// and the raw term slug stays in source_value — which is what the index
			// builder matches term relationships against.
			$rows[] = array(
				'value_slug'   => rawurldecode( $term->slug ),
				'source_value' => $term->slug,
				'label'        => $term->name,
				'num_value'    => Facets::numeric( $term->name ),
			);
		}

		$rows = self::sort_values( $rows );

		$saved = 0;

		foreach ( $rows as $row ) {
			if ( Facets::save_value( $facet_id, $row ) ) {
				++$saved;
			}
		}

		return array(
			'facet_id' => $facet_id,
			'values'   => $saved,
			'created'  => ! $existing,
		);
	}

	/**
	 * Add the terms a facet does not know yet — a colour created after the facet
	 * was imported, a brand added last week. Existing values are left exactly as
	 * the owner set them, and a slug a merge swallowed stays merged.
	 *
	 * @return int number of values added
	 */
	public static function sync_values( object $facet ): int {
		$facet_id = (int) $facet->facet_id;

		self::$synced[ $facet_id ] = true;

		if ( ! in_array( $facet->source, array( Settings::SRC_ATTRIBUTE, Settings::SRC_TAXONOMY ), true ) || ! taxonomy_exists( (string) $facet->source_key ) ) {
			return 0;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => (string) $facet->source_key,
				'hide_empty' => false,
				// A single facet index stores values for every Polylang term slug;
				// the archive language chooses which translated product IDs are counted.
				'lang'       => '',
			)
		);

		if ( is_wp_error( $terms ) || ! $terms ) {
			return 0;
		}

		$known   = Facets::aliases( $facet_id );
		$slugs   = array();
		$current = array();

		foreach ( Facets::values( $facet_id ) as $value ) {
			$known[ (string) $value->source_value ] = (int) $value->value_id;
			$slugs[ (string) $value->value_slug ]   = true;
			$current[]                              = $value;
		}

		$rows = array();

		foreach ( $terms as $term ) {
			$rows[] = array(
				'value_slug'   => rawurldecode( $term->slug ),
				'source_value' => $term->slug,
				'label'        => $term->name,
				'num_value'    => Facets::numeric( $term->name ),
			);
		}

		$rows = self::sort_values( $rows );

		// If the owner never reordered the values by hand (their order is still the
		// automatic one), a new value takes its natural place and the list is
		// renumbered. A hand-made order is left alone and new values go last.
		$position = array();

		foreach ( $rows as $row ) {
			$position[ $row['source_value'] ] = (int) $row['sort_order'];
		}

		$natural = true;
		$last    = -1;
		$max     = -1;

		foreach ( $current as $value ) {
			$max = max( $max, (int) $value->sort_order );

			if ( ! isset( $position[ (string) $value->source_value ] ) || $position[ (string) $value->source_value ] < $last ) {
				$natural = false;
			} else {
				$last = $position[ (string) $value->source_value ];
			}
		}

		$added = 0;
		$fresh = array();

		foreach ( $rows as $row ) {
			if ( isset( $known[ $row['source_value'] ] ) || isset( $known[ $row['value_slug'] ] ) ) {
				continue;
			}

			$fresh[] = $row;
		}

		if ( ! $fresh ) {
			return 0;
		}

		if ( $natural ) {
			global $wpdb;

			foreach ( $current as $value ) {
				$order = $position[ (string) $value->source_value ];

				if ( (int) $value->sort_order !== $order ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->update( Repository::values_table(), array( 'sort_order' => $order ), array( 'value_id' => (int) $value->value_id ) );
				}
			}
		}

		foreach ( $fresh as $row ) {
			if ( ! $natural ) {
				$row['sort_order'] = ++$max;
			}

			$slug = Facets::clean_slug( $row['value_slug'] );

			if ( '' === $slug || isset( $slugs[ $slug ] ) ) {
				continue;
			}

			if ( Facets::save_value( $facet_id, $row ) ) {
				$slugs[ $slug ] = true;
				++$added;
			}
		}

		if ( $added ) {
			unset( self::$maps[ $facet_id ] );
		}

		return $added;
	}

	/** Forget the cached value lookups — values or merge aliases just changed. */
	public static function flush_maps(): void {
		self::$maps = array();
	}

	/**
	 * Sync every term-based facet. Runs before a full rebuild, so the button the
	 * owner presses when "something is missing" actually brings it in.
	 */
	public static function sync_all(): int {
		$added = 0;

		foreach ( Facets::all() as $facet ) {
			$added += self::sync_values( $facet );
		}

		return $added;
	}

	/** Facets built on the given taxonomy. */
	private static function facets_for_taxonomy( string $taxonomy ): array {
		$out = array();

		foreach ( Facets::all() as $facet ) {
			if ( in_array( $facet->source, array( Settings::SRC_ATTRIBUTE, Settings::SRC_TAXONOMY ), true ) && (string) $facet->source_key === $taxonomy ) {
				$out[] = $facet;
			}
		}

		return $out;
	}

	/**
	 * `created_term`: a new term of a filtered taxonomy becomes a value at once.
	 *
	 * @param int    $term_id
	 * @param int    $tt_id
	 * @param string $taxonomy
	 */
	public static function on_term_created( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		try {
			foreach ( self::facets_for_taxonomy( (string) $taxonomy ) as $facet ) {
				self::sync_values( $facet );
			}
		} catch ( \Throwable $e ) {
			self::log( 'term sync failed: ' . $e->getMessage() );
		}
	}

	/**
	 * `edit_terms` fires before the row changes — the only moment the old slug is
	 * still readable.
	 *
	 * @param int    $term_id
	 * @param string $taxonomy
	 */
	public static function before_term_edit( $term_id, $taxonomy = '' ): void {
		if ( ! self::facets_for_taxonomy( (string) $taxonomy ) ) {
			return;
		}

		$term = get_term( (int) $term_id, (string) $taxonomy );

		if ( $term && ! is_wp_error( $term ) ) {
			self::$before_edit[ (int) $term_id ] = array(
				'slug' => (string) $term->slug,
				'name' => (string) $term->name,
			);
		}
	}

	/**
	 * `edited_term`: follow a renamed term. The value keeps its id and its URL
	 * slug (links already shared keep working); it only learns the new term slug,
	 * and the new name unless the owner had already relabelled it by hand.
	 *
	 * @param int    $term_id
	 * @param int    $tt_id
	 * @param string $taxonomy
	 */
	public static function on_term_edited( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		global $wpdb;

		try {
			$facets = self::facets_for_taxonomy( (string) $taxonomy );
			$term   = get_term( (int) $term_id, (string) $taxonomy );
			$before = self::$before_edit[ (int) $term_id ] ?? null;

			unset( self::$before_edit[ (int) $term_id ] );

			if ( ! $facets || ! $term || is_wp_error( $term ) ) {
				return;
			}

			$table = Repository::values_table();

			foreach ( $facets as $facet ) {
				$facet_id = (int) $facet->facet_id;
				$value    = null;

				if ( $before ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
					$value = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE facet_id = %d AND source_value = %s", $facet_id, $before['slug'] ) );
				}

				if ( $value ) {
					$fields = array( 'source_value' => Facets::raw_value( (string) $term->slug ) );

					if ( (string) $value->label === $before['name'] && $before['name'] !== (string) $term->name ) {
						$fields['label']     = sanitize_text_field( (string) $term->name );
						$fields['num_value'] = Facets::numeric( (string) $term->name );
					}

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->update( $table, $fields, array( 'value_id' => (int) $value->value_id ) );

					unset( self::$maps[ $facet_id ] );
					Repository::bump_version();

					continue;
				}

				self::sync_values( $facet );
			}
		} catch ( \Throwable $e ) {
			self::log( 'term sync failed: ' . $e->getMessage() );
		}
	}

	private static function log( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'catcode-smart-filter' ) );
		}
	}

	/**
	 * Numeric values first, in numeric order; text after, alphabetically.
	 *
	 * This is the difference between `1.5 / 2 / 10 / 12.5` and the alphabetical
	 * `1.5 / 10 / 12.5 / 2` that every term-sorted filter shows.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<int,array<string,mixed>>
	 */
	private static function sort_values( array $rows ): array {
		usort(
			$rows,
			static function ( array $a, array $b ) {
				$na = $a['num_value'];
				$nb = $b['num_value'];

				if ( null !== $na && null !== $nb && $na !== $nb ) {
					return $na < $nb ? -1 : 1;
				}

				if ( null !== $na && null === $nb ) {
					return -1;
				}

				if ( null === $na && null !== $nb ) {
					return 1;
				}

				return strcmp( Facets::normalize( (string) $a['label'] ), Facets::normalize( (string) $b['label'] ) );
			}
		);

		$order = 0;

		foreach ( $rows as &$row ) {
			$row['sort_order'] = $order++;
		}

		unset( $row );

		return $rows;
	}

	// -----------------------------------------------------------------------
	// Build
	// -----------------------------------------------------------------------

	public static function truncate(): void {
		global $wpdb;

		$table = Repository::index_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Index one batch of products, resuming after `$cursor`.
	 *
	 * @return array{cursor:int,processed:int,rows:int,done:bool}
	 */
	public static function build_batch( int $cursor, int $limit ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'product' AND post_status = 'publish' AND ID > %d
					ORDER BY ID ASC LIMIT %d",
				$cursor,
				$limit
			)
		);

		$ids = array_map( 'intval', $ids );

		if ( ! $ids ) {
			return array(
				'cursor'    => $cursor,
				'processed' => 0,
				'rows'      => 0,
				'done'      => true,
			);
		}

		$rows = self::index_products( $ids );

		return array(
			'cursor'    => (int) end( $ids ),
			'processed' => count( $ids ),
			'rows'      => $rows,
			'done'      => count( $ids ) < $limit,
		);
	}

	public static function index_product( int $product_id ): int {
		return self::index_products( array( $product_id ) );
	}

	public static function delete_product( int $product_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Repository::index_table(), array( 'product_id' => $product_id ) );
	}

	/**
	 * The whole write path. Rows for the given products are replaced, never
	 * merged: a value the owner removed from a product has to disappear from the
	 * sidebar in the same request, otherwise the filter promises products it
	 * cannot deliver.
	 */
	private static function index_products( array $product_ids ): int {
		global $wpdb;

		$product_ids = array_values( array_unique( array_map( 'intval', $product_ids ) ) );

		if ( ! $product_ids ) {
			return 0;
		}

		$facets = Facets::all( true );

		if ( ! $facets ) {
			return 0;
		}

		$index   = Repository::index_table();
		$id_list = implode( ',', $product_ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$wpdb->query( "DELETE FROM {$index} WHERE product_id IN ({$id_list})" );

		$rows = array();

		foreach ( $facets as $facet ) {
			if ( ! in_array( $facet->source, array( Settings::SRC_ATTRIBUTE, Settings::SRC_TAXONOMY ), true ) ) {
				continue;
			}

			$map = self::value_map( (int) $facet->facet_id );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$assigned = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tr.object_id AS product_id, t.slug AS slug
						FROM {$wpdb->term_relationships} tr
						INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
						INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
						WHERE tt.taxonomy = %s AND tr.object_id IN ({$id_list})",
					$facet->source_key
				)
			);

			foreach ( $assigned as $row ) {
				$slug = (string) $row->slug;

				// A term the facet has never seen (created by an importer that skips
				// the term hooks, or before this version was installed): pick it up
				// now, once per facet per request, instead of silently leaving the
				// product out of the filter.
				if ( ! isset( $map[ $slug ] ) && empty( self::$synced[ (int) $facet->facet_id ] ) ) {
					if ( self::sync_values( $facet ) ) {
						$map = self::value_map( (int) $facet->facet_id );
					}
				}

				if ( ! isset( $map[ $slug ] ) ) {
					continue;
				}

				$rows[] = array(
					(int) $row->product_id,
					(int) $facet->facet_id,
					(int) $map[ $slug ]['value_id'],
					$map[ $slug ]['num_value'],
				);
			}
		}

		return self::insert_rows( $rows );
	}

	/**
	 * @return array<string,array{value_id:int,num_value:float|null}>
	 */
	private static function value_map( int $facet_id ): array {
		if ( isset( self::$maps[ $facet_id ] ) ) {
			return self::$maps[ $facet_id ];
		}

		$map  = array();
		$byid = array();

		foreach ( Facets::values( $facet_id ) as $value ) {
			$slug = (string) $value->value_slug;

			$entry = array(
				'value_id'  => (int) $value->value_id,
				'num_value' => ( null === $value->num_value ) ? null : (float) $value->num_value,
			);

			$map[ $slug ] = $entry;

			$byid[ (int) $value->value_id ] = $entry;

			// A merged value keeps answering under the term slug it swallowed, so
			// products still land on it without touching the catalogue.
			$source = (string) $value->source_value;

			if ( '' !== $source && ! isset( $map[ $source ] ) ) {
				$map[ $source ] = $entry;
			}
		}

		// Term slugs swallowed by a merge land on the value they were merged into.
		foreach ( Facets::aliases( $facet_id ) as $source => $value_id ) {
			if ( isset( $byid[ $value_id ] ) && ! isset( $map[ (string) $source ] ) ) {
				$map[ (string) $source ] = $byid[ $value_id ];
			}
		}

		self::$maps[ $facet_id ] = $map;

		return $map;
	}

	private static function insert_rows( array $rows ): int {
		global $wpdb;

		if ( ! $rows ) {
			return 0;
		}

		$index   = Repository::index_table();
		$written = 0;

		foreach ( array_chunk( $rows, 500 ) as $chunk ) {
			$values = array();

			foreach ( $chunk as $row ) {
				list( $product_id, $facet_id, $value_id, $num ) = $row;

				if ( null === $num ) {
					$values[] = $wpdb->prepare( '(%d,%d,%d,NULL)', $product_id, $facet_id, $value_id );
				} else {
					$values[] = $wpdb->prepare( '(%d,%d,%d,%f)', $product_id, $facet_id, $value_id, $num );
				}
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- table name is ours, every row prepared above.
			$wpdb->query(
				"INSERT IGNORE INTO {$index} (product_id, facet_id, value_id, num_value) VALUES " . implode( ',', $values )
			);
			// phpcs:enable

			$written += count( $chunk );
		}

		return $written;
	}

	/**
	 * Index rows whose product or value no longer exists. A direct SQL import
	 * bypasses every hook, so this sweep is what keeps counters from quoting
	 * ghosts.
	 */
	public static function prune_orphans(): int {
		global $wpdb;

		$before = Repository::index_rows();
		$index  = Repository::index_table();
		$values = Repository::values_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$wpdb->query(
			"DELETE i FROM {$index} i
				LEFT JOIN {$wpdb->posts} p ON p.ID = i.product_id AND p.post_type = 'product' AND p.post_status = 'publish'
				WHERE p.ID IS NULL"
		);

		$wpdb->query(
			"DELETE i FROM {$index} i
				LEFT JOIN {$values} v ON v.value_id = i.value_id
				WHERE v.value_id IS NULL"
		);
		// phpcs:enable

		return max( 0, $before - Repository::index_rows() );
	}

	/**
	 * Values that carry no products at all — dead weight in the sidebar.
	 *
	 * @return array<int,object>
	 */
	public static function empty_values(): array {
		global $wpdb;

		$values = Repository::values_table();
		$index  = Repository::index_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		return (array) $wpdb->get_results(
			"SELECT v.value_id, v.facet_id, v.label
				FROM {$values} v
				LEFT JOIN {$index} i ON i.value_id = v.value_id
				WHERE i.value_id IS NULL"
		);
	}
}
