<?php
// Regression checks; disposable localhost mi_ledger_test only, via existing fixture.
require __DIR__ . '/accommodation-change-innodb.php';
seed_auto_rooms(80, ['singola', 'singola']);
$wpdb->query($wpdb->prepare("UPDATE wp_mi_registrations SET snapshot_json=%s,economic_mode='FULL_PAYMENT',total_cents=20000,initial_due_cents=20000 WHERE id=80", $snapshot));
$wpdb->query("INSERT INTO wp_mi_registration_items (registration_id,ticket_type_code,quantity,unit_price_cents) VALUES (80,'',2,0)");
$wpdb->query($wpdb->prepare('UPDATE wp_mi_participants SET options_json=%s WHERE registration_id=80', json_encode([['code'=>'alloggio-singola','name'=>'Singola','quantity'=>1,'unit_price_cents'=>10000]])));
$ids = array_map('intval', array_column($wpdb->get_results('SELECT id FROM wp_mi_participants WHERE registration_id=80 ORDER BY id', ARRAY_A), 'id'));
$wpdb->insert('wp_mi_payments', ['registration_id'=>80,'transaction_kind'=>'PAYMENT','amount_cents'=>10000,'payment_source'=>'CASH','origin_id'=>'audit-80','effective_at'=>gmdate('Y-m-d H:i:s'),'created_at'=>gmdate('Y-m-d H:i:s'),'participant_allocations_json'=>json_encode([['participant_id'=>$ids[0],'name'=>'Persona fittizia','amount_cents'=>10000]])]);
$data=['people'=>[['code'=>'AUTO80','number'=>1]],'type'=>'alloggio-tripla','number'=>'','reason'=>'Audit con identita fittizie'];
$preview=MI_Management_Service::change_accommodation(42,$data);
check(isset($preview['version']), 'Anteprima audit non disponibile: '.json_encode($preview));
$order=$preview['orders'][0];
check($order['due']===10000 && $order['refund']===4000, 'Il credito personale compensa il debito altrui');
echo 'PASS_SEPARATE_BALANCES '.json_encode(['actual_due'=>$order['due'],'actual_refund'=>$order['refund'],'expected_individual_due'=>10000,'expected_individual_refund'=>4000])."\n";
$wpdb->query($wpdb->prepare('UPDATE wp_mi_registrations SET total_cents=12000,initial_due_cents=12000,quote_adjustments_json=%s WHERE id=80', json_encode([$ids[0]=>-8000])));
$data['type']='alloggio-multipla';
$preview=MI_Management_Service::change_accommodation(42,$data);
check(!empty($preview['rejected']), 'Cambio con quota negativa accettato');
check((int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=80')===12000, 'Un cambio rifiutato ha modificato il dovuto');
echo "PASS: cambio negativo rifiutato senza scritture.\n";
