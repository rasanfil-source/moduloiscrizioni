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
    static function summary($id){$GLOBALS['reads']++;return $GLOBALS['summary_fixture']??['people'=>[],'items'=>[],'rooms'=>[]];}
    static function attendance_availability($id){return ['available'=>false];}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-list.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal-management.php';
foreach ([[false,true,'rooms',403,0],[true,false,'attendance',403,0],[true,true,'invalid',400,0],[true,true,'rooms',200,1],[true,true,'attendance',200,1],[true,true,'offers',200,1]] as [$nonce,$access,$panel,$status,$expectedReads]) {
    $reads=0;$_POST=['operation'=>'summary_panel','event_id'=>42,'panel'=>$panel];
    try{MI_Portal_Management::ajax();throw new RuntimeException('No response');}catch(JsonResponse $response){if($response->getCode()!==$status||$reads!==$expectedReads)throw new RuntimeException('Access contract failed: '.$panel);}
}
echo "Panel endpoint: nonce, event access and panel allowlist verified.\n";

class MI_Travel {
    static $enabled=false, $calls=0, $fail=false;
    static function config($id){return ['enabled'=>self::$enabled];}
    static function management_overview($id){self::$calls++;if(self::$fail)throw new RuntimeException('Errore sintetico');return ['waiting'=>3,'available'=>4,'assigned'=>2,'reserved'=>1];}
}
foreach ([[false,0,false,false],[true,1,false,false],[true,0,false,true],[true,0,true,true]] as [$enabled,$missing,$fail,$expected]) {
    $nonce=$access=true;$reads=0;MI_Travel::$enabled=$enabled;MI_Travel::$fail=$fail;MI_Travel::$calls=0;
    $summary_fixture=['people'=>[],'rooms'=>[],'items'=>[['status'=>'CONFIRMED','active'=>true,'missing'=>$missing]]];
    $_POST=['operation'=>'summary','event_id'=>42];
    try{MI_Portal_Management::ajax();throw new RuntimeException('No response');}catch(JsonResponse $response){
        if($response->getCode()!==200||MI_Travel::$calls!==(int)$expected||array_key_exists('travel_overview',$response->data)!==$expected)throw new RuntimeException('Condizione casella passaggi errata');
        if($expected&&($fail?$response->data['travel_overview']!==null:$response->data['travel_overview']['available']!==4))throw new RuntimeException('Conteggi/fallback errati');
    }
}
echo "PASS: passaggi letti solo con funzione attiva e nessun dato mancante; errore isolato dal riepilogo.\n";
