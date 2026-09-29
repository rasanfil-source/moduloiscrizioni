<?php
// In-memory database and mail transport; the dispatcher is the unmodified source.
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
define('MINUTE_IN_SECONDS', 60);
class WP_Error {
    function __construct(public $code, public $message, public $data = null) {}
    function get_error_code() { return $this->code; }
    function get_error_data() { return $this->data; }
    function get_error_message() { return $this->message; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function absint($v) { return abs((int) $v); }
function sanitize_key($v) { return strtolower($v); }
function sanitize_text_field($v) { return $v; }
function sanitize_email($v) { return $v; }
function is_email($v) { return str_contains($v, '@'); }
function esc_html($v) { return htmlspecialchars($v, ENT_QUOTES); }
function home_url() { return 'https://example.invalid'; }
function wp_salt($v) { return 'synthetic-audit-salt'; }
function get_option($key, $default = null) {
    return [
        'mi_modalita_spedizione_email' => $GLOBALS['auditMode'],
        'mi_destinatario_prova_email' => 'test@example.invalid',
        'mi_prova_email_verificata' => hash_hmac('sha256', 'test@example.invalid', wp_salt('auth')),
    ][$key] ?? $default;
}
function wp_next_scheduled(...$args) { return false; }
function update_option(...$args) { return true; }
function wp_schedule_single_event(...$args) { $GLOBALS['scheduled']++; }
function current_time(...$args) { return gmdate('Y-m-d H:i:s'); }
class MI_Event_Deletion {
    static function enter($id) { return true; }
    static function release($id) {}
}
class MI_Modello_Email {
    const EMAIL_SEGRETERIA = 'sender@example.invalid';
    const NOME_SEGRETERIA = 'Segreteria';
    static function ripara_istantanea_codifica($v) { return $v; }
    static function componi_html($v, $code) { return $v['html']; }
    static function componi_testo($v) { return $v['testo']; }
}
class MI_Workspace_Client {
    static $calls = 0;
    static function request($action, $payload) {
        self::$calls++;
        throw new RuntimeException('Simulated process interruption during fifth delivery');
    }
}
class AuditOutboxDatabase {
    public $prefix = 'wp_', $last_error = '', $rows = [];
    function prepare($sql, ...$args) {
        foreach ($args as $value) $sql = preg_replace_callback('/%[ds]/', fn($m) => $m[0] === '%d' ? (string) (int) $value : "'" . str_replace("'", "''", (string) $value) . "'", $sql, 1);
        return $sql;
    }
    function query($sql) {
        if (preg_match("/SET status = '(PENDING|TEST_PENDING)', processing_started_at = NULL WHERE status = '(SENDING|TEST_SENDING)' AND processing_started_at < '([^']+)'/", $sql, $m)) {
            $changed = 0;
            foreach ($this->rows as &$row) if ($row['status'] === $m[2] && $row['processing_started_at'] < $m[3]) {
                $row['status'] = $m[1]; $row['processing_started_at'] = null; $changed++;
            }
            return $changed;
        }
        if (preg_match("/SET status = '(SENDING|TEST_SENDING)', attempts = attempts \+ 1, processing_started_at = '([^']+)' WHERE id = (\d+) AND status = '([^']+)'/", $sql, $m)) {
            if ($this->rows[$m[3]]['status'] !== $m[4]) return 0;
            $this->rows[$m[3]]['status'] = $m[1];
            $this->rows[$m[3]]['processing_started_at'] = $m[2];
            $this->rows[$m[3]]['attempts']++;
            return 1;
        }
        if (preg_match("/SET status = '(FAILED|TEST_FAILED)'.*WHERE status = '(PENDING|TEST_PENDING)' AND attempts >= 5/", $sql, $m)) {
            foreach ($this->rows as &$row) if ($row['status'] === $m[2] && $row['attempts'] >= 5) $row['status'] = $m[1];
            return 1;
        }
        throw new RuntimeException('Unexpected audit SQL: ' . $sql);
    }
    function get_results($sql, $mode) {
        if (!preg_match('/WHERE status IN \(([^)]+)\) AND attempts < (\d+)/', $sql, $m)) throw new RuntimeException('Unexpected audit SELECT');
        $states = array_map(fn($v) => trim($v, " '"), explode(',', $m[1]));
        return array_values(array_filter($this->rows, fn($row) => in_array($row['status'], $states, true) && $row['attempts'] < (int) $m[2]));
    }
    function get_var($sql) { return count($this->get_results($sql, ARRAY_A)); }
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
foreach (['OPERATIVO', 'PROVA'] as $auditMode) {
    $scheduled = 0;
    MI_Workspace_Client::$calls = 0;
    $wpdb = new AuditOutboxDatabase();
    $wpdb->rows[1] = ['id' => 1, 'registration_id' => null, 'recipient' => 'recipient@example.invalid', 'template_type' => 'EVENT_NOTICE', 'payload_json' => json_encode(['event_id' => 42, 'email_preview' => ['attivo' => true, 'oggetto' => 'Audit sintetico', 'testo' => 'Messaggio sintetico', 'html' => '<p>Messaggio sintetico</p>']]), 'attempts' => 4, 'status' => $auditMode === 'PROVA' ? 'TEST_PENDING' : 'PENDING', 'processing_started_at' => null];
    $interrupted = false;
    try { MI_Spedizione_Email::spedisci_coda(); } catch (RuntimeException $error) {
        if (!str_starts_with($error->getMessage(), 'Simulated process interruption')) throw $error;
        $interrupted = true;
    }
    $crashedStatus = $wpdb->rows[1]['status'];
    $wpdb->rows[1]['processing_started_at'] = gmdate('Y-m-d H:i:s', time() - 16 * 60);
    MI_Spedizione_Email::spedisci_coda();
    MI_Spedizione_Email::spedisci_coda();
    $bug = $interrupted && $wpdb->rows[1]['attempts'] === 5 && in_array($wpdb->rows[1]['status'], ['FAILED', 'TEST_FAILED'], true) && MI_Workspace_Client::$calls === 1 && $scheduled === 0;
    echo json_encode(['case' => 'fifth_attempt_interrupted', 'mode' => $auditMode, 'regression_passed' => $bug, 'status_at_interruption' => $crashedStatus, 'status_after_two_recovery_runs' => $wpdb->rows[1]['status'], 'attempts' => $wpdb->rows[1]['attempts'], 'delivery_calls' => MI_Workspace_Client::$calls, 'retries_scheduled' => $scheduled]) . "\n";
    if (!$bug) throw new RuntimeException('Outbox reproduction failed');
}
