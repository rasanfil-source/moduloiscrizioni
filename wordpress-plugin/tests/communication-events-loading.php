<?php
// Il caricamento di Messaggi deve leggere gli eventi senza JOIN combinatori sui metadati.
define( 'ABSPATH', __DIR__ );
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['metadata'][$id][$key] ?? ''; }
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
function current_time( $format, $gmt = false ) { return strtotime( '2026-11-09 12:00:00 UTC' ); }
function get_posts( $query ) {
	$GLOBALS['queries'][] = $query;
	if ( isset( $query['meta_query'] ) || empty( $query['update_post_meta_cache'] ) || ! empty( $query['update_post_term_cache'] ) || empty( $query['no_found_rows'] ) ) throw new RuntimeException( 'Caricamento costoso ripristinato.' );
	if ( $query['post_status'] !== array( 'publish', 'draft', 'private' ) || $query['orderby'] !== 'title' || $query['order'] !== 'ASC' ) throw new RuntimeException( 'Stati o ordine modificati.' );
	return array_values( array_filter( $GLOBALS['events'], static function ( $event ) use ( $query ) { return ! isset( $query['post__in'] ) || in_array( $event->ID, $query['post__in'], true ); } ) );
}
class MI_Access { public static $scope = 'ALL'; public static function event_ids() { return self::$scope; } }
class MI_Event_Post_Type { const EVENT_TYPE = 'mi_event'; }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-portal.php';
function check_events( $actual, $expected, $message ) { if ( $actual !== $expected ) throw new RuntimeException( $message . ': ' . json_encode( $actual ) ); }
$metadata = array(
	1 => array( '_mi_event_starts_at' => '2026-11-10T08:00' ),
	2 => array( '_mi_event_starts_at' => '2026-11-08T20:00' ),
	3 => array( '_mi_event_starts_at' => '2026-11-09T00:00' ),
	4 => array( '_mi_registration_closes_at' => '2026-11-08T23:59' ),
	5 => array(),
	6 => array( '_mi_event_starts_at' => '2026-11-01T08:00', '_mi_event_cancelled_at' => '2026-11-02 10:00:00' ),
	7 => array( '_mi_event_starts_at' => '2026-12-01T08:00', '_mi_event_archived_at' => '2026-11-08 10:00:00' ),
	8 => array( '_mi_event_starts_at' => '2026-11-01T08:00', '_mi_event_archived_at' => '2026-11-08 10:00:00', '_mi_event_cancelled_at' => '2026-11-02 10:00:00' ),
	9 => array( '_mi_event_starts_at' => '2026-11-10T08:00', '_mi_registration_closes_at' => '2026-11-01T08:00' ),
	10 => array( '_mi_registration_closes_at' => '2026-11-09T00:00' ),
	11 => array( '_mi_event_starts_at' => '2026-11-08T08:00', '_mi_event_ends_at' => '2026-11-11T18:00' ),
	12 => array( '_mi_event_starts_at' => '2026-11-08T08:00', '_mi_event_ends_at' => '2026-11-09T08:00' ),
);
$events = array_map( static function ( $id ) { return (object) array( 'ID' => $id, 'post_title' => 'Evento ' . $id ); }, array_keys( $metadata ) );
$queries = array();
$reader = new ReflectionMethod( 'MI_Portal', 'accessible_events' );
$read = static function ( $past ) use ( $reader ) { return array_column( $reader->invoke( null, $past ), 'ID' ); };
check_events( $read( false ), array( 1, 3, 5, 6, 9, 10, 11, 12 ), 'Elenco corrente cambiato' );
check_events( $read( true ), array( 2, 4, 7, 8 ), 'Elenco storico cambiato' );
check_events( MI_Portal::event_dates_past( 2, array( 'event_ends_at' => '2026-11-11T18:00' ) ), false, 'Scheda pubblicata classificata prima della conclusione' );
check_events( MI_Portal::event_dates_past( 11, array( 'event_ends_at' => '', 'event_starts_at' => '2026-11-08T08:00' ) ), true, 'Conclusione in bozza applicata alla revisione pubblicata' );
MI_Access::$scope = array( 1, 2, 6 );
check_events( $read( false ), array( 1, 6 ), 'Ambito corrente perso' );
check_events( $read( true ), array( 2 ), 'Ambito storico perso' );
check_events( $queries[2]['post__in'], array( 1, 2, 6 ), 'Filtro SQL autorizzazioni perso' );
MI_Access::$scope = array(); $before = count( $queries );
check_events( $read( false ), array(), 'Ambito vuoto esteso' );
check_events( count( $queries ), $before, 'Query superflua per ambito vuoto' );
// Molti eventi mantengono una sola lettura e non caricano destinatari, email o Google.
MI_Access::$scope = 'ALL';
$events = array(); $metadata = array();
for ( $id = 1; $id <= 3000; $id++ ) { $events[] = (object) array( 'ID' => $id ); $metadata[$id] = array( '_mi_event_starts_at' => $id % 2 ? '2026-11-10T08:00' : '2026-11-01T08:00' ); }
$before = count( $queries );
check_events( count( $read( false ) ), 1500, 'Elenco numeroso incompleto' );
check_events( count( $queries ) - $before, 1, 'Piu letture per evento' );
echo "PASS: Messaggi senza JOIN sui metadati; correnti/storico, date, annullati, archiviati, permessi e 3000 eventi.\n";
