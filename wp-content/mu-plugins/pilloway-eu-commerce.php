<?php
/** Plugin Name: Pilloway EU Commerce
 * Description: Country-aware checkout and payment availability for the EU storefront.
 */
defined('ABSPATH') || exit;

// The native WooCommerce country selector updates WCPBC prices and shipping during
// update_order_review, without a second selector or a reload that loses form data.
add_filter('woocommerce_checkout_fields', function ($fields) {
    if (isset($fields['billing']['billing_country'])) {
        $fields['billing']['billing_country']['priority'] = 5;
        $fields['billing']['billing_country']['required'] = true;
        $fields['billing']['billing_country']['class'][] = 'update_totals_on_change';
    }
    // International billing details are needed by the payment provider. The UA
    // checkout plugin removes them to avoid duplicating Nova Post's address.
    $country = WC()->customer ? WC()->customer->get_billing_country() : '';
    {
        $address = WC()->countries->get_address_fields($country, 'billing_');
        foreach (array('address_1','address_2','city','state','postcode') as $part) {
            if (isset($address['billing_'.$part])) {
                $fields['billing']['billing_'.$part] = $address['billing_'.$part];
                if ('UA' === $country) $fields['billing']['billing_'.$part]['required'] = false;
            }
        }
    }
    return $fields;
}, 40);

add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (is_admin() && !wp_doing_ajax()) return $gateways;
    $country = WC()->customer ? WC()->customer->get_billing_country() : '';
    $currency = get_woocommerce_currency();
    if ('UA' !== $country || 'UAH' !== $currency) unset($gateways['liqpay']);
    if ('UA' === $country || !in_array($currency, array('EUR','USD'), true)) unset($gateways['wayforpay']);
    // No EU bank beneficiary was supplied. Do not offer the copied UA account abroad.
    if ('UA' !== $country) unset($gateways['isi_bank_transfer'], $gateways['bacs'], $gateways['cheque']);
    if ('production' === wp_get_environment_type()) {
        foreach ($gateways as $id => $gateway) {
            if ('yes' === $gateway->get_option('testmode', 'no')) unset($gateways[$id]);
        }
    }
    return $gateways;
}, 100);

add_action('wp_enqueue_scripts', function () {
    if (!is_checkout() || is_order_received_page()) return;
    wp_add_inline_script('wc-checkout', "jQuery(function($){function addressVisibility(){var ua=$('#billing_country').val()==='UA'; $('body').toggleClass('pilloway-eu-ua-address',ua);}$(document.body).on('change','#billing_country',addressVisibility).on('updated_checkout',addressVisibility);addressVisibility();});");
    wp_register_style('pilloway-eu-address', false);wp_enqueue_style('pilloway-eu-address');
    wp_add_inline_style('pilloway-eu-address', '.pilloway-eu-ua-address #billing_address_1_field,.pilloway-eu-ua-address #billing_address_2_field,.pilloway-eu-ua-address #billing_city_field,.pilloway-eu-ua-address #billing_state_field,.pilloway-eu-ua-address #billing_postcode_field{display:none!important}');
}, 100);
