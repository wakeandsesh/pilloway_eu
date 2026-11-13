<?php
/**
 * Plugin Name: WordPress Ngrok Preview URL Rewrite
 * Description: Temporarily maps this project's local WordPress URLs to its active preview tunnel host.
 */

if ( ! defined( 'WP_NGROK_PREVIEW_HOST' ) || ! defined( 'WP_NGROK_PREVIEW_PORT' ) ) {
	return;
}

$wp_quick_tunnel_host = WP_NGROK_PREVIEW_HOST;
$wp_quick_tunnel_port = (int) WP_NGROK_PREVIEW_PORT;
$wp_quick_tunnel_target = 'https://' . $wp_quick_tunnel_host;
$wp_quick_tunnel_sources = array();

global $wpdb;

foreach ( array( 'home', 'siteurl' ) as $wp_quick_tunnel_option ) {
	$wp_quick_tunnel_saved_url = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			$wp_quick_tunnel_option
		)
	);

	if ( ! is_string( $wp_quick_tunnel_saved_url ) || '' === $wp_quick_tunnel_saved_url ) {
		continue;
	}

	$wp_quick_tunnel_parts = wp_parse_url( $wp_quick_tunnel_saved_url );

	if ( empty( $wp_quick_tunnel_parts['scheme'] ) || empty( $wp_quick_tunnel_parts['host'] ) ) {
		continue;
	}

	$wp_quick_tunnel_source = $wp_quick_tunnel_parts['scheme'] . '://' . $wp_quick_tunnel_parts['host'];

	if ( isset( $wp_quick_tunnel_parts['port'] ) ) {
		$wp_quick_tunnel_source .= ':' . $wp_quick_tunnel_parts['port'];
	}

	if ( ! empty( $wp_quick_tunnel_parts['path'] ) ) {
		$wp_quick_tunnel_source .= rtrim( $wp_quick_tunnel_parts['path'], '/' );
	}

	$wp_quick_tunnel_sources[] = rtrim( $wp_quick_tunnel_source, '/' );
}

$wp_quick_tunnel_sources = array_merge(
	$wp_quick_tunnel_sources,
	array(
		'http://localhost:' . $wp_quick_tunnel_port,
		'https://localhost:' . $wp_quick_tunnel_port,
		'http://127.0.0.1:' . $wp_quick_tunnel_port,
		'https://127.0.0.1:' . $wp_quick_tunnel_port,
	)
);

$wp_quick_tunnel_sources = array_values( array_unique( $wp_quick_tunnel_sources ) );

if ( empty( $wp_quick_tunnel_sources ) ) {
	return;
}

$wp_quick_tunnel_rewrite = static function ( $value ) use ( $wp_quick_tunnel_sources, $wp_quick_tunnel_target ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return $value;
	}

	foreach ( $wp_quick_tunnel_sources as $wp_quick_tunnel_source ) {
		$source_length = strlen( $wp_quick_tunnel_source );
		$offset        = 0;

		while ( false !== ( $position = stripos( $value, $wp_quick_tunnel_source, $offset ) ) ) {
			$after_source = $value[ $position + $source_length ] ?? '';

			if ( '' === $after_source || false !== strpos( '/?#"\'<>\\', $after_source ) || ctype_space( $after_source ) ) {
				$value = substr_replace( $value, $wp_quick_tunnel_target, $position, $source_length );
				$offset = $position + strlen( $wp_quick_tunnel_target );
				continue;
			}

			$offset = $position + $source_length;
		}

		$escaped_source = str_replace( '/', '\\/', $wp_quick_tunnel_source );
		$escaped_target = str_replace( '/', '\\/', $wp_quick_tunnel_target );
		$value          = str_replace( $escaped_source, $escaped_target, $value );
	}

	return $value;
};

foreach (
	array(
		'home_url',
		'site_url',
		'content_url',
		'plugins_url',
		'stylesheet_directory_uri',
		'template_directory_uri',
		'wp_get_attachment_url',
		'script_loader_src',
		'style_loader_src',
		'rest_url',
		'the_content',
		'the_excerpt',
		'widget_text',
		'widget_block_content',
		'widget_custom_html_content',
	) as $wp_quick_tunnel_filter
) {
	add_filter( $wp_quick_tunnel_filter, $wp_quick_tunnel_rewrite, 1000 );
}

add_filter(
	'nav_menu_link_attributes',
	static function ( $attributes ) use ( $wp_quick_tunnel_rewrite ) {
		if ( isset( $attributes['href'] ) ) {
			$attributes['href'] = $wp_quick_tunnel_rewrite( $attributes['href'] );
		}

		return $attributes;
	},
	1000
);

$wp_quick_tunnel_rewrite_response = static function ( $buffer ) use ( $wp_quick_tunnel_rewrite ) {
	$content_type = '';

	foreach ( headers_list() as $header ) {
		if ( 0 === stripos( $header, 'Content-Type:' ) ) {
			$content_type = trim( substr( $header, strlen( 'Content-Type:' ) ) );
			break;
		}
	}

	if ( '' !== $content_type && 1 !== preg_match( '/^(?:text\/(?:html|css|javascript|plain)|application\/(?:json|javascript|xml|xhtml\+xml)|image\/svg\+xml)(?:\s*;|$)/i', $content_type ) ) {
		return $buffer;
	}

	return $wp_quick_tunnel_rewrite( $buffer );
};

ob_start( $wp_quick_tunnel_rewrite_response );
