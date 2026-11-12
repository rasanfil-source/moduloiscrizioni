<?php
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
class WP_Error { function __construct(public $code,private $message){} function get_error_message(){return $this->message;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function current_user_can($cap){return $cap==='manage_options'&&($GLOBALS['allowed']??true);}
function get_post($id){return $id===42?(object)['post_type'=>'mi_event','post_status'=>$GLOBALS['status']??'draft']:null;}
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';}
class MI_Spedizione_Email {static function modalita(){return $GLOBALS['mode']??'ANTEPRIMA';}}
class MI_Registration_Service {
 static function cancel_registration($id,$actor,$promote,$notify){
  if($actor!=='ADMIN_DEMO_CLEANUP'||$promote||$notify)throw new RuntimeException('Side effects in demo cleanup');
  $GLOBALS['calls'][]=$id;
  return $id===8?new WP_Error('failed','Prova fallita'):'CANCELLED';
 }
}
class DemoDatabase {
 public $prefix='wp_',$last_error='';
 function prepare($sql,...$args){if($args!==[42])throw new RuntimeException('Event scope');return $sql;}
 function get_results($sql,$mode){
  return [['id'=>7,'order_code'=>'DEMO-7'],['id'=>8,'order_code'=>'DEMO-8']];
 }
}
class DemoInnoDB extends DemoDatabase {
 private $db;
 function __construct(){
  $this->db=new mysqli('127.0.0.1','root','local-ledger-test-only','mi_ledger_test',33317);
  $this->db->query('CREATE TEMPORARY TABLE wp_mi_registrations (id INT PRIMARY KEY,event_id INT,order_code VARCHAR(30),status VARCHAR(30)) ENGINE=InnoDB');
  $this->db->query('CREATE TEMPORARY TABLE wp_mi_registration_events (registration_id INT,event_type VARCHAR(30),actor_label VARCHAR(30)) ENGINE=InnoDB');
  $this->db->query("INSERT INTO wp_mi_registrations VALUES (7,42,'DEMO-7','CONFIRMED'),(8,42,'DEMO-8','WAITLISTED'),(9,42,'DEMO-9','CANCELLED'),(10,42,'REAL-10','CONFIRMED'),(11,99,'DEMO-11','CONFIRMED'),(12,42,'REAL-12','CONFIRMED')");
  $this->db->query("INSERT INTO wp_mi_registration_events VALUES (7,'CREATED','admin_demo'),(8,'CREATED','admin_demo'),(9,'CREATED','admin_demo'),(10,'CREATED','public'),(11,'CREATED','admin_demo'),(12,'PAYMENT_RECORDED','admin_demo')");
 }
 function prepare($sql,...$args){return str_replace('%d',(string)(int)$args[0],$sql);}
 function get_results($sql,$mode){return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);}
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-admin.php';
$wpdb=getenv('MI_DEMO_TEST_DB')?new DemoInnoDB():new DemoDatabase();$calls=[];
$result=MI_Admin::cancel_demo_event(42);
if($calls!==[7,8]||$result!==['cancelled'=>1,'errors'=>['DEMO-8: Prova fallita']])throw new RuntimeException('Partial result lost');
$calls=[];$allowed=false;if(!is_wp_error(MI_Admin::cancel_demo_event(42)))throw new RuntimeException('Unauthorized cleanup');
$allowed=true;$status='publish';if(!is_wp_error(MI_Admin::cancel_demo_event(42)))throw new RuntimeException('Published event cleanup');
$status='draft';$mode='REALE';if(!is_wp_error(MI_Admin::cancel_demo_event(42)))throw new RuntimeException('Live email mode cleanup');
$mode='ANTEPRIMA';$wpdb->last_error='DB unavailable';if(!is_wp_error(MI_Admin::cancel_demo_event(42))||$calls)throw new RuntimeException('Cleanup after failed read');
echo "PASS: annullamento demo, permessi, anteprima ed errori parziali".(getenv('MI_DEMO_TEST_DB')?' con selezione reale InnoDB':'').".\n";
