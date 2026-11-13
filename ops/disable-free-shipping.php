<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require '/var/www/html/wp-load.php';
$wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id='free_shipping'");
foreach($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_free_shipping_%_settings'") as $key)delete_option($key);
WC_Cache_Helper::get_transient_version('shipping',true);wp_cache_flush();
echo "EU free shipping removed from all zones.\n";
