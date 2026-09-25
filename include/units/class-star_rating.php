<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Star Rating: SVG stars on a 5 or 10 point scale with fractional fills, solid or outline unmarked stars, a title and alignment. */
class StarRating extends Unit {
 public function type(){return 'star_rating';} public function title(){return __('Star Rating', 'canvasly-lite');} public function icon(){return '★';} public function category(){return 'basic';}
 public function keywords(){return ['star','rating','review','score','stars'];}
 const STAR='M12 1 L14.76 8.2 L22.46 8.6 L16.47 13.45 L18.47 20.9 L12 16.7 L5.53 20.9 L7.53 13.45 L1.54 8.6 L9.24 8.2 Z';
 public function defaults(){return ['scale'=>'5','rating'=>5,'unmarked_style'=>'solid','title'=>'','align'=>'left','title_color'=>'','title_gap'=>10,'size'=>22,'star_gap'=>2,'color'=>'#f0ad4e','unmarked_color'=>'#cccccc'];}
 public function controls(){return ['scale'=>'select','rating'=>'number','unmarked_style'=>'select','title'=>'text','align'=>'select','title_color'=>'color','title_gap'=>'number','size'=>'number','star_gap'=>'number','color'=>'color','unmarked_color'=>'color'];}
 public static function star_svg($class){return '<svg class="'.esc_attr($class).'" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="'.self::STAR.'"/></svg>';}
 public function render($s,$children=''){
  $max=(string)($s['scale']??'5')==='10'?10:5;
  $rating=max(0,min($max,round(floatval($this->scalar($s['rating']??5,5)),1)));
  $unmarked=($s['unmarked_style']??'solid')==='outline'?'outline':'solid';
  $align=in_array($s['align']??'left',['left','center','right','justify'],true)?$s['align']:'left';
  $vars=$this->style_attr(['--lb-star-size'=>$this->unit($s['size']??''),'--lb-star-gap'=>$this->unit($s['star_gap']??''),'--lb-star-color'=>$s['color']??'','--lb-star-unmarked'=>$s['unmarked_color']??'','--lb-star-title-color'=>$s['title_color']??'','--lb-star-title-gap'=>$this->unit($s['title_gap']??'')]);
  $stars='';
  for($i=1;$i<=$max;$i++){
   $fill=max(0,min(1,$rating-($i-1)));
   $stars.='<span class="lb-star" style="--lb-star-fill:'.esc_attr(round($fill*100)).'%">'.self::star_svg('lb-star-base').self::star_svg('lb-star-fill').'</span>';
  }
  $out='<div class="'.$this->cls($s).' lb-star-rating lb-star-rating-'.$align.' lb-star-unmarked-'.$unmarked.'"'.$vars.'>';
  if(($s['title']??'')!=='')$out.='<span class="lb-star-rating-title">'.esc_html($s['title']).'</span>';
  $out.='<span class="lb-stars" role="img" aria-label="'.esc_attr(sprintf('%s out of %d stars',rtrim(rtrim(number_format($rating,1,'.',''),'0'),'.'),$max)).'">'.$stars.'</span>';
  return $out.'</div>';
 }
}
