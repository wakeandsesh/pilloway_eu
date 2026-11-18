<?php
/**
 * Plugin Name: Pilloway Landing WebP
 * Description: Serves optimized variants on the three collection templates, including all translations.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', static function () {
	if ( is_feed() || is_preview() || ! is_page() ) {
		return;
	}

	$templates = array( 'page-beauty-balance.php', 'page-correct-shape.php', 'page-family-dream.php' );
	if ( ! in_array( get_page_template_slug( get_queried_object_id() ), $templates, true ) ) {
		return;
	}

	$uploads = wp_get_upload_dir();
	$directory = $uploads['basedir'] . '/pilloway-landing-webp';
	$manifest = $directory . '/manifest.json';
	if ( ! is_readable( $manifest ) ) {
		return;
	}

	$files = json_decode( file_get_contents( $manifest ), true );
	if ( ! is_array( $files ) || ! $files ) {
		return;
	}

	$upload_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
	$replacements = array();
	foreach ( $files as $original => $optimized ) {
		if ( ! is_string( $optimized ) || ! is_file( $directory . '/' . $optimized ) ) {
			continue;
		}
		$target = $upload_path . '/pilloway-landing-webp/' . $optimized;
		$replacements[ $original ] = $target;
		$replacements[ str_replace( '/', '\/', $original ) ] = str_replace( '/', '\/', $target );
	}

	// Keep original media and WordPress attachment metadata intact. Rewrite URL
	// references in markup, responsive candidates, and inline variant data alike.
	ob_start( static function ( $html ) use ( $replacements ) {
		return strtr( $html, $replacements );
	} );
}, 99 );
