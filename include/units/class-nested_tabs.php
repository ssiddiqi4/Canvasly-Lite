<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Nested Tabs: each repeater item is a slot that can hold any child unit.
 * The text-only Tabs widget is kept for legacy documents.
 */
class NestedTabs extends Unit {
 public function type(){return 'nested_tabs';}
 public function title(){return __('Nested Tabs', 'canvasly-lite');}
 public function icon(){return '⧉';}
 public function category(){return 'basic';}
 public function keywords(){return ['tabs','nested','panel','slot','tab'];}
 public function scripts($s=[]){return $this->frontend_scripts();}
 public function supports_children(){return true;}
 public function slot_source(){return 'tabs';}
 public function defaults(){return [
  'tabs'=>[
   ['_id'=>'tab1','title'=>'Tab 1'],
   ['_id'=>'tab2','title'=>'Tab 2'],
   ['_id'=>'tab3','title'=>'Tab 3'],
  ],
  'active'=>0,'orientation'=>'horizontal','tabs_align'=>'start','title_tag'=>'div','nav_width'=>'25%',
  'tab_color'=>'','tab_active_color'=>'','tab_background'=>'','tab_active_background'=>'','content_color'=>'','content_background'=>'','border_color'=>'#d7dce2','border_width'=>1,'tab_padding'=>'','content_padding'=>'',
 ];}
 public function controls(){
  $tabs=__('Tabs', 'canvasly-lite'); $title=__('Title', 'canvasly-lite'); $content=__('Content', 'canvasly-lite');
  return [
   'tabs'=>$this->ctrl('repeater',__('Tabs', 'canvasly-lite'),'content',$tabs,[
    'title_field'=>'{{title}}','prevent_empty'=>true,
    'fields'=>[
     'title'=>$this->field('text',__('Title', 'canvasly-lite')),
    ],
   ]),
   'active'=>$this->ctrl('number',__('Active Tab', 'canvasly-lite'),'content',$tabs,['range'=>['min'=>0,'max'=>20]]),
   'orientation'=>$this->ctrl('select',__('Orientation', 'canvasly-lite'),'content',$tabs,['options'=>['horizontal'=>__('Horizontal', 'canvasly-lite'),'vertical'=>__('Vertical', 'canvasly-lite')]]),
   'tabs_align'=>$this->ctrl('select',__('Alignment', 'canvasly-lite'),'content',$tabs,['options'=>['start'=>__('Start', 'canvasly-lite'),'center'=>__('Center', 'canvasly-lite'),'end'=>__('End', 'canvasly-lite'),'stretch'=>__('Stretch', 'canvasly-lite')]]),
   'title_tag'=>$this->ctrl('select',__('Title HTML Tag', 'canvasly-lite'),'content',$tabs,['options'=>self::opt_title_tags()]),
   'nav_width'=>$this->ctrl('text',__('Navigation Width', 'canvasly-lite'),'style',$tabs,['condition'=>['orientation'=>'vertical']]),
   'tab_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$title),
   'tab_active_color'=>$this->ctrl('color',__('Active Color', 'canvasly-lite'),'style',$title),
   'tab_background'=>$this->ctrl('color',__('Background', 'canvasly-lite'),'style',$title),
   'tab_active_background'=>$this->ctrl('color',__('Active Background', 'canvasly-lite'),'style',$title),
   'tab_padding'=>$this->ctrl('text',__('Padding', 'canvasly-lite'),'style',$title),
   'content_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$content),
   'content_background'=>$this->ctrl('color',__('Background', 'canvasly-lite'),'style',$content),
   'content_padding'=>$this->ctrl('text',__('Padding', 'canvasly-lite'),'style',$content),
   'border_color'=>$this->ctrl('color',__('Border Color', 'canvasly-lite'),'style',$tabs),
   'border_width'=>$this->ctrl('number',__('Border Width', 'canvasly-lite'),'style',$tabs),
  ];
 }
 public function render($s,$children=''){
  $slots=$this->slots($s);
  if(!$slots)return '<div class="lb-embed-placeholder">'.esc_html__('Add tabs', 'canvasly-lite').'</div>';
  $html=is_array($children)?$children:[];
  $active=max(0,min(count($slots)-1,absint($s['active']??0)));
  $vertical=($s['orientation']??'horizontal')==='vertical';
  $align=in_array($s['tabs_align']??'start',['start','center','end','stretch'],true)?$s['tabs_align']:'start';
  $tag=$this->tag($s['title_tag']??'div',$this->title_tags(),'div');
  $uid='lb'.substr(md5(wp_json_encode($slots).(string)($s['css_id']??'').wp_rand()),0,8);
  $vars=$this->style_attr([
   '--lb-tabs-nav-width'=>$vertical?sanitize_text_field((string)($s['nav_width']??'25%')):'',
   '--lb-tab-color'=>$s['tab_color']??'','--lb-tab-active-color'=>$s['tab_active_color']??'','--lb-tab-bg'=>$s['tab_background']??'','--lb-tab-active-bg'=>$s['tab_active_background']??'',
   '--lb-tab-content-color'=>$s['content_color']??'','--lb-tab-content-bg'=>$s['content_background']??'','--lb-tab-border-color'=>$s['border_color']??'','--lb-tab-border-width'=>$this->unit($s['border_width']??''),
   '--lb-tab-padding'=>sanitize_text_field((string)($s['tab_padding']??'')),'--lb-tab-content-padding'=>sanitize_text_field((string)($s['content_padding']??'')),
  ]);
  $nav='';$panels='';
  foreach($slots as $i=>$slot){
   $is=$i===$active; $tid=$uid.'-tab'.$i; $pid=$uid.'-panel'.$i;
   $sid=$slot['id']; $title=$slot['title']!==''?$slot['title']:sprintf(/* translators: %d tab index */__('Tab %d', 'canvasly-lite'),$i+1);
   $nav.='<'.$tag.' class="lb-tab-button'.($is?' is-active':'').'" role="tab" tabindex="'.($is?'0':'-1').'" id="'.esc_attr($tid).'" aria-selected="'.($is?'true':'false').'" aria-controls="'.esc_attr($pid).'">'.wp_kses_post($title).'</'.$tag.'>';
   $panels.='<div class="lb-tab-panel lb-slot-panel" role="tabpanel" id="'.esc_attr($pid).'" data-lb-slot="'.esc_attr($sid).'" aria-labelledby="'.esc_attr($tid).'"'.($is?'':' hidden').'>'.($html[$sid]??'').'</div>';
  }
  return '<div class="'.$this->cls($s).' lb-tabs-widget lb-nested-tabs lb-tabs-'.($vertical?'vertical':'horizontal').' lb-tabs-align-'.$align.'" data-active="'.$active.'"'.$vars.'><div class="lb-tabs-nav" role="tablist" aria-orientation="'.($vertical?'vertical':'horizontal').'">'.$nav.'</div><div class="lb-tabs-panels">'.$panels.'</div></div>';
 }
}
