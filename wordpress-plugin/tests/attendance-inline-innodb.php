<?php
require __DIR__.'/individual-management-innodb.php';
function attendance_test_save($event,$items,$request=null){static $n=0;return MI_Management_Service::save_attendance_bulk($event,$items,$request??sprintf('wp_7_87654321-1234-4123-8123-%012d',++$n));}
$GLOBALS['test_event_meta']['_mi_activity_id']=99;
$GLOBALS['test_event_meta']['_mi_annual_attendance_report']='1';
$GLOBALS['test_event_meta']['_mi_event_starts_at']=(new DateTimeImmutable('+1 day',wp_timezone()))->format('Y-m-d\TH:i');
check(!MI_Management_Service::attendance_availability(42)['available'],'future hidden');
check(is_wp_error(attendance_test_save(42,[['id'=>1,'attendance'=>'PRESENT']])),'future rejected');
$GLOBALS['test_event_meta']['_mi_event_starts_at']=(new DateTimeImmutable('-1 day',wp_timezone()))->format('Y-m-d\TH:i');
check(MI_Management_Service::attendance_availability(42)['available'],'started visible');
$wpdb->query("UPDATE wp_mi_registrations SET status='CONFIRMED' WHERE id=1");
$wpdb->query("UPDATE wp_mi_participants SET status='ACTIVE' WHERE id=1");
$before=(int)$wpdb->get_var('SELECT workspace_revision FROM wp_mi_registrations WHERE id=1');
$request='wp_7_87654321-1234-4123-8123-111111111111';
check(!is_wp_error(attendance_test_save(42,[['id'=>1,'attendance'=>'PRESENT']],$request)),'save present');
check(attendance_test_save(42,[['id'=>1,'attendance'=>'PRESENT']],$request)['replayed'],'replay acknowledged');
check((int)$wpdb->get_var('SELECT workspace_revision FROM wp_mi_registrations WHERE id=1')===$before+1,'replica revision');
$last=json_decode($wpdb->get_var("SELECT detail_json FROM wp_mi_registration_events WHERE event_type='MANAGEMENT_attendance' ORDER BY id DESC LIMIT 1"),true);
check($last['participant_id']===1&&$last['attendance']==='PRESENT','saved individual');
check(!is_wp_error(attendance_test_save(42,[['id'=>1,'attendance'=>'ABSENT']])),'undo attendance');
check(is_wp_error(attendance_test_save(43,[['id'=>1,'attendance'=>'PRESENT']])),'other event blocked');
class AttendanceTransactionFault extends DatabaseAdapter {
 function __construct($existing){$this->db=$existing->db;}
 function query($sql){if($sql==='START TRANSACTION'){$this->last_error='Injected begin failure';return false;}return parent::query($sql);}
}
$attendanceDatabase=$wpdb;
$auditBefore=(int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_registration_events');
$wpdb=new AttendanceTransactionFault($attendanceDatabase);
check(is_wp_error(attendance_test_save(42,[['id'=>1,'attendance'=>'PRESENT']])),'failed transaction start rejected');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_registration_events')===$auditBefore,'failed transaction start writes no attendance');
$wpdb=$attendanceDatabase;
$GLOBALS['test_event_meta']['_mi_annual_attendance_report']='0';
check(!MI_Management_Service::attendance_availability(42)['available'],'disabled group');
check(is_wp_error(attendance_test_save(42,[['id'=>1,'attendance'=>'PRESENT']])),'disabled blocked');
echo "PASS: presenza per gruppo e orario, salvataggio individuale, revoca e replica accodata.\n";
