<?php
require __DIR__.'/innodb-fixture.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
$wpdb=new DatabaseAdapter();
$worker=$argv[1]??'';
$isWorker=in_array($worker,['worker','reverse_worker'],true);
if(!$isWorker){
 $wpdb->query('CREATE DATABASE IF NOT EXISTS mi_ledger_test');
}
$wpdb->db->select_db('mi_ledger_test');
if(!$isWorker){
 $schema=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php');
 foreach(['registrations'=>'registrations','payments'=>'payments','participants'=>'participants','registration_items'=>'items']as $name=>$variable){
  preg_match('/CREATE TABLE \{\$'.$variable.'\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);
  if(!$m)throw new RuntimeException('Schema mancante');
  $wpdb->query('DROP TABLE IF EXISTS wp_mi_'.$name);
  if($wpdb->query('CREATE TABLE wp_mi_'.$name.' ('.$m[1].') ENGINE=InnoDB')===false)throw new RuntimeException($wpdb->last_error);
 }
 $wpdb->query("INSERT INTO wp_mi_registrations (id,order_code,event_id,status,buyer_first_name,buyer_last_name,buyer_email,buyer_phone,total_qty,total_cents,initial_due_cents,idempotency_key,created_at) VALUES (1,'TEST',42,'PENDING_PAYMENT','Test','Locale','','',1,10000,2000,'test',NOW())");
 $wpdb->query("UPDATE wp_mi_registrations SET economic_mode='DEPOSIT_BALANCE',initial_due_cents=7000 WHERE id=1");
 $wpdb->query("INSERT INTO wp_mi_registration_items (registration_id,ticket_type_code,quantity,unit_price_cents) VALUES (1,'base',1,10000)");
 $wpdb->query("INSERT INTO wp_mi_participants (id,registration_id,ticket_type_code,first_name,last_name,status,deposit_due_cents) VALUES (1,1,'base','Persona','Test','ACTIVE',7000)");
}
$input=['request_id'=>'wp_7_12345678-1234-4234-8234-'.($argv[2]??'123456789abc'),'importo'=>'70','tipo'=>'INCASSO','metodo'=>'BONIFICO','data'=>'2026-09-09'];
$input+=['participant_ids'=>'[1]','rata'=>'DEPOSIT'];
if($isWorker){if($worker==='reverse_worker')$input=array_replace($input,['tipo'=>'STORNO','importo'=>'20','reverses_payment_id'=>$argv[3]]);echo json_encode(MI_Payment_Ledger::save(1,$input));exit;}
$php=PHP_BINARY;$ext=dirname($php).'/ext';$processes=[];
foreach(['123456789abc','123456789abd']as $suffix){$cmd=[$php,'-d','extension_dir='.$ext,'-d','extension=mbstring','-d','extension=mysqli',__FILE__,'worker',$suffix];$pipes=[];$proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$proc,$pipes];}
$success=0;
foreach($processes as [$proc,$pipes]){$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($exit!==0)throw new RuntimeException($error);$result=json_decode($output,true);if(!$result)throw new RuntimeException($output);if(!empty($result['saved']))$success++;}
if($success!==1||MI_Payment_Ledger::net_paid(1)!==7000)throw new RuntimeException('Concorrenza non sicura');
$row=$wpdb->get_row('SELECT workspace_revision,workspace_status,status FROM wp_mi_registrations WHERE id=1',ARRAY_A);
if((int)$row['workspace_revision']!==1||$row['workspace_status']!=='PENDING'||$row['status']!=='CONFIRMED')throw new RuntimeException('Stato incoerente');
echo "InnoDB reale: due incassi concorrenti da 70 su totale 100, uno solo registrato. Saldo, stato e revisione coerenti.\n";
$source=(int)$wpdb->get_var("SELECT id FROM wp_mi_payments WHERE transaction_kind='PAYMENT' LIMIT 1");
$counter=100;
function reversal_input($patch=[]){global $input,$counter;return array_replace($input,['request_id'=>'wp_7_12345678-1234-4234-8234-'.str_pad((string)++$counter,12,'0',STR_PAD_LEFT)],$patch);}
function assert_reversal($condition,$message){if(!$condition)throw new RuntimeException($message);}
$topup=MI_Payment_Ledger::save(1,reversal_input(['rata'=>'BALANCE','importo'=>'30']));
assert_reversal(!empty($topup['saved']),'Balance setup failed');
// A different participant has paid too: a valid global balance must not bypass source attribution.
$wpdb->query("INSERT INTO wp_mi_participants (id,registration_id,ticket_type_code,first_name,last_name,status,deposit_due_cents) VALUES (2,1,'base','Seconda','Persona','ACTIVE',7000)");
$wpdb->query("UPDATE wp_mi_registrations SET total_cents=20000,total_qty=2,initial_due_cents=14000 WHERE id=1");
$wpdb->query("UPDATE wp_mi_registration_items SET quantity=2 WHERE registration_id=1");
$other=MI_Payment_Ledger::save(1,reversal_input(['participant_ids'=>'[2]','importo'=>'70','rata'=>'DEPOSIT']));
assert_reversal(!empty($other['saved']),'Second participant setup failed');
assert_reversal(empty(MI_Payment_Ledger::save(1,reversal_input(['tipo'=>'STORNO','importo'=>'10','participant_ids'=>'[2]','reverses_payment_id'=>$source]))['saved']),'Different source participant accepted');
$wpdb->query("INSERT INTO wp_mi_payments (registration_id,transaction_kind,movement_kind,effective_at,amount_cents,payment_source,origin_channel,origin_id,created_at) VALUES (999,'PAYMENT','INCASSO',NOW(),5000,'CASH','TEST','foreign-source',NOW())");
$foreign=(int)$wpdb->get_var("SELECT id FROM wp_mi_payments WHERE origin_id='foreign-source'");
assert_reversal(empty(MI_Payment_Ledger::save(1,reversal_input(['tipo'=>'STORNO','importo'=>'10','reverses_payment_id'=>$foreign]))['saved']),'Foreign registration source accepted');
$reverseInput=reversal_input(['tipo'=>'STORNO','importo'=>'50','reverses_payment_id'=>$source]);
$reverse=MI_Payment_Ledger::save(1,$reverseInput);
assert_reversal(!empty($reverse['saved'])&&!empty(MI_Payment_Ledger::save(1,$reverseInput)['replayed']),'Linked reversal/retry failed');
assert_reversal((int)$wpdb->get_var('SELECT reverses_payment_id FROM wp_mi_payments WHERE id='.(int)$reverse['payment_id'])===$source,'Structured source not stored');
assert_reversal(empty(MI_Payment_Ledger::save(1,reversal_input(['tipo'=>'STORNO','importo'=>'30','reverses_payment_id'=>$source]))['saved']),'Source residual exceeded despite available global balance');
assert_reversal(empty(MI_Payment_Ledger::save(1,reversal_input(['tipo'=>'STORNO','importo'=>'10','reverses_payment_id'=>$reverse['payment_id']]))['saved']),'Refund selected as incoming payment');
$processes=[];
foreach(['987654321abc','987654321abd']as $suffix){$cmd=[$php,'-d','extension_dir='.$ext,'-d','extension=mbstring','-d','extension=mysqli',__FILE__,'reverse_worker',$suffix,(string)$source];$pipes=[];$proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$proc,$pipes];}
$success=0;
foreach($processes as [$proc,$pipes]){$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($exit!==0)throw new RuntimeException($error);$result=json_decode($output,true);if(!is_array($result))throw new RuntimeException($output);if(!empty($result['saved']))$success++;}
assert_reversal($success===1,'Concurrent reversal exceeded source amount');
assert_reversal((int)$wpdb->get_var('SELECT SUM(amount_cents) FROM wp_mi_payments WHERE reverses_payment_id='.$source)===7000,'Source total incoherent');
assert_reversal(MI_Payment_Ledger::net_paid(1)===10000,'Net amount after linked reversals incoherent');
echo "PASS InnoDB: storni collegati, stessa prenotazione/persona, residuo dell’incasso e concorrenza.\n";
