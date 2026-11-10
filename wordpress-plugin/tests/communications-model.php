<?php
require __DIR__.'/operational-email-deadline.php';
$GLOBALS['email_meta'][42]['_mi_email_template']=['enabled'=>'0'];
$snapshot=MI_Modello_Email::crea_istantanea_operativa(42,['{{evento.titolo}}'=>'Evento','{{sottoscrittore.nome_completo}}'=>'Persona'],'PRE_DEPARTURE_REMINDER','Avviso','');
expect($snapshot['attivo']===true,'manual communication inherited disabled automatic confirmation');
expect(MI_Modello_Email::crea_istantanea(42,[])['attivo']===false,'automatic confirmation was re-enabled');
class SampleQueueDatabase extends AuditReminderDatabase {
 public $origins=[],$pendingOrigin='';
 function get_results($sql,...$args){return array_slice(parent::get_results($sql,...$args),0,1);}
 function prepare($sql,...$args){if(str_starts_with($sql,'INSERT'))$this->pendingOrigin=$args[3];return parent::prepare($sql,...$args);}
 function get_var($sql){return str_contains($sql,'SELECT id FROM wp_mi_email_outbox')?99:1;}
 function query($sql){if(!str_starts_with($sql,'INSERT'))return 1;if(isset($this->origins[$this->pendingOrigin]))return 0;$this->origins[$this->pendingOrigin]=true;return 1;}
}
$wpdb=new SampleQueueDatabase();
$payload=['event_id'=>42,'communication_id'=>'same-request','template_type'=>'PRE_DEPARTURE_REMINDER','message'=>'Avviso','test_only'=>true,'test_recipient'=>'chosen@example.invalid','recipients'=>[['order_code'=>'AUDIT1']]];
$first=MI_Spedizione_Email::accoda_comunicazione_operativa($payload);
$again=MI_Spedizione_Email::accoda_comunicazione_operativa($payload);
expect(!is_wp_error($first)&&$first['count']===1&&$first['outbox_id']===99,'sample row not committed');
expect(!is_wp_error($again)&&$again['count']===0&&$again['outbox_id']===99,'sample replay duplicated row or lost id');
$stored=$wpdb->payloads[0];
expect($stored['portal_test_copy']===true&&$stored['test_recipient']==='chosen@example.invalid','test recipient lost');
expect($stored['order_code']==='PROVA'&&str_contains($stored['email_preview']['testo'],'Persona di prova'),'sample not anonymized');
expect(!str_contains(json_encode($stored),'test@example.invalid'),'actual subscriber address leaked into sample');
echo "PASS: manual messages independent of confirmation toggle; anonymous sample, durable deduplication and stable outbox id.\n";
