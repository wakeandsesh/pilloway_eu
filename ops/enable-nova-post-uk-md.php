<?php
/** Set up Nova Post pickup for the UK and Moldova. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!defined('ABSPATH')) {
    require dirname(__DIR__) . '/wp-load.php';
}

$markets = array('GB', 'MD');
$name = 'Nova Post pickup — UK and Moldova';
$match_zone = static function ($country) {
    return WC_Shipping_Zones::get_zone_matching_package(array(
        'destination' => array('country' => $country, 'state' => '', 'postcode' => ''),
    ));
};
$zone = null;
foreach (WC_Shipping_Zones::get_zones() as $configured) {
    if ($name === $configured['zone_name']) {
        $zone = new WC_Shipping_Zone($configured['zone_id']);
        break;
    }
}
foreach ($markets as $country) {
    $matched = $match_zone($country);
    if ($matched->get_id() && (!$zone || $matched->get_id() !== $zone->get_id())) {
        fwrite(STDERR, "Review the existing WooCommerce shipping zone for {$country}.\n");
        exit(1);
    }
}
if (!$zone) {
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name($name);
    $zone->set_zone_order(0);
    foreach ($markets as $country) {
        $zone->add_location($country, 'country');
    }
    $zone->save();
}
$methods = wp_list_pluck($zone->get_shipping_methods(), 'id');
if (!in_array('nova_post_division', $methods, true) && !$zone->add_shipping_method('nova_post_division')) {
    fwrite(STDERR, "The Nova Post pickup method could not be added.\n");
    exit(1);
}
WC_Cache_Helper::get_transient_version('shipping', true);
foreach ($markets as $country) {
    $matched = $match_zone($country);
    $methods = wp_list_pluck($matched->get_shipping_methods(true), 'id');
    if ($matched->get_id() !== $zone->get_id() || !in_array('nova_post_division', $methods, true)) {
        fwrite(STDERR, "The enabled Nova Post pickup method did not match {$country}.\n");
        exit(1);
    }
}
foreach (array('woocommerce_specific_allowed_countries', 'woocommerce_specific_ship_to_countries') as $option) {
    $configured = (array) get_option($option, array());
    update_option($option, array_values(array_unique(array_merge($configured, $markets))));
}
echo "Nova Post pickup enabled for GB and MD.\n";
