<?php
define('ABSPATH', __DIR__);
class PortalJsonResponse extends RuntimeException { public $status; function __construct($status) { $this->status = $status; } }
class WP_Error {
	private $message; private $data;
	function __construct($code, $message, $data = null) { $this->message = $message; $this->data = $data; }
	function get_error_message() { return $this->message; }
	function get_error_data() { return $this->data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return $value; }
function nocache_headers() {}
function check_ajax_referer($action, $field, $die = true) { return true; }
function is_user_logged_in() { return true; }
function current_user_can($capability) { return true; }
function get_current_user_id() { return 1; }
function wp_get_current_user() { return (object) array('display_name' => 'Test'); }
if (!function_exists('mb_substr')) { function mb_substr($value, $start, $length = null) { return substr((string) $value, $start, $length); } }
function wp_send_json_error($data, $status = 200) { throw new PortalJsonResponse((int) $status); }
class MI_Access { static function is_suspended() { return false; } static function can_access_event($event) { return true; } }
class MI_Management_Service {
	static $status = 409;
	static function change_accommodation($event, $data, $version, $request) { return new WP_Error('management_conflict', 'Conflitto di versione.', self::$status ? array('status' => self::$status) : null); }
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-portal-management.php';
foreach ([409 => 409, 0 => 500] as $provided => $expected) {
	MI_Management_Service::$status = (int) $provided;
	$_POST = array('operation' => 'change_accommodation', 'event_id' => '42', 'data' => '{}', 'request_id' => '12345678-1234-4234-8234-123456789abc', 'preview_version' => 'v1');
	try { MI_Portal_Management::ajax(); throw new RuntimeException('L’endpoint non ha restituito un errore JSON.'); }
	catch (PortalJsonResponse $response) { if ($response->status !== $expected) throw new RuntimeException('Status HTTP errato: ' . $response->status); }
}
echo "Portale gestione: status HTTP WP_Error rispettato e fallback 500 validato.\n";
