<?php
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( (string) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function remove_accents( $value ) { return strtr( $value, array( 'é' => 'e', 'è' => 'e' ) ); }
class WP_Error {}
class MI_Portal_Management { public static function allowed() { return true; } }
class MI_Access { public static function can_access_event( $id ) { return true; } }
class MI_Shortcode { public static function url_iscrizione( $id ) { return 'https://example.invalid/event'; } }
class PageSqlDatabase {
	public $prefix = 'wp_';
	public $last_error = '';
	public $queries = array();
	public function prepare( $sql, ...$args ) { foreach ( $args as $arg ) $sql = preg_replace( '/%[ds]/', is_int( $arg ) ? (string) $arg : "'" . str_replace( "'", "''", $arg ) . "'", $sql, 1 ); return $sql; }
	public function get_results( $sql, $format = null ) { $this->queries[] = $sql; return array(); }
	public function get_var( $sql ) { $this->queries[] = $sql; return 0; }
	public function get_row( $sql, $format = null ) { $this->queries[] = $sql; return array( 'total' => 0, 'max_id' => 0, 'revisions' => 0 ); }
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-list.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-service.php';
$GLOBALS['wpdb'] = new PageSqlDatabase();
MI_Management_Service::page( 42, array( 'state' => 'CANCELLED', 'includeClosed' => true ) );
$sql = implode( "\n", $GLOBALS['wpdb']->queries );
if ( ! str_contains( $sql, "(p.status='CANCELLED' OR r.status='CANCELLED')" ) ) throw new RuntimeException( 'Cancelled booking participants were omitted.' );
$GLOBALS['wpdb']->queries = array();
MI_Management_Service::page( 42, array() );
$sql = implode( "\n", $GLOBALS['wpdb']->queries );
if ( ! str_contains( $sql, "p.status<>'CANCELLED'" ) ) throw new RuntimeException( 'Open-booking participants used a narrower state filter.' );
$GLOBALS['wpdb']->queries = array();
MI_Management_Service::page( 42, array( 'query' => 'René', 'view' => 'orders' ) );
$sql = implode( "\n", $GLOBALS['wpdb']->queries );
if ( str_contains( $sql, ' LIKE ' ) ) throw new RuntimeException( 'Accent-sensitive SQL prefilter could lose order search results.' );
if ( ! str_contains( $sql, 'AND r.id>0 ORDER BY r.id LIMIT 200' ) || str_contains( $sql, 'OFFSET' ) ) throw new RuntimeException( 'Advanced search did not use bounded keyset batches.' );
$GLOBALS['wpdb']->queries = array();
MI_Management_Service::page( 42, array( 'view' => 'orders', 'sort' => 'room' ) );
$sql = implode( "\n", $GLOBALS['wpdb']->queries );
if ( ! str_contains( $sql, 'ORDER BY (r.status IN (\'CANCELLED\',\'EXPIRED\')),r.order_code ASC,r.id' ) ) throw new RuntimeException( 'Order room sorting did not follow the shared selector tie-breaker.' );
echo "PASS: stati dei partecipanti e ricerca avanzata SQL senza falsi negativi.\n";
