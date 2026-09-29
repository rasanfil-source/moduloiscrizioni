<?php
// Read-only audit: real calculation/summary classes, synthetic WordPress/database reads.
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
function wp_json_encode($value) { return json_encode($value); }
function get_post_meta($id, $key, $single=true) { return ['_mi_options'=>[], '_mi_custom_participant_fields'=>[], '_mi_economic_mode'=>'DEPOSIT_BALANCE'][$key] ?? ''; }
function get_the_title($id) { return 'Evento sintetico'; }
function wp_date($format) { return gmdate($format); }
function remove_accents($value) { return $value; }
function absint($value) { return abs((int)$value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error { function __construct(public $code, public $message) {} }
class MI_Portal_Management { static function allowed() { return true; } }
class MI_Access { static function can_access_event($id) { return $id === 42; } }
class MI_Shortcode { static function url_iscrizione($id) { return 'https://example.invalid/event'; } }
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-service.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-management-list.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
class AuditDatabase {
  public $prefix='wp_', $last_error='';
  public $order, $people, $items, $payments;
  function prepare($sql, ...$args) { return $sql; }
  function get_results($sql, $format=null) {
    if(str_contains($sql, 'mi_registration_events') || str_contains($sql, 'mi_rooms')) return [];
    if(str_contains($sql, 'mi_participants')) return $this->people;
    if(str_contains($sql, 'mi_registration_items')) return $this->items;
    if(str_contains($sql, 'mi_payments')) return $this->payments;
    if(str_contains($sql, 'mi_registrations')) return [$this->order];
    throw new RuntimeException('Unexpected read: '.$sql);
  }
  function get_row($sql, $format=null) { return $this->order; }
}
$wpdb=new AuditDatabase();
$wpdb->order=['id'=>1,'event_id'=>42,'order_code'=>'TEST','status'=>'PENDING_PAYMENT','buyer_first_name'=>'Ada','buyer_last_name'=>'Esempio','buyer_email'=>'','buyer_phone'=>'','total_cents'=>20000,'initial_due_cents'=>6000,'economic_mode'=>'DEPOSIT_BALANCE','snapshot_json'=>json_encode(['event'=>['deposit_mode'=>'FIXED','deposit_fixed_cents'=>3000,'options'=>[]]]),'order_options_json'=>'[]'];
$wpdb->people=[];
foreach([1,2] as $id) $wpdb->people[]=['id'=>$id,'registration_id'=>1,'ticket_type_code'=>'base','first_name'=>$id===1?'Ada':'Luca','last_name'=>'Esempio','status'=>'ACTIVE','room_code'=>'','extra_json'=>'{}','options_json'=>'[]','deposit_due_cents'=>3000];
$wpdb->items=[['registration_id'=>1,'ticket_type_code'=>'base','unit_price_cents'=>10000]];
$wpdb->payments=[['id'=>1,'registration_id'=>1,'transaction_kind'=>'PAYMENT','amount_cents'=>20000,'participant_allocations_json'=>'[{"participant_id":1,"amount_cents":20000,"name":"Ada Esempio"}]','effective_at'=>'2026-09-29 10:00:00','movement_kind'=>'INCASSO','payment_source'=>'CASH','external_reference'=>'','operator_label'=>'Test','administrative_note'=>'']];
$healthy=MI_Management_Service::summary(42, [1], []);
if(is_wp_error($healthy))throw new RuntimeException($healthy->message);
$wpdb->people[0]['deposit_due_cents']=2000;
$position=MI_Payment_People::calculate($wpdb->order,$wpdb->people,$wpdb->items,$wpdb->payments);
$management=MI_Management_Service::summary(42, [1], []);
if(is_wp_error($management))throw new RuntimeException($management->message);
$overview=MI_Management_List::overview($management);
$detail=MI_Payment_Ledger::detail(1);
$out=['quotes_known'=>$position['quotes_known'],'payments_known'=>$position['payments_known'],'deposits_known'=>$position['deposits_known'],'individual_balances'=>array_column($position['people'],'balance'),'healthy_management_balance'=>$healthy['items'][0]['balance'],'inconsistent_deposit_management_balance'=>$management['items'][0]['balance'],'overview_receivable'=>$overview['metrics']['receivable'],'payment_detail_balance'=>$detail['saldo']['residuo']];
if($out['healthy_management_balance']!==10000||$out['inconsistent_deposit_management_balance']!==10000||$out['overview_receivable']!==10000||$out['payment_detail_balance']!==10000)throw new RuntimeException('Bug reproduction changed');
if(!in_array('--json',$argv,true))echo json_encode($out,JSON_PRETTY_PRINT).PHP_EOL;
// Ordinary partial family deposit: one of two people has paid; booking remains pending.
$wpdb->people[0]['deposit_due_cents']=3000;
$wpdb->payments[0]['amount_cents']=3000;
$wpdb->payments[0]['participant_allocations_json']='[{"participant_id":1,"amount_cents":3000,"name":"Ada Esempio"}]';
$partial=MI_Management_Service::summary(42,[1],[]);
$partialOverview=MI_Management_List::overview($partial);
if(!in_array('--json',$argv,true))echo json_encode(['case'=>'partial-family-deposit','payment_counts'=>$partialOverview['payment_counts'],'states'=>$partialOverview['metrics']['states']]).PHP_EOL;

if(in_array('--json',$argv,true))echo json_encode(['overview'=>$partialOverview,'page'=>MI_Management_List::page($partial,[])]);
