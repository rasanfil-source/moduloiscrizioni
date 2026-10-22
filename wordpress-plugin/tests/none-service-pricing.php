<?php
require __DIR__ . '/management-deposit-totals.php';
function sanitize_text_field($v) { return trim((string)$v); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$v)); }
function sanitize_textarea_field($v) { return trim((string)$v); }
function wp_timezone() { return new DateTimeZone('Europe/Rome'); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';
$option = ['code'=>'pranzo','name'=>'Pranzo','scope'=>'TICKET','price_cents'=>2000,'max_quantity'=>1];
$method = new ReflectionMethod(MI_Management_Service::class, 'options_plan');
foreach (['NONE', 'ZERO'] as $mode) {
    $price = $mode === 'NONE' ? 2000 : 0;
    $wpdb = new AuditDatabase();
    $wpdb->order = ['id'=>1,'event_id'=>42,'order_code'=>'TEST','status'=>'PENDING_PAYMENT','buyer_first_name'=>'Persona','buyer_last_name'=>'Esempio','buyer_email'=>'','buyer_phone'=>'','total_cents'=>$price,'initial_due_cents'=>$price,'economic_mode'=>'FULL_PAYMENT','snapshot_json'=>json_encode(['event'=>['pricing_mode'=>$mode,'options'=>[$option]]]),'order_options_json'=>'[]'];
    $selected = [['code'=>'pranzo','name'=>'Pranzo','quantity'=>1,'unit_price_cents'=>$price]];
    $wpdb->people = [['id'=>1,'registration_id'=>1,'ticket_type_code'=>'base','first_name'=>'Persona','last_name'=>'Esempio','status'=>'ACTIVE','room_code'=>'','extra_json'=>'{}','options_json'=>json_encode($selected),'deposit_due_cents'=>$price]];
    $wpdb->items = [['registration_id'=>1,'ticket_type_code'=>'base','unit_price_cents'=>0]];
    $wpdb->payments = [];
    $booking = ['participants'=>[['id'=>1,'status'=>'ACTIVE','options'=>$selected]],'fields'=>[]];
    $remove = $method->invoke(null, $wpdb->order, $booking, ['participant_id'=>1,'options'=>[],'reason'=>'Rimozione pranzo']);
    if ($remove['delta'] !== -$price || $remove['changes']['total_cents'] !== 0 || $remove['after_options'] !== []) throw new RuntimeException('Rimozione errata: '.$mode);
    $wpdb->order['total_cents'] = $wpdb->order['initial_due_cents'] = 0;
    $wpdb->people[0]['options_json'] = '[]'; $wpdb->people[0]['deposit_due_cents'] = 0;
    $booking['participants'][0]['options'] = [];
    $add = $method->invoke(null, $wpdb->order, $booking, ['participant_id'=>1,'options'=>['pranzo'=>1],'reason'=>'Aggiunta pranzo']);
    if ($add['delta'] !== $price || $add['changes']['total_cents'] !== $price) throw new RuntimeException('Aggiunta errata: '.$mode);
}
echo "NONE/ZERO: aggiunta e rimozione servizi verificate.\n";
