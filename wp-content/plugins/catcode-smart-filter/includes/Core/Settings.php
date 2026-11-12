<?php
/**
 * Settings repository — one option row holding every switch.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Core;

defined( 'ABSPATH' ) || exit;

class Settings {

	public const OPTION = 'catcode_sf_settings';

	/** GET parameter carrying the whole selection, e.g. ?ccf=pa_color:red,blue;p:100-900 */
	public const PARAM = 'ccf';

	/** Selection string separators. URL-safe and readable. */
	public const SEP_FACET = ';';
	public const SEP_VALUE = ':';
	public const SEP_LIST  = ',';

	/** Facet sources we can index. */
	public const SRC_ATTRIBUTE = 'attribute';
	public const SRC_TAXONOMY  = 'taxonomy';
	public const SRC_META      = 'meta';

	/** @var array|null */
	private static $cache = null;

	public static function defaults(): array {
		return array(
			// --- general ---------------------------------------------------
			'enabled'            => 'yes',
			'ajax'               => 'yes',
			'auto_apply'         => 'yes',
			'show_counts'        => 'yes',
			'zero_values'        => 'hide',   // hide | dim
			'hide_single_value'  => 'yes',
			'mobile_drawer'      => 'yes',
			'chips'              => 'yes',
			'facet_visible'      => 8,
			'search_from'        => 12,

			// --- built-in facets --------------------------------------------
			'facet_price'        => 'yes',
			'price_step'         => 0,
			'facet_stock'        => 'yes',
			'facet_rating'       => 'no',
			'facet_onsale'       => 'no',
			'facet_category'     => 'no',

			// --- index ------------------------------------------------------
			'reindex_on_save'    => 'yes',
			'index_batch'        => 300,
			'index_built_at'     => '',
			'index_rows'         => 0,

			// --- cache ------------------------------------------------------
			'cache_counters'     => 'yes',
			'cache_ttl'          => 3600,

			// --- SEO ---------------------------------------------------------
			'canonical_mode'     => 'archive', // archive | self
			'robots_single'      => 'index,follow',
			'robots_multi'       => 'noindex,follow',

			// --- markup -------------------------------------------------------
			'container'          => '.wc-block-product-template, ul.products',
			'container_extra'    => '.wp-block-woocommerce-product-results-count, .woocommerce-result-count, .wp-block-query-pagination, .woocommerce-pagination, .woocommerce-products-header__title',
			'load_css'           => 'yes',
			'heading'            => '',

			'notice_dismissed'   => 'no',
		);
	}

	/**
	 * Options the settings form never posts: they belong to the index runner.
	 *
	 * @return string[]
	 */
	public static function server_owned(): array {
		return array(
			'notice_dismissed',
			'index_built_at',
			'index_rows',
		);
	}

	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION );
		$stored = is_array( $stored ) ? $stored : array();

		self::$cache = array_merge( self::defaults(), $stored );

		return self::$cache;
	}

	/**
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Version string for an enqueued asset.
	 *
	 * The plugin version alone is not enough: a hotfix shipped to a client without a
	 * version bump keeps the old CSS and JS in every browser and in every page-cache
	 * layer, and the bug report that follows is about the fix "not working". The file
	 * modification time changes on every deploy, so the URL does too.
	 */
	public static function asset_version( string $relative ): string {
		$path = CATCODE_SF_DIR . ltrim( $relative, '/' );
		$time = file_exists( $path ) ? (int) filemtime( $path ) : 0;

		return $time ? CATCODE_SF_VERSION . '.' . $time : CATCODE_SF_VERSION;
	}

	public static function is_on( string $key ): bool {
		return 'yes' === self::get( $key, 'no' );
	}

	public static function set( array $values ): void {
		$all = array_merge( self::all(), $values );

		update_option( self::OPTION, $all, true );

		self::$cache = $all;
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}

	public static function batch_size(): int {
		return max( 25, min( 2000, (int) self::get( 'index_batch', 300 ) ) );
	}

	public static function cache_ttl(): int {
		if ( ! self::is_on( 'cache_counters' ) ) {
			return 0;
		}

		return max( 60, min( DAY_IN_SECONDS, (int) self::get( 'cache_ttl', HOUR_IN_SECONDS ) ) );
	}

	/** Selectors the AJAX swap replaces besides the product grid. */
	public static function extra_containers(): array {
		$out = array();

		foreach ( explode( ',', (string) self::get( 'container_extra', '' ) ) as $selector ) {
			$selector = trim( $selector );

			if ( '' !== $selector ) {
				$out[] = $selector;
			}
		}

		return $out;
	}

	public static function container(): string {
		$selector = trim( (string) self::get( 'container', '' ) );

		return '' !== $selector ? $selector : 'ul.products';
	}
}
