<?php

defined( 'ABSPATH' ) || exit;

final class MI_Registration_Service {
	public static function public_event( $event_id ) {
		$event = get_post( $event_id );
		if ( ! $event || MI_Event_Post_Type::EVENT_TYPE !== $event->post_type || 'publish' !== $event->post_status ) {
			return new WP_Error( 'mi_event_not_found', 'Evento non disponibile.', array( 'status' => 404 ) );
		}

		$ticket_types = get_post_meta( $event_id, '_mi_ticket_types', true );
		if ( ! is_array( $ticket_types ) || empty( $ticket_types ) ) {
			return new WP_Error( 'mi_event_invalid', 'Configurazione evento incompleta.', array( 'status' => 409 ) );
		}

		$activity_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		$activity = get_post( $activity_id );
		return array(
			'id'               => $event_id,
			'title'            => get_the_title( $event_id ),
			'description'      => wp_strip_all_tags( $event->post_content ),
			'activity'         => $activity ? $activity->post_title : '',
			'activity_logo'    => $activity ? get_the_post_thumbnail_url( $activity, 'medium' ) : '',
			'capacity'         => max( 1, absint( get_post_meta( $event_id, '_mi_capacity', true ) ) ),
			'waitlist_enabled' => '1' === get_post_meta( $event_id, '_mi_waitlist_enabled', true ),
			'opens_at'         => (string) get_post_meta( $event_id, '_mi_registration_opens_at', true ),
			'closes_at'        => (string) get_post_meta( $event_id, '_mi_registration_closes_at', true ),
			'pricing_mode'     => (string) get_post_meta( $event_id, '_mi_pricing_mode', true ),
			'ticket_types'     => array_values( $ticket_types ),
		);
	}

	public static function registration_state( $event ) {
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
		$participants = self::validate_participants( $payload['participants'] ?? array(), $selection['quantity'] );
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
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT order_code, status FROM {$registrations_table} WHERE event_id = %d AND idempotency_key = %s", $event_id, $idempotency_key ), ARRAY_A );
		if ( $existing ) {
			return array( 'order_code' => $existing['order_code'], 'status' => $existing['status'], 'replayed' => true );
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
					'idempotency_key' => $idempotency_key,
					'created_at'      => $now,
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
			if ( ! $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				$existing = $wpdb->get_row( $wpdb->prepare( "SELECT order_code, status FROM {$registrations_table} WHERE event_id = %d AND idempotency_key = %s", $event_id, $idempotency_key ), ARRAY_A );
				if ( $existing ) {
					return array( 'order_code' => $existing['order_code'], 'status' => $existing['status'], 'replayed' => true );
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
				if ( false === $wpdb->insert( $participants_table, array( 'registration_id' => $registration_id, 'first_name' => $participant['first_name'], 'last_name' => $participant['last_name'] ), array( '%d', '%s', '%s' ) ) ) {
					throw new RuntimeException( 'Partecipante non salvato.' );
				}
			}
			$counter_updated = $wpdb->query( $wpdb->prepare( "UPDATE {$counters_table} SET {$counter_field} = {$counter_field} + %d, updated_at = %s WHERE event_id = %d", $selection['quantity'], $now, $event_id ) );
			if ( 1 !== $counter_updated ) {
				throw new RuntimeException( 'Contatore non aggiornato.' );
			}
			$payload_json = wp_json_encode( array( 'event_title' => $event['title'], 'order_code' => $order_code, 'status' => $status, 'quantity' => $selection['quantity'], 'total_cents' => $selection['total_cents'] ) );
			if ( false === $wpdb->insert( $outbox_table, array( 'registration_id' => $registration_id, 'recipient' => $buyer['email'], 'template_type' => 'REGISTRATION_CONFIRMATION', 'payload_json' => $payload_json, 'status' => 'PREVIEW', 'created_at' => $now ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) ) ) {
				throw new RuntimeException( 'Outbox non salvata.' );
			}
			$wpdb->query( 'COMMIT' );
			return array( 'order_code' => $order_code, 'status' => $status, 'replayed' => false );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'mi_storage_error', 'Non è stato possibile completare l’iscrizione.', array( 'status' => 500 ) );
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

	private static function validate_participants( $raw_participants, $expected ) {
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
			$participants[] = compact( 'first_name', 'last_name' );
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
