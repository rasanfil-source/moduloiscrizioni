<?php
// Soltanto mi_deletion_test sul database sintetico locale; Google e posta simulati.
require __DIR__.'/event-deletion-innodb.php';
function sanitize_textarea_field($v){return trim(strip_tags((string)$v));}
$table='wp_mi_email_outbox';
foreach(['not-json','{"event_id":999}','{"event_id":"43"}']as $payload)check(false!==$wpdb->insert($table,['registration_id'=>0,'recipient'=>'test@example.invalid','template_type'=>'NOTICE','payload_json'=>$payload,'status'=>'FAILED','created_at'=>gmdate('Y-m-d H:i:s')]),$wpdb->last_error);
$matching_id=$wpdb->insert_id;
$emails=(new ReflectionMethod(MI_Event_Deletion::class,'event_emails'))->invoke(null,43);
check(!$wpdb->last_error,'Invalid JSON evaluated by SQL');
check(count($emails)===2&&in_array($matching_id,array_map('intval',array_column($emails,'id')),true),'Event SQL filter lost matching numeric string or included foreign payload');
check(false!==$wpdb->insert($table,['registration_id'=>43,'recipient'=>'test@example.invalid','template_type'=>'REGISTERED','payload_json'=>'{"event_id":43,"email_preview":{"testo":"Snapshot"}}','status'=>'FAILED','attempts'=>5,'created_at'=>gmdate('Y-m-d H:i:s')]),$wpdb->last_error);
$source=$wpdb->insert_id;$count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$new=MI_Spedizione_Email::rigenera_consegna($source,'Verifica locale del mancato invio',true);
check(is_int($new)&&$new>$source,'Real transaction failed: '.json_encode($new));
check($wpdb->get_var("SELECT status FROM {$table} WHERE id={$source}")==='SUPERSEDED','Original delivery not archived');
check($wpdb->get_var("SELECT status FROM {$table} WHERE id={$new}")==='PENDING','Wrong new delivery mode');
check(MI_Spedizione_Email::rigenera_consegna($source,'Replay',true)===$new,'Real replay duplicated replacement');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}")===$count+1,'Unexpected delivery count');
check(false!==$wpdb->insert($table,['registration_id'=>43,'recipient'=>'test@example.invalid','template_type'=>'REGISTERED','payload_json'=>'{"event_id":43}','status'=>'TEST_FAILED','created_at'=>gmdate('Y-m-d H:i:s')]),$wpdb->last_error);
$source=$wpdb->insert_id;$count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$wpdb->fail='UPDATE wp_mi_email_outbox';
check(is_wp_error(MI_Spedizione_Email::rigenera_consegna($source,'Rollback',true)),'Update failure accepted');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}")===$count&&$wpdb->get_var("SELECT status FROM {$table} WHERE id={$source}")==='TEST_FAILED','InnoDB rollback left replacement or superseded source');
$new=MI_Spedizione_Email::rigenera_consegna($source,'Retry',true);
check(is_int($new)&&$wpdb->get_var("SELECT status FROM {$table} WHERE id={$new}")==='TEST_PENDING','Test mode lost on retry');
echo "PASS InnoDB: JSON event filtering, malformed payloads, atomic email regeneration, replay and rollback.\n";
