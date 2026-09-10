<?php
// Existing suite provisions only the disposable localhost database mi_ledger_test.
require __DIR__.'/management-innodb.php';
$definitions=[];foreach(['singola'=>10000,'doppia-separati'=>7000,'tripla'=>6000,'multipla'=>3000]as $code=>$price)$definitions[]=['code'=>'alloggio-'.$code,'name'=>$code,'scope'=>'TICKET','price_cents'=>$price,'max_quantity'=>1];
$snapshot=json_encode(['event'=>['options'=>$definitions,'pricing_mode'=>'CALCULATED','participant_extra_scope'=>'ALL']]);
foreach([30,31]as $id){seed_auto_rooms($id,['singola']);$wpdb->query($wpdb->prepare("UPDATE wp_mi_registrations SET snapshot_json=%s,economic_mode='DEPOSIT_BALANCE',total_cents=12000,initial_due_cents=3000,balance_cents=9000 WHERE id=%d",$snapshot,$id));$wpdb->query($wpdb->prepare('UPDATE wp_mi_participants SET options_json=%s WHERE registration_id=%d',json_encode([['code'=>'alloggio-singola','name'=>'Singola','quantity'=>1,'unit_price_cents'=>10000],['code'=>'bus','name'=>'Pullman','quantity'=>1,'unit_price_cents'=>1000]]),$id));run_auto_rooms($id);}
// total 12000 includes a previous +1000 correction; switching must preserve it and bus.
$data=['people'=>[['code'=>'AUTO30','number'=>1],['code'=>'AUTO31','number'=>1]],'type'=>'alloggio-doppia-separati','number'=>'','reason'=>'Abbinamento concordato'];
$before=assigned_rooms(30);$p=MI_Management_Service::change_accommodation(42,$data);check(isset($p['version']),'Anteprima assente: '.json_encode($p));
check($p['orders'][0]['after_total']===9000&&$p['orders'][1]['after_total']===9000,'Differenza economica o rettifica precedente persa');
check($p['people'][0]['after_room']===$p['people'][1]['after_room'],'Nuova doppia non condivisa');check(assigned_rooms(30)===$before,'Anteprima ha scritto dati');
$r=MI_Management_Service::change_accommodation(42,$data,$p['version'],'wp_7_12345678-1234-4234-8234-123456781001');check(!empty($r['saved']),'Cambio unico non salvato: '.json_encode($r));
foreach([30,31]as $id){check((int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id='.$id)===9000,'Dovuto non aggiornato');$opts=json_decode($wpdb->get_var('SELECT options_json FROM wp_mi_participants WHERE registration_id='.$id),true);check($opts[0]['code']==='bus'&&$opts[1]['code']==='alloggio-doppia-separati','Servizi estranei alterati');}
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_registration_events WHERE event_type='CHANGE_ACCOMMODATION'")===2,'Audit separato per iscrizione assente');
$r=MI_Management_Service::change_accommodation(42,$data,$p['version'],'wp_7_12345678-1234-4234-8234-123456781001');check(!empty($r['replayed']),'Retry non idempotente');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_payments')===0,'Cambio ha creato pagamenti/rimborsi');
$data['people']=[['code'=>'AUTO30','number'=>1]];$data['type']='alloggio-tripla';$p=MI_Management_Service::change_accommodation(42,$data);
$wpdb->query('UPDATE wp_mi_registrations SET total_cents=9500 WHERE id=30');
$r=MI_Management_Service::change_accommodation(42,$data,$p['version'],'wp_7_12345678-1234-4234-8234-123456781002');check(!empty($r['rejected']),'Anteprima obsoleta accettata');
$p=MI_Management_Service::change_accommodation(42,$data);check($p['orders'][0]['after_total']===8500,'Rettifica successiva non conservata');
// A second selected registration without the target tariff rejects the whole operation.
$data['people'][]=['code'=>'AUTO31','number'=>1];$wpdb->query("UPDATE wp_mi_registrations SET snapshot_json='{}' WHERE id=31");
$p=MI_Management_Service::change_accommodation(42,$data);check(!empty($p['rejected']),'Tariffa mancante ignorata');check((int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=30')===9500,'Cambio parziale');
$wpdb->query($wpdb->prepare('UPDATE wp_mi_registrations SET snapshot_json=%s WHERE id=31',$snapshot));
// Test payment freshness and refund estimate using real ledger table defaults.
$data['people']=[['code'=>'AUTO30','number'=>1]];$p=MI_Management_Service::change_accommodation(42,$data);
check(false!==$wpdb->insert('wp_mi_payments',['registration_id'=>30,'transaction_kind'=>'PAYMENT','amount_cents'=>10000,'payment_source'=>'CASH','origin_id'=>'test-room-payment','effective_at'=>gmdate('Y-m-d H:i:s'),'created_at'=>gmdate('Y-m-d H:i:s')]),$wpdb->last_error);
$r=MI_Management_Service::change_accommodation(42,$data,$p['version'],'wp_7_12345678-1234-4234-8234-123456781003');check(!empty($r['rejected']),'Pagamento intervenuto dopo anteprima ignorato');
$p=MI_Management_Service::change_accommodation(42,$data);check($p['orders'][0]['refund']===1500&&$p['orders'][0]['due']===0,'Rimborso proposto errato');
$r=MI_Management_Service::change_accommodation(42,$data,$p['version'],'wp_7_12345678-1234-4234-8234-123456781004');check(!empty($r['saved']),'Cambio con restituzione non salvato');check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_payments')===1,'Rimborso creato automaticamente');
check(MI_Management_Service::change_accommodation(43,$data) instanceof WP_Error,'Evento non autorizzato accessibile');
$GLOBALS['test_payment_permission']=false;check(MI_Management_Service::change_accommodation(42,$data) instanceof WP_Error,'Permesso pagamenti ignorato');$GLOBALS['test_payment_permission']=true;
// Failure during second registration update must roll back rooms, services, totals and audit.
$data=['people'=>[['code'=>'AUTO30','number'=>1],['code'=>'AUTO31','number'=>1]],'type'=>'alloggio-multipla','number'=>'','reason'=>'Cambio camerata'];$p=MI_Management_Service::change_accommodation(42,$data);
$before=$wpdb->get_results('SELECT id,room_code,options_json FROM wp_mi_participants ORDER BY id',ARRAY_A);$before_rooms=$wpdb->get_results('SELECT * FROM wp_mi_rooms ORDER BY code',ARRAY_A);
class FailingAccommodationDatabase extends DatabaseAdapter {function update($table,$data,$where){if($table==='wp_mi_registrations'&&(int)$where['id']===31)return false;return parent::update($table,$data,$where);}}
$real_db=$wpdb;$wpdb=new FailingAccommodationDatabase();$wpdb->db->select_db('mi_ledger_test');$r=MI_Management_Service::change_accommodation(42,$data,$p['version'],'wp_7_12345678-1234-4234-8234-123456781005');$wpdb=$real_db;
check($r instanceof WP_Error,'Errore database ignorato');check($before===$wpdb->get_results('SELECT id,room_code,options_json FROM wp_mi_participants ORDER BY id',ARRAY_A),'Rollback persone incompleto');check($before_rooms===$wpdb->get_results('SELECT * FROM wp_mi_rooms ORDER BY code',ARRAY_A),'Rollback camere incompleto');
// Free event retains its zero economics despite nonzero catalog tariffs.
$free=json_encode(['event'=>['options'=>$definitions,'pricing_mode'=>'ZERO']]);$wpdb->query($wpdb->prepare("UPDATE wp_mi_registrations SET snapshot_json=%s,economic_mode='NO_PAYMENT',total_cents=0,initial_due_cents=0,balance_cents=0 WHERE id=31",$free));
$data['people']=[['code'=>'AUTO31','number'=>1]];$p=MI_Management_Service::change_accommodation(42,$data);check($p['orders'][0]['after_total']===0&&$p['orders'][0]['delta']===0,'Evento gratuito ha acquisito un dovuto');
echo "Cambio unico: anteprima senza scritture, quote storiche e rettifiche, atomicità multi-iscrizione, retry, conflitto pagamenti e rimborso manuale verificati.\n";
