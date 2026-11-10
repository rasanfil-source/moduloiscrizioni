<?php
define( 'ABSPATH', __DIR__ );
function absint( $v ) { return abs( (int) $v ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-admin.php';
$render = new ReflectionMethod( 'MI_Admin', 'cancellation_diagnostics' );
function diagnostic_html( $detail, $payload ) { ob_start(); $GLOBALS['render']->invoke( null, $detail, $payload ); return ob_get_clean(); }
function check_diagnostic( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$detail = array( 'template_type' => 'PARTICIPANT_CANCEL_SECRETARIAT', 'registration_id' => 123 );
$payload = array( 'cancellation_actor' => 'PARTICIPANT_LINK', 'order_code' => 'ORD-DEMO', 'participant_id' => 456, 'cancel_token' => 'never-display-this-token', 'email_preview' => array( 'html' => '<p>Anteprima</p>' ) );
$original = $payload;
$html = diagnostic_html( $detail, $payload );
foreach ( array( 'Origine dell’annullamento', 'PARTICIPANT_LINK', 'Conferma tramite collegamento personale', 'ORD-DEMO', '123', '456', 'non identifica chi' ) as $expected ) check_diagnostic( str_contains( $html, $expected ), 'Informazione mancante: ' . $expected );
check_diagnostic( ! str_contains( $html, 'never-display-this-token' ) && ! str_contains( $html, '<form' ), 'Token o azioni operative esposti' );
check_diagnostic( $payload === $original, 'Dati conservati modificati' );
foreach ( array( 'TRAVEL_DEADLINE' => 'automatico', 'TRAVEL_LINK' => 'spostamenti', 'ADMIN' => 'amministrativa' ) as $actor => $description ) {
	$payload['cancellation_actor'] = $actor;
	check_diagnostic( str_contains( diagnostic_html( $detail, $payload ), $description ), 'Origine automatica/amministrativa non distinta' );
}
$payload['cancellation_actor'] = 'Operatore <script>alert(1)</script>';
$html = diagnostic_html( $detail, $payload );
check_diagnostic( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ), 'Origine non escapata' );
$html = diagnostic_html( $detail, array() );
check_diagnostic( str_contains( $html, 'Non presente nei dati conservati.' ) && ! str_contains( $html, 'PARTICIPANT_LINK' ), 'Origine inventata per dati mancanti' );
$detail['template_type'] = 'REGISTRATION_CONFIRMATION';
check_diagnostic( diagnostic_html( $detail, $payload ) === '', 'Sezione aggiunta ad altre email' );
if ( in_array( '--html', $argv, true ) ) {
	$detail['template_type'] = 'PARTICIPANT_CANCEL_SECRETARIAT'; $payload = $original;
	echo '<!doctype html><meta charset="utf-8"><style>body{font:16px Arial;margin:30px;background:#f5f5f5}.card{background:white;padding:20px;border:1px solid #ddd}dd{margin:6px 0 18px}code{background:#eee;padding:3px}</style>' . diagnostic_html( $detail, $payload );
} else echo "PASS: origine annullamento conservata, canali distinti, dati mancanti espliciti, escaping e nessuna esposizione di token o azioni.\n";
