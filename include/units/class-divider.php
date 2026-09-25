<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Divider: a horizontal separator with line or pattern styles, width, alignment,
 * weight, spacing and an optional centred text or icon unit.
 */
class Divider extends Unit {
 public function type(){return 'divider';} public function title(){return __('Divider', 'canvasly-lite');} public function icon(){return '—';} public function category(){return 'basic';}
 public function keywords(){return ['divider','separator','line','hr','rule'];}
 public static function line_styles(){return ['solid','double','dotted','dashed','wavy','zigzag','curly','slashes','squared','multiple'];}
 public function defaults(){return ['style'=>'solid','divider_width'=>'100%','align'=>'center','thickness'=>1,'color'=>'#dddddd','divider_gap'=>15,'pattern_size'=>20,'look'=>'line','text'=>'Divider','text_tag'=>'span','icon'=>'star','unit_align'=>'center','unit_spacing'=>15,'text_color'=>'','icon_color'=>'','icon_size'=>'','icon_view'=>'default'];}
 public function controls(){return ['style'=>'select','divider_width'=>'text','align'=>'select','thickness'=>'number','color'=>'color','divider_gap'=>'number','pattern_size'=>'number','look'=>'select','text'=>'text','text_tag'=>'select','icon'=>'icon','unit_align'=>'select','unit_spacing'=>'number','text_color'=>'color','icon_color'=>'color','icon_size'=>'number','icon_view'=>'select'];}
 public function render($s,$children=''){
  $style=in_array($s['style']??'solid',self::line_styles(),true)?$s['style']:'solid';
  $align=in_array($s['align']??'center',['left','center','right'],true)?$s['align']:'center';
  $look=in_array($s['look']??'line',['line','line_text','line_icon'],true)?$s['look']:'line';
  $ealign=in_array($s['unit_align']??'center',['left','center','right'],true)?$s['unit_align']:'center';
  $view=in_array($s['icon_view']??'default',['default','stacked','framed'],true)?$s['icon_view']:'default';
  $vars=$this->style_attr([
   '--lb-divider-width'=>sanitize_text_field((string)($s['divider_width']??'100%')),'--lb-divider-color'=>$s['color']??'','--lb-divider-weight'=>$this->unit(max(1,(float)($s['thickness']??1))),
   '--lb-divider-gap'=>$this->unit($s['divider_gap']??''),'--lb-divider-pattern'=>$this->unit($s['pattern_size']??''),'--lb-divider-spacing'=>$this->unit($s['unit_spacing']??''),
   '--lb-divider-text-color'=>$s['text_color']??'','--lb-divider-icon-color'=>$s['icon_color']??'','--lb-divider-icon-size'=>$this->unit($s['icon_size']??''),
  ]);
  $classes=$this->cls($s).' lb-divider lb-divider-'.$style.' lb-divider-align-'.$align.' lb-divider-look-'.str_replace('_','-',$look).' lb-divider-unit-'.$ealign;
  $line='<span class="lb-divider-line" aria-hidden="true"></span>';
  $element='';
  if($look==='line_text'&&($s['text']??'')!==''){$tag=$this->tag($s['text_tag']??'span',$this->title_tags(),'span');$element='<'.$tag.' class="lb-divider-text">'.esc_html($s['text']).'</'.$tag.'>';}
  elseif($look==='line_icon'){$element='<span class="lb-divider-icon lb-icon-view-'.$view.'">'.\CanvaslyLite\Utils\Icons::svg($s['icon']??'star').'</span>';}
  return '<div class="'.esc_attr($classes).'"'.$vars.'>'.$line.($element!==''?$element.$line:'').'</div>';
 }
}
