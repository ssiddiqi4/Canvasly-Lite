<?php
namespace CanvaslyLite\Editor;
use CanvaslyLite\Units\UnitRegistry;
use CanvaslyLite\Controls\Controls;
use CanvaslyLite\Controls\Code;
use CanvaslyLite\Document\DocumentManager;
use CanvaslyLite\Document\Documents;
use CanvaslyLite\Document\DevMode;
use CanvaslyLite\Design\IconLibrary;
use CanvaslyLite\Design\GlobalClasses;
use CanvaslyLite\Design\Variables;
use CanvaslyLite\Design\ThemeStyle;
use CanvaslyLite\Design\Favorites;
use CanvaslyLite\Design\SiteNavigation;
if(!defined('ABSPATH'))exit;
class Editor {
 public static function enqueue($hook_suffix=''){
  $ok=class_exists('\\CanvaslyLite\\Admin\\AdminContext')?\CanvaslyLite\Admin\AdminContext::is_editor_page($hook_suffix):($hook_suffix==='toplevel_page_canvasly-lite');
  if(!$ok)return;
  if(class_exists('\\CanvaslyLite\\Settings\\AdminSettings')&&\CanvaslyLite\Settings\AdminSettings::is_editor_iframe_shell())return;
  add_filter('user_can_richedit','__return_true',99);
  wp_enqueue_media();
  if(!class_exists('_WP_Editors',false)) require_once ABSPATH.WPINC.'/class-wp-editor.php';
  wp_enqueue_editor();
  wp_enqueue_style('editor-buttons');
  wp_enqueue_script('wp-tinymce');
  wp_enqueue_script('editor');
  wp_enqueue_script('quicktags');
  wp_enqueue_script('wplink');
  add_action('admin_print_footer_scripts',[self::class,'print_tinymce'],50);
  $editor_css=CANVASLY_LITE_PATH.'assets/css/editor.css';
  $editor_js=CANVASLY_LITE_PATH.'assets/js/editor.js';
  $asset_version=CANVASLY_LITE_VERSION.'-'.(file_exists($editor_js)?filemtime($editor_js):time());
  // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only editor screen query var.
  $post_id=isset($_GET['post_id'])?absint(wp_unslash($_GET['post_id'])):0;
  // phpcs:enable WordPress.Security.NonceVerification.Recommended
  $code_editor=[];
  $editor_deps=['jquery','heartbeat','wp-tinymce','editor','quicktags'];
  $style_deps=[];
  if(!class_exists(Code::class,false)){
   $code_file=CANVASLY_LITE_PATH.'includes/controls/class-code.php';
   if(is_readable($code_file)) require_once $code_file;
  }
  if(class_exists(Code::class))$code_editor=Code::enqueue();
  if($code_editor){$editor_deps[]='code-editor';$style_deps[]='code-editor';}
  wp_enqueue_style('canvasly-lite-editor',CANVASLY_LITE_URL.'assets/css/editor.css',$style_deps,$asset_version);
  wp_style_add_data('canvasly-lite-editor','rtl','replace');
  wp_enqueue_script('canvasly-lite-editor',CANVASLY_LITE_URL.'assets/js/editor.js',$editor_deps,$asset_version,true);
  /**
   * Fires after the core editor assets are enqueued. Add-ons enqueue their editor scripts here with
   * 'canvasly-lite-editor' as a dependency so `window.CanvaslyLite` exists when they run.
   * @param int $post_id
   */
  do_action('canvasly-lite/editor/enqueue',$post_id);
  $data=[
   'wpRest'=>esc_url_raw(rest_url('wp/v2')),
   'api'=>esc_url_raw(rest_url('canvasly-lite/v1')),
   'nonce'=>wp_create_nonce('wp_rest'),
   'units'=>self::unit_data(),
   'postId'=>$post_id,
   'updated'=>$post_id?(string)get_post_meta($post_id,DocumentManager::UPDATED,true):'',
   'previewUrl'=>$post_id?esc_url_raw(get_preview_post_link($post_id)):'',
   'permalink'=>$post_id?esc_url_raw(get_permalink($post_id)):'',
   'postTitle'=>$post_id?(get_the_title($post_id)?:''):'',
   'adminUrl'=>admin_url(),
   'globals'=>\CanvaslyLite\Settings\GlobalSettings::get(),
   'icons'=>IconLibrary::all(),
   'iconLibraryUrl'=>CANVASLY_LITE_URL.'assets/data/fontawesome-free-icons.json',
   'googleFontsUrl'=>CANVASLY_LITE_URL.'assets/data/google-fonts.json',
   'googleFonts'=>self::google_fonts(),
   'fontDisplay'=>class_exists('\\CanvaslyLite\\Design\\Fonts')?\CanvaslyLite\Design\Fonts::display():'swap',
   'googleFontsLocal'=>class_exists('\\CanvaslyLite\\Design\\Fonts')?\CanvaslyLite\Design\Fonts::is_local():false,
   'classes'=>GlobalClasses::all(),
   'variables'=>Variables::all(),
   'themeStyle'=>class_exists(ThemeStyle::class)?ThemeStyle::all():[],
   'kitSettings'=>class_exists('\\CanvaslyLite\\Settings\\KitSettings')?\CanvaslyLite\Settings\KitSettings::all():[],
   'designCss'=>Variables::css().(class_exists(ThemeStyle::class)?ThemeStyle::css():'').(class_exists('\\CanvaslyLite\\Settings\\KitSettings')?\CanvaslyLite\Settings\KitSettings::css():'').GlobalClasses::css().(class_exists('\\CanvaslyLite\\Design\\Interactions')?\CanvaslyLite\Design\Interactions::css():''),
   'designSystem'=>\CanvaslyLite\Design\DesignSystem::export(),
   'favorites'=>Favorites::all(),
   'preferences'=>class_exists('\\CanvaslyLite\\Settings\\UserPreferences')?\CanvaslyLite\Settings\UserPreferences::get():[],
   'navigation'=>SiteNavigation::pages(),
   'breakpoints'=>\CanvaslyLite\Settings\Breakpoints::all(),
   'sidebars'=>self::sidebars(),
   'widgets'=>self::widgets(),
   'menus'=>class_exists('\\CanvaslyLite\\Units\\MenuAnchor')?\CanvaslyLite\Units\MenuAnchor::catalog():[],
   'editorCss'=>add_query_arg('v',rawurlencode((string)$asset_version),(string)CANVASLY_LITE_URL.'assets/css/editor'.(function_exists('is_rtl')&&is_rtl()?'-rtl':'').'.css'),
   'frontendCss'=>add_query_arg('v',rawurlencode(CANVASLY_LITE_VERSION.'-'.(file_exists(CANVASLY_LITE_PATH.'assets/css/frontend.css')?filemtime(CANVASLY_LITE_PATH.'assets/css/frontend.css'):time())),(string)CANVASLY_LITE_URL.'assets/css/frontend'.(function_exists('is_rtl')&&is_rtl()?'-rtl':'').'.css'),
   'atomicTypes'=>\CanvaslyLite\Design\Atomic::types(),
   'assetManifest'=>\CanvaslyLite\Design\Performance::manifest(),
   'tinymceBaseUrl'=>includes_url('js/tinymce'),
   'tinymceSkinUrl'=>includes_url('js/tinymce/skins/lightgray/skin.min.css'),
   'controlTypes'=>class_exists(Controls::class)?Controls::instance()->export():[],
   'codeEditor'=>is_array($code_editor)?$code_editor:[],
   'version'=>CANVASLY_LITE_VERSION,
   'googleMapsEmbed'=>class_exists('\\CanvaslyLite\\Settings\\AdminSettings')&&\CanvaslyLite\Settings\AdminSettings::maps_embed_enabled(),
   'googleMapsKey'=>(class_exists('\\CanvaslyLite\\Settings\\AdminSettings')&&\CanvaslyLite\Settings\AdminSettings::maps_embed_enabled())?\CanvaslyLite\Settings\AdminSettings::google_maps_api_key():'',
   'schema'=>DocumentManager::SCHEMA,
   'devMode'=>class_exists(DevMode::class)?DevMode::enabled():false,
   'dynamic'=>class_exists(DevMode::class)?DevMode::canvas_dynamic_map($post_id):[],
   'dynamicTags'=>(class_exists('\\CanvaslyLite\\Dynamic\\Tags')&&class_exists('\\CanvaslyLite\\Dynamic\\Tag'))?\CanvaslyLite\Dynamic\Tags::ready()->export($post_id):['tags'=>[],'groups'=>[],'categories'=>[],'previews'=>[]],
   'interactions'=>class_exists('\\CanvaslyLite\\Design\\Interactions')?\CanvaslyLite\Design\Interactions::export():['presets'=>[],'groups'=>[],'triggers'=>[],'kinds'=>[],'easings'=>[]],
   'i18n'=>class_exists('\CanvaslyLite\I18n\I18n')?\CanvaslyLite\I18n\I18n::editor_strings():[],
   'isRtl'=>function_exists('is_rtl')&&is_rtl(),
   'postType'=>$post_id?(string)get_post_type($post_id):'',
   'templateTypes'=>class_exists('\\CanvaslyLite\\Templates\\SavedTemplates')?\CanvaslyLite\Templates\SavedTemplates::types():[],
   'templateCategories'=>class_exists('\\CanvaslyLite\\Templates\\SavedTemplates')?\CanvaslyLite\Templates\SavedTemplates::category_names():[],
   'templateType'=>$post_id&&function_exists('get_post_type')&&get_post_type($post_id)==='lb_template'?sanitize_key((string)get_post_meta($post_id,'_lb_template_type',true)?:'page'):'',
   'themeChrome'=>class_exists('\\CanvaslyLite\\Templates\\ThemeChrome')?\CanvaslyLite\Templates\ThemeChrome::export():['header'=>false,'footer'=>false,'inherit'=>false],
   'caps'=>class_exists('\\CanvaslyLite\\Settings\\Roles')?\CanvaslyLite\Settings\Roles::editor_caps():['edit'=>current_user_can('canvasly_lite_edit'),'design'=>current_user_can('canvasly_lite_design'),'contentOnly'=>false,'access'=>'full'],
  ];
  if($post_id&&class_exists('\\CanvaslyLite\\Design\\Collaboration')){
   $lock=\CanvaslyLite\Design\Collaboration::heartbeat($post_id);
   $data['lock']=is_array($lock)?$lock:['locked'=>false];
   $data['postLock']=\CanvaslyLite\Design\Collaboration::token($post_id);
   $data['heartbeat']=true;
  }else{
   $data['lock']=['locked'=>false];
   $data['postLock']='';
   $data['heartbeat']=false;
  }
  if(class_exists('\\CanvaslyLite\\Settings\\UnitsManager')){
   $data['caps']['units']=\CanvaslyLite\Settings\UnitsManager::allowed_types();
  }
  /** Filter the data passed to the editor as `window.CanvaslyLiteData`. @param array $data @param int $post_id */
  $filtered=apply_filters('canvasly-lite/editor/localize_data',$data,$post_id);
  wp_localize_script('canvasly-lite-editor','CanvaslyLiteData',is_array($filtered)?$filtered:$data);
 }
 public static function print_tinymce(){
  if(!class_exists('_WP_Editors',false)) require_once ABSPATH.WPINC.'/class-wp-editor.php';
  add_filter('user_can_richedit','__return_true',99);
  \_WP_Editors::print_tinymce_scripts();
 }
 /**
  * Replace the WordPress admin favicon with the Canvasly C mark.
  * WordPress prints its default W logo after `admin_head`, so icon links are
  * rewritten on DOMContentLoaded as well as immediately.
  */
 public static function print_favicon(){
  $url=self::favicon_url();
  if($url==='')return;
  echo '<link rel="icon" href="'.esc_url($url).'" type="image/svg+xml" sizes="any">'."\n";
  echo '<link rel="apple-touch-icon" href="'.esc_url($url).'">'."\n";
 }
 public static function enqueue_favicon($hook_suffix=''){
  unset($hook_suffix);
  $url=self::favicon_url();
  if($url==='')return;
  $ver=defined('CANVASLY_LITE_VERSION')?CANVASLY_LITE_VERSION:'0';
  wp_register_script('canvasly-lite-admin-favicon',CANVASLY_LITE_URL.'assets/js/admin-favicon.js',array(),$ver,true);
  wp_enqueue_script('canvasly-lite-admin-favicon');
  wp_add_inline_script('canvasly-lite-admin-favicon','window.canvaslyLiteAdminIcon='.wp_json_encode($url).';','before');
 }
 private static function favicon_url(){
  global $plugin_page;
  $page=is_string($plugin_page)?$plugin_page:'';
  if($page===''&&isset($_GET['page']))$page=sanitize_key(wp_unslash($_GET['page'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page slug.
  if($page!=='canvasly-lite'&&strpos($page,'canvasly-lite-')!==0)return '';
  $file=CANVASLY_LITE_PATH.'assets/images/lb-icon.svg';
  $url=CANVASLY_LITE_URL.'assets/images/lb-icon.svg';
  if(is_readable($file))$url=add_query_arg('v',rawurlencode(CANVASLY_LITE_VERSION.'.'.(string)filemtime($file)),(string)$url);
  return (string)$url;
 }
 private static function google_fonts(){
  $file=CANVASLY_LITE_PATH.'assets/data/google-fonts.json';
  $data=class_exists('\\CanvaslyLite\\Utils\\JsonCache')?\CanvaslyLite\Utils\JsonCache::read($file):array();
  $fonts=$data?array_values(array_filter($data,'is_string')):[];
  /**
   * Font names shown in the typography control. Google families are the default.
   * Add-ons append custom families; Pro prints the matching @font-face rules.
   *
   * @param string[] $fonts
   */
  $filtered=apply_filters('canvasly-lite/fonts/families',$fonts);
  if(!is_array($filtered))return $fonts;
  $out=[];
  foreach($filtered as $name){ if(is_string($name)&&$name!=='')$out[]=$name; }
  return $out;
 }
 private static function widgets(){$out=[];foreach(\CanvaslyLite\Units\WordPressWidget::registry() as $base=>$w)$out[]=['id'=>$base,'name'=>$w['name']];usort($out,function($a,$b){return strcasecmp($a['name'],$b['name']);});return $out;}
 private static function sidebars(){global $wp_registered_sidebars;$out=[];foreach((array)$wp_registered_sidebars as $id=>$sb)$out[]=['id'=>$id,'name'=>$sb['name']??$id];return $out;}
 private static function unit_source($e){
  if(is_object($e)&&method_exists($e,'source')){
   $slug=sanitize_key((string)$e->source());
   if($slug!=='') return $slug;
  }
  if(class_exists('\\CanvaslyLite\\Settings\\UnitsManager')) return \CanvaslyLite\Settings\UnitsManager::source_slug($e);
  return 'lite';
 }
 private static function unit_data(){$out=[];foreach(UnitRegistry::instance()->all() as $e)$out[]=['type'=>$e->type(),'title'=>$e->title(),'icon'=>$e->icon(),'category'=>$e->category(),'source'=>self::unit_source($e),'controls'=>$e->all_controls(),'defaults'=>$e->get_defaults(),'children'=>$e->supports_children(),'slots'=>$e->supports_slots(),'slot_source'=>$e->slot_source(),'keywords'=>$e->keywords(),'schema'=>$e->uses_schema(),'uses_button'=>$e->uses_button()];return $out;}
 public static function screen(){
  if(class_exists('\\CanvaslyLite\\Settings\\Roles')&&!\CanvaslyLite\Settings\Roles::can_edit()){
   wp_die(esc_html__('You do not have permission to use Canvasly.', 'canvasly-lite'));
  }
  if(class_exists('\\CanvaslyLite\\Settings\\AdminSettings')&&\CanvaslyLite\Settings\AdminSettings::is_editor_iframe_shell()){
   if(class_exists('\\CanvaslyLite\\Ops\\SafeMode'))\CanvaslyLite\Ops\SafeMode::print_banner();
   $src=function_exists('add_query_arg')?add_query_arg('lb_iframe','1'):'';
   // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor screen query var.
   $iframe_post=isset($_GET['post_id'])?absint(wp_unslash($_GET['post_id'])):0;
   /** Filter the editor shell iframe URL. @param string $src @param int $iframe_post */
   $src=apply_filters('canvasly-lite/editor/iframe_src',$src,$iframe_post);
   echo '<iframe class="lb-editor-frame" src="'.esc_url($src).'" title="'.esc_attr__('Canvasly editor', 'canvasly-lite').'"></iframe>';
   return;
  }
  if(class_exists('\\CanvaslyLite\\Ops\\SafeMode'))\CanvaslyLite\Ops\SafeMode::print_banner();
  // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only editor screen query vars.
  $post_id=isset($_GET['post_id'])?absint(wp_unslash($_GET['post_id'])):0;
  if(!$post_id){
    $requested_type=isset($_GET['post_type'])?sanitize_key(wp_unslash($_GET['post_type'])):'';
    if($requested_type==='' && !empty($_GET['new_page']))$requested_type='page';
    $is_template=$requested_type==='lb_template'&&class_exists('\\CanvaslyLite\\Templates\\SavedTemplates');
    if(!$is_template){
     if($requested_type==='' || !Documents::supports($requested_type))$requested_type=Documents::fallback();
     if($requested_type==='' || !Documents::supports($requested_type))wp_die(esc_html__('No post types are enabled for Canvasly.', 'canvasly-lite'));
    }
    $post_type=$requested_type;
    if($is_template){if(!current_user_can('edit_pages'))wp_die(esc_html__('You do not have permission to use Canvasly.', 'canvasly-lite'));}
    elseif(!current_user_can(Documents::edit_cap($post_type)))wp_die(esc_html__('You do not have permission to use Canvasly.', 'canvasly-lite'));
    if($is_template){
     $post_id=\CanvaslyLite\Templates\SavedTemplates::create_blank();
     if(is_wp_error($post_id)||!$post_id)wp_die(esc_html__('WordPress could not create the draft.', 'canvasly-lite'));
    } else {
     $obj=function_exists('get_post_type_object')?get_post_type_object($post_type):null;
     $singular=$obj&&!empty($obj->labels->singular_name)?$obj->labels->singular_name:$post_type;
     if('page'===$post_type)$title=!empty($_GET['new_page'])?__('Canvasly Page', 'canvasly-lite'):__('Canvasly Draft', 'canvasly-lite');
     elseif('post'===$post_type)$title=__('Canvasly Post', 'canvasly-lite');
     else $title=sprintf(/* translators: %s: post type singular name */__('Canvasly %s', 'canvasly-lite'),$singular);
     $post_id=wp_insert_post(['post_title'=>$title,'post_status'=>'draft','post_type'=>$post_type],true);
     if(is_wp_error($post_id)||!$post_id)wp_die(esc_html__('WordPress could not create the draft.', 'canvasly-lite'));
    }
  }
  // phpcs:enable WordPress.Security.NonceVerification.Recommended
  $post=get_post($post_id);
  $is_template=$post&&$post->post_type==='lb_template'&&class_exists('\\CanvaslyLite\\Templates\\SavedTemplates');
  if(!$post || (!$is_template && !Documents::supports($post->post_type)))wp_die(esc_html__('Canvasly is not enabled for this post type.', 'canvasly-lite'));
  if($is_template){if(!current_user_can('edit_post',$post_id)&&!current_user_can('edit_pages'))wp_die(esc_html__('You do not have permission to edit this content.', 'canvasly-lite'));}
  elseif(!current_user_can('edit_post',$post_id))wp_die(esc_html__('You do not have permission to edit this content.', 'canvasly-lite'));
  $doc=DocumentManager::get($post_id);
  if(class_exists(DevMode::class))$doc=DevMode::sanitize_document($doc);
  echo '<div id="lb-editor-shell" class="lb-admin lb-fullscreen'.(class_exists('\\CanvaslyLite\\Settings\\Roles')&&\CanvaslyLite\Settings\Roles::is_content_only()?' lb-content-only':'').'"'.(function_exists('is_rtl')&&is_rtl()?' dir="rtl"':'').'><div id="lb-editor" data-post-id="'.esc_attr($post_id).'" data-document="'.esc_attr(wp_json_encode($doc)).'"></div></div>';
  echo '<div id="lb-tinymce-boot-wrap" class="lb-tinymce-boot-wrap" hidden>';
  wp_editor('<p></p>','lb_tinymce_boot',[
   'textarea_rows'=>2,
   'media_buttons'=>true,
   'drag_drop_upload'=>true,
   'tinymce'=>[
    'wp_skip_init'=>false,
    'toolbar1'=>'formatselect,bold,italic,underline,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
    'toolbar2'=>'',
   ],
   'quicktags'=>false,
  ]);
  echo '</div>';
}
}
