<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Button: link button with size presets, alignment, icon before/after the label, icon spacing, colours, border and hover animation. */
class Button extends Unit {
 public function type(){return 'button';} public function title(){return __('Button', 'canvasly-lite');} public function icon(){return 'B';} public function category(){return 'basic';}
 public function uses_button(){return true;}
 public function keywords(){return ['button','link','cta','call to action'];}
 public static function sizes(){return ['xs','small','medium','large','xl'];}
 public function defaults(){return ['text'=>__('Click Here', 'canvasly-lite'),'url'=>'#','target'=>'_self','button_type'=>'default','size'=>'medium','align'=>'left','background'=>'#222222','text_color'=>'#ffffff','hover_background'=>'','hover_text_color'=>'','hover_border_color'=>'','padding'=>'12px 22px','radius'=>6,'border_color'=>'','border_width'=>0,'border_style'=>'solid','shadow'=>[],'hover_shadow'=>[],'typography'=>[],'icon'=>'','icon_placement'=>'before','icon_spacing'=>8,'icon_size'=>'','hover_animation'=>'','button_id'=>''];}
 public function controls(){
  $btn=__('Button', 'canvasly-lite'); $style=__('Button', 'canvasly-lite'); $hover=__('Hover', 'canvasly-lite');
  $a='{{WRAPPER}} .lb-button';
  return [
   'text'=>$this->ctrl('wysiwyg',__('Text', 'canvasly-lite'),'content',$btn,['dynamic'=>true]),
   'url'=>$this->ctrl('url',__('Link', 'canvasly-lite'),'content',$btn,['dynamic'=>true]),
   'target'=>$this->ctrl('select',__('Open In', 'canvasly-lite'),'content',$btn,['options'=>self::opt_target()]),
   'button_type'=>$this->ctrl('select',__('Type', 'canvasly-lite'),'content',$btn,['options'=>['default'=>__('Default', 'canvasly-lite'),'info'=>__('Info', 'canvasly-lite'),'success'=>__('Success', 'canvasly-lite'),'warning'=>__('Warning', 'canvasly-lite'),'danger'=>__('Danger', 'canvasly-lite')]]),
   'size'=>$this->ctrl('select',__('Size', 'canvasly-lite'),'content',$btn,['options'=>['xs'=>__('Extra Small', 'canvasly-lite'),'small'=>__('Small', 'canvasly-lite'),'medium'=>__('Medium', 'canvasly-lite'),'large'=>__('Large', 'canvasly-lite'),'xl'=>__('Extra Large', 'canvasly-lite')]]),
   'icon'=>$this->ctrl('icon',__('Icon', 'canvasly-lite'),'content',$btn),
   'icon_placement'=>$this->ctrl('select',__('Icon Position', 'canvasly-lite'),'content',$btn,['options'=>['before'=>__('Before', 'canvasly-lite'),'after'=>__('After', 'canvasly-lite')],'condition'=>['icon!'=>'']]),
   'button_id'=>$this->ctrl('text',__('Button ID', 'canvasly-lite'),'content',$btn),
   'align'=>$this->ctrl('choose',__('Alignment', 'canvasly-lite'),'style',$style,['responsive'=>true,'options'=>self::opt_align(),'map'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end','justify'=>'stretch'],'selectors'=>['{{WRAPPER}} .lb-button-wrap'=>'justify-content: {{VALUE}};','{{WRAPPER}}'=>'--lb-button-align: {{RAW}};']]),
   'typography'=>$this->ctrl('typography',__('Typography', 'canvasly-lite'),'style',$style,['selectors'=>[$a=>'{{VALUE}}']]),
   'background'=>$this->ctrl('color',__('Background Color', 'canvasly-lite'),'style',$style,['selectors'=>[$a=>'background: {{VALUE}};']]),
   'text_color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$style,['selectors'=>[$a=>'color: {{VALUE}};']]),
   'icon_spacing'=>$this->ctrl('slider',__('Icon Spacing', 'canvasly-lite'),'style',$style,['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>0,'max'=>60],'condition'=>['icon!'=>''],'selectors'=>[$a=>'--lb-button-icon-gap: {{SIZE}}{{UNIT}};']]),
   'icon_size'=>$this->ctrl('slider',__('Icon Size', 'canvasly-lite'),'style',$style,['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>8,'max'=>80],'condition'=>['icon!'=>''],'selectors'=>['{{WRAPPER}} .lb-button-icon'=>'font-size: {{SIZE}}{{UNIT}};']]),
   'padding'=>$this->ctrl('dimensions',__('Padding', 'canvasly-lite'),'style',$style,['selectors'=>[$a=>'padding: {{VALUE}};']]),
   'radius'=>$this->ctrl('slider',__('Border Radius', 'canvasly-lite'),'style',$style,['responsive'=>true,'units'=>['px','%','em'],'range'=>['min'=>0,'max'=>80],'selectors'=>[$a=>'border-radius: {{SIZE}}{{UNIT}};']]),
   'border_width'=>$this->ctrl('slider',__('Border Width', 'canvasly-lite'),'style',$style,['units'=>['px'],'range'=>['min'=>0,'max'=>20],'selectors'=>[$a=>'border-width: {{SIZE}}{{UNIT}};']]),
   'border_style'=>$this->ctrl('select',__('Border Style', 'canvasly-lite'),'style',$style,['options'=>[''=>__('None', 'canvasly-lite'),'solid'=>__('Solid', 'canvasly-lite'),'dashed'=>__('Dashed', 'canvasly-lite'),'dotted'=>__('Dotted', 'canvasly-lite'),'double'=>__('Double', 'canvasly-lite')],'selectors'=>[$a=>'border-style: {{VALUE}};']]),
   'border_color'=>$this->ctrl('color',__('Border Color', 'canvasly-lite'),'style',$style,['selectors'=>[$a=>'border-color: {{VALUE}};']]),
   'shadow'=>$this->ctrl('box_shadow',__('Shadow', 'canvasly-lite'),'style',$style,['selectors'=>[$a=>'box-shadow: {{VALUE}};']]),
   'hover_background'=>$this->ctrl('color',__('Background Color', 'canvasly-lite'),'style',$hover,['selectors'=>[$a.':hover'=>'background: {{VALUE}};','{{WRAPPER}}'=>'--lb-button-hover-bg: {{VALUE}};']]),
   'hover_text_color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$hover,['selectors'=>[$a.':hover'=>'color: {{VALUE}};','{{WRAPPER}}'=>'--lb-button-hover-color: {{VALUE}};']]),
   'hover_border_color'=>$this->ctrl('color',__('Border Color', 'canvasly-lite'),'style',$hover,['selectors'=>[$a.':hover'=>'border-color: {{VALUE}};','{{WRAPPER}}'=>'--lb-button-hover-border: {{VALUE}};']]),
   'hover_shadow'=>$this->ctrl('box_shadow',__('Shadow', 'canvasly-lite'),'style',$hover,['selectors'=>[$a.':hover'=>'box-shadow: {{VALUE}};']]),
   'hover_animation'=>$this->ctrl('select',__('Hover Animation', 'canvasly-lite'),'style',$hover,['options'=>self::opt_hover()]),
  ];
 }
 public function render($s,$children=''){
  $size=in_array($s['size']??'medium',self::sizes(),true)?$s['size']:'medium';
  $align=$this->scalar($s['align']??'left','left');
  $align=in_array($align,['left','center','right','justify'],true)?$align:'left';
  $placement=($s['icon_placement']??'before')==='after'?'after':'before';
  $icon=!empty($s['icon'])?'<span class="lb-button-icon lb-button-icon-'.$placement.'" aria-hidden="true">'.\CanvaslyLite\Utils\Icons::svg($s['icon']).'</span>':'';
  $label='<span class="lb-button-text" data-inline="text">'.wp_kses_post($s['text']??__('Click Here', 'canvasly-lite')).'</span>';
  $t=($s['target']??'_self')==='_blank'?' target="_blank" rel="noopener"':'';
  $id=!empty($s['button_id'])?' id="'.esc_attr(sanitize_html_class($s['button_id'])).'"':'';
  $type=in_array($s['button_type']??'default',['default','info','success','warning','danger'],true)?$s['button_type']:'default';
  $a='<a class="'.$this->cls($s).' lb-button lb-button-'.sanitize_html_class($size).' lb-button-type-'.sanitize_html_class($type).($icon?' lb-button-has-icon':'').$this->hover_class($s).'"'.$id.' href="'.esc_attr(self::link_href($s['url']??'#')).'"'.$t.'>'.($placement==='before'?$icon.$label:$label.$icon).'</a>';
  return '<div class="lb-button-wrap lb-button-align-'.$align.'">'.$a.'</div>';
 }
}
