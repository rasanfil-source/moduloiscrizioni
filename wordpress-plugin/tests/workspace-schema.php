<?php
define( 'ABSPATH', __DIR__ );
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-workspace-settings.php';
$check = new ReflectionMethod( MI_Workspace_Settings::class, 'supports_direct_projection' );
$valid = array( 'direct_projection' => true, 'standalone' => true, 'projection_pull' => true, 'central_workbook' => false );
if ( true !== $check->invoke( null, $valid ) ) throw new RuntimeException( 'Autonomous deployment rejected.' );
foreach ( array( 'direct_projection', 'standalone', 'projection_pull', 'central_workbook' ) as $key ) {
	$invalid = $valid;
	$invalid[$key] = 'central_workbook' === $key ? true : false;
	if ( false !== $check->invoke( null, $invalid ) ) throw new RuntimeException( 'Unsafe capability accepted: ' . $key );
}
if ( false !== $check->invoke( null, new WP_Error() ) ) throw new RuntimeException( 'Remote error accepted as capability.' );
echo "PASS: verifica firmata del progetto autonomo e rifiuto del workbook centrale.\n";
