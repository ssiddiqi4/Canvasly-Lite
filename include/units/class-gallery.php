<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Gallery: a single image set, or several named collections with a filter bar.
 * Layouts: justified rows, equal grid, and masonry columns — plus order,
 * spacing, file/attachment links, lightbox, captions and hover animation.
 */
class Gallery extends Unit {
 public function type(){return 'gallery';} public function title(){return __('Gallery', 'canvasly-lite');} public function icon(){return '▦';} public function category(){return 'media';}
 public function keywords(){return ['gallery','images','grid','photos','lightbox','filter','albums','collections','justified','masonry'];}
 public function scripts($s=[]){return $this->frontend_scripts();}
 public function defaults(){return ['mode'=>'single','ids'=>'','collections'=>[],'media_urls'=>[],'media_ratios'=>[],'order_by'=>'default','show_all'=>true,'all_label'=>'All','columns'=>4,'gap'=>10,'gallery_layout'=>'justified','image_ratio'=>'1:1','row_height'=>220,'last_row'=>'auto','lazy_load'=>true,'link'=>'file','size'=>'medium','lightbox'=>true,'caption'=>'none','image_radius'=>'','hover_animation'=>'','caption_align'=>'center','caption_color'=>'','caption_size'=>''];}
 public function controls(){return ['mode'=>'select','ids'=>'gallery','collections'=>'repeater','media_urls'=>'url_map','media_ratios'=>'number_map','order_by'=>'select','show_all'=>'switch','all_label'=>'text','lazy_load'=>'switch','gallery_layout'=>'select','row_height'=>'number','last_row'=>'select','columns'=>'number','gap'=>'number','image_ratio'=>'select','link'=>'select','size'=>'select','lightbox'=>'switch','caption'=>'select','image_radius'=>'number','hover_animation'=>'select','caption_align'=>'select','caption_color'=>'color','caption_size'=>'number'];}

