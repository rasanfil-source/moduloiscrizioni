<?php
/** Validazione e, con MI_TRAVEL_TEST_PORT, integrazione su MariaDB locale sintetico. */
define('ABSPATH', dirname(__DIR__,2).'/.tmp/travel-wp/'); define('ARRAY_A','ARRAY_A'); define('MI_VERSION','test-travel');
define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600);
class WP_Error { function __construct(public $code,public $message,public $data=null){} function get_error_message(){return $this->message;} }
class WP_REST_Response { function __construct(public $data){} function header(...$args){} }
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',(string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_textarea_field($v){return sanitize_text_field($v);}
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function sanitize_email($v){return $v;}
function is_email($v){return (bool)filter_var($v,FILTER_VALIDATE_EMAIL);}
function wp_json_encode($v){return json_encode($v,JSON_UNESCAPED_UNICODE);}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function wp_date($format,$time=null,$zone=null){return (new DateTimeImmutable('@'.($time??time())))->setTimezone($zone??wp_timezone())->format($format);}
function current_time($format='mysql',$utc=false){return $format==='mysql'?gmdate('Y-m-d H:i:s'):wp_date($format);}
function remove_accents($text){return iconv('UTF-8','ASCII//TRANSLIT',$text);}
function wp_salt($key){return 'synthetic-travel-test-only';}
function home_url($path=''){return 'https://example.invalid'.$path;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_url($v){return esc_html($v);}
function get_the_title($id){return 'Evento sintetico';}
function get_post($id){return (object)['post_type'=>'event','post_status'=>'publish','post_title'=>'Evento sintetico'];}
function get_post_status($id){return 'publish';}
function get_post_meta($id,$key,...$args){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;return true;}
function get_option($key,$default=null){return $default;}
function update_option(...$args){return true;}
function wp_next_scheduled(...$args){return false;}
function wp_schedule_single_event(...$args){return true;}
function wp_list_pluck($rows,$key){return array_column($rows,$key);}
class MI_Event_Post_Type {const EVENT_TYPE='event';}
class MI_REST_Controller {const NAMESPACE='modulo-iscrizioni/v1';}
class MI_Field_Schema {static function validate_answers(...$args){return [];}static function relay_only_keys(...$args){return [];}static function normalize_phone($v){return $v;}}
class MI_Management_Service {
 static function lock_room_event($id){global $wpdb;if(false===$wpdb->query("INSERT INTO wp_mi_management_state (event_id) VALUES ($id) ON DUPLICATE KEY UPDATE event_id=$id"))throw new RuntimeException('lock');}
 static function auto_assign_rooms_locked($id){} static function room_types(){return [];}
}
class MI_Portal {static function participant_cancel_url($id,$token){return 'https://example.invalid/cancel/'.$id;}static function status_url(...$args){return 'https://example.invalid/status';}static function waitlist_offer_url($id,$token){return 'https://example.invalid/offer/'.$id;}}
class MI_Modello_Email {
 static function crea_istantanea_istituzionale($event,$subject,$preheader,$html,$text,$actions=[]){return ['attivo'=>true,'oggetto'=>$subject,'html'=>$html,'testo'=>$text];}
 static function valori_ordine(...$args){return [];}
 static function crea_istantanea(...$args){return ['attivo'=>true];}
 static function crea_istantanea_lista_attesa(...$args){return ['attivo'=>true];}
 static function crea_istantanea_nuova_iscrizione_segreteria(...$args){return ['attivo'=>true];}
 static function crea_istantanea_annullamento_partecipazione_iscritto(...$args){return ['attivo'=>true];}
 static function crea_istantanea_annullamento_partecipazione_segreteria(...$args){return ['attivo'=>true];}
 static function crea_istantanea_annullamento_iscrizione_iscritto(...$args){return ['attivo'=>true];}
 static function crea_istantanea_offerta_lista_attesa(...$args){return ['attivo'=>true];}
}
class MI_Spedizione_Email {static function stato_nuova_email($snapshot){return $snapshot['attivo']?'PENDING':'PREVIEW';}static function pianifica_spedizione(){}static function tenta_spedizione_immediata(){}static function email_da_spedire($v){return $v==='PENDING';}static function destinatario_evento($id){return 'segreteria@example.invalid';}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-amount.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-travel.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-registration-service.php';
function check_travel($condition,$message){if(!$condition)throw new RuntimeException($message);}
$deadline=wp_date('Y-m-d\TH:i',time()+4*86400);
$start=wp_date('Y-m-d\TH:i',time()+5*86400);
$event=['id'=>42,'title'=>'Evento sintetico','event_starts_at'=>$start,'travel'=>['enabled'=>true,'price_mode'=>'FIXED','price_cents'=>1200,'deadline'=>$deadline]];
$person=['ticket_type_code'=>'standard','ticket_index'=>1,'first_name'=>'Ada','last_name'=>'Esempio'];
function validated_travel($choice,$email='ada@example.invalid'){global $event,$person;return MI_Travel::validate_people($event,[$person],[array_merge($person,['travel'=>$choice])],$email);}
check_travel(is_wp_error(validated_travel([])),'Missing mode accepted');
check_travel(!is_wp_error(validated_travel(['mode'=>'OWN'],'')),'Own travel should not require email');
check_travel(is_wp_error(validated_travel(['mode'=>'PUBLIC'],'')),'Removed public transport mode was accepted');
check_travel(is_wp_error(validated_travel(['mode'=>'DRIVER','seats'=>-1])),'Negative seats accepted');
check_travel(!is_wp_error(validated_travel(['mode'=>'DRIVER','seats'=>0])),'Zero seats should not require route or price');
check_travel(is_wp_error(validated_travel(['mode'=>'DRIVER','seats'=>9])),'Nine seats accepted');
$driverChoice=['mode'=>'DRIVER','seats'=>1,'origin'=>'Roma, Piazza Esempio','departure'=>wp_date('d/m/Y H:i',time()+4*86400+7200),'price'=>'99'];
$driver=validated_travel($driverChoice);check_travel(!is_wp_error($driver)&&$driver[0]['travel']['price_cents']===1200,'Fixed contribution is not authoritative');
$driverAtEventTime=validated_travel(array_replace($driverChoice,['departure'=>'']));check_travel(!is_wp_error($driverAtEventTime)&&$driverAtEventTime[0]['travel']['departure_at']===gmdate('Y-m-d H:i:s',MI_Travel::local_timestamp($start)),'Blank departure should use event start');
$event['travel']['price_mode']='DRIVER';$driver=validated_travel(array_replace($driverChoice,['price'=>'8,50']));check_travel($driver[0]['travel']['price_cents']===850,'Driver contribution not parsed exactly');
check_travel(is_wp_error(validated_travel(array_replace($driverChoice,['price'=>'-1']))),'Negative contribution accepted');
$event['travel']['price_mode']='FIXED';
$needChoice=['mode'=>'NEED','fallback'=>'REQUIRED','driver_id'=>0,'waitlist'=>true];
$need=validated_travel($needChoice);check_travel(!is_wp_error($need)&&MI_Travel::pending_people($need),'Conditional travel was confirmed');
check_travel(!MI_Travel::pending_people(validated_travel(array_replace($needChoice,['fallback'=>'ANYWAY']))),'Independent participant suspended');
check_travel(is_wp_error(validated_travel(array_replace($needChoice,['waitlist'=>false]))),'Silent waitlist enrollment');
check_travel(is_wp_error(validated_travel($needChoice,'')),'Waitlist without email accepted');
check_travel(is_wp_error(validated_travel(['mode'=>[]])),'Nested travel data accepted');
check_travel(false===MI_Travel::local_timestamp('31/02/2027 09:00'),'Invalid calendar date accepted');
$fixed=MI_Travel::validate_config(['travel_enabled'=>1,'travel_price_mode'=>'FIXED','travel_price'=>'0','travel_deadline'=>$deadline],$start);check_travel(!is_wp_error($fixed)&&$fixed['price_cents']===0,'Free ride rejected');
$economic=MI_Registration_Service::riepilogo_economico(['economic_mode'=>'FULL_PAYMENT','payment_methods'=>['CASH']],9000,'TRAVEL_PENDING');check_travel($economic['initial_due_cents']===0,'Payment requested during travel wait');
$hashMethod=new ReflectionMethod(MI_Registration_Service::class,'registration_request_hash');
$hashBase=['tickets'=>['standard'=>1],'participants'=>[['ticket_type_code'=>'standard','ticket_index'=>1,'travel'=>['mode'=>'OWN']]],'travel_booking'=>['mode'=>'OWN']];
$hashOwn=$hashMethod->invoke(null,$hashBase);$hashRide=$hashMethod->invoke(null,array_replace($hashBase,['travel_booking'=>['mode'=>'NEED','fallback'=>'REQUIRED']]));
check_travel($hashOwn!==$hashRide,'Replay hash ignores the travel_booking choice used by validation');
$event['travel']['deadline']=wp_date('Y-m-d\TH:i',time()-60);
check_travel(!is_wp_error(validated_travel(['mode'=>'OWN'],'')),'Deadline must allow independent travel');
check_travel(is_wp_error(validated_travel($needChoice)),'Ride request after deadline accepted');
$event['travel']['deadline']=$deadline;
$party=[$person,array_replace($person,['ticket_index'=>2]),array_replace($person,['ticket_index'=>3])];
$group=MI_Travel::validate_people($event,$party,[],'ada@example.invalid',array_replace($driverChoice,['seats'=>2]));
check_travel(!is_wp_error($group)&&array_column(array_column($group,'travel'),'mode')===['DRIVER','OWN','OWN'],'First booking participant should be the internal default driver');
check_travel(array_sum(array_column(array_column($group,'travel'),'seats'))===2,'Offered seats duplicated per person');
$groupWithInjectedDriver=MI_Travel::validate_people($event,$party,[],'ada@example.invalid',array_replace($driverChoice,['driver_key'=>'standard:3','seats'=>2]));
check_travel(!is_wp_error($groupWithInjectedDriver)&&array_column(array_column($groupWithInjectedDriver,'travel'),'mode')===['DRIVER','OWN','OWN'],'Client-supplied driver should not override first participant');
$group=MI_Travel::validate_people($event,$party,[],'ada@example.invalid',$needChoice);
check_travel(!is_wp_error($group)&&count(array_filter($group,fn($p)=>$p['travel']['required_ride']===1))===3,'Not all booking members request a ride');
$splitChoice=array_replace($needChoice,['assignments'=>[['driver_id'=>101,'seats'=>1,'price_cents'=>1200],['driver_id'=>102,'seats'=>1,'price_cents'=>1200],['driver_id'=>103,'seats'=>1,'price_cents'=>1200]]]);
$splitGroup=MI_Travel::validate_people($event,$party,[],'ada@example.invalid',$splitChoice);
check_travel(!is_wp_error($splitGroup)&&array_column(array_column($splitGroup,'travel'),'driver_id')===[101,102,103],'Three single-seat offers should distribute across a booking of three');
$splitWithoutFallback=$splitChoice;unset($splitWithoutFallback['fallback']);$splitWithoutFallback=MI_Travel::validate_people($event,$party,[],'ada@example.invalid',$splitWithoutFallback);
check_travel(is_wp_error($splitWithoutFallback),'Assigned rides were accepted without an explicit fallback choice');
$splitAnyway=MI_Travel::validate_people($event,$party,[],'ada@example.invalid',array_replace($splitChoice,['fallback'=>'ANYWAY']));
check_travel(!is_wp_error($splitAnyway)&&count(array_filter($splitAnyway,fn($p)=>$p['travel']['required_ride']===1))===0,'Explicit autonomous fallback was not respected for already selected rides');
$partialGroup=MI_Travel::validate_people($event,$party,[],'ada@example.invalid',array_replace($needChoice,['assignments'=>[['driver_id'=>101,'seats'=>1,'price_cents'=>1200]]]));
check_travel(!is_wp_error($partialGroup)&&array_column(array_column($partialGroup,'travel'),'driver_id')===[101,null,null]&&count(array_filter($partialGroup,fn($p)=>$p['travel']['required_ride']===1))===3,'One selected seat should be assigned while the other booking members wait');
echo "PASS travel validation: modes, zero seats, fixed/driver contributions, consent, conditional payment and deadlines.\n";

if(!getenv('MI_TRAVEL_TEST_PORT'))exit;
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
class TravelDatabase {
 public $prefix='wp_',$last_error='',$insert_id=0; public $db;
 function __construct(){ $this->db=new mysqli('127.0.0.1','root','',null,(int)getenv('MI_TRAVEL_TEST_PORT'));$this->db->query('CREATE DATABASE IF NOT EXISTS mi_travel_synthetic');$this->db->select_db('mi_travel_synthetic');$this->db->set_charset('utf8mb4'); }
 function get_charset_collate(){return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';}
 function prepare($sql,...$args){foreach($args as $v)$sql=preg_replace_callback('/%[ds]/',fn($m)=>$m[0]==='%d'?(string)(int)$v:"'".$this->db->real_escape_string((string)$v)."'",$sql,1);return $sql;}
 function query($sql){$this->last_error='';try{$result=$this->db->query($sql);$this->insert_id=$this->db->insert_id;return $result===true?$this->db->affected_rows:$result;}catch(Throwable $e){$this->last_error=$e->getMessage();return false;}}
 function get_results($sql,$format=null){$r=$this->query($sql);return $r instanceof mysqli_result?$r->fetch_all(MYSQLI_ASSOC):[];}
 function get_row($sql,$format=null){return $this->get_results($sql)[0]??null;}
 function get_var($sql){$row=$this->get_row($sql);return $row?array_values($row)[0]:null;}
 function get_col($sql){return array_map(fn($r)=>array_values($r)[0],$this->get_results($sql));}
 function insert($table,$data,$formats=null){$keys=implode(',',array_keys($data));$values=implode(',',array_map(fn($v)=>$v===null?'NULL':"'".$this->db->real_escape_string((string)$v)."'",array_values($data)));return $this->query("INSERT INTO $table ($keys) VALUES ($values)");}
 function update($table,$data,$where,...$args){$set=implode(',',array_map(fn($key)=>$key.'='.($data[$key]===null?'NULL':"'".$this->db->real_escape_string((string)$data[$key])."'"),array_keys($data)));$filter=implode(' AND ',array_map(fn($key)=>$key."='".$this->db->real_escape_string((string)$where[$key])."'",array_keys($where)));return $this->query("UPDATE $table SET $set WHERE $filter");}
}
$wpdb=new TravelDatabase();
if(($argv[1]??'')==='worker'){
 $event=json_decode($wpdb->get_var('SELECT config_json FROM wp_mi_event_revisions WHERE event_id=42 LIMIT 1'),true);
 $GLOBALS['meta'][42]=['_mi_published_revision_id'=>1,'_mi_travel'=>$event['travel']];
 $payload=['buyer'=>['first_name'=>'Test','last_name'=>'Esempio','email'=>'test@example.invalid','phone'=>'+390000000000'],'tickets'=>['standard'=>1],'participants'=>[array_merge($person,['travel'=>['mode'=>'NEED','fallback'=>'ANYWAY','driver_id'=>(int)$argv[2],'price_cents'=>1200]])],'privacy_accepted'=>true];
 $result=MI_Registration_Service::create(42,$payload,bin2hex(random_bytes(16)),false,'CONCURRENT_TEST',true);
 echo json_encode(['worker_success'=>!is_wp_error($result),'message'=>is_wp_error($result)?$result->message:''])."\n";exit;
}
function dbDelta($sql){global $wpdb;if(false===$wpdb->query($sql))throw new RuntimeException($wpdb->last_error);}
// This database is dedicated to this test. Never connect the harness to a user database.
foreach($wpdb->get_col('SHOW TABLES')as$table)$wpdb->query("DROP TABLE `$table`");
if(!is_dir(ABSPATH.'wp-admin/includes'))mkdir(ABSPATH.'wp-admin/includes',0777,true);
file_put_contents(ABSPATH.'wp-admin/includes/upgrade.php',"<?php\n");
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php';
(new ReflectionMethod(MI_Activator::class,'create_tables'))->invoke(null);
$event+=['capacity'=>50,'waitlist_enabled'=>true,'options'=>[],'participant_fields'=>[],'participant_extra_scope'=>'ALL','pricing_mode'=>'FIXED','fixed_price_cents'=>2000,'economic_mode'=>'FULL_PAYMENT','payment_methods'=>['CASH'],'deposit_percentage'=>30,'opens_at'=>'','closes_at'=>wp_date('Y-m-d\TH:i',time()+3*86400),'privacy_url'=>'https://example.invalid/privacy','privacy_policy_version'=>'test','privacy_consent_id'=>'test','ticket_types'=>[['code'=>'standard','name'=>'Partecipazione','price_cents'=>2000,'max_per_order'=>20,'capacity'=>0]]];
$wpdb->insert('wp_mi_event_revisions',['event_id'=>42,'revision_number'=>1,'config_hash'=>str_repeat('a',64),'config_json'=>json_encode($event),'created_at'=>gmdate('Y-m-d H:i:s')]);
$GLOBALS['meta'][42]=['_mi_published_revision_id'=>$wpdb->insert_id,'_mi_travel'=>$event['travel'],'_mi_capacity'=>50,'_mi_waitlist_enabled'=>'1','_mi_event_starts_at'=>$start];
function create_travel_booking($choice,$index=1,$event_id=42){global $person;$payload=['buyer'=>['first_name'=>'Ada','last_name'=>'Esempio','email'=>'ada@example.invalid','phone'=>'+390000000000'],'tickets'=>['standard'=>1],'participants'=>[array_merge($person,['first_name'=>'Persona'.$index,'travel'=>$choice])],'privacy_accepted'=>true];$result=MI_Registration_Service::create($event_id,$payload,bin2hex(random_bytes(16)),false,'TEST',true);check_travel(!is_wp_error($result),'Create failed: '.(is_wp_error($result)?$result->message:''));return $result;}
function travel_row($order){global $wpdb;return $wpdb->get_row($wpdb->prepare('SELECT t.* FROM wp_mi_travel t JOIN wp_mi_registrations r ON r.id=t.registration_id WHERE r.order_code=%s',$order['order_code']));}
$driverBooking=create_travel_booking($driverChoice);$driverRow=travel_row($driverBooking);
check_travel($driverBooking['economic_summary']['total_cents']===2000,'Driver contribution entered event total');
$first=create_travel_booking($needChoice,2);$firstRow=travel_row($first);
check_travel($first['status']==='TRAVEL_PENDING'&&$firstRow['state']==='OFFERED','Conditional booking did not reserve ride');
$pendingEmail=$wpdb->get_row($wpdb->prepare("SELECT payload_json FROM wp_mi_email_outbox WHERE registration_id=%d AND template_type='REGISTRATION_CONFIRMATION' ORDER BY id DESC LIMIT 1",$firstRow['registration_id']));
$pendingSnapshot=json_decode($pendingEmail['payload_json']??'',true)['email_preview']??[];
check_travel(str_contains($pendingSnapshot['html']??'','mi_travel='.$firstRow['participant_id'])&&str_contains($pendingSnapshot['html']??'','/cancel/'.$firstRow['participant_id']),'Pending email does not provide separate travel-management and cancellation links');
$second=create_travel_booking($needChoice,3);$secondRow=travel_row($second);check_travel($secondRow['state']==='WAITING','Last car seat assigned twice');
check_travel(abs(strtotime($firstRow['offer_expires_at'].' UTC')-time()-86400)<8,'Proposal is not exactly 24 hours');
$counter=$wpdb->get_row('SELECT * FROM wp_mi_event_counters WHERE event_id=42');check_travel((int)$counter['confirmed_count']===3,'Travel pending must reserve event capacity');
$token=hash_hmac('sha256','mi-travel|'.$firstRow['participant_id'],wp_salt('auth'));
check_travel(is_wp_error(MI_Travel::decide($firstRow['participant_id'],'bad','accept',$driverRow['participant_id'],$firstRow['offer_expires_at'])),'Invalid token accepted');
$accept=MI_Travel::decide($firstRow['participant_id'],$token,'accept',$driverRow['participant_id'],$firstRow['offer_expires_at']);check_travel(true===$accept,'Ride accept failed: '.(is_wp_error($accept)?$accept->message:''));
$firstRow=travel_row($first);check_travel($firstRow['state']==='ASSIGNED','Accepted ride was not assigned');
$status=$wpdb->get_row($wpdb->prepare('SELECT * FROM wp_mi_registrations WHERE id=%d',$firstRow['registration_id']));check_travel($status['status']==='PENDING_PAYMENT'&&(int)$status['initial_due_cents']===2000,'Payment did not start after ride acceptance');
$driverToken=hash_hmac('sha256','mi-travel|'.$driverRow['participant_id'],wp_salt('auth'));
check_travel(true===MI_Travel::decide($driverRow['participant_id'],$driverToken,'withdraw'),'Withdraw failed');
$firstRow=travel_row($first);$status=$wpdb->get_row($wpdb->prepare('SELECT * FROM wp_mi_registrations WHERE id=%d',$firstRow['registration_id']));check_travel($firstRow['state']==='WAITING'&&$status['status']==='TRAVEL_PENDING'&&(int)$status['initial_due_cents']===0,'Driver withdrawal did not suspend conditional booking');
$newDriver=create_travel_booking($driverChoice,4);$firstRow=travel_row($first);check_travel($firstRow['state']==='OFFERED','Waiter was not matched after driver withdrawal');
$wpdb->update('wp_mi_travel',['offer_expires_at'=>gmdate('Y-m-d H:i:s',time()-1)],['participant_id'=>$firstRow['participant_id']]);MI_Travel::maintain(42);
check_travel(travel_row($first)['state']==='EXPIRED'&&travel_row($second)['state']==='OFFERED','Expired offer did not release seat to next waiter');
$response=MI_Travel::availability(['id'=>42]);check_travel($response instanceof WP_REST_Response&&count($response->data['rides'])===0,'Reserved seat exposed as available');
$booked=(int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_registrations');
$bad=MI_Registration_Service::create(42,['buyer'=>['first_name'=>'Test','last_name'=>'Esempio','email'=>'test@example.invalid','phone'=>'+390000000000'],'tickets'=>['standard'=>1],'participants'=>[array_merge($person,['travel'=>['mode'=>'NEED','fallback'=>'ANYWAY','driver_id'=>travel_row($newDriver)['participant_id'],'price_cents'=>1200]])],'privacy_accepted'=>true],bin2hex(random_bytes(16)),false,'TEST',true);
check_travel(is_wp_error($bad)&&(int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_registrations')===$booked,'Stale direct selection did not roll back booking');
$projection=MI_Travel::projection_fields($firstRow['participant_id'],42);check_travel(isset($projection['travel_state'])&&!str_contains(json_encode($projection),'travel_token'),'Projection missing fields or exposing private links');
$raceDriver=create_travel_booking($driverChoice,5);$raceId=travel_row($raceDriver)['participant_id'];
$jobs=[];
for($i=0;$i<2;$i++){
 $command=[PHP_BINARY,'-n','-d','extension_dir='.ini_get('extension_dir'),'-d','extension=mbstring','-d','extension=mysqli',__FILE__,'worker',(string)$raceId];
 $pipes=[];$proc=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true,'create_new_console'=>false]);check_travel(is_resource($proc),'Concurrency worker failed to start');fclose($pipes[0]);$jobs[]=[$proc,$pipes];
}
$successes=0;
foreach($jobs as[$proc,$pipes]){$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);check_travel($code===0,'Worker failed: '.$errors.$output);preg_match('/\{"worker_success"[^\r\n]+\}/',$output,$match);$result=json_decode($match[0]??'',true);check_travel(is_array($result),'Worker output missing');$successes+=(int)$result['worker_success'];}
check_travel($successes===1,'Concurrent requests did not produce exactly one seat assignment');
check_travel((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_travel WHERE driver_id=$raceId AND state='ASSIGNED'")===1,'Concurrent oversubscription');
// A declined proposal frees its seat, and returning to the queue goes to the end.
$secondRow=travel_row($second);$secondToken=hash_hmac('sha256','mi-travel|'.$secondRow['participant_id'],wp_salt('auth'));
check_travel(true===MI_Travel::decide($secondRow['participant_id'],$secondToken,'decline',$secondRow['driver_id'],$secondRow['offer_expires_at']),'Decline failed');
check_travel(travel_row($second)['state']==='DECLINED','Declined passenger automatically reoffered same seat');
check_travel(true===MI_Travel::decide($secondRow['participant_id'],$secondToken,'rejoin'),'Explicit rejoin failed');
// Deadline cancels only the participants who required a ride, releasing event capacity.
$GLOBALS['meta'][42]['_mi_travel']['deadline']=wp_date('Y-m-d\TH:i',time()-60);
$wpdb->query("UPDATE wp_mi_travel SET offer_expires_at='2000-01-01 00:00:00' WHERE state='OFFERED'");
MI_Travel::tick();
check_travel((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_registrations WHERE status='TRAVEL_PENDING'")===0,'Expired/declined conditional requests survived final deadline');
check_travel((int)$wpdb->get_var("SELECT confirmed_count FROM wp_mi_event_counters WHERE event_id=42")===4,'Final travel deadline did not release event places');
$event['id']=43;$event['capacity']=1;
$wpdb->insert('wp_mi_event_revisions',['event_id'=>43,'revision_number'=>1,'config_hash'=>str_repeat('b',64),'config_json'=>json_encode($event),'created_at'=>gmdate('Y-m-d H:i:s')]);
$GLOBALS['meta'][43]=['_mi_published_revision_id'=>$wpdb->insert_id,'_mi_travel'=>$event['travel'],'_mi_capacity'=>1,'_mi_waitlist_enabled'=>'1','_mi_event_starts_at'=>$start];
$eventDriver=create_travel_booking($driverChoice,7,43);$eventDriverRow=travel_row($eventDriver);
$eventWaiter=create_travel_booking(array_replace($needChoice,['driver_id'=>(int)$eventDriverRow['participant_id'],'price_cents'=>1200]),8,43);$eventWaiterRow=travel_row($eventWaiter);
check_travel($eventWaiter['status']==='WAITLISTED'&&$eventWaiterRow['state']==='WAITING'&&!$eventWaiterRow['driver_id'],'Event waitlist consumed car seat');
$eventAvailability=MI_Travel::availability(['id'=>43]);check_travel(count($eventAvailability->data['rides'])===1&&$eventAvailability->data['accepting_new_requests'],'Event waiter hid free car seat: '.json_encode($eventAvailability->data));
check_travel((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM wp_mi_email_outbox WHERE registration_id=%d AND template_type='TRAVEL_UPDATE' AND payload_json LIKE %s",$eventDriverRow['registration_id'],'%ha scelto un posto sulla tua auto%'))===0,'Event waitlist falsely notified a private driver before admission');
$cancel=MI_Registration_Service::cancel_registration($eventDriverRow['registration_id'],'TEST',true,false);check_travel(!is_wp_error($cancel),'Driver event cancellation failed');
$eventWaiterBooking=$wpdb->get_row($wpdb->prepare('SELECT * FROM wp_mi_registrations WHERE id=%d',$eventWaiterRow['registration_id']));check_travel($eventWaiterBooking['status']==='WAITLIST_OFFERED','Event waitlist did not advance');
$accepted=MI_Registration_Service::respond_waitlist_offer($eventWaiterRow['registration_id'],'','ACCEPT',false);
check_travel(is_wp_error($accepted),'Event offer accepted without token');
// The raw event-offer token is only in its email, not in SQL: use the production verifier with a known synthetic hash.
$offerToken=str_repeat('c',64);$wpdb->update('wp_mi_registrations',['waitlist_offer_token_hash'=>hash('sha256',$offerToken)],['id'=>$eventWaiterRow['registration_id']]);
$accepted=MI_Registration_Service::respond_waitlist_offer($eventWaiterRow['registration_id'],$offerToken,'ACCEPT');check_travel($accepted==='ACCEPTED','Event acceptance failed');
$eventWaiterBooking=$wpdb->get_row($wpdb->prepare('SELECT * FROM wp_mi_registrations WHERE id=%d',$eventWaiterRow['registration_id']));check_travel($eventWaiterBooking['status']==='TRAVEL_PENDING'&&(int)$eventWaiterBooking['initial_due_cents']===0,'Event acceptance bypassed required ride');
$event['id']=44;$event['capacity']=50;
$wpdb->insert('wp_mi_event_revisions',['event_id'=>44,'revision_number'=>1,'config_hash'=>str_repeat('d',64),'config_json'=>json_encode($event),'created_at'=>gmdate('Y-m-d H:i:s')]);
$GLOBALS['meta'][44]=['_mi_published_revision_id'=>$wpdb->insert_id,'_mi_travel'=>$event['travel'],'_mi_capacity'=>50,'_mi_waitlist_enabled'=>'1','_mi_event_starts_at'=>$start];
$resizable=create_travel_booking(array_replace($driverChoice,['seats'=>2]),9,44);$resizableRow=travel_row($resizable);
$zeroDriver=create_travel_booking(['mode'=>'DRIVER','seats'=>0],20,44);$zeroDriverRow=travel_row($zeroDriver);
$zeroToken=hash_hmac('sha256','mi-travel|'.$zeroDriverRow['participant_id'],wp_salt('auth'));
$missingDetails=MI_Travel::decide($zeroDriverRow['participant_id'],$zeroToken,'update_seats',0,'',1,['origin'=>'','departure'=>'','price'=>'']);
check_travel(is_wp_error($missingDetails),'Zero-seat offer reactivated without a valid departure location');
$validDetails=['origin'=>'Roma, Piazza Esempio','departure'=>wp_date('d/m/Y H:i',time()+4*86400+7200),'price'=>'12,00'];
check_travel(true===MI_Travel::decide($zeroDriverRow['participant_id'],$zeroToken,'update_seats',0,'',1,$validDetails),'Zero-seat offer could not be activated with valid details');
$zeroDriverRow=travel_row($zeroDriver);check_travel((int)$zeroDriverRow['seats']===1&&$zeroDriverRow['origin']==='Roma, Piazza Esempio'&&!empty($zeroDriverRow['departure_at']),'Valid route details were not stored on offer activation');
$resizableToken=hash_hmac('sha256','mi-travel|'.$resizableRow['participant_id'],wp_salt('auth'));
$rideChoice=array_replace($needChoice,['fallback'=>'ANYWAY','driver_id'=>(int)$resizableRow['participant_id'],'price_cents'=>1200]);
$rideOne=create_travel_booking($rideChoice,10,44);$rideOneRow=travel_row($rideOne);
check_travel($rideOneRow['state']==='ASSIGNED','Direct test passenger was not assigned');
check_travel(true===MI_Travel::decide($resizableRow['participant_id'],$resizableToken,'update_seats',0,'',1),'Driver could not reduce unoccupied offer');
check_travel((int)travel_row($resizable)['seats']===1,'Reduced seat count was not stored');
check_travel(true===MI_Travel::decide($resizableRow['participant_id'],$resizableToken,'update_seats',0,'',2),'Driver could not increase offer');
$rideTwo=create_travel_booking($rideChoice,11,44);$rideTwoRow=travel_row($rideTwo);
$blockedShrink=MI_Travel::decide($resizableRow['participant_id'],$resizableToken,'update_seats',0,'',1);
check_travel(is_wp_error($blockedShrink),'Driver reduced below assigned seats');
$rideTwoToken=hash_hmac('sha256','mi-travel|'.$rideTwoRow['participant_id'],wp_salt('auth'));
check_travel(true===MI_Travel::decide($rideTwoRow['participant_id'],$rideTwoToken,'own'),'Passenger could not release assigned ride');
$driverNotice=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM wp_mi_email_outbox WHERE registration_id=%d AND template_type='TRAVEL_UPDATE' AND payload_json LIKE %s",$resizableRow['registration_id'],'%ha rinunciato al passaggio%'));
check_travel($driverNotice>0,'Driver was not notified when assigned passenger chose independent travel');
check_travel(true===MI_Travel::decide($resizableRow['participant_id'],$resizableToken,'update_seats',0,'',1),'Driver could not reduce offer after seat was released');
$seatAudit=json_decode($wpdb->get_var($wpdb->prepare("SELECT detail_json FROM wp_mi_registration_events WHERE registration_id=%d AND event_type='TRAVEL_UPDATE_SEATS' ORDER BY id DESC LIMIT 1",$resizableRow['registration_id'])),true);
check_travel(($seatAudit['previous_seats']??null)===2&&($seatAudit['seats']??null)===1,'Seat change audit did not retain old and new counts');
check_travel(true===MI_Travel::decide($resizableRow['participant_id'],$resizableToken,'update_seats',0,'',2),'Driver could not reopen a seat for cancellation test');
$rideThree=create_travel_booking($rideChoice,12,44);$rideThreeRow=travel_row($rideThree);
$cancelledRide=MI_Registration_Service::cancel_participant($rideThreeRow['participant_id'],'TEST');
check_travel($cancelledRide==='CANCELLED','Assigned passenger cancellation failed');
$cancelNotice=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM wp_mi_email_outbox WHERE registration_id=%d AND template_type='TRAVEL_UPDATE' AND payload_json LIKE %s",$resizableRow['registration_id'],'%non partecipa più all%'));
check_travel($cancelNotice>0,'Driver was not notified when assigned passenger cancelled participation');
// When the driver withdraws within 24 hours of the travel deadline, each required passenger gets a final 24-hour choice window.
$event['id']=45;$event['capacity']=50;$event['travel']['deadline']=$deadline;
$wpdb->insert('wp_mi_event_revisions',['event_id'=>45,'revision_number'=>1,'config_hash'=>str_repeat('e',64),'config_json'=>json_encode($event),'created_at'=>gmdate('Y-m-d H:i:s')]);
$GLOBALS['meta'][45]=['_mi_published_revision_id'=>$wpdb->insert_id,'_mi_travel'=>$event['travel'],'_mi_capacity'=>50,'_mi_waitlist_enabled'=>'1','_mi_event_starts_at'=>$start];
$lateDriver=create_travel_booking(array_replace($driverChoice,['seats'=>2]),30,45);$lateDriverRow=travel_row($lateDriver);
$lateChoice=array_replace($needChoice,['fallback'=>'REQUIRED','driver_id'=>(int)$lateDriverRow['participant_id'],'price_cents'=>1200]);
$lateOwn=create_travel_booking($lateChoice,31,45);$lateOwnRow=travel_row($lateOwn);
$lateCancel=create_travel_booking($lateChoice,32,45);$lateCancelRow=travel_row($lateCancel);
check_travel($lateOwnRow['state']==='ASSIGNED'&&$lateCancelRow['state']==='ASSIGNED','Late-loss passengers were not initially assigned');
$nearDeadline=wp_date('Y-m-d\\TH:i',time()+12*3600);
$GLOBALS['meta'][45]['_mi_travel']['deadline']=$nearDeadline;
$lateDriverToken=hash_hmac('sha256','mi-travel|'.$lateDriverRow['participant_id'],wp_salt('auth'));
$lateIncrease=MI_Travel::decide($lateDriverRow['participant_id'],$lateDriverToken,'update_seats',0,'',3,['origin'=>'','departure'=>'','price'=>'']);
check_travel(is_wp_error($lateIncrease)&&(int)travel_row($lateDriver)['seats']===2,'Driver increased offer during the final 24-hour response window');
$lateDriverCancelled=MI_Registration_Service::cancel_participant($lateDriverRow['participant_id'],'TEST');
check_travel($lateDriverCancelled==='CANCELLED','Late-loss driver cancellation failed');
$lateOwnRow=travel_row($lateOwn);$lateCancelRow=travel_row($lateCancel);
check_travel($lateOwnRow['state']==='WAITING'&&$lateCancelRow['state']==='WAITING'&&!empty($lateOwnRow['decision_deadline_at'])&&!empty($lateCancelRow['decision_deadline_at']),'Required passengers did not receive the grace deadline');
$graceDeadline=strtotime($lateOwnRow['decision_deadline_at'].' UTC');
check_travel(abs($graceDeadline-time()-86400)<8,'Grace period was not 24 hours from driver loss');
check_travel((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM wp_mi_email_outbox WHERE registration_id=%d AND template_type='TRAVEL_UPDATE' AND payload_json LIKE %s",$lateOwnRow['registration_id'],'%organizzarti autonomamente%'))>0,'Passenger was not told how to resolve a lost ride');
$GLOBALS['meta'][45]['_mi_travel']['deadline']=wp_date('Y-m-d\\TH:i',time()-60);
$lateOwnToken=hash_hmac('sha256','mi-travel|'.$lateOwnRow['participant_id'],wp_salt('auth'));
check_travel(true===MI_Travel::decide($lateOwnRow['participant_id'],$lateOwnToken,'own'),'Passenger could not choose independent travel after assignment deadline');
$lateOwnBooking=$wpdb->get_row($wpdb->prepare('SELECT status FROM wp_mi_registrations WHERE id=%d',$lateOwnRow['registration_id']));
check_travel($lateOwnBooking['status']==='PENDING_PAYMENT','Independent-travel choice did not restore the event booking');
$wpdb->update('wp_mi_travel',['decision_deadline_at'=>gmdate('Y-m-d H:i:s',time()-1)],['participant_id'=>$lateCancelRow['participant_id']]);
MI_Travel::tick();
$lateCancelBooking=$wpdb->get_row($wpdb->prepare('SELECT status FROM wp_mi_registrations WHERE id=%d',$lateCancelRow['registration_id']));
check_travel($lateCancelBooking['status']==='CANCELLED'&&(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM wp_mi_email_outbox WHERE registration_id=%d AND template_type='PARTICIPANT_CANCEL_SECRETARIAT'",$lateCancelRow['registration_id']))===1,'Expired grace period did not cancel the participant and inform the secretariat');
// A group booking gets one driver notice and one passenger message with a personal link per person.
$event['id']=47;$event['capacity']=50;$event['travel']['deadline']=$deadline;
$wpdb->insert('wp_mi_event_revisions',['event_id'=>47,'revision_number'=>1,'config_hash'=>str_repeat('f',64),'config_json'=>json_encode($event),'created_at'=>gmdate('Y-m-d H:i:s')]);
$GLOBALS['meta'][47]=['_mi_published_revision_id'=>$wpdb->insert_id,'_mi_travel'=>$event['travel'],'_mi_capacity'=>50,'_mi_waitlist_enabled'=>'1','_mi_event_starts_at'=>$start];
$groupDriver=create_travel_booking(array_replace($driverChoice,['seats'=>2]),47,47);$groupDriverRow=travel_row($groupDriver);
$groupPayload=['buyer'=>['first_name'=>'Ada','last_name'=>'Esempio','email'=>'ada@example.invalid','phone'=>'+390000000000'],'tickets'=>['standard'=>2],'participants'=>[array_replace($person,['first_name'=>'Persona A','last_name'=>'Esempio','ticket_index'=>1]),array_replace($person,['first_name'=>'Persona B','last_name'=>'Esempio','ticket_index'=>2])],'travel_booking'=>['mode'=>'NEED','fallback'=>'ANYWAY','assignments'=>[['driver_id'=>(int)$groupDriverRow['participant_id'],'seats'=>2,'price_cents'=>1200]]],'privacy_accepted'=>true];
$groupBooking=MI_Registration_Service::create(47,$groupPayload,bin2hex(random_bytes(16)),false,'TEST',true);check_travel(!is_wp_error($groupBooking),'Group travel registration failed');
$groupBookingRow=travel_row($groupBooking);$groupPassengerNotices=$wpdb->get_results($wpdb->prepare("SELECT payload_json FROM wp_mi_email_outbox WHERE registration_id=%d AND template_type='TRAVEL_UPDATE'",$groupBookingRow['registration_id']));
check_travel(count($groupPassengerNotices)===1&&substr_count(json_decode($groupPassengerNotices[0]['payload_json'],true)['email_preview']['html']??'','mi_travel=')===2,'Group passengers received duplicate notices or lack individual links');
$groupDriverNotices=$wpdb->get_results($wpdb->prepare("SELECT payload_json FROM wp_mi_email_outbox WHERE registration_id=%d AND template_type='TRAVEL_UPDATE' AND payload_json LIKE %s",$groupDriverRow['registration_id'],'%Persona A%'));
check_travel(count($groupDriverNotices)===1&&str_contains($groupDriverNotices[0]['payload_json'],'Persona B'),'Driver did not receive a single combined group notice');
echo "PASS travel offer edit, committed-seat guard, audit trail, and driver notices for passenger release/cancellation.\n";
echo "PASS MariaDB travel integration: production schema and registration, last seat, full 24h, invalid token, acceptance/payment, driver withdrawal, expiry/FIFO, availability and transaction rollback.\n";
echo "PASS concurrent last-seat requests, decline/rejoin and final conditional-participation cancellation.\n";
echo "PASS independent event/car waitlists, driver cancellation, event offer token and conditional state on event acceptance.\n";
