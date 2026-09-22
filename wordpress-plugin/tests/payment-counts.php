<?php
define( 'ABSPATH', __DIR__ );
function wp_json_encode( $value ) { return json_encode( $value ); }
function remove_accents( $value ) { return $value; }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-service.php';
function check_payment_counts( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }

$counts = MI_Management_Service::payment_counts( array(
	array( 'status' => 'PENDING_PAYMENT', 'economics_known' => true, 'balance' => 0, 'deposit_covered' => true ),
	array( 'status' => 'PENDING_PAYMENT', 'economics_known' => true, 'balance' => 10000, 'deposit_covered' => false ),
	array( 'status' => 'CONFIRMED', 'economics_known' => false, 'balance' => 0, 'deposit_covered' => false ),
	array( 'status' => 'CANCELLED', 'economics_known' => true, 'balance' => 0, 'deposit_covered' => true ),
) );
check_payment_counts( 1 === $counts['settled'], 'Il saldo di una persona è stato compensato dal debito di un altra.' );
check_payment_counts( 1 === $counts['deposit_covered'], 'La caparra individuale non è stata conteggiata.' );
check_payment_counts( 1 === $counts['unknown'], 'Lo storico non attribuito non è stato segnalato.' );
echo "PASS: conteggi individuali di saldo e caparra.\n";
