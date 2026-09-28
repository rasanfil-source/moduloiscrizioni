<?php
define('ABSPATH',__DIR__);
class JsonResponse extends RuntimeException { public $data; public function __construct($data,$code){parent::__construct('', $code);$this->data=$data;} }
function nocache_headers(){}
function is_user_logged_in(){return true;}
function current_user_can($cap){return true;}
function check_ajax_referer(...$args){return $GLOBALS['nonce'];}
function sanitize_key($v){return $v;}
function wp_unslash($v){return $v;}
function absint($v){return abs((int)$v);}
function wp_json_encode($v){return json_encode($v);}
function wp_send_json_error($data,$code){throw new JsonResponse($data,$code);}
function wp_send_json_success($data){throw new JsonResponse($data,200);}
function is_wp_error($v){return false;}
function get_post_meta(...$args){return '';}
class MI_Access {static function is_suspended(){return false;}static function can_access_event($id){return $GLOBALS['access']&&$id===42;} }
class MI_Portal_Payments {static function report_url($id){return '';}}
class MI_Management_Service {
    static function summary($id){$GLOBALS['reads']++;return ['people'=>[],'items'=>[],'rooms'=>[]];}
    static function attendance_availability($id){return ['available'=>false];}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-list.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal-management.php';
foreach ([[false,true,'rooms',403,0],[true,false,'attendance',403,0],[true,true,'invalid',400,0],[true,true,'rooms',200,1],[true,true,'attendance',200,1],[true,true,'offers',200,1]] as [$nonce,$access,$panel,$status,$expectedReads]) {
    $reads=0;$_POST=['operation'=>'summary_panel','event_id'=>42,'panel'=>$panel];
    try{MI_Portal_Management::ajax();throw new RuntimeException('No response');}catch(JsonResponse $response){if($response->getCode()!==$status||$reads!==$expectedReads)throw new RuntimeException('Access contract failed: '.$panel);}
}
echo "Panel endpoint: nonce, event access and panel allowlist verified.\n";
