<?php
define( 'ABSPATH', __DIR__ );
define( 'MI_PLUGIN_URL', 'https://example.invalid/plugin/' );
define( 'MI_VERSION', 'test' );
class WP_Error { private $message; function __construct( $code, $message ) { $this->message = $message; } function get_error_message() { return $this->message; } }
class JsonResult extends Exception { public $data; public $status; function __construct( $data, $status ) { $this->data = $data; $this->status = $status; } }
class MI_Event_Post_Type { const EVENT_TYPE = 'mi_event'; }
class MI_Access { static function can_access_event( $id ) { return $GLOBALS['access']; } }
class MI_Shortcode { static function url_iscrizione( $id ) { return $GLOBALS['url']; } }
function absint($v) { return abs((int)$v); }
function check_ajax_referer($action) { if ( $action !== 'mi_event_qr_42' || !$GLOBALS['nonce'] ) throw new JsonResult('nonce',403); }
function is_user_logged_in() { return $GLOBALS['logged']; }
function current_user_can($cap) { return $GLOBALS['cap']; }
function get_post_status($id) { return $GLOBALS['state']; }
function get_post_type($id) { return $GLOBALS['type']; }
function get_post_meta($id,$key,$single=true) { return $GLOBALS['meta'][$key] ?? ''; }
function get_post_thumbnail_id($id) { return $GLOBALS['logo_id']; }
function wp_get_attachment_image_url($id,$size) { return 'https://example.invalid/logo.png'; }
function wp_safe_remote_get($url,$args) { $GLOBALS['http_args']=$args; return $GLOBALS['response']; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function esc_url_raw($v,$schemes=[]) { return $v; }
function wp_send_json_error($data,$code) { throw new JsonResult($data,$code); }
function wp_send_json_success($data) { throw new JsonResult($data,200); }
function nocache_headers() {}
function wp_enqueue_script(...$args) {}
function add_query_arg($args,$url) { return $url . '?' . http_build_query($args); }
function wp_create_nonce($action) { return $action; }
function admin_url($url) { return 'https://example.invalid/wp-admin/'.$url; }
function esc_url($url) { return htmlspecialchars($url); }
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-event-qr.php';
function expect_qr($ok,$message) { if(!$ok) throw new RuntimeException($message); }
function request_qr() { try { MI_Event_QR::data(); } catch(JsonResult $result) { return $result; } throw new RuntimeException('Missing response'); }
$nonce=$logged=$cap=$access=true; $state='publish'; $type='mi_event'; $logo_id=9;
$meta=['_mi_event_qr_enabled'=>'1','_mi_activity_id'=>7];
$url='https://example.invalid/v/test'; $_GET['event_id']=42;
$response=['code'=>200,'body'=>base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=')];
$result=request_qr(); expect_qr($result->status===200 && $result->data['url']===$url && str_starts_with($result->data['logo'],'data:image/png;base64,'),'PNG inputs');
expect_qr($http_args['limit_response_size']===2097153,'bounded logo fetch');
$url='https://example.invalid/v/changed'; expect_qr(request_qr()->data['url']===$url,'fresh URL');
foreach(['nonce','logged','cap','access'] as $field) { $$field=false; expect_qr(request_qr()->status===403,$field); $$field=true; }
foreach(['draft','private','trash','mi_archived'] as $state) { expect_qr(request_qr()->status===409,'unpublished'); ob_start(); MI_Event_QR::render(42); expect_qr(ob_get_clean()==='','no unpublished UI'); }
$state='publish'; $meta['_mi_event_qr_enabled']='0'; expect_qr(request_qr()->status===409,'disabled');
ob_start(); MI_Event_QR::render(42); expect_qr(ob_get_clean()==='','no disabled UI');
$meta['_mi_event_qr_enabled']='1'; $type='page'; expect_qr(request_qr()->status===409,'wrong type'); $type='mi_event';
$logo_id=0; expect_qr(request_qr()->status===422,'missing logo'); $logo_id=9;
$response=['code'=>200,'body'=>'<svg></svg>']; expect_qr(request_qr()->status===422,'reject SVG');
$response=['code'=>200,'body'=>str_repeat('x',2097153)]; expect_qr(request_qr()->status===422,'oversize');
$response=['code'=>404,'body'=>'']; expect_qr(request_qr()->status===422,'failed logo');
echo "Event QR: access, nonce, opt-in, publication, current URL, logo validation passed.\n";
