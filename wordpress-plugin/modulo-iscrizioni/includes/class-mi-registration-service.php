<?php

defined( 'ABSPATH' ) || exit;

final class MI_Registration_Service {
	public static function ensure_published_revision( $event_id, $force = false ) {
		global $wpdb;
		$event_id = absint( $event_id );
		$event = get_post( $event_id );
		if ( ! $event || MI_Event_Post_Type::EVENT_TYPE !== $event->post_type || 'publish' !== $event->post_status ) {
			return null;
		}
		$table = $wpdb->prefix . 'mi_event_revisions';
		$config = self::public_event( $event_id, true );
		if ( is_wp_error( $config ) ) {
			return null;
		}
		unset( $config['availability'], $config['revision'] );
		$config['schema_version'] = MI_VERSION;
		$canonical = MI_Workspace_Client::stable_json( $config );
		$hash = hash( 'sha256', $canonical );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, revision_number, config_hash, config_json FROM {$table} WHERE event_id = %d AND config_hash = %s", $event_id, $hash ), ARRAY_A );
		if ( $existing ) {
			update_post_meta( $event_id, '_mi_published_revision_id', (int) $existing['id'] );
			delete_post_meta( $event_id, '_mi_needs_republish' );
			return $existing;
		}
		if ( ! $force && get_post_meta( $event_id, '_mi_published_revision_id', true ) ) {
			return self::published_revision_row( $event_id );
		}
		$inserted = false;
		$revision_number = 0;
		for ( $attempt = 0; $attempt < 3 && false === $inserted; $attempt++ ) {
			$revision_number = 1 + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(revision_number), 0) FROM {$table} WHERE event_id = %d", $event_id ) );
			$inserted = $wpdb->insert( $table, array( 'event_id' => $event_id, 'revision_number' => $revision_number, 'config_hash' => $hash, 'config_json' => $canonical, 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%s', '%s', '%s' ) );
			if ( false === $inserted ) {
				// Un salvataggio concorrente può aver creato lo stesso hash o occupato il numero di revisione.
				$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, revision_number, config_hash, config_json FROM {$table} WHERE event_id = %d AND config_hash = %s", $event_id, $hash ), ARRAY_A );
				if ( $existing ) {
					update_post_meta( $event_id, '_mi_published_revision_id', (int) $existing['id'] );
					delete_post_meta( $event_id, '_mi_needs_republish' );
					return $existing;
				}
			}
		}
		if ( false === $inserted ) {
			return null;
		}
		$revision = array( 'id' => (int) $wpdb->insert_id, 'revision_number' => $revision_number, 'config_hash' => $hash, 'config_json' => $canonical );
		update_post_meta( $event_id, '_mi_published_revision_id', $revision['id'] );
		delete_post_meta( $event_id, '_mi_needs_republish' );
		return $revision;
	}

	private static function published_revision_row( $event_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mi_event_revisions';
		$revision_id = absint( get_post_meta( $event_id, '_mi_published_revision_id', true ) );
		if ( $revision_id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, revision_number, config_hash, config_json FROM {$table} WHERE id = %d AND event_id = %d", $revision_id, $event_id ), ARRAY_A );
			if ( $row ) {
				return $row;
			}
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, revision_number, config_hash, config_json FROM {$table} WHERE event_id = %d ORDER BY revision_number DESC LIMIT 1", $event_id ), ARRAY_A );
	}

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
		$group_cover_id = $activity ? absint( get_post_meta( $activity_id, '_mi_group_cover_image_id', true ) ) : 0;
		$resolved_cover_id = $event_thumbnail_id ?: ( $group_cover_id ?: $activity_thumbnail_id );
		$external_group_logo = $activity ? esc_url_raw( get_post_meta( $activity_id, '_mi_group_logo_url', true ), array( 'https' ) ) : '';
		$external_group_cover = $activity ? esc_url_raw( get_post_meta( $activity_id, '_mi_group_cover_image_url', true ), array( 'https' ) ) : '';
		$legacy_activity_color = $activity ? sanitize_hex_color( get_post_meta( $activity_id, '_mi_accent_color', true ) ) : '';
		$activity_primary_color = $activity ? sanitize_hex_color( get_post_meta( $activity_id, '_mi_primary_color', true ) ) : '';
		$activity_secondary_color = $activity ? sanitize_hex_color( get_post_meta( $activity_id, '_mi_secondary_color', true ) ) : '';
		$activity_primary_color = $activity_primary_color ?: ( $legacy_activity_color ?: '#151b38' );
		$activity_secondary_color = $activity_secondary_color ?: '#337ab7';
		$public_event = array(
			'id'               => $event_id,
			'title'            => get_the_title( $event_id ),
			'description'      => mb_substr( wp_strip_all_tags( $event->post_content ), 0, 5000 ),
			'activity'         => $activity ? $activity->post_title : '',
			'activity_logo'    => $activity ? ( get_the_post_thumbnail_url( $activity, 'medium' ) ?: $external_group_logo ) : '',
			'activity_logo_alt'=> $activity_thumbnail_id ? (string) get_post_meta( $activity_thumbnail_id, '_wp_attachment_image_alt', true ) : '',
			'accent_color'     => $activity_primary_color,
			'resolved_branding'=> array(
				'primary_color'   => $activity_primary_color,
				'secondary_color' => $activity_secondary_color,
			),
			'cover_image'      => $resolved_cover_id ? (string) wp_get_attachment_image_url( $resolved_cover_id, 'large' ) : ( $external_group_cover ?: $external_group_logo ),
			'cover_image_alt'  => $resolved_cover_id ? (string) get_post_meta( $resolved_cover_id, '_wp_attachment_image_alt', true ) : '',
			'event_starts_at'  => (string) get_post_meta( $event_id, '_mi_event_starts_at', true ),
			'event_location'   => (string) get_post_meta( $event_id, '_mi_event_location', true ),
			'capacity'         => max( 1, absint( get_post_meta( $event_id, '_mi_capacity', true ) ) ),
			'waitlist_enabled' => '1' === get_post_meta( $event_id, '_mi_waitlist_enabled', true ),
			'opens_at'         => (string) get_post_meta( $event_id, '_mi_registration_opens_at', true ),
			'closes_at'        => (string) get_post_meta( $event_id, '_mi_registration_closes_at', true ),
			'pricing_mode'     => (string) get_post_meta( $event_id, '_mi_pricing_mode', true ),
			'fixed_price_cents'=> max( 0, (int) get_post_meta( $event_id, '_mi_fixed_price_cents', true ) ),
			'economic_mode'    => (string) ( get_post_meta( $event_id, '_mi_economic_mode', true ) ?: 'REGISTRATION_ONLY' ),
			'operational_profile' => MI_Field_Schema::sanitize_operational_profile( get_post_meta( $event_id, '_mi_operational_profile', true ) ),
			'deposit_percentage' => min( 99, max( 1, absint( get_post_meta( $event_id, '_mi_deposit_percentage', true ) ?: 30 ) ) ),
			'deposit_mode'       => 'FIXED' === strtoupper( (string) get_post_meta( $event_id, '_mi_deposit_mode', true ) ) ? 'FIXED' : 'PERCENTAGE',
			'deposit_fixed_cents'=> max( 0, (int) get_post_meta( $event_id, '_mi_deposit_fixed_cents', true ) ),
			'payment_methods'  => (array) get_post_meta( $event_id, '_mi_payment_methods', true ),
			'identifier_display' => in_array( strtoupper( (string) get_post_meta( $event_id, '_mi_identifier_display', true ) ), array( 'NONE', 'TEXT', 'QR', 'BARCODE' ), true ) ? strtoupper( (string) get_post_meta( $event_id, '_mi_identifier_display', true ) ) : 'TEXT',
			'ticket_types'     => array_values( $ticket_types ),
			'options'          => array_values( array_filter( (array) get_post_meta( $event_id, '_mi_options', true ), 'is_array' ) ),
			'data_profile'     => $field_configuration['profile'],
			'participant_fields'=> array_merge( MI_Field_Schema::public_fields( $field_configuration ), MI_Field_Schema::sanitize_custom_fields( get_post_meta( $event_id, '_mi_custom_participant_fields', true ) ) ),
			'participant_extra_scope' => 'ALL' === strtoupper( (string) get_post_meta( $event_id, '_mi_participant_extra_scope', true ) ) ? 'ALL' : 'ONE',
			'special_requests_enabled' => '1' === get_post_meta( $event_id, '_mi_special_requests_enabled', true ),
			'payment_deadline_at'=> (string) get_post_meta( $event_id, '_mi_payment_deadline_at', true ),
			'reservation_minutes'=> min( 10080, absint( get_post_meta( $event_id, '_mi_reservation_minutes', true ) ) ),
			'privacy_url'      => get_privacy_policy_url(),
			'privacy_policy_version' => (string) get_post_meta( $event_id, '_mi_privacy_policy_version', true ),
			'privacy_consent_id' => (string) get_post_meta( $event_id, '_mi_privacy_consent_id', true ),
			'marketing_enabled' => '1' === get_post_meta( $event_id, '_mi_marketing_enabled', true ),
			'marketing_consent_id' => (string) get_post_meta( $event_id, '_mi_marketing_consent_id', true ),
		);
		if ( ! $allow_unpublished ) {
			$revision = self::published_revision_row( $event_id );
			if ( ! $revision ) {
				$revision = self::ensure_published_revision( $event_id, true );
			}
			if ( ! $revision ) {
				return new WP_Error( 'mi_event_revision_unavailable', 'La revisione pubblicata non è disponibile. Riprova più tardi.', array( 'status' => 503 ) );
			}
			$revision_config = $revision ? json_decode( (string) $revision['config_json'], true ) : null;
			if ( is_array( $revision_config ) ) {
				$public_event = $revision_config;
				$public_event['revision'] = array( 'id' => (int) $revision['id'], 'number' => (int) $revision['revision_number'], 'hash' => (string) $revision['config_hash'] );
			}
		}
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
		$ticket_availability = array();
		$any_ticket_available = false;
		if ( $event_id ) {
			$ticket_table = $wpdb->prefix . 'mi_ticket_counters';
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ticket_type_code, confirmed_count, waitlisted_count FROM {$ticket_table} WHERE event_id = %d", $event_id ), ARRAY_A );
			$indexed = array();
			foreach ( $rows as $row ) {
				$indexed[ $row['ticket_type_code'] ] = $row;
			}
			foreach ( (array) ( $event['ticket_types'] ?? array() ) as $ticket ) {
				$code = (string) $ticket['code'];
				$type_capacity = absint( $ticket['capacity'] ?? 0 );
				$type_confirmed = (int) ( $indexed[ $code ]['confirmed_count'] ?? 0 );
				$type_remaining = $type_capacity ? max( 0, $type_capacity - $type_confirmed ) : null;
				if ( null === $type_remaining || $type_remaining > 0 ) {
					$any_ticket_available = true;
				}
				$ticket_availability[ $code ] = array( 'capacity' => $type_capacity, 'confirmed' => $type_confirmed, 'waitlisted' => (int) ( $indexed[ $code ]['waitlisted_count'] ?? 0 ), 'remaining' => $type_remaining );
			}
		}
		return array( 'capacity' => $capacity, 'confirmed' => $confirmed, 'waitlisted' => $waitlisted, 'remaining' => $remaining, 'full' => 0 === $remaining || ( ! empty( $event['ticket_types'] ) && ! $any_ticket_available ), 'ticket_types' => $ticket_availability );
	}

	public static function create( $event_id, $payload, $idempotency_key, $allow_unpublished = false, $audit_actor = 'PUBLIC_FORM' ) {
		global $wpdb;
		$event_id = absint( $event_id );
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

		$event = self::public_event( $event_id, (bool) $allow_unpublished );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( ! $allow_unpublished && 'OPEN' !== self::registration_state( $event ) ) {
			return new WP_Error( 'mi_registration_closed', 'Le iscrizioni non sono aperte.', array( 'status' => 409 ) );
		}

		if ( ! empty( $payload['website'] ) ) {
			return new WP_Error( 'mi_spam', 'Richiesta non valida.', array( 'status' => 400 ) );
		}
		$started_at = isset( $payload['started_at'] ) ? absint( $payload['started_at'] ) : 0;
		if ( ! $started_at || time() - $started_at < 2 || time() - $started_at > DAY_IN_SECONDS ) {
			return new WP_Error( 'mi_form_timing', 'Aggiorna la pagina e riprova.', array( 'status' => 400 ) );
		}

		$selection = self::validate_selection( $event, $payload['tickets'] ?? array() );
		if ( is_wp_error( $selection ) ) {
			return $selection;
		}
		$order_options = self::validate_options( $payload['order_options'] ?? array(), $event['options'] ?? array(), 'ORDER' );
		if ( is_wp_error( $order_options ) ) {
			return $order_options;
		}
		$participants = self::validate_participants( $payload['participants'] ?? array(), $selection, $event['participant_fields'], $event['options'] ?? array(), $event['participant_extra_scope'] ?? 'ONE' );
		if ( is_wp_error( $participants ) ) {
			return $participants;
		}
		$buyer = self::validate_buyer( $payload['buyer'] ?? array() );
		if ( is_wp_error( $buyer ) ) {
			return $buyer;
		}
		$special_requests = ! empty( $event['special_requests_enabled'] ) ? sanitize_textarea_field( $payload['special_requests'] ?? '' ) : '';
		if ( strlen( $special_requests ) > 2000 ) return new WP_Error( 'mi_special_requests_invalid', 'Le richieste particolari sono troppo lunghe.', array( 'status' => 400 ) );
		if ( true !== ( $payload['privacy_accepted'] ?? false ) || empty( $event['privacy_url'] ) || empty( $event['privacy_policy_version'] ) || empty( $event['privacy_consent_id'] ) ) {
			return new WP_Error( 'mi_privacy_required', 'È necessario accettare l’informativa privacy.', array( 'status' => 400 ) );
		}
		if ( ! empty( $event['marketing_enabled'] ) && empty( $event['marketing_consent_id'] ) ) {
			return new WP_Error( 'mi_marketing_misconfigured', 'Il consenso marketing dell’evento non è configurato.', array( 'status' => 409 ) );
		}
		$marketing_accepted = ! empty( $event['marketing_enabled'] ) && true === ( $payload['marketing_accepted'] ?? false );
		if ( ! $allow_unpublished ) {
			$rate_limit = self::consume_registration_rate_limit( $event_id, $buyer['email'] );
			if ( is_wp_error( $rate_limit ) ) return $rate_limit;
		}

		$counters_table = $wpdb->prefix . 'mi_event_counters';
		$ticket_counters_table = $wpdb->prefix . 'mi_ticket_counters';
		$items_table = $wpdb->prefix . 'mi_registration_items';
		$participants_table = $wpdb->prefix . 'mi_participants';
		$payments_table = $wpdb->prefix . 'mi_payments';
		$outbox_table = $wpdb->prefix . 'mi_email_outbox';
		$now = current_time( 'mysql', true );
		$order_code = self::generate_order_code();
		$options_total = in_array( $event['pricing_mode'], array( 'FIXED', 'CALCULATED' ), true ) ? self::options_total( $order_options, $participants ) : 0;

		$wpdb->query( 'START TRANSACTION' );
		try {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$counters_table} (event_id, confirmed_count, waitlisted_count, updated_at) VALUES (%d, 0, 0, %s)", $event_id, $now ) );
			$counter = $wpdb->get_row( $wpdb->prepare( "SELECT confirmed_count, waitlisted_count FROM {$counters_table} WHERE event_id = %d FOR UPDATE", $event_id ), ARRAY_A );
			if ( ! $counter ) {
				throw new RuntimeException( 'Contatore non disponibile.' );
			}
			if ( ! $allow_unpublished && 'OPEN' !== self::registration_time_state( $event ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'mi_registration_closed', 'Le iscrizioni sono state chiuse. Aggiorna la pagina.', array( 'status' => 409 ) );
			}
			$ticket_counts = array();
			foreach ( $selection['items'] as $item ) {
				$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$ticket_counters_table} (event_id, ticket_type_code, confirmed_count, waitlisted_count, updated_at) VALUES (%d, %s, 0, 0, %s)", $event_id, $item['code'], $now ) );
			}
			$ticket_codes = array_column( $selection['items'], 'code' );
			sort( $ticket_codes, SORT_STRING );
			foreach ( $ticket_codes as $ticket_code ) {
				$ticket_counts[ $ticket_code ] = $wpdb->get_row( $wpdb->prepare( "SELECT confirmed_count, waitlisted_count FROM {$ticket_counters_table} WHERE event_id = %d AND ticket_type_code = %s FOR UPDATE", $event_id, $ticket_code ), ARRAY_A );
			}
			$remaining = max( 0, (int) $event['capacity'] - (int) $counter['confirmed_count'] );
			$type_capacity_available = true;
			foreach ( $selection['items'] as $item ) {
				$type_capacity = absint( $item['capacity'] ?? 0 );
				if ( $type_capacity && (int) ( $ticket_counts[ $item['code'] ]['confirmed_count'] ?? 0 ) + (int) $item['quantity'] > $type_capacity ) {
					$type_capacity_available = false;
					break;
				}
			}
			if ( $selection['quantity'] <= $remaining && $type_capacity_available ) {
				$status = in_array( $event['economic_mode'] ?? '', array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) && ( $selection['total_cents'] + $options_total ) > 0 ? 'PENDING_PAYMENT' : 'CONFIRMED';
				$counter_field = 'confirmed_count';
			} elseif ( $event['waitlist_enabled'] ) {
				$status = 'WAITLISTED';
				$counter_field = 'waitlisted_count';
			} else {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'mi_sold_out', 'Posti esauriti.', array( 'status' => 409 ) );
			}
			$economic_summary = self::riepilogo_economico( $event, $selection['total_cents'] + $options_total, $status );
			$expires_at = self::registration_expiry( $event, $status, $now );
			$revision = (array) ( $event['revision'] ?? array() );
			$accepted_at = current_time( 'mysql', true );
			$snapshot = self::build_order_snapshot( $event, $selection, $participants, $order_options, $buyer, $economic_summary, $status, $accepted_at, $marketing_accepted, $special_requests );
			$snapshot_json = wp_json_encode( $snapshot );
			if ( false === $snapshot_json || strlen( $snapshot_json ) > 45000 ) {
				throw new RuntimeException( 'Istantanea ordine non serializzabile.' );
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
					'special_requests'=> $special_requests,
					'total_qty'       => $selection['quantity'],
					'economic_mode'   => $economic_summary['mode'],
					'total_cents'     => $economic_summary['total_cents'],
					'initial_due_cents'=> $economic_summary['initial_due_cents'],
					'balance_cents'   => $economic_summary['balance_cents'],
					'payment_methods_json' => wp_json_encode( $economic_summary['payment_methods'] ),
					'order_options_json' => wp_json_encode( $order_options ),
					'event_revision_id' => absint( $revision['id'] ?? 0 ) ?: null,
					'event_revision_hash' => sanitize_text_field( $revision['hash'] ?? '' ),
					'snapshot_json'   => $snapshot_json,
					'privacy_consent_id' => sanitize_key( $event['privacy_consent_id'] ),
					'privacy_policy_version' => sanitize_text_field( $event['privacy_policy_version'] ),
					'privacy_accepted_at' => $accepted_at,
					'marketing_consent_id' => $marketing_accepted ? sanitize_key( $event['marketing_consent_id'] ) : null,
					'marketing_accepted_at' => $marketing_accepted ? $accepted_at : null,
					'expires_at'      => $expires_at,
					'payment_deadline_at' => $expires_at,
					'idempotency_key' => $idempotency_key,
					'created_at'      => $now,
				),
				null
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
				if ( false === $wpdb->insert( $items_table, array( 'registration_id' => $registration_id, 'ticket_type_code' => $item['code'], 'ticket_type_name' => $item['name'], 'quantity' => $item['quantity'], 'unit_price_cents' => $item['unit_price_cents'] ), array( '%d', '%s', '%s', '%d', '%d' ) ) ) {
					throw new RuntimeException( 'Quota non salvata.' );
				}
			}
			$participant_management = array();
			foreach ( $participants as $participant ) {
				$cancel_token = bin2hex( random_bytes( 32 ) );
				if ( false === $wpdb->insert( $participants_table, array( 'registration_id' => $registration_id, 'ticket_type_code' => $participant['ticket_type_code'], 'ticket_index' => $participant['ticket_index'], 'first_name' => $participant['first_name'], 'last_name' => $participant['last_name'], 'extra_json' => wp_json_encode( $participant['fields'] ), 'options_json' => wp_json_encode( $participant['options'] ), 'status' => 'ACTIVE', 'cancellation_token_hash' => hash( 'sha256', $cancel_token ) ), array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) ) ) {
					throw new RuntimeException( 'Partecipante non salvato.' );
				}
				$participant_management[] = array( 'name' => trim( $participant['first_name'] . ' ' . $participant['last_name'] ), 'url' => MI_Portal::participant_cancel_url( (int) $wpdb->insert_id, $cancel_token ) );
			}
			$counter_updated = $wpdb->query( $wpdb->prepare( "UPDATE {$counters_table} SET {$counter_field} = {$counter_field} + %d, updated_at = %s WHERE event_id = %d", $selection['quantity'], $now, $event_id ) );
			if ( 1 !== $counter_updated ) {
				throw new RuntimeException( 'Contatore non aggiornato.' );
			}
			foreach ( $selection['items'] as $item ) {
				$ticket_updated = $wpdb->query( $wpdb->prepare( "UPDATE {$ticket_counters_table} SET {$counter_field} = {$counter_field} + %d, updated_at = %s WHERE event_id = %d AND ticket_type_code = %s", $item['quantity'], $now, $event_id, $item['code'] ) );
				if ( 1 !== $ticket_updated ) {
					throw new RuntimeException( 'Contatore tipologia non aggiornato.' );
				}
			}
			if ( ! self::append_registration_event( $registration_id, 'CREATED', '', $status, sanitize_key( $audit_actor ), array( 'expires_at' => $expires_at ) ) ) {
				throw new RuntimeException( 'Evento di audit non salvato.' );
			}
			$email_items = array_merge( $selection['items'], $order_options );
			$email_values = MI_Modello_Email::valori_ordine( $event, $order_code, 'CONFIRMED' === $status ? 'Confermata' : ( 'PENDING_PAYMENT' === $status ? 'In attesa di pagamento' : 'Lista d’attesa' ), $selection['quantity'], $buyer['first_name'] . ' ' . $buyer['last_name'], $economic_summary, $email_items );
			$email_values['_participant_management'] = $participant_management;
			$email_snapshot = MI_Modello_Email::crea_istantanea( $event_id, $email_values );
			$email_snapshot['status_url'] = MI_Portal::status_url( $registration_id, $order_code, $buyer['email'] );
			$email_status = MI_Spedizione_Email::stato_nuova_email( $email_snapshot );
			$payload_json = wp_json_encode( array( 'event_title' => $event['title'], 'order_code' => $order_code, 'status' => $status, 'quantity' => $selection['quantity'], 'total_cents' => $economic_summary['total_cents'], 'economic_summary' => $economic_summary, 'email_preview' => $email_snapshot ) );
			if ( false === $wpdb->insert( $outbox_table, array( 'registration_id' => $registration_id, 'recipient' => $buyer['email'], 'template_type' => 'REGISTRATION_CONFIRMATION', 'payload_json' => $payload_json, 'status' => $email_status, 'created_at' => $now ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) ) ) {
				throw new RuntimeException( 'Outbox non salvata.' );
			}
			$wpdb->query( 'COMMIT' );
			if ( 'PENDING' === $email_status ) {
				MI_Spedizione_Email::pianifica_spedizione();
			}
			$workspace_status = self::accoda_sincronizzazione_workspace( $registration_id, 'PENDING' );
			return array( 'order_code' => $order_code, 'status' => $status, 'workspace_status' => $workspace_status, 'economic_summary' => $economic_summary, 'replayed' => false );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'mi_storage_error', 'Non è stato possibile completare l’iscrizione.', array( 'status' => 500 ) );
		}
	}

	/**
	 * Incrementa i limiti sotto un named lock MySQL: get/set_transient da soli
	 * non sono atomici. Il limite per IP è volutamente più ampio per non
	 * penalizzare gruppi collegati dalla stessa rete; quello per email frena
	 * invece le ripetizioni sulla stessa identità.
	 */
	private static function consume_registration_rate_limit( $event_id, $email ) {
		global $wpdb;
		$identities = array(
			array( 'ip|' . (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ), 60 ),
			array( 'email|' . strtolower( (string) $email ), 12 ),
		);
		foreach ( $identities as $identity ) {
			$hash = hash( 'sha256', $identity[0] . '|' . absint( $event_id ) );
			$transient_key = 'mi_rate_' . $hash;
			$lock_name = 'mi_rate_' . substr( $hash, 0, 48 );
			$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $lock_name ) );
			if ( 1 !== $locked ) return new WP_Error( 'mi_rate_busy', 'Servizio momentaneamente occupato. Riprova.', array( 'status' => 503 ) );
			try {
				$attempts = absint( get_transient( $transient_key ) );
				if ( $attempts >= (int) $identity[1] ) return new WP_Error( 'mi_rate_limited', 'Troppi tentativi. Riprova più tardi.', array( 'status' => 429 ) );
				set_transient( $transient_key, $attempts + 1, HOUR_IN_SECONDS );
			} finally {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
			}
		}
		return true;
	}

	public static function riepilogo_economico( $event, $total_cents, $status ) {
		$total_cents = max( 0, (int) $total_cents );
		$mode = in_array( $event['economic_mode'] ?? '', array( 'REGISTRATION_ONLY', 'PRICE_ONLY', 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ? $event['economic_mode'] : 'REGISTRATION_ONLY';
		$initial_due = 0;
		$balance = 0;
		if ( in_array( $status, array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) && 'FULL_PAYMENT' === $mode ) {
			$initial_due = $total_cents;
		} elseif ( in_array( $status, array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) && 'DEPOSIT_BALANCE' === $mode ) {
			if ( 'FIXED' === strtoupper( (string) ( $event['deposit_mode'] ?? '' ) ) ) {
				$initial_due = min( $total_cents, max( 0, (int) ( $event['deposit_fixed_cents'] ?? 0 ) ) );
			} else {
				$percentage = min( 99, max( 1, absint( $event['deposit_percentage'] ?? 30 ) ) );
				$initial_due = (int) round( $total_cents * $percentage / 100 );
			}
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
		$payments_table = $wpdb->prefix . 'mi_payments';
		$registration = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$registrations_table} WHERE id = %d", $registration_id ), ARRAY_A );
		if ( ! $registration ) {
			return 'UNAVAILABLE';
		}
		if ( 'SYNCED' === $registration['workspace_status'] ) {
			return 'SYNCED';
		}
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT ticket_type_code, ticket_type_name, quantity, unit_price_cents, options_json FROM {$items_table} WHERE registration_id = %d ORDER BY id", $registration_id ), ARRAY_A );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ticket_type_code, ticket_index, first_name, last_name, extra_json, options_json, status, cancelled_at FROM {$participants_table} WHERE registration_id = %d ORDER BY id", $registration_id ), ARRAY_A );
		$payments = $wpdb->get_results( $wpdb->prepare( "SELECT transaction_kind, installment_kind, effective_at, amount_cents, payment_source, external_reference, operator_label, administrative_note FROM {$payments_table} WHERE registration_id = %d ORDER BY effective_at, id", $registration_id ), ARRAY_A );
		$participants = array_map(
			static function ( $row ) {
				$fields = json_decode( (string) $row['extra_json'], true );
				$options = json_decode( (string) $row['options_json'], true );
				return array(
					'ticket_type_code' => $row['ticket_type_code'],
					'ticket_index' => (int) $row['ticket_index'],
					'first_name' => $row['first_name'],
					'last_name'  => $row['last_name'],
					'fields'     => is_array( $fields ) ? $fields : array(),
					'options'    => is_array( $options ) ? $options : array(),
					'status'     => $row['status'] ?: 'ACTIVE',
					'cancelled_at' => $row['cancelled_at'],
				);
			},
			$rows
		);
		$ticket_slots = array();
		foreach ( $items as $item ) {
			for ( $ticket_index = 1; $ticket_index <= (int) $item['quantity']; $ticket_index++ ) {
				$ticket_slots[] = array( 'ticket_type_code' => $item['ticket_type_code'], 'ticket_index' => $ticket_index );
			}
		}
		$mapping_valid = count( $ticket_slots ) === count( $participants );
		$seen_slots = array();
		foreach ( $participants as $participant ) {
			$slot_key = $participant['ticket_type_code'] . ':' . $participant['ticket_index'];
			if ( ! $participant['ticket_type_code'] || $participant['ticket_index'] < 1 || isset( $seen_slots[ $slot_key ] ) || ! in_array( array( 'ticket_type_code' => $participant['ticket_type_code'], 'ticket_index' => $participant['ticket_index'] ), $ticket_slots, true ) ) {
				$mapping_valid = false;
				break;
			}
			$seen_slots[ $slot_key ] = true;
		}
		if ( ! $mapping_valid && count( $ticket_slots ) === count( $participants ) ) {
			foreach ( $participants as $index => &$participant ) {
				$participant['ticket_type_code'] = $ticket_slots[ $index ]['ticket_type_code'];
				$participant['ticket_index'] = $ticket_slots[ $index ]['ticket_index'];
			}
			unset( $participant );
		}
		$order_options = json_decode( (string) ( $registration['order_options_json'] ?? '' ), true );
		$order_options = is_array( $order_options ) ? $order_options : array();
		$snapshot_json = (string) ( $registration['snapshot_json'] ?? '' );
		$revision_id = (string) ( $registration['event_revision_id'] ?? '' );
		$revision_hash = (string) ( $registration['event_revision_hash'] ?? '' );
		$privacy_consent_id = (string) ( $registration['privacy_consent_id'] ?? '' );
		$privacy_policy_version = (string) ( $registration['privacy_policy_version'] ?? '' );
		$privacy_accepted_at = (string) ( $registration['privacy_accepted_at'] ?? '' );
		if ( ! $snapshot_json || ! $revision_id || ! preg_match( '/^[a-f0-9]{64}$/i', $revision_hash ) ) {
			$legacy_snapshot = array( 'schema_version' => MI_VERSION, 'legacy_record' => true, 'order_code' => $registration['order_code'], 'event_id' => (int) $registration['event_id'], 'status' => $registration['status'], 'buyer' => array( 'first_name' => $registration['buyer_first_name'], 'last_name' => $registration['buyer_last_name'], 'email' => $registration['buyer_email'], 'phone' => $registration['buyer_phone'] ), 'tickets' => $items, 'participants' => $participants, 'order_options' => $order_options, 'economic_summary' => self::riepilogo_salvato( $registration ), 'created_at' => $registration['created_at'] );
			$snapshot_json = MI_Workspace_Client::stable_json( $legacy_snapshot );
			$revision_id = '0';
			$revision_hash = hash( 'sha256', $snapshot_json );
			$privacy_consent_id = $privacy_consent_id ?: 'legacy-unavailable';
			$privacy_policy_version = $privacy_policy_version ?: 'legacy-unavailable';
			$privacy_accepted_at = $privacy_accepted_at ?: (string) $registration['created_at'];
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
				'special_requests'=> (string) ( $registration['special_requests'] ?? '' ),
				'participants'   => $participants,
				'tickets'        => $items,
				'order_options'  => $order_options,
				'total_cents'    => (int) $registration['total_cents'],
				'economic_mode'  => (string) $registration['economic_mode'],
				'initial_due_cents' => (int) $registration['initial_due_cents'],
				'balance_cents'  => (int) $registration['balance_cents'],
				'payment_methods'=> (array) json_decode( (string) $registration['payment_methods_json'], true ),
				'event_revision_id' => $revision_id,
				'event_revision_hash' => $revision_hash,
				'snapshot_json' => $snapshot_json,
				'privacy_consent_id' => $privacy_consent_id,
				'privacy_policy_version' => $privacy_policy_version,
				'privacy_accepted_at' => $privacy_accepted_at,
				'marketing_consent_id' => (string) $registration['marketing_consent_id'],
				'marketing_accepted_at' => (string) $registration['marketing_accepted_at'],
				'payments'       => array_map( static function ( $payment ) { return array_map( 'sanitize_text_field', $payment ); }, $payments ),
			)
		);
		if ( is_wp_error( $result ) || empty( $result['complete'] ) ) {
			$error_code = is_wp_error( $result ) ? $result->get_error_code() : 'incomplete_replica';
			$wpdb->query( $wpdb->prepare( "UPDATE {$registrations_table} SET workspace_status = 'PENDING', workspace_attempts = workspace_attempts + 1, workspace_last_error = %s WHERE id = %d", sanitize_key( $error_code ), $registration_id ) );
			return 'PENDING';
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$registrations_table} SET workspace_status = 'SYNCED', workspace_attempts = workspace_attempts + 1, workspace_last_error = NULL, workspace_synced_at = %s WHERE id = %d", current_time( 'mysql', true ), $registration_id ) );
		self::scrub_relay_only_fields( $registration_id, (int) $registration['event_id'] );
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

	public static function expire_due_registrations() {
		global $wpdb;
		$registrations = $wpdb->prefix . 'mi_registrations';
		$payments = $wpdb->prefix . 'mi_payments';
		$now = current_time( 'mysql', true );
		// La riconciliazione migliora la coerenza con Workspace, ma un endpoint GAS
		// temporaneamente non aggiornato non deve sospendere le scadenze WordPress.
		if ( MI_Workspace_Client::is_configured() ) self::reconcile_workspace_payments();
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT r.id FROM {$registrations} r
				 LEFT JOIN {$payments} p ON p.registration_id = r.id
				 WHERE r.status = 'PENDING_PAYMENT' AND r.capacity_released_at IS NULL AND r.expires_at IS NOT NULL AND r.expires_at <= %s
				 GROUP BY r.id, r.initial_due_cents
				 HAVING COALESCE(SUM(CASE WHEN p.transaction_kind = 'REFUND' THEN -p.amount_cents ELSE p.amount_cents END), 0) < r.initial_due_cents
				 ORDER BY r.id LIMIT 50",
				$now
			)
		);
		foreach ( $ids as $registration_id ) {
			self::transition_registration_status( (int) $registration_id, 'EXPIRED', 'SYSTEM_CRON' );
		}
	}

	public static function reconcile_workspace_payments( $requested_order_codes = array() ) {
		global $wpdb;
		$registrations = $wpdb->prefix . 'mi_registrations';
		$payments_table = $wpdb->prefix . 'mi_payments';
		$order_codes = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $requested_order_codes ) ) ) );
		if ( $order_codes ) {
			$order_codes = array_slice( $order_codes, 0, 20 );
			$placeholders = implode( ',', array_fill( 0, count( $order_codes ), '%s' ) );
			$orders = $wpdb->get_results( $wpdb->prepare( "SELECT id, order_code FROM {$registrations} WHERE order_code IN ({$placeholders}) AND status IN ('CONFIRMED','PENDING_PAYMENT') AND capacity_released_at IS NULL ORDER BY id LIMIT 20", $order_codes ), ARRAY_A );
		} else {
			$orders = $wpdb->get_results( "SELECT id, order_code FROM {$registrations} WHERE status IN ('CONFIRMED','PENDING_PAYMENT') AND capacity_released_at IS NULL ORDER BY id LIMIT 50", ARRAY_A );
		}
		if ( ! $orders ) return 0;
		$result = MI_Workspace_Client::request( 'ELENCA_PAGAMENTI', array( 'order_codes' => array_column( $orders, 'order_code' ) ) );
		if ( is_wp_error( $result ) || ! isset( $result['payments'] ) || ! is_array( $result['payments'] ) ) return new WP_Error( 'mi_workspace_payment_reconciliation_failed', 'Riconciliazione pagamenti Workspace non disponibile.' );
		$by_code = array(); foreach ( $orders as $order ) $by_code[ $order['order_code'] ] = (int) $order['id'];
		$kind_map = array( 'INCASSO' => 'PAYMENT', 'RIMBORSO' => 'REFUND', 'STORNO' => 'REFUND' );
		$installment_map = array( 'CAPARRA' => 'DEPOSIT', 'SALDO' => 'BALANCE', 'INTERO' => 'FULL', 'INTERMEDIO' => 'OTHER', 'NON_ASSEGNATO' => 'OTHER' );
		$source_map = array( 'BONIFICO' => 'BANK_TRANSFER', 'CARTA' => 'CARD', 'CONTANTE' => 'CASH' );
		$inserted = 0;
		foreach ( array_slice( $result['payments'], 0, 500 ) as $payment ) {
			$order_code = sanitize_text_field( $payment['codice_ordine'] ?? '' );
			$origin_id = sanitize_text_field( $payment['id_pagamento'] ?? '' );
			$kind = $kind_map[ strtoupper( sanitize_key( $payment['tipo_movimento'] ?? '' ) ) ] ?? '';
			$installment = $installment_map[ strtoupper( sanitize_key( $payment['tipo_rata'] ?? '' ) ) ] ?? '';
			$source = $source_map[ strtoupper( sanitize_key( $payment['fonte_pagamento'] ?? '' ) ) ] ?? '';
			$amount = absint( $payment['importo_centesimi'] ?? 0 );
			$date = strtotime( (string) ( $payment['data_effettiva'] ?? '' ) );
			if ( ! isset( $by_code[ $order_code ] ) || ! $origin_id || ! $kind || ! $installment || ! $source || $amount < 1 || false === $date ) continue;
			$result_insert = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$payments_table} (registration_id, transaction_kind, installment_kind, effective_at, amount_cents, payment_source, external_reference, operator_label, administrative_note, origin_channel, origin_id, created_at) VALUES (%d,%s,%s,%s,%d,%s,%s,%s,%s,'WORKSPACE',%s,%s)", $by_code[ $order_code ], $kind, $installment, gmdate( 'Y-m-d H:i:s', $date ), $amount, $source, sanitize_text_field( $payment['riferimento_esterno'] ?? '' ), sanitize_text_field( $payment['etichetta_operatore'] ?? '' ), sanitize_textarea_field( $payment['nota_amministrativa'] ?? '' ), $origin_id, current_time( 'mysql', true ) ) );
			if ( $result_insert ) $inserted++;
		}
		foreach ( $orders as $order ) self::reconcile_payment_status( (int) $order['id'], 'WORKSPACE' );
		return $inserted;
	}

	public static function public_status_token( $registration_id, $order_code, $email ) {
		$message = absint( $registration_id ) . '|' . sanitize_text_field( (string) $order_code ) . '|' . strtolower( sanitize_email( (string) $email ) );
		return hash_hmac( 'sha256', $message, wp_salt( 'auth' ) );
	}

	public static function public_status( $order_code, $email = '', $token = '', $event_id = 0 ) {
		global $wpdb;
		$order_code = strtoupper( substr( sanitize_text_field( (string) $order_code ), 0, 32 ) );
		$email = strtolower( sanitize_email( (string) $email ) );
		$token = strtolower( sanitize_text_field( (string) $token ) );
		if ( ! $order_code || ( ! $email && ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) ) return new WP_Error( 'mi_status_not_found', 'Non è stato possibile verificare la prenotazione.' );
		$registration = $wpdb->get_row( $wpdb->prepare( "SELECT id,event_id,order_code,status,buyer_email,total_cents,initial_due_cents,balance_cents,payment_deadline_at FROM {$wpdb->prefix}mi_registrations WHERE order_code=%s LIMIT 1", $order_code ), ARRAY_A );
		if ( ! $registration ) return new WP_Error( 'mi_status_not_found', 'Non è stato possibile verificare la prenotazione.' );
		if ( $event_id && absint( $registration['event_id'] ) !== absint( $event_id ) ) return new WP_Error( 'mi_status_not_found', 'Non è stato possibile verificare la prenotazione per questo evento.' );
		$valid = $email
			? hash_equals( strtolower( (string) $registration['buyer_email'] ), $email )
			: hash_equals( self::public_status_token( $registration['id'], $registration['order_code'], $registration['buyer_email'] ), $token );
		if ( ! $valid ) return new WP_Error( 'mi_status_not_found', 'Non è stato possibile verificare la prenotazione.' );
		if ( MI_Workspace_Client::is_configured() ) {
			$reconciled = self::reconcile_workspace_payments( array( $registration['order_code'] ) );
			if ( is_wp_error( $reconciled ) ) return new WP_Error( 'mi_status_temporarily_unavailable', 'Lo stato aggiornato non è momentaneamente disponibile. Riprova tra poco.' );
		}
		$paid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN transaction_kind='REFUND' THEN -amount_cents ELSE amount_cents END),0) FROM {$wpdb->prefix}mi_payments WHERE registration_id=%d", $registration['id'] ) );
		$total = max( 0, (int) $registration['total_cents'] );
		$balance = max( 0, $total - $paid );
		$status_labels = array( 'CONFIRMED' => 'Confermata', 'PENDING_PAYMENT' => 'In attesa di pagamento', 'WAITLISTED' => 'Lista d’attesa', 'CANCELLED' => 'Annullata', 'EXPIRED' => 'Scaduta' );
		if ( 0 === $total ) $payment_label = 'Nessun pagamento previsto';
		elseif ( $paid >= $total ) $payment_label = 'Saldo completato';
		elseif ( $paid >= (int) $registration['initial_due_cents'] && (int) $registration['initial_due_cents'] > 0 ) $payment_label = 'Caparra ricevuta, saldo ancora dovuto';
		elseif ( $paid > 0 ) $payment_label = 'Versamento parziale ricevuto';
		else $payment_label = (int) $registration['initial_due_cents'] > 0 ? 'Caparra ancora da versare' : 'Pagamento ancora da completare';
		return array(
			'order_code'       => (string) $registration['order_code'],
			'event_title'      => get_the_title( (int) $registration['event_id'] ),
			'status'           => $status_labels[ $registration['status'] ] ?? sanitize_text_field( (string) $registration['status'] ),
			'payment_status'   => $payment_label,
			'paid_cents'       => max( 0, $paid ),
			'balance_cents'    => $balance,
			'total_cents'      => $total,
			'payment_deadline' => (string) $registration['payment_deadline_at'],
		);
	}

	private static function reconcile_payment_status( $registration_id, $actor_label ) {
		global $wpdb;
		$registrations = $wpdb->prefix . 'mi_registrations';
		$payments = $wpdb->prefix . 'mi_payments';
		$wpdb->query( 'START TRANSACTION' );
		try {
			$registration = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, initial_due_cents, payment_deadline_at FROM {$registrations} WHERE id = %d FOR UPDATE", $registration_id ), ARRAY_A );
			if ( ! $registration || ! in_array( $registration['status'], array( 'PENDING_PAYMENT', 'CONFIRMED' ), true ) || (int) $registration['initial_due_cents'] < 1 ) { $wpdb->query( 'COMMIT' ); return; }
			$paid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN transaction_kind = 'REFUND' THEN -amount_cents ELSE amount_cents END),0) FROM {$payments} WHERE registration_id = %d", $registration_id ) );
			$new_status = $paid >= (int) $registration['initial_due_cents'] ? 'CONFIRMED' : 'PENDING_PAYMENT';
			if ( $new_status === $registration['status'] ) { $wpdb->query( 'COMMIT' ); return; }
			$expires_at = 'CONFIRMED' === $new_status ? null : $registration['payment_deadline_at'];
			if ( false === $wpdb->update( $registrations, array( 'status' => $new_status, 'expires_at' => $expires_at, 'workspace_status' => 'PENDING', 'workspace_last_error' => 'payment_status_changed' ), array( 'id' => $registration_id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) ) || ! self::append_registration_event( $registration_id, 'PAYMENT_STATUS_CHANGED', $registration['status'], $new_status, $actor_label, array( 'net_paid_cents' => $paid, 'initial_due_cents' => (int) $registration['initial_due_cents'] ) ) ) throw new RuntimeException( 'Stato pagamento non aggiornato.' );
			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
		}
	}

	public static function cancel_registration( $registration_id, $actor_label = 'ADMIN' ) {
		return self::transition_registration_status( absint( $registration_id ), 'CANCELLED', $actor_label );
	}

	public static function participant_from_token( $participant_id, $token ) {
		global $wpdb;
		$participant_id = absint( $participant_id );
		$token = (string) $token;
		if ( ! $participant_id || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) return new WP_Error( 'mi_cancel_token_invalid', 'Collegamento non valido.' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT p.id,p.registration_id,p.first_name,p.last_name,p.status,p.cancellation_token_hash,r.event_id,r.order_code,r.status registration_status FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE p.id=%d", $participant_id ), ARRAY_A );
		if ( ! $row || empty( $row['cancellation_token_hash'] ) || ! hash_equals( (string) $row['cancellation_token_hash'], hash( 'sha256', $token ) ) ) return new WP_Error( 'mi_cancel_token_invalid', 'Collegamento non valido o non più utilizzabile.' );
		unset( $row['cancellation_token_hash'] );
		return $row;
	}

	public static function cancel_participant_with_token( $participant_id, $token ) {
		$participant = self::participant_from_token( $participant_id, $token );
		if ( is_wp_error( $participant ) ) return $participant;
		return self::cancel_participant( $participant_id, 'PARTICIPANT_LINK' );
	}

	public static function cancel_participant( $participant_id, $actor_label = 'ADMIN' ) {
		global $wpdb;
		$participants = $wpdb->prefix . 'mi_participants';
		$registrations = $wpdb->prefix . 'mi_registrations';
		$counters = $wpdb->prefix . 'mi_event_counters';
		$ticket_counters = $wpdb->prefix . 'mi_ticket_counters';
		$participant_id = absint( $participant_id );
		$wpdb->query( 'START TRANSACTION' );
		try {
			$participant = $wpdb->get_row( $wpdb->prepare( "SELECT id,registration_id,ticket_type_code,status FROM {$participants} WHERE id=%d FOR UPDATE", $participant_id ), ARRAY_A );
			if ( ! $participant ) throw new RuntimeException( 'Partecipante non trovato.' );
			if ( 'CANCELLED' === $participant['status'] ) { $wpdb->query( 'COMMIT' ); return 'CANCELLED'; }
			$registration = $wpdb->get_row( $wpdb->prepare( "SELECT id,event_id,status,capacity_released_at FROM {$registrations} WHERE id=%d FOR UPDATE", $participant['registration_id'] ), ARRAY_A );
			if ( ! $registration || ! in_array( $registration['status'], array( 'CONFIRMED', 'PENDING_PAYMENT', 'WAITLISTED' ), true ) || $registration['capacity_released_at'] ) throw new RuntimeException( 'Partecipazione non annullabile.' );
			$event_id = (int) $registration['event_id'];
			$counter_field = in_array( $registration['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ? 'confirmed_count' : 'waitlisted_count';
			$wpdb->get_row( $wpdb->prepare( "SELECT event_id FROM {$counters} WHERE event_id=%d FOR UPDATE", $event_id ), ARRAY_A );
			$wpdb->get_row( $wpdb->prepare( "SELECT event_id FROM {$ticket_counters} WHERE event_id=%d AND ticket_type_code=%s FOR UPDATE", $event_id, $participant['ticket_type_code'] ), ARRAY_A );
			$now = current_time( 'mysql', true );
			$wpdb->query( $wpdb->prepare( "UPDATE {$counters} SET {$counter_field}=GREATEST(0,{$counter_field}-1),updated_at=%s WHERE event_id=%d", $now, $event_id ) );
			$wpdb->query( $wpdb->prepare( "UPDATE {$ticket_counters} SET {$counter_field}=GREATEST(0,{$counter_field}-1),updated_at=%s WHERE event_id=%d AND ticket_type_code=%s", $now, $event_id, $participant['ticket_type_code'] ) );
			$actor_label = substr( sanitize_text_field( $actor_label ), 0, 120 );
			$participant_updated = $wpdb->query( $wpdb->prepare( "UPDATE {$participants} SET status='CANCELLED',cancelled_at=%s,cancellation_actor=%s,cancellation_token_hash=NULL WHERE id=%d AND status='ACTIVE'", $now, $actor_label, $participant_id ) );
			if ( 1 !== $participant_updated ) throw new RuntimeException( 'Partecipante non aggiornato.' );
			$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$participants} WHERE registration_id=%d AND status='ACTIVE'", $registration['id'] ) );
			$registration_update = array( 'workspace_status' => 'PENDING', 'workspace_last_error' => 'participant_cancelled' );
			$formats = array( '%s', '%s' );
			if ( 0 === $remaining ) { $registration_update['status'] = 'CANCELLED'; $registration_update['capacity_released_at'] = $now; $formats[] = '%s'; $formats[] = '%s'; }
			if ( false === $wpdb->update( $registrations, $registration_update, array( 'id' => $registration['id'] ), $formats, array( '%d' ) ) ) throw new RuntimeException( 'Prenotazione non aggiornata.' );
			if ( ! self::append_registration_event( (int) $registration['id'], 'PARTICIPANT_CANCELLED', $registration['status'], 0 === $remaining ? 'CANCELLED' : $registration['status'], $actor_label, array( 'participant_id' => $participant_id, 'remaining_participants' => $remaining ) ) ) throw new RuntimeException( 'Audit non aggiornato.' );
			$promoted = in_array( $registration['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ? self::promote_waitlisted_locked( $event_id, $now ) : array();
			$wpdb->query( 'COMMIT' );
			self::accoda_sincronizzazione_workspace( (int) $registration['id'], 'PENDING' );
			foreach ( $promoted as $promoted_id ) self::accoda_sincronizzazione_workspace( $promoted_id, 'PENDING' );
			if ( $promoted ) MI_Spedizione_Email::pianifica_spedizione();
			return 'CANCELLED';
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'mi_participant_cancel_failed', 'Non è stato possibile annullare la partecipazione.' );
		}
	}

	private static function transition_registration_status( $registration_id, $target_status, $actor_label ) {
		global $wpdb;
		$registrations = $wpdb->prefix . 'mi_registrations';
		$items_table = $wpdb->prefix . 'mi_registration_items';
		$participants = $wpdb->prefix . 'mi_participants';
		$counters = $wpdb->prefix . 'mi_event_counters';
		$ticket_counters = $wpdb->prefix . 'mi_ticket_counters';
		$payments = $wpdb->prefix . 'mi_payments';
		$target_status = strtoupper( sanitize_key( $target_status ) );
		if ( ! in_array( $target_status, array( 'CANCELLED', 'EXPIRED' ), true ) ) {
			return new WP_Error( 'mi_status_invalid', 'Stato non valido.' );
		}
		$wpdb->query( 'START TRANSACTION' );
		try {
			$registration = $wpdb->get_row( $wpdb->prepare( "SELECT id, event_id, status, total_qty, initial_due_cents, capacity_released_at FROM {$registrations} WHERE id = %d FOR UPDATE", $registration_id ), ARRAY_A );
			if ( ! $registration ) {
				throw new RuntimeException( 'Iscrizione non trovata.' );
			}
			if ( in_array( $registration['status'], array( 'CANCELLED', 'EXPIRED' ), true ) ) {
				$wpdb->query( 'COMMIT' );
				return $registration['status'];
			}
			if ( ! in_array( $registration['status'], array( 'CONFIRMED', 'PENDING_PAYMENT', 'WAITLISTED' ), true ) || $registration['capacity_released_at'] ) {
				throw new RuntimeException( 'Iscrizione non annullabile.' );
			}
			if ( 'EXPIRED' === $target_status ) {
				$paid_cents = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN transaction_kind = 'REFUND' THEN -amount_cents ELSE amount_cents END), 0) FROM {$payments} WHERE registration_id = %d", $registration_id ) );
				if ( $paid_cents >= (int) $registration['initial_due_cents'] ) {
					$wpdb->update( $registrations, array( 'expires_at' => null ), array( 'id' => $registration_id ), array( '%s' ), array( '%d' ) );
					$wpdb->query( 'COMMIT' );
					return $registration['status'];
				}
			}
			$event_id = (int) $registration['event_id'];
			$counter_field = in_array( $registration['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ? 'confirmed_count' : 'waitlisted_count';
			$wpdb->get_row( $wpdb->prepare( "SELECT event_id FROM {$counters} WHERE event_id = %d FOR UPDATE", $event_id ), ARRAY_A );
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT ticket_type_code, COUNT(*) quantity FROM {$participants} WHERE registration_id = %d AND status = 'ACTIVE' GROUP BY ticket_type_code ORDER BY ticket_type_code", $registration_id ), ARRAY_A );
			$remaining_qty = array_sum( array_map( 'intval', wp_list_pluck( $items, 'quantity' ) ) );
			foreach ( $items as $item ) {
				$wpdb->get_row( $wpdb->prepare( "SELECT event_id FROM {$ticket_counters} WHERE event_id = %d AND ticket_type_code = %s FOR UPDATE", $event_id, $item['ticket_type_code'] ), ARRAY_A );
			}
			$now = current_time( 'mysql', true );
			$wpdb->query( $wpdb->prepare( "UPDATE {$counters} SET {$counter_field} = GREATEST(0, {$counter_field} - %d), updated_at = %s WHERE event_id = %d", $remaining_qty, $now, $event_id ) );
			foreach ( $items as $item ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$ticket_counters} SET {$counter_field} = GREATEST(0, {$counter_field} - %d), updated_at = %s WHERE event_id = %d AND ticket_type_code = %s", $item['quantity'], $now, $event_id, $item['ticket_type_code'] ) );
			}
			$updated = $wpdb->update( $registrations, array( 'status' => $target_status, 'capacity_released_at' => $now, 'workspace_status' => 'PENDING', 'workspace_last_error' => 'status_changed' ), array( 'id' => $registration_id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
			if ( false === $updated || ! self::append_registration_event( $registration_id, $target_status, $registration['status'], $target_status, $actor_label ) ) {
				throw new RuntimeException( 'Stato non aggiornato.' );
			}
			$promoted = in_array( $registration['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ? self::promote_waitlisted_locked( $event_id, $now ) : array();
			$wpdb->query( 'COMMIT' );
			self::accoda_sincronizzazione_workspace( $registration_id, 'PENDING' );
			foreach ( $promoted as $promoted_id ) self::accoda_sincronizzazione_workspace( $promoted_id, 'PENDING' );
			if ( $promoted ) MI_Spedizione_Email::pianifica_spedizione();
			return $target_status;
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'mi_status_transition_failed', 'Impossibile aggiornare lo stato dell’iscrizione.' );
		}
	}

	private static function promote_waitlisted_locked( $event_id, $now ) {
		global $wpdb;
		$registrations = $wpdb->prefix . 'mi_registrations';
		$participants = $wpdb->prefix . 'mi_participants';
		$counters = $wpdb->prefix . 'mi_event_counters';
		$ticket_counters = $wpdb->prefix . 'mi_ticket_counters';
		$outbox = $wpdb->prefix . 'mi_email_outbox';
		$event = self::public_event( $event_id, 'publish' !== get_post_status( $event_id ) );
		if ( is_wp_error( $event ) || empty( $event['waitlist_enabled'] ) ) return array();
		$counter = $wpdb->get_row( $wpdb->prepare( "SELECT confirmed_count, waitlisted_count FROM {$counters} WHERE event_id = %d FOR UPDATE", $event_id ), ARRAY_A );
		if ( ! $counter ) return array();
		$type_limits = array();
		foreach ( $event['ticket_types'] as $ticket ) $type_limits[ $ticket['code'] ] = absint( $ticket['capacity'] ?? 0 );
		$type_counts = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ticket_type_code, confirmed_count, waitlisted_count FROM {$ticket_counters} WHERE event_id = %d ORDER BY ticket_type_code FOR UPDATE", $event_id ), ARRAY_A ) as $row ) $type_counts[ $row['ticket_type_code'] ] = $row;
		$candidates = $wpdb->get_results( $wpdb->prepare( "SELECT id, total_qty, total_cents, buyer_first_name, buyer_last_name, buyer_email, order_code FROM {$registrations} WHERE event_id = %d AND status = 'WAITLISTED' AND capacity_released_at IS NULL ORDER BY created_at, id FOR UPDATE", $event_id ), ARRAY_A );
		$promoted = array();
		foreach ( $candidates as $candidate ) {
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT ticket_type_code, COUNT(*) quantity FROM {$participants} WHERE registration_id = %d AND status = 'ACTIVE' GROUP BY ticket_type_code ORDER BY ticket_type_code", $candidate['id'] ), ARRAY_A );
			$active_qty = array_sum( array_map( 'intval', wp_list_pluck( $items, 'quantity' ) ) );
			if ( ! $active_qty || (int) $counter['confirmed_count'] + $active_qty > (int) $event['capacity'] ) continue;
			$fits = true;
			foreach ( $items as $item ) {
				$limit = (int) ( $type_limits[ $item['ticket_type_code'] ] ?? 0 );
				$current = (int) ( $type_counts[ $item['ticket_type_code'] ]['confirmed_count'] ?? 0 );
				if ( $limit && $current + (int) $item['quantity'] > $limit ) { $fits = false; break; }
			}
			if ( ! $fits ) continue;
			$promoted_status = in_array( $event['economic_mode'] ?? '', array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) && (int) $candidate['total_cents'] > 0 ? 'PENDING_PAYMENT' : 'CONFIRMED';
			$economic = self::riepilogo_economico( $event, (int) $candidate['total_cents'], $promoted_status );
			$deadline = self::registration_expiry( $event, $promoted_status, $now );
			$wpdb->update( $registrations, array( 'status' => $promoted_status, 'initial_due_cents' => $economic['initial_due_cents'], 'balance_cents' => $economic['balance_cents'], 'expires_at' => $deadline, 'payment_deadline_at' => $deadline, 'workspace_status' => 'PENDING', 'workspace_last_error' => 'waitlist_promoted' ), array( 'id' => $candidate['id'] ), array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' ), array( '%d' ) );
			$wpdb->query( $wpdb->prepare( "UPDATE {$counters} SET waitlisted_count = GREATEST(0, waitlisted_count - %d), confirmed_count = confirmed_count + %d, updated_at = %s WHERE event_id = %d", $active_qty, $active_qty, $now, $event_id ) );
			$counter['confirmed_count'] += $active_qty;
			foreach ( $items as $item ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$ticket_counters} SET waitlisted_count = GREATEST(0, waitlisted_count - %d), confirmed_count = confirmed_count + %d, updated_at = %s WHERE event_id = %d AND ticket_type_code = %s", $item['quantity'], $item['quantity'], $now, $event_id, $item['ticket_type_code'] ) );
				$type_counts[ $item['ticket_type_code'] ]['confirmed_count'] = (int) ( $type_counts[ $item['ticket_type_code'] ]['confirmed_count'] ?? 0 ) + (int) $item['quantity'];
			}
			self::append_registration_event( (int) $candidate['id'], 'WAITLIST_PROMOTED', 'WAITLISTED', $promoted_status, 'SYSTEM', array( 'expires_at' => $deadline ) );
			$email_items = array();
			foreach ( $items as $item ) {
				foreach ( $event['ticket_types'] as $ticket ) {
					if ( $ticket['code'] === $item['ticket_type_code'] ) {
						$email_items[] = array( 'name' => $ticket['name'], 'quantity' => $item['quantity'] );
						break;
					}
				}
			}
			$email_values = MI_Modello_Email::valori_ordine( $event, $candidate['order_code'], 'PENDING_PAYMENT' === $promoted_status ? 'In attesa di pagamento' : 'Confermata', $active_qty, $candidate['buyer_first_name'] . ' ' . $candidate['buyer_last_name'], $economic, $email_items );
			$participant_management = array();
			$active_participants = $wpdb->get_results( $wpdb->prepare( "SELECT id,first_name,last_name FROM {$participants} WHERE registration_id=%d AND status='ACTIVE' ORDER BY id", $candidate['id'] ), ARRAY_A );
			foreach ( $active_participants as $active_participant ) {
				$cancel_token = bin2hex( random_bytes( 32 ) );
				$updated_token = $wpdb->update( $participants, array( 'cancellation_token_hash' => hash( 'sha256', $cancel_token ) ), array( 'id' => (int) $active_participant['id'], 'status' => 'ACTIVE' ), array( '%s' ), array( '%d', '%s' ) );
				if ( false === $updated_token ) throw new RuntimeException( 'Collegamento partecipante non aggiornato.' );
				$participant_management[] = array( 'name' => trim( $active_participant['first_name'] . ' ' . $active_participant['last_name'] ), 'url' => MI_Portal::participant_cancel_url( (int) $active_participant['id'], $cancel_token ) );
			}
			$email_values['_participant_management'] = $participant_management;
			$email_snapshot = MI_Modello_Email::crea_istantanea( $event_id, $email_values );
			$email_snapshot['status_url'] = MI_Portal::status_url( $candidate['id'], $candidate['order_code'], $candidate['buyer_email'] );
			$email_status = MI_Spedizione_Email::stato_nuova_email( $email_snapshot );
			$wpdb->insert( $outbox, array( 'registration_id' => $candidate['id'], 'recipient' => $candidate['buyer_email'], 'template_type' => 'WAITLIST_PROMOTION', 'payload_json' => wp_json_encode( array( 'event_title' => $event['title'], 'order_code' => $candidate['order_code'], 'status' => $promoted_status, 'email_preview' => $email_snapshot ) ), 'status' => $email_status, 'created_at' => $now ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) );
			$promoted[] = (int) $candidate['id'];
		}
		return $promoted;
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
			if ( ! is_int( $raw_quantity ) && ! ( is_string( $raw_quantity ) && preg_match( '/^\d+$/', $raw_quantity ) ) ) {
				return new WP_Error( 'mi_ticket_quantity_invalid', 'La quantità deve essere un numero intero.', array( 'status' => 400 ) );
			}
			$item_quantity = absint( $raw_quantity );
			if ( $item_quantity > (int) $allowed[ $code ]['max_per_order'] ) {
				return new WP_Error( 'mi_ticket_limit', 'Quantità superiore al limite per ordine.', array( 'status' => 400 ) );
			}
			if ( $item_quantity > 0 ) {
				$unit_price = 'FIXED' === $event['pricing_mode'] ? max( 0, (int) ( $event['fixed_price_cents'] ?? 0 ) ) : ( 'CALCULATED' === $event['pricing_mode'] ? (int) $allowed[ $code ]['price_cents'] : 0 );
				$items[] = array( 'code' => $code, 'name' => sanitize_text_field( $allowed[ $code ]['name'] ), 'quantity' => $item_quantity, 'unit_price_cents' => $unit_price, 'capacity' => absint( $allowed[ $code ]['capacity'] ?? 0 ) );
				$quantity += $item_quantity;
				$total += $item_quantity * $unit_price;
			}
		}
		if ( $quantity < 1 || $quantity > 20 ) {
			return new WP_Error( 'mi_quantity', 'Seleziona da 1 a 20 partecipanti.', array( 'status' => 400 ) );
		}
		return array( 'items' => $items, 'quantity' => $quantity, 'total_cents' => $total );
	}

	private static function validate_participants( $raw_participants, $selection, $fields, $option_definitions, $extra_scope = 'ONE' ) {
		$expected = (int) $selection['quantity'];
		$extra_scope = 'ALL' === strtoupper( (string) $extra_scope ) ? 'ALL' : 'ONE';
		if ( ! is_array( $raw_participants ) || count( $raw_participants ) !== $expected ) {
			return new WP_Error( 'mi_participants', 'Inserisci nome e cognome di ogni partecipante.', array( 'status' => 400 ) );
		}
		$participants = array();
		$remaining = array();
		$seen_indexes = array();
		foreach ( $selection['items'] as $item ) {
			$remaining[ $item['code'] ] = (int) $item['quantity'];
			$seen_indexes[ $item['code'] ] = array();
		}
		foreach ( $raw_participants as $participant_position => $raw ) {
			if ( ! is_array( $raw ) ) {
				return new WP_Error( 'mi_participant_invalid', 'Controlla i dati dei partecipanti.', array( 'status' => 400 ) );
			}
			$ticket_type_code = sanitize_key( $raw['ticket_type_code'] ?? '' );
			$ticket_index = absint( $raw['ticket_index'] ?? 0 );
			$selected_quantity = isset( $seen_indexes[ $ticket_type_code ] ) ? (int) $remaining[ $ticket_type_code ] + count( $seen_indexes[ $ticket_type_code ] ) : 0;
			if ( empty( $remaining[ $ticket_type_code ] ) || $ticket_index < 1 || $ticket_index > $selected_quantity || isset( $seen_indexes[ $ticket_type_code ][ $ticket_index ] ) ) {
				return new WP_Error( 'mi_participant_ticket_invalid', 'Associazione tra partecipante e tipologia non valida.', array( 'status' => 400 ) );
			}
			$seen_indexes[ $ticket_type_code ][ $ticket_index ] = true;
			$remaining[ $ticket_type_code ]--;
			$first_name = sanitize_text_field( $raw['first_name'] ?? '' );
			$last_name = sanitize_text_field( $raw['last_name'] ?? '' );
			$raw_fields = is_array( $raw['fields'] ?? null ) ? $raw['fields'] : array();
			$raw_options = is_array( $raw['options'] ?? null ) ? $raw['options'] : array();
			$has_extra_data = array_filter( $raw_fields, static function ( $value ) { return '' !== trim( (string) $value ); } ) || array_filter( $raw_options, static function ( $value ) { return (int) $value > 0; } );
			if ( ! $first_name || ! $last_name || strlen( $first_name ) > 80 || strlen( $last_name ) > 80 ) {
				return new WP_Error( 'mi_participant_invalid', 'Controlla i dati dei partecipanti.', array( 'status' => 400 ) );
			}
			$requires_extra_data = 'ALL' === $extra_scope || 0 === $participant_position;
			$validation_fields = array_map( static function ( $field ) use ( $requires_extra_data ) {
				$field['required'] = $requires_extra_data && ! empty( $field['required'] );
				return $field;
			}, $fields );
			$answers = $requires_extra_data || $has_extra_data ? MI_Field_Schema::validate_answers( $raw_fields, $validation_fields ) : array();
			if ( is_wp_error( $answers ) ) {
				return $answers;
			}
			$options = self::validate_options( $raw_options, $option_definitions, 'TICKET' );
			if ( is_wp_error( $options ) ) {
				return $options;
			}
			$participants[] = array( 'ticket_type_code' => $ticket_type_code, 'ticket_index' => $ticket_index, 'first_name' => $first_name, 'last_name' => $last_name, 'fields' => $answers, 'options' => $options );
		}
		if ( array_sum( $remaining ) !== 0 ) {
			return new WP_Error( 'mi_participant_ticket_invalid', 'Associazione tra partecipanti e tipologie incompleta.', array( 'status' => 400 ) );
		}
		return $participants;
	}

	private static function validate_options( $raw, $definitions, $scope ) {
		$allowed = array();
		foreach ( (array) $definitions as $definition ) {
			if ( strtoupper( (string) ( $definition['scope'] ?? '' ) ) === $scope ) {
				$allowed[ sanitize_key( $definition['code'] ?? '' ) ] = $definition;
			}
		}
		$result = array();
		foreach ( (array) $raw as $raw_code => $raw_quantity ) {
			$code = sanitize_key( $raw_code );
			if ( ! isset( $allowed[ $code ] ) ) {
				return new WP_Error( 'mi_option_invalid', 'Opzione non valida.', array( 'status' => 400 ) );
			}
			if ( ! is_int( $raw_quantity ) && ! ( is_string( $raw_quantity ) && preg_match( '/^\d+$/', $raw_quantity ) ) ) {
				return new WP_Error( 'mi_option_quantity_invalid', 'Quantità opzione non valida.', array( 'status' => 400 ) );
			}
			$quantity = absint( $raw_quantity );
			if ( $quantity > absint( $allowed[ $code ]['max_quantity'] ?? 1 ) ) {
				return new WP_Error( 'mi_option_limit', 'Quantità opzione superiore al limite.', array( 'status' => 400 ) );
			}
			if ( $quantity ) {
				$result[] = array( 'code' => $code, 'name' => sanitize_text_field( $allowed[ $code ]['name'] ), 'quantity' => $quantity, 'unit_price_cents' => max( 0, (int) $allowed[ $code ]['price_cents'] ) );
			}
		}
		return $result;
	}

	private static function options_total( $order_options, $participants ) {
		$total = 0;
		foreach ( (array) $order_options as $option ) {
			$total += (int) $option['quantity'] * (int) $option['unit_price_cents'];
		}
		foreach ( (array) $participants as $participant ) {
			foreach ( (array) $participant['options'] as $option ) {
				$total += (int) $option['quantity'] * (int) $option['unit_price_cents'];
			}
		}
		return max( 0, $total );
	}

	private static function registration_expiry( $event, $status, $now ) {
		if ( 'PENDING_PAYMENT' !== $status || ! in_array( $event['economic_mode'] ?? '', array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ) {
			return null;
		}
		$deadline = (string) ( $event['payment_deadline_at'] ?? '' );
		if ( $deadline && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $deadline ) ) {
			$local = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $deadline, wp_timezone() );
			return $local ? $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : null;
		}
		// Compatibilità con eventi salvati prima della versione 3.4.9.
		$minutes = absint( $event['reservation_minutes'] ?? 0 );
		if ( ! $minutes ) return null;
		$base = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $now, new DateTimeZone( 'UTC' ) );
		return $base ? $base->modify( '+' . $minutes . ' minutes' )->format( 'Y-m-d H:i:s' ) : null;
	}

	private static function build_order_snapshot( $event, $selection, $participants, $order_options, $buyer, $economic_summary, $status, $accepted_at, $marketing_accepted, $special_requests = '' ) {
		$relay_keys = MI_Field_Schema::relay_only_keys( $event['participant_fields'] ?? array() );
		$snapshot_participants = array_map( static function ( $participant ) use ( $relay_keys ) {
			$participant['fields'] = array_diff_key( (array) ( $participant['fields'] ?? array() ), array_flip( $relay_keys ) );
			return $participant;
		}, $participants );
		return array(
			'schema_version' => MI_VERSION,
			'event' => $event,
			'status' => $status,
			'buyer' => $buyer,
			'special_requests' => $special_requests,
			'tickets' => $selection['items'],
			'participants' => $snapshot_participants,
			'order_options' => $order_options,
			'economic_summary' => $economic_summary,
			'consents' => array(
				'privacy' => array( 'id' => $event['privacy_consent_id'], 'policy_version' => $event['privacy_policy_version'], 'accepted_at' => $accepted_at ),
				'marketing' => array( 'id' => $event['marketing_consent_id'] ?? '', 'accepted' => (bool) $marketing_accepted, 'accepted_at' => $marketing_accepted ? $accepted_at : null ),
			),
		);
	}

	private static function scrub_relay_only_fields( $registration_id, $event_id ) {
		global $wpdb;
		$snapshot_json = (string) $wpdb->get_var( $wpdb->prepare( "SELECT snapshot_json FROM {$wpdb->prefix}mi_registrations WHERE id = %d", $registration_id ) );
		$snapshot = json_decode( $snapshot_json, true );
		$historical_fields = is_array( $snapshot ) ? (array) ( $snapshot['event']['participant_fields'] ?? array() ) : array();
		if ( ! $historical_fields ) {
			$event = self::public_event( $event_id, true );
			if ( is_wp_error( $event ) ) return;
			$historical_fields = (array) ( $event['participant_fields'] ?? array() );
		}
		$relay_keys = MI_Field_Schema::relay_only_keys( $historical_fields );
		if ( ! $relay_keys ) return;
		$table = $wpdb->prefix . 'mi_participants';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, extra_json FROM {$table} WHERE registration_id = %d", $registration_id ), ARRAY_A );
		foreach ( $rows as $row ) {
			$fields = json_decode( (string) $row['extra_json'], true );
			if ( ! is_array( $fields ) || ! array_intersect( $relay_keys, array_keys( $fields ) ) ) continue;
			$fields = array_diff_key( $fields, array_flip( $relay_keys ) );
			$wpdb->update( $table, array( 'extra_json' => wp_json_encode( $fields ) ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );
		}
		self::append_registration_event( $registration_id, 'relay_fields_scrubbed', '', '', 'SYSTEM', array( 'count' => count( $relay_keys ) ) );
	}

	public static function append_registration_event( $registration_id, $event_type, $from_status, $to_status, $actor_label, $detail = array() ) {
		global $wpdb;
		return false !== $wpdb->insert( $wpdb->prefix . 'mi_registration_events', array( 'registration_id' => absint( $registration_id ), 'event_type' => sanitize_key( $event_type ), 'from_status' => sanitize_key( $from_status ), 'to_status' => sanitize_key( $to_status ), 'actor_label' => sanitize_text_field( $actor_label ), 'detail_json' => wp_json_encode( $detail ), 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );
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
