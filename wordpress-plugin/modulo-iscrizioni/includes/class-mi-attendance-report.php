<?php
defined( 'ABSPATH' ) || exit;

/** Annual attendance: only operator-confirmed links identify the same person. */
final class MI_Attendance_Report {
	public static function target( $source_event, $code, $number ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,event_id FROM {$wpdb->prefix}mi_registrations WHERE order_code=%s", $code ), ARRAY_A );
		if ( $wpdb->last_error || ! $row || ! MI_Access::can_access_event( (int) $row['event_id'] ) ) throw new InvalidArgumentException( 'Prenotazione di riferimento non accessibile.' );
		$group = (int) get_post_meta( $source_event, '_mi_activity_id', true );
		if ( ! $group || $group !== (int) get_post_meta( $row['event_id'], '_mi_activity_id', true ) ) throw new InvalidArgumentException( 'Il collegamento deve riguardare lo stesso gruppo.' );
		$people = $wpdb->get_results( $wpdb->prepare( "SELECT id,first_name,last_name FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d ORDER BY id", $row['id'] ), ARRAY_A );
		if ( $wpdb->last_error || $number < 1 || ! isset( $people[$number - 1] ) ) throw new InvalidArgumentException( 'Partecipante di riferimento non trovato.' );
		return $people[$number - 1];
	}
	public static function aggregate( $people, $audit, $events, $year, $minimum ) {
		$by_id = array_column( $people, null, 'id' ); $parents = array(); $links = array(); $attendance = array();
		foreach ( $people as $person ) $parents[(int) $person['id']] = (int) $person['id'];
		foreach ( $audit as $row ) {
			$detail = json_decode( (string) $row['detail_json'], true ); if ( ! is_array( $detail ) ) continue;
			$id = (int) ( $detail['participant_id'] ?? 0 );
			if ( ! isset( $by_id[$id] ) || (int) $by_id[$id]['registration_id'] !== (int) $row['registration_id'] ) continue;
			if ( 'MANAGEMENT_attendance' === $row['event_type'] ) $attendance[$id] = $detail['attendance'] ?? 'UNRECORDED';
			if ( 'MANAGEMENT_identity_link' === $row['event_type'] ) $links[$id] = (int) ( $detail['target_id'] ?? 0 );
		}
		$root = static function ( $id ) use ( &$parents ) { while ( $parents[$id] !== $id ) $id = $parents[$id]; return $id; };
		foreach ( $links as $id => $target ) if ( $target && isset( $parents[$target] ) ) { $left = $root( $id ); $right = $root( $target ); if ( $left !== $right ) $parents[max( $left, $right )] = min( $left, $right ); }
		$groups = array(); $unrecorded = 0;
		foreach ( $people as $person ) {
			$event = $events[(int) $person['event_id']] ?? null; if ( ! $event || (int) substr( $event['date'], 0, 4 ) !== $year ) continue;
			if ( ! isset( $attendance[$person['id']] ) || 'UNRECORDED' === $attendance[$person['id']] ) $unrecorded++;
			if ( 'PRESENT' !== ( $attendance[$person['id']] ?? '' ) ) continue;
			$key = $root( (int) $person['id'] );
			if ( ! isset( $groups[$key] ) ) $groups[$key] = array( 'identity' => $key, 'names' => array(), 'events' => array(), 'records' => array() );
			$name = trim( $person['first_name'] . ' ' . $person['last_name'] ); $groups[$key]['names'][$name] = $name;
			$groups[$key]['events'][(int) $person['event_id']] = $event['title'];
			$groups[$key]['records'][] = array( 'id' => (int) $person['id'], 'code' => $person['order_code'], 'name' => $name );
		}
		$items = array(); foreach ( $groups as $group ) if ( count( $group['events'] ) >= $minimum ) { $group['count'] = count( $group['events'] ); $group['events'] = array_values( $group['events'] ); $group['names'] = array_values( $group['names'] ); $items[] = $group; }
		usort( $items, static function ( $a, $b ) { return strcasecmp( implode( ', ', $a['names'] ), implode( ', ', $b['names'] ) ); } );
		return array( 'items' => $items, 'unrecorded' => $unrecorded, 'year' => $year, 'minimum' => $minimum );
	}
	public static function read( $group_id, $year, $minimum ) {
		global $wpdb;
		if ( ! MI_Portal_Management::allowed() || ! MI_Access::can_access_activity( $group_id ) || $year < 2000 || $year > 2200 || $minimum < 1 || $minimum > 1000 ) return new WP_Error( 'mi_report_scope', 'Gruppo o criteri non accessibili.' );
		$posts = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'private', 'draft' ), 'numberposts' => -1, 'meta_key' => '_mi_activity_id', 'meta_value' => $group_id ) );
		$events = array(); foreach ( $posts as $post ) if ( MI_Access::can_access_event( $post->ID ) ) $events[$post->ID] = array( 'title' => $post->post_title, 'date' => (string) get_post_meta( $post->ID, '_mi_event_starts_at', true ) );
		if ( ! $events ) return self::aggregate( array(), array(), array(), $year, $minimum );
		$ids = implode( ',', array_map( 'intval', array_keys( $events ) ) );
		$people = $wpdb->get_results( "SELECT p.id,p.registration_id,p.first_name,p.last_name,r.event_id,r.order_code FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id IN ({$ids}) ORDER BY p.id", ARRAY_A );
		if ( $wpdb->last_error ) return new WP_Error( 'mi_report_read', 'Presenze non disponibili.' );
		$audit = $wpdb->get_results( "SELECT a.registration_id,a.event_type,a.detail_json FROM {$wpdb->prefix}mi_registration_events a JOIN {$wpdb->prefix}mi_registrations r ON r.id=a.registration_id WHERE r.event_id IN ({$ids}) AND a.event_type IN ('MANAGEMENT_attendance','MANAGEMENT_identity_link') ORDER BY a.id", ARRAY_A );
		if ( $wpdb->last_error ) return new WP_Error( 'mi_report_read', 'Storico presenze non disponibile.' );
		return self::aggregate( $people, $audit, $events, $year, $minimum );
	}
}
