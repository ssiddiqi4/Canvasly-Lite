<?php
namespace CanvaslyLite\Design;
if(!defined('ABSPATH')) exit;
class Accessibility {
 public static function warnings($node){$w=[];$type=$node['type']??'';$s=$node['settings']??[];if($type==='image'&&empty($s['alt']))$w[]='Image is missing alternative text.';if($type==='button'&&empty(trim((string)($s['text']??''))))$w[]='Button text should describe the action.';if($type==='heading'&&!in_array($s['tag']??'h2',['h1','h2','h3','h4','h5','h6'],true))$w[]='Use a semantic heading level.';if($type==='link_in_bio'&&empty($s['title']))$w[]='Add a descriptive title.';return $w;}
}
