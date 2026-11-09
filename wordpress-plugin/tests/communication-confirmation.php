<?php
define('ABSPATH', __DIR__); define('ARRAY_A','ARRAY_A'); define('MINUTE_IN_SECONDS',60);
class Redirected extends Exception {}
function is_user_logged_in(){return true;}
function current_user_can($cap){return true;}
function check_admin_referer($a,$b){if(empty($GLOBALS['nonce']))throw new Exception('nonce');}
function sanitize_key($s){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',$s));}
function sanitize_textarea_field($s){return strip_tags($s);}
function sanitize_text_field($s){return strip_tags($s);}
function sanitize_email($s){return filter_var($s,FILTER_SANITIZE_EMAIL);}
function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function wp_unslash($v){return $v;}
function absint($v){return abs((int)$v);}
function get_current_user_id(){return 7;}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}
function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=$v;return true;}
function delete_transient($k){unset($GLOBALS['transients'][$k]);}
function get_option($k,$default=[]){return $default;}
function get_post_status($id){return 'publish';}
function wp_generate_password(){return 'testtoken';}
function wp_json_encode($v){return json_encode($v);}
function add_query_arg(...$args){return 'https://example.invalid/?'.http_build_query(is_array($args[0])?$args[0]:[$args[0]=>$args[1]]);}
function get_permalink(){return 'https://example.invalid/';}
function get_posts($query=[]){$GLOBALS['event_query']=$query;return $GLOBALS['fixture_events']??[];}
function home_url(){return 'https://example.invalid/';}
function wp_safe_redirect($u){throw new Redirected($u);}
function is_wp_error($v){return false;}
class MI_Access{static function can_access_event($id){return true;}static function event_ids(){return 'ALL';}}
class MI_Payment_Ledger{static function positions($rows){return [1=>['paid'=>0,'balance'=>100,'effective_paid'=>0,'effective_balance'=>100,'managed'=>true]];}}
class MI_Spedizione_Email{
 static $calls=[]; static $mode='OPERATIVO';
 static function modalita(){return self::$mode;}
 static function accoda_comunicazione_operativa($p){self::$calls[]=$p;return ['count'=>1,'mode'=>self::$mode,'outbox_id'=>99];}
 static function spedisci_coda($id){throw new Exception('HTTP request must never wait for Google');}
 static function stato_nuova_email($s){return 'TEST_PENDING';}
 static function destinatario_evento($id){return 'group@example.invalid';}
}
class MI_Email_Jobs {
 static function enqueue($p){$r=MI_Spedizione_Email::accoda_comunicazione_operativa($p);$r['job_id']=str_repeat('b',64);return $r;}
 static function render_progress($id){}
}
class FakeDB{public $prefix='wp_',$last_error='';public $email='a@example.invalid';function prepare($s,...$args){return $s;}function get_row(){return ['status'=>'SENT','last_error'=>''];}function get_results(){return [['id'=>1,'order_code'=>'A','buyer_email'=>$this->email,'total_cents'=>100,'economic_mode'=>'MANAGED']];}}
$wpdb=new FakeDB();$nonce=true;$transients=[];
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-modello-email.php';
$handler=new ReflectionMethod('MI_Portal','handle_communication_action');
function run_action(){global $handler;try{$handler->invoke(null);}catch(Redirected $e){}}
$_POST=['event_id'=>1,'template_type'=>'PRE_DEPARTURE_REMINDER','message'=>'**Ore 8**'];
run_action(); if(MI_Spedizione_Email::$calls)throw new Exception('Preview sent mail');
$draft=$transients['mi_comm_7_testtoken'];
$_POST=['communication_token'=>'testtoken']; run_action();
if(count(MI_Spedizione_Email::$calls)!==1 || !MI_Spedizione_Email::$calls[0]['allow_operational'])throw new Exception('Confirmation failed');
run_action();if(count(MI_Spedizione_Email::$calls)!==1)throw new Exception('Replay accepted');
$transients['mi_comm_7_testtoken']=$draft;$wpdb->email='changed@example.invalid';run_action();
if(count(MI_Spedizione_Email::$calls)!==1)throw new Exception('Changed recipients accepted');
$wpdb->email='a@example.invalid';MI_Spedizione_Email::$mode='PROVA';run_action();
if(count(MI_Spedizione_Email::$calls)!==1)throw new Exception('Changed mode accepted');
$nonce=false;try{run_action();throw new Exception('Invalid nonce accepted');}catch(Exception $e){if($e->getMessage()!=='nonce')throw $e;}
$html=MI_Modello_Email::formatta_comunicazione("**Ore 8**\n*puntuali*\n- acqua\n- pranzo\n<script>alert(1)</script>");
foreach(['<strong>Ore 8</strong>','<em>puntuali</em>','<ul><li>acqua</li><li>pranzo</li></ul>','&lt;script&gt;'] as $part)if(!str_contains($html,$part))throw new Exception('Formatting failed: '.$part);
if(str_contains($html,'<script>'))throw new Exception('Unsafe HTML');
echo "PASS: preview does not send; confirmation; replay; recipient/mode changes; nonce; safe formatting.\n";
$nonce=true;MI_Spedizione_Email::$mode='OPERATIVO';$transients['mi_comm_7_testtoken']=$draft;MI_Spedizione_Email::$calls=[];
$_POST=['communication_token'=>'testtoken','communication_test'=>'1','communication_test_request'=>str_repeat('a',32),'communication_test_recipient'=>'chosen@example.invalid'];
for($i=0;$i<2;$i++){
 try{$handler->invoke(null);}catch(Redirected $e){if(!str_contains($e->getMessage(),'mi_comm_preview=testtoken'))throw new Exception('Test lost preview');}
}
if(!isset($transients['mi_comm_7_testtoken']))throw new Exception('Test consumed draft');
if(count(MI_Spedizione_Email::$calls)!==2||MI_Spedizione_Email::$calls[0]['communication_id']!==MI_Spedizione_Email::$calls[1]['communication_id'])throw new Exception('Duplicate POST changed idempotency key');
if(MI_Spedizione_Email::$calls[0]['allow_operational']||!MI_Spedizione_Email::$calls[0]['test_only']||count(MI_Spedizione_Email::$calls[0]['recipients'])!==1)throw new Exception('Test scope escaped');
echo "PASS: test preserves preview and draft; duplicate POST retains queue key; targeted test only.\n";
function esc_attr($s){return esc_html($s);}
function esc_url($s){return $s;}
function esc_textarea($s){return esc_html($s);}
function get_the_title($id){return 'Evento di prova';}
function get_post_meta($id,$key,$single=true){return $key==='_mi_event_starts_at'?'2099-12-31T12:00':'';}
function wp_date($format){return '2026-11-09';}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function current_time(...$args){return 1794182400;}
function wp_nonce_field(...$args){}
function wp_create_nonce(...$args){return 'nonce';}
function admin_url($path){return 'https://example.invalid/wp-admin/'.$path;}
function selected($a,$b,$echo=false){return (string)$a===(string)$b?'selected="selected"':'';}
class MI_Event_Post_Type{const EVENT_TYPE='mi_event';}
$fixture_events=[(object)['ID'=>1,'post_title'=>'Evento di prova']];
$transients['mi_comm_7_testtoken']['template_type']='BALANCE_REMINDER';
$transients['mi_comm_7_testtoken']['message']='Testo personalizzato da conservare';
$_GET=['mi_comm_edit'=>'testtoken'];
$view=new ReflectionMethod('MI_Portal','communications_view');ob_start();$view->invoke(null);$html=ob_get_clean();
if(!str_contains($html,'Testo personalizzato da conservare</textarea>')||!preg_match('/value="BALANCE_REMINDER"[^>]*selected="selected"/',$html))throw new Exception('Editing lost type/message');
if(isset($event_query['meta_query'])||empty($event_query['update_post_meta_cache'])||str_contains($html,'data-mi-event-period="past"'))throw new Exception('Heavy metadata joins or eagerly rendered history');
$_GET=['mi_comm_preview'=>'testtoken'];ob_start();$view->invoke(null);$html=ob_get_clean();
if(!str_contains($html,'mi_comm_edit=testtoken')||!str_contains($html,'communication_test_request'))throw new Exception('Preview lacks editing/request identity');
echo "PASS: editing preserves event, type and custom text; history deferred; preview links to same draft.\n";
