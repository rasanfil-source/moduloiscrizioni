<?php
// Modelli e accodamento effettivi, database sintetico e nessun trasporto email.
$fixture = file_get_contents(__DIR__ . '/operational-email-deadline.php');
$fixture = str_replace('$tests = __DIR__;', '$tests = ' . var_export(__DIR__, true) . ';', $fixture);
$fixture = str_replace("'individual_known'=>false", "'individual_known'=>(\$GLOBALS['auditKnown']??false),'individual_deposit_due'=>0,'individual_deposit_missing'=>0", $fixture);
$fixture = str_replace("'effective_paid'=>0", "'effective_paid'=>(\$GLOBALS['auditPaid']??0)", $fixture);
$fixture = str_replace("'effective_balance'=>10000", "'effective_balance'=>(\$GLOBALS['auditBalance']??10000)", $fixture);
ob_start(); eval('?>' . $fixture); ob_end_clean();
class FullPaymentAuditDatabase extends AuditReminderDatabase {
    function get_results($sql, ...$args) { return array_slice(parent::get_results($sql, ...$args), 0, 1); }
}
$GLOBALS['auditKnown']=true; $GLOBALS['auditPaid']=3000; $GLOBALS['auditBalance']=7000;
foreach (['BALANCE_REMINDER','DEPOSIT_REMINDER'] as $type) {
    $wpdb=new FullPaymentAuditDatabase();
    $result=MI_Spedizione_Email::accoda_comunicazione_operativa(['event_id'=>42,'communication_id'=>'full-payment-audit','template_type'=>$type,'recipients'=>[['order_code'=>'AUDIT1']]]);
    expect(!is_wp_error($result) && $result['count']===1, 'Pagamento unico escluso dal promemoria');
    $snapshot=$wpdb->payloads[1]['email_preview'];
    expect(str_contains($snapshot['testo'],'70,00') && !str_contains($snapshot['testo'],'Non applicabile'), 'Residuo/scadenza pagamento unico errati');
    expect(str_contains($snapshot['testo'],'AUDIT1'), 'Causale pagamento unico assente');
    if ($type==='DEPOSIT_REMINDER') expect(!str_contains($snapshot['testo'],'caparra') && str_contains($snapshot['oggetto'],'pagamento'), 'Pagamento unico etichettato come caparra');
    if ($type==='BALANCE_REMINDER') {
        expect(str_contains(MI_Modello_Email::componi_html($snapshot),'href="https://example.invalid/balance"'), 'Pulsante saldo perso');
        expect(str_contains(MI_Modello_Email::componi_testo($snapshot),'https://example.invalid/balance'), 'Link saldo testo perso');
    }
}
$values=MI_Modello_Email::valori_ordine(['title'=>'Evento','economic_mode'=>'FULL_PAYMENT','payment_deadline_at'=>'2026-12-01T12:00'],'AUDIT1','Da pagare',1,'Persona',['total_cents'=>10000,'initial_due_cents'=>0,'balance_cents'=>7000]);
expect($values['{{pagamento.importo_dovuto}}']==='100,00 €','Importo iniziale FULL_PAYMENT ricavato da caparra zero');
$GLOBALS['auditBalance']=0;
foreach (['BALANCE_REMINDER','DEPOSIT_REMINDER'] as $type) {
    $wpdb=new FullPaymentAuditDatabase();
    $result=MI_Spedizione_Email::accoda_comunicazione_operativa(['event_id'=>42,'communication_id'=>'settled-audit','template_type'=>$type,'recipients'=>[['order_code'=>'AUDIT1']]]);
    expect($result['count']===0, 'Sollecito pagamento unico già saldato');
}
$GLOBALS['email_meta'][42]['_mi_email_template']=['enabled'=>'0'];
expect(!MI_Modello_Email::crea_istantanea(42,$values)['attivo'],'Conferma iniziale riattivata');
expect(MI_Modello_Email::crea_istantanea_lista_attesa(42,$values)['attivo'],'Lista attesa bloccata dal modello');
$offered=MI_Modello_Email::crea_istantanea_offerta_lista_attesa(42,$values,'https://example.invalid/offer','12 dicembre');
expect($offered['attivo'] && $offered['action_url']==='https://example.invalid/offer','Offerta posto disabilitata o link perso');
foreach (['INSTITUTIONAL','STANDARD'] as $layout) {
    $snapshot=['layout'=>$layout,'oggetto'=>'Evento all&#8217;Abbazia &amp; B','evento'=>['titolo'=>'all&#8217;Abbazia &amp; B'],'testo'=>'Testo all&#8217;Abbazia &amp; B'];
    $text=MI_Modello_Email::componi_testo($snapshot);
    expect(!preg_match('/&(?:#8217|amp);/',$text) && str_contains($text,'all’Abbazia & B'), 'Entità rimaste nel testo semplice finale');
}
echo "PASS: FULL_PAYMENT con quote individuali, residuo, saldo estinto, link personale, lista/offerta autonome ed entità nel testo finale.\n";