 protected function id_list($raw){
  return array_values(array_filter(array_map('absint',preg_split('/[,\s]+/',(string)$raw))));
 }
 protected function collections($s){
  if(($s['mode']??'single')==='multiple' && !empty($s['collections']) && is_array($s['collections'])){
   $out=[];
   foreach($s['collections'] as $c){
    if(!is_array($c)) continue;
    $out[]=['label'=>sanitize_text_field($c['label']??''),'ids'=>$this->id_list($c['ids']??'')];
   }
   return $out;
  }
  return [['label'=>'','ids'=>$this->id_list($s['ids']??'')]];
 }
 protected function ordered($ids,$order){
  if(!$ids) return $ids;
  if($order==='random'){ shuffle($ids); return $ids; }
  if($order==='date' || $order==='title'){
   $posts=get_posts([
    'post_type'=>'attachment','post__in'=>$ids,'orderby'=>$order==='date'?'date':'title',
    'order'=>'ASC','posts_per_page'=>-1,'post_status'=>'inherit','fields'=>'ids',
    'no_found_rows'=>true,'update_post_meta_cache'=>false,'update_post_term_cache'=>false,'lazy_load_term_meta'=>false
   ]);
   if($posts) return array_map('absint', $posts);
  }
  return $ids;
 }
 protected function ratio($id){
  $m=wp_get_attachment_metadata($id);
  $w=(int)($m['width']??0); $h=(int)($m['height']??0);
  return ($w>0 && $h>0)? round($w/$h, 4) : 1.5;
 }
 protected function layout_of($s){
  $v=$s['gallery_layout']??'justified';
  return in_array($v,['grid','masonry','justified'],true)?$v:'justified';
 }
 protected function item_style($s,$ratio){
  $layout=$this->layout_of($s);
  $gap=max(0,absint($this->scalar($s['gap']??10,10)));
  if($layout==='justified'){
   $rh=max(80,min(800,absint($this->scalar($s['row_height']??220,220))));
   $w=max(40,(int)round($rh*$ratio));
   return 'height:'.$rh.'px;width:'.$w.'px;flex:1 1 '.$w.'px;overflow:hidden;margin:0';
  }
  if($layout==='masonry') return 'display:inline-block;width:100%;margin:0 0 '.$gap.'px;break-inside:avoid;overflow:hidden;position:static';
  return 'margin:0;min-width:0;overflow:hidden';
 }
 protected function box_style($s){
  $cols=max(1,min(10,absint($this->scalar($s['columns']??4,4))));
  $gap=max(0,absint($this->scalar($s['gap']??10,10)));
  $layout=$this->layout_of($s);
  $rowH=max(80,min(800,absint($this->scalar($s['row_height']??220,220))));
  $ratioMap=['1:1'=>'1 / 1','3:2'=>'3 / 2','4:3'=>'4 / 3','16:9'=>'16 / 9','9:16'=>'9 / 16','auto'=>'auto'];
  $ratio=$ratioMap[$s['image_ratio']??'1:1']??'1 / 1';
  $vars='--lb-cols:'.$cols.';--lb-gap:'.$gap.'px;--lb-gallery-ratio:'.$ratio.';--lb-row-h:'.$rowH.'px';
  if($layout==='masonry') return $vars.';display:block;column-count:'.$cols.';column-gap:'.$gap.'px;width:100%;height:auto;position:static';
  if($layout==='grid') return $vars.';display:grid;grid-template-columns:repeat('.$cols.',minmax(0,1fr));gap:'.$gap.'px;width:100%';
  return $vars.';display:flex;flex-wrap:wrap;align-content:flex-start;gap:'.$gap.'px;width:100%';
 }
 protected function figure($id,$s,$set='',$hidden=false){
  $url=wp_get_attachment_image_url($id,sanitize_key($s['size']??'medium')?:'medium');
  if(!$url) return '';
  $alt=(string)get_post_meta($id,'_wp_attachment_image_alt',true);
  $capMode=in_array($s['caption']??'none',['none','title','caption','description'],true)?$s['caption']:'none';
  $cap=$capMode!=='none'?Carousel::caption_text($id,$capMode):'';
  $lazy=!isset($s['lazy_load']) || !empty($s['lazy_load']);
  $ratio=$this->ratio($id);
  $inner='<img src="'.esc_url($url).'" alt="'.esc_attr($alt).'"'.($lazy?' loading="lazy"':'').'>'.($cap!==''?'<figcaption class="lb-gallery-caption">'.esc_html($cap).'</figcaption>':'');
  $cls='lb-gallery-item'.$this->hover_class($s);
  $style=$this->item_style($s,$ratio);
  $extra=' class="'.$cls.'" data-lb-ratio="'.esc_attr((string)$ratio).'" style="'.esc_attr($style).'"'.($set!==''?' data-lb-in="'.esc_attr($set).'"':'').($hidden?' hidden':'');
  $linkMode=in_array($s['link']??'file',['none','file','attachment'],true)?$s['link']:'file';
  $light=!empty($s['lightbox'])&&$linkMode==='file';
  if($linkMode==='file'){
   $lb='';
   if($light){
    $lb=' data-lb-lightbox="1"';
    if(class_exists('\\CanvaslyLite\\Settings\\KitSettings'))$lb.=\CanvaslyLite\Settings\KitSettings::lightbox_data_attrs($id,$alt,$cap,$id?get_the_title($id):'');
   }
   return '<a'.$extra.' href="'.esc_url(wp_get_attachment_image_url($id,'full')).'"'.$lb.'><figure>'.$inner.'</figure></a>';
  }
  if($linkMode==='attachment') return '<a'.$extra.' href="'.esc_url(get_attachment_link($id)).'"><figure>'.$inner.'</figure></a>';
  return '<figure'.$extra.'>'.$inner.'</figure>';
 }
 public function render($s,$children=''){
  $groups=$this->collections($s);
  $order=in_array($s['order_by']??'default',['default','random','date','title'],true)?$s['order_by']:'default';
  $hasMany=($s['mode']??'single')==='multiple' && count($groups)>1;
  $showAll=!isset($s['show_all']) || !empty($s['show_all']);
  $cols=max(1,min(10,absint($this->scalar($s['columns']??4,4))));
  $layout=$this->layout_of($s);
  $capAlign=in_array($s['caption_align']??'center',['left','center','right'],true)?$s['caption_align']:'center';
  $last=in_array($s['last_row']??'auto',['auto','fit','grow'],true)?$s['last_row']:'auto';
  $layoutCls=' is-'.$layout.(($s['image_ratio']??'1:1')==='auto'?' is-ratio-auto':'');
  $items='';$any=false;
  foreach($groups as $i=>$g){
   $ids=$this->ordered($g['ids'],$order);
   $hide=$hasMany && !$showAll && $i!==0;
   foreach($ids as $id){
    $html=$this->figure($id,$s,($s['mode']??'single')==='multiple'?(string)$i:'',$hide);
    if($html){ $items.=$html; $any=true; }
   }
  }
  if(!$any){
   $ph='';
   for($i=0;$i<$cols;$i++)$ph.='<div class="lb-gallery-placeholder">'.esc_html__('Choose images', 'canvasly-lite').'</div>';
   return '<div class="'.$this->cls($s).' lb-gallery lb-gallery-empty is-grid" style="display:grid;grid-template-columns:repeat('.$cols.',minmax(0,1fr));gap:'.max(0,absint($this->scalar($s['gap']??10,10))).'px">'.$ph.'</div>';
  }
  $nav='';
  if($hasMany){
   $all=sanitize_text_field($s['all_label']??'All')?:'All';
   $nav='<nav class="lb-gallery-nav" role="tablist" aria-label="Gallery">';
   if($showAll) $nav.='<button type="button" class="is-active" role="tab" aria-selected="true" data-lb-set="">'.esc_html($all).'</button>';
   foreach($groups as $i=>$g){
    $label=$g['label']!==''?$g['label']:'Gallery '.($i+1);
    $active=!$showAll && $i===0;
    $nav.='<button type="button" role="tab" aria-selected="'.($active?'true':'false').'"'.($active?' class="is-active"':'').' data-lb-set="'.esc_attr((string)$i).'">'.esc_html($label).'</button>';
   }
   $nav.='</nav>';
  }
  $host=$hasMany?' lb-gallery-has-nav':'';
  $filter=$hasMany?' data-lb-gallery-filter-host="1"':'';
  $light=!empty($s['lightbox'])&&(($s['link']??'file')==='file');
  return '<div class="'.$this->cls($s).' lb-gallery-shell'.$host.'"'.$filter.'>'.$nav.'<div class="lb-gallery'.$layoutCls.' lb-gallery-caption-'.$capAlign.'" data-lb-gal-layout="'.esc_attr($layout).'" data-lb-last-row="'.esc_attr($last).'" data-lightbox="'.($light?'1':'0').'" style="'.esc_attr($this->box_style($s)).'">'.$items.'</div></div>';
 }
}
