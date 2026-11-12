<?php
ob_start(); require __DIR__ . '/email-newlines.php'; ob_end_clean();
define( 'MINUTE_IN_SECONDS', 60 );
function get_current_user_id() { return 7; }
function set_transient( ...$args ) { $GLOBALS['notices'][] = $args; return true; }
$GLOBALS['email_meta'][42] = array();
$values = array( '{{evento.titolo}}' => 'Evento sintetico', '{{sottoscrittore.nome_completo}}' => 'Persona Esempio', '{{sottoscrittore.nome}}' => 'Persona', '{{ordine.codice}}' => '4521' );
$GLOBALS['email_test_options']['mi_custom_communication_types'] = array( 'CUSTOM_RITROVO' => 'Cambio ritrovo' );
foreach ( array( 'PRE_DEPARTURE_REMINDER', 'CUSTOM_RITROVO', 'PARTICIPATION_CANCELLATION_REMINDER' ) as $type ) {
    $s = MI_Modello_Email::crea_istantanea_operativa( 42, $values, $type, 'Avviso per {{evento.titolo}} e {{sottoscrittore.nome}}.', '' );
    expect( ! str_contains( $s['testo'], '{{' ) && str_contains( $s['testo'], 'Evento sintetico e Persona.' ), 'Segnaposto non sostituiti: ' . $type );
    if ( $type === 'CUSTOM_RITROVO' ) expect( $s['oggetto'] === 'Cambio ritrovo — Evento sintetico' && $s['titolo'] === 'Cambio ritrovo', 'Nome del tipo personalizzato perso' );
}
$s = MI_Modello_Email::crea_istantanea_operativa( 42, $values, 'PRE_DEPARTURE_REMINDER', "Codice: 4521\nCodice iscrizione: cancello laterale", '' );
$s = MI_Modello_Email::ripara_istantanea_codifica( $s );
expect( str_contains( $s['testo'], 'Codice: 4521' ) && str_contains( $s['testo'], 'Codice iscrizione: cancello' ), 'Testo libero eliminato per coincidenza col codice ordine' );
$s = MI_Modello_Email::crea_istantanea_operativa( 42, $values, 'PRE_DEPARTURE_REMINDER', '**importante *nota* qui** e 2 * 3 + 4 * 5. [Consulta](https://example.invalid/event?a=1&b=2)', '' );
$s = MI_Modello_Email::ripara_istantanea_codifica( $s );
expect( str_contains( $s['html'], '<strong>importante <em>nota</em> qui</strong>' ) && str_contains( $s['html'], '2 * 3 + 4 * 5' ), 'Enfasi annidata o moltiplicazione alterata' );
expect( str_contains( $s['html'], '<a href="https://example.invalid/event?a=1&amp;b=2">Consulta</a>' ) || str_contains( $s['html'], '<a href="https://example.invalid/event?a=1&b=2">Consulta</a>' ), 'Link Markdown perso' );
$s = MI_Modello_Email::crea_istantanea_operativa( 42, $values, 'PARTICIPATION_CANCELLATION_REMINDER', '', '' );
expect( ! str_contains( $s['testo'], 'collegamento personale riportato qui sotto' ) && ! $s['gestione_partecipanti'], 'Promesso un link non presente' );
$money = MI_Modello_Email::valori_ordine( array( 'payment_deadline_at' => '2027-11-22T15:00' ), 'AUDIT', 'Confermata', 1, 'Persona', array( 'total_cents' => 10000, 'initial_due_cents' => 0, 'balance_cents' => 10000, 'payment_methods' => array( 'BANK_TRANSFER' ) ) );
expect( $money['{{pagamento.causale}}'] === 'AUDIT' && $money['{{pagamento.scadenza}}'] !== 'Non applicabile' && str_contains( $money['{{pagamento.istruzioni}}'], 'Bonifico' ), 'Saldo positivo trattato come assenza di pagamento' );
$GLOBALS['email_meta'][42] = array( '_mi_email_template' => array( 'subject' => 'Prima' ), '_mi_email_style' => array( 'identity_name' => 'Prima' ) );
$_POST = wp_slash( array( 'mi_modello_email_nonce' => 'valid', 'mi_email_subject' => '{{segnaposto.inesistente}}', 'mi_email_event_identity_enabled' => '1', 'mi_email_event_identity_name' => 'Dopo' ) );
MI_Modello_Email::salva( 42, (object) array( 'ID' => 42 ) );
expect( $GLOBALS['email_meta'][42]['_mi_email_template']['subject'] === 'Prima' && $GLOBALS['email_meta'][42]['_mi_email_style']['identity_name'] === 'Prima', 'Salvataggio parziale dopo validazione fallita' );
$_POST = wp_slash( array( 'mi_modello_email_nonce' => 'valid', 'mi_email_subject' => 'Oggetto', 'mi_email_event_identity_enabled' => '1', 'mi_email_event_signature' => 'C:\cartella\file' ) );
MI_Modello_Email::salva( 42, (object) array( 'ID' => 42 ) );
expect( $GLOBALS['email_meta'][42]['_mi_email_style']['signature'] === 'C:\cartella\file', 'Backslash persi durante salvataggio o sanitizzazione' );
$normalize = new ReflectionMethod( 'MI_Modello_Email', 'uniforma_grafica_corpo' );
$first = $normalize->invoke( null, '<p>Testo</p>' );
expect( $first === $normalize->invoke( null, $first ), 'Normalizzazione style non idempotente' );

foreach ([['REGISTRATION_ONLY',0,false],['FULL_PAYMENT',0,false],['DEPOSIT_BALANCE',0,false],['FULL_PAYMENT',2000,true],['DEPOSIT_BALANCE',2000,true],['PRICE_ONLY',2000,false]] as [$mode,$total,$expected]) {
    $event=['title'=>'Evento in attesa','economic_mode'=>$mode,'deposit_percentage'=>30,'payment_methods'=>['CASH']];
    $economic=MI_Registration_Service::riepilogo_economico($event,$total,'WAITLISTED');
    $values=MI_Modello_Email::valori_ordine($event,'TEST','Lista d’attesa',1,'Ada Esempio',$economic);
    $wait=MI_Modello_Email::crea_istantanea_lista_attesa(42,$values);
    $offer=MI_Modello_Email::crea_istantanea_offerta_lista_attesa(42,$values,'https://example.invalid/offer?token=test','domani');
    foreach (['html','testo'] as $format) {
        expect(str_contains($wait[$format],$expected?'Al momento non sono richiesti pagamenti né altre azioni.':'Al momento non sono richieste altre azioni.'),'Istruzione attesa incoerente: '.$mode.'/'.$total.'/'.$format);
        expect(str_contains($offer[$format],'Il pagamento potrà essere effettuato')===$expected,'Istruzione offerta incoerente: '.$mode.'/'.$total.'/'.$format);
        expect(str_contains($offer[$format],'Accetta il posto')&&str_contains($offer[$format],'Rinuncia'),'Scelte offerta perse');
    }
    expect($offer['action_url']==='https://example.invalid/offer?token=test','Link risposta alterato');
}
echo "PASS: attesa e offerta distinguono gratuiti, quota zero e pagamenti dopo l’ammissione, anche con caparra.\n";
echo "PASS: segnaposto, tipi personalizzati, codici liberi, Markdown, saldo positivo, salvataggio atomico e backslash.\n";
