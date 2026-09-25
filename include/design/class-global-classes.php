<?php
namespace CanvaslyLite\Design;
if(!defined('ABSPATH')) exit;
class GlobalClasses {
 const KEY='canvasly_lite_global_classes';
 public static function all(){
  $raw=get_option(self::KEY,[]); if(!is_array($raw))return [];
  $out=[];
  foreach($raw as $name=>$data){$name=sanitize_title($name);if(!$name)continue;$out[$name]=self::normalize($data);}
  return $out;
 }
 public static function get($name){$all=self::all();$name=sanitize_title($name);return $all[$name]??null;}
 private static function normalize($data){
  if(is_string($data)) return ['base'=>self::clean($data),'hover'=>'','focus'=>'','active'=>'','focus_visible'=>'','extends'=>[],'description'=>''];
  $d=is_array($data)?$data:[];
  $out=['base'=>self::clean($d['base']??($d['css']??'')),'hover'=>self::clean($d['hover']??''),'focus'=>self::clean($d['focus']??''),'active'=>self::clean($d['active']??''),'focus_visible'=>self::clean($d['focus_visible']??''),'extends'=>[],'description'=>sanitize_text_field($d['description']??'')];
  foreach((array)($d['extends']??[]) as $x){$x=sanitize_title($x);if($x)$out['extends'][]=$x;}
  $out['extends']=array_values(array_unique($out['extends']));
  return $out;
 }
 public static function save($name,$css,$extends=[],$description=''){
  if(!current_user_can('canvasly_lite_design'))return new \WP_Error('forbidden',__('You cannot manage global classes.', 'canvasly-lite'),['status'=>403]);
  $name=sanitize_title($name);if(!$name)return new \WP_Error('invalid',__('Class name required.', 'canvasly-lite'),['status'=>400]);
  $all=self::all();$existing=$all[$name]??self::normalize([]);
  if(is_array($css))$existing=self::normalize(array_merge($existing,$css));else $existing=self::normalize(['base'=>$css,'extends'=>$extends,'description'=>$description]);
  if(!empty($extends))$existing['extends']=array_values(array_unique(array_filter(array_map('sanitize_title',(array)$extends))));
  if(self::would_cycle($name,$existing['extends'],$all))return new \WP_Error('class_cycle',__('Class inheritance would create a cycle.', 'canvasly-lite'),['status'=>400]);
  if($description!=='')$existing['description']=sanitize_text_field($description);
  $existing['extends']=array_values(array_diff($existing['extends'],[$name]));
  $all[$name]=$existing;update_option(self::KEY,$all,false);return ['name'=>$name,'data'=>$existing];
 }
 public static function delete($name){if(!current_user_can('canvasly_lite_design'))return false;$all=self::all();unset($all[sanitize_title($name)]);foreach($all as $n=>$d){$d=self::normalize($d);$d['extends']=array_values(array_diff($d['extends'],[sanitize_title($name)]));$all[$n]=$d;}update_option(self::KEY,$all,false);return true;}
 private static function clean($css){$css=is_array($css)?'':(string)$css;return preg_replace('/<[^>]*>|expression\s*\(|javascript\s*:/i','',wp_strip_all_tags($css));}
 private static function would_cycle($name,$parents,$all,$seen=[]){
  $name=sanitize_title($name);if(isset($seen[$name]))return true;$seen[$name]=true;
  foreach((array)$parents as $p){$p=sanitize_title($p);if(!$p)continue;if($p===$name)return true;$d=$all[$p]??null;if($d&&self::would_cycle($p,(array)($d['extends']??[]),$all,$seen))return true;}
  return false;
 }
 private static function inherited($name,&$seen=[]){$all=self::all();$name=sanitize_title($name);if(isset($seen[$name]))return [];$seen[$name]=true;$d=$all[$name]??null;if(!$d)return [];$out=[];foreach((array)$d['extends'] as $parent){foreach(self::inherited($parent,$seen) as $k=>$v)$out[$k]=($out[$k]??'').$v;}$out[$name]=$d;return $out;}
 public static function css(){
  $out='';$all=self::all();
  foreach($all as $name=>$d){$states=['base'=>'','.hover'=>':hover'];$seen=[];$chain=self::inherited($name,$seen);foreach(['base'=>'','.hover'=>':hover','focus'=>':focus','active'=>':active','focus_visible'=>':focus-visible'] as $st=>$ps){$decl='';foreach($chain as $cd){$decl.=Variables::resolve_references(self::clean($cd[$st]??''));}$decl=trim($decl);if($decl!=='')$out.='.lb-class-'.sanitize_html_class($name).$ps.'{'.$decl.'}';}}
  return $out;
 }
 public static function usage($doc){$u=[];$walk=function($nodes)use(&$walk,&$u){foreach((array)$nodes as $n){$s=(array)($n['settings']??[]);foreach(preg_split('/[\s,]+/',trim((string)($s['global_class']??''))) as $c)if($c)$u[sanitize_title($c)]=($u[sanitize_title($c)]??0)+1;if(!empty($n['children']))$walk($n['children']);}};$walk($doc['root']??[]);return $u;}
 public static function resolve_classes($names){$all=self::all();$seen=[];$out=[];$walk=function($name)use(&$walk,&$all,&$seen,&$out){$name=sanitize_title($name);if(!$name||isset($seen[$name]))return;$seen[$name]=true;if(!isset($all[$name]))return;foreach((array)$all[$name]['extends'] as $p)$walk($p);$out[]=$name;};foreach((array)$names as $n)$walk($n);return $out;}
}
