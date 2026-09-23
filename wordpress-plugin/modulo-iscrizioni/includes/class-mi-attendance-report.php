<?php
defined( 'ABSPATH' ) || exit;

/** Annual attendance: personal mobile numbers identify repeated registrations. */
final class MI_Attendance_Report {
	public static function group_period( $group_id ) {
		return self::period( wp_date( 'Y' ), (string) get_post_meta( $group_id, '_mi_attendance_from_month', true ), (string) get_post_meta( $group_id, '_mi_attendance_to_month', true ) );
	}
	public static function period_fields( $group_id = 0 ) {
		try { list( $from, $to ) = self::group_period( $group_id ); } catch ( InvalidArgumentException $e ) { $from = ''; $to = ''; }
		?><div class="mi-group-form-grid"><label>Presenze dal mese<input name="mi_attendance_from_month" type="month" min="2000-01" max="2200-12" required value="<?php echo esc_attr( $from ); ?>"></label><label>Al mese (incluso)<input name="mi_attendance_to_month" type="month" min="2000-01" max="2200-12" required value="<?php echo esc_attr( $to ); ?>"></label></div><p class="mi-portal-muted">Il periodo vale per il rapporto di tutti gli eventi del gruppo e può comprendere due anni, per esempio settembre–giugno. Salva il gruppo per applicarlo.</p><?php
	}
	public static function render_group( $group_id ) {
		if ( '1' !== get_post_meta( $group_id, '_mi_annual_attendance_report', true ) || ! MI_Portal_Management::allowed() || ! MI_Access::can_access_activity( $group_id ) ) return;
		try { list( $from, $to ) = self::group_period( $group_id ); } catch ( InvalidArgumentException $e ) { return; }
		?><details class="mi-management" data-group-attendance data-group="<?php echo esc_attr( $group_id ); ?>" data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mi_portal_management' ) ); ?>"><summary>Rapporto presenze del gruppo</summary><p>Periodo salvato: <?php echo esc_html( $from . ' — ' . $to ); ?>, estremi inclusi. Conta le presenze effettive negli eventi accessibili del gruppo, riconoscendo la persona dal cellulare personale.</p><label>Numero minimo di eventi frequentati<input data-annual-minimum type="number" min="1" max="1000" value="2" required></label><button type="button" data-load-annual>Genera rapporto</button><p data-annual-status role="status"></p><div data-annual-results></div></details><?php
	}
	private static function mobile( $person ) {
		$fields = json_decode( (string) ( $person['extra_json'] ?? '{}' ), true ) ?: array();
		$value = $fields['participant_phone'] ?? $fields['phone'] ?? $fields['mobile'] ?? '';
		$number = preg_replace( '/[^0-9]/', '', (string) $value );
		if ( str_starts_with( $number, '00' ) ) $number = substr( $number, 2 );
		if ( preg_match( '/^3[0-9]{9}$/', $number ) ) $number = '39' . $number;
		return strlen( $number ) >= 8 && strlen( $number ) <= 15 ? $number : '';
	}
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
	public static function period( $year, $from_month = '', $to_month = '' ) {
		if ( '' === $from_month && '' === $to_month ) { $from_month = $year . '-01'; $to_month = $year . '-12'; }
		foreach ( array( $from_month, $to_month ) as $month ) if ( ! preg_match( '/^(20[0-9]{2}|21[0-9]{2}|2200)-(0[1-9]|1[0-2])$/D', $month ) ) throw new InvalidArgumentException( 'Scegli un mese iniziale e un mese finale validi.' );
		if ( $from_month > $to_month ) throw new InvalidArgumentException( 'Il mese finale deve seguire o coincidere con quello iniziale.' );
		return array( $from_month, $to_month );
	}
	public static function aggregate( $people, $audit, $events, $year, $minimum, $from_month = '', $to_month = '' ) {
		list( $from_month, $to_month ) = self::period( $year, $from_month, $to_month );
		$by_id = array_column( $people, null, 'id' ); $parents = array(); $links = array(); $attendance = array();
		foreach ( $people as $person ) $parents[(int) $person['id']] = (int) $person['id'];
		foreach ( $audit as $row ) {
			$detail = json_decode( (string) $row['detail_json'], true ); if ( ! is_array( $detail ) ) continue;
			$id = (int) ( $detail['participant_id'] ?? 0 );
			if ( ! isset( $by_id[$id] ) || (int) $by_id[$id]['registration_id'] !== (int) $row['registration_id'] ) continue;
			if ( 'management_attendance' === strtolower( $row['event_type'] ) ) $attendance[$id] = $detail['attendance'] ?? 'UNRECORDED';
			if ( 'management_identity_link' === strtolower( $row['event_type'] ) ) $links[$id] = (int) ( $detail['target_id'] ?? 0 );
		}
		// Path halving shortens repeated lookups without changing the chosen identity.
		$root = static function ( $id ) use ( &$parents ) { while ( $parents[$id] !== $id ) { $parents[$id] = $parents[$parents[$id]]; $id = $parents[$id]; } return $id; };
		$mobiles = array(); $by_mobile = array();
		foreach ( $people as $person ) {
			$id = (int) $person['id']; $mobile = self::mobile( $person ); $mobiles[$id] = $mobile;
			if ( ! $mobile ) continue;
			if ( isset( $by_mobile[$mobile] ) ) $parents[$id] = $root( $by_mobile[$mobile] ); else $by_mobile[$mobile] = $id;
		}
		// Retain historical confirmed links only where neither record has a personal mobile.
		foreach ( $links as $id => $target ) if ( $target && isset( $parents[$target] ) && empty( $mobiles[$id] ) && empty( $mobiles[$target] ) ) { $left = $root( $id ); $right = $root( $target ); if ( $left !== $right ) $parents[max( $left, $right )] = min( $left, $right ); }
		$groups = array(); $unrecorded = 0;
		foreach ( $people as $person ) {
			$event = $events[(int) $person['event_id']] ?? null; if ( ! $event || substr( $event['date'], 0, 7 ) < $from_month || substr( $event['date'], 0, 7 ) > $to_month ) continue;
			if ( ! isset( $attendance[$person['id']] ) || 'UNRECORDED' === $attendance[$person['id']] ) $unrecorded++;
			if ( 'PRESENT' !== ( $attendance[$person['id']] ?? '' ) ) continue;
			$key = $root( (int) $person['id'] );
			if ( ! isset( $groups[$key] ) ) $groups[$key] = array( 'identity' => $key, 'names' => array(), 'events' => array(), 'records' => array() );
			$name = trim( $person['last_name'] . ' ' . $person['first_name'] ); $groups[$key]['names'][$name] = $name;
			$groups[$key]['events'][(int) $person['event_id']] = $event['title'];
			$groups[$key]['records'][] = array( 'id' => (int) $person['id'], 'code' => $person['order_code'], 'name' => $name );
		}
		$items = array(); foreach ( $groups as $group ) if ( count( $group['events'] ) >= $minimum ) { $group['count'] = count( $group['events'] ); $group['events'] = array_values( $group['events'] ); $group['names'] = array_values( $group['names'] ); $items[] = $group; }
		usort( $items, static function ( $a, $b ) { return strcasecmp( implode( ', ', $a['names'] ), implode( ', ', $b['names'] ) ); } );
		return array( 'items' => $items, 'unrecorded' => $unrecorded, 'year' => $year, 'from_month' => $from_month, 'to_month' => $to_month, 'minimum' => $minimum );
	}
	public static function read( $group_id, $year, $minimum, $from_month = '', $to_month = '' ) {
		global $wpdb;
		if ( ! MI_Portal_Management::allowed() || ! MI_Access::can_access_activity( $group_id ) || $minimum < 1 || $minimum > 1000 ) return new WP_Error( 'mi_report_scope', 'Gruppo o criteri non accessibili.' );
		try { list( $from_month, $to_month ) = self::period( $year, $from_month, $to_month ); } catch ( InvalidArgumentException $e ) { return new WP_Error( 'mi_report_period', $e->getMessage() ); }
		$posts = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'private', 'draft' ), 'numberposts' => -1, 'meta_key' => '_mi_activity_id', 'meta_value' => $group_id ) );
		$events = array(); foreach ( $posts as $post ) if ( MI_Access::can_access_event( $post->ID ) ) $events[$post->ID] = array( 'title' => $post->post_title, 'date' => (string) get_post_meta( $post->ID, '_mi_event_starts_at', true ) );
		if ( ! $events ) return self::aggregate( array(), array(), array(), $year, $minimum, $from_month, $to_month );
		$ids = implode( ',', array_map( 'intval', array_keys( $events ) ) );
		$people = $wpdb->get_results( "SELECT p.id,p.registration_id,p.first_name,p.last_name,p.extra_json,r.event_id,r.order_code FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id IN ({$ids}) ORDER BY p.id", ARRAY_A );
		if ( $wpdb->last_error ) return new WP_Error( 'mi_report_read', 'Presenze non disponibili.' );
		$audit = $wpdb->get_results( "SELECT a.registration_id,a.event_type,a.detail_json FROM {$wpdb->prefix}mi_registration_events a JOIN {$wpdb->prefix}mi_registrations r ON r.id=a.registration_id WHERE r.event_id IN ({$ids}) AND a.event_type IN ('MANAGEMENT_attendance','MANAGEMENT_identity_link') ORDER BY a.id", ARRAY_A );
		if ( $wpdb->last_error ) return new WP_Error( 'mi_report_read', 'Storico presenze non disponibile.' );
		return self::aggregate( $people, $audit, $events, $year, $minimum, $from_month, $to_month );
	}
}
