<?php
/** Reuse existing archive translations for product reviews. Dry run default; --apply writes backup first. CLI only. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$_SERVER['HTTP_HOST']='127.0.0.1:8098';$_SERVER['REQUEST_URI']='/';require dirname(__DIR__).'/wp-load.php';require __DIR__.'/product-review-counters.php';
function pr_norm($s){return trim(preg_replace('/\s+/u',' ',html_entity_decode(wp_strip_all_tags($s),ENT_QUOTES|ENT_HTML5,'UTF-8')));}
function pr_cards($s){
 $d=new DOMDocument();$old=libxml_use_internal_errors(true);$d->loadHTML('<?xml encoding="utf-8" ?>'.$s);libxml_clear_errors();libxml_use_internal_errors($old);$x=new DOMXPath($d);$out=[];
 foreach($x->query('//article[contains(@class,"pilloway-review-card")]') as $a){
  $get=fn($c)=>$x->query('.//*[contains(@class,"'.$c.'")]',$a)->item(0);
  $author=$get('pilloway-review-author');$date=$get('pilloway-review-date');$stars=$get('pilloway-review-stars');$body=$get('pilloway-review-body');$rating=0;
  if($stars&&preg_match('/([1-5])\s*\/\s*5/',$stars->getAttribute('aria-label'),$m))$rating=(int)$m[1];
  $html=$d->saveHTML($a);preg_match_all('/(?<![0-9])([0-9]{9,12})(?![0-9])/',$html,$ids);
  $out[]=['source_ids'=>$ids[1],'key'=>pr_norm($author?$author->textContent:'').'|'.($date?$date->getAttribute('datetime'):'').'|'.$rating,'text'=>$body?pr_norm($body->textContent):''];
 }return $out;
}
$page=get_page_by_path('vidguky-pilloway');if(!$page||!function_exists('pll_get_post_translations'))throw new RuntimeException('Missing archive or Polylang');
$archive=[];foreach(pll_get_post_translations($page->ID) as $l=>$id)$archive[$l]=pr_cards(get_post_field('post_content',$id));
$index=[];foreach($archive['uk'] as $i=>$a)$index[$a['key']][]=$i;
$groups=[];foreach(get_comments(['type'=>'review','status'=>'approve','number'=>0,'orderby'=>'comment_ID','order'=>'ASC']) as $c){
 $key=get_comment_meta($c->comment_ID,'_pilloway_prom_source_key',true);if(!$key)continue;$tr=pll_get_post_translations($c->comment_post_ID);$root=$tr['uk']??min($tr?:[$c->comment_post_ID]);$g=$root.'|'.$key;
 if(!isset($groups[$g])||pll_get_post_language($c->comment_post_ID)==='uk')$groups[$g]=$c;
}
$plan=[];$skips=[];
foreach($groups as $c){
 $rating=(int)get_comment_meta($c->comment_ID,'rating',true);$key=pr_norm($c->comment_author).'|'.substr($c->comment_date,0,10).'|'.$rating;$matches=$index[$key]??[];$original=pr_norm((string)get_comment_meta($c->comment_ID,'_pilloway_prom_original_text',true));
 if(count($matches)>1){$source_id=(string)get_comment_meta($c->comment_ID,'_pilloway_prom_source_id',true);$by_product=array_values(array_filter($matches,fn($i)=>in_array($source_id,$archive['uk'][$i]['source_ids'],true)));if(count($by_product)===1)$matches=$by_product;else{$translations=get_comment_meta($c->comment_ID,'_pilloway_prom_translations',true);$uk=pr_norm(is_array($translations)?($translations['uk']??''):'');$matches=array_values(array_filter($matches,fn($i)=>in_array($archive['uk'][$i]['text'],array_filter([$original,$uk,pr_norm($c->comment_content)]),true)));}}
 if(count($matches)!==1){$skips['missing_or_ambiguous']=($skips['missing_or_ambiguous']??0)+1;$skips['unmatched_sources'][]=['comment'=>(int)$c->comment_ID,'product'=>(int)$c->comment_post_ID];continue;}$i=$matches[0];
 foreach(pll_get_post_translations($c->comment_post_ID) as $l=>$id){
  if(in_array($l,['uk','ru'],true))continue;
  if(!isset($archive[$l][$i])||$archive[$l][$i]['key']!==$key){$skips['identity_mismatch']=($skips['identity_mismatch']??0)+1;continue;}
  $text=$archive[$l][$i]['text'];if($original===''&&trim($c->comment_content)==='')$text='';if($original!==''&&$text===''){$skips['missing_text']=($skips['missing_text']??0)+1;continue;}
  $old=get_comments(['post_id'=>$id,'type'=>'review','status'=>'all','number'=>0,'meta_key'=>'_pilloway_prom_source_key','meta_value'=>get_comment_meta($c->comment_ID,'_pilloway_prom_source_key',true)]);
  if(count($old)>1)throw new RuntimeException('Duplicate target review');$old=$old[0]??null;
  if($old&&get_comment_meta($old->comment_ID,'_pilloway_archive_translation',true)!=='1')continue;
  if($old&&$old->comment_content===$text&&$old->comment_approved==='1')continue;
  $plan[]=['source'=>$c,'target'=>$id,'lang'=>$l,'text'=>$text,'old'=>$old];
 }
}
$apply=in_array('--apply',$argv,true);$summary=['mode'=>$apply?'apply':'dry-run','source_reviews'=>count($groups),'planned'=>count($plan),'languages'=>array_count_values(array_column($plan,'lang')),'skipped'=>$skips];
if($apply&&$plan){
 $dir=sys_get_temp_dir().'/pilloway-review-backups';if(!is_dir($dir))mkdir($dir,0700,true);$backup=$dir.'/product-reviews-'.gmdate('Ymd-His').'.json';
 $manifest=['before'=>[],'planned'=>[],'created_ids'=>[]];foreach($plan as $r){$manifest['planned'][]=['source_id'=>$r['source']->comment_ID,'target'=>$r['target'],'lang'=>$r['lang'],'source_key'=>get_comment_meta($r['source']->comment_ID,'_pilloway_prom_source_key',true)];if($r['old'])$manifest['before'][]=['comment'=>$r['old'],'meta'=>get_comment_meta($r['old']->comment_ID)];}
 if(file_put_contents($backup,json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))===false)throw new RuntimeException('Backup failed');chmod($backup,0600);$touched=[];
 global $wpdb; $wpdb->query('START TRANSACTION'); wp_defer_comment_counting(true); wp_suspend_cache_invalidation(true);
 register_shutdown_function(function() use ($wpdb) { $wpdb->query('ROLLBACK'); });
 foreach($plan as $r){
  $c=$r['source'];$data=['comment_post_ID'=>$r['target'],'comment_author'=>$c->comment_author,'comment_author_email'=>'','comment_author_url'=>'','comment_author_IP'=>'','comment_date'=>$c->comment_date,'comment_date_gmt'=>$c->comment_date_gmt,'comment_content'=>$r['text'],'comment_type'=>'review','comment_approved'=>1,'comment_parent'=>0,'user_id'=>0];
  if($r['old']){$data['comment_ID']=$r['old']->comment_ID;$id=wp_update_comment(wp_slash($data),true);}else{$id=wp_insert_comment(wp_slash($data));}
  if(is_wp_error($id)||!$id)throw new RuntimeException('Write failed');
  foreach(['rating','_pilloway_prom_source_key','_pilloway_prom_source_id','_pilloway_prom_source_page','_pilloway_prom_original_text'] as $k)update_comment_meta($id,$k,get_comment_meta($c->comment_ID,$k,true));
  update_comment_meta($id,'verified','0');update_comment_meta($id,'_pilloway_prom_language',$r['lang']);update_comment_meta($id,'_pilloway_archive_translation','1');update_comment_meta($id,'_pilloway_archive_source_comment',(int)$c->comment_ID);
  if(!$r['old']){$manifest['created_ids'][]=$id;}$touched[$r['target']]=true;
 }
 $wpdb->query('COMMIT'); file_put_contents($backup,json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); wp_suspend_cache_invalidation(false); remove_action('wp_update_comment_count',['WC_Comments','clear_transients']); wp_defer_comment_counting(false);
 $summary['counters_refreshed']=pilloway_refresh_translated_review_counts();
 $summary['written']=count($plan);$summary['products']=count($touched);$summary['backup']=basename($backup);
}
echo json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;

