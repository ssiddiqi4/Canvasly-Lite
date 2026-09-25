<?php
namespace CanvaslyLite\Units;
if ( ! defined('ABSPATH') ) exit;
abstract class Unit {
 public function type(){return '';}
 public function title(){return '';}
 public function icon(){return '◇';}
 public function category(){return 'basic';}
 /** Widget package slug (`lite`, `pro`, or an add-on). Empty lets Units Manager infer it from the class namespace. */
 public function source(){return '';}
 public function keywords(){return [$this->type(),$this->title()];}
 public function supports_children(){return false;}
 /**
  * Repeater control key whose items become named child slots.
  * Empty string means the unit is not slot-aware (children, if any, are a flat list).
  */
 public function slot_source(){return '';}
 /** True when panels are addressed by repeater-item `_id` rather than a flat children list. */
 public function supports_slots(){return $this->slot_source()!=='';}
 /**
  * Slot descriptors for the current settings: `[ ['id'=>'tab1','title'=>'Tab 1'], ... ]`.
  * Each `id` matches a repeater item `_id`; editor drop targets and frontend panels use it.
  */
 public function slots($settings){
  $key=$this->slot_source();
  if($key==='')return [];
  $s=is_array($settings)?$settings:[];
  $items=$this->repeater_items($s[$key]??[],['title']);
  $out=[];
  foreach($items as $i=>$item){
   $id=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($item['_id']??''));
   if($id==='')$id='slot'.$i;
   $out[]=['id'=>$id,'title'=>(string)($item['title']??'')];
  }
  return $out;
 }
 public function defaults(){return [];}
 /**
  * Control schema. Each entry is either a type string (`'color'`) or a definition array:
  *
  *   'key' => [
  *     'type'        => 'slider',                 // any registered control type
  *     'label'=>__('Width', 'canvasly-lite'),
  *     'section'     => 'Layout',                 // panel section (accordion) title
  *     'tab'         => 'content|style|advanced', // default 'content'
  *     'responsive'  => true,                     // value stored as ['desktop'=>.., breakpoint names]
  *     'units'       => ['px','%','em','rem','vw','vh'], // slider units; [] = unitless number
  *     'range'       => ['min'=>0,'max'=>100,'step'=>1],
  *     'options'     => ['value'=>__('Label', 'canvasly-lite'), ...] or ['a','b'],
  *     'condition'   => ['other_key'=>'value', 'other_key!'=>'', 'k'=>['a','b']], // all must match
  *     'selectors'   => ['{{WRAPPER}} .x'=>'width: {{SIZE}}{{UNIT}};'],          // see Style::schema_css()
  *     'map'         => ['left'=>'flex-start'],   // {{VALUE}} is looked up here; {{RAW}} is the stored value
  *     'default'     => '',
  *     'separator'   => 'before|after',
  *     'description'=>__('Help text', 'canvasly-lite'),
  *     'placeholder' => '',
  *     'hidden'      => false,                    // accepted by the sanitizer but not shown in the panel
  *     'dynamic'     => true,                     // or ['categories'=>['text','url']]; per-control tag toggle
  *     'translatable'=> true,                     // WPML/Polylang; default true for text/textarea/wysiwyg/url/html
  *     'fields'      => [...],                    // repeater: nested control schema (same shape as this)
  *     'title_field' => 'title' or '{{title}}',   // repeater: item header label
  *     'prevent_empty'=> true,                    // repeater: keep at least one item
  *   ]
  *
  * String values are shorthand for `['type' => $string]`.
  */
 public function controls(){return [];}
 public function render($settings,$children=''){return $children;}
 /** Extra CSS for this unit. `$id` is the node id (selector is `#lb-node-{$id}`). Add-ons may also use the `canvasly-lite/unit/style_css` filter. */
 public function style_css($id,$settings){return '';}
 /**
  * WP script handles this unit needs on the frontend. Empty means no extra JS.
  * Return values are settings-aware so lightbox, dismiss, overlay, load-more, etc. can opt in.
  * The renderer enqueues only handles that appear on the current page.
  *
  * @param array $settings
  * @return string[]
  */
 public function scripts($settings=[]){return [];}
 /**
  * WP style handles this unit needs on the frontend. Empty means no extra CSS.
  *
  * @param array $settings
  * @return string[]
  */
 public function styles($settings=[]){return [];}
 /** Normalized unique script handles after `canvasly-lite/unit/scripts` and background-layer detection. */
 public function get_scripts($settings=[]){
  $s=is_array($settings)?$settings:[];
  $handles=$this->scripts($s);
  if(!is_array($handles))$handles=[];
  if(self::settings_need_frontend($s))$handles[]='canvasly-lite-frontend';
  $handles=apply_filters('canvasly-lite/unit/scripts',$handles,$this,$s);
  return self::normalize_handles(is_array($handles)?$handles:[]);
 }
 /** Normalized unique style handles after `canvasly-lite/unit/styles`. */
 public function get_styles($settings=[]){
  $s=is_array($settings)?$settings:[];
  $handles=$this->styles($s);
  if(!is_array($handles))$handles=[];
  $handles=apply_filters('canvasly-lite/unit/styles',$handles,$this,$s);
  return self::normalize_handles(is_array($handles)?$handles:[]);
 }
 /** True when advanced background video/slideshow on any unit needs the core frontend bundle. */
 public static function settings_need_frontend($settings){
  $s=is_array($settings)?$settings:[];
  if(!empty($s['background_video']))return true;
  $bg=is_array($s['background']??null)?$s['background']:[];
  if(!empty($bg['video_url']))return true;
  $type=(string)($bg['type']??'');
  return $type==='slideshow'||$type==='video';
 }
 /** @param mixed $handles @return string[] */
 public static function normalize_handles($handles){
  $out=[];
  foreach((array)$handles as $h){
   $h=sanitize_key((string)$h);
   if($h!=='')$out[$h]=true;
  }
  return array_keys($out);
 }
 /** Core frontend bundle handle, for subclasses that always need JS. */
 protected function frontend_scripts(){return ['canvasly-lite-frontend'];}
 /** Defaults after the `canvasly-lite/unit/defaults` filter. Schema `default`s are merged under `defaults()`. Use this instead of defaults() when reading. */
 public function get_defaults(){
  $schema=[];
  foreach($this->controls() as $k=>$def){ if(is_array($def)&&array_key_exists('default',$def))$schema[$k]=$def['default']; }
  $d=apply_filters('canvasly-lite/unit/defaults',array_merge($schema,(array)$this->defaults()),$this);
  return is_array($d)?$d:[];
 }
 /** True when the unit declares at least one control as a schema array (opts the panel into the schema renderer). */
 public function uses_schema(){
  foreach($this->controls() as $def){ if(is_array($def))return true; }
  return false;
 }
 /** True when the unit renders a clickable button (CTA, submit, read more, …). */
 public function uses_button(){return false;}
 /** Descendant selectors for button-like nodes, used by the shared Advanced radius control. */
 public static function button_selector(){
  return '{{WRAPPER}} .lb-button,{{WRAPPER}} .lb-form button,{{WRAPPER}} .lb-read-more,{{WRAPPER}} .lb-price-table>a,{{WRAPPER}} .lb-login button,{{WRAPPER}} .lb-link-bio-links a,{{WRAPPER}} .lb-flip-button,{{WRAPPER}} .lb-loop-more,{{WRAPPER}} .lb-loop-page,{{WRAPPER}} .cp-cta-button';
 }
 /**
  * Full normalized control schema: unit controls + shared controls, filtered through
  * `canvasly-lite/unit/controls`, every value normalized to a definition array (see controls()).
  */
 public function all_controls(){
  $c=apply_filters('canvasly-lite/unit/controls',$this->base_controls(),$this);
  if(!is_array($c))return [];
  $out=[];
  foreach($c as $k=>$def){
   $k=sanitize_key((string)$k); if($k==='')continue;
   if($k==='button_radius'&&!$this->uses_button()){
    if(!is_array($def))$def=['type'=>$def];
    $def['hidden']=true;
   }
   $out[$k]=self::normalize_control($k,$def);
  }
  return $out;
 }
 /** Flat `key => type` map (legacy shape of all_controls()). */
 public function control_types(){
  $out=[]; foreach($this->all_controls() as $k=>$def)$out[$k]=$def['type']; return $out;
 }
 /** Unfiltered unit + shared controls. Unit definitions win over shared ones with the same key. */
 protected function base_controls(){
  return $this->controls()+self::shared_controls();
 }
 /** Compact schema entry: type, label, tab, section, plus any extra keys (options, selectors, condition…). */
 protected function ctrl($type,$label,$tab,$section,array $extra=[]){
  return array_merge(['type'=>$type,'label'=>$label,'tab'=>$tab,'section'=>$section],$extra);
 }
 /** Nested repeater field (type + label + extras). Tab/section are unused inside a repeater. */
 protected function field($type,$label,array $extra=[]){
  return array_merge(['type'=>$type,'label'=>$label],$extra);
 }
 /** Shared option lists used by the migrated widgets. */
 protected static function opt_target(){return ['_self'=>__('Same Window', 'canvasly-lite'),'_blank'=>__('New Window', 'canvasly-lite')];}
 protected static function opt_lcr(){return ['left'=>__('Left', 'canvasly-lite'),'center'=>__('Center', 'canvasly-lite'),'right'=>__('Right', 'canvasly-lite')];}
 protected static function opt_align(){return ['left'=>__('Left', 'canvasly-lite'),'center'=>__('Center', 'canvasly-lite'),'right'=>__('Right', 'canvasly-lite'),'justify'=>__('Justify', 'canvasly-lite')];}
 protected static function opt_hover(){return [''=>__('None', 'canvasly-lite'),'zoom'=>__('Zoom', 'canvasly-lite'),'grow'=>__('Grow', 'canvasly-lite'),'shrink'=>__('Shrink', 'canvasly-lite'),'lift'=>__('Lift', 'canvasly-lite'),'sink'=>__('Sink', 'canvasly-lite'),'fade'=>__('Fade', 'canvasly-lite'),'rotate'=>__('Rotate', 'canvasly-lite'),'float'=>__('Float', 'canvasly-lite'),'pulse'=>__('Pulse', 'canvasly-lite'),'skew'=>__('Skew', 'canvasly-lite'),'wobble'=>__('Wobble', 'canvasly-lite'),'buzz'=>__('Buzz', 'canvasly-lite')];}
 protected static function opt_title_tags(){return ['h1'=>__('H1', 'canvasly-lite'),'h2'=>__('H2', 'canvasly-lite'),'h3'=>__('H3', 'canvasly-lite'),'h4'=>__('H4', 'canvasly-lite'),'h5'=>__('H5', 'canvasly-lite'),'h6'=>__('H6', 'canvasly-lite'),'div'=>__('div', 'canvasly-lite'),'span'=>__('span', 'canvasly-lite'),'p'=>__('p', 'canvasly-lite')];}
 protected static function opt_weight(){return [''=>__('Default', 'canvasly-lite'),'100'=>'100','200'=>'200','300'=>'300','400'=>'400','500'=>'500','600'=>'600','700'=>'700','800'=>'800','900'=>'900'];}
 /** Turn a control definition (string shorthand or array) into a complete definition array. */
 public static function normalize_control($key,$def){
  if(is_string($def))$def=['type'=>$def];
  if(!is_array($def))$def=[];
  $type=isset($def['type'])&&is_string($def['type'])&&$def['type']!==''?sanitize_key($def['type']):'text';
  $tab=isset($def['tab'])&&in_array($def['tab'],['content','style','advanced'],true)?$def['tab']:'content';
  $out=$def;
  $out['type']=$type;
  $out['label']=isset($def['label'])&&is_string($def['label'])?$def['label']:self::humanize($key);
  $out['section']=isset($def['section'])&&is_string($def['section'])?$def['section']:'';
  $out['tab']=$tab;
  $out['responsive']=!empty($def['responsive']);
  $out['hidden']=!empty($def['hidden']);
  if(array_key_exists('translatable',$def))$out['translatable']=!empty($def['translatable']);
  $dyn=self::normalize_dynamic($key,$type,$def['dynamic']??null);
  if($dyn)$out['dynamic']=$dyn; else unset($out['dynamic']);
  if(isset($def['units'])){ $out['units']=is_array($def['units'])?array_values(array_map('strval',$def['units'])):[]; }
  if(isset($def['range'])&&!is_array($def['range']))unset($out['range']);
  if(isset($def['options'])&&!is_array($def['options']))unset($out['options']);
  if(isset($def['condition'])&&!is_array($def['condition']))unset($out['condition']);
  if(isset($def['selectors'])&&!is_array($def['selectors']))unset($out['selectors']);
  if(isset($def['map'])&&!is_array($def['map']))unset($out['map']);
  if($type==='repeater'){
   $fields=[];
   foreach((array)($def['fields']??[]) as $fk=>$fdef){ $fk=sanitize_key((string)$fk); if($fk==='')continue; $fields[$fk]=self::normalize_control($fk,$fdef); }
   $out['fields']=$fields;
   $out['title_field']=isset($def['title_field'])?(string)$def['title_field']:'';
   $out['prevent_empty']=!empty($def['prevent_empty']);
  }
  return $out;
 }
 /**
  * Normalize a control `dynamic` flag. `true` infers categories from the type; an array may set
  * `categories`. Content keys (text, title, url, …) default to on when the flag is omitted.
  *
  * @param string $key
  * @param string $type
  * @param mixed  $dynamic
  * @return array{active:bool,categories:string[]}|false
  */
 public static function normalize_dynamic($key,$type,$dynamic=null){
  $by_type=array(
   'text'=>array('text'),'textarea'=>array('text'),'wysiwyg'=>array('text','html'),'code'=>array('text','html'),
   'url'=>array('url','text'),'media'=>array('image'),'number'=>array('number'),'slider'=>array('number'),'color'=>array('color'),
  );
  $content_keys=array('text','title','content','html','url','link','image_url','image_id','alt','caption','quote','author','excerpt','description','address','shortcode','prefix','suffix','role','name','bio');
  if($dynamic===false || $dynamic===0 || $dynamic==='0')return false;
  $cats=array();
  if(is_array($dynamic)){
   if(array_key_exists('active',$dynamic)&&empty($dynamic['active']))return false;
   $raw=$dynamic['categories']??$dynamic;
   if(is_array($raw)){
    foreach($raw as $c){
     $c=sanitize_key((string)$c);
     if($c!=='')$cats[]=$c;
    }
   }
  }
  if(!$cats)$cats=$by_type[$type]??array();
  if(!$cats)return false;
  if($dynamic===null && !in_array($key,$content_keys,true))return false;
  if($dynamic===null && !isset($by_type[$type]))return false;
  $allowed=array('text','url','image','number','color','html');
  $cats=array_values(array_unique(array_filter($cats,function($c)use($allowed){return in_array($c,$allowed,true);})));
  return $cats?array('active'=>true,'categories'=>$cats):false;
 }
 /** "some_key" → "Some Key". */
 public static function humanize($key){return ucwords(str_replace('_',' ',(string)$key));}
 /** Evaluate a schema `condition` against a settings array. Keys ending in `!` mean "not equal"; array values mean "one of". */
 public static function condition_met($cond,array $s){
  if(!is_array($cond)||!$cond)return true;
  foreach($cond as $k=>$want){
   $neg=substr((string)$k,-1)==='!'; $key=$neg?substr((string)$k,0,-1):(string)$k;
   $have=$s[$key]??null; if(is_array($have))$have=$have['desktop']??reset($have);
   if(is_bool($have))$have=$have?'1':'';
   $have=(string)$have;
   $set=is_array($want)?$want:[$want];
   $hit=false; foreach($set as $w){ if(is_bool($w))$w=$w?'1':''; if((string)$w===$have){$hit=true;break;} }
   if($neg?$hit:!$hit)return false;
  }
  return true;
 }
 /**
  * Controls every unit shares (Advanced tab + hidden compatibility keys). Units override any of
  * these by declaring the same key in controls().
  */
 public static function shared_controls(){
  static $c=null; if($c!==null)return $c;
  $adv=function($type,$label,$section,array $extra=[])use(&$c){return array_merge(['type'=>$type,'label'=>$label,'section'=>$section,'tab'=>'advanced'],$extra);};
  $sty=function($type,$label,$section,array $extra=[]){return array_merge(['type'=>$type,'label'=>$label,'section'=>$section,'tab'=>'style'],$extra);};
  $hidden=function($type,array $extra=[]){return array_merge(['type'=>$type,'hidden'=>true,'tab'=>'advanced'],$extra);};
  $len=['px','%','em','rem','vw','vh'];
  $c=[
   // Layout
   'width'=>$adv('slider',__('Width', 'canvasly-lite'),__('Layout', 'canvasly-lite'),['responsive'=>true,'units'=>['%','px','vw','em','rem'],'range'=>['min'=>0,'max'=>1000],'selectors'=>['{{WRAPPER}}'=>'--lb-el-w: {{VALUE}}; width: {{VALUE}};']]),
   'max_width'=>$adv('slider',__('Max Width', 'canvasly-lite'),__('Layout', 'canvasly-lite'),['responsive'=>true,'units'=>['px','%','vw','em','rem'],'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}}'=>'--lb-el-max-w: {{VALUE}}; max-width: {{VALUE}};']]),
   'height'=>$adv('slider',__('Height', 'canvasly-lite'),__('Layout', 'canvasly-lite'),['responsive'=>true,'units'=>['px','%','vh','em','rem','auto'],'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}}'=>'--lb-el-h: {{VALUE}}; height: {{VALUE}};']]),
   'min_height'=>$adv('slider',__('Min Height', 'canvasly-lite'),__('Layout', 'canvasly-lite'),['responsive'=>true,'units'=>['px','%','vh','em','rem'],'range'=>['min'=>0,'max'=>2000],'selectors'=>['{{WRAPPER}}'=>'--lb-el-min-h: {{VALUE}}; min-height: {{VALUE}};']]),
   // Spacing
   'margin'=>$adv('dimensions',__('Margin', 'canvasly-lite'),__('Spacing', 'canvasly-lite')),
   'padding'=>$adv('dimensions',__('Padding', 'canvasly-lite'),__('Spacing', 'canvasly-lite')),
   // Position
   'position'=>$adv('select',__('Position', 'canvasly-lite'),__('Position', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'relative'=>__('Relative', 'canvasly-lite'),'absolute'=>__('Absolute', 'canvasly-lite'),'fixed'=>__('Fixed', 'canvasly-lite'),'sticky'=>__('Sticky', 'canvasly-lite')]]),
   'top'=>$adv('slider',__('Top', 'canvasly-lite'),__('Position', 'canvasly-lite'),['units'=>['px','%','em','vh'],'range'=>['min'=>-400,'max'=>400],'condition'=>['position'=>['absolute','fixed','sticky']]]),
   'right'=>$adv('slider',__('Right', 'canvasly-lite'),__('Position', 'canvasly-lite'),['units'=>['px','%','em','vw'],'range'=>['min'=>-400,'max'=>400],'condition'=>['position'=>['absolute','fixed','sticky']]]),
   'bottom'=>$adv('slider',__('Bottom', 'canvasly-lite'),__('Position', 'canvasly-lite'),['units'=>['px','%','em','vh'],'range'=>['min'=>-400,'max'=>400],'condition'=>['position'=>['absolute','fixed','sticky']]]),
   'left'=>$adv('slider',__('Left', 'canvasly-lite'),__('Position', 'canvasly-lite'),['units'=>['px','%','em','vw'],'range'=>['min'=>-400,'max'=>400],'condition'=>['position'=>['absolute','fixed','sticky']]]),
   'z_index'=>$adv('number',__('Z-Index', 'canvasly-lite'),__('Position', 'canvasly-lite'),['range'=>['min'=>0,'max'=>9999,'step'=>1]]),
   'overflow'=>$adv('select',__('Overflow', 'canvasly-lite'),__('Position', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'visible'=>__('Visible', 'canvasly-lite'),'hidden'=>__('Hidden', 'canvasly-lite'),'auto'=>__('Auto', 'canvasly-lite'),'scroll'=>__('Scroll', 'canvasly-lite')]]),
   // Flex / grid item
   'display'=>$adv('select',__('Display', 'canvasly-lite'),__('Flex Item', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'block'=>__('Block', 'canvasly-lite'),'inline-block'=>__('Inline Block', 'canvasly-lite'),'flex'=>__('Flex', 'canvasly-lite'),'grid'=>__('Grid', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite')]]),
   'visibility'=>$adv('select',__('Visibility', 'canvasly-lite'),__('Flex Item', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'visible'=>__('Visible', 'canvasly-lite'),'hidden'=>__('Hidden', 'canvasly-lite')]]),
   'order'=>$adv('number',__('Order', 'canvasly-lite'),__('Flex Item', 'canvasly-lite')),
   'flex_grow'=>$adv('number',__('Flex Grow', 'canvasly-lite'),__('Flex Item', 'canvasly-lite'),['range'=>['min'=>0,'max'=>10,'step'=>1]]),
   'flex_shrink'=>$adv('number',__('Flex Shrink', 'canvasly-lite'),__('Flex Item', 'canvasly-lite'),['range'=>['min'=>0,'max'=>10,'step'=>1]]),
   'flex_basis'=>$adv('text',__('Flex Basis', 'canvasly-lite'),__('Flex Item', 'canvasly-lite'),['placeholder'=>__('auto', 'canvasly-lite')]),
   'align_self'=>$adv('select',__('Align Self', 'canvasly-lite'),__('Flex Item', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'auto'=>__('Auto', 'canvasly-lite'),'stretch'=>__('Stretch', 'canvasly-lite'),'flex-start'=>__('Start', 'canvasly-lite'),'center'=>__('Center', 'canvasly-lite'),'flex-end'=>__('End', 'canvasly-lite'),'baseline'=>__('Baseline', 'canvasly-lite')]]),
   'justify_self'=>$adv('select',__('Justify Self', 'canvasly-lite'),__('Flex Item', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'auto'=>__('Auto', 'canvasly-lite'),'stretch'=>__('Stretch', 'canvasly-lite'),'start'=>__('Start', 'canvasly-lite'),'center'=>__('Center', 'canvasly-lite'),'end'=>__('End', 'canvasly-lite')]]),
   'grid_column_start'=>$adv('number',__('Column Start', 'canvasly-lite'),__('Grid Item', 'canvasly-lite'),['range'=>['min'=>1,'max'=>24,'step'=>1]]),
   'grid_column_span'=>$adv('number',__('Column Span', 'canvasly-lite'),__('Grid Item', 'canvasly-lite'),['range'=>['min'=>1,'max'=>24,'step'=>1]]),
   'grid_row_start'=>$adv('number',__('Row Start', 'canvasly-lite'),__('Grid Item', 'canvasly-lite'),['range'=>['min'=>1,'max'=>24,'step'=>1]]),
   'grid_row_span'=>$adv('number',__('Row Span', 'canvasly-lite'),__('Grid Item', 'canvasly-lite'),['range'=>['min'=>1,'max'=>24,'step'=>1]]),
   // Responsive visibility
   'hide_desktop'=>$adv('switch',__('Hide On Desktop', 'canvasly-lite'),__('Responsive', 'canvasly-lite')),
   'hide_laptop'=>$adv('switch',__('Hide On Laptop', 'canvasly-lite'),__('Responsive', 'canvasly-lite')),
   'hide_tablet_extra'=>$adv('switch',__('Hide On Tablet Extra', 'canvasly-lite'),__('Responsive', 'canvasly-lite')),
   'hide_tablet'=>$adv('switch',__('Hide On Tablet', 'canvasly-lite'),__('Responsive', 'canvasly-lite')),
   'hide_mobile_extra'=>$adv('switch',__('Hide On Mobile Extra', 'canvasly-lite'),__('Responsive', 'canvasly-lite')),
   'hide_mobile'=>$adv('switch',__('Hide On Mobile', 'canvasly-lite'),__('Responsive', 'canvasly-lite')),
   'hide_widescreen'=>$adv('switch',__('Hide On Widescreen', 'canvasly-lite'),__('Responsive', 'canvasly-lite')),
   // Motion (legacy settings; Interactions 2.0 lives on node.interactions[])
   'interaction'=>$adv('select',__('Entrance Animation', 'canvasly-lite'),__('Motion Effects', 'canvasly-lite'),['hidden'=>true,'options'=>[''=>__('None', 'canvasly-lite'),'fade'=>__('Fade', 'canvasly-lite'),'slide-up'=>__('Slide Up', 'canvasly-lite'),'scale'=>__('Scale', 'canvasly-lite')]]),
   'interaction_trigger'=>$adv('select',__('Trigger', 'canvasly-lite'),__('Motion Effects', 'canvasly-lite'),['hidden'=>true,'options'=>['viewport'=>__('In Viewport', 'canvasly-lite'),'load'=>__('On Load', 'canvasly-lite'),'hover'=>__('Hover', 'canvasly-lite'),'click'=>__('Click', 'canvasly-lite'),'scroll'=>__('Scroll Progress', 'canvasly-lite'),'focus'=>__('Focus', 'canvasly-lite')]]),
   'interaction_duration'=>$adv('number',__('Duration (s)', 'canvasly-lite'),__('Motion Effects', 'canvasly-lite'),['hidden'=>true,'range'=>['min'=>0,'max'=>30,'step'=>0.05]]),
   'interaction_delay'=>$adv('number',__('Delay (s)', 'canvasly-lite'),__('Motion Effects', 'canvasly-lite'),['hidden'=>true,'range'=>['min'=>0,'max'=>30,'step'=>0.05]]),
   'interaction_easing'=>$adv('select',__('Easing', 'canvasly-lite'),__('Motion Effects', 'canvasly-lite'),['hidden'=>true,'options'=>['ease'=>__('Ease', 'canvasly-lite'),'ease-in'=>__('Ease In', 'canvasly-lite'),'ease-out'=>__('Ease Out', 'canvasly-lite'),'ease-in-out'=>__('Ease In Out', 'canvasly-lite'),'linear'=>__('Linear', 'canvasly-lite')]]),
   'interaction_threshold'=>$adv('number',__('Viewport Threshold', 'canvasly-lite'),__('Motion Effects', 'canvasly-lite'),['hidden'=>true,'range'=>['min'=>0,'max'=>1,'step'=>0.05]]),
   'interaction_repeat'=>$adv('switch',__('Repeat', 'canvasly-lite'),__('Motion Effects', 'canvasly-lite'),['hidden'=>true]),
   // Effects
   'opacity'=>$adv('slider',__('Opacity', 'canvasly-lite'),__('Effects', 'canvasly-lite'),['units'=>[],'range'=>['min'=>0,'max'=>1,'step'=>0.05]]),
   'transform'=>$adv('transform',__('Transform', 'canvasly-lite'),__('Effects', 'canvasly-lite')),
   'transition'=>$adv('transition',__('Transition', 'canvasly-lite'),__('Effects', 'canvasly-lite')),
   'filter'=>$adv('css_filter',__('CSS Filter', 'canvasly-lite'),__('Effects', 'canvasly-lite')),
   'mix_blend_mode'=>$adv('select',__('Blend Mode', 'canvasly-lite'),__('Effects', 'canvasly-lite'),['options'=>[''=>__('Normal', 'canvasly-lite'),'multiply'=>__('Multiply', 'canvasly-lite'),'screen'=>__('Screen', 'canvasly-lite'),'overlay'=>__('Overlay', 'canvasly-lite'),'darken'=>__('Darken', 'canvasly-lite'),'lighten'=>__('Lighten', 'canvasly-lite'),'color-dodge'=>__('Color Dodge', 'canvasly-lite'),'color-burn'=>__('Color Burn', 'canvasly-lite'),'hard-light'=>__('Hard Light', 'canvasly-lite'),'soft-light'=>__('Soft Light', 'canvasly-lite'),'difference'=>__('Difference', 'canvasly-lite'),'exclusion'=>__('Exclusion', 'canvasly-lite')]]),
   'mask_shape'=>$adv('select',__('Mask', 'canvasly-lite'),__('Effects', 'canvasly-lite'),['options'=>[''=>__('None', 'canvasly-lite'),'circle'=>__('Circle', 'canvasly-lite'),'ellipse'=>__('Ellipse', 'canvasly-lite'),'hexagon'=>__('Hexagon', 'canvasly-lite'),'triangle'=>__('Triangle', 'canvasly-lite'),'diamond'=>__('Diamond', 'canvasly-lite'),'pill'=>__('Pill', 'canvasly-lite')]]),
   'cursor'=>$adv('select',__('Cursor', 'canvasly-lite'),__('Effects', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'default'=>__('Arrow', 'canvasly-lite'),'pointer'=>__('Pointer', 'canvasly-lite'),'move'=>__('Move', 'canvasly-lite'),'text'=>__('Text', 'canvasly-lite'),'not-allowed'=>__('Not Allowed', 'canvasly-lite')]]),
   // Background
   'background'=>$adv('background',__('Background', 'canvasly-lite'),__('Background', 'canvasly-lite')),
   'background_image'=>$hidden('url'),
   'background_size'=>$hidden('select',['options'=>['','cover','contain','auto']]),
   'background_position'=>$hidden('text'),
   'background_repeat'=>$hidden('select',['options'=>['','no-repeat','repeat','repeat-x','repeat-y']]),
   'background_overlay'=>$hidden('color'),
   'background_gradient'=>$hidden('text'),
   'background_clip'=>$adv('select',__('Background Clip', 'canvasly-lite'),__('Background', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'border-box'=>__('Border Box', 'canvasly-lite'),'padding-box'=>__('Padding Box', 'canvasly-lite'),'text'=>__('Text', 'canvasly-lite')]]),
   // Border
   'border_style'=>$adv('select',__('Border Style', 'canvasly-lite'),__('Border', 'canvasly-lite'),['options'=>[''=>__('None', 'canvasly-lite'),'solid'=>__('Solid', 'canvasly-lite'),'dashed'=>__('Dashed', 'canvasly-lite'),'dotted'=>__('Dotted', 'canvasly-lite'),'double'=>__('Double', 'canvasly-lite')]]),
   'border_width'=>$adv('dimensions',__('Border Width', 'canvasly-lite'),__('Border', 'canvasly-lite'),['condition'=>['border_style!'=>'']]),
   'border_color'=>$adv('color',__('Border Color', 'canvasly-lite'),__('Border', 'canvasly-lite'),['condition'=>['border_style!'=>'']]),
   'border_radius'=>$adv('dimensions',__('Border Radius', 'canvasly-lite'),__('Border', 'canvasly-lite')),
   'button_radius'=>$adv('slider',__('Button Radius', 'canvasly-lite'),__('Border', 'canvasly-lite'),['units'=>['px','%'],'range'=>['min'=>0,'max'=>80],'selectors'=>['{{WRAPPER}}'=>'--lb-btn-radius: {{SIZE}}{{UNIT}};',self::button_selector()=>'border-radius: {{SIZE}}{{UNIT}};']]),
   'shadow'=>$adv('box_shadow',__('Box Shadow', 'canvasly-lite'),__('Border', 'canvasly-lite')),
   // Attributes
   'css_id'=>$adv('text',__('CSS ID', 'canvasly-lite'),__('Attributes', 'canvasly-lite'),['description'=>__('Same-page jump target. Link a menu item, button, or text link to #this-id (for example #contact-us). Works the same way as a Menu Anchor.', 'canvasly-lite'),'placeholder'=>'contact-us']),
   'css_class'=>$adv('text',__('CSS Classes', 'canvasly-lite'),__('Attributes', 'canvasly-lite')),
   'global_class'=>$adv('text',__('Global Classes', 'canvasly-lite'),__('Attributes', 'canvasly-lite'),['description'=>__('Space separated class names from the Global Classes manager.', 'canvasly-lite')]),
   'aria_label'=>$adv('text',__('ARIA Label', 'canvasly-lite'),__('Attributes', 'canvasly-lite')),
   'role'=>$adv('text',__('Role', 'canvasly-lite'),__('Attributes', 'canvasly-lite')),
   'html_attributes'=>$adv('textarea',__('Custom Attributes', 'canvasly-lite'),__('Attributes', 'canvasly-lite'),['placeholder'=>"title=Example\ndata-key=value",'description'=>__('One attribute per line, as name=value. Only aria-*, data-*, title, rel and download are kept.', 'canvasly-lite')]),
   'custom_css'=>$adv('code',__('Custom CSS', 'canvasly-lite'),__('Custom CSS', 'canvasly-lite'),['language'=>'css','rows'=>10,'description'=>__('Rules are scoped to this unit. Use "selector" to target the wrapper.', 'canvasly-lite')]),
   // Typography on Style. A unit that declares the same key keeps its own tab, section and selectors.
   'color'=>$sty('color',__('Text Color', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['selectors'=>['{{WRAPPER}}'=>'color: {{VALUE}};']]),
   'font_family'=>$sty('font',__('Font Family', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['selectors'=>['{{WRAPPER}}'=>'font-family: {{VALUE}};']]),
   'font_size'=>$sty('slider',__('Font Size', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['responsive'=>true,'units'=>['px','em','rem'],'range'=>['min'=>6,'max'=>200],'selectors'=>['{{WRAPPER}}'=>'font-size: {{SIZE}}{{UNIT}};']]),
   'font_weight'=>$sty('select',__('Weight', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['options'=>self::opt_weight(),'selectors'=>['{{WRAPPER}}'=>'font-weight: {{VALUE}};']]),
   'font_style'=>$sty('select',__('Style', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'normal'=>__('Normal', 'canvasly-lite'),'italic'=>__('Italic', 'canvasly-lite'),'oblique'=>__('Oblique', 'canvasly-lite')],'selectors'=>['{{WRAPPER}}'=>'font-style: {{VALUE}};']]),
   'text_transform'=>$sty('select',__('Transform', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite'),'uppercase'=>__('Uppercase', 'canvasly-lite'),'lowercase'=>__('Lowercase', 'canvasly-lite'),'capitalize'=>__('Capitalize', 'canvasly-lite')],'selectors'=>['{{WRAPPER}}'=>'text-transform: {{VALUE}};']]),
   'text_decoration'=>$sty('select',__('Decoration', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['options'=>[''=>__('Default', 'canvasly-lite'),'none'=>__('None', 'canvasly-lite'),'underline'=>__('Underline', 'canvasly-lite'),'overline'=>__('Overline', 'canvasly-lite'),'line-through'=>__('Line Through', 'canvasly-lite')],'selectors'=>['{{WRAPPER}}'=>'text-decoration: {{VALUE}};']]),
   'line_height'=>$sty('slider',__('Line Height', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['responsive'=>true,'units'=>[],'range'=>['min'=>0.6,'max'=>3,'step'=>0.05],'selectors'=>['{{WRAPPER}}'=>'line-height: {{SIZE}};']]),
   'letter_spacing'=>$sty('slider',__('Letter Spacing', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['responsive'=>true,'units'=>['px','em'],'range'=>['min'=>-5,'max'=>20,'step'=>0.1],'selectors'=>['{{WRAPPER}}'=>'letter-spacing: {{SIZE}}{{UNIT}};']]),
   'text_shadow'=>$sty('text_shadow',__('Text Shadow', 'canvasly-lite'),__('Typography', 'canvasly-lite'),['selectors'=>['{{WRAPPER}}'=>'text-shadow: {{VALUE}};']]),
   // Hidden compatibility keys (accepted by the sanitizer, not shown in the schema panel).
   'class_mode'=>$hidden('select',['options'=>['inline','class-first']]),'variable_ref'=>$hidden('text'),'typography_global'=>$hidden('text'),
   'border_top_width'=>$hidden('number'),'border_right_width'=>$hidden('number'),'border_bottom_width'=>$hidden('number'),'border_left_width'=>$hidden('number'),'border_top_color'=>$hidden('color'),'border_right_color'=>$hidden('color'),'border_bottom_color'=>$hidden('color'),'border_left_color'=>$hidden('color'),'border_top_style'=>$hidden('select'),'border_right_style'=>$hidden('select'),'border_bottom_style'=>$hidden('select'),'border_left_style'=>$hidden('select'),'box_shadow'=>$hidden('box_shadow'),
   'object_fit'=>$hidden('select'),'object_position'=>$hidden('text'),'dynamic_source'=>$hidden('select'),'dynamic_key'=>$hidden('text'),'dynamic_meta_key'=>$hidden('text'),
  ];
  return $c;
 }
 /** Parse a multi-line "a|b|c" list control into trimmed row arrays. Empty lines are skipped. */
 protected function rows($text){
  $out=[];
  foreach(preg_split('/\r?\n/',(string)$text) as $line){
   if(trim($line)==='')continue;
   $out[]=array_map('trim',explode('|',$line));
  }
  return $out;
 }
 /**
  * Repeater items from an array (schema 2.2) or a legacy pipe-delimited string.
  * `$columns` maps column index to field name, e.g. ['title','content'].
  */
 protected function repeater_items($value,array $columns=[]){
  if(is_array($value)){
   $out=[];
   foreach($value as $row){
    if(is_array($row))$out[]=$row;
    elseif(is_string($row)&&trim($row)!==''&&$columns)$out[]=$this->pipe_item($row,$columns);
   }
   return $out;
  }
  $out=[];
  foreach($this->rows($value) as $row){
   $item=[];
   foreach(array_values($columns) as $i=>$key)$item[$key]=$row[$i]??'';
   $out[]=$item;
  }
  return $out;
 }
 /** One pipe-delimited line → associative item using `$columns` field names. */
 protected function pipe_item($line,array $columns){
  $parts=array_map('trim',explode('|',(string)$line));
  $item=[];
  foreach(array_values($columns) as $i=>$key)$item[$key]=$parts[$i]??'';
  return $item;
 }
 /** Whitelist an HTML tag name. */
 protected function tag($value,$allowed,$fallback){
  $value=strtolower(trim((string)$value));
  return in_array($value,$allowed,true)?$value:$fallback;
 }
 /** Heading-like tag list shared by widgets that expose a title tag. */
 protected function title_tags(){return ['h1','h2','h3','h4','h5','h6','div','span','p'];}
 /** Hover animation class from the shared `hover_animation` control. */
 protected function hover_class($s){
  $h=sanitize_html_class((string)($s['hover_animation']??''));
  return $h!==''?' lb-hover-'.$h:'';
 }
 /** Build a `style` attribute from a property=>value map, skipping empty values. */
 protected function style_attr(array $props){
  $parts=[];
  foreach($props as $p=>$v){ if($v===''||$v===null||is_array($v))continue; $parts[]=$p.':'.esc_attr((string)$v); }
  return $parts?' style="'.implode(';',$parts).'"':'';
 }
 /** Desktop value of a responsive breakpoint map, or the plain value. */
 protected function scalar($v,$fallback=''){
  if(is_array($v))$v=$v['desktop']??$fallback;
  return ($v===null||$v==='')?$fallback:$v;
 }
 /** Numeric value with unit, or raw string when the user typed one. */
 protected function unit($v,$unit='px',$fallback=''){
  $v=$this->scalar($v);
  if($v===''||$v===null||is_array($v))return $fallback;
  return is_numeric($v)?((float)$v).$unit:sanitize_text_field((string)$v);
 }
 /**
  * CSS variables for the shared icon view system (default / stacked / framed + shape).
  * Used by Icon, Icon Box, Divider and Text drop caps so they style identically.
  */
 protected function icon_vars($s,$sizeKey='icon_size',$colorKey='icon_color'){
  return [
   '--lb-icon-size'=>$this->unit($s[$sizeKey]??''),
   '--lb-icon-primary'=>$s[$colorKey]??'',
   '--lb-icon-secondary'=>$s['secondary_color']??'',
   '--lb-icon-hover-primary'=>$s['hover_color']??'',
   '--lb-icon-hover-secondary'=>$s['hover_secondary_color']??'',
   '--lb-icon-padding'=>$this->unit($s['icon_padding']??''),
   '--lb-icon-border'=>$this->unit($s['icon_border_width']??''),
   '--lb-icon-radius'=>$this->unit($s['icon_radius']??''),
   '--lb-icon-rotate'=>$this->unit($s['rotate']??'','deg'),
  ];
 }
 /** Resolve a media picker pair (id + url) to a URL for the requested size. */
 protected function media_url($id,$url,$size='full'){
  $id=absint($id);
  if($id){ $u=wp_get_attachment_image_url($id,$size?:'full'); if($u)return $u; }
  return esc_url_raw((string)$url);
 }
 protected function cls($s){
  $c='lb-unit';
  foreach(preg_split('/\s+/',trim((string)($s['css_class']??''))) as $part){$safe=sanitize_html_class($part);if($safe)$c.=' '.$safe;}
  foreach(preg_split('/[\s,]+/',trim((string)($s['global_class']??''))) as $part){$safe=sanitize_html_class($part);if($safe)$c.=' lb-class-'.$safe;}
  return $c;
 }
 /**
  * Href for a menu item, button, or text link.
  * Fragment-only values such as `#contact-us` stay intact. `esc_url()` drops those.
  *
  * @param mixed $url
  * @return string
  */
 public static function link_href($url){
  $url=trim((string)$url);
  if($url===''||$url==='#')return '#';
  if(preg_match('/^\s*(javascript|vbscript|data)\s*:/i',$url))return '';
  if(isset($url[0])&&$url[0]==='#'){
   $id=MenuAnchor::slug(substr($url,1));
   return $id!==''?'#'.$id:'#';
  }
  $safe=esc_url($url);
  if($safe!=='')return $safe;
  if(preg_match('/#([A-Za-z0-9][A-Za-z0-9_-]*)\s*$/',$url,$m)&&!preg_match('/^[a-z][a-z0-9+.-]*:/i',$url)){
   $id=MenuAnchor::slug($m[1]);
   return $id!==''?'#'.$id:'#';
  }
  return '';
 }
 /**
  * Invisible jump target for a section CSS ID. The wrapper already uses `lb-node-{id}`.
  *
  * @param mixed $css_id
  * @return string
  */
 public static function jump_target($css_id){
  $id=MenuAnchor::slug($css_id);
  if($id==='')return '';
  return '<span id="'.esc_attr($id).'" class="lb-menu-anchor"></span>';
 }
 public function attrs($s){
  $a='';
  if(!empty($s['aria_label']))$a.=' aria-label="'.esc_attr($s['aria_label']).'"';
  if(!empty($s['role']))$a.=' role="'.esc_attr(sanitize_key($s['role'])).'"';
  if(!empty($s['html_attributes'])){ foreach(preg_split('/\r?\n/',(string)$s['html_attributes']) as $row){$p=explode('=',trim($row),2);if(count($p)!==2)continue;$name=sanitize_key($p[0]);if(!preg_match('/^(aria-[a-z0-9_-]+|data-[a-z0-9_-]+|title|rel|download)$/i',$name))continue;$a.=' '.esc_attr($name).'="'.esc_attr(trim($p[1]," \"'")).'"';}}
  return $a;
 }
}
