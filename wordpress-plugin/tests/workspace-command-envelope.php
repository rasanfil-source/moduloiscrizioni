<?php
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MI_WORKSPACE_SHARED_SECRET', 'synthetic-workspace-secret-32-characters' );
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_transient( $key ) { return $GLOBALS['nonces'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['nonces'][$key] = $value; }
class WP_Error { public function __construct( public $code, public $message, public $data = array() ) {} }
class WP_REST_Request { public function __construct( private $data ) {} public function get_json_params() { return $this->data; } }
class MI_Spedizione_Email { public static function accoda_comunicazione_operativa( $payload ) { return array( 'ok' => true, 'received' => $payload ); } }
$wpdb = new class { public function prepare( $sql, ...$args ) { return $sql; } public function get_var( $sql ) { return 1; } };
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-workspace-client.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-rest-controller.php';
function dispatch_envelope( $envelope ) { return MI_REST_Controller::workspace_command( new WP_REST_Request( $envelope ) ); }
// The Node integration test passes an envelope produced by the actual GAS sender.
if ( in_array( '--stdin', $argv, true ) ) {
    $result = dispatch_envelope( json_decode( stream_get_contents( STDIN ), true ) );
    echo json_encode( $result, JSON_UNESCAPED_UNICODE );
    exit( is_wp_error( $result ) ? 1 : 0 );
}
function check_envelope( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function envelope( $raw, $protocol = 2 ) {
    $e = array( 'protocollo' => $protocol, 'timestamp' => (int) floor( microtime( true ) * 1000 ), 'nonce' => bin2hex( random_bytes( 16 ) ), 'action' => 'QUEUE_OPERATIONAL_EMAILS' );
    if ( 2 === $protocol ) { $e['payload_firmato'] = $raw; $e['payload_hash'] = hash( 'sha256', $raw ); $content = $e['payload_hash']; }
    else { $e['payload'] = json_decode( $raw, true ); $content = MI_Workspace_Client::stable_json( $e['payload'] ); }
    $e['signature'] = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $e['timestamp'] . "\n" . $e['nonce'] . "\n" . $e['action'] . "\n" . $content, MI_WORKSPACE_SHARED_SECRET, true ) ), '+/', '-_' ), '=' );
    return $e;
}
$raw = '{"message":"Città 🌍 / prova","extra":{},"list":[],"nested":{"empty":{}},"amount":1.0}';
$e = envelope( $raw );
$e['payload'] = array( 'message' => 'unsigned replacement' );
$result = dispatch_envelope( $e );
check_envelope( ! is_wp_error( $result ) && $result['received'] === json_decode( $raw, true ), 'Command did not consume the authenticated JSON' );
check_envelope( is_wp_error( dispatch_envelope( $e ) ), 'Replay accepted' );
$legacy = envelope( '{"message":"old Apps Script"}', 1 );
unset( $legacy['protocollo'] );
check_envelope( dispatch_envelope( $legacy )['received']['message'] === 'old Apps Script', 'Legacy deployment broken' );
foreach ( array( 'raw', 'hash', 'signature', 'action', 'timestamp', 'protocol', 'missing' ) as $tamper ) {
    $bad = envelope( $raw );
    if ( 'raw' === $tamper ) $bad['payload_firmato'] .= ' ';
    if ( 'hash' === $tamper ) $bad['payload_hash'] = str_repeat( '0', 64 );
    if ( 'signature' === $tamper ) $bad['signature'] = str_repeat( 'a', 43 );
    if ( 'action' === $tamper ) $bad['action'] = 'GET_EMAIL_MODE';
    if ( 'timestamp' === $tamper ) $bad['timestamp'] -= 180000;
    if ( 'protocol' === $tamper ) $bad['protocollo'] = 3;
    if ( 'missing' === $tamper ) unset( $bad['payload_firmato'] );
    check_envelope( is_wp_error( dispatch_envelope( $bad ) ), 'Tampering accepted: ' . $tamper );
}
foreach ( array( '[]', 'null', '1', '{broken', str_repeat( ' ', 2000001 ) ) as $invalid ) check_envelope( is_wp_error( dispatch_envelope( envelope( $invalid ) ) ), 'Invalid payload accepted' );
check_envelope( dispatch_envelope( envelope( '{}' ) )['received'] === array(), 'Empty root object rejected' );
echo "PASS: signed JSON dispatch, Unicode, empty objects, legacy protocol, tampering and replay rejection.\n";
