<?php
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
class WP_Error {function __construct(public $code,private $message){} function get_error_message(){return $this->message;}}
function is_wp_error($v){return $v instanceof WP_Error;} function wp_json_encode($v){return json_encode($v);} function get_current_user_id(){return $GLOBALS['user']??7;}
function wp_cache_delete($id,$group){}
function get_transient($key){return $GLOBALS['sessions'][$key]??false;} function set_transient($key,$value,$ttl){$GLOBALS['sessions'][$key]=$value;} function delete_transient($key){unset($GLOBALS['sessions'][$key]);}
class MI_Portal_Management {static function allowed(){return $GLOBALS['allowed']??true;}}
class MI_Access {static function can_access_event($id){return $id===42;}}
class MI_Field_Schema {static function resolved_operational_profile($id){return $GLOBALS['profile']??'QUOTA_UNICA';} static function workspace_event_schema($id){return $GLOBALS['schema']??['fields'=>[],'options'=>[],'room'=>false,'pricing'=>'ZERO'];}}
class SheetDatabase {
 public $prefix='wp_',$last_error='',$rows=[['id'=>1,'order_code'=>'DEMO','workspace_revision'=>'1','workspace_status'=>'PENDING']];
 function prepare($sql,...$args){return $sql;} function get_results($sql,$mode){return str_contains($sql,'mi_registrations')?$this->rows:[];} function get_var($sql){return '0';}
}
class MI_Registration_Service {static $calls=[]; static function sync_workspace($id,$force=false){self::$calls[]=[$id,$force];$GLOBALS['wpdb']->rows[0]['workspace_status']='SYNCED';return $GLOBALS['sync_result']??'SYNCED';}}
class MI_Workspace_Client {static function stable_json($v){if(is_array($v)){if(!array_is_list($v))ksort($v);foreach($v as &$item)$item=json_decode(self::stable_json($item),true);}return json_encode($v);} static $calls=0;static function request($action,$payload){self::$calls++;check($payload['event_schema']===MI_Field_Schema::workspace_event_schema(42),'Schema missing from request');check($payload['operational_profile']===MI_Field_Schema::resolved_operational_profile(42),'Profile missing from request');if(isset($GLOBALS['on_remote']))($GLOBALS['on_remote'])();return $GLOBALS['remote']??['ok'=>true,'ready'=>true,'event_schema'=>array_reverse($payload['event_schema'],true),'operational_profile'=>$payload['operational_profile'],'event_sheet_complete'=>true,'url_foglio'=>'https://docs.google.com/spreadsheets/d/synthetic/edit'];}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-sheet-open.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$wpdb=new SheetDatabase();
$first=MI_Sheet_Open::step(42);check(!$first['ready']&&MI_Workspace_Client::$calls===0,'Opened before replica');
check(MI_Registration_Service::$calls===[[1,true]],'Authoritative replica not forced');
$done=MI_Sheet_Open::step(42,$first['token']);check($done['ready']&&isset($done['url']),'Verified sheet did not open');
check(MI_Sheet_Open::step(43) instanceof WP_Error,'Wrong event accepted');
$GLOBALS['allowed']=false;check(MI_Sheet_Open::step(42) instanceof WP_Error,'Unauthorized user accepted');$GLOBALS['allowed']=true;
check(MI_Sheet_Open::step(42,str_repeat('a',32)) instanceof WP_Error,'Unknown session accepted');
$GLOBALS['remote']=['ok'=>true,'ready'=>false,'needs_sync'=>['DEMO']];
$repair=MI_Sheet_Open::step(42);check(!$repair['ready'],'Missing replica ignored');
$GLOBALS['user']=8;check(MI_Sheet_Open::step(42,$repair['token']) instanceof WP_Error,'Session crossed users');$GLOBALS['user']=7;
$repair2=MI_Sheet_Open::step(42,$repair['token']);check(!$repair2['ready'],'Repair did not run');
unset($GLOBALS['remote']);check(MI_Sheet_Open::step(42,$repair['token'])['ready'],'Repaired replica failed');
$GLOBALS['remote']=['ok'=>true,'ready'=>false,'event_sheet_complete'=>false];check(MI_Sheet_Open::step(42) instanceof WP_Error,'Pending edits opened stale sheet');
$GLOBALS['remote']=new WP_Error('offline','Offline');check(MI_Sheet_Open::step(42) instanceof WP_Error,'Offline opened stale sheet');
$GLOBALS['remote']=['ok'=>true,'ready'=>true,'event_sheet_complete'=>true,'url_foglio'=>'https://example.invalid/sheet'];check(MI_Sheet_Open::step(42) instanceof WP_Error,'Unsafe URL accepted');
unset($GLOBALS['remote']);$GLOBALS['on_remote']=function()use($wpdb){$wpdb->rows[0]['workspace_revision']='2';};check(MI_Sheet_Open::step(42) instanceof WP_Error,'Concurrent edit during refresh ignored');unset($GLOBALS['on_remote']);
$wpdb->rows[0]['workspace_status']='PENDING';$state=MI_Sheet_Open::step(42);$wpdb->rows[0]['workspace_revision']='3';check(MI_Sheet_Open::step(42,$state['token']) instanceof WP_Error,'Concurrent edit between batches ignored');
$GLOBALS['sync_result']='PENDING';$wpdb->rows[0]['workspace_status']='PENDING';check(MI_Sheet_Open::step(42) instanceof WP_Error,'Failed replica accepted');
$wpdb->last_error='synthetic';check(MI_Sheet_Open::step(42) instanceof WP_Error,'Database error treated as empty event');
$wpdb->last_error='';$wpdb->rows[0]['workspace_status']='SYNCED';
$GLOBALS['remote']=['ready'=>true,'event_sheet_complete'=>true,'url_foglio'=>'https://docs.google.com/spreadsheets/d/synthetic/edit'];check(MI_Sheet_Open::step(42) instanceof WP_Error,'Old Workspace opened without confirming profile');unset($GLOBALS['remote']);
$GLOBALS['on_remote']=function(){$GLOBALS['profile']='MINIMO';};check(MI_Sheet_Open::step(42) instanceof WP_Error,'Concurrent profile change ignored');unset($GLOBALS['on_remote']);
$GLOBALS['on_remote']=function(){$GLOBALS['schema']=['fields'=>[['key'=>'birth_date']],'options'=>[],'room'=>false,'pricing'=>'ZERO'];};check(MI_Sheet_Open::step(42) instanceof WP_Error,'Concurrent field configuration change ignored');unset($GLOBALS['on_remote']);
$GLOBALS['remote']=['ready'=>true,'event_sheet_complete'=>true,'operational_profile'=>MI_Field_Schema::resolved_operational_profile(42),'url_foglio'=>'https://docs.google.com/spreadsheets/d/synthetic/edit'];check(MI_Sheet_Open::step(42) instanceof WP_Error,'Missing field schema confirmation accepted');unset($GLOBALS['remote']);
$GLOBALS['remote']=['ok'=>true,'ready'=>false,'busy'=>true];
$waiting=MI_Sheet_Open::step(42);check(!$waiting['ready']&&$waiting['retry_after']===3,'Busy request failed instead of retrying');
$again=MI_Sheet_Open::step(42,$waiting['token']);check($again['token']===$waiting['token'],'Busy request discarded session');
unset($GLOBALS['remote']);check(MI_Sheet_Open::step(42,$waiting['token'])['ready'],'Busy request did not recover');
$GLOBALS['remote']=['ok'=>true,'busy'=>true];$waiting=MI_Sheet_Open::step(42);$key='mi_sheet_open_7_'.$waiting['token'];
$GLOBALS['sessions'][$key]['busy_since']=time()-121;
check(MI_Sheet_Open::step(42,$waiting['token']) instanceof WP_Error,'Unbounded busy retry');check(!isset($GLOBALS['sessions'][$key]),'Expired wait retained session');
echo "PASS: apertura obbligatoria, replica, recupero, permessi, sessioni isolate, modifiche concorrenti, celle pendenti, attesa limitata ed errori.\n";
