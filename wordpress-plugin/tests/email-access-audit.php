<?php
define( 'ABSPATH', __DIR__ );
class AuditResponse extends RuntimeException { public function __construct( public $data ) { parent::__construct( json_encode( $data ) ); } }
function get_current_user_id() { return 7; }
function get_user_meta( ...$args ) { return $GLOBALS['suspended']; }
function is_user_logged_in() { return true; }
function is_ssl() { return true; }
function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['caps'], true ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $s ) ), '-' ); }
function sanitize_text_field( $s ) { return strip_tags( $s ); }
function wp_unslash( $s ) { return $s; }
function absint( $s ) { return abs( (int) $s ); }
function get_option( $k, $default = false ) { return $GLOBALS['options'][$k] ?? $default; }
function get_posts( ...$args ) { return array(); }
function update_option( $k, $value, ...$args ) { $GLOBALS['options'][$k] = $value; return true; }
function check_admin_referer( ...$args ) { return true; }
function check_ajax_referer( ...$args ) { return true; }
function wp_send_json_success( $data ) { throw new AuditResponse( array( 'status' => 200, 'data' => $data ) ); }
function wp_send_json_error( $data, $status ) { throw new AuditResponse( array( 'status' => $status, 'data' => $data ) ); }
function wp_die( $text, ...$args ) { throw new AuditResponse( array( 'denied' => $text ) ); }
function wp_safe_redirect( $url ) { throw new AuditResponse( array( 'redirect' => $url ) ); }
function home_url( $p = '' ) { return 'https://example.invalid/' . ltrim( $p, '/' ); }
function add_query_arg( $args, $v, $url = null ) { if ( is_array( $args ) ) $url = $v; else $args = array( $args => $v ); return $url . '?' . http_build_query( $args ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return esc_attr( $s ); }
function wp_logout_url( $url ) { return $url; }
class MI_Portal_Payments { public static function allowed() { return false; } }
class MI_Portal_Management { public static function render() { echo '<section>Iscrizioni</section>'; } }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-access.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-modello-email.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-portal.php';
function run_access( $name, $callback ) { try { $callback(); } catch ( AuditResponse $response ) { echo json_encode( array( 'case' => $name, 'actual' => $response->data ), JSON_UNESCAPED_UNICODE ) . "\n"; } }
ob_start();
$GLOBALS['suspended'] = true; $GLOBALS['options'] = array();
$GLOBALS['caps'] = array( 'mi_portal_access', 'mi_manage_all_events', 'mi_manage_communications' );
$_GET = array( 'mi_portal' => 1 ); $_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'mi_portal_action' => 'add_communication_type', 'communication_type_label' => 'Tipo sintetico' );
run_access( 'suspended_add_type', fn() => MI_Portal::handle_actions() );
echo json_encode( array( 'case' => 'suspended_type_saved', 'actual' => get_option( 'mi_custom_communication_types' ) ) ) . "\n";
$_POST = array( 'template_type' => 'PRE_DEPARTURE_REMINDER' );
run_access( 'suspended_default_text', fn() => MI_Portal::ajax_communication_default_text() );
run_access( 'suspended_event_list_control', fn() => MI_Portal::ajax_communication_events() );
$_POST = array( 'mi_portal_action' => 'delete_communication_type', 'communication_type_code' => 'CUSTOM_TIPO_SINTETICO' );
run_access( 'suspended_delete_type', fn() => MI_Portal::handle_actions() );
$GLOBALS['suspended'] = false;
foreach ( array( 'Indicazioni per il viaggio di andata', 'Indicazioni per il viaggio di ritorno' ) as $label ) {
    $_POST = array( 'mi_portal_action' => 'add_communication_type', 'communication_type_label' => $label );
    run_access( 'custom_code_' . $label, fn() => MI_Portal::handle_actions() );
}
$GLOBALS['caps'] = array( 'mi_portal_access' );
$html = MI_Portal::render();
echo json_encode( array( 'case' => 'portal_only_navigation', 'shows_messages' => str_contains( $html, '>Messaggi</a>' ) ) ) . "\n";
run_access( 'portal_only_ajax_denied', fn() => MI_Portal::ajax_communication_default_text() );

$lines=array_map(fn($v)=>json_decode($v,true),explode("\n",trim(ob_get_clean())));
$cases=array_column($lines,null,'case');
foreach(['suspended_add_type','suspended_delete_type'] as $case) if(empty($cases[$case]['actual']['denied']))throw new RuntimeException('Suspended type mutation allowed');
if($cases['suspended_type_saved']['actual']!==false)throw new RuntimeException('Suspended registry changed');
if($cases['suspended_default_text']['actual']['status']!==403 || $cases['portal_only_ajax_denied']['actual']['status']!==403 || $cases['portal_only_navigation']['shows_messages'])throw new RuntimeException('Communication permission bypass');
if(count(get_option('mi_custom_communication_types'))!==2)throw new RuntimeException('Distinct long labels collided');
echo "PASS: suspended endpoints, capability navigation and custom type collisions.\n";
