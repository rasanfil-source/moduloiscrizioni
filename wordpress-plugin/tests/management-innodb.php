<?php
require __DIR__.'/innodb-fixture.php';
function is_wp_error($value){return $value instanceof WP_Error;}
function is_email($value){return filter_var($value,FILTER_VALIDATE_EMAIL)!==false;}
class MI_Portal_Management {static function allowed(){return true;}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-service.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-list.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
class MI_Shortcode {static function url_iscrizione($id){return 'https://example.invalid/iscrizione';}}
function remove_accents($value){return $value;}
$wpdb=new DatabaseAdapter();
$worker=in_array($argv[1]??'',['worker','auto-worker'],true);
if(!$worker)$wpdb->query('CREATE DATABASE IF NOT EXISTS mi_ledger_test');
$wpdb->db->select_db('mi_ledger_test');
function booking_version($id){global $wpdb;$r=$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id='.(int)$id,ARRAY_A);$method=new ReflectionMethod(MI_Management_Service::class,'booking');return $method->invoke(null,$r)['version'];}
function save_change($id,$operation,$data,$suffix,$version=null){return MI_Management_Service::save($id,$operation,$data,$version??booking_version($id),'wp_7_12345678-1234-4234-8234-'.$suffix);}
function check($yes,$message){if(!$yes)throw new RuntimeException($message);}
if($worker){
 if($argv[1]==='auto-worker'){$wpdb->query('START TRANSACTION');MI_Management_Service::lock_room_event(42);MI_Management_Service::auto_assign_rooms_locked((int)$argv[2]);$wpdb->query('COMMIT');echo 'ok';exit;}
 $id=(int)$argv[2];$version=$argv[3];
 echo json_encode(save_change($id,'participant',['number'=>1,'first_name'=>'Persona','last_name'=>'Test','fields'=>[],'room'=>'A'],'123456789ab'.$id,$version));exit;
}
$schema=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php');
foreach(['registrations','participants','rooms','payments','registration_events','management_state','management_requests']as $name){
 preg_match('/CREATE TABLE \{\$'.$name.'\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);check(!empty($m),'Schema assente');
 $wpdb->query('DROP TABLE IF EXISTS wp_mi_'.$name);
 check(false!==$wpdb->query('CREATE TABLE wp_mi_'.$name.' ('.$m[1].') ENGINE=InnoDB'),$wpdb->last_error);
}
function inventory_version(){global $wpdb;$method=new ReflectionMethod(MI_Management_Service::class,'rooms');return hash('sha256',wp_json_encode($method->invoke(null,42)));}
$empty_version=inventory_version();$inventory_data=['code'=>'PRE','name'=>'Prima camera','capacity'=>2];
$r=MI_Management_Service::save_event_room(42,'room_save',$inventory_data,$empty_version,'wp_7_12345678-1234-4234-8234-123456789ac1');check(!empty($r['saved']),'Inventario senza prenotazioni non salvato');
$r=MI_Management_Service::save_event_room(42,'room_save',$inventory_data,$empty_version,'wp_7_12345678-1234-4234-8234-123456789ac1');check(!empty($r['replayed']),'Retry inventario non idempotente');
$r=MI_Management_Service::save_event_room(42,'room_save',['code'=>'PRE','name'=>'Sovrascrittura','capacity'=>3],$empty_version,'wp_7_12345678-1234-4234-8234-123456789ac2');check(!empty($r['rejected']),'Versione inventario obsoleta accettata');
$r=MI_Management_Service::save_event_room(43,'room_save',$inventory_data,$empty_version,'wp_7_12345678-1234-4234-8234-123456789ac3');check($r instanceof WP_Error,'Inventario fuori ambito accettato');
$r=MI_Management_Service::save_event_room(42,'room_delete',['code'=>'PRE'],inventory_version(),'wp_7_12345678-1234-4234-8234-123456789ac4');check(!empty($r['saved']),'Camera vuota non eliminata');
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
$r=MI_Management_Service::save_event_room(42,'room_delete',['code'=>'A'],inventory_version(),'wp_7_12345678-1234-4234-8234-123456789ac5');check(!empty($r['rejected']),'Camera occupata eliminata dall’inventario');
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
$swap=array_map(fn($p)=>array_replace($p,['before'=>$p['after'],'after'=>$p['before']]),$changes);
$r=MI_Management_Service::save_sheet(42,$swap,'wp_7_12345678-1234-4234-8234-123456789abc','ROOM_SWAP');check(!empty($r['saved']),'Scambio dal portale non riuscito');
$r=MI_Management_Service::save_sheet(42,$swap,'wp_7_12345678-1234-4234-8234-123456789abc','ROOM_SWAP');check(!empty($r['replayed']),'Retry scambio portale non idempotente');
$r=MI_Management_Service::save_sheet(42,$conflict,'wp_7_12345678-1234-4234-8234-123456789abd','ROOM_SWAP');check($r instanceof WP_Error,'Scambio portale ha accettato campi diversi dalla camera');
echo "Batch Sheets: scambio camere piene, retry e rollback integrale in caso di conflitto verificati.\n";
echo "InnoDB gestione: retry, conflitto richiesta, concorrenza sull’ultimo posto, camera occupata e rifiuto senza scritture parziali verificati.\n";

$GLOBALS['management_audit_test']=true;
$wpdb->query("UPDATE wp_mi_registrations SET special_requests='Richiesta di prova' WHERE id=1");
$reviewVersion=booking_version(1);
$r=save_change(1,'request_review',['reviewed'=>true],'123456789ad1',$reviewVersion);check(!empty($r['saved']),'Verifica richiesta non salvata');
$r=save_change(1,'request_review',['reviewed'=>true],'123456789ad1',$reviewVersion);check(!empty($r['replayed']),'Retry verifica non idempotente');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_registration_events WHERE event_type='MANAGEMENT_request_review'")===1,'Audit verifica duplicato');
$method=new ReflectionMethod(MI_Management_Service::class,'request_review');
$registration=$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=1',ARRAY_A);
check($method->invoke(null,$registration)['reviewed'],'Verifica non visibile');
$registration['special_requests']='Testo cambiato';check(!$method->invoke(null,$registration)['reviewed'],'Verifica mantenuta su testo cambiato');
$r=save_change(1,'request_review',['reviewed'=>false],'123456789ad2',$reviewVersion);check(!empty($r['rejected']),'Verifica obsoleta accettata');
echo "Richieste: audit, retry, conflitto e invalidazione dopo cambio testo verificati.\n";
$attendanceVersion=booking_version(1);
$r=save_change(1,'attendance',['participant_id'=>1,'attendance'=>'PRESENT'],'123456789ae1',$attendanceVersion);check(!empty($r['saved']),'Presenza non salvata');
$r=save_change(1,'attendance',['participant_id'=>1,'attendance'=>'PRESENT'],'123456789ae1',$attendanceVersion);check(!empty($r['replayed']),'Retry presenza non idempotente');
$method=new ReflectionMethod(MI_Management_Service::class,'booking');$registration=$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=1',ARRAY_A);
check($method->invoke(null,$registration)['participants'][0]['attendance']['state']==='PRESENT','Presenza non leggibile');
$r=save_change(1,'attendance',['participant_id'=>2,'attendance'=>'PRESENT'],'123456789ae2');check(!empty($r['rejected']),'Presenza su persona di altro ordine accettata');
$wpdb->query("UPDATE wp_mi_registrations SET status='WAITLISTED' WHERE id=1");
$r=save_change(1,'attendance',['participant_id'=>1,'attendance'=>'PRESENT'],'123456789ae3');check(!empty($r['rejected']),'Presenza di persona in attesa accettata');
echo "Presenze: audit, retry, appartenenza e ammissione verificati.\n";
$wpdb->query("UPDATE wp_mi_registrations SET status='CONFIRMED',economic_mode='DEPOSIT_BALANCE',total_cents=10000,initial_due_cents=2000,balance_cents=8000 WHERE id=1");
$version=booking_version(1);$r=save_change(1,'adjust_due',['total_cents'=>1500,'reason'=>'Riduzione concordata'],'123456789af1',$version);check(!empty($r['saved']),'Rettifica non salvata');
$r=save_change(1,'adjust_due',['total_cents'=>1500,'reason'=>'Riduzione concordata'],'123456789af1',$version);check(!empty($r['replayed']),'Rettifica duplicata al retry');
$row=$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=1',ARRAY_A);check((int)$row['total_cents']===1500&&(int)$row['initial_due_cents']===1500&&(int)$row['balance_cents']===0,'Piano rettificato incoerente');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_payments')===0,'Rettifica ha creato un movimento');
$audit=json_decode($wpdb->get_var("SELECT detail_json FROM wp_mi_registration_events WHERE event_type='MANAGEMENT_adjust_due' ORDER BY id DESC LIMIT 1"),true);check($audit['before_total']===10000&&$audit['after_total']===1500,'Importi precedenti non conservati');
$r=save_change(1,'adjust_due',['total_cents'=>1000,'reason'=>''],'123456789af2');check(!empty($r['rejected']),'Rettifica priva di motivo accettata');
$wpdb->query("UPDATE wp_mi_registrations SET status='CANCELLED' WHERE id=1");
$r=save_change(1,'adjust_due',['total_cents'=>0,'reason'=>'Annullamento concordato'],'123456789af3');check(!empty($r['saved']),'Rettifica annullamento non salvata');check($wpdb->get_var('SELECT status FROM wp_mi_registrations WHERE id=1')==='CANCELLED','Rettifica ha riaperto prenotazione annullata');
echo "Rettifiche: importi precedenti, motivazione, retry, piano coerente e assenza di movimenti automatici verificati.\n";

// Load the production validator under a test alias, keeping queue fixture stubs.
$source=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-registration-service.php');
$source=preg_replace('/^<\?php\s*/','',$source);$source=str_replace('class MI_Registration_Service','class MI_Registration_Validation_Test',$source);eval($source);
$definitions=[['code'=>'single','name'=>'Singola','scope'=>'TICKET','price_cents'=>5000,'max_quantity'=>1,'choice_group'=>'room'],['code'=>'double','name'=>'Doppia','scope'=>'TICKET','price_cents'=>3000,'max_quantity'=>1,'choice_group'=>'room']];
$snapshot=json_encode(['event'=>['options'=>$definitions,'participant_extra_scope'=>'ALL']]);
$wpdb->query($wpdb->prepare("UPDATE wp_mi_registrations SET status='CONFIRMED',snapshot_json=%s,total_cents=5000 WHERE id=1",$snapshot));
$wpdb->query($wpdb->prepare("UPDATE wp_mi_participants SET options_json=%s WHERE id=1",json_encode([['code'=>'single','name'=>'Singola','quantity'=>1,'unit_price_cents'=>5000]])));
$version=booking_version(1);$change=['participant_id'=>1,'options'=>['single'=>0,'double'=>1],'reason'=>'Passaggio concordato a doppia'];
$r=save_change(1,'change_options',$change,'123456789ab4',$version);check(!empty($r['saved']),'Cambio servizi non salvato');
$r=save_change(1,'change_options',$change,'123456789ab4',$version);check(!empty($r['replayed']),'Retry cambio servizi non idempotente');
$options=json_decode($wpdb->get_var('SELECT options_json FROM wp_mi_participants WHERE id=1'),true);check(count($options)===1&&$options[0]['code']==='double','Sistemazione non aggiornata');
check((int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=1')===5000,'Cambio servizi ha rettificato automaticamente il dovuto');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_payments')===0,'Cambio servizi ha creato un movimento');
$invalid=$change;$invalid['options']=['double'=>-1];$r=save_change(1,'change_options',$invalid,'123456789ab5');check(!empty($r['rejected']),'Quantità negativa accettata nel salvataggio');
$audit=json_decode($wpdb->get_var("SELECT detail_json FROM wp_mi_registration_events WHERE event_type='MANAGEMENT_change_options' ORDER BY id DESC LIMIT 1"),true);check($audit['before_options'][0]['code']==='single'&&$audit['after_options'][0]['code']==='double','Variazione non tracciata');
echo "Cambio singola/doppia: transazione, retry, audit e separazione del rimborso verificati.\n";
function get_post_meta($id,$key,$single){return 5;}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-attendance-report.php';
$version=booking_version(1);$link=['participant_id'=>1,'target_id'=>2,'target_order'=>'TEST2','target_number'=>1];
$r=save_change(1,'identity_link',$link,'123456789ab6',$version);check(!empty($r['saved']),'Collegamento personale non salvato');
$r=save_change(1,'identity_link',$link,'123456789ab6',$version);check(!empty($r['replayed']),'Retry collegamento non idempotente');
$link['target_id']=99;$r=save_change(1,'identity_link',$link,'123456789ab7');check(!empty($r['rejected']),'Riferimento diverso dall’anteprima accettato');
$r=save_change(1,'identity_link',['participant_id'=>1,'target_id'=>0],'123456789ab8');check(!empty($r['saved']),'Rimozione collegamento non salvata');
$method=new ReflectionMethod(MI_Management_Service::class,'booking');$registration=$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=1',ARRAY_A);check($method->invoke(null,$registration)['participants'][0]['identity_target']===0,'Collegamento rimosso ancora attivo');
echo "Identità: collegamento esplicito, retry, riferimento verificato e rimozione tracciata verificati.\n";
$summary=MI_Management_Service::summary(42);
check(!is_wp_error($summary),'Riepilogo MySQL non leggibile');
$page=MI_Management_List::page($summary,['query'=>'Persona','view'=>'people'],0,1);
check(count($page['rows'])===1 && $page['total']>=1,'Paginazione da riepilogo MySQL');
check(isset($page['rows'][0]['fields'],$page['rows'][0]['attendance']),'Pagina priva dei dati operativi');
check(is_wp_error(MI_Management_Service::summary(43)),'Riepilogo fuori ambito accessibile');
echo "MySQL → riepilogo → pagina: dati operativi e ambito verificati.\n";

// Shared room codes across separate registrations; M is an individual dormitory code.
$GLOBALS['management_audit_test']=true;
$wpdb->query("UPDATE wp_mi_registrations SET status='CONFIRMED' WHERE id IN (1,2)");
$wpdb->query("UPDATE wp_mi_participants SET status='ACTIVE',room_code='' WHERE id IN (1,2)");
$requested=json_encode([['code'=>'alloggio-doppia-separati','quantity'=>1]]);
$wpdb->query($wpdb->prepare('UPDATE wp_mi_participants SET options_json=%s WHERE id IN (1,2)',$requested));
$beforeMoney=$wpdb->get_results('SELECT id,total_cents FROM wp_mi_registrations ORDER BY id',ARRAY_A);
$assign=[];foreach([1,2]as $id)$assign[]=['order_code'=>'TEST'.$id,'number'=>1,'key'=>'room','before'=>'','after'=>'DS1','type'=>'alloggio-doppia-separati'];
$r=MI_Management_Service::save_sheet(42,$assign,'wp_7_12345678-1234-4234-8234-123456780001','ROOM_ASSIGN');check(!empty($r['saved']),'Abbinamento DS tra iscrizioni diverse non salvato: '.json_encode($r));
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_participants WHERE room_code='DS1'")===2,'DS1 non condivisa');
$r=MI_Management_Service::save_sheet(42,$assign,'wp_7_12345678-1234-4234-8234-123456780001','ROOM_ASSIGN');check(!empty($r['replayed']),'Retry assegnazione non idempotente');
$audit=json_decode($wpdb->get_var("SELECT detail_json FROM wp_mi_registration_events WHERE event_type='ROOM_ASSIGN' ORDER BY id DESC LIMIT 1"),true);check($audit['assignments'][0]['after']==='DS1','Audit assegnazione assente');
check($beforeMoney===$wpdb->get_results('SELECT id,total_cents FROM wp_mi_registrations ORDER BY id',ARRAY_A),'Assegnazione modifica importi');
$wpdb->query("INSERT INTO wp_mi_registrations (id,order_code,event_id,status,buyer_first_name,buyer_last_name,buyer_email,buyer_phone,total_qty,total_cents,initial_due_cents,idempotency_key,created_at,snapshot_json) VALUES (3,'TEST3',42,'CONFIRMED','Test','Locale','','',1,10000,2000,'test3',NOW(),'{}')");
$wpdb->query($wpdb->prepare("INSERT INTO wp_mi_participants (id,registration_id,first_name,last_name,extra_json,options_json) VALUES (3,3,'Terza','Persona','{}',%s)",$requested));
$third=[['order_code'=>'TEST3','number'=>1,'key'=>'room','before'=>'','after'=>'DS1','type'=>'alloggio-doppia-separati']];
$r=MI_Management_Service::save_sheet(42,$third,'wp_7_12345678-1234-4234-8234-123456780007','ROOM_ASSIGN');check(!empty($r['rejected']),'Terza persona ammessa in una doppia');
$wrong=$assign;$wrong[0]['before']='DS1';$wrong[0]['after']='T1';$r=MI_Management_Service::save_sheet(42,$wrong,'wp_7_12345678-1234-4234-8234-123456780002','ROOM_ASSIGN');check(!empty($r['rejected']),'Prefisso estraneo accettato');
$wpdb->query($wpdb->prepare('UPDATE wp_mi_participants SET options_json=%s WHERE id IN (1,2)',json_encode([['code'=>'alloggio-multipla','quantity'=>1]])));
$multiple=[];foreach([1,2]as $id)$multiple[]=['order_code'=>'TEST'.$id,'number'=>1,'key'=>'room','before'=>'DS1','after'=>'M1','type'=>'alloggio-multipla'];
$r=MI_Management_Service::save_sheet(42,$multiple,'wp_7_12345678-1234-4234-8234-123456780003','ROOM_ASSIGN');check(!empty($r['rejected']),'Codice M condiviso accettato');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_rooms WHERE code='M1'")===0,'Camera creata non annullata dopo rifiuto');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_participants WHERE room_code='DS1'")===2,'Batch rifiutato ha modificato persone');
$multiple[1]['after']='M2';$r=MI_Management_Service::save_sheet(42,$multiple,'wp_7_12345678-1234-4234-8234-123456780004','ROOM_ASSIGN');check(!empty($r['saved']),'Progressivi M individuali rifiutati');
$r=MI_Management_Service::save_sheet(43,$multiple,'wp_7_12345678-1234-4234-8234-123456780005','ROOM_ASSIGN');check($r instanceof WP_Error,'Assegnazione fuori evento accettata');
$stale=$multiple;$stale[0]['before']='DS1';$stale[0]['after']='M3';$r=MI_Management_Service::save_sheet(42,$stale,'wp_7_12345678-1234-4234-8234-123456780006','ROOM_ASSIGN');check(!empty($r['rejected']),'Assegnazione obsoleta accettata');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_rooms WHERE code='M3'")===0,'Nuovo codice persistito dopo conflitto');
$wpdb->query($wpdb->prepare('UPDATE wp_mi_participants SET options_json=%s WHERE id IN (1,2,3)',json_encode([['code'=>'alloggio-tripla','quantity'=>1]])));
$triples=[];foreach([1,2,3]as $id)$triples[]=['order_code'=>'TEST'.$id,'number'=>1,'key'=>'room','before'=>$id===3?'':'M'.$id,'after'=>'T1','type'=>'alloggio-tripla'];
$r=MI_Management_Service::save_sheet(42,$triples,'wp_7_12345678-1234-4234-8234-123456780008','ROOM_ASSIGN');check(!empty($r['saved']),'Tripla tra tre iscrizioni distinte non salvata');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_participants WHERE room_code='T1'")===3,'Tripla incompleta');
echo "Camere: abbinamento tra iscrizioni, progressivi M individuali, audit, retry e rollback verificati.\n";

