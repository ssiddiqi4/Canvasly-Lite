<?php
namespace CanvaslyLite\Design;
if(!defined('ABSPATH')) exit;
class DesignSystem {
 const SCHEMA='2.1';
 public static function export(){
  $out=['schema'=>self::SCHEMA,'exported_at'=>current_time('c'),'variables'=>Variables::all(),'theme_style'=>class_exists(ThemeStyle::class)?ThemeStyle::all():[],'kit_settings'=>class_exists('\\CanvaslyLite\\Settings\\KitSettings')?\CanvaslyLite\Settings\KitSettings::all():[],'classes'=>GlobalClasses::all(),'components'=>Components::all(),'global_settings'=>class_exists('\\CanvaslyLite\\Settings\\GlobalSettings')?\CanvaslyLite\Settings\GlobalSettings::get():[],'atomic'=>Atomic::types(),'templates'=>class_exists(Kit::class)?Kit::templates():[]];
  /** Filter the design-system / kit JSON payload. @param array $out */
  $filtered=apply_filters('canvasly-lite/design_system/export',$out);
  return is_array($filtered)?$filtered:$out;
 }
 public static function import($d,$mode='merge'){
  $d=(array)$d;$mode=in_array($mode,['merge','replace'],true)?$mode:'merge';
  if($mode==='replace'&&current_user_can('manage_options')){delete_option(GlobalClasses::KEY);delete_option(Variables::KEY);if(class_exists(ThemeStyle::class))delete_option(ThemeStyle::KEY);if(class_exists('\\CanvaslyLite\\Settings\\KitSettings'))delete_option(\CanvaslyLite\Settings\KitSettings::KEY);$q=new \WP_Query(['post_type'=>'lb_component','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true]);foreach((array)$q->posts as $cid)wp_delete_post($cid,true);if(class_exists(Kit::class)&&isset($d['templates'])&&is_array($d['templates']))Kit::delete_templates_for_replace();}
  if(isset($d['variables'])&&is_array($d['variables']))Variables::save($d['variables']);
  if(isset($d['theme_style'])&&is_array($d['theme_style'])&&class_exists(ThemeStyle::class))ThemeStyle::save($d['theme_style']);
  if(isset($d['kit_settings'])&&is_array($d['kit_settings'])&&class_exists('\\CanvaslyLite\\Settings\\KitSettings'))\CanvaslyLite\Settings\KitSettings::save($d['kit_settings']);
  if(isset($d['classes'])&&is_array($d['classes']))foreach($d['classes'] as $name=>$css){$x=is_array($css)?$css:['base'=>$css];GlobalClasses::save($name,$x,$x['extends']??[],$x['description']??'');}
  if(isset($d['global_settings'])&&is_array($d['global_settings'])&&current_user_can('manage_options'))update_option(\CanvaslyLite\Settings\GlobalSettings::KEY,$d['global_settings'],false);
  if(isset($d['components'])&&is_array($d['components']))foreach($d['components'] as $c){if(!is_array($c)||empty($c['title'])||empty($c['document']))continue;Components::save($c['title'],$c['document'],$c['exposed']??[],0,$c['key']??'');}
  if(isset($d['templates'])&&is_array($d['templates'])&&class_exists(Kit::class))Kit::import_templates($d['templates'],'merge');
  return self::export();
 }
}
