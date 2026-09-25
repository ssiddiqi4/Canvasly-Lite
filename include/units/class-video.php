<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Video: YouTube, Vimeo, Dailymotion, VideoPress and self-hosted sources with
 * start/end offsets, playback options, privacy mode, lazy loading, an image
 * overlay with a play icon, a lightbox mode and a fixed aspect ratio box.
 */
class Video extends Unit {
 public function type(){return 'video';} public function title(){return __('Video', 'canvasly-lite');} public function icon(){return '▶';} public function category(){return 'media';}
 public function keywords(){return ['video','player','embed','oembed','youtube','vimeo','dailymotion','videopress','mp4','lightbox'];}
 public function scripts($s=[]){
  $s=is_array($s)?$s:[];
  if(!empty($s['show_overlay'])||!empty($s['lightbox']))return $this->frontend_scripts();
  return [];
 }
 public function defaults(){return [
  'source'=>'youtube','url'=>'','start'=>'','end'=>'',
  'autoplay'=>false,'play_on_mobile'=>false,'mute'=>false,'loop'=>false,'controls'=>true,'show_related'=>false,'privacy_mode'=>false,'lazy_load'=>false,
  'preload'=>'metadata','download_button'=>false,'poster_id'=>0,'poster_url'=>'',
  'show_overlay'=>false,'overlay_image_id'=>0,'overlay_image_url'=>'','show_play_icon'=>true,'play_icon'=>'play','play_icon_color'=>'#ffffff','play_icon_size'=>72,'lightbox'=>false,
  'aspect_ratio'=>'16:9',
 ];}
 public function controls(){return [
  'source'=>'select','url'=>'url','start'=>'number','end'=>'number',
  'autoplay'=>'switch','play_on_mobile'=>'switch','mute'=>'switch','loop'=>'switch','controls'=>'switch','show_related'=>'switch','privacy_mode'=>'switch','lazy_load'=>'switch',
  'preload'=>'select','download_button'=>'switch','poster_id'=>'media','poster_url'=>'url',
  'show_overlay'=>'switch','overlay_image_id'=>'media','overlay_image_url'=>'url','show_play_icon'=>'switch','play_icon'=>'icon','play_icon_color'=>'color','play_icon_size'=>'number','lightbox'=>'switch',
  'aspect_ratio'=>'select',
 ];}
 /** Detect the provider from the URL when the user pasted a link without changing the source select. */
 public static function detect_source($url,$fallback='youtube'){
  $u=strtolower((string)$url);
  if(strpos($u,'youtu')!==false)return 'youtube';
  if(strpos($u,'vimeo')!==false)return 'vimeo';
  if(strpos($u,'dailymotion')!==false||strpos($u,'dai.ly')!==false)return 'dailymotion';
  if(strpos($u,'videopress')!==false)return 'videopress';
  if(preg_match('/\.(mp4|webm|ogv|ogg|m4v|mov)(\?|#|$)/',$u))return 'hosted';
  return $fallback;
 }
 public static function provider_id($source,$url){
  $url=(string)$url;
  switch($source){
   case 'youtube': if(preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{6,})~',$url,$m))return $m[1]; break;
   case 'vimeo': if(preg_match('~vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d+)~',$url,$m))return $m[1]; break;
   case 'dailymotion': if(preg_match('~(?:dailymotion\.com/(?:embed/)?video/|dai\.ly/)([A-Za-z0-9]+)~',$url,$m))return $m[1]; break;
   case 'videopress': if(preg_match('~videopress\.com/(?:v|embed)/([A-Za-z0-9]+)~',$url,$m))return $m[1]; break;
  }
  return '';
 }
 /** Build the provider embed URL from the unit settings. */
 public static function embed_url($s,$force_autoplay=false){
  $source=self::detect_source($s['url']??'',$s['source']??'youtube');
  $id=self::provider_id($source,$s['url']??''); if(!$id)return '';
  $start=absint($s['start']??0); $end=absint($s['end']??0); $auto=$force_autoplay||!empty($s['autoplay']);
  $q=[];
  if($source==='youtube'){
   $host=!empty($s['privacy_mode'])?'https://www.youtube-nocookie.com':'https://www.youtube.com';
   $q=['autoplay'=>$auto?1:0,'mute'=>!empty($s['mute'])||($auto&&!empty($s['play_on_mobile']))?1:0,'controls'=>!empty($s['controls'])?1:0,'rel'=>!empty($s['show_related'])?1:0,'playsinline'=>!empty($s['play_on_mobile'])?1:0];
   if(!empty($s['loop'])){$q['loop']=1;$q['playlist']=$id;}
   if($start)$q['start']=$start; if($end)$q['end']=$end;
   return $host.'/embed/'.rawurlencode($id).'?'.http_build_query($q);
  }
  if($source==='vimeo'){
   $q=['autoplay'=>$auto?1:0,'muted'=>!empty($s['mute'])?1:0,'loop'=>!empty($s['loop'])?1:0,'controls'=>!empty($s['controls'])?1:0,'playsinline'=>!empty($s['play_on_mobile'])?1:0];
   if(!empty($s['privacy_mode']))$q['dnt']=1;
   return 'https://player.vimeo.com/video/'.rawurlencode($id).'?'.http_build_query($q).($start?'#t='.$start.'s':'');
  }
  if($source==='dailymotion'){
   $q=['autoplay'=>$auto?'true':'false','mute'=>!empty($s['mute'])?'true':'false','controls'=>!empty($s['controls'])?'true':'false'];
   if($start)$q['start']=$start;
   return 'https://www.dailymotion.com/embed/video/'.rawurlencode($id).'?'.http_build_query($q);
  }
  if($source==='videopress'){
   $q=['autoPlay'=>$auto?'true':'false','muted'=>!empty($s['mute'])?'true':'false','loop'=>!empty($s['loop'])?'true':'false','controls'=>!empty($s['controls'])?'true':'false'];
   if($start)$q['at']=$start;
   return 'https://videopress.com/embed/'.rawurlencode($id).'?'.http_build_query($q);
  }
  return '';
 }
 public static function ratio_value($v){
  $map=['16:9'=>'16 / 9','21:9'=>'21 / 9','4:3'=>'4 / 3','3:2'=>'3 / 2','1:1'=>'1 / 1','9:16'=>'9 / 16'];
  return $map[$v]??'16 / 9';
 }
 private function hosted_tag($s,$autoplay=false){
  $url=esc_url($s['url']??''); if(!$url)return '';
  $start=absint($s['start']??0);$end=absint($s['end']??0);
  if($start||$end)$url.='#t='.$start.($end?','.$end:'');
  $poster=$this->media_url($s['poster_id']??0,$s['poster_url']??'','large');
  $a=' class="lb-video-media" playsinline';
  if(!empty($s['controls']))$a.=' controls';
  if($autoplay||!empty($s['autoplay']))$a.=' autoplay';
  if(!empty($s['mute'])||(!empty($s['autoplay'])&&!empty($s['play_on_mobile'])))$a.=' muted';
  if(!empty($s['loop']))$a.=' loop';
  if(empty($s['download_button']))$a.=' controlsList="nodownload"';
  $a.=' preload="'.esc_attr(in_array($s['preload']??'metadata',['none','metadata','auto'],true)?$s['preload']:'metadata').'"';
  if($poster)$a.=' poster="'.esc_url($poster).'"';
  return '<video'.$a.'><source src="'.$url.'"></video>';
 }
 private function iframe($src,$s){
  $lazy=!empty($s['lazy_load'])?' loading="lazy"':'';
  return '<iframe class="lb-video-media" src="'.esc_url($src).'" title="Video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"'.$lazy.'></iframe>';
 }
 public function render($s,$children=''){
  if(empty($s['url']))return '<div class="'.$this->cls($s).' lb-video-placeholder">'.esc_html__('Add a video URL', 'canvasly-lite').'</div>';
  $source=self::detect_source($s['url'],$s['source']??'youtube');
  $ratio=self::ratio_value($s['aspect_ratio']??'16:9');
  $hosted=$source==='hosted';
  $embed=$hosted?'':self::embed_url($s);
  $oembed='';
  if(!$hosted&&!$embed&&class_exists('\\CanvaslyLite\\Embed\\OEmbed'))$oembed=\CanvaslyLite\Embed\OEmbed::html((string)($s['url']??''));
  if(!$hosted&&!$embed&&$oembed==='')return '<div class="'.$this->cls($s).' lb-video-placeholder">'.esc_html__('This video URL could not be recognised', 'canvasly-lite').'</div>';
  $overlay=$this->media_url($s['overlay_image_id']??0,$s['overlay_image_url']??'','large');
  $use_overlay=!empty($s['show_overlay'])&&$overlay&&$oembed==='';
  $lightbox=!empty($s['lightbox'])&&$oembed==='';
  $vars=$this->style_attr(['--lb-video-ratio'=>$ratio,'--lb-play-color'=>$s['play_icon_color']??'','--lb-play-size'=>$this->unit($s['play_icon_size']??'')]);
  $classes=$this->cls($s).' lb-video lb-video-'.($oembed!==''?'oembed':$source).($use_overlay?' lb-video-has-overlay':'').($lightbox?' lb-video-lightbox':'');
  $out='<div class="'.$classes.'"'.$vars.'>';
  if($oembed!==''){
   $out.='<div class="lb-video-frame">'.$oembed.'</div>';
  }elseif($use_overlay||$lightbox){
   // The player is created on click; store the autoplaying source so the tap starts playback immediately.
   $play_src=$hosted?esc_url($s['url']):self::embed_url($s,true);
   $data=' data-lb-video-src="'.esc_attr($play_src).'" data-lb-video-kind="'.($hosted?'hosted':'embed').'" data-lb-video-lightbox="'.($lightbox?'1':'0').'" data-lb-video-ratio="'.esc_attr($ratio).'"';
   if($hosted)$data.=' data-lb-video-attrs="'.esc_attr(trim((!empty($s['controls'])?'controls ':'').(!empty($s['loop'])?'loop ':'').(!empty($s['mute'])?'muted ':''))).'"';
   $out.='<div class="lb-video-frame lb-video-overlay"'.($use_overlay?' style="background-image:url('.esc_url($overlay).')"':'').$data.'>';
   if(!$use_overlay)$out.=$hosted?$this->hosted_tag($s):$this->iframe($embed,$s);
   if(!empty($s['show_play_icon'])||!$use_overlay&&$lightbox)$out.='<button type="button" class="lb-video-play" aria-label="Play video">'.\CanvaslyLite\Utils\Icons::svg($s['play_icon']??'play').'</button>';
   $out.='</div>';
  } else {
   $out.='<div class="lb-video-frame">'.($hosted?$this->hosted_tag($s):$this->iframe($embed,$s)).'</div>';
  }
  return $out.'</div>';
 }
}
