<?php
ob_start(); require __DIR__.'/travel-companions.php'; ob_end_clean();
$restore=new ReflectionMethod('MI_Travel','restore_companions');
$snapshot=['schema_version'=>'3.27.83','participants'=>[
 ['ticket_type_code'=>'standard','ticket_index'=>1,'travel'=>['mode'=>'DRIVER','seats'=>3]],
 ['ticket_type_code'=>'standard','ticket_index'=>2,'travel'=>['mode'=>'OWN']]
]];
$base=['registration_id'=>42,'event_id'=>7,'person_status'=>'ACTIVE','booking_status'=>'CONFIRMED','capacity_released_at'=>null,'state'=>'NONE','driver_id'=>null,'companion_of'=>null,'ticket_type_code'=>'standard','snapshot_json'=>json_encode($snapshot)];
$driver=$base+['participant_id'=>1,'first_name'=>'Raimondo','last_name'=>'Sanfilippo','mode'=>'DRIVER','ticket_index'=>1,'seats'=>3];
$companion=$base+['participant_id'=>2,'first_name'=>'Miquel','last_name'=>'Delgado','mode'=>'OWN','ticket_index'=>2];
$result=$restore->invoke(null,[$driver,$companion]);
if($result[1]['companion_of']!==1||$result[0]['seats']!==3||isset($result[0]['snapshot_json']))throw new RuntimeException('Legacy companion not restored or offered seats changed');
if(MI_Travel::participant_summary($result[1],$result)!=='Viaggio in auto con Raimondo Sanfilippo')throw new RuntimeException('Wrong driver');
foreach([
 array_replace($companion,['registration_id'=>99]),
 array_replace($companion,['person_status'=>'CANCELLED']),
 array_replace($companion,['mode'=>'NEED','state'=>'ASSIGNED','driver_id'=>9]),
 array_replace($companion,['ticket_index'=>3]),
] as $other){$result=$restore->invoke(null,[$driver,$other]);if(!empty($result[1]['companion_of']))throw new RuntimeException('Unrelated or inactive companion inferred');}
foreach(['3.27.8','3.27.93',''] as $version){$s=$snapshot;$s['schema_version']=$version;$json=json_encode($s);$result=$restore->invoke(null,[array_replace($driver,['snapshot_json'=>$json]),array_replace($companion,['snapshot_json'=>$json])]);if(!empty($result[1]['companion_of']))throw new RuntimeException('Unsupported legacy snapshot inferred');}
$snapshot['schema_version']='3.27.93';$snapshot['participants'][1]['travel']['companion_key']='standard:1';$json=json_encode($snapshot);
$result=$restore->invoke(null,[array_replace($driver,['snapshot_json'=>$json]),array_replace($companion,['snapshot_json'=>$json])]);if($result[1]['companion_of']!==1)throw new RuntimeException('Explicit snapshot relationship ignored');
echo "PASS: legacy companions restored from booking snapshot, current explicit links, isolation and unchanged offered seats.\n";
