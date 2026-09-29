<?php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-people.php';
function check_deposit( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
$registration = array( 'total_cents' => 20000, 'initial_due_cents' => 10000, 'economic_mode' => 'DEPOSIT_BALANCE', 'snapshot_json' => '{}' );
$people = array();
foreach ( array( 1, 2 ) as $id ) $people[] = array( 'id' => $id, 'ticket_type_code' => 'base', 'first_name' => 'Test', 'last_name' => (string) $id, 'status' => 'ACTIVE', 'options_json' => '[]', 'deposit_due_cents' => 3000 );
$items = array( array( 'ticket_type_code' => 'base', 'unit_price_cents' => 10000 ) );
$position = MI_Payment_People::calculate( $registration, $people, $items, array() );
$summary = MI_Payment_People::summary( $position );
check_deposit( ! $position['ready'] && $position['quotes_known'] && ! $position['deposits_known'], 'Deposit inconsistency must not invalidate known totals' );
check_deposit( ! $summary['known'] && $summary['totals_known'] && ! $summary['deposits_known'] && null === $summary['deposit_due'] && null === $summary['deposit_missing'], 'Invalid deposits presented as known or zero' );
check_deposit( 20000 === $summary['total'] && 20000 === $summary['balance'], 'Valid totals lost' );
check_deposit( null === MI_Payment_People::covered( $position, 'DEPOSIT_BALANCE' ), 'Invalid deposit coverage inferred' );
check_deposit( false === MI_Payment_People::covered( $position, 'FULL_PAYMENT' ), 'Full payment coverage must remain knowable' );
try { MI_Payment_People::plan( $position, array( 1 ), 'DEPOSIT', 3000 ); throw new RuntimeException( 'Invalid payment allowed' ); } catch ( InvalidArgumentException $expected ) {}
foreach ( $people as &$person ) $person['deposit_due_cents'] = 5000;
unset( $person );
$position = MI_Payment_People::calculate( $registration, $people, $items, array() );
$summary = MI_Payment_People::summary( $position );
check_deposit( $position['ready'] && $position['deposits_known'] && $summary['known'] && 10000 === $summary['deposit_due'], 'Valid deposits broken' );
check_deposit( count( MI_Payment_People::plan( $position, array( 1, 2 ), 'DEPOSIT', 10000 ) ) === 2, 'Valid payment rejected' );
echo "PASS: inconsistent deposits stay unknown while totals remain valid; payments stay protected.\n";
