<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Accordion: a list of collapsible panels where only one panel is open at a time.
 * Items are a repeater of title/content (schema 2.2). Legacy "Title|Content" lines
 * are migrated on load. Toggle reuses this markup but allows several panels open.
 */
class Accordion extends Unit {
 public function type(){return 'accordion';} public function title(){return __('Accordion', 'canvasly-lite');} public function icon(){return '≡';} public function category(){return 'basic';}
 public function keywords(){return ['accordion','collapse','faq','panel','toggle'];}
 public function scripts($s=[]){return $this->frontend_scripts();}
 protected function single_open(){return true;}
 protected function root_class(){return 'lb-accordion';}
 public function defaults(){return [
  'items'=>[
   ['_id'=>'acc1','title'=>'Accordion Item 1','content'=>'Add the content for the first panel here.'],
   ['_id'=>'acc2','title'=>'Accordion Item 2','content'=>'Add the content for the second panel here.'],
  ],
  'title_tag'=>'div','icon'=>'chevron-down','active_icon'=>'chevron-up','icon_position'=>'right','first_open'=>true,'faq_schema'=>false,
  'title_color'=>'','active_color'=>'','title_background'=>'','content_color'=>'','content_background'=>'','icon_color'=>'','icon_active_color'=>'','border_color'=>'#d8dde3','border_width'=>1,'title_padding'=>'','content_padding'=>'','icon_space'=>10,'space_between'=>0,
 ];}
 public function controls(){
  $acc=__('Accordion', 'canvasly-lite'); $title=__('Title', 'canvasly-lite'); $content=__('Content', 'canvasly-lite'); $icon=__('Icon', 'canvasly-lite'); $border=__('Border', 'canvasly-lite');
  return [
   'items'=>$this->ctrl('repeater',__('Items', 'canvasly-lite'),'content',$acc,[
    'title_field'=>'{{title}}','prevent_empty'=>true,
    'fields'=>[
     'title'=>$this->field('text',__('Title', 'canvasly-lite'),['dynamic'=>true]),
     'content'=>$this->field('wysiwyg',__('Content', 'canvasly-lite'),['dynamic'=>true]),
    ],
   ]),
   'title_tag'=>$this->ctrl('select',__('Title HTML Tag', 'canvasly-lite'),'content',$acc,['options'=>self::opt_title_tags()]),
   'icon'=>$this->ctrl('icon',__('Icon', 'canvasly-lite'),'content',$acc),
   'active_icon'=>$this->ctrl('icon',__('Active Icon', 'canvasly-lite'),'content',$acc),
   'icon_position'=>$this->ctrl('select',__('Icon Position', 'canvasly-lite'),'content',$acc,['options'=>['left'=>__('Left', 'canvasly-lite'),'right'=>__('Right', 'canvasly-lite')]]),
   'first_open'=>$this->ctrl('switch',__('First Item Opened', 'canvasly-lite'),'content',$acc),
   'faq_schema'=>$this->ctrl('switch',__('FAQ Schema', 'canvasly-lite'),'content',$acc),
   'title_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$title),
   'active_color'=>$this->ctrl('color',__('Active Color', 'canvasly-lite'),'style',$title),
   'title_background'=>$this->ctrl('color',__('Background', 'canvasly-lite'),'style',$title),
   'title_padding'=>$this->ctrl('text',__('Padding', 'canvasly-lite'),'style',$title),
   'content_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$content),
   'content_background'=>$this->ctrl('color',__('Background', 'canvasly-lite'),'style',$content),
   'content_padding'=>$this->ctrl('text',__('Padding', 'canvasly-lite'),'style',$content),
   'icon_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$icon),
   'icon_active_color'=>$this->ctrl('color',__('Active Color', 'canvasly-lite'),'style',$icon),
   'icon_space'=>$this->ctrl('number',__('Spacing', 'canvasly-lite'),'style',$icon),
   'border_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$border),
   'border_width'=>$this->ctrl('number',__('Width', 'canvasly-lite'),'style',$border),
   'space_between'=>$this->ctrl('number',__('Space Between', 'canvasly-lite'),'style',$border),
  ];
 }
 public function render($s,$children=''){
  $rows=$this->repeater_items($s['items']??'',['title','content']);
  if(!$rows&&!empty($s['title']))$rows=[['title'=>(string)$s['title'],'content'=>(string)($s['text']??'')]];
  $tag=$this->tag($s['title_tag']??'div',$this->title_tags(),'div');
  $uid='lb'.substr(md5(wp_json_encode($rows).microtime(true).wp_rand()),0,8);
  $vars=$this->style_attr([
   '--lb-acc-title-color'=>$s['title_color']??'','--lb-acc-active-color'=>$s['active_color']??'','--lb-acc-title-bg'=>$s['title_background']??'',
   '--lb-acc-content-color'=>$s['content_color']??'','--lb-acc-content-bg'=>$s['content_background']??'','--lb-acc-icon-color'=>$s['icon_color']??'','--lb-acc-icon-active-color'=>$s['icon_active_color']??'',
   '--lb-acc-border-color'=>$s['border_color']??'','--lb-acc-border-width'=>$this->unit($s['border_width']??''),'--lb-acc-title-padding'=>sanitize_text_field((string)($s['title_padding']??'')),'--lb-acc-content-padding'=>sanitize_text_field((string)($s['content_padding']??'')),
   '--lb-acc-icon-space'=>$this->unit($s['icon_space']??''),'--lb-acc-gap'=>$this->unit($s['space_between']??''),
  ]);
  $pos=($s['icon_position']??'right')==='left'?'left':'right';
  $out='<div class="'.$this->cls($s).' '.$this->root_class().' lb-collapse lb-collapse-icon-'.$pos.'" data-lb-collapse="'.($this->single_open()?'single':'multi').'"'.$vars.'>';
  $faq=[];
  foreach($rows as $i=>$row){
   $title=(string)($row['title']??$row[0]??''); $content=(string)($row['content']??$row[1]??'');
   $open=$i===0&&!empty($s['first_open']);
   $tid=$uid.'-t'.$i; $cid=$uid.'-c'.$i;
   $icon='<span class="lb-collapse-icon" aria-hidden="true"><span class="lb-collapse-icon-closed">'.\CanvaslyLite\Utils\Icons::svg($s['icon']??'chevron-down').'</span><span class="lb-collapse-icon-opened">'.\CanvaslyLite\Utils\Icons::svg($s['active_icon']??'chevron-up').'</span></span>';
   $out.='<div class="lb-collapse-item'.($open?' is-open':'').'">';
   $out.='<'.$tag.' class="lb-collapse-title" id="'.esc_attr($tid).'" role="button" tabindex="0" aria-expanded="'.($open?'true':'false').'" aria-controls="'.esc_attr($cid).'">'.($pos==='left'?$icon:'').'<span class="lb-collapse-heading">'.wp_kses_post($title).'</span>'.($pos==='right'?$icon:'').'</'.$tag.'>';
   $out.='<div class="lb-collapse-content" id="'.esc_attr($cid).'" role="region" aria-labelledby="'.esc_attr($tid).'"'.($open?'':' hidden').'>'.wpautop(wp_kses_post($content)).'</div>';
   $out.='</div>';
   $faq[]=['@type'=>'Question','name'=>wp_strip_all_tags($title),'acceptedAnswer'=>['@type'=>'Answer','text'=>wp_strip_all_tags($content)]];
  }
  $out.='</div>';
  if(!empty($s['faq_schema'])&&$faq)$out.='<script type="application/ld+json">'.wp_json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$faq]).'</script>';
  return $out;
 }
}
