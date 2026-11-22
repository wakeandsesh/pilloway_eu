<?php
/**
 * Where the block appears, and what search engines are told about the page.
 *
 * Three ways to place it, because themes differ: the widget/block area, a
 * shortcode, and an automatic hook before the product loop for shops whose theme
 * has no sidebar on the archive.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Frontend;

use CatCode\SmartFilter\Core\Params;
use CatCode\SmartFilter\Core\Runtime;
use CatCode\SmartFilter\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Display {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'catcode_filter', array( __CLASS__, 'shortcode' ) );

		add_filter( 'woocommerce_redirect_single_search_result', array( __CLASS__, 'keep_filtered_search' ) );

		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'get_canonical_url', array( __CLASS__, 'canonical' ), 20, 2 );
		add_action( 'wp_head', array( __CLASS__, 'archive_canonical' ), 9 );

		// Yoast and Rank Math own the canonical tag when installed; both offer the
		// same filter shape, so hooking them keeps one canonical on the page
		// instead of two — two are treated as none at all.
		add_filter( 'wpseo_canonical', array( __CLASS__, 'seo_canonical' ), 20 );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'seo_canonical' ), 20 );
		add_filter( 'wpseo_robots', array( __CLASS__, 'seo_robots' ), 20 );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'seo_robots_array' ), 20 );

		add_filter( 'document_title_parts', array( __CLASS__, 'title' ), 20 );
		add_action( 'woocommerce_archive_description', array( __CLASS__, 'landing_text' ), 5 );
		add_filter( 'woocommerce_page_title', array( __CLASS__, 'landing_heading' ), 20 );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'landing_heading' ), 20 );
	}

	public static function assets(): void {
		if ( ! Settings::is_on( 'enabled' ) ) {
			return;
		}

		if ( Settings::is_on( 'load_css' ) ) {
			wp_enqueue_style( 'catcode-smart-filter', CATCODE_SF_URL . 'assets/css/filter.css', array(), Settings::asset_version( 'assets/css/filter.css' ) );
		}

		wp_enqueue_script( 'catcode-smart-filter', CATCODE_SF_URL . 'assets/js/filter.js', array(), Settings::asset_version( 'assets/js/filter.js' ), true );
	}

	/**
	 * WooCommerce jumps to the product when a search finds exactly one. On a
	 * filtered search that took the shopper out of the listing, and the no-reload
	 * swap received a product page instead of the results.
	 *
	 * @param bool $redirect Whether WooCommerce is about to redirect.
	 */
	public static function keep_filtered_search( $redirect ) {
		if ( ! Settings::is_on( 'enabled' ) ) {
			return $redirect;
		}

		Runtime::boot();

		return Runtime::selection() ? false : $redirect;
	}

	public static function shortcode( $atts = array() ): string {
		return Renderer::render();
	}

	/**
	 * @param array<string,bool|string> $robots
	 * @return array<string,bool|string>
	 */
	public static function robots( array $robots ): array {
		$value = self::robots_value();

		if ( '' === $value ) {
			return $robots;
		}

		// Never lift a stricter directive set earlier (a closed site, a page
		// marked noindex): the filter may only tighten the robots value.
		$noindex  = ! empty( $robots['noindex'] ) || false !== strpos( $value, 'noindex' );
		$nofollow = ! empty( $robots['nofollow'] ) || false !== strpos( $value, 'nofollow' );

		unset( $robots['index'], $robots['noindex'], $robots['follow'], $robots['nofollow'] );

		if ( $noindex ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
		} else {
			$robots['index'] = true;
		}

		if ( $nofollow ) {
			$robots['nofollow'] = true;
		} else {
			$robots['follow'] = true;
		}

		return $robots;
	}

	/**
	 * Yoast passes a comma-and-space separated string («index, follow,
	 * max-snippet:-1 …») and splits the result on «, » again. Returning the raw
	 * setting («noindex,follow») left one unknown token next to Yoast's own
	 * index/follow pair: «noindex, nofollow, index,follow». Only the index and
	 * follow tokens are replaced here, the rest of Yoast's value stays.
	 *
	 * @param string|bool $robots Yoast robots string.
	 * @return string|bool
	 */
	public static function seo_robots( $robots ) {
		$value = self::robots_value();

		if ( '' === $value || ! is_string( $robots ) ) {
			return $robots;
		}

		$tokens = array_filter( array_map( 'trim', explode( ',', $robots ) ), 'strlen' );

		$noindex  = in_array( 'noindex', $tokens, true ) || false !== strpos( $value, 'noindex' );
		$nofollow = in_array( 'nofollow', $tokens, true ) || false !== strpos( $value, 'nofollow' );

		$rest = array();

		foreach ( $tokens as $token ) {
			if ( in_array( $token, array( 'index', 'noindex', 'follow', 'nofollow' ), true ) ) {
				continue;
			}
			if ( $noindex && 0 === strpos( $token, 'max-' ) ) {
				continue;
			}
			$rest[] = $token;
		}

		array_unshift( $rest, $noindex ? 'noindex' : 'index', $nofollow ? 'nofollow' : 'follow' );

		return implode( ', ', $rest );
	}

	public static function seo_robots_array( $robots ) {
		$value = self::robots_value();

		if ( '' === $value || ! is_array( $robots ) ) {
			return $robots;
		}

		if ( false !== strpos( $value, 'noindex' ) ) {
			$robots['index'] = 'noindex';
		}

		return $robots;
	}

	private static function robots_value(): string {
		if ( is_admin() || ! Settings::is_on( 'enabled' ) ) {
			return '';
		}

		Runtime::boot();

		$selection = Runtime::selection();

		if ( ! $selection ) {
			return '';
		}

		$page = Runtime::page();

		if ( $page && ! empty( $page['robots'] ) ) {
			return (string) $page['robots'];
		}

		return Urls::robots( $selection, (bool) $page );
	}

	public static function canonical( $url, $post = null ) {
		$custom = self::canonical_value();

		return '' === $custom ? $url : $custom;
	}

	/**
	 * Canonical tag for a filtered archive.
	 *
	 * `get_canonical_url` never fires here: WordPress emits rel=canonical for
	 * singular views only, so on a shop or category archive nothing would point the
	 * filtered URL back at the clean one and every combination reads as a separate
	 * page. Yoast and Rank Math do print one on archives, so when either is active
	 * this stays quiet and the filters above adjust theirs instead — two canonicals
	 * in one head is worse than none.
	 */
	public static function archive_canonical(): void {
		if ( is_singular() || defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || class_exists( 'All_in_One_SEO_Pack' ) ) {
			return;
		}

		$custom = self::canonical_value();

		if ( '' === $custom ) {
			return;
		}

		echo '<link rel="canonical" href="' . esc_url( $custom ) . '" />' . "
";
	}

	public static function seo_canonical( $url ) {
		$custom = self::canonical_value();

		return '' === $custom ? $url : $custom;
	}

	private static function canonical_value(): string {
		if ( is_admin() || ! Settings::is_on( 'enabled' ) ) {
			return '';
		}

		Runtime::boot();

		$selection = Runtime::selection();

		if ( ! $selection ) {
			return '';
		}

		return Urls::canonical( $selection, (bool) Runtime::page() );
	}

	/**
	 * @param array<string,string> $parts
	 * @return array<string,string>
	 */
	public static function title( array $parts ): array {
		$page = Runtime::page();

		if ( $page && ! empty( $page['meta_title'] ) ) {
			$parts['title'] = (string) $page['meta_title'];
		}

		return $parts;
	}

	public static function landing_heading( $title ) {
		$page = Runtime::page();

		if ( $page && ! empty( $page['h1'] ) ) {
			return (string) $page['h1'];
		}

		return $title;
	}

	public static function landing_text(): void {
		$page = Runtime::page();

		if ( ! $page || empty( $page['description'] ) ) {
			return;
		}

		echo '<div class="ccf-landing-text">' . wp_kses_post( wpautop( (string) $page['description'] ) ) . '</div>';
	}
}
