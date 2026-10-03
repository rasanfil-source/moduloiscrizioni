<?php
define('ABSPATH', __DIR__);
class WP_Error { function __construct(public $code, public $message, public $data = null) {} function get_error_message() { return $this->message; } }
function sanitize_text_field($v) { return trim(strip_tags($v)); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($v)); }
function wp_timezone() { return new DateTimeZone('Europe/Rome'); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-field-schema.php';
function check_date_field($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$fields = MI_Field_Schema::sanitize_custom_fields([['key' => 'arrivo', 'label' => 'Data di arrivo', 'type' => 'date', 'required' => true]]);
$legacy = $fields;
unset($legacy[0]['date_rule']);
$today = new DateTimeImmutable('today', wp_timezone());
foreach ([$fields, $legacy] as $definitions) {
    foreach (['-1 day', '+10 days'] as $relative) {
        $value = $today->modify($relative)->format('Y-m-d');
        $result = MI_Field_Schema::validate_answers(['custom_arrivo' => $value], $definitions);
        check_date_field(is_array($result) && $result['custom_arrivo'] === $value, 'Valid custom date rejected: ' . $relative);
    }
    foreach (['', '2030-02-30', '2030-13-01', '2030-1-01', 'not-a-date'] as $value) {
        check_date_field(MI_Field_Schema::validate_answers(['custom_arrivo' => $value], $definitions) instanceof WP_Error, 'Invalid or missing custom date accepted');
    }
}
$catalog = MI_Field_Schema::catalog();
foreach (['birth_date', 'document_issue_date'] as $key) {
    check_date_field(MI_Field_Schema::validate_answers([$key => $today->modify('+1 day')->format('Y-m-d')], [$catalog[$key]]) instanceof WP_Error, 'Future personal date accepted');
    check_date_field(is_array(MI_Field_Schema::validate_answers([$key => $today->modify('-1 day')->format('Y-m-d')], [$catalog[$key]])), 'Past personal date rejected');
}
check_date_field(MI_Field_Schema::validate_answers(['document_expiry' => $today->modify('-1 day')->format('Y-m-d')], [$catalog['document_expiry']]) instanceof WP_Error, 'Expired document accepted');
check_date_field(is_array(MI_Field_Schema::validate_answers(['document_expiry' => $today->modify('+10 days')->format('Y-m-d')], [$catalog['document_expiry']])), 'Future document expiry rejected');
echo "PASS: custom dates accept past/future on current and legacy schemas; invalid dates, birth and document limits remain protected.\n";

// Exercise the management adapter and its real save method, shared with sheet edits.
function sanitize_textarea_field($v) { return trim(strip_tags($v)); }
function get_post_meta(...$args) { return ''; }
function wp_json_encode($v) { return json_encode($v); }
function is_wp_error($v) { return $v instanceof WP_Error; }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-service.php';
$wpdb = new class {
    public $prefix = 'wp_', $writes = [];
    function update($table, $data, $where) { $this->writes[] = $data; return 1; }
};
$adapt = new ReflectionMethod(MI_Management_Service::class, 'definitions');
$save = new ReflectionMethod(MI_Management_Service::class, 'save_participant');
$future = $today->modify('+10 days')->format('Y-m-d');
$past = $today->modify('-10 days')->format('Y-m-d');
$cases = [
    [$legacy[0], $future, true], [$fields[0], $future, true],
    [$legacy[0] + ['date_rule'=>'past'], $future, false],
    [$legacy[0], '2030-02-30', false],
    [$catalog['birth_date'], $future, false], [$catalog['birth_date'], $past, true],
    [$catalog['document_expiry'], $past, false], [$catalog['document_expiry'], $future, true],
];
foreach ($cases as [$definition, $value, $expected]) {
    $key = $definition['key'];
    $registration = ['event_id'=>42, 'snapshot_json'=>json_encode(['event'=>['participant_fields'=>[$definition]]])];
    $booking = ['status'=>'CONFIRMED', 'fields'=>array_values($adapt->invoke(null, $registration)), 'participants'=>[
        ['id'=>7, 'number'=>1, 'status'=>'ACTIVE', 'fields'=>[$key=>''], 'room'=>'']
    ]];
    $before = count($wpdb->writes); $saved = false;
    try {
        $save->invoke(null, $booking, ['number'=>1, 'first_name'=>'Persona', 'last_name'=>'Sintetica', 'fields'=>[$key=>$value]]);
        $saved = true;
    } catch (InvalidArgumentException $error) {}
    check_date_field($saved === $expected, 'Management date validation mismatch: ' . $key . ' ' . $value);
    check_date_field(count($wpdb->writes) === $before + (int)$expected, 'Rejected date changed the participant');
    if ($expected) check_date_field(json_decode(end($wpdb->writes)['extra_json'], true)[$key] === $value, 'Date not persisted');
}
echo "PASS: management and sheet participant saves preserve legacy custom dates and explicit date constraints.\n";
