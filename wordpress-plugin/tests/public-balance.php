<?php
define( 'ABSPATH', __DIR__ ); define( 'ARRAY_A', 'ARRAY_A' );
function absint($v){return abs((int)$v);} function sanitize_text_field($v){return strip_tags((string)$v);} function sanitize_email($v){return (string)$v;} function wp_salt($v){return 'synthetic-public-test';} function get_the_title($id){return 'Evento prova';}
class WP_Error { public function __construct(public $code,public $message){} }
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-registration-service.php';
class PublicBalanceDatabase {
 public $prefix='wp_', $last_error='', $paid=3000, $fail=false, $cross=false;
 public $row=['id'=>7,'event_id'=>42,'order_code'=>'TEST','status'=>'CONFIRMED','buyer_email'=>'ref@example.invalid','economic_mode'=>'DEPOSIT_BALANCE','total_cents'=>10000,'initial_due_cents'=>3000,'balance_cents'=>7000,'payment_deadline_at'=>'2026-10-01 10:00:00','payment_methods_json'=>'["BANK_TRANSFER"]'];
 function prepare($sql,...$args){return $sql;} function get_row($sql,$format){return $this->row;}
 function get_results($sql,$format){
  $this->last_error=$this->fail?'error':'';
  if(str_contains($sql,'mi_payments'))return [['transaction_kind'=>'PAYMENT','amount_cents'=>$this->paid,'participant_allocations_json'=>json_encode([['participant_id'=>70,'amount_cents'=>$this->paid]])]];
  if(str_contains($sql,'mi_participants'))return $this->cross
   ? [['id'=>70,'ticket_type_code'=>'standard','first_name'=>'Persona','last_name'=>'Uno','options_json'=>'[]','status'=>'ACTIVE','deposit_due_cents'=>10000],['id'=>71,'ticket_type_code'=>'standard','first_name'=>'Persona','last_name'=>'Due','options_json'=>'[]','status'=>'ACTIVE','deposit_due_cents'=>10000]]
   : [['id'=>70,'ticket_type_code'=>'standard','first_name'=>'Persona','last_name'=>'Prova','options_json'=>'[]','status'=>'ACTIVE','deposit_due_cents'=>3000]];
  return [['ticket_type_code'=>'standard','unit_price_cents'=>$this->cross?30000:10000]];
 }
}
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);}
$wpdb=new PublicBalanceDatabase();
$r=MI_Registration_Service::public_status('TEST','ref@example.invalid');verify($r['balance_cents']===7000&&$r['collectible'],'Actual residual');
verify(MI_Registration_Service::public_status('TEST','wrong@example.invalid') instanceof WP_Error,'Wrong email rejected');
$token=MI_Registration_Service::public_status_token(7,'TEST','ref@example.invalid');verify(is_array(MI_Registration_Service::public_status('TEST','',$token,42)),'Token accepted');verify(MI_Registration_Service::public_status('TEST','',$token,43) instanceof WP_Error,'Cross event rejected');
foreach(['WAITLISTED','WAITLIST_OFFERED','CANCELLED','EXPIRED'] as $status){$wpdb->row['status']=$status;$r=MI_Registration_Service::public_status('TEST','ref@example.invalid');verify(!$r['collectible']&&!$r['payment_methods']&&!$r['payment_deadline'],'Closed/waitlisted must not request payment');}
$wpdb->row['status']='CONFIRMED';$wpdb->row['economic_mode']='PRICE_ONLY';verify(!MI_Registration_Service::public_status('TEST','ref@example.invalid')['collectible'],'Price only not collectible');
$wpdb->row['economic_mode']='FULL_PAYMENT';$wpdb->paid=0;$r=MI_Registration_Service::public_status('TEST','ref@example.invalid');verify(!str_contains($r['payment_status'],'Caparra'),'Full payment not labelled deposit');
$wpdb->row['economic_mode']='DEPOSIT_BALANCE';$wpdb->row['total_cents']=60000;$wpdb->row['initial_due_cents']=20000;$wpdb->paid=60000;$wpdb->cross=true;$r=MI_Registration_Service::public_status('TEST','ref@example.invalid');verify($r['balance_cents']===30000&&$r['payment_status']!=='Saldo completato','One participant credit must not settle another participant');
$wpdb->fail=true;verify(MI_Registration_Service::public_status('TEST','ref@example.invalid') instanceof WP_Error,'Read failure not interpreted as unpaid');
echo "Saldo pubblico: identificazione, residuo, stati, modalità e errore di lettura verificati.\n";
function sanitize_key($value){return preg_replace('/[^a-z0-9_-]/','',strtolower($value));}
$definitions=[['code'=>'single','name'=>'Singola','scope'=>'TICKET','price_cents'=>5000,'max_quantity'=>1,'choice_group'=>'room'],['code'=>'double','name'=>'Doppia','scope'=>'TICKET','price_cents'=>3000,'max_quantity'=>1,'choice_group'=>'room']];
$options=MI_Registration_Service::validate_options(['single'=>0,'double'=>1],$definitions,'TICKET');verify(is_array($options)&&count($options)===1&&$options[0]['code']==='double'&&$options[0]['unit_price_cents']===3000,'Cambio sistemazione valido');
foreach([['single'=>1,'double'=>1],['double'=>2],['double'=>-1],['unknown'=>1],['double'=>'1.5']] as $invalid)verify(MI_Registration_Service::validate_options($invalid,$definitions,'TICKET') instanceof WP_Error,'Servizi invalidi accettati');
verify(MI_Registration_Service::validate_options(['double'=>1],$definitions,'ORDER') instanceof WP_Error,'Ambito opzione errato accettato');
echo "Servizi: cambio singola/doppia, alternative, quantità, prezzi e ambito verificati.\n";
