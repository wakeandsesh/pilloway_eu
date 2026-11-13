<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require '/var/www/html/wp-load.php';

$eu=array('AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE');
update_option('woocommerce_allowed_countries','specific');
update_option('woocommerce_specific_allowed_countries',$eu);
update_option('woocommerce_ship_to_countries','specific');
update_option('woocommerce_specific_ship_to_countries',$eu);
update_option('woocommerce_default_customer_address','');

WC_Cache_Helper::get_transient_version('shipping',true);wp_cache_flush();echo 'Restricted billing and shipping to 27 EU countries.'.PHP_EOL;
