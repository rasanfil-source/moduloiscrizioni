<?php

define( 'ABSPATH', __DIR__ . '/' );

final class WP_Error {
	public $code;
	public $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

final class Fake_WPDB {
	public $prefix = 'wp_';
	public $registration_count = 3;
	public function prepare( $query, ...$arguments ) { return array( $query, $arguments ); }
	public function get_var( $prepared ) { return $this->registration_count; }
}

$wpdb = new Fake_WPDB();
$mi_test_can_manage = true;
$mi_test_types = array( 7342 => 'mi_event', 7000 => 'mi_activity', 7428 => 'mi_activity', 9000 => 'post' );
$mi_test_statuses = array( 7342 => 'draft', 7000 => 'publish', 7428 => 'draft', 9000 => 'publish' );
$mi_test_meta = array( 7342 => array( '_mi_activity_id' => 7000 ) );
$mi_test_logs = array();

function absint( $value ) { return abs( (int) $value ); }
function current_user_can( $capability ) { global $mi_test_can_manage; return 'manage_options' === $capability && $mi_test_can_manage; }
function get_post_type( $post_id ) { global $mi_test_types; return $mi_test_types[ $post_id ] ?? false; }
function get_post_status( $post_id ) { global $mi_test_statuses; return $mi_test_statuses[ $post_id ] ?? false; }
function get_post_meta( $post_id, $key, $single = false ) { global $mi_test_meta; return $mi_test_meta[ $post_id ][ $key ] ?? ''; }
function update_post_meta( $post_id, $key, $value ) { global $mi_test_meta; $mi_test_meta[ $post_id ][ $key ] = $value; return true; }
function add_post_meta( $post_id, $key, $value ) { global $mi_test_logs; $mi_test_logs[] = array( $post_id, $key, $value ); return count( $mi_test_logs ); }
function get_current_user_id() { return 11; }
function current_time( $type, $gmt = false ) { return '2026-08-26 20:00:00'; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }

final class MI_Event_Post_Type {
	const EVENT_TYPE = 'mi_event';
	const ACTIVITY_TYPE = 'mi_activity';
}

require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-event-activity-migration.php';

function expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$registrations_snapshot = array( 'registration-1', 'registration-2', 'registration-3' );
$registrations_before = serialize( $registrations_snapshot );
$result = MI_Event_Activity_Migration::migrate( 7342, 7428, 'MIGRA 7342 A 7428' );
expect( ! is_wp_error( $result ), 'migrazione valida rifiutata' );
expect( 7428 === $mi_test_meta[7342]['_mi_activity_id'], 'attività evento non aggiornata' );
expect( '1' === $mi_test_meta[7342]['_mi_needs_republish'], 'ripubblicazione non richiesta' );
expect( 3 === $result['registrations_before'] && 3 === $result['registrations_after'], 'conteggio iscrizioni non conservato' );
expect( $registrations_before === serialize( $registrations_snapshot ), 'iscrizioni modificate' );
expect( 1 === count( $mi_test_logs ) && 7000 === $mi_test_logs[0][2]['from_activity_id'] && 7428 === $mi_test_logs[0][2]['to_activity_id'], 'audit migrazione non scritto' );

$mi_test_meta[7342]['_mi_activity_id'] = 7000;
$bad_confirmation = MI_Event_Activity_Migration::migrate( 7342, 7428, 'MIGRA 7342 A 7000' );
expect( is_wp_error( $bad_confirmation ) && 'mi_migration_confirmation_invalid' === $bad_confirmation->code, 'conferma errata accettata' );
expect( 7000 === $mi_test_meta[7342]['_mi_activity_id'], 'conferma errata ha modificato attività' );

$bad_activity = MI_Event_Activity_Migration::migrate( 7342, 9000, 'MIGRA 7342 A 9000' );
expect( is_wp_error( $bad_activity ) && 'mi_migration_activity_invalid' === $bad_activity->code, 'destinazione non-attività accettata' );

$mi_test_can_manage = false;
$forbidden = MI_Event_Activity_Migration::migrate( 7342, 7428, 'MIGRA 7342 A 7428' );
expect( is_wp_error( $forbidden ) && 'mi_migration_forbidden' === $forbidden->code, 'utente non amministratore autorizzato' );
$mi_test_can_manage = true;

$wpdb->registration_count = 0;
$not_required = MI_Event_Activity_Migration::migrate( 7342, 7428, 'MIGRA 7342 A 7428' );
expect( is_wp_error( $not_required ) && 'mi_migration_not_required' === $not_required->code, 'migrazione speciale consentita senza iscrizioni' );

fwrite( STDOUT, "Event activity migration tests: OK\n" );
