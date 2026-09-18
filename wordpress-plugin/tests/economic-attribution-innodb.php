<?php
// Regression tests for attributed quotes, deposits and credits.
// Requires the isolated mi_ledger_test database on localhost:33317; uses temporary tables.
require __DIR__ . '/individual-management-innodb.php';
$event = ['economic_mode'=>'DEPOSIT_BALANCE','deposit_mode'=>'FIXED','deposit_fixed_cents'=>3000];
$economic = MI_Registration_Validation_Test::riepilogo_economico($event, 11000, 'PENDING_PAYMENT', 2, [10000,1000]);
$registration = ['total_cents'=>11000,'initial_due_cents'=>$economic['initial_due_cents'],'economic_mode'=>'DEPOSIT_BALANCE','snapshot_json'=>json_encode(['event'=>$event])];
$people = [['id'=>1,'ticket_type_code'=>'adult','first_name'=>'Ada','last_name'=>'Esempio','status'=>'ACTIVE','options_json'=>'[]'],['id'=>2,'ticket_type_code'=>'child','first_name'=>'Luca','last_name'=>'Esempio','status'=>'ACTIVE','options_json'=>'[]']];
$position = MI_Payment_People::calculate($registration,$people,[['ticket_type_code'=>'adult','unit_price_cents'=>10000],['ticket_type_code'=>'child','unit_price_cents'=>1000]],[]);
echo 'FIXED_MIXED '.json_encode(['booking_deposit'=>$economic['initial_due_cents'],'people_deposit'=>array_sum(array_column($position['people'],'deposit')),'ready'=>$position['ready'],'message'=>$position['message']])."\n";
check($position['ready'] && $economic['initial_due_cents']===4000, 'Fixed deposit regression');

function reset_audit_booking($mode='FULL_PAYMENT') {
 global $wpdb;
 foreach(['payments','participants','registration_items','management_requests','registration_events','registrations'] as $table) check(false!==$wpdb->query('DELETE FROM wp_mi_'.$table),'reset');
 $event=['deposit_mode'=>'FIXED','deposit_fixed_cents'=>3000,'options'=>[['code'=>'common','name'=>'Servizio comune','scope'=>'ORDER','price_cents'=>2000,'max_quantity'=>1]]];
 $wpdb->insert('wp_mi_registrations',['id'=>1,'event_id'=>42,'order_code'=>'AUDIT','status'=>'PENDING_PAYMENT','buyer_first_name'=>'Ada','buyer_last_name'=>'Esempio','buyer_email'=>'ada@example.invalid','buyer_phone'=>'','total_qty'=>1,'total_cents'=>10000,'initial_due_cents'=>10000,'economic_mode'=>$mode,'snapshot_json'=>json_encode(['event'=>$event]),'idempotency_key'=>'audit','created_at'=>gmdate('Y-m-d H:i:s')]);
 $wpdb->insert('wp_mi_registration_items',['registration_id'=>1,'ticket_type_code'=>'base','quantity'=>1,'unit_price_cents'=>10000]);
 $wpdb->insert('wp_mi_participants',['id'=>1,'registration_id'=>1,'ticket_type_code'=>'base','first_name'=>'Ada','last_name'=>'Esempio','status'=>'ACTIVE','options_json'=>'[]']);
 $wpdb->insert('wp_mi_payments',['registration_id'=>1,'transaction_kind'=>'PAYMENT','amount_cents'=>8000,'participant_allocations_json'=>'[{"participant_id":1,"amount_cents":8000}]','origin_id'=>'audit','payment_source'=>'BANK_TRANSFER','effective_at'=>gmdate('Y-m-d H:i:s'),'request_hash'=>str_repeat('b',64),'created_at'=>gmdate('Y-m-d H:i:s')]);
}
reset_audit_booking();
$result=MI_Management_Service::save(1,'adjust_due',['total_cents'=>8000,'reason'=>'Sconto sintetico'],version(),'wp_7_12345678-1234-4234-8234-123456789b01');
$row=$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=1',ARRAY_A);
$position=MI_Payment_Ledger::detail(1)['saldo'];
echo 'ADJUST_REDUCTION '.json_encode(['saved'=>$result['saved']??false,'total'=>$row['total_cents'],'paid'=>$position['versato'],'balance'=>$position['residuo'],'status'=>$row['status'],'individual_ready'=>$position['individual']['ready']])."\n";
check(!empty($result['saved'])&&$row['status']==='CONFIRMED'&&$position['residuo']===0&&$position['individual']['ready'],'Reduction regression');

reset_audit_booking();
$result=MI_Management_Service::save(1,'adjust_due',['total_cents'=>12000,'reason'=>'Supplemento sintetico'],version(),'wp_7_12345678-1234-4234-8234-123456789b02');
$payment=MI_Payment_Ledger::save(1,['request_id'=>'wp_7_12345678-1234-4234-8234-123456789b03','importo'=>'40','tipo'=>'INCASSO','metodo'=>'BONIFICO','data'=>'2026-09-18','rata'=>'FULL','participant_ids'=>'[1]']);
echo 'ADJUST_BLOCKS_PAYMENT '.json_encode(['adjusted'=>$result['saved']??false,'payment'=>$payment])."\n";
check(!empty($result['saved'])&&!empty($payment['saved']),'Adjustment payment regression');

