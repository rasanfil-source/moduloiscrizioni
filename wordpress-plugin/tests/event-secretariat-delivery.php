<?php
// Solo database locale; il trasporto Workspace registra i messaggi senza inviarli.
require __DIR__.'/publication-email.php';
define('MINUTE_IN_SECONDS',60);
function home_url(){return 'https://example.invalid';}
function wp_salt($scheme){return 'local-test-only';}
class MI_Event_Deletion {static function enter($id){return true;}static function release($id){}static function job($id){return ['stage'=>'done'];}}
function update_option($k,$v,$autoload=false){$GLOBALS['email_options'][$k]=$v;return true;}
class MI_Workspace_Client {static $sent=[];static function request($action,$payload){
 expect($action==='INVIA_EMAIL_LOTTO','batch action');$results=[];
 foreach($payload['messages'] as $message){self::$sent[]=$message;$results[$message['delivery_key']]=['ok'=>true,'channel'=>'GOOGLE_WORKSPACE'];}
 return ['ok'=>true,'channel'=>'GOOGLE_WORKSPACE','results'=>$results];
}}

$GLOBALS['email_options']=['mi_modalita_spedizione_email'=>'OPERATIVO','mi_prova_email_verificata'=>hash('sha256','test@example.invalid')];
// Prepara la prova con lo stesso calcolo d'impronta usato dall'applicazione.
$fingerprint=new ReflectionMethod(MI_Spedizione_Email::class,'impronta_destinatario');$fingerprint->setAccessible(true);
$GLOBALS['email_options']['mi_prova_email_verificata']=$fingerprint->invoke(null,'test@example.invalid');
$GLOBALS['email_meta'][9]['_mi_email_style']['contact_email']='group@example.invalid';
$GLOBALS['email_meta'][43]['_mi_activity_id']=9;
$result=MI_Spedizione_Email::accoda_notifiche_attivazione_evento(43,new WP_User(),'','group@example.invalid');
expect($result['count']===3&&$result['mode']==='OPERATIVO','creation queues central secretariat and group');
$result=MI_Spedizione_Email::accoda_avviso_annullamento_segreteria(44,'Pioggia <forte>');
expect($result['count']===1,'empty event cancellation queues internal notice');
expect(MI_Spedizione_Email::accoda_avviso_annullamento_segreteria(44,'Pioggia')['count']===0,'cancellation retry deduplicates');
$wpdb->fail=true;expect(is_wp_error(MI_Spedizione_Email::accoda_avviso_annullamento_segreteria(45,'')),'storage failure remains visible');
$GLOBALS['event_status']='draft';
MI_Spedizione_Email::spedisci_coda();
expect(count(MI_Workspace_Client::$sent)===4,'operational delivery excludes old test queue and includes cancelled draft event');
foreach(MI_Workspace_Client::$sent as $message){
 expect($message['mode']==='OPERATIVO','operational transport');
 expect(!str_contains($message['oggetto'],'[PROVA]'),'no PROVA prefix in operational mail');
 expect($message['destinatario']!=='test@example.invalid','no test mailbox in operational mode');
}
$cancel=MI_Workspace_Client::$sent[3];
expect($cancel['destinatario']==='secretariat@example.invalid'&&str_contains($cancel['oggetto'],'Evento annullato'),'cancellation goes to central secretariat');
expect(str_contains($cancel['html'],'Pioggia &lt;forte&gt;'),'reason safely rendered in institutional template');
expect($wpdb->get_var("SELECT COUNT(*) FROM wp_mi_email_outbox WHERE status='TEST_PENDING'")==2,'old test preserved without release');
MI_Spedizione_Email::spedisci_coda();expect(count(MI_Workspace_Client::$sent)===4,'accepted messages are not resent');
echo "PASS: operational creation and cancellation, central mailbox, manager, blank event, retries, no test leakage. No real messages sent.\n";
