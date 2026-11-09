<?php
// InnoDB locale, sole tabelle TEMPORARY e trasporto Google simulato. Nessuna email reale.
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');define('MINUTE_IN_SECONDS',60);define('DAY_IN_SECONDS',86400);
class WP_Error{
 function __construct(public $code,public $message,public $data=null){}
 function get_error_code(){return $this->code;} function get_error_message(){return $this->message;} function get_error_data(){return $this->data;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function check($v,$message){if(!$v)throw new RuntimeException($message);}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',(string)$v));}
function sanitize_email($v){return filter_var($v,FILTER_SANITIZE_EMAIL);}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_textarea_field($v){return trim((string)$v);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function wp_salt($v){return 'local-synthetic-test-only';}
function home_url(){return 'https://example.invalid';}
function admin_url($v){return home_url().'/wp-admin/'.$v;}
function current_time(...$a){return gmdate('Y-m-d H:i:s');}
function get_current_user_id(){return $GLOBALS['actor']??7;}
function current_user_can($v){return true;}
function get_post_status($id){return 'publish';}
function wp_json_encode($v){return json_encode($v);}
function wp_next_scheduled($hook){return false;}
function wp_schedule_single_event(...$v){$GLOBALS['cron'][]=$v;return true;}
function wp_unschedule_event(...$v){}
function add_action(...$v){$GLOBALS['actions'][]=$v;}
function wp_date($f,$t){return gmdate($f,$t);}
function wp_timezone(){return new DateTimeZone('UTC');}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES);}
function wp_remote_post(...$v){$GLOBALS['loopbacks'][]=$v;return true;}
function wp_die($s='',$t='',$args=[]){throw new RuntimeException('HTTP '.($args['response']??0));}
class BulkDatabase{
 public $prefix='wp_',$options='wp_options',$last_error='',$insert_id=0,$db;
 function __construct(){
  mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
  $this->db=new mysqli('127.0.0.1','root','local-ledger-test-only','mi_ledger_test',33317);
 }
 function esc_like($s){return addcslashes($s,'_%\\');}
 function prepare($sql,...$args){
  if(isset($args[0])&&is_array($args[0]))$args=$args[0];$i=0;
  return preg_replace_callback('/%[ds]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:"'".$this->db->real_escape_string($v)."'";},$sql);
 }
 function query($sql){$this->db->query($sql);$this->insert_id=$this->db->insert_id;return $this->db->affected_rows;}
 function get_results($sql,$type=ARRAY_A){return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);}
 function get_row($sql,$type=ARRAY_A){return $this->db->query($sql)->fetch_assoc();}
 function get_var($sql){return $this->db->query($sql)->fetch_row()[0]??null;}
 function update($table,$data,$where,...$args){
  $assign=[];$filters=[];
  foreach($data as $k=>$v)$assign[]="$k=".($v===null?'NULL':$this->prepare('%s',$v));
  foreach($where as $k=>$v)$filters[]="$k=".$this->prepare('%s',$v);
  return $this->query("UPDATE $table SET ".implode(',',$assign).' WHERE '.implode(' AND ',$filters));
 }
}
$wpdb=new BulkDatabase();
$wpdb->query("CREATE TEMPORARY TABLE wp_options(option_id BIGINT AUTO_INCREMENT PRIMARY KEY,option_name VARCHAR(191) UNIQUE,option_value LONGTEXT,autoload VARCHAR(20) DEFAULT 'no') ENGINE=InnoDB");
$schema=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php');
preg_match('/CREATE TABLE \{\$outbox\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);
check(isset($m[1]),'Outbox schema not found');
$wpdb->query('CREATE TEMPORARY TABLE wp_mi_email_outbox ('.$m[1].') ENGINE=InnoDB');
$wpdb->query("CREATE TEMPORARY TABLE wp_mi_registrations(id BIGINT PRIMARY KEY,event_id BIGINT,order_code VARCHAR(64),status VARCHAR(32),economic_mode VARCHAR(32),buyer_first_name VARCHAR(100),buyer_last_name VARCHAR(100),buyer_email VARCHAR(254),total_qty INT,total_cents INT,initial_due_cents INT,balance_cents INT,payment_methods_json TEXT,payment_deadline_at DATETIME NULL,capacity_released_at DATETIME NULL) ENGINE=InnoDB");
for($i=1;$i<=198;$i++)$wpdb->query("INSERT INTO wp_mi_registrations VALUES($i,42,'ORDER$i','CONFIRMED','FULL_PAYMENT','Persona$i','Test','person$i@example.invalid',1,100,100,100,'[]',NULL,NULL)");
function get_option($k,$default=false){global $wpdb;$v=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM wp_options WHERE option_name=%s",$k));return $v===null?$default:(str_starts_with($v,'a:')?unserialize($v):$v);}
function add_option($k,$v,$unused='',$autoload=false){global $wpdb;return 1===$wpdb->query($wpdb->prepare("INSERT IGNORE INTO wp_options(option_name,option_value) VALUES(%s,%s)",$k,(is_array($v)?serialize($v):$v)));}
function update_option($k,$v,$autoload=false){global $wpdb;$wpdb->query($wpdb->prepare("INSERT INTO wp_options(option_name,option_value) VALUES(%s,%s) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)",$k,(is_array($v)?serialize($v):$v)));return true;}
function delete_option($k){global $wpdb;$wpdb->query($wpdb->prepare("DELETE FROM wp_options WHERE option_name=%s",$k));}
function get_transient($k){return get_option('_transient_'.$k);}
function set_transient($k,$v,$ttl){return update_option('_transient_'.$k,$v);}
class MI_Event_Deletion{
 static function enter($id){return true;} static function release($id){}
 static function registration_event($id){global $wpdb;return (int)$wpdb->get_var("SELECT event_id FROM wp_mi_registrations WHERE id=".(int)$id);}
}
class MI_Access{static function can_access_event($id){return $id===42;}}
class MI_Registration_Service{static function public_event(...$v){return ['title'=>'Evento sintetico'];}}
class MI_Portal{static function status_url(...$v){return 'https://example.invalid/status';}static function balance_url(...$v){return 'https://example.invalid/balance';}}
class MI_Payment_Ledger{
 static function positions($rows){$out=[];foreach($rows as $r)$out[$r['id']]=['effective_paid'=>0,'effective_balance'=>100,'managed'=>true,'effective_total'=>100,'individual_known'=>false,'individual_people_count'=>1,'individual_active_count'=>1];return $out;}
}
class MI_Modello_Email{
 const EMAIL_SEGRETERIA='sender@example.invalid';const NOME_SEGRETERIA='Gruppo';
 static function valori_ordine($event,$code,$status,$qty,$buyer,...$v){return ['buyer'=>$buyer,'code'=>$code];}
 static function crea_istantanea_operativa($event,$values,...$v){return ['attivo'=>true,'oggetto'=>'Evento','html'=>'<p>Ciao '.$values['buyer'].'</p>','testo'=>'Ciao '.$values['buyer']];}
 static function ripara_istantanea_codifica($v){return $v;}
 static function componi_html($v,...$a){return $v['html'];}static function componi_testo($v){return $v['testo'];}
}
class MI_Workspace_Client{
 static $calls=0,$accepted=[],$payloads=[],$quota=1500,$lost=false,$uncertain=false;
 static function request($action,$payload){
  check($action==='INVIA_EMAIL_LOTTO','Batch action missing');check(count($payload['messages'])<=10,'Oversized batch');
  self::$calls++;$results=[];
  foreach($payload['messages'] as $p){
   $key=$p['delivery_key'];self::$payloads[$key]=$p;
   if(isset(self::$accepted[$key]))$results[$key]=self::$accepted[$key];
   elseif(self::$quota<1)$results[$key]=['ok'=>false,'error'=>'EMAIL_QUOTA_EXCEEDED'];
   else{
    self::$quota--;self::$accepted[$key]=['ok'=>true,'channel'=>'GOOGLE_WORKSPACE'];$results[$key]=self::$accepted[$key];
    if(self::$uncertain&&$p['destinatario']==='person1@example.invalid')$results[$key]=['ok'=>false,'error'=>'EMAIL_DELIVERY_UNCERTAIN'];
   }
  }
  if(self::$lost){self::$lost=false;return new WP_Error('mi_workspace_unreachable','Synthetic lost HTTP response');}
  return ['ok'=>true,'channel'=>'GOOGLE_WORKSPACE','results'=>$results,'quota_remaining'=>self::$quota];
 }
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-email-jobs.php';
function reset_bulk(){
 global $wpdb;
 $wpdb->query('DELETE FROM wp_mi_email_outbox');$wpdb->query('DELETE FROM wp_options');
 update_option('mi_modalita_spedizione_email','OPERATIVO');
 update_option('mi_destinatario_prova_email','test@example.invalid');
 update_option('mi_prova_email_verificata',hash_hmac('sha256','test@example.invalid',wp_salt('auth')));
 MI_Workspace_Client::$calls=0;MI_Workspace_Client::$accepted=[];MI_Workspace_Client::$payloads=[];MI_Workspace_Client::$quota=1500;MI_Workspace_Client::$lost=false;MI_Workspace_Client::$uncertain=false;
}
function bulk_payload($id='campaign'){return ['communication_id'=>$id,'event_id'=>42,'template_type'=>'EVENT_NOTICE','message'=>'Informazioni','allow_operational'=>true,'recipients'=>array_map(fn($i)=>['order_code'=>'ORDER'.$i],range(1,198))];}
function complete_job($id){
 for($i=0;$i<80;$i++){
  MI_Spedizione_Email::spedisci_coda();$state=MI_Email_Jobs::status($id);
  if($state['done'])return $state;
  update_option('mi_email_retry_after',0); // advance the test clock; no waiting or actual mail
 }
 throw new RuntimeException('Campaign did not finish');
}
reset_bulk();
$started=microtime(true);$job=MI_Email_Jobs::enqueue(bulk_payload());$elapsed=microtime(true)-$started;
check($elapsed<1&&MI_Workspace_Client::$calls===0&&(int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_email_outbox')===0,'Page waited for preparation or transport');
check(MI_Email_Jobs::enqueue(bulk_payload())['job_id']===$job['job_id'],'Duplicate POST changed job');
$other=new mysqli('127.0.0.1','root','local-ledger-test-only','mi_ledger_test',33317);
$lock='mi_mail_worker_'.md5($wpdb->prefix);$other->query("SELECT GET_LOCK('$lock',0)");
MI_Spedizione_Email::spedisci_coda();check(MI_Workspace_Client::$calls===0,'Concurrent worker was not excluded');$other->query("SELECT RELEASE_LOCK('$lock')");$other->close();
$state=complete_job($job['job_id']);
check($state['sent']===198&&$state['prepared']===198&&$state['pending']===0&&$state['failed']===0,'Incorrect campaign counts: '.json_encode($state));
check(MI_Workspace_Client::$calls===20&&count(MI_Workspace_Client::$accepted)===198,'Expected 20 calls and 198 individual acceptances');
foreach(MI_Workspace_Client::$payloads as $p)check($p['mode']==='OPERATIVO'&&!isset($p['cc'])&&!isset($p['bcc'])&&str_contains($p['html'],'Persona'),'Message mode, privacy or personalisation broken');
MI_Spedizione_Email::spedisci_coda();check(count(MI_Workspace_Client::$accepted)===198,'Campaign replay duplicated emails');
$GLOBALS['actor']=8;check(MI_Email_Jobs::status($job['job_id'])!==false,'Admin should have access');$GLOBALS['actor']=7;
echo 'PASS: 198 recipients, 20 HTTP batches, persistent preparation, duplicate POST and SQL worker exclusion. Enqueue '.round($elapsed*1000,2)." ms (local synthetic test).\n";
reset_bulk();MI_Workspace_Client::$quota=17;$job=MI_Email_Jobs::enqueue(bulk_payload('quota'));
for($i=0;$i<4;$i++)MI_Spedizione_Email::spedisci_coda();
check(count(MI_Workspace_Client::$accepted)===17&&get_option('mi_email_retry_after')>time(),'Quota pause missing');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_email_outbox WHERE status IN('FAILED','TEST_FAILED')")===0,'Quota exhausted attempts');
MI_Workspace_Client::$quota=500;update_option('mi_email_retry_after',0);
$state=complete_job($job['job_id']);check($state['sent']===198&&count(MI_Workspace_Client::$accepted)===198,'Quota recovery lost or duplicated mail');
echo "PASS: quota exhausted at 17, pending messages retained, all 198 resume once.\n";
reset_bulk();MI_Workspace_Client::$lost=true;$job=MI_Email_Jobs::enqueue(bulk_payload('timeout'));
$state=complete_job($job['job_id']);check($state['sent']===198&&count(MI_Workspace_Client::$accepted)===198,'Lost response duplicated or lost mail');
echo "PASS: response lost after Google acceptance, same delivery keys safely resume.\n";
reset_bulk();MI_Workspace_Client::$uncertain=true;$job=MI_Email_Jobs::enqueue(bulk_payload('uncertain'));
$state=complete_job($job['job_id']);check($state['sent']===197&&$state['failed']===1,'Uncertain outcome hidden');
check((int)$wpdb->get_var("SELECT attempts FROM wp_mi_email_outbox WHERE recipient='person1@example.invalid'")===1,'Uncertain outcome retried automatically');
echo "PASS: uncertain result stops that recipient immediately and other 197 finish.\n";
reset_bulk();$job=MI_Email_Jobs::enqueue(bulk_payload('mode'));update_option('mi_modalita_spedizione_email','PROVA');
MI_Spedizione_Email::spedisci_coda();check(MI_Email_Jobs::status($job['job_id'])['paused']&&MI_Workspace_Client::$calls===0,'Mode changed but campaign sent');
update_option('mi_modalita_spedizione_email','OPERATIVO');check(complete_job($job['job_id'])['sent']===198,'Original mode did not resume');
echo "PASS: mode change suspends preparation; original mode resumes.\n";
reset_bulk();update_option('mi_modalita_spedizione_email','ANTEPRIMA');
$p=bulk_payload('test');$p['test_only']=true;$p['allow_operational']=false;$p['test_recipient']='chosen@example.invalid';
$job=MI_Email_Jobs::enqueue($p);$state=complete_job($job['job_id']);
check($state['total']===1&&$state['sent']===1,'Explicit test did not finish');
foreach(MI_Workspace_Client::$payloads as $p)check($p['mode']==='PROVA_PUNTUALE'&&$p['destinatario']==='chosen@example.invalid'&&!str_contains($p['html'],'Persona1'),'Test scope or anonymisation escaped');
$_POST=['stamp'=>(string)time(),'signature'=>'invalid'];
try{MI_Email_Jobs::worker();throw new RuntimeException('Unsigned worker accepted');}catch(RuntimeException $e){check($e->getMessage()==='HTTP 403','Worker authentication failed');}
echo "PASS: explicit anonymised test goes only to chosen mailbox; unsigned worker denied.\n";

reset_bulk();update_option('mi_prova_email_verificata','');
$r=MI_Email_Jobs::enqueue(bulk_payload('unverified'));
check(is_wp_error($r)&&MI_Workspace_Client::$calls===0,'Unverified operative mode pretended to send');
echo "PASS: operative mode without a successful test fails visibly before enqueue.\n";

reset_bulk();$campaign=MI_Email_Jobs::enqueue(bulk_payload('campaign-priority'));MI_Spedizione_Email::spedisci_coda();
$p=bulk_payload('priority-test');$p['test_only']=true;$p['allow_operational']=false;$p['test_recipient']='chosen@example.invalid';
$proof=MI_Email_Jobs::enqueue($p);MI_Spedizione_Email::spedisci_coda();
check(MI_Email_Jobs::status($proof['job_id'])['sent']===1,'Explicit test waited behind mass campaign');
check(MI_Email_Jobs::status($campaign['job_id'])['sent']===10,'Priority test advanced mass cursor');
check(complete_job($campaign['job_id'])['sent']===198,'Campaign did not continue after priority test');
echo "PASS: new proof takes priority over remaining 188 recipients and mass campaign continues.\n";

reset_bulk();update_option('mi_modalita_spedizione_email','ANTEPRIMA');
$p=bulk_payload('stale-proof');$p['test_only']=true;$p['test_recipient']='chosen@example.invalid';$proof=MI_Email_Jobs::enqueue($p);
complete_job($proof['job_id']);$before=MI_Workspace_Client::$calls;
$wpdb->query("UPDATE wp_mi_email_outbox SET status='TEST_SENDING',attempts=5,processing_started_at='".gmdate('Y-m-d H:i:s',time()-16*60)."'");
MI_Spedizione_Email::spedisci_coda();
check($wpdb->get_var('SELECT status FROM wp_mi_email_outbox LIMIT 1')==='TEST_FAILED'&&MI_Workspace_Client::$calls===$before,'Stale proof remained sending in preview mode or was resent');
echo "PASS: fifth-attempt stale proof becomes visible failure even in preview mode, without resending.\n";
