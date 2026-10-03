<?php
// Real management, selector and compressed cache; synthetic WordPress and SQL reads.
define('ABSPATH', __DIR__); define('ARRAY_A', 'ARRAY_A');
function absint($v) { return abs((int)$v); }
function sanitize_key($v) { return strtolower((string)$v); }
function wp_json_encode($v, ...$args) { return json_encode($v, ...$args); }
function remove_accents($v) { return strtr($v, ['é'=>'e', 'è'=>'e', 'È'=>'E']); }
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_date($format) { return gmdate($format); }
function get_the_title($id) { return 'Evento sintetico'; }
function get_post($id) { return (object)['post_type'=>'mi_event', 'post_title'=>'Evento sintetico', 'post_status'=>'publish']; }
function get_post_meta($id, $key = '', $single = true) { return $key === '' ? $GLOBALS['meta'] : ($GLOBALS['meta'][$key] ?? ''); }
function get_locale() { return 'it_IT'; }
function wp_cache_delete(...$args) {}
function get_transient($key) { $item = $GLOBALS['transients'][$key] ?? null; return $item && $item['expires'] > $GLOBALS['cache_clock'] ? $item['value'] : false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = ['value'=>$value, 'expires'=>$GLOBALS['cache_clock']+$ttl]; }
class WP_Error { function __construct(public $code, public $message) {} }
class MI_Portal_Management { static function allowed() { return true; } }
class MI_Access { static function can_access_event($id) { return $id === 42; } }
class MI_Event_Post_Type { const EVENT_TYPE = 'mi_event'; }
class MI_Shortcode { static function url_iscrizione($id) { return 'https://example.invalid/event'; } }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-service.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-list.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-ledger.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-workspace-client.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-event-read-cache.php';
class ConsistencyDatabase {
    public $prefix = 'wp_', $last_error = '', $orders = [], $people = [], $selected = [];
    function prepare($sql, ...$args) { foreach ($args as $arg) $sql = preg_replace('/%[ds]/', is_int($arg) ? (string)$arg : "'".$arg."'", $sql, 1); return $sql; }
    function get_row($sql, $mode = null) { return ['total'=>count($this->orders), 'max_id'=>max(array_keys($this->orders)), 'revisions'=>array_sum(array_column($this->orders, 'workspace_revision')), 'pending'=>0]; }
    function candidates($sql) {
        $individual = str_contains($sql, 'mi_participants p');
        return array_values(array_filter($individual ? $this->people : $this->orders, function($row) use ($sql, $individual) {
            $order = $individual ? $this->orders[$row['registration_id']] : $row;
            if (str_contains($sql, "r.status NOT IN ('CANCELLED','EXPIRED')") && in_array($order['status'], ['CANCELLED','EXPIRED'], true)) return false;
            if ($individual && str_contains($sql, "p.status<>'CANCELLED'") && $row['status'] === 'CANCELLED') return false;
            return true;
        }));
    }
    function get_var($sql) { return str_contains($sql, 'SELECT revision') ? 1 : count($this->candidates($sql)); }
    function get_results($sql, $mode = null) {
        if (str_starts_with($sql, 'SELECT p.id,r.id registration_id') || str_starts_with($sql, 'SELECT r.id registration_id')) {
            $this->selected[] = $sql;
            $individual = str_starts_with($sql, 'SELECT p.id');
            $rows = $this->candidates($sql);
            if (preg_match('/AND [pr]\.id>(\d+) ORDER BY [pr]\.id LIMIT (\d+)/', $sql, $match)) {
                $rows = array_values(array_filter($rows, fn($r)=>$r['id'] > (int)$match[1]));
                usort($rows, fn($a,$b)=>$a['id'] <=> $b['id']);
                $rows = array_slice($rows, 0, (int)$match[2]);
            } else {
                $desc = str_contains($sql, 'r.created_at DESC');
                usort($rows, function($a,$b) use ($individual, $desc, $sql) {
                    if (str_contains($sql, 'p.room_code ASC')) return strcmp($a['room_code'], $b['room_code']);
                    $ra = $individual ? $this->orders[$a['registration_id']] : $a;
                    $rb = $individual ? $this->orders[$b['registration_id']] : $b;
                    $order = strcmp($ra['created_at'], $rb['created_at']) ?: ($ra['id'] <=> $rb['id']);
                    return ($desc ? -1 : 1) * $order ?: ($a['id'] <=> $b['id']);
                });
                preg_match('/LIMIT (\d+) OFFSET (\d+)/', $sql, $match);
                $rows = array_slice($rows, (int)$match[2], (int)$match[1]);
            }
            return array_map(fn($r)=>$individual ? ['id'=>$r['id'], 'registration_id'=>$r['registration_id']] : ['registration_id'=>$r['id'], 'order_code'=>$r['order_code']], $rows);
        }
        if (str_contains($sql, 'mi_registration_events') || str_contains($sql, 'mi_rooms')) return [];
        preg_match('/r.id IN \(([0-9,]+)\)/', $sql, $match);
        $ids = isset($match[1]) ? array_map('intval', explode(',', $match[1])) : array_keys($this->orders);
        if (str_contains($sql, 'mi_participants')) return array_values(array_filter($this->people, fn($p)=>in_array($p['registration_id'], $ids, true)));
        if (str_contains($sql, 'mi_registration_items')) return array_map(fn($id)=>['registration_id'=>$id, 'ticket_type_code'=>'base', 'unit_price_cents'=>0], $ids);
        if (str_contains($sql, 'mi_payments')) return [];
        if (str_contains($sql, 'mi_registrations')) return array_values(array_intersect_key($this->orders, array_flip($ids)));
        throw new RuntimeException('Unexpected SQL: '.$sql);
    }
}
function check_page($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function read_page($context, $offset = 0, $limit = 30) {
    $page = MI_Management_Service::page(42, $context, $offset, $limit);
    if (is_wp_error($page)) throw new RuntimeException($page->message);
    return $page;
}
$GLOBALS['meta'] = ['_mi_options'=>[], '_mi_custom_participant_fields'=>[], '_mi_economic_mode'=>'REGISTRATION_ONLY'];
$GLOBALS['transients'] = []; $GLOBALS['cache_clock'] = 1000;
$wpdb = new ConsistencyDatabase();
for ($id=1; $id<=241; $id++) {
    $wpdb->orders[$id] = ['id'=>$id, 'event_id'=>42, 'order_code'=>'T'.$id, 'status'=>$id%37 ? 'CONFIRMED' : 'CANCELLED', 'buyer_first_name'=>'Persona', 'buyer_last_name'=>'Èva '.($id%12), 'buyer_email'=>'', 'buyer_phone'=>'', 'created_at'=>sprintf('2026-09-%02d 10:00:00', $id%28+1), 'total_cents'=>0, 'initial_due_cents'=>0, 'economic_mode'=>'REGISTRATION_ONLY', 'snapshot_json'=>'{}', 'order_options_json'=>'[]', 'workspace_revision'=>1];
    $wpdb->people[] = ['id'=>$id, 'registration_id'=>$id, 'ticket_type_code'=>'base', 'first_name'=>'Persona', 'last_name'=>($id%2 ? 'René ' : 'Rene ').($id%15), 'status'=>$id%41 ? 'ACTIVE' : 'CANCELLED', 'room_code'=>'S'.$id, 'extra_json'=>'{}', 'options_json'=>'[]', 'deposit_due_cents'=>null];
}
$model = MI_Management_Service::summary(42);
check_page(!is_wp_error($model), 'Fixture summary failed');
$token = MI_Event_Read_Cache::state(42)['token'];
// Every text comparator, direction and view must agree across cache/SQL pages and chunks.
foreach (['people','orders'] as $view) foreach (['created_at','name','buyer','code','room'] as $sort) foreach (['asc','desc'] as $direction) {
    $context = ['view'=>$view, 'sort'=>$sort, 'direction'=>$direction, 'includeClosed'=>true];
    $expected = MI_Management_List::page($model, $context, 0, 200, $token);
    $expectedRows = array_merge($expected['rows'], MI_Management_List::page($model, $context, 200, 200, $token)['rows']);
    MI_Event_Read_Cache::put(42, $token, $model);
    $first = read_page($context);
    $GLOBALS['cache_clock'] += 301; // Expire only the optional cache, preserving the canonical state.
    $rows = $first['rows'];
    while (count($rows) < $first['total']) {
        $page = read_page($context, count($rows));
        check_page($page['fingerprint'] === $first['fingerprint'], 'Cache expiry changed fingerprint: '.$view.' '.$sort.' '.$direction);
        check_page(count($page['rows']) > 0, 'Missing next page');
        $rows = array_merge($rows, $page['rows']);
    }
    check_page($rows === $expectedRows, 'Page boundary changed ordering: '.$view.' '.$sort.' '.$direction);
    $coldFirst = read_page($context);
    MI_Event_Read_Cache::put(42, $token, $model);
    check_page(read_page($context, 30)['fingerprint'] === $coldFirst['fingerprint'], 'Cache warming changed fingerprint');
    $exportFirst = read_page($context, 0, 200);
    $GLOBALS['cache_clock'] += 301;
    $exportLast = read_page($context, 200, 200);
    check_page($exportFirst['fingerprint'] === $exportLast['fingerprint'] && array_merge($exportFirst['rows'], $exportLast['rows']) === $expectedRows, 'Export batches differ across cache expiry');
}
// The actual natural room sequence, without closed rows, must not end in S9.
$context = ['view'=>'people', 'sort'=>'room'];
$GLOBALS['cache_clock'] += 301;
$page = read_page($context, 0, 30);
$rooms = array_column($page['rows'], 'room');
check_page($rooms === array_map(fn($id)=>'S'.$id, range(1,30)), 'First 30 naturally ordered rooms differ');
// Ordinary pagination retains the SQL date fast path; textual sorts scan bounded chunks.
check_page((bool)array_filter($wpdb->selected, fn($sql)=>str_contains($sql, 'r.created_at DESC') && str_contains($sql, 'OFFSET 30')), 'Chronological fast path lost');
check_page((bool)array_filter($wpdb->selected, fn($sql)=>str_contains($sql, 'p.id>200 ORDER BY p.id LIMIT 200')), 'Second bounded chunk not exercised');
// Text search and empty selections also have cache-independent fingerprints.
foreach (['Rene 2','not-present'] as $query) {
    $context = ['query'=>$query, 'sort'=>'name'];
    MI_Event_Read_Cache::put(42, $token, $model); $cached = read_page($context);
    $GLOBALS['cache_clock'] += 301; $cold = read_page($context);
    check_page($cached === $cold, 'Filtered page differs after cache expiry');
}
// Actual record and event metadata changes must still invalidate multi-page reads.
$before = read_page([])['fingerprint'];
$wpdb->orders[1]['workspace_revision']++;
check_page(read_page([])['fingerprint'] !== $before, 'Canonical write not detected');
$before = read_page([])['fingerprint'];
$GLOBALS['meta']['_mi_special_requests_enabled'] = '1';
check_page(read_page([])['fingerprint'] !== $before, 'Event schema change not detected');
// Timed membership spans two chunks; fingerprints use all matches, not only the returned page.
foreach ($wpdb->orders as $id=>&$row) { $row['status']='WAITLIST_OFFERED'; $row['waitlist_offer_expires_at']=gmdate('Y-m-d H:i:s', time()+($id<=210 ? 3600 : 172800)); } unset($row);
foreach ($wpdb->people as &$row) $row['status']='ACTIVE'; unset($row);
$GLOBALS['transients'] = [];
$model = MI_Management_Service::summary(42);
foreach (['people','orders'] as $view) {
    $context = ['view'=>$view, 'deadline'=>'soon', 'sort'=>'name', 'direction'=>'desc'];
    $token = MI_Event_Read_Cache::state(42)['token']; MI_Event_Read_Cache::put(42, $token, $model);
    $cached = read_page($context, 200);
    $GLOBALS['cache_clock'] += 301; $cold = read_page($context, 200);
    check_page($cached['total'] === 210 && count($cached['rows']) === 10 && $cached === $cold, 'Timed filter changed with cache/chunk boundaries');
}
echo "PASS: cached/SQL pages agree across expiry, warming, text sorts, directions, views, export batches and timed filters; real version changes remain detectable.\n";

// Inject failures and writes between version capture and row selection.
class InterruptedPageDatabase extends ConsistencyDatabase {
    public $changeOnRead = false, $failCount = false, $pageReads = 0;
    function get_row($sql, $mode = null) {
        $this->last_error = '';
        return parent::get_row($sql, $mode);
    }
    function get_var($sql) {
        $this->last_error = '';
        if ($this->failCount && str_starts_with($sql, 'SELECT COUNT(*)')) {
            $this->last_error = 'Injected COUNT failure'; return null;
        }
        return parent::get_var($sql);
    }
    function get_results($sql, $mode = null) {
        $this->last_error = '';
        if (str_starts_with($sql, 'SELECT p.id,r.id registration_id') || str_starts_with($sql, 'SELECT r.id registration_id')) {
            $this->pageReads++;
            if ($this->changeOnRead) {
                $this->changeOnRead = false;
                foreach ($this->orders as &$row) $row['workspace_revision']++;
                unset($row);
                foreach ($this->people as &$row) $row['room_code'] = 'CHANGED';
                unset($row);
            }
        }
        return parent::get_results($sql, $mode);
    }
}
$original = $wpdb;
$wpdb = new InterruptedPageDatabase();
$wpdb->orders = $original->orders; $wpdb->people = $original->people;
$GLOBALS['transients'] = [];
foreach (['people', 'orders'] as $view) {
    foreach (['created_at', 'name'] as $sort) {
        $wpdb->changeOnRead = true;
        $result = MI_Management_Service::page(42, ['view'=>$view, 'sort'=>$sort]);
        check_page(is_wp_error($result) && $result->code === 'mi_management_changed', 'Concurrent write accepted: '.$view.'/'.$sort);
        check_page(!is_wp_error(MI_Management_Service::page(42, ['view'=>$view, 'sort'=>$sort])), 'Stable retry failed');
    }
    $wpdb->failCount = true; $reads = $wpdb->pageReads;
    $result = MI_Management_Service::page(42, ['view'=>$view]);
    check_page(is_wp_error($result) && $result->code === 'mi_management_read', 'COUNT failure accepted: '.$view);
    check_page($wpdb->pageReads === $reads, 'SELECT executed after failed COUNT');
    $wpdb->failCount = false;
    check_page(!is_wp_error(MI_Management_Service::page(42, ['view'=>$view])), 'Read did not recover after COUNT failure');
}
echo "PASS: concurrent writes rejected in both views and SQL/scanned paths; COUNT errors stop before SELECT.\n";
