<?php
namespace CanvaslyLite\Bootstrap; use CanvaslyLite\Units\UnitRegistry; use CanvaslyLite\Document\DocumentManager; use CanvaslyLite\Document\Documents; if(!defined('ABSPATH')) exit;
class Plugin { private static $instance; public static function instance(){ if(!self::$instance){ self::$instance=new self; self::$instance->init(); } return self::$instance; }
 public static function is_admin_request(){ return !function_exists('is_admin') || is_admin(); }
 public static function init(){
  if(class_exists('CanvaslyLite\\Compatibility\\Compat'))\CanvaslyLite\Compatibility\Compat::init();
  elseif(class_exists('CanvaslyLite\\Compatibility\\ImportExport'))\CanvaslyLite\Compatibility\ImportExport::init();
  if(class_exists('CanvaslyLite\\Compatibility\\Duplicate')&&!class_exists('CanvaslyLite\\Compatibility\\Compat'))\CanvaslyLite\Compatibility\Duplicate::init();
  if(class_exists('CanvaslyLite\\Upgrade\\Upgrades'))\CanvaslyLite\Upgrade\Upgrades::init();
  // Keep the native WordPress Block Editor isolated from Canvasly runtime hooks.
  // Gutenberg owns its React application, REST editor state, and editor assets.
  // Canvasly only adds a safe launcher after the editor has rendered.
  if(self::is_native_block_editor_request()){
   add_action('admin_enqueue_scripts',[self::class,'native_editor_button']);
   return;
  }
  add_action('init',[self::class,'on_init']);
  add_action('rest_api_init',['CanvaslyLite\Api\Rest','register_routes']);
  if(self::is_admin_request()){
   if(class_exists('CanvaslyLite\\Admin\\Dashboard'))\CanvaslyLite\Admin\Dashboard::init();
   add_action('admin_menu',[self::class,'menu']);
   add_action('admin_enqueue_scripts',['CanvaslyLite\Editor\Editor','enqueue'],10,1);
   add_action('admin_enqueue_scripts',['CanvaslyLite\Editor\Editor','enqueue_favicon'],10,1);
   add_action('admin_head',['CanvaslyLite\Editor\Editor','print_favicon']);
   add_filter('post_row_actions',[self::class,'row_action'],10,2);
   add_filter('page_row_actions',[self::class,'row_action'],10,2);
  } else {
   add_action('wp_enqueue_scripts',['CanvaslyLite\Rendering\FrontendRenderer','enqueue']);
   add_filter('the_content',['CanvaslyLite\Rendering\FrontendRenderer','filter_content'],20);
   add_action('wp_enqueue_scripts',[self::class,'frontend_head'],30);
  }
  if(class_exists('CanvaslyLite\\Templates\\PageTemplates'))\CanvaslyLite\Templates\PageTemplates::init();
  if(class_exists('CanvaslyLite\\Templates\\ThemeChrome'))\CanvaslyLite\Templates\ThemeChrome::init();
  if(class_exists('CanvaslyLite\\Templates\\ThemeChromeEdits'))\CanvaslyLite\Templates\ThemeChromeEdits::init();
  if(class_exists('CanvaslyLite\\Theme\\Locations'))\CanvaslyLite\Theme\Locations::init();
  if(class_exists('CanvaslyLite\\Templates\\SavedTemplates'))\CanvaslyLite\Templates\SavedTemplates::init();
  if(class_exists('CanvaslyLite\\Convert\\Tool'))\CanvaslyLite\Convert\Tool::init();
  if(class_exists('CanvaslyLite\\Tools\\ReplaceUrl'))\CanvaslyLite\Tools\ReplaceUrl::init();
  if(class_exists('CanvaslyLite\\Design\\CssPrint'))\CanvaslyLite\Design\CssPrint::init();
  if(class_exists('CanvaslyLite\\Design\\Fonts'))\CanvaslyLite\Design\Fonts::init();
  if(class_exists('CanvaslyLite\\Design\\Optimize'))\CanvaslyLite\Design\Optimize::init();
  if(class_exists('CanvaslyLite\\Embed\\OEmbed'))\CanvaslyLite\Embed\OEmbed::init();
  if(class_exists('CanvaslyLite\\Ops\\Maintenance'))\CanvaslyLite\Ops\Maintenance::init();
  if(class_exists('CanvaslyLite\\Ops\\SafeMode'))\CanvaslyLite\Ops\SafeMode::init();
  if(class_exists('CanvaslyLite\\Ops\\SystemInfo'))\CanvaslyLite\Ops\SystemInfo::init();
  if(class_exists('CanvaslyLite\\Ops\\Rollback'))\CanvaslyLite\Ops\Rollback::init();
  if(class_exists('CanvaslyLite\\Cli\\Cli'))\CanvaslyLite\Cli\Cli::init();
  if(class_exists('CanvaslyLite\\Admin\\AdminBar'))\CanvaslyLite\Admin\AdminBar::init();
  if(class_exists('CanvaslyLite\\Widgets\\TemplateWidget'))add_action('widgets_init',['CanvaslyLite\\Widgets\\TemplateWidget','register']);
  if(class_exists('CanvaslyLite\\Document\\Revisions'))\CanvaslyLite\Document\Revisions::init();
 }
 public static function is_native_block_editor_request(){
  $pagenow=$GLOBALS['pagenow']??'';
  if(!in_array($pagenow,['post.php','post-new.php'],true)) return false;
  // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only WordPress editor screen routing.
  if($pagenow==='post.php'){
   $post_id=isset($_GET['post'])?absint(wp_unslash($_GET['post'])):0; if(!$post_id)return false;
   $post=get_post($post_id); if(!$post || !Documents::supports($post->post_type))return false;
   if(function_exists('use_block_editor_for_post'))return (bool)use_block_editor_for_post($post);
   return true;
  }
  $post_type=isset($_GET['post_type'])?sanitize_key(wp_unslash($_GET['post_type'])):'post';
  // phpcs:enable WordPress.Security.NonceVerification.Recommended
  if(!Documents::supports($post_type))return false;
  if(function_exists('use_block_editor_for_post_type'))return (bool)use_block_editor_for_post_type($post_type);
  return true;
 }
 public static function native_editor_button(){
  if(function_exists('canvasly_lite_native_editor_launcher')) canvasly_lite_native_editor_launcher();
 }
 public static function on_init(){
  self::register_template_cpt();
  self::register_component_cpt();
  self::register_global_class_cpt();
  self::register_units();
  self::init_global_settings();
  self::compatibility_hooks();
 }
 public static function init_global_settings(){
  if(class_exists('CanvaslyLite\\Settings\\GlobalSettings')) \CanvaslyLite\Settings\GlobalSettings::init();
  if(class_exists('CanvaslyLite\\Settings\\AdminSettings')) \CanvaslyLite\Settings\AdminSettings::init();
  if(class_exists('CanvaslyLite\\Settings\\Roles')) \CanvaslyLite\Settings\Roles::init();
  if(class_exists('CanvaslyLite\\Settings\\UnitsManager')) \CanvaslyLite\Settings\UnitsManager::init();
  if(class_exists('CanvaslyLite\\Settings\\KitSettings')) \CanvaslyLite\Settings\KitSettings::init();
  if(class_exists('CanvaslyLite\\Design\\Kit')) \CanvaslyLite\Design\Kit::init();
 }
 public static function menu(){
  $cap=class_exists('CanvaslyLite\\Settings\\Roles')?\CanvaslyLite\Settings\Roles::CAP_EDIT:'edit_posts';
  add_menu_page(__('Canvasly', 'canvasly-lite'),__('Canvasly', 'canvasly-lite'),$cap,'canvasly-lite',['CanvaslyLite\Editor\Editor','screen'],'dashicons-layout',58);
 }
 public static function gutenberg_editor_notice(){
  // Keep the native WordPress block editor completely untouched. Canvasly only adds a small PHP notice/button; it does not enqueue editor assets here.
  $screen=get_current_screen();
  if(!$screen || !Documents::supports($screen->post_type) || !in_array($screen->base,['post','post-new'],true) || !current_user_can(Documents::edit_cap($screen->post_type))) return;
  if(class_exists('CanvaslyLite\\Settings\\Roles')&&!\CanvaslyLite\Settings\Roles::can_edit()) return;
  $post_id=0;
  // phpcs:disable WordPress.Security.NonceVerification -- Read-only post ID from the editor screen URL or the post form.
  if(!empty($_GET['post'])) $post_id=absint(wp_unslash($_GET['post']));
  elseif(!empty($_POST['post_ID'])) $post_id=absint(wp_unslash($_POST['post_ID']));
  // phpcs:enable WordPress.Security.NonceVerification
  if($post_id && !current_user_can('edit_post',$post_id)) return;
  if($post_id){
    $post=get_post($post_id);
    if($post && function_exists('use_block_editor_for_post') && !use_block_editor_for_post($post)) return;
  } elseif(function_exists('use_block_editor_for_post_type') && !use_block_editor_for_post_type($screen->post_type)) {
    return;
  }
  $url=$post_id ? admin_url('admin.php?page=canvasly-lite&post_id='.$post_id) : admin_url('admin.php?page=canvasly-lite&post_type='.rawurlencode($screen->post_type));
  echo '<div class="notice notice-info" style="display:flex;align-items:center;gap:14px;padding:10px 12px;"><strong>'.esc_html__('Canvasly', 'canvasly-lite').'</strong><span>'.esc_html__('You can edit this content with the visual builder.', 'canvasly-lite').'</span><a class="button button-primary" href="'.esc_url($url).'">'.esc_html__('Edit with Canvasly', 'canvasly-lite').'</a></div>';
 }
 public static function register_template_cpt(){
  if(class_exists('\\CanvaslyLite\\Templates\\SavedTemplates')){\CanvaslyLite\Templates\SavedTemplates::register();return;}
  register_post_type('lb_template',['label'=>__('Canvasly Templates', 'canvasly-lite'),'public'=>false,'show_ui'=>false,'supports'=>['title']]);
 }
 public static function register_component_cpt(){register_post_type('lb_component',['label'=>__('Canvasly Components', 'canvasly-lite'),'public'=>false,'show_ui'=>false,'supports'=>['title']]);}
 public static function register_global_class_cpt(){register_post_type('lb_global_class',['label'=>__('Canvasly Global Classes', 'canvasly-lite'),'public'=>false,'show_ui'=>false,'supports'=>['title']]);}
 public static function compatibility_hooks(){
  if(!class_exists('CanvaslyLite\\Design\\Performance'))return;
  if(class_exists('\\CanvaslyLite\\Admin\\AdminContext')&&!\CanvaslyLite\Admin\AdminContext::allows_background())return;
  $manifest=\CanvaslyLite\Design\Performance::manifest();
  $stored=function_exists('get_option')?get_option('canvasly_lite_asset_manifest'):false;
  if(is_array($stored)&&isset($stored['version'])&&isset($manifest['version'])&&(string)$stored['version']===(string)$manifest['version'])return;
  update_option('canvasly_lite_asset_manifest',$manifest,false);
 }
 public static function performance_hooks(){ if(function_exists('wp_script_add_data')) wp_script_add_data('canvasly-lite-frontend','strategy','defer'); }
 public static function defer_frontend($tag,$handle,$src){ unset($handle,$src); return $tag; }
 public static function row_action($a,$p){
  if(!Documents::supports($p->post_type)||!current_user_can('edit_post',$p->ID))return $a;
  if(class_exists('CanvaslyLite\\Settings\\Roles')&&!\CanvaslyLite\Settings\Roles::can_edit())return $a;
  $a['canvasly-lite']='<a href="'.esc_url(admin_url('admin.php?page=canvasly-lite&post_id='.$p->ID)).'">'.esc_html__('Edit with Canvasly', 'canvasly-lite').'</a>';
  return $a;
 }
 public static function activate(){
  if(class_exists('CanvaslyLite\\Upgrade\\Upgrades'))\CanvaslyLite\Upgrade\Upgrades::on_activate();
  else update_option('canvasly_lite_version', CANVASLY_LITE_VERSION, false);
  if(class_exists('CanvaslyLite\\Settings\\Roles'))\CanvaslyLite\Settings\Roles::sync();
  flush_rewrite_rules();
 } public static function deactivate(){
  if(class_exists('CanvaslyLite\\Upgrade\\Upgrades'))\CanvaslyLite\Upgrade\Upgrades::on_deactivate();
  flush_rewrite_rules();
 } public static function register_units(){ $r=UnitRegistry::instance();$unit_file=CANVASLY_LITE_PATH.'includes/units/class-unit.php';if(is_readable($unit_file)) require_once $unit_file;$files=['container','inner_section','grid','heading','text','image','button','divider','spacer','icon','icon_list','image_box','progress','counter','alert','html','embed','shortcode','video','accordion','toggle','tabs','nested_tabs','nested_accordion','nested_toggle','social','gallery','carousel','star_rating','testimonial','menu_anchor','site_nav','read_more','soundcloud','audio','google_maps','sidebar','wordpress','link_in_bio','rating','icon_box','text_path','code','price_table','flip_box','login','collection_loop','template','component','form','tinymce_text_editor'];$classes=['Container','InnerSection','Grid','Heading','Text','Image','Button','Divider','Spacer','Icon','IconList','ImageBox','Progress','Counter','Alert','Html','Embed','Shortcode','Video','Accordion','Toggle','Tabs','NestedTabs','NestedAccordion','NestedToggle','Social','Gallery','Carousel','StarRating','Testimonial','MenuAnchor','SiteNav','ReadMore','SoundCloud','Audio','GoogleMaps','Sidebar','WordPressWidget','LinkInBio','Rating','IconBox','TextPath','Code','PriceTable','FlipBox','Login','CollectionLoop','Template','Component','Form','TinyMCETextEditor'];foreach($files as $i=>$f){$fq='CanvaslyLite\\Units\\'.$classes[$i];$type=($f==='wordpress')?'wordpress_widget':$f;$r->register_lazy($type,CANVASLY_LITE_PATH.'includes/units/class-'.$f.'.php',$fq);}
  // Extension points: control types first (units may use them), then add-on units.
  // Require the file directly — do not rely on the autoloader — so a missing/outdated
  // deploy cannot fatal with "Class Controls not found" / "undefined method boot()".
  $controls_file=CANVASLY_LITE_PATH.'includes/controls/class-controls.php';
  if(is_readable($controls_file)) require_once $controls_file;
  if(class_exists('\CanvaslyLite\Controls\Controls',false))\CanvaslyLite\Controls\Controls::instance()->boot();
  $groups_file=CANVASLY_LITE_PATH.'includes/controls/class-groups.php';
  if(is_readable($groups_file)) require_once $groups_file;
  if(class_exists('\CanvaslyLite\Controls\Groups',false))\CanvaslyLite\Controls\Groups::init();
  $code_file=CANVASLY_LITE_PATH.'includes/controls/class-code.php';
  if(is_readable($code_file)) require_once $code_file;
  if(class_exists('\CanvaslyLite\Controls\Code',false))\CanvaslyLite\Controls\Code::init();
  $query_file=CANVASLY_LITE_PATH.'includes/query/class-query.php';
  if(is_readable($query_file)) require_once $query_file;
  $tags_dir=CANVASLY_LITE_PATH.'includes/dynamic/';
  foreach(array('tag','tags','resolver','builtin') as $f){ $file=$tags_dir.'class-'.$f.'.php'; if(is_readable($file)) require_once $file; }
  if(class_exists('\CanvaslyLite\Dynamic\Tags',false)&&class_exists('\CanvaslyLite\Dynamic\Tag',false))\CanvaslyLite\Dynamic\Tags::ready();
  if(method_exists($r,'boot'))$r->boot();
  if(function_exists('canvasly_pro_register_units')) canvasly_pro_register_units();
  $anchor_file=CANVASLY_LITE_PATH.'includes/units/class-menu_anchor.php';
  if(is_readable($anchor_file)) require_once $anchor_file;
  if(class_exists('\CanvaslyLite\Units\MenuAnchor',false)&&method_exists('\CanvaslyLite\Units\MenuAnchor','boot')) \CanvaslyLite\Units\MenuAnchor::boot();
  $nav_file=CANVASLY_LITE_PATH.'includes/units/class-site_nav.php';
  if(is_readable($nav_file)) require_once $nav_file;
  if(class_exists('\CanvaslyLite\Units\SiteNav',false)&&method_exists('\CanvaslyLite\Units\SiteNav','boot')) \CanvaslyLite\Units\SiteNav::boot();
 }
 public static function frontend_assets(){if(is_singular()){} }
 public static function frontend_head(){
  if(function_exists('is_singular')&&!is_singular())return;
  $id=function_exists('get_the_ID')?absint(get_the_ID()):0;
  if(!$id||!class_exists('\\CanvaslyLite\\Document\\DocumentManager')||!DocumentManager::has($id))return;
  self::frontend_global_css();
 }
 public static function frontend_form_endpoint(){ if(!function_exists('wp_add_inline_script'))return; $js='window.canvaslyLiteFormEndpoint='.wp_json_encode(esc_url_raw(rest_url('canvasly-lite/v1/form'))).';'; if(function_exists('wp_script_is')&&!wp_script_is('canvasly-lite-frontend','registered')&&function_exists('wp_register_script')) wp_register_script('canvasly-lite-frontend',false,array(),defined('CANVASLY_LITE_VERSION')?CANVASLY_LITE_VERSION:null,true); wp_enqueue_script('canvasly-lite-frontend'); wp_add_inline_script('canvasly-lite-frontend',$js,'before'); }
 public static function frontend_global_css(){
  if(class_exists('\\CanvaslyLite\\Design\\CssPrint')){
   \CanvaslyLite\Design\CssPrint::print_head_fallback();
   return;
  }
  if(!is_singular())return;$css=\CanvaslyLite\Design\Variables::css();if(class_exists('\\CanvaslyLite\\Design\\ThemeStyle'))$css.=\CanvaslyLite\Design\ThemeStyle::css();if(class_exists('\\CanvaslyLite\\Settings\\KitSettings'))$css.=\CanvaslyLite\Settings\KitSettings::css();$css.=\CanvaslyLite\Design\GlobalClasses::css().\CanvaslyLite\Design\Interactions::css();if($css&&function_exists('wp_add_inline_style')){wp_register_style('canvasly-lite-global',false,array('canvasly-lite-frontend'),defined('CANVASLY_LITE_VERSION')?CANVASLY_LITE_VERSION:null);wp_enqueue_style('canvasly-lite-frontend');wp_enqueue_style('canvasly-lite-global');wp_add_inline_style('canvasly-lite-global',wp_strip_all_tags($css));}
 }
}
