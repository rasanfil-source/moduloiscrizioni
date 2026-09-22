<?php
require __DIR__.'/innodb-fixture.php';
function is_wp_error($v){return $v instanceof WP_Error;}
function get_post_meta($id,$key,$single){return $GLOBALS['test_event_meta'][$key]??'';}
function get_the_title($id){return 'Pellegrinaggio ad Assisi';}
function wp_date($format){return date($format);}
class MI_Portal_Management {static function allowed(){return true;}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-service.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
require_once __DIR__.'/../modulo-iscrizioni/includes/class-mi-booking-search.php';
$source=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-registration-service.php');
$source=str_replace("require_once __DIR__ . '/class-mi-option-rules.php';", '', $source);
$source=preg_replace("/^require_once __DIR__ \\. '\\/class-mi-payment-people\\.php';\\R/m",'', $source);
eval(str_replace('class MI_Registration_Service','class MI_Registration_Validation_Test',preg_replace('/^<\?php\s*/','',$source)));
function check($value,$message){if(!$value)throw new RuntimeException($message);}
$wpdb=new DatabaseAdapter();$wpdb->db->select_db('mi_ledger_test');$GLOBALS['management_audit_test']=true;
$schema=file_get_contents(__DIR__.'/../modulo-iscrizioni/includes/class-mi-activator.php');
foreach(['registrations'=>'registrations','participants'=>'participants','items'=>'registration_items','payments'=>'payments','rooms'=>'rooms','registration_events'=>'registration_events','management_state'=>'management_state','management_requests'=>'management_requests']as $key=>$table){
 preg_match('/CREATE TABLE \{\$'.$key.'\} \((.*?)\) ENGINE=InnoDB/s',$schema,$m);check($m&&false!==$wpdb->query('CREATE TEMPORARY TABLE wp_mi_'.$table.' ('.$m[1].') ENGINE=InnoDB'),'schema '.$table.' '.$wpdb->last_error);
}
$bus=['code'=>'bus-andata','name'=>'Pullman di andata','scope'=>'TICKET','price_cents'=>2500,'max_quantity'=>1];
$snapshot=json_encode(['event'=>['options'=>[$bus],'participant_extra_scope'=>'ALL','deposit_mode'=>'FIXED','deposit_fixed_cents'=>10000]]);
$wpdb->insert('wp_mi_registrations',['id'=>1,'event_id'=>42,'order_code'=>'FAMILY','status'=>'CONFIRMED','buyer_first_name'=>'Raimondo','buyer_last_name'=>'Sanfilippo','buyer_email'=>'','buyer_phone'=>'','total_qty'=>2,'total_cents'=>62500,'initial_due_cents'=>20000,'balance_cents'=>42500,'economic_mode'=>'DEPOSIT_BALANCE','snapshot_json'=>$snapshot,'idempotency_key'=>'family','created_at'=>gmdate('Y-m-d H:i:s')]);
$wpdb->insert('wp_mi_registration_items',['registration_id'=>1,'ticket_type_code'=>'base','quantity'=>2,'unit_price_cents'=>30000]);
foreach([1,2]as $id)$wpdb->insert('wp_mi_participants',['id'=>$id,'registration_id'=>1,'ticket_type_code'=>'base','first_name'=>$id===1?'Raimondo':'Gustavo','last_name'=>$id===1?'Sanfilippo':'Lora','status'=>'ACTIVE','options_json'=>$id===1?'[]':json_encode([['code'=>'bus-andata','name'=>'Pullman di andata','quantity'=>1,'unit_price_cents'=>2500]])]);
check(false!==$wpdb->insert('wp_mi_payments',['registration_id'=>1,'transaction_kind'=>'PAYMENT','amount_cents'=>42500,'participant_allocations_json'=>json_encode([['participant_id'=>1,'name'=>'Raimondo','amount_cents'=>10000],['participant_id'=>2,'name'=>'Gustavo','amount_cents'=>32500]]),'origin_id'=>'fixture','payment_source'=>'BONIFICO','effective_at'=>gmdate('Y-m-d H:i:s'),'request_hash'=>str_repeat('a',64),'created_at'=>gmdate('Y-m-d H:i:s')]),$wpdb->last_error);
function version(){global $wpdb;$m=new ReflectionMethod(MI_Management_Service::class,'booking');return $m->invoke(null,$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=1',ARRAY_A))['version'];}
$data=['participant_id'=>2,'options'=>['bus-andata'=>0],'reason'=>'Accompagnato in auto'];$v=version();
$preview=MI_Management_Service::options_preview(1,$data,$v);
check($preview['before_total']===32500&&$preview['after_total']===30000&&$preview['credit']===2500,'preview quote e credito '.json_encode($preview));
check((int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=1')===62500,'preview ha scritto');
$request='wp_7_12345678-1234-4234-8234-123456789a01';
$result=MI_Management_Service::save(1,'change_options',$data,$v,$request);check(!empty($result['saved']),'salvataggio '.json_encode($result));
check((int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=1')===60000,'totale non aggiornato');
$r=MI_Payment_Ledger::detail(1)['saldo']['individual'];$rows=array_column($r['people'],null,'id');
check($r['ready']&&$rows[1]['total']===30000&&$rows[1]['paid']===10000&&$rows[1]['deposit']===10000,'altro iscritto modificato');
check($rows[2]['total']===30000&&$rows[2]['credit']===2500&&$rows[2]['paid']===32500,'credito individuale');
check((int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_payments')===1,'rimborso automatico');
$again=MI_Management_Service::save(1,'change_options',$data,$v,$request);check(!empty($again['replayed']),'retry duplicato');
$stale=MI_Management_Service::save(1,'change_options',$data,$v,'wp_7_12345678-1234-4234-8234-123456789a02');check(!empty($stale['rejected']),'versione obsoleta');
$pay=MI_Payment_Ledger::save(1,['request_id'=>'wp_7_12345678-1234-4234-8234-123456789a03','importo'=>'200','tipo'=>'INCASSO','metodo'=>'BONIFICO','data'=>'2026-09-14','rata'=>'BALANCE','participant_ids'=>'[1]']);check(!empty($pay['saved']),'credito Gustavo impedisce saldo Raimondo '.json_encode($pay));
$data['options']['bus-andata']=1;$result=MI_Management_Service::save(1,'change_options',$data,version(),'wp_7_12345678-1234-4234-8234-123456789a04');check(!empty($result['saved']),'aggiunta servizio');
check((int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=1')===62500,'aggiunta non ricalcolata');
$list=MI_Management_Service::all_people([42,43],'Gustavo Lora');check(count($list['items'])===1&&$list['items'][0]['number']==2,'ricerca persona non referente');
check(!MI_Management_Service::all_people([43],'')['items'],'scope non rispettato');
$foreign=$data;$foreign['participant_id']=999;check(is_wp_error(MI_Management_Service::options_preview(1,$foreign,version())),'persona estranea');
$GLOBALS['test_payment_permission']=false;
check(is_wp_error(MI_Management_Service::options_preview(1,$data,version())),'anteprima servizi senza permesso pagamenti');
check(is_wp_error(MI_Management_Service::save(1,'change_options',$data,version(),'wp_7_12345678-1234-4234-8234-123456789a05')),'salvataggio servizi senza permesso pagamenti');
$GLOBALS['test_payment_permission']=true;
echo "PASS: anteprima, rimozione/aggiunta servizi, quote personali, credito, caparre fisse, permesso pagamenti, retry, versioni e ricerca autorizzata.\n";
