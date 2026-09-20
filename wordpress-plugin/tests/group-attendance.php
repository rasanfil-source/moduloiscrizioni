<?php
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
class WP_Error {function __construct(public $code,public $message){}function get_error_message(){return $this->message;}}
class JsonResult extends Exception {function __construct(public $data,public $status){parent::__construct();}}
function wp_send_json_error($data,$status){throw new JsonResult($data,$status);}
function wp_send_json_success($data){throw new JsonResult($data,200);}
function nocache_headers(){}function is_user_logged_in(){return true;}function current_user_can($s){return true;}function check_ajax_referer(...$a){return true;}
function sanitize_key($s){return $s;}function wp_unslash($s){return $s;}function absint($v){return abs((int)$v);}function wp_date($format){return '2026';}
function is_wp_error($v){return $v instanceof WP_Error;}
class MI_Access {static function is_suspended(){return false;}static function can_access_activity($id){return $id===5;}static function can_access_event($id){return $id===10;}}
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';}
$meta=['_mi_annual_attendance_report'=>'1','_mi_attendance_from_month'=>'2025-09','_mi_attendance_to_month'=>'2026-06'];
function get_post_meta($id,$key,$single){global $meta;return $id===5?($meta[$key]??''):($key==='_mi_event_starts_at'?'2025-09-01':'');}
function get_posts($args){return [(object)['ID'=>10,'post_title'=>'Autorizzato'],(object)['ID'=>11,'post_title'=>'Non autorizzato']];}
class ReportDB {public $prefix='wp_',$last_error='',$queries=[];function get_results($sql,$format){$this->queries[]=$sql;return [];}}
$wpdb=new ReportDB();
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-attendance-report.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal-management.php';
function call_report($data){$_POST=$data+['operation'=>'annual_report','minimum'=>2];try{MI_Portal_Management::ajax();throw new Exception('No response');}catch(JsonResult $r){return $r;}}
function verify($ok,$message){if(!$ok)throw new Exception($message);}
$r=call_report(['group_id'=>5,'from_month'=>'2000-01','to_month'=>'2200-12']);
verify($r->status===200&&$r->data['from_month']==='2025-09'&&$r->data['to_month']==='2026-06','Il rapporto deve usare il periodo del gruppo, senza evento e ignorando override del browser');
foreach($wpdb->queries as $sql)verify(str_contains($sql,'IN (10)'),'Evento non autorizzato incluso');
verify(call_report(['group_id'=>6])->status===403,'Gruppo estraneo accessibile');
$meta['_mi_annual_attendance_report']='0';verify(call_report(['group_id'=>5])->status===403,'Rapporto disabilitato accessibile');
$meta['_mi_annual_attendance_report']='1';$meta['_mi_attendance_to_month']='2024-06';verify(call_report(['group_id'=>5])->status===400,'Periodo invertito accettato');
unset($meta['_mi_attendance_from_month'],$meta['_mi_attendance_to_month']);verify(MI_Attendance_Report::group_period(5)===['2026-01','2026-12'],'Default gruppi preesistenti errato');
echo "PASS: periodo del gruppo, nessun evento richiesto, override ignorati, permessi e date invalidi.\n";
