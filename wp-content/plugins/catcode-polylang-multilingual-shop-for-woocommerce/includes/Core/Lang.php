<?php
/**
 * Thin layer over the Polylang API: languages, translations of posts and terms,
 * and a temporary language switch (e-mails in the order language).
 *
 * Every call goes through the public pll_* functions; the only internal touch is
 * PLL()->curlang during a switch, which is the property Polylang itself reads
 * for "the current language" (its link filters hold it by reference).
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Core;

defined( 'ABSPATH' ) || exit;

class Lang {

	/**
	 * Active switches, innermost last.
	 *
	 * @var array<int,array{slug:string,prev:mixed,locale:bool}>
	 */
	private static $stack = array();

	/** Polylang is active and has at least one language. */
	public static function ready(): bool {
		if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'PLL' ) ) {
			return false;
		}
		return array() !== self::slugs();
	}

	/** @return string[] Language slugs, default language first. */
	public static function slugs(): array {
		$list = function_exists( 'pll_languages_list' ) ? pll_languages_list( array( 'fields' => 'slug' ) ) : array();
		return is_array( $list ) ? array_values( array_filter( array_map( 'strval', $list ) ) ) : array();
	}

	public static function default(): string {
		$slug = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
		return is_string( $slug ) ? $slug : '';
	}

	public static function is_valid( string $slug ): bool {
		return '' !== $slug && in_array( $slug, self::slugs(), true );
	}

	/** @return \PLL_Language|null */
	public static function object( string $slug ) {
		if ( ! self::ready() || '' === $slug ) {
			return null;
		}
		$lang = PLL()->model->get_language( $slug );
		return $lang ? $lang : null;
	}

	public static function name( string $slug ): string {
		$lang = self::object( $slug );
		return $lang ? (string) $lang->name : $slug;
	}

	public static function locale( string $slug ): string {
		$lang = self::object( $slug );
		return $lang ? (string) $lang->locale : '';
	}

	/**
	 * Is this a request where the shopper's language applies?
	 * The admin screens keep their own language; a switch always counts.
	 */
	public static function front(): bool {
		if ( ! empty( self::$stack ) ) {
			return true;
		}
		if ( wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( is_admin() ) {
			return wp_doing_ajax() && class_exists( 'Polylang' ) && \Polylang::is_ajax_on_front();
		}
		return true;
	}

	/** Language of the current shopper request (or of an active switch); '' when unknown. */
	public static function current(): string {
		if ( ! empty( self::$stack ) ) {
			$top = end( self::$stack );
			return $top['slug'];
		}
		if ( ! self::ready() || ! self::front() ) {
			return '';
		}
		$slug = pll_current_language();
		return is_string( $slug ) ? $slug : '';
	}

	public static function switched(): bool {
		return ! empty( self::$stack );
	}

	/**
	 * Make $slug the current language — Polylang's and WordPress's (gettext) —
	 * until restore(). Nested switches are fine; each restore() undoes one.
	 */
	public static function switch_to( string $slug ): bool {
		$lang = self::object( $slug );
		if ( null === $lang ) {
			return false;
		}
		$pll  = PLL();
		$prev = isset( $pll->curlang ) ? $pll->curlang : null;

		$pll->curlang = $lang;
		// false when the locale already is the requested one — then there is nothing to restore.
		$locale = '' !== (string) $lang->locale && switch_to_locale( (string) $lang->locale );

		self::$stack[] = array(
			'slug'   => $slug,
			'prev'   => $prev,
			'locale' => (bool) $locale,
		);

		/**
		 * Fires after the language was switched (e-mail in the order language, admin e-mail in the default one).
		 *
		 * @param string $slug Language slug.
		 */
		do_action( 'catcode_plms_switched', $slug );
		return true;
	}

	public static function restore(): void {
		if ( empty( self::$stack ) ) {
			return;
		}
		$top = array_pop( self::$stack );
		if ( $top['locale'] ) {
			restore_previous_locale();
		}
		PLL()->curlang = $top['prev'];

		/**
		 * Fires after a language switch was undone.
		 *
		 * @param string $slug Language that was active.
		 */
		do_action( 'catcode_plms_restored', $top['slug'] );
	}

	/* ----------------------------------------------------------------- posts */

	public static function post_lang( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'pll_get_post_language' ) ) {
			return '';
		}
		$slug = pll_get_post_language( $post_id );
		return is_string( $slug ) ? $slug : '';
	}

	/** @return array<string,int> lang => post id, including $post_id itself. */
	public static function post_translations( int $post_id ): array {
		if ( $post_id <= 0 || ! function_exists( 'pll_get_post_translations' ) ) {
			return array();
		}
		$list = pll_get_post_translations( $post_id );
		return is_array( $list ) ? array_map( 'intval', $list ) : array();
	}

	/** @return array<string,int> Translations of $post_id without the post itself. */
	public static function other_translations( int $post_id ): array {
		$list = self::post_translations( $post_id );
		foreach ( $list as $slug => $id ) {
			if ( $id === $post_id ) {
				unset( $list[ $slug ] );
			}
		}
		return $list;
	}

	public static function post_in( int $post_id, string $lang ): int {
		if ( $post_id <= 0 || '' === $lang || ! function_exists( 'pll_get_post' ) ) {
			return 0;
		}
		return (int) pll_get_post( $post_id, $lang );
	}

	/**
	 * Link $post_id as the $lang translation of $source_id.
	 */
	public static function link_post( int $source_id, int $post_id, string $lang ): void {
		pll_set_post_language( $post_id, $lang );
		$group = self::post_translations( $source_id );
		if ( empty( $group ) ) {
			$source_lang = self::post_lang( $source_id );
			if ( '' !== $source_lang ) {
				$group[ $source_lang ] = $source_id;
			}
		}
		$group[ $lang ] = $post_id;
		pll_save_post_translations( $group );
	}

	/* ----------------------------------------------------------------- terms */

	public static function term_lang( int $term_id ): string {
		if ( $term_id <= 0 || ! function_exists( 'pll_get_term_language' ) ) {
			return '';
		}
		$slug = pll_get_term_language( $term_id );
		return is_string( $slug ) ? $slug : '';
	}

	/** @return array<string,int> lang => term id, including $term_id itself. */
	public static function term_translations( int $term_id ): array {
		if ( $term_id <= 0 || ! function_exists( 'pll_get_term_translations' ) ) {
			return array();
		}
		$list = pll_get_term_translations( $term_id );
		return is_array( $list ) ? array_map( 'intval', $list ) : array();
	}

	public static function term_in( int $term_id, string $lang ): int {
		if ( $term_id <= 0 || '' === $lang || ! function_exists( 'pll_get_term' ) ) {
			return 0;
		}
		return (int) pll_get_term( $term_id, $lang );
	}

	public static function is_translated_taxonomy( string $taxonomy ): bool {
		return function_exists( 'pll_is_translated_taxonomy' ) && pll_is_translated_taxonomy( $taxonomy );
	}

	/** Polylang's string translation in a given language (the original when untranslated). */
	public static function string( string $text, string $lang ): string {
		if ( '' === $text || '' === $lang || ! function_exists( 'pll_translate_string' ) ) {
			return $text;
		}
		return (string) pll_translate_string( $text, $lang );
	}
}
