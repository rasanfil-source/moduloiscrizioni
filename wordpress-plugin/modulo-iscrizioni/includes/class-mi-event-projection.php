<?php
defined( 'ABSPATH' ) || exit;

/** Builds the complete, rebuildable event-sheet projection from canonical MySQL data. */
final class MI_Event_Projection {
	private static function check_database() {
		global $wpdb;
		if ( $wpdb->last_error ) throw new RuntimeException( 'Impossibile costruire la proiezione dell’evento.' );
	}

	/** Called while the event lease serializes canonical reads and all event writes. */
	private static function generation( $event_id, $fingerprint ) {
		$previous = get_post_meta( $event_id, '_mi_projection_version', true );
		if ( is_array( $previous ) && ( $previous['fingerprint'] ?? '' ) === $fingerprint ) return (string) $previous['generation'];
		$generation = (int) ( is_array( $previous ) ? ( $previous['generation'] ?? 0 ) : 0 ) + 1;
		if ( $generation < 1 || $generation > 9007199254740991 ) throw new RuntimeException( 'Generazione della proiezione non valida.' );
		$next = array( 'generation' => (string) $generation, 'fingerprint' => $fingerprint );
		update_post_meta( $event_id, '_mi_projection_version', $next );
		if ( get_post_meta( $event_id, '_mi_projection_version', true ) !== $next ) throw new RuntimeException( 'Versione della proiezione non registrata.' );
		return (string) $generation;
	}
	private static function decode_list( $value ) {
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private static function grouped( array $rows, $key ) {
		$result = array();
		foreach ( $rows as $row ) $result[(int) $row[$key]][] = $row;
		return $result;
	}

	private static function net_paid( array $payments ) {
		$total = 0;
		foreach ( $payments as $payment ) $total += in_array( strtoupper( (string) $payment['transaction_kind'] ), array( 'REFUND', 'STORNO', 'RIMBORSO' ), true ) ? -(int) $payment['amount_cents'] : (int) $payment['amount_cents'];
		return $total;
	}

	public static function snapshot( $event_id ) {
		global $wpdb;
		$event_id = absint( $event_id );
		if ( class_exists( 'MI_Event_Deletion' ) ) {
			$lease = MI_Event_Deletion::enter( $event_id );
			if ( is_wp_error( $lease ) ) throw new RuntimeException( $lease->get_error_message() );
		}
		wp_cache_delete( $event_id, 'post_meta' );
		$event = get_post( $event_id );
		if ( ! $event || MI_Event_Post_Type::EVENT_TYPE !== $event->post_type ) throw new InvalidArgumentException( 'Evento non valido.' );
		$registrations = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d ORDER BY id", $event_id ), ARRAY_A );
		self::check_database();
		$people = $wpdb->get_results( $wpdb->prepare( "SELECT p.* FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id=%d ORDER BY p.registration_id,p.id", $event_id ), ARRAY_A );
		self::check_database();
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT i.* FROM {$wpdb->prefix}mi_registration_items i JOIN {$wpdb->prefix}mi_registrations r ON r.id=i.registration_id WHERE r.event_id=%d ORDER BY i.registration_id,i.id", $event_id ), ARRAY_A );
		self::check_database();
		$payments = $wpdb->get_results( $wpdb->prepare( "SELECT p.* FROM {$wpdb->prefix}mi_payments p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id=%d ORDER BY p.registration_id,p.effective_at,p.id", $event_id ), ARRAY_A );
		self::check_database();
		$attendance_rows = $wpdb->get_results( $wpdb->prepare( "SELECT e.detail_json FROM {$wpdb->prefix}mi_registration_events e JOIN {$wpdb->prefix}mi_registrations r ON r.id=e.registration_id WHERE r.event_id=%d AND e.event_type='MANAGEMENT_attendance' ORDER BY e.id", $event_id ), ARRAY_A );
		self::check_database();
		$rooms = $wpdb->get_results( $wpdb->prepare( "SELECT code,name,capacity FROM {$wpdb->prefix}mi_rooms WHERE event_id=%d ORDER BY code", $event_id ), ARRAY_A );
		self::check_database();
		$event_revision = $wpdb->get_var( $wpdb->prepare( "SELECT revision FROM {$wpdb->prefix}mi_management_state WHERE event_id=%d", $event_id ) );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Impossibile costruire la proiezione dell’evento.' );

		$people_by_registration = self::grouped( $people, 'registration_id' );
		$items_by_registration = self::grouped( $items, 'registration_id' );
		$payments_by_registration = self::grouped( $payments, 'registration_id' );
		$attendance = array();
		foreach ( $attendance_rows as $entry ) {
			$detail = json_decode( (string) $entry['detail_json'], true );
			if ( isset( $detail['participant_id'], $detail['attendance'] ) ) $attendance[(int) $detail['participant_id']] = sanitize_key( $detail['attendance'] );
		}

		$registration_rows = array();
		$participant_rows = array();
		$payment_rows = array();
		$versions = array();
		foreach ( $registrations as $registration ) {
			$id = (int) $registration['id'];
			$registration_people = $people_by_registration[$id] ?? array();
			$registration_items = $items_by_registration[$id] ?? array();
			$registration_payments = $payments_by_registration[$id] ?? array();
			$individual = MI_Payment_People::calculate_for_display( $registration, $registration_people, $registration_items, $registration_payments );
			$summary = MI_Payment_People::summary( $individual );
			$individual_by_id = array_column( $individual['people'], null, 'id' );
			$net_paid = self::net_paid( $registration_payments );
			$versions[] = array( 'id' => $id, 'order_code' => $registration['order_code'], 'revision' => (string) $registration['workspace_revision'] );
			$registration_rows[] = array(
				'codice_ordine' => $registration['order_code'], 'id_evento' => (string) $event_id, 'stato' => $registration['status'],
				'nome_referente' => $registration['buyer_first_name'], 'cognome_referente' => $registration['buyer_last_name'],
				'email_referente' => $registration['buyer_email'], 'telefono_referente' => $registration['buyer_phone'],
				'richieste_particolari' => (string) ( $registration['special_requests'] ?? '' ),
				'numero_partecipanti' => (int) $registration['total_qty'], 'totale_centesimi' => $summary['known'] ? $summary['total'] : (int) $registration['total_cents'],
				'modalita_economica' => $registration['economic_mode'], 'primo_versamento_centesimi' => (int) $registration['initial_due_cents'],
				'saldo_centesimi' => $summary['known'] ? $summary['balance'] : max( 0, (int) $registration['total_cents'] - $net_paid ),
				'versato_centesimi' => $summary['known'] ? $summary['paid'] : max( 0, $net_paid ),
				'opzioni_ordine_json' => (string) ( $registration['order_options_json'] ?? '[]' ),
				'snapshot_json' => (string) ( $registration['snapshot_json'] ?? '{}' ),
				'workspace_revision' => (string) $registration['workspace_revision'], 'replica_completa_revision' => (string) $registration['workspace_revision'],
			);
			foreach ( $registration_people as $number => $person ) {
				$fields = self::decode_list( $person['extra_json'] ?? '' );
				if ( ! empty( $person['room_code'] ) ) $fields['room'] = (string) $person['room_code'];
				if ( isset( $attendance[(int) $person['id']] ) ) $fields['attendance'] = strtoupper( $attendance[(int) $person['id']] );
				$economic = $individual_by_id[(int) $person['id']] ?? array();
				$participant_rows[] = array(
					'id_partecipante' => (int) $person['id'], 'codice_ordine' => $registration['order_code'], 'numero_partecipante' => $number + 1,
					'tipo_biglietto' => $person['ticket_type_code'], 'indice_biglietto' => (int) $person['ticket_index'],
					'nome' => $person['first_name'], 'cognome' => $person['last_name'], 'dati_aggiuntivi_json' => wp_json_encode( $fields ),
					'opzioni_json' => (string) ( $person['options_json'] ?? '[]' ), 'stato_partecipante' => $person['status'] ?: 'ACTIVE',
					'totale_centesimi' => $summary['known'] ? max( 0, (int) ( $economic['total'] ?? 0 ) ) : '',
					'versato_centesimi' => $summary['known'] ? max( 0, (int) ( $economic['paid'] ?? 0 ) ) : '',
					'saldo_centesimi' => $summary['known'] ? max( 0, (int) ( $economic['balance'] ?? 0 ) ) : '',
				);
			}
			foreach ( $registration_payments as $payment ) {
				$payment_rows[] = array(
					'id_pagamento' => (string) $payment['id'], 'codice_ordine' => $registration['order_code'], 'data_effettiva' => (string) $payment['effective_at'],
					'tipo_movimento' => 'REFUND' === strtoupper( (string) $payment['transaction_kind'] ) ? 'RIMBORSO' : 'INCASSO',
					'importo_centesimi' => (int) $payment['amount_cents'],
					'fonte_pagamento' => array( 'BANK_TRANSFER' => 'BONIFICO', 'CARD' => 'CARTA', 'CASH' => 'CONTANTE' )[ strtoupper( (string) $payment['payment_source'] ) ] ?? strtoupper( (string) $payment['payment_source'] ),
					'riferimento_esterno' => (string) $payment['external_reference'], 'etichetta_operatore' => (string) $payment['operator_label'], 'nota_amministrativa' => (string) $payment['administrative_note'],
				);
			}
		}

		$schema = MI_Field_Schema::workspace_event_schema( $event_id );
		$projection = array(
			'event' => array( 'id_evento' => (string) $event_id, 'titolo' => $event->post_title, 'modalita_prezzo' => $schema['pricing'], 'profilo_operativo' => MI_Field_Schema::resolved_operational_profile( $event_id ), 'servizi_json' => wp_json_encode( $schema['options'] ), 'domande_json' => wp_json_encode( $schema['fields'] ), 'schema_vista_json' => wp_json_encode( $schema ) ),
			'registrations' => $registration_rows, 'participants' => $participant_rows, 'payments' => $payment_rows, 'rooms' => $rooms,
		);
		$fingerprint = hash( 'sha256', MI_Workspace_Client::stable_json( array( $projection, $versions, (string) $event_revision ) ) );
		$rows = array_map( static function ( $row ) { return array( 'id' => (int) $row['id'], 'order_code' => $row['order_code'], 'workspace_revision' => (string) $row['workspace_revision'], 'workspace_status' => $row['workspace_status'] ); }, $registrations );
		return array( 'projection' => $projection, 'versions' => $versions, 'rows' => $rows, 'revision' => (string) ( $event_revision ?? '0' ), 'schema' => $schema, 'profile' => $projection['event']['profilo_operativo'], 'rooms' => $rooms, 'fingerprint' => $fingerprint, 'generation' => self::generation( $event_id, $fingerprint ) );
	}

	public static function request_payload( $event_id, $background = false, $snapshot = null ) {
		$snapshot = is_array( $snapshot ) ? $snapshot : self::snapshot( $event_id );
		$transfer = self::encoded_snapshot( $snapshot );
		$payload = array(
			'event_id' => (string) absint( $event_id ), 'sheet_id' => (string) get_post_meta( $event_id, '_mi_operational_sheet_id', true ),
			'projection_hash' => $transfer['projection_hash'], 'fingerprint' => $snapshot['fingerprint'],
			'workspace_event_revision' => $snapshot['revision'], 'background' => (bool) $background,
			'projection_generation' => (string) ( $snapshot['generation'] ?? '' ),
		);
		if ( strlen( $transfer['projection_gzip'] ) > 1800000 ) $payload['projection_pull'] = true;
		else $payload['projection_gzip'] = $transfer['projection_gzip'];
		return array( 'snapshot' => $snapshot, 'payload' => $payload );
	}

	/** Large snapshots travel over the already authenticated Workspace → WordPress channel. */
	public static function encoded_snapshot( array $snapshot ) {
		$json = MI_Workspace_Client::stable_json( $snapshot['projection'] );
		if ( ! is_string( $json ) || strlen( $json ) > 12000000 ) throw new RuntimeException( 'La proiezione dell’evento supera il limite di elaborazione Workspace.' );
		$compressed = gzencode( $json, 6 );
		if ( false === $compressed ) throw new RuntimeException( 'Compressione della proiezione non riuscita.' );
		$encoded = base64_encode( $compressed );
		if ( strlen( $encoded ) > 8000000 ) throw new RuntimeException( 'La proiezione dell’evento supera il limite di elaborazione Workspace.' );
		return array( 'projection_gzip' => $encoded, 'projection_hash' => hash( 'sha256', $json ) );
	}
}
