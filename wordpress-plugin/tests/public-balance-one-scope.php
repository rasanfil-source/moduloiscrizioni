<?php
// Real public lookup, preview and email rendering with synthetic WordPress/DB.
$fixture = explode('function check_balance(', file_get_contents(__DIR__ . '/public-balance-model.php'), 2)[0];
eval('?>' . str_replace('__DIR__', var_export(__DIR__, true), $fixture));
function check_scope($ok, $message) { if (!$ok) throw new RuntimeException($message); }
foreach (['ONE', 'ALL'] as $scope) foreach (['ACTIVE', 'CANCELLED'] as $firstStatus) {
    $wpdb = new BalanceDB();
    $snapshot = json_decode($wpdb->reg['snapshot_json'], true);
    $snapshot['event']['participant_extra_scope'] = $scope;
    $snapshot['event']['participant_fields'] = [['key'=>'custom_arrivo', 'label'=>'Data di arrivo', 'type'=>'date', 'required'=>true, 'date_rule'=>'any']];
    $wpdb->reg['snapshot_json'] = json_encode($snapshot);
    $wpdb->reg['total_cents'] = 100000; $wpdb->reg['initial_due_cents'] = 30000; $wpdb->reg['balance_cents'] = 70000;
    $wpdb->persons[0]['extra_json'] = '{"custom_arrivo":"2026-10-20"}';
    $wpdb->persons[] = array_replace($wpdb->persons[0], ['id'=>2, 'first_name'=>'Maria', 'room_code'=>'S2', 'extra_json'=>'{}']);
    $wpdb->persons[0]['status'] = $firstStatus;
    $lookup = MI_Public_Balance::lookup(42, ['action'=>'lookupPersona', 'cognome'=>'decclesia', 'nome'=>'Maria', 'candidate'=>2]);
    $data = ['persone'=>[['row'=>2, 'token'=>$lookup['persona']['token'], 'version'=>$lookup['persona']['version'], 'services'=>[]]], 'email'=>''];
    $preview = MI_Public_Balance::save(42, $data, true);
    check_scope($preview['people'][0]['missing'] === ($scope === 'ALL' ? ['Data di arrivo'] : []), 'Required fields shifted after cancellation: ' . $scope . ' ' . $firstStatus);
    check_scope(count($lookup['persona']['services']) === ($scope === 'ALL' || $firstStatus === 'CANCELLED' ? 1 : 0), 'Shared services must remain editable by the first active participant');
    check_scope($wpdb->writes === 0, 'Preview wrote to the database');
    $html = (new ReflectionMethod(MI_Public_Balance::class, 'email_body'))->invoke(null, 42, $preview, ['methods'=>[]]);
    check_scope(str_contains($html, 'Data di arrivo') === ($scope === 'ALL'), 'Email requested fields from the wrong participant');
    if ($scope === 'ONE' && $firstStatus === 'ACTIVE') {
        $data['persone'][0]['services'] = ['pullman-a'=>1]; $rejected = false;
        try { MI_Public_Balance::save(42, $data, true); }
        catch (InvalidArgumentException $error) { $rejected = true; }
        check_scope($rejected, 'A forged request allowed services for a participant outside the shared scope');
        if ($firstStatus === 'ACTIVE') {
            $first = MI_Public_Balance::lookup(42, ['action'=>'lookupPersona', 'cognome'=>'decclesia', 'nome'=>'Marco', 'candidate'=>1]);
            check_scope(count($first['persona']['services']) === 1, 'Original participant lost service eligibility');
        }
    }
}
echo "PASS: ONE field obligations stay with the original participant after cancellation; ALL and shared service editing retain their own scope.\n";
