<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Text Editor: rich text with drop cap, multi-column flow, paragraph spacing and link colours. */
class Text extends Unit {
 public function type(){return 'text';} public function title(){return __('Text Editor', 'canvasly-lite');} public function icon(){return 'T';} public function category(){return 'basic';}
 public function keywords(){return ['text','paragraph','editor','content','drop cap','columns'];}
 public function defaults(){return ['text'=>'Start writing your content here.','font_size'=>16,'font_family'=>'','font_style'=>'','text_transform'=>'','text_decoration'=>'','line_height'=>'1.6','letter_spacing'=>0,'color'=>'','hover_color'=>'','align'=>'left','weight'=>'400','drop_cap'=>false,'drop_cap_view'=>'default','drop_cap_color'=>'','drop_cap_secondary_color'=>'','drop_cap_size'=>'','drop_cap_space'=>'','drop_cap_radius'=>'','drop_cap_border_width'=>'','text_columns'=>'1','column_gap'=>'','paragraph_spacing'=>'','link_color'=>'','link_hover_color'=>'','text_shadow'=>'','hover_text_shadow'=>''];}
 public function controls(){
  $content=__('Text Editor', 'canvasly-lite'); $typo=__('Typography', 'canvasly-lite'); $space=__('Spacing', 'canvasly-lite'); $links=__('Links', 'canvasly-lite'); $hover=__('Hover', 'canvasly-lite'); $drop=__('Drop Cap', 'canvasly-lite');
  $box='{{WRAPPER}} .lb-text';
  $cols=['1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10'];
  return [
   'text'=>$this->ctrl('wysiwyg',__('Text', 'canvasly-lite'),'content',$content,['dynamic'=>true]),
   'drop_cap'=>$this->ctrl('switch',__('Drop Cap', 'canvasly-lite'),'content',$content),
   'drop_cap_view'=>$this->ctrl('select',__('View', 'canvasly-lite'),'content',$content,['options'=>['default'=>__('Default', 'canvasly-lite'),'stacked'=>__('Stacked', 'canvasly-lite'),'framed'=>__('Framed', 'canvasly-lite')],'condition'=>['drop_cap'=>true]]),
   'text_columns'=>$this->ctrl('select',__('Columns', 'canvasly-lite'),'content',$content,['options'=>$cols]),
   'align'=>$this->ctrl('choose',__('Alignment', 'canvasly-lite'),'style',$typo,['responsive'=>true,'options'=>self::opt_align(),'selectors'=>['{{WRAPPER}}'=>'text-align: {{VALUE}};',$box=>'text-align: {{VALUE}};']]),
   'color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$typo,['selectors'=>[$box=>'color: {{VALUE}};']]),
   'font_family'=>$this->ctrl('font',__('Font Family', 'canvasly-lite'),'style',$typo,['selectors'=>[$box=>'font-family: {{VALUE}};']]),
   'font_size'=>$this->ctrl('slider',__('Size', 'canvasly-lite'),'style',$typo,['responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>6,'max'=>200],'selectors'=>[$box=>'font-size: {{SIZE}}{{UNIT}};']]),
   'weight'=>$this->ctrl('select',__('Weight', 'canvasly-lite'),'style',$typo,['options'=>self::opt_weight(),'selectors'=>[$box=>'font-weight: {{VALUE}};']]),
   'font_style'=>$this->ctrl('select',__('Style', 'canvasly-lite'),'style',$typo,['options'=>[''=>__('Default', 'canvasly-lite'),'normal'=>__('Normal', 'canvasly-lite'),'italic'=>__('Italic', 'canvasly-lite'),'oblique'=>__('Oblique', 'canvasly-lite')],'selectors'=>[$box=>'font-style: {{VALUE}};']]),
   'text_transform'=>$this->ctrl('select',__('Transform', 'canvasly-lite'),'style',$typo,['options'=>[''=>__('Default', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite'),'uppercase'=>__('Uppercase', 'canvasly-lite'),'lowercase'=>__('Lowercase', 'canvasly-lite'),'capitalize'=>__('Capitalize', 'canvasly-lite')],'selectors'=>[$box=>'text-transform: {{VALUE}};']]),
   'text_decoration'=>$this->ctrl('select',__('Decoration', 'canvasly-lite'),'style',$typo,['options'=>[''=>__('Default', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite'),'underline'=>__('Underline', 'canvasly-lite'),'overline'=>__('Overline', 'canvasly-lite'),'line-through'=>__('Line Through', 'canvasly-lite')],'selectors'=>[$box=>'text-decoration: {{VALUE}};']]),
   'line_height'=>$this->ctrl('slider',__('Line Height', 'canvasly-lite'),'style',$typo,['responsive'=>true,'units'=>[],'range'=>['min'=>0.6,'max'=>3,'step'=>0.05],'selectors'=>[$box=>'line-height: {{SIZE}};']]),
   'letter_spacing'=>$this->ctrl('slider',__('Letter Spacing', 'canvasly-lite'),'style',$typo,['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>-5,'max'=>20,'step'=>0.1],'selectors'=>[$box=>'letter-spacing: {{SIZE}}{{UNIT}};']]),
   'text_shadow'=>$this->ctrl('text_shadow',__('Text Shadow', 'canvasly-lite'),'style',$typo,['selectors'=>[$box=>'text-shadow: {{VALUE}};']]),
   'paragraph_spacing'=>$this->ctrl('slider',__('Paragraph Spacing', 'canvasly-lite'),'style',$space,['responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>0,'max'=>80],'selectors'=>['{{WRAPPER}}'=>'--lb-paragraph-spacing: {{SIZE}}{{UNIT}};']]),
   'column_gap'=>$this->ctrl('slider',__('Column Gap', 'canvasly-lite'),'style',$space,['responsive'=>true,'units'=>['px','em','rem','%'],'range'=>['min'=>0,'max'=>80],'condition'=>['text_columns!'=>'1'],'selectors'=>['{{WRAPPER}}'=>'--lb-text-column-gap: {{SIZE}}{{UNIT}};']]),
   'link_color'=>$this->ctrl('color',__('Link Color', 'canvasly-lite'),'style',$links,['selectors'=>['{{WRAPPER}}'=>'--lb-link-color: {{VALUE}};']]),
   'link_hover_color'=>$this->ctrl('color',__('Link Hover Color', 'canvasly-lite'),'style',$links,['selectors'=>['{{WRAPPER}}'=>'--lb-link-hover: {{VALUE}};']]),
   'hover_color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$hover,['selectors'=>['{{WRAPPER}}'=>'--lb-text-hover: {{VALUE}};',$box.':hover'=>'color: {{VALUE}};']]),
   'hover_text_shadow'=>$this->ctrl('text_shadow',__('Text Shadow', 'canvasly-lite'),'style',$hover,['selectors'=>[$box.':hover'=>'text-shadow: {{VALUE}};']]),
   'drop_cap_color'=>$this->ctrl('color',__('Primary Color', 'canvasly-lite'),'style',$drop,['condition'=>['drop_cap'=>true],'selectors'=>['{{WRAPPER}}'=>'--lb-drop-cap-color: {{VALUE}};']]),
   'drop_cap_secondary_color'=>$this->ctrl('color',__('Secondary Color', 'canvasly-lite'),'style',$drop,['condition'=>['drop_cap'=>true,'drop_cap_view'=>['stacked','framed']],'selectors'=>['{{WRAPPER}}'=>'--lb-drop-cap-secondary: {{VALUE}};']]),
   'drop_cap_size'=>$this->ctrl('slider',__('Size', 'canvasly-lite'),'style',$drop,['responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>16,'max'=>200],'condition'=>['drop_cap'=>true],'selectors'=>['{{WRAPPER}}'=>'--lb-drop-cap-size: {{SIZE}}{{UNIT}};']]),
   'drop_cap_space'=>$this->ctrl('slider',__('Spacing', 'canvasly-lite'),'style',$drop,['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>0,'max'=>60],'condition'=>['drop_cap'=>true],'selectors'=>['{{WRAPPER}}'=>'--lb-drop-cap-space: {{SIZE}}{{UNIT}};']]),
   'drop_cap_radius'=>$this->ctrl('slider',__('Radius', 'canvasly-lite'),'style',$drop,['units'=>['px','%'],'range'=>['min'=>0,'max'=>80],'condition'=>['drop_cap'=>true,'drop_cap_view'=>['stacked','framed']],'selectors'=>['{{WRAPPER}}'=>'--lb-drop-cap-radius: {{SIZE}}{{UNIT}};']]),
   'drop_cap_border_width'=>$this->ctrl('slider',__('Border Width', 'canvasly-lite'),'style',$drop,['units'=>['px'],'range'=>['min'=>0,'max'=>20],'condition'=>['drop_cap'=>true,'drop_cap_view'=>'framed'],'selectors'=>['{{WRAPPER}}'=>'--lb-drop-cap-border: {{SIZE}}{{UNIT}};']]),
  ];
 }
 public function render($s,$children=''){
  $cols=max(1,min(10,absint($this->scalar($s['text_columns']??1,1))));
  $view=in_array($s['drop_cap_view']??'default',['default','stacked','framed'],true)?$s['drop_cap_view']:'default';
  $vars=$this->style_attr(['--lb-text-columns'=>$cols>1?$cols:'','--lb-text-column-gap'=>$this->unit($s['column_gap']??''),'--lb-paragraph-spacing'=>$this->unit($s['paragraph_spacing']??''),'--lb-link-color'=>$s['link_color']??'','--lb-link-hover'=>$s['link_hover_color']??'','--lb-text-hover'=>$s['hover_color']??'','--lb-drop-cap-color'=>$s['drop_cap_color']??'','--lb-drop-cap-secondary'=>$s['drop_cap_secondary_color']??'','--lb-drop-cap-size'=>$this->unit($s['drop_cap_size']??''),'--lb-drop-cap-space'=>$this->unit($s['drop_cap_space']??''),'--lb-drop-cap-radius'=>$this->unit($s['drop_cap_radius']??''),'--lb-drop-cap-border'=>$this->unit($s['drop_cap_border_width']??'')]);
  $classes=$this->cls($s).' lb-text'.($cols>1?' lb-text-columns':'').(!empty($s['drop_cap'])?' lb-text-drop-cap lb-drop-cap-'.$view:'');
  return '<div class="'.$classes.'"'.$vars.' data-inline="text">'.wp_kses_post($s['text']??'').'</div>';
 }
}
