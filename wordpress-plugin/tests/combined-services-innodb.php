<?php
// All mutations use the isolated temporary InnoDB tables from this fixture.
require __DIR__.'/individual-management-innodb.php';
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL)!==false;}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
class MI_Modello_Email {static function crea_istantanea($id,$values){return ['attivo'=>true];}}
class MI_Spedizione_Email {static function stato_nuova_email($snapshot){return $GLOBALS['test_email_mode']??'PREVIEW';}static function pianifica_spedizione(){}}
class MI_Portal {static function balance_url(...$args){return 'https://example.test/saldo';}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-booking-update-email.php';
preg_match('/CREATE TABLE \{\$outbox\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);
check($m&&false!==$wpdb->query('CREATE TEMPORARY TABLE wp_mi_email_outbox ('.$m[1].') ENGINE=InnoDB'),'outbox schema '.$wpdb->last_error);
$GLOBALS['test_event_meta']['_mi_bus_assignment_enabled']='1';
$defs=[$bus,['code'=>'alloggio-singola','name'=>'Singola','scope'=>'TICKET','price_cents'=>5000,'max_quantity'=>1],['code'=>'alloggio-doppia-separati','name'=>'Doppia','scope'=>'TICKET','price_cents'=>3000,'max_quantity'=>1]];
$snapshot=json_encode(['event'=>['options'=>$defs,'participant_extra_scope'=>'ALL','deposit_mode'=>'FIXED','deposit_fixed_cents'=>10000]]);
$wpdb->query($wpdb->prepare("UPDATE wp_mi_registrations SET snapshot_json=%s,buyer_email='person@example.invalid' WHERE id=1",$snapshot));
$paymentsBefore=$wpdb->get_results('SELECT * FROM wp_mi_payments ORDER BY id',ARRAY_A);
$data=['participant_id'=>2,'options'=>['bus-andata'=>1],'accommodation_type'=>'alloggio-singola','room'=>'__AUTO__','bus'=>'A','reason'=>''];
$v=version();$preview=MI_Management_Service::options_preview(1,$data,$v);
check(!is_wp_error($preview)&&$preview['delta']===5000&&$preview['assignment']['room']==='S1','combined preview '.json_encode($preview));
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_rooms')===0,'preview creates room');
$key='wp_7_12345678-1234-4234-8234-123456780001';
$result=MI_Management_Service::save(1,'change_options',$data,$v,$key);check(!empty($result['saved']),'combined save '.json_encode($result));
$person=$wpdb->get_row('SELECT * FROM wp_mi_participants WHERE id=2',ARRAY_A);
check($person['room_code']==='S1'&&json_decode($person['extra_json'],true)['pullman']==='A','assignments absent');
check((int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=1')===67500,'combined total');
$outbox=$wpdb->get_row('SELECT * FROM wp_mi_email_outbox ORDER BY id DESC LIMIT 1',ARRAY_A);
check($outbox&&$outbox['status']==='PREVIEW'&&$outbox['template_type']==='BOOKING_UPDATED','notification mode');
check(str_contains($outbox['payload_json'],'S1'),'notification missing assignment');
check(!empty(MI_Management_Service::save(1,'change_options',$data,$v,$key)['replayed']),'combined retry');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_email_outbox')===1,'duplicate notification');
// The full single room cannot receive the other person, and no service is saved partially.
$before=$wpdb->get_results('SELECT * FROM wp_mi_participants ORDER BY id',ARRAY_A);
$bad=$data;$bad['participant_id']=1;$bad['room']='S1';
$rejected=MI_Management_Service::save(1,'change_options',$bad,version(),'wp_7_12345678-1234-4234-8234-123456780002');
check(!empty($rejected['rejected']),'full room accepted');
check($before===$wpdb->get_results('SELECT * FROM wp_mi_participants ORDER BY id',ARRAY_A),'partial save on full room');
// A second update supersedes the first draft. Existing sent history remains intact.
$wpdb->query("UPDATE wp_mi_email_outbox SET status='SENT'");
$data['accommodation_type']='alloggio-doppia-separati';$data['bus']='B';
$GLOBALS['test_email_mode']='PENDING';
$result=MI_Management_Service::save(1,'change_options',$data,version(),'wp_7_12345678-1234-4234-8234-123456780003');check(!empty($result['saved']),'second update');
check($wpdb->get_var('SELECT status FROM wp_mi_email_outbox WHERE id='.(int)$outbox['id'])==='SENT','sent history overwritten');
$row=$wpdb->get_row('SELECT * FROM wp_mi_email_outbox ORDER BY id DESC LIMIT 1',ARRAY_A);check($row['status']==='PENDING','operative mode');
$data['room']='DS1';$data['bus']='C';
$result=MI_Management_Service::save(1,'change_options',$data,version(),'wp_7_12345678-1234-4234-8234-123456780004');check(!empty($result['saved']),'third update');
check($wpdb->get_var('SELECT status FROM wp_mi_email_outbox WHERE id='.(int)$row['id'])==='SUPERSEDED','old pending notification active');
check(MI_Booking_Update_Email::refresh($row)===null,'superseded notification resent');
$latest=$wpdb->get_row('SELECT * FROM wp_mi_email_outbox ORDER BY id DESC LIMIT 1',ARRAY_A);
// A payment made before dispatch is reflected in the message.
$wpdb->query("UPDATE wp_mi_payments SET amount_cents=32500,participant_allocations_json='[{\"participant_id\":1,\"amount_cents\":0},{\"participant_id\":2,\"amount_cents\":32500}]' WHERE origin_id='fixture'");
$fresh=MI_Booking_Update_Email::refresh($latest);
check(str_contains($fresh['email_preview']['testo'],'Camera: DS1')&&str_contains($fresh['email_preview']['testo'],'Pullman assegnato: C'),'fresh assignment');
// An outbox constraint failure must roll back price, room creation, assignments and audit.
$wpdb->query("ALTER TABLE wp_mi_email_outbox ADD CONSTRAINT reject_new CHECK (status <> 'TEST_PENDING')");
$GLOBALS['test_email_mode']='TEST_PENDING';$data['room']='__AUTO__';$data['accommodation_type']='alloggio-singola';
$before=$wpdb->get_results('SELECT * FROM wp_mi_participants ORDER BY id',ARRAY_A);$rooms=$wpdb->get_results('SELECT * FROM wp_mi_rooms ORDER BY code',ARRAY_A);$total=$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=1');$auditCount=$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_registration_events');
$failed=MI_Management_Service::save(1,'change_options',$data,version(),'wp_7_12345678-1234-4234-8234-123456780005');check(is_wp_error($failed),'outbox failure ignored');
check($before===$wpdb->get_results('SELECT * FROM wp_mi_participants ORDER BY id',ARRAY_A)&&$rooms===$wpdb->get_results('SELECT * FROM wp_mi_rooms ORDER BY code',ARRAY_A),'outbox failure partial write');
check($total===$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=1')&&$auditCount===$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_registration_events'),'outbox failure economic/audit rollback');
check(count($paymentsBefore)===(int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_payments'),'automatic payment/refund');
echo "Combined services: preview, capacity, assignments, one transaction, retry, notification modes, superseding, fresh snapshot and outbox rollback passed.\n";
