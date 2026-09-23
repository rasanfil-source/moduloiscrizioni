<?php
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
class WP_Error {function __construct(public $code,public $message){}}
function absint($v){return abs((int)$v);}function sanitize_key($v){return strtolower($v);}
function wp_json_encode($v){return json_encode($v);}function get_current_user_id(){return 1;}
function current_time(...$a){return '2026-09-22 12:00:00';}
function wp_timezone(){return new DateTimeZone('UTC');}
function get_post_meta($id,$key,$single){return ['_mi_activity_id'=>9,'_mi_annual_attendance_report'=>'1','_mi_event_starts_at'=>'2020-01-01T10:00'][$key]??'';}
class MI_Access {static function can_access_event($id){return true;}}
class MI_Portal_Management {static function allowed(){return true;}}
class MI_Registration_Service {
 static function append_registration_event(...$a){$GLOBALS['wpdb']->audits++;return true;}
 static function mark_workspace_changed_locked(...$a){$GLOBALS['wpdb']->revisions++;}
 static function accoda_iscrizione_workspace(...$a){$GLOBALS['wpdb']->syncs++;}
}
class AttendanceDB {
 public $prefix='wp_',$last_error='',$requests=[],$audits=0,$revisions=0,$syncs=0,$fail=false,$snapshot;
 function prepare($sql,...$args){return $sql.'|'.json_encode($args);}
 function query($sql){
  if($sql==='START TRANSACTION')$this->snapshot=[$this->requests,$this->audits,$this->revisions];
  if($sql==='ROLLBACK')[$this->requests,$this->audits,$this->revisions]=$this->snapshot;
  return 1;
 }
 function get_row($sql,...$args){$args=json_decode(explode('|',$sql)[1],true);return $this->requests[$args[0]]??null;}
 function get_results(...$args){return [['id'=>1,'registration_id'=>10]];}
 function insert($table,$data){if($this->fail)return false;$this->requests[$data['request_id']]=$data;return 1;}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-service.php';
$wpdb=new AttendanceDB();$key='wp_1_12345678-1234-4123-8123-123456789abc';$items=[['id'=>1,'attendance'=>'PRESENT']];
function verify_attendance($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$first=MI_Management_Service::save_attendance_bulk(42,$items,$key);
verify_attendance(is_array($first)&&$wpdb->audits===1&&$wpdb->syncs===1,'Initial save failed');
$second=MI_Management_Service::save_attendance_bulk(42,$items,$key);
verify_attendance($second['replayed']&&$wpdb->audits===1&&$wpdb->revisions===1&&$wpdb->syncs===1,'Replay repeated side effects');
$changed=MI_Management_Service::save_attendance_bulk(42,[['id'=>1,'attendance'=>'ABSENT']],$key);
verify_attendance($changed instanceof WP_Error&&$wpdb->audits===1,'Changed payload reused key');
$wpdb->fail=true;
$failure=MI_Management_Service::save_attendance_bulk(42,$items,str_replace('abc','abd',$key));
verify_attendance($failure instanceof WP_Error&&$wpdb->audits===1&&$wpdb->revisions===1,'Failed receipt did not roll back');
echo "PASS: attendance replay, payload conflict and request persistence failure.\n";
