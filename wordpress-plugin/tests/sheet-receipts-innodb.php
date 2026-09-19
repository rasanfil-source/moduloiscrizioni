<?php
require __DIR__.'/management-innodb.php';
seed_auto_rooms(90,['singola']);
$change=['order_code'=>'AUTO90','number'=>1,'key'=>'first_name','before'=>'Auto','after'=>' Ada  Maria '];
$change['accepted']='Client cannot choose the server receipt';
$request='wp_7_12345678-1234-4234-8234-123456789090';
$saved=MI_Management_Service::save_sheet(42,[$change],$request);
check(!empty($saved['saved']),'Sheet change not saved');
check(($saved['confirmations'][0]['accepted']??null)==='Ada Maria','Receipt missing canonical accepted value');
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
