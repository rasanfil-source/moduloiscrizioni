<?php
// Verifica la classificazione applicativa usando un orologio WordPress fisso.
define('ABSPATH', __DIR__);
function wp_timezone() { return new DateTimeZone('Europe/Rome'); }
function current_time($type, $gmt = false) { return $GLOBALS['event_test_now']; }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-portal.php';
function check_event_day($now, $event, $expected) {
    $GLOBALS['event_test_now'] = (new DateTimeImmutable($now, wp_timezone()))->getTimestamp();
    if (MI_Portal::is_past_event($event) !== $expected) throw new RuntimeException("Unexpected event classification: $now / $event");
}
check_event_day('2026-10-10 14:00:00', '2026-10-10T15:00', false);
check_event_day('2026-10-10 15:00:01', '2026-10-10T15:00', false);
check_event_day('2026-10-10 23:59:59', '2026-10-10T15:00', false);
check_event_day('2026-10-11 00:00:00', '2026-10-10T15:00', true);
check_event_day('2026-10-11 00:00:00', '2026-10-11T00:00', false);
check_event_day('2026-10-11 00:00:00', '2026-10-12T15:00', false);
check_event_day('2026-10-11 00:00:00', '', false);
check_event_day('2026-10-11 00:00:00', 'invalid', false);
// Midnight follows local calendar days even around daylight-saving changes.
check_event_day('2026-03-29 23:59:59', '2026-03-29T00:30', false);
check_event_day('2026-03-30 00:00:00', '2026-03-29T00:30', true);
check_event_day('2026-10-25 23:59:59', '2026-10-25T00:30', false);
check_event_day('2026-10-26 00:00:00', '2026-10-25T00:30', true);
$deadline = new ReflectionMethod(MI_Portal::class, 'registration_deadline_passed');
$GLOBALS['event_test_now'] = (new DateTimeImmutable('2026-10-10 15:00:01', wp_timezone()))->getTimestamp();
if (!$deadline->invoke(null, '2026-10-10T15:00')) throw new RuntimeException('Registration deadline must still respect its hour.');
if ($deadline->invoke(null, '2026-10-10T16:00')) throw new RuntimeException('Registration deadline closed too early.');
echo "PASS: event remains active until local midnight; DST and registration deadlines preserved.\n";
