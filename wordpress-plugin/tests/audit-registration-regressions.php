<?php
// Reuse only fixture declarations/data; all tested service methods are real.
$testDir = __DIR__;
$fixture = file_get_contents($testDir . '/registration-failures.php');
$fixture = explode('$failures=[];', $fixture, 2)[0];
$fixture = str_replace('__DIR__', var_export($testDir, true), $fixture);
$fixture = str_replace('class MI_Portal {', 'class MI_Portal { static function status_url(...$args) { return "https://example.invalid/status"; }', $fixture);
eval('?>' . $fixture);
function wp_date($format, $timestamp, $zone = null) { return (new DateTimeImmutable('@' . $timestamp))->setTimezone($zone ?: wp_timezone())->format($format); }

class AuditRegistrationDatabase extends FaultDatabase {
    public $people = [], $items = [], $payments = [], $selectedDeadline = '', $deadlineAtLock = '';
    function get_col($sql) {
        $this->selectedDeadline = $this->registration['expires_at'];
        // A partial payment/price correction commits after the cron SELECT.
        $this->registration['expires_at'] = gmdate('Y-m-d H:i:s', time() + 48 * 3600);
        $this->registration['payment_deadline_at'] = $this->registration['expires_at'];
        $this->payments = [['transaction_kind' => 'PAYMENT', 'amount_cents' => 10000, 'participant_allocations_json' => '[{"participant_id":1,"amount_cents":10000}]']];
        return [1];
    }
    function get_row($sql, $mode) {
        if (str_contains($sql, 'mi_registrations') && str_contains($sql, 'FOR UPDATE')) $this->deadlineAtLock = $this->registration['expires_at'] ?? '';
        return parent::get_row($sql, $mode);
    }
    function get_results($sql, $mode) {
        $this->last_error = '';
        if (str_contains($sql, 'mi_payments')) return $this->payments;
        if (str_contains($sql, 'mi_registration_items')) return $this->items;
        if (str_contains($sql, 'mi_participants') && !str_contains($sql, 'COUNT(*)')) return $this->people;
        if (str_contains($sql, 'mi_participants')) return [['ticket_type_code' => 'a', 'quantity' => count($this->people)]];
        return parent::get_results($sql, $mode);
    }
    function insert($table, $data, ...$args) {
        $result = parent::insert($table, $data, ...$args);
        if ($table === 'wp_mi_participants') $this->people[] = $data + ['id' => $this->insert_id];
        if ($table === 'wp_mi_registration_items') $this->items[] = $data;
        return $result;
    }
}
function audit_result($label, $confirmed, $detail) {
    echo json_encode(['case' => $label, 'regression_passed' => $confirmed, 'detail' => $detail], JSON_UNESCAPED_SLASHES) . "\n";
    if (!$confirmed) throw new RuntimeException('Regression failed: ' . $label);
}
$GLOBALS['fail_schedule'] = false;
$GLOBALS['cancellation_pending'] = false;

$wpdb = new AuditRegistrationDatabase();
$wpdb->people = [['id' => 1, 'registration_id' => 1, 'ticket_type_code' => 'a', 'first_name' => 'Persona', 'last_name' => 'Test', 'status' => 'ACTIVE', 'options_json' => '[]']];
$wpdb->people[] = array_replace($wpdb->people[0], ['id' => 2, 'first_name' => 'Seconda']);
$wpdb->items = [['ticket_type_code' => 'a', 'unit_price_cents' => 10000]];
$wpdb->registration = array_replace($stored, ['status' => 'PENDING_PAYMENT', 'economic_mode' => 'FULL_PAYMENT', 'total_qty' => 2, 'total_cents' => 20000, 'initial_due_cents' => 20000, 'expires_at' => gmdate('Y-m-d H:i:s', time() - 60), 'payment_deadline_at' => gmdate('Y-m-d H:i:s', time() - 60)]);
MI_Registration_Service::expire_due_registrations();
audit_result('expiry_after_deadline_extension', $wpdb->registration['status'] === 'PENDING_PAYMENT' && strtotime($wpdb->deadlineAtLock . ' UTC') > time(), ['selected_deadline' => $wpdb->selectedDeadline, 'deadline_at_lock' => $wpdb->deadlineAtLock, 'final_status' => $wpdb->registration['status']]);

