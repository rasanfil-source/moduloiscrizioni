<?php
define( 'ABSPATH', __DIR__ );

class WP_Error {
	public function __construct( public $code, private $message ) {}
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function esc_url_raw( $value ) { return (string) $value; }
function get_current_user_id() { return 7; }
function current_time( $format, $gmt = false ) { return '2026-09-21 00:00:00'; }
function wp_cache_delete( $id, $group ) {}
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][$id][$key] = $value; }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][$key] ); }
function add_action( ...$args ) {}
function wp_next_scheduled( $hook, $args ) { return $GLOBALS['schedule'][$hook . json_encode( $args )] ?? false; }
function wp_schedule_single_event( $when, $hook, $args ) { $GLOBALS['schedule'][$hook . json_encode( $args )] = $when; }
function wp_clear_scheduled_hook( $hook, $args ) { unset( $GLOBALS['schedule'][$hook . json_encode( $args )] ); }
function wp_doing_cron() { return true; }
function get_post_status( $id ) { return 'publish'; }

class MI_Portal_Management { public static function allowed() { return $GLOBALS['allowed'] ?? true; } }
class MI_Access { public static function can_access_event( $id ) { return 42 === (int) $id; } }
class MI_Event_Projection {
	public static function snapshot( $event_id ) {
		$rows = $GLOBALS['wpdb']->rows;
		$versions = array_map( static function ( $row ) { return array( 'id' => $row['id'], 'revision' => $row['workspace_revision'] ); }, $rows );
		return array(
			'rows' => $rows,
			'versions' => $versions,
			'fingerprint' => hash( 'sha256', json_encode( array( $versions, $GLOBALS['schema'] ?? array() ) ) ),
			'schema' => $GLOBALS['schema'] ?? array( 'fields' => array(), 'options' => array(), 'pricing' => 'ZERO' ),
			'profile' => $GLOBALS['profile'] ?? 'MINIMO',
		);
	}
	public static function request_payload( $event_id, $background = false, $snapshot = null ) {
		$snapshot = $snapshot ?: self::snapshot( $event_id );
		return array( 'snapshot' => $snapshot, 'payload' => array( 'projection_hash' => $snapshot['fingerprint'] ) );
	}
}
class MI_Workspace_Client {
	public static $calls = 0;
	public static function stable_json( $value ) { if ( is_array( $value ) ) ksort( $value ); return json_encode( $value ); }
	public static function request( $action, $payload ) {
		self::$calls++;
		if ( isset( $GLOBALS['on_remote'] ) ) ( $GLOBALS['on_remote'] )();
		return $GLOBALS['remote'] ?? array(
			'ok' => true, 'ready' => true, 'event_sheet_complete' => true,
			'id_foglio' => $GLOBALS['sheet_id'], 'url_foglio' => $GLOBALS['sheet_url'],
			'event_schema' => $GLOBALS['schema'] ?? array( 'fields' => array(), 'options' => array(), 'pricing' => 'ZERO' ),
			'operational_profile' => $GLOBALS['profile'] ?? 'MINIMO',
			'projection_hash' => $payload['projection_hash'],
		);
	}
}
class SheetDatabase {
	public $prefix = 'wp_';
	public $last_error = '';
	public $rows = array();
	public $queries = array();
	public function prepare( $sql, ...$args ) {
		foreach ( $args as $arg ) $sql = preg_replace( '/%[ds]/', is_int( $arg ) ? (string) $arg : "'" . str_replace( "'", "''", $arg ) . "'", $sql, 1 );
		return $sql;
	}
	public function query( $sql ) {
		$this->queries[] = $sql;
		if ( str_contains( $sql, "workspace_status='SYNCED'" ) ) {
			foreach ( $this->rows as &$row ) if ( 'PENDING' === $row['workspace_status'] && str_contains( $sql, '(id=' . $row['id'] . ' AND workspace_revision=' . $row['workspace_revision'] . ')' ) ) $row['workspace_status'] = 'SYNCED';
			unset( $row );
		}
		return 1;
	}
}

require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-sheet-open.php';
$GLOBALS['wpdb'] = new SheetDatabase();
$GLOBALS['sheet_id'] = str_repeat( 's', 32 );
$GLOBALS['sheet_url'] = 'https://docs.google.com/spreadsheets/d/' . $GLOBALS['sheet_id'] . '/edit';
$GLOBALS['meta'][42]['_mi_operational_sheet_id'] = $GLOBALS['sheet_id'];
$GLOBALS['meta'][42]['_mi_operational_sheet_url'] = $GLOBALS['sheet_url'];
$GLOBALS['wpdb']->rows = array( array( 'id' => 1, 'order_code' => 'DEMO', 'workspace_revision' => '1', 'workspace_status' => 'PENDING' ) );

$opened = MI_Sheet_Open::step( 42 );
check( ! is_wp_error( $opened ) && $opened['ready'], 'Direct projection was not opened.' );
check( 'SYNCED' === $GLOBALS['wpdb']->rows[0]['workspace_status'], 'Projection receipt was not recorded.' );
check( 1 === count( $GLOBALS['wpdb']->queries ), 'Projection receipt was not batched.' );
$calls = MI_Workspace_Client::$calls;
check( MI_Sheet_Open::step( 42 )['ready'] && $calls === MI_Workspace_Client::$calls, 'Unchanged projection contacted Google.' );

$GLOBALS['allowed'] = false;
check( is_wp_error( MI_Sheet_Open::step( 42 ) ), 'Unauthorized opening succeeded.' );
$GLOBALS['allowed'] = true;
$GLOBALS['wpdb']->rows[0]['workspace_revision'] = '2';
$GLOBALS['wpdb']->rows[0]['workspace_status'] = 'PENDING';
$GLOBALS['remote'] = array( 'ok' => true, 'ready' => false, 'event_sheet_complete' => false );
check( is_wp_error( MI_Sheet_Open::step( 42 ) ), 'Pending edits were overwritten.' );
unset( $GLOBALS['remote'] );

$GLOBALS['remote'] = array( 'ok' => true, 'ready' => true, 'event_sheet_complete' => true, 'id_foglio' => str_repeat( 'x', 32 ), 'url_foglio' => $GLOBALS['sheet_url'] );
check( is_wp_error( MI_Sheet_Open::step( 42 ) ), 'Mismatched sheet identity accepted.' );
unset( $GLOBALS['remote'] );

$GLOBALS['on_remote'] = static function () { $GLOBALS['wpdb']->rows[0]['workspace_revision'] = '3'; };
check( is_wp_error( MI_Sheet_Open::step( 42 ) ), 'Concurrent edit accepted.' );
unset( $GLOBALS['on_remote'] );

$GLOBALS['wpdb']->rows = array();
for ( $i = 1; $i <= 150; $i++ ) $GLOBALS['wpdb']->rows[] = array( 'id' => $i, 'order_code' => 'ORDER_' . $i, 'workspace_revision' => '1', 'workspace_status' => 'PENDING' );
$GLOBALS['wpdb']->queries = array();
check( MI_Sheet_Open::step( 42 )['ready'], 'Large projection did not open.' );
check( 2 === count( $GLOBALS['wpdb']->queries ), 'Receipt used one UPDATE per registration.' );
check( 150 === count( array_filter( $GLOBALS['wpdb']->rows, static function ( $row ) { return 'SYNCED' === $row['workspace_status']; } ) ), 'Batched receipt missed registrations.' );
echo "PASS: proiezione diretta, ricevuta accorpata, identità del foglio, modifiche pendenti e concorrenza.\n";
