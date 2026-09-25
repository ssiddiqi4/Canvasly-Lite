<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Testimonial: one or more quotes (repeater) with author image, name, role and optional link. */
class Testimonial extends Unit {
 public function type(){return 'testimonial';} public function title(){return __('Testimonial', 'canvasly-lite');} public function icon(){return '❝';} public function category(){return 'basic';}
 public function keywords(){return ['testimonial','quote','review','customer','feedback'];}
 public function defaults(){return [
  'items'=>[[
   '_id'=>'tm1','quote'=>'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.','author'=>'John Doe','role'=>'Designer','image_id'=>0,'image_url'=>'','link'=>'','link_target'=>'_self',
  ]],
  'image_size'=>'thumbnail','image_position'=>'aside','align'=>'center','name_tag'=>'div','quote_color'=>'','name_color'=>'','role_color'=>'','image_width'=>'','image_radius'=>'',
 ];}
 public function controls(){
  $tm='Testimonial'; $content=__('Content', 'canvasly-lite'); $image=__('Image', 'canvasly-lite'); $name=__('Name', 'canvasly-lite'); $role=__('Title', 'canvasly-lite');
  return [
   'items'=>$this->ctrl('repeater',__('Testimonials', 'canvasly-lite'),'content',$tm,[
    'title_field'=>'{{author}}','prevent_empty'=>true,
    'fields'=>[
     'quote'=>$this->field('wysiwyg',__('Content', 'canvasly-lite')),
     'image_id'=>$this->field('media',__('Image', 'canvasly-lite')),
     'image_url'=>$this->field('url',__('Image URL', 'canvasly-lite'),['hidden'=>true]),
     'author'=>$this->field('text',__('Name', 'canvasly-lite')),
     'role'=>$this->field('text',__('Title', 'canvasly-lite')),
     'link'=>$this->field('url',__('Link', 'canvasly-lite')),
     'link_target'=>$this->field('select',__('Link Target', 'canvasly-lite'),['options'=>self::opt_target(),'condition'=>['link!'=>'']]),
    ],
   ]),
   'image_size'=>$this->ctrl('select',__('Image Size', 'canvasly-lite'),'content',$tm,['options'=>['thumbnail'=>__('Thumbnail', 'canvasly-lite'),'medium'=>__('Medium', 'canvasly-lite'),'large'=>__('Large', 'canvasly-lite'),'full'=>__('Full', 'canvasly-lite')]]),
   'image_position'=>$this->ctrl('select',__('Image Position', 'canvasly-lite'),'content',$tm,['options'=>['aside'=>__('Aside', 'canvasly-lite'),'top'=>__('Top', 'canvasly-lite')]]),
   'align'=>$this->ctrl('select',__('Alignment', 'canvasly-lite'),'content',$tm,['options'=>self::opt_lcr()]),
   'name_tag'=>$this->ctrl('select',__('Name HTML Tag', 'canvasly-lite'),'content',$tm,['options'=>self::opt_title_tags()]),
   'quote_color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$content),
   'image_width'=>$this->ctrl('number',__('Size', 'canvasly-lite'),'style',$image),
   'image_radius'=>$this->ctrl('number',__('Radius', 'canvasly-lite'),'style',$image),
   'name_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$name),
   'role_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$role),
  ];
 }
 protected function item_html($s,$item){
  $pos=($s['image_position']??'aside')==='top'?'top':'aside';
  $align=in_array($s['align']??'center',['left','center','right'],true)?$s['align']:'center';
  $tag=$this->tag($s['name_tag']??'div',$this->title_tags(),'div');
  $author=(string)($item['author']??''); $role=(string)($item['role']??''); $quote=(string)($item['quote']??'');
  $url=$this->media_url($item['image_id']??0,$item['image_url']??'',sanitize_key($s['image_size']??'thumbnail'));
  $link=!empty($item['link'])?esc_url($item['link']):''; $t=($item['link_target']??'_self')==='_blank'?' target="_blank" rel="noopener"':'';
  $wrap=function($html,$attrs='')use($link,$t){return $link?'<a href="'.$link.'"'.$t.$attrs.'>'.$html.'</a>':$html;};
  $img=$url?'<div class="lb-testimonial-image">'.$wrap('<img src="'.esc_url($url).'" alt="'.esc_attr($author).'" loading="lazy">',' tabindex="-1" aria-hidden="true"').'</div>':'';
  $name=$author!==''?'<'.$tag.' class="lb-testimonial-name">'.$wrap(esc_html($author)).'</'.$tag.'>':'';
  $roleHtml=$role!==''?'<div class="lb-testimonial-role">'.esc_html($role).'</div>':'';
  $out='<figure class="lb-testimonial lb-testimonial-image-'.$pos.' lb-testimonial-align-'.$align.'">';
  if($quote!=='')$out.='<blockquote class="lb-testimonial-quote">'.wp_kses_post($quote).'</blockquote>';
  $out.='<figcaption class="lb-testimonial-meta">'.$img.'<div class="lb-testimonial-details">'.$name.$roleHtml.'</div></figcaption>';
  return $out.'</figure>';
 }
 public function render($s,$children=''){
  $items=$this->repeater_items($s['items']??'',['quote','author','role']);
  if(!$items && (trim((string)($s['quote']??'').($s['author']??''))!=='')){
   $items=[['quote'=>$s['quote']??'','author'=>$s['author']??'','role'=>$s['role']??'','image_id'=>$s['image_id']??0,'image_url'=>$s['image_url']??'','link'=>$s['link']??'','link_target'=>$s['link_target']??'_self']];
  }
  $vars=$this->style_attr(['--lb-testimonial-quote-color'=>$s['quote_color']??'','--lb-testimonial-name-color'=>$s['name_color']??'','--lb-testimonial-role-color'=>$s['role_color']??'','--lb-testimonial-image-width'=>$this->unit($s['image_width']??''),'--lb-testimonial-image-radius'=>$this->unit($s['image_radius']??'')]);
  if(count($items)<=1){
   $html=$items?$this->item_html($s,$items[0]):'<figure class="lb-testimonial"></figure>';
   return '<div class="'.$this->cls($s).'"'.$vars.'>'.$html.'</div>';
  }
  $out='<div class="'.$this->cls($s).' lb-testimonial-list"'.$vars.'>';
  foreach($items as $item)$out.=$this->item_html($s,$item);
  return $out.'</div>';
 }
}
