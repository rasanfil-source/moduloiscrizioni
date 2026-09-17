<?php
define( 'ABSPATH', __DIR__ );
function absint( $value ) { return abs( (int) $value ); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-service.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';
function check_percentage( $value, $message ) { if ( ! $value ) throw new RuntimeException( $message ); }
$method = new ReflectionMethod( MI_Management_Service::class, 'percentage_deposits' );
$registration = array( 'economic_mode' => 'DEPOSIT_BALANCE', 'snapshot_json' => json_encode( array( 'event' => array( 'deposit_mode' => 'PERCENTAGE', 'deposit_percentage' => 30 ) ) ) );
$position = array( 'quotes_known' => true, 'people' => array( array( 'id' => 1, 'total' => 30000 ), array( 'id' => 2, 'total' => 40000 ) ) );
$deposits = $method->invoke( null, $registration, $position, array( 2 => -10000 ), 60000 );
check_percentage( $deposits === array( 1 => 9000, 2 => 9000 ), 'La caparra percentuale non è stata ricalcolata sui nuovi totali personali.' );
$position['people'] = array( array( 'id' => 1, 'total' => 33333 ), array( 'id' => 2, 'total' => 33334 ) );
$deposits = $method->invoke( null, $registration, $position, array(), 66667 );
check_percentage( array_sum( $deposits ) === 20000 && $deposits[1] === 10000 && $deposits[2] === 10000, 'La distribuzione dei centesimi non coincide con la caparra complessiva.' );
$registration['snapshot_json'] = json_encode( array( 'event' => array( 'deposit_mode' => 'FIXED', 'deposit_fixed_cents' => 10000 ) ) );
check_percentage( null === $method->invoke( null, $registration, $position, array(), 66667 ), 'Le caparre fisse non devono essere modificate dal ricalcolo percentuale.' );
$now = strtotime( '2026-09-17 10:00:00 UTC' );
$reopened = MI_Registration_Service::reopened_payment_deadline( array( 'event_id' => 42, 'payment_deadline_at' => '2026-09-16 09:00:00', 'snapshot_json' => json_encode( array( 'event' => array( 'waitlist_offer_hours' => 24 ) ) ) ), $now );
check_percentage( '2026-09-18 10:00:00' === $reopened, 'Una scadenza trascorsa non è stata sostituita con una nuova finestra.' );
$future = MI_Registration_Service::reopened_payment_deadline( array( 'event_id' => 42, 'payment_deadline_at' => '2026-09-20 09:00:00', 'snapshot_json' => '{}' ), $now );
check_percentage( '2026-09-20 09:00:00' === $future, 'Una scadenza ancora valida è stata modificata.' );
echo "PASS: ricalcolo percentuale esatto e caparra fissa invariata.\n";
