<?php
namespace CanvaslyLite\Design;
if(!defined('ABSPATH')) exit;
class Media {
 public static function attachment($id,$size='full'){
  $id=absint($id); if(!$id||get_post_type($id)!=='attachment') return null;
  $src=wp_get_attachment_image_src($id,$size?:'full'); if(!$src)return null;
  return ['id'=>$id,'url'=>$src[0],'width'=>(int)$src[1],'height'=>(int)$src[2],'alt'=>(string)get_post_meta($id,'_wp_attachment_image_alt',true),'caption'=>(string)wp_get_attachment_caption($id),'title'=>get_the_title($id),'srcset'=>wp_get_attachment_image_srcset($id,$size?:'full'),'sizes'=>wp_get_attachment_image_sizes($id,$size?:'full'),'mime'=>get_post_mime_type($id)];
 }
 public static function library($search='',$limit=100){
  $args=['post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>max(1,min(200,absint($limit))),'orderby'=>'date','order'=>'DESC','post_mime_type'=>'image','no_found_rows'=>true,'update_post_term_cache'=>false,'lazy_load_term_meta'=>false];
  if($search!=='')$args['s']=sanitize_text_field($search);$q=new \WP_Query($args);$out=[];foreach($q->posts as $p){$x=self::attachment($p->ID,'thumbnail');if($x)$out[]=$x;}return $out;
 }
}
