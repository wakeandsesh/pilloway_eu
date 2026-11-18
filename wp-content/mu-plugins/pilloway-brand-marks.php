<?php
/**
 * Plugin Name: Pilloway Brand Marks
 * Description: Adds registered marks to the three brand names in storefront HTML.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_brand_marks_text( string $text ): string {
    return preg_replace(
        '~(?<![\p{L}\p{N}])((?:Beauty(?:\h|&nbsp;|&#160;)+Balance|Correct(?:\h|&nbsp;|&#160;)+Shape|Family(?:\h|&nbsp;|&#160;)+Dream))(?![\p{L}\p{N}])(?!(?:\h|&nbsp;|&#160;|</(?:strong|b|em|span)>)*(?:®|&reg;|&#0*174;|&#x0*ae;|™|&trade;))~iu',
        '$1®',
        $text
    ) ?? $text;
}

function pilloway_brand_marks_html( string $html ): string {
    return preg_replace_callback(
        '~<!--.*?-->|<(?:script|style|textarea|code|pre|svg)\b[^>]*>.*?</(?:script|style|textarea|code|pre|svg)\s*>|<(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*>|[^<]+(?:</(?:strong|b|em|span)>\s*)+(?:®|&reg;|&#0*174;|&#x0*ae;|™|&trade;)|[^<]+~is',
        static function ( array $match ): string {
            $value = $match[0];
            if ( $value[0] === '<' ) {
                if ( preg_match( '~^<(?:!|script\b|style\b|textarea\b|code\b|pre\b|svg\b)~i', $value ) ) {
                    return $value;
                }
                return preg_replace_callback(
                    '~(\s(?:alt|title|aria-label)=)(["\x27])(.*?)\2~is',
                    static fn( array $attribute ): string => $attribute[1] . $attribute[2] . pilloway_brand_marks_text( $attribute[3] ) . $attribute[2],
                    $value
                ) ?? $value;
            }
            return pilloway_brand_marks_text( $value );
        },
        $html
    ) ?? $html;
}

add_action( 'template_redirect', static function (): void {
    if ( ! is_feed() && ! is_trackback() && ! is_robots() && ! is_favicon() && ! wp_is_json_request() && ! is_embed() && ! get_query_var( 'sitemap' ) ) {
        ob_start( 'pilloway_brand_marks_html' );
    }
}, 100 );

