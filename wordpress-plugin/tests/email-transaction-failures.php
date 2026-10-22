<?php
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);
class WP_Error {function __construct(public $code,public $message,public $data=null){}}
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower($v);}
function sanitize_text_field($v){return $v;}
function sanitize_textarea_field($v){return $v;}
function sanitize_email($v){return $v;}
function is_email($v){return true;}
function get_option($k,$default=null){return ['mi_modalita_spedizione_email'=>'PROVA','mi_destinatario_prova_email'=>'test@example.invalid'][$k]??$default;}
function get_post_status($id){return 'publish';}
function get_transient($k){return $GLOBALS['receipt']??false;}
function set_transient(...$args){if($GLOBALS['failure']==='receipt')throw new RuntimeException('cache unavailable');$GLOBALS['receipt']=true;}
function wp_next_scheduled(...$args){return false;}
function wp_schedule_single_event(...$args){if($GLOBALS['failure']==='scheduler')throw new RuntimeException('cron unavailable');}
function current_time(...$args){return gmdate('Y-m-d H:i:s');}
function wp_json_encode($v){return json_encode($v);}
class MI_Registration_Service {static function public_event(...$args){return ['title'=>'Evento sintetico'];}}
class MI_Payment_Ledger {static function positions($rows){return [1=>['effective_paid'=>0,'effective_balance'=>100,'managed'=>true,'effective_total'=>100,'individual_known'=>false,'individual_people_count'=>1,'individual_active_count'=>1]];}}
class MI_Portal {static function status_url(...$args){return 'https://example.invalid/status';}}
class MI_Modello_Email {static function valori_ordine(...$args){return [];}static function crea_istantanea_operativa(...$args){return ['attivo'=>true];}}
class EmailFaultDatabase {
 public $prefix='wp_',$last_error='', $rows=0,$snapshot=null,$commits=0;
 function prepare($sql,...$args){return $sql;}
 function get_var($sql){return 1;}
 function get_results(...$args){return [['id'=>1,'order_code'=>'TEST1','status'=>'CONFIRMED','economic_mode'=>'FULL_PAYMENT','buyer_first_name'=>'Persona','buyer_last_name'=>'Test','buyer_email'=>'test@example.invalid','total_qty'=>1,'total_cents'=>100,'initial_due_cents'=>100,'balance_cents'=>100,'payment_methods_json'=>'[]']];}
 function query($sql){
  if($sql===$GLOBALS['failure'])return false;
  if($sql==='START TRANSACTION')$this->snapshot=$this->rows;
  elseif($sql==='COMMIT'){$this->commits++;$this->snapshot=null;}
  elseif($sql==='ROLLBACK'&&$this->snapshot!==null){$this->rows=$this->snapshot;$this->snapshot=null;}
  elseif(str_starts_with($sql,'INSERT')){$inserted=$this->rows===0;$this->rows=1;return (int)$inserted;}
  return 1;
 }
}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
$payload=['event_id'=>42,'communication_id'=>'synthetic-communication','template_type'=>'EVENT_NOTICE','allow_operational'=>true,'recipients'=>[['order_code'=>'TEST1']]];
$failed=0;
foreach(['START TRANSACTION','COMMIT','receipt','scheduler','none'] as $failure){
 $wpdb=new EmailFaultDatabase();$receipt=false;
 try{$result=MI_Spedizione_Email::accoda_comunicazione_operativa($payload);}catch(Throwable $e){$result=new WP_Error('uncaught',$e->getMessage());}
 $success=in_array($failure,['receipt','scheduler','none'],true);
 $ok=$success?!is_wp_error($result)&&$wpdb->rows===1&&$wpdb->commits===1:is_wp_error($result)&&$wpdb->rows===0&&!$receipt;
 echo ($ok?'PASS: ':'FAIL: ').'email '.$failure."\n";if(!$ok)$failed++;
 if($success){$result=MI_Spedizione_Email::accoda_comunicazione_operativa($payload);$ok=!is_wp_error($result)&&$result['count']===0&&$wpdb->rows===1;echo ($ok?'PASS: ':'FAIL: ').'email retry '.$failure."\n";if(!$ok)$failed++;}
}
exit($failed?1:0);
