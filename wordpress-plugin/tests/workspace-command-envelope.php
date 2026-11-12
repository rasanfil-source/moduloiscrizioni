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
class MI_Spedizione_Email { public static function retention_event_deadlines( $payload ) { return array( 'ok' => true, 'received' => $payload ); } }
$wpdb = new class {
    public $prefix = 'wp_', $receipts = array(), $fail = false;
    public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
    public function query( $prepared ) {
        list( $sql, $args ) = $prepared;
        if ( str_starts_with( $sql, 'DELETE FROM wp_mi_workspace_nonces WHERE expires_at <' ) ) {
            $this->receipts = array_filter( $this->receipts, static fn($expires) => $expires >= $args[0] );
            return 0;
        }
        if ( ! str_starts_with( $sql, 'INSERT IGNORE INTO wp_mi_workspace_nonces' ) ) throw new RuntimeException( 'Unexpected nonce SQL' );
        if ( $this->fail ) return false;
        if ( isset( $this->receipts[$args[0]] ) ) return 0;
        $this->receipts[$args[0]] = $args[1];
        return 1;
    }
};
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-workspace-client.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-rest-controller.php';
function dispatch_envelope( $envelope ) { return MI_REST_Controller::workspace_command( new WP_REST_Request( $envelope ) ); }
// La verifica d'integrazione Node usa una busta prodotta dal mittente Apps Script applicativo.
if ( in_array( '--stdin', $argv, true ) ) {
    $result = dispatch_envelope( json_decode( stream_get_contents( STDIN ), true ) );
    echo json_encode( $result, JSON_UNESCAPED_UNICODE );
    exit( is_wp_error( $result ) ? 1 : 0 );
}
function check_envelope( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function envelope( $raw, $protocol = 2, $offset_ms = 0, $action = 'GET_EMAIL_RETENTION' ) {
    $e = array( 'protocollo' => $protocol, 'timestamp' => (int) floor( microtime( true ) * 1000 ), 'nonce' => bin2hex( random_bytes( 16 ) ), 'action' => $action );
    $e['timestamp'] += $offset_ms;
    if ( 2 === $protocol ) { $e['payload_firmato'] = $raw; $e['payload_hash'] = hash( 'sha256', $raw ); $content = $e['payload_hash']; }
    else { $e['payload'] = json_decode( $raw, true ); $content = MI_Workspace_Client::stable_json( $e['payload'] ); }
    $e['signature'] = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $e['timestamp'] . "\n" . $e['nonce'] . "\n" . $e['action'] . "\n" . $content, MI_WORKSPACE_SHARED_SECRET, true ) ), '+/', '-_' ), '=' );
    return $e;
}
$raw = '{"message":"Città 🌍 / prova","extra":{},"list":[],"nested":{"empty":{}},"amount":1.0}';
foreach(['CREATE_EVENT_DRAFT','CREATE_GROUP','GET_MANUAL_REGISTRATION_SCHEMA','CREATE_MANUAL_REGISTRATION','QUEUE_OPERATIONAL_EMAILS'] as $retired){
 check_envelope(dispatch_envelope(envelope('{}',2,0,$retired))->code==='mi_workspace_action_not_allowed','Retired signed command accepted: '.$retired);
}
$e = envelope( $raw );
$e['payload'] = array( 'message' => 'unsigned replacement' );
$result = dispatch_envelope( $e );
check_envelope( ! is_wp_error( $result ) && $result['received'] === json_decode( $raw, true ), 'Command did not consume the authenticated JSON' );
check_envelope( is_wp_error( dispatch_envelope( $e ) ), 'Replay accepted' );
$ahead = envelope( $raw, 2, 110000 );
check_envelope( ! is_wp_error( dispatch_envelope( $ahead ) ), 'Valid skew rejected' );
$GLOBALS['nonces'] = array();
check_envelope( dispatch_envelope( $ahead )->code === 'mi_workspace_replay', 'Cache eviction allowed replay' );
check_envelope( $wpdb->receipts[hash('sha256',$ahead['nonce'])] === $ahead['timestamp'] + 120000, 'Receipt expires before signature' );
$wpdb->receipts['expired'] = 1;
$wpdb->fail = true;
$retry = envelope( $raw );
check_envelope( dispatch_envelope( $retry )->data['status'] === 503 && ! isset($wpdb->receipts['expired']), 'Failed SQL claim dispatched or stale receipt retained' );
$wpdb->fail = false;
check_envelope( ! is_wp_error( dispatch_envelope( $retry ) ), 'Failed claim prevented retry' );
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
