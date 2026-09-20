<?php
// Render the real opening page with local synthetic WordPress services.
define('ABSPATH',__DIR__);
function is_user_logged_in(){return true;} function absint($v){return abs((int)$v);} function nocache_headers(){}
function esc_url($v){return htmlspecialchars($v,ENT_QUOTES);} function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function admin_url($v){return '/ajax';} function wp_create_nonce($v){return 'synthetic';}
function add_query_arg($k,$v,$url){return $url.'&'.$k.'='.$v;}
class MI_Portal_Management {static function allowed(){return true;} static function url($id){return '/?management='.$id;}}
class MI_Access {static function can_access_event($id){return $id===42;}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-sheet-open.php';
$_GET['mi_open_sheet']=42;MI_Sheet_Open::render();
