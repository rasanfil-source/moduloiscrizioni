<?php
define( 'ABSPATH', __DIR__ );
function is_ssl() { return true; }
function is_user_logged_in() { throw new RuntimeException( 'La prova non richiede autenticazione' ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $s ) ); }
function wp_unslash( $v ) { return $v; }
function home_url( $path = '' ) { return 'https://example.invalid/' . ltrim( $path, '/' ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
class MI_Registration_Service { public static function cancel_participant_with_token( ...$args ) { throw new RuntimeException( 'Una prova non deve chiamare la cancellazione reale' ); } }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-modello-email.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-portal.php';
if ( in_array( '--html', $argv, true ) ) { $_GET = array( 'mi_cancel_demo' => in_array( '--done', $argv, true ) ? 'done' : '1' ); echo MI_Portal::render(); exit; }
$_GET = array( 'mi_cancel_demo' => '1', 'mi_cancel_participant' => '99', 'mi_cancel_token' => 'not-a-real-token' );
$html = MI_Portal::render();
foreach ( array( 'Annullare questa partecipazione?', 'Simula annullamento', 'Persona di prova', 'Nessuna iscrizione reale', 'method="get"' ) as $expected ) if ( ! str_contains( $html, $expected ) ) throw new RuntimeException( 'Pagina dimostrativa incompleta: ' . $expected );
foreach ( array( 'mi_cancel_token', 'participant_id', 'cancel_participant_public', 'mi_portal_action' ) as $forbidden ) if ( str_contains( $html, $forbidden ) ) throw new RuntimeException( 'Controllo operativo nella dimostrazione' );
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = array( 'mi_portal_action' => 'cancel_participant_public', 'participant_id' => 99, 'cancel_token' => 'not-a-real-token' );
MI_Portal::handle_actions();
$_GET = array( 'mi_cancel_demo' => 'done' ); $done = MI_Portal::render();
if ( ! str_contains( $done, 'Simulazione completata' ) || ! str_contains( $done, 'Nessuna iscrizione reale' ) ) throw new RuntimeException( 'Esito demo ambiguo' );
$public = new ReflectionMethod( 'MI_Portal', 'is_public_response_view' );
if ( ! $public->invoke( null ) ) throw new RuntimeException( 'Demo trattata come portale riservato' );
echo "PASS: conferma e completamento dimostrativi pubblici, nessuna scrittura, nessun token, POST operativo ignorato.\n";
