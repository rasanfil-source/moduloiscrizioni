<?php
define('ABSPATH',__DIR__);
class WP_Error{function __construct(public $code,public $message){}}
function get_option($k,$default=false){return $GLOBALS['options'][$k]??$default;}
function add_option($k,$v,...$args){if(isset($GLOBALS['options'][$k]))return false;$GLOBALS['options'][$k]=$v;return true;}
function get_post_meta($id,$k,$single=true){return $GLOBALS['meta'][$id][$k]??'';}
function update_post_meta($id,$k,$v){$GLOBALS['meta'][$id][$k]=$v;}
function get_page_by_path($p){return $p==='v/existing-page'?(object)['ID'=>1]:null;}
function home_url($p){return 'https://example.invalid'.$p;}
function absint($v){return abs((int)$v);}
function add_query_arg($k,$v,$url){return $url.'?'.$k.'='.$v;}
class DB{public $prefix='wp_';function prepare($s,...$args){return $s;}function get_var($s){return 1;}}
$wpdb=new DB();$options=[];$meta=[];
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-shortcode.php';
function check($v){if(!$v)throw new Exception('Check failed');}
check(MI_Shortcode::validate_public_slug('CAMMINO27')==='cammino27');
foreach(['https://example.invalid/v/test','foo/bar','a b','-abc','abc-','a--b',str_repeat('a',61)] as $bad)check(MI_Shortcode::validate_public_slug($bad) instanceof WP_Error);
check(MI_Shortcode::validate_public_slug('existing-page') instanceof WP_Error);
check(MI_Shortcode::save_public_slug(12,'cammino27')===true);
check(MI_Shortcode::url_iscrizione(12)==='https://example.invalid/v/cammino27');
check(MI_Shortcode::validate_public_slug('cammino27',13) instanceof WP_Error);
check(MI_Shortcode::validate_public_slug('cammino27',12)==='cammino27');
check(MI_Shortcode::save_public_slug(13,'cammino27') instanceof WP_Error);
MI_Shortcode::save_public_slug(12,'cammino28');
check(get_option('mi_public_slug_cammino27')===12);
check(MI_Shortcode::url_iscrizione(12)==='https://example.invalid/v/cammino28');
check(MI_Shortcode::url_iscrizione(13)==='https://example.invalid/?mi_iscrizione=13');
echo "PASS: normalization, invalid names, existing pages, ownership, collision, old alias retention, canonical sharing URL and fallback.\n";
