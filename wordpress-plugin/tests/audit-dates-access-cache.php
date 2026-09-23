<?php
// Synthetic regression cases for the external audit, without WordPress or a live DB.
define( 'ABSPATH', __DIR__ ); define( 'ARRAY_A', 'ARRAY_A' ); define( 'HOUR_IN_SECONDS', 3600 );
class WP_Error { public function __construct( public $code, public $message ) {} }
function absint( $value ) { return abs( (int) $value ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
function get_current_user_id() { return 1; }
function wp_get_current_user() { return get_user_by( 'id', 1 ); }
function get_user_by( $field, $id ) { return $GLOBALS['users'][$id] ?? false; }
function user_can( $user, $cap ) { return in_array( $cap, $user->caps, true ); }
function get_user_meta( $id, $key, $single ) { return $GLOBALS['scope'][$id][$key] ?? array(); }
function get_post_meta( $id, $key, $single ) { return 9; }
function get_transient( $key ) { return $GLOBALS['cache'][$key] ?? false; }
function set_transient( $key, $data, $ttl ) { $GLOBALS['cache'][$key] = $data; }
function check_audit( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-access.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-portal.php';

$now = new DateTimeImmutable( 'now', wp_timezone() );
$date = fn( $relative ) => $now->modify( $relative )->format( 'Y-m-d\TH:i' );
$event = array( 'opens_at' => '', 'closes_at' => $date( '+2 days' ), 'event_starts_at' => $date( '+3 days' ) );
check_audit( 'OPEN' === MI_Registration_Service::registration_time_state( $event ), 'Blank opening must open immediately' );
check_audit( true === MI_Registration_Service::validate_event_dates( $event ), 'Blank opening must be publishable' );
foreach ( array( 'invalid', '2030-02-30T10:00', '2030-01-01T25:00', '2030-1-01T10:00', "2030-01-01T10:00\0" ) as $bad ) {
    check_audit( 'MISCONFIGURED' === MI_Registration_Service::registration_time_state( array_replace( $event, array( 'opens_at' => $bad ) ) ), 'Malformed opening accepted: ' . $bad );
    check_audit( MI_Registration_Service::validate_event_dates( array_replace( $event, array( 'closes_at' => $bad ) ) ) instanceof WP_Error, 'Malformed closing accepted' );
    check_audit( MI_Registration_Service::validate_event_dates( array_replace( $event, array( 'event_starts_at' => $bad ) ) ) instanceof WP_Error, 'Malformed event start accepted' );
}
foreach ( array( $event['closes_at'], $date( '+4 days' ) ) as $open ) check_audit( 'MISCONFIGURED' === MI_Registration_Service::registration_time_state( array_replace( $event, array( 'opens_at' => $open ) ) ), 'Zero or negative registration window accepted' );
check_audit( 'NOT_OPEN' === MI_Registration_Service::registration_time_state( array_replace( $event, array( 'opens_at' => $date( '+1 day' ) ) ) ), 'Future opening ignored' );
check_audit( 'CLOSED' === MI_Registration_Service::registration_time_state( array_replace( $event, array( 'closes_at' => $date( '-1 day' ) ) ) ), 'Past closing ignored' );
foreach ( array( array( 'closes_at' => '' ), array( 'event_starts_at' => '' ), array( 'event_starts_at' => $date( '+1 day' ) ), array( 'closes_at' => $date( '-1 day' ) ) ) as $invalid ) check_audit( MI_Registration_Service::validate_event_dates( array_replace( $event, $invalid ) ) instanceof WP_Error, 'Invalid publication dates accepted' );
check_audit( true === MI_Registration_Service::validate_event_dates( array_replace( $event, array( 'opens_at' => $date( '+1 day' ), 'event_starts_at' => $event['closes_at'] ) ) ), 'Future opening or closing at event start rejected' );

$GLOBALS['users'] = array(
    1 => (object) array( 'ID' => 1, 'roles' => array( 'administrator', 'mi_assigned_event_manager' ), 'caps' => array( 'manage_options' ) ),
    2 => (object) array( 'ID' => 2, 'roles' => array( 'mi_assigned_event_manager' ), 'caps' => array() ),
    3 => (object) array( 'ID' => 3, 'roles' => array(), 'caps' => array( 'mi_manage_all_events' ) ),
    4 => (object) array( 'ID' => 4, 'roles' => array( 'mi_group_manager' ), 'caps' => array() ),
);
$GLOBALS['scope'] = array( 2 => array( '_mi_event_scope' => array( 42 ) ), 4 => array( '_mi_activity_scope' => array( 9 ) ) );
foreach ( array( 1, 3 ) as $id ) check_audit( 'ALL' === MI_Access::event_ids( $id ) && MI_Access::can_access_event( 999, $id ), 'Global permissions differ between list and detail' );
check_audit( MI_Access::can_access_event( 999 ), 'Current global user rejected' );
check_audit( MI_Access::can_access_event( 42, 2 ) && ! MI_Access::can_access_event( 43, 2 ), 'Assigned scope bypassed' );
check_audit( MI_Access::can_access_event( 42, 4 ) && ! MI_Access::can_access_event( 42, 99 ), 'Group or unknown user access changed' );
$GLOBALS['scope'][2]['_mi_access_suspended']=1;
check_audit(!MI_Access::can_access_event(42,2)&&MI_Access::event_ids(2)===array(),'Suspended session retained event access');
unset($GLOBALS['scope'][2]['_mi_access_suspended']);
class MI_Event_Post_Type {const EVENT_TYPE='mi_event';}
function get_post_type($id){return $id===1000?'post':'mi_event';}
function get_post($id){return (object)['post_status'=>'draft','post_author'=>2];}
foreach(['edit_post','delete_post','read_post'] as $cap){
 check_audit(MI_Access::map_event_meta_cap(['original'],$cap,2,[43])===['do_not_allow'],'Core capability escaped scope');
 check_audit(MI_Access::map_event_meta_cap(['original'],$cap,2,[42])!==['do_not_allow'],'Assigned event rejected');
 check_audit(MI_Access::map_event_meta_cap(['original'],$cap,2,[1000])===['original'],'Other post type changed');
}

class CardAuditDB {
    public $prefix = 'wp_', $last_error = '', $queries = 0, $fail = false;
    public function get_results( $sql, $mode ) {
        $this->queries++;
        $this->last_error = $this->fail ? 'synthetic read failure' : '';
        preg_match( '/IN \(([0-9,]+)\)/', $sql, $match );
        return array_map( fn( $id ) => array( 'event_id' => $id, 'config_json' => json_encode( array( 'title' => 'Revision ' . $id, 'capacity' => 42, 'opens_at' => '', 'participant_fields' => array_fill( 0, 100, 'large unused configuration' ) ) ) ), explode( ',', $match[1] ) );
    }
}
$wpdb = new CardAuditDB();
$cards = new ReflectionMethod( MI_Portal::class, 'published_card_summaries' );
$first = $cards->invoke( null, array( 2, 1, 2 ) );
check_audit( $wpdb->queries === 1 && ! isset( $first[1]['participant_fields'] ) && isset( $first[1]['opens_at'] ), 'Cache is not a lightweight projection' );
check_audit( $first === $cards->invoke( null, array( 1, 2 ) ) && $wpdb->queries === 1, 'Stable revision set reread full snapshots' );
$changed = $cards->invoke( null, array( 3, 2 ) );
check_audit( isset( $changed[3] ) && ! isset( $changed[1] ) && $wpdb->queries === 2, 'Revision change did not invalidate' );
$subset = $cards->invoke( null, array( 2 ) );
check_audit( count( $subset ) === 1 && ! isset( $subset[1] ), 'Cache leaked a different scope' );
$wpdb->fail = true; check_audit( array() === $cards->invoke( null, array( 4 ) ), 'Failed query exposed a partial cache' );
$wpdb->fail = false; check_audit( isset( $cards->invoke( null, array( 4 ) )[4] ), 'Failed query persisted' );
echo "PASS: optional opening, strict dates/publication, global and assigned access, lightweight revision cache and read failures.\n";
