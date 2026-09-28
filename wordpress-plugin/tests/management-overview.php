<?php
define('ABSPATH',__DIR__);
function wp_json_encode($v){return json_encode($v);}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-list.php';
function check_overview($ok,$message){if(!$ok)throw new RuntimeException($message);}
function fixture($count){
    $data=['features'=>['rooms'=>true],'people'=>[],'items'=>[],'rooms'=>[['code'=>'D1','capacity'=>2]],'room_types'=>['double'=>['capacity'=>2]],'option_definitions'=>[['code'=>'meal','name'=>'Pranzo','category'=>'pranzo']],'field_labels'=>['custom_phone'=>'Telefono'],'payment_counts'=>['settled'=>2]];
    for($i=0;$i<$count;$i++){
        $status=['CONFIRMED','PENDING_PAYMENT','CANCELLED','WAITLISTED','WAITLIST_OFFERED'][$i%5];
        $active=!in_array($status,['CANCELLED'],true);$collectible=in_array($status,['CONFIRMED','PENDING_PAYMENT'],true);
        $data['people'][]=['id'=>$i+1,'number'=>1,'code'=>'ORDER'.$i,'name'=>'Private Person '.$i,'status'=>$status,'room'=>'D1','fields'=>['custom_phone'=>'secret'],'email'=>'secret@example.invalid','options'=>[['code'=>'meal','name'=>'Pranzo','quantity'=>2]],'attendance'=>['state'=>'PRESENT']];
        $data['items'][]=['code'=>'ORDER'.$i,'name'=>'Private Buyer '.$i,'status'=>$status,'active'=>$active,'collectible'=>$collectible,'participants'=>1,'paid'=>$status==='CANCELLED'?-300:500,'balance'=>1000,'missing'=>1,'unassigned'=>1,'requests'=>'private request','order_options'=>[['code'=>'coach','name'=>'Bus','quantity'=>3]],'offer_expires_at'=>'2026-10-10 10:00:00'];
    }
    return $data;
}
$data=fixture(1000);$overview=MI_Management_List::overview($data);$json=wp_json_encode($overview);
check_overview(!isset($overview['people'])&&!isset($overview['items'])&&!isset($overview['rooms']),'No full records in overview');
check_overview($overview['room_types']===$data['room_types'],'Room prefixes remain available on the first list page');
check_overview(!str_contains($json,'Private')&&!str_contains($json,'secret')&&!str_contains($json,'ORDER'),'No personal data leaked by aggregates');
check_overview($overview['metrics']['states']['CONFIRMED']===200&&$overview['metrics']['receivable']===400000&&$overview['metrics']['paid']===340000,'Status totals and signed payments preserved');
check_overview($overview['metrics']['missing']===800&&$overview['metrics']['unassigned']===800&&$overview['metrics']['offers']===200&&$overview['metrics']['admitted']===400,'Active-only quality and panel counts');
check_overview($overview['service_totals'][0]['quantity']==800&&$overview['service_totals'][0]['people']===400,'Participant services count people, not quantities');
check_overview($overview['order_service_totals'][0]['quantity']==1200&&$overview['order_service_totals'][0]['orders']===400,'Order services counted once per order');
check_overview($overview['field_keys']===['custom_phone']&&$overview['metrics']['has_attendance']&&$overview['metrics']['has_requests'],'Columns/filters preserved');
check_overview($overview['payment_counts']===$data['payment_counts'],'Individual payment counts untouched');
$large=wp_json_encode(MI_Management_List::overview(fixture(5000)));
check_overview(strlen($large)<strlen($json)+100,'Payload must not grow per participant');
$rooms=MI_Management_List::panel($data,'rooms');$attendance=MI_Management_List::panel($data,'attendance');$offers=MI_Management_List::panel($data,'offers');
check_overview(count($rooms['people'])===800&&count($attendance['people'])===400&&count($offers['offers'])===200,'Panel scopes');
check_overview(!isset($rooms['people'][0]['email'])&&!isset($attendance['people'][0]['options'])&&!isset($offers['offers'][0]['requests']),'Minimal panel fields');
check_overview($rooms['rooms_version']===hash('sha256',wp_json_encode($data['rooms'])),'Room concurrency version retained');
try{MI_Management_List::panel($data,'invalid');throw new RuntimeException('Invalid panel accepted');}catch(InvalidArgumentException $expected){}
$empty=MI_Management_List::overview(fixture(0));check_overview($empty['metrics']['admitted']===0&&$empty['service_totals']===[],'Empty event');
echo json_encode(['participants'=>1000,'previous_bytes'=>strlen(wp_json_encode(MI_Management_List::compact($data))),'overview_bytes'=>strlen($json),'overview_5000_bytes'=>strlen($large)]).PHP_EOL;
