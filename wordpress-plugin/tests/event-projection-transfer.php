<?php
define( 'ABSPATH', __DIR__ );
function absint( $value ) { return abs( (int) $value ); }
function get_post_meta( $id, $key, $single = true ) { return ''; }
class MI_Workspace_Client {
	public static function stable_json( $value ) { return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-event-projection.php';
function verify_transfer( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$base = array( 'fingerprint' => str_repeat( 'a', 64 ), 'revision' => '1', 'projection' => array( 'event' => array( 'id_evento' => '42', 'title' => 'Small' ) ) );
$small = MI_Event_Projection::request_payload( 42, false, $base )['payload'];
verify_transfer( isset( $small['projection_gzip'] ) && ! isset( $small['projection_pull'] ), 'Small event did not use the signed envelope.' );
$base['projection']['event']['title'] = base64_encode( random_bytes( 2100000 ) );
$large = MI_Event_Projection::request_payload( 42, false, $base )['payload'];
verify_transfer( ! isset( $large['projection_gzip'] ) && true === $large['projection_pull'], 'Large event was not switched to authenticated pull.' );
$transfer = MI_Event_Projection::encoded_snapshot( $base );
verify_transfer( strlen( $transfer['projection_gzip'] ) > 1800000 && hash( 'sha256', MI_Workspace_Client::stable_json( $base['projection'] ) ) === $large['projection_hash'], 'Large transfer hash mismatch.' );
echo "PASS: trasferimento diretto e prelievo firmato delle proiezioni grandi.\n";
