<?php
/**
 * Plugin Name: Pilloway Checkout Flow
 * Description: Refines classic WooCommerce checkout presentation and keeps delivery data aligned with Nova Post.
 * Version: 1.2.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: pilloway-checkout-flow
 */
defined( 'ABSPATH' ) || exit;

function pilloway_checkout_flow_declare_hpos_compatibility() {
    $features_util = 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil';
    if ( class_exists( $features_util ) ) {
        call_user_func( array( $features_util, 'declare_compatibility' ), 'custom_order_tables', __FILE__, true );
    }
}
add_action( 'before_woocommerce_init', 'pilloway_checkout_flow_declare_hpos_compatibility' );

/**
 * Keep phone required, make email optional, and remove duplicate billing-address fields.
 * Delivery address fields remain available for courier delivery.
 */
function pilloway_checkout_flow_configure_fields( $fields ) {
    if ( isset( $fields['billing']['billing_email'] ) ) {
        $fields['billing']['billing_email']['required'] = false;
    }
    if ( isset( $fields['billing']['billing_phone'] ) ) {
        $fields['billing']['billing_phone']['required'] = true;
    }
    foreach ( array( 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $part ) {
        unset( $fields['billing'][ 'billing_' . $part ] );
    }
    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'pilloway_checkout_flow_configure_fields', 20 );

/**
 * Register WooCommerce option strings with Polylang Free.
 *
 * These values are configured in WooCommerce settings, so they belong in
 * Polylang's native string translation table rather than in a language fallback.
 */
function pilloway_checkout_flow_register_polylang_strings() {
    if ( ! function_exists( 'pll_register_string' ) || ! function_exists( 'WC' ) || ! WC() ) {
        return;
    }

    $gateways = WC()->payment_gateways();
    if ( ! $gateways ) {
        return;
    }

    foreach ( $gateways->payment_gateways() as $gateway_id => $gateway ) {
        $settings = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
        foreach ( array( 'title', 'description', 'instructions' ) as $field ) {
            $value = isset( $settings[ $field ] ) ? $settings[ $field ] : ( isset( $gateway->{$field} ) ? $gateway->{$field} : '' );
            if ( ! is_string( $value ) || '' === trim( wp_strip_all_tags( $value ) ) ) {
                continue;
            }
            pll_register_string(
                'WooCommerce payment ' . sanitize_key( $gateway_id . '_' . $field ),
                $value,
                'Pilloway checkout',
                'title' !== $field
            );
        }
    }

    $privacy_text = get_option( 'woocommerce_checkout_privacy_policy_text', '' );
    if ( is_string( $privacy_text ) && '' !== trim( $privacy_text ) ) {
        pll_register_string( 'WooCommerce checkout privacy text', $privacy_text, 'Pilloway checkout', true );
    }
}
add_action( 'admin_init', 'pilloway_checkout_flow_register_polylang_strings', 20 );

/**
 * Translate a configured WooCommerce option through Polylang's string table.
 *
 * @param string $value Configured source string.
 * @return string
 */
function pilloway_checkout_flow_translate_polylang_option( $value ) {
    if ( ! is_string( $value ) || is_admin() || ! function_exists( 'pll__' ) ) {
        return $value;
    }

    return pll__( $value );
}

add_filter( 'woocommerce_gateway_title', 'pilloway_checkout_flow_translate_polylang_option', 25, 2 );
add_filter( 'woocommerce_gateway_description', 'pilloway_checkout_flow_translate_polylang_option', 25, 2 );
add_filter( 'woocommerce_get_privacy_policy_text', 'pilloway_checkout_flow_translate_polylang_option', 25 );

function pilloway_checkout_flow_enqueue_assets() {
    if ( is_admin() ) {
        return;
    }

    $url  = plugin_dir_url( __FILE__ );
    $path = plugin_dir_path( __FILE__ );

    wp_enqueue_style(
        'pilloway-site-ui',
        $url . 'assets/site-ui.css',
        array(),
        file_exists( $path . 'assets/site-ui.css' ) ? (string) filemtime( $path . 'assets/site-ui.css' ) : '1.2.0'
    );

    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
        return;
    }

    wp_enqueue_style(
        'pilloway-checkout-flow',
        $url . 'assets/checkout.css',
        array( 'pilloway-site-ui' ),
        file_exists( $path . 'assets/checkout.css' ) ? (string) filemtime( $path . 'assets/checkout.css' ) : '1.2.0'
    );
    wp_enqueue_style(
        'pilloway-checkout-layout',
        $url . 'assets/checkout-layout.css',
        array( 'pilloway-checkout-flow' ),
        file_exists( $path . 'assets/checkout-layout.css' ) ? (string) filemtime( $path . 'assets/checkout-layout.css' ) : '1.2.0'
    );
    wp_enqueue_script(
        'pilloway-checkout-flow',
        $url . 'assets/checkout-flow.js',
        array( 'jquery', 'wc-checkout' ),
        file_exists( $path . 'assets/checkout-flow.js' ) ? (string) filemtime( $path . 'assets/checkout-flow.js' ) : '1.2.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'pilloway_checkout_flow_enqueue_assets', 30 );

add_action(
    'init',
    function () {
        load_plugin_textdomain(
            'pilloway-checkout-flow',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }
);

/**
 * Use a concise, translated agreement phrase and WooCommerce's current terms page.
 *
 * @param string $text Default WooCommerce checkbox text.
 * @return string
 */
function pilloway_checkout_flow_terms_checkbox_text( $text ) {
    if ( ! function_exists( 'wc_terms_and_conditions_page_id' ) ) {
        return $text;
    }

    $terms_page_id = wc_terms_and_conditions_page_id();
    if ( ! $terms_page_id ) {
        return $text;
    }

    if ( function_exists( 'pll_current_language' ) && function_exists( 'pll_get_post' ) ) {
        $current_language = pll_current_language( 'slug' );
        $translated_page  = $current_language ? (int) pll_get_post( (int) $terms_page_id, $current_language ) : 0;
        if ( ! $translated_page ) {
            return $text;
        }
        $terms_page_id = $translated_page;
    }

    $terms_title = get_the_title( $terms_page_id );
    $terms_url   = get_permalink( $terms_page_id );
    if ( ! $terms_title || ! $terms_url ) {
        return $text;
    }

    $terms_link = sprintf(
        '<a href="%1$s" class="woocommerce-terms-and-conditions-link">%2$s</a>',
        esc_url( $terms_url ),
        esc_html( $terms_title )
    );

    return sprintf( __( 'I agree to the %s', 'pilloway-checkout-flow' ), $terms_link );
}
add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', 'pilloway_checkout_flow_terms_checkbox_text', 20 );
