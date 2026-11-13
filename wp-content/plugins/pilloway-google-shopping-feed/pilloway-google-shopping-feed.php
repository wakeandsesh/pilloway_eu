<?php
/**
 * Plugin Name: Pilloway Google Shopping Feed
 * Description: Google Merchant Center product feed for Pilloway EU (English, EUR).
 * Version: 1.2.0
 * Requires Plugins: woocommerce
 * Text Domain: pilloway-google-shopping-feed
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Pilloway_Google_Shopping_Feed {
 const OPTION='pilloway_gmc_settings', STATS='pilloway_gmc_feed_stats', FEED_HOOK='pilloway_gmc_regenerate_feed', SINGLE_HOOK='pilloway_gmc_regenerate_feed_single', CRON='pilloway_gmc_six_hours', QUERY='pilloway_gmc_feed', TOKEN_QUERY='pilloway_gmc_token';
 private static $instance=null; private $invalid_gtin_count=0; private $missing_identifier_count=0;
 public static function instance(){ if(null===self::$instance) self::$instance=new self(); return self::$instance; }
 private function __construct(){
  add_filter('cron_schedules',array($this,'cron_schedules')); add_filter('query_vars',array($this,'query_vars')); add_action('template_redirect',array($this,'serve_feed'),0);
  add_action(self::FEED_HOOK,array($this,'generate_feed')); add_action(self::SINGLE_HOOK,array($this,'generate_feed')); add_action('save_post_product',array($this,'schedule_after_product_save'),20,3);
  add_action('woocommerce_product_set_stock',array($this,'schedule_feed')); add_action('woocommerce_product_set_stock_status',array($this,'schedule_feed'));
  add_action('admin_menu',array($this,'admin_menu')); add_action('admin_post_pilloway_gmc_save_settings',array($this,'save_settings')); add_action('admin_post_pilloway_gmc_regenerate',array($this,'admin_regenerate'));
  add_action('admin_post_pilloway_gmc_sku_preview',array($this,'admin_sku_preview')); add_action('admin_post_pilloway_gmc_sku_apply',array($this,'admin_sku_apply'));
  add_action('add_meta_boxes_product',array($this,'add_product_meta_box')); add_action('save_post_product',array($this,'save_product_meta'),10,2); add_action('admin_notices',array($this,'admin_notice'));
 }
 public static function activate(){
  self::instance();
  $s=get_option(self::OPTION,array()); if(empty($s['token'])) $s['token']=wp_generate_password(48,false,false);
  $s=wp_parse_args($s,array('scope_mode'=>'categories','category_scope'=>'all','category_ids'=>array())); update_option(self::OPTION,$s);
  if(!wp_next_scheduled(self::FEED_HOOK)) wp_schedule_event(time()+60,self::CRON,self::FEED_HOOK);
 }
 public static function deactivate(){wp_clear_scheduled_hook(self::FEED_HOOK);wp_clear_scheduled_hook(self::SINGLE_HOOK);}
 public function cron_schedules($s){$s[self::CRON]=array('interval'=>6*HOUR_IN_SECONDS,'display'=>__('Every six hours','pilloway-google-shopping-feed'));return $s;}
 public function query_vars($vars){$vars[]=self::QUERY;$vars[]=self::TOKEN_QUERY;return $vars;}
 private function settings(){ $s=get_option(self::OPTION,array()); if(empty($s['token'])){$s['token']=wp_generate_password(48,false,false);update_option(self::OPTION,$s);} return wp_parse_args($s,array('scope_mode'=>'categories','category_scope'=>'all','category_ids'=>array(),'token'=>'')); }
 private function feed_path(){ $u=wp_upload_dir();if(!empty($u['error']))return false;$d=trailingslashit($u['basedir']).'pilloway-google-shopping-feed';if(!wp_mkdir_p($d))return false;return trailingslashit($d).'google-shopping-eu.xml'; }
 private function feed_url(){ $s=$this->settings();return add_query_arg(array(self::QUERY=>1,self::TOKEN_QUERY=>$s['token']),home_url('/')); }
 public function serve_feed(){
  if(!get_query_var(self::QUERY))return;$s=$this->settings();$given=(string)get_query_var(self::TOKEN_QUERY);
  if(!$given||!hash_equals((string)$s['token'],$given)){status_header(403);nocache_headers();exit('Forbidden');}
  $p=$this->feed_path();if(!$p||!is_readable($p)){status_header(503);nocache_headers();exit('Feed is not generated yet. Generate it in WooCommerce > Google Shopping Feed.');}
  nocache_headers();header('Content-Type: application/xml; charset=UTF-8');header('Content-Disposition: inline; filename="google-shopping-eu.xml"');readfile($p);exit;
 }
 public function schedule_feed(){if(!wp_next_scheduled(self::SINGLE_HOOK))wp_schedule_single_event(time()+30,self::SINGLE_HOOK);}
 public function schedule_after_product_save($id,$post,$update){if(wp_is_post_revision($id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||!$post||'product'!==$post->post_type)return;$this->schedule_feed();} private function each_product_batch($args,$callback){
  $page=1;$max=1;do{$query=$args;$query['limit']=40;$query['page']=$page;$query['paginate']=true;$previous=wp_suspend_cache_addition(false);try{$result=wc_get_products($query);$batch=(is_object($result)&&isset($result->products))?$result->products:array();$max=(is_object($result)&&isset($result->max_num_pages))?max(1,(int)$result->max_num_pages):$page;foreach($batch as $product)$callback($product);$has=!empty($batch);}finally{wp_suspend_cache_addition($previous);unset($result,$batch,$query);if(function_exists('wp_cache_flush_runtime'))wp_cache_flush_runtime();if(function_exists('gc_collect_cycles'))gc_collect_cycles();}$page++;}while($has&&$page<=$max);
 }
 public function generate_feed(){
  $previous=function_exists('PLL')?PLL()->curlang:null;
  if(function_exists('PLL'))PLL()->curlang=PLL()->model->get_language('en');
  $currency=static function(){return 'EUR';};add_filter('woocommerce_currency',$currency,999);
  try{return $this->generate_eu_feed();}finally{remove_filter('woocommerce_currency',$currency,999);if(function_exists('PLL'))PLL()->curlang=$previous;}
 }
 private function generate_eu_feed(){
  if(!function_exists('wc_get_products'))return false;$s=$this->settings();$args=array('status'=>'publish','return'=>'objects','orderby'=>'ID','order'=>'ASC');$english=get_posts(array('post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>-1,'lang'=>'en','suppress_filters'=>false));$args['include']=$english;$run=!empty($english);
  if('categories'===$s['scope_mode']&&'selected'===$s['category_scope']){$ids=array_values(array_filter(array_map('absint',(array)$s['category_ids'])));$slugs=array();foreach($ids as $term_id){$term_id=function_exists('pll_get_term')?(pll_get_term($term_id,'en')?:$term_id):$term_id;$term=get_term($term_id,'product_cat');if($term&&!is_wp_error($term))$slugs[]=$term->slug;}if($slugs)$args['category']=$slugs;else $run=false;}if('sale'===$s['scope_mode']){$on_sale=function_exists('wc_get_product_ids_on_sale')?wc_get_product_ids_on_sale():array();if($on_sale)$args['include']=$on_sale;else $run=false;}
  $items=array();$skip_lang=0;$skip_sale=0;$this->invalid_gtin_count=0;$this->missing_identifier_count=0;
  if($run)$this->each_product_batch($args,function($p)use(&$items,&$skip_lang,&$skip_sale,$s){if(!$p instanceof WC_Product||$p->is_type('variation'))return;if(function_exists('pll_get_post_language')){$lang=pll_get_post_language($p->get_id(),'slug');if($lang&&'en'!==$lang){$skip_lang++;return;}}if('sale'===$s['scope_mode']&&!$p->is_on_sale()){$skip_sale++;return;}$item=$this->product_item($p);if($item)$items[]=$item;});
  $path=$this->feed_path();if(!$path||false===file_put_contents($path,$this->render_xml($items),LOCK_EX))return false;
  update_option(self::STATS,array('generated_at'=>time(),'count'=>count($items),'skipped_language'=>$skip_lang,'skipped_sale'=>$skip_sale,'currency'=>get_woocommerce_currency(),'category_counts'=>$this->category_counts($items),'invalid_gtin'=>$this->invalid_gtin_count,'missing_identifiers'=>$this->missing_identifier_count),false);return true;
 }
 private function product_item($p){
  $id=$p->get_id();$image_id=$p->get_image_id();$image=$image_id?wp_get_attachment_image_url($image_id,'full'):'';if(!$image)return false;
  $title=$this->clean_text($p->get_name());$desc=$this->clean_text($p->get_description()?:$p->get_short_description());if(!$desc)$desc=$title;
  $sku=trim((string)$p->get_sku());$currency='EUR';$regular=$p->get_regular_price();$sale=$p->get_sale_price();if($sale!==''&&((float)$sale<=0||(float)$sale>=(float)$regular))$sale='';$shown=$sale!==''?$sale:$p->get_price();if($shown==='')return false;
  $base=$sale!==''?$regular:$shown;$price=$this->format_price(wc_get_price_to_display($p,array('price'=>$base)),$currency);$sale_price=$sale!==''?$this->format_price(wc_get_price_to_display($p,array('price'=>$sale)),$currency):'';
  $raw='';if(method_exists($p,'get_global_unique_id'))$raw=(string)$p->get_global_unique_id();if(!$raw)$raw=(string)get_post_meta($id,'_global_unique_id',true);$gtin=preg_replace('/\D+/','',$raw);$valid=$gtin&&$this->valid_gtin($gtin);if($raw&&!$valid)$this->invalid_gtin_count++;
  $mpn=trim((string)get_post_meta($id,'_pilloway_gmc_mpn',true));$explicit_no='no'===get_post_meta($id,'_pilloway_gmc_identifier_exists',true);$identifier_exists='';if($valid||$mpn)$identifier_exists='yes';elseif($explicit_no)$identifier_exists='no';else $this->missing_identifier_count++;$category=absint(get_post_meta($id,'_pilloway_gmc_category_id',true));if(!$category)$category=$this->infer_category($p);
  $item=array('id'=>$sku?$sku:'pilloway-'.$id,'title'=>$title,'description'=>$desc,'link'=>get_permalink($id),'image_link'=>esc_url_raw($image),'availability'=>$p->is_in_stock()?'in_stock':'out_of_stock','condition'=>'new','price'=>$price,'sale_price'=>$sale_price,'brand'=>$this->clean_text(get_post_meta($id,'_pilloway_gmc_brand',true)?:$this->brand($p)),'gtin'=>$valid?$gtin:'','mpn'=>$mpn,'identifier_exists'=>$identifier_exists,'google_product_category'=>(string)$category,'product_type'=>$this->product_type($p),'color'=>$this->attribute($p,array('pa_color','color','колір','цвет')),'material'=>$this->attribute($p,array('pa_material','material','матеріал','материал')));
  $weight=$p->get_weight();if($weight!==''&&(float)$weight>0)$item['shipping_weight']=number_format((float)wc_get_weight((float)$weight,'kg',get_option('woocommerce_weight_unit','kg')),3,'.','').' kg';
  $images=array();foreach((array)$p->get_gallery_image_ids() as $gid){$u=wp_get_attachment_image_url($gid,'full');if($u&&$u!==$image)$images[]=esc_url_raw($u);if(count($images)>=10)break;}$item['additional_image_link']=$images;return $item;
 }
 private function render_xml($items){
  $x='<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n";
  $x.='<title>'.esc_xml(get_bloginfo('name').' Google Shopping').'</title><link>'.esc_xml(home_url('/')).'</link><description>Pilloway product feed for Google Merchant Center</description>' . "\n";
  foreach($items as $item){$x.='<item>' . "\n";foreach($item as $key=>$value){if('additional_image_link'===$key){foreach((array)$value as $im)$x.='<g:additional_image_link>'.esc_xml($im).'</g:additional_image_link>' . "\n";continue;}if($value===''||$value===null)continue;$x.='<g:'.$key.'>'.esc_xml((string)$value).'</g:'.$key.'>' . "\n";}$x.='</item>' . "\n";}$x.='</channel></rss>';return $x;
 }
 private function clean_text($t){$t=strip_shortcodes((string)$t);$t=wp_strip_all_tags($t,true);$t=html_entity_decode($t,ENT_QUOTES|ENT_HTML5,'UTF-8');return trim(preg_replace('/\s+/u',' ',$t));}
 private function format_price($price,$currency){return number_format((float)wc_format_decimal($price,wc_get_price_decimals()),wc_get_price_decimals(),'.','').' '.$currency;}
 private function valid_gtin($g){if(!preg_match('/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/',$g))return false;$a=array_map('intval',str_split($g));$check=array_pop($a);$sum=0;$weight=3;for($i=count($a)-1;$i>=0;$i--){$sum+=$a[$i]*$weight;$weight=4-$weight;}return ((10-($sum%10))%10)===$check;}
 private function lower($t){return function_exists('mb_strtolower')?mb_strtolower((string)$t,'UTF-8'):strtolower((string)$t);}
 private function brand($p){$t=$this->lower($p->get_name());$map=array('beauty balance'=>'Beauty Balance','correct shape'=>'Correct Shape','family dream'=>'Family Dream','school comfort'=>'School Comfort');foreach($map as $needle=>$name)if(false!==strpos($t,$needle))return $name;if(false!==strpos($t,'mother')&&false!==strpos($t,'dream'))return "Mother's Dream";return 'Pilloway';}
 private function infer_category($p){
  $t=$this->lower($p->get_name());
  if(preg_match('/маск[аи]|eye.?mask|sleep.?mask/u',$t))return 4313;
  if(preg_match('/резинк[аи]|scrunch|hair.?tie|pony.?tail/u',$t))return 1483;
  if(preg_match('/подушк[аи].{0,30}(для )?(кормлен|грудн)|nursing pillow|breastfeeding pillow/u',$t))return 5298;
  if(preg_match('/наволоч|pillow.?case|sham\b/u',$t))return 2927;
  if(preg_match('/дорожн|travel|підков|подков/u',$t))return 4211;
  if(preg_match('/поперек|поясниц|lumbar|підтримк.{0,20}спин|поддержк.{0,20}спин|seat cushion|шкіл|школь|подушк[аи].{0,25}(для )?(сидін|сиден)/u',$t))return 7404;
  if(preg_match('/спинк.{0,20}кресл|backrest pillow/u',$t))return 4456;
  if(preg_match('/диван|крісло|кресло|sofa cushion|chair cushion/u',$t)&&!preg_match('/ортопед|поперек|поясниц/u',$t))return 4453;
  return 2700;
 }
 private function product_type($p){$n=wp_get_post_terms($p->get_id(),'product_cat',array('fields'=>'names'));return is_wp_error($n)?'':implode(' > ',array_map(array($this,'clean_text'),array_slice($n,0,3)));} private function attribute($p,$needles){foreach($p->get_attributes() as $a){if(!$a instanceof WC_Product_Attribute)continue;$name=$this->lower($a->get_name());foreach($needles as $n)if($name===$this->lower($n)||false!==strpos($name,$this->lower($n))){$v=wc_get_product_terms($p->get_id(),$a->get_name(),array('fields'=>'names'));if(!is_wp_error($v)&&$v)return $this->clean_text(implode(', ',$v));return $this->clean_text(implode(', ',$a->get_options()));}}return '';}
 private function category_counts($items){$c=array();foreach($items as $i){$n=$i['google_product_category']??'unset';$c[$n]=($c[$n]??0)+1;}ksort($c);return $c;}
 public function admin_menu(){add_submenu_page('woocommerce',__('Google Shopping Feed','pilloway-google-shopping-feed'),__('Google Shopping Feed','pilloway-google-shopping-feed'),'manage_woocommerce','pilloway-google-shopping-feed',array($this,'render_admin_page'));}
 private function categories(){ $a=array('taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'name','order'=>'ASC');if(function_exists('pll_languages_list'))$a['lang']='uk';$t=get_terms($a);return is_wp_error($t)?array():$t; }
 public function render_admin_page(){
  if(!current_user_can('manage_woocommerce'))return;$s=$this->settings();$stats=get_option(self::STATS,array());$next=wp_next_scheduled(self::FEED_HOOK);$flash=isset($_GET['pilloway_message'])?sanitize_text_field(wp_unslash($_GET['pilloway_message'])):'';
  echo '<div class="wrap"><h1>'.esc_html__('Google Merchant Center product feed','pilloway-google-shopping-feed').'</h1>';if($flash){$type=(isset($_GET['pilloway_message_type'])&&'error'===sanitize_key(wp_unslash($_GET['pilloway_message_type'])))?'error':'success';echo '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.esc_html($flash).'</p></div>';}
  echo '<p>'.esc_html__('This is a product feed for Merchant Center, not the WordPress XML sitemap.','pilloway-google-shopping-feed').'</p><h2>'.esc_html__('Feed URL','pilloway-google-shopping-feed').'</h2><p><code style="user-select:all">'.esc_html($this->feed_url()).'</code></p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('pilloway_gmc_save_settings');echo '<input type="hidden" name="action" value="pilloway_gmc_save_settings"><h2>'.esc_html__('Choose products for the feed','pilloway-google-shopping-feed').'</h2>';
  echo '<fieldset><legend class="screen-reader-text">'.esc_html__('Product selection mode','pilloway-google-shopping-feed').'</legend><label><input type="radio" name="scope_mode" value="categories" '.checked('categories',$s['scope_mode'],false).'> '.esc_html__('Products from categories','pilloway-google-shopping-feed').'</label><br><label><input type="radio" name="scope_mode" value="sale" '.checked('sale',$s['scope_mode'],false).'> '.esc_html__('Sale products only','pilloway-google-shopping-feed').'</label></fieldset>';
  echo '<div id="pilloway-category-options" style="margin:16px 0;padding:16px;border:1px solid #c3c4c7;max-width:900px"><h3>'.esc_html__('Category mode','pilloway-google-shopping-feed').'</h3><label><input type="radio" name="category_scope" value="all" '.checked('all',$s['category_scope'],false).'> '.esc_html__('All categories','pilloway-google-shopping-feed').'</label><br><label><input type="radio" name="category_scope" value="selected" '.checked('selected',$s['category_scope'],false).'> '.esc_html__('Only selected categories','pilloway-google-shopping-feed').'</label><div id="pilloway-category-checks" style="columns:2;max-width:760px;margin-top:12px">';
  $sel=array_map('absint',(array)$s['category_ids']);foreach($this->categories() as $term)echo '<label style="display:block;margin:6px 0"><input class="pilloway-gmc-category" type="checkbox" name="category_ids[]" value="'.esc_attr($term->term_id).'" '.checked(in_array((int)$term->term_id,$sel,true),true,false).'> '.esc_html($term->name).' <small>('.absint($term->count).')</small></label>';
  echo '</div></div><p><button class="button button-primary">'.esc_html__('Save selection','pilloway-google-shopping-feed').'</button></p></form>';
  echo '<script>(function(){const a=document.querySelectorAll("input[name=scope_mode]"),b=document.querySelectorAll("input[name=category_scope]"),w=document.getElementById("pilloway-category-options"),checks=document.getElementById("pilloway-category-checks"),c=document.querySelectorAll(".pilloway-gmc-category");function f(){let m=document.querySelector("input[name=scope_mode]:checked"),s=document.querySelector("input[name=category_scope]:checked"),on=m&&m.value==="categories",selected=s&&s.value==="selected";w.style.display=on?"block":"none";checks.style.opacity=selected?"1":".55";c.forEach(x=>x.disabled=!(on&&selected));}a.forEach(x=>x.addEventListener("change",f));b.forEach(x=>x.addEventListener("change",f));f();})();</script>';
  echo '<hr><h2>'.esc_html__('Regenerate and scheduled updates','pilloway-google-shopping-feed').'</h2>';
  if(!empty($stats['generated_at']))echo '<p>'.esc_html(sprintf(__('Last generated: %1$s; %2$d items; currency: %3$s.','pilloway-google-shopping-feed'),wp_date(get_option('date_format').' '.get_option('time_format'),$stats['generated_at']),(int)$stats['count'],$stats['currency'])).'</p>';else echo '<p>'.esc_html__('Feed has not been generated yet.','pilloway-google-shopping-feed').'</p>';
  if($next)echo '<p>'.esc_html(sprintf(__('Next scheduled refresh: %s','pilloway-google-shopping-feed'),wp_date(get_option('date_format').' '.get_option('time_format'),$next))).'</p>';

  if(!empty($stats['category_counts'])){echo '<ul>';foreach($stats['category_counts'] as $id=>$count)echo '<li>'.esc_html(sprintf(__('Google category %1$s: %2$d products','pilloway-google-shopping-feed'),$id,$count)).'</li>';echo '</ul>';}
  if(!empty($stats['invalid_gtin']))echo '<p class="notice notice-warning inline">'.esc_html(sprintf(__('%d invalid GTIN values were omitted.','pilloway-google-shopping-feed'),(int)$stats['invalid_gtin'])).'</p>';
  if(!empty($stats['missing_identifiers']))echo '<p class="notice notice-warning inline">'.esc_html(sprintf(__('%d products have no GTIN or MPN in WooCommerce. The feed leaves identifier_exists blank until you confirm on each product whether no identifier was assigned.','pilloway-google-shopping-feed'),(int)$stats['missing_identifiers'])).'</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('pilloway_gmc_regenerate');echo '<input type="hidden" name="action" value="pilloway_gmc_regenerate"><button class="button">'.esc_html__('Regenerate feed now','pilloway-google-shopping-feed').'</button></form><p class="description">'.esc_html__('WordPress Cron runs when the site receives traffic. Configure a server cron on hosting for reliable refreshes.','pilloway-google-shopping-feed').'</p>';  echo '<hr><h2>'.esc_html__('Generate brand-based SKUs for placeholder entries','pilloway-google-shopping-feed').'</h2><p>'.esc_html__('Only generated PROM-… SKUs and the placeholder 0000 will be changed. Existing valid SKUs are kept; the original placeholder is saved in metadata.','pilloway-google-shopping-feed').'</p>';
  echo '<form style="display:inline-block;margin-right:8px" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('pilloway_gmc_sku_preview');echo '<input type="hidden" name="action" value="pilloway_gmc_sku_preview"><button class="button">'.esc_html__('Preview SKU changes','pilloway-google-shopping-feed').'</button></form>';
  echo '<form style="display:inline-block" method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm('.esc_attr(wp_json_encode(__('Apply the listed SKU replacements?','pilloway-google-shopping-feed'))).')">';wp_nonce_field('pilloway_gmc_sku_apply');echo '<input type="hidden" name="action" value="pilloway_gmc_sku_apply"><button class="button button-secondary">'.esc_html__('Apply brand-based SKUs','pilloway-google-shopping-feed').'</button></form>';
  echo '<h2>'.esc_html__('Identifier policy','pilloway-google-shopping-feed').'</h2><p>'.esc_html__('GTIN is read from WooCommerce’s native unique product identifier field and validated. MPN is entered per product only when genuinely assigned by the manufacturer. Store SKUs and Prom codes are never claimed as GTIN or MPN.','pilloway-google-shopping-feed').'</p></div>';
 }
 public function save_settings(){
  if(!current_user_can('manage_woocommerce'))wp_die(esc_html__('You are not allowed to manage this feed.','pilloway-google-shopping-feed'));check_admin_referer('pilloway_gmc_save_settings');$old=$this->settings();
  $mode=isset($_POST['scope_mode'])?sanitize_key(wp_unslash($_POST['scope_mode'])):'categories';$scope=isset($_POST['category_scope'])?sanitize_key(wp_unslash($_POST['category_scope'])):'all';$mode=in_array($mode,array('categories','sale'),true)?$mode:'categories';$scope=in_array($scope,array('all','selected'),true)?$scope:'all';$ids=(array)$old['category_ids'];
  if('categories'===$mode&&'selected'===$scope){$ids=isset($_POST['category_ids'])?array_values(array_filter(array_map('absint',(array)wp_unslash($_POST['category_ids'])))):array();$valid=wp_list_pluck($this->categories(),'term_id');$ids=array_values(array_intersect($ids,$valid));if(!$ids)$this->redirect(__('Choose at least one category, or switch to all categories.','pilloway-google-shopping-feed'),'error');}
  update_option(self::OPTION,array('scope_mode'=>$mode,'category_scope'=>$scope,'category_ids'=>$ids,'token'=>$old['token']));$this->schedule_feed();$this->redirect(__('Feed selection saved; regeneration has been queued.','pilloway-google-shopping-feed'));
 }
 public function admin_regenerate(){if(!current_user_can('manage_woocommerce'))wp_die(esc_html__('Not allowed.','pilloway-google-shopping-feed'));check_admin_referer('pilloway_gmc_regenerate');$this->schedule_feed();$this->redirect(__('Feed regeneration has been queued for the next WordPress Cron run.','pilloway-google-shopping-feed'));}
 private function redirect($message,$type='success'){wp_safe_redirect(add_query_arg(array('page'=>'pilloway-google-shopping-feed','pilloway_message'=>$message,'pilloway_message_type'=>$type),admin_url('admin.php')));exit;}
 private function is_placeholder_sku($sku){return ''===$sku||'0000'===$sku||(bool)preg_match('/^PROM-\d+-(?:UK|RU)$/i',$sku);}
 private function sku_targets(){
  $out=array();$args=array('status'=>'publish','return'=>'objects','orderby'=>'ID','order'=>'ASC');$this->each_product_batch($args,function($p)use(&$out){if(function_exists('pll_get_post_language')){$lang=pll_get_post_language($p->get_id(),'slug');if($lang&&'uk'!==$lang)return;}$sku=trim((string)$p->get_sku());if(!$this->is_placeholder_sku($sku))return;$tr=function_exists('pll_get_post_translations')?pll_get_post_translations($p->get_id()):array('uk'=>$p->get_id());if(empty($tr['uk']))$tr['uk']=$p->get_id();foreach(array('uk','ru') as $l){if(empty($tr[$l]))continue;$q=wc_get_product($tr[$l]);if(!$q)continue;$old=trim((string)$q->get_sku());if($this->is_placeholder_sku($old))$out[]=array('product'=>$q,'language'=>$l,'old'=>$old);}});return $out;
 }
 private function generated_sku($p,$lang,$old){
  $name=$this->lower($p->get_name());$prefix='PY';foreach(array('beauty balance'=>'BB','correct shape'=>'CS','family dream'=>'FD','school comfort'=>'SC') as $n=>$code)if(false!==strpos($name,$n)){$prefix=$code;break;}if(false!==strpos($name,'mother')&&false!==strpos($name,'dream'))$prefix='MD';
  $source='';if(preg_match('/^PROM-(\d+)-/i',$old,$m))$source=$m[1];if(!$source)foreach(array('_pilloway_prom_id','_pilloway_source_id','_prom_id','prom_product_id') as $key){$source=preg_replace('/\D+/','',(string)get_post_meta($p->get_id(),$key,true));if($source)break;}
  if(!$source)$source=(string)$p->get_id();$source=ltrim($source,'0');if(''===$source)$source=(string)$p->get_id();return $prefix.'-'.$source.('ru'===$lang?'-RU':'-UA');
 }
 public function admin_sku_preview(){
  if(!current_user_can('manage_woocommerce'))wp_die(esc_html__('Not allowed.','pilloway-google-shopping-feed'));check_admin_referer('pilloway_gmc_sku_preview');$rows=array();foreach($this->sku_targets() as $t){$p=$t['product'];$rows[]=array('id'=>$p->get_id(),'name'=>$p->get_name(),'old'=>$t['old'],'new'=>$this->generated_sku($p,$t['language'],$t['old']));}
  set_transient('pilloway_gmc_sku_preview_'.get_current_user_id(),$rows,10*MINUTE_IN_SECONDS);$this->redirect(sprintf(__('SKU preview: %d product records will change. Review proposed codes below.','pilloway-google-shopping-feed'),count($rows)));
 }
 public function admin_sku_apply(){
  if(!current_user_can('manage_woocommerce'))wp_die(esc_html__('Not allowed.','pilloway-google-shopping-feed'));check_admin_referer('pilloway_gmc_sku_apply');$changed=0;$collisions=0;
  foreach($this->sku_targets() as $t){$p=$t['product'];$sku=$this->generated_sku($p,$t['language'],$t['old']);$base=$sku;$n=2;while(($found=wc_get_product_id_by_sku($sku))&&(int)$found!==(int)$p->get_id()){$sku=$base.'-'.$n++;$collisions++;}
   if(!get_post_meta($p->get_id(),'_pilloway_previous_sku',true))update_post_meta($p->get_id(),'_pilloway_previous_sku',$t['old']);$p->set_sku($sku);$p->save();$changed++;
  }$this->schedule_feed();$this->redirect(sprintf(__('Updated %1$d SKUs; collision suffixes used: %2$d. Feed regeneration has been queued.','pilloway-google-shopping-feed'),$changed,$collisions));
 } public function admin_notice(){
  if(!current_user_can('manage_woocommerce'))return;$screen=function_exists('get_current_screen')?get_current_screen():null;if(!$screen||'woocommerce_page_pilloway-google-shopping-feed'!==$screen->id)return;$rows=get_transient('pilloway_gmc_sku_preview_'.get_current_user_id());if(false===$rows)return;delete_transient('pilloway_gmc_sku_preview_'.get_current_user_id());
  echo '<div class="notice notice-info"><p>'.esc_html__('Proposed SKU changes:','pilloway-google-shopping-feed').'</p><ul style="max-height:320px;overflow:auto">';foreach($rows as $r)echo '<li>'.esc_html(sprintf('#%1$d %2$s: %3$s → %4$s',$r['id'],$r['name'],$r['old']?:'—',$r['new'])).'</li>';echo '</ul></div>';
 }
 public function add_product_meta_box(){add_meta_box('pilloway-gmc-product-fields',__('Google Shopping data','pilloway-google-shopping-feed'),array($this,'render_product_meta_box'),'product','side','default');}
 public function render_product_meta_box($post){
  wp_nonce_field('pilloway_gmc_product_meta','pilloway_gmc_product_nonce');$brand=get_post_meta($post->ID,'_pilloway_gmc_brand',true);$mpn=get_post_meta($post->ID,'_pilloway_gmc_mpn',true);$cat=absint(get_post_meta($post->ID,'_pilloway_gmc_category_id',true));$identifier=get_post_meta($post->ID,'_pilloway_gmc_identifier_exists',true);
  echo '<p><label for="pilloway_gmc_brand">'.esc_html__('Brand override (blank = automatic)','pilloway-google-shopping-feed').'</label><input class="widefat" id="pilloway_gmc_brand" name="pilloway_gmc_brand" value="'.esc_attr($brand).'"></p>';
  echo '<p><label for="pilloway_gmc_mpn">'.esc_html__('Manufacturer-assigned MPN only','pilloway-google-shopping-feed').'</label><input class="widefat" id="pilloway_gmc_mpn" name="pilloway_gmc_mpn" value="'.esc_attr($mpn).'"></p>';
  echo '<p><label for="pilloway_gmc_identifier_exists">'.esc_html__('Identifier status','pilloway-google-shopping-feed').'</label><select class="widefat" id="pilloway_gmc_identifier_exists" name="pilloway_gmc_identifier_exists"><option value="" '.selected($identifier,'',false).'>'.esc_html__('Not confirmed: omit this field','pilloway-google-shopping-feed').'</option><option value="no" '.selected($identifier,'no',false).'>'.esc_html__('No GTIN or MPN is assigned (confirmed)','pilloway-google-shopping-feed').'</option></select><span class="description">'.esc_html__('Choose “no” only after checking the product/package or manufacturer.','pilloway-google-shopping-feed').'</span></p>';
  echo '<p><label for="pilloway_gmc_category_id">'.esc_html__('Google product category','pilloway-google-shopping-feed').'</label><select class="widefat" id="pilloway_gmc_category_id" name="pilloway_gmc_category_id"><option value="0">'.esc_html__('Automatic from product data','pilloway-google-shopping-feed').'</option>';
  foreach($this->google_categories() as $id=>$label)echo '<option value="'.absint($id).'" '.selected($cat,$id,false).'>'.esc_html($id.' — '.$label).'</option>';echo '</select></p><p class="description">'.esc_html__('GTIN comes from WooCommerce’s unique product identifier. Do not enter a store SKU as MPN unless the manufacturer assigned that exact code.','pilloway-google-shopping-feed').'</p>';
 }
 public function save_product_meta($id,$post){
  if(!isset($_POST['pilloway_gmc_product_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pilloway_gmc_product_nonce'])),'pilloway_gmc_product_meta'))return;
  if((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||!$post||'product'!==$post->post_type||!current_user_can('edit_post',$id))return;
  $ident=isset($_POST['pilloway_gmc_identifier_exists'])&&'no'===sanitize_key(wp_unslash($_POST['pilloway_gmc_identifier_exists']))?'no':'';
  $values=array('_pilloway_gmc_brand'=>isset($_POST['pilloway_gmc_brand'])?sanitize_text_field(wp_unslash($_POST['pilloway_gmc_brand'])):'','_pilloway_gmc_mpn'=>isset($_POST['pilloway_gmc_mpn'])?sanitize_text_field(wp_unslash($_POST['pilloway_gmc_mpn'])):'','_pilloway_gmc_category_id'=>isset($_POST['pilloway_gmc_category_id'])?absint($_POST['pilloway_gmc_category_id']):0,'_pilloway_gmc_identifier_exists'=>$ident);
  foreach($values as $key=>$value){if(''===$value||0===$value)delete_post_meta($id,$key);else update_post_meta($id,$key,$value);}$this->schedule_feed();
 }
 private function google_categories(){return array(
  2700=>__('Pillows (orthopedic, sleep, pregnancy)','pilloway-google-shopping-feed'),2927=>__('Pillowcases & Shams','pilloway-google-shopping-feed'),7404=>__('Back & Lumbar Support Cushions','pilloway-google-shopping-feed'),4211=>__('Travel Pillows','pilloway-google-shopping-feed'),5298=>__('Nursing Pillows','pilloway-google-shopping-feed'),4453=>__('Chair & Sofa Cushions','pilloway-google-shopping-feed'),4456=>__('Backrest Pillows','pilloway-google-shopping-feed'),4313=>__('Eye Masks','pilloway-google-shopping-feed'),1483=>__('Ponytail Holders','pilloway-google-shopping-feed'));
 }
}
register_activation_hook(__FILE__,array('Pilloway_Google_Shopping_Feed','activate'));
register_deactivation_hook(__FILE__,array('Pilloway_Google_Shopping_Feed','deactivate'));
add_action('plugins_loaded',array('Pilloway_Google_Shopping_Feed','instance'),20);