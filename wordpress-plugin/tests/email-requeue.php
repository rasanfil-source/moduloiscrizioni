<?php
define('ABSPATH', __DIR__);
function current_user_can($cap) { return true; }
function check_admin_referer($action) { if ($action !== 'mi_riaccoda_email') throw new RuntimeException('Nonce errato'); }
function absint($v) { return abs((int)$v); }
function admin_url($path) { return 'https://example.invalid/' . $path; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
class RedirectResult extends RuntimeException {}
function wp_safe_redirect($url) { throw new RedirectResult($url); }
function get_option($key, $default = null) { return $key === 'mi_modalita_spedizione_email' ? 'ANTEPRIMA' : $default; }
class MI_Event_Post_Type { const EVENT_TYPE = 'mi_event'; }
class RequeueDatabase {
    public $prefix = 'wp_', $result = 0, $calls = 0;
    function prepare($sql, $id) {
        if ($id !== 42) throw new RuntimeException('ID errato');
        return str_replace('%d', (string)$id, $sql);
    }
    function query($sql) {
        $this->calls++;
        if (!str_contains($sql, "WHERE id = 42 AND status IN ('FAILED', 'TEST_FAILED')")) throw new RuntimeException('Stati non protetti');
        if (!str_contains($sql, "CASE WHEN status = 'TEST_FAILED' THEN 'TEST_PENDING' ELSE 'PENDING' END")) throw new RuntimeException('Modalita non preservata');
        return $this->result;
    }
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
foreach ([[1, 'riaccodata'], [0, 'riaccoda_non_disponibile'], [false, 'riaccoda_errore']] as [$affected, $expected]) {
    $wpdb = new RequeueDatabase(); $wpdb->result = $affected; $_POST = ['email_id' => 42];
    try { MI_Spedizione_Email::riaccoda_email(); } catch (RedirectResult $redirect) {
        parse_str(parse_url($redirect->getMessage(), PHP_URL_QUERY), $query);
        if ($query['mi_esito'] !== $expected || $wpdb->calls !== 1) throw new RuntimeException('Esito errato');
    }
}
echo "Riaccodatura: esiti di scrittura, concorrenza e errore SQL verificati.\n";
