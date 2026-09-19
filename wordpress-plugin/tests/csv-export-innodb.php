<?php
// Only the isolated local test database. Tables are owned by this test.
define( 'ABSPATH', __DIR__ ); define( 'ARRAY_A', 'ARRAY_A' );
function current_user_can( $c ) { return true; }
function check_admin_referer( $n ) {}
function wp_unslash( $s ) { return $s; }
function sanitize_key( $s ) { return $s; }
function sanitize_text_field( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function get_the_title( $id ) { return 'Evento ' . $id; }
function wp_die( $message ) { echo 'ERROR:' . $message; exit( 2 ); }
class MI_Access { static function activity_ids() { return 'ALL'; } static function can_access_event( $id ) { return true; } }
class MI_Field_Schema { static function catalog() { return array(); } }
class CsvDatabase {
	public $prefix = 'csv_audit_', $last_error = '', $db, $batches = 0;
	function __construct() {
		mysqli_report( MYSQLI_REPORT_OFF );
		$this->db = new mysqli( '127.0.0.1', 'root', 'local-ledger-test-only', 'mi_ledger_test', 33317 );
		if ( $this->db->connect_errno ) throw new RuntimeException( 'Local test database unavailable' );
		$this->db->set_charset( 'utf8mb4' );
	}
	function prepare( $sql, ...$args ) {
		if ( count( $args ) === 1 && is_array( $args[0] ) ) $args = $args[0];
		$i = 0;
		return preg_replace_callback( '/%[ds]/', function ( $m ) use ( &$i, $args ) { $v = $args[$i++]; return $m[0] === '%d' ? (string) (int) $v : "'" . $this->db->real_escape_string( $v ) . "'"; }, $sql );
	}
	function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	function query( $sql ) { $r = $this->db->query( $sql ); $this->last_error = $this->db->error; return $r; }
	function get_results( $sql, $format ) {
		$is_page = strpos( $sql, 'LIMIT 500' ) !== false;
		if ( $is_page && ++$this->batches === 2 && ( $GLOBALS['argv'][3] ?? '' ) === 'fail' ) { $this->last_error = 'injected'; return null; }
		$r = $this->query( $sql ); $rows = $r ? $r->fetch_all( MYSQLI_ASSOC ) : null;
		if ( $is_page && $this->batches === 1 && ( $GLOBALS['argv'][3] ?? '' ) === 'concurrent' ) {
			$other = new self();
			foreach ( array( "UPDATE csv_audit_mi_registrations SET total_cents=999999 WHERE id=2", "UPDATE csv_audit_mi_payments SET amount_cents=999 WHERE id=1", "DELETE FROM csv_audit_mi_participants WHERE id=1", "INSERT INTO csv_audit_mi_payments (registration_id,effective_at,transaction_kind,amount_cents) VALUES (2,'2020-01-01','PAYMENT',500)" ) as $q ) if ( ! $other->query( $q ) ) throw new RuntimeException( $other->last_error );
			$other->db->close();
		}
		return $rows;
	}
}
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-admin.php';
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
$wpdb = new CsvDatabase();
if ( ( $argv[1] ?? '' ) === 'child' ) {
	if ( ( $argv[3] ?? '' ) === 'empty' ) $_GET = array( 'event_id' => 99, 'payment_event_id' => 99 );
	if ( ( $argv[3] ?? '' ) === 'filtered' ) $_GET = array( 'event_id' => 42, 'mi_search' => 'ORDER-2', 'payment_event_id' => 42, 'transaction_kind' => 'REFUND', 'payment_from' => '2026-01-01', 'payment_to' => '2026-01-01' );
	if ( $argv[2] === 'payments' ) MI_Admin::export_payments(); else MI_Admin::export_registrations();
	exit;
}
function check_csv( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
function sql_csv( $sql ) { global $wpdb; if ( ! $wpdb->query( $sql ) ) throw new RuntimeException( $wpdb->last_error ); }
function seed_csv() {
	foreach ( array( 'payments', 'participants', 'registrations' ) as $table ) sql_csv( 'DELETE FROM csv_audit_mi_' . $table );
	for ( $i = 1; $i <= 4; $i++ ) sql_csv( "INSERT INTO csv_audit_mi_registrations (id,event_id,order_code,total_cents,initial_due_cents,economic_mode) VALUES ($i,42,'ORDER-$i',10000,3000,'DEPOSIT_BALANCE')" );
	$id = 0;
	foreach ( array( 2 => 501, 3 => 500, 4 => 200 ) as $rid => $count ) for ( $i = 0; $i < $count; $i++ ) {
		$id++; $extra = $rid === 2 && $i === 500 ? '{"late":"=formula"}' : '{}';
		sql_csv( "INSERT INTO csv_audit_mi_participants (id,registration_id,first_name,extra_json) VALUES ($id,$rid,'Person-$id','$extra')" );
	}
	for ( $i = 1; $i <= 1001; $i++ ) {
		$kind = $i % 2 === 0 ? 'REFUND' : 'PAYMENT'; $day = $i <= 600 ? '2026-01-01' : '2026-01-02';
		sql_csv( "INSERT INTO csv_audit_mi_payments (id,registration_id,effective_at,transaction_kind,amount_cents) VALUES ($i,2,'$day','$kind',10)" );
	}
}
function run_csv( $kind, $mode ) {
	$tmp = tmpfile(); $errors = tmpfile();
	$cmd = array( PHP_BINARY, '-d', 'extension_dir=' . dirname( PHP_BINARY ) . '/ext', '-d', 'extension=mysqli', '-d', 'error_reporting=24575', __FILE__, 'child', $kind, $mode );
	$process = proc_open( $cmd, array( 0 => array( 'pipe', 'r' ), 1 => $tmp, 2 => $errors ), $pipes );
	fclose( $pipes[0] ); $status = proc_close( $process ); rewind( $tmp ); rewind( $errors );
	$content = stream_get_contents( $tmp ); $error = stream_get_contents( $errors ); fclose( $tmp ); fclose( $errors );
	check_csv( $error === '', 'Unexpected PHP warning: ' . $error );
	check_csv( $status === ( $mode === 'fail' ? 2 : 0 ), 'Unexpected exit: ' . $content );
	return $content;
}
$created_tables = array();
try {
	// Refuse to overwrite tables from another unfinished run.
	$registration_text = array( 'order_code','status','workspace_status','buyer_first_name','buyer_last_name','buyer_email','buyer_phone','special_requests','economic_mode','order_options_json','privacy_consent_id','privacy_policy_version','privacy_accepted_at','created_at' );
	$participant_text = array( 'ticket_type_code','first_name','last_name','extra_json','options_json' );
	$payment_text = array( 'transaction_kind','installment_kind','payment_source','external_reference','operator_label','administrative_note' );
	$columns = static function ( $names ) { return implode( ',', array_map( static function ( $n ) { return "$n VARCHAR(255) NOT NULL DEFAULT ''"; }, $names ) ); };
	sql_csv( 'CREATE TABLE csv_audit_mi_registrations (id INT PRIMARY KEY,event_id INT,total_cents INT,initial_due_cents INT,balance_cents INT DEFAULT 7000,' . $columns( $registration_text ) . ') ENGINE=InnoDB' );
	$created_tables[] = 'registrations';
	sql_csv( 'CREATE TABLE csv_audit_mi_participants (id INT PRIMARY KEY,registration_id INT,' . $columns( $participant_text ) . ',KEY(registration_id)) ENGINE=InnoDB' );
	$created_tables[] = 'participants';
	sql_csv( 'CREATE TABLE csv_audit_mi_payments (id INT AUTO_INCREMENT PRIMARY KEY,registration_id INT,effective_at DATETIME,amount_cents INT,' . $columns( $payment_text ) . ',KEY(effective_at),KEY(registration_id)) ENGINE=InnoDB' );
	$created_tables[] = 'payments';
	foreach ( array( 'payments' => 1001, 'registrations' => 1202 ) as $kind => $count ) {
		seed_csv(); $baseline = run_csv( $kind, 'normal' );
		check_csv( $baseline === run_csv( $kind, 'concurrent' ), 'Concurrent mutation changed snapshot: ' . $kind );
		$lines = explode( "\n", rtrim( substr( $baseline, 3 ) ) );
		$rows = array_map( static function ( $line ) { return str_getcsv( $line, ';' ); }, $lines ); $headers = array_shift( $rows );
		check_csv( count( $rows ) === $count, 'Lost or duplicate rows: ' . $kind );
		if ( $kind === 'registrations' ) {
			check_csv( count( array_unique( array_column( $rows, 20 ) ) ) === 1202, 'Duplicate participant' );
			check_csv( array_sum( array_map( 'intval', array_column( $rows, 10 ) ) ) === 40000, 'Duplicate totals' );
			check_csv( array_sum( array_map( 'intval', array_column( $rows, 12 ) ) ) === 10, 'Incorrect net payments' );
			check_csv( array_sum( array_map( 'intval', array_column( $rows, 13 ) ) ) === 39990, 'Incorrect balance' );
			check_csv( end( $headers ) === 'Dato aggiuntivo (late)', 'Missing late dynamic column' );
			check_csv( strpos( $baseline, "'=formula" ) !== false, 'CSV injection protection lost' );
		}
		check_csv( strpos( run_csv( $kind, 'fail' ), 'ERROR:' ) === 0, 'Partial CSV sent on database failure' );
		$empty = run_csv( $kind, 'empty' ); check_csv( substr_count( $empty, "\n" ) === 1, 'Empty export should contain only header' );
		seed_csv(); $filtered = run_csv( $kind, 'filtered' );
		check_csv( substr_count( $filtered, "\n" ) === ( $kind === 'payments' ? 301 : 502 ), 'Filters changed export results' );
	}
	echo "CSV export InnoDB OK: multiple chunks, same-date payments, split registration, no participants, dynamic columns, balances, concurrent writes, failures, empty exports.\n";
} finally {
	foreach ( array_reverse( $created_tables ) as $table ) $wpdb->query( 'DROP TABLE IF EXISTS csv_audit_mi_' . $table );
}
