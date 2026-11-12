<?php
/**
 * Clear the cache of all plugins when the "Load product price in the background" option change.
 *
 * @since 2.3.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Cache_Plugins_Helper Class
 */
class WCPBC_Cache_Plugins_Helper {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'update_option_wc_price_based_country_caching_support', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Flush the caches of the common caching plugins.
	 */
	public static function flush_cache() {

		/**
		 * Filters whether the caches of common caching plugins shall be flushed.
		 *
		 * @param bool $flush Whether caches of caching plugins shall be flushed. Default true.
		 */
		if ( ! apply_filters( 'wc_price_based_country_flush_plugins_caches', true ) ) {
			return false;
		}

		$callbacks = self::get_purge_cache_callbacks();

		foreach ( $callbacks as $callback ) {
			call_user_func( $callback->function, ...$callback->args );
		}
	}

	/**
	 * Returns true if there is a static caching in use. False otherwise.
	 */
	public static function has_cache_plugin() {

		if ( in_array( 'advanced-cache.php', array_keys( get_dropins() ), true ) ) {
			return true;
		}

		if ( ! empty( self::get_purge_cache_callbacks() ) ) {
			return true;
		}

		$cache_headers = array( 'cf-cache-status', 'sg-optimizer-worker-status', 'x-proxy-cache' );
		$response      = wp_remote_get( get_home_url() );

		if ( ! is_wp_error( $response ) && isset( $response['headers'] ) ) {

			foreach ( $response['headers'] as $key => $value ) {
				if ( in_array( $key, $cache_headers, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Returns the purge cache callback.
	 *
	 * @return bool|stdClass False if there is no cache plugin detected.
	 */
	private static function get_purge_cache_callbacks() {

		$callbacks = [];

		foreach ( self::get_known_cache_plugins() as $id => $data ) {

			if ( isset( $data['function'] ) ) {

				if ( ! is_callable( $data['function'] ) ) {
					continue;
				}

				$callbacks[] = (object) [
					'function' => $data['function'],
					'args'     => [],
				];

			} elseif ( isset( $data['action'] ) ) {

				if ( ! has_action( $data['action'] ) ) {
					continue;
				}

				$callbacks[] = (object) [
					'function' => 'do_action',
					'args'     => [ $data['action'] ],
				];

			} else {

				if ( ! self::{"is_callable_pruge_$id"}() ) {
					continue;
				}

				$callbacks[] = (object) [
					'function' => [ __CLASS__, "purge_cache_$id" ],
					'args'     => [],
				];
			}
		}

		return $callbacks;
	}

	/**
	 * Returns an array of known cache plugins.
	 *
	 * @return array
	 */
	private static function get_known_cache_plugins() {
		return [

			'litespeed'    => [
				'name'   => 'LiteSpeed Cache by LiteSpeed Technologies',
				'action' => 'litespeed_purge_all',
			],

			'wpsupercache' => [
				'name'     => 'WP Super Cache by Automattic',
				'function' => 'wp_cache_clear_cache',
			],

			'siteground'   => [
				'name'     => 'SiteGround Optimizer by SiteGround',
				'function' => 'sg_cachepress_purge_cache',
			],

			'w3tc'         => [
				'name'     => 'W3 Total Cache by BoldGrid',
				'function' => 'w3tc_pgcache_flush',
			],

			'wpfastest'    => [
				'name'     => 'WP Fastest Cache by Emre Vona',
				'function' => 'wpfc_clear_all_cache',
			],

			'wpoptimize'   => [
				'name' => 'WP-Optimize by David Anderson',
			],

			'wprocket'     => [
				'name'     => 'WP Rocket by WP MEDIA',
				'function' => 'rocket_clean_domain',
			],

			'breeze'       => [
				'name'     => 'Breeze by Cloudways',
				'function' => [ 'Breeze_PurgeCache', 'breeze_cache_flush' ],
			],

			'cacheenabler' => [
				'name'   => 'Cache Enabler by KeyCDN',
				'action' => 'cache_enabler_clear_complete_cache',
			],

			'comet'        => [
				'name'     => 'Comet Cache by WP Sharks',
				'function' => [ 'comet_cache', 'clear' ],
			],

			'pantheon'     => [
				'name'     => 'Pantheon Advanced Page Cache By Pantheon',
				'function' => 'pantheon_wp_clear_edge_all',
			],

			'swift'        => [
				'name'     => 'Swift Performance Lite by SWTE',
				'function' => [ 'Swift_Performance_Cache', 'clear_all_cache' ],
			],

			'cachify'      => [
				'name'   => 'Cachify by pluginkollektiv',
				'action' => 'cachify_flush_cache',
			],

			'wpengine'     => [
				'name'     => 'WP Engine hosting cache',
				'function' => [ 'WpeCommon', 'purge_varnish_cache' ],
			],

			'kinsta'       => [
				'name' => 'Kinsta hosting cache',
			],

			'endurance'    => [
				'name'   => 'Endurance Page Cache by Bluehost',
				'action' => 'epc_purge',
			],

			'warpdrive'    => [
				'name'   => 'Warpdrive by Savvii hosting',
				'action' => 'warpdrive_domain_flush',
			],

			'pagely'       => [
				'name' => 'Pagely hosting cache',
			],

			'pressidum'    => [
				'name' => 'Pressidum hosting cache',
			],

		];
	}

	/*
	|--------------------------------------------------------------------------
	| WP Optimize
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if the WP Optimize purge function is callable.
	 *
	 * @return bool
	 */
	private static function is_callable_pruge_wpoptimize() {
		return function_exists( 'WP_Optimize' ) && is_callable( [ WP_Optimize(), 'get_page_cache' ] ) && is_callable( [ WP_Optimize()->get_page_cache(), 'purge' ] );
	}

	/**
	 * Purge the WP Optimize cache.
	 */
	private static function purge_cache_wpoptimize() {
		WP_Optimize()->get_page_cache()->purge();
	}

	/*
	|--------------------------------------------------------------------------
	| Kinsta Hosting
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if the Kinsta purge function is callable.
	 *
	 * @return bool
	 */
	private static function is_callable_pruge_kinsta() {
		return isset( $GLOBALS['kinsta_cache'] ) && ! empty( $GLOBALS['kinsta_cache']->kinsta_cache_purge ) && is_callable( array( $GLOBALS['kinsta_cache']->kinsta_cache_purge, 'purge_complete_caches' ) );
	}

	/**
	 * Purge Kinsta cache.
	 */
	private static function purge_cache_kinsta() {
		$GLOBALS['kinsta_cache']->kinsta_cache_purge->purge_complete_caches();
	}

	/*
	|--------------------------------------------------------------------------
	| Pagely Hosting
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if the Pagely purge function is callable.
	 *
	 * @return bool
	 */
	private static function is_callable_pruge_pagely() {
		return class_exists( 'PagelyCachePurge' ) && method_exists( 'PagelyCachePurge', 'purgeAll' );
	}

	/**
	 * Purge the Pagely cache.
	 */
	private static function purge_cache_pagely() {
		$_pagely = new PagelyCachePurge();
		$_pagely->purgeAll();
	}

	/*
	|--------------------------------------------------------------------------
	| Pressidum Hosting
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if the Pressidum purge function is callable.
	 *
	 * @return bool
	 */
	private static function is_callable_pruge_pressidum() {
		return is_callable( [ 'Ninukis_Plugin', 'get_instance' ] ) && is_callable( [ Ninukis_Plugin::get_instance(), 'purgeAllCaches' ] );
	}

	/**
	 * Purge the Pressidum cache.
	 */
	private static function purge_cache_pressidum() {
		Ninukis_Plugin::get_instance()->purgeAllCaches();
	}
}
