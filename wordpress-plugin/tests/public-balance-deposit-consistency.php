<?php
// Reuse only the synthetic adapter; exercise the real balance and quote services.
$fixture = file_get_contents(__DIR__ . '/public-balance-model.php');
$fixture = explode('foreach([[0,15000', $fixture, 2)[0];
eval('?>' . str_replace('__DIR__', var_export(__DIR__, true), $fixture));
$wpdb = new BalanceDB();
$wpdb->reg['initial_due_cents'] = 20000;
$wpdb->reg['balance_cents'] = 30000;
$wpdb->payments = [['id' => 1, 'amount_cents' => 15000, 'transaction_kind' => 'PAYMENT']];
$position = MI_Payment_People::read($wpdb->reg, $wpdb->payments);
check_balance(!$position['deposits_known'], 'Fixture must contain inconsistent deposits');
$bundle = (new ReflectionMethod(MI_Public_Balance::class, 'bundle'))->invoke(null, 42, 1);
$data = ['persone' => [['row' => 1, 'token' => hash_hmac('sha256', 'balance-person|42|1', wp_salt('auth')), 'version' => $bundle['version'], 'services' => []]], 'email' => '', 'requestId' => '32345678-1234-4234-8234-123456789abc'];
$before = [$wpdb->reg, $wpdb->persons, $wpdb->events, $wpdb->writes];
foreach (['lookup', 'preview', 'save'] as $action) {
    $rejected = false;
    try {
        if ($action === 'lookup') MI_Public_Balance::lookup(42, ['action' => 'lookupByCognome', 'cognome' => 'decclesia']);
        else MI_Public_Balance::save(42, $data, $action === 'preview');
    } catch (InvalidArgumentException $error) {
        $rejected = str_contains($error->getMessage(), 'caparra');
    }
    check_balance($rejected, 'Inconsistent deposit must be rejected at ' . $action);
    check_balance($before === [$wpdb->reg, $wpdb->persons, $wpdb->events, $wpdb->writes], 'Rejected request changed stored data');
}
// Once staff resolves the inconsistency, a normal confirmation works again.
$wpdb->persons[0]['deposit_due_cents'] = 20000;
$lookup = MI_Public_Balance::lookup(42, ['action' => 'lookupByCognome', 'cognome' => 'decclesia']);
$data['persone'][0]['version'] = $lookup['persona']['version'];
$preview = MI_Public_Balance::save(42, $data, true);
$data['fingerprint'] = $preview['fingerprint'];
$saved = MI_Public_Balance::save(42, $data);
check_balance($saved['success'] && $saved['depositDue'] === 5000, 'Consistent deposit confirmation failed');
check_balance($wpdb->reg['initial_due_cents'] === 20000 && $wpdb->reg['status'] === 'PENDING_PAYMENT', 'Confirmation changed valid deposit or payment status');
echo "PASS: inconsistent public deposits reject lookup, preview and save without writes; resolved deposits remain usable.\n";