$wpdb = new AuditRegistrationDatabase();
$first = MI_Registration_Service::create(42, $payload, 'audit-registration-request-0001', false, 'TEST', true);
if (is_wp_error($first)) throw new RuntimeException($first->message);
$same = MI_Registration_Service::create(42, $payload, 'audit-registration-request-0001', false, 'TEST', true);
if (is_wp_error($same) || !$same['replayed']) throw new RuntimeException('Identical request was not replayed');
$reordered = array_reverse($payload, true); $reordered['started_at'] = time();
$same = MI_Registration_Service::create(42, $reordered, 'audit-registration-request-0001', false, 'TEST', true);
if (is_wp_error($same) || !$same['replayed']) throw new RuntimeException('Object key ordering or timing changed retry identity');
$corrected = $payload;
$corrected['buyer']['email'] = 'corrected@example.invalid';
$corrected['participants'][0]['first_name'] = 'Corretto';
$retry = MI_Registration_Service::create(42, $corrected, 'audit-registration-request-0001', false, 'TEST', true);
audit_result('changed_payload_silently_replayed', is_wp_error($retry) && $retry->code === 'mi_idempotency_conflict' && $wpdb->registration['buyer_email'] !== $corrected['buyer']['email'], ['error' => is_wp_error($retry) ? $retry->code : '', 'stored_email' => $wpdb->registration['buyer_email'], 'submitted_email' => $corrected['buyer']['email'], 'stored_person' => $wpdb->people[0]['first_name'], 'submitted_person' => $corrected['participants'][0]['first_name']]);
$legacy = json_decode($wpdb->registration['snapshot_json'], true); unset($legacy['request_hash']);
$wpdb->registration['snapshot_json'] = json_encode($legacy);
$legacyRetry = MI_Registration_Service::create(42, $payload, 'audit-registration-request-0001', false, 'TEST', true);
if (!is_wp_error($legacyRetry) || $legacyRetry->code !== 'mi_idempotency_legacy') throw new RuntimeException('Legacy retry falsely verified or duplicated');

$wpdb = new AuditRegistrationDatabase();
$wpdb->people = [['id' => 1, 'registration_id' => 1, 'ticket_type_code' => 'a', 'first_name' => 'Persona', 'last_name' => 'Test', 'status' => 'ACTIVE', 'options_json' => '[]']];
$wpdb->items = [['ticket_type_code' => 'a', 'unit_price_cents' => 10000]];
$event = array_replace($event, ['economic_mode' => 'DEPOSIT_BALANCE', 'pricing_mode' => 'FIXED', 'deposit_mode' => 'FIXED', 'deposit_fixed_cents' => 3000, 'payment_methods' => ['CASH'], 'payment_deadline_at' => '2099-01-01T12:00']);
$offerToken = str_repeat('a', 64);
$wpdb->registration = array_replace($stored, ['status' => 'WAITLIST_OFFERED', 'economic_mode' => 'DEPOSIT_BALANCE', 'total_qty' => 1, 'total_cents' => 10000, 'initial_due_cents' => 0, 'snapshot_json' => json_encode(['event' => ['deposit_mode' => 'FIXED', 'deposit_fixed_cents' => 2000]]), 'waitlist_offer_expires_at' => gmdate('Y-m-d H:i:s', time() + 86400), 'waitlist_offer_token_hash' => hash('sha256', $offerToken)]);
$accepted = MI_Registration_Service::respond_waitlist_offer(1, $offerToken, 'ACCEPT');
$position = MI_Payment_People::read($wpdb->registration, []);
$paymentError = '';
try { MI_Payment_People::plan($position, [1], 'DEPOSIT', 2000); } catch (InvalidArgumentException $error) { $paymentError = $error->getMessage(); }
audit_result('waitlist_acceptance_after_fixed_deposit_change', $accepted === 'ACCEPTED' && $position['ready'] && $paymentError === '' && $wpdb->registration['initial_due_cents'] === 2000, ['acceptance' => is_wp_error($accepted) ? $accepted->message : $accepted, 'order_deposit' => $wpdb->registration['initial_due_cents'], 'person_deposit' => $position['people'][0]['deposit'], 'payment_error' => $paymentError]);