function seed_auto_rooms($id,$types,$status='CONFIRMED'){
	global $wpdb;
	$wpdb->query($wpdb->prepare("INSERT INTO wp_mi_registrations (id,order_code,event_id,status,buyer_first_name,buyer_last_name,buyer_email,buyer_phone,total_qty,total_cents,initial_due_cents,idempotency_key,created_at,snapshot_json) VALUES (%d,%s,42,%s,'Auto','Test','','',%d,10000,2000,%s,NOW(),'{}')",$id,'AUTO'.$id,$status,count($types),'auto-key-'.$id));
	foreach($types as $type)$wpdb->query($wpdb->prepare("INSERT INTO wp_mi_participants (registration_id,first_name,last_name,extra_json,options_json) VALUES (%d,'Auto','Persona','{}',%s)",$id,json_encode([['code'=>'alloggio-'.$type,'quantity'=>1]])));
}
function run_auto_rooms($id){global $wpdb;$wpdb->query('START TRANSACTION');try{MI_Management_Service::lock_room_event(42);MI_Management_Service::auto_assign_rooms_locked($id);$wpdb->query('COMMIT');}catch(Throwable $e){$wpdb->query('ROLLBACK');throw $e;}}
function assigned_rooms($id){global $wpdb;return array_column($wpdb->get_results('SELECT room_code FROM wp_mi_participants WHERE registration_id='.(int)$id.' ORDER BY id',ARRAY_A),'room_code');}
foreach([10=>['singola','singola'],11=>['multipla','multipla'],12=>['doppia-matrimoniale','doppia-matrimoniale'],13=>['doppia-separati','doppia-separati'],14=>['tripla','tripla','tripla'],15=>['doppia-separati'],16=>['doppia-separati','doppia-separati','doppia-separati','doppia-separati'],17=>['doppia-separati','singola']]as $id=>$types){seed_auto_rooms($id,$types);run_auto_rooms($id);}
check(assigned_rooms(10)===['S1','S2'],'Singole senza progressivi individuali');
check(assigned_rooms(11)===['M3','M4'],'Multiple senza progressivi individuali successivi');
foreach([12=>'DM1',13=>'DS2',14=>'T2']as $id=>$code)check(count(array_unique(assigned_rooms($id)))===1&&assigned_rooms($id)[0]===$code,'Gruppo congiunto senza codice condiviso '.$id);
check(assigned_rooms(15)===['']&&assigned_rooms(16)===['','','',''],'Richieste da abbinare assegnate automaticamente');
check(assigned_rooms(17)===['','S3'],'Iscrizione mista abbinata impropriamente');
$before=assigned_rooms(12);$audit_count=(int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_registration_events WHERE event_type='AUTO_ROOM_ASSIGN'");run_auto_rooms(12);check($before===assigned_rooms(12)&&(int)$wpdb->get_var("SELECT COUNT(*) FROM wp_mi_registration_events WHERE event_type='AUTO_ROOM_ASSIGN'")===$audit_count,'Automatismo ripetuto modifica assegnazioni esistenti');
seed_auto_rooms(18,['singola'],'WAITLISTED');run_auto_rooms(18);check(assigned_rooms(18)===[''],'Lista attesa assegnata prima dell’ammissione');
$wpdb->query("UPDATE wp_mi_registrations SET status='CONFIRMED' WHERE id=18");run_auto_rooms(18);check(assigned_rooms(18)===['S4'],'Assegnazione dopo ammissione assente');
echo "Automatismi: progressivi S/M, gruppi congiunti DM/DS/T, esclusione richieste incomplete e lista attesa verificati.\n";
seed_auto_rooms(19,['singola']);seed_auto_rooms(20,['singola']);$processes=[];
foreach([19,20]as $id){$pipes=[];$proc=proc_open([PHP_BINARY,'-d','extension_dir='.dirname(PHP_BINARY).'/ext','-d','extension=mbstring','-d','extension=mysqli',__FILE__,'auto-worker',(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$proc,$pipes];}
foreach($processes as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0&&$out==='ok','Automatismo concorrente fallito: '.$out.$err);}
$codes=array_merge(assigned_rooms(19),assigned_rooms(20));sort($codes);check($codes===['S5','S6'],'Numeri automatici duplicati in concorrenza');
echo "Progressivi automatici concorrenti: nessuna duplicazione.\n";
