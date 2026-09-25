<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
class Code extends Unit { public function type(){return 'code';} public function title(){return __('Code', 'canvasly-lite');} public function icon(){return '</>'; } public function defaults(){return ['code'=>'','language'=>'text'];} public function controls(){return ['code'=>'code','language'=>'select'];} public function render($s,$children=''){return '<pre class="lb-code"><code>'.esc_html($s['code']??'').'</code></pre>';}}
