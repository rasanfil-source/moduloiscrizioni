<?php
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
function absint( $n ) { return abs( (int) $n ); }
function sanitize_text_field( $s ) { return (string) $s; }
function current_time( ...$args ) { return '2026-09-14 12:00:00'; }
function is_wp_error( $v ) { return false; }
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
class MI_Workspace_Client {
	static $payload;
	static function request( $action, $payload ) { self::$payload = $payload; return array( 'complete' => true, 'workspace_revision' => '1' ); }
}
class BalanceDatabase {
	public $prefix = 'wp_', $last_error = '', $paid = 0, $revision = 1, $fail = false;
	function prepare( $sql, ...$args ) { return $sql; }
	function get_row( $sql, $mode ) {
		return array_merge( array_fill_keys( array( 'buyer_first_name','buyer_last_name','buyer_email','buyer_phone','privacy_consent_id','privacy_policy_version','privacy_accepted_at','marketing_consent_id','marketing_accepted_at' ), '' ), array( 'id' => 1, 'event_id' => 2, 'workspace_status' => 'PENDING', 'workspace_revision' => 1, 'order_code' => 'TEST', 'idempotency_key' => 'test', 'status' => 'CONFIRMED', 'total_cents' => 10000, 'initial_due_cents' => 3000, 'balance_cents' => 7000, 'economic_mode' => 'DEPOSIT_BALANCE', 'snapshot_json' => '{}', 'event_revision_id' => '1', 'event_revision_hash' => str_repeat( 'a', 64 ), 'payment_methods_json' => '[]' ) );
	}
	function get_results( $sql, $mode ) { return array(); }
	function get_var( $sql ) {
		if ( strpos( $sql, 'SUM(' ) !== false ) { if ( $this->fail ) $this->last_error = 'failure'; return $this->paid; }
		return $this->revision;
	}
	function query( $sql ) { return 1; }
}
foreach ( array( 0 => 10000, 3000 => 7000, 10000 => 0, 8000 => 2000, 12000 => 0 ) as $paid => $balance ) {
	$wpdb = new BalanceDatabase(); $wpdb->paid = $paid;
	MI_Registration_Service::sync_workspace( 1 );
	$p = MI_Workspace_Client::$payload;
	if ( $p['paid_cents'] !== $paid || $p['balance_cents'] !== $balance || $p['payments'] !== array() ) throw new RuntimeException( 'Saldo replica errato' );
}
foreach ( array( 'fail', 'revision' ) as $failure ) {
	$wpdb = new BalanceDatabase(); $wpdb->$failure = $failure === 'fail' ? true : 2;
	MI_Workspace_Client::$payload = null;
	if ( MI_Registration_Service::sync_workspace( 1 ) !== 'PENDING' || MI_Workspace_Client::$payload !== null ) throw new RuntimeException( 'Replica incoerente inviata' );
}
echo "Workspace balance OK: zero, parziale, saldo, rimborso, credito, errore DB e revisione concorrente.\n";
