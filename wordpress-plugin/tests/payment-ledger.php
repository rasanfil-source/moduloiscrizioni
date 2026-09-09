<?php
// Eseguire con PHP CLI e mbstring; nessuna connessione al sito.
define('ABSPATH', __DIR__);
function wp_timezone() { return new DateTimeZone('Europe/Rome'); }
function sanitize_text_field($v) { return strip_tags((string)$v); }
function sanitize_textarea_field($v) { return strip_tags((string)$v); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
$base = ['request_id'=>'wp_7_12345678-1234-4234-8234-123456789abc','importo'=>'12,34','tipo'=>'INCASSO','metodo'=>'BONIFICO','data'=>'2026-09-09'];
function check($condition,$message) { if (!$condition) throw new RuntimeException($message); }
function rejected($base,$patch) {
 try { MI_Payment_Ledger::normalize(array_replace($base,$patch)); }
 catch (InvalidArgumentException $e) { return; }
 throw new RuntimeException('Input accettato indebitamente: '.json_encode($patch));
}
$p=MI_Payment_Ledger::normalize($base);
check($p['amount_cents']===1234,'Centesimi esatti');
check($p['effective_at']==='2026-09-08 22:00:00','Data locale convertita in UTC');
check($p['transaction_kind']==='PAYMENT','Incasso');
$p=MI_Payment_Ledger::normalize(array_replace($base,['tipo'=>'STORNO']));
check($p['transaction_kind']==='REFUND' && $p['movement_kind']==='STORNO','Storno distinto e sottrattivo');
foreach (['0','-1','1.001','1e3','1.000,00','42949673','NaN'] as $amount) rejected($base,['importo'=>$amount]);
foreach (['2026-02-30','2026-13-01','09/09/2026'] as $date) rejected($base,['data'=>$date]);
rejected($base,['request_id'=>'wp_7_------------------------------------']);
rejected($base,['metodo'=>'ALTRO']);
rejected($base,['tipo'=>'ALTRO']);
rejected($base,['nota'=>'4111 1111 1111 1111']);
echo "Controlli PHP del registro pagamenti superati.\n";

define('ARRAY_A','ARRAY_A');
class WP_Error { public $code; function __construct($code,$message){$this->code=$code;} }
class MI_Access { static function can_access_event($id){return $id===42;} }
class MI_Portal_Payments { static function allowed(){return true;} }
class MI_Registration_Service {
 static function append_registration_event(...$args){return true;}
 static function accoda_iscrizione_workspace($id){throw new RuntimeException('Cron non disponibile');}
}
function get_current_user_id(){return 7;}
function wp_get_current_user(){return (object)['display_name'=>'Operatore prova'];}
function wp_json_encode($v){return json_encode($v);}
function current_time(...$args){return '2026-09-09 10:00:00';}
class LedgerDatabase {
 public $prefix='wp_', $last_error='', $insert_id=0, $payments=[], $failUpdate=false;
 public $registration=['id'=>1,'event_id'=>42,'workspace_revision'=>0,'status'=>'PENDING_PAYMENT','total_cents'=>10000,'initial_due_cents'=>2000,'payment_deadline_at'=>'2026-10-01 10:00:00'];
 private $snapshot;
 function prepare($sql,...$args){return [$sql,$args];}
 function query($sql){
  if($sql==='START TRANSACTION')$this->snapshot=[$this->payments,$this->registration];
  if($sql==='ROLLBACK')[$this->payments,$this->registration]=$this->snapshot;
  return 1;
 }
 function get_row($query,$mode){
  [$sql,$args]=$query;
  if(str_contains($sql,'mi_registrations'))return $args[0]===1?$this->registration:null;
  foreach($this->payments as $p)if($p['origin_id']===$args[0])return $p;
  return null;
 }
 function get_var($query){return array_sum(array_map(fn($p)=>($p['transaction_kind']==='REFUND'?-1:1)*$p['amount_cents'],$this->payments));}
 function insert($table,$data){$data['id']=++$this->insert_id;$this->payments[]=$data;return 1;}
 function update($table,$changes,$where){if($this->failUpdate)return false;$this->registration=array_replace($this->registration,$changes);return 1;}
}
$wpdb=new LedgerDatabase();
$input=array_replace($base,['importo'=>'30']);
$saved=MI_Payment_Ledger::save(1,$input);
check($saved['saved']===true && count($wpdb->payments)===1,'Salvataggio anche con cron non disponibile');
check($wpdb->registration['workspace_revision']===1,'Nuovo movimento incrementa revisione');
check($wpdb->registration['status']==='CONFIRMED' && $wpdb->registration['workspace_status']==='PENDING','Stato e coda atomici');
$retry=MI_Payment_Ledger::save(1,$input);
check($retry['replayed']===true && count($wpdb->payments)===1,'Retry non duplica');
check($wpdb->registration['workspace_revision']===1,'Retry non incrementa revisione');
check(MI_Payment_Ledger::save(1,array_replace($input,['importo'=>'31']))['saved']===false,'Identificativo con contenuto diverso');
$second=array_replace($input,['request_id'=>str_replace('abc','abd',$input['request_id'])]);
check(MI_Payment_Ledger::save(1,array_replace($second,['importo'=>'71']))['saved']===false,'No sovrapagamento');
check(MI_Payment_Ledger::save(1,array_replace($second,['tipo'=>'RIMBORSO','importo'=>'31']))['saved']===false,'No rimborso eccessivo');
$wpdb->failUpdate=true;
check(MI_Payment_Ledger::save(1,$second) instanceof WP_Error,'Errore database segnalato');
check(count($wpdb->payments)===1,'Rollback del movimento quando fallisce aggiornamento');
$wpdb->failUpdate=false;
$refund=MI_Payment_Ledger::save(1,array_replace($second,['tipo'=>'STORNO','importo'=>'20']));
check($refund['saved']===true && $wpdb->registration['status']==='PENDING_PAYMENT','Storno ricalcola stato');
echo "Retry, saldo, rollback e coda verificati con database simulato.\n";
