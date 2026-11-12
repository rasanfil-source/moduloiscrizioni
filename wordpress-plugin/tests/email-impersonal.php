<?php
ob_start(); require __DIR__ . '/email-newlines.php'; ob_end_clean();
function add_query_arg( $key, $value, $url ) { return $url . '?' . http_build_query( array( $key => $value ) ); }
$GLOBALS['email_meta'][42] = array( '_mi_pricing_mode' => 'NONE' );
$values = MI_Modello_Email::valori_ordine( array( 'title' => 'Evento', 'economic_mode' => 'REGISTRATION_ONLY' ), 'TEST', 'Confermata', 1, 'Ada Esempio', array() );
$samples = array(
    'conferma' => MI_Modello_Email::crea_istantanea( 42, $values ),
    'attesa' => MI_Modello_Email::crea_istantanea_lista_attesa( 42, $values ),
    'offerta' => MI_Modello_Email::crea_istantanea_offerta_lista_attesa( 42, $values, 'https://example.invalid/offerta', '15/11/2026 18:00' ),
);
$samples['offerta-parziale'] = MI_Modello_Email::crea_offerta_un_posto_per_due( 42, $values, 'https://example.invalid/offerta', '15/11/2026 18:00' );
foreach ( array( 'PRE_DEPARTURE_REMINDER', 'BALANCE_REMINDER', 'DEPOSIT_REMINDER', 'EVENT_CANCELLATION', 'REGISTRATION_CANCELLATION', 'MATERIAL_DELIVERY' ) as $type ) {
    $samples[$type] = MI_Modello_Email::crea_istantanea_operativa( 42, $values, $type, '', '' );
}
$paid_values = MI_Modello_Email::valori_ordine( array( 'title' => 'Evento', 'economic_mode' => 'FULL_PAYMENT' ), 'TEST', 'Da pagare', 1, 'Ada Esempio', array( 'mode' => 'FULL_PAYMENT', 'total_cents' => 1000, 'initial_due_cents' => 1000, 'balance_cents' => 1000, 'payment_methods' => array( 'BANK_TRANSFER', 'CARD', 'CASH' ) ) );
$samples['conferma-pagamento'] = MI_Modello_Email::crea_istantanea( 42, $paid_values );
expect( str_contains( $samples['conferma-pagamento']['testo'], 'Indicazioni per il pagamento:' ), 'Pagamento richiesto eliminato dalla conferma' );
expect( str_contains( $samples['conferma-pagamento']['testo'], 'utilizzare il codice' ) && ! preg_match( '/\b(usa|segui|concorda)\b/u', $samples['conferma-pagamento']['testo'] ), 'Istruzioni di pagamento confidenziali' );
foreach ( $samples as $name => $sample ) {
    foreach ( array( 'html', 'testo' ) as $format ) {
        expect( str_contains( $sample[$format], 'Buongiorno Ada Esempio.' ), 'Saluto incompleto: ' . $name . '/' . $format );
        expect( ! preg_match( '/\b(tua|tuo|tuoi|tue|puoi|devi|riceverai|rispondi|ti)\b/iu', strip_tags( $sample[$format] ) ), 'Tono confidenziale: ' . $name . '/' . $format );
    }
}
foreach ( array( 'html', 'testo' ) as $format ) {
    expect( ! preg_match( '/pagamento|situazione economica|causale|rimbors/iu', $samples['conferma'][$format] ), 'Riferimenti economici nel gratuito' );
    expect( ! str_contains( $samples['EVENT_CANCELLATION'][$format], 'rimborsi' ), 'Rimborsi nell’annullamento gratuito' );
}
$html = MI_Modello_Email::componi_html( $samples['conferma'] );
$text = MI_Modello_Email::componi_testo( $samples['conferma'] );
expect( str_contains( $html, '>Per assistenza<' ) && str_contains( $html, '>Scrivere a:<' ), 'Assistenza HTML non aggiornata' );
expect( str_contains( $text, 'Per assistenza — Scrivere a:' ), 'Assistenza testo non aggiornata' );
$single = MI_Modello_Email::crea_istantanea_annullamento_partecipazione_iscritto( 42, 'Ada Esempio', '', 0, 'Ada Esempio', false );
$multiple = MI_Modello_Email::crea_istantanea_annullamento_partecipazione_iscritto( 42, 'Ada Esempio', '', 1, 'Referente Esempio', false );
foreach ( array( 'html', 'testo' ) as $format ) {
    expect( ! str_contains( $single[$format], 'altre partecipazioni' ) && ! str_contains( $single[$format], 'soltanto la persona' ), 'Singola con riferimenti ad altre persone' );
    expect( str_contains( $multiple[$format], 'Le altre partecipazioni della prenotazione restano invariate.' ), 'Multipla senza precisazione' );
    expect( str_contains( $single[$format], 'Si conferma che' ), 'Conferma non impersonale' );
}
$automatic = MI_Modello_Email::crea_istantanea_annullamento_partecipazione_iscritto( 42, 'Ada Esempio', 'TRAVEL_DEADLINE', 0, 'Ada Esempio', false );
expect( str_contains( $automatic['testo'], 'passaggio richiesto' ) && ! str_contains( $automatic['testo'], 'rimbors' ), 'Motivo automatico perso o rimborsi nel gratuito' );
// Saved standard wording is upgraded before rendering, without modifying data, markup or URLs.
$legacy = '<p>Ciao {{sottoscrittore.nome}},</p><p>la tua iscrizione è stata registrata.</p><a title="la tua iscrizione" href="https://example.invalid/?test=tuo">Dettagli</a>';
$GLOBALS['email_meta'][42]['_mi_email_template'] = array( 'html' => $legacy, 'text' => 'Ciao {{sottoscrittore.nome}},' );
$upgraded = MI_Modello_Email::crea_istantanea( 42, $values );
expect( str_contains( $upgraded['html'], 'Buongiorno Ada Esempio.' ), 'Saluto storico non aggiornato' );
expect( str_contains( $upgraded['html'], 'title="la tua iscrizione" href="https://example.invalid/?test=tuo"' ), 'Attributi personalizzati alterati' );
expect( $GLOBALS['email_meta'][42]['_mi_email_template']['html'] === $legacy, 'Modello salvato sovrascritto' );
$old_reminder = 'Ti ricordiamo che per la tua iscrizione a {{evento.titolo}} resta da versare il saldo.';
$clean = MI_Modello_Email::uniforma_testo_comunicazione( $old_reminder );
expect( $clean === 'Per l’iscrizione a {{evento.titolo}} resta da versare il saldo.', 'Promemoria storico non aggiornato' );
expect( $clean === MI_Modello_Email::uniforma_testo_comunicazione( $clean ), 'Conversione non idempotente' );
if ( in_array( '--render', $argv, true ) ) {
    $dir = dirname( __DIR__, 2 ) . '/output/email-3.27.58';
    if ( ! is_dir( $dir ) ) mkdir( $dir, 0777, true );
    foreach ( array_merge( $samples, array( 'annullamento-singola' => $single, 'annullamento-multipla' => $multiple ) ) as $name => $sample ) {
        file_put_contents( $dir . '/' . $name . '.html', MI_Modello_Email::componi_html( $sample ) );
        file_put_contents( $dir . '/' . $name . '.txt', MI_Modello_Email::componi_testo( $sample ) );
    }
}
echo "PASS: tono impersonale, saluto completo, assistenza, gratuiti, annullamenti singoli/multipli, modelli storici e integrità URL.\n";
