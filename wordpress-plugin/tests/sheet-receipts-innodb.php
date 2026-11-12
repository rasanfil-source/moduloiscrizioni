<?php
require __DIR__.'/management-innodb.php';
seed_auto_rooms(90,['singola']);
$change=['order_code'=>'AUTO90','number'=>1,'key'=>'first_name','before'=>'Auto','after'=>' Ada  Maria '];
$change['accepted']='Client cannot choose the server receipt';
$request='wp_7_12345678-1234-4234-8234-123456789090';
$saved=MI_Management_Service::save_sheet(42,[$change],$request);
check(!empty($saved['saved']),'Sheet change not saved');
check(($saved['confirmations'][0]['accepted']??null)==='Ada Maria','Receipt missing canonical accepted value');
check(($saved['confirmations'][0]['workspace_revision']??null)===(string)$wpdb->get_var('SELECT workspace_revision FROM wp_mi_registrations WHERE id=90'),'Receipt missing the accepted database revision');
$replay=MI_Management_Service::save_sheet(42,[$change],$request);
check(!empty($replay['replayed'])&&$replay['confirmations']===$saved['confirmations'],'Lost receipt not recoverable on retry');
$retry=MI_Management_Service::save_sheet(42,[$change],'wp_7_12345678-1234-4234-8234-123456789091');
check(!empty($retry['saved'])&&count($retry['confirmations'])===1,'Existing normalized value cannot recover a previously stuck edit');
$wpdb->query("UPDATE wp_mi_participants SET first_name='Nome successivo' WHERE registration_id=90");
$replay=MI_Management_Service::save_sheet(42,[$change],$request);
check($replay['confirmations']===[],'Old receipt overwrites a later database change');
$conflict=MI_Management_Service::save_sheet(42,[$change],'wp_7_12345678-1234-4234-8234-123456789092');
check(!empty($conflict['rejected']),'Normalization bypassed a genuine conflict');
echo "PASS: ricevute canoniche, replay, recupero di modifiche bloccate e conservazione conflitti.\n";

$bulk=[];
for($id=1000;$id<=1250;$id++){
 seed_auto_rooms($id,['singola']);
 $bulk[]=['order_code'=>'AUTO'.$id,'number'=>1,'key'=>'first_name','before'=>'Auto','after'=>'Ada'];
 if($id<1250)$bulk[]=['order_code'=>'AUTO'.$id,'number'=>1,'key'=>'last_name','before'=>'Persona','after'=>'Esempio'];
}
$longKey='custom_'.str_repeat('a',90);
$wpdb->query($wpdb->prepare('UPDATE wp_mi_participants SET extra_json=%s WHERE registration_id=1250',json_encode([$longKey=>'Prima'])));
$bulk[]=['order_code'=>'AUTO1250','number'=>1,'key'=>$longKey,'before'=>'Prima','after'=>'Dopo'];
$conflicting=$bulk;
$conflicting[count($conflicting)-1]['before']='Versione obsoleta';
$rejected=MI_Management_Service::save_sheet(42,$conflicting,'wp_7_12345678-1234-4234-8234-123456789093');
check(!empty($rejected['rejected']),'Bulk conflict was accepted');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_participants WHERE registration_id BETWEEN 1000 AND 1250 AND first_name='Ada'")===0,'Bulk conflict left partial changes');
$bulkRequest='wp_7_12345678-1234-4234-8234-123456789094';
$saved=MI_Management_Service::save_sheet(42,$bulk,$bulkRequest);
check(!empty($saved['saved']) && count($saved['confirmations'])===502,'More than 500 cells cannot be synchronized: '.json_encode($saved));
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_participants WHERE registration_id BETWEEN 1000 AND 1250 AND first_name='Ada'")===251,'Bulk import missed participants');
$extra=json_decode($wpdb->get_var('SELECT extra_json FROM wp_mi_participants WHERE registration_id=1250'),true);
check($extra[$longKey]==='Dopo','Long custom key lost during synchronization');
$replay=MI_Management_Service::save_sheet(42,$bulk,$bulkRequest);
check(!empty($replay['replayed']) && $replay['confirmations']===$saved['confirmations'],'Bulk retry changed the accepted result');
seed_auto_rooms(2000,['singola'],'TRAVEL_PENDING');
$room=MI_Management_Service::save_sheet(42,[['order_code'=>'AUTO2000','number'=>1,'key'=>'room','before'=>'','after'=>'S1']],'wp_7_12345678-1234-4234-8234-123456789095');
check(!empty($room['rejected']) && str_contains($room['message'],'ammessa'),'Travel waitlist obtained a room through sheet synchronization');
check(assigned_rooms(2000)===[''],'Rejected assignment occupied a room');
echo "PASS: 502 cells, long historical keys, full rollback, replay and travel-pending room guard on MariaDB.\n";
