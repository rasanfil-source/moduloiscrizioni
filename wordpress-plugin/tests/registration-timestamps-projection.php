<?php
define( 'ABSPATH', __DIR__ ); define( 'ARRAY_A', 'ARRAY_A' );
$timestamp_meta = '1'; $stored_meta = array();
function absint( $v ) { return abs( (int) $v ); }
function wp_cache_delete( ...$args ) {}
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
function get_post( $id ) { return (object) array( 'post_type' => 'mi_event', 'post_title' => 'Evento sintetico' ); }
function get_post_meta( $id, $key, $single = true ) { return '_mi_registration_timestamps' === $key ? $GLOBALS['timestamp_meta'] : ( $GLOBALS['stored_meta'][$key] ?? '' ); }
function update_post_meta( $id, $key, $value ) { $GLOBALS['stored_meta'][$key] = $value; }
class MI_Event_Post_Type { const EVENT_TYPE = 'mi_event'; }
class MI_Workspace_Client { public static function stable_json( $v ) { return json_encode( $v ); } }
class MI_Field_Schema {
	public static function workspace_event_schema( $id ) { return array( 'pricing' => 'ZERO', 'options' => array(), 'fields' => array() ) + ( $GLOBALS['timestamp_meta'] === '1' ? array( 'registration_timestamps' => true ) : array() ); }
	public static function resolved_operational_profile( $id ) { return 'MINIMO'; }
}
class MI_Payment_People {
	public static function calculate_for_display( ...$args ) { return array( 'people' => array() ); }
	public static function summary( $position ) { return array( 'totals_known' => false ); }
}
class TimestampProjectionDB {
	public $prefix = 'wp_', $last_error = '';
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_var( $sql ) { return '1'; }
	public function get_results( $sql, $format ) {
		if ( str_contains( $sql, 'SELECT * FROM wp_mi_registrations' ) ) return array( array( 'id' => 1, 'event_id' => 42, 'order_code' => 'ORD-1', 'status' => 'CANCELLED', 'buyer_first_name' => 'Persona', 'buyer_last_name' => 'Sintetica', 'buyer_email' => '', 'buyer_phone' => '', 'total_qty' => 0, 'total_cents' => 0, 'economic_mode' => 'REGISTRATION_ONLY', 'initial_due_cents' => 0, 'workspace_revision' => '2', 'workspace_status' => 'PENDING', 'created_at' => '2026-11-09 09:10:11', 'capacity_released_at' => '2026-11-10 11:12:13' ) );
		if ( str_contains( $sql, 'SELECT p.* FROM wp_mi_participants' ) ) return array(
			array( 'id' => 1, 'registration_id' => 1, 'first_name' => 'Persona', 'last_name' => 'Uno', 'status' => 'CANCELLED', 'cancelled_at' => '2026-11-09 10:11:12', 'ticket_type_code' => 'base', 'ticket_index' => 1, 'extra_json' => '{"registration_time":"non autorevole"}' ),
			array( 'id' => 2, 'registration_id' => 1, 'first_name' => 'Persona', 'last_name' => 'Due', 'status' => 'ACTIVE', 'ticket_type_code' => 'base', 'ticket_index' => 2, 'extra_json' => '{}' )
		);
		return array();
	}
}
$wpdb = new TimestampProjectionDB();
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-event-projection.php';
$snapshot = MI_Event_Projection::snapshot( 42 );
$people = $snapshot['projection']['participants'];
$one = json_decode( $people[0]['dati_aggiuntivi_json'], true ); $two = json_decode( $people[1]['dati_aggiuntivi_json'], true );
if ( $one['registration_time'] !== '2026-11-09 10:10:11' || $one['cancellation_time'] !== '2026-11-09 11:11:12' || $two['cancellation_time'] !== '2026-11-10 12:12:13' ) throw new RuntimeException( 'Proiezione Google perde le date canoniche' );
$timestamp_meta = '';
$legacy = MI_Event_Projection::snapshot( 42 );
if ( isset( json_decode( $legacy['projection']['participants'][1]['dati_aggiuntivi_json'], true )['registration_time'] ) ) throw new RuntimeException( 'Evento esistente riceve date nuove' );
echo "PASS: proiezione firmata Google con date autorevoli; annullamento individuale e collettivo, nessuna aggiunta retroattiva.\n";
