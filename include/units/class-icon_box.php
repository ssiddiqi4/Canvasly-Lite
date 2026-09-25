<?php
namespace CanvaslyLite\Units;
if(!defined('ABSPATH')) exit;
/** Icon Box: icon + title + description with icon placement (top / left / right), view/shape styling, link and hover animation. */
class IconBox extends Unit {
 public function type(){return 'icon_box';} public function title(){return __('Icon Box', 'canvasly-lite');} public function icon(){return '✧';} public function category(){return 'basic';}
 public function keywords(){return ['icon box','feature','service','icon','box'];}
 public function defaults(){return ['icon'=>'star','icon_view'=>'default','shape'=>'circle','title'=>'This is the heading','title_tag'=>'h3','text'=>'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.','link'=>'','link_target'=>'_self','box_layout'=>'top','content_align'=>'center','vertical_align'=>'top','icon_size'=>44,'icon_space'=>15,'title_space'=>10,'icon_color'=>'#3f7fdf','secondary_color'=>'#ffffff','hover_color'=>'','hover_secondary_color'=>'','title_color'=>'#222222','title_hover_color'=>'','text_color'=>'','icon_padding'=>'','icon_border_width'=>'','icon_radius'=>'','rotate'=>0,'hover_animation'=>''];}
 public function controls(){return ['icon'=>'icon','icon_view'=>'select','shape'=>'select','title'=>'text','title_tag'=>'select','text'=>'wysiwyg','link'=>'url','link_target'=>'select','box_layout'=>'select','content_align'=>'select','vertical_align'=>'select','icon_size'=>'number','icon_space'=>'number','title_space'=>'number','icon_color'=>'color','secondary_color'=>'color','hover_color'=>'color','hover_secondary_color'=>'color','title_color'=>'color','title_hover_color'=>'color','text_color'=>'color','icon_padding'=>'number','icon_border_width'=>'number','icon_radius'=>'number','rotate'=>'number','hover_animation'=>'select'];}
 public function render($s,$children=''){
  $view=in_array($s['icon_view']??'default',['default','stacked','framed'],true)?$s['icon_view']:'default';
  $shape=in_array($s['shape']??'circle',['circle','rounded','square'],true)?$s['shape']:'circle';
  $layout=in_array($s['box_layout']??'top',['top','left','right'],true)?$s['box_layout']:'top';
  $align=in_array($s['content_align']??'center',['left','center','right'],true)?$s['content_align']:'center';
  $valign=in_array($s['vertical_align']??'top',['top','middle','bottom'],true)?$s['vertical_align']:'top';
  $tag=$this->tag($s['title_tag']??'h3',$this->title_tags(),'h3');
  $vars=$this->style_attr(array_merge($this->icon_vars($s),['--lb-box-icon-space'=>$this->unit($s['icon_space']??''),'--lb-box-title-space'=>$this->unit($s['title_space']??''),'--lb-box-title-color'=>$s['title_color']??'','--lb-box-title-hover'=>$s['title_hover_color']??'','--lb-box-text-color'=>$s['text_color']??'']));
  $link=!empty($s['link'])?esc_attr(self::link_href($s['link'])):'';
  $t=($s['link_target']??'_self')==='_blank'?' target="_blank" rel="noopener"':'';
  $svg=\CanvaslyLite\Utils\Icons::svg($s['icon']??'star');
  $glyph=$link?'<a class="lb-icon-glyph'.$this->hover_class($s).'" href="'.$link.'"'.$t.' tabindex="-1" aria-hidden="true">'.$svg.'</a>':'<span class="lb-icon-glyph'.$this->hover_class($s).'">'.$svg.'</span>';
  $title=esc_html($s['title']??'');
  if($link)$title='<a href="'.$link.'"'.$t.'>'.$title.'</a>';
  $out='<div class="'.$this->cls($s).' lb-icon-box lb-icon-box-'.$layout.' lb-icon-box-align-'.$align.' lb-icon-box-valign-'.$valign.' lb-icon-view-'.$view.' lb-icon-shape-'.$shape.'"'.$vars.'>';
  $out.='<div class="lb-icon-box-icon">'.$glyph.'</div>';
  $out.='<div class="lb-icon-box-content">';
  if($title!=='')$out.='<'.$tag.' class="lb-icon-box-title">'.$title.'</'.$tag.'>';
  if(($s['text']??'')!=='')$out.='<div class="lb-icon-box-text">'.wp_kses_post($s['text']).'</div>';
  return $out.'</div></div>';
 }
}
