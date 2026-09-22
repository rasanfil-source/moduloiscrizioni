<?php
require __DIR__.'/management-innodb.php';
if (!class_exists('MI_Sheet_Open')) {
 class MI_Sheet_Open {static function enqueue($event_id){throw new RuntimeException('Injected cron outage');}}
}
$data=['code'=>'POSTCOMMIT','name'=>'Camera sintetica','capacity'=>1];
$version=inventory_version();$request='wp_7_12345678-1234-4234-8234-123456789fff';
$result=MI_Management_Service::save_event_room(42,'room_save',$data,$version,$request);
check(!is_wp_error($result)&&$result['saved'],'cron error must not hide committed room');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_rooms WHERE code='POSTCOMMIT'")===1,'one room committed');
$result=MI_Management_Service::save_event_room(42,'room_save',$data,$version,$request);
check(!is_wp_error($result)&&$result['replayed'],'retry returns committed result even during cron outage');
echo "PASS: inventario confermato con cron indisponibile e retry senza duplicazione.\n";
