<?php
// Database e trasporto simulati: nessuna email viene inviata.
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');define('MINUTE_IN_SECONDS',60);
class WP_Error {function __construct(public $code,public $message){}function get_error_message(){return $this->message;}}
function is_wp_error($v){return $v instanceof WP_Error;}
function current_user_can($cap){return $GLOBALS['allowed'];}
function absint($v){return abs((int)$v);}
function sanitize_textarea_field($v){return trim(strip_tags((string)$v));}
function get_current_user_id(){return 7;}
function wp_json_encode($v){return json_encode($v);}
function get_option($key,$default=null){return $key==='mi_modalita_spedizione_email'?'ANTEPRIMA':$default;}
class MI_Access {
 static function is_suspended(){return $GLOBALS['suspended'];}
 static function can_access_event($id){return $GLOBALS['scope']&&$id===42;}
}
class MI_Event_Deletion {
 static function registration_event($id){return 42;}
 static function job($id){return ['stage'=>$GLOBALS['deletion_stage']];}
 static function enter($id){$GLOBALS['lease']++;if($GLOBALS['deleting'])return new WP_Error('deleting','Deletion running');return true;}
 static function release($id){$GLOBALS['lease']--;}
}
class RegenerationDB {
 public $prefix='wp_',$last_error='',$insert_id=10,$rows=[],$backup,$failure='';
 function __construct($status='FAILED'){$this->rows[1]=['id'=>1,'registration_id'=>9,'recipient'=>'example@example.invalid','template_type'=>'REGISTERED','status'=>$status,'payload_json'=>json_encode(['event_id'=>42,'email_preview'=>['testo'=>'Preserved snapshot']]),'origin_key'=>null,'attempts'=>5];}
 function prepare($sql,...$args){return [$sql,$args];}
 function get_row($prepared,$format){[$sql,$args]=$prepared;if($this->failure==='locked-read'&&str_contains($sql,'FOR UPDATE')){$this->last_error='read failed';return null;}return $this->rows[$args[0]]??null;}
 function get_var($prepared){foreach($this->rows as $row)if($row['origin_key']===$prepared[1][0])return $row['id'];return null;}
 function query($sql){
  if($sql==='START TRANSACTION'){$this->backup=$this->rows;if($this->failure==='start')return false;}
  if($sql==='COMMIT'&&$this->failure==='commit')return false;
  if($sql==='ROLLBACK'){$this->rows=$this->backup;$this->last_error='';}
  return 1;
 }
 function insert($table,$data){if($this->failure==='insert')return false;$this->insert_id++;$this->rows[$this->insert_id]=['id'=>$this->insert_id,'attempts'=>0]+$data;return 1;}
 function update($table,$data,$where){if($this->failure==='update')return false;if($this->rows[$where['id']]['status']!==$where['status'])return 0;$this->rows[$where['id']]=array_replace($this->rows[$where['id']],$data);return 1;}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
function expect($ok,$message){if(!$ok)throw new RuntimeException($message);}
$allowed=true;$scope=true;$suspended=false;$deleting=false;$deletion_stage='done';$lease=0;
foreach(['FAILED'=>'PENDING','TEST_FAILED'=>'TEST_PENDING']as $before=>$after){
 $wpdb=new RegenerationDB($before);$id=MI_Spedizione_Email::rigenera_consegna(1,'Verificati inviati e riscontro destinatario',true);
 expect($id===11&&count($wpdb->rows)===2&&$wpdb->rows[11]['status']===$after,'Wrong delivery mode or count');
 expect($wpdb->rows[1]['status']==='SUPERSEDED'&&$wpdb->rows[11]['attempts']===0,'Original not superseded or attempts copied');
 $audit=json_decode($wpdb->rows[1]['payload_json'],true)['delivery_regeneration'];
 expect($audit['operator_id']===7&&$audit['source_id']===1&&$audit['replacement_id']===11,'Missing audit trail');
 expect(json_decode($wpdb->rows[11]['payload_json'],true)['email_preview']['testo']==='Preserved snapshot','Snapshot changed');
 expect(MI_Spedizione_Email::rigenera_consegna(1,'Repeated confirmation',true)===11&&count($wpdb->rows)===2,'Repeated request duplicated delivery');
 expect($lease===0,'Lease leaked after success/replay');
}
foreach(['SENDING','TEST_SENDING','SENT','TEST_SENT','PENDING','PREVIEW','SUPERSEDED']as $status){
 $wpdb=new RegenerationDB($status);expect(is_wp_error(MI_Spedizione_Email::rigenera_consegna(1,'Checked',true))&&count($wpdb->rows)===1&&$lease===0,'Wrong status allowed');
}
foreach(['start','locked-read','insert','update','commit']as $failure){
 $wpdb=new RegenerationDB();$before=$wpdb->rows;$wpdb->failure=$failure;
 expect(is_wp_error(MI_Spedizione_Email::rigenera_consegna(1,'Checked',true))&&$wpdb->rows===$before&&$lease===0,'Incomplete rollback: '.$failure);
 $wpdb->failure='';expect(is_int(MI_Spedizione_Email::rigenera_consegna(1,'Checked',true))&&count($wpdb->rows)===2,'Retry after failure failed');
}
$wpdb=new RegenerationDB();
foreach([['',true],['Checked',false],[str_repeat('x',501),true]]as [$reason,$verified])expect(is_wp_error(MI_Spedizione_Email::rigenera_consegna(1,$reason,$verified))&&count($wpdb->rows)===1,'Missing human verification allowed');
$allowed=false;expect(is_wp_error(MI_Spedizione_Email::rigenera_consegna(1,'Checked',true)),'Non-admin allowed');$allowed=true;
$suspended=true;expect(is_wp_error(MI_Spedizione_Email::rigenera_consegna(1,'Checked',true)),'Suspended admin allowed');$suspended=false;
$scope=false;expect(is_wp_error(MI_Spedizione_Email::rigenera_consegna(1,'Checked',true)),'Wrong event scope allowed');$scope=true;
$deleting=true;expect(is_wp_error(MI_Spedizione_Email::rigenera_consegna(1,'Checked',true))&&count($wpdb->rows)===1,'Active deletion allowed');
$wpdb->rows[1]['template_type']='EVENT_DELETED_NOTICE';$wpdb->rows[1]['registration_id']=0;$deletion_stage='waiting';
expect(is_wp_error(MI_Spedizione_Email::rigenera_consegna(1,'Checked',true)),'Incomplete deletion notice allowed');
$deletion_stage='done';expect(MI_Spedizione_Email::rigenera_consegna(1,'Checked',true)===11,'Completed deletion notice rejected');
expect($lease===0,'Lease leaked');
echo "PASS manual email regeneration: permissions, verification, modes, replay, deletion, rollback and audit.\n";
