<?php
/**
 * JavaScript translations on a language subdomain or domain.
 *
 * With "the language is set from the subdomain" (or from different domains) Polylang
 * moves content_url() and plugins_url() to the host of the language. Scripts that were
 * registered before (WooCommerce registers its block scripts early) keep the main host.
 * WordPress then cannot tell which plugin or theme such a script belongs to, finds no
 * JSON translation for it, and the block cart and checkout show their texts in English
 * on every language subdomain. Here the script path is resolved against the content
 * folder whatever the host, as WordPress does when the hosts match.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Front;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class ScriptTranslations {

	/** @var string 'plugins' or 'themes' for the script resolved last, '' when WordPress resolved it itself. */
	private $dir = '';

	/** @var array<int,string>|null Hosts of the site and of its languages. */
	private $hosts = null;

	public function register(): void {
		if ( is_admin() || ! self::uses_hosts() ) {
			return;
		}
		add_filter( 'load_script_textdomain_relative_path', array( $this, 'relative' ), 20, 2 );
		add_filter( 'load_script_translation_file', array( $this, 'file' ), 20, 3 );
	}

	/** Polylang takes the language from the subdomain (2) or from separate domains (3). */
	public static function uses_hosts(): bool {
		if ( ! function_exists( 'PLL' ) || ! isset( PLL()->options ) ) {
			return false;
		}
		return (int) PLL()->options['force_lang'] >= 2;
	}

	/**
	 * @param string|false $relative Path WordPress worked out.
	 * @param string       $src      Script URL.
	 * @return string|false
	 */
	public function relative( $relative, $src ) {
		$this->dir = '';
		$src       = (string) $src;
		$src_host  = (string) wp_parse_url( $src, PHP_URL_HOST );
		$src_path  = (string) wp_parse_url( $src, PHP_URL_PATH );
		$content   = wp_parse_url( content_url() );
		if ( '' === $src_host || '' === $src_path || ! is_array( $content ) || empty( $content['host'] ) ) {
			return $relative;
		}
		// Same host: WordPress resolved it already. A foreign host: not ours to guess.
		if ( strtolower( $src_host ) === strtolower( $content['host'] ) || ! in_array( strtolower( $src_host ), $this->hosts(), true ) ) {
			return $relative;
		}
		$base = isset( $content['path'] ) ? untrailingslashit( $content['path'] ) : '';
		if ( '' !== $base && 0 !== strpos( $src_path, $base . '/' ) ) {
			return $relative;
		}
		$parts = explode( '/', trim( substr( $src_path, strlen( $base ) ), '/' ) );
		if ( count( $parts ) < 3 ) {
			return $relative;
		}
		$theme_dir = basename( get_theme_root() );
		$this->dir = $theme_dir === $parts[0] ? 'themes' : 'plugins';
		return implode( '/', array_slice( $parts, 2 ) ); // Without plugins/<plugin> or themes/<theme>.
	}

	/**
	 * WordPress looks for the file in the root languages folder once its own resolution
	 * failed; the file of a plugin or theme lives in languages/plugins or languages/themes.
	 *
	 * @param string|false $file   File WordPress is about to read.
	 * @param string       $handle Script handle.
	 * @param string       $domain Text domain.
	 * @return string|false
	 */
	public function file( $file, $handle = '', $domain = '' ) {
		unset( $handle, $domain );
		if ( '' === $this->dir || ! is_string( $file ) || is_readable( $file ) ) {
			return $file;
		}
		if ( wp_normalize_path( dirname( $file ) ) !== wp_normalize_path( untrailingslashit( WP_LANG_DIR ) ) ) {
			return $file;
		}
		$moved = trailingslashit( WP_LANG_DIR ) . $this->dir . '/' . basename( $file );
		return is_readable( $moved ) ? $moved : $file;
	}

	/** @return array<int,string> */
	private function hosts(): array {
		if ( null !== $this->hosts ) {
			return $this->hosts;
		}
		$hosts = array( (string) wp_parse_url( home_url(), PHP_URL_HOST ), (string) wp_parse_url( site_url(), PHP_URL_HOST ) );
		foreach ( Lang::slugs() as $slug ) {
			$hosts[] = (string) wp_parse_url( (string) pll_home_url( $slug ), PHP_URL_HOST );
		}
		$this->hosts = array_values( array_unique( array_filter( array_map( 'strtolower', $hosts ) ) ) );
		return $this->hosts;
	}
}
