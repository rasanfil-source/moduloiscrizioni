<?php
// Real management summary; reuse the existing synthetic read adapter.
$fixture = explode('$wpdb=new AuditDatabase();', file_get_contents(__DIR__ . '/management-deposit-totals.php'), 2)[0];
eval('?>' . str_replace('__DIR__', var_export(__DIR__, true), $fixture));
foreach (['ONE', 'ALL'] as $scope) foreach (['ACTIVE', 'CANCELLED'] as $status) {
    $wpdb = new AuditDatabase();
    $wpdb->order = ['id'=>1, 'event_id'=>42, 'order_code'=>'TEST', 'status'=>'CONFIRMED', 'buyer_first_name'=>'Persona', 'buyer_last_name'=>'Uno', 'buyer_email'=>'', 'buyer_phone'=>'', 'total_cents'=>0, 'initial_due_cents'=>0, 'economic_mode'=>'REGISTRATION_ONLY', 'snapshot_json'=>json_encode(['event'=>['participant_extra_scope'=>$scope, 'participant_fields'=>[['key'=>'custom_arrivo', 'label'=>'Data di arrivo', 'type'=>'date', 'required'=>true]]]]), 'order_options_json'=>'[]'];
    $wpdb->people = [
        ['id'=>1, 'registration_id'=>1, 'ticket_type_code'=>'base', 'first_name'=>'Persona', 'last_name'=>'Uno', 'status'=>$status, 'room_code'=>'', 'extra_json'=>'{"custom_arrivo":"2026-10-20"}', 'options_json'=>'[]', 'deposit_due_cents'=>null],
        ['id'=>2, 'registration_id'=>1, 'ticket_type_code'=>'base', 'first_name'=>'Persona', 'last_name'=>'Due', 'status'=>'ACTIVE', 'room_code'=>'', 'extra_json'=>'{}', 'options_json'=>'[]', 'deposit_due_cents'=>null],
    ];
    $wpdb->items = [['registration_id'=>1, 'ticket_type_code'=>'base', 'unit_price_cents'=>0]];
    $wpdb->payments = [];
    $summary = MI_Management_Service::summary(42, [1], []);
    if (is_wp_error($summary)) throw new RuntimeException($summary->message);
    $expected = $scope === 'ALL' ? ['Data di arrivo'] : [];
    if ($summary['people'][1]['missing'] !== $expected || $summary['items'][0]['missing'] !== count($expected)) throw new RuntimeException('Management shifted field obligations: '.$scope.' '.$status);
    $overview = MI_Management_List::overview($summary);
    if ($overview['metrics']['missing'] !== count($expected)) throw new RuntimeException('Overview counted a transferred obligation');
    $page = MI_Management_List::page($summary, ['filter'=>'missing']);
    if ($page['total'] !== count($expected)) throw new RuntimeException('Missing-fields filter included the wrong participant');
}
echo "PASS: management, overview and missing-fields filter retain original ONE obligations after cancellation.\n";
