<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Heading: h1–h6 title with size presets, typography, hover colour/shadow and an optional link. */
class Heading extends Unit {
 public function type(){return 'heading';} public function title(){return __('Heading', 'canvasly-lite');} public function icon(){return 'H';} public function category(){return 'basic';}
 public function keywords(){return ['heading','title','h1','h2','h3','headline'];}
 public static function presets(){return ['default','small','medium','large','xl','xxl'];}
 public function defaults(){return ['text'=>__('Your Heading', 'canvasly-lite'),'tag'=>'h2','size_preset'=>'default','size'=>36,'weight'=>'600','color'=>'#222222','hover_color'=>'','font_family'=>'','font_style'=>'','text_transform'=>'','text_decoration'=>'','line_height'=>'1.2','letter_spacing'=>0,'align'=>'left','link'=>'','link_target'=>'_self','text_shadow'=>'','hover_text_shadow'=>'','mix_blend_mode'=>''];}
 public function controls(){
  $title=__('Title', 'canvasly-lite'); $typo=__('Typography', 'canvasly-lite'); $hover=__('Hover', 'canvasly-lite');
  $h='{{WRAPPER}} .lb-heading';
  return [
   'text'=>$this->ctrl('wysiwyg',__('Title', 'canvasly-lite'),'content',$title,['dynamic'=>true]),
   'tag'=>$this->ctrl('select',__('HTML Tag', 'canvasly-lite'),'content',$title,['options'=>self::opt_title_tags()]),
   'link'=>$this->ctrl('url',__('Link', 'canvasly-lite'),'content',$title,['dynamic'=>true]),
   'link_target'=>$this->ctrl('select',__('Link Target', 'canvasly-lite'),'content',$title,['options'=>self::opt_target(),'condition'=>['link!'=>'']]),
   'size_preset'=>$this->ctrl('select',__('Size', 'canvasly-lite'),'content',$title,['options'=>['default'=>__('Default', 'canvasly-lite'),'small'=>__('Small', 'canvasly-lite'),'medium'=>__('Medium', 'canvasly-lite'),'large'=>__('Large', 'canvasly-lite'),'xl'=>__('XL', 'canvasly-lite'),'xxl'=>__('XXL', 'canvasly-lite')]]),
   'align'=>$this->ctrl('choose',__('Alignment', 'canvasly-lite'),'style',$typo,['responsive'=>true,'options'=>self::opt_align(),'selectors'=>['{{WRAPPER}}'=>'text-align: {{VALUE}};']]),
   'color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$typo,['selectors'=>[$h=>'color: {{VALUE}};']]),
   'font_family'=>$this->ctrl('font',__('Font Family', 'canvasly-lite'),'style',$typo,['selectors'=>[$h=>'font-family: {{VALUE}};']]),
   'size'=>$this->ctrl('slider',__('Size', 'canvasly-lite'),'style',$typo,['responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>8,'max'=>200],'condition'=>['size_preset'=>'default'],'selectors'=>[$h=>'font-size: {{SIZE}}{{UNIT}};']]),
   'weight'=>$this->ctrl('select',__('Weight', 'canvasly-lite'),'style',$typo,['options'=>self::opt_weight(),'selectors'=>[$h=>'font-weight: {{VALUE}};']]),
   'font_style'=>$this->ctrl('select',__('Style', 'canvasly-lite'),'style',$typo,['options'=>[''=>__('Default', 'canvasly-lite'),'normal'=>__('Normal', 'canvasly-lite'),'italic'=>__('Italic', 'canvasly-lite'),'oblique'=>__('Oblique', 'canvasly-lite')],'selectors'=>[$h=>'font-style: {{VALUE}};']]),
   'text_transform'=>$this->ctrl('select',__('Transform', 'canvasly-lite'),'style',$typo,['options'=>[''=>__('Default', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite'),'uppercase'=>__('Uppercase', 'canvasly-lite'),'lowercase'=>__('Lowercase', 'canvasly-lite'),'capitalize'=>__('Capitalize', 'canvasly-lite')],'selectors'=>[$h=>'text-transform: {{VALUE}};']]),
   'text_decoration'=>$this->ctrl('select',__('Decoration', 'canvasly-lite'),'style',$typo,['options'=>[''=>__('Default', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite'),'underline'=>__('Underline', 'canvasly-lite'),'overline'=>__('Overline', 'canvasly-lite'),'line-through'=>__('Line Through', 'canvasly-lite')],'selectors'=>[$h=>'text-decoration: {{VALUE}};']]),
   'line_height'=>$this->ctrl('slider',__('Line Height', 'canvasly-lite'),'style',$typo,['responsive'=>true,'units'=>[],'range'=>['min'=>0.6,'max'=>3,'step'=>0.05],'selectors'=>[$h=>'line-height: {{SIZE}};']]),
   'letter_spacing'=>$this->ctrl('slider',__('Letter Spacing', 'canvasly-lite'),'style',$typo,['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>-5,'max'=>20,'step'=>0.1],'selectors'=>[$h=>'letter-spacing: {{SIZE}}{{UNIT}};']]),
   'text_shadow'=>$this->ctrl('text_shadow',__('Text Shadow', 'canvasly-lite'),'style',$typo,['selectors'=>[$h=>'text-shadow: {{VALUE}};']]),
   'mix_blend_mode'=>$this->ctrl('select',__('Blend Mode', 'canvasly-lite'),'style',$typo,['options'=>[''=>__('Normal', 'canvasly-lite'),'multiply'=>__('Multiply', 'canvasly-lite'),'screen'=>__('Screen', 'canvasly-lite'),'overlay'=>__('Overlay', 'canvasly-lite'),'darken'=>__('Darken', 'canvasly-lite'),'lighten'=>__('Lighten', 'canvasly-lite'),'color-dodge'=>__('Color Dodge', 'canvasly-lite'),'color-burn'=>__('Color Burn', 'canvasly-lite'),'difference'=>__('Difference', 'canvasly-lite')],'selectors'=>[$h=>'mix-blend-mode: {{VALUE}};']]),
   'hover_color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$hover,['selectors'=>['{{WRAPPER}}'=>'--lb-heading-hover: {{VALUE}};',$h.':hover'=>'color: {{VALUE}};',$h.':hover .lb-heading-link'=>'color: {{VALUE}};']]),
   'hover_text_shadow'=>$this->ctrl('text_shadow',__('Text Shadow', 'canvasly-lite'),'style',$hover,['selectors'=>[$h.':hover'=>'text-shadow: {{VALUE}};']]),
  ];
 }
 public function render($s,$children=''){
  $tag=$this->tag($s['tag']??'h2',$this->title_tags(),'h2');
  $preset_raw=$s['size_preset']??'default';
  $preset=in_array($preset_raw,self::presets(),true)?$preset_raw:'default';
  $text=wp_kses_post($s['text']??'');
  if(!empty($s['link'])){$t=($s['link_target']??'_self')==='_blank'?' target="_blank" rel="noopener"':'';$text='<a class="lb-heading-link" href="'.esc_attr(self::link_href($s['link'])).'"'.$t.'>'.$text.'</a>';}
  return '<'.$tag.' class="'.$this->cls($s).' lb-heading lb-heading-size-'.$preset.'" data-inline="text">'.$text.'</'.$tag.'>';
 }
}
