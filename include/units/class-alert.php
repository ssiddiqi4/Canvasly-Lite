<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Alert: a coloured notice with an optional dismiss button. */
class Alert extends Unit {
 public function type(){return 'alert';} public function title(){return __('Alert', 'canvasly-lite');} public function icon(){return '!';} public function category(){return 'basic';}
 public function keywords(){return ['alert','notice','message','warning','info','success','danger'];}
 public function scripts($s=[]){return !empty($s['dismissible'])?$this->frontend_scripts():[];}
 public function defaults(){return ['title'=>'This is an Alert','text'=>'I am a description. Click the edit button to change this text.','type'=>'info','dismissible'=>true,'dismiss_icon'=>'times','background'=>'','border_color'=>'','title_color'=>'','text_color'=>'','dismiss_color'=>'','dismiss_size'=>''];}
 public function controls(){return ['title'=>'text','text'=>'wysiwyg','type'=>'select','dismissible'=>'switch','dismiss_icon'=>'icon','background'=>'color','border_color'=>'color','title_color'=>'color','text_color'=>'color','dismiss_color'=>'color','dismiss_size'=>'number'];}
 public function render($s,$children=''){
  $type=in_array($s['type']??'info',['info','success','warning','danger'],true)?$s['type']:'info';
  $vars=$this->style_attr(['--lb-alert-bg'=>$s['background']??'','--lb-alert-border'=>$s['border_color']??'','--lb-alert-title'=>$s['title_color']??'','--lb-alert-text'=>$s['text_color']??'','--lb-alert-dismiss'=>$s['dismiss_color']??'','--lb-alert-dismiss-size'=>$this->unit($s['dismiss_size']??'')]);
  $out='<div class="'.$this->cls($s).' lb-alert lb-alert-'.$type.'" role="alert"'.$vars.'>';
  if(($s['title']??'')!=='')$out.='<span class="lb-alert-title">'.esc_html($s['title']).'</span>';
  if(($s['text']??'')!=='')$out.='<span class="lb-alert-text">'.wp_kses_post($s['text']).'</span>';
  if(!empty($s['dismissible']))$out.='<button type="button" class="lb-alert-dismiss" aria-label="Dismiss this alert" data-lb-dismiss>'.\CanvaslyLite\Utils\Icons::svg($s['dismiss_icon']??'times').'</button>';
  return $out.'</div>';
 }
}
