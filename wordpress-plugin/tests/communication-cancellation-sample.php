<?php
function home_url( $path = '' ) { return 'https://example.invalid/' . ltrim( $path, '/' ); }
function add_query_arg( $args, $url ) { return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args ); }
ob_start(); require __DIR__ . '/communications-model.php'; ob_end_clean();
$wpdb = new SampleQueueDatabase();
$payload = array( 'event_id' => 42, 'communication_id' => 'cancellation-demo-request', 'template_type' => 'PARTICIPATION_CANCELLATION_REMINDER', 'message' => 'Usa il collegamento personale riportato qui sotto.', 'test_only' => true, 'test_recipient' => 'chosen@example.invalid', 'recipients' => array( array( 'order_code' => 'AUDIT1' ) ) );
$result = MI_Spedizione_Email::accoda_comunicazione_operativa( $payload );
expect( ! is_wp_error( $result ) && $result['count'] === 1, 'Preparazione prova con collegamento dimostrativo fallita' );
$stored = $wpdb->payloads[0]; $snapshot = $stored['email_preview'];
expect( ! empty( $snapshot['cancellation_demo'] ), 'Prova senza indicatore dimostrativo' );
expect( count( $snapshot['gestione_partecipanti'] ) === 1, 'Prova senza pulsante di cancellazione' );
$link = $snapshot['gestione_partecipanti'][0];
parse_str( parse_url( $link['url'], PHP_URL_QUERY ), $query );
expect( $query === array( 'mi_portal' => '1', 'mi_cancel_demo' => '1' ), 'Link di prova contiene token o riferimenti a iscrizioni reali' );
expect( $link['nome'] === 'Persona di prova', 'Nome reale nella prova' );
$html = MI_Modello_Email::componi_html( $snapshot ); $plain = MI_Modello_Email::componi_testo( $snapshot );
expect( strpos( $html, 'Annullamento dell’iscrizione' ) < strpos( $html, 'Pagina dell’evento' ), 'Pulsante pagina evento prima della cancellazione nella prova' );
expect( strpos( $plain, 'Annullamento dell’iscrizione' ) < strpos( $plain, 'Pagina evento:' ), 'Ordine link testo semplice diverso dalla prova HTML' );
foreach ( array( $html, $plain ) as $body ) {
	expect( str_contains( $body, 'Annullamento dell’iscrizione' ) && str_contains( $body, 'mi_cancel_demo' ), 'Pulsante o link assente dal contenuto inviato' );
	expect( str_contains( $body, 'Collegamento dimostrativo' ), 'Prova confondibile con cancellazione reale' );
}
expect( ! str_contains( json_encode( $stored ), 'mi_cancel_token' ) && ! str_contains( json_encode( $stored ), 'mi_cancel_participant' ), 'Creato link personale nella prova' );
$real_links = array( array( 'name' => 'Persona Uno', 'url' => 'https://example.invalid/?mi_cancel_participant=7&mi_cancel_token=synthetic-token' ), array( 'name' => 'Persona Due', 'url' => 'https://example.invalid/?mi_cancel_participant=8&mi_cancel_token=second-synthetic-token' ) );
$real = MI_Modello_Email::crea_istantanea_operativa( 42, array( '{{evento.titolo}}' => 'Evento sintetico' ), 'PARTICIPATION_CANCELLATION_REMINDER', 'Messaggio', '', $real_links, true, false );
expect( empty( $real['cancellation_demo'] ) && count( $real['gestione_partecipanti'] ) === 2, 'Invio operativo trasformato in prova' );
expect( $real['gestione_partecipanti'][0]['url'] === $real_links[0]['url'], 'Link operativo modificato' );
expect( ! str_contains( MI_Modello_Email::componi_html( $real ), 'mi_cancel_demo' ), 'Demo finita nel messaggio operativo' );
expect( strpos( MI_Modello_Email::componi_html( $real ), 'Annullamento dell’iscrizione di Persona Due' ) < strpos( MI_Modello_Email::componi_html( $real ), 'Pagina dell’evento' ), 'Pagina evento prima dei link delle persone nell invio operativo' );
$other = MI_Modello_Email::crea_istantanea_operativa( 42, array(), 'PRE_DEPARTURE_REMINDER', 'Avviso', '', array(), false, true );
expect( empty( $other['cancellation_demo'] ) && ! $other['gestione_partecipanti'], 'Pulsante aggiunto a un altro tipo di messaggio' );
$other['gestione_partecipanti'] = $real['gestione_partecipanti'];
$other_html = MI_Modello_Email::componi_html( $other );
expect( strpos( $other_html, 'Pagina dell’evento' ) < strpos( $other_html, 'Annullamento dell’iscrizione di Persona Uno' ), 'Ordine modificato negli altri tipi di email' );
if ( in_array( '--render', $argv, true ) ) file_put_contents( dirname( __DIR__, 2 ) . '/output/cancellazione-prova-3.27.33/email.html', $html );
echo "PASS: email di prova con link dimostrativo HTML/testo, campione anonimo, nessun token reale, invii operativi e altri tipi invariati.\n";
