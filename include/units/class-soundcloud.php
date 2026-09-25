<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Audio Embed: SoundCloud player with visual / classic modes and every player toggle (artwork, sharing, comments, play count, user, buying, liking, download, colour). */
class SoundCloud extends Unit {
 public function type(){return 'soundcloud';} public function title(){return __('SoundCloud', 'canvasly-lite');} public function icon(){return '♫';} public function category(){return 'media';}
 public function keywords(){return ['soundcloud','audio','music','embed','player','podcast'];}
 public function defaults(){return ['url'=>'','visual'=>false,'height'=>166,'auto_play'=>false,'buying'=>true,'liking'=>true,'download'=>true,'show_artwork'=>true,'sharing'=>true,'show_comments'=>true,'show_playcount'=>true,'show_user'=>true,'player_color'=>'#ff5500'];}
 public function controls(){return ['url'=>'url','visual'=>'switch','height'=>'number','auto_play'=>'switch','buying'=>'switch','liking'=>'switch','download'=>'switch','show_artwork'=>'switch','sharing'=>'switch','show_comments'=>'switch','show_playcount'=>'switch','show_user'=>'switch','player_color'=>'color'];}
 public function render($s,$children=''){
  $url=esc_url_raw($s['url']??''); if(!$url)return '<div class="'.$this->cls($s).' lb-embed-placeholder">'.esc_html__('Add a SoundCloud track or playlist URL', 'canvasly-lite').'</div>';
  $visual=!empty($s['visual']);
  $flag=function($k)use($s){return !empty($s[$k])?'true':'false';};
  $params=['url'=>$url,'visual'=>$visual?'true':'false','auto_play'=>$flag('auto_play'),'buying'=>$flag('buying'),'liking'=>$flag('liking'),'download'=>$flag('download'),'show_artwork'=>$flag('show_artwork'),'sharing'=>$flag('sharing'),'show_comments'=>$flag('show_comments'),'show_playcount'=>$flag('show_playcount'),'show_user'=>$flag('show_user'),'color'=>ltrim((string)($s['player_color']??'ff5500'),'#')];
  $height=$visual?450:max(80,absint($this->scalar($s['height']??166,166)));
  $src='https://w.soundcloud.com/player/?'.http_build_query($params);
  return '<div class="'.$this->cls($s).' lb-soundcloud'.($visual?' lb-soundcloud-visual':'').'"><iframe class="lb-audio" title="SoundCloud player" width="100%" height="'.$height.'" scrolling="no" frameborder="no" allow="autoplay" loading="lazy" src="'.esc_url($src).'"></iframe></div>';
 }
}
