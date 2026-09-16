<?php
require __DIR__.'/innodb-fixture.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
function wp_date($format){return date($format);}
function get_the_title($id){return 'Evento sintetico';}
$wpdb=new DatabaseAdapter();$wpdb->db->select_db('mi_ledger_test');
$schema=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php');
foreach(['registrations'=>'registrations','payments'=>'payments','participants'=>'participants','items'=>'registration_items']as $key=>$table){preg_match('/CREATE TABLE \{\$'.$key.'\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);if(!$m||false===$wpdb->query('CREATE TEMPORARY TABLE wp_mi_'.$table.' ('.$m[1].') ENGINE=InnoDB'))throw new RuntimeException($wpdb->last_error);}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$wpdb->insert('wp_mi_registrations',['id'=>1,'event_id'=>42,'order_code'=>'PEOPLE-DEMO','status'=>'PENDING_PAYMENT','buyer_first_name'=>'Ada','buyer_last_name'=>'Esempio','buyer_phone'=>'','buyer_email'=>'ada@example.invalid','total_qty'=>2,'total_cents'=>60000,'initial_due_cents'=>20000,'economic_mode'=>'DEPOSIT_BALANCE','snapshot_json'=>json_encode(['event'=>['deposit_mode'=>'FIXED','deposit_fixed_cents'=>10000]]),'idempotency_key'=>'people-demo','created_at'=>gmdate('Y-m-d H:i:s')]);
check(!$wpdb->last_error,'registration fixture '.$wpdb->last_error);
$wpdb->insert('wp_mi_registration_items',['registration_id'=>1,'ticket_type_code'=>'base','quantity'=>2,'unit_price_cents'=>30000]);
foreach([1,2]as $id)$wpdb->insert('wp_mi_participants',['id'=>$id,'registration_id'=>1,'ticket_type_code'=>'base','ticket_index'=>$id,'first_name'=>'Persona','last_name'=>'Esempio '.$id,'status'=>'ACTIVE','options_json'=>'[]']);
$input=['request_id'=>'wp_7_12345678-1234-4234-8234-123456789abc','importo'=>'100','tipo'=>'INCASSO','metodo'=>'BONIFICO','data'=>'2026-09-14','rata'=>'DEPOSIT','participant_ids'=>'[2]'];
$result=MI_Payment_Ledger::save(1,$input);check(!empty($result['saved']),'deposit saved '.json_encode($result));
$detail=MI_Payment_Ledger::detail(1)['saldo'];check($detail['versato']===10000,'aggregate paid');check($detail['individual']['people'][0]['paid']===0&&$detail['individual']['people'][1]['paid']===10000,'only selected credited');
check(!empty(MI_Payment_Ledger::save(1,$input)['replayed']),'retry same request');
$changed=$input;$changed['participant_ids']='[1]';check(empty(MI_Payment_Ledger::save(1,$changed)['saved']),'same key different selection rejected');
$changed['request_id']='wp_7_12345678-1234-4234-8234-123456789abd';$changed['rata']='BALANCE';$changed['importo']='200';check(empty(MI_Payment_Ledger::save(1,$changed)['saved']),'balance before deposit rejected');
$changed['participant_ids']='[2]';check(!empty(MI_Payment_Ledger::save(1,$changed)['saved']),'selected balance saved');
$detail=MI_Payment_Ledger::detail(1)['saldo'];check($detail['individual']['people'][0]['balance']===30000&&$detail['individual']['people'][1]['balance']===0,'unselected debt unchanged');
check($wpdb->get_var('SELECT status FROM wp_mi_registrations WHERE id=1')==='PENDING_PAYMENT','saldo di una persona non conferma la prenotazione senza caparra altrui');
$changed['request_id']='wp_7_12345678-1234-4234-8234-123456789abe';check(empty(MI_Payment_Ledger::save(1,$changed)['saved']),'second payment of settled amount rejected');
$deposit=$input;$deposit['request_id']='wp_7_12345678-1234-4234-8234-123456789ab1';$deposit['participant_ids']='[1]';check(!empty(MI_Payment_Ledger::save(1,$deposit)['saved']),'caparra altra persona salvata');
check($wpdb->get_var('SELECT status FROM wp_mi_registrations WHERE id=1')==='CONFIRMED','prenotazione confermata quando ogni caparra personale è coperta');
$refund=['request_id'=>'wp_7_12345678-1234-4234-8234-123456789abf','importo'=>'50','tipo'=>'RIMBORSO','metodo'=>'BONIFICO','data'=>'2026-09-14','participant_ids'=>'[2]'];
check(!empty(MI_Payment_Ledger::save(1,$refund)['saved']),'rimborso individuale');
$detail=MI_Payment_Ledger::detail(1)['saldo'];$people=array_column($detail['individual']['people'],null,'id');
check($people[1]['paid']===10000&&$people[2]['paid']===25000&&$people[2]['balance']===5000,'rimborso attribuito solo alla persona scelta');
$refund['request_id']='wp_7_12345678-1234-4234-8234-123456789ac0';$refund['importo']='260';check(empty(MI_Payment_Ledger::save(1,$refund)['saved']),'rimborso superiore al versato personale rifiutato');
$refund['request_id']='wp_7_12345678-1234-4234-8234-123456789ac1';$refund['importo']='10';$refund['participant_ids']='[1,2]';check(empty(MI_Payment_Ledger::save(1,$refund)['saved']),'rimborso ambiguo su più persone rifiutato');
$full=MI_Payment_People::calculate(
	['id'=>2,'economic_mode'=>'FULL_PAYMENT','total_cents'=>30000,'initial_due_cents'=>0,'snapshot_json'=>'{}'],
	[['id'=>3,'ticket_type_code'=>'base','first_name'=>'Quota','last_name'=>'Intera','status'=>'ACTIVE','options_json'=>'[]','deposit_due_cents'=>10000]],
	[['ticket_type_code'=>'base','unit_price_cents'=>30000]],
	[]
);
check(!$full['deposit_plan']&&$full['people'][0]['deposit']===0&&$full['people'][0]['deposit_missing']===0&&$full['people'][0]['saldo']===30000,'caparra residua applicata fuori dal piano caparra/saldo');
echo "PASS InnoDB: attribuzione atomica, caparra e saldo, isolamento persone, rimborso individuale, retry e rifiuto duplicati.\n";
