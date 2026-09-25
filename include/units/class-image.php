<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
class Image extends Unit {
 public function type(){return 'image';} public function title(){return __('Image', 'canvasly-lite');} public function icon(){return '▧';} public function category(){return 'media';}
 public function keywords(){return ['image','photo','picture','lightbox','caption','responsive','media','srcset','alt'];}
 public function scripts($s=[]){
  $s=is_array($s)?$s:[];
  if(($s['link_to']??'')==='file'||($s['link']??'')==='lightbox'||!empty($s['lightbox']))return $this->frontend_scripts();
  return [];
 }
 public function defaults(){return ['image_id'=>0,'image_url'=>'','image_size'=>'full','alt'=>'','link_to'=>'none','link'=>'','link_target'=>'_self','lightbox'=>false,'caption'=>'','caption_type'=>'none','alignment'=>'left','align_self'=>'start','width'=>'100%','height'=>'','object_fit'=>'cover','object_position'=>'center','hover_animation'=>'','border_width'=>[],'border_style'=>'','border_color'=>'','border_radius'=>[],'radius'=>0,'shadow'=>[],'hover_shadow'=>[],'hover_border_color'=>'','opacity'=>1,'filter'=>'','hover_filter'=>'','image_hover_opacity'=>'','hover_transition'=>'','caption_align'=>'center','caption_color'=>'','caption_spacing'=>'','caption_size'=>'','margin'=>[],'padding'=>[],'position'=>'','z_index'=>0,'css_id'=>'','css_class'=>'','global_class'=>'','custom_css'=>'','loading'=>'lazy','decoding'=>'async','fetchpriority'=>'auto'];}
 public function controls(){
  $img=__('Image', 'canvasly-lite'); $sizes=['thumbnail'=>'Thumbnail','medium'=>'Medium','medium_large'=>'Medium Large','large'=>'Large','1536x1536'=>'1536×1536','2048x2048'=>'2048×2048','full'=>'Full'];
  $len=['%','px','vw','em','rem'];
  $hover=__('Hover', 'canvasly-lite'); $border=__('Border', 'canvasly-lite'); $cap=__('Caption', 'canvasly-lite');
  $pic='{{WRAPPER}} .lb-image, {{WRAPPER}} .lb-image-preview';
  $el='{{WRAPPER}} .lb-image-img';
  return [
   'image_id'=>$this->ctrl('media',__('Choose Image', 'canvasly-lite'),'content',$img,['dynamic'=>true]),
   'image_url'=>$this->ctrl('url',__('Image URL', 'canvasly-lite'),'content',$img,['hidden'=>true,'dynamic'=>true]),
   'image_size'=>$this->ctrl('select',__('Image Size', 'canvasly-lite'),'content',$img,['options'=>$sizes]),
   'link_to'=>$this->ctrl('select',__('Link', 'canvasly-lite'),'content',$img,['options'=>['none'=>__('None', 'canvasly-lite'),'file'=>__('Media File', 'canvasly-lite'),'custom'=>__('Custom URL', 'canvasly-lite')]]),
   'link'=>$this->ctrl('url',__('Custom URL', 'canvasly-lite'),'content',$img,['condition'=>['link_to'=>'custom'],'dynamic'=>true]),
   'link_target'=>$this->ctrl('select',__('Link Target', 'canvasly-lite'),'content',$img,['options'=>self::opt_target(),'condition'=>['link_to'=>'custom']]),
   'lightbox'=>$this->ctrl('switch',__('Lightbox', 'canvasly-lite'),'content',$img),
   'caption_type'=>$this->ctrl('select',__('Caption', 'canvasly-lite'),'content',$img,['options'=>['none'=>__('None', 'canvasly-lite'),'custom'=>__('Custom', 'canvasly-lite'),'attachment'=>__('Attachment', 'canvasly-lite')]]),
   'caption'=>$this->ctrl('text',__('Caption Text', 'canvasly-lite'),'content',$img,['condition'=>['caption_type'=>'custom'],'dynamic'=>true]),
   'alt'=>$this->ctrl('text',__('Alt Text', 'canvasly-lite'),'content',$img,['dynamic'=>true]),
   'loading'=>$this->ctrl('select',__('Lazy Load', 'canvasly-lite'),'content',$img,['options'=>['lazy'=>__('Lazy', 'canvasly-lite'),'eager'=>__('Eager', 'canvasly-lite'),'auto'=>__('Auto', 'canvasly-lite')]]),
   'decoding'=>$this->ctrl('select',__('Decoding', 'canvasly-lite'),'content',$img,['options'=>['async'=>__('Async', 'canvasly-lite'),'sync'=>__('Sync', 'canvasly-lite'),'auto'=>__('Auto', 'canvasly-lite')]]),
   'fetchpriority'=>$this->ctrl('select',__('Fetch Priority', 'canvasly-lite'),'content',$img,['options'=>['auto'=>__('Auto', 'canvasly-lite'),'high'=>__('High', 'canvasly-lite'),'low'=>__('Low', 'canvasly-lite')]]),
   'alignment'=>$this->ctrl('choose',__('Alignment', 'canvasly-lite'),'style',$img,['responsive'=>true,'options'=>self::opt_lcr(),'map'=>['left'=>'flex-start','center'=>'center','right'=>'flex-end'],'selectors'=>['{{WRAPPER}}'=>'align-items: {{VALUE}};text-align: {{RAW}};--lb-media-items: {{VALUE}};']]),
   'align_self'=>$this->ctrl('select',__('Vertical Align', 'canvasly-lite'),'style',$img,['options'=>['start'=>__('Start', 'canvasly-lite'),'center'=>__('Center', 'canvasly-lite'),'end'=>__('End', 'canvasly-lite'),'stretch'=>__('Stretch', 'canvasly-lite')]]),
   'width'=>$this->ctrl('slider',__('Width', 'canvasly-lite'),'style',$img,['responsive'=>true,'units'=>$len,'range'=>['min'=>0,'max'=>1000],'selectors'=>['{{WRAPPER}}'=>'--lb-img-w: {{VALUE}};',$pic=>'width: {{VALUE}};max-width: 100%;']]),
   'max_width'=>$this->ctrl('slider',__('Max Width', 'canvasly-lite'),'style',$img,['responsive'=>true,'units'=>['px','%','vw','em','rem'],'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}}'=>'--lb-img-max-w: {{VALUE}};',$pic=>'max-width: {{VALUE}};']]),
   'height'=>$this->ctrl('slider',__('Height', 'canvasly-lite'),'style',$img,['responsive'=>true,'units'=>['px','%','vh','em','rem','auto'],'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}}'=>'--lb-img-h: {{VALUE}};',$el=>'height: {{VALUE}};']]),
   'object_fit'=>$this->ctrl('select',__('Object Fit', 'canvasly-lite'),'style',$img,['options'=>['cover'=>__('Cover', 'canvasly-lite'),'contain'=>__('Contain', 'canvasly-lite'),'fill'=>__('Fill', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite'),'scale-down'=>__('Scale Down', 'canvasly-lite')],'selectors'=>['{{WRAPPER}}'=>'--lb-object-fit: {{VALUE}};',$el=>'object-fit: {{VALUE}};']]),
   'object_position'=>$this->ctrl('select',__('Object Position', 'canvasly-lite'),'style',$img,['options'=>['center'=>__('Center', 'canvasly-lite'),'top'=>__('Top', 'canvasly-lite'),'right'=>__('Right', 'canvasly-lite'),'bottom'=>__('Bottom', 'canvasly-lite'),'left'=>__('Left', 'canvasly-lite'),'top left'=>__('Top Left', 'canvasly-lite'),'top right'=>__('Top Right', 'canvasly-lite'),'bottom left'=>__('Bottom Left', 'canvasly-lite'),'bottom right'=>__('Bottom Right', 'canvasly-lite')],'selectors'=>['{{WRAPPER}}'=>'--lb-object-position: {{VALUE}};',$el=>'object-position: {{VALUE}};']]),
   'opacity'=>$this->ctrl('slider',__('Opacity', 'canvasly-lite'),'style',$img,['units'=>[],'range'=>['min'=>0,'max'=>1,'step'=>0.05],'selectors'=>[$el=>'opacity: {{SIZE}};']]),
   'filter'=>$this->ctrl('css_filter',__('CSS Filter', 'canvasly-lite'),'style',$img,['selectors'=>[$el=>'filter: {{VALUE}};']]),
   'image_hover_opacity'=>$this->ctrl('slider',__('Opacity', 'canvasly-lite'),'style',$hover,['units'=>[],'range'=>['min'=>0,'max'=>1,'step'=>0.05],'selectors'=>['{{WRAPPER}}:hover .lb-image-img'=>'opacity: {{SIZE}};']]),
   'hover_filter'=>$this->ctrl('css_filter',__('CSS Filter', 'canvasly-lite'),'style',$hover,['selectors'=>['{{WRAPPER}}:hover .lb-image-img'=>'filter: {{VALUE}};']]),
   'hover_border_color'=>$this->ctrl('color',__('Border Color', 'canvasly-lite'),'style',$hover,['condition'=>['border_style!'=>''],'selectors'=>['{{WRAPPER}}:hover .lb-image, {{WRAPPER}}:hover .lb-image-img'=>'border-color: {{VALUE}};']]),
   'hover_shadow'=>$this->ctrl('box_shadow',__('Shadow', 'canvasly-lite'),'style',$hover,['selectors'=>['{{WRAPPER}}:hover .lb-image'=>'box-shadow: {{VALUE}};']]),
   'hover_animation'=>$this->ctrl('select',__('Hover Animation', 'canvasly-lite'),'style',$hover,['options'=>self::opt_hover()]),
   'hover_transition'=>$this->ctrl('slider',__('Transition Duration', 'canvasly-lite'),'style',$hover,['units'=>['s','ms'],'range'=>['min'=>0,'max'=>3,'step'=>0.05],'selectors'=>[$el=>'transition-duration: {{SIZE}}{{UNIT}};']]),
   'border_style'=>$this->ctrl('select',__('Border Style', 'canvasly-lite'),'style',$border,['options'=>[''=>__('None', 'canvasly-lite'),'solid'=>__('Solid', 'canvasly-lite'),'dashed'=>__('Dashed', 'canvasly-lite'),'dotted'=>__('Dotted', 'canvasly-lite'),'double'=>__('Double', 'canvasly-lite')],'selectors'=>[$pic.', '.$el=>'border-style: {{VALUE}};']]),
   'border_width'=>$this->ctrl('dimensions',__('Border Width', 'canvasly-lite'),'style',$border,['condition'=>['border_style!'=>''],'selectors'=>[$pic.', '.$el=>'border-width: {{VALUE}};']]),
   'border_color'=>$this->ctrl('color',__('Border Color', 'canvasly-lite'),'style',$border,['condition'=>['border_style!'=>''],'selectors'=>[$pic.', '.$el=>'border-color: {{VALUE}};']]),
   'border_radius'=>$this->ctrl('dimensions',__('Radius', 'canvasly-lite'),'style',$border,['selectors'=>[$pic.', '.$el=>'border-radius: {{VALUE}};']]),
   'shadow'=>$this->ctrl('box_shadow',__('Shadow', 'canvasly-lite'),'style',$border,['selectors'=>['{{WRAPPER}} .lb-image'=>'box-shadow: {{VALUE}};']]),
   'caption_align'=>$this->ctrl('choose',__('Alignment', 'canvasly-lite'),'style',$cap,['responsive'=>true,'options'=>self::opt_lcr(),'condition'=>['caption_type!'=>'none'],'selectors'=>['{{WRAPPER}} .lb-image-caption'=>'text-align: {{VALUE}};']]),
   'caption_color'=>$this->ctrl('color',__('Color', 'canvasly-lite'),'style',$cap,['condition'=>['caption_type!'=>'none'],'selectors'=>['{{WRAPPER}} .lb-image-caption'=>'color: {{VALUE}};']]),
   'caption_size'=>$this->ctrl('slider',__('Size', 'canvasly-lite'),'style',$cap,['responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>8,'max'=>48],'condition'=>['caption_type!'=>'none'],'selectors'=>['{{WRAPPER}} .lb-image-caption'=>'font-size: {{SIZE}}{{UNIT}};']]),
   'caption_spacing'=>$this->ctrl('slider',__('Spacing', 'canvasly-lite'),'style',$cap,['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>0,'max'=>60],'condition'=>['caption_type!'=>'none'],'selectors'=>['{{WRAPPER}} .lb-image-caption'=>'margin-top: {{SIZE}}{{UNIT}};']]),
  ];
 }
 public function render($s,$children=''){
  $id=absint($s['image_id']??0); $size=sanitize_key($s['image_size']??'full');$url=esc_url($s['image_url']??'');
  if($id){$src=wp_get_attachment_image_src($id,$size?:'full');if($src)$url=esc_url($src[0]);}
  if(!$url)return '<div class="'.$this->cls($s).' lb-image-placeholder">'.esc_html__('Choose an image', 'canvasly-lite').'</div>';
  $alt=$s['alt']??'';if($alt===''&&$id)$alt=get_post_meta($id,'_wp_attachment_image_alt',true);
  $attrs=' alt="'.esc_attr($alt).'" loading="'.esc_attr($s['loading']??'lazy').'" decoding="'.esc_attr($s['decoding']??'async').'"';if(($s['fetchpriority']??'auto')!=='auto')$attrs.=' fetchpriority="'.esc_attr($s['fetchpriority']).'"';
  if($id){$srcset=wp_get_attachment_image_srcset($id,$size?:'full');$sizes=wp_get_attachment_image_sizes($id,$size?:'full');if($srcset)$attrs.=' srcset="'.esc_attr($srcset).'"';if($sizes)$attrs.=' sizes="'.esc_attr($sizes).'"';}
  $img='<img class="lb-image-img" src="'.$url.'"'.$attrs.'>';
  $link_to=$s['link_to']??'';
  if($link_to===''&&!empty($s['lightbox']))$link_to='file';
  if($link_to===''&&!empty($s['link']))$link_to='custom';
  if($link_to==='file'||!empty($s['lightbox'])){
   $attrs_lb=' data-lb-lightbox="1" aria-label="Open image in lightbox"';
   if(class_exists('\\CanvaslyLite\\Settings\\KitSettings'))$attrs_lb=\CanvaslyLite\Settings\KitSettings::lightbox_data_attrs($id,$alt,$s['caption']??'',$id?get_the_title($id):'').$attrs_lb;
   $img='<a class="lb-image-lightbox" href="'.$url.'"'.$attrs_lb.'>'.$img.'</a>';
  }
  elseif($link_to==='custom'&&!empty($s['link']))$img='<a href="'.esc_attr(self::link_href($s['link'])).'" target="'.esc_attr($s['link_target']??'_self').'"'.(($s['link_target']??'')==='_blank'?' rel="noopener"':'').'>'.$img.'</a>';
  $cap='';if(($s['caption_type']??'none')!=='none'){$text=$s['caption']??'';if($text===''&&$id)$text=wp_get_attachment_caption($id)?:'';if($text!=='')$cap='<figcaption class="lb-image-caption">'.esc_html($text).'</figcaption>';}
  $self=in_array($s['align_self']??'start',['start','center','end','stretch'],true)?$s['align_self']:'start';
  return '<figure class="'.$this->cls($s).' lb-image'.$this->hover_class($s).'" style="justify-self:stretch;align-self:'.esc_attr($self).';max-width:100%;min-width:0;overflow:hidden;box-sizing:border-box;"'.$this->attrs($s).'>'.$img.$cap.'</figure>';
 }
 public function style_css($id,$s){
  $sel='#lb-node-'.$id;
  return $sel.'{display:flex;flex-direction:column;max-width:100%;min-width:0;overflow:hidden;box-sizing:border-box;}'.$sel.' .lb-image-img{width:100%;max-width:100%;display:block;}';
 }
}
