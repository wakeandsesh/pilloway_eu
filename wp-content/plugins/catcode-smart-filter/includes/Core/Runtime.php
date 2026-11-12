<?php
/**
 * What the current request is filtering.
 *
 * One place holds the decoded selection and the archive scope for the whole
 * request: the query filter reads it, the widget renders from it, and the SEO
 * layer decides canonical/robots from it. Passing it around instead would mean
 * every one of them re-parsing the query string — and drifting apart the moment
 * one of them normalises differently.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Core;

defined( 'ABSPATH' ) || exit;

class Runtime {

	/** @var array<string,array<int,string>> */
	private static $selection = array();

	/** @var array<string,mixed> */
	private static $scope = array();

	/** @var bool */
	private static $booted = false;

	/** @var array<string,mixed>|null */
	private static $page = null;

	/** @var string none|applied|failed */
	private static $applied = 'none';

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted    = true;
		self::$selection = Params::from_request();
		self::$scope     = self::detect_scope();
	}

	/**
	 * Which archive we are on. The scope is what the counters count over, so it
	 * has to match what the listing itself shows — including the subtree of a
	 * category.
	 *
	 * @return array<string,mixed>
	 */
	private static function detect_scope(): array {
		$scope = array();

		if ( is_search() ) {
			$scope['search'] = get_search_query();

			return $scope;
		}

		foreach ( array( 'product_cat', 'product_tag', 'product_brand' ) as $taxonomy ) {
			if ( is_tax( $taxonomy ) ) {
				$term = get_queried_object();

				if ( $term && isset( $term->term_id ) ) {
					$scope['taxonomy'] = $taxonomy;
					$scope['term_id']  = (int) $term->term_id;
				}

				return $scope;
			}
		}

		if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$term = get_queried_object();

			if ( $term && isset( $term->taxonomy, $term->term_id ) ) {
				$scope['taxonomy'] = (string) $term->taxonomy;
				$scope['term_id']  = (int) $term->term_id;
			}
		}

		return $scope;
	}

	/** @return array<string,array<int,string>> */
	public static function selection(): array {
		return self::$selection;
	}

	public static function selection_string(): string {
		return Params::encode( self::$selection );
	}

	public static function is_active(): bool {
		return ! empty( self::$selection );
	}

	/** @return array<string,mixed> */
	public static function scope(): array {
		return self::$scope;
	}

	public static function set_scope( array $scope ): void {
		self::$scope = $scope;
	}

	public static function mark_applied( string $how ): void {
		self::$applied = $how;
	}

	public static function applied(): string {
		return self::$applied;
	}

	/** @param array<string,mixed>|null $page */
	public static function set_page( ?array $page ): void {
		self::$page = $page;
	}

	/** @return array<string,mixed>|null */
	public static function page(): ?array {
		return self::$page;
	}

	public static function reset(): void {
		self::$booted    = false;
		self::$selection = array();
		self::$scope     = array();
		self::$page      = null;
		self::$applied   = 'none';
	}
}
