<?php
// Isolated database fixture on localhost:33317; no WordPress/Google network access.
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
$fixture=file_get_contents(__DIR__.'/innodb-fixture.php');
eval(substr($fixture,strpos($fixture,'class DatabaseAdapter')));
class DeletionDatabase extends DatabaseAdapter {
 public $posts='wp_posts',$postmeta='wp_postmeta',$options='wp_options',$fail='';
 function get_col($sql){return array_map(fn($r)=>reset($r),$this->get_results($sql,ARRAY_A));}
 function query($sql){if($this->fail&&str_contains($sql,$this->fail)){$this->fail='';$this->last_error='Injected failure';return false;}return parent::query($sql);}
}
class WP_Error {function __construct(public $code,public $message){}function get_error_message(){return $this->message;}}
class MI_Access {static function is_suspended(){return false;}static function is_global_manager(){return true;}static function can_access_event($id){return true;}}
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';}
class MI_Workspace_Client {static $fail=true;static $calls=0;static function request($action,$payload){self::$calls++;return self::$fail?new WP_Error('google','Offline'):['ok'=>true,'complete'=>true,'sheet_url'=>''];}}
function absint($v){return abs((int)$v);}function is_wp_error($v){return $v instanceof WP_Error;}
function is_user_logged_in(){return true;}function current_user_can($cap){global $allowed;return $allowed;}
function get_current_user_id(){return 7;}function wp_cache_delete(...$args){}function clean_post_cache($id){}
function wp_json_encode($v){return json_encode($v);}function maybe_unserialize($v){return $v;}
function get_option($k,$default=false){global $options;return $options[$k]??$default;}
function update_option($k,$v,...$args){global $options;$options[$k]=$v;return true;}
function get_post($id){global $posts;return $posts[$id]??null;}function get_post_type($id){return get_post($id)->post_type??null;}
function get_post_meta($id,$key=null,$single=false){global $meta;return $key?($meta[$id][$key]??''):($meta[$id]??[]);}
function wp_delete_post($id,$force){global $posts,$meta;unset($posts[$id],$meta[$id]);return true;}
function get_users($args){return [7];}function get_user_meta($id,$key,$single){global $scopes;return $scopes[$id]??[];}
function update_user_meta($id,$key,$value){global $scopes;$scopes[$id]=$value;return true;}
function check($yes,$message){if(!$yes)throw new RuntimeException($message);}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-event-deletion.php';
$wpdb=new DeletionDatabase();
if (($argv[1]??'')==='worker') { $result=MI_Event_Deletion::enter(42,true); check(is_wp_error($result)&&$result->code==='mi_event_busy','Concurrent request entered leased event'); echo 'Concurrent lease blocked'; exit; }
$wpdb->query('CREATE DATABASE IF NOT EXISTS mi_deletion_test');$wpdb->db->select_db('mi_deletion_test');
$allowed=true;$options=[];$meta=[];$scopes=[7=>[42,43]];$posts=[];
foreach([42,43]as$id)$posts[$id]=(object)['ID'=>$id,'post_type'=>'mi_event','post_status'=>'draft','post_title'=>'Evento '.$id,'post_modified_gmt'=>'2026-09-09 00:00:00'];
$schema=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php');
foreach(['registrations'=>'registrations','items'=>'registration_items','participants'=>'participants','counters'=>'event_counters','ticket_counters'=>'ticket_counters','event_revisions'=>'event_revisions','registration_events'=>'registration_events','outbox'=>'email_outbox','payments'=>'payments','rooms'=>'rooms','management_state'=>'management_state','management_requests'=>'management_requests']as$var=>$name){
 preg_match('/CREATE TABLE \{\$'.$var.'\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);check(!empty($m),'Schema missing '.$name);
 $wpdb->query('DROP TABLE IF EXISTS wp_mi_'.$name);check(false!==$wpdb->query('CREATE TABLE wp_mi_'.$name.' ('.$m[1].') ENGINE=InnoDB'),$wpdb->last_error);
}
$wpdb->query('DROP TABLE IF EXISTS wp_mi_booking_codes');$wpdb->query('CREATE TABLE wp_mi_booking_codes(event_id BIGINT PRIMARY KEY,prefix VARCHAR(16),sequence BIGINT) ENGINE=InnoDB');
foreach([42,43]as$id){
 check(false!==$wpdb->query("INSERT INTO wp_mi_registrations (id,order_code,event_id,status,buyer_first_name,buyer_last_name,buyer_email,buyer_phone,total_qty,total_cents,initial_due_cents,idempotency_key,created_at,snapshot_json) VALUES ($id,'TEST$id',$id,'CONFIRMED','Test','Locale','','',1,100,100,'key$id',NOW(),'{}')"),$wpdb->last_error);
 $wpdb->query("INSERT INTO wp_mi_participants (id,registration_id,first_name,last_name,extra_json) VALUES ($id,$id,'Test','Persona','{}')");
 $wpdb->query("INSERT INTO wp_mi_payments (id,registration_id,effective_at,amount_cents,payment_source,created_at) VALUES ($id,$id,NOW(),100,'CASH',NOW())");
 $wpdb->query("INSERT INTO wp_mi_rooms VALUES ($id,'A','Camera',2)");
 $wpdb->query("INSERT INTO wp_mi_email_outbox (registration_id,recipient,template_type,payload_json,status,created_at) VALUES (0,'test@example.invalid','EVENT_MANAGER_READY','{\"event_id\":$id}','PREVIEW',NOW())");
}
$request='12345678-1234-4234-8234-123456789abc';$preview=MI_Event_Deletion::preview(42);
$allowed=false;check(is_wp_error(MI_Event_Deletion::begin(42,$preview['fingerprint'],$request,'trash','Evento 42',true)),'Permissions bypassed');$allowed=true;
$wpdb->query('UPDATE wp_mi_payments SET amount_cents=200 WHERE id=42');
check(is_wp_error(MI_Event_Deletion::begin(42,$preview['fingerprint'],$request,'trash','Evento 42',true)),'Stale preview accepted');
$preview=MI_Event_Deletion::preview(42);
check(is_wp_error(MI_Event_Deletion::begin(42,$preview['fingerprint'],$request,'trash','Wrong title',true)),'Wrong title accepted');
check(!is_wp_error(MI_Event_Deletion::begin(42,$preview['fingerprint'],$request,'trash','Evento 42',true)),'Cannot start');
check(is_wp_error(MI_Event_Deletion::enter(42)),'Writes allowed after deletion began');
$pipes=[];$proc=proc_open([PHP_BINARY,'-d','extension_dir='.dirname(PHP_BINARY).'/ext','-d','extension=mysqli',__FILE__,'worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0&&str_contains($out,'blocked'),'Concurrent worker: '.$out.$err);
check(is_wp_error(MI_Event_Deletion::advance(42)),'Offline Google accepted');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_registrations')===2,'SQL removed before Google');
MI_Workspace_Client::$fail=false;$wpdb->fail='DELETE c FROM wp_mi_payments';
check(is_wp_error(MI_Event_Deletion::advance(42)),'SQL failure hidden');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_participants')===2,'SQL rollback failed');
$calls=MI_Workspace_Client::$calls;
$result=MI_Event_Deletion::advance(42);check(!is_wp_error($result)&&$result['stage']==='done','Retry did not finish');
check(MI_Workspace_Client::$calls===$calls,'Repeated successful Google phase');
foreach(['registrations','participants','payments','rooms','email_outbox']as$table)check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_'.$table)===1,'Wrong cleanup '.$table);
check(!isset($posts[42])&&isset($posts[43]),'Wrong post removed');check($scopes[7]===[43],'Operator scope incorrect');
check(MI_Event_Deletion::advance(42)['stage']==='done','Retry not idempotent');
check(is_wp_error(MI_Event_Deletion::enter(42)),'Deleted event accepts delayed write');
echo "Eliminazione InnoDB: permessi, riepilogo obsoleto, titolo, blocco scritture, Google offline, rollback SQL, ripresa, isolamento e retry verificati.\n";
