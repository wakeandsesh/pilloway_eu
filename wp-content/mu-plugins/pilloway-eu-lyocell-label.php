<?php
defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_attribute_label', static function ( $label, $name ) {
    if ( ! preg_match( '/^(?:pa_)?spec_tensel_0bc4f$/D', $name ) ) {
        return $label;
    }
    $language = function_exists( 'pll_current_language' ) ? pll_current_language() : '';
    return [ 'uk' => 'Ліоцел', 'ru' => 'Лиоцелл' ][ $language ] ?? 'Lyocell';
}, 110, 2 );
