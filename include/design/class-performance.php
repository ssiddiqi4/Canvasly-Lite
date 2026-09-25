<?php
namespace CanvaslyLite\Design;
if(!defined('ABSPATH')) exit;
class Performance {
 const MANIFEST='canvasly_lite_asset_manifest';
 public static function manifest(){return ['version'=>CANVASLY_LITE_VERSION,'editor'=>CANVASLY_LITE_URL.'assets/js/editor.js','frontend'=>CANVASLY_LITE_URL.'assets/js/frontend.js','frontend_css'=>CANVASLY_LITE_URL.'assets/css/frontend.css'];}
 public static function invalidate($post_id){
  delete_post_meta(absint($post_id),'_lb_css_cache');
  update_post_meta(absint($post_id),'_lb_asset_version',CANVASLY_LITE_VERSION.'-'.time());
  if(class_exists(CssPrint::class))CssPrint::invalidate_post($post_id);
  if(class_exists(Optimize::class))Optimize::invalidate_post($post_id);
 }
 public static function version($post_id){return get_post_meta(absint($post_id),'_lb_asset_version',true)?:CANVASLY_LITE_VERSION;}
 public static function minify_css($css){$css=preg_replace('/\/\*.*?\*\//s','',$css);$css=preg_replace('/\s+/',' ',$css);return trim($css);}
}
