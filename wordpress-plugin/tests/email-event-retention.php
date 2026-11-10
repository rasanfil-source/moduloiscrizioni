<?php
define( 'ABSPATH', __DIR__ );
class WP_Error { public function __construct( public $code, public $message, public $data = array() ) {} }
class MI_Event_Post_Type { const EVENT_TYPE = 'mi_event'; }
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
function get_post( $id ) { return 99 === $id ? null : (object) array( 'post_type' => 98 === $id ? 'page' : 'mi_event' ); }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';
function check_retention( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
$meta = array(
 42 => array( '_mi_event_starts_at' => '2027-01-01T10:00', '_mi_event_ends_at' => '2027-01-04T18:00' ),
 43 => array( '_mi_event_starts_at' => '2027-01-01T10:00' ),
 44 => array( '_mi_event_starts_at' => '2027-01-01T10:00', '_mi_event_ends_at' => 'invalid' ),
 45 => array( '_mi_event_starts_at' => '2027-01-01T10:00', '_mi_event_ends_at' => '2026-12-31T10:00' ),
 46 => array( '_mi_event_starts_at' => '2027-02-30T10:00' ),
);
$r = MI_Spedizione_Email::retention_event_deadlines( array( 'event_ids' => array( 42, 43, 44, 45, 46, 98, 99 ) ) );
$deadlines = (array) $r['deadlines'];
check_retention( count( $deadlines ) === 2, 'Eventi ignoti o date invalide ammessi alla pulizia' );
foreach ( array( 42 => '2027-01-04T18:00', 43 => '2027-01-01T10:00' ) as $id => $raw ) {
 $expected = ( new DateTimeImmutable( $raw, wp_timezone() ) )->modify( '+90 days' )->getTimestamp() * 1000;
 check_retention( $deadlines[$id] === $expected, 'Scadenza o fuso orario errati' );
}
$meta[43]['_mi_event_starts_at'] = '2027-06-01T10:00';
$postponed = MI_Spedizione_Email::retention_event_deadlines( array( 'event_ids' => array( 43 ) ) );
check_retention( $postponed['deadlines']->{'43'} > $deadlines[43], 'Rinvio evento ignorato' );
check_retention( is_wp_error( MI_Spedizione_Email::retention_event_deadlines( array( 'event_ids' => range( 1, 101 ) ) ) ), 'Limite lotto ignorato' );
check_retention( true === MI_Registration_Service::validate_event_end( '2027-01-01T10:00', '' ), 'Fine facoltativa rifiutata' );
check_retention( true === MI_Registration_Service::validate_event_end( '2027-01-01T10:00', '2027-01-04T18:00' ), 'Evento di più giorni rifiutato' );
check_retention( is_wp_error( MI_Registration_Service::validate_event_end( '2027-01-01T10:00', '2026-12-31T18:00' ) ), 'Fine prima dell’inizio accettata' );
check_retention( is_wp_error( MI_Registration_Service::validate_event_end( '2027-01-01T10:00', '2027-02-30T18:00' ) ), 'Data inesistente accettata' );
echo "PASS: fine evento, ripiego sull’inizio, rinvio, fuso orario, date invalide e validazione.\n";
