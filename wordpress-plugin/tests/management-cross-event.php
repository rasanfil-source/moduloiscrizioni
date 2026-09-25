<?php
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
class WP_Error {}
class MI_Portal_Management { static function allowed() { return true; } }
class MI_Access { static function can_access_event( $id ) { return 42 === $id; } }
class CrossEventDatabase {
	public $prefix = 'wp_', $last_error = '', $sql = '';
	function prepare( $sql, ...$args ) { foreach ( $args as $arg ) $sql = preg_replace( '/%s/', "'" . str_replace( "'", "''", $arg ) . "'", $sql, 1 ); return $sql; }
	function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
	function get_results( $sql, $format ) { $this->sql = $sql; return array(); }
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-management-service.php';
$wpdb = new CrossEventDatabase();
function check_cross( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
foreach ( array( 'CONFIRMED', 'PENDING_PAYMENT', 'WAITLISTED', 'WAITLIST_OFFERED', 'CANCELLED', 'EXPIRED' ) as $status ) {
	MI_Management_Service::all_people( array( 42, 99 ), '', 30, false, $status );
	check_cross( str_contains( $wpdb->sql, "r.status='$status'" ), 'Missing status filter' );
	check_cross( str_contains( $wpdb->sql, 'r.event_id IN (42)' ) && str_contains( $wpdb->sql, 'LIMIT 30,31' ), 'Scope or pagination lost' );
	check_cross( ! str_contains( $wpdb->sql, 'r.status NOT IN' ), 'Explicit closed state excluded' );
	check_cross( str_contains( $wpdb->sql, "p.status='ACTIVE'" ) === ! in_array( $status, array( 'CANCELLED', 'EXPIRED' ), true ), 'Wrong participant scope' );
}
MI_Management_Service::all_people( array( 42 ), 'Mario 10%', 0, false, "' OR 1=1 --" );
check_cross( ! str_contains( $wpdb->sql, 'OR 1=1' ) && str_contains( $wpdb->sql, 'r.status NOT IN' ), 'Invalid status bypasses default' );
check_cross( str_contains( $wpdb->sql, 'r.buyer_first_name,r.buyer_last_name' ) && str_contains( $wpdb->sql, '10\\%' ), 'Buyer search or LIKE escaping missing' );
MI_Management_Service::all_people( array( 42 ), '', 0, true );
check_cross( ! str_contains( $wpdb->sql, 'r.status NOT IN' ), 'Include closed ignored' );
$wpdb->sql = '';
$result = MI_Management_Service::all_people( array( 99 ), '', 0, true, 'CANCELLED' );
check_cross( $result === array( 'items' => array(), 'more' => false ) && '' === $wpdb->sql, 'Unauthorized event queried' );
echo "PASS cross-event states, closed registrations, scope, pagination and escaped search.\n";
