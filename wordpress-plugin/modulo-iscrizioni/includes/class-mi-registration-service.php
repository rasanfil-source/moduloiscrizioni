<?php

defined( 'ABSPATH' ) || exit;

final class MI_Registration_Service {
	public static function public_event( $event_id, $allow_unpublished = false ) {
		$event = get_post( $event_id );
		$allowed_status = $allow_unpublished ? array( 'publish', 'draft', 'private' ) : array( 'publish' );
		if ( ! $event || MI_Event_Post_Type::EVENT_TYPE !== $event->post_type || ! in_array( $event->post_status, $allowed_status, true ) ) {
			return new WP_Error( 'mi_event_not_found', 'Evento non disponibile.', array( 'status' => 404 ) );
		}

		$ticket_types = get_post_meta( $event_id, '_mi_ticket_types', true );
		if ( ! is_array( $ticket_types ) || empty( $ticket_types ) ) {
			return new WP_Error( 'mi_event_invalid', 'Configurazione evento incompleta.', array( 'status' => 409 ) );
		}

		$activity_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		$activity = get_post( $activity_id );
		$field_configuration = MI_Field_Schema::event_configuration( $event_id );
		$activity_thumbnail_id = $activity ? get_post_thumbnail_id( $activity ) : 0;
		$event_thumbnail_id = get_post_thumbnail_id( $event_id );
		$public_event = array(
			'id'               => $event_id,
			'title'            => get_the_title( $event_id ),
			'description'      => wp_strip_all_tags( $event->post_content ),
			'activity'         => $activity ? $activity->post_title : '',
			'activity_logo'    => $activity ? get_the_post_thumbnail_url( $activity, 'medium' ) : '',
			'activity_logo_alt'=> $activity_thumbnail_id ? (string) get_post_meta( $activity_thumbnail_id, '_wp_attachment_image_alt', true ) : '',
			'accent_color'     => $activity ? ( sanitize_hex_color( get_post_meta( $activity_id, '_mi_accent_color', true ) ) ?: '#c43b2f' ) : '#c43b2f',
			'cover_image'      => $event_thumbnail_id ? get_the_post_thumbnail_url( $event_id, 'large' ) : '',
			'cover_image_alt'  => $event_thumbnail_id ? (string) get_post_meta( $event_thumbnail_id, '_wp_attachment_image_alt', true ) : '',
			'event_starts_at'  => (string) get_post_meta( $event_id, '_mi_event_starts_at', true ),
			'event_location'   => (string) get_post_meta( $event_id, '_mi_event_location', true ),
			'capacity'         => max( 1, absint( get_post_meta( $event_id, '_mi_capacity', true ) ) ),
			'waitlist_enabled' => '1' === get_post_meta( $event_id, '_mi_waitlist_enabled', true ),
			'opens_at'         => (string) get_post_meta( $event_id, '_mi_registration_opens_at', true ),
			'closes_at'        => (string) get_post_meta( $event_id, '_mi_registration_closes_at', true ),
			'pricing_mode'     => (string) get_post_meta( $event_id, '_mi_pricing_mode', true ),
			'economic_mode'    => (string) ( get_post_meta( $event_id, '_mi_economic_mode', true ) ?: 'REGISTRATION_ONLY' ),
			'deposit_percentage' => min( 99, max( 1, absint( get_post_meta( $event_id, '_mi_deposit_percentage', true ) ?: 30 ) ) ),
			'payment_methods'  => (array) get_post_meta( $event_id, '_mi_payment_methods', true ),
			'ticket_types'     => array_values( $ticket_types ),
			'data_profile'     => $field_configuration['profile'],
			'participant_fields'=> MI_Field_Schema::public_fields( $field_configuration ),
		);
		$public_event['availability'] = self::availability( $public_event );
		return $public_event;
	}

	public static function registration_state( $event ) {
		$time_state = self::registration_time_state( $event );
		if ( 'OPEN' !== $time_state ) {
			return $time_state;
		}
		$availability = isset( $event['availability'] ) && is_array( $event['availability'] ) ? $event['availability'] : self::availability( $event );
		return $availability['full'] && empty( $event['waitlist_enabled'] ) ? 'SOLD_OUT' : 'OPEN';
	}

