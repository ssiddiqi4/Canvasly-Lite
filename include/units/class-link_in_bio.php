<?php
namespace CanvaslyLite\Units;
if(!defined('ABSPATH')) exit;
class LinkInBio extends Unit {
 public function type(){return 'link_in_bio';} public function title(){return __('Link in Bio', 'canvasly-lite');} public function icon(){return '↗';} public function category(){return 'content';}
 public function uses_button(){return true;}
 public function defaults(){return ['title'=>__('My Links', 'canvasly-lite'),'subtitle'=>'Find me online','avatar'=>'','links'=>'Website|https://example.com\nInstagram|https://instagram.com\nYouTube|https://youtube.com','layout'=>'stack','css_class'=>''];}
 public function controls(){return ['title'=>'text','subtitle'=>'text','avatar'=>'url','links'=>'textarea','layout'=>'select','css_class'=>'text'];}
 public function render($s,$children=''){ $out='<div class="'.$this->cls($s).' lb-link-bio lb-link-bio-'.sanitize_html_class($s['layout']??'stack').'">';if(!empty($s['avatar']))$out.='<img class="lb-link-bio-avatar" src="'.esc_url($s['avatar']).'" alt="">';$out.='<h3>'.esc_html($s['title']??__('My Links', 'canvasly-lite')).'</h3><p>'.esc_html($s['subtitle']??'').'</p><div class="lb-link-bio-links">';foreach(preg_split('/\r?\n/',(string)($s['links']??'')) as $line){$p=explode('|',$line,2);if(!empty($p[0]))$out.='<a href="'.esc_url($p[1]??'#').'" target="_blank" rel="noopener noreferrer">'.esc_html($p[0]).'</a>';}return $out.'</div></div>'; }
}
