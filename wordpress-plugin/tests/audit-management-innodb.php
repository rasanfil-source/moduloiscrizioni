<?php
// Uses only temporary tables in the isolated localhost:33317 test database.
require __DIR__ . '/individual-management-innodb.php';
function is_email($v) { return false !== filter_var($v, FILTER_VALIDATE_EMAIL); }
$extra=['code'=>'extra-notte','name'=>'Notte aggiuntiva','scope'=>'TICKET','category'=>'alloggio','choice_group'=>'','price_cents'=>1000,'max_quantity'=>1];
$snapshot=['event'=>['pricing_mode'=>'CALCULATED','options'=>[$extra],'participant_fields'=>[['key'=>'custom_food-note','label'=>'Food','type'=>'text','required'=>false],['key'=>'email','type'=>'email','required'=>false]]]];
check(false!==$wpdb->insert('wp_mi_registrations',['id'=>200,'event_id'=>42,'order_code'=>'AUDIT200','status'=>'CONFIRMED','buyer_first_name'=>'Referente','buyer_last_name'=>'Test','buyer_email'=>'buyer@example.invalid','buyer_phone'=>'+39 333 1234567','total_qty'=>1,'total_cents'=>10000,'initial_due_cents'=>10000,'economic_mode'=>'FULL_PAYMENT','snapshot_json'=>json_encode($snapshot),'idempotency_key'=>'audit200','created_at'=>gmdate('Y-m-d H:i:s')]),$wpdb->last_error);
$wpdb->insert('wp_mi_registration_items',['registration_id'=>200,'ticket_type_code'=>'base','quantity'=>1,'unit_price_cents'=>10000]);
$wpdb->insert('wp_mi_participants',['id'=>200,'registration_id'=>200,'ticket_type_code'=>'base','first_name'=>'Persona','last_name'=>'Test','status'=>'ACTIVE','extra_json'=>'{}','options_json'=>'[]']);
function audit_version(){global $wpdb;return (new ReflectionMethod(MI_Management_Service::class,'booking'))->invoke(null,$wpdb->get_row('SELECT * FROM wp_mi_registrations WHERE id=200',ARRAY_A))['version'];}
$data=['participant_id'=>200,'options'=>['extra-notte'=>1],'reason'=>''];
$saved=MI_Management_Service::save(200,'change_options',$data,audit_version(),'wp_7_12345678-1234-4234-8234-123456789201');
check(!empty($saved['saved']),'Accommodation extra not addable: '.json_encode($saved));
check((int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=200')===11000,'Extra price not applied');
$data['options']=[];
$saved=MI_Management_Service::save(200,'change_options',$data,audit_version(),'wp_7_12345678-1234-4234-8234-123456789202');
check(!empty($saved['saved'])&&(int)$wpdb->get_var('SELECT total_cents FROM wp_mi_registrations WHERE id=200')===10000,'Extra cannot be removed');
$changes=[];
foreach(['custom_food-note'=>['','No nuts'],'phone'=>['+39 333 1234567','+39 333 7654321'],'email'=>['buyer@example.invalid','person@example.invalid']] as $key=>$values)$changes[]=['order_code'=>'AUDIT200','number'=>1,'key'=>$key,'before'=>$values[0],'after'=>$values[1]];
$result=MI_Management_Service::save_sheet(42,$changes,'wp_7_12345678-1234-4234-8234-123456789203');
check(!empty($result['saved'])&&count($result['confirmations'])===3,'Hyphen/contact batch rejected: '.json_encode($result));
$fields=json_decode($wpdb->get_var('SELECT extra_json FROM wp_mi_participants WHERE id=200'),true);
check($fields['custom_food-note']==='No nuts'&&$fields['phone']==='+39 333 7654321'&&$fields['email']==='person@example.invalid','Personal fields not saved');
check($wpdb->get_var('SELECT buyer_phone FROM wp_mi_registrations WHERE id=200')==='+39 333 1234567','Personal edit changed buyer');
$changes[0]['after']='New answer';$changes[0]['before']='No nuts';
$changes[1]['after']='+39 333 9999999';
$result=MI_Management_Service::save_sheet(42,$changes,'wp_7_12345678-1234-4234-8234-123456789204');
check(!empty($result['rejected']),'A genuinely stale phone overwrite was allowed');
check(json_decode($wpdb->get_var('SELECT extra_json FROM wp_mi_participants WHERE id=200'),true)['custom_food-note']==='No nuts','Conflict allowed partial batch write');
echo "PASS: accommodation extras add/remove with prices, hyphen fields, personal contact fallback, receipts and atomic conflict rejection.\n";
$room=['code'=>'alloggio-singola','name'=>'Singola','scope'=>'TICKET','price_cents'=>2000,'max_quantity'=>1];
$snapshot['event']['options']=[$extra,$room];
$wpdb->update('wp_mi_registrations',['snapshot_json'=>json_encode($snapshot)],['id'=>200]);
$data=['participant_id'=>200,'options'=>['extra-notte'=>1],'accommodation_type'=>'alloggio-singola','room'=>'','reason'=>''];
$saved=MI_Management_Service::save(200,'change_options',$data,audit_version(),'wp_7_12345678-1234-4234-8234-123456789205');
check(!empty($saved['saved']),'Cumulative extra and standard accommodation rejected: '.json_encode($saved));
$extra['choice_group']='alloggio';$snapshot['event']['options']=[$extra,$room];
$wpdb->update('wp_mi_registrations',['snapshot_json'=>json_encode($snapshot)],['id'=>200]);
$preview=MI_Management_Service::options_preview(200,$data,audit_version());
check(is_wp_error($preview),'Explicit accommodation exclusivity bypassed');
$data['accommodation_type']='';
$saved=MI_Management_Service::save(200,'change_options',$data,audit_version(),'wp_7_12345678-1234-4234-8234-123456789206');
check(!empty($saved['saved']),'Cannot replace standard accommodation with exclusive extra');
echo "PASS: cumulative extras coexist with standard rooms; explicit exclusive groups remain enforced.\n";
