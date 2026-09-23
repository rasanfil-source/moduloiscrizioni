<?php
// Fault injection around the real registration service. No remote services or personal data.
define('ABSPATH', __DIR__); define('ARRAY_A', 'ARRAY_A'); define('MI_VERSION', 'test');
define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600);
class WP_Error { public function __construct(public $code, public $message, public $data=null) {} }
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower((string)$v);}
function sanitize_text_field($v){return trim((string)$v);}
function sanitize_textarea_field($v){return trim((string)$v);}
function sanitize_email($v){return $v;}
function is_email($v){return str_contains($v,'@');}
function wp_json_encode($v){return json_encode($v);}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function current_time(...$args){return gmdate('Y-m-d H:i:s');}
function get_the_title($id){return 'Evento sintetico';}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function get_post_status($id){return 'publish';}
function get_post($id){return (object)['post_type'=>'event','post_status'=>'publish'];}
function wp_list_pluck($rows,$key){return array_column($rows,$key);}
function get_post_meta($id,$key,...$args){return $key==='_mi_event_cancellation_job'&&!empty($GLOBALS['cancellation_pending'])?['recipients'=>[]]:'';}
function wp_next_scheduled(...$args){return false;}
function wp_schedule_single_event(...$args){if($GLOBALS['fail_schedule'])throw new RuntimeException('cron unavailable');}
class MI_Event_Post_Type {const EVENT_TYPE='event';}
class MI_Field_Schema {
 static function relay_only_keys($fields){return [];}
 static function validate_answers(...$args){return [];}
 static function normalize_phone($value){return $value;}
}
class MI_Portal {static function participant_cancel_url(...$args){return 'https://example.invalid/cancel';}}
class MI_Modello_Email {
 static function valori_ordine(...$args){return [];}
 static function crea_istantanea(...$args){return [];}
 static function crea_istantanea_nuova_iscrizione_segreteria(...$args){return [];}
 static function crea_istantanea_annullamento_partecipazione_segreteria(...$args){return [];}
 static function crea_istantanea_annullamento_iscrizione_iscritto(...$args){return [];}
 static function crea_istantanea_istituzionale(...$args){return [];}
}
class MI_Spedizione_Email {
 static function stato_nuova_email($v){return 'PENDING';}
 static function destinatario_evento($id){return 'test@example.invalid';}
 static function email_da_spedire($v){return $v==='PENDING';}
 static function pianifica_spedizione(){if($GLOBALS['fail_schedule'])throw new RuntimeException('mail cron unavailable');}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-people.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-registration-service.php';

class FaultDatabase {
 public $prefix='wp_', $last_error='', $insert_id=0, $failure='', $commits=0, $writes=0, $registration=null, $snapshot=null;
 function prepare($sql,...$args){foreach($args as $v)$sql=preg_replace('/%[ds]/',is_int($v)?(string)$v:"'".$v."'",$sql,1);return $sql;}
 function query($sql){
  $this->last_error='';
  if($sql===$this->failure){$this->last_error='injected';return false;}
  if($sql==='START TRANSACTION')$this->snapshot=[$this->registration,$this->writes];
  elseif($sql==='ROLLBACK'&&$this->snapshot!==null){[$this->registration,$this->writes]=$this->snapshot;$this->snapshot=null;}
  elseif($sql==='COMMIT'){$this->commits++;$this->snapshot=null;}
  elseif(str_starts_with($sql,'UPDATE'))$this->writes++;
  return 1;
 }
 function get_row($sql,$mode){
  $this->last_error='';
  if(str_contains($sql,'mi_event_revisions'))return ['id'=>1,'revision_number'=>1,'config_hash'=>'test','config_json'=>json_encode($GLOBALS['event'])];
  if(str_contains($sql,'mi_booking_codes'))return ['prefix'=>'TEST','sequence'=>0];
  if($this->failure==='concurrent'&&str_contains($sql,'mi_event_counters')&&str_contains($sql,'FOR UPDATE')){
   $this->registration=['id'=>7,'order_code'=>'TEST7','status'=>'CONFIRMED','workspace_status'=>'SYNCED','economic_mode'=>'REGISTRATION_ONLY','total_cents'=>0,'initial_due_cents'=>0,'balance_cents'=>0,'payment_methods_json'=>'[]'];
   $this->snapshot=[$this->registration,0];
   return ['confirmed_count'=>10,'waitlisted_count'=>0];
  }
  if($this->failure==='event_lock'&&str_contains($sql,'mi_event_counters')&&str_contains($sql,'FOR UPDATE')){$this->last_error='injected';return null;}
  if(str_contains($sql,'mi_ticket_counters')&&str_contains($sql,'FOR UPDATE')&&$this->failure==='ticket_lock'){$this->last_error='injected';return null;}
  if(str_contains($sql,'mi_event_counters')||str_contains($sql,'mi_ticket_counters'))return ['event_id'=>42,'confirmed_count'=>0,'waitlisted_count'=>2];
  if(str_contains($sql,'mi_participants'))return ['id'=>1,'registration_id'=>1,'ticket_type_code'=>'a','first_name'=>'Persona','last_name'=>'Test','status'=>'ACTIVE'];
  if(str_contains($sql,'mi_registrations'))return $this->registration;
  throw new RuntimeException('Unexpected row query: '.$sql);
 }
 function get_results($sql,$mode){
  $this->last_error='';
  if(str_contains($sql,'mi_ticket_counters')){if($this->failure==='type_read'&&str_contains($sql,'FOR UPDATE'))$this->last_error='injected';return [];}
  if(str_contains($sql,'mi_registrations')){if($this->failure==='candidate_read')$this->last_error='injected';return [];}
  if(str_contains($sql,'mi_participants')){
   if($this->failure==='people_read'){$this->last_error='injected';return [];}
   return [['ticket_type_code'=>'a','quantity'=>2]];
  }
  throw new RuntimeException('Unexpected list query: '.$sql);
 }
 function get_var($sql){
  $this->last_error='';
  if(str_contains($sql,'COUNT(*)')){if($this->failure==='people_count'){$this->last_error='injected';return null;}return 1;}
  return 42;
 }
 function insert($table,$data,...$args){if($table==='wp_mi_email_outbox'&&strlen($data['template_type'])>40)return false;if($this->failure==='outbox'&&$table==='wp_mi_email_outbox')return false;$this->writes++;$this->insert_id++;
  if($table==='wp_mi_registrations')$this->registration=$data+['id'=>$this->insert_id,'workspace_status'=>'PENDING'];return 1;
 }
 function update($table,$data,...$args){$this->last_error='';$this->writes++;if($table==='wp_mi_registrations')$this->registration=array_replace($this->registration,$data);return 1;}
}
$event=['id'=>42,'title'=>'Evento sintetico','capacity'=>10,'opens_at'=>'','closes_at'=>'2099-01-01T12:00','pricing_mode'=>'ZERO','economic_mode'=>'REGISTRATION_ONLY','waitlist_enabled'=>false,'ticket_types'=>[['code'=>'a','name'=>'Quota','price_cents'=>0,'max_per_order'=>4,'capacity'=>10]],'participant_fields'=>[],'options'=>[],'privacy_url'=>'https://example.invalid/privacy','privacy_policy_version'=>'1','privacy_consent_id'=>'privacy-test'];
$payload=['tickets'=>['a'=>1],'participants'=>[['ticket_type_code'=>'a','ticket_index'=>1,'first_name'=>'Persona','last_name'=>'Test']],'buyer'=>['first_name'=>'Persona','last_name'=>'Test','email'=>'test@example.invalid','phone'=>'+39 3330000000'],'privacy_accepted'=>true];
$stored=['id'=>1,'event_id'=>42,'status'=>'WAITLISTED','capacity_released_at'=>null,'total_qty'=>2,'economic_mode'=>'REGISTRATION_ONLY','order_code'=>'TEST1','buyer_first_name'=>'Persona','buyer_last_name'=>'Test','buyer_email'=>'test@example.invalid'];
$failures=[];
function check_case($condition,$label){global $failures;if(!$condition)$failures[]=$label;echo ($condition?'PASS: ':'FAIL: ').$label."\n";}
foreach(['START TRANSACTION','COMMIT','ticket_lock','scheduler','none'] as $failure){
 $wpdb=new FaultDatabase();$wpdb->failure=$failure;$GLOBALS['fail_schedule']=$failure==='scheduler';
 $result=MI_Registration_Service::create(42,$payload,'synthetic-request-0001',false,'TEST',true);
 $success=in_array($failure,['scheduler','none'],true);
 check_case($success?!is_wp_error($result)&&$wpdb->commits===1:is_wp_error($result)&&$wpdb->writes===0,'create '.$failure.(is_wp_error($result)?' ['.$result->code.']':''));
}
$transition=new ReflectionMethod(MI_Registration_Service::class,'transition_registration_status');
$wpdb=new FaultDatabase();$wpdb->failure='concurrent';$GLOBALS['fail_schedule']=false;
$result=MI_Registration_Service::create(42,$payload,'synthetic-request-0001',false,'TEST',true);
check_case(!is_wp_error($result)&&!empty($result['replayed'])&&$result['order_code']==='TEST7'&&$wpdb->writes===0,'concurrent replay after last seat was committed');
foreach(['START TRANSACTION','COMMIT','people_read','event_lock','ticket_lock','scheduler','none'] as $failure){
 $wpdb=new FaultDatabase();$wpdb->registration=$stored;$wpdb->failure=$failure;$GLOBALS['fail_schedule']=$failure==='scheduler';
 $result=$transition->invoke(null,1,'CANCELLED','TEST',false);
 $success=in_array($failure,['scheduler','none'],true);
 check_case($success?$result==='CANCELLED'&&$wpdb->commits===1:is_wp_error($result)&&$wpdb->writes===0,'cancel booking '.$failure);
}
foreach(['START TRANSACTION','COMMIT','people_count','event_lock','ticket_lock','scheduler','none'] as $failure){
 $wpdb=new FaultDatabase();$wpdb->registration=$stored;$wpdb->failure=$failure;$GLOBALS['fail_schedule']=$failure==='scheduler';
 $result=MI_Registration_Service::cancel_participant(1,'TEST');
 $success=in_array($failure,['scheduler','none'],true);
 check_case($success?$result==='CANCELLED'&&$wpdb->commits===1:is_wp_error($result)&&$wpdb->writes===0,'cancel participant '.$failure);
}
foreach(['START TRANSACTION','COMMIT','people_read','scheduler','none'] as $failure){
 $wpdb=new FaultDatabase();$wpdb->registration=array_replace($stored,['status'=>'WAITLIST_OFFERED','waitlist_offer_expires_at'=>'2099-01-01 12:00:00']);
 $wpdb->failure=$failure;$GLOBALS['fail_schedule']=$failure==='scheduler';
 $result=MI_Registration_Service::respond_waitlist_offer(1,'','DECLINE',true);
 $success=in_array($failure,['scheduler','none'],true);
 check_case($success?$result==='DECLINED'&&$wpdb->commits===1:is_wp_error($result)&&$wpdb->writes===0,'waitlist decline '.$failure);
}
$wpdb=new FaultDatabase();$wpdb->registration=$stored;$wpdb->failure='outbox';$GLOBALS['fail_schedule']=false;
$result=MI_Registration_Service::cancel_registration(1,'TEST',false,true);
check_case(is_wp_error($result)&&$wpdb->writes===0&&$wpdb->registration['status']==='WAITLISTED','cancellation outbox failure rolls back before commit');
$wpdb=new FaultDatabase();$wpdb->registration=$stored;
$result=MI_Registration_Service::cancel_registration(1,'TEST',false,true);$writes=$wpdb->writes;
check_case($result==='CANCELLED'&&MI_Registration_Service::cancel_registration(1,'TEST',false,true)==='CANCELLED'&&$writes===$wpdb->writes,'cancellation and notifications commit once');
$event['waitlist_enabled']=true;
foreach(['type_read','candidate_read'] as $failure){
 $wpdb=new FaultDatabase();$wpdb->failure=$failure;$caught=false;
 try{(new ReflectionMethod(MI_Registration_Service::class,'promote_waitlisted_locked'))->invoke(null,42,current_time('mysql'));}catch(RuntimeException $e){$caught=true;}
 check_case($caught&&$wpdb->writes===0,'waitlist promotion rejects '.$failure);
}
$GLOBALS['cancellation_pending']=true;$wpdb=new FaultDatabase();
$result=MI_Registration_Service::create(42,$payload,'synthetic-request-0001',false,'TEST',true);
check_case(is_wp_error($result)&&$result->code==='mi_event_cancelled'&&$wpdb->writes===0,'pending cancellation blocks new registrations');
$wpdb->registration=$stored;
$result=$transition->invoke(null,1,'EXPIRED','SYSTEM_CRON');
check_case(is_wp_error($result)&&$wpdb->writes===0,'pending cancellation prevents expiry from losing recipients');
$result=(new ReflectionMethod(MI_Registration_Service::class,'promote_waitlisted_locked'))->invoke(null,42,current_time('mysql'));
check_case($result===[]&&$wpdb->writes===0,'pending cancellation blocks waitlist promotions');
exit($failures?1:0);