	public static function registration_time_state( $event ) {
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		$opens = self::local_datetime( $event['opens_at'] );
		$closes = self::local_datetime( $event['closes_at'] );
		if ( ! $opens || ! $closes || $closes <= $opens ) {
			return 'MISCONFIGURED';
		}
		if ( $now < $opens ) {
			return 'NOT_OPEN';
		}
		if ( $now > $closes ) {
			return 'CLOSED';
		}
		return 'OPEN';
	}

	public static function availability( $event ) {
		global $wpdb;
		$event_id = absint( $event['id'] ?? 0 );
		$capacity = max( 1, absint( $event['capacity'] ?? 1 ) );
		$confirmed = 0;
		$waitlisted = 0;
		if ( $event_id ) {
			$table = $wpdb->prefix . 'mi_event_counters';
			$counter = $wpdb->get_row( $wpdb->prepare( "SELECT confirmed_count, waitlisted_count FROM {$table} WHERE event_id = %d", $event_id ), ARRAY_A );
			$confirmed = max( 0, (int) ( $counter['confirmed_count'] ?? 0 ) );
			$waitlisted = max( 0, (int) ( $counter['waitlisted_count'] ?? 0 ) );
		}
		$remaining = max( 0, $capacity - $confirmed );
		return array( 'capacity' => $capacity, 'confirmed' => $confirmed, 'waitlisted' => $waitlisted, 'remaining' => $remaining, 'full' => 0 === $remaining );
	}

	public static function create( $event_id, $payload, $idempotency_key ) {
		global $wpdb;

		$event = self::public_event( $event_id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( 'OPEN' !== self::registration_state( $event ) ) {
			return new WP_Error( 'mi_registration_closed', 'Le iscrizioni non sono aperte.', array( 'status' => 409 ) );
		}

		if ( ! empty( $payload['website'] ) ) {
			return new WP_Error( 'mi_spam', 'Richiesta non valida.', array( 'status' => 400 ) );
		}
		$started_at = isset( $payload['started_at'] ) ? absint( $payload['started_at'] ) : 0;
		if ( ! $started_at || time() - $started_at < 2 || time() - $started_at > DAY_IN_SECONDS ) {
			return new WP_Error( 'mi_form_timing', 'Aggiorna la pagina e riprova.', array( 'status' => 400 ) );
		}

		$rate_key = 'mi_rate_' . hash( 'sha256', (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) . '|' . $event_id );
		$attempts = absint( get_transient( $rate_key ) );
		if ( $attempts >= 12 ) {
			return new WP_Error( 'mi_rate_limited', 'Troppi tentativi. Riprova più tardi.', array( 'status' => 429 ) );
		}
		set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );

		$selection = self::validate_selection( $event, $payload['tickets'] ?? array() );
		if ( is_wp_error( $selection ) ) {
			return $selection;
		}
		$participants = self::validate_participants( $payload['participants'] ?? array(), $selection['quantity'], $event['participant_fields'] );
		if ( is_wp_error( $participants ) ) {
			return $participants;
		}
		$buyer = self::validate_buyer( $payload['buyer'] ?? array() );
		if ( is_wp_error( $buyer ) ) {
			return $buyer;
		}
		if ( empty( $payload['privacy_accepted'] ) ) {
			return new WP_Error( 'mi_privacy_required', 'È necessario accettare l’informativa privacy.', array( 'status' => 400 ) );
		}

		$idempotency_key = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $idempotency_key );
		if ( strlen( $idempotency_key ) < 16 || strlen( $idempotency_key ) > 64 ) {
			return new WP_Error( 'mi_idempotency', 'Identificativo richiesta non valido.', array( 'status' => 400 ) );
		}

		$registrations_table = $wpdb->prefix . 'mi_registrations';
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, order_code, status, workspace_status, economic_mode, total_cents, initial_due_cents, balance_cents, payment_methods_json FROM {$registrations_table} WHERE event_id = %d AND idempotency_key = %s", $event_id, $idempotency_key ), ARRAY_A );
		if ( $existing ) {
			$workspace_status = self::accoda_sincronizzazione_workspace( (int) $existing['id'], $existing['workspace_status'] );
			return array( 'order_code' => $existing['order_code'], 'status' => $existing['status'], 'workspace_status' => $workspace_status, 'economic_summary' => self::riepilogo_salvato( $existing ), 'replayed' => true );
		}

