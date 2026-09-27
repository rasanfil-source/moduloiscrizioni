<?php
// Real management handlers, real deadline algorithm, isolated temporary InnoDB tables.
$root=dirname(__DIR__,2);$tests=$root.'/wordpress-plugin/tests';
$fixture=file_get_contents($tests.'/innodb-fixture.php');
$fixture=preg_replace('/static function reopened_payment_deadline\(.*?(?=static function append_registration_event)/s','static function reopened_payment_deadline($registration,$now=null){return MI_Registration_Validation_Test::reopened_payment_deadline($registration,$now);}',$fixture);
eval('?>'.str_replace('__DIR__',var_export($tests,true),$fixture));
$prefix=explode('$bus=',file_get_contents($tests.'/individual-management-innodb.php'),2)[0];
$prefix=str_replace("require __DIR__.'/innodb-fixture.php';",'',$prefix);
eval('?>'.str_replace('__DIR__.',var_export($tests,true).'.',$prefix));
require_once $root.'/wordpress-plugin/modulo-iscrizioni/includes/class-mi-field-schema.php';
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL)!==false;}
function sanitize_email($v){return filter_var($v,FILTER_SANITIZE_EMAIL);}
function result_extra($name,$data){echo json_encode(['case'=>$name]+$data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";}
function request_extra(){static $n=0;return 'wp_7_12345678-1234-4234-8234-'.str_pad((string)++$n,12,'0',STR_PAD_LEFT);}
function row_extra(){global $wpdb;return $wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=1',ARRAY_A);}
function booking_extra(){return (new ReflectionMethod(MI_Management_Service::class,'booking'))->invoke(null,row_extra());}
function save_extra($operation,$data){$r=MI_Management_Service::save(1,$operation,$data,booking_extra()['version'],request_extra());check(!empty($r['saved']),'Management save failed: '.json_encode($r));return $r;}
$bus=['code'=>'bus-andata','name'=>'Bus','scope'=>'TICKET','price_cents'=>1000,'max_quantity'=>1];
$fields=[['key'=>'participant_phone','type'=>'tel'],['key'=>'participant_email','type'=>'email'],['key'=>'birth_date','type'=>'date'],['key'=>'document_expiry','type'=>'date','date_rule'=>'future']];
$snapshot=json_encode(['event'=>['options'=>[$bus],'participant_fields'=>$fields,'participant_extra_scope'=>'ALL','pricing_mode'=>'CALCULATED']]);
$GLOBALS['test_event_meta']['_mi_bus_assignment_enabled']='1';
check(false!==$wpdb->insert('wp_mi_registrations',['id'=>1,'event_id'=>42,'order_code'=>'AUDIT1','status'=>'PENDING_PAYMENT','buyer_first_name'=>'Referente','buyer_last_name'=>'Prova','buyer_email'=>'referente@example.invalid','buyer_phone'=>'+393331234567','total_qty'=>1,'total_cents'=>11000,'initial_due_cents'=>11000,'balance_cents'=>0,'economic_mode'=>'FULL_PAYMENT','snapshot_json'=>$snapshot,'idempotency_key'=>'extra-audit','created_at'=>gmdate('Y-m-d H:i:s')]),'Seed registration '.$wpdb->last_error);
$wpdb->insert('wp_mi_registration_items',['registration_id'=>1,'ticket_type_code'=>'base','quantity'=>1,'unit_price_cents'=>10000]);
$wpdb->insert('wp_mi_participants',['id'=>1,'registration_id'=>1,'ticket_type_code'=>'base','first_name'=>'Persona','last_name'=>'Prova','status'=>'ACTIVE','options_json'=>json_encode([['code'=>'bus-andata','name'=>'Bus','quantity'=>1,'unit_price_cents'=>1000]]),'extra_json'=>'{}']);
$data=['participant_id'=>1,'options'=>['bus-andata'=>1],'bus'=>'A','reason'=>''];
$preview=MI_Management_Service::options_preview(1,$data,booking_extra()['version']);
$before=row_extra();save_extra('change_options',$data);$after=row_extra();
check($before['expires_at']===null&&$after['expires_at']===null&&(int)$before['total_cents']===(int)$after['total_cents']&&$preview['delta']===0,'Logistics deadline reproduction');
result_extra('1_logistics_deadline',['passed'=>true,'delta'=>$preview['delta'],'before'=>$before['expires_at'],'after'=>$after['expires_at'],'deadline_preserved'=>$after['expires_at']===null]);
$wpdb->update('wp_mi_registrations',['expires_at'=>null,'payment_deadline_at'=>null],['id'=>1]);
save_extra('adjust_due',['participant_id'=>1,'total_cents'=>11000,'reason'=>'Conferma senza rettifica']);
$after=row_extra();check($after['expires_at']===null,'No-op due deadline preserved');
result_extra('1_noop_adjust_due',['passed'=>true,'total_before'=>11000,'total_after'=>(int)$after['total_cents'],'expires_at'=>$after['expires_at']]);
$old=gmdate('Y-m-d H:i:s',time()-3600);$wpdb->update('wp_mi_registrations',['expires_at'=>$old,'payment_deadline_at'=>$old],['id'=>1]);
$data['bus']='B';save_extra('change_options',$data);$after=row_extra();
check($after['expires_at']===$old,'Expired deadline must not extend');
result_extra('1_expired_deadline_extension',['passed'=>true,'before'=>$old,'after'=>$after['expires_at']]);
$changes=[['order_code'=>'AUDIT1','number'=>1,'key'=>'phone','before'=>'+393331234567','after'=>''],['order_code'=>'AUDIT1','number'=>1,'key'=>'email','before'=>'referente@example.invalid','after'=>'']];
$r=MI_Management_Service::save_sheet(42,$changes,request_extra());check(!empty($r['saved']),'Clear contacts failed '.json_encode($r));
$person=$wpdb->get_row('SELECT * FROM wp_mi_participants WHERE id=1',ARRAY_A);$stored=json_decode($person['extra_json'],true);
check($stored['participant_phone']===''&&$stored['participant_email']==='','Explicit empty fields');
result_extra('4_clear_contact_saved',['saved'=>true,'stored'=>$stored,'confirmations'=>$r['confirmations']]);
$invalid=['participant_phone'=>'non e un numero','birth_date'=>'2099-01-01','document_expiry'=>'1900-01-01'];
$beforeFields=$wpdb->get_var('SELECT extra_json FROM wp_mi_participants WHERE id=1');
foreach($invalid as $key=>$value){
 $r=MI_Management_Service::save(1,'participant',['number'=>1,'first_name'=>'Persona','last_name'=>'Prova','room'=>'','fields'=>[$key=>$value]],booking_extra()['version'],request_extra());
 check(!empty($r['rejected'])&&$wpdb->get_var('SELECT extra_json FROM wp_mi_participants WHERE id=1')===$beforeFields,'Invalid field accepted: '.$key);
}
$phoneChange=['order_code'=>'AUDIT1','number'=>1,'key'=>'phone','before'=>'','after'=>'3331234567'];$request=request_extra();
$r=MI_Management_Service::save_sheet(42,[$phoneChange],$request);
check(!empty($r['saved'])&&($r['confirmations'][0]['accepted']??null)==='+39 333 1234567','Normalized phone receipt '.json_encode($r));
$retry=MI_Management_Service::save_sheet(42,[$phoneChange],$request);
check(!empty($retry['replayed'])&&$r['confirmations']===$retry['confirmations'],'Phone receipt replay');
$retry=MI_Management_Service::save_sheet(42,[$phoneChange],request_extra());
check(!empty($retry['saved'])&&count($retry['confirmations'])===1,'Normalized phone recovery');
$wpdb->update('wp_mi_participants',['extra_json'=>json_encode(['document_expiry'=>'2000-01-01'])],['id'=>1]);
save_extra('participant',['number'=>1,'first_name'=>'Persona','last_name'=>'Corretta','room'=>'','fields'=>['document_expiry'=>'2000-01-01']]);
result_extra('4_validation_and_receipts',['passed'=>true]);
foreach(['participant_phone'=>'399991112233','participant_email'=>'persona-unica@example.invalid','mobile'=>'398887776655']as $key=>$value){
 $wpdb->update('wp_mi_participants',['extra_json'=>json_encode([$key=>$value])],['id'=>1]);
 $list=MI_Management_Service::all_people([42],$value);
 $sql=MI_Booking_Search::sql($value);$matches=(int)$wpdb->get_var('SELECT COUNT(*) FROM wp_mi_registrations r WHERE '.$sql);
 check(count($list['items'])===1&&$matches===1,'Alias search reproduction '.$key);
 result_extra('6_search_'.$key,['passed'=>true,'all_people_matches'=>count($list['items']),'booking_search_matches'=>$matches]);
}
check($wpdb->last_error==='','Unexpected database error');

// Keep quantities within configured limits, including a no-op staff save.
$bus['max_quantity']=3;$snapshot=json_decode($snapshot,true);$snapshot['event']['options']=[$bus];
$wpdb->update('wp_mi_registrations',['snapshot_json'=>json_encode($snapshot)],['id'=>1]);
save_extra('change_options',['participant_id'=>1,'options'=>['bus-andata'=>3],'reason'=>'']);
$savedOptions=json_decode($wpdb->get_var('SELECT options_json FROM wp_mi_participants WHERE id=1'),true);
check($savedOptions[0]['quantity']===3&&(int)row_extra()['total_cents']===13000,'Multiple service quantities');
$deadline=row_extra()['expires_at'];
save_extra('change_options',['participant_id'=>1,'options'=>['bus-andata'=>3],'reason'=>'']);
check(row_extra()['expires_at']===$deadline,'Quantity no-op deadline');
$invalid=MI_Management_Service::options_preview(1,['participant_id'=>1,'options'=>['bus-andata'=>4],'reason'=>''],booking_extra()['version']);
check(is_wp_error($invalid),'Exceeded service maximum');
$contact=new ReflectionMethod(MI_Management_Service::class,'participant_contact');
check($contact->invoke(null,['participant_phone'=>''],[],'phone','referente')==='','Explicit empty phone');
check($contact->invoke(null,[],[],'phone','referente')==='referente','Absent phone fallback');
echo "PASS confirmed audit management regressions.\n";
