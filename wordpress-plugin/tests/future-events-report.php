<?php
define('ABSPATH', __DIR__);
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-service.php';
$cases = array(
    array(array('marketing_consent_id' => 'marketing-42'), 'Sì'),
    array(array('snapshot_json' => json_encode(array('consents' => array('marketing' => array('accepted' => true))))), 'Sì'),
    array(array('snapshot_json' => json_encode(array('event' => array('marketing_enabled' => true)))), 'No'),
    array(array('snapshot_json' => json_encode(array('event' => array('marketing_enabled' => false)))), 'Non richiesto'),
    array(array(), 'Non rilevato'),
);
foreach ($cases as [$registration, $expected]) {
    if (MI_Management_Service::future_events_response($registration) !== $expected) throw new RuntimeException('Risposta errata: ' . $expected);
}
echo "PASS: future-event report preserves accepted, declined, unrequested and unknown responses.\n";
