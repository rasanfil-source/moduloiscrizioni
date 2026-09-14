<?php
define('ABSPATH',__DIR__);
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-people.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function reject($fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Expected rejection');}
$r=['id'=>1,'total_cents'=>60000,'initial_due_cents'=>20000,'economic_mode'=>'DEPOSIT_BALANCE','snapshot_json'=>json_encode(['event'=>['deposit_mode'=>'FIXED','deposit_fixed_cents'=>10000]])];
$people=[['id'=>1,'ticket_type_code'=>'base','first_name'=>'Ada','last_name'=>'Esempio','status'=>'ACTIVE','options_json'=>'[]'],['id'=>2,'ticket_type_code'=>'base','first_name'=>'Luca','last_name'=>'Esempio','status'=>'ACTIVE','options_json'=>'[]']];
$items=[['ticket_type_code'=>'base','unit_price_cents'=>30000]];
$p=MI_Payment_People::calculate($r,$people,$items,[]);
check($p['ready'],'initial ready');check($p['people'][0]['deposit']===10000,'fixed deposit');
$plan=MI_Payment_People::plan($p,[2],'DEPOSIT',10000);
reject(fn()=>MI_Payment_People::plan($p,[2],'BALANCE',20000));
reject(fn()=>MI_Payment_People::plan($p,[1,2],'DEPOSIT',10000));
reject(fn()=>MI_Payment_People::plan($p,[2,2],'DEPOSIT',20000));
reject(fn()=>MI_Payment_People::plan($p,[999],'FULL',30000));
$payments=[['transaction_kind'=>'PAYMENT','amount_cents'=>10000,'participant_allocations_json'=>json_encode($plan)]];
$p=MI_Payment_People::calculate($r,$people,$items,$payments);
check($p['people'][0]['paid']===0&&$p['people'][1]['paid']===10000,'only selected person credited');
check(MI_Payment_People::plan($p,[2],'BALANCE',20000)[0]['amount_cents']===20000,'balance after deposit');
reject(fn()=>MI_Payment_People::plan($p,[1,2],'BALANCE',40000));
check(count(MI_Payment_People::plan($p,[1,2],'FULL',50000))===2,'total outstanding mixed states');
$old=MI_Payment_People::calculate($r,$people,$items,[['transaction_kind'=>'PAYMENT','amount_cents'=>10000]]);check(!$old['ready'],'no invented historical allocation');
$single=$r;$single['economic_mode']='FULL_PAYMENT';$p=MI_Payment_People::calculate($single,$people,$items,[]);reject(fn()=>MI_Payment_People::plan($p,[1],'DEPOSIT',10000));
echo "PASS: caparra, saldo successivo, totale, selezioni isolate, importi esatti, duplicati, persone estranee e storico non attribuito.\n";
