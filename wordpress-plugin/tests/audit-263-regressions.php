<?php
// Prove isolate: classi reali, dati sintetici, nessun database o servizio remoto.
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
define('HOUR_IN_SECONDS', 3600);
class WP_Error { function __construct(public $code, public $message, public $data = null) {} }
function is_wp_error($value) { return $value instanceof WP_Error; }
function absint($value) { return abs((int)$value); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string)$value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$value)); }
function sanitize_user($value, ...$args) { return (string)$value; }
function sanitize_email($value) { return (string)$value; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL) !== false; }
function wp_json_encode($value) { return json_encode($value); }
function wp_timezone() { return new DateTimeZone('Europe/Rome'); }
function current_time($format, ...$args) { return gmdate($format === 'mysql' ? 'Y-m-d H:i:s' : $format); }
function get_post_meta(...$args) { return ''; }
function get_current_user_id() { return 7; }
function wp_get_current_user() { return (object)['ID'=>7, 'display_name'=>'Operatore sintetico', 'roles'=>['mi_registration_manager']]; }
function get_user_by(...$args) { return wp_get_current_user(); }
function user_can($user, $capability) { return current_user_can($capability); }
function current_user_can($capability) { return in_array($capability, ['mi_manage_all_events', 'mi_manage_module_users', 'mi_manage_events', 'mi_portal_access'], true); }
function get_user_meta($id, $key, ...$args) { return $key === '_mi_access_suspended' && !empty($GLOBALS['audit_suspended']) ? '1' : ''; }
function wp_next_scheduled(...$args) { return time(); }
function is_user_logged_in() { return true; }
function check_admin_referer(...$args) { return true; } // Simula un nonce ancora valido di una sessione aperta.
function wp_unslash($value) { return $value; }
function wp_die($message, ...$args) { throw new RuntimeException('ACCESS_BLOCKED: '.$message); }
class AuditMutationReached extends RuntimeException {}
function wp_insert_user($data) { $GLOBALS['audit_insert_attempt'] = $data; throw new AuditMutationReached('Scrittura intercettata: nessun utente creato.'); }
class MI_Portal_Payments { static function allowed() { return true; } }

$auditIncludes = __DIR__.'/../modulo-iscrizioni/includes/';
require $auditIncludes.'class-mi-access.php';
require $auditIncludes.'class-mi-payment-ledger.php';
require $auditIncludes.'class-mi-registration-service.php';
require $auditIncludes.'class-mi-portal.php';
require $auditIncludes.'class-mi-attendance-report.php';

class AuditLedgerDatabase {
    public $prefix='wp_', $last_error='', $insert_id=0, $payments=[], $registration, $audit=[];
    private $snapshot;
    function __construct() {
        $deadline = gmdate('Y-m-d H:i:s', time()+3600);
        $this->registration = ['id'=>1, 'event_id'=>42, 'workspace_revision'=>0, 'workspace_status'=>'PENDING', 'status'=>'PENDING_PAYMENT', 'economic_mode'=>'DEPOSIT_BALANCE', 'total_cents'=>20000, 'initial_due_cents'=>6000, 'payment_deadline_at'=>$deadline, 'expires_at'=>$deadline, 'snapshot_json'=>json_encode(['event'=>['payment_deadline_at'=>(new DateTimeImmutable($deadline, new DateTimeZone('UTC')))->setTimezone(wp_timezone())->format('Y-m-d\TH:i'), 'deposit_mode'=>'FIXED', 'deposit_fixed_cents'=>3000, 'reopened_payment_hours'=>48]])];
    }
    function prepare($sql, ...$args) { return [$sql, $args]; }
    function query($sql) {
        if ($sql === 'START TRANSACTION') $this->snapshot = [$this->payments, $this->registration, $this->audit];
        if ($sql === 'ROLLBACK') [$this->payments, $this->registration, $this->audit] = $this->snapshot;
        return 1;
    }
    function get_row($query, $format) {
        [$sql, $args] = $query;
        if (str_contains($sql, 'mi_registrations')) return $this->registration;
        foreach ($this->payments as $payment) if ($payment['origin_id'] === $args[0]) return $payment;
        return null;
    }
    function get_var($query) {
        [$sql] = $query;
        if (str_contains($sql, 'workspace_status')) return 'PENDING';
        return array_sum(array_map(fn($p) => ($p['transaction_kind'] === 'REFUND' ? -1 : 1)*$p['amount_cents'], $this->payments));
    }
    function get_results($query, $format) {
        [$sql] = $query;
        if (str_contains($sql, 'mi_participants')) return array_map(fn($id) => ['id'=>$id, 'ticket_type_code'=>'base', 'first_name'=>'Persona', 'last_name'=>'Test'.$id, 'options_json'=>'[]', 'status'=>'ACTIVE', 'deposit_due_cents'=>3000], [1, 2]);
        if (str_contains($sql, 'mi_registration_items')) return [['ticket_type_code'=>'base', 'unit_price_cents'=>10000]];
        return $this->payments;
    }
    function insert($table, $data, ...$args) {
        $data['id'] = ++$this->insert_id;
        if ($table === 'wp_mi_payments') $this->payments[] = $data;
        else $this->audit[] = $data;
        return 1;
    }
    function update($table, $changes, $where, ...$args) { $this->registration = array_replace($this->registration, $changes); return 1; }
}

