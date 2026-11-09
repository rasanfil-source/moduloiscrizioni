<?php
define( 'ABSPATH', __DIR__ ); define( 'ARRAY_A', 'ARRAY_A' );
class WP_Error { public function __construct( public $code, public $message ) {} public function get_error_code() { return $this->code; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_current_user_id() { return 7; }
function absint( $v ) { return abs( (int) $v ); }
function get_post_status( $id ) { return 'draft'; }
function get_option( $k, $d = false ) { return $GLOBALS['options'][$k] ?? $d; }
function update_option( $k, $v, ...$args ) { $GLOBALS['options'][$k] = $v; return true; }
function add_option( $k, $v, ...$args ) { if ( isset( $GLOBALS['options'][$k] ) ) return false; $GLOBALS['options'][$k] = $v; $GLOBALS['ids'][$k] = count( $GLOBALS['ids'] ) + 1; return true; }
function delete_option( $k ) { unset( $GLOBALS['options'][$k] ); }
function wp_next_scheduled( $hook ) { return false; }
function wp_schedule_single_event( ...$args ) { $GLOBALS['scheduled'][] = $args; }
function add_action( ...$args ) { $GLOBALS['actions'][] = $args; }
function wp_salt( $scheme ) { return 'synthetic'; }
function wp_remote_post( ...$args ) { $GLOBALS['loopbacks'][] = $args; }
function admin_url( $path ) { return 'https://example.invalid/' . $path; }
class MI_Spedizione_Email {
    public static $mode = 'OPERATIVO', $ready = true;
    public static function modalita() { return self::$mode; }
    public static function stato_nuova_email( $snapshot ) { return ! self::$ready || self::$mode === 'ANTEPRIMA' ? 'PREVIEW' : ( self::$mode === 'OPERATIVO' ? 'PENDING' : 'TEST_PENDING' ); }
    public static function accoda_comunicazione_operativa( $payload ) { return array( 'count' => count( $payload['recipients'] ), 'origin_keys' => array(), 'mode' => 'ANTEPRIMA' ); }
}
class MI_Event_Deletion { public static function release( $id ) {} }
class AuditJobsDatabase {
    public $options = 'wp_options';
    public function esc_like( $v ) { return $v; }
    public function prepare( $sql, ...$args ) { return $args; }
    public function get_results( $args, $format ) {
        $rows = array();
        foreach ( $GLOBALS['ids'] as $key => $id ) if ( str_starts_with( $key, 'mi_email_pending_' ) && isset( $GLOBALS['options'][$key] ) && ( $id > $args[1] || str_starts_with( $key, 'mi_email_pending_test_' ) ) ) $rows[] = array( 'option_id' => $id, 'option_value' => $GLOBALS['options'][$key] );
        usort( $rows, fn( $a, $b ) => $a['option_id'] <=> $b['option_id'] );
        return array_slice( $rows, 0, 10 );
    }
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-email-jobs.php';
function reset_jobs() { $GLOBALS['options'] = $GLOBALS['ids'] = $GLOBALS['scheduled'] = $GLOBALS['actions'] = $GLOBALS['loopbacks'] = array(); }
function record_job( $name, $result ) { echo json_encode( array( 'case' => $name, 'actual' => $result ) ) . "\n"; }
ob_start();
$wpdb = new AuditJobsDatabase(); reset_jobs();
$payload = array( 'communication_id' => 'campaign', 'event_id' => 42, 'recipients' => array_fill( 0, 30, array( 'order_code' => 'DEMO' ) ) );
MI_Spedizione_Email::$ready = false;
$result = MI_Email_Jobs::enqueue( $payload );
record_job( 'draft_without_verified_sender', is_wp_error( $result ) ? $result->get_error_code() : $result );
MI_Spedizione_Email::$ready = true;
$campaign = MI_Email_Jobs::enqueue( $payload );
record_job( 'draft_job_reported_mode', $campaign['mode'] );
$test = MI_Email_Jobs::enqueue( array_replace( $payload, array( 'communication_id' => 'sample', 'test_only' => true ) ) );
record_job( 'test_replaced_last_campaign', get_option( 'mi_email_last_job_7' ) === $test['job_id'] && $test['job_id'] !== $campaign['job_id'] );
reset_jobs();
foreach ( range( 1, 11 ) as $i ) {
    $id = hash( 'sha256', 'job-' . $i );
    add_option( 'mi_email_pending_' . $id, $id );
    update_option( 'mi_email_job_' . $id, array( 'state' => 'pending', 'mode' => $i <= 10 ? 'PROVA' : 'OPERATIVO', 'event_id' => 42, 'offset' => 0, 'total' => 30, 'origin_keys' => array(), 'payload' => $payload ) );
    if ( $i === 11 ) $active_id = $id;
}
foreach ( range( 1, 4 ) as $run ) {
    $GLOBALS['scheduled'] = $GLOBALS['actions'] = array(); MI_Email_Jobs::prepare_next();
    record_job( 'paused_jobs_run_' . $run, array( 'prepared' => get_option( 'mi_email_job_' . $active_id )['offset'], 'next_steps' => count( $GLOBALS['scheduled'] ) + count( $GLOBALS['actions'] ) ) );
}
reset_jobs(); MI_Spedizione_Email::$mode = 'ANTEPRIMA'; update_option( 'mi_email_retry_after', time() + 900 );
MI_Email_Jobs::dispatch();
record_job( 'preview_dispatch_global_pause', count( $GLOBALS['loopbacks'] ) );
$preview_job = MI_Email_Jobs::enqueue( array_replace( $payload, array( 'communication_id' => 'preview-paused' ) ) );
MI_Email_Jobs::prepare_next();
record_job( 'preview_prepare_while_global_pause', get_option( 'mi_email_job_' . $preview_job['job_id'] )['offset'] );

$cases=array_column(array_map(fn($v)=>json_decode($v,true),explode("\n",trim(ob_get_clean()))),'actual','case');
if(!is_array($cases['draft_without_verified_sender']) || $cases['draft_job_reported_mode']!=='ANTEPRIMA')throw new RuntimeException('Draft effective mode invalid');
if($cases['test_replaced_last_campaign'])throw new RuntimeException('Test hid actual campaign');
foreach(range(1,3) as $run)if($cases['paused_jobs_run_'.$run]['prepared']!==10*$run)throw new RuntimeException('Paused jobs stalled active job');
if($cases['preview_dispatch_global_pause']!==1 || $cases['preview_prepare_while_global_pause']!==10)throw new RuntimeException('Transport pause blocked preview');
echo "PASS: effective draft mode, separate test pointer, paused-job scan and preview during pause.\n";
