<?php
define('ABSPATH', __DIR__);
class WP_Error { function __construct(public $code, public $message) {} }
function is_wp_error($v) { return $v instanceof WP_Error; }
function current_user_can(...$args) { return true; }
function get_current_user_id() { return 7; }
function wp_slash($v) { return $v; }
function maybe_unserialize($v) { return $v; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_meta($id) { return array_map(fn($v) => [$v], $GLOBALS['meta'][$id] ?? []); }
function add_post_meta($id, $key, $value, $unique = false) { $GLOBALS['meta'][$id][$key] = $value; return true; }
function wp_insert_post($data, $error = false) { $GLOBALS['posts'][43] = (object)($data + ['ID' => 43]); return 43; }
class MI_Event_Post_Type { const EVENT_TYPE = 'mi_event'; }
class MI_Access { static function can_access_event($id) { return true; } }
class DuplicateDB {
    public $prefix = 'wp_', $posts = 'wp_posts', $postmeta = 'wp_postmeta';
    function prepare($sql, ...$args) { return $sql; }
    function get_var($sql) { return str_contains($sql, 'GET_LOCK') || str_contains($sql, 'RELEASE_LOCK') ? '1' : null; }
}
$wpdb = new DuplicateDB();
$posts = [42 => (object)['ID' => 42, 'post_type' => 'mi_event', 'post_status' => 'draft', 'post_title' => 'Evento annullato sintetico', 'post_content' => '', 'post_excerpt' => '', 'menu_order' => 0, 'comment_status' => 'closed', 'ping_status' => 'closed']];
$meta = [42 => ['_mi_capacity' => 10, '_mi_event_cancelled_at' => '2026-09-29 12:00:00', '_mi_event_cancellation_reason' => 'Prova', '_mi_event_cancellation_job' => ['reason' => 'Prova', 'recipients' => [['order_code' => 'TEST-OLD']]]]];
$original = $meta[42];
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-event-duplicator.php';
$copy = MI_Event_Duplicator::duplicate(42, '12345678-1234-4234-8234-123456789abc');
if ($copy !== 43 || $posts[43]->post_status !== 'draft' || $meta[43]['_mi_capacity'] !== 10 || $meta[42] !== $original) throw new RuntimeException('Copy or source configuration changed unexpectedly');
foreach (['_mi_event_cancelled_at', '_mi_event_cancellation_reason', '_mi_event_cancellation_job'] as $key) {
    if (isset($meta[43][$key])) throw new RuntimeException('Copied cancellation state: ' . $key);
}
if ($meta[43]['_mi_privacy_consent_id'] !== 'privacy-43') throw new RuntimeException('Copied consent identity');
echo "PASS: cancelled event duplicates retain configuration and new consent IDs, without cancellation state; source remains intact.\n";
