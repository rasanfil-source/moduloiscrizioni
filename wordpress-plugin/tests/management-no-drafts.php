<?php
define('ABSPATH',__DIR__);
class StopEventQuery extends RuntimeException{}
function is_user_logged_in(){return true;}
function current_user_can($cap){return true;}
function nocache_headers(){}
function check_ajax_referer(...$args){return true;}
function sanitize_key($v){return $v;}
function sanitize_text_field($v){return $v;}
function wp_unslash($v){return $v;}
function absint($v){return abs((int)$v);}
function get_posts($args){
 if($args['post_status']!==['publish','private'])throw new RuntimeException('Bozze incluse nella scheda Iscrizioni');
 throw new StopEventQuery();
}
class MI_Event_Post_Type{const EVENT_TYPE='mi_event';}
class MI_Access{static function is_suspended(){return false;}static function event_ids(){return 'ALL';}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal-management.php';
foreach(['selector','all_people'] as $path){
 $_GET=[];$_POST=['operation'=>'all_people'];$checked=false;
 try{if($path==='selector')MI_Portal_Management::render();else MI_Portal_Management::ajax();}catch(StopEventQuery $e){$checked=true;}
 if(!$checked)throw new RuntimeException('Query non verificata: '.$path);
}
echo "PASS: selettore Iscrizioni e ricerca Tutti gli eventi escludono le bozze, mantenendo eventi pubblicati e privati.\n";
