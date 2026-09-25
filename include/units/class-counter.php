<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Counter: an animated number that counts from a start value to an end value when it scrolls into view. */
class Counter extends Unit {
 public function type(){return 'counter';} public function title(){return __('Counter', 'canvasly-lite');} public function icon(){return '123';} public function category(){return 'basic';}
 public function keywords(){return ['counter','number','statistic','count up','animation'];}
 public function scripts($s=[]){return $this->frontend_scripts();}
 public function defaults(){return ['start'=>0,'number'=>100,'prefix'=>'','suffix'=>'+','duration'=>2000,'thousand_separator'=>true,'separator_char'=>',','title'=>'Happy Clients','title_tag'=>'div','title_position'=>'after','align'=>'center','number_color'=>'','title_color'=>'','number_size'=>'','title_gap'=>''];}
 public function controls(){return ['start'=>'number','number'=>'number','prefix'=>'text','suffix'=>'text','duration'=>'number','thousand_separator'=>'switch','separator_char'=>'select','title'=>'text','title_tag'=>'select','title_position'=>'select','align'=>'select','number_color'=>'color','title_color'=>'color','number_size'=>'number','title_gap'=>'number'];}
 public static function format($n,$sep){
  $n=(float)$n; $neg=$n<0; $n=abs($n);
  $dec=floor($n)==$n?0:strlen(substr(strrchr((string)$n,'.'),1));
  $out=number_format($n,min(4,$dec),'.',$sep);
  return ($neg?'-':'').$out;
 }
 public function render($s,$children=''){
  $end=(float)($s['number']??100);$start=(float)($s['start']??0);
  $sep=!empty($s['thousand_separator'])?(string)($s['separator_char']??','):'';
  if(!in_array($sep,[',','.',' ',"'",''],true))$sep=',';
  $tag=$this->tag($s['title_tag']??'div',$this->title_tags(),'div');
  $pos=($s['title_position']??'after')==='before'?'before':'after';
  $align=in_array($s['align']??'center',['left','center','right'],true)?$s['align']:'center';
  $vars=$this->style_attr(['--lb-counter-number-color'=>$s['number_color']??'','--lb-counter-title-color'=>$s['title_color']??'','--lb-counter-number-size'=>$this->unit($s['number_size']??''),'--lb-counter-gap'=>$this->unit($s['title_gap']??''),'text-align'=>$align]);
  $title=($s['title']??'')!==''?'<'.$tag.' class="lb-counter-title">'.esc_html($s['title']).'</'.$tag.'>':'';
  $value='<div class="lb-counter-value"><span class="lb-counter-prefix">'.esc_html($s['prefix']??'').'</span><span class="lb-counter-number" data-lb-counter data-start="'.esc_attr($start).'" data-end="'.esc_attr($end).'" data-duration="'.absint($s['duration']??2000).'" data-separator="'.esc_attr($sep).'">'.esc_html(self::format($end,$sep)).'</span><span class="lb-counter-suffix">'.esc_html($s['suffix']??'').'</span></div>';
  return '<div class="'.$this->cls($s).' lb-counter lb-counter-title-'.$pos.'"'.$vars.'>'.($pos==='before'?$title.$value:$value.$title).'</div>';
 }
}
