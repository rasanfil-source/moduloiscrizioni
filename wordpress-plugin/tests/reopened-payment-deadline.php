<?php
define( 'ABSPATH', __DIR__ );
function absint( $value ) { return abs( (int) $value ); }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['event_meta'][$id][$key] ?? ''; }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';
$now = strtotime( '2026-09-25 10:00:00 UTC' );
$GLOBALS['event_meta'] = array( 42 => array( '_mi_waitlist_offer_hours' => 1 ) );
function deadline_check( $registration, $hours, $message ) {
	global $now;
	$actual = MI_Registration_Service::reopened_payment_deadline( $registration, $now );
	if ( gmdate( 'Y-m-d H:i:s', $now + $hours * 3600 ) !== $actual ) throw new RuntimeException( $message . ': ' . $actual );
}
$legacy = array( 'event_id' => 42, 'snapshot_json' => json_encode( array( 'event' => array( 'waitlist_offer_hours' => 2 ) ) ) );
deadline_check( $legacy, 48, 'Legacy waitlist snapshot and event must not shorten payment window' );
$GLOBALS['event_meta'][42]['_mi_reopened_payment_hours'] = 72;
deadline_check( $legacy, 72, 'Legacy booking must use dedicated event setting' );
$current = $legacy;
$current['snapshot_json'] = json_encode( array( 'event' => array( 'reopened_payment_hours' => 24, 'waitlist_offer_hours' => 1 ) ) );
deadline_check( $current, 24, 'Dedicated snapshot must take precedence' );
foreach ( array( null, '', 'invalid', '2026-09-24 10:00:00', '2026-09-25 10:00:00' ) as $deadline ) {
	deadline_check( $current + array( 'payment_deadline_at' => $deadline ), 24, 'Missing or expired deadline must reopen' );
}
deadline_check( $current + array( 'payment_deadline_at' => '2026-09-25 11:00:00' ), 1, 'Future deadline must be preserved even when shorter than the window' );
foreach ( array( 1 => 1, 168 => 168, 999 => 168 ) as $hours => $expected ) {
	deadline_check( array( 'snapshot_json' => json_encode( array( 'event' => array( 'reopened_payment_hours' => $hours ) ) ) ), $expected, 'Window bounds' );
}
$GLOBALS['event_meta'][42]['_mi_waitlist_offer_hours'] = 168;
deadline_check( $current, 24, 'Changing waitlist settings must not affect reopened payment' );
echo "PASS dedicated payment window, legacy fallback, snapshot precedence, bounds and future deadlines.\n";
