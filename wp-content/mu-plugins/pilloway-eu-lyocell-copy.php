<?php
/**
 * Plugin Name: Pilloway EU Lyocell copy
 */

defined( 'ABSPATH' ) || exit;

function pilloway_eu_lyocell_copy( $text ) {
	if ( ! is_string( $text ) || false === stripos( $text, 'tencel' ) ) {
		return $text;
	}

	$language = function_exists( 'pll_current_language' ) ? pll_current_language() : '';
	$label = array( 'uk' => 'Ліоцел', 'ru' => 'Лиоцелл' )[ $language ] ?? 'Lyocell';
	$parts = wp_html_split( $text );

	// Leave HTML attributes and existing permalink slugs intact.
	for ( $index = 0; $index < count( $parts ); $index += 2 ) {
		$parts[ $index ] = preg_replace( '/(?<![\p{L}\p{N}_\/-])tencel(?:\s*(?:[®™]|&(?:reg|trade);))?(?![\p{L}\p{N}_\/-])/iu', $label, $parts[ $index ] ) ?? $parts[ $index ];
	}

	return implode( '', $parts );
}

add_filter( 'the_title', 'pilloway_eu_lyocell_copy', 110 );
add_filter( 'the_content', 'pilloway_eu_lyocell_copy', 110 );
add_filter( 'get_the_excerpt', 'pilloway_eu_lyocell_copy', 110 );
add_filter( 'woocommerce_product_get_name', 'pilloway_eu_lyocell_copy', 110 );
add_filter( 'woocommerce_product_get_description', 'pilloway_eu_lyocell_copy', 110 );
add_filter( 'woocommerce_product_get_short_description', 'pilloway_eu_lyocell_copy', 110 );
add_filter( 'wpseo_title', 'pilloway_eu_lyocell_copy', 110 );
add_filter( 'wpseo_metadesc', 'pilloway_eu_lyocell_copy', 110 );

add_filter( 'document_title_parts', static function ( $parts ) {
	return array_map( 'pilloway_eu_lyocell_copy', $parts );
}, 110 );