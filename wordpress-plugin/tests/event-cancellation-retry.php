<?php
define('ABSPATH',__DIR__); define('ARRAY_A','ARRAY_A');
class WP_Error {function __construct(public $code,public $message){} function get_error_message(){return $this->message;}}
class RedirectResult extends Exception {function __construct(public $result){}}
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);} function wp_unslash($v){return $v;}
function sanitize_textarea_field($v){return $v;} function sanitize_text_field($v){return $v;}
function is_user_logged_in(){return true;} function current_user_can(...$a){return true;}
function check_admin_referer(...$a){} function wp_get_current_user(){return (object)['display_name'=>'TEST'];}
function get_post_meta($id,$key,$single){return $GLOBALS['meta'][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$key]=$value;return true;}
function current_time(...$a){return '2026-09-22 12:00:00';}
function wp_update_post($data){$GLOBALS['post_status']=$data['post_status'];return $data['ID'];}
function get_posts(...$a){return [];} function home_url(...$a){return 'https://example.invalid/';}
function add_query_arg($args,$url){return $args;} function wp_safe_redirect($result){throw new RedirectResult($result);}
class MI_Access {static function can_access_event($id){return true;}}
class MI_Registration_Service {
 static function cancel_registration($id,...$args){
  if($GLOBALS['cancel_failure']===$id)return new WP_Error('cancel','synthetic');
  $GLOBALS['rows'][$id]['status']='CANCELLED';return 'CANCELLED';
 }
}
class MI_Payment_Ledger {static function positions($rows){$result=[];foreach($rows as $r)$result[$r['id']]=['effective_paid'=>0,'effective_balance'=>100,'managed'=>true];return $result;}}
class MI_Spedizione_Email {
 static function modalita(){return 'OPERATIVO';}
 static function accoda_comunicazione_operativa($payload){
  foreach($GLOBALS['rows'] as $row)if($row['status']!=='CANCELLED')throw new RuntimeException('Email before cancellation');
  if($GLOBALS['email_failure'])return new WP_Error('email','synthetic');
  $count=0;foreach($payload['recipients'] as $r){$key=$payload['communication_id'].'|'.$r['order_code'];if(!isset($GLOBALS['outbox'][$key]))$count++;$GLOBALS['outbox'][$key]=true;}
  return ['count'=>$count,'mode'=>'OPERATIVO'];
 }
 static function accoda_avviso_annullamento_segreteria(...$args){return ['count'=>0];}
}
class CancellationDB {
 public $prefix='wp_',$last_error='';
 function prepare($sql,...$args){return $sql;}
 function get_results(...$a){return array_values(array_filter($GLOBALS['rows'],fn($r)=>$r['status']!=='CANCELLED'));}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
$wpdb=new CancellationDB();$rows=[];$meta=[];$outbox=[];$post_status='publish';
for($id=1;$id<=1001;$id++)$rows[$id]=['id'=>$id,'order_code'=>'TEST'.$id,'status'=>'CONFIRMED'];
$_POST=['event_id'=>42,'confirm_cancellation'=>1,'cancellation_reason'=>'Test'];
$method=new ReflectionMethod(MI_Portal::class,'handle_event_management_action');
function attempt(){global $method;try{$method->invoke(null,'cancel_event');}catch(RedirectResult $r){return $r->result;}throw new RuntimeException('Missing result');}
function verify_cancel($ok,$message){if(!$ok)throw new RuntimeException($message);}
$cancel_failure=2;$email_failure=false;
verify_cancel(attempt()['mi_portal_error']==='1'&&count($outbox)===0,'Partial cancellation sent email');
verify_cancel(count($meta['_mi_event_cancellation_job']['recipients'])===1001,'Recipient snapshot missing');
$cancel_failure=0;$email_failure=true;
verify_cancel(attempt()['mi_portal_error']==='1'&&count($outbox)===0,'Email failure hidden');
$email_failure=false;
verify_cancel(attempt()['mi_portal_error']==='0'&&count($outbox)===1001&&$post_status==='draft','Retry lost cancelled recipients or second batch');
verify_cancel(attempt()['mi_portal_error']==='0'&&count($outbox)===1001,'Retry duplicated notices');
echo "PASS: partial cancellation, failed email, persistent recipients, batching and repeated retry.\n";
