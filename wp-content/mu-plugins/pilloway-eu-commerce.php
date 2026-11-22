<?php
/** Plugin Name: Pilloway EU Commerce
 * Description: Country-aware checkout and payment availability for the EU storefront.
 */
defined('ABSPATH') || exit;

// Nova Post collects the delivery details for the selected shipping method.
add_filter('woocommerce_checkout_fields', function ($fields) {
    $billing = array(
        'billing_country'    => true,
        'billing_first_name' => true,
        'billing_last_name'  => true,
        'billing_phone'      => true,
        'billing_email'      => false,
    );

    $fields['billing'] = array_intersect_key($fields['billing'], $billing);
    foreach ($fields['billing'] as $key => &$field) {
        $field['required'] = $billing[$key];
    }
    unset($field);

    $fields['billing']['billing_country']['priority'] = 5;
    $fields['billing']['billing_country']['class'][] = 'update_totals_on_change';
    $fields['shipping'] = array();
    return $fields;
}, 100);

add_filter('woocommerce_cart_needs_shipping_address', function ($needs_address) {
    return is_checkout() ? false : $needs_address;
});

add_action('wp_loaded', function () {
    remove_action('woocommerce_after_checkout_validation', 'pilloway_checkout_flow_validate_shipping_email', 20);
});

add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (is_admin() && !wp_doing_ajax()) return $gateways;
    $gateways = array_intersect_key($gateways, array('liqpay' => true, 'isi_bank_transfer' => true));
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
function pilloway_eu_pickup_countries() {
    return array('AT','CZ','DE','EE','ES','FR','HU','IT','LT','LV','NL','PL','RO','SK');
}
function pilloway_eu_filter_countries($countries) {
    return array_intersect_key($countries, array_flip(pilloway_eu_pickup_countries()));
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
    return $country && !in_array($country,pilloway_eu_pickup_countries(),true) ? '' : $value;
},100,2);
// Recalculate previously cached WooCommerce rates after switching to pickup-only delivery.
add_filter('woocommerce_cart_shipping_packages', function ($packages) {
    foreach ($packages as &$package) {
        $package['pilloway_nova_pickup_only'] = true;
    }
    unset($package);
    return $packages;
});

// Restrict rates to Nova Post pickup in supported destinations.
add_filter('woocommerce_package_rates',function($rates,$package) {
    if (!in_array($package['destination']['country'] ?? '', pilloway_eu_pickup_countries(), true)) {
        return array();
    }
    return array_filter($rates, static function ($rate) {
        return 'nova_post_division' === $rate->get_method_id();
    });
},PHP_INT_MAX,2);

function pilloway_eu_validate_countries($data,$errors) {
    foreach(array('billing_country','shipping_country') as $key) {
        if('shipping_country'===$key && empty($data['ship_to_different_address'])) continue;
        if(!empty($data[$key]) && !in_array($data[$key],pilloway_eu_pickup_countries(),true)) {
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
