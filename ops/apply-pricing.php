<?php
/** Idempotent EUR-only migration. Back up the database first. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$m=json_decode(file_get_contents(__DIR__.'/pricing-2026-11-12.json'),true,512,JSON_THROW_ON_ERROR);
$wpdb->query('START TRANSACTION');
try {
 foreach($m['products'] as $row) {
  $id=$row['id'];
  if ('product'!==get_post_type($id)) throw new RuntimeException('Missing product '.$id);
  // Immutable EUR results already include the agreed markup and sale rounding.
  foreach(['regular'=>'_regular_price','sale'=>'_sale_price','price'=>'_price'] as $field=>$key) update_post_meta($id,$key,(string)$row['prices']['EUR'][$field]);
  foreach(['_sale_price_dates_from','_sale_price_dates_to'] as $key) delete_post_meta($id,$key);
  $price=(float)$row['prices']['EUR']['price'];
  if(false===$wpdb->update($wpdb->prefix.'wc_product_meta_lookup',['min_price'=>$price,'max_price'=>$price,'onsale'=>$row['ua_on_sale']?1:0],['product_id'=>$id])) throw new RuntimeException($wpdb->last_error);
 }
 update_option('woocommerce_currency','EUR');

$eu=array('AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE');
update_option('woocommerce_allowed_countries','specific');
update_option('woocommerce_specific_allowed_countries',$eu);
update_option('woocommerce_ship_to_countries','specific');
update_option('woocommerce_specific_ship_to_countries',$eu);
update_option('woocommerce_default_customer_address','');

 deactivate_plugins('woocommerce-product-price-based-on-countries/woocommerce-product-price-based-on-countries.php');
 foreach($wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key REGEXP '^_(ua-uah|europe-eur|rest-usd)_'") as $key) delete_post_meta_by_key($key);
 foreach($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wc_price_based_country%' OR option_name LIKE 'wcpbc_%'") as $key) delete_option($key);
 // No free shipping in any EU storefront zone, regardless of destination.
 $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id='free_shipping'");
 foreach($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_free_shipping_%_settings'") as $key) delete_option($key);
 WC_Cache_Helper::get_transient_version('shipping',true);
 update_option('pilloway_eu_exchange_rate_audit',['currency'=>'EUR','rate_date'=>$m['rate_date'],'rate'=>$m['rates']['EUR'],'rule'=>$m['rule'],'products'=>count($m['products'])]);
 $wpdb->query('COMMIT');
} catch(Throwable $e) {$wpdb->query('ROLLBACK');throw $e;}
wc_delete_product_transients();WC_Cache_Helper::get_transient_version('product',true);wp_cache_flush();
echo "EUR-only migration complete; existing EUR prices preserved.\n";
