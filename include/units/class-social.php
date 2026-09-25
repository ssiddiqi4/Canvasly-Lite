<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
/**
 * Social Links: repeater of network / URL / optional icon. Legacy "Network|URL[|icon]"
 * rows are migrated on load.
 */
class Social extends Unit {
 public function type(){return 'social';} public function title(){return __('Social Icons', 'canvasly-lite');} public function icon(){return '⚭';} public function category(){return 'basic';}
 public function keywords(){return ['social','icons','share','facebook','instagram','linkedin','x','youtube','network'];}
 /** network key => [icon id, brand colour] */
 public static function networks(){return [
  'facebook'=>['facebook-f','#1877f2'],'x'=>['x-twitter','#1da1f2'],'twitter'=>['twitter','#1da1f2'],'instagram'=>['instagram','#e4405f'],'linkedin'=>['linkedin-in','#0a66c2'],'youtube'=>['youtube','#ff0000'],
  'tiktok'=>['tiktok','#010101'],'pinterest'=>['pinterest','#e60023'],'github'=>['github','#181717'],'whatsapp'=>['whatsapp','#25d366'],'telegram'=>['telegram','#2aabee'],'googleplus'=>['google-plus','#db4437'],'google-plus'=>['google-plus','#db4437'],'discord'=>['discord','#5865f2'],
  'reddit'=>['reddit','#ff4500'],'snapchat'=>['snapchat','#fffc00'],'spotify'=>['spotify','#1db954'],'twitch'=>['twitch','#9146ff'],'vimeo'=>['vimeo','#1ab7ea'],'dribbble'=>['dribbble','#ea4c89'],
  'behance'=>['behance','#1769ff'],'medium'=>['medium','#000000'],'threads'=>['threads','#000000'],'mastodon'=>['mastodon','#6364ff'],'soundcloud'=>['soundcloud','#ff5500'],'rss'=>['rss','#f26522'],
  'skype'=>['skype','#00aff0'],'slack'=>['slack','#4a154b'],'tumblr'=>['tumblr','#36465d'],'flickr'=>['flickr','#0063dc'],'apple'=>['apple','#000000'],'android'=>['android','#3ddc84'],'yelp'=>['yelp','#d32323'],
  'steam'=>['steam','#000000'],'xing'=>['xing','#006567'],'weibo'=>['weibo','#df2029'],'vk'=>['vk','#0077ff'],'wordpress'=>['wordpress','#21759b'],'email'=>['envelope','#ea4335'],'mail'=>['envelope','#ea4335'],'website'=>['globe','#3f7fdf'],'link'=>['link','#3f7fdf'],
 ];}
 public static function detect($label,$url){
  $key=sanitize_key(str_replace('+','plus',preg_replace('/\s+/','',strtolower((string)$label))));
  $n=self::networks(); if(isset($n[$key]))return [$key,$n[$key][0],$n[$key][1]];
  $host=strtolower((string)wp_parse_url($url,PHP_URL_HOST)); $host=preg_replace('/^www\./','',$host);
  foreach($n as $k=>$v){ if($host!==''&&self::host_has($host,$k))return [$k,$v[0],$v[1]]; }
  if(strpos((string)$url,'mailto:')===0)return ['email','envelope','#ea4335'];
  return ['custom','link','#3f7fdf'];
 }
 private static function host_has($host,$key){
  if($host===$key)return true;
  $dot='.'.$key;
  return strpos($host,$key.'.')===0||substr($host,-strlen($dot))===$dot||strpos($host,$dot.'.')!==false;
 }
 public function defaults(){return [
  'links'=>[
   ['_id'=>'so1','network'=>'Facebook','url'=>'https://facebook.com','icon'=>''],
   ['_id'=>'so2','network'=>'X','url'=>'https://x.com','icon'=>''],
   ['_id'=>'so3','network'=>'Instagram','url'=>'https://instagram.com','icon'=>''],
   ['_id'=>'so4','network'=>'WhatsApp','url'=>'https://wa.me','icon'=>''],
   ['_id'=>'so5','network'=>'YouTube','url'=>'https://youtube.com','icon'=>''],
   ['_id'=>'so6','network'=>'Telegram','url'=>'https://t.me','icon'=>''],
   ['_id'=>'so7','network'=>'TikTok','url'=>'https://tiktok.com','icon'=>''],
   ['_id'=>'so8','network'=>'LinkedIn','url'=>'https://linkedin.com','icon'=>''],
   ['_id'=>'so9','network'=>'Snapchat','url'=>'https://snapchat.com','icon'=>''],
  ],
  'target'=>'_blank','shape'=>'rounded','color_scheme'=>'official','color'=>'#333333','icon_color'=>'#ffffff','hover_color'=>'','hover_icon_color'=>'','size'=>32,'icon_padding'=>'','gap'=>14,'row_gap'=>14,'align'=>'center','columns'=>3,'icon_radius'=>'','hover_animation'=>'',
 ];}
 public function controls(){
  $soc=__('Social Icons', 'canvasly-lite'); $icon=__('Icon', 'canvasly-lite');
  return [
   'links'=>$this->ctrl('repeater',__('Icons', 'canvasly-lite'),'content',$soc,[
    'title_field'=>'{{network}}','prevent_empty'=>true,
    'fields'=>[
     'network'=>$this->field('text',__('Network', 'canvasly-lite')),
     'url'=>$this->field('url',__('URL', 'canvasly-lite')),
     'icon'=>$this->field('icon',__('Icon', 'canvasly-lite')),
    ],
   ]),
   'target'=>$this->ctrl('select',__('Open In', 'canvasly-lite'),'content',$soc,['options'=>self::opt_target()]),
   'shape'=>$this->ctrl('select',__('Shape', 'canvasly-lite'),'content',$soc,['options'=>['square'=>__('Square', 'canvasly-lite'),'rounded'=>__('Rounded', 'canvasly-lite'),'circle'=>__('Circle', 'canvasly-lite')]]),
   'color_scheme'=>$this->ctrl('select',__('Color', 'canvasly-lite'),'content',$soc,['options'=>['official'=>__('Official', 'canvasly-lite'),'custom'=>__('Custom', 'canvasly-lite')]]),
   'align'=>$this->ctrl('select',__('Alignment', 'canvasly-lite'),'content',$soc,['options'=>self::opt_lcr()]),
   'columns'=>$this->ctrl('number',__('Columns', 'canvasly-lite'),'content',$soc,['range'=>['min'=>0,'max'=>12]]),
   'hover_animation'=>$this->ctrl('select',__('Hover Animation', 'canvasly-lite'),'content',$soc,['options'=>self::opt_hover()]),
   'color'=>$this->ctrl('color',__('Background', 'canvasly-lite'),'style',$icon,['condition'=>['color_scheme'=>'custom'],'selectors'=>['{{WRAPPER}} .lb-social'=>'--lb-social-bg: {{VALUE}};']]),
   'icon_color'=>$this->ctrl('color',__('Icon Color', 'canvasly-lite'),'style',$icon,['selectors'=>['{{WRAPPER}} .lb-social'=>'--lb-social-color: {{VALUE}};']]),
   'hover_color'=>$this->ctrl('color',__('Hover Background', 'canvasly-lite'),'style',$icon,['selectors'=>['{{WRAPPER}} .lb-social'=>'--lb-social-hover-bg: {{VALUE}};']]),
   'hover_icon_color'=>$this->ctrl('color',__('Hover Icon Color', 'canvasly-lite'),'style',$icon,['selectors'=>['{{WRAPPER}} .lb-social'=>'--lb-social-hover-color: {{VALUE}};']]),
   'size'=>$this->ctrl('slider',__('Size', 'canvasly-lite'),'style',$icon,['units'=>['px','em','rem'],'range'=>['min'=>6,'max'=>300],'selectors'=>['{{WRAPPER}} .lb-social'=>'--lb-social-size: {{SIZE}}{{UNIT}};']]),
   'icon_padding'=>$this->ctrl('slider',__('Padding', 'canvasly-lite'),'style',$icon,['units'=>['px','em','rem'],'range'=>['min'=>0,'max'=>80],'selectors'=>['{{WRAPPER}} .lb-social'=>'--lb-social-padding: {{SIZE}}{{UNIT}};']]),
   'gap'=>$this->ctrl('slider',__('Spacing', 'canvasly-lite'),'style',$icon,['units'=>['px','em','rem'],'range'=>['min'=>0,'max'=>100],'selectors'=>['{{WRAPPER}} .lb-social'=>'--lb-social-gap: {{SIZE}}{{UNIT}};']]),
   'row_gap'=>$this->ctrl('slider',__('Rows Gap', 'canvasly-lite'),'style',$icon,['units'=>['px','em','rem'],'range'=>['min'=>0,'max'=>100],'selectors'=>['{{WRAPPER}} .lb-social'=>'--lb-social-row-gap: {{SIZE}}{{UNIT}};']]),
   'icon_radius'=>$this->ctrl('slider',__('Border Radius', 'canvasly-lite'),'style',$icon,['units'=>['px','%','em'],'range'=>['min'=>0,'max'=>200],'selectors'=>['{{WRAPPER}} .lb-social'=>'--lb-social-radius: {{SIZE}}{{UNIT}};']]),
  ];
 }
 public function render($s,$children=''){
  $shape=in_array($s['shape']??'rounded',['square','rounded','circle'],true)?$s['shape']:'rounded';
  $scheme=($s['color_scheme']??'official')==='custom'?'custom':'official';
  $align=in_array($s['align']??'left',['left','center','right'],true)?$s['align']:'left';
  $cols=max(0,min(12,absint($s['columns']??0)));
  $vars=$this->style_attr(['--lb-social-size'=>$this->unit($s['size']??''),'--lb-social-padding'=>$this->unit($s['icon_padding']??''),'--lb-social-gap'=>$this->unit($s['gap']??''),'--lb-social-row-gap'=>$this->unit($s['row_gap']??''),'--lb-social-radius'=>$this->unit($s['icon_radius']??''),'--lb-social-bg'=>$scheme==='custom'?($s['color']??''):'','--lb-social-color'=>$s['icon_color']??'','--lb-social-hover-bg'=>$s['hover_color']??'','--lb-social-hover-color'=>$s['hover_icon_color']??'','--lb-social-cols'=>$cols?:'','justify-content'=>$align==='left'?'flex-start':($align==='right'?'flex-end':'center')]);
  $t=($s['target']??'_blank')==='_blank'?' target="_blank" rel="noopener noreferrer"':'';
  $out='<div class="'.$this->cls($s).' lb-social lb-social-'.$shape.' lb-social-'.$scheme.($cols?' lb-social-grid':'').'"'.$vars.'>';
  foreach($this->repeater_items($s['links']??'',['network','url','icon']) as $row){
   $label=(string)($row['network']??$row[0]??''); $url=(string)($row['url']??$row[1]??''); if($url==='')continue;
   [$net,$icon,$brand]=self::detect($label,$url); $custom=(string)($row['icon']??$row[2]??'');
   $glyph=$custom!==''?\CanvaslyLite\Utils\Icons::svg($custom):\CanvaslyLite\Utils\BrandIcons::svg($net);
   if($glyph==='')$glyph=\CanvaslyLite\Utils\Icons::svg($custom!==''?$custom:$icon);
   $brandStyle=$scheme==='official'?' style="--lb-social-brand:'.esc_attr($brand).'"':'';
   $out.='<a class="lb-social-item lb-social-'.sanitize_html_class($net).$this->hover_class($s).'" href="'.esc_url($url).'"'.$t.$brandStyle.' aria-label="'.esc_attr($label!==''?$label:$net).'">'.$glyph.'<span class="lb-sr-only">'.esc_html($label).'</span></a>';
  }
  return $out.'</div>';
 }
}
