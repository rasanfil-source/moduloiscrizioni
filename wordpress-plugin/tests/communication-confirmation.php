<?php
define('ABSPATH', __DIR__); define('ARRAY_A','ARRAY_A'); define('MINUTE_IN_SECONDS',60);
class Redirected extends Exception {}
function is_user_logged_in(){return true;}
function current_user_can($cap){return true;}
function check_admin_referer($a,$b){if(empty($GLOBALS['nonce']))throw new Exception('nonce');}
function sanitize_key($s){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',$s));}
function sanitize_textarea_field($s){return strip_tags($s);}
function sanitize_text_field($s){return strip_tags($s);}
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
function add_query_arg(...$args){return 'https://example.invalid/';}
function get_permalink(){return 'https://example.invalid/';}
function get_posts(){return [];}
function home_url(){return 'https://example.invalid/';}
function wp_safe_redirect($u){throw new Redirected($u);}
function is_wp_error($v){return false;}
class MI_Access{static function can_access_event($id){return true;}}
class MI_Payment_Ledger{static function positions($rows){return [1=>['paid'=>0,'balance'=>100,'managed'=>true]];}}
class MI_Spedizione_Email{
 static $calls=[]; static $mode='OPERATIVO';
 static function modalita(){return self::$mode;}
 static function accoda_comunicazione_operativa($p){self::$calls[]=$p;return ['count'=>1,'mode'=>self::$mode];}
 static function stato_nuova_email($s){return 'TEST_PENDING';}
}
class FakeDB{public $prefix='wp_',$last_error='';public $email='a@example.invalid';function prepare($s,...$args){return $s;}function get_results(){return [['id'=>1,'order_code'=>'A','buyer_email'=>$this->email,'total_cents'=>100,'economic_mode'=>'MANAGED']];}}
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
