<?php
// Pure domain/read tests: no WordPress deployment, email or Sheets operations.
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-portal.php';
function verify_position( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$order = array( 'id' => 1, 'total_cents' => 10000, 'initial_due_cents' => 3000, 'balance_cents' => 7000, 'economic_mode' => 'DEPOSIT_BALANCE' );
foreach ( array( 0 => 10000, 3000 => 7000, 10000 => 0, 8000 => 2000 ) as $paid => $expected ) {
	$position = MI_Payment_Ledger::position( $order, $paid );
	verify_position( $position['balance'] === $expected, 'Residual must follow actual net movements, including refunds' );
}
verify_position( MI_Payment_Ledger::position( array_replace( $order, array( 'economic_mode' => 'FULL_PAYMENT', 'balance_cents' => 0 ) ), 0 )['balance'] === 10000, 'Unpaid single installment is not settled' );
verify_position( ! MI_Payment_Ledger::position( array_replace( $order, array( 'economic_mode' => 'PRICE_ONLY' ) ), 0 )['managed'], 'Price only is not a collection workflow' );
verify_position( $order['balance_cents'] === 7000, 'Original plan remains unchanged' );
foreach ( array( 0=>3000, 1000=>2000, 3000=>0, 10000=>0, 2000=>1000 ) as $paid=>$missing ) {
	$p=MI_Payment_Ledger::position($order,$paid);
	verify_position($p['deposit_due']===3000 && $p['deposit_missing']===$missing && $p['deposit_covered']===($paid>=3000),'Deposit follows net payments including refunds');
}
$p=MI_Payment_Ledger::position(array_replace($order,array('initial_due_cents'=>0)),0);
verify_position(!$p['deposit_covered'] && $p['deposit_missing']===0,'Zero deposit must not imply money paid');
$p=MI_Payment_Ledger::position(array_replace($order,array('total_cents'=>1000)),500);
verify_position($p['deposit_due']===1000 && $p['deposit_missing']===500,'Adjusted total caps deposit');
verify_position(!MI_Payment_Ledger::position(array_replace($order,array('economic_mode'=>'FULL_PAYMENT')),0)['deposit_plan'],'Single installment is not a deposit');
class PositionDatabase {
	public $prefix = 'wp_', $last_error = '', $queries = array();
	function get_results( $sql, $format ) { $this->queries[] = $sql; return array( array( 'registration_id' => 1, 'paid' => 10000 ) ); }
}
$wpdb = new PositionDatabase();
$positions = MI_Payment_Ledger::positions( array( $order, array_replace( $order, array( 'id' => 2 ) ) ) );
verify_position( $positions[1]['balance'] === 0 && $positions[2]['balance'] === 10000, 'Missing movements mean unpaid, not missing order' );
verify_position( count( $wpdb->queries ) === 1 && str_contains( $wpdb->queries[0], 'IN (1,2)' ), 'One bounded aggregate for scoped orders' );
$wpdb->last_error = 'synthetic failure';
try { MI_Payment_Ledger::positions( array( $order ) ); throw new LogicException( 'Read error hidden' ); }
catch ( RuntimeException $expected ) { verify_position( ! ( $expected instanceof LogicException ), 'Read error must not imply zero payments' ); }
$free = array( 'pricing_mode' => 'ZERO', 'options' => array() );
verify_position( MI_Portal::is_free_configuration( $free ), 'Explicit free event' );
verify_position( MI_Portal::is_free_configuration( array_replace( $free, array( 'options' => array( array( 'price_cents' => 100 ) ) ) ) ), 'Explicit declaration remains authoritative regardless of stored amounts' );
verify_position( ! MI_Portal::is_free_configuration( array( 'pricing_mode' => 'FIXED', 'fixed_price_cents' => 0 ) ), 'Zero amount without declaration does not mean free' );
verify_position( ! MI_Portal::is_free_configuration( array( 'pricing_mode' => 'FIXED', 'fixed_price_cents' => 100 ) ), 'Paid ticket means no free badge' );
verify_position( ! MI_Portal::is_free_configuration( array() ), 'Incomplete draft must not claim free' );
verify_position( ! MI_Portal::is_free_configuration( array( 'pricing_mode' => 'CALCULATED', 'ticket_types' => array( array( 'price_cents' => 0 ), array( 'price_cents' => 100 ) ) ) ), 'Mixed ticket prices are not free' );
echo "Situazione economica corrente, lettura in blocco e gratuità verificati.\n";

class MI_Access { static function can_access_event( $id ) { return 42 === $id; } }
class MI_Portal_Management { static function allowed() { return true; } }
class MI_Shortcode { static function url_iscrizione( $id ) { return 'https://example.invalid/registration'; } }
class WP_Error { function __construct( $code, $message ) { throw new RuntimeException( $message ); } }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-service.php';
class SummaryDatabase {
	public $prefix = 'wp_', $last_error = '', $scope = 'ONE', $status = 'CONFIRMED';
	function prepare( $sql, ...$args ) { return $sql; }
	function get_results( $sql, $format ) {
		if ( str_contains( $sql, 'SELECT * FROM wp_mi_registrations' ) ) return array( array( 'id'=>1, 'order_code'=>'TEST', 'buyer_first_name'=>'Persona', 'buyer_last_name'=>'Prova', 'status'=>$this->status, 'total_cents'=>10000, 'economic_mode'=>'FULL_PAYMENT', 'snapshot_json'=>json_encode( array( 'event'=>array( 'participant_extra_scope'=>$this->scope, 'participant_fields'=>array( array( 'key'=>'phone', 'required'=>true ) ) ) ) ) ) );
		if ( str_contains( $sql, 'SELECT p.id,p.registration_id' ) ) return array( array( 'id'=>1, 'registration_id'=>1, 'status'=>'CANCELLED', 'extra_json'=>'{}', 'room_code'=>'' ), array( 'id'=>2, 'registration_id'=>1, 'status'=>'ACTIVE', 'extra_json'=>'{}', 'room_code'=>'' ) );
		return array();
	}
}
$wpdb = new SummaryDatabase();
$summary = MI_Management_Service::summary( 42 );
verify_position( $summary['items'][0]['participants'] === 1 && $summary['items'][0]['missing'] === 0, 'Cancelled first participant does not transfer required fields to second person' );
verify_position( $summary['items'][0]['collectible'], 'Confirmed unpaid booking is collectible' );
$wpdb->scope = 'ALL'; $wpdb->status = 'WAITLISTED';
$summary = MI_Management_Service::summary( 42 );
verify_position( $summary['items'][0]['missing'] === 1 && ! $summary['items'][0]['collectible'], 'All-person requirements apply, but waitlist does not enter collection total' );
echo "Conteggi individuali, obblighi ONE/ALL e perimetro incassi verificati.\n";
