<?php
require __DIR__.'/innodb-fixture.php';
function is_wp_error($value){return $value instanceof WP_Error;}
function is_email($value){return filter_var($value,FILTER_VALIDATE_EMAIL)!==false;}
class MI_Portal_Management {static function allowed(){return true;}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-service.php';
$wpdb=new DatabaseAdapter();
$worker=($argv[1]??'')==='worker';
if(!$worker)$wpdb->query('CREATE DATABASE IF NOT EXISTS mi_ledger_test');
$wpdb->db->select_db('mi_ledger_test');
function booking_version($id){global $wpdb;$r=$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id='.(int)$id,ARRAY_A);$method=new ReflectionMethod(MI_Management_Service::class,'booking');return $method->invoke(null,$r)['version'];}
function save_change($id,$operation,$data,$suffix,$version=null){return MI_Management_Service::save($id,$operation,$data,$version??booking_version($id),'wp_7_12345678-1234-4234-8234-'.$suffix);}
function check($yes,$message){if(!$yes)throw new RuntimeException($message);}
if($worker){
 $id=(int)$argv[2];$version=$argv[3];
 echo json_encode(save_change($id,'participant',['number'=>1,'first_name'=>'Persona','last_name'=>'Test','fields'=>[],'room'=>'A'],'123456789ab'.$id,$version));exit;
}
$schema=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php');
foreach(['registrations','participants','rooms','management_state','management_requests']as $name){
 preg_match('/CREATE TABLE \{\$'.$name.'\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);check(!empty($m),'Schema assente');
 $wpdb->query('DROP TABLE IF EXISTS wp_mi_'.$name);
 check(false!==$wpdb->query('CREATE TABLE wp_mi_'.$name.' ('.$m[1].') ENGINE=InnoDB'),$wpdb->last_error);
}
foreach([1,2]as $id){
 check(false!==$wpdb->query("INSERT INTO wp_mi_registrations (id,order_code,event_id,status,buyer_first_name,buyer_last_name,buyer_email,buyer_phone,total_qty,total_cents,initial_due_cents,idempotency_key,created_at,snapshot_json) VALUES ($id,'TEST$id',42,'CONFIRMED','Test','Locale','','',1,10000,2000,'test$id',NOW(),'{}')"),$wpdb->last_error);
 check(false!==$wpdb->query("INSERT INTO wp_mi_participants (id,registration_id,first_name,last_name,extra_json) VALUES ($id,$id,'Persona','Test','{}')"),$wpdb->last_error);
}
$v=booking_version(1);$room=['code'=>'A','name'=>'Camera A','capacity'=>1];
$r=save_change(1,'room_save',$room,'123456789aaa',$v);check(!empty($r['saved']),'Camera non salvata');
$r=save_change(1,'room_save',$room,'123456789aaa',$v);check(!empty($r['replayed']),'Retry camera non idempotente');
$room['capacity']=2;$r=save_change(1,'room_save',$room,'123456789aaa',$v);check(!empty($r['rejected']),'Richiesta diversa accettata');
$processes=[];$php=PHP_BINARY;$ext=dirname($php).'/ext';
foreach([1,2]as $id){$cmd=[$php,'-d','extension_dir='.$ext,'-d','extension=mbstring','-d','extension=mysqli',__FILE__,'worker',(string)$id,booking_version($id)];$pipes=[];$proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$proc,$pipes];}
$success=0;
foreach($processes as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0,$err);$r=json_decode($out,true);check(is_array($r),$out);if(!empty($r['saved']))$success++;}
check($success===1,'Due assegnazioni hanno consumato un solo posto');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_participants WHERE room_code='A'")===1,'Capienza superata');
$r=save_change(1,'room_delete',['code'=>'A'],'123456789aac');check(!empty($r['rejected']),'Camera occupata eliminata');
$r=save_change(1,'participant',['number'=>1,'first_name'=>'Nuovo','last_name'=>'Test','fields'=>['arbitrary'=>'test']],'123456789aad');check(!empty($r['rejected']),'Campo arbitrario accettato');
check($wpdb->get_var('SELECT first_name FROM wp_mi_participants WHERE id=1')==='Persona','Scrittura parziale dopo rifiuto');
$r=save_change(1,'room_save',['code'=>'B','name'=>'Camera B','capacity'=>1],'123456789aae');check(!empty($r['saved']),'Seconda camera non salvata');
$empty=(int)$wpdb->get_var("SELECT id FROM wp_mi_participants WHERE room_code=''");
$r=save_change($empty,'participant',['number'=>1,'first_name'=>'Persona','last_name'=>'Test','fields'=>[],'room'=>'B'],'123456789aaf');check(!empty($r['saved']),'Seconda assegnazione non salvata');
$before=$wpdb->get_results('SELECT id,room_code FROM wp_mi_participants ORDER BY id',ARRAY_A);
$changes=array_map(fn($p)=>['order_code'=>'TEST'.$p['id'],'number'=>1,'key'=>'room','before'=>$p['room_code'],'after'=>$p['room_code']==='A'?'B':'A'],$before);
$r=MI_Management_Service::save_sheet(42,$changes,'wp_7_12345678-1234-4234-8234-123456789aba');check(!empty($r['saved']),'Scambio camere piene non riuscito');
$r=MI_Management_Service::save_sheet(42,$changes,'wp_7_12345678-1234-4234-8234-123456789aba');check(!empty($r['replayed']),'Retry scambio non idempotente');
$conflict=[['order_code'=>'TEST1','number'=>1,'key'=>'first_name','before'=>'Persona','after'=>'Nuovo'],['order_code'=>'TEST2','number'=>1,'key'=>'first_name','before'=>'Obsoleto','after'=>'Altro']];
$r=MI_Management_Service::save_sheet(42,$conflict,'wp_7_12345678-1234-4234-8234-123456789abb');check(!empty($r['rejected']),'Conflitto non rifiutato');
check($wpdb->get_var('SELECT first_name FROM wp_mi_participants WHERE id=1')==='Persona','Batch con conflitto non atomico');
echo "Batch Sheets: scambio camere piene, retry e rollback integrale in caso di conflitto verificati.\n";
echo "InnoDB gestione: retry, conflitto richiesta, concorrenza sull’ultimo posto, camera occupata e rifiuto senza scritture parziali verificati.\n";
