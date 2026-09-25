<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/** Image Box: image + title + description with image placement (top / left / right), media-library picker, link and hover animation. */
class ImageBox extends Unit {
 public function type(){return 'image_box';} public function title(){return __('Image Box', 'canvasly-lite');} public function icon(){return '▣';} public function category(){return 'media';}
 public function keywords(){return ['image box','feature','card','image','box'];}
 public function defaults(){return ['image_id'=>0,'image_url'=>'','image_size'=>'large','alt'=>'','title'=>'This is the heading','title_tag'=>'h3','text'=>'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.','link'=>'','link_target'=>'_self','box_layout'=>'top','content_align'=>'center','vertical_align'=>'top','image_width'=>'','image_space'=>15,'title_space'=>10,'image_radius'=>'','image_opacity'=>'','image_hover_opacity'=>'','title_color'=>'#222222','title_hover_color'=>'','text_color'=>'','hover_animation'=>''];}
 public function controls(){return ['image_id'=>'media','image_url'=>'url','image_size'=>'select','alt'=>'text','title'=>'text','title_tag'=>'select','text'=>'wysiwyg','link'=>'url','link_target'=>'select','box_layout'=>'select','content_align'=>'select','vertical_align'=>'select','image_width'=>'text','image_space'=>'number','title_space'=>'number','image_radius'=>'number','image_opacity'=>'number','image_hover_opacity'=>'number','title_color'=>'color','title_hover_color'=>'color','text_color'=>'color','hover_animation'=>'select'];}
 public function render($s,$children=''){
  $layout=in_array($s['box_layout']??'top',['top','left','right'],true)?$s['box_layout']:'top';
  $align=in_array($s['content_align']??'center',['left','center','right'],true)?$s['content_align']:'center';
  $valign=in_array($s['vertical_align']??'top',['top','middle','bottom'],true)?$s['vertical_align']:'top';
  $tag=$this->tag($s['title_tag']??'h3',$this->title_tags(),'h3');
  $vars=$this->style_attr(['--lb-box-image-width'=>sanitize_text_field((string)($s['image_width']??'')),'--lb-box-image-space'=>$this->unit($s['image_space']??''),'--lb-box-title-space'=>$this->unit($s['title_space']??''),'--lb-box-image-radius'=>$this->unit($s['image_radius']??''),'--lb-box-image-opacity'=>$s['image_opacity']??'','--lb-box-image-hover-opacity'=>$s['image_hover_opacity']??'','--lb-box-title-color'=>$s['title_color']??'','--lb-box-title-hover'=>$s['title_hover_color']??'','--lb-box-text-color'=>$s['text_color']??'']);
  $link=!empty($s['link'])?esc_attr(self::link_href($s['link'])):'';
  $t=($s['link_target']??'_self')==='_blank'?' target="_blank" rel="noopener"':'';
  $id=absint($s['image_id']??0); $size=sanitize_key($s['image_size']??'large');
  $url=$this->media_url($id,$s['image_url']??'',$size);
  $alt=(string)($s['alt']??''); if($alt===''&&$id)$alt=(string)get_post_meta($id,'_wp_attachment_image_alt',true);
  $img=$url?'<img class="lb-image-box-img'.$this->hover_class($s).'" src="'.esc_url($url).'" alt="'.esc_attr($alt).'" loading="lazy">':'<span class="lb-image-placeholder">'.esc_html__('Choose an image', 'canvasly-lite').'</span>';
  if($link&&$url)$img='<a href="'.$link.'"'.$t.' tabindex="-1" aria-hidden="true">'.$img.'</a>';
  $title=esc_html($s['title']??''); if($link)$title='<a href="'.$link.'"'.$t.'>'.$title.'</a>';
  $out='<div class="'.$this->cls($s).' lb-image-box lb-image-box-'.$layout.' lb-image-box-align-'.$align.' lb-image-box-valign-'.$valign.'"'.$vars.'>';
  $out.='<figure class="lb-image-box-figure">'.$img.'</figure><div class="lb-image-box-content">';
  if($title!=='')$out.='<'.$tag.' class="lb-image-box-title">'.$title.'</'.$tag.'>';
  if(($s['text']??'')!=='')$out.='<div class="lb-image-box-text">'.wp_kses_post($s['text']).'</div>';
  return $out.'</div></div>';
 }
}
