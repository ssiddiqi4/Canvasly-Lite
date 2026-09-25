<?php
namespace CanvaslyLite\Utils;
if(!defined('ABSPATH')) exit;
class Icons {
 private static $icons=null;
 private static function all(){
  if(self::$icons!==null)return self::$icons;
  $file=CANVASLY_LITE_PATH.'assets/data/fontawesome-free-icons.json';
  $data=[];
  $x=class_exists(JsonCache::class)?JsonCache::read($file):null;
  if(!is_array($x)&&is_readable($file)){ $raw=file_get_contents($file); $x=json_decode($raw,true); }
  if(is_array($x))foreach($x as $i){if(!empty($i['id']))$data[$i['id']]=$i;}
  $custom=(array)get_option('canvasly_lite_custom_icons',[]);
  foreach($custom as $id=>$i){if(!empty($i['id']))$data[$i['id']]=$i;}
  if(class_exists('\\CanvaslyLite\\Design\\IconLibrary')){
   foreach(\CanvaslyLite\Design\IconLibrary::registered() as $i){
    if(is_array($i)&&!empty($i['id']))$data[sanitize_key((string)$i['id'])]=$i;
   }
  }
  return self::$icons=$data;
 }
 public static function flush_runtime(){ self::$icons=null; }
 public static function svg($id,$class=''){
  $id=sanitize_key((string)$id); $i=self::all()[$id]??null;
  if(!$i && $id==='spark')$i=self::all()['star']??null;
  if(!$i)return '<span class="lb-icon-fallback" aria-hidden="true">★</span>';
  if(!empty($i['svg'])){
   $svg=wp_kses($i['svg'],['svg'=>['viewBox'=>true,'viewbox'=>true,'aria-hidden'=>true,'role'=>true,'xmlns'=>true,'width'=>true,'height'=>true,'class'=>true], 'path'=>['d'=>true,'fill'=>true,'stroke'=>true,'stroke-width'=>true,'fill-rule'=>true,'clip-rule'=>true]]);
   return preg_replace('/<svg\b/i','<svg class="'.esc_attr(trim('lb-fa-icon '.$class)).'" width="1em" height="1em"', $svg,1);
  }
  $w=absint($i['width']??512);$h=absint($i['height']??512);$path=$i['path']??'';
  return '<svg class="'.esc_attr(trim('lb-fa-icon '.$class)).'" width="1em" height="1em" viewBox="0 0 '.$w.' '.$h.'" aria-hidden="true" focusable="false"><path d="'.esc_attr($path).'" fill="currentColor"></path></svg>';
 }
}
