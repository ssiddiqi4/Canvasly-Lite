<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Container: flex / grid / block layout box. Also provides a semantic HTML tag,
 * a whole-container link, a background overlay, top & bottom shape dividers and
 * a self-hosted background video.
 */
class Container extends Unit {
 public function type(){return 'container';} public function title(){return __('Container', 'canvasly-lite');} public function icon(){return '▣';} public function category(){return 'layout';} public function supports_children(){return true;}
 public function keywords(){return ['container','section','flex','grid','wrapper','layout','box'];}
 public static function html_tags(){return ['div','section','header','footer','main','article','aside','nav'];}
 public static function shapes(){return ['','wave','tilt','triangle','curve','arrow','zigzag','mountains'];}
 public function defaults(){return ['layout'=>'flex','direction'=>'column','wrap'=>'nowrap','justify'=>'flex-start','align'=>'stretch','gap'=>16,'column_gap'=>16,'row_gap'=>16,'columns'=>2,'width'=>'100%','min_height'=>'','max_width'=>'','background'=>'','padding'=>[],'margin'=>[],
  'html_tag'=>'div','link'=>'','link_target'=>'_self',
  'overlay_color'=>'','overlay_opacity'=>0.5,'overlay_blend_mode'=>'',
  'shape_top'=>'','shape_top_color'=>'#ffffff','shape_top_height'=>80,'shape_top_width'=>'100%','shape_top_flip'=>false,'shape_top_front'=>false,
  'shape_bottom'=>'','shape_bottom_color'=>'#ffffff','shape_bottom_height'=>80,'shape_bottom_width'=>'100%','shape_bottom_flip'=>false,'shape_bottom_front'=>false,
  'background_video'=>'','background_video_start'=>'','background_video_end'=>'','background_video_poster'=>'','background_video_mobile'=>false,'background_video_loop'=>true];}
 public function controls(){
  $lay=__('Layout', 'canvasly-lite'); $link=__('Link', 'canvasly-lite'); $bg='Background'; $shape=__('Shape Dividers', 'canvasly-lite'); $video=__('Background Video', 'canvasly-lite');
  $flex=['layout'=>'flex']; $grid=['layout'=>'grid'];
  $shape_opts=[''=>'None','wave'=>'Wave','tilt'=>'Tilt','triangle'=>'Triangle','curve'=>'Curve','arrow'=>'Arrow','zigzag'=>'Zigzag','mountains'=>'Mountains'];
  $blend=[''=>'Normal','normal'=>'Normal','multiply'=>'Multiply','screen'=>'Screen','overlay'=>'Overlay','darken'=>'Darken','lighten'=>'Lighten','color-dodge'=>'Color Dodge','color-burn'=>'Color Burn','hard-light'=>'Hard Light','soft-light'=>'Soft Light','difference'=>'Difference','exclusion'=>'Exclusion','hue'=>'Hue','saturation'=>'Saturation','color'=>'Color','luminosity'=>'Luminosity'];
  return [
   'layout'=>$this->ctrl('choose',__('Layout', 'canvasly-lite'),'content',$lay,['options'=>['flex'=>__('Flex', 'canvasly-lite'),'grid'=>__('Grid', 'canvasly-lite'),'block'=>__('Block', 'canvasly-lite')],'map'=>['flex'=>'flex','grid'=>'grid','block'=>'block'],'selectors'=>['{{WRAPPER}} .lb-container'=>'display: {{VALUE}};']]),
   'direction'=>$this->ctrl('select',__('Direction', 'canvasly-lite'),'content',$lay,['options'=>['row'=>__('Row', 'canvasly-lite'),'row-reverse'=>__('Row Reverse', 'canvasly-lite'),'column'=>__('Column', 'canvasly-lite'),'column-reverse'=>__('Column Reverse', 'canvasly-lite')],'condition'=>$flex,'selectors'=>['{{WRAPPER}} .lb-container'=>'flex-direction: {{VALUE}};']]),
   'wrap'=>$this->ctrl('select',__('Wrap', 'canvasly-lite'),'content',$lay,['options'=>['nowrap'=>__('No Wrap', 'canvasly-lite'),'wrap'=>__('Wrap', 'canvasly-lite'),'wrap-reverse'=>__('Wrap Reverse', 'canvasly-lite')],'condition'=>$flex,'selectors'=>['{{WRAPPER}} .lb-container'=>'flex-wrap: {{VALUE}};']]),
   'justify'=>$this->ctrl('select',__('Justify', 'canvasly-lite'),'content',$lay,['options'=>['flex-start'=>__('Start', 'canvasly-lite'),'center'=>__('Center', 'canvasly-lite'),'flex-end'=>__('End', 'canvasly-lite'),'space-between'=>__('Space Between', 'canvasly-lite'),'space-around'=>__('Space Around', 'canvasly-lite'),'space-evenly'=>__('Space Evenly', 'canvasly-lite')],'condition'=>$flex,'selectors'=>['{{WRAPPER}} .lb-container'=>'justify-content: {{VALUE}};']]),
   'align'=>$this->ctrl('select',__('Align', 'canvasly-lite'),'content',$lay,['options'=>['stretch'=>__('Stretch', 'canvasly-lite'),'flex-start'=>__('Start', 'canvasly-lite'),'center'=>__('Center', 'canvasly-lite'),'flex-end'=>__('End', 'canvasly-lite'),'baseline'=>__('Baseline', 'canvasly-lite')],'selectors'=>['{{WRAPPER}} .lb-container'=>'align-items: {{VALUE}};']]),
   'gap'=>$this->ctrl('slider',__('Gap', 'canvasly-lite'),'content',$lay,['responsive'=>true,'units'=>['px','em','rem','%'],'range'=>['min'=>0,'max'=>80],'selectors'=>['{{WRAPPER}} .lb-container'=>'gap: {{SIZE}}{{UNIT}};']]),
   'gaps'=>$this->ctrl('gaps',__('Gaps', 'canvasly-lite'),'content',$lay,['condition'=>$grid,'selectors'=>['{{WRAPPER}} .lb-container'=>'gap: {{VALUE}};']]),
   'column_gap'=>$this->ctrl('slider',__('Column Gap', 'canvasly-lite'),'content',$lay,['hidden'=>true,'responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>0,'max'=>80],'condition'=>$grid,'selectors'=>['{{WRAPPER}} .lb-container'=>'column-gap: {{SIZE}}{{UNIT}};']]),
   'row_gap'=>$this->ctrl('slider',__('Row Gap', 'canvasly-lite'),'content',$lay,['hidden'=>true,'responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>0,'max'=>80],'condition'=>$grid,'selectors'=>['{{WRAPPER}} .lb-container'=>'row-gap: {{SIZE}}{{UNIT}};']]),
   'columns'=>$this->ctrl('number',__('Columns', 'canvasly-lite'),'content',$lay,['range'=>['min'=>1,'max'=>12],'condition'=>$grid]),
   'overflow'=>$this->ctrl('select',__('Overflow', 'canvasly-lite'),'content',$lay,['options'=>[''=>__('Default', 'canvasly-lite'),'visible'=>__('Visible', 'canvasly-lite'),'hidden'=>__('Hidden', 'canvasly-lite'),'auto'=>__('Auto', 'canvasly-lite'),'scroll'=>__('Scroll', 'canvasly-lite')],'selectors'=>['{{WRAPPER}} .lb-container'=>'overflow: {{VALUE}};']]),
   'html_tag'=>$this->ctrl('select',__('HTML Tag', 'canvasly-lite'),'content',$link,['options'=>['div'=>__('div', 'canvasly-lite'),'section'=>__('section', 'canvasly-lite'),'header'=>__('header', 'canvasly-lite'),'footer'=>__('footer', 'canvasly-lite'),'main'=>__('main', 'canvasly-lite'),'article'=>__('article', 'canvasly-lite'),'aside'=>__('aside', 'canvasly-lite'),'nav'=>__('nav', 'canvasly-lite')]]),
   'link'=>$this->ctrl('url',__('Link', 'canvasly-lite'),'content',$link,['dynamic'=>true]),
   'link_target'=>$this->ctrl('select',__('Link Target', 'canvasly-lite'),'content',$link,['options'=>self::opt_target(),'condition'=>['link!'=>'']]),
   'width'=>$this->ctrl('slider',__('Width', 'canvasly-lite'),'style',$lay,['responsive'=>true,'units'=>['%','px','vw','em','rem'],'range'=>['min'=>0,'max'=>1000],'selectors'=>['{{WRAPPER}} .lb-container'=>'width: {{VALUE}};']]),
   'min_height'=>$this->ctrl('slider',__('Min Height', 'canvasly-lite'),'style',$lay,['responsive'=>true,'units'=>['px','%','vh','em','rem'],'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}} .lb-container'=>'min-height: {{VALUE}};']]),
   'max_width'=>$this->ctrl('slider',__('Max Width', 'canvasly-lite'),'style',$lay,['responsive'=>true,'units'=>['px','%','vw','em','rem'],'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}} .lb-container'=>'max-width: {{VALUE}};']]),
   'background'=>$this->ctrl('background',__('Background', 'canvasly-lite'),'style',$bg,['selectors'=>['{{WRAPPER}} .lb-container'=>'{{VALUE}}']]),
   'padding'=>$this->ctrl('dimensions',__('Padding', 'canvasly-lite'),'style',$lay,['selectors'=>['{{WRAPPER}} .lb-container'=>'padding: {{VALUE}};']]),
   'margin'=>$this->ctrl('dimensions',__('Margin', 'canvasly-lite'),'style',$lay,['selectors'=>['{{WRAPPER}} .lb-container'=>'margin: {{VALUE}};']]),
   'overlay_color'=>$this->ctrl('color',__('Overlay Color', 'canvasly-lite'),'style',$bg,['hidden'=>true]),
   'overlay_opacity'=>$this->ctrl('slider',__('Overlay Opacity', 'canvasly-lite'),'style',$bg,['hidden'=>true,'units'=>[],'range'=>['min'=>0,'max'=>1,'step'=>0.05]]),
   'overlay_blend_mode'=>$this->ctrl('select',__('Blend Mode', 'canvasly-lite'),'style',$bg,['hidden'=>true,'options'=>$blend]),
   'background_video'=>$this->ctrl('url',__('Background Video', 'canvasly-lite'),'content',$video,['hidden'=>true]),
   'background_video_start'=>$this->ctrl('number',__('Video Start', 'canvasly-lite'),'content',$video,['hidden'=>true,'range'=>['min'=>0,'max'=>36000]]),
   'background_video_end'=>$this->ctrl('number',__('Video End', 'canvasly-lite'),'content',$video,['hidden'=>true,'range'=>['min'=>0,'max'=>36000]]),
   'background_video_poster'=>$this->ctrl('url',__('Video Poster', 'canvasly-lite'),'content',$video,['hidden'=>true]),
   'background_video_loop'=>$this->ctrl('switch',__('Loop Video', 'canvasly-lite'),'content',$video,['hidden'=>true]),
   'background_video_mobile'=>$this->ctrl('switch',__('Play Video on Mobile', 'canvasly-lite'),'content',$video,['hidden'=>true]),
   'shape_top'=>$this->ctrl('select',__('Top Shape', 'canvasly-lite'),'style',$shape,['options'=>$shape_opts]),
   'shape_top_color'=>$this->ctrl('color',__('Top Shape Color', 'canvasly-lite'),'style',$shape,['condition'=>['shape_top!'=>'']]),
   'shape_top_height'=>$this->ctrl('slider',__('Top Shape Height', 'canvasly-lite'),'style',$shape,['units'=>['px','vh','%'],'range'=>['min'=>10,'max'=>400],'condition'=>['shape_top!'=>'']]),
   'shape_top_width'=>$this->ctrl('text',__('Top Shape Width', 'canvasly-lite'),'style',$shape,['condition'=>['shape_top!'=>'']]),
   'shape_top_flip'=>$this->ctrl('switch',__('Flip Top Shape', 'canvasly-lite'),'style',$shape,['condition'=>['shape_top!'=>'']]),
   'shape_top_front'=>$this->ctrl('switch',__('Bring Top Shape Front', 'canvasly-lite'),'style',$shape,['condition'=>['shape_top!'=>'']]),
   'shape_bottom'=>$this->ctrl('select',__('Bottom Shape', 'canvasly-lite'),'style',$shape,['options'=>$shape_opts]),
   'shape_bottom_color'=>$this->ctrl('color',__('Bottom Shape Color', 'canvasly-lite'),'style',$shape,['condition'=>['shape_bottom!'=>'']]),
   'shape_bottom_height'=>$this->ctrl('slider',__('Bottom Shape Height', 'canvasly-lite'),'style',$shape,['units'=>['px','vh','%'],'range'=>['min'=>10,'max'=>400],'condition'=>['shape_bottom!'=>'']]),
   'shape_bottom_width'=>$this->ctrl('text',__('Bottom Shape Width', 'canvasly-lite'),'style',$shape,['condition'=>['shape_bottom!'=>'']]),
   'shape_bottom_flip'=>$this->ctrl('switch',__('Flip Bottom Shape', 'canvasly-lite'),'style',$shape,['condition'=>['shape_bottom!'=>'']]),
   'shape_bottom_front'=>$this->ctrl('switch',__('Bring Bottom Shape Front', 'canvasly-lite'),'style',$shape,['condition'=>['shape_bottom!'=>'']]),
  ];
 }
 /** Original Canvasly shape geometry, drawn in a 1000×100 box and filled toward the bottom edge. */
 public static function shape_path($shape){
  switch($shape){
   case 'wave': return 'M0,60 C150,110 350,10 500,60 C650,110 850,10 1000,60 L1000,100 L0,100 Z';
   case 'tilt': return 'M0,100 L1000,0 L1000,100 Z';
   case 'triangle': return 'M0,100 L500,0 L1000,100 Z';
   case 'curve': return 'M0,100 C250,0 750,0 1000,100 Z';
   case 'arrow': return 'M0,100 L0,60 L450,60 L500,20 L550,60 L1000,60 L1000,100 Z';
   case 'mountains': return 'M0,100 L0,70 L200,30 L350,65 L500,15 L700,60 L850,35 L1000,75 L1000,100 Z';
   case 'zigzag': $d='M0,100 L0,50'; for($x=0;$x<1000;$x+=100){$d.=' L'.($x+50).',100 L'.($x+100).',50';} return $d.' L1000,100 Z';
  }
  return '';
 }
 public static function shape_svg($shape,$color,$height,$width,$flip,$front,$side){
  $path=self::shape_path($shape); if(!$path)return '';
  if(is_array($height))$height=$height['desktop']??80; if($height===''||$height===null)$height=80;
  $style=' style="--lb-shape-height:'.esc_attr(is_numeric($height)?$height.'px':$height).';--lb-shape-width:'.esc_attr($width?:'100%').';"';
  return '<div class="lb-shape lb-shape-'.esc_attr($side).($front?' lb-shape-front':'').($flip?' lb-shape-flip':'').'" aria-hidden="true"'.$style.'><svg viewBox="0 0 1000 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="'.esc_attr($path).'" fill="'.esc_attr($color?:'#ffffff').'"/></svg></div>';
 }
 public function render($s,$children=''){
  $layout=$s['layout']??'flex';
  $tag=$this->tag($s['html_tag']??'div',self::html_tags(),'div');
  $layers=class_exists('\\CanvaslyLite\\Controls\\Groups')?\CanvaslyLite\Controls\Groups::layers_html($s):'';
  foreach(['top','bottom'] as $side){
   $shape=sanitize_key($s['shape_'.$side]??''); if(!$shape||!in_array($shape,self::shapes(),true))continue;
   $layers.=self::shape_svg($shape,$s['shape_'.$side.'_color']??'#ffffff',$s['shape_'.$side.'_height']??80,sanitize_text_field((string)($s['shape_'.$side.'_width']??'100%')),!empty($s['shape_'.$side.'_flip']),!empty($s['shape_'.$side.'_front']),$side);
  }
  $linkHtml='';
  if(!empty($s['link'])){
   $t=($s['link_target']??'_self')==='_blank'?' target="_blank" rel="noopener"':'';
   $linkHtml='<a class="lb-container-link" href="'.esc_attr(self::link_href($s['link'])).'"'.$t.' aria-label="'.esc_attr($s['aria_label']??'Open link').'"></a>';
  }
  $classes=$this->cls($s).' lb-container lb-layout-'.$layout.($layers||$linkHtml?' lb-has-layers':'');
  return '<'.$tag.' class="'.esc_attr($classes).'">'.$layers.$children.$linkHtml.'</'.$tag.'>';
 }
 public function style_css($id,$s){
  $sel='#lb-node-'.$id.' .lb-container';
  $layout=$s['layout']??'flex';
  $css='';
  if($layout==='grid'){
   $cols=trim((string)($s['grid_template_columns']??''));
   if($cols==='')$cols='repeat('.max(1,(int)($s['columns']??2)).',minmax(0,1fr))';
   $css.=$sel.'{grid-template-columns:'.esc_attr($cols).';';
   $rows=trim((string)($s['grid_template_rows']??''));
   if($rows!=='')$css.='grid-template-rows:'.esc_attr($rows).';';
   elseif(!empty($s['grid_rows']))$css.='grid-template-rows:repeat('.max(1,(int)$s['grid_rows']).',auto);';
   $css.='}';
  }
  return $css;
 }
}
