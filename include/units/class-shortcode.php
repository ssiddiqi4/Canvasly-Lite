<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
class Shortcode extends Unit {
 public function type(){return 'shortcode';} public function title(){return __('Shortcode', 'canvasly-lite');} public function icon(){return '[]';} public function category(){return 'basic';}
 public function defaults(){return ['shortcode'=>''];} public function controls(){return ['shortcode'=>'text'];}
 public function render($s,$children=''){ return '<div class="lb-shortcode">'.do_shortcode(wp_kses_post($s['shortcode']??'')).'</div>'; }
}