reset_audit_booking();
$wpdb->query('UPDATE wp_mi_payments SET amount_cents=10000,participant_allocations_json=\'[{"participant_id":1,"amount_cents":10000}]\'');
$wpdb->query("UPDATE wp_mi_registrations SET status='CONFIRMED'");
$result=MI_Management_Service::save(1,'change_options',['participant_id'=>0,'options'=>['common'=>1],'reason'=>'Servizio ordine sintetico'],version(),'wp_7_12345678-1234-4234-8234-123456789b04');
$position=MI_Payment_Ledger::detail(1)['saldo'];
echo 'ORDER_SERVICE '.json_encode(['saved'=>$result['saved']??false,'status'=>$wpdb->get_var('SELECT status FROM wp_mi_registrations WHERE id=1'),'balance'=>$position['residuo'],'individual_ready'=>$position['individual']['ready']])."\n";
check(!empty($result['saved'])&&$position['residuo']===2000&&$wpdb->get_var('SELECT status FROM wp_mi_registrations WHERE id=1')==='PENDING_PAYMENT'&&$position['individual']['ready'],'Order service regression');

reset_audit_booking('DEPOSIT_BALANCE');
$wpdb->query('UPDATE wp_mi_registrations SET total_qty=2,total_cents=20000,initial_due_cents=6000,balance_cents=14000');
$wpdb->query('UPDATE wp_mi_registration_items SET quantity=2');
$wpdb->insert('wp_mi_participants',['id'=>2,'registration_id'=>1,'ticket_type_code'=>'base','first_name'=>'Luca','last_name'=>'Esempio','status'=>'ACTIVE','options_json'=>'[]']);
$wpdb->query('UPDATE wp_mi_payments SET amount_cents=15000,participant_allocations_json=\'[{"participant_id":1,"amount_cents":15000}]\'');
$detail=MI_Payment_Ledger::detail(1)['saldo'];
$summary=MI_Payment_People::summary($detail['individual']);
echo 'PAYMENT_HEADER '.json_encode(['header_balance'=>$detail['residuo'],'individual_balance'=>$summary['balance'],'header_deposit_missing'=>$detail['deposit_missing'],'individual_deposit_missing'=>$summary['deposit_missing'],'credit'=>$summary['credit']])."\n";
check($detail['residuo']===10000&&$summary['balance']===10000&&$detail['deposit_missing']===3000&&$summary['deposit_missing']===3000,'Header compensation regression');

// A shared cost has an exact equal proposal and a persisted operator allocation.
$shared=['participant_id'=>0,'options'=>['common'=>1],'reason'=>'Ripartizione concordata'];
$preview=MI_Management_Service::options_preview(1,$shared,version());
check($preview['allocations']===[1=>1000,2=>1000],'Equal allocation proposal');
$shared['allocations']=[1=>500,2=>1500];$v=version();
$saved=MI_Management_Service::save(1,'change_options',$shared,$v,'wp_7_12345678-1234-4234-8234-123456789b05');
check(!empty($saved['saved']),'Custom allocation saved');
check(!empty(MI_Management_Service::save(1,'change_options',$shared,$v,'wp_7_12345678-1234-4234-8234-123456789b05')['replayed']),'Allocation retry');
$detail=MI_Payment_Ledger::detail(1)['saldo'];$people=array_column($detail['individual']['people'],null,'id');
check($people[1]['total']===10500&&$people[2]['total']===11500,'Custom shares reflected in quotes');
$before=$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=1',ARRAY_A);
$shared['allocations']=[1=>1,999=>1999];
check(!empty(MI_Management_Service::save(1,'change_options',$shared,version(),'wp_7_12345678-1234-4234-8234-123456789b06')['rejected']),'Foreign participant rejected');
check($before===$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=1',ARRAY_A),'Invalid shares rolled back');
$adjust=['total_cents'=>21000,'reason'=>'Sconto'];
check(!empty(MI_Management_Service::save(1,'adjust_due',$adjust,version(),'wp_7_12345678-1234-4234-8234-123456789b07')['rejected']),'Ambiguous adjustment rejected');
$adjust['participant_id']=2;
check(!empty(MI_Management_Service::save(1,'adjust_due',$adjust,version(),'wp_7_12345678-1234-4234-8234-123456789b08')['saved']),'Selected adjustment saved');
$detail=MI_Payment_Ledger::detail(1)['saldo'];$people=array_column($detail['individual']['people'],null,'id');
check($people[1]['total']===10500&&$people[2]['total']===10500,'Only selected quote adjusted');
$equal=MI_Payment_People::common_shares(['order_options_json'=>'[{"quantity":1,"unit_price_cents":1001}]'],[['id'=>2],['id'=>1]]);
check($equal===[1=>501,2=>500],'Remainder cents distributed deterministically');
echo "PASS: quote, caparre, ripartizioni modificabili, rettifiche personali, credito, retry e rollback.\n";
