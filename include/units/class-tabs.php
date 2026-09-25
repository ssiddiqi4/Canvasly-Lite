<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Tabs: repeater of title/content rendered as an accessible tablist. */
class Tabs extends Unit {
 public function type(){return 'tabs';} public function title(){return __('Tabs', 'canvasly-lite');} public function icon(){return '⊟';} public function category(){return 'basic';}
 public function keywords(){return ['tabs','tab','panel','switch','navigation'];}
 public function scripts($s=[]){return $this->frontend_scripts();}
 public function defaults(){return [
  'tabs'=>[
   ['_id'=>'tab1','title'=>'Tab 1','content'=>'Content for the first tab.'],
   ['_id'=>'tab2','title'=>'Tab 2','content'=>'Content for the second tab.'],
   ['_id'=>'tab3','title'=>'Tab 3','content'=>'Content for the third tab.'],
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
     'content'=>$this->field('wysiwyg',__('Content', 'canvasly-lite')),
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
  $rows=$this->repeater_items($s['tabs']??'',['title','content']);
  if(!$rows)return '<div class="lb-embed-placeholder">'.esc_html__('Add tabs', 'canvasly-lite').'</div>';
  $active=max(0,min(count($rows)-1,absint($s['active']??0)));
  $vertical=($s['orientation']??'horizontal')==='vertical';
  $align=in_array($s['tabs_align']??'start',['start','center','end','stretch'],true)?$s['tabs_align']:'start';
  $tag=$this->tag($s['title_tag']??'div',$this->title_tags(),'div');
  $uid='lb'.substr(md5(wp_json_encode($rows).microtime(true).wp_rand()),0,8);
  $vars=$this->style_attr([
   '--lb-tabs-nav-width'=>$vertical?sanitize_text_field((string)($s['nav_width']??'25%')):'',
   '--lb-tab-color'=>$s['tab_color']??'','--lb-tab-active-color'=>$s['tab_active_color']??'','--lb-tab-bg'=>$s['tab_background']??'','--lb-tab-active-bg'=>$s['tab_active_background']??'',
   '--lb-tab-content-color'=>$s['content_color']??'','--lb-tab-content-bg'=>$s['content_background']??'','--lb-tab-border-color'=>$s['border_color']??'','--lb-tab-border-width'=>$this->unit($s['border_width']??''),
   '--lb-tab-padding'=>sanitize_text_field((string)($s['tab_padding']??'')),'--lb-tab-content-padding'=>sanitize_text_field((string)($s['content_padding']??'')),
  ]);
  $nav='';$panels='';
  foreach($rows as $i=>$row){
   $is=$i===$active; $tid=$uid.'-tab'.$i; $pid=$uid.'-panel'.$i;
   $title=(string)($row['title']??$row[0]??''); $content=(string)($row['content']??$row[1]??'');
   $nav.='<'.$tag.' class="lb-tab-button'.($is?' is-active':'').'" role="tab" tabindex="'.($is?'0':'-1').'" id="'.esc_attr($tid).'" aria-selected="'.($is?'true':'false').'" aria-controls="'.esc_attr($pid).'">'.wp_kses_post($title).'</'.$tag.'>';
   $panels.='<div class="lb-tab-panel" role="tabpanel" id="'.esc_attr($pid).'" aria-labelledby="'.esc_attr($tid).'"'.($is?'':' hidden').'>'.wpautop(wp_kses_post($content)).'</div>';
  }
  return '<div class="'.$this->cls($s).' lb-tabs-widget lb-tabs-'.($vertical?'vertical':'horizontal').' lb-tabs-align-'.$align.'" data-active="'.$active.'"'.$vars.'><div class="lb-tabs-nav" role="tablist" aria-orientation="'.($vertical?'vertical':'horizontal').'">'.$nav.'</div><div class="lb-tabs-panels">'.$panels.'</div></div>';
 }
}
