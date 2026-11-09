<?php
ob_start(); require __DIR__ . '/email-newlines.php'; ob_end_clean();
function current_time( $format, $utc = false ) { return '2026-11-09 12:00'; }
class MI_Portal_Management { public static function url( $id ) { return 'https://example.invalid/manage'; } }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-public-balance.php';
$GLOBALS['email_meta'][42] = array();
$values = array( '{{evento.titolo}}' => 'Evento sintetico', '{{sottoscrittore.nome_completo}}' => 'Persona Esempio', '{{pagamento.saldo}}' => '24,00 euro', '{{pagamento.scadenza}}' => '10 novembre', '{{pagamento.causale}}' => 'DEMO' );
$samples = array( 'conferma' => MI_Modello_Email::crea_istantanea( 42, $values ) );
$links = array( array( 'name' => 'Persona Esempio', 'url' => 'https://example.invalid/cancel?id=demo' ) );
foreach ( array( 'PRE_DEPARTURE_REMINDER', 'BALANCE_REMINDER', 'DEPOSIT_REMINDER', 'PARTICIPATION_CANCELLATION_REMINDER', 'REGISTRATION_CANCELLATION', 'EVENT_CANCELLATION' ) as $type ) {
	$samples[$type] = MI_Modello_Email::crea_istantanea_operativa( 42, $values, $type, '', 'https://example.invalid/status', $links, true );
}
$samples['attesa'] = MI_Modello_Email::crea_istantanea_lista_attesa( 42, $values );
$samples['offerta'] = MI_Modello_Email::crea_istantanea_offerta_lista_attesa( 42, $values, 'https://example.invalid/offer', 'domani' );
$samples['segreteria'] = MI_Modello_Email::crea_istantanea_nuova_iscrizione_segreteria( 42, $values, 1 );
$samples['annullamento_iscrizione'] = MI_Modello_Email::crea_istantanea_annullamento_iscrizione_iscritto( 42, 'Persona Esempio', 'DEMO' );
$samples['annullamento_persona'] = MI_Modello_Email::crea_istantanea_annullamento_partecipazione_iscritto( 42, 'Persona Esempio' );
$samples['annullamento_segreteria'] = MI_Modello_Email::crea_istantanea_annullamento_partecipazione_segreteria( 42, 'Persona Esempio', 'DEMO' );
$samples['pubblicazione'] = MI_Modello_Email::crea_istantanea_pubblicazione_evento( 42 );
$proof = new ReflectionMethod( 'MI_Spedizione_Email', 'prepara_istantanea_prova' );
$samples['prova'] = $proof->invoke( null, $samples['BALANCE_REMINDER'], '', true );
$receipt = array( 'people' => array( array( 'name' => 'Persona Esempio', 'lines' => array( array( 'name' => 'Partecipazione', 'price' => 0 ) ) ) ), 'total' => 0, 'paid' => 0, 'balance' => 0, 'deposit' => false, 'causale' => 'DEMO' );
$receipt_body = new ReflectionMethod( 'MI_Public_Balance', 'email_body' );
$samples['riepilogo'] = $samples['conferma'];
$samples['riepilogo']['html'] = $receipt_body->invoke( null, 42, $receipt, array( 'methods' => array() ) );
$samples['saldo_storico'] = array( 'layout' => 'PUBLIC_BALANCE', 'html' => '<p>Saluto</p><div>Corpo</div>' );
$bigger = '<p style="font-size:12px;margin:8px;color:#123456">Saluto</p><div style="font-size:15pt">Corpo <strong>in evidenza</strong> <a href="https://example.invalid/personal?a=1&amp;b=2" title="Dettaglio > altro">collegamento</a></div><h2 style="font-size:24px"><span>Titolo</span></h2>';
$samples['maggiore'] = array( 'layout' => 'INSTITUTIONAL', 'html' => $bigger );
$rendered = array();
foreach ( $samples as $name => $snapshot ) {
	$before = $snapshot;
	$rendered[$name] = MI_Modello_Email::componi_html( $snapshot );
	expect( $snapshot === $before, 'Snapshot modificata durante la composizione: ' . $name );
	expect( str_contains( $rendered[$name], 'font-size:' . ( 'maggiore' === $name ? '20' : '17' ) . 'px;line-height:1.68;' ), 'Formato comune assente: ' . $name );
}
expect( str_contains( $rendered['maggiore'], 'margin:8px;color:#123456' ), 'Stili non tipografici modificati' );
expect( str_contains( $rendered['maggiore'], '<h2 style="font-size:24px"><span>Titolo</span></h2>' ), 'Titolo o suoi discendenti ridotti' );
expect( str_contains( $rendered['maggiore'], 'href="https://example.invalid/personal?a=1&amp;b=2" title="Dettaglio > altro"' ), 'Attributi o collegamenti alterati' );
$normalize = new ReflectionMethod( 'MI_Modello_Email', 'uniforma_tipografia_corpo' );
$encoded_style = $normalize->invoke( null, '<p style="background-image:url(&quot;https://example.invalid/image?a=1&amp;b=2&quot;)">Testo</p>' );
expect( str_contains( $encoded_style, 'url(&quot;https://example.invalid/image?a=1&amp;b=2&quot;)' ), 'Entita negli stili codificate piu volte' );
foreach ( array( 'font-size:22px' => 22, 'font-size:150%' => 25.5, 'font:italic 700 20px/1.4 Arial !important' => 20 ) as $style => $expected ) {
	$html = $normalize->invoke( null, '<p>Saluto</p><div style="' . $style . '">Corpo</div>' );
	expect( substr_count( $html, 'font-size:' . $expected . 'px' ) === 3, 'Formato maggiore non applicato a saluto e corpo: ' . $style );
}
if ( in_array( '--render', $argv, true ) ) {
	$target = dirname( __DIR__, 2 ) . '/output/tipografia-email-3.27.39';
	if ( ! is_dir( $target ) ) mkdir( $target, 0777, true );
	foreach ( $rendered as $name => $html ) file_put_contents( $target . '/' . $name . '.html', $html );
}
echo 'PASS: ' . count( $samples ) . " modelli, anteprime/prove, formati maggiori conservati, titoli e link intatti, snapshot immutate.\n";
