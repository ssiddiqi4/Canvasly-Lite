<?php
namespace CanvaslyLite\Units; if(!defined('ABSPATH')) exit;
class PriceTable extends Unit {
 public function type(){return 'price_table';} public function title(){return __('Price Table', 'canvasly-lite');} public function icon(){return '$';} public function category(){return 'basic';}
 public function uses_button(){return true;}
 public function keywords(){return ['price','pricing','table','plan','features'];}
 public function defaults(){return [
  'title'=>'Professional','price'=>'49','period'=>'/month',
  'features'=>[
   ['_id'=>'pf1','text'=>'Feature one','icon'=>''],
   ['_id'=>'pf2','text'=>'Feature two','icon'=>''],
   ['_id'=>'pf3','text'=>'Feature three','icon'=>''],
  ],
  'button'=>'Get Started','url'=>'#',
  'align'=>'center',
  'button_background'=>'#222222','button_text_color'=>'#ffffff',
 ];}
 public function controls(){
  $plan=__('Price Table', 'canvasly-lite');
  $header=__('Header', 'canvasly-lite');
  $pricing=__('Pricing', 'canvasly-lite');
  $features=__('Features', 'canvasly-lite');
  $button=__('Button', 'canvasly-lite');
  $hover=__('Button Hover', 'canvasly-lite');
  $box=__('Box', 'canvasly-lite');
  $root='{{WRAPPER}} .lb-price-table';
  $title=$root.' h3';
  $amount=$root.' .lb-price strong';
  $period=$root.' .lb-price span';
  $list=$root.' li';
  $icon=$root.' .lb-price-feature-icon';
  $btn=$root.'>a';
  return [
   'title'=>$this->ctrl('text',__('Title', 'canvasly-lite'),'content',$plan),
   'price'=>$this->ctrl('text',__('Price', 'canvasly-lite'),'content',$plan),
   'period'=>$this->ctrl('text',__('Period', 'canvasly-lite'),'content',$plan),
   'features'=>$this->ctrl('repeater',__('Features', 'canvasly-lite'),'content',$plan,[
    'title_field'=>'{{text}}',
    'fields'=>[
     'text'=>$this->field('text',__('Text', 'canvasly-lite')),
     'icon'=>$this->field('icon',__('Icon', 'canvasly-lite')),
    ],
   ]),
   'button'=>$this->ctrl('text',__('Button Text', 'canvasly-lite'),'content',$plan),
   'url'=>$this->ctrl('url',__('Button Link', 'canvasly-lite'),'content',$plan),
   'align'=>$this->ctrl('choose',__('Alignment', 'canvasly-lite'),'style',$box,['options'=>self::opt_align(),'selectors'=>[$root=>'text-align: {{VALUE}};']]),
   'table_background'=>$this->ctrl('color',__('Background', 'canvasly-lite'),'style',$box,['selectors'=>[$root=>'background: {{VALUE}};']]),
   'title_color'=>$this->ctrl('color',__('Title Color', 'canvasly-lite'),'style',$header,['selectors'=>[$title=>'color: {{VALUE}};']]),
   'title_typography'=>$this->ctrl('typography',__('Title Typography', 'canvasly-lite'),'style',$header,['selectors'=>[$title=>'{{VALUE}}']]),
   'price_color'=>$this->ctrl('color',__('Price Color', 'canvasly-lite'),'style',$pricing,['selectors'=>[$amount=>'color: {{VALUE}};']]),
   'price_typography'=>$this->ctrl('typography',__('Price Typography', 'canvasly-lite'),'style',$pricing,['selectors'=>[$amount=>'{{VALUE}}']]),
   'period_color'=>$this->ctrl('color',__('Period Color', 'canvasly-lite'),'style',$pricing,['selectors'=>[$period=>'color: {{VALUE}};']]),
   'period_typography'=>$this->ctrl('typography',__('Period Typography', 'canvasly-lite'),'style',$pricing,['selectors'=>[$period=>'{{VALUE}}']]),
   'feature_color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$features,['selectors'=>[$list=>'color: {{VALUE}};']]),
   'feature_typography'=>$this->ctrl('typography',__('Typography', 'canvasly-lite'),'style',$features,['selectors'=>[$list=>'{{VALUE}}']]),
   'feature_icon_color'=>$this->ctrl('color',__('Icon Color', 'canvasly-lite'),'style',$features,['selectors'=>[$icon=>'color: {{VALUE}};']]),
   'button_background'=>$this->ctrl('color',__('Background', 'canvasly-lite'),'style',$button,['default'=>'#222222','selectors'=>[$btn=>'background: {{VALUE}};',$root=>'--lb-price-btn: {{VALUE}};']]),
   'button_text_color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$button,['default'=>'#ffffff','selectors'=>[$btn=>'color: {{VALUE}};',$root=>'--lb-price-btn-text: {{VALUE}};']]),
   'button_typography'=>$this->ctrl('typography',__('Typography', 'canvasly-lite'),'style',$button,['selectors'=>[$btn=>'{{VALUE}}']]),
   'button_padding'=>$this->ctrl('dimensions',__('Padding', 'canvasly-lite'),'style',$button,['selectors'=>[$btn=>'padding: {{VALUE}};']]),
   'button_border_radius'=>$this->ctrl('slider',__('Border Radius', 'canvasly-lite'),'style',$button,['units'=>['px','%'],'range'=>['min'=>0,'max'=>80],'selectors'=>[$btn=>'border-radius: {{SIZE}}{{UNIT}};']]),
   'button_hover_background'=>$this->ctrl('color',__('Background', 'canvasly-lite'),'style',$hover,['selectors'=>[$btn.':hover'=>'background: {{VALUE}};',$root=>'--lb-price-btn-hover: {{VALUE}};']]),
   'button_hover_color'=>$this->ctrl('color',__('Text Color', 'canvasly-lite'),'style',$hover,['selectors'=>[$btn.':hover'=>'color: {{VALUE}};',$root=>'--lb-price-btn-hover-text: {{VALUE}};']]),
  ];
 }
 public function render($s,$children=''){
  $items='';
  foreach($this->repeater_items($s['features']??'',['text','icon']) as $row){
   $text=trim((string)($row['text']??$row[0]??'')); if($text==='')continue;
   $icon=trim((string)($row['icon']??$row[1]??''));
   $glyph=$icon!==''?'<span class="lb-price-feature-icon" aria-hidden="true">'.\CanvaslyLite\Utils\Icons::svg($icon).'</span>':'';
   $items.='<li>'.$glyph.esc_html($text).'</li>';
  }
  $paint=function($v,$fallback){
   $v=trim((string)$v);
   if($v!==''&&preg_match('/^(#[0-9a-f]{3,8}|rgba?\([^)]{1,80}\)|hsla?\([^)]{1,80}\)|var\(--[a-z0-9_-]+\))$/i',$v))return $v;
   return $fallback;
  };
  $bg=$paint($s['button_background']??'','#222222');
  $fg=$paint($s['button_text_color']??'','#ffffff');
  $style='--lb-price-btn:'.esc_attr($bg).';--lb-price-btn-text:'.esc_attr($fg).';';
  return '<div class="'.$this->cls($s).' lb-price-table" style="'.$style.'"><h3>'.esc_html($s['title']??'').'</h3><div class="lb-price"><strong>'.esc_html($s['price']??'').'</strong><span>'.esc_html($s['period']??'').'</span></div><ul>'.$items.'</ul><a href="'.esc_attr(self::link_href($s['url']??'#')).'" style="background:'.esc_attr($bg).';color:'.esc_attr($fg).';">'.esc_html($s['button']??'').'</a></div>';
 }
}
