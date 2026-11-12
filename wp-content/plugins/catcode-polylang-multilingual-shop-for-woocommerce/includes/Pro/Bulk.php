<?php
/**
 * Translate the whole catalogue into a language, in short AJAX steps:
 *   assign   — products / categories / tags / attribute terms with no language get the default one;
 *   terms    — categories, tags and attribute terms get their copy in the target language;
 *   products — every default-language product without a translation gets one (full copy).
 * Nothing that already has a translation is touched.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Pro;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Core\Registry;
use CatCode\PolylangShop\Product\Terms;
use CatCode\PolylangShop\Product\Translator;

defined( 'ABSPATH' ) || exit;

class Bulk {

	private const PRODUCTS_PER_STEP = 10;

	private const TERMS_PER_STEP = 100;

	/** Products without a language get one in batches of this size; the stage repeats until none is left. */
	private const ASSIGN_PER_STEP = 500;

	/**
	 * @return array<string,mixed> stage / offset of the next call, counters, done flag.
	 */
	public static function step( string $lang, string $stage, int $offset, string $status ): array {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- may be disabled on the host.
		}
		switch ( $stage ) {
			case 'terms':
				return self::terms( $lang, $offset );
			case 'products':
				return self::products( $lang, $offset, $status );
			default:
				return self::assign();
		}
	}

	/** @return array<string,mixed> */
	private static function assign(): array {
		$default = Lang::default();
		$fixed   = 0;
		$ids     = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page'   => self::ASSIGN_PER_STEP,
				'fields'           => 'ids',
				'lang'             => '',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- admin tool, runs on demand.
				'tax_query'        => array(
					array(
						'taxonomy' => 'language',
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			pll_set_post_language( (int) $id, $default );
			++$fixed;
		}
		if ( count( $ids ) >= self::ASSIGN_PER_STEP ) {
			// A catalogue imported before Polylang: more products wait, the next call takes them.
			return array(
				'stage'   => 'assign',
				'offset'  => 0,
				'done'    => false,
				'fixed'   => $fixed,
				'message' => sprintf(
					/* translators: %d: number of products and terms. */
					_n( '%d item without a language was put in the default language.', '%d items without a language were put in the default language.', $fixed, 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					$fixed
				),
			);
		}
		foreach ( Registry::bulk_taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
					'lang'       => '',
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term_id ) {
				if ( '' === Lang::term_lang( (int) $term_id ) ) {
					pll_set_term_language( (int) $term_id, $default );
					++$fixed;
				}
			}
		}
		return array(
			'stage'   => 'terms',
			'offset'  => 0,
			'done'    => false,
			'fixed'   => $fixed,
			'message' => sprintf(
				/* translators: %d: number of products and terms. */
				_n( '%d item without a language was put in the default language.', '%d items without a language were put in the default language.', $fixed, 'catcode-polylang-multilingual-shop-for-woocommerce' ),
				$fixed
			),
		);
	}

	/** @return array<string,mixed> */
	private static function terms( string $lang, int $offset ): array {
		$all = array();
		foreach ( Registry::bulk_taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
					'lang'       => Lang::default(),
					'orderby'    => 'term_id',
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term_id ) {
				$all[] = array( (int) $term_id, $taxonomy );
			}
		}
		$slice   = array_slice( $all, $offset, self::TERMS_PER_STEP );
		$created = 0;
		foreach ( $slice as $pair ) {
			if ( Lang::term_in( $pair[0], $lang ) > 0 ) {
				continue;
			}
			if ( Terms::translate_id( $pair[0], $pair[1], $lang, true ) > 0 ) {
				++$created;
			}
		}
		$next = $offset + count( $slice );
		$done = $next >= count( $all );
		return array(
			'stage'   => $done ? 'products' : 'terms',
			'offset'  => $done ? 0 : $next,
			'done'    => false,
			'created' => $created,
			'message' => sprintf(
				/* translators: 1: terms checked so far, 2: total terms. */
				__( 'Categories, tags and attribute terms: %1$d of %2$d checked.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
				$next,
				count( $all )
			),
		);
	}

	/** @return array<string,mixed> */
	private static function products( string $lang, int $offset, string $status ): array {
		$ids     = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'lang'             => Lang::default(),
				'suppress_filters' => false,
			)
		);
		$ids     = array_map( 'intval', $ids );
		$slice   = array_slice( $ids, $offset, self::PRODUCTS_PER_STEP );
		$created = 0;
		$errors  = array();
		foreach ( $slice as $id ) {
			if ( Lang::post_in( $id, $lang ) > 0 ) {
				continue;
			}
			$result = Translator::translate( $id, $lang, $status );
			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( '#%d: %s', $id, $result->get_error_message() );
			} else {
				++$created;
			}
		}
		$next = $offset + count( $slice );
		$done = $next >= count( $ids );
		return array(
			'stage'   => 'products',
			'offset'  => $next,
			'done'    => $done,
			'created' => $created,
			'errors'  => $errors,
			'message' => sprintf(
				/* translators: 1: products checked so far, 2: total products. */
				__( 'Products: %1$d of %2$d checked.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
				$next,
				count( $ids )
			),
		);
	}
}
