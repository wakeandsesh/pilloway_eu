<?php
/** Nova Post selection before its destination-dependent price is available. */
defined( 'ABSPATH' ) || exit;

function pilloway_checkout_flow_shipping_destination( $packages ) {
    $class = 'NovaPostForWoocommerce\\Models\\UserStorage';
    if ( ! class_exists( $class ) ) {
        return $packages;
    }
    $storage = $class::get_instance();
    // Include widget state in WooCommerce's package hash to invalidate stale rates.
    foreach ( $packages as &$package ) {
        $package['pilloway_nova_division'] = array(
            $storage->get( 'division_id' ),
            $storage->get( 'division' ),
        );
    }
    unset( $package );
    return $packages;
}
add_filter( 'woocommerce_cart_shipping_packages', 'pilloway_checkout_flow_shipping_destination', 30 );

function pilloway_checkout_flow_pending_nova_rate( $rates, $package ) {
    $zone = WC_Shipping_Zones::get_zone_matching_package( $package );
    foreach ( $zone->get_shipping_methods( true ) as $method ) {
        if ( 'nova_post_division' !== $method->id || ! $method->is_available( $package ) ) {
            continue;
        }
        $id = $method->get_rate_id();
        if ( isset( $rates[ $id ] ) ) {
            continue;
        }
        // This option opens the picker. It must never be accepted as a final quote.
        $rate = new WC_Shipping_Rate( $id, $method->title, 0, array(), $method->id, $method->instance_id );
        $rate->add_meta_data( 'pilloway_pending_quote', true );
        $rates[ $id ] = $rate;
    }
    return $rates;
}
add_filter( 'woocommerce_package_rates', 'pilloway_checkout_flow_pending_nova_rate', 30, 2 );

function pilloway_checkout_flow_pending_nova_label( $label, $rate ) {
    if ( ! empty( $rate->get_meta_data()['pilloway_pending_quote'] ) ) {
        return esc_html( $rate->get_label() ) . ' — ' . esc_html__( 'Оберіть відділення для розрахунку доставки', 'pilloway-checkout-flow' );
    }
    return $label;
}
add_filter( 'woocommerce_cart_shipping_method_full_label', 'pilloway_checkout_flow_pending_nova_label', 30, 2 );

function pilloway_checkout_flow_validate_shipping_quote( $data, $errors ) {
    if ( ! WC()->shipping() ) {
        return;
    }
    foreach ( WC()->shipping()->get_packages() as $index => $package ) {
        $chosen = $data['shipping_method'][ $index ] ?? '';
        $rate = $package['rates'][ $chosen ] ?? null;
        if ( $rate && ! empty( $rate->get_meta_data()['pilloway_pending_quote'] ) ) {
            $errors->add( 'pilloway_shipping_quote', __( 'Оберіть відділення Нової пошти та дочекайтеся розрахунку доставки.', 'pilloway-checkout-flow' ) );
            return;
        }
    }
}
add_action( 'woocommerce_after_checkout_validation', 'pilloway_checkout_flow_validate_shipping_quote', 30, 2 );

/** Apply the same unpaid-quote guard if a client uses the Store API. */
function pilloway_checkout_flow_validate_store_shipping_quote( $errors ) {
    $chosen = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
    pilloway_checkout_flow_validate_shipping_quote( array( 'shipping_method' => $chosen ), $errors );
}
add_action( 'woocommerce_store_api_cart_errors', 'pilloway_checkout_flow_validate_store_shipping_quote', 30 );
