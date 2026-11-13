<?php
/** Plugin Name: Pilloway EU Commerce
 * Description: Country-aware checkout and payment availability for the EU storefront.
 */
defined('ABSPATH') || exit;

// The native WooCommerce country selector updates shipping during
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
            }
        }
    }
    return $fields;
}, 40);

add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (is_admin() && !wp_doing_ajax()) return $gateways;
    $currency = get_woocommerce_currency();
    unset($gateways['liqpay']);
    if ('EUR' !== $currency) unset($gateways['wayforpay']);
    // No EU bank beneficiary was supplied. Do not offer the copied UA account abroad.
    unset($gateways['isi_bank_transfer'], $gateways['bacs'], $gateways['cheque']);
    if ('production' === wp_get_environment_type()) {
        foreach ($gateways as $id => $gateway) {
            if ('yes' === $gateway->get_option('testmode', 'no')) unset($gateways[$id]);
        }
    }
    return $gateways;
}, 100);

/** Map cached on-sale IDs to the active Polylang language. */
function pilloway_eu_localize_sale_ids($ids) {
    if (!is_array($ids) || !function_exists('pll_current_language')) return $ids;
    $lang=pll_current_language();if(!$lang)return $ids;
    $localized=array();foreach($ids as $id){$translated=pll_get_post($id,$lang);if($translated)$localized[]=(int)$translated;}
    return array_values(array_unique($localized));
}
add_filter('pre_transient_wc_products_onsale','pilloway_eu_localize_sale_ids',30);
add_filter('transient_wc_products_onsale','pilloway_eu_localize_sale_ids',30);

/** EU never offers free shipping, including cached rates or free-shipping coupons. */
add_filter('woocommerce_shipping_free_shipping_is_available', '__return_false', PHP_INT_MAX);
add_filter('woocommerce_package_rates', function ($rates) {
    foreach ($rates as $id => $rate) {
        if ('free_shipping' === $rate->get_method_id()) unset($rates[$id]);
    }
    return $rates;
}, PHP_INT_MAX);

/** Only the 27 EU member states may be used for billing and delivery. */
function pilloway_eu_member_states() {
    return array('AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE');
}
function pilloway_eu_filter_countries($countries) {
    return array_intersect_key($countries, array_flip(pilloway_eu_member_states()));
}
add_filter('woocommerce_countries_allowed_countries','pilloway_eu_filter_countries',PHP_INT_MAX);
add_filter('woocommerce_countries_shipping_countries','pilloway_eu_filter_countries',PHP_INT_MAX);
// Do not preselect an old non-EU country saved in the customer session.
add_filter('woocommerce_checkout_get_value', function ($value,$input) {
    if (!in_array($input,array('billing_country','shipping_country'),true)) return $value;
    $country=$value;
    if (null===$country && WC()->customer) {
        $country='billing_country'===$input ? WC()->customer->get_billing_country() : WC()->customer->get_shipping_country();
    }
    return $country && !in_array($country,pilloway_eu_member_states(),true) ? '' : $value;
},100,2);
// Exclude stale cached rates for destinations outside the permitted shipping list.
add_filter('woocommerce_package_rates',function($rates,$package) {
    return in_array($package['destination']['country'] ?? '',pilloway_eu_member_states(),true) ? $rates : array();
},PHP_INT_MAX,2);

function pilloway_eu_validate_countries($data,$errors) {
    foreach(array('billing_country','shipping_country') as $key) {
        if('shipping_country'===$key && empty($data['ship_to_different_address'])) continue;
        if(!empty($data[$key]) && !in_array($data[$key],pilloway_eu_member_states(),true)) {
            $errors->add('pilloway_eu_'.$key,__('Please select a country / region.', 'woocommerce'),array('id'=>$key));
        }
    }
}
add_action('woocommerce_after_checkout_validation','pilloway_eu_validate_countries',100,2);
add_action('woocommerce_store_api_cart_errors',function($errors) {
    if(!WC()->customer)return;
    $data=array('billing_country'=>WC()->customer->get_billing_country(),'shipping_country'=>WC()->customer->get_shipping_country(),'ship_to_different_address'=>true);
    pilloway_eu_validate_countries($data,$errors);
},100);
