<?php
defined( 'ABSPATH' ) || exit;

/** Rebuildable read models. Permissions are checked by callers on every request;
 * cache identity covers canonical row revisions, event state and inherited schema.
 */
final class MI_Event_Read_Cache {
	public static function state( $event_id ) {
		global $wpdb;
		$event_id = absint( $event_id );
		$rows = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) total,COALESCE(MAX(id),0) max_id,COALESCE(SUM(workspace_revision),0) revisions,COALESCE(SUM(workspace_status<>'SYNCED'),0) pending FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d", $event_id ), ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) ) throw new RuntimeException( 'Versione delle iscrizioni non disponibile.' );
		$revision = $wpdb->get_var( $wpdb->prepare( "SELECT revision FROM {$wpdb->prefix}mi_management_state WHERE event_id=%d", $event_id ) );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Versione della gestione non disponibile.' );
		wp_cache_delete( $event_id, 'post_meta' );
		wp_cache_delete( $event_id, 'posts' );
		$event = get_post( $event_id );
		if ( ! $event || MI_Event_Post_Type::EVENT_TYPE !== $event->post_type ) throw new RuntimeException( 'Evento non disponibile.' );
		$meta = get_post_meta( $event_id );
		foreach ( array_keys( $meta ) as $key ) {
			if ( 0 !== strpos( $key, '_mi_' ) || preg_match( '/^_mi_(projection_|sheet_|operational_sheet_)/', $key ) ) unset( $meta[$key] );
		}
		$pending = (int) $rows['pending']; unset( $rows['pending'] );
		$token = hash( 'sha256', MI_Workspace_Client::stable_json( array( $rows, (string) $revision, $event->post_title, $event->post_status, $meta, MI_Field_Schema::workspace_event_schema( $event_id ), MI_Field_Schema::resolved_operational_profile( $event_id ), get_locale() ) ) );
		return array( 'token' => $token, 'pending' => $pending );
	}
	public static function get( $event_id, $token ) {
		$cached = get_transient( 'mi_read_151_' . absint( $event_id ) );
		if ( ! is_array( $cached ) || ( $cached['token'] ?? '' ) !== $token ) return null;
		$json = gzuncompress( base64_decode( $cached['data'], true ), 32000000 );
		$result = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $result ) ? $result : null;
	}
	public static function put( $event_id, $token, array $model ) {
		$json = wp_json_encode( $model );
		if ( ! is_string( $json ) || strlen( $json ) > 32000000 ) return;
		$data = gzcompress( $json, 1 );
		if ( false !== $data && strlen( $data ) < 4000000 ) set_transient( 'mi_read_151_' . absint( $event_id ), array( 'token' => $token, 'data' => base64_encode( $data ) ), 300 );
	}
}
