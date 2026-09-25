<?php
namespace CanvaslyLite\Units;
if(!defined('ABSPATH')) exit;
/**
 * WordPress Widget: renders any widget registered with the widget factory
 * (Recent Posts, Categories, Search, Calendar, Tag Cloud, custom plugin widgets…)
 * with an optional title and free-form settings. Falls back to a whole sidebar.
 */
class WordPressWidget extends Unit {
 public function type(){return 'wordpress_widget';} public function title(){return __('WordPress Widget', 'canvasly-lite');} public function icon(){return 'W';} public function category(){return 'advanced';}
 public function keywords(){return ['wordpress','widget','recent posts','categories','search','calendar','archives','tag cloud'];}
 public function defaults(){return ['widget'=>'','title'=>'','widget_options'=>'','sidebar'=>'','css_class'=>''];}
 public function controls(){return ['widget'=>'select','title'=>'text','widget_options'=>'textarea','sidebar'=>'select','css_class'=>'text'];}
 /** id_base => widget class, for every registered widget. */
 public static function registry(){
  global $wp_widget_factory; $out=[];
  if(!empty($wp_widget_factory->widgets))foreach($wp_widget_factory->widgets as $class=>$w){ if($w instanceof \WP_Widget)$out[$w->id_base]=['class'=>$class,'name'=>$w->name]; }
  return $out;
 }
 public function render($s,$children=''){
  $base=sanitize_key($s['widget']??''); $reg=self::registry();
  ob_start();
  if($base&&isset($reg[$base])){
   $instance=[]; foreach(preg_split('/\r?\n/',(string)($s['widget_options']??'')) as $row){$p=explode('=',$row,2);if(count($p)===2)$instance[sanitize_key($p[0])]=sanitize_text_field(trim($p[1]));}
   if(($s['title']??'')!=='')$instance['title']=sanitize_text_field($s['title']);
   the_widget($reg[$base]['class'],$instance,['before_widget'=>'<div class="lb-wp-widget">','after_widget'=>'</div>','before_title'=>'<h3 class="lb-wp-widget-title">','after_title'=>'</h3>']);
  } elseif(!empty($s['sidebar'])&&function_exists('dynamic_sidebar')){
   if(($s['title']??'')!=='')echo '<h3 class="lb-wp-widget-title">'.esc_html($s['title']).'</h3>';
   dynamic_sidebar(sanitize_key($s['sidebar']));
  } else echo '<div class="lb-embed-placeholder">'.esc_html__('Choose a WordPress widget', 'canvasly-lite').'</div>';
  return '<div class="'.$this->cls($s).' lb-wordpress-widget">'.ob_get_clean().'</div>';
 }
}
