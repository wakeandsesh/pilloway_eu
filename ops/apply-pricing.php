<?php
/** Idempotent, transactional pricing migration for the simple-product EU catalog. */
if(PHP_SAPI !== 'cli'){http_response_code(404);exit;}
$_SERVER['HTTP_HOST']='127.0.0.1:8098';require '/var/www/html/wp-load.php';
$data=json_decode(file_get_contents(__DIR__.'/pricing-2026-11-12.json'),true,512,JSON_THROW_ON_ERROR);
function eu_price_query($sql){global $wpdb;$result=$wpdb->query($sql);if(false===$result)throw new RuntimeException($wpdb->last_error);return $result;}
function eu_price_meta($key,$expression){global $wpdb;$k=$wpdb->prepare('%s',$key);eu_price_query("UPDATE {$wpdb->postmeta} m JOIN pilloway_eu_price_stage s ON s.id=m.post_id SET m.meta_value=$expression WHERE m.meta_key=$k");eu_price_query("INSERT INTO {$wpdb->postmeta}(post_id,meta_key,meta_value) SELECT s.id,$k,$expression FROM pilloway_eu_price_stage s LEFT JOIN {$wpdb->postmeta} m ON m.post_id=s.id AND m.meta_key=$k WHERE m.meta_id IS NULL");}
$schema='id BIGINT UNSIGNED PRIMARY KEY';foreach(['UAH','EUR','USD'] as $c)foreach(['regular','sale','price'] as $f)$schema.=", {$c}_{$f} VARCHAR(32) NOT NULL";
eu_price_query('CREATE TEMPORARY TABLE pilloway_eu_price_stage ('.$schema.')');
foreach(array_chunk($data['products'],200) as $chunk){$values=[];foreach($chunk as $r){$v=[$r['id']];foreach(['UAH','EUR','USD'] as $c)foreach(['regular','sale','price'] as $f)$v[]=$r['prices'][$c][$f];$values[]=$wpdb->prepare('('.implode(',',array_fill(0,count($v),'%s')).')',$v);}eu_price_query('INSERT INTO pilloway_eu_price_stage VALUES '.implode(',',$values));}
$zones=[''=>'UAH','_ua-uah'=>'UAH','_europe-eur'=>'EUR','_rest-usd'=>'USD'];
// Product lookup synchronization below is for simple products. Fail rather than
// incorrectly apply that algorithm to a later catalog containing variable parents.
$variable=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} t ON t.term_taxonomy_id=r.term_taxonomy_id JOIN {$wpdb->terms} n ON n.term_id=t.term_id JOIN pilloway_eu_price_stage s ON s.id=r.object_id WHERE t.taxonomy='product_type' AND n.slug='variable'");if($variable)throw new RuntimeException('Variable products require a dedicated parent price synchronization.');
eu_price_query('START TRANSACTION');
try{
 // Phase 1: remove all prior discounts and scheduled sales for these products.
 $keys=[];foreach($zones as $prefix=>$c)foreach(['_sale_price','_sale_price_dates_from','_sale_price_dates_to'] as $key)$keys[]=$prefix.$key;
 $in=implode(',',array_map(fn($k)=>$wpdb->prepare('%s',$k),$keys));eu_price_query("UPDATE {$wpdb->postmeta} m JOIN pilloway_eu_price_stage s ON s.id=m.post_id SET m.meta_value='' WHERE m.meta_key IN ($in)");
 // Phase 2: mark up the immutable pre-discount regular prices by 15%, ceil.
 foreach($zones as $prefix=>$c){eu_price_meta($prefix.'_regular_price','s.'.$c.'_regular');eu_price_meta($prefix.'_price','s.'.$c.'_regular');if($prefix)eu_price_meta($prefix.'_price_method',"'manual'");}
 // Phase 3: apply 30% only to the actual UA sale membership, ceil final price.
 foreach($zones as $prefix=>$c){eu_price_meta($prefix.'_sale_price','s.'.$c.'_sale');eu_price_meta($prefix.'_price','s.'.$c.'_price');}
 eu_price_query("UPDATE {$wpdb->prefix}wc_product_meta_lookup l JOIN pilloway_eu_price_stage s ON s.id=l.product_id SET l.min_price=CAST(s.UAH_price AS DECIMAL(19,4)),l.max_price=CAST(s.UAH_price AS DECIMAL(19,4)),l.onsale=IF(s.UAH_sale='',0,1)");
 eu_price_query('COMMIT');
}catch(Throwable $e){$wpdb->query('ROLLBACK');throw $e;}
$regions=get_option('wc_price_based_country_regions');foreach(['ua-uah'=>'UAH','europe-eur'=>'EUR','rest-usd'=>'USD'] as $z=>$c)$regions[$z]['exchange_rate']=$data['rates'][$c];update_option('wc_price_based_country_regions',$regions);
update_option('pilloway_eu_exchange_rate_audit',['date'=>$data['rate_date'],'rates'=>$data['rates'],'rule'=>$data['rule'],'products'=>count($data['products']),'sale_products'=>count(array_filter($data['products'],fn($r)=>$r['ua_on_sale']))]);
wc_delete_product_transients();WC_Cache_Helper::get_transient_version('product',true);wp_cache_flush();
echo json_encode(get_option('pilloway_eu_exchange_rate_audit'),JSON_PRETTY_PRINT).PHP_EOL;
