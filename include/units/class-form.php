<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
class Form extends Unit {
 public function type(){return 'form';} public function title(){return __('Form', 'canvasly-lite');} public function icon(){return '▤';} public function category(){return 'content';}
 public function uses_button(){return true;}
 public function keywords(){return ['form','contact','fields','email'];}
 public function scripts($s=[]){
  $h=$this->frontend_scripts();
  if(class_exists('\\CanvaslyLite\\Settings\\AdminSettings')&&\CanvaslyLite\Settings\AdminSettings::recaptcha_enabled())$h[]='google-recaptcha';
  return $h;
 }
 public function defaults(){return [
  'title'=>__('Contact Us', 'canvasly-lite'),
  'fields'=>[
   ['_id'=>'ff1','label'=>__('Name', 'canvasly-lite'),'name'=>'name','type'=>'text','required'=>true,'placeholder'=>'','options'=>''],
   ['_id'=>'ff2','label'=>__('Email', 'canvasly-lite'),'name'=>'email','type'=>'email','required'=>true,'placeholder'=>'','options'=>''],
   ['_id'=>'ff3','label'=>__('Message', 'canvasly-lite'),'name'=>'message','type'=>'textarea','required'=>true,'placeholder'=>'','options'=>''],
  ],
  'submit'=>__('Send Message', 'canvasly-lite'),'success'=>__('Thanks! Your message has been sent.', 'canvasly-lite'),'email'=>'','honeypot'=>true,'layout'=>'stack',
 ];}
 public function controls(){
  $form=__('Form', 'canvasly-lite');
  return [
   'title'=>$this->ctrl('text',__('Title', 'canvasly-lite'),'content',$form),
   'fields'=>$this->ctrl('repeater',__('Fields', 'canvasly-lite'),'content',$form,[
    'title_field'=>'{{label}}','prevent_empty'=>true,
    'fields'=>[
     'label'=>$this->field('text',__('Label', 'canvasly-lite')),
     'name'=>$this->field('text',__('Name', 'canvasly-lite'),['description'=>__('HTML name attribute. Defaults to a slug of the label.', 'canvasly-lite')]),
     'type'=>$this->field('select',__('Type', 'canvasly-lite'),['options'=>['text'=>__('Text', 'canvasly-lite'),'email'=>__('Email', 'canvasly-lite'),'tel'=>__('Tel', 'canvasly-lite'),'url'=>__('URL', 'canvasly-lite'),'textarea'=>__('Textarea', 'canvasly-lite'),'select'=>__('Select', 'canvasly-lite'),'checkbox'=>__('Checkbox', 'canvasly-lite')]]),
     'required'=>$this->field('switch',__('Required', 'canvasly-lite')),
     'placeholder'=>$this->field('text',__('Placeholder', 'canvasly-lite'),['condition'=>['type'=>['text','email','tel','url','textarea']]]),
     'options'=>$this->field('textarea',__('Options', 'canvasly-lite'),['description'=>__('One option per line.', 'canvasly-lite'),'condition'=>['type'=>'select']]),
    ],
   ]),
   'submit'=>$this->ctrl('text',__('Submit Button', 'canvasly-lite'),'content',$form),
   'success'=>$this->ctrl('textarea',__('Success Message', 'canvasly-lite'),'content',$form),
   'email'=>$this->ctrl('text',__('Send To', 'canvasly-lite'),'content',$form,['placeholder'=>__('Leave empty to use the site admin email', 'canvasly-lite')]),
   'honeypot'=>$this->ctrl('switch',__('Honeypot', 'canvasly-lite'),'content',$form),
   'layout'=>$this->ctrl('select',__('Layout', 'canvasly-lite'),'content',$form,['options'=>['stack'=>__('Stacked', 'canvasly-lite'),'inline'=>__('Inline', 'canvasly-lite'),'two-column'=>__('Two Columns', 'canvasly-lite')]]),
  ];
 }
 public function render($s,$children=''){
  $id='lb-form-'.wp_generate_uuid4();
  $layout_raw=$s['layout']??'stack';
  $layout=in_array($layout_raw,['stack','inline','two-column'],true)?$layout_raw:'stack';
  $fields=[];
  foreach($this->repeater_items($s['fields']??'',['label','type','required','placeholder']) as $row){
   $label=trim((string)($row['label']??$row[0]??'')); if($label==='')continue;
   $name=sanitize_key($row['name']??''); if($name==='')$name=sanitize_key($label);
   $raw=(string)($row['type']??($row[1]??'text'));
   $known=['text','email','tel','url','textarea','select','checkbox'];
   $type=in_array($raw,$known,true)?$raw:'';
   $req=!empty($row['required'])||(($row[2]??'')==='required');
   $placeholder=(string)($row['placeholder']??$row[3]??'');
   $ph=esc_attr($placeholder);
   $reqAttr=$req?' required':'';
   $phAttr=$ph!==''?' placeholder="'.$ph.'"':'';
   $input='';
   if($type===''){
    /** Extra field types from add-ons. Return escaped HTML, or empty to keep a text input. Known types skip this filter. */
    $custom=apply_filters('canvasly-lite/form/field_html','',$raw,array('name'=>$name,'label'=>$label,'required'=>$req,'placeholder'=>$placeholder,'options'=>(string)($row['options']??''),'row'=>$row));
    if(is_string($custom)&&$custom!==''){ $type=sanitize_key($raw); $input=$custom; }
    else $type='text';
   }
   if($input===''){
    if($type==='textarea')$input='<textarea name="'.esc_attr($name).'"'.$reqAttr.$phAttr.'></textarea>';
    elseif($type==='select'){
     $opts='<option value="">'.esc_html__('Select…', 'canvasly-lite').'</option>';
     foreach(preg_split('/\r?\n/',(string)($row['options']??'')) as $opt){ $opt=trim($opt); if($opt==='')continue; $opts.='<option value="'.esc_attr($opt).'">'.esc_html($opt).'</option>'; }
     $input='<select name="'.esc_attr($name).'"'.$reqAttr.'>'.$opts.'</select>';
    }
    elseif($type==='checkbox')$input='<label><input type="checkbox" name="'.esc_attr($name).'" value="1"'.$reqAttr.'> '.esc_html($label).'</label>';
    else $input='<input type="'.esc_attr($type).'" name="'.esc_attr($name).'"'.$reqAttr.$phAttr.'>';
   }
   $bare=in_array($type,['checkbox','acceptance'],true);
   $fields[]='<div class="lb-form-field">'.($bare?'':('<label>'.esc_html($label).'</label>')).$input.'</div>';
  }
  $hp=!empty($s['honeypot'])?'<input class="lb-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">':'';
  $to=!empty($s['email'])?'<input type="hidden" name="_to" value="'.esc_attr($s['email']).'">':'';
  $ok=!empty($s['success'])?'<input type="hidden" name="_success" value="'.esc_attr($s['success']).'">':'';
  $captcha='';
  if(class_exists('\\CanvaslyLite\\Settings\\AdminSettings')&&\CanvaslyLite\Settings\AdminSettings::recaptcha_enabled()){
   $captcha='<div class="lb-recaptcha" data-lb-recaptcha="'.esc_attr(\CanvaslyLite\Settings\AdminSettings::recaptcha_type()).'" data-sitekey="'.esc_attr(\CanvaslyLite\Settings\AdminSettings::recaptcha_site_key()).'"></div>';
  }
  return '<form class="'.$this->cls($s).' lb-form lb-form-'.$layout.'" id="'.esc_attr($id).'" method="post" data-lb-form="1">'.((string)($s['title']??'')!==''?'<h3>'.esc_html($s['title']).'</h3>':'').implode('',$fields).$hp.$to.$ok.$captcha.'<button type="submit">'.esc_html($s['submit']??__('Send Message', 'canvasly-lite')).'</button><div class="lb-form-message" role="status" aria-live="polite"></div></form>';
 }
}
