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
		return array( array( 'registration_id' => 1, 'paid' => 8000 ), array( 'registration_id' => 2, 'paid' => 10000 ) );
	}
};
$registration = array( 'id' => 1, 'total_cents' => 10000, 'initial_due_cents' => 3000, 'balance_cents' => 7000, 'economic_mode' => 'DEPOSIT_BALANCE' );
$other = array_merge( $registration, array( 'id' => 2 ) );
$positions = MI_Payment_Ledger::positions( array( $registration, $registration, $other ) );
check( $positions[1]['balance'], 2000 );
check( $positions[2]['balance'], 0 );
check( $wpdb->queries, 1 );
check( MI_Payment_Ledger::position( $registration, 6000 )['balance'], 4000 );
echo "Admin economics OK\n";
