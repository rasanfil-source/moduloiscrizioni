<?php
// Isolated report rendering: real reader/template, synthetic WordPress and database.
define('ABSPATH', __DIR__); define('ARRAY_A', 'ARRAY_A');
$caps=['mi_portal_access'=>true,'mi_view_registrations'=>true,'mi_manage_payments'=>true]; $logged=true; $no_events=false;
function current_user_can($cap){return $GLOBALS['caps'][$cap]??false;}
function is_user_logged_in(){return $GLOBALS['logged'];}
function is_ssl(){return true;}
function wp_logout_url($url){return home_url('logout');}
class MI_Portal_Management {static function url($id=0){return home_url('?mi_portal_view=management');}}
function absint($v){return abs((int)$v);}
function wp_unslash($v){return $v;}
function sanitize_key($v){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',$v));}
function sanitize_text_field($v){return strip_tags($v);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_attr($v){return esc_html($v);}
function esc_url($v){return esc_html($v);}
function esc_url_raw($v){return $v;}
function esc_html__($v,$domain){return esc_html($v);}
function wp_kses_post($v){return $v;}
function wp_die($v){throw new RuntimeException($v);}
function home_url($p=''){return 'https://report.invalid/'.$p;}
function admin_url($p){return home_url('wp-admin/'.$p);}
function get_option($k,$default=[]){return $default;}
function get_posts($args=[]){return $GLOBALS['no_events']||($args['post_type']??'')==='page'?[]:[(object)['ID'=>42,'post_title'=>'Evento dimostrativo']];}
function add_query_arg($args,$url=null,$third=null){if(!is_array($args)){ $args=[$args=>$url];$url=$third; }$url=$url?:home_url('?'.http_build_query($_GET));$p=parse_url(html_entity_decode($url));parse_str($p['query']??'',$q);$q=array_merge($q,$args);return ($p['scheme']??'https').'://'.($p['host']??'report.invalid').($p['path']??'/').'?'.http_build_query($q);}
function remove_query_arg($keys,$url=null){$url=$url?:home_url('?'.http_build_query($_GET));$p=parse_url($url);parse_str($p['query']??'',$q);foreach((array)$keys as $key)unset($q[$key]);return home_url('?'.http_build_query($q));}
function wp_nonce_url($url,$action){return add_query_arg('_wpnonce','fixture',$url);}
function selected($a,$b,$echo=true){$s=(string)$a===(string)$b?'selected="selected"':'';if($echo)echo $s;return $s;}
function number_format_i18n($v,$n=0){return number_format($v,$n,',','.');}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function wp_date($f,$t,$tz){return (new DateTimeImmutable('@'.$t))->setTimezone($tz)->format($f);}
function paginate_links($args){return '<a class="page-numbers" href="'.esc_url(str_replace('%#%','2',$args['base'])).'">2</a>';}
class MI_Access {static $scope=[42];static $suspended=false;static function event_ids(){return self::$scope;}static function can_access_event($id){return self::$scope==='ALL'||in_array($id,self::$scope,true);}static function is_suspended(){return self::$suspended;}}
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';}
class MI_Spedizione_Email {static $mode='OPERATIVO';static $ready=true;static function modalita(){return self::$mode;}static function stato_nuova_email($a){return self::$ready?'TEST_PENDING':'DISABLED';}}
class ReportDB {
 public $prefix='wp_',$posts='wp_posts',$queries=[];
 function prepare($q,...$args){$args=is_array($args[0]??null)?$args[0]:$args;return preg_replace_callback('/%[ds]/',function($m)use(&$args){$v=array_shift($args);return $m[0]==='%d'?(string)(int)$v:"'".addslashes($v)."'";},$q);}
 function get_results($q,$mode){$this->queries[]=$q;return [['effective_at'=>'2026-09-24 08:00:00','order_code'=>'DEMO-001','event_title'=>'Evento dimostrativo','transaction_kind'=>'PAYMENT','installment_kind'=>'BALANCE','amount_cents'=>12500,'payment_source'=>'CARD','external_reference'=>'Riferimento <sicuro>','operator_label'=>'Operatore demo']];}
 function get_var($q){$this->queries[]=$q;return 101;}
 function get_row($q,$mode){$this->queries[]=$q;return ['payments'=>12500,'refunds'=>1500,'bank_transfers'=>0,'cards'=>12500,'cash'=>-1500];}
}
$wpdb=new ReportDB();
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-portal-payments.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-admin.php';
function report_html($portal=true){ob_start();MI_Admin::payments_page($portal);return ob_get_clean();}
function check($v,$message){if(!$v)throw new RuntimeException($message);}
$_GET=['mi_portal'=>1,'mi_portal_view'=>'payment-report','payment_event_id'=>42,'payment_source'=>'CARD','transaction_kind'=>'PAYMENT','payment_from'=>'2026-09-01','payment_to'=>'2026-09-30'];
if(($argv[1]??'')==='--html'){parse_str($argv[2]??'', $query);$_GET=array_merge($_GET,$query);echo MI_Portal::render();exit;}
check(str_contains(MI_Portal::render(),'mi-payment-report-title'),'Portal route missing');$wpdb->queries=[];
$portal=report_html();$queries=$wpdb->queries;$wpdb->queries=[];report_html(false);check($queries===$wpdb->queries,'Admin and portal queries differ');
check(substr_count($portal,'scope="col"')===9,'Missing report columns');
check(str_contains($portal,'Riferimento &lt;sicuro&gt;'),'Escaping lost');
check(str_contains($portal,'-15,00'),'Signed totals lost');
check(!str_contains($portal,'edit.php'),'Admin page link remains');
preg_match('/href="([^"]+mi_export_payments[^"]+)"/',$portal,$match);parse_str(parse_url(html_entity_decode($match[1]),PHP_URL_QUERY),$export);
foreach(['payment_event_id','payment_source','transaction_kind','payment_from','payment_to'] as $key)check((string)$export[$key]===(string)$_GET[$key],'Export filter lost: '.$key);
check(isset($export['_wpnonce']),'Missing export nonce');check(str_contains($portal,'paged=2'),'Pagination missing');
$caps['mi_manage_payments']=false;check(str_contains(report_html(),'Consultazione soltanto'),'Read-only report missing');check(!str_contains(report_html(),'Torna ai pagamenti'),'Read-only payment access');
$caps['mi_view_registrations']=false;check(!MI_Portal_Payments::report_allowed(),'Missing capability accepted');$caps['mi_view_registrations']=true;
$logged=false;check(!MI_Portal_Payments::report_allowed(),'Anonymous accepted');$logged=true;MI_Access::$suspended=true;check(!MI_Portal_Payments::report_allowed(),'Suspended accepted');MI_Access::$suspended=false;
$_GET['payment_event_id']=99;$wpdb->queries=[];report_html();foreach($wpdb->queries as $q)check(str_contains($q,'WHERE 1 = 0'),'Unauthorized event scope lost');
$_GET['payment_event_id']=0;MI_Access::$scope=[];$wpdb->queries=[];report_html();foreach($wpdb->queries as $q)check(str_contains($q,'IN (0)'),'Empty scope broadened');
$no_events=true;$_GET=[];$method=new ReflectionMethod('MI_Portal','communications_view');
foreach([['OPERATIVO',true,''],['PROVA',true,'esclusivamente'],['PROVA',false,'Configura un indirizzo'],['ANTEPRIMA',false,'Preparazione senza invio']] as [$mode,$ready,$expected]){MI_Spedizione_Email::$mode=$mode;MI_Spedizione_Email::$ready=$ready;ob_start();$method->invoke(null);$html=ob_get_clean();check(!str_contains($html,'Modalità operativa'),'Operational notice remains');check($expected?str_contains($html,$expected):!str_contains($html,'mi-portal-notice'),'Other notice changed');}
echo "PASS: report query parity, columns, escaping, totals, CSV filters and nonce, pagination, access and event scopes, communication notices.\n";
