<?php
// Prove locali: nessun database reale, invio email o richiesta di rete.
define('ABSPATH', __DIR__); define('ARRAY_A', 'ARRAY_A');
define('HOUR_IN_SECONDS', 3600); define('MINUTE_IN_SECONDS', 60);
define('MI_PLUGIN_URL', 'https://example.invalid/plugin/'); define('MI_VERSION', 'audit');
class WP_Error { public function __construct(public $code, public $message='') {} function get_error_message(){return $this->message;} function get_error_code(){return $this->code;} }
function is_wp_error($v) { return $v instanceof WP_Error; }
function absint($v) { return abs((int)$v); }
function wp_json_encode($v) { return json_encode($v); }
function wp_timezone() { return new DateTimeZone('UTC'); }
function current_time(...$args) { return gmdate('Y-m-d H:i:s'); }
function sanitize_key($v) { return strtolower($v); }
function sanitize_text_field($v) { return trim((string)$v); }
function sanitize_email($v) { return $v; }
function is_email($v) { return str_contains($v, '@'); }
function esc_html($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($v, ...$args) { return $v; }
function wp_http_validate_url($v) { return $v; }
function wp_parse_url($v) { return parse_url($v); }
function get_the_title($id) { return 'Evento dimostrativo'; }
function wp_strip_all_tags($v) { return strip_tags($v); }
function get_post_thumbnail_id($id) { return 0; }
function home_url(...$args) { return 'https://example.invalid/'; }
function add_query_arg(...$args) { return 'https://example.invalid/gestione'; }
function user_can(...$args) { return true; }
function get_current_user_id() { return 7; }
function update_option($key,$value,...$args){$GLOBALS['options'][$key]=$value;}
function get_option($key, $fallback=false) { return $GLOBALS['options'][$key] ?? $fallback; }
function get_post_meta($id, $key, ...$args) {
    if ($key === '_mi_payment_deadline_at') {
        $GLOBALS['checked_events'][] = $id;
        return $id === 999 ? gmdate('Y-m-d\TH:i', time()-3600) : '';
    }
    return '';
}
function wp_schedule_single_event(...$args) { $GLOBALS['scheduled'][]=$args; return true; }
function wp_safe_remote_post($url, $args) {
    $GLOBALS['sent'][]=$url;
    return ['status'=>str_contains($url, '/failing') ? 503 : 201];
}
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return ''; }
class MI_Access { static function is_suspended(...$args){return false;} static function can_access_event(...$args){return true;} }
class MI_Event_Deletion {
    static function release($id){$GLOBALS['released'][]=$id;}
    static function registration_event($id) { return $id; }
    static function enter($id) { $GLOBALS['expiry_attempts'][]=$id; return new WP_Error('audit_stop_before_write'); }
}
class MI_Modello_Email { static function crea_istantanea(...$args) { return []; } }
class MI_Portal { static function balance_url(...$args) { return 'https://example.invalid/saldo'; } }
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-payment-people.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-booking-update-email.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-registration-service.php';
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-web-push.php';
class AuditDatabase {
    public $prefix='wp_', $last_error='', $offers=[], $subscriptions=[], $onlyLate=false;
    public $registration=['id'=>1,'event_id'=>42,'status'=>'CONFIRMED','buyer_email'=>'persona@example.invalid','economic_mode'=>'FULL_PAYMENT','total_cents'=>20000,'initial_due_cents'=>20000,'order_code'=>'DEMO','snapshot_json'=>'{}','order_options_json'=>'[]'];
    public $people=[];
    function prepare($sql,...$args) { foreach($args as $arg)$sql=preg_replace('/%[ds]/',(string)$arg,$sql,1);return $sql; }
    function get_row($sql,...$args) { return $this->registration; }
    function get_results($sql,...$args) {
        if (str_contains($sql, "status='WAITLIST_OFFERED'")) {
            preg_match('/id>(\d+)/',$sql,$cursor);$rows=array_values(array_filter($this->offers,fn($r)=>$r['id']>(int)($cursor[1]??0)));
            usort($rows,fn($a,$b)=>$a['id']<=>$b['id']);
            preg_match('/LIMIT (\d+)/',$sql,$m);
            return array_slice($rows,0,isset($m[1])?(int)$m[1]:count($rows));
        }
        if (str_contains($sql,'mi_web_push_subscriptions')) {if(preg_match('/id IN \(([^)]+)\)/',$sql,$m))return array_values(array_filter($this->subscriptions,fn($r)=>in_array($r['id'],array_map('intval',explode(',',$m[1])),true)));return $this->subscriptions;}
        if (str_contains($sql,'mi_participants')) return $this->people;
        if (str_contains($sql,'mi_registration_items')) return [['ticket_type_code'=>'base','unit_price_cents'=>10000]];
        if (str_contains($sql,'mi_payments')) return [];
        throw new RuntimeException('Query non prevista: '.$sql);
    }
}
function evidence($label,$data) { echo json_encode(['case'=>$label]+$data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n"; }
$wpdb=new AuditDatabase;
foreach (['ACTIVE','CANCELLED'] as $i=>$status) $wpdb->people[]=['id'=>$i+1,'ticket_type_code'=>'base','first_name'=>'Persona','last_name'=>(string)($i+1),'options_json'=>'[]','room_code'=>'','extra_json'=>'{}','status'=>$status];
$message=MI_Booking_Update_Email::snapshot(1);
$position=MI_Payment_People::read($wpdb->registration,[]);
$summary=MI_Payment_People::summary($position);
evidence('email_after_partial_cancellation',['active_total_cents'=>$summary['total'],'message'=>$message['email_preview']['testo']]);
if ($summary['total']!==10000 || !str_contains($message['email_preview']['testo'],'100,00 EUR') || str_contains($message['email_preview']['testo'],'200,00 EUR')) throw new RuntimeException('Email includes cancelled participant total');

for($i=1;$i<=200;$i++) $wpdb->offers[]=['id'=>$i,'event_id'=>42,'waitlist_offer_expires_at'=>gmdate('Y-m-d H:i:s',time()+3600)];
$wpdb->offers[]=['id'=>201,'event_id'=>999,'waitlist_offer_expires_at'=>gmdate('Y-m-d H:i:s',time()+7200)];
$GLOBALS['checked_events']=[]; $GLOBALS['expiry_attempts']=[];
for($i=0;$i<2;$i++) MI_Registration_Service::expire_due_waitlist_offers();
evidence('waitlist_scan_reaches_second_page',['runs'=>2,'event_999_checked'=>in_array(999,$GLOBALS['checked_events'],true),'expiry_attempts'=>$GLOBALS['expiry_attempts']]);
if (!in_array(999,$GLOBALS['checked_events'],true) || get_option('mi_waitlist_expiry_cursor')!==0) throw new RuntimeException('Waitlist scan starved');
$cursor=get_option('mi_waitlist_expiry_cursor');$wpdb->last_error='db unavailable';MI_Registration_Service::expire_due_waitlist_offers();if(get_option('mi_waitlist_expiry_cursor')!==$cursor)throw new RuntimeException('Failed query advanced cursor');$wpdb->last_error='';
evidence('waitlist_control_without_first_200',['expiry_attempts'=>$GLOBALS['expiry_attempts']]);
if ($GLOBALS['expiry_attempts']!==[201] || $GLOBALS['released']!==[999]) throw new RuntimeException('Controllo della scadenza fallito');

echo "PASS active email totals and bounded waitlist scan with database failure.\n";
