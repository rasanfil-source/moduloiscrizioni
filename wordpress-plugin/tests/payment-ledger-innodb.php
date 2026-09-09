<?php
require __DIR__.'/innodb-fixture.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
$wpdb=new DatabaseAdapter();
$worker=$argv[1]??'';
if($worker!=='worker'){
 $wpdb->query('CREATE DATABASE IF NOT EXISTS mi_ledger_test');
}
$wpdb->db->select_db('mi_ledger_test');
if($worker!=='worker'){
 $schema=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php');
 foreach(['registrations','payments']as $name){
  preg_match('/CREATE TABLE \{\$'.$name.'\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);
  if(!$m)throw new RuntimeException('Schema mancante');
  $wpdb->query('DROP TABLE IF EXISTS wp_mi_'.$name);
  if($wpdb->query('CREATE TABLE wp_mi_'.$name.' ('.$m[1].') ENGINE=InnoDB')===false)throw new RuntimeException($wpdb->last_error);
 }
 $wpdb->query("INSERT INTO wp_mi_registrations (id,order_code,event_id,status,buyer_first_name,buyer_last_name,buyer_email,buyer_phone,total_qty,total_cents,initial_due_cents,idempotency_key,created_at) VALUES (1,'TEST',42,'PENDING_PAYMENT','Test','Locale','','',1,10000,2000,'test',NOW())");
}
$input=['request_id'=>'wp_7_12345678-1234-4234-8234-'.($argv[2]??'123456789abc'),'importo'=>'70','tipo'=>'INCASSO','metodo'=>'BONIFICO','data'=>'2026-09-09'];
if($worker==='worker'){echo json_encode(MI_Payment_Ledger::save(1,$input));exit;}
$php=PHP_BINARY;$ext=dirname($php).'/ext';$processes=[];
foreach(['123456789abc','123456789abd']as $suffix){$cmd=[$php,'-d','extension_dir='.$ext,'-d','extension=mbstring','-d','extension=mysqli',__FILE__,'worker',$suffix];$pipes=[];$proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$proc,$pipes];}
$success=0;
foreach($processes as [$proc,$pipes]){$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($exit!==0)throw new RuntimeException($error);$result=json_decode($output,true);if(!$result)throw new RuntimeException($output);if(!empty($result['saved']))$success++;}
if($success!==1||MI_Payment_Ledger::net_paid(1)!==7000)throw new RuntimeException('Concorrenza non sicura');
$row=$wpdb->get_row('SELECT workspace_revision,workspace_status,status FROM wp_mi_registrations WHERE id=1',ARRAY_A);
if((int)$row['workspace_revision']!==1||$row['workspace_status']!=='PENDING'||$row['status']!=='CONFIRMED')throw new RuntimeException('Stato incoerente');
echo "InnoDB reale: due incassi concorrenti da 70 su totale 100, uno solo registrato. Saldo, stato e revisione coerenti.\n";
