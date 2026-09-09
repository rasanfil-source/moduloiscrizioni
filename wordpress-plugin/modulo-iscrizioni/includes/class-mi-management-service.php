<?php
defined( 'ABSPATH' ) || exit;

/** Operational records in MySQL. Google receives a projection of these records. */
final class MI_Management_Service {
	private static function decode( $value ) {
		$result = json_decode( (string) $value, true );
		return is_array( $result ) ? $result : array();
	}
	private static function check_database() {
		global $wpdb;
		if ( $wpdb->last_error ) throw new RuntimeException( 'Lettura del registro non disponibile.' );
	}
	private static function registration( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $id ), ARRAY_A );
		self::check_database();
		if ( ! $row || ! MI_Portal_Management::allowed() || ! MI_Access::can_access_event( (int) $row['event_id'] ) ) throw new InvalidArgumentException( 'Prenotazione non accessibile.' );
		return $row;
	}
	private static function definitions( $registration ) {
		$snapshot = self::decode( $registration['snapshot_json'] );
		$fields = array();
		foreach ( (array) ( $snapshot['event']['participant_fields'] ?? array() ) as $field ) {
			$key = $field['key'] ?? '';
			if ( ! preg_match( '/^[a-z][a-z0-9_]{0,79}$/', $key ) || in_array( $key, array( 'constructor','prototype','room','camera','alloggio','first_name','last_name' ), true ) ) continue;
			$fields[$key] = array( 'key' => $key, 'label' => $field['label'] ?? $key, 'type' => $field['type'] ?? 'text', 'required' => ! empty( $field['required'] ), 'options' => (array) ( $field['options'] ?? array() ) );
		}
		if ( ! isset( $fields['pullman'] ) ) $fields['pullman'] = array( 'key' => 'pullman', 'label' => 'Assegnazione pullman (sigla)', 'type' => 'text', 'required' => false );
		return $fields;
	}
	private static function rooms( $event_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT code,name,capacity FROM {$wpdb->prefix}mi_rooms WHERE event_id=%d ORDER BY code", $event_id ), ARRAY_A );
		self::check_database();
		$counts = $wpdb->get_results( $wpdb->prepare( "SELECT p.room_code,COUNT(*) AS occupied FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id=%d AND r.status NOT IN ('CANCELLED','EXPIRED') AND p.status='ACTIVE' AND p.room_code<>'' GROUP BY p.room_code", $event_id ), ARRAY_A );
		self::check_database();
		$occupied = array_column( $counts, 'occupied', 'room_code' );
		foreach ( $rows as &$row ) {
			$row['capacity'] = (int) $row['capacity'];
			$row['occupied'] = (int) ( $occupied[$row['code']] ?? 0 );
			$row['available'] = max( 0, $row['capacity'] - $row['occupied'] );
		}
		return $rows;
	}
	private static function booking( $registration ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,first_name,last_name,extra_json,room_code,status FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d ORDER BY id", $registration['id'] ), ARRAY_A );
		self::check_database();
		$participants = array();
		foreach ( $rows as $i => $row ) {
			$fields = self::decode( $row['extra_json'] );
			foreach ( array( 'room','camera','alloggio','first_name','last_name' ) as $key ) unset( $fields[$key] );
			$participants[] = array( 'id' => (int) $row['id'], 'number' => $i + 1, 'first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'status' => $row['status'], 'fields' => $fields, 'room' => $row['room_code'] );
		}
		$rooms = self::rooms( (int) $registration['event_id'] );
		return array( 'ok' => true, 'registration_id' => (int) $registration['id'], 'event_id' => (int) $registration['event_id'], 'order_code' => $registration['order_code'], 'status' => $registration['status'], 'buyer' => array( 'first_name' => $registration['buyer_first_name'], 'last_name' => $registration['buyer_last_name'] ), 'participants' => $participants, 'accommodations' => $rooms, 'fields' => array_values( self::definitions( $registration ) ), 'version' => hash( 'sha256', wp_json_encode( array( $registration['status'], $participants, $rooms ) ) ) );
	}
	public static function detail( $id ) {
		try {
			$booking = self::booking( self::registration( $id ) );
			$economic = MI_Payment_Ledger::detail( $id );
			if ( is_wp_error( $economic ) ) return $economic;
			$booking['total_cents'] = $economic['saldo']['totale'];
			$booking['paid_cents'] = $economic['saldo']['versato'];
			$booking['balance_cents'] = $economic['saldo']['residuo'];
			$booking['movements'] = $economic['saldo']['movimenti'];
			$booking['event_title'] = get_the_title( $booking['event_id'] );
			$booking['payment_url'] = MI_Portal_Payments::allowed() ? add_query_arg( array( 'mi_portal_view' => 'payments', 'mi_order' => $booking['order_code'] ), MI_Portal::url() ) : '';
			return $booking;
		} catch ( Throwable $error ) { return new WP_Error( 'mi_management_read', $error->getMessage() ); }
	}
	public static function summary( $event_id ) {
		global $wpdb;
		if ( ! MI_Portal_Management::allowed() || ! MI_Access::can_access_event( $event_id ) ) return new WP_Error( 'mi_management_scope', 'Evento non accessibile.' );
		try {
			$orders = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d ORDER BY id", $event_id ), ARRAY_A );
			self::check_database();
			$people = $wpdb->get_results( $wpdb->prepare( "SELECT p.registration_id,p.extra_json,p.room_code FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id=%d AND p.status='ACTIVE' ORDER BY p.id", $event_id ), ARRAY_A );
			self::check_database();
			$payments = $wpdb->get_results( $wpdb->prepare( "SELECT p.registration_id,SUM(CASE WHEN p.transaction_kind='REFUND' THEN -p.amount_cents ELSE p.amount_cents END) AS paid FROM {$wpdb->prefix}mi_payments p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id=%d GROUP BY p.registration_id", $event_id ), ARRAY_A );
			self::check_database();
			$paid = array_column( $payments, 'paid', 'registration_id' );
			$grouped = array();
			foreach ( $people as $person ) $grouped[$person['registration_id']][] = $person;
			$has_rooms = count( self::rooms( $event_id ) ) > 0;
			$items = array();
			foreach ( $orders as $order ) {
				$participants = $grouped[$order['id']] ?? array(); $missing = 0; $unassigned = 0;
				$definitions = self::definitions( $order ); $snapshot = self::decode( $order['snapshot_json'] );
				foreach ( $participants as $i => $person ) {
					$fields = self::decode( $person['extra_json'] );
					if ( 0 === $i || 'ALL' === ( $snapshot['event']['participant_extra_scope'] ?? '' ) ) foreach ( $definitions as $f ) if ( $f['required'] && '' === trim( (string) ( $fields[$f['key']] ?? '' ) ) ) { $missing++; break; }
					if ( $has_rooms && ! $person['room_code'] ) $unassigned++;
				}
				$sum = (int) ( $paid[$order['id']] ?? 0 );
				$items[] = array( 'code' => $order['order_code'], 'name' => trim( $order['buyer_first_name'] . ' ' . $order['buyer_last_name'] ), 'status' => $order['status'], 'active' => ! in_array( $order['status'], array( 'CANCELLED','EXPIRED' ), true ), 'participants' => count( $participants ), 'total' => (int) $order['total_cents'], 'paid' => $sum, 'balance' => max( 0, (int) $order['total_cents'] - $sum ), 'missing' => $missing, 'unassigned' => $unassigned );
			}
			return array( 'ok' => true, 'items' => $items, 'updated_at' => gmdate( 'c' ), 'registration_url' => MI_Shortcode::url_iscrizione( $event_id ) );
		} catch ( Throwable $error ) { return new WP_Error( 'mi_management_read', $error->getMessage() ); }
	}
	public static function save( $id, $operation, $data, $version, $request_id ) {
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( MI_Event_Deletion::registration_event( $id ) ); if ( is_wp_error( $lease ) ) return $lease; }
		global $wpdb;
		if ( ! MI_Portal_Management::allowed() || ! preg_match( '/^wp_' . get_current_user_id() . '_[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $request_id ) || ! is_array( $data ) || ! in_array( $operation, array( 'participant','room_save','room_delete' ), true ) ) return new WP_Error( 'mi_management_request', 'Richiesta non valida.' );
		try { $registration = self::registration( $id ); } catch ( Throwable $error ) { return new WP_Error( 'mi_management_scope', $error->getMessage() ); }
		$event_id = (int) $registration['event_id'];
		$hash = hash( 'sha256', wp_json_encode( array( $id, $operation, $data, $version ) ) );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) return new WP_Error( 'mi_management_busy', 'Registro non disponibile.' );
		try {
			// Serialize room allocations for this event; other events remain independent.
			if ( false === $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}mi_management_state (event_id) VALUES (%d) ON DUPLICATE KEY UPDATE event_id=VALUES(event_id)", $event_id ) ) ) throw new RuntimeException( 'Evento non disponibile.' );
			$locked = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE id=%d FOR UPDATE", $id ), ARRAY_A );
			self::check_database();
			if ( ! $locked || (int) $locked['event_id'] !== $event_id ) throw new InvalidArgumentException( 'Prenotazione non disponibile.' );
			$previous = $wpdb->get_row( $wpdb->prepare( "SELECT request_hash FROM {$wpdb->prefix}mi_management_requests WHERE request_id=%s", $request_id ), ARRAY_A );
			self::check_database();
			if ( $previous ) {
				if ( ! hash_equals( $previous['request_hash'], $hash ) ) throw new InvalidArgumentException( 'Identificativo già utilizzato con dati diversi.' );
				if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma non disponibile.' );
				return array( 'ok' => true, 'saved' => true, 'replayed' => true, 'message' => 'Modifica già registrata.' );
			}
			$booking = self::booking( $locked );
			if ( ! hash_equals( $booking['version'], (string) $version ) ) throw new InvalidArgumentException( 'I dati sono cambiati: ricarica la scheda prima di salvare.' );
			if ( 'participant' === $operation ) self::save_participant( $booking, $data );
			else self::save_room( $booking, $operation, $data );
			if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_management_state SET revision=revision+1 WHERE event_id=%d", $event_id ) ) ) throw new RuntimeException( 'Versione evento non aggiornata.' );
			if ( 'participant' === $operation ) MI_Registration_Service::mark_workspace_changed_locked( $id );
			else if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_registrations SET workspace_revision=workspace_revision+1,workspace_status='PENDING',workspace_attempts=0 WHERE event_id=%d", $event_id ) ) ) throw new RuntimeException( 'Allineamento non accodato.' );
			if ( false === $wpdb->insert( $wpdb->prefix . 'mi_management_requests', array( 'request_id' => $request_id, 'event_id' => $event_id, 'registration_id' => $id, 'request_hash' => $hash, 'actor_id' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) ) ) throw new RuntimeException( 'Richiesta non registrata.' );
			if ( ! MI_Registration_Service::append_registration_event( $id, 'MANAGEMENT_' . $operation, '', '', 'WP#' . get_current_user_id(), array( 'request_id' => $request_id ) ) ) throw new RuntimeException( 'Operazione non registrata.' );
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma del salvataggio non disponibile.' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			if ( $error instanceof InvalidArgumentException ) return array( 'ok' => true, 'saved' => false, 'rejected' => true, 'message' => $error->getMessage() );
			return new WP_Error( 'mi_management_save', 'Salvataggio non confermato. Riprova la stessa richiesta.' );
		}
		try { MI_Registration_Service::accoda_iscrizione_workspace( $id ); } catch ( Throwable $error ) { /* Persistent queue retains the committed change. */ }
		return array( 'ok' => true, 'saved' => true, 'message' => 'Modifica salvata. Il foglio Google verrà allineato.' );
	}
	/** Explicit Sheets commit. All affected people, including room swaps, commit together. */
	public static function save_sheet( $event_id, $changes, $request_id ) {
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( $event_id ); if ( is_wp_error( $lease ) ) return $lease; }
		global $wpdb;
		if ( ! MI_Portal_Management::allowed() || ! MI_Access::can_access_event( $event_id ) || ! preg_match( '/^wp_' . get_current_user_id() . '_[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $request_id ) || ! is_array( $changes ) || count( $changes ) > 500 ) return new WP_Error( 'mi_sheet_request', 'Richiesta non valida (massimo 500 celle per sincronizzazione).' );
		if ( ! $changes ) return array( 'ok' => true, 'saved' => true, 'message' => 'Nessuna modifica da sincronizzare.' );
		$grouped = array();
		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) || ! is_string( $change['order_code'] ?? null ) || ! is_int( $change['number'] ?? null ) || $change['number'] < 1 || ! is_string( $change['key'] ?? null ) || ! preg_match( '/^[a-z][a-z0-9_]{0,79}$/', $change['key'] ) || ! is_string( $change['before'] ?? null ) || ! is_string( $change['after'] ?? null ) || mb_strlen( $change['after'] ) > 1000 ) return new WP_Error( 'mi_sheet_data', 'Una delle celle contiene dati non validi.' );
			$grouped[$change['order_code']][] = $change;
		}
		ksort( $grouped );
		$hash = hash( 'sha256', wp_json_encode( array( $event_id, $changes ) ) );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) return new WP_Error( 'mi_sheet_busy', 'Registro non disponibile.' );
		$ids = array();
		try {
			if ( false === $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}mi_management_state (event_id) VALUES (%d) ON DUPLICATE KEY UPDATE event_id=VALUES(event_id)", $event_id ) ) ) throw new RuntimeException( 'Evento non disponibile.' );
			$previous = $wpdb->get_row( $wpdb->prepare( "SELECT request_hash FROM {$wpdb->prefix}mi_management_requests WHERE request_id=%s", $request_id ), ARRAY_A );
			self::check_database();
			if ( $previous ) {
				if ( ! hash_equals( $previous['request_hash'], $hash ) ) throw new InvalidArgumentException( 'Identificativo già utilizzato con dati diversi.' );
				if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma non disponibile.' );
				return array( 'ok' => true, 'saved' => true, 'replayed' => true, 'message' => 'Modifiche già sincronizzate.' );
			}
			foreach ( $grouped as $code => $patches ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d AND order_code=%s FOR UPDATE", $event_id, $code ), ARRAY_A );
				self::check_database();
				if ( ! $row ) throw new InvalidArgumentException( 'Prenotazione non disponibile: ' . $code );
				$booking = self::booking( $row ); $people = array_column( $booking['participants'], null, 'number' ); $updates = array(); $seen = array();
				foreach ( $patches as $patch ) {
					$p = $people[$patch['number']] ?? null;
					if ( ! $p || 'ACTIVE' !== $p['status'] ) throw new InvalidArgumentException( 'Partecipante non disponibile: ' . $code );
					$key = self::sheet_field_key( $patch['key'], $booking, $p );
					$identity = $p['number'] . ':' . $key;
					if ( isset( $seen[$identity] ) ) throw new InvalidArgumentException( 'La stessa cella compare più volte.' );
					$seen[$identity] = true;
					$current = in_array( $key, array( 'first_name','last_name','room' ), true ) ? $p[$key] : ( $p['fields'][$key] ?? '' );
					if ( (string) $current !== $patch['before'] && (string) $current !== $patch['after'] ) throw new InvalidArgumentException( 'Conflitto in ' . $code . ', partecipante ' . $p['number'] . ', campo ' . $patch['key'] . '. Nessuna modifica applicata.' );
					if ( ! isset( $updates[$p['number']] ) ) $updates[$p['number']] = array( 'number' => $p['number'], 'first_name' => $p['first_name'], 'last_name' => $p['last_name'], 'room' => $p['room'], 'fields' => array() );
					if ( in_array( $key, array( 'first_name','last_name','room' ), true ) ) $updates[$p['number']][$key] = $patch['after'];
					else $updates[$p['number']]['fields'][$key] = $patch['after'];
				}
				foreach ( $updates as $update ) self::save_participant( $booking, $update, true );
				$ids[] = (int) $row['id'];
				MI_Registration_Service::mark_workspace_changed_locked( (int) $row['id'] );
				if ( ! MI_Registration_Service::append_registration_event( (int) $row['id'], 'SHEET_SYNC', '', '', 'WP#' . get_current_user_id(), array( 'request_id' => $request_id, 'cells' => count( $patches ) ) ) ) throw new RuntimeException( 'Registro operazioni non aggiornato.' );
			}
			foreach ( self::rooms( $event_id ) as $room ) if ( $room['occupied'] > $room['capacity'] ) throw new InvalidArgumentException( 'Capienza superata per la camera ' . $room['code'] . '. Nessuna modifica applicata.' );
			if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_management_state SET revision=revision+1 WHERE event_id=%d", $event_id ) ) ) throw new RuntimeException( 'Versione evento non aggiornata.' );
			if ( false === $wpdb->insert( $wpdb->prefix . 'mi_management_requests', array( 'request_id' => $request_id, 'event_id' => $event_id, 'registration_id' => 0, 'request_hash' => $hash, 'actor_id' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) ) ) throw new RuntimeException( 'Richiesta non registrata.' );
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma non disponibile.' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			if ( $error instanceof InvalidArgumentException ) return array( 'ok' => true, 'saved' => false, 'rejected' => true, 'message' => $error->getMessage() );
			return new WP_Error( 'mi_sheet_save', 'Sincronizzazione non confermata. Riprova la stessa richiesta.' );
		}
		foreach ( $ids as $id ) try { MI_Registration_Service::accoda_iscrizione_workspace( $id ); } catch ( Throwable $error ) { /* Persistent queue retains the committed change. */ }
		return array( 'ok' => true, 'saved' => true, 'message' => count( $changes ) . ' celle sincronizzate.' );
	}
	private static function sheet_field_key( $key, $booking, $person ) {
		if ( in_array( $key, array( 'first_name','last_name','room' ), true ) ) return $key;
		if ( in_array( $key, array( 'event','order_code','participant_number','status','options','total','paid','paid_cash','paid_transfer','paid_card','balance','special_requests','constructor','prototype' ), true ) ) throw new InvalidArgumentException( 'Colonna non modificabile: ' . $key );
		$aliases = array( 'email' => array( 'participant_email','email' ), 'phone' => array( 'participant_phone','phone','mobile' ), 'document_expiry_date' => array( 'document_expiry_date','document_expiry' ), 'transport' => array( 'pullman','transport' ) );
		$known = array_column( $booking['fields'], null, 'key' );
		foreach ( $aliases[$key] ?? array( $key ) as $candidate ) if ( isset( $known[$candidate] ) || array_key_exists( $candidate, $person['fields'] ) ) return $candidate;
		throw new InvalidArgumentException( 'Campo non previsto nell’iscrizione: ' . $key );
	}
	private static function save_participant( $booking, $data, $defer_capacity = false ) {
		global $wpdb;
		if ( in_array( $booking['status'], array( 'CANCELLED','EXPIRED' ), true ) ) throw new InvalidArgumentException( 'Prenotazione non modificabile.' );
		$person = null;
		foreach ( $booking['participants'] as $p ) if ( $p['number'] === ( $data['number'] ?? null ) ) $person = $p;
		if ( ! $person || 'ACTIVE' !== $person['status'] ) throw new InvalidArgumentException( 'Partecipante non disponibile.' );
		$names = array();
		foreach ( array( 'first_name','last_name' ) as $key ) {
			$value = $data[$key] ?? null;
			if ( ! is_string( $value ) || ! trim( $value ) || mb_strlen( $value ) > 80 ) throw new InvalidArgumentException( 'Nome e cognome sono obbligatori (massimo 80 caratteri).' );
			$names[$key] = sanitize_text_field( $value );
			if ( ! $names[$key] ) throw new InvalidArgumentException( 'Nome e cognome sono obbligatori.' );
		}
		$allowed = array_column( $booking['fields'], null, 'key' );
		foreach ( $person['fields'] as $key => $value ) if ( ! isset( $allowed[$key] ) ) $allowed[$key] = array( 'type' => 'text' );
		$fields = $person['fields'];
		if ( ! is_array( $data['fields'] ?? null ) ) throw new InvalidArgumentException( 'Campi non validi.' );
		foreach ( $data['fields'] as $key => $value ) {
			if ( ! isset( $allowed[$key] ) || ! is_string( $value ) || mb_strlen( $value ) > 1000 ) throw new InvalidArgumentException( 'Campo non modificabile o troppo lungo.' );
			$value = sanitize_textarea_field( $value ); $type = $allowed[$key]['type'] ?? 'text';
			if ( $value && 'email' === $type && ! is_email( $value ) ) throw new InvalidArgumentException( 'Email non valida.' );
			if ( $value && 'date' === $type ) {
				$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
				if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) throw new InvalidArgumentException( 'Data non valida.' );
			}
			if ( $value && in_array( $type, array( 'select','yesno' ), true ) && ! in_array( $value, $allowed[$key]['options'] ?? array(), true ) && $value !== ( $fields[$key] ?? '' ) ) throw new InvalidArgumentException( 'Scegli uno dei valori disponibili.' );
			$fields[$key] = $value;
		}
		$room = $data['room'] ?? $person['room'];
		if ( ! is_string( $room ) ) throw new InvalidArgumentException( 'Camera non valida.' );
		if ( '' !== $room ) {
			$rooms = array_column( $booking['accommodations'], null, 'code' );
			if ( ! isset( $rooms[$room] ) || ( ! $defer_capacity && $rooms[$room]['available'] < 1 && $person['room'] !== $room ) ) throw new InvalidArgumentException( 'Camera non disponibile o al completo.' );
		}
		if ( false === $wpdb->update( $wpdb->prefix . 'mi_participants', $names + array( 'extra_json' => wp_json_encode( $fields ), 'room_code' => $room ), array( 'id' => $person['id'] ) ) ) throw new RuntimeException( 'Partecipante non salvato.' );
	}
	private static function save_room( $booking, $operation, $data ) {
		global $wpdb;
		$code = $data['code'] ?? '';
		if ( ! is_string( $code ) || ! preg_match( '/^[A-Za-z0-9_-]{1,80}$/', $code ) ) throw new InvalidArgumentException( 'Usa un codice camera con lettere, numeri o trattini.' );
		$rooms = array_column( $booking['accommodations'], null, 'code' );
		$occupied = $rooms[$code]['occupied'] ?? 0;
		if ( 'room_delete' === $operation ) {
			if ( $occupied ) throw new InvalidArgumentException( 'Riassegna gli occupanti prima di eliminare la camera.' );
			$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}mi_rooms WHERE event_id=%d AND code=%s", $booking['event_id'], $code ) );
		} else {
			$capacity = $data['capacity'] ?? null; $name = $data['name'] ?? null;
			if ( ! is_int( $capacity ) || $capacity < max( 1, $occupied ) || $capacity > 1000 || ! is_string( $name ) || ! trim( $name ) || mb_strlen( $name ) > 120 ) throw new InvalidArgumentException( 'Nome o capienza non validi.' );
			$name = sanitize_text_field( $name );
			if ( ! $name ) throw new InvalidArgumentException( 'Nome camera obbligatorio.' );
			$result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}mi_rooms (event_id,code,name,capacity) VALUES (%d,%s,%s,%d) ON DUPLICATE KEY UPDATE name=VALUES(name),capacity=VALUES(capacity)", $booking['event_id'], $code, $name, $capacity ) );
		}
		if ( false === $result ) throw new RuntimeException( 'Camera non salvata.' );
	}
}
