<?php
defined( 'ABSPATH' ) || exit;
if ( 'EUR' !== get_woocommerce_currency() || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), [ 'pilloway.eu', 'www.pilloway.eu', '127.0.0.1', 'localhost' ], true ) ) {
	throw new RuntimeException( 'This migration is for Pilloway EU only.' );
}
$labels = [
	'uk' => [ 'Європа', 'Вироблено в Європі' ],
	'ru' => [ 'Европа', 'Произведено в Европе' ],
	'en' => [ 'Europe', 'Made in Europe' ],
	'de' => [ 'Europa', 'Hergestellt in Europa' ],
	'fr' => [ 'Europe', 'Fabriqué en Europe' ],
	'pl' => [ 'Europa', 'Wyprodukowano w Europie' ],
];
$made = '~\b(?:made\s+in|hergestellt\s+in(?:\s+der)?|fabriqu[ée]e?\s+en|wyprodukowano\s+w|вироблено\s+в|произведено\s+в|сделано\s+в)\s*:?\s*(?:Ukraine|Ukraini[ae]|Україні|Украине)\b~iu';
$name = '(?:Ukraine|Ukraina|Ukrainie|Україна|Украина)';
$country = '~(\b(?:country of (?:origin|manufacture)|manufacturing country|herstellungsland|ursprungsland|pays (?:de fabrication|d[’\x27]origine)|kraj (?:produkcji|pochodzenia)|країна (?:виробництва|виробник|походження)|страна (?:производства|производитель|происхождения))\s*[:—–-]\s*(?:</(?:strong|b|span)>\s*)*)' . $name . '\b~iu';
global $wpdb;
$updated = 0;
foreach ( $wpdb->get_results( "SELECT ID, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status NOT IN ('trash', 'auto-draft')" ) as $product ) {
	[ $region, $made_in ] = $labels[ pll_get_post_language( $product->ID ) ?: 'en' ] ?? $labels['en'];
	$place = [ 'uk' => 'Європі', 'ru' => 'Европе', 'pl' => 'Europie' ][ pll_get_post_language( $product->ID ) ] ?? $region;
	$changes = [];
	foreach ( [ 'post_title', 'post_content', 'post_excerpt' ] as $field ) {
		$value = preg_replace( $made, $made_in, $product->$field );
		$value = preg_replace_callback( '~(\b(?:made\s+in|hergestellt\s+in(?:\s+der)?|fabriqu[ée]e?\s+en|wyprodukowano\s+w|вироблено\s+в|произведено\s+в|сделано\s+в)\s*:\s*</(?:strong|b|span)>\s*)(?:Ukraine|Ukraini[ae]|Україні|Украине)\b~iu', static fn( $match ) => $match[1] . $place, $value );
		$value = preg_replace_callback( $country, static fn( $match ) => $match[1] . $region, $value );
		$value = preg_replace( '~(?<=>)\s*' . $name . '\s*(?=<)~iu', $region, $value );
		$value = preg_replace_callback( '~([.!?]\s+)' . $name . '(?=\s*[.]?(?:\s*</|\s*$))~iu', static fn( $match ) => $match[1] . $region, $value );
		if ( $value !== $product->$field ) {
			$changes[ $field ] = $value;
		}
	}
	if ( $changes ) {
		if ( false === $wpdb->update( $wpdb->posts, $changes, [ 'ID' => $product->ID ] ) ) {
			throw new RuntimeException( "Failed to update product {$product->ID}." );
		}
		clean_post_cache( $product->ID );
		++$updated;
	}
}
$terms = get_terms( [ 'taxonomy' => 'pa_country_of_origin', 'hide_empty' => false, 'lang' => '' ] );
if ( is_wp_error( $terms ) ) {
	throw new RuntimeException( $terms->get_error_message() );
}
$renamed = 0;
foreach ( $terms as $term ) {
	if ( ! preg_match( '~^' . $name . '$~iu', $term->name ) ) {
		continue;
	}
	$lang = pll_get_term_language( $term->term_id ) ?: 'en';
	$result = wp_update_term( $term->term_id, $term->taxonomy, [ 'name' => ( $labels[ $lang ] ?? $labels['en'] )[0] ] );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	++$renamed;
}
printf( "Updated %d products and %d origin terms.\n", $updated, $renamed );