		$counters_table = $wpdb->prefix . 'mi_event_counters';
		$items_table = $wpdb->prefix . 'mi_registration_items';
		$participants_table = $wpdb->prefix . 'mi_participants';
		$outbox_table = $wpdb->prefix . 'mi_email_outbox';
		$now = current_time( 'mysql', true );
		$order_code = self::generate_order_code();

		$wpdb->query( 'START TRANSACTION' );
		try {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$counters_table} (event_id, confirmed_count, waitlisted_count, updated_at) VALUES (%d, 0, 0, %s)", $event_id, $now ) );
			$counter = $wpdb->get_row( $wpdb->prepare( "SELECT confirmed_count, waitlisted_count FROM {$counters_table} WHERE event_id = %d FOR UPDATE", $event_id ), ARRAY_A );
			if ( ! $counter ) {
				throw new RuntimeException( 'Contatore non disponibile.' );
			}
			if ( 'OPEN' !== self::registration_time_state( $event ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'mi_registration_closed', 'Le iscrizioni sono state chiuse. Aggiorna la pagina.', array( 'status' => 409 ) );
			}
			$remaining = max( 0, (int) $event['capacity'] - (int) $counter['confirmed_count'] );
			if ( $selection['quantity'] <= $remaining ) {
				$status = 'CONFIRMED';
				$counter_field = 'confirmed_count';
			} elseif ( $event['waitlist_enabled'] ) {
				$status = 'WAITLISTED';
				$counter_field = 'waitlisted_count';
			} else {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'mi_sold_out', 'Posti esauriti.', array( 'status' => 409 ) );
			}
			$economic_summary = self::riepilogo_economico( $event, $selection['total_cents'], $status );

			$inserted = $wpdb->insert(
				$registrations_table,
				array(
					'order_code'      => $order_code,
					'event_id'        => $event_id,
					'status'          => $status,
					'buyer_first_name'=> $buyer['first_name'],
					'buyer_last_name' => $buyer['last_name'],
					'buyer_email'     => $buyer['email'],
					'buyer_phone'     => $buyer['phone'],
					'total_qty'       => $selection['quantity'],
					'economic_mode'   => $economic_summary['mode'],
					'total_cents'     => $economic_summary['total_cents'],
					'initial_due_cents'=> $economic_summary['initial_due_cents'],
					'balance_cents'   => $economic_summary['balance_cents'],
					'payment_methods_json' => wp_json_encode( $economic_summary['payment_methods'] ),
					'idempotency_key' => $idempotency_key,
					'created_at'      => $now,
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
			);
			if ( ! $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, order_code, status, workspace_status, economic_mode, total_cents, initial_due_cents, balance_cents, payment_methods_json FROM {$registrations_table} WHERE event_id = %d AND idempotency_key = %s", $event_id, $idempotency_key ), ARRAY_A );
				if ( $existing ) {
					$workspace_status = self::accoda_sincronizzazione_workspace( (int) $existing['id'], $existing['workspace_status'] );
					return array( 'order_code' => $existing['order_code'], 'status' => $existing['status'], 'workspace_status' => $workspace_status, 'economic_summary' => self::riepilogo_salvato( $existing ), 'replayed' => true );
				}
				throw new RuntimeException( 'Registrazione non salvata.' );
			}
			$registration_id = (int) $wpdb->insert_id;
			foreach ( $selection['items'] as $item ) {
				if ( false === $wpdb->insert( $items_table, array( 'registration_id' => $registration_id, 'ticket_type_code' => $item['code'], 'quantity' => $item['quantity'], 'unit_price_cents' => $item['unit_price_cents'] ), array( '%d', '%s', '%d', '%d' ) ) ) {
					throw new RuntimeException( 'Quota non salvata.' );
				}
			}
			foreach ( $participants as $participant ) {
				if ( false === $wpdb->insert( $participants_table, array( 'registration_id' => $registration_id, 'first_name' => $participant['first_name'], 'last_name' => $participant['last_name'], 'extra_json' => wp_json_encode( $participant['fields'] ) ), array( '%d', '%s', '%s', '%s' ) ) ) {
					throw new RuntimeException( 'Partecipante non salvato.' );
				}
			}
			$counter_updated = $wpdb->query( $wpdb->prepare( "UPDATE {$counters_table} SET {$counter_field} = {$counter_field} + %d, updated_at = %s WHERE event_id = %d", $selection['quantity'], $now, $event_id ) );
			if ( 1 !== $counter_updated ) {
				throw new RuntimeException( 'Contatore non aggiornato.' );
			}
			$email_values = array(
				'{{evento.titolo}}'           => $event['title'],
				'{{attivita.nome}}'           => $event['activity'],
				'{{ordine.codice}}'           => $order_code,
				'{{ordine.stato}}'            => 'CONFIRMED' === $status ? 'Confermata' : 'Lista d’attesa',
				'{{ordine.partecipanti}}'     => (string) $selection['quantity'],
				'{{referente.nome_completo}}' => $buyer['first_name'] . ' ' . $buyer['last_name'],
			);
			$payload_json = wp_json_encode( array( 'event_title' => $event['title'], 'order_code' => $order_code, 'status' => $status, 'quantity' => $selection['quantity'], 'total_cents' => $selection['total_cents'], 'economic_summary' => $economic_summary, 'email_preview' => MI_Modello_Email::crea_istantanea( $event_id, $email_values ) ) );
			if ( false === $wpdb->insert( $outbox_table, array( 'registration_id' => $registration_id, 'recipient' => $buyer['email'], 'template_type' => 'REGISTRATION_CONFIRMATION', 'payload_json' => $payload_json, 'status' => 'PREVIEW', 'created_at' => $now ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) ) ) {
				throw new RuntimeException( 'Outbox non salvata.' );
			}
			$wpdb->query( 'COMMIT' );
			$workspace_status = self::accoda_sincronizzazione_workspace( $registration_id, 'PENDING' );
			return array( 'order_code' => $order_code, 'status' => $status, 'workspace_status' => $workspace_status, 'economic_summary' => $economic_summary, 'replayed' => false );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'mi_storage_error', 'Non è stato possibile completare l’iscrizione.', array( 'status' => 500 ) );
		}
	}

	public static function riepilogo_economico( $event, $total_cents, $status ) {
		$total_cents = max( 0, (int) $total_cents );
		$mode = in_array( $event['economic_mode'] ?? '', array( 'REGISTRATION_ONLY', 'PRICE_ONLY', 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ? $event['economic_mode'] : 'REGISTRATION_ONLY';
		$initial_due = 0;
		$balance = 0;
		if ( 'CONFIRMED' === $status && 'FULL_PAYMENT' === $mode ) {
			$initial_due = $total_cents;
		} elseif ( 'CONFIRMED' === $status && 'DEPOSIT_BALANCE' === $mode ) {
			$percentage = min( 99, max( 1, absint( $event['deposit_percentage'] ?? 30 ) ) );
			$initial_due = (int) round( $total_cents * $percentage / 100 );
			$balance = max( 0, $total_cents - $initial_due );
		}
		return array( 'mode' => $mode, 'total_cents' => $total_cents, 'initial_due_cents' => $initial_due, 'balance_cents' => $balance, 'payment_methods' => in_array( $mode, array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ? array_values( (array) ( $event['payment_methods'] ?? array() ) ) : array() );
	}

	private static function riepilogo_salvato( $registration ) {
		$payment_methods = json_decode( (string) ( $registration['payment_methods_json'] ?? '' ), true );
		return array( 'mode' => (string) $registration['economic_mode'], 'total_cents' => (int) $registration['total_cents'], 'initial_due_cents' => (int) $registration['initial_due_cents'], 'balance_cents' => (int) $registration['balance_cents'], 'payment_methods' => is_array( $payment_methods ) ? $payment_methods : array() );
	}

	public static function sync_workspace( $registration_id ) {
		global $wpdb;
		$registration_id = absint( $registration_id );
		$registrations_table = $wpdb->prefix . 'mi_registrations';
		$items_table = $wpdb->prefix . 'mi_registration_items';
		$participants_table = $wpdb->prefix . 'mi_participants';
		$registration = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$registrations_table} WHERE id = %d", $registration_id ), ARRAY_A );
		if ( ! $registration ) {
			return 'UNAVAILABLE';
		}
		if ( 'SYNCED' === $registration['workspace_status'] ) {
			return 'SYNCED';
		}
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT quantity, unit_price_cents FROM {$items_table} WHERE registration_id = %d ORDER BY id", $registration_id ), ARRAY_A );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT first_name, last_name, extra_json FROM {$participants_table} WHERE registration_id = %d ORDER BY id", $registration_id ), ARRAY_A );
		$participants = array_map(
			static function ( $row ) {
				$fields = json_decode( (string) $row['extra_json'], true );
				return array(
					'first_name' => $row['first_name'],
					'last_name'  => $row['last_name'],
					'fields'     => is_array( $fields ) ? $fields : array(),
				);
			},
			$rows
		);
		$total_cents = 0;
		foreach ( $items as $item ) {
			$total_cents += (int) $item['quantity'] * (int) $item['unit_price_cents'];
		}
		$result = MI_Workspace_Client::request(
			'APPEND_REGISTRATION',
			array(
				'order_code'     => $registration['order_code'],
				'event_id'       => (string) $registration['event_id'],
				'idempotency_key'=> $registration['idempotency_key'],
				'status'         => $registration['status'],
				'buyer'          => array(
					'first_name' => $registration['buyer_first_name'],
					'last_name'  => $registration['buyer_last_name'],
					'email'      => $registration['buyer_email'],
					'phone'      => $registration['buyer_phone'],
				),
				'participants'   => $participants,
				'total_cents'    => $total_cents,
				'economic_mode'  => (string) $registration['economic_mode'],
				'initial_due_cents' => (int) $registration['initial_due_cents'],
				'balance_cents'  => (int) $registration['balance_cents'],
				'payment_methods'=> (array) json_decode( (string) $registration['payment_methods_json'], true ),
			)
		);
		if ( is_wp_error( $result ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$registrations_table} SET workspace_status = 'PENDING', workspace_attempts = workspace_attempts + 1, workspace_last_error = %s WHERE id = %d", sanitize_key( $result->get_error_code() ), $registration_id ) );
			return 'PENDING';
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$registrations_table} SET workspace_status = 'SYNCED', workspace_attempts = workspace_attempts + 1, workspace_last_error = NULL, workspace_synced_at = %s WHERE id = %d", current_time( 'mysql', true ), $registration_id ) );
		return 'SYNCED';
	}

	public static function sync_pending_workspace() {
		global $wpdb;
		$table = $wpdb->prefix . 'mi_registrations';
		$ids = $wpdb->get_col( "SELECT id FROM {$table} WHERE workspace_status = 'PENDING' AND workspace_attempts < 10 ORDER BY id LIMIT 10" );
		foreach ( $ids as $registration_id ) {
			self::sync_workspace_safely( (int) $registration_id );
		}
	}

	public static function sincronizza_iscrizione_workspace( $registration_id ) {
		return self::sync_workspace_safely( absint( $registration_id ) );
	}

	public static function accoda_iscrizione_workspace( $registration_id ) {
		global $wpdb;
		$registration_id = absint( $registration_id );
		$table = $wpdb->prefix . 'mi_registrations';
		$current_status = $wpdb->get_var( $wpdb->prepare( "SELECT workspace_status FROM {$table} WHERE id = %d", $registration_id ) );
		if ( ! $current_status ) {
			return 'UNAVAILABLE';
		}
		return self::accoda_sincronizzazione_workspace( $registration_id, $current_status );
	}

	private static function accoda_sincronizzazione_workspace( $registration_id, $current_status ) {
		if ( 'SYNCED' === $current_status ) {
			return 'SYNCED';
		}
		$registration_id = absint( $registration_id );
		$args = array( $registration_id );
		if ( $registration_id && ! wp_next_scheduled( 'mi_sync_workspace_registration', $args ) ) {
			wp_schedule_single_event( time() + 1, 'mi_sync_workspace_registration', $args );
		}
		return 'PENDING';
	}

	private static function sync_workspace_safely( $registration_id ) {
		try {
			return self::sync_workspace( $registration_id );
		} catch ( Throwable $sync_error ) {
			return 'PENDING';
		}
	}

	private static function validate_selection( $event, $raw_tickets ) {
		$allowed = array();
		foreach ( $event['ticket_types'] as $ticket ) {
			$allowed[ $ticket['code'] ] = $ticket;
		}
		$items = array();
		$quantity = 0;
		$total = 0;
		foreach ( (array) $raw_tickets as $code => $raw_quantity ) {
			$code = sanitize_key( $code );
			if ( ! isset( $allowed[ $code ] ) ) {
				return new WP_Error( 'mi_ticket_invalid', 'Tipologia di iscrizione non valida.', array( 'status' => 400 ) );
			}
			$item_quantity = absint( $raw_quantity );
			if ( $item_quantity > (int) $allowed[ $code ]['max_per_order'] ) {
				return new WP_Error( 'mi_ticket_limit', 'Quantità superiore al limite per ordine.', array( 'status' => 400 ) );
			}
			if ( $item_quantity > 0 ) {
				$unit_price = 'CALCULATED' === $event['pricing_mode'] ? (int) $allowed[ $code ]['price_cents'] : 0;
				$items[] = array( 'code' => $code, 'quantity' => $item_quantity, 'unit_price_cents' => $unit_price );
				$quantity += $item_quantity;
				$total += $item_quantity * $unit_price;
			}
		}
		if ( $quantity < 1 || $quantity > 20 ) {
			return new WP_Error( 'mi_quantity', 'Seleziona da 1 a 20 partecipanti.', array( 'status' => 400 ) );
		}
		return array( 'items' => $items, 'quantity' => $quantity, 'total_cents' => $total );
	}

	private static function validate_participants( $raw_participants, $expected, $fields ) {
		if ( ! is_array( $raw_participants ) || count( $raw_participants ) !== $expected ) {
			return new WP_Error( 'mi_participants', 'Inserisci nome e cognome di ogni partecipante.', array( 'status' => 400 ) );
		}
		$participants = array();
		foreach ( $raw_participants as $raw ) {
			$first_name = sanitize_text_field( $raw['first_name'] ?? '' );
			$last_name = sanitize_text_field( $raw['last_name'] ?? '' );
			if ( ! $first_name || ! $last_name || strlen( $first_name ) > 80 || strlen( $last_name ) > 80 ) {
				return new WP_Error( 'mi_participant_invalid', 'Controlla i dati dei partecipanti.', array( 'status' => 400 ) );
			}
			$answers = MI_Field_Schema::validate_answers( $raw['fields'] ?? array(), $fields );
			if ( is_wp_error( $answers ) ) {
				return $answers;
			}
			$participants[] = array( 'first_name' => $first_name, 'last_name' => $last_name, 'fields' => $answers );
		}
		return $participants;
	}

	private static function validate_buyer( $raw ) {
		$buyer = array(
			'first_name' => sanitize_text_field( $raw['first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $raw['last_name'] ?? '' ),
			'email'      => sanitize_email( $raw['email'] ?? '' ),
			'phone'      => preg_replace( '/[^0-9+().\s-]/', '', (string) ( $raw['phone'] ?? '' ) ),
		);
		if ( ! $buyer['first_name'] || ! $buyer['last_name'] || ! is_email( $buyer['email'] ) || ! preg_match( '/^\+[1-9][0-9().\s-]{6,30}$/', $buyer['phone'] ) ) {
			return new WP_Error( 'mi_buyer_invalid', 'Controlla i dati del referente.', array( 'status' => 400 ) );
		}
		return $buyer;
	}

	private static function local_datetime( $value ) {
		try {
			return DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', (string) $value, wp_timezone() ) ?: null;
		} catch ( Exception $error ) {
			return null;
		}
	}

	private static function generate_order_code() {
		return 'MI-' . gmdate( 'ymd' ) . '-' . strtoupper( wp_generate_password( 8, false, false ) );
	}
}
