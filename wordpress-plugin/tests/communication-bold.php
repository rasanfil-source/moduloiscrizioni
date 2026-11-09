<?php
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function home_url($path = '') { return 'https://example.invalid/' . ltrim($path, '/'); }
ob_start(); require __DIR__ . '/email-newlines.php'; ob_end_clean();
$message = "Siamo lieti dell'interesse per l'iniziativa.\n\nTi invitiamo a verificare la disponibilita: **se sai gia di non poter partecipare**, ti chiediamo di **annullare **la tua partecipazione tramite il **collegamento personale riportato qui sotto**.\n\nGrazie per la collaborazione.**";
$snapshot = MI_Modello_Email::crea_istantanea_operativa( 42, array( '{{evento.titolo}}' => 'Evento sintetico', '{{sottoscrittore.nome_completo}}' => 'Persona di prova' ), 'PARTICIPATION_CANCELLATION_REMINDER', $message, '', array(), false, true );
$proof = new ReflectionMethod( 'MI_Spedizione_Email', 'prepara_istantanea_prova' );
$snapshot = $proof->invoke( null, $snapshot, '', true );
for ( $i = 0; $i < 5; $i++ ) {
	$snapshot = MI_Modello_Email::ripara_istantanea_codifica( $snapshot );
	foreach ( array( 'se sai gia di non poter partecipare', 'annullare ', 'collegamento personale riportato qui sotto' ) as $bold ) {
		expect( str_contains( $snapshot['html'], '<strong>' . $bold . '</strong>' ), 'grassetto perso dopo normalizzazione ' . $i . ': ' . $bold );
	}
}
expect( str_contains( $snapshot['html'], 'Campione anonimizzato' ), 'Avviso prova perso durante normalizzazione' );
$rich = array( 'testo' => 'Versione semplice senza formattazione.', 'html' => '<p><strong>Gia formattato</strong> e **ancora in grassetto**; *corsivo*.</p><p>Nota finale.**</p><a href="https://example.invalid/a" title="Due > **attributo**">Collegamento</a>' );
$once = MI_Modello_Email::ripara_istantanea_codifica( $rich );
$twice = MI_Modello_Email::ripara_istantanea_codifica( $once );
$canonical_html = static function ( $html ) { return $html; };
expect( $canonical_html( $once['html'] ) === $canonical_html( $twice['html'] ), 'Riparazione non idempotente' );
expect( str_contains( $twice['html'], '<strong>Gia formattato</strong>' ) && str_contains( $twice['html'], '<strong>ancora in grassetto</strong>' ), 'HTML valido ricostruito dal testo semplice' );
expect( str_contains( $twice['html'], 'title="Due > **attributo**"' ), 'Markdown interpretato dentro attributo HTML' );
expect( str_contains( $twice['html'], '<a href="https://example.invalid/a"' ), 'Collegamento perso' );
$template = new ReflectionMethod( 'MI_Modello_Email', 'ripara_modello_testuale' );
$settings = $template->invoke( null, array( 'html' => $rich['html'], 'text' => $rich['testo'], 'footer' => '' ) );
expect( str_contains( $settings['html'], '<strong>Gia formattato</strong>' ) && str_contains( $settings['html'], '<strong>ancora in grassetto</strong>' ), 'Modello salvato perde i grassetti esistenti' );
$complete = MI_Modello_Email::componi_html( $snapshot );
expect( preg_match( '#<strong\b[^>]*>collegamento personale riportato qui sotto</strong>#u', $complete ), 'Grassetto perso nell HTML finale' );
$unsafe = MI_Modello_Email::ripara_istantanea_codifica( array( 'html' => MI_Modello_Email::formatta_comunicazione( '**<img src=x onerror=alert(1)>** e nota.**' ), 'testo' => 'Testo semplice' ) );
expect( ! str_contains( $unsafe['html'], '<img' ) && str_contains( $unsafe['html'], '&lt;img' ), 'HTML inserito dall utente non escapato' );
if ( in_array( '--render', $argv, true ) ) file_put_contents( dirname( __DIR__, 2 ) . '/output/grassetto-3.27.32/prova.html', $complete );
echo "PASS: grassetti conservati in prova e HTML finale, normalizzazioni ripetute, marcatore spaiato, avviso e collegamenti.\n";
