<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Image Carousel: repeater of slides (image, caption, link). Legacy comma-separated
 * `ids` plus `custom_urls` lines are migrated on load.
 */
class Carousel extends Unit {
 public function type(){return 'carousel';} public function title(){return __('Image Carousel', 'canvasly-lite');} public function icon(){return '⧉';} public function category(){return 'media';}
 public function keywords(){return ['carousel','slider','slideshow','images','gallery'];}
 public function scripts($s=[]){return $this->frontend_scripts();}
 public function defaults(){return ['slides'=>[],'ids'=>'','image_size'=>'large','slides_to_show'=>1,'slides_to_scroll'=>1,'image_stretch'=>false,'navigation'=>'both','link'=>'none','custom_urls'=>'','lightbox'=>true,'caption'=>'none','lazyload'=>true,'autoplay'=>true,'pause_on_hover'=>true,'pause_on_interaction'=>true,'interval'=>5000,'loop'=>true,'effect'=>'slide','speed'=>500,'slide_direction'=>'ltr','height'=>'','image_spacing'=>10,'image_radius'=>'','arrows_size'=>'','arrows_color'=>'','dots_size'=>'','dots_color'=>'','caption_align'=>'center','caption_color'=>''];}
 public function controls(){
  $car=__('Image Carousel', 'canvasly-lite'); $images=__('Images', 'canvasly-lite'); $arrows=__('Arrows', 'canvasly-lite'); $dots=__('Dots', 'canvasly-lite'); $cap=__('Caption', 'canvasly-lite');
  return [
   'slides'=>$this->ctrl('repeater',__('Slides', 'canvasly-lite'),'content',$car,[
    'title_field'=>'{{caption}}',
    'fields'=>[
     'image_id'=>$this->field('media',__('Image', 'canvasly-lite'),['media_types'=>['image','video'],'description'=>__('Choose an image or a video from the Media Library.', 'canvasly-lite')]),
     'image_url'=>$this->field('url',__('Image URL', 'canvasly-lite'),['hidden'=>true]),
     'caption'=>$this->field('text',__('Caption', 'canvasly-lite')),
     'alt'=>$this->field('text',__('Alt Text', 'canvasly-lite')),
     'link'=>$this->field('url',__('Link', 'canvasly-lite')),
    ],
   ]),
   'ids'=>$this->ctrl('gallery',__('Images', 'canvasly-lite'),'content',$car,['hidden'=>true]),
   'custom_urls'=>$this->ctrl('textarea',__('Custom URLs', 'canvasly-lite'),'content',$car,['hidden'=>true]),
   'image_size'=>$this->ctrl('select',__('Image Size', 'canvasly-lite'),'content',$car,['options'=>['thumbnail'=>__('Thumbnail', 'canvasly-lite'),'medium'=>__('Medium', 'canvasly-lite'),'medium_large'=>__('Medium Large', 'canvasly-lite'),'large'=>__('Large', 'canvasly-lite'),'full'=>__('Full', 'canvasly-lite')]]),
   'slides_to_show'=>$this->ctrl('number',__('Slides to Show', 'canvasly-lite'),'content',$car,['range'=>['min'=>1,'max'=>10]]),
   'slides_to_scroll'=>$this->ctrl('number',__('Slides to Scroll', 'canvasly-lite'),'content',$car,['range'=>['min'=>1,'max'=>10]]),
   'image_stretch'=>$this->ctrl('switch',__('Image Stretch', 'canvasly-lite'),'content',$car),
   'navigation'=>$this->ctrl('select',__('Navigation', 'canvasly-lite'),'content',$car,['options'=>['both'=>__('Arrows and Dots', 'canvasly-lite'),'arrows'=>__('Arrows', 'canvasly-lite'),'dots'=>__('Dots', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite')]]),
   'link'=>$this->ctrl('select',__('Link', 'canvasly-lite'),'content',$car,['options'=>['none'=>__('None', 'canvasly-lite'),'file'=>__('Media File', 'canvasly-lite'),'custom'=>__('Custom URL', 'canvasly-lite')]]),
   'lightbox'=>$this->ctrl('switch',__('Lightbox', 'canvasly-lite'),'content',$car,['condition'=>['link'=>'file']]),
   'caption'=>$this->ctrl('select',__('Caption', 'canvasly-lite'),'content',$car,['options'=>['none'=>__('None', 'canvasly-lite'),'title'=>__('Title', 'canvasly-lite'),'caption'=>__('Caption', 'canvasly-lite'),'description'=>__('Description', 'canvasly-lite')]]),
   'lazyload'=>$this->ctrl('switch',__('Lazy Load', 'canvasly-lite'),'content',$car),
   'autoplay'=>$this->ctrl('switch',__('Autoplay', 'canvasly-lite'),'content',$car),
   'pause_on_hover'=>$this->ctrl('switch',__('Pause on Hover', 'canvasly-lite'),'content',$car),
   'pause_on_interaction'=>$this->ctrl('switch',__('Pause on Interaction', 'canvasly-lite'),'content',$car),
   'interval'=>$this->ctrl('number',__('Autoplay Speed', 'canvasly-lite'),'content',$car,['range'=>['min'=>500,'max'=>15000,'step'=>100]]),
   'loop'=>$this->ctrl('switch',__('Infinite Loop', 'canvasly-lite'),'content',$car),
   'effect'=>$this->ctrl('select',__('Effect', 'canvasly-lite'),'content',$car,['options'=>['slide'=>__('Slide', 'canvasly-lite'),'fade'=>__('Fade', 'canvasly-lite')]]),
   'speed'=>$this->ctrl('number',__('Animation Speed', 'canvasly-lite'),'content',$car,['range'=>['min'=>100,'max'=>3000,'step'=>50]]),
   'slide_direction'=>$this->ctrl('select',__('Direction', 'canvasly-lite'),'content',$car,['options'=>['ltr'=>__('Left to Right', 'canvasly-lite'),'rtl'=>__('Right to Left', 'canvasly-lite')]]),
   'height'=>$this->ctrl('text',__('Height', 'canvasly-lite'),'style',$images),
   'image_spacing'=>$this->ctrl('number',__('Spacing', 'canvasly-lite'),'style',$images),
   'image_radius'=>$this->ctrl('number',__('Border Radius', 'canvasly-lite'),'style',$images),
   'arrows_size'=>$this->ctrl('number',__('Size', 'canvasly-lite'),'style',$arrows),
   'arrows_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$arrows),
   'dots_size'=>$this->ctrl('number',__('Size', 'canvasly-lite'),'style',$dots),
   'dots_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$dots),
   'caption_align'=>$this->ctrl('select',__('Alignment', 'canvasly-lite'),'style',$cap,['options'=>self::opt_lcr()]),
   'caption_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$cap),
  ];
 }
 public static function caption_text($id,$mode){
  if($mode==='title')return get_the_title($id);
  if($mode==='caption')return (string)wp_get_attachment_caption($id);
  if($mode==='description')return (string)get_post_field('post_content',$id);
  return '';
 }
 protected function slides($s){
  $items=$this->repeater_items($s['slides']??[],[]);
  if($items)return $items;
  $ids=array_values(array_filter(array_map('absint',preg_split('/[,\s]+/',(string)($s['ids']??'')))));
  $custom=array_map('trim',preg_split('/\r?\n/',(string)($s['custom_urls']??'')));
  $out=[];
  foreach($ids as $i=>$id)$out[]=['image_id'=>$id,'link'=>$custom[$i]??'','caption'=>'','alt'=>''];
  return $out;
 }
 public function render($s,$children=''){
  $slides=$this->slides($s);
  if(!$slides)return '<div class="'.$this->cls($s).' lb-carousel-placeholder">'.esc_html__('Choose images for the carousel', 'canvasly-lite').'</div>';
  $show=max(1,min(10,absint($s['slides_to_show']??1)));$scroll=max(1,min($show,absint($s['slides_to_scroll']??1)));
  $nav=in_array($s['navigation']??'both',['both','arrows','dots','none'],true)?$s['navigation']:'both';
  $linkMode=in_array($s['link']??'none',['none','file','custom'],true)?$s['link']:'none';
  $effect=($s['effect']??'slide')==='fade'&&$show===1?'fade':'slide';
  $dir=($s['slide_direction']??'ltr')==='rtl'?'rtl':'ltr';
  $capMode=in_array($s['caption']??'none',['none','title','caption','description'],true)?$s['caption']:'none';
  $capAlign=in_array($s['caption_align']??'center',['left','center','right'],true)?$s['caption_align']:'center';
  $size=sanitize_key($s['image_size']??'large');
  $height=$this->scalar($s['height']??'');
  $vars=$this->style_attr(['--lb-carousel-show'=>$show,'--lb-carousel-spacing'=>$this->unit($s['image_spacing']??''),'--lb-carousel-radius'=>$this->unit($s['image_radius']??''),'--lb-carousel-height'=>$height!==''?$this->unit($height):'','--lb-carousel-speed'=>max(0,absint($s['speed']??500)).'ms','--lb-carousel-arrow-size'=>$this->unit($s['arrows_size']??''),'--lb-carousel-arrow-color'=>$s['arrows_color']??'','--lb-carousel-dot-size'=>$this->unit($s['dots_size']??''),'--lb-carousel-dot-color'=>$s['dots_color']??'','--lb-carousel-caption-color'=>$s['caption_color']??'']);
  $data=' data-lb-carousel data-show="'.$show.'" data-scroll="'.$scroll.'" data-effect="'.$effect.'" data-direction="'.$dir.'" data-autoplay="'.(!empty($s['autoplay'])?'1':'0').'" data-interval="'.max(500,absint($s['interval']??5000)).'" data-loop="'.(!empty($s['loop'])?'1':'0').'" data-arrows="'.(in_array($nav,['both','arrows'],true)?'1':'0').'" data-dots="'.(in_array($nav,['both','dots'],true)?'1':'0').'" data-pause-hover="'.(!empty($s['pause_on_hover'])?'1':'0').'" data-pause-interaction="'.(!empty($s['pause_on_interaction'])?'1':'0').'" data-lightbox="'.(!empty($s['lightbox'])&&$linkMode==='file'?'1':'0').'"';
  $classes=$this->cls($s).' lb-carousel lb-carousel-'.$effect.' lb-carousel-'.$dir.(!empty($s['image_stretch'])?' lb-carousel-stretch':'').' lb-carousel-caption-'.$capAlign;
  $out='<div class="'.esc_attr($classes).'" dir="'.$dir.'" aria-roledescription="carousel"'.$data.$vars.'><div class="lb-carousel-track">';
  $total=count($slides);
  foreach($slides as $i=>$slide){
   $id=absint($slide['image_id']??0);
   $video=$id&&function_exists('wp_attachment_is')&&wp_attachment_is('video',$id);
   $url=$video?(string)(wp_get_attachment_url($id)?:($slide['image_url']??'')):$this->media_url($id,$slide['image_url']??'',$size?:'large');
   if(!$url&&!empty($slide['image_url'])&&preg_match('/\.(mp4|webm|ogg|ogv|mov|m4v)(\?|#|$)/i',(string)$slide['image_url'])){$video=true;$url=(string)$slide['image_url'];}
   if(!$url)continue;
   $alt=(string)($slide['alt']??''); if($alt===''&&$id)$alt=(string)get_post_meta($id,'_wp_attachment_image_alt',true);
   $cap=trim((string)($slide['caption']??''));
   if($cap===''&&$capMode!=='none'&&$id)$cap=self::caption_text($id,$capMode);
   $img=$video?'<video class="lb-carousel-video" src="'.esc_url($url).'" controls playsinline'.(!empty($s['lazyload'])?' preload="metadata"':'').'></video>':'<img src="'.esc_url($url).'" alt="'.esc_attr($alt).'"'.(!empty($s['lazyload'])?' loading="lazy"':'').'>';
   $href='';
   if($linkMode==='file'&&$id)$href=wp_get_attachment_image_url($id,'full')?:$url;
   elseif($linkMode==='custom')$href=(string)($slide['link']??'');
   elseif($linkMode==='none'&&!empty($slide['link']))$href=(string)$slide['link'];
   if($href){
    $lb='';
    if($linkMode==='file'&&!empty($s['lightbox'])){
     $lb=' data-lb-lightbox="1"';
     if(class_exists('\\CanvaslyLite\\Settings\\KitSettings'))$lb.=\CanvaslyLite\Settings\KitSettings::lightbox_data_attrs($id,$alt,$cap,$id?get_the_title($id):'');
    }
    $img='<a class="lb-carousel-link" href="'.esc_url($href).'"'.$lb.'>'.$img.'</a>';
   }
   $out.='<figure class="lb-carousel-slide" role="group" aria-roledescription="slide" aria-label="'.esc_attr(($i+1).' of '.$total).'">'.$img.($cap!==''?'<figcaption class="lb-carousel-caption">'.esc_html($cap).'</figcaption>':'').'</figure>';
  }
  return $out.'</div></div>';
 }
}
