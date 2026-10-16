<?php
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
class WP_Error {function __construct(public $code,public $message){}}
function get_current_user_id(){return 7;}function wp_json_encode($v){return json_encode($v);}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}function sanitize_textarea_field($v){return sanitize_text_field($v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}function absint($v){return abs((int)$v);}
function get_post_meta($id,$key,$single=true){return $key==='_mi_custom_participant_fields'?[]:'';}
function current_time($f,$gmt=false){return '2026-10-04 12:00:00';}
class MI_Portal_Management {static function allowed(){return true;}}
class MI_Access {static function can_access_event($id){return $id===42;}}
class MI_Registration_Service {static function mark_workspace_changed_locked($id){}static function append_registration_event(...$args){return true;}static function accoda_iscrizione_workspace($id){}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-service.php';
class RoomAuditDB {
 public $prefix='wp_',$last_error='',$people=[],$regs=[],$committed=false,$backup;
 function __construct(){foreach([1=>['S1','alloggio-singola',10000],2=>['DM1','alloggio-doppia-matrimoniale',6000]] as $id=>[$room,$option,$price]){
  $this->regs[$id]=['id'=>$id,'event_id'=>42,'order_code'=>'DEMO'.$id,'status'=>'CONFIRMED','buyer_first_name'=>'Persona','buyer_last_name'=>'Demo','snapshot_json'=>'{}','total_cents'=>$price];
  $this->people[$id]=['id'=>$id,'registration_id'=>$id,'first_name'=>'Persona','last_name'=>'Demo'.$id,'status'=>'ACTIVE','room_code'=>$room,'extra_json'=>'{}','options_json'=>json_encode([['code'=>$option,'quantity'=>1,'unit_price_cents'=>$price]])];
 }}
 function prepare($sql,...$args){return [$sql,$args];}
 function query($q){if($q==='START TRANSACTION')$this->backup=$this->people;if($q==='ROLLBACK')$this->people=$this->backup;if($q==='COMMIT')$this->committed=true;return 1;}
 function get_row($q,$f){[$sql,$args]=$q;if(str_contains($sql,'mi_registrations')){foreach($this->regs as $r)if($r['order_code']===$args[1])return $r;}return null;}
 function get_results($q,$f){[$sql,$args]=$q;
  if(str_contains($sql,'mi_registration_events'))return [];
  if(str_contains($sql,'mi_rooms'))return [['code'=>'S1','name'=>'Singola','capacity'=>1],['code'=>'DM1','name'=>'Matrimoniale','capacity'=>2]];
  if(str_contains($sql,'GROUP BY p.room_code')){$counts=[];foreach($this->people as $p)$counts[$p['room_code']]=($counts[$p['room_code']]??0)+1;return array_map(fn($code,$n)=>['room_code'=>$code,'occupied'=>$n],array_keys($counts),array_values($counts));}
  if(str_contains($sql,'mi_participants'))return [$this->people[$args[0]]];
  throw new RuntimeException('Unhandled SQL: '.$sql);
 }
 function update($table,$data,$where){$this->people[$where['id']]=array_replace($this->people[$where['id']],$data);return 1;}
 function insert($table,$data){return 1;}
}

function assert_room($ok,$message){if(!$ok)throw new RuntimeException($message);}
function swap_rooms($source='ROOM_SWAP'){
 global $wpdb;
 return MI_Management_Service::save_sheet(42,[['order_code'=>'DEMO1','number'=>1,'key'=>'room','before'=>$wpdb->people[1]['room_code'],'after'=>$wpdb->people[2]['room_code']],['order_code'=>'DEMO2','number'=>1,'key'=>'room','before'=>$wpdb->people[2]['room_code'],'after'=>$wpdb->people[1]['room_code']]],'wp_7_12345678-1234-4234-8234-123456789abc',$source);
}
foreach(['ROOM_SWAP','SHEET_SYNC'] as $source){
 $wpdb=new RoomAuditDB();$before=$wpdb->people;$r=swap_rooms($source);
 assert_room(!empty($r['rejected'])&&str_contains($r['message'],'incompatibile'),'Cross-type swap accepted');
 assert_room($wpdb->people===$before&&!$wpdb->committed,'Rejected swap changed people');
}
// First update succeeds, second fails: the entire swap must roll back.
$wpdb=new RoomAuditDB();$wpdb->people[1]['options_json']='[]';$before=$wpdb->people;$r=swap_rooms();
assert_room(!empty($r['rejected'])&&$wpdb->people===$before,'Second-person failure left a partial swap');
// Generic legacy inventory is intentionally supported without typed services.
$wpdb=new RoomAuditDB();foreach($wpdb->people as &$p)$p['options_json']='[]';unset($p);
$r=swap_rooms();assert_room(!empty($r['saved'])&&$wpdb->committed,'Legacy swap rejected');
// Same-type full rooms: transient overcapacity must not block an atomic swap.
class SameTypeDB extends RoomAuditDB {
 function get_results($q,$f){if(str_contains($q[0],'mi_rooms'))return [['code'=>'S1','name'=>'Singola 1','capacity'=>1],['code'=>'S2','name'=>'Singola 2','capacity'=>1]];return parent::get_results($q,$f);}
}
$wpdb=new SameTypeDB();$wpdb->people[2]['room_code']='S2';$wpdb->people[2]['options_json']=$wpdb->people[1]['options_json'];
$r=swap_rooms();assert_room(!empty($r['saved'])&&$wpdb->people[1]['room_code']==='S2'&&$wpdb->people[2]['room_code']==='S1','Compatible full-room swap rejected');
echo "PASS: incompatible swap/import, atomic rollback, legacy rooms and compatible full-room swaps.\n";
