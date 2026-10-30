<?php
require __DIR__.'/management-deposit-totals.php';
function sanitize_text_field($v) { return trim((string)$v); }
function sanitize_textarea_field($v) { return trim((string)$v); }
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-registration-service.php';
class DeadlineAuditDatabase extends AuditDatabase {
  function get_results($sql,$format=null) {
    if(str_contains($sql,'GROUP BY p.room_code')) return [];
    return parent::get_results($sql,$format);
  }
  function get_row($sql,$format=null) {
    if(str_contains($sql,'mi_registration_events')) return null;
    return parent::get_row($sql,$format);
  }
}
$wpdb=new DeadlineAuditDatabase();
$options=[['code'=>'alloggio-singola','name'=>'Singola','scope'=>'TICKET','price_cents'=>10000],['code'=>'alloggio-doppia-separati','name'=>'Doppia','scope'=>'TICKET','price_cents'=>10000]];
$wpdb->order=['id'=>1,'event_id'=>42,'order_code'=>'TEST','status'=>'PENDING_PAYMENT','buyer_first_name'=>'Persona','buyer_last_name'=>'Esempio','total_cents'=>10000,'initial_due_cents'=>10000,'economic_mode'=>'FULL_PAYMENT','snapshot_json'=>json_encode(['event'=>['pricing_mode'=>'CALCULATED','options'=>$options,'reopened_payment_hours'=>48,'reservation_minutes'=>60]]),'order_options_json'=>'[]','payment_deadline_at'=>'2026-01-01 10:00:00','expires_at'=>'2026-01-01 10:00:00'];
$wpdb->people=[['id'=>1,'registration_id'=>1,'ticket_type_code'=>'base','first_name'=>'Persona','last_name'=>'Esempio','status'=>'ACTIVE','room_code'=>'','extra_json'=>'{}','options_json'=>json_encode([['code'=>'alloggio-singola','name'=>'Singola','quantity'=>1,'unit_price_cents'=>10000]]),'deposit_due_cents'=>10000]];
$wpdb->items=[['registration_id'=>1,'ticket_type_code'=>'base','unit_price_cents'=>0]];
$wpdb->payments=[];
$method=new ReflectionMethod(MI_Management_Service::class,'accommodation_plan');
$plan=$method->invoke(null,42,['people'=>[['code'=>'TEST','number'=>1]],'type'=>'alloggio-doppia-separati','number'=>'','reason'=>'Cambio senza variazione di prezzo']);
$changes=$plan['orders'][0]['changes'];
if($plan['orders'][0]['delta']!==0 || array_key_exists('expires_at',$changes) || array_key_exists('payment_deadline_at',$changes)) throw new RuntimeException('Cambio logistico rinnova la scadenza');
$options[1]['price_cents']=12000;
$wpdb->order['snapshot_json']=json_encode(['event'=>['pricing_mode'=>'CALCULATED','options'=>$options,'reopened_payment_hours'=>48,'reservation_minutes'=>60]]);
$plan=$method->invoke(null,42,['people'=>[['code'=>'TEST','number'=>1]],'type'=>'alloggio-doppia-separati','number'=>'','reason'=>'Cambio con aumento']);
if($plan['orders'][0]['delta']!==2000 || strtotime($plan['orders'][0]['changes']['expires_at'].' UTC')<=time()) throw new RuntimeException('Variazione economica non riapre la scadenza');
echo "PASS: cambio logistico conserva la scadenza; aumento economico riapre il pagamento.\n";
