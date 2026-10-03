<?php
// Synthetic reads; calculations, public status, payment ledger and projection are real.
define('ABSPATH', __DIR__); define('ARRAY_A', 'ARRAY_A');
function absint($value) { return abs((int)$value); }
function wp_json_encode($value, ...$args) { return json_encode($value, ...$args); }
function sanitize_text_field($value) { return trim((string)$value); }
function sanitize_email($value) { return $value; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function sanitize_key($value) { return strtolower($value); }
function wp_salt($value) { return 'synthetic-audit-only'; }
function get_the_title($id) { return 'Evento sintetico'; }
function wp_date($format) { return gmdate($format); }
function wp_cache_delete(...$args) {}
function get_post($id) { return (object)['post_type' => 'event', 'post_title' => 'Evento sintetico']; }
function get_post_meta($id, $key, ...$args) { return $GLOBALS['audit_meta'][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['audit_meta'][$key] = $value; }
class WP_Error { public function __construct(public $code, public $message, public $data = null) {} }
class MI_Access { static function can_access_event($id) { return $id === 42; } }
class MI_Event_Post_Type { const EVENT_TYPE = 'event'; }
class MI_Field_Schema {
    static function workspace_event_schema($id) { return ['pricing' => 'FIXED', 'options' => [], 'fields' => []]; }
    static function resolved_operational_profile($id) { return 'COMPLETO'; }
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-workspace-client.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-event-projection.php';
class EconomicsAuditDatabase {
    public $prefix = 'wp_', $last_error = '', $order, $people, $items, $payments;
    function prepare($sql, ...$args) { return $sql; }
    function get_row($sql, $mode) { return $this->order; }
    function get_var($sql) { return 1; }
    function get_results($sql, $mode) {
        if (str_contains($sql, 'mi_registration_events') || str_contains($sql, 'mi_rooms')) return [];
        if (str_contains($sql, 'mi_participants')) return $this->people;
        if (str_contains($sql, 'mi_registration_items')) return $this->items;
        if (str_contains($sql, 'mi_payments')) return $this->payments;
        if (str_contains($sql, 'mi_registrations')) return [$this->order];
        throw new RuntimeException('Unexpected query: ' . $sql);
    }
}
$wpdb = new EconomicsAuditDatabase();
$wpdb->order = ['id'=>1, 'event_id'=>42, 'order_code'=>'AUDIT1', 'status'=>'PENDING_PAYMENT', 'buyer_first_name'=>'Ada', 'buyer_last_name'=>'Esempio', 'buyer_email'=>'audit@example.invalid', 'buyer_phone'=>'', 'total_qty'=>2, 'total_cents'=>20000, 'initial_due_cents'=>6000, 'balance_cents'=>14000, 'economic_mode'=>'DEPOSIT_BALANCE', 'snapshot_json'=>json_encode(['event'=>['deposit_mode'=>'FIXED','deposit_fixed_cents'=>3000]]), 'order_options_json'=>'[]', 'payment_methods_json'=>'["BANK_TRANSFER"]', 'payment_deadline_at'=>'2099-01-01 10:00:00', 'workspace_revision'=>1, 'workspace_status'=>'PENDING'];
foreach ([1,2] as $id) $wpdb->people[] = ['id'=>$id, 'registration_id'=>1, 'ticket_index'=>$id, 'ticket_type_code'=>'base', 'first_name'=>$id===1?'Ada':'Luca', 'last_name'=>'Esempio', 'status'=>'ACTIVE', 'options_json'=>'[]', 'extra_json'=>'{}', 'room_code'=>'', 'deposit_due_cents'=>3000];
$wpdb->items = [['registration_id'=>1, 'ticket_type_code'=>'base', 'unit_price_cents'=>10000]];
$wpdb->payments = [['id'=>1, 'registration_id'=>1, 'transaction_kind'=>'PAYMENT', 'amount_cents'=>20000, 'participant_allocations_json'=>'[{"participant_id":1,"amount_cents":20000}]', 'effective_at'=>'2026-09-29 10:00:00', 'movement_kind'=>'INCASSO', 'payment_source'=>'CASH', 'external_reference'=>'', 'operator_label'=>'Test', 'administrative_note'=>'']];
$outputs = [];
foreach (['consistent'=>3000, 'inconsistent'=>2000] as $label=>$deposit) {
    $wpdb->people[0]['deposit_due_cents'] = $deposit;
    $position = MI_Payment_People::calculate($wpdb->order, $wpdb->people, $wpdb->items, $wpdb->payments);
    $status = MI_Registration_Service::public_status('AUDIT1', 'audit@example.invalid');
    $ledger = MI_Payment_Ledger::positions([$wpdb->order])[1];
    $detail = MI_Payment_Ledger::detail(1);
    $projection = MI_Event_Projection::snapshot(42)['projection'];
    $outputs[$label] = ['quotes_known'=>$position['quotes_known'], 'payments_known'=>$position['payments_known'], 'deposits_known'=>$position['deposits_known'], 'individual_due'=>array_sum(array_column($position['people'],'balance')), 'public_due'=>$status['balance_cents'], 'public_label'=>$status['payment_status'], 'admin_due'=>$ledger['effective_balance'], 'payment_detail_due'=>$detail['saldo']['residuo'], 'projection_order_due'=>$projection['registrations'][0]['saldo_centesimi'], 'projection_person_due'=>array_column($projection['participants'],'saldo_centesimi')];
}
foreach ($outputs as $case => $result) {
    if (!$result['quotes_known'] || !$result['payments_known'] || $result['deposits_known'] !== ($case === 'consistent')) throw new RuntimeException('Invalid fixture: ' . $case);
    foreach (['individual_due', 'public_due', 'admin_due', 'payment_detail_due', 'projection_order_due'] as $field) {
        if ($result[$field] !== 10000) throw new RuntimeException($case . ': lost individual debt in ' . $field);
    }
    if ($result['projection_person_due'] !== [0, 10000]) throw new RuntimeException('Projection lost known participant amounts');
}
if ($outputs['inconsistent']['public_label'] !== 'Caparra da verificare con la segreteria') throw new RuntimeException('Inconsistent deposit presented as covered');
echo "PASS: public status, administration, ledger detail and Google projection preserve known individual debts with inconsistent deposits.\n";
