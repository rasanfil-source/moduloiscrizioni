<?php
define( 'ABSPATH', __DIR__ );
function wp_timezone() { return new DateTimeZone( $GLOBALS['test_timezone'] ?? 'Europe/Rome' ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
class MI_Access { static function event_ids() { return 'ALL'; } }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-admin.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
function check_date_filter( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
$filter = new ReflectionMethod( MI_Admin::class, 'payment_where' );
foreach ( array(
    array( 'Europe/Rome', '2026-09-28', '2026-09-27 22:00:00', '2026-09-28 22:00:00' ),
    array( 'Europe/Rome', '2026-01-15', '2026-01-14 23:00:00', '2026-01-15 23:00:00' ),
    array( 'Europe/Rome', '2026-03-29', '2026-03-28 23:00:00', '2026-03-29 22:00:00' ),
    array( 'Europe/Rome', '2026-10-25', '2026-10-24 22:00:00', '2026-10-25 23:00:00' ),
    array( 'America/New_York', '2026-09-28', '2026-09-28 04:00:00', '2026-09-29 04:00:00' ),
    array( 'UTC', '2026-09-28', '2026-09-28 00:00:00', '2026-09-29 00:00:00' ),
) as [ $timezone, $day, $start, $end ] ) {
    $GLOBALS['test_timezone'] = $timezone;
    [ $sql, $args ] = $filter->invoke( null, 0, '', '', $day, $day );
    check_date_filter( $args === array( $start, $end ) && str_contains( $sql, 'p.effective_at < %s' ), 'Wrong local day boundaries: ' . $timezone . ' ' . $day );
    $payment = MI_Payment_Ledger::normalize( array( 'importo' => '10', 'tipo' => 'INCASSO', 'metodo' => 'CONTANTE', 'data' => $day, 'request_id' => 'wp_1_12345678-1234-4234-8234-123456789abc' ) );
    check_date_filter( $payment['effective_at'] >= $args[0] && $payment['effective_at'] < $args[1], 'Payment excluded from its local date' );
    $previous = ( new DateTimeImmutable( $start, new DateTimeZone( 'UTC' ) ) )->modify( '-1 second' )->format( 'Y-m-d H:i:s' );
    check_date_filter( $previous < $args[0] && ! ( $end < $args[1] ), 'Adjacent days must be excluded' );
}
$GLOBALS['test_timezone'] = 'Europe/Rome';
check_date_filter( $filter->invoke( null, 0, '', '', '2026-09-28', '' )[1] === array( '2026-09-27 22:00:00' ), 'Open-ended start' );
check_date_filter( $filter->invoke( null, 0, '', '', '', '2026-09-28' )[1] === array( '2026-09-28 22:00:00' ), 'Open-ended end' );
foreach ( array( '2026-02-30', 'invalid', '2026-9-28' ) as $invalid ) check_date_filter( $filter->invoke( null, 0, '', '', $invalid, '' )[0] === 'WHERE 1 = 0', 'Invalid date accepted' );
echo "PASS: payment local dates, DST boundaries, open-ended and invalid filters.\n";
