<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Progress Bar: titled bar with preset colour styles, an optional percentage label and inner text. The fill animates when scrolled into view. */
class Progress extends Unit {
 public function type(){return 'progress';} public function title(){return __('Progress Bar', 'canvasly-lite');} public function icon(){return '▭';} public function category(){return 'basic';}
 public function keywords(){return ['progress','bar','skill','percentage','meter'];}
 public function scripts($s=[]){return $this->frontend_scripts();}
 public function defaults(){return ['label'=>__('My Skill', 'canvasly-lite'),'title_tag'=>'span','value'=>70,'bar_style'=>'default','show_percentage'=>true,'inner_text'=>'','color'=>'','background'=>'','bar_height'=>'','bar_radius'=>'','inner_color'=>'','title_color'=>''];}
 public function controls(){return ['label'=>'text','title_tag'=>'select','value'=>'number','bar_style'=>'select','show_percentage'=>'switch','inner_text'=>'text','color'=>'color','background'=>'color','bar_height'=>'number','bar_radius'=>'number','inner_color'=>'color','title_color'=>'color'];}
 public function render($s,$children=''){
  $v=max(0,min(100,floatval($s['value']??70)));
  $style=in_array($s['bar_style']??'default',['default','info','success','warning','danger'],true)?$s['bar_style']:'default';
  $tag=$this->tag($s['title_tag']??'span',$this->title_tags(),'span');
  $vars=$this->style_attr(['--lb-bar-color'=>$s['color']??'','--lb-bar-bg'=>$s['background']??'','--lb-bar-height'=>$this->unit($s['bar_height']??''),'--lb-bar-radius'=>$this->unit($s['bar_radius']??''),'--lb-bar-inner-color'=>$s['inner_color']??'','--lb-bar-title-color'=>$s['title_color']??'']);
  $out='<div class="'.$this->cls($s).' lb-progress lb-progress-'.$style.'"'.$vars.'>';
  if(($s['label']??'')!=='')$out.='<'.$tag.' class="lb-progress-label">'.esc_html($s['label']).'</'.$tag.'>';
  $out.='<div class="lb-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'.esc_attr($v).'" aria-label="'.esc_attr(($s['label']??'')!==''?$s['label']:'Progress').'"><span class="lb-progress-fill" data-lb-progress="'.esc_attr($v).'" style="width:'.esc_attr($v).'%">';
  if(($s['inner_text']??'')!=='')$out.='<span class="lb-progress-inner">'.esc_html($s['inner_text']).'</span>';
  if(!empty($s['show_percentage']))$out.='<span class="lb-progress-percent">'.esc_html(rtrim(rtrim(number_format($v,1,'.',''),'0'),'.')).'%</span>';
  return $out.'</span></div></div>';
 }
}
