<?php
// Local audit with the existing synthetic database adapter; no email transport.
$testDir = __DIR__;
$fixture = file_get_contents($testDir . '/public-balance-model.php');
$fixture = explode('function check_balance(', $fixture, 2)[0];
$fixture = str_replace('__DIR__', var_export($testDir, true), $fixture);
$fixture = preg_replace('/static function reopened_payment_deadline\(.*?\}static function mark_workspace_changed_locked/s', 'static function reopened_payment_deadline($registration,$now=null){return AuditRealRegistration::reopened_payment_deadline($registration,$now);}static function mark_workspace_changed_locked', $fixture, 1);
eval('?>' . $fixture);
$registrationSource = file_get_contents(dirname($testDir) . '/modulo-iscrizioni/includes/class-mi-registration-service.php');
$registrationSource = str_replace('__DIR__', var_export(dirname($testDir) . '/modulo-iscrizioni/includes', true), $registrationSource);
$registrationSource = str_replace('final class MI_Registration_Service', 'final class AuditRealRegistration', $registrationSource);
eval('?>' . $registrationSource);
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
class WP_Error { public function __construct(public $code, public $message, public $data = null) {} }
function audit_public_result($case, $ok, $detail) {
    echo json_encode(['case' => $case, 'passed' => $ok, 'detail' => $detail], JSON_UNESCAPED_UNICODE) . "\n";
    if (!$ok) throw new RuntimeException('Regression: ' . $case);
}
function audit_public_save($services, $request) {
    $lookup = MI_Public_Balance::lookup(42, ['action' => 'lookupByCognome', 'cognome' => 'decclesia']);
    $data = ['persone' => [['row' => 1, 'token' => $lookup['persona']['token'], 'version' => $lookup['persona']['version'], 'services' => $services]], 'email' => '', 'requestId' => $request];
    $quote = MI_Public_Balance::save(42, $data, true);
    $data['fingerprint'] = $quote['fingerprint'];
    return MI_Public_Balance::save(42, $data);
}

$wpdb = new BalanceDB();
$definitions = [
    ['code' => 'transfer-privato', 'name' => 'Transfer privato', 'category' => 'altro', 'scope' => 'TICKET', 'price_cents' => 2000, 'max_quantity' => 1, 'choice_group' => 'trasporto'],
    ['code' => 'pullman-a', 'name' => 'Transfer condiviso', 'category' => 'pullman', 'scope' => 'TICKET', 'price_cents' => 1000, 'max_quantity' => 1, 'choice_group' => 'trasporto']
];
$snapshot = json_decode($wpdb->reg['snapshot_json'], true);
$snapshot['event']['options'] = $definitions;
$wpdb->reg['snapshot_json'] = json_encode($snapshot);
$wpdb->reg['total_cents'] = 52000;
$wpdb->reg['balance_cents'] = 37000;
$wpdb->persons[0]['options_json'] = json_encode([['code' => 'transfer-privato', 'name' => 'Transfer privato', 'quantity' => 1, 'unit_price_cents' => 2000]]);
$beforeOptions=$wpdb->persons[0]['options_json'];$rejected=false;
try{audit_public_save(['pullman-a'=>1],'12345678-1234-4234-8234-123456789a01');}catch(InvalidArgumentException $e){$rejected=true;}
audit_public_result('exclusive_group_includes_locked_services',$rejected&&$wpdb->reg['total_cents']===52000&&$wpdb->persons[0]['options_json']===$beforeOptions,[]);

$wpdb = new BalanceDB();
$snapshot = json_decode($wpdb->reg['snapshot_json'], true);
$snapshot['event']['options'] = [['code' => 'pullman-a', 'name' => 'Trasporti', 'category' => 'pullman', 'scope' => 'TICKET', 'price_cents' => 1000, 'max_quantity' => 3]];
$wpdb->reg['snapshot_json'] = json_encode($snapshot);
$wpdb->reg['total_cents'] = 53000;
$wpdb->reg['balance_cents'] = 38000;
$wpdb->persons[0]['options_json'] = json_encode([['code' => 'pullman-a', 'name' => 'Trasporti', 'quantity' => 3, 'unit_price_cents' => 1000]]);
$saved = audit_public_save([], '12345678-1234-4234-8234-123456789a02');
$options = json_decode($wpdb->persons[0]['options_json'], true);
audit_public_result('summary_confirmation_reduces_service_quantity', $saved['success'] && $options[0]['quantity'] === 3 && $wpdb->reg['total_cents'] === 53000, ['quantity_before' => 3, 'quantity_after' => $options[0]['quantity'], 'total_before' => 53000, 'total_after' => $wpdb->reg['total_cents']]);

$wpdb = new BalanceDB();
$oldDeadline = gmdate('Y-m-d H:i:s', time() - 60);
$wpdb->reg['expires_at'] = $oldDeadline;
$wpdb->reg['payment_deadline_at'] = $oldDeadline;
$saved = audit_public_save(['pullman-a' => 0], '12345678-1234-4234-8234-123456789a03');
$newDeadline = $wpdb->reg['expires_at'];
audit_public_result('unchanged_summary_extends_expired_booking', $saved['success'] && $newDeadline === $oldDeadline && $wpdb->reg['total_cents'] === 50000 && $wpdb->persons[0]['options_json'] === '[]', ['status' => $wpdb->reg['status'], 'deadline_before' => $oldDeadline, 'deadline_after' => $newDeadline, 'total_cents' => $wpdb->reg['total_cents'], 'services_unchanged' => true]);

$wpdb = new BalanceDB();
$wpdb->reg['expires_at'] = null;
$wpdb->reg['payment_deadline_at'] = null;
$snapshot = json_decode($wpdb->reg['snapshot_json'], true);
$snapshot['event']['payment_deadline_at'] = '';
$snapshot['event']['reservation_minutes'] = 0;
$wpdb->reg['snapshot_json'] = json_encode($snapshot);
$initialExpiry = (new ReflectionMethod(AuditRealRegistration::class, 'registration_expiry'))->invoke(null, ['economic_mode' => 'DEPOSIT_BALANCE', 'payment_deadline_at' => '', 'reservation_minutes' => 0], 'PENDING_PAYMENT', gmdate('Y-m-d H:i:s'));
$saved = audit_public_save(['pullman-a' => 0], '12345678-1234-4234-8234-123456789a04');
audit_public_result('unchanged_summary_introduces_expiry', $initialExpiry === null && $saved['success'] && $wpdb->reg['expires_at'] === null, ['creation_expiry' => $initialExpiry, 'deadline_before' => null, 'deadline_after' => $wpdb->reg['expires_at'], 'services_unchanged' => $wpdb->persons[0]['options_json'] === '[]']);

// A real increase still opens a payment window; a future window is not extended.
$saved=audit_public_save(['pullman-a'=>1],'12345678-1234-4234-8234-123456789a05');
audit_public_result('economic_increase_reopens_payment',$wpdb->reg['total_cents']===51000&&strtotime($wpdb->reg['expires_at'].' UTC')>time()+47*3600,[]);
$future=$wpdb->reg['expires_at'];
audit_public_save(['pullman-a'=>1],'12345678-1234-4234-8234-123456789a06');
audit_public_result('future_deadline_preserved',$wpdb->reg['expires_at']===$future,[]);
