<?php
define( 'ABSPATH', __DIR__ );
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	public function __construct( private $code, private $message, private $data = array() ) {}
	public function get_error_code() { return $this->code; }
}
class MI_Event_Deletion {
	public static $released = 0;
	public static function enter( $id ) { return true; }
	public static function release( $id ) { self::$released++; }
}
class MI_Event_Projection {
	public static function snapshot( $id ) { return $GLOBALS['snapshot']; }
	public static function encoded_snapshot( $snapshot ) {
		$json = json_encode( $snapshot['projection'] );
		return array( 'projection_gzip' => base64_encode( gzencode( $json ) ), 'projection_hash' => hash( 'sha256', $json ) );
	}
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-rest-controller.php';
function verify_projection( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$GLOBALS['snapshot'] = array( 'fingerprint' => str_repeat( 'a', 64 ), 'projection' => array( 'event' => array( 'id_evento' => '42' ) ) );
$expected = MI_Event_Projection::encoded_snapshot( $GLOBALS['snapshot'] );
$method = new ReflectionMethod( MI_REST_Controller::class, 'event_projection_for_workspace' );
$payload = array( 'event_id' => '42', 'fingerprint' => $GLOBALS['snapshot']['fingerprint'], 'projection_hash' => $expected['projection_hash'] );
$result = $method->invoke( null, $payload );
verify_projection( true === $result['ok'] && $result['projection_gzip'] === $expected['projection_gzip'] && 1 === MI_Event_Deletion::$released, 'Authenticated pull did not return the canonical snapshot.' );
$changed = $method->invoke( null, array_merge( $payload, array( 'fingerprint' => str_repeat( 'b', 64 ) ) ) );
verify_projection( is_wp_error( $changed ) && 'mi_projection_changed' === $changed->get_error_code() && 2 === MI_Event_Deletion::$released, 'Stale fingerprint was accepted or event lease was retained.' );
$invalid = $method->invoke( null, array_merge( $payload, array( 'projection_hash' => 'bad' ) ) );
verify_projection( is_wp_error( $invalid ) && 'mi_projection_invalid' === $invalid->get_error_code(), 'Invalid digest was accepted.' );
echo "PASS: prelievo canonico, impronta obsoleta e rilascio del lease.\n";
