<?php
// Trasporto e database in memoria: verifica servizio operativo senza inviare email.
$fixture = file_get_contents(__DIR__ . '/operational-email-deadline.php');
$fixture = str_replace('$tests = __DIR__;', '$tests = ' . var_export(__DIR__, true) . ';', $fixture);
$fixture = str_replace("'effective_paid'=>0", "'effective_paid'=>(\$GLOBALS['auditPaid']??0)", $fixture);
ob_start(); eval('?>' . $fixture); ob_end_clean();
class RecipientAuditDatabase extends AuditReminderDatabase {
    public $recipient = '', $deposit = 1000;
    function get_results($sql, ...$args) {
        $row = parent::get_results($sql, ...$args)[0];
        $row['buyer_email'] = $this->recipient;
        $row['initial_due_cents'] = $this->deposit;
        $row['economic_mode'] = 'DEPOSIT_BALANCE';
        return array($row);
    }
}
function prepare_recipient_audit($type, $extra = array()) {
    return MI_Spedizione_Email::accoda_comunicazione_operativa(array_replace(array('event_id'=>42,'communication_id'=>'synthetic-audit','template_type'=>$type,'message'=>'Avviso','recipients'=>array(array('order_code'=>'AUDIT1'))), $extra));
}
$wpdb = new RecipientAuditDatabase();
$result = prepare_recipient_audit('PRE_DEPARTURE_REMINDER');
expect($result['count']===0 && $result['excluded']===1 && !$wpdb->payloads, 'Email assente non esclusa prima della coda');
$result = prepare_recipient_audit('PRE_DEPARTURE_REMINDER', array('test_only'=>true,'test_recipient'=>'test@example.invalid'));
expect(!is_wp_error($result) && $result['count']===1, 'Prova anonima bloccata dal recapito assente del campione');
$wpdb = new RecipientAuditDatabase(); $wpdb->recipient='person@example.invalid'; $GLOBALS['auditPaid']=1000;
$result = prepare_recipient_audit('DEPOSIT_REMINDER', array('message'=>''));
expect($result['count']===0 && !$wpdb->payloads, 'Sollecito inviato con caparra coperta');
$GLOBALS['auditPaid']=300;
$result = prepare_recipient_audit('DEPOSIT_REMINDER', array('message'=>''));
expect($result['count']===1 && str_contains($wpdb->payloads[1]['email_preview']['testo'], '7,00'), 'Sollecito non usa residuo caparra');
$result = prepare_recipient_audit('REGISTRATION_CANCELLATION');
expect(str_contains($wpdb->last_select,"status='CANCELLED'"), 'Annullamento cerca iscrizioni attive');
$result = prepare_recipient_audit('PRE_DEPARTURE_REMINDER', array('recipients'=>array_fill(0,1001,array('order_code'=>'AUDIT1'))));
expect(is_wp_error($result), 'Oltre 1000 destinatari troncati senza errore');
$wpdb = new RecipientAuditDatabase();
$wpdb->recipient = 'persona@example.invalid';
$invite = prepare_recipient_audit('SIMILAR_EVENTS_INVITE', array('message'=>'', 'subject'=>'Domenica al cineforum'));
expect($invite['count'] === 1 && str_contains($wpdb->last_select, MI_Spedizione_Email::future_events_condition()), 'Filtro obbligatorio mancante nel worker inviti');
$snapshot = $wpdb->payloads[1]['email_preview'];
expect($snapshot['oggetto'] === 'Domenica al cineforum' && $snapshot['titolo'] === 'Invito eventi simili', 'Oggetto o titolo invito errato');
foreach (array('testo','html') as $format) expect(str_contains($snapshot[$format], 'è stato espresso') && str_contains($snapshot[$format], 'Evento sintetico') && !str_contains($snapshot[$format], 'allegat'), 'Invito non impersonale o incompleto');
expect($snapshot['status_url'] === '' && $snapshot['evento']['url'] === '', 'Invito con link alla vecchia prenotazione');
$GLOBALS['email_test_options']['mi_custom_communication_types'] = array('CUSTOM_CINEFORUM'=>'Invito esteso a tutti');
foreach (array(false, true) as $test_only) {
    $result = prepare_recipient_audit('CUSTOM_CINEFORUM', array('subject'=>'Domenica al cineforum', 'test_only'=>$test_only, 'test_recipient'=>'test@example.invalid'));
    expect($result['count'] === 1, 'Messaggio personalizzato non preparato');
    $custom = $wpdb->payloads[$test_only ? 0 : 1]['email_preview'];
    expect($custom['oggetto'] === 'Domenica al cineforum' && $custom['titolo'] === 'Domenica al cineforum', 'Oggetto personalizzato perso nella coda o sotto il banner');
}
prepare_recipient_audit('PRE_DEPARTURE_REMINDER', array('future_only'=>true));
expect(str_contains($wpdb->last_select, MI_Spedizione_Email::future_events_condition()), 'Filtro facoltativo perso nel worker');
prepare_recipient_audit('PRE_DEPARTURE_REMINDER');
expect(!str_contains($wpdb->last_select, MI_Spedizione_Email::future_events_condition()), 'Filtro applicato anche senza richiesta');
echo "PASS: recapiti esclusi, prova anonima, caparra coperta/residua, annullamenti e limite esplicito.\n";
