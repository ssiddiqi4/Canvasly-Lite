<?php
namespace CanvaslyLite\Units;
if(!defined('ABSPATH')) exit;
/** Legacy compatibility structure. New documents should prefer Container. */
class InnerSection extends Container {
 public function type(){return 'inner_section';}
 public function title(){return __('Inner Section', 'canvasly-lite');}
 public function icon(){return '▤';}
 public function category(){return 'layout';}
 public function defaults(){return ['layout'=>'flex','direction'=>'row','wrap'=>'nowrap','justify'=>'flex-start','align'=>'stretch','gap'=>16,'columns'=>2,'width'=>'100%','min_height'=>'','max_width'=>'','background'=>'','padding'=>[],'margin'=>[]];}
}
