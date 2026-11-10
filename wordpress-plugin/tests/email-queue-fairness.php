<?php
// Database e trasporto in memoria; viene usato il gestore delle consegne applicativo.
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
define('MINUTE_IN_SECONDS', 60);
class WP_Error {
    function __construct(public $code, public $message, public $data = null) {}
    function get_error_code() { return $this->code; }
    function get_error_message() { return $this->message; }
    function get_error_data() { return $this->data; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function absint($v) { return abs((int)$v); }
function sanitize_email($v) { return $v; }
function esc_html($v) { return htmlspecialchars($v); }
function sanitize_text_field($v) { return $v; }
function is_email($v) { return filter_var($v, FILTER_VALIDATE_EMAIL); }
function current_time(...$args) { return gmdate('Y-m-d H:i:s'); }
function home_url() { return 'https://example.invalid'; }
function wp_salt($v) { return 'synthetic-only'; }
function update_option($key,$value,$autoload=false) { $GLOBALS['auditOptions'][$key]=$value; return true; }
function get_option($key, $default = false) {
    if(array_key_exists($key,$GLOBALS['auditOptions']??[]))return $GLOBALS['auditOptions'][$key];
    return [
        'mi_modalita_spedizione_email' => 'OPERATIVO',
        'mi_destinatario_prova_email' => 'test@example.invalid',
        'mi_prova_email_verificata' => hash_hmac('sha256', 'test@example.invalid', wp_salt('auth')),
    ][$key] ?? $default;
}
function wp_next_scheduled(...$args) { return false; }
function sanitize_key($v){return strtolower($v);}
function add_action(...$args){}
function wp_unschedule_event(...$args){}
function wp_schedule_single_event(...$args) { $GLOBALS['scheduled']++; return true; }
class MI_Event_Deletion {
    static $read_failure = false;
    static function registration_event($registration) {
        global $wpdb;
        $wpdb->last_error = self::$read_failure ? 'SQL failure' : '';
        return self::$read_failure || $registration === 99 ? 0 : 43;
    }
    static $blocked = true;
    static function enter($event) { return self::$blocked && $event === 42 ? new WP_Error('mi_event_deleting', 'Synthetic paused deletion') : true; }
    static function release($event) {}
}
class MI_Modello_Email {
    const EMAIL_SEGRETERIA = 'sender@example.invalid';
    const NOME_SEGRETERIA = 'Synthetic sender';
    static function ripara_istantanea_codifica($v) { return $v; }
    static function componi_html($v, $code) { return $v['html']; }
    static function componi_testo($v) { return $v['testo']; }
}
class MI_Workspace_Client {
    static $recipients = [];
    static $calls=0;
    static function request($action, $payload) {
        if($action!=='INVIA_EMAIL_LOTTO')throw new Exception('Expected batch transport');
        self::$calls++;$results=[];
        foreach($payload['messages'] as $p){self::$recipients[]=$p['destinatario'];$results[$p['delivery_key']]=['ok'=>true,'channel'=>'GOOGLE_WORKSPACE'];}
        return ['ok'=>true,'channel'=>'GOOGLE_WORKSPACE','results'=>$results];
    }
}
class AuditQueueDatabase {
    public $prefix = 'wp_', $last_error = '', $rows = [];
    function get_row($sql,$format) {
        if (!str_contains($sql,'SELECT economic_mode,total_cents')) throw new RuntimeException('Unexpected registration read');
        return ['economic_mode'=>'REGISTRATION_ONLY','total_cents'=>0];
    }
    function prepare($sql, ...$args) {
        foreach ($args as $arg) $sql = preg_replace_callback('/%[ds]/', fn($m) => $m[0] === '%d' ? (string)(int)$arg : "'" . str_replace("'", "''", (string)$arg) . "'", $sql, 1);
        return $sql;
    }
    function query($sql) {
        if (preg_match("/SET status = '(SENDING|TEST_SENDING)', attempts = attempts \\+ 1, processing_started_at = '([^']+)' WHERE id = (\\d+) AND status = '(PENDING|TEST_PENDING)'/", $sql, $m)) {
            if ($this->rows[$m[3]]['status'] !== $m[4]) return 0;
            $this->rows[$m[3]]['status'] = $m[1];
            $this->rows[$m[3]]['attempts']++;
            $this->rows[$m[3]]['processing_started_at'] = $m[2];
            return 1;
        }
        if (str_starts_with($sql, 'UPDATE') && (str_contains($sql, 'processing_started_at <') || str_contains($sql, 'attempts >= 5'))) return 0;
        throw new RuntimeException('Unexpected SQL: ' . $sql);
    }
    function get_results($sql, $format) {
        $this->last_error='';
        if (!str_contains($sql, 'FROM wp_mi_email_outbox WHERE') || !str_contains($sql,'ORDER BY id ASC LIMIT 10')) throw new RuntimeException('Unexpected select');
        $after=preg_match('/AND id > (\d+)/',$sql,$matches)?(int)$matches[1]:0;
        $target=preg_match('/\(id=(\d+) AND/',$sql,$matches)?(int)$matches[1]:0;
        $normal=str_contains($sql,"status IN ('PENDING')");$test=str_contains($sql,"status IN ('TEST_PENDING')");
        return array_slice(array_values(array_filter($this->rows, fn($r) => $r['id']>$after && (!$target || $target===$r['id']) && $r['attempts']<5 && ($target ? ($r['status']==='TEST_PENDING' && $r['template_type']==='PORTAL_COMMUNICATION_TEST') : (($normal && $r['status']==='PENDING') || ($test && $r['status']==='TEST_PENDING') || ($r['status']==='TEST_PENDING' && $r['template_type']==='PORTAL_COMMUNICATION_TEST'))))),0,10);
    }
    function get_var($sql) { if(str_contains($sql,'GET_LOCK')||str_contains($sql,'RELEASE_LOCK'))return 1; if(str_starts_with($sql,'SELECT id FROM')){foreach($this->rows as $r)if($r['status']==='TEST_PENDING'&&$r['template_type']==='PORTAL_COMMUNICATION_TEST'&&$r['attempts']<5)return $r['id'];return 0;} return count(array_filter($this->rows, fn($r) => in_array($r['status'], ['PENDING', 'TEST_PENDING']) && $r['attempts'] < 5)); }
    function update($table, $data, $where, ...$args) { $this->rows[$where['id']] = array_merge($this->rows[$where['id']], $data); return 1; }
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';

function seed_queue($blocked,$healthy){
    global $wpdb,$scheduled,$auditOptions;
    $wpdb=new AuditQueueDatabase();$scheduled=0;$auditOptions=[];MI_Workspace_Client::$recipients=[];MI_Event_Deletion::$blocked=true;
    for($id=1;$id<=$blocked+$healthy;$id++)$wpdb->rows[$id]=[
        'id'=>$id,'registration_id'=>0,'recipient'=>"recipient$id@example.invalid",'template_type'=>'EVENT_NOTICE',
        'payload_json'=>json_encode(['event_id'=>$id<=$blocked?42:43,'email_preview'=>['attivo'=>true,'oggetto'=>'Synthetic audit','html'=>'<p>Synthetic</p>','testo'=>'Synthetic']]),
        'status'=>'PENDING','attempts'=>0,'processing_started_at'=>null
    ];
}
foreach([10,105] as $blocked){
    seed_queue($blocked,1);
    for($run=0;$run<3;$run++)MI_Spedizione_Email::spedisci_coda();
    if(count(MI_Workspace_Client::$recipients)!==1||$wpdb->rows[$blocked+1]['status']!=='SENT')throw new RuntimeException('Other event starved');
    for($id=1;$id<=$blocked;$id++)if($wpdb->rows[$id]['attempts']!==0||$wpdb->rows[$id]['status']!=='PENDING')throw new RuntimeException('Blocked event delivered');
    echo "PASS: $blocked blocked emails do not starve another event.\n";
}
seed_queue(0,25);
foreach([10,20,25] as $expected){
    MI_Spedizione_Email::spedisci_coda();
    if(count(MI_Workspace_Client::$recipients)!==$expected)throw new RuntimeException('Ten-delivery budget or cursor broken');
}
MI_Spedizione_Email::spedisci_coda();
if(count(MI_Workspace_Client::$recipients)!==25)throw new RuntimeException('Duplicate delivery');
echo "PASS: ten deliveries per worker, bounded cursor scans, wrap and no duplicates.\n";
seed_queue(10,1);
MI_Spedizione_Email::spedisci_coda();
MI_Event_Deletion::$blocked=false;
for($run=0;$run<3;$run++)MI_Spedizione_Email::spedisci_coda();
if(count(MI_Workspace_Client::$recipients)!==11)throw new RuntimeException('Unblocked messages lost');
echo "PASS: previously blocked messages resume.\n";

foreach(['OPERATIVO','PROVA','ANTEPRIMA'] as $mode){
 seed_queue(0,126);$auditOptions['mi_modalita_spedizione_email']=$mode;
 $sample=$wpdb->rows[126];$sample['status']='TEST_PENDING';$sample['template_type']='PORTAL_COMMUNICATION_TEST';
 $sample['payload_json']=json_encode(['event_id'=>43,'portal_test_copy'=>true,'test_recipient'=>'chosen@example.invalid','email_preview'=>['attivo'=>true,'oggetto'=>'Prova','html'=>'<p>Prova</p>','testo'=>'Prova']]);
 $wpdb->rows[126]=$sample;$auditOptions['mi_email_scan_cursor_'.strtolower($mode)]=88;
 MI_Spedizione_Email::spedisci_coda(126);
 if(MI_Workspace_Client::$recipients!==['chosen@example.invalid'] || $wpdb->rows[126]['status']!=='TEST_SENT')throw new RuntimeException('Target test not delivered immediately');
 if($wpdb->rows[1]['status']!=='PENDING'||$auditOptions['mi_email_scan_cursor_'.strtolower($mode)]!==88)throw new RuntimeException('Target test touched mass queue or its cursor');
 MI_Spedizione_Email::spedisci_coda(126);
 if(count(MI_Workspace_Client::$recipients)!==1)throw new RuntimeException('Target test delivered twice');
 echo "PASS: targeted test in $mode bypasses 125 queued emails, preserves cursor, sends once.\n";
}

seed_queue(0,2);$wpdb->rows[1]['status']='TEST_PENDING';$wpdb->rows[1]['template_type']='ORDINARY_TEST';
MI_Spedizione_Email::spedisci_coda();
if(MI_Workspace_Client::$recipients!==['recipient2@example.invalid'])throw new RuntimeException('Operational mode sent ordinary test queue');
echo "PASS: operational mode does not deliver ordinary test queue.\n";

foreach (['PENDING'=>'OPERATIVO','TEST_PENDING'=>'PROVA'] as $pending=>$mode) {
    seed_queue(0,2);
    $auditOptions['mi_modalita_spedizione_email']=$mode;
    foreach ($wpdb->rows as $id=>$row) {
        $wpdb->rows[$id]['registration_id']=$id===1 ? 7 : 99;
        $wpdb->rows[$id]['status']=$pending;
    }
    MI_Event_Deletion::$read_failure=true;
    MI_Spedizione_Email::spedisci_coda();
    if ($wpdb->rows[1]['status']!==$pending || $wpdb->rows[1]['attempts']!==0 || MI_Workspace_Client::$recipients) throw new RuntimeException('SQL failure consumed a delivery');
    MI_Event_Deletion::$read_failure=false;
    MI_Spedizione_Email::spedisci_coda();
    $prefix=$mode==='PROVA' ? 'TEST_' : '';
    if ($wpdb->rows[1]['status']!==$prefix.'SENT' || $wpdb->rows[2]['status']!==$prefix.'FAILED' || count(MI_Workspace_Client::$recipients)!==1) throw new RuntimeException('Recovery or missing registration handled incorrectly');
}
