<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require '/var/www/html/wp-load.php';
$m=json_decode(file_get_contents(__DIR__.'/pricing-2026-11-12.json'),true);$errors=[];$sale=0;
foreach($m['products'] as $r){
 $expected=(int)ceil((float)$r['original_regular_uah']*1.15*(float)$m['rates']['EUR']);
 if($expected!==(int)$r['prices']['EUR']['regular'])$errors[]=['formula',$r['id']];
 if($r['ua_on_sale'] && (int)ceil($expected*.70)!==(int)$r['prices']['EUR']['sale'])$errors[]=['sale_formula',$r['id']];
 foreach(['regular'=>'_regular_price','sale'=>'_sale_price','price'=>'_price'] as $field=>$key)if((string)get_post_meta($r['id'],$key,true)!==(string)$r['prices']['EUR'][$field])$errors[]=[$r['id'],$key];
 if($r['ua_on_sale'])$sale++;
}
if(get_woocommerce_currency()!=='EUR')$errors[]=['currency'];
if(in_array('woocommerce-product-price-based-on-countries/woocommerce-product-price-based-on-countries.php',get_option('active_plugins',[]),true))$errors[]=['multicurrency_active'];
$actual=array_map('intval',$wpdb->get_col("SELECT l.product_id FROM {$wpdb->prefix}wc_product_meta_lookup l JOIN {$wpdb->posts} p ON p.ID=l.product_id WHERE l.onsale=1 AND p.post_status='publish'"));sort($actual);$expected=array_column(array_filter($m['products'],fn($r)=>$r['ua_on_sale']),'id');sort($expected);if($actual!==$expected)$errors[]=['sale_lookup'];
echo json_encode(['currency'=>get_woocommerce_currency(),'products'=>count($m['products']),'sale_products'=>$sale,'errors'=>$errors],JSON_PRETTY_PRINT).PHP_EOL;exit($errors?1:0);
