<?php
namespace CanvaslyLite\Units;
use CanvaslyLite\Design\Components;
use CanvaslyLite\Units\UnitRegistry;
if(!defined('ABSPATH')) exit;
class Component extends Unit {
 public function type(){return 'component';} public function title(){return __('Component', 'canvasly-lite');} public function icon(){return '◇';} public function category(){return 'advanced';}
 public function defaults(){return ['component_id'=>0,'css_class'=>'','overrides'=>[]];}
 public function controls(){return ['component_id'=>'number','css_class'=>'text','overrides'=>'textarea'];}
 public function render($s,$children=''){ $id=absint($s['component_id']??0);foreach(Components::all() as $c)if((int)$c['id']===$id&&is_array($c['document']??null)){$doc=$c['document'];if(!empty($s['overrides'])&&is_array($s['overrides']))$doc=Components::apply_overrides($doc,$s['overrides'],$c['exposed']??[]);return '<div class="'.$this->cls($s).' lb-component-instance" data-lb-component="'.$id.'" data-lb-component-version="'.esc_attr($c['version']??1).'">'.self::render_doc($doc).'</div>';}return '<div class="'.$this->cls($s).' lb-component-placeholder">'.sprintf(
		/* translators: %s: component ID */
		esc_html__('Component #%s not found', 'canvasly-lite'),
		esc_html((string)$id)
	).'</div>'; }
 private static function render_doc($doc){$out='';foreach((array)($doc['root']??[]) as $n){$e=\CanvaslyLite\Units\UnitRegistry::instance()->get($n['type']??'');if(!$e)continue;$s=$n['settings']??[];$classes='lb-component-node lb-node-'.sanitize_html_class($n['type']??'');foreach(preg_split('/\s+/',trim((string)($s['css_class']??''))) as $part){$safe=sanitize_html_class($part);if($safe)$classes.=' '.$safe;}foreach(preg_split('/[\s,]+/',trim((string)($s['global_class']??''))) as $part){$safe=sanitize_html_class($part);if($safe)$classes.=' lb-class-'.$safe;}$out.='<div class="'.esc_attr(trim($classes)).'" data-lb-component-node="'.esc_attr($n['id']??'').'">'.$e->render($s,!empty($n['children'])?self::render_doc(['root'=>$n['children']]):'').'</div>';}return $out;}
}
