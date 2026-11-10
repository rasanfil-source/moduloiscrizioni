<?php
ob_start(); require __DIR__ . '/email-queue-fairness.php'; ob_end_clean();
class OutcomeAuditDatabase extends AuditQueueDatabase {
    function query($sql) { if(str_contains($sql,'attempts=GREATEST'))return 1; return parent::query($sql); }
}
$outcome = new ReflectionMethod('MI_Spedizione_Email','registra_esito');
foreach(array('EVENT_BUSY','EMAIL_STORAGE_UNAVAILABLE','EMAIL_DELIVERY_UNCERTAIN') as $code) {
    $wpdb=new OutcomeAuditDatabase(); $auditOptions=array();
    $row=array('id'=>1,'status'=>'PENDING','attempts'=>0,'event_id'=>42);
    $wpdb->rows[1]=array_replace($row,array('status'=>'SENDING'));
    $outcome->invoke(null,$row,new WP_Error('mi_remote','Synthetic',array('remote_code'=>$code)));
    if($code==='EVENT_BUSY' && (get_option('mi_email_retry_after') || get_option('mi_email_event_retry_42')<=time()))throw new RuntimeException('Event pause became global');
    if($code==='EMAIL_STORAGE_UNAVAILABLE' && get_option('mi_email_retry_after')<time()+890)throw new RuntimeException('Storage failure not deferred');
    if($code==='EMAIL_DELIVERY_UNCERTAIN' && $wpdb->rows[1]['status']!=='FAILED')throw new RuntimeException('Uncertain delivery retried');
}
seed_queue(10,0); MI_Spedizione_Email::spedisci_coda();
if($scheduled!==0 || MI_Workspace_Client::$recipients)throw new RuntimeException('Blocked-only queue busy loops');
seed_queue(0,1);$wpdb->rows[1]['payload_json']='{}';MI_Spedizione_Email::spedisci_coda();
if($wpdb->rows[1]['status']!=='FAILED' || $wpdb->rows[1]['attempts']!==0)throw new RuntimeException('Orphan queue entry retained');
echo "PASS: event-scoped pause, storage defer, uncertain no-retry, blocked-only scheduling and orphan terminal state.\n";
