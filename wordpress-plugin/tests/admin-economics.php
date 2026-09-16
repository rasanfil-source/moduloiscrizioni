<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-event-post-type.php';
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
function check( $actual, $expected ) {
	if ( $actual !== $expected ) throw new RuntimeException( var_export( array( $actual, $expected ), true ) );
}
$parser = new ReflectionMethod( MI_Event_Post_Type::class, 'price_cents' );
$parser->setAccessible( true );
foreach ( array( '12,50' => 1250, '12.50' => 1250, '12,5' => 1250, '0' => 0, '-5' => 0, '12abc' => 0, '1.234' => 0, '1e3' => 0 ) as $raw => $expected ) check( $parser->invoke( null, $raw ), $expected );
check( $parser->invoke( null, array() ), 0 );
$wpdb = new class {
	public $prefix = 'wp_';
	public $last_error = '';
	public $queries = 0;
	public function get_results( $sql, $format ) {
		$this->queries++;
		if ( str_contains( $sql, 'mi_registrations' ) ) return array(
			array( 'id' => 1, 'total_cents' => 10000, 'initial_due_cents' => 3000, 'economic_mode' => 'DEPOSIT_BALANCE', 'snapshot_json' => '{}' ),
			array( 'id' => 2, 'total_cents' => 10000, 'initial_due_cents' => 3000, 'economic_mode' => 'DEPOSIT_BALANCE', 'snapshot_json' => '{}' ),
		);
		if ( str_contains( $sql, 'mi_participants' ) ) return array(
			array( 'id'=>11, 'registration_id'=>1, 'ticket_type_code'=>'a', 'first_name'=>'A', 'last_name'=>'Uno', 'options_json'=>'[]', 'status'=>'ACTIVE', 'deposit_due_cents'=>1500 ),
			array( 'id'=>12, 'registration_id'=>1, 'ticket_type_code'=>'b', 'first_name'=>'B', 'last_name'=>'Due', 'options_json'=>'[]', 'status'=>'ACTIVE', 'deposit_due_cents'=>1500 ),
			array( 'id'=>21, 'registration_id'=>2, 'ticket_type_code'=>'c', 'first_name'=>'C', 'last_name'=>'Tre', 'options_json'=>'[]', 'status'=>'ACTIVE', 'deposit_due_cents'=>3000 ),
		);
		if ( str_contains( $sql, 'mi_registration_items' ) ) return array(
			array( 'registration_id'=>1, 'ticket_type_code'=>'a', 'unit_price_cents'=>5000 ), array( 'registration_id'=>1, 'ticket_type_code'=>'b', 'unit_price_cents'=>5000 ), array( 'registration_id'=>2, 'ticket_type_code'=>'c', 'unit_price_cents'=>10000 ),
		);
		return array(
			array( 'registration_id'=>1, 'transaction_kind'=>'PAYMENT', 'amount_cents'=>7000, 'participant_allocations_json'=>'[{"participant_id":11,"amount_cents":7000}]' ),
			array( 'registration_id'=>1, 'transaction_kind'=>'PAYMENT', 'amount_cents'=>1000, 'participant_allocations_json'=>'[{"participant_id":12,"amount_cents":1000}]' ),
			array( 'registration_id'=>2, 'transaction_kind'=>'PAYMENT', 'amount_cents'=>10000, 'participant_allocations_json'=>'[{"participant_id":21,"amount_cents":10000}]' ),
		);
	}
};
$registration = array( 'id' => 1, 'total_cents' => 10000, 'initial_due_cents' => 3000, 'balance_cents' => 7000, 'economic_mode' => 'DEPOSIT_BALANCE' );
$other = array_merge( $registration, array( 'id' => 2 ) );
$positions = MI_Payment_Ledger::positions( array( $registration, $registration, $other ) );
check( $positions[1]['balance'], 2000 );
check( $positions[1]['effective_balance'], 4000 );
check( $positions[1]['individual_credit'], 2000 );
check( $positions[2]['balance'], 0 );
check( $wpdb->queries, 4 );
check( MI_Payment_Ledger::position( $registration, 6000 )['balance'], 4000 );
echo "Admin economics OK\n";
