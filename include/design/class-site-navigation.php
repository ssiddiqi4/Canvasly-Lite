<?php
namespace CanvaslyLite\Design;
if(!defined('ABSPATH')) exit;
class SiteNavigation {
 public static function pages(){
  $types=class_exists('\CanvaslyLite\Document\Documents')?\CanvaslyLite\Document\Documents::enabled():['page','post'];
  if(!$types)return [];
  $items=[]; $q=new \WP_Query(['post_type'=>$types,'post_status'=>['publish','draft','private'],'posts_per_page'=>50,'orderby'=>'modified','order'=>'DESC','no_found_rows'=>true,'update_post_meta_cache'=>false,'update_post_term_cache'=>false,'lazy_load_term_meta'=>false]);
  foreach((array)$q->posts as $p){
   if(!is_object($p)||empty($p->ID))continue;
   $items[]=['id'=>(int)$p->ID,'title'=>$p->post_title!==''?$p->post_title:'(Untitled)','type'=>$p->post_type,'status'=>$p->post_status,'url'=>get_permalink($p)];
  }
  return $items;
 }
}