$wpdb = new AuditLedgerDatabase();
$before = $wpdb->registration['expires_at'];
$result = MI_Payment_Ledger::save(1, ['request_id'=>'wp_7_12345678-1234-4234-8234-123456789abc', 'importo'=>'30', 'tipo'=>'INCASSO', 'metodo'=>'BONIFICO', 'data'=>'2026-10-26', 'participant_ids'=>'[1]', 'rata'=>'DEPOSIT']);
$after = $wpdb->registration['expires_at'];
$deadlineBug = !empty($result['saved']) && strtotime($after.' UTC')-strtotime($before.' UTC') > 46*3600;
echo json_encode(['case'=>'payment_extends_other_person_deadline', 'reproduced'=>$deadlineBug, 'saved'=>$result, 'status'=>$wpdb->registration['status'], 'deadline_before'=>$before, 'deadline_after'=>$after, 'extension_hours'=>(strtotime($after.' UTC')-strtotime($before.' UTC'))/3600], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";

$GLOBALS['audit_suspended'] = true;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['mi_portal_action'=>'create_operator', 'operator_role'=>'mi_registration_manager', 'operator_username'=>'audit_synthetic', 'operator_email'=>'synthetic@example.invalid', 'operator_name'=>'Prova locale', 'operator_password'=>'synthetic-not-used'];
$suspensionBug = false;
try { MI_Portal::handle_actions(); }
catch (AuditMutationReached $error) { $suspensionBug = true; }
catch (RuntimeException $error) { if (!str_starts_with($error->getMessage(), "ACCESS_BLOCKED:")) throw $error; }
echo json_encode(['case'=>'suspended_operator_reaches_user_creation', 'reproduced'=>$suspensionBug, 'suspended'=>MI_Access::is_suspended(), 'event_access_blocked'=>!MI_Access::can_access_event(42), 'user_creation_reached'=>isset($GLOBALS['audit_insert_attempt']), 'actual_users_created'=>0], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";

$people = [
    ['id'=>1, 'registration_id'=>1, 'event_id'=>10, 'order_code'=>'SYNTH1', 'first_name'=>'Anna', 'last_name'=>'Esempio', 'extra_json'=>json_encode(['participant_phone'=>'+39 333 0000000']), 'status'=>'ACTIVE', 'booking_status'=>'CONFIRMED'],
    ['id'=>2, 'registration_id'=>2, 'event_id'=>11, 'order_code'=>'SYNTH2', 'first_name'=>'Luca', 'last_name'=>'Esempio', 'extra_json'=>json_encode(['participant_phone'=>'+39 333 0000000']), 'status'=>'ACTIVE', 'booking_status'=>'CONFIRMED']
];
$attendance = array_map(fn($id) => ['registration_id'=>$id, 'event_type'=>'MANAGEMENT_attendance', 'detail_json'=>json_encode(['participant_id'=>$id, 'attendance'=>'PRESENT'])], [1, 2]);
$events = [10=>['date'=>'2026-04-01', 'title'=>'Evento sintetico A'], 11=>['date'=>'2026-05-01', 'title'=>'Evento sintetico B']];
$report = MI_Attendance_Report::aggregate($people, $attendance, $events, 2026, 2);
$identityBug = count($report['items']) === 1 && $report['items'][0]['count'] === 2 && count($report['items'][0]['names']) === 2;
echo json_encode(['case'=>'shared_family_phone_merges_distinct_people', 'reproduced'=>$identityBug, 'explicit_identity_links'=>0, 'expected_people_with_two_attendances'=>0, 'actual_report'=>$report], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
if (empty($result['saved']) || $before !== $after || $suspensionBug || count($report['items']) !== 0) throw new RuntimeException('Audit regression');
foreach (['can_manage_groups', 'can_manage_module_users'] as $method) {
    if ((new ReflectionMethod(MI_Portal::class, $method))->invoke(null)) throw new RuntimeException('Suspension ignored');
    $GLOBALS['audit_suspended'] = false;
    if ($method === 'can_manage_module_users' && !(new ReflectionMethod(MI_Portal::class, $method))->invoke(null)) throw new RuntimeException('Active manager blocked');
    $GLOBALS['audit_suspended'] = true;
}
echo "PASS: suspended access, unchanged group deadline and shared phone isolation.\n";

// Gli incassi conservano anche termini già trascorsi o assenti.
$GLOBALS['audit_suspended'] = false;
foreach ([null, gmdate('Y-m-d H:i:s', time()-3600)] as $deadline) {
    $wpdb = new AuditLedgerDatabase();
    $wpdb->registration['expires_at'] = $wpdb->registration['payment_deadline_at'] = $deadline;
    $saved = MI_Payment_Ledger::save(1, ['request_id'=>'wp_7_12345678-1234-4234-8234-123456789abd', 'importo'=>'30', 'tipo'=>'INCASSO', 'metodo'=>'BONIFICO', 'data'=>'2026-10-26', 'participant_ids'=>'[1]', 'rata'=>'DEPOSIT']);
    if (empty($saved['saved']) || $wpdb->registration['expires_at'] !== $deadline || $wpdb->registration['payment_deadline_at'] !== $deadline) throw new RuntimeException('Existing deadline changed');
}
// Il completamento conferma la prenotazione; uno storno che la riapre concede la finestra prevista.
$wpdb = new AuditLedgerDatabase();
$input = ['request_id'=>'wp_7_12345678-1234-4234-8234-123456789abe', 'importo'=>'60', 'tipo'=>'INCASSO', 'metodo'=>'BONIFICO', 'data'=>'2026-10-26', 'participant_ids'=>'[1,2]', 'rata'=>'DEPOSIT'];
$saved = MI_Payment_Ledger::save(1, $input);
if (empty($saved['saved']) || $wpdb->registration['status'] !== 'CONFIRMED' || $wpdb->registration['expires_at'] !== null) throw new RuntimeException('Completion failed');
$refund = MI_Payment_Ledger::save(1, array_replace($input, ['request_id'=>'wp_7_12345678-1234-4234-8234-123456789abf', 'tipo'=>'STORNO', 'importo'=>'30', 'participant_ids'=>'[1]']));
if (empty($refund['saved']) || $wpdb->registration['status'] !== 'PENDING_PAYMENT' || strtotime($wpdb->registration['expires_at'].' UTC') < time()+47*3600) throw new RuntimeException('Reopening failed');
echo "PASS: missing/expired deadlines, complete deposits and refund reopening.\n";
