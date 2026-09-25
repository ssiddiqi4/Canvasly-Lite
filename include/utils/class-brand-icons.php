<?php
namespace CanvaslyLite\Utils;
if(!defined('ABSPATH')) exit;
/**
 * Badge glyphs for the social widget: white marks on a brand-colored tile,
 * matching the familiar circle and rounded-square social icon sets.
 */
class BrandIcons {
 private static $marks=null;
 private static function marks(){
  if(self::$marks!==null)return self::$marks;
  $file=CANVASLY_LITE_PATH.'assets/data/social-brand-icons.json';
  $data=class_exists(JsonCache::class)?JsonCache::read($file):null;
  if(!is_array($data)&&is_readable($file)){ $raw=file_get_contents($file); $data=json_decode($raw,true); }
  return self::$marks=is_array($data)?$data:[];
 }
 public static function flush_runtime(){ self::$marks=null; }
 public static function svg($id){
  $id=sanitize_key((string)$id);
  $parts=self::marks()[$id]??null;
  if(!is_array($parts)||!$parts)return '';
  $inner='';
  foreach($parts as $p){
   if(!is_array($p))continue;
   if(!empty($p['text'])){
    $inner.='<text x="'.esc_attr((string)($p['x']??12)).'" y="'.esc_attr((string)($p['y']??16)).'" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="'.esc_attr((string)($p['size']??10)).'" font-weight="700" fill="currentColor">'.esc_html((string)$p['text']).'</text>';
    continue;
   }
   if(empty($p['d']))continue;
   $cls=!empty($p['class'])?' class="'.esc_attr((string)$p['class']).'"':'';
   $rule=!empty($p['rule'])?' fill-rule="'.esc_attr((string)$p['rule']).'"':'';
   $tf=!empty($p['transform'])?' transform="'.esc_attr((string)$p['transform']).'"':'';
   $inner.='<path'.$cls.' d="'.esc_attr((string)$p['d']).'" fill="'.esc_attr((string)($p['fill']??'currentColor')).'"'.$rule.$tf.'></path>';
  }
  if($inner==='')return '';
  return '<svg class="lb-fa-icon lb-brand-icon" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.$inner.'</svg>';
 }
}
