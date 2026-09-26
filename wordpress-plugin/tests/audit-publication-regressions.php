<?php
// Local diagnostics: real application methods, isolated WordPress/database doubles.
$fixture = __DIR__ . '/php-behavior.php';
$prefix = explode('$event = array(', file_get_contents($fixture), 2)[0];
eval('?>' . str_replace('__DIR__', var_export(dirname($fixture), true), $prefix));
define('MINUTE_IN_SECONDS', 60);
define('ARRAY_A', 'ARRAY_A');
function wp_unslash($v) { return $v; }
function wp_verify_nonce($v, $action) { return true; }
function get_current_user_id() { return 1; }
function get_privacy_policy_url() { return 'https://example.invalid/privacy'; }
function get_post_type($id) { return MI_Event_Post_Type::ACTIVITY_TYPE; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['audit_meta'][$id][$key] ?? ''; }
function delete_post_meta($id, $key) { unset($GLOBALS['audit_meta'][$id][$key]); }
function update_post_meta($id, $key, $value) { $GLOBALS['audit_meta'][$id][$key] = $value; }
function set_transient($key, $value, $ttl) { $GLOBALS['audit_transients'][$key] = $value; }
function wp_json_encode($v) { return json_encode($v); }
class MI_Portal_Management { public static function allowed() { return true; } }
class MI_Access { public static function can_access_event($id) { return true; } }
foreach (['event-post-type', 'admin', 'portal', 'management-service', 'attendance-report'] as $name) {
    require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-' . $name . '.php';
}
function call_audit($class, $method, array $args) {
    return (new ReflectionMethod($class, $method))->invokeArgs(null, $args);
}
function report_audit($name, $value) { echo $name . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n"; }
function rejects_audit($class, $method, array $args) {
    try { return call_audit($class, $method, $args); }
    catch (InvalidArgumentException $e) { return $e->getMessage(); }
}
$dates = ['opens_at' => '', 'closes_at' => '2099-01-01T10:00'];
$state = MI_Registration_Service::registration_time_state($dates);
expect($state === 'OPEN', 'Opening omitted must currently be OPEN');
report_audit('public_blank_opening', $state);
$_POST = ['mi_event_nonce' => 'test', 'mi_activity_id' => 5, 'mi_registration_opens_at' => '',
    'mi_registration_closes_at' => $dates['closes_at'], 'mi_ticket_code' => ['standard'],
    'mi_ticket_name' => ['Standard'], 'mi_ticket_price' => ['0'],
    'mi_economic_mode' => 'REGISTRATION_ONLY', 'mi_pricing_mode' => 'ZERO'];
$post = ['post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'publish'];
$GLOBALS['audit_meta'][42]['_mi_privacy_policy_version'] = '2026-09';
$blank = MI_Admin::guard_publication($post, ['ID' => 42]);
$_POST['mi_registration_opens_at'] = '2026-01-01T00:00';
$dated = MI_Admin::guard_publication($post, ['ID' => 42]);
expect($blank['post_status'] === 'publish' && $dated['post_status'] === 'publish', 'Admin publication reproduction changed');
report_audit('wp_admin_opening', ['blank' => $blank['post_status'], 'filled' => $dated['post_status']]);
$GLOBALS['audit_meta'][42]['_mi_registration_opens_at'] = '2098-01-01T00:00';
call_audit('MI_Portal', 'save_date', [42, '_mi_registration_opens_at', '']);
expect(get_post_meta(42, '_mi_registration_opens_at') === '', 'Portal now clears blank date');
report_audit('portal_after_clearing', get_post_meta(42, '_mi_registration_opens_at'));
$_POST['mi_economic_mode'] = 'PRICE_ONLY'; $_POST['mi_pricing_mode'] = 'CALCULATED';
$_POST['mi_ticket_price'] = ['0,50'];
$comma = MI_Admin::guard_publication($post, ['ID' => 42]);
$_POST['mi_ticket_price'] = ['0.50'];
$dot = MI_Admin::guard_publication($post, ['ID' => 42]);
report_audit('raw_post_price_format', ['comma' => $comma['post_status'], 'dot' => $dot['post_status']]);
expect($comma['post_status'] === 'publish' && $dot['post_status'] === 'publish', 'Decimal separator publication mismatch');
foreach (['2099-02-30T10:00', '', '2020-01-01T10:00'] as $invalid) {
    $_POST['mi_registration_closes_at'] = $invalid;
    expect(MI_Admin::guard_publication($post, ['ID' => 42])['post_status'] === 'draft', 'Invalid or past closing published');
}
echo "PASS: optional opening, clear existing date, strict closing and comma prices.\n";
