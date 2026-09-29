<?php
// Synthetic in-memory database and transport; original dispatcher is unchanged.
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
function wp_schedule_single_event(...$args) { $GLOBALS['scheduled']++; return true; }
class MI_Event_Deletion {
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
    static function request($action, $payload) { self::$recipients[] = $payload['destinatario']; return ['ok' => true, 'channel' => 'GOOGLE_WORKSPACE']; }
}
class AuditQueueDatabase {
    public $prefix = 'wp_', $last_error = '', $rows = [];
    function prepare($sql, ...$args) {
        foreach ($args as $arg) $sql = preg_replace_callback('/%[ds]/', fn($m) => $m[0] === '%d' ? (string)(int)$arg : "'" . str_replace("'", "''", (string)$arg) . "'", $sql, 1);
        return $sql;
    }
    function query($sql) {
        if (preg_match("/SET status = 'SENDING', attempts = attempts \\+ 1, processing_started_at = '([^']+)' WHERE id = (\\d+) AND status = 'PENDING'/", $sql, $m)) {
            if ($this->rows[$m[2]]['status'] !== 'PENDING') return 0;
            $this->rows[$m[2]]['status'] = 'SENDING';
            $this->rows[$m[2]]['attempts']++;
            $this->rows[$m[2]]['processing_started_at'] = $m[1];
            return 1;
        }
        if (str_starts_with($sql, 'UPDATE') && (str_contains($sql, 'processing_started_at <') || str_contains($sql, 'attempts >= 5'))) return 0;
        throw new RuntimeException('Unexpected SQL: ' . $sql);
    }
    function get_results($sql, $format) {
        if (!str_contains($sql, "status IN ('PENDING') AND attempts < 5") || !str_contains($sql,'ORDER BY id ASC LIMIT 10')) throw new RuntimeException('Unexpected select');
        $after=preg_match('/AND id > (\d+)/',$sql,$matches)?(int)$matches[1]:0;
        return array_slice(array_values(array_filter($this->rows, fn($r) => $r['id']>$after && $r['status'] === 'PENDING' && $r['attempts'] < 5)), 0, 10);
    }
    function get_var($sql) { return count(array_filter($this->rows, fn($r) => in_array($r['status'], ['PENDING', 'TEST_PENDING']) && $r['attempts'] < 5)); }
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
