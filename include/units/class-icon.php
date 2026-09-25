<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Icon: a single SVG glyph with default / stacked / framed views, shapes, colours, link, rotation and hover animation. */
class Icon extends Unit {
 public function type(){return 'icon';} public function title(){return __('Icon', 'canvasly-lite');} public function icon(){return '✦';} public function category(){return 'basic';}
 public function keywords(){return ['icon','glyph','symbol','svg','stacked','framed'];}
 public function defaults(){return ['icon'=>'star','icon_view'=>'default','shape'=>'circle','size'=>32,'color'=>'#222222','secondary_color'=>'#ffffff','hover_color'=>'','hover_secondary_color'=>'','link'=>'','link_target'=>'_self','rotate'=>0,'align'=>'center','icon_padding'=>'','icon_border_width'=>'','icon_radius'=>'','shadow'=>[],'hover_shadow'=>[],'hover_animation'=>''];}
 public function controls(){
  $icon=__('Icon', 'canvasly-lite'); $view=['stacked','framed']; $hover=__('Hover', 'canvasly-lite');
  return [
   'icon'=>$this->ctrl('icon',__('Icon', 'canvasly-lite'),'content',$icon),
   'icon_view'=>$this->ctrl('select',__('View', 'canvasly-lite'),'content',$icon,['options'=>['default'=>__('Default', 'canvasly-lite'),'stacked'=>__('Stacked', 'canvasly-lite'),'framed'=>__('Framed', 'canvasly-lite')]]),
   'shape'=>$this->ctrl('select',__('Shape', 'canvasly-lite'),'content',$icon,['options'=>['circle'=>__('Circle', 'canvasly-lite'),'rounded'=>__('Rounded', 'canvasly-lite'),'square'=>__('Square', 'canvasly-lite')],'condition'=>['icon_view'=>$view]]),
   'link'=>$this->ctrl('url',__('Link', 'canvasly-lite'),'content',$icon,['dynamic'=>true]),
   'link_target'=>$this->ctrl('select',__('Link Target', 'canvasly-lite'),'content',$icon,['options'=>self::opt_target(),'condition'=>['link!'=>'']]),
   'align'=>$this->ctrl('choose',__('Alignment', 'canvasly-lite'),'style',$icon,['responsive'=>true,'options'=>self::opt_lcr(),'selectors'=>['{{WRAPPER}} .lb-icon-wrap'=>'text-align: {{VALUE}};']]),
   'size'=>$this->ctrl('slider',__('Size', 'canvasly-lite'),'style',$icon,['responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>8,'max'=>200],'selectors'=>['{{WRAPPER}}'=>'--lb-icon-size: {{SIZE}}{{UNIT}};']]),
   'color'=>$this->ctrl('color',__('Primary Color', 'canvasly-lite'),'style',$icon,['selectors'=>['{{WRAPPER}}'=>'--lb-icon-primary: {{VALUE}};']]),
   'secondary_color'=>$this->ctrl('color',__('Secondary Color', 'canvasly-lite'),'style',$icon,['condition'=>['icon_view'=>$view],'selectors'=>['{{WRAPPER}}'=>'--lb-icon-secondary: {{VALUE}};']]),
   'rotate'=>$this->ctrl('slider',__('Rotate', 'canvasly-lite'),'style',$icon,['responsive'=>true,'units'=>['deg'],'range'=>['min'=>0,'max'=>360],'selectors'=>['{{WRAPPER}}'=>'--lb-icon-rotate: {{SIZE}}{{UNIT}};']]),
   'icon_padding'=>$this->ctrl('slider',__('Icon Padding', 'canvasly-lite'),'style',$icon,['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>0,'max'=>80],'condition'=>['icon_view'=>$view],'selectors'=>['{{WRAPPER}}'=>'--lb-icon-padding: {{SIZE}}{{UNIT}};']]),
   'icon_border_width'=>$this->ctrl('slider',__('Icon Border', 'canvasly-lite'),'style',$icon,['responsive'=>true,'units'=>['px'],'range'=>['min'=>0,'max'=>20],'condition'=>['icon_view'=>'framed'],'selectors'=>['{{WRAPPER}}'=>'--lb-icon-border: {{SIZE}}{{UNIT}};']]),
   'icon_radius'=>$this->ctrl('slider',__('Icon Radius', 'canvasly-lite'),'style',$icon,['responsive'=>true,'units'=>['px','%'],'range'=>['min'=>0,'max'=>200],'condition'=>['icon_view'=>$view],'selectors'=>['{{WRAPPER}}'=>'--lb-icon-radius: {{SIZE}}{{UNIT}};']]),
   'shadow'=>$this->ctrl('box_shadow',__('Shadow', 'canvasly-lite'),'style',$icon,['selectors'=>['{{WRAPPER}} .lb-icon-glyph'=>'box-shadow: {{VALUE}};']]),
   'hover_color'=>$this->ctrl('color',__('Primary Color', 'canvasly-lite'),'style',$hover,['selectors'=>['{{WRAPPER}}'=>'--lb-icon-hover-primary: {{VALUE}};']]),
   'hover_secondary_color'=>$this->ctrl('color',__('Secondary Color', 'canvasly-lite'),'style',$hover,['condition'=>['icon_view'=>$view],'selectors'=>['{{WRAPPER}}'=>'--lb-icon-hover-secondary: {{VALUE}};']]),
   'hover_shadow'=>$this->ctrl('box_shadow',__('Shadow', 'canvasly-lite'),'style',$hover,['selectors'=>['{{WRAPPER}} .lb-icon-glyph:hover'=>'box-shadow: {{VALUE}};']]),
   'hover_animation'=>$this->ctrl('select',__('Hover Animation', 'canvasly-lite'),'style',$hover,['options'=>self::opt_hover()]),
  ];
 }
 public function render($s,$children=''){
  $view=in_array($s['icon_view']??'default',['default','stacked','framed'],true)?$s['icon_view']:'default';
  $shape=in_array($s['shape']??'circle',['circle','rounded','square'],true)?$s['shape']:'circle';
  $svg=\CanvaslyLite\Utils\Icons::svg($s['icon']??'star');
  $inner_class='lb-icon-glyph'.$this->hover_class($s);
  if(!empty($s['link'])){
   $t=($s['link_target']??'_self')==='_blank'?' target="_blank" rel="noopener"':'';
   $glyph='<a class="'.$inner_class.'" href="'.esc_attr(self::link_href($s['link'])).'"'.$t.'>'.$svg.'</a>';
  } else $glyph='<span class="'.$inner_class.'">'.$svg.'</span>';
  return '<div class="'.$this->cls($s).' lb-icon-wrap lb-icon-view-'.$view.' lb-icon-shape-'.$shape.'">'.$glyph.'</div>';
 }
}
