<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Text Path: words drawn along a wave, arc, circle or straight line.
 * Style covers the same typography, colour and stroke choices a visual builder puts on Style.
 */
class TextPath extends Unit {
 public function type(){return 'text_path';}
 public function title(){return __('Text Path', 'canvasly-lite');}
 public function icon(){return '◌';}
 public function keywords(){return ['text path','svg','curve','wave','marquee'];}
 public function defaults(){
  return [
   'text'=>'Canvasly','link'=>'','link_target'=>'_self','path'=>'wave','show_path'=>false,'speed'=>20,
   'align'=>'center','color'=>'#222222','hover_color'=>'','font_family'=>'','font_size'=>'42',
   'font_weight'=>'600','font_style'=>'','text_transform'=>'','text_decoration'=>'','line_height'=>'',
   'letter_spacing'=>'','word_spacing'=>'','stroke_width'=>'','stroke_color'=>'#222222',
   'path_color'=>'#c5cad1','path_width'=>1,'size'=>160,
  ];
 }
 public function controls(){
  $content=__('Text Path', 'canvasly-lite');
  $text=__('Text', 'canvasly-lite');
  $path=__('Path', 'canvasly-lite');
  $svg='{{WRAPPER}} .lb-text-path';
  $label=$svg.' text';
  return [
   'text'=>$this->ctrl('textarea',__('Text', 'canvasly-lite'),'content',$content,['dynamic'=>true]),
   'link'=>$this->ctrl('url',__('Link', 'canvasly-lite'),'content',$content,['dynamic'=>true]),
   'link_target'=>$this->ctrl('select',__('Link Target', 'canvasly-lite'),'content',$content,['options'=>self::opt_target(),'condition'=>['link!'=>'']]),
   'path'=>$this->ctrl('select',__('Path Type', 'canvasly-lite'),'content',$content,['options'=>['wave'=>__('Wave', 'canvasly-lite'),'arc'=>__('Arc', 'canvasly-lite'),'circle'=>__('Circle', 'canvasly-lite'),'line'=>__('Line', 'canvasly-lite')]]),
   'show_path'=>$this->ctrl('switch',__('Show Path', 'canvasly-lite'),'content',$content),
   'speed'=>$this->ctrl('slider',__('Animation Speed', 'canvasly-lite'),'content',$content,['units'=>[],'range'=>['min'=>5,'max'=>60,'step'=>1],'default'=>20,'selectors'=>[$svg=>'--lb-speed: {{SIZE}}s;']]),
   'align'=>$this->ctrl('choose',__('Alignment', 'canvasly-lite'),'style',$text,['options'=>self::opt_lcr(),'selectors'=>[$svg=>'text-align: {{VALUE}};']]),
   'color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$text,['selectors'=>[$label=>'fill: {{VALUE}}; color: {{VALUE}};']]),
   'hover_color'=>$this->ctrl('color',__('Hover Color', 'canvasly-lite'),'style',$text,['selectors'=>[$svg.':hover text'=>'fill: {{VALUE}}; color: {{VALUE}};']]),
   'font_family'=>$this->ctrl('font',__('Font Family', 'canvasly-lite'),'style',$text,['selectors'=>[$label=>'font-family: {{VALUE}};']]),
   'font_size'=>$this->ctrl('slider',__('Size', 'canvasly-lite'),'style',$text,['responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>8,'max'=>200],'selectors'=>[$label=>'font-size: {{SIZE}}{{UNIT}};']]),
   'font_weight'=>$this->ctrl('select',__('Weight', 'canvasly-lite'),'style',$text,['options'=>self::opt_weight(),'selectors'=>[$label=>'font-weight: {{VALUE}};']]),
   'font_style'=>$this->ctrl('select',__('Style', 'canvasly-lite'),'style',$text,['options'=>[''=>__('Default', 'canvasly-lite'),'normal'=>__('Normal', 'canvasly-lite'),'italic'=>__('Italic', 'canvasly-lite'),'oblique'=>__('Oblique', 'canvasly-lite')],'selectors'=>[$label=>'font-style: {{VALUE}};']]),
   'text_transform'=>$this->ctrl('select',__('Transform', 'canvasly-lite'),'style',$text,['options'=>[''=>__('Default', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite'),'uppercase'=>__('Uppercase', 'canvasly-lite'),'lowercase'=>__('Lowercase', 'canvasly-lite'),'capitalize'=>__('Capitalize', 'canvasly-lite')],'selectors'=>[$label=>'text-transform: {{VALUE}};']]),
   'text_decoration'=>$this->ctrl('select',__('Decoration', 'canvasly-lite'),'style',$text,['options'=>[''=>__('Default', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite'),'underline'=>__('Underline', 'canvasly-lite'),'overline'=>__('Overline', 'canvasly-lite'),'line-through'=>__('Line Through', 'canvasly-lite')],'selectors'=>[$label=>'text-decoration: {{VALUE}};']]),
   'line_height'=>$this->ctrl('slider',__('Line Height', 'canvasly-lite'),'style',$text,['responsive'=>true,'units'=>[],'range'=>['min'=>0.6,'max'=>3,'step'=>0.05],'selectors'=>[$label=>'line-height: {{SIZE}};']]),
   'letter_spacing'=>$this->ctrl('slider',__('Letter Spacing', 'canvasly-lite'),'style',$text,['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>-5,'max'=>40,'step'=>0.1],'selectors'=>[$label=>'letter-spacing: {{SIZE}}{{UNIT}};']]),
   'word_spacing'=>$this->ctrl('slider',__('Word Spacing', 'canvasly-lite'),'style',$text,['units'=>['px','em'],'range'=>['min'=>-10,'max'=>80],'selectors'=>[$label=>'word-spacing: {{SIZE}}{{UNIT}};']]),
   'stroke_width'=>$this->ctrl('slider',__('Stroke Width', 'canvasly-lite'),'style',__('Stroke', 'canvasly-lite'),['units'=>['px'],'range'=>['min'=>0,'max'=>20],'selectors'=>[$label=>'-webkit-text-stroke-width: {{SIZE}}{{UNIT}}; stroke-width: {{SIZE}}{{UNIT}};']]),
   'stroke_color'=>$this->ctrl('color',__('Stroke Color', 'canvasly-lite'),'style',__('Stroke', 'canvasly-lite'),['selectors'=>[$label=>'-webkit-text-stroke-color: {{VALUE}}; stroke: {{VALUE}};']]),
   'size'=>$this->ctrl('slider',__('Height', 'canvasly-lite'),'style',$path,['units'=>['px'],'range'=>['min'=>40,'max'=>400],'selectors'=>[$svg.' svg'=>'height: {{SIZE}}{{UNIT}};']]),
   'path_color'=>$this->ctrl('color',__('Path Color', 'canvasly-lite'),'style',$path,['condition'=>['show_path'=>'1'],'selectors'=>[$svg.' .lb-text-path-guide'=>'stroke: {{VALUE}};']]),
   'path_width'=>$this->ctrl('slider',__('Path Width', 'canvasly-lite'),'style',$path,['units'=>['px'],'range'=>['min'=>0,'max'=>20],'condition'=>['show_path'=>'1'],'selectors'=>[$svg.' .lb-text-path-guide'=>'stroke-width: {{SIZE}}{{UNIT}};']]),
  ];
 }
 public function render($s,$children=''){
  unset($children);
  $text=esc_html($s['text']??'Canvasly');
  $kind=in_array($s['path']??'wave',['wave','arc','circle','line'],true)?$s['path']:'wave';
  $paths=['wave'=>'M 20 80 Q 140 16 260 80 T 500 80 T 740 80 T 980 80','arc'=>'M 36 112 Q 500 8 964 112','circle'=>'M 500 46 A 150 32 0 1 1 499 46','line'=>'M 20 96 H 980'];
  $id=function_exists('wp_unique_id')?wp_unique_id('lb-tp-'):'lb-tp-'.substr(md5(uniqid('',true)),0,8);
  $speed=max(5,absint($s['speed']??20));
  $guide=!empty($s['show_path'])?'':' style="stroke:none"';
  $svg='<svg viewBox="0 0 1000 160" overflow="visible" role="img" aria-label="'.$text.'"><path id="'.esc_attr($id).'" class="lb-text-path-guide" d="'.esc_attr($paths[$kind]).'" fill="none"'.$guide.'></path><text visibility="hidden"><textPath href="#'.esc_attr($id).'" startOffset="0">'.$text.'</textPath></text></svg>';
  if(!empty($s['link'])){
   $t=($s['link_target']??'_self')==='_blank'?' target="_blank" rel="noopener"':'';
   $svg='<a class="lb-text-path-link" href="'.esc_attr(self::link_href($s['link'])).'"'.$t.'>'.$svg.'</a>';
  }
  return '<div class="lb-text-path lb-text-path-'.$kind.'" data-lb-speed="'.$speed.'" style="--lb-speed:'.$speed.'s">'.$svg.'</div>';
 }
}
