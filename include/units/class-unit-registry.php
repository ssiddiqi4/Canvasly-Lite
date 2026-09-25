<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Unit registry.
 *
 * Core units are registered by Plugin::register_units() on `init`; afterwards `boot()` fires
 * `canvasly-lite/units/register` with the registry so add-ons can call register()/unregister().
 * register() accepts an Unit instance or a fully-qualified class name extending Unit.
 *
 * Core widgets may be registered lazily (file + class) so frontend and wp-admin screens that
 * never render a Canvasly tree do not parse 50+ unit files on every request.
 */
class UnitRegistry {
 private static $i;
 private $items=[];
 /** @var array<string,array{file:string,class:string}> */
 private $lazy=[];
 private $booted=false;
 public static function instance(){return self::$i?:self::$i=new self;}
 public static function reset_for_tests(){
  if(self::$i){
   self::$i->items=[];
   self::$i->lazy=[];
   self::$i->booted=false;
  }
  self::$i=null;
 }
 /** @param Unit|string $e Instance or class name. @return bool */
 public function register($e){
  if(is_string($e)){ if(!class_exists($e)) return false; $e=new $e; }
  if(!($e instanceof Unit)) return false;
  $type=sanitize_key((string)$e->type());
  if($type==='') return false;
  unset($this->lazy[$type]);
  $this->items[$type]=$e;
  return true;
 }
 /**
  * Queue a core unit to load on first get()/all()/has() use.
  *
  * @param string $type  Unit type() key.
  * @param string $file  Absolute path to class-*.php.
  * @param string $class Fully-qualified class name.
  */
 public function register_lazy($type,$file,$class){
  $type=sanitize_key((string)$type);
  if($type===''||isset($this->items[$type])) return false;
  $file=(string)$file;
  $class=(string)$class;
  if($file===''||$class==='') return false;
  $this->lazy[$type]=['file'=>$file,'class'=>$class];
  return true;
 }
 public function unregister($t){
  $t=sanitize_key((string)$t);
  $had=isset($this->items[$t])||isset($this->lazy[$t]);
  unset($this->items[$t],$this->lazy[$t]);
  return $had;
 }
 public function has($t){
  $t=sanitize_key((string)$t);
  return isset($this->items[$t])||isset($this->lazy[$t]);
 }
 public function get($t){
  $t=sanitize_key((string)$t);
  if(!isset($this->items[$t])) $this->realize($t);
  return $this->items[$t]??null;
 }
 public function all(){
  $this->realize_all();
  return $this->items;
 }
 /** Fire the add-on registration hook once, after core units exist. */
 public function is_booted(){return $this->booted;}
 public function boot(){
  if($this->booted) return $this;
  $this->booted=true;
  do_action('canvasly-lite/units/register',$this);
  do_action('canvasly-lite/elements/register',$this);
  return $this;
 }
 private function realize($type){
  if(isset($this->items[$type])||!isset($this->lazy[$type])) return;
  $spec=$this->lazy[$type];
  unset($this->lazy[$type]);
  $file=$spec['file']??'';
  $class=$spec['class']??'';
  if($file!==''&&is_readable($file)) require_once $file;
  if($class===''||!class_exists($class,false)) return;
  $this->register(new $class);
 }
 private function realize_all(){
  foreach(array_keys($this->lazy) as $type) $this->realize($type);
 }
}
