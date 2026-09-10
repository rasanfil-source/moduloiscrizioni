<?php
// Test distruttivo esclusivamente nel database locale mi_ledger_test sulla porta 33317.
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function sanitize_text_field($v){return strip_tags((string)$v);}
function sanitize_textarea_field($v){return strip_tags((string)$v);}
function get_current_user_id(){return 7;}
function wp_get_current_user(){return(object)['display_name'=>'Test'];}
function wp_json_encode($v){return json_encode($v);}
function current_time(...$v){return gmdate('Y-m-d H:i:s');}
function absint($value){return abs((int)$value);} function sanitize_key($value){return preg_replace("/[^a-z0-9_-]/","",strtolower($value));}
class WP_Error {function get_error_message(){return $this->code;}public $code;function __construct($c,$m){$this->code=$c;}}
class MI_Access {static function event_ids(){return [42];}static function can_access_event($id){return $id===42;}}
class MI_Portal_Payments {static function allowed(){return $GLOBALS['test_payment_permission']??true;}}
class MI_Registration_Service {static function validate_options(...$args){return MI_Registration_Validation_Test::validate_options(...$args);}static function append_registration_event(...$v){global $wpdb;if(empty($GLOBALS['management_audit_test']))return true;return false!==$wpdb->insert('wp_mi_registration_events',['registration_id'=>$v[0],'event_type'=>$v[1],'from_status'=>$v[2],'to_status'=>$v[3],'actor_label'=>$v[4],'detail_json'=>json_encode($v[5]??[]),'created_at'=>gmdate('Y-m-d H:i:s')]);}static function accoda_iscrizione_workspace($id){} static function mark_workspace_changed_locked($id){global $wpdb;if(1!==$wpdb->query($wpdb->prepare("UPDATE wp_mi_registrations SET workspace_revision=workspace_revision+1,workspace_status='PENDING',workspace_attempts=0 WHERE id=%d",$id)))throw new RuntimeException('Revision failed');}}

class DatabaseAdapter {
 function esc_like($value){return addcslashes($value,'_%\\');}
 public $prefix='wp_',$last_error='',$insert_id=0,$db;
 function __construct(){mysqli_report(MYSQLI_REPORT_OFF);$this->db=new mysqli('127.0.0.1','root','local-ledger-test-only','',33317);if($this->db->connect_errno)throw new RuntimeException('Database locale non disponibile');}
 function prepare($sql,...$args){$i=0;return preg_replace_callback('/%[ds]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:"'".$this->db->real_escape_string($v)."'";},$sql);}
 function query($sql){$r=$this->db->query($sql);$this->last_error=$this->db->error;return $r===false?false:($r===true?$this->db->affected_rows:$r);}
 function get_row($sql,$mode){$r=$this->query($sql);return $r instanceof mysqli_result?$r->fetch_assoc():null;}
 function get_results($sql,$mode){$r=$this->query($sql);return $r instanceof mysqli_result?$r->fetch_all(MYSQLI_ASSOC):[];}
 function get_var($sql){$r=$this->get_row($sql,ARRAY_A);return $r?reset($r):null;}
 function insert($table,$data){$vals=array_map(fn($v)=>$v===null?'NULL':"'".$this->db->real_escape_string((string)$v)."'",array_values($data));$r=$this->query('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',$vals).')');$this->insert_id=$this->db->insert_id;return $r;}
 function update($table,$data,$where){$set=[];foreach($data as $k=>$v)$set[]="`$k`=".($v===null?'NULL':"'".$this->db->real_escape_string((string)$v)."'");return $this->query('UPDATE '.$table.' SET '.implode(',',$set).' WHERE id='.(int)$where['id']);}
}
