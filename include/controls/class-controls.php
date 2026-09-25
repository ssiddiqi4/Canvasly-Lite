<?php
namespace CanvaslyLite\Controls;
if(!defined('ABSPATH')) exit;
/**
 * Control-type registry.
 *
 * Every control type the editor, sanitizer and CSS builder understand is listed here. Core types are
 * registered without callbacks (their behaviour lives in DocumentManager::sanitize_control() and
 * Style). Add-ons register new types with:
 *
 *   Controls::instance()->register( 'my_type', $sanitizer, $css_handler, $args );
 *
 *   $sanitizer   callable( mixed $value, string $key, array $settings ): mixed    (required for custom types)
 *   $css_handler callable( mixed $value, string $selector, string $key, array $settings, array $node ): string
 *                returns full CSS rules (selector included) or ''. Optional.
 *   $args        ['label' => string, 'default' => mixed, 'editor' => array]  — 'editor' is passed to
 *                CanvaslyLiteData.controlTypes[type].editor so the JS renderer can read options.
 *
 * Fire order: Plugin::register_units() boots this registry on `init`, which fires
 * `canvasly-lite/controls/register` before `canvasly-lite/units/register`.
 */
class Controls {
 const BUILTIN=['text','textarea','wysiwyg','url','number','slider','color','switch','select','choose','icon','media','gallery','spacing','dimensions','box_shadow','gradient','repeater','url_map','number_map','typography','border','background','text_shadow','css_filter','transform','transition','gaps','code','font'];
 private static $i;
 private $types=[];
 private $booted=false;
 public static function instance(){
  if(!self::$i){ self::$i=new self; foreach(self::BUILTIN as $t) self::$i->types[$t]=['type'=>$t,'sanitizer'=>null,'css'=>null,'builtin'=>true,'label'=>ucwords(str_replace('_',' ',$t)),'default'=>'','editor'=>[]]; }
  return self::$i;
 }
 /** Fire the registration hook once so add-ons can add control types. */
 public function boot(){
  if($this->booted) return $this;
  $this->booted=true;
  do_action('canvasly-lite/controls/register',$this);
  return $this;
 }
 public function register($type,$sanitizer=null,$css_handler=null,array $args=[]){
  $type=sanitize_key((string)$type);
  if($type==='') return false;
  if($sanitizer!==null&&!is_callable($sanitizer)) return false;
  if($css_handler!==null&&!is_callable($css_handler)) return false;
  $builtin=in_array($type,self::BUILTIN,true);
  if(!$builtin&&$sanitizer===null) return false; // custom types must say how they are sanitized
  $this->types[$type]=[
   'type'=>$type,
   'sanitizer'=>$sanitizer,
   'css'=>$css_handler,
   'builtin'=>$builtin,
   'label'=>isset($args['label'])?(string)$args['label']:ucwords(str_replace('_',' ',$type)),
   'default'=>$args['default']??'',
   'editor'=>is_array($args['editor']??null)?$args['editor']:[],
  ];
  return true;
 }
 /** Remove a custom type. Core types cannot be removed (they may be reset to core behaviour). */
 public function unregister($type){
  $type=sanitize_key((string)$type);
  if(!isset($this->types[$type])) return false;
  if($this->types[$type]['builtin']){ $this->types[$type]['sanitizer']=null; $this->types[$type]['css']=null; return true; }
  unset($this->types[$type]);
  return true;
 }
 public function has($type){return isset($this->types[sanitize_key((string)$type)]);}
 public function get($type){return $this->types[sanitize_key((string)$type)]??null;}
 public function all(){return $this->types;}
 public function types(){return array_keys($this->types);}
 public function has_sanitizer($type){$t=$this->get($type);return $t&&$t['sanitizer']!==null;}
 public function has_css($type){$t=$this->get($type);return $t&&$t['css']!==null;}
 /** Run the registered sanitizer. Callers must check has_sanitizer() first. */
 public function sanitize($type,$value,$key='',array $settings=[],array $def=[]){
  $t=$this->get($type);
  if(!$t||$t['sanitizer']===null) return $value;
  return call_user_func($t['sanitizer'],$value,$key,$settings,$def);
 }
 /** Run the registered CSS handler for one setting. Returns CSS rules or ''. */
 public function css($type,$value,$selector,$key='',array $settings=[],array $node=[]){
  $t=$this->get($type);
  if(!$t||$t['css']===null) return '';
  $out=call_user_func($t['css'],$value,$selector,$key,$settings,$node);
  return is_string($out)?$out:'';
 }
 /** CSS for every setting of a node whose control type has a CSS handler. `$controls` may be a `key => type` map or a normalized schema (`key => ['type'=>..]`). */
 public function node_css(array $controls,array $settings,$selector,array $node=[]){
  $css='';
  foreach($controls as $key=>$type){
   if(is_array($type))$type=$type['type']??null;
   if(!is_string($type)||!array_key_exists($key,$settings)) continue;
   if(!$this->has_css($type)) continue;
   $css.=$this->css($type,$settings[$key],$selector,$key,$settings,$node);
  }
  return $css;
 }
 /** Serializable description of all types for the editor (no callables). */
 public function export(){
  $out=[];
  foreach($this->types as $type=>$t) $out[$type]=['type'=>$type,'builtin'=>$t['builtin'],'label'=>$t['label'],'default'=>$t['default'],'editor'=>$t['editor'],'custom'=>!$t['builtin']||$t['sanitizer']!==null];
  return $out;
 }
}
