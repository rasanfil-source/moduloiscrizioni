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
	private static function visible_special_requests( $value ) {
		$value = trim( (string) $value );
		return 'Iscrizione dimostrativa generata dal pannello amministrativo.' === $value ? '' : $value;
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
		if ( '1' === get_post_meta( $registration['event_id'], '_mi_bus_assignment_enabled', true ) ) {
			if ( ! isset( $fields['pullman'] ) ) $fields['pullman'] = array( 'key' => 'pullman', 'label' => 'Assegnato al Pullmann…', 'type' => 'text', 'required' => false );
		} else unset( $fields['pullman'] );
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
		$attendance_rows = $wpdb->get_results( $wpdb->prepare( "SELECT detail_json,actor_label,created_at FROM {$wpdb->prefix}mi_registration_events WHERE registration_id=%d AND event_type='MANAGEMENT_attendance' ORDER BY id", $registration['id'] ), ARRAY_A );
		self::check_database(); $attendance = self::attendance_map( $attendance_rows );
		$identity_rows = $wpdb->get_results( $wpdb->prepare( "SELECT detail_json FROM {$wpdb->prefix}mi_registration_events WHERE registration_id=%d AND event_type='MANAGEMENT_identity_link' ORDER BY id", $registration['id'] ), ARRAY_A ); self::check_database();
		$identities = array(); foreach ( $identity_rows as $identity_row ) { $link = self::decode( $identity_row['detail_json'] ); $identities[(int) ( $link['participant_id'] ?? 0 )] = (int) ( $link['target_id'] ?? 0 ); }
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,first_name,last_name,extra_json,options_json,ticket_type_code,room_code,status FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d ORDER BY id", $registration['id'] ), ARRAY_A );
		self::check_database();
		$participants = array();
		foreach ( $rows as $i => $row ) {
			$fields = self::decode( $row['extra_json'] );
			foreach ( array( 'room','camera','alloggio','first_name','last_name' ) as $key ) unset( $fields[$key] );
			$participants[] = array( 'id' => (int) $row['id'], 'number' => $i + 1, 'first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'status' => $row['status'], 'fields' => $fields, 'room' => $row['room_code'], 'options' => self::decode( $row['options_json'] ?? '' ), 'ticket_type' => $row['ticket_type_code'] ?? '' );
			$participants[count( $participants ) - 1]['attendance'] = $attendance[$row['id']] ?? array( 'state' => 'UNRECORDED', 'actor' => '', 'at' => '' );
			$participants[count( $participants ) - 1]['identity_target'] = $identities[$row['id']] ?? 0;
		}
		$rooms = self::rooms( (int) $registration['event_id'] );
		$review = self::request_review( $registration );
		return array( 'ok' => true, 'registration_id' => (int) $registration['id'], 'event_id' => (int) $registration['event_id'], 'order_code' => $registration['order_code'], 'status' => $registration['status'], 'buyer' => array( 'first_name' => $registration['buyer_first_name'], 'last_name' => $registration['buyer_last_name'], 'email' => $registration['buyer_email'] ?? '', 'phone' => $registration['buyer_phone'] ?? '' ), 'special_requests' => self::visible_special_requests( $registration['special_requests'] ?? '' ), 'order_options' => self::decode( $registration['order_options_json'] ?? '' ), 'workspace_status' => $registration['workspace_status'] ?? '', 'workspace_synced_at' => $registration['workspace_synced_at'] ?? '', 'offer_expires_at' => $registration['waitlist_offer_expires_at'] ?? '', 'participants' => $participants, 'accommodations' => $rooms, 'features' => array( 'rooms' => '1' === get_post_meta( (int) $registration['event_id'], '_mi_overnight', true ) ), 'request_review' => $review, 'fields' => array_values( self::definitions( $registration ) ), 'version' => hash( 'sha256', wp_json_encode( array( $registration['status'], $participants, $rooms, $review, $registration['special_requests'] ?? '', $registration['total_cents'] ?? 0, $registration['initial_due_cents'] ?? 0, $registration['order_options_json'] ?? '' ) ) ) );
	}
	private static function attendance_map( $rows ) {
		$map = array();
		foreach ( $rows as $row ) { $detail = self::decode( $row['detail_json'] ); if ( isset( $detail['participant_id'], $detail['attendance'] ) ) $map[(int) $detail['participant_id']] = array( 'state' => $detail['attendance'], 'actor' => $row['actor_label'], 'at' => $row['created_at'] ); }
		return $map;
	}
	private static function request_review( $registration, $row = null ) {
		global $wpdb;
		if ( null === $row ) $row = $wpdb->get_row( $wpdb->prepare( "SELECT id,detail_json,actor_label,created_at FROM {$wpdb->prefix}mi_registration_events WHERE registration_id=%d AND event_type='MANAGEMENT_request_review' ORDER BY id DESC LIMIT 1", $registration['id'] ), ARRAY_A );
		self::check_database();
		$detail = self::decode( $row['detail_json'] ?? '' );
		return array( 'id' => (int) ( $row['id'] ?? 0 ), 'reviewed' => ! empty( $detail['reviewed'] ) && hash_equals( (string) ( $detail['text_hash'] ?? '' ), hash( 'sha256', (string) ( $registration['special_requests'] ?? '' ) ) ), 'actor' => $row['actor_label'] ?? '', 'at' => $row['created_at'] ?? '' );
	}
	public static function detail( $id ) {
		try {
			$booking = self::booking( self::registration( $id ) );
			$booking['bus_assignment_enabled'] = '1' === get_post_meta( $booking['event_id'], '_mi_bus_assignment_enabled', true );
			$booking['room_types'] = self::room_types();
			$economic = MI_Payment_Ledger::detail( $id );
			if ( is_wp_error( $economic ) ) return $economic;
			$booking['total_cents'] = $economic['saldo']['totale'];
			$booking['paid_cents'] = $economic['saldo']['versato'];
			$booking['balance_cents'] = $economic['saldo']['residuo'];
			$booking['movements'] = $economic['saldo']['movimenti'];
			foreach ( array( 'deposit_plan', 'deposit_due', 'deposit_missing', 'deposit_covered' ) as $key ) $booking[$key] = $economic['saldo'][$key];
            global $wpdb;
            $booking['adjustments'] = $wpdb->get_results( $wpdb->prepare( "SELECT detail_json,actor_label,created_at FROM {$wpdb->prefix}mi_registration_events WHERE registration_id=%d AND event_type='MANAGEMENT_adjust_due' ORDER BY id DESC", $id ), ARRAY_A );
            self::check_database();
            foreach ( $booking['adjustments'] as &$adjustment_row ) $adjustment_row['change'] = self::decode( $adjustment_row['detail_json'] );
			$booking['can_adjust_due'] = MI_Portal_Payments::allowed() && in_array( self::registration( $id )['economic_mode'], array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true );
			$saved_registration = self::registration( $id ); $saved_snapshot = self::decode( $saved_registration['snapshot_json'] );
			$booking['is_free_event'] = 'ZERO' === strtoupper( (string) ( $saved_snapshot['event']['pricing_mode'] ?? get_post_meta( $booking['event_id'], '_mi_pricing_mode', true ) ) );
			if ( $booking['is_free_event'] ) $booking['can_adjust_due'] = false;
            $booking['option_definitions'] = $saved_snapshot['event']['options'] ?? array();
			$booking['option_scope'] = $saved_snapshot['event']['participant_extra_scope'] ?? 'ONE';
			$group_id = absint( get_post_meta( $booking['event_id'], '_mi_activity_id', true ) );
			$booking['attendance_enabled'] = $group_id && '1' === get_post_meta( $group_id, '_mi_annual_attendance_report', true );
            $booking['option_changes'] = $wpdb->get_results( $wpdb->prepare( "SELECT detail_json,actor_label,created_at FROM {$wpdb->prefix}mi_registration_events WHERE registration_id=%d AND event_type='MANAGEMENT_change_options' ORDER BY id DESC", $id ), ARRAY_A ); self::check_database();
            foreach ( $booking['option_changes'] as &$option_row ) $option_row['change'] = self::decode( $option_row['detail_json'] );
			$booking['accommodation_changes'] = $wpdb->get_results( $wpdb->prepare( "SELECT detail_json,actor_label,created_at FROM {$wpdb->prefix}mi_registration_events WHERE registration_id=%d AND event_type='CHANGE_ACCOMMODATION' ORDER BY id DESC", $id ), ARRAY_A ); self::check_database();
			foreach ( $booking['accommodation_changes'] as &$change_row ) $change_row['change'] = self::decode( $change_row['detail_json'] );
            $booking['event_title'] = get_the_title( $booking['event_id'] );
			$booking['payment_url'] = ! $booking['is_free_event'] && MI_Portal_Payments::allowed() ? add_query_arg( array( 'mi_portal_view' => 'payments', 'mi_order' => $booking['order_code'] ), MI_Portal::url() ) : '';
			if ( ! $booking['is_free_event'] && MI_Portal_Payments::allowed() ) { ob_start(); MI_Portal_Payments::render(); $booking['payment_html'] = ob_get_clean(); }
			return $booking;
		} catch ( Throwable $error ) { return new WP_Error( 'mi_management_read', $error->getMessage() ); }
	}
	public static function summary( $event_id ) {
		global $wpdb;
		if ( ! MI_Portal_Management::allowed() || ! MI_Access::can_access_event( $event_id ) ) return new WP_Error( 'mi_management_scope', 'Evento non accessibile.' );
		try {
			$orders = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d ORDER BY id", $event_id ), ARRAY_A );
			self::check_database();
			$people = $wpdb->get_results( $wpdb->prepare( "SELECT p.id,p.registration_id,p.first_name,p.last_name,p.extra_json,p.options_json,p.room_code,p.status FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id=%d ORDER BY p.id", $event_id ), ARRAY_A );
			self::check_database();
			$payments = $wpdb->get_results( $wpdb->prepare( "SELECT p.registration_id,SUM(CASE WHEN p.transaction_kind='REFUND' THEN -p.amount_cents ELSE p.amount_cents END) AS paid FROM {$wpdb->prefix}mi_payments p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id=%d GROUP BY p.registration_id", $event_id ), ARRAY_A );
			self::check_database();
			$paid = array_column( $payments, 'paid', 'registration_id' );
			$review_rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.* FROM {$wpdb->prefix}mi_registration_events a JOIN {$wpdb->prefix}mi_registrations r ON r.id=a.registration_id WHERE r.event_id=%d AND a.event_type='MANAGEMENT_request_review' AND a.id=(SELECT MAX(b.id) FROM {$wpdb->prefix}mi_registration_events b WHERE b.registration_id=a.registration_id AND b.event_type='MANAGEMENT_request_review')", $event_id ), ARRAY_A );
			self::check_database(); $reviews = array_column( $review_rows, null, 'registration_id' );
            $attendance_rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.detail_json,a.actor_label,a.created_at FROM {$wpdb->prefix}mi_registration_events a JOIN {$wpdb->prefix}mi_registrations r ON r.id=a.registration_id WHERE r.event_id=%d AND a.event_type='MANAGEMENT_attendance' ORDER BY a.id", $event_id ), ARRAY_A );
            self::check_database(); $attendance = self::attendance_map( $attendance_rows );
			$grouped = array();
			foreach ( $people as $person ) $grouped[$person['registration_id']][] = $person;
			$has_rooms = count( self::rooms( $event_id ) ) > 0;
			$requested_rooms = array();
			foreach ( $people as $person ) foreach ( self::decode( $person['options_json'] ?? '' ) as $option ) if ( isset( self::room_types()[$option['code'] ?? ''] ) && (int) ( $option['quantity'] ?? 0 ) > 0 ) $requested_rooms[(int) $person['id']] = true;
			$needs_room = static function ( $person ) use ( $requested_rooms, $has_rooms ) { return $requested_rooms ? isset( $requested_rooms[(int) $person['id']] ) : $has_rooms; };
			$items = array(); $individuals = array(); $field_labels = array();
			foreach ( $orders as $order ) {
				$request_review = self::request_review( $order, $reviews[$order['id']] ?? array() );
				$all_participants = $grouped[$order['id']] ?? array(); $missing = 0; $unassigned = 0;
				$buyer_matches = array_values( array_filter( $all_participants, static function ( $person ) use ( $order ) { return mb_strtolower( trim( $person['first_name'] ) ) === mb_strtolower( trim( $order['buyer_first_name'] ) ) && mb_strtolower( trim( $person['last_name'] ) ) === mb_strtolower( trim( $order['buyer_last_name'] ) ); } ) );
				$buyer_participant_id = count( $all_participants ) > 1 && 1 === count( $buyer_matches ) ? (int) $buyer_matches[0]['id'] : 0;
				$first_person_id = (int) ( $all_participants[0]['id'] ?? 0 );
				$participants = array_values( array_filter( $all_participants, static function ( $person ) { return 'ACTIVE' === $person['status']; } ) );
				$definitions = self::definitions( $order ); $snapshot = self::decode( $order['snapshot_json'] );
				foreach ( $definitions as $definition ) $field_labels[$definition['key']] = $definition['label'];
				foreach ( $participants as $i => $person ) {
					$fields = self::decode( $person['extra_json'] );
					if ( (int) $person['id'] === $first_person_id || 'ALL' === ( $snapshot['event']['participant_extra_scope'] ?? '' ) ) foreach ( $definitions as $f ) if ( $f['required'] && '' === trim( (string) ( $fields[$f['key']] ?? '' ) ) ) { $missing++; break; }
					if ( $needs_room( $person ) && ! $person['room_code'] ) $unassigned++;
				}
				$sum = (int) ( $paid[$order['id']] ?? 0 );
				$position = MI_Payment_Ledger::position( $order, $sum );
				$deposit = array_intersect_key( $position, array_flip( array( 'deposit_plan', 'deposit_due', 'deposit_missing', 'deposit_covered', 'balance' ) ) );
				$deposit['paid'] = $sum;
				$collectible = $position['managed'] && in_array( $order['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true );
				foreach ( $all_participants as $number => $person ) {
					$fields = self::decode( $person['extra_json'] ); $missing_fields = array();
					if ( (int) $person['id'] === $first_person_id || 'ALL' === ( $snapshot['event']['participant_extra_scope'] ?? '' ) ) foreach ( $definitions as $f ) if ( $f['required'] && '' === trim( (string) ( $fields[$f['key']] ?? '' ) ) ) $missing_fields[] = $f['label'];
					$individuals[] = $deposit + array( 'is_buyer' => (int) $person['id'] === $buyer_participant_id, 'id' => (int) $person['id'], 'number' => $number + 1, 'attendance' => $attendance[$person['id']]['state'] ?? 'UNRECORDED', 'code' => $order['order_code'], 'name' => trim( ( $person['first_name'] ?? '' ) . ' ' . ( $person['last_name'] ?? '' ) ), 'buyer' => trim( $order['buyer_first_name'] . ' ' . $order['buyer_last_name'] ), 'email' => self::participant_contact( $fields, $definitions, 'email', $order['buyer_email'] ?? '' ), 'phone' => self::participant_contact( $fields, $definitions, 'phone', $order['buyer_phone'] ?? '' ), 'status' => 'CANCELLED' === $person['status'] ? 'CANCELLED' : $order['status'], 'room' => $person['room_code'], 'fields' => $fields, 'missing' => $missing_fields, 'unassigned' => $needs_room( $person ) && ! $person['room_code'], 'collectible' => $collectible && $position['balance'] > 0, 'requests' => self::visible_special_requests( $order['special_requests'] ?? '' ), 'requests_reviewed' => $request_review['reviewed'], 'offer_expires_at' => $order['waitlist_offer_expires_at'] ?? '', 'options' => self::decode( $person['options_json'] ?? '' ) );
				}
				$items[] = $deposit + array( 'code' => $order['order_code'], 'name' => trim( $order['buyer_first_name'] . ' ' . $order['buyer_last_name'] ), 'status' => $order['status'], 'active' => ! in_array( $order['status'], array( 'CANCELLED','EXPIRED' ), true ), 'participants' => count( $participants ), 'total' => (int) $order['total_cents'], 'paid' => $sum, 'balance' => $position['balance'], 'collectible' => $collectible, 'missing' => $missing, 'unassigned' => $unassigned, 'requests' => self::visible_special_requests( $order['special_requests'] ?? '' ), 'requests_reviewed' => $request_review['reviewed'], 'offer_expires_at' => $order['waitlist_offer_expires_at'] ?? '', 'order_options' => self::decode( $order['order_options_json'] ?? '' ) );
			}
			$options = function_exists( 'get_post_meta' ) ? (array) get_post_meta( $event_id, '_mi_options', true ) : array();
			$mode = function_exists( 'get_post_meta' ) ? get_post_meta( $event_id, '_mi_economic_mode', true ) : '';
			$features = array( 'rooms' => $has_rooms, 'room_inventory' => 'ON_DEMAND' !== get_post_meta( $event_id, '_mi_accommodation_management', true ), 'payments' => in_array( $mode, array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ), 'deposit' => 'DEPOSIT_BALANCE' === $mode );
			foreach ( $orders as $order ) {
				$snapshot = self::decode( $order['snapshot_json'] );
				$options = array_merge( $options, (array) ( $snapshot['event']['options'] ?? array() ) );
				$features['payments'] = $features['payments'] || in_array( $order['economic_mode'] ?? '', array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true );
				$features['deposit'] = $features['deposit'] || 'DEPOSIT_BALANCE' === ( $order['economic_mode'] ?? '' );
			}
			foreach ( $options as $option ) if ( is_array( $option ) && 0 === strpos( (string) ( $option['code'] ?? '' ), 'alloggio-' ) ) $features['rooms'] = true;
			if ( $requested_rooms ) $features['rooms'] = true;
			$room_types = array(); $known_types = self::room_types();
			foreach ( $individuals as $person ) $options = array_merge( $options, $person['options'] );
			foreach ( $options as $option ) if ( isset( $known_types[$option['code'] ?? ''] ) ) $room_types[$option['code']] = $known_types[$option['code']];
			return array( 'ok' => true, 'features' => $features, 'room_types' => $room_types, 'option_definitions' => array_values( array_filter( $options, 'is_array' ) ), 'items' => $items, 'people' => $individuals, 'field_labels' => $field_labels, 'rooms' => self::rooms( $event_id ), 'updated_at' => gmdate( 'c' ), 'registration_url' => MI_Shortcode::url_iscrizione( $event_id ) );
		} catch ( Throwable $error ) { return new WP_Error( 'mi_management_read', $error->getMessage() ); }
	}
	/** Usa il contatto personale quando il modulo lo prevede; altrimenti conserva il recapito del referente. */
	private static function participant_contact( $fields, $definitions, $kind, $fallback ) {
		$aliases = 'email' === $kind ? array( 'email', 'participant_email' ) : array( 'phone', 'participant_phone', 'mobile' );
		$type = 'email' === $kind ? 'email' : 'tel';
		foreach ( $definitions as $definition ) {
			$key = (string) ( $definition['key'] ?? '' );
			if ( $type !== ( $definition['type'] ?? '' ) && ! in_array( $key, $aliases, true ) ) continue;
			$value = trim( (string) ( $fields[$key] ?? '' ) );
			if ( '' !== $value ) return $value;
		}
		return (string) $fallback;
	}
	/** Canonical codes generated by the event creation form. */
	public static function room_types() {
		return array(
			'alloggio-singola' => array( 'prefix' => 'S', 'name' => 'Singola', 'capacity' => 1 ),
			'alloggio-doppia-matrimoniale' => array( 'prefix' => 'DM', 'name' => 'Doppia matrimoniale', 'capacity' => 2 ),
			'alloggio-doppia-separati' => array( 'prefix' => 'DS', 'name' => 'Doppia letti separati', 'capacity' => 2 ),
			'alloggio-tripla' => array( 'prefix' => 'T', 'name' => 'Tripla', 'capacity' => 3 ),
			'alloggio-multipla' => array( 'prefix' => 'M', 'name' => 'Multipla', 'capacity' => 1, 'individual' => true ),
		);
	}
	/** Preview and commit use the same server-calculated plan. No client-supplied prices. */
	private static function accommodation_plan( $event_id, $data, $lock = false ) {
		global $wpdb;
		if ( ! is_array( $data ) || ! is_array( $data['people'] ?? null ) || ! count( $data['people'] ) || count( $data['people'] ) > 100 || ! is_string( $data['reason'] ?? '' ) || mb_strlen( $data['reason'] ?? '' ) > 500 ) throw new InvalidArgumentException( 'Seleziona le persone. Le annotazioni facoltative possono contenere al massimo 500 caratteri.' );
		$type = self::room_types()[$data['type'] ?? ''] ?? null;
		$number = $data['number'] ?? '';
		if ( ! $type || ! is_string( $number ) || ( '' !== $number && ! preg_match( '/^[1-9][0-9]{0,5}$/', $number ) ) ) throw new InvalidArgumentException( 'Sistemazione o numero non valido.' );
		if ( 1 === $type['capacity'] && count( $data['people'] ) > 1 && '' !== $number ) throw new InvalidArgumentException( 'Per più singole o multiple lascia il numero automatico: ciascuna persona ha un codice distinto.' );
		$grouped = array(); $seen = array();
		foreach ( $data['people'] as $person ) {
			if ( ! is_array( $person ) || ! is_string( $person['code'] ?? null ) || ! is_int( $person['number'] ?? null ) || $person['number'] < 1 ) throw new InvalidArgumentException( 'Persona non valida.' );
			$key = $person['code'] . ':' . $person['number']; if ( isset( $seen[$key] ) ) throw new InvalidArgumentException( 'Persona selezionata due volte.' );
			$seen[$key] = true; $grouped[$person['code']][] = $person['number'];
		}
		ksort( $grouped ); $rooms = self::rooms( $event_id ); $inventory = array_column( $rooms, null, 'code' ); $next = 1;
		foreach ( $rooms as $room ) if ( preg_match( '/^' . $type['prefix'] . '([1-9][0-9]*)$/', $room['code'], $match ) ) $next = max( $next, (int) $match[1] + 1 );
		$shared = '' !== $number ? $type['prefix'] . $number : $type['prefix'] . $next;
		$plan = array( 'reason' => sanitize_textarea_field( $data['reason'] ?? '' ), 'people' => array(), 'orders' => array(), 'new_rooms' => array() );
		$fingerprint = array( $data, $rooms ); $occupancy = array_column( $rooms, 'occupied', 'code' );
		foreach ( $grouped as $code => $numbers ) {
			sort( $numbers );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d AND order_code=%s" . ( $lock ? ' FOR UPDATE' : '' ), $event_id, $code ), ARRAY_A ); self::check_database();
			if ( ! $row || ! in_array( $row['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ) throw new InvalidArgumentException( 'Iscrizione non ammessa o non accessibile: ' . $code );
			$booking = self::booking( $row ); $snapshot = self::decode( $row['snapshot_json'] );
			$definitions = array_column( $snapshot['event']['options'] ?? array(), null, 'code' );
			$target = $definitions[$data['type']] ?? null;
			if ( ! $target || ( $target['scope'] ?? '' ) !== 'TICKET' || ! isset( $target['price_cents'] ) || (int) $target['price_cents'] < 0 ) throw new InvalidArgumentException( 'La nuova sistemazione non ha una tariffa valida nell’iscrizione ' . $code . '.' );
			$movements = $wpdb->get_results( $wpdb->prepare( "SELECT amount_cents,transaction_kind FROM {$wpdb->prefix}mi_payments WHERE registration_id=%d" . ( $lock ? ' FOR UPDATE' : '' ), $row['id'] ), ARRAY_A ); self::check_database();
			$paid = 0; foreach ( $movements as $movement ) $paid += ( 'REFUND' === $movement['transaction_kind'] ? -1 : 1 ) * (int) $movement['amount_cents'];
			$fingerprint[] = array( $row, $booking['version'], $paid ); $delta = 0;
			foreach ( $numbers as $n ) {
				$person = array_column( $booking['participants'], null, 'number' )[$n] ?? null;
				if ( ! $person || 'ACTIVE' !== $person['status'] ) throw new InvalidArgumentException( 'Persona non disponibile in ' . $code );
				$old = array_values( array_filter( $person['options'], static function ( $option ) { return 0 === strpos( $option['code'] ?? '', 'alloggio-' ) && (int) ( $option['quantity'] ?? 0 ) > 0; } ) );
				if ( count( $old ) !== 1 || (int) $old[0]['quantity'] !== 1 || ! isset( $old[0]['unit_price_cents'] ) ) throw new InvalidArgumentException( 'Verifica la sistemazione e la quota precedente di ' . $person['first_name'] . ' ' . $person['last_name'] . '.' );
				if ( $old[0]['code'] === $data['type'] ) throw new InvalidArgumentException( 'La sistemazione è già quella scelta. Per cambiare solo il numero usa Salva assegnazioni.' );
				$options = array_values( array_filter( $person['options'], static function ( $option ) { return 0 !== strpos( $option['code'] ?? '', 'alloggio-' ); } ) );
				$options[] = array( 'code' => $data['type'], 'name' => sanitize_text_field( $target['name'] ?? $type['name'] ), 'quantity' => 1, 'unit_price_cents' => (int) $target['price_cents'] );
				$pricing = $snapshot['event']['pricing_mode'] ?? '';
				if ( ! in_array( $pricing, array( 'FIXED', 'CALCULATED', 'ZERO' ), true ) ) throw new InvalidArgumentException( 'Modalità tariffaria non disponibile per ' . $code . '. Verifica l’iscrizione prima del cambio.' );
				$change = 'ZERO' === $pricing ? 0 : (int) $target['price_cents'] - (int) $old[0]['unit_price_cents']; $delta += $change;
				$new_room = 1 === $type['capacity'] && '' === $number ? $type['prefix'] . $next++ : $shared;
				if ( ! preg_match( '/^' . $type['prefix'] . '[1-9][0-9]{0,5}$/', $new_room ) ) throw new InvalidArgumentException( 'Numerazione esaurita.' );
				if ( isset( $inventory[$new_room] ) && $inventory[$new_room]['capacity'] !== $type['capacity'] ) throw new InvalidArgumentException( 'Capienza incompatibile per ' . $new_room );
				if ( ! isset( $inventory[$new_room] ) ) $plan['new_rooms'][$new_room] = array( 'code' => $new_room, 'name' => $new_room, 'capacity' => $type['capacity'] );
				if ( $person['room'] ) $occupancy[$person['room']] = ( $occupancy[$person['room']] ?? 0 ) - 1;
				$occupancy[$new_room] = ( $occupancy[$new_room] ?? 0 ) + 1;
				$plan['people'][] = array( 'id' => $person['id'], 'registration_id' => (int) $row['id'], 'code' => $code, 'number' => $n, 'name' => trim( $person['first_name'] . ' ' . $person['last_name'] ), 'before_type' => $old[0]['name'] ?? $old[0]['code'], 'after_type' => $type['name'], 'before_room' => $person['room'], 'after_room' => $new_room, 'before_options' => $person['options'], 'after_options' => $options, 'delta' => $change );
			}
			$total = (int) $row['total_cents'] + $delta;
			if ( $total < 0 || $total > 100000000 ) throw new InvalidArgumentException( 'Il nuovo dovuto di ' . $code . ' non è valido: verifica le rettifiche precedenti.' );
			$managed = in_array( $row['economic_mode'], array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true );
			$initial = 'FULL_PAYMENT' === $row['economic_mode'] ? $total : min( (int) $row['initial_due_cents'], $total );
			$changes = array( 'total_cents' => $total );
			if ( $managed ) { $changes += array( 'initial_due_cents' => $initial, 'balance_cents' => $total - $initial, 'status' => $paid >= $initial ? 'CONFIRMED' : 'PENDING_PAYMENT', 'expires_at' => $paid >= $initial ? null : $row['payment_deadline_at'] ); }
			$plan['orders'][] = array( 'id' => (int) $row['id'], 'code' => $code, 'before_total' => (int) $row['total_cents'], 'after_total' => $total, 'delta' => $delta, 'paid' => $paid, 'due' => $managed ? max( 0, $total - $paid ) : 0, 'refund' => $managed ? max( 0, $paid - $total ) : 0, 'changes' => $changes );
		}
		foreach ( $occupancy as $code => $count ) if ( $count > ( $inventory[$code]['capacity'] ?? $plan['new_rooms'][$code]['capacity'] ?? 0 ) ) throw new InvalidArgumentException( 'Capienza superata per ' . $code . '. Scegli un’altra camera.' );
		$plan['version'] = hash( 'sha256', wp_json_encode( array( $fingerprint, $plan ) ) );
		return $plan;
	}
	public static function change_accommodation( $event_id, $data, $version = null, $request_id = '' ) {
		global $wpdb;
		if ( ! MI_Portal_Management::allowed() || ! MI_Portal_Payments::allowed() || ! MI_Access::can_access_event( $event_id ) ) return new WP_Error( 'mi_room_change_scope', 'Occorre il permesso di gestione dei pagamenti per cambiare sistemazione e dovuto.' );
		$save = null !== $version;
		if ( $save && ! preg_match( '/^wp_' . get_current_user_id() . '_[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $request_id ) ) return new WP_Error( 'mi_room_change_request', 'Identificativo non valido.' );
		if ( $save && class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( $event_id ); if ( is_wp_error( $lease ) ) return $lease; }
		$hash = hash( 'sha256', wp_json_encode( array( 'CHANGE_ACCOMMODATION', $event_id, $data, $version ) ) );
		try {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new RuntimeException();
			if ( $save ) {
				self::lock_room_event( $event_id );
				$previous = $wpdb->get_row( $wpdb->prepare( "SELECT request_hash FROM {$wpdb->prefix}mi_management_requests WHERE request_id=%s", $request_id ), ARRAY_A ); self::check_database();
				if ( $previous ) {
					if ( ! hash_equals( $previous['request_hash'], $hash ) ) throw new InvalidArgumentException( 'Identificativo già usato con dati diversi.' );
					if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException();
					return array( 'saved' => true, 'replayed' => true, 'message' => 'Cambio già registrato. Eventuali rimborsi vanno registrati separatamente.' );
				}
			}
			$plan = self::accommodation_plan( $event_id, $data, $save );
			if ( ! $save ) { $wpdb->query( 'ROLLBACK' ); return $plan; }
			if ( ! is_string( $version ) || ! hash_equals( $plan['version'], $version ) ) throw new InvalidArgumentException( 'I dati o i versamenti sono cambiati. Ricalcola l’anteprima prima di confermare.' );
			foreach ( $plan['new_rooms'] as $room ) self::save_room( array( 'event_id' => $event_id, 'accommodations' => self::rooms( $event_id ) ), 'room_save', $room );
			foreach ( $plan['people'] as $person ) if ( false === $wpdb->update( $wpdb->prefix . 'mi_participants', array( 'options_json' => wp_json_encode( $person['after_options'] ), 'room_code' => $person['after_room'] ), array( 'id' => $person['id'] ) ) ) throw new RuntimeException();
			foreach ( $plan['orders'] as $order ) {
				if ( false === $wpdb->update( $wpdb->prefix . 'mi_registrations', $order['changes'], array( 'id' => $order['id'] ) ) ) throw new RuntimeException();
				MI_Registration_Service::mark_workspace_changed_locked( $order['id'] );
				$audit = array( 'request_id' => $request_id, 'reason' => $plan['reason'], 'economics' => $order, 'people' => array_values( array_filter( $plan['people'], static function ( $p ) use ( $order ) { return $p['registration_id'] === $order['id']; } ) ) );
				if ( ! MI_Registration_Service::append_registration_event( $order['id'], 'CHANGE_ACCOMMODATION', '', '', 'WP#' . get_current_user_id(), $audit ) ) throw new RuntimeException();
			}
			if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_management_state SET revision=revision+1 WHERE event_id=%d", $event_id ) ) || false === $wpdb->insert( $wpdb->prefix . 'mi_management_requests', array( 'request_id' => $request_id, 'event_id' => $event_id, 'registration_id' => 0, 'request_hash' => $hash, 'actor_id' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) ) ) throw new RuntimeException();
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException();
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			if ( $error instanceof InvalidArgumentException ) return array( 'saved' => false, 'rejected' => true, 'message' => $error->getMessage() );
			return new WP_Error( 'mi_room_change_save', 'Operazione non confermata. Riprova la stessa richiesta.' );
		}
		foreach ( $plan['orders'] as $order ) try { MI_Registration_Service::accoda_iscrizione_workspace( $order['id'] ); } catch ( Throwable $error ) { /* Persistent queue retries. */ }
		return array( 'saved' => true, 'message' => 'Sistemazione, camera e dovuto aggiornati. Eventuali rimborsi vanno registrati separatamente.', 'orders' => $plan['orders'] );
	}
	/** First lock in registration and room-allocation transactions. */
	public static function lock_room_event( $event_id ) {
		global $wpdb;
		if ( $event_id < 1 || false === $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}mi_management_state (event_id) VALUES (%d) ON DUPLICATE KEY UPDATE event_id=VALUES(event_id)", $event_id ) ) ) throw new RuntimeException( 'Evento non disponibile per le assegnazioni.' );
	}
	/** Internal to registration transactions; the caller holds the event room lock. */
	public static function auto_assign_rooms_locked( $registration_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE id=%d FOR UPDATE", $registration_id ), ARRAY_A ); self::check_database();
		if ( ! $row || ! in_array( $row['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ) return;
		$event_id = (int) $row['event_id']; $booking = self::booking( $row ); $all = $booking['participants']; $rooms = self::rooms( $event_id ); $groups = array();
		foreach ( self::room_types() as $code => $type ) {
			$selected = array_values( array_filter( $all, static function ( $person ) use ( $code ) {
				return 'ACTIVE' === $person['status'] && ! $person['room'] && array_filter( $person['options'], static function ( $option ) use ( $code ) { return ( $option['code'] ?? '' ) === $code && (int) ( $option['quantity'] ?? 0 ) > 0; } );
			} ) );
			if ( 1 === $type['capacity'] ) { foreach ( $selected as $person ) $groups[] = array( $type, array( $person ) ); }
			elseif ( count( $all ) === $type['capacity'] && count( $selected ) === count( $all ) ) $groups[] = array( $type, $selected );
			elseif ( $selected ) {
				$has_open_room = false;
				foreach ( $rooms as $room ) if ( preg_match( '/^' . $type['prefix'] . '[1-9][0-9]*$/', $room['code'] ) && $room['available'] > 0 ) { $has_open_room = true; break; }
				if ( ! $has_open_room ) $groups[] = array( $type, array( $selected[0] ) );
			}
		}
		if ( ! $groups ) return;
		$next = array(); $assignments = array();
		foreach ( $groups as list( $type, $persons ) ) {
			$prefix = $type['prefix'];
			if ( ! isset( $next[$prefix] ) ) {
				$next[$prefix] = 1;
				foreach ( $rooms as $room ) if ( preg_match( '/^' . $prefix . '([1-9][0-9]*)$/', $room['code'], $match ) ) $next[$prefix] = max( $next[$prefix], (int) $match[1] + 1 );
			}
			if ( $next[$prefix] > 999999 ) throw new RuntimeException( 'Numerazione camere esaurita.' );
			$code = $prefix . $next[$prefix]++;
			self::save_room( array( 'event_id' => $event_id, 'accommodations' => $rooms ), 'room_save', array( 'code' => $code, 'name' => $code, 'capacity' => $type['capacity'] ) );
			foreach ( $persons as $person ) {
				if ( false === $wpdb->update( $wpdb->prefix . 'mi_participants', array( 'room_code' => $code ), array( 'id' => $person['id'] ) ) ) throw new RuntimeException( 'Assegnazione automatica non salvata.' );
				$assignments[] = array( 'participant_id' => $person['id'], 'before' => '', 'after' => $code );
			}
		}
		MI_Registration_Service::mark_workspace_changed_locked( $registration_id );
		if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_management_state SET revision=revision+1 WHERE event_id=%d", $event_id ) ) || ! MI_Registration_Service::append_registration_event( $registration_id, 'AUTO_ROOM_ASSIGN', '', '', 'SYSTEM', array( 'assignments' => $assignments ) ) ) throw new RuntimeException( 'Assegnazione automatica non tracciata.' );
	}
	/** Event inventory also works before the first registration exists. */
	public static function save_event_room( $event_id, $operation, $data, $version, $request_id ) {
		global $wpdb;
		if ( ! MI_Portal_Management::allowed() || ! MI_Access::can_access_event( $event_id ) || ! in_array( $operation, array( 'room_save', 'room_delete' ), true ) || ! is_array( $data ) || ! preg_match( '/^wp_' . get_current_user_id() . '_[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $request_id ) ) return new WP_Error( 'mi_room_scope', 'Richiesta non consentita.' );
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( $event_id ); if ( is_wp_error( $lease ) ) return $lease; }
		$hash = hash( 'sha256', wp_json_encode( array( 'event_room', $event_id, $operation, $data, $version ) ) );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) return new WP_Error( 'mi_room_busy', 'Registro non disponibile.' );
		try {
			if ( false === $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}mi_management_state (event_id) VALUES (%d) ON DUPLICATE KEY UPDATE event_id=VALUES(event_id)", $event_id ) ) ) throw new RuntimeException();
			$previous = $wpdb->get_row( $wpdb->prepare( "SELECT request_hash FROM {$wpdb->prefix}mi_management_requests WHERE request_id=%s", $request_id ), ARRAY_A ); self::check_database();
			if ( $previous ) {
				if ( ! hash_equals( $previous['request_hash'], $hash ) ) throw new InvalidArgumentException( 'Identificativo già utilizzato con dati diversi.' );
			} else {
				$rooms = self::rooms( $event_id );
				if ( ! hash_equals( hash( 'sha256', wp_json_encode( $rooms ) ), (string) $version ) ) throw new InvalidArgumentException( 'Le camere sono cambiate. Aggiorna il riepilogo prima di salvare.' );
				self::save_room( array( 'event_id' => $event_id, 'accommodations' => $rooms ), $operation, $data );
				if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_management_state SET revision=revision+1 WHERE event_id=%d", $event_id ) ) ) throw new RuntimeException();
				if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_registrations SET workspace_revision=workspace_revision+1,workspace_status='PENDING',workspace_attempts=0 WHERE event_id=%d", $event_id ) ) ) throw new RuntimeException();
				if ( false === $wpdb->insert( $wpdb->prefix . 'mi_management_requests', array( 'request_id' => $request_id, 'event_id' => $event_id, 'registration_id' => 0, 'request_hash' => $hash, 'actor_id' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) ) ) throw new RuntimeException();
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException();
			return array( 'saved' => true, 'replayed' => (bool) $previous, 'message' => 'Inventario camere salvato. Le prenotazioni sono in attesa di replica sul foglio.' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			if ( $error instanceof InvalidArgumentException ) return array( 'saved' => false, 'rejected' => true, 'message' => $error->getMessage() );
			return new WP_Error( 'mi_room_save', 'Salvataggio non confermato. Riprova la stessa richiesta.' );
		}
	}

	public static function save_attendance_bulk( $event_id, $items ) {
		global $wpdb;
		$group_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		if ( ! $group_id || '1' !== get_post_meta( $group_id, '_mi_annual_attendance_report', true ) ) return new WP_Error( 'mi_attendance_disabled', 'La registrazione delle presenze non è attiva per questo gruppo.' );
		if ( ! is_array( $items ) || ! count( $items ) || count( $items ) > 500 ) return new WP_Error( 'mi_attendance_bulk', 'Seleziona da una a 500 persone.' );
		$normalized = array();
		foreach ( $items as $item ) {
			$id = absint( $item['id'] ?? 0 ); $state = sanitize_key( $item['attendance'] ?? '' );
			$state = strtoupper( $state );
			if ( ! $id || ! in_array( $state, array( 'UNRECORDED', 'PRESENT', 'ABSENT' ), true ) ) return new WP_Error( 'mi_attendance_bulk', 'Presenza non valida.' );
			$normalized[$id] = $state;
		}
		$ids = implode( ',', array_map( 'absint', array_keys( $normalized ) ) );
		$rows = $wpdb->get_results( "SELECT p.id,p.registration_id FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE p.id IN ({$ids}) AND p.status='ACTIVE' AND r.event_id=" . absint( $event_id ) . " AND r.status IN ('CONFIRMED','PENDING_PAYMENT')", ARRAY_A );
		self::check_database();
		if ( count( $rows ) !== count( $normalized ) ) return new WP_Error( 'mi_attendance_bulk', 'Una o più persone non sono più disponibili. Aggiorna l’elenco.' );
		$wpdb->query( 'START TRANSACTION' );
		try {
			foreach ( $rows as $row ) if ( ! MI_Registration_Service::append_registration_event( (int) $row['registration_id'], 'MANAGEMENT_attendance', '', '', 'WP#' . get_current_user_id(), array( 'participant_id' => (int) $row['id'], 'attendance' => $normalized[(int) $row['id']] ) ) ) throw new RuntimeException( 'Presenza non registrata.' );
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma delle presenze non disponibile.' );
		} catch ( Throwable $error ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'mi_attendance_bulk', $error->getMessage() ); }
		return array( 'ok' => true, 'saved' => true, 'count' => count( $rows ), 'message' => count( $rows ) . ' presenze aggiornate.' );
	}
	public static function save( $id, $operation, $data, $version, $request_id ) {
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( MI_Event_Deletion::registration_event( $id ) ); if ( is_wp_error( $lease ) ) return $lease; }
		global $wpdb;
		if ( ! MI_Portal_Management::allowed() || ! preg_match( '/^wp_' . get_current_user_id() . '_[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $request_id ) || ! is_array( $data ) || ! in_array( $operation, array( 'participant','room_save','room_delete','request_review','attendance','adjust_due','identity_link','change_options' ), true ) ) return new WP_Error( 'mi_management_request', 'Richiesta non valida.' );
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
			if ( 'request_review' === $operation ) {
				if ( ! isset( $data['reviewed'] ) || ! is_bool( $data['reviewed'] ) || ! trim( (string) ( $locked['special_requests'] ?? '' ) ) ) throw new InvalidArgumentException( 'Richiesta particolare assente o verifica non valida.' );
			} elseif ( 'change_options' === $operation ) {
				if ( ! in_array( $locked['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) || ! is_array( $data['options'] ?? null ) || ! is_int( $data['participant_id'] ?? null ) || ! is_string( $data['reason'] ?? null ) || mb_strlen( $data['reason'] ) > 500 ) throw new InvalidArgumentException( 'Indica servizi validi per una prenotazione ammessa. L’annotazione facoltativa può contenere al massimo 500 caratteri.' );
				$snapshot = self::decode( $locked['snapshot_json'] ); $person = null;
				if ( $data['participant_id'] ) {
					$person = array_column( $booking['participants'], null, 'id' )[$data['participant_id']] ?? null;
					if ( ! $person || 'ACTIVE' !== $person['status'] || ( 'ONE' === ( $snapshot['event']['participant_extra_scope'] ?? 'ONE' ) && 1 !== $person['number'] ) ) throw new InvalidArgumentException( 'Servizi individuali non modificabili per questa persona.' );
				}
				$is_accommodation = static function ( $definition ) { return 0 === strpos( sanitize_key( $definition['code'] ?? '' ), 'alloggio-' ) || 'alloggio' === sanitize_key( $definition['choice_group'] ?? '' ) || 'alloggio' === sanitize_key( $definition['category'] ?? '' ); };
				$definitions = array_values( array_filter( (array) ( $snapshot['event']['options'] ?? array() ), static function ( $definition ) use ( $is_accommodation ) { return ! $is_accommodation( (array) $definition ); } ) );
				$accommodation_codes = array_map( static function ( $definition ) { return sanitize_key( $definition['code'] ?? '' ); }, array_filter( (array) ( $snapshot['event']['options'] ?? array() ), $is_accommodation ) );
				if ( $person ) foreach ( array_keys( $data['options'] ) as $option_code ) if ( in_array( sanitize_key( $option_code ), $accommodation_codes, true ) ) throw new InvalidArgumentException( 'Per cambiare alloggio o camera usa Cambia sistemazione.' );
				if ( $person ) foreach ( $data['options'] as $quantity ) if ( ! in_array( $quantity, array( 0, 1 ), true ) ) throw new InvalidArgumentException( 'Ogni servizio individuale può essere selezionato una sola volta.' );
				$options = MI_Registration_Service::validate_options( $data['options'], $definitions, $person ? 'TICKET' : 'ORDER' );
				if ( is_wp_error( $options ) ) throw new InvalidArgumentException( $options->get_error_message() );
				$current_options = $person ? $person['options'] : self::decode( $locked['order_options_json'] );
				if ( $person ) $options = array_merge( array_values( array_filter( $current_options, static function ( $option ) { return 0 === strpos( sanitize_key( $option['code'] ?? '' ), 'alloggio-' ); } ) ), $options );
				$option_change = array( 'participant_id' => $person ? $person['id'] : 0, 'before_options' => $current_options, 'after_options' => $options, 'reason' => sanitize_textarea_field( $data['reason'] ) );
				$table = $wpdb->prefix . ( $person ? 'mi_participants' : 'mi_registrations' ); $column = $person ? 'options_json' : 'order_options_json';
				if ( false === $wpdb->update( $table, array( $column => wp_json_encode( $options ) ), array( 'id' => $person ? $person['id'] : $id ) ) ) throw new RuntimeException( 'Servizi non aggiornati.' );
			} elseif ( 'identity_link' === $operation ) {
				$person = array_column( $booking['participants'], null, 'id' )[$data['participant_id'] ?? 0] ?? null;
				if ( ! $person || ! is_int( $data['target_id'] ?? null ) || $data['target_id'] < 0 ) throw new InvalidArgumentException( 'Collegamento personale non valido.' );
				$target_id = 0;
				if ( $data['target_id'] ) {
					$target = MI_Attendance_Report::target( $event_id, sanitize_text_field( $data['target_order'] ?? '' ), (int) ( $data['target_number'] ?? 0 ) );
					if ( (int) $target['id'] !== $data['target_id'] || (int) $target['id'] === (int) $person['id'] ) throw new InvalidArgumentException( 'Riferimento cambiato o uguale alla persona corrente. Verifica nuovamente.' );
					$target_id = (int) $target['id'];
				}
			} elseif ( 'adjust_due' === $operation ) {
				if ( ! MI_Portal_Payments::allowed() || ! in_array( $locked['status'], array( 'CONFIRMED', 'PENDING_PAYMENT', 'CANCELLED', 'EXPIRED' ), true ) || ! in_array( $locked['economic_mode'], array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) || ! is_int( $data['total_cents'] ?? null ) || $data['total_cents'] < 0 || $data['total_cents'] > 100000000 || ! is_string( $data['reason'] ?? null ) || ! trim( $data['reason'] ) || mb_strlen( $data['reason'] ) > 500 ) throw new InvalidArgumentException( 'Indica un importo valido e il motivo della rettifica. È richiesto il permesso pagamenti.' );
				$adjustment = array( 'before_total' => (int) $locked['total_cents'], 'before_initial' => (int) $locked['initial_due_cents'], 'before_balance' => (int) $locked['balance_cents'], 'after_total' => $data['total_cents'], 'reason' => sanitize_textarea_field( $data['reason'] ) );
				if ( ! trim( $adjustment['reason'] ) ) throw new InvalidArgumentException( 'Indica il motivo della rettifica.' );
				$initial = 'FULL_PAYMENT' === $locked['economic_mode'] ? $data['total_cents'] : min( (int) $locked['initial_due_cents'], $data['total_cents'] );
				$changes = array( 'total_cents' => $data['total_cents'], 'initial_due_cents' => $initial, 'balance_cents' => $data['total_cents'] - $initial );
				if ( in_array( $locked['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ) {
					$paid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN transaction_kind='REFUND' THEN -amount_cents ELSE amount_cents END),0) FROM {$wpdb->prefix}mi_payments WHERE registration_id=%d", $id ) ); self::check_database();
					$changes['status'] = $paid >= $initial ? 'CONFIRMED' : 'PENDING_PAYMENT';
					$changes['expires_at'] = 'CONFIRMED' === $changes['status'] ? null : $locked['payment_deadline_at'];
				}
				if ( false === $wpdb->update( $wpdb->prefix . 'mi_registrations', $changes, array( 'id' => $id ) ) ) throw new RuntimeException( 'Rettifica non salvata.' );
			} elseif ( 'attendance' === $operation ) {
				$person = array_column( $booking['participants'], null, 'id' )[$data['participant_id'] ?? 0] ?? null;
				if ( ! $person || 'ACTIVE' !== $person['status'] || ! in_array( $locked['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) || ! in_array( $data['attendance'] ?? '', array( 'UNRECORDED', 'PRESENT', 'ABSENT' ), true ) ) throw new InvalidArgumentException( 'La presenza si registra soltanto per una persona ammessa. Scegli uno stato valido.' );
			} elseif ( 'participant' === $operation ) self::save_participant( $booking, $data );
			else self::save_room( $booking, $operation, $data );
			if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_management_state SET revision=revision+1 WHERE event_id=%d", $event_id ) ) ) throw new RuntimeException( 'Versione evento non aggiornata.' );
			if ( in_array( $operation, array( 'participant', 'adjust_due', 'change_options' ), true ) ) MI_Registration_Service::mark_workspace_changed_locked( $id );
			else if ( ! in_array( $operation, array( 'request_review', 'attendance', 'identity_link' ), true ) && false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_registrations SET workspace_revision=workspace_revision+1,workspace_status='PENDING',workspace_attempts=0 WHERE event_id=%d", $event_id ) ) ) throw new RuntimeException( 'Allineamento non accodato.' );
			if ( false === $wpdb->insert( $wpdb->prefix . 'mi_management_requests', array( 'request_id' => $request_id, 'event_id' => $event_id, 'registration_id' => $id, 'request_hash' => $hash, 'actor_id' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) ) ) throw new RuntimeException( 'Richiesta non registrata.' );
			$audit = array( 'request_id' => $request_id );
			if ( 'request_review' === $operation ) $audit += array( 'reviewed' => $data['reviewed'], 'text_hash' => hash( 'sha256', (string) $locked['special_requests'] ) );
			if ( 'attendance' === $operation ) $audit += array( 'participant_id' => (int) $person['id'], 'attendance' => $data['attendance'] );
			if ( 'adjust_due' === $operation ) $audit += $adjustment;
			if ( 'identity_link' === $operation ) $audit += array( 'participant_id' => (int) $person['id'], 'target_id' => $target_id );
			if ( 'change_options' === $operation ) $audit += $option_change;
			if ( ! MI_Registration_Service::append_registration_event( $id, 'MANAGEMENT_' . $operation, '', '', 'WP#' . get_current_user_id(), $audit ) ) throw new RuntimeException( 'Operazione non registrata.' );
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma del salvataggio non disponibile.' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			if ( $error instanceof InvalidArgumentException ) return array( 'ok' => true, 'saved' => false, 'rejected' => true, 'message' => $error->getMessage() );
			return new WP_Error( 'mi_management_save', 'Salvataggio non confermato. Riprova la stessa richiesta.' );
		}
		try { if ( ! in_array( $operation, array( 'request_review', 'attendance', 'identity_link' ), true ) ) MI_Registration_Service::accoda_iscrizione_workspace( $id ); } catch ( Throwable $error ) { /* Persistent queue retains the committed change. */ }
		return array( 'ok' => true, 'saved' => true, 'message' => 'change_options' === $operation ? 'Servizi aggiornati. Verifica il dovuto concordato ed eventualmente rettificalo; il rimborso si registra separatamente.' : ( 'attendance' === $operation ? 'Presenza salvata.' : ( 'request_review' === $operation ? 'Verifica della richiesta salvata.' : 'Modifica salvata. Il foglio Google verrà allineato.' ) ) );
	}
	/** Explicit Sheets commit. All affected people, including room swaps, commit together. */
	public static function save_sheet( $event_id, $changes, $request_id, $source = 'SHEET_SYNC' ) {
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
		if ( ! in_array( $source, array( 'SHEET_SYNC', 'ROOM_SWAP', 'ROOM_ASSIGN' ), true ) ) return new WP_Error( 'mi_sheet_source', 'Operazione non valida.' );
		if ( 'ROOM_ASSIGN' === $source && array_filter( $changes, static function ( $change ) { return 'room' !== $change['key']; } ) ) return new WP_Error( 'mi_room_assign', 'Sono consentite soltanto assegnazioni di camera.' );
		if ( 'ROOM_SWAP' === $source && ( 2 !== count( $changes ) || array_filter( $changes, static function ( $change ) { return 'room' !== $change['key']; } ) ) ) return new WP_Error( 'mi_room_swap', 'Lo scambio deve riguardare le camere di due persone.' );
		$hash = hash( 'sha256', wp_json_encode( 'SHEET_SYNC' === $source ? array( $event_id, $changes ) : array( $event_id, $changes, $source ) ) );
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
					if ( 'ROOM_ASSIGN' === $source ) {
						if ( ! in_array( $booking['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ) throw new InvalidArgumentException( 'La persona non è ancora ammessa all’evento.' );
						$type = self::room_types()[$patch['type'] ?? ''] ?? null;
						$requested = array_filter( $p['options'], static function ( $option ) use ( $patch ) { return ( $option['code'] ?? '' ) === ( $patch['type'] ?? '' ) && (int) ( $option['quantity'] ?? 0 ) > 0; } );
						if ( ! $type || ! $requested ) throw new InvalidArgumentException( 'La sistemazione richiesta è cambiata. Aggiorna il riepilogo.' );
						if ( '' !== $patch['after'] ) {
							if ( ! preg_match( '/^' . $type['prefix'] . '[1-9][0-9]{0,5}$/', $patch['after'] ) ) throw new InvalidArgumentException( 'Numero o sigla camera non validi per la sistemazione richiesta.' );
							$existing = array_column( self::rooms( $event_id ), null, 'code' )[$patch['after']] ?? null;
							$capacity = $type['capacity'] ?? ( $existing['capacity'] ?? ( $patch['capacity'] ?? null ) );
							if ( ! is_int( $capacity ) || $capacity < 1 || $capacity > 1000 ) throw new InvalidArgumentException( 'Indica il numero di posti per la camera multipla.' );
							if ( $existing && (int) $existing['capacity'] !== $capacity ) throw new InvalidArgumentException( 'La capienza della camera non corrisponde al tipo richiesto. Verifica l’inventario.' );
							if ( ! $existing ) self::save_room( array( 'event_id' => $event_id, 'accommodations' => self::rooms( $event_id ) ), 'room_save', array( 'code' => $patch['after'], 'name' => $patch['after'], 'capacity' => $capacity ) );
							$booking['accommodations'] = self::rooms( $event_id );
						}
					}
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
				if ( ! MI_Registration_Service::append_registration_event( (int) $row['id'], $source, '', '', 'WP#' . get_current_user_id(), array( 'request_id' => $request_id, 'cells' => count( $patches ), 'assignments' => 'ROOM_ASSIGN' === $source ? $patches : array() ) ) ) throw new RuntimeException( 'Registro operazioni non aggiornato.' );
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
		return array( 'ok' => true, 'saved' => true, 'message' => 'ROOM_ASSIGN' === $source ? 'Assegnazioni camere salvate. Aggiornamento del foglio accodato.' : count( $changes ) . ' celle sincronizzate.' );
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
