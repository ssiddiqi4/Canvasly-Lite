<?php
namespace CanvaslyLite\Units;
if ( ! defined('ABSPATH') ) exit;

class TinyMCETextEditor extends Unit {
 public function type(){ return 'tinymce_text_editor'; }
 public function title(){ return __('TinyMCE Text Editor', 'canvasly-lite'); }
 public function icon(){ return 'Tm'; }
 public function category(){ return 'basic'; }
 public function keywords(){ return ['tinymce','tiny mce','rich text','rich text editor','text editor','wordpress editor','wysiwyg']; }
 public function defaults(){ return [
  'content'=>'<p>Start writing your content here.</p>',
  'font_family'=>'',
  'font_size'=>16,
  'font_weight'=>'400',
  'line_height'=>'1.6',
  'letter_spacing'=>0,
  'color'=>'',
  'align'=>'left',
  'width'=>'',
  'max_width'=>'',
  'height'=>'',
  'min_height'=>''
 ]; }
 public function controls(){
  $c='Content'; $typo=__('Typography', 'canvasly-lite'); $lay=__('Layout', 'canvasly-lite');
  $len=['%','px','vw','em','rem'];
  $hlen=['px','%','vh','em','rem','auto'];
  $box='{{WRAPPER}} .lb-tinymce-text-editor, {{WRAPPER}} .lb-tinymce-preview';
  return [
   'content'=>$this->ctrl('wysiwyg',__('Content', 'canvasly-lite'),'content',$c),
   'width'=>$this->ctrl('slider',__('Width', 'canvasly-lite'),'style',$lay,['responsive'=>true,'units'=>$len,'range'=>['min'=>0,'max'=>1000],'selectors'=>['{{WRAPPER}}'=>'--lb-tiny-w: {{VALUE}};',$box=>'width: {{VALUE}};max-width: 100%;']]),
   'max_width'=>$this->ctrl('slider',__('Max Width', 'canvasly-lite'),'style',$lay,['responsive'=>true,'units'=>['px','%','vw','em','rem'],'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}}'=>'--lb-tiny-max-w: {{VALUE}};',$box=>'max-width: {{VALUE}};']]),
   'height'=>$this->ctrl('slider',__('Height', 'canvasly-lite'),'style',$lay,['responsive'=>true,'units'=>$hlen,'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}}'=>'--lb-tiny-h: {{VALUE}};',$box=>'height: {{VALUE}};']]),
   'min_height'=>$this->ctrl('slider',__('Min Height', 'canvasly-lite'),'style',$lay,['responsive'=>true,'units'=>['px','%','vh','em','rem'],'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}}'=>'--lb-tiny-min-h: {{VALUE}};',$box=>'min-height: {{VALUE}};']]),
   'align'=>$this->ctrl('select',__('Alignment', 'canvasly-lite'),'style',$typo,['options'=>self::opt_align(),'selectors'=>[$box=>'text-align: {{VALUE}};']]),
   'color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$typo,['selectors'=>[$box=>'color: {{VALUE}};']]),
   'font_family'=>$this->ctrl('font',__('Font Family', 'canvasly-lite'),'style',$typo,['selectors'=>[$box=>'font-family: {{VALUE}};']]),
   'font_size'=>$this->ctrl('slider',__('Font Size', 'canvasly-lite'),'style',$typo,['responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>6,'max'=>200],'selectors'=>[$box=>'font-size: {{SIZE}}{{UNIT}};']]),
   'font_weight'=>$this->ctrl('select',__('Weight', 'canvasly-lite'),'style',$typo,['options'=>self::opt_weight(),'selectors'=>[$box=>'font-weight: {{VALUE}};']]),
   'line_height'=>$this->ctrl('slider',__('Line Height', 'canvasly-lite'),'style',$typo,['responsive'=>true,'units'=>[],'range'=>['min'=>0.6,'max'=>3,'step'=>0.05],'selectors'=>[$box=>'line-height: {{SIZE}};']]),
   'letter_spacing'=>$this->ctrl('slider',__('Letter Spacing', 'canvasly-lite'),'style',$typo,['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>-5,'max'=>20,'step'=>0.1],'selectors'=>[$box=>'letter-spacing: {{SIZE}}{{UNIT}};']]),
  ];
 }
 public function render($s,$children=''){
  $content=wp_kses_post($s['content']??'<p>Start writing your content here.</p>');
  return '<div class="'.$this->cls($s).' lb-tinymce-text-editor"'.$this->attrs($s).'>'.$content.'</div>';
 }
 public function style_css($id,$s){
  $sel='#lb-node-'.$id;
  return $sel.'{display:flex;flex-direction:column;align-items:flex-start;min-width:0;max-width:100%;box-sizing:border-box;}'
   .$sel.' .lb-tinymce-text-editor{width:var(--lb-tiny-w,100%);max-width:min(100%,var(--lb-tiny-max-w,100%));height:var(--lb-tiny-h,auto);min-height:var(--lb-tiny-min-h,0);box-sizing:border-box;overflow:auto;}';
 }
}
