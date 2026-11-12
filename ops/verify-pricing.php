<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$_SERVER['HTTP_HOST']='127.0.0.1:8098';require '/var/www/html/wp-load.php';
$m=json_decode(file_get_contents(__DIR__.'/pricing-2026-11-12.json'),true);$errors=[];$sale=0;
$zones=['UAH'=>'ua-uah','EUR'=>'europe-eur','USD'=>'rest-usd'];
foreach($m['products'] as $r){$id=$r['id'];foreach($zones as $currency=>$zone){foreach(['regular'=>'_regular_price','sale'=>'_sale_price','price'=>'_price'] as $field=>$key){$actual=get_post_meta($id,'_'.$zone.$key,true);if((string)$actual!==(string)$r['prices'][$currency][$field])$errors[]=[$id,$currency,$key,$actual,$r['prices'][$currency][$field]];if($currency==='UAH'&&(string)get_post_meta($id,$key,true)!==(string)$r['prices'][$currency][$field])$errors[]=[$id,'base',$key,get_post_meta($id,$key,true),$r['prices'][$currency][$field]];}}
if($r['ua_on_sale'])$sale++;
}
$actual_sale=array_map('intval',$wpdb->get_col("SELECT l.product_id FROM {$wpdb->prefix}wc_product_meta_lookup l JOIN {$wpdb->posts} p ON p.ID=l.product_id WHERE l.onsale=1 AND p.post_status='publish'"));sort($actual_sale);$expected=array_column(array_filter($m['products'],fn($r)=>$r['ua_on_sale']),'id');sort($expected);
if($actual_sale!==$expected)$errors[]=['on_sale_lookup_mismatch','actual'=>count($actual_sale),'expected'=>count($expected)];
echo json_encode(['checked_products'=>count($m['products']),'checked_currencies'=>array_keys($zones),'sale_products'=>$sale,'current_language_on_sale'=>count(wc_get_product_ids_on_sale()),'errors'=>$errors],JSON_PRETTY_PRINT).PHP_EOL;exit(count($errors)?1:0);
