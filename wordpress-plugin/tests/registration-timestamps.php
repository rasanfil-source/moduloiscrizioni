<?php
define( 'ABSPATH', __DIR__ );
$meta = array(); $hooks = array();
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function add_post_meta( $id, $key, $value, $unique = false ) { if ( $unique && isset( $GLOBALS['meta'][$id][$key] ) ) return false; $GLOBALS['meta'][$id][$key] = $value; return true; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = array( $hook, $callback, $priority, $args ); }
function add_filter( ...$args ) {}
function wp_timezone() { return new DateTimeZone( $GLOBALS['timezone'] ?? 'Europe/Rome' ); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-timestamps.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-event-post-type.php';
function check_time( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
MI_Event_Post_Type::boot();
check_time( in_array( array( 'save_post_mi_event', array( 'MI_Event_Post_Type', 'initialize_registration_timestamps' ), 5, 3 ), $hooks, true ), 'Inizializzazione non collegata alla creazione evento' );
$post = (object) array( 'post_type' => 'mi_event' );
MI_Event_Post_Type::initialize_registration_timestamps( 10, $post, true );
check_time( ! MI_Registration_Timestamps::enabled( 10 ), 'Evento preesistente modificato' );
MI_Event_Post_Type::initialize_registration_timestamps( 11, $post, false );
check_time( MI_Registration_Timestamps::enabled( 11 ), 'Nuovo evento non abilitato' );
MI_Event_Post_Type::initialize_registration_timestamps( 12, (object) array( 'post_type' => 'post' ), false );
check_time( ! MI_Registration_Timestamps::enabled( 12 ), 'Tipo estraneo modificato' );
$order = array( 'created_at' => '2026-11-09 10:20:30', 'status' => 'CONFIRMED', 'capacity_released_at' => null );
$active = array( 'status' => 'ACTIVE', 'cancelled_at' => null );
$times = MI_Registration_Timestamps::values( $order, $active );
check_time( $times === array( 'registration_time' => '2026-11-09 11:20:30', 'cancellation_time' => '' ), 'Ora iscrizione o vuoto attivo errati' );
$person = array( 'status' => 'CANCELLED', 'cancelled_at' => '2026-11-09 11:01:02' );
check_time( MI_Registration_Timestamps::values( $order, $person )['cancellation_time'] === '2026-11-09 12:01:02', 'Annullamento individuale perso' );
$order['status'] = 'CANCELLED'; $order['capacity_released_at'] = '2026-11-10 09:05:06';
check_time( MI_Registration_Timestamps::values( $order, $active )['cancellation_time'] === '2026-11-10 10:05:06', 'Annullamento intera prenotazione perso' );
check_time( MI_Registration_Timestamps::values( $order, $person )['cancellation_time'] === '2026-11-09 12:01:02', 'Annullamento precedente sovrascritto da quello collettivo' );
check_time( MI_Registration_Timestamps::values( $order )['cancellation_time'] === '2026-11-10 10:05:06', 'Rapporto prenotazioni senza data annullamento' );
check_time( MI_Registration_Timestamps::values( $order, array( 'status' => 'EXPIRED', 'cancelled_at' => '2026-11-01 12:00:00' ) )['cancellation_time'] === '', 'Scadenza scambiata per annullamento' );
$order['status'] = 'EXPIRED';
check_time( MI_Registration_Timestamps::values( $order, $active )['cancellation_time'] === '', 'Rilascio capienza per scadenza scambiato per annullamento' );
$order['created_at'] = '2026-07-01 10:20:30';
check_time( MI_Registration_Timestamps::values( $order )['registration_time'] === '2026-07-01 12:20:30', 'Ora legale errata' );
foreach ( array( '', '0000-00-00 00:00:00', '2026-02-30 12:00:00', '=NOW()', null ) as $invalid ) {
	$order['created_at'] = $invalid;
	check_time( MI_Registration_Timestamps::values( $order )['registration_time'] === '', 'Data mancante o invalida inventata' );
}
echo "PASS: solo nuovi eventi; date canoniche, secondi, fuso/ora legale, annullamenti parziali e completi, scadenze e dati mancanti.\n";
