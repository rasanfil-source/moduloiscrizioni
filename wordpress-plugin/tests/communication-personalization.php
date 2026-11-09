<?php
define( 'ARRAY_A', 'ARRAY_A' );
function current_time( ...$args ) { return '2026-11-09 18:00:00'; }
function home_url( $path = '' ) { return 'https://example.invalid/' . ltrim( $path, '/' ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
ob_start(); require __DIR__ . '/email-newlines.php'; ob_end_clean();
class MI_Portal {
	public static function participant_cancel_url( $id, $token ) { return add_query_arg( array( 'mi_portal' => 1, 'mi_cancel_participant' => $id, 'mi_cancel_token' => $token ), home_url( '/' ) ); }
}
class PersonalizationDatabase {
	public $prefix = 'wp_', $last_error = '', $tokens = array();
	public function prepare( $sql, ...$args ) { expect( $args === array( 1 ), 'Prenotazione diversa da quella selezionata' ); return $sql; }
	public function get_results( $sql, $format ) {
		expect( str_contains( $sql, "status='ACTIVE'" ), 'Partecipanti annullati non esclusi dai collegamenti' );
		return array( array( 'id' => 7, 'first_name' => 'Maria', 'last_name' => 'Rossi' ), array( 'id' => 8, 'first_name' => 'Paolo', 'last_name' => 'Bianchi' ) );
	}
	public function insert( $table, $data, $formats ) { expect( $table === 'wp_mi_cancellation_tokens', 'Scrittura inattesa' ); $this->tokens[] = $data; return 1; }
}
$wpdb = new PersonalizationDatabase();
$links = MI_Registration_Service::create_participant_cancellation_links( 1 );
expect( array_column( $links, 'name' ) === array( 'Maria Rossi', 'Paolo Bianchi' ), 'Nome e cognome non letti dai partecipanti effettivi' );
foreach ( $links as $index => $link ) {
	parse_str( parse_url( $link['url'], PHP_URL_QUERY ), $query );
	expect( (int) $query['mi_cancel_participant'] === $wpdb->tokens[$index]['participant_id'], 'Token associato al partecipante sbagliato' );
	expect( hash( 'sha256', $query['mi_cancel_token'] ) === $wpdb->tokens[$index]['token_hash'], 'Token diverso da quello salvato' );
}
$GLOBALS['email_meta'][42] = array();
$values = array( '{{evento.titolo}}' => 'Evento sintetico', '{{sottoscrittore.nome_completo}}' => 'Referente diverso dai partecipanti' );
$many = MI_Modello_Email::crea_istantanea_operativa( 42, $values, 'PARTICIPATION_CANCELLATION_REMINDER', '', '', $links, true, false );
expect( str_starts_with( $many['testo'], 'Buongiorno Maria Rossi e Paolo Bianchi,' ), 'Saluto familiare senza i nomi dei partecipanti' );
$single = MI_Modello_Email::crea_istantanea_operativa( 42, $values, 'PARTICIPATION_CANCELLATION_REMINDER', '', '', array( $links[0] ), true, false );
expect( str_starts_with( $single['testo'], 'Buongiorno Maria Rossi,' ), 'Saluto individuale non personalizzato' );
expect( ! str_contains( $single['html'], 'Referente diverso' ) && ! str_contains( $single['html'], 'Persona di prova' ), 'Invio operativo usa nome del referente o campione' );
expect( str_contains( $single['html'], '<strong>annullare la relativa iscrizione</strong>' ), 'Testo suggerito perde il grassetto' );
expect( str_contains( $many['html'], '<strong>annullare la relativa iscrizione</strong>' ), 'Testo neutro assente dalla prenotazione multipla' );
expect( ! str_contains( $single['html'], 'ti scriviamo per sapere' ), 'Introduzione ridondante rimasta' );
foreach ( array( $single, $many ) as $snapshot ) {
	$html = MI_Modello_Email::componi_html( $snapshot ); $plain = MI_Modello_Email::componi_testo( $snapshot );
	expect( strpos( $html, 'A presto!' ) < strpos( $html, '>Assistenza<' ), 'Firma HTML dopo Assistenza' );
	expect( strpos( $plain, 'A presto!' ) < strpos( $plain, 'Assistenza:' ), 'Firma testo dopo Assistenza' );
}
$demo_values = $values; $demo_values['{{sottoscrittore.nome_completo}}'] = 'Persona di prova';
$demo = MI_Modello_Email::crea_istantanea_operativa( 42, $demo_values, 'PARTICIPATION_CANCELLATION_REMINDER', '', '', array(), false, true );
expect( str_starts_with( $demo['testo'], 'Buongiorno Persona di prova,' ), 'Campione di prova non anonimizzato' );
if ( in_array( '--render', $argv, true ) ) {
	file_put_contents( dirname( __DIR__, 2 ) . '/output/cancellazione-prova-3.27.33/operativo.html', MI_Modello_Email::componi_html( $single ) );
	file_put_contents( dirname( __DIR__, 2 ) . '/output/cancellazione-prova-3.27.33/email.html', MI_Modello_Email::componi_html( $demo ) );
}
echo "PASS: nomi effettivi dal servizio partecipanti, token corretti per persona, referente diverso, saluto singolo/familiare, prova anonima e firma prima di Assistenza.\n";
