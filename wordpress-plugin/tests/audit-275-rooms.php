<?php
// Riusa gli adattatori sintetici della suite, senza connessioni MySQL.
require __DIR__.'/room-swap-entitlement.php';
require_once __DIR__.'/../modulo-iscrizioni/includes/class-mi-option-rules.php';
require_once __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-people.php';
class ExternalRoomAuditDB extends RoomAuditDB {
 public $capacity=4,$occupied=0;
 function get_results($q,$f) {
  if(str_contains($q[0],'mi_registration_items'))return [['ticket_type_code'=>'base','unit_price_cents'=>0]];
  if(str_contains($q[0],'GROUP BY p.room_code'))return [['room_code'=>'M1','occupied'=>$this->occupied]];
  if(str_contains($q[0],'mi_rooms'))return [['code'=>'S1','name'=>'Singola','capacity'=>1],['code'=>'M1','name'=>'Multipla','capacity'=>$this->capacity]];
  if(str_contains($q[0],'mi_payments'))return [];
  return parent::get_results($q,$f);
 }
}
$wpdb=new ExternalRoomAuditDB();
$wpdb->regs[1]['snapshot_json']=json_encode(['event'=>['options'=>[['code'=>'alloggio-multipla','scope'=>'TICKET','name'=>'Multipla','price_cents'=>3000]],'pricing_mode'=>'CALCULATED']]);
foreach($wpdb->regs as &$reg){$reg['snapshot_json']=$wpdb->regs[1]['snapshot_json'];$reg['economic_mode']='PRICE_ONLY';$reg['initial_due_cents']=0;}unset($reg);
foreach($wpdb->people as &$person)$person['ticket_type_code']='base';unset($person);
$type=MI_Management_Service::room_types()['alloggio-multipla'];
$compatible=(new ReflectionMethod(MI_Management_Service::class,'room_compatible_with_type'))->invoke(null,$type,['code'=>'M1','capacity'=>4]);
assert_room($compatible,'La stanza dovrebbe essere compatibile');
$plan=new ReflectionMethod(MI_Management_Service::class,'accommodation_plan');
$data=['type'=>'alloggio-multipla','number'=>'1','reason'=>'Verifica locale','people'=>[['code'=>'DEMO1','number'=>1]]];

foreach([1,2]as $count){
 if($count===2)$data['people'][]=['code'=>'DEMO2','number'=>1];
 $result=$plan->invoke(null,42,$data);
 assert_room(count($result['people'])===$count&&array_column($result['people'],'after_room')===array_fill(0,$count,'M1'),'Shared multiple rejected');
 assert_room(!$result['new_rooms'],'Existing shared room recreated');
}
function reject_plan($plan,$data,$text){try{$plan->invoke(null,42,$data);}catch(InvalidArgumentException $e){assert_room(str_contains($e->getMessage(),$text),$e->getMessage());return;}throw new RuntimeException('Invalid room assignment accepted');}
$wpdb->occupied=3;reject_plan($plan,$data,'Capienza superata');
$wpdb->occupied=0;$wpdb->capacity=1;reject_plan($plan,$data,'multipla condivisa');
$data['number']='9';reject_plan($plan,$data,'multipla condivisa');
$data['type']='alloggio-singola';reject_plan($plan,$data,'più singole');
$data['type']='alloggio-multipla';$data['number']='';
$result=$plan->invoke(null,42,$data);assert_room(array_column($result['people'],'after_room')===['M2','M3'],'Automatic individual multiple codes changed');
echo "PASS shared multiple capacity, occupancy and individual automatic codes.\n";
