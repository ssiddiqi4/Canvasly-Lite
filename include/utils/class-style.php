<?php
namespace CanvaslyLite\Utils;
use CanvaslyLite\Design\Variables;
use CanvaslyLite\Design\ThemeStyle;
use CanvaslyLite\Design\GlobalClasses;
use CanvaslyLite\Units\Unit;
use CanvaslyLite\Units\UnitRegistry;
use CanvaslyLite\Controls\Controls;
use CanvaslyLite\Controls\Groups;
use CanvaslyLite\Settings\Breakpoints;
if(!defined('ABSPATH')) exit;
class Style {
 /** Button settings that style the clickable anchor rather than its wrapper. */
 const BUTTON_INNER_KEYS=['background','background_image','background_size','background_position','background_repeat','color','text_color','hover_background','hover_text_color','padding','border_width','border_style','border_color','border_radius','radius','shadow','font_size','font_family','weight','line_height','letter_spacing','transition','cursor'];
 public static function document_css($doc){
  $root=is_array($doc['root']??null)?$doc['root']:[];
  $css=Variables::css().(class_exists(ThemeStyle::class)?ThemeStyle::css():'').(class_exists('\\CanvaslyLite\\Settings\\KitSettings')?\CanvaslyLite\Settings\KitSettings::css():'').GlobalClasses::css().Breakpoints::css();
  $walk=function($nodes,$in_loop=false)use(&$walk,&$css){
   foreach((array)$nodes as $n){
    $chunk=self::node_css($n);
    $css.=$chunk;
    if($in_loop&&!empty($n['id'])){
     $src='.lb-src-'.preg_replace('/[^a-zA-Z0-9_-]/','',(string)$n['id']);
     if($src!=='.lb-src-')$css.=str_replace('#lb-node-'.$n['id'],$src,$chunk);
    }
    $next=$in_loop||(($n['type']??'')==='collection_loop');
    $walk($n['children']??[],$next);
   }
  };
  $parts=array_merge(is_array($doc['header']??null)?$doc['header']:[],$root,is_array($doc['footer']??null)?$doc['footer']:[]);
  if(class_exists('\\CanvaslyLite\\Design\\Optimize'))\CanvaslyLite\Design\Optimize::begin($parts);
  $walk($doc['header']??[]);
  $walk($root);
  $walk($doc['footer']??[]); return $css;
 }
 /** CSS for a node list only (no global kit / variables). Used when embedding a saved loop-item template. */
 public static function nodes_css($nodes){
  $css='';
  $nodes=is_array($nodes)?$nodes:[];
  if(class_exists('\\CanvaslyLite\\Design\\Optimize'))\CanvaslyLite\Design\Optimize::begin($nodes);
  $walk=function($items)use(&$walk,&$css){foreach((array)$items as $n){$css.=self::node_css($n);$walk($n['children']??[]);}};
  $walk($nodes);
  return $css;
 }
 private static function val($v){ if(is_array($v)) return ''; return esc_attr(self::resolve_token((string)$v)); }
 private static function resolve_token($v){return Variables::resolve_references((string)$v);}
 const TYPO_KEYS=['font_family','font_size','font_weight','font_style','text_transform','text_decoration','line_height','letter_spacing','weight'];
 private static function is_typography_control($key,array $def){
  if(in_array($key,self::TYPO_KEYS,true)) return true;
  if(empty($def['selectors'])||!is_array($def['selectors'])) return false;
  $joined=implode(' ',array_values($def['selectors']));
  return (bool)preg_match('/font-(family|size|weight|style)|line-height|letter-spacing|text-transform|text-decoration/',$joined);
 }
 private static function typography_bind_css($sel,$id){
  $id=sanitize_key((string)$id); if($id==='') return '';
  $props=['font-family'=>'font-family','font-size'=>'font-size','font-weight'=>'font-weight','font-style'=>'font-style','text-transform'=>'text-transform','text-decoration'=>'text-decoration','line-height'=>'line-height','letter-spacing'=>'letter-spacing'];
  $d=[]; foreach($props as $css=>$prop){ $d[]=$css.':var(--lb-typo-'.$id.'-'.$prop.');'; }
  $targets=$sel.','.$sel.' .lb-heading,'.$sel.' .lb-text,'.$sel.' .lb-button,'.$sel.' .lb-heading-link';
  return $targets.'{'.implode('',$d).'}';
 }
 private static function length($v){
  $v=trim((string)$v);
  if($v===''||$v==='0') return '0';
  if(preg_match('/^-?\d+(\.\d+)?$/',$v)) return $v.'px';
  return $v;
 }
 private static function spacing($v){
  if(is_array($v)){ $a=[]; foreach(['top','right','bottom','left'] as $k)$a[]=self::length(self::val($v[$k]??'0')); return implode(' ',$a); }
  return self::length(self::val($v));
 }
 private static function dimensions($v){
  if(!is_array($v)) return self::length(self::val($v));
  $a=[]; foreach(['top','right','bottom','left'] as $k)$a[]=self::length(self::val($v[$k]??'0')); return implode(' ',$a);
 }
 private static function shadow($v){
  if(!is_array($v)) return self::val($v);
  $x=self::val($v['x']??0);$y=self::val($v['y']??0);$blur=self::val($v['blur']??0);$spread=self::val($v['spread']??0);$color=self::val($v['color']??'rgba(0,0,0,.15)');$in=!empty($v['inset'])?'inset ':'';
  return $in.$x.'px '.$y.'px '.$blur.'px '.$spread.'px '.$color;
 }
 public static function node_css($n){
  $id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($n['id']??'')); if(!$id)return '';
  $el=UnitRegistry::instance()->get((string)($n['type']??''));
  $s=(array)($n['settings']??[]); $sel='#lb-node-'.$id;
  if(class_exists('\\CanvaslyLite\\Dynamic\\Resolver'))$s=\CanvaslyLite\Dynamic\Resolver::settings($s,\CanvaslyLite\Dynamic\Resolver::context(),$el?$el->all_controls():[]);
  $schema=$el&&method_exists($el,'uses_schema')&&$el->uses_schema();
  $controls=$el?$el->all_controls():[];
  $sDecl=$s;
  $typoBound=sanitize_key((string)($s['typography_global']??''));
  if($typoBound){ foreach(self::TYPO_KEYS as $k) unset($sDecl[$k]); if(in_array($n['type']??'',['heading','text'],true)) unset($sDecl['size']); }
  if($schema){ foreach($controls as $k=>$def){ if(is_array($def)&&!empty($def['selectors']))unset($sDecl[$k]); } }
  $imgW=null;
  if(!$schema&&($n['type']??'')==='image'){ $imgW=$s['width']??'100%'; unset($sDecl['width']); }
  if(!$schema&&($n['type']??'')==='button'){
   // Paint the anchor, not its alignment wrapper: colours, borders, typography and hover states belong on .lb-button.
   $inner=[]; foreach(self::BUTTON_INNER_KEYS as $k){ if(array_key_exists($k,$sDecl)){ $inner[$k]=$sDecl[$k]; unset($sDecl[$k]); } }
   if($typoBound){ foreach(self::TYPO_KEYS as $k) unset($inner[$k]); }
   $css=self::declarations($sel,$sDecl,$typoBound).self::responsive_css($sel,$sDecl).self::declarations($sel.' .lb-button',$inner,$typoBound).self::responsive_css($sel.' .lb-button',$inner);
  } else $css=self::declarations($sel,$sDecl,$typoBound).self::responsive_css($sel,$sDecl);
  if(($n['type']??'')==='grid') $css.=self::grid_css($sel,$s);
  $css.=self::state_css($sel,(array)($n['styles']??[]));
  if(!empty($s['custom_css']))$css.=self::scope_custom_css($s['custom_css'],$sel);
  $active=Breakpoints::enabled();foreach(Breakpoints::names() as $bp){if(!empty($s['hide_'.$bp])&&isset($active[$bp]))$css.=Breakpoints::wrap_range($bp,$sel.'{display:none!important;}');}
  if(!$schema&&($n['type']??'')==='image'){
   $img=$sel.' .lb-image-img'; $rules=[];
   $fit=sanitize_text_field((string)($s['object_fit']??'cover')); if($fit==='')$fit='cover';
   $pos=sanitize_text_field((string)($s['object_position']??'center')); if($pos==='')$pos='center';
   $rules[]='object-fit:'.esc_attr($fit).';'; $rules[]='object-position:'.esc_attr($pos).';';
   $rules[]='width:100%;'; $rules[]='max-width:100%;'; $rules[]='display:block;';
   $h=$s['height']??''; if(is_array($h))$h=$h['desktop']??''; if(!is_array($h)&&$h!==''&&$h!==null)$rules[]='height:'.esc_attr(self::length($h)).';';
   $css.=$img.'{'.implode('',$rules).'}';
   $align=in_array($s['alignment']??'left',['left','center','right'],true)?$s['alignment']:'left';
   $items=['left'=>'flex-start','center'=>'center','right'=>'flex-end'][$align];
   $w=$imgW??'100%'; if(is_array($w))$w=$w['desktop']??'100%'; $w=trim((string)$w); if($w!==''&&preg_match('/^\d+(\.\d+)?$/',$w))$w.='px'; if($w==='')$w='100%';
   $mw=$s['max_width']??'100%'; if(is_array($mw))$mw=$mw['desktop']??'100%'; $mw=trim((string)$mw); if($mw!==''&&preg_match('/^\d+(\.\d+)?$/',$mw))$mw.='px'; if($mw==='')$mw='100%';
   $css.=$sel.'{display:flex;flex-direction:column;align-items:'.$items.';text-align:'.esc_attr($align).';max-width:100%;min-width:0;overflow:hidden;box-sizing:border-box;--lb-img-w:'.esc_attr($w).';--lb-img-max-w:'.esc_attr($mw).';--lb-object-fit:'.esc_attr($fit).';--lb-object-position:'.esc_attr($pos).';--lb-media-items:'.$items.';}';
   $css.=$sel.' .lb-image,'.$sel.' .lb-image-preview{width:'.esc_attr($w).';max-width:'.esc_attr($mw).';overflow:hidden;box-sizing:border-box;}';
   if(!empty($s['lightbox']))$css.=$sel.' .lb-image-lightbox{display:block;}';
  }
  if(in_array($n['type']??'',['video','gallery','carousel','audio','image_box','soundcloud','embed'],true)){
   $css.=$sel.'{min-width:0;max-width:100%;overflow:hidden;box-sizing:border-box;}';
  }
  // Schema selectors, then registered control-type CSS handlers, then the unit's own style_css(), then a filter.
  if($el){
   $css.=self::schema_css($controls,$s,$sel);
   if(class_exists(Controls::class))$css.=Controls::instance()->node_css($controls,$s,$sel,$n);
   $css.=(string)$el->style_css($id,$s);
  }
  if($typoBound)$css.=self::typography_bind_css($sel,$typoBound);
  if(class_exists('\\CanvaslyLite\\Design\\Interactions'))$css.=\CanvaslyLite\Design\Interactions::custom_css($n);
  if(class_exists('\\CanvaslyLite\\Design\\Optimize')){
   if(\CanvaslyLite\Design\Optimize::is_lazy_bg($id))$css=\CanvaslyLite\Design\Optimize::strip_node_bg_image($css,$id);
   $css=\CanvaslyLite\Design\Optimize::expand_selectors($css);
  }
  /** Filter the CSS generated for one node. @param string $css @param array $n Node @param Unit|null $el */
  $filtered=apply_filters('canvasly-lite/unit/style_css',$css,$n,$el);
  return is_string($filtered)?$filtered:$css;
 }

 /**
  * Compile `selectors` templates from a normalized control schema.
  * Placeholders: {{WRAPPER}} node selector, {{VALUE}} mapped CSS value, {{RAW}} stored value,
  * {{SIZE}} numeric part, {{UNIT}} unit. Responsive values emit @media blocks per enabled breakpoint.
  */
 public static function schema_css(array $controls,array $settings,$wrapper){
  $css='';
  foreach($controls as $key=>$def){
   if(!is_array($def)||empty($def['selectors'])||!is_array($def['selectors'])) continue;
   if(!empty($def['condition'])&&!Unit::condition_met($def['condition'],$settings)) continue;
   if(!array_key_exists($key,$settings)) continue;
   if(!empty($settings['typography_global'])&&self::is_typography_control($key,$def)) continue;
   $css.=self::schema_control_css($def,$settings[$key],$wrapper);
  }
  return $css;
 }
 private static function schema_control_css(array $def,$value,$wrapper){
  if(!empty($def['responsive'])&&is_array($value)&&Breakpoints::is_map($value)){
   $out=self::schema_rules($def,$value['desktop']??'',$wrapper);
   foreach(Breakpoints::cascade_order() as $dev){
    if(!array_key_exists($dev,$value)) continue;
    $chunk=self::schema_rules($def,$value[$dev],$wrapper);
    if($chunk)$out.=Breakpoints::wrap_cascade($dev,$chunk);
   }
   return $out;
  }
  return self::schema_rules($def,$value,$wrapper);
 }
 private static function schema_rules(array $def,$value,$wrapper){
  $tok=self::schema_tokens($def,$value);
  if($tok['VALUE']===''&&$tok['SIZE']===''&&$tok['RAW']==='') return '';
  $out='';
  foreach($def['selectors'] as $selector=>$decl){
   if(!is_string($selector)||!is_string($decl)||$decl==='') continue;
   $sel=str_replace('{{WRAPPER}}',$wrapper,$selector);
   $value=$tok['VALUE'];
   if(strpos($decl,'font-family:')!==false && strpos($decl,'{{VALUE}}')!==false && class_exists(Groups::class)) $value=Groups::quote_family($value);
   $rule=str_replace(['{{VALUE}}','{{RAW}}','{{SIZE}}','{{UNIT}}'],[esc_attr($value),esc_attr($tok['RAW']),esc_attr($tok['SIZE']),esc_attr($tok['UNIT'])],$decl);
   $rule=trim($rule);
   if($rule==='') continue;
   if(substr($rule,-1)!==';')$rule.=';';
   $out.=$sel.'{'.$rule.'}';
  }
  return $out;
 }
 private static function schema_tokens(array $def,$raw){
  $type=$def['type']??'text';
  if($type==='dimensions'||$type==='spacing'){
   $v=self::dimensions($raw);
   return ['VALUE'=>$v,'RAW'=>'','SIZE'=>'','UNIT'=>''];
  }
  if($type==='box_shadow'){
   $v=class_exists(Groups::class)?Groups::compile_box_shadow($raw):self::shadow($raw);
   return ['VALUE'=>$v,'RAW'=>'','SIZE'=>'','UNIT'=>''];
  }
  if(class_exists(Groups::class)&&Groups::handles($type)){
   $v=Groups::compile($type,$raw);
   return ['VALUE'=>$v,'RAW'=>'','SIZE'=>'','UNIT'=>''];
  }
  if(is_array($raw))$raw=$raw['desktop']??reset($raw);
  if(is_bool($raw))$raw=$raw?'1':'';
  $str=trim((string)$raw);
  $size='';$unit='';
  if(preg_match('/^(-?\d*\.?\d+)\s*([a-z%]*)\s*$/i',$str,$m)){
   $size=$m[1]; $unit=strtolower($m[2]);
   if($unit===''&&isset($def['units'])&&is_array($def['units'])&&$def['units']&&$def['units'][0]!=='')$unit=(string)$def['units'][0];
  }
  $css=$str;
  if($size!==''&&$unit!==''&&!preg_match('/[a-z%]/i',$str))$css=$size.$unit;
  if($type==='url'&&$css!=='')$css=esc_url($css);
  $css=self::resolve_token($css);
  $mapped=$css;
  if(!empty($def['map'])&&is_array($def['map'])&&array_key_exists($str,$def['map']))$mapped=(string)$def['map'][$str];
  return ['VALUE'=>$mapped,'RAW'=>$str,'SIZE'=>$size,'UNIT'=>$unit];
 }

 private static function grid_css($sel,$s){
  $d=[];$put=function($p,$v)use(&$d){if($v!==''&&$v!==null)$d[]=$p.':'.esc_attr(self::resolve_token($v)).';';};
  $cols=trim((string)($s['grid_template_columns']??''));
  if($cols===''){ $count=max(1,min(24,(int)($s['columns']??3))); $min=$s['min_column']??'0px'; $cols='repeat('.$count.',minmax('.$min.',1fr))'; }
  $rows=trim((string)($s['grid_template_rows']??'')); if($rows==='')$rows=trim((string)($s['rows']??''));
  $put('display','grid'); $put('grid-template-columns',$cols); if($rows!=='')$put('grid-template-rows',$rows);
  $put('grid-auto-flow',$s['auto_flow']??'row'); $put('grid-auto-columns',$s['grid_auto_columns']??'auto'); $put('grid-auto-rows',$s['grid_auto_rows']??($s['min_row']??'auto'));
  if(isset($s['column_gap'])&&!is_array($s['column_gap']))$put('column-gap',is_numeric($s['column_gap'])?$s['column_gap'].'px':$s['column_gap']);
  if(isset($s['row_gap'])&&!is_array($s['row_gap']))$put('row-gap',is_numeric($s['row_gap'])?$s['row_gap'].'px':$s['row_gap']);
  $put('align-items',$s['align']??'stretch');$put('justify-items',$s['justify']??'stretch');
  $out=$d?$sel.'{'.implode('',$d).'}':'';
  foreach(Breakpoints::cascade_order() as $dev){$rd=[];$rp=function($prop,$v)use(&$rd){if($v!==''&&$v!==null)$rd[]=$prop.':'.esc_attr(self::resolve_token($v)).' !important;';};
   foreach(['grid_template_columns','grid_template_rows','grid_auto_columns','grid_auto_rows','auto_flow','grid_column_start','grid_column_span','grid_row_start','grid_row_span','column_gap','row_gap'] as $k){if(isset($s[$k])&&is_array($s[$k])&&isset($s[$k][$dev])){ $v=$s[$k][$dev]; if($k==='grid_column_span')$rp('grid-column',$v===''?'':((isset($s['grid_column_start'])&&is_array($s['grid_column_start'])&&isset($s['grid_column_start'][$dev])&&$s['grid_column_start'][$dev]!=='')?(int)$s['grid_column_start'][$dev]:'auto').' / span '.max(1,(int)$v)); elseif($k==='grid_row_span')$rp('grid-row',$v===''?'':((isset($s['grid_row_start'])&&is_array($s['grid_row_start'])&&isset($s['grid_row_start'][$dev])&&$s['grid_row_start'][$dev]!=='')?(int)$s['grid_row_start'][$dev]:'auto').' / span '.max(1,(int)$v)); elseif(in_array($k,['column_gap','row_gap'],true)&&is_numeric($v))$rp(str_replace('_','-',$k),$v.'px'); else $rp(str_replace('_','-',$k),$v); }}
   if($rd)$out.=Breakpoints::wrap_cascade($dev,$sel.'{'.implode('',$rd).'}');
  }
  return $out;
 }
 private static function declarations($sel,$s,$skip_typo=false){
  $d=[];$put=function($p,$v)use(&$d){if($v!==''&&$v!==null)$d[]=$p.':'.esc_attr(self::resolve_token($v)).';';};
  $put('opacity',isset($s['opacity'])?max(0,min(1,(float)$s['opacity'])):'');
  $put('overflow',$s['overflow']??'');$put('position',$s['position']??'');$put('z-index',isset($s['z_index'])&&$s['z_index']!==''?absint($s['z_index']):'');
  foreach(['top','right','bottom','left'] as $p)$put($p,$s[$p]??'');
  foreach(['display','visibility','order','flex-grow','flex-shrink','flex-basis','align-self','justify-self','cursor','mix-blend-mode'] as $p){$k=str_replace('-','_',$p);$put($p,$s[$k]??'');}
  if(class_exists(Groups::class)){
   if(isset($s['filter']))$put('filter',is_array($s['filter'])?Groups::compile_filter($s['filter']):(is_string($s['filter'])?$s['filter']:''));
   if(isset($s['transform'])){ $tv=is_array($s['transform'])?Groups::compile_transform($s['transform']):(is_string($s['transform'])?$s['transform']:''); $put('transform',$tv); if($tv!==''&&is_array($s['transform'])&&!empty($s['transform']['origin']))$put('transform-origin',$s['transform']['origin']); }
   if(isset($s['transition']))$put('transition',is_array($s['transition'])?Groups::compile_transition($s['transition']):(is_string($s['transition'])?$s['transition']:''));
   if(!empty($s['typography'])&&is_array($s['typography'])){ foreach(Groups::typography_map($s['typography']) as $p=>$x)$put($p,$x); }
   if(!empty($s['border'])&&is_array($s['border'])){ foreach(Groups::border_map($s['border']) as $p=>$x)$put($p,$x); }
   if(!empty($s['gaps'])&&is_array($s['gaps']))$put('gap',Groups::compile_gaps($s['gaps']));
  }else{
   foreach(['filter','transition','transform'] as $p)$put($p,isset($s[$p])&&is_string($s[$p])?$s[$p]:'');
  }
  if(!empty($s['mask_shape'])){ $masks=['circle'=>'circle(50% at 50% 50%)','ellipse'=>'ellipse(50% 42% at 50% 50%)','hexagon'=>'polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%)','triangle'=>'polygon(50% 0%,0% 100%,100% 100%)','diamond'=>'polygon(50% 0%,100% 50%,50% 100%,0% 50%)','pill'=>'inset(0 round 999px)']; if(isset($masks[$s['mask_shape']]))$put('clip-path',$masks[$s['mask_shape']]); } if(!isset($s['grid_column_span'])||$s['grid_column_span']===''){foreach(['grid-column-start','grid-column-end','grid-row-start','grid-row-end'] as $p){$k=str_replace('-','_',$p);$put($p,$s[$k]??'');}} if(isset($s['grid_column_span'])&&$s['grid_column_span']!==''){$gcs=$s['grid_column_start']??'';$put('grid-column',((is_scalar($gcs)&&$gcs!=='')?(int)$gcs:'auto').' / span '.max(1,(int)$s['grid_column_span']));} if(isset($s['grid_row_span'])&&$s['grid_row_span']!==''){$grs=$s['grid_row_start']??'';$put('grid-row',((is_scalar($grs)&&$grs!=='')?(int)$grs:'auto').' / span '.max(1,(int)$s['grid_row_span']));}
  if(isset($s['background'])&&is_array($s['background'])&&class_exists(Groups::class)){ foreach(Groups::background_map($s['background']) as $p=>$x)$put($p,$x); }
  elseif(isset($s['background'])&&$s['background']!==''&&!is_array($s['background']))$put('background',self::resolve_token($s['background']));
  if(!empty($s['background_image'])&&empty($s['background']['image_url']))$put('background-image','url('.esc_url($s['background_image']).')');
  $put('background-size',$s['background_size']??'');$put('background-position',$s['background_position']??'');$put('background-repeat',$s['background_repeat']??'');
  if(!empty($s['background_overlay']))$put('--lb-background-overlay',$s['background_overlay']);
  $put('color',self::resolve_token($s['color']??($s['text_color']??'')));
  if(isset($s['padding']))$put('padding',self::spacing($s['padding'])); if(isset($s['margin']))$put('margin',self::spacing($s['margin']));
  if(isset($s['border_width']))$put('border-width',self::dimensions($s['border_width']));$put('border-style',$s['border_style']??'');$put('border-color',$s['border_color']??'');
  if(isset($s['border_radius']))$put('border-radius',self::dimensions($s['border_radius'])); elseif(isset($s['radius'])&&!is_array($s['radius']))$put('border-radius',floatval($s['radius']).'px');
  if(!empty($s['shadow']))$put('box-shadow',is_array($s['shadow'])&&class_exists(Groups::class)?Groups::compile_box_shadow($s['shadow']):self::shadow($s['shadow']));
  if(!empty($s['box_shadow'])&&empty($s['shadow']))$put('box-shadow',is_array($s['box_shadow'])&&class_exists(Groups::class)?Groups::compile_box_shadow($s['box_shadow']):self::shadow($s['box_shadow']));
  foreach(['width','height','max_width','min_height','max_height'] as $dim){if(!isset($s[$dim]))continue;$v=$s[$dim];if(is_array($v))$v=$v['desktop']??'';if(!is_string($v)&&!is_numeric($v))continue;$v=trim((string)$v);if($v!==''&&preg_match('/^\d+(\.\d+)?$/',$v))$v.='px';$put(str_replace('_','-',$dim),$v);}
  if($d)$out=' '.$sel.'{'.implode('',$d).'}'; else $out=''; if(!empty($s['hover_background'])||!empty($s['hover_text_color'])){ $hd=[]; if(!empty($s['hover_background']))$hd[]='background:'.esc_attr(self::resolve_token($s['hover_background'])).';'; if(!empty($s['hover_text_color']))$hd[]='color:'.esc_attr(self::resolve_token($s['hover_text_color'])).';'; $out.=$sel.':hover{'.implode('',$hd).'}'; } if(!$skip_typo){ if(!empty($s['font_size'])&&!is_array($s['font_size']))$out.=$sel.'{font-size:'.esc_attr(self::resolve_token($s['font_size'])).'px;}'; if(!empty($s['size'])&&!is_array($s['size'])&&in_array($s['unit_type']??'', ['heading','text'],true))$out.=$sel.'{font-size:'.esc_attr($s['size']).'px;}'; if(isset($s['font_family'])&&$s['font_family']!=='')$out.=$sel.'{font-family:'.esc_attr(class_exists(Groups::class)?Groups::quote_family(self::resolve_token($s['font_family'])):self::resolve_token($s['font_family'])).';}'; if(isset($s['weight'])&&$s['weight']!=='')$out.=$sel.'{font-weight:'.self::resolve_token($s['weight']).';}'; if(isset($s['line_height'])&&!is_array($s['line_height']))$out.=$sel.'{line-height:'.esc_attr(self::resolve_token($s['line_height'])).';}'; if(isset($s['letter_spacing'])&&!is_array($s['letter_spacing']))$out.=$sel.'{letter-spacing:'.esc_attr(self::resolve_token($s['letter_spacing'])).'px;}'; } if(isset($s['align'])&&$s['align']!=='')$out.=$sel.'{text-align:'.esc_attr($s['align']).';}'; if(!empty($s['text_shadow'])){ $ts=is_array($s['text_shadow'])&&class_exists(Groups::class)?Groups::compile_text_shadow($s['text_shadow']):(is_string($s['text_shadow'])?$s['text_shadow']:''); if($ts!=='')$out.=$sel.'{text-shadow:'.esc_attr($ts).';}'; } return $out;
 }
 private static function responsive_css($sel,$s){
  $out='';
  foreach(Breakpoints::cascade_order() as $dev){$d=[];$put=function($p,$v,$suffix='')use(&$d){if($v!==''&&$v!==null)$d[]=$p.':'.esc_attr(self::resolve_token($v)).$suffix.';';};
   foreach(['width','height','min_height','max_width','max_height','font_size','size','line_height','letter_spacing','gap','column_gap','row_gap','opacity','order','flex_grow','flex_shrink','flex_basis','top','right','bottom','left'] as $k){if(isset($s[$k])&&is_array($s[$k])&&isset($s[$k][$dev])){$suffix=in_array($k,['font_size','size','letter_spacing','gap','column_gap','row_gap'],true)?'px':'';$prop=str_replace('_','-',$k);$put($prop,$s[$k][$dev],$suffix);}}
   foreach(['padding'=>'padding','margin'=>'margin','border_width'=>'border-width','border_radius'=>'border-radius'] as $k=>$prop){if(isset($s[$k])&&is_array($s[$k])&&isset($s[$k][$dev]))$put($prop,self::dimensions($s[$k][$dev]));}
   if(isset($s['background'])&&is_array($s['background'])&&isset($s['background'][$dev]))$put('background',$s['background'][$dev]);
   if(isset($s['color'])&&is_array($s['color'])&&isset($s['color'][$dev]))$put('color',$s['color'][$dev]);
   foreach(['grid_template_columns','grid_template_rows','grid_auto_columns','grid_auto_rows','auto_flow','grid_column_start','grid_column_span','grid_row_start','grid_row_span'] as $k){if(isset($s[$k])&&is_array($s[$k])&&isset($s[$k][$dev])){ $prop=str_replace('_','-',$k); $v=$s[$k][$dev]; if($k==='grid_column_span')$put('grid-column',$v===''?'':((isset($s['grid_column_start'])&&is_array($s['grid_column_start'])&&isset($s['grid_column_start'][$dev])&&$s['grid_column_start'][$dev]!=='')?(int)$s['grid_column_start'][$dev]:'auto').' / span '.max(1,(int)$v)); elseif($k==='grid_row_span')$put('grid-row',$v===''?'':((isset($s['grid_row_start'])&&is_array($s['grid_row_start'])&&isset($s['grid_row_start'][$dev])&&$s['grid_row_start'][$dev]!=='')?(int)$s['grid_row_start'][$dev]:'auto').' / span '.max(1,(int)$v)); else { $put($prop,$v); if(in_array($k,['grid_template_columns','grid_template_rows','grid_auto_columns','grid_auto_rows','auto_flow','grid_column_start','grid_column_span','grid_row_start','grid_row_span'],true) && !empty($d)){ $d[count($d)-1]=rtrim($d[count($d)-1],';'). ' !important;'; } } }}
   if($d)$out.=Breakpoints::wrap_cascade($dev,$sel.'{'.implode('',$d).'}');
  } return $out;
 }
 private static function state_css($sel,$styles){$out='';foreach(['hover'=>':hover','focus'=>':focus','active'=>':active','focus_visible'=>':focus-visible'] as $state=>$pseudo){if(empty($styles[$state])||!is_array($styles[$state]))continue;$d='';foreach($styles[$state] as $k=>$v){$prop=str_replace('_','-',sanitize_key($k));if(is_array($v))continue;$d.=$prop.':'.esc_attr($v).';';}if($d)$out.=$sel.$pseudo.'{'.$d.'}';}return $out;}
 private static function scope_custom_css($css,$sel){
  $css=preg_replace('/<[^>]*>|expression\s*\(|javascript\s*:/i','',wp_strip_all_tags((string)$css));
  $css=preg_replace('/\bbody\b|\bhtml\b|:root/i','.lb-scope',$css);$css=str_replace('{','{',$css);
  $rules=''; foreach(explode('}',trim($css,'}')) as $rule){$p=explode('{',$rule,2);if(count($p)!==2)continue;$rules.=$sel.' '.trim($p[0]).'{'.trim($p[1]).'}';}
  return $rules;
 }
}
