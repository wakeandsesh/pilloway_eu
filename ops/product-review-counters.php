<?php
/** Recalculate imported review counters without product-save side effects. */
function pilloway_refresh_translated_review_counts() {
 global $wpdb;
 $ids=array_map('intval',$wpdb->get_col("SELECT DISTINCT c.comment_post_ID FROM {$wpdb->comments} c JOIN {$wpdb->commentmeta} m ON m.comment_id=c.comment_ID WHERE m.meta_key='_pilloway_archive_translation' AND m.meta_value='1'"));
 if(!$ids)return 0;$list=implode(',',$ids);
 $counts=WC_Comments::get_review_counts_for_product_ids($ids);$ratings=[];
 foreach($wpdb->get_results("SELECT c.comment_post_ID id, m.meta_value rating, COUNT(*) n FROM {$wpdb->comments} c JOIN {$wpdb->commentmeta} m ON m.comment_id=c.comment_ID WHERE c.comment_post_ID IN ($list) AND c.comment_approved='1' AND m.meta_key='rating' AND CAST(m.meta_value AS UNSIGNED) BETWEEN 1 AND 5 GROUP BY c.comment_post_ID,m.meta_value") as $row)$ratings[$row->id][(int)$row->rating]=(int)$row->n;
 $wpdb->query('START TRANSACTION');
 foreach($ids as $id){$rc=$ratings[$id]??[];$n=array_sum($rc);$sum=0;foreach($rc as $rating=>$num)$sum+=$rating*$num;$avg=$n?number_format($sum/$n,2,'.',''):'0';
  foreach(['_wc_review_count'=>$counts[$id]??0,'_wc_average_rating'=>$avg,'_wc_rating_count'=>maybe_serialize($rc)] as $key=>$value){
   $exists=$wpdb->get_var($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s LIMIT 1",$id,$key));
   if($exists)$ok=$wpdb->update($wpdb->postmeta,['meta_value'=>$value],['post_id'=>$id,'meta_key'=>$key]);else $ok=$wpdb->insert($wpdb->postmeta,['post_id'=>$id,'meta_key'=>$key,'meta_value'=>$value]);
   if($ok===false)throw new RuntimeException('Review counter update failed');
  }
  $wpdb->update($wpdb->posts,['comment_count'=>$counts[$id]??0],['ID'=>$id]);
  $wpdb->update($wpdb->wc_product_meta_lookup,['rating_count'=>$n,'average_rating'=>$avg],['product_id'=>$id]);
  wp_cache_delete($id,'post_meta');wp_cache_delete($id,'posts');WC_Cache_Helper::invalidate_cache_group('product_'.$id);
 }
 $wpdb->query('COMMIT');wp_cache_set_comments_last_changed();return count($ids);
}
