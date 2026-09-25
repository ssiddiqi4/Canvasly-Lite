<?php
namespace CanvaslyLite\Design;
if(!defined('ABSPATH')) exit;
class IconLibrary {
 const KEY='canvasly_lite_custom_icons';
 public static function builtins(){return [
  ['id'=>'spark','title'=>'Spark','category'=>'Shapes','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2z" fill="currentColor"/></svg>'],
  ['id'=>'arrow','title'=>'Arrow','category'=>'Arrows','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11h12.2l-4.6-4.6L13 5l7 7-7 7-1.4-1.4 4.6-4.6H4v-2z" fill="currentColor"/></svg>'],
  ['id'=>'check','title'=>'Check','category'=>'Interface','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.2 17.2L4.8 12.8l1.4-1.4 3 3L17.8 5.8l1.4 1.4-10 10z" fill="currentColor"/></svg>'],
  ['id'=>'plus','title'=>'Plus','category'=>'Interface','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5z" fill="currentColor"/></svg>'],
  ['id'=>'menu','title'=>'Menu','category'=>'Interface','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z" fill="currentColor"/></svg>'],
  ['id'=>'play','title'=>'Play','category'=>'Media','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5l11 7-11 7V5z" fill="currentColor"/></svg>'],
  ['id'=>'mail','title'=>'Mail','category'=>'Communication','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3V5zm2 2v.5l7 5 7-5V7l-7 5-7-5z" fill="currentColor"/></svg>'],
  ['id'=>'phone','title'=>'Phone','category'=>'Communication','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h3l1.5 4-2 1.2a14 14 0 006.3 6.3l1.2-2 4 1.5v3c0 1-1 2-2 2C11.3 19 5 12.7 5 5c0-1 1-2 2-2z" fill="currentColor"/></svg>'],
  ['id'=>'heart','title'=>'Heart','category'=>'Shapes','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20S4 15.3 4 9.5A4.5 4.5 0 018.5 5c1.4 0 2.7.7 3.5 1.8A4.4 4.4 0 0115.5 5 4.5 4.5 0 0120 9.5C20 15.3 12 20 12 20z" fill="currentColor"/></svg>'],
  ['id'=>'image','title'=>'Image','category'=>'Media','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4V4zm2 2v12h12V6H6zm2 8l2.5-3 2 2 2-2.5L18 16H8z" fill="currentColor"/></svg>'],
  ['id'=>'star','title'=>'Star','category'=>'Shapes','svg'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3-5.8 3 1.1-6.5-4.7-4.6 6.5-.9L12 2.5z" fill="currentColor"/></svg>']
 ];}
 public static function all(){return array_merge(self::builtins(),array_values((array)get_option(self::KEY,[])),self::registered());}
 /**
  * Icons added on `canvasly-lite/icons/register`. Fired each call so a set
  * registered during the request is visible to the editor and to Icons::svg().
  *
  * @return array<int,array>
  */
 public static function registered(){
  $sets=new IconSets();
  do_action('canvasly-lite/icons/register',$sets);
  $icons=$sets->all();
  return is_array($icons)?$icons:[];
 }
 public static function save($id,$title,$category,$svg){if(!current_user_can('canvasly_lite_design'))return new \WP_Error('forbidden',__('You cannot manage custom icons.', 'canvasly-lite'));$id=sanitize_key($id?:sanitize_title($title));$svg=wp_kses($svg,['svg'=>['viewBox'=>true,'viewbox'=>true,'aria-hidden'=>true,'role'=>true,'xmlns'=>true,'width'=>true,'height'=>true],'path'=>['d'=>true,'fill'=>true,'stroke'=>true,'stroke-width'=>true,'fill-rule'=>true,'clip-rule'=>true]]);if(!$id||strpos($svg,'<svg')===false)return new \WP_Error('invalid',__('Valid SVG icon required.', 'canvasly-lite'));$all=(array)get_option(self::KEY,[]);$all[$id]=['id'=>$id,'title'=>sanitize_text_field($title?:$id),'category'=>sanitize_text_field($category?:'Custom'),'svg'=>$svg];update_option(self::KEY,$all,false);return $all[$id];}
 public static function delete($id){if(!current_user_can('canvasly_lite_design'))return false;$all=(array)get_option(self::KEY,[]);unset($all[sanitize_key($id)]);update_option(self::KEY,$all,false);return true;}
}
