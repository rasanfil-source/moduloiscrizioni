<?php

defined( 'ABSPATH' ) || exit;

final class MI_Spedizione_Email {
	const OPZIONE_MODALITA = 'mi_modalita_spedizione_email';
	const OPZIONE_DESTINATARIO_PROVA = 'mi_destinatario_prova_email';
	const OPZIONE_PROVA_VERIFICATA = 'mi_prova_email_verificata';
	private static $nome_mittente = '';
	private static $indirizzo_mittente = '';
	private static $codice_incorporato = '';
	private static $corpo_testo = '';
	private static $pubblicazioni = array();
	private static $invio_immediato_in_corso = false;

	public static function avvia() {
		add_action( 'admin_menu', array( __CLASS__, 'aggiungi_pagina' ) );
		add_action( 'admin_post_mi_salva_spedizione_email', array( __CLASS__, 'salva_impostazioni' ) );
		add_action( 'admin_post_mi_invia_email_prova', array( __CLASS__, 'invia_prova' ) );
		add_action( 'admin_post_mi_riaccoda_email', array( __CLASS__, 'riaccoda_email' ) );
		add_action( 'admin_post_mi_rigenera_email', array( __CLASS__, 'rigenera_email' ) );
		add_action( 'admin_post_mi_recupera_pubblicazione', array( __CLASS__, 'recupera_pubblicazione' ) );
		add_action( 'mi_riprova_notifica_pubblicazione', array( __CLASS__, 'riprova_pubblicazione' ), 10, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'rileva_pubblicazione' ), 10, 3 );
		add_action( 'save_post_' . MI_Event_Post_Type::EVENT_TYPE, array( __CLASS__, 'notifica_pubblicazione' ), 40, 2 );
		add_action( 'mi_spedisci_email_in_coda', array( __CLASS__, 'spedisci_coda' ) );
		add_action( 'mi_sync_workspace_pending', array( __CLASS__, 'spedisci_coda' ), 30 );
	}

	public static function destinatario_evento( $event_id ) {
		$group_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		$style = $group_id ? (array) get_post_meta( $group_id, '_mi_email_style', true ) : array();
		$email = sanitize_email( $style['contact_email'] ?? '' );
		return is_email( $email ) ? $email : self::destinatario_segreteria();
	}
	public static function destinatario_segreteria() {
		$email = sanitize_email( get_option( 'mi_email_segreteria_eventi', '' ) );
		return is_email( $email ) ? $email : MI_Modello_Email::EMAIL_SEGRETERIA;
	}
	private static function gestore_assegnato_evento( $event_id ) {
		$user_id = absint( get_post_meta( $event_id, '_mi_manager_user_id', true ) );
		$user = $user_id ? get_user_by( 'id', $user_id ) : false;
		$manager_roles = array( 'mi_group_manager', 'mi_assigned_event_manager' );
		if ( ! $user instanceof WP_User || ! is_email( $user->user_email ) || ! array_intersect( $manager_roles, (array) $user->roles ) ) return null;
		if ( class_exists( 'MI_Access' ) && ( MI_Access::is_suspended( $user_id ) || ! MI_Access::can_access_event( $event_id, $user_id ) ) ) return null;
		return $user;
	}
	public static function destinatari_avvisi_evento( $event_id, ?WP_User $gestore = null ) {
		if ( ! $gestore ) $gestore = self::gestore_assegnato_evento( $event_id );
		$emails = array( self::destinatario_segreteria(), self::destinatario_evento( $event_id ) );
		if ( $gestore instanceof WP_User ) $emails[] = $gestore->user_email;
		$recipients = array();
		foreach ( $emails as $email ) {
			$email = sanitize_email( $email );
			if ( is_email( $email ) ) $recipients[strtolower( $email )] = $email;
		}
		return array_values( $recipients );
	}
	public static function rileva_pubblicazione( $new, $old, $post ) {
		if ( 'publish' === $new && 'publish' !== $old && MI_Event_Post_Type::EVENT_TYPE === $post->post_type ) {
			self::$pubblicazioni[$post->ID] = true;
			update_post_meta( $post->ID, '_mi_publication_notification_pending', '1' );
			// Registra un'alternativa prima degli hook di salvataggio e delle richieste remote.
			wp_schedule_single_event( time() + 120, 'mi_riprova_notifica_pubblicazione', array( (int) $post->ID, 1 ) );
		}
	}
	public static function notifica_pubblicazione( $event_id, $post ) {
		if ( empty( self::$pubblicazioni[$event_id] ) && ! get_post_meta( $event_id, '_mi_publication_notification_pending', true ) ) return;
		if ( 'publish' !== $post->post_status || wp_is_post_revision( $event_id ) || wp_is_post_autosave( $event_id ) ) return;
		if ( get_post_meta( $event_id, '_mi_publication_notified', true ) ) return;
		$result = self::accoda_notifiche_attivazione_evento( $event_id, null, '', '' );
		self::registra_esito_pubblicazione( $event_id, $result );
	}

	private static function registra_esito_pubblicazione( $event_id, $result ) {
		update_post_meta( $event_id, '_mi_publication_notification_error', is_wp_error( $result ) ? $result->get_error_message() : '' );
		if ( ! is_wp_error( $result ) ) { update_post_meta( $event_id, '_mi_publication_notified', '1' ); update_post_meta( $event_id, '_mi_publication_notification_pending', '' ); }
	}

	public static function riprova_pubblicazione( $event_id, $attempt = 1 ) {
		if ( ! get_post_meta( $event_id, '_mi_publication_notification_pending', true ) || 'publish' !== get_post_status( $event_id ) ) return;
		$result = self::accoda_notifiche_attivazione_evento( $event_id, null, '', '' );
		self::registra_esito_pubblicazione( $event_id, $result );
		if ( is_wp_error( $result ) && $attempt < 3 ) wp_schedule_single_event( time() + 300, 'mi_riprova_notifica_pubblicazione', array( (int) $event_id, (int) $attempt + 1 ) );
	}

	public static function recupera_pubblicazione() {
		self::verifica_amministratore( 'mi_recupera_pubblicazione' );
		$event_id = absint( $_POST['event_id'] ?? 0 );
		if ( MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event_id ) || ! MI_Access::can_access_event( $event_id ) ) wp_die( 'Evento non accessibile.', 403 );
		$result = self::accoda_notifiche_attivazione_evento( $event_id, null, '', '' );
		self::registra_esito_pubblicazione( $event_id, $result );
		$message = is_wp_error( $result ) ? $result->get_error_message() : ( $result['count'] ? 'Notifica di pubblicazione preparata in modalità ' . $result['mode'] . '.' : 'La notifica è già presente in coda: nessun duplicato creato.' );
		set_transient( 'mi_recupero_pubblicazione_' . get_current_user_id(), $message, 300 );
		wp_safe_redirect( add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-email-outbox' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public static function modalita() {
		$modalita = strtoupper( (string) get_option( self::OPZIONE_MODALITA, 'ANTEPRIMA' ) );
		return in_array( $modalita, array( 'ANTEPRIMA', 'PROVA', 'OPERATIVO' ), true ) ? $modalita : 'ANTEPRIMA';
	}
	/** Chiamata nella transazione di eliminazione, prima di rimuovere i contatti. */
	public static function accoda_avviso_eliminazione( $event_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT buyer_email FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d AND status IN ('CONFIRMED','PENDING_PAYMENT','TRAVEL_PENDING','WAITLISTED','WAITLIST_OFFERED')", $event_id ), ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Destinatari dell’avviso non disponibili.' );
		$title = html_entity_decode( get_the_title( $event_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$contact = self::destinatario_evento( $event_id );
		$body = '<p>Ti informiamo che l’evento <strong>' . esc_html( $title ) . '</strong> è stato eliminato dal programma e la tua prenotazione non è più attiva.</p><p>Per maggiori ragguagli, scrivi a <a href="mailto:' . esc_attr( $contact ) . '">' . esc_html( $contact ) . '</a>.</p>';
		$snapshot = MI_Modello_Email::crea_istantanea_istituzionale( $event_id, 'Evento eliminato — ' . $title, 'Comunicazione relativa alla tua prenotazione.', $body, "L’evento {$title} è stato eliminato dal programma e la tua prenotazione non è più attiva.\nPer maggiori ragguagli, scrivi a: {$contact}." );
		$snapshot['identita_email']['indirizzo_risposte'] = $contact;
		foreach ( array_unique( array_column( $rows, 'buyer_email' ) ) as $recipient ) {
			if ( ! is_email( $recipient ) ) continue;
			$key = hash( 'sha256', 'event-deleted|' . $event_id . '|' . strtolower( $recipient ) );
			$payload = wp_json_encode( array( 'event_id' => $event_id, 'email_preview' => $snapshot ) );
			if ( false === $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}mi_email_outbox (registration_id,recipient,template_type,origin_key,payload_json,status,created_at) VALUES (0,%s,'EVENT_DELETED_NOTICE',%s,%s,%s,%s)", $recipient, $key, $payload, self::stato_nuova_email( $snapshot ), current_time( 'mysql', true ) ) ) ) throw new RuntimeException( 'Avviso di eliminazione non salvato.' );
		}
		$segreteria = self::destinatario_segreteria();
		if ( ! is_email( $segreteria ) ) throw new RuntimeException( 'Indirizzo della segreteria non valido.' );
		$segreteria_body = '<p>L’evento <strong>' . esc_html( $title ) . '</strong> è stato eliminato dal portale.</p><p>Le prenotazioni, i partecipanti e i dati collegati sono stati rimossi; la pulizia del foglio Google è stata completata prima di questa notifica.</p><p>Questa è una comunicazione interna automatica.</p>';
		$segreteria_snapshot = MI_Modello_Email::crea_istantanea_istituzionale( $event_id, 'Evento eliminato — ' . $title, 'Avviso interno per la segreteria.', $segreteria_body, "L’evento {$title} è stato eliminato dal portale.\nLe prenotazioni, i partecipanti e i dati collegati sono stati rimossi; la pulizia del foglio Google è stata completata.\n\nQuesta è una comunicazione interna automatica." );
		$segreteria_snapshot['identita_email']['indirizzo_risposte'] = $contact;
		foreach ( self::destinatari_avvisi_evento( $event_id ) as $segreteria ) {
		$key = hash( 'sha256', 'event-deleted-secretariat|' . $event_id . '|' . strtolower( $segreteria ) );
		$payload = wp_json_encode( array( 'event_id' => $event_id, 'email_preview' => $segreteria_snapshot ) );
		if ( false === $payload || false === $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}mi_email_outbox (registration_id,recipient,template_type,origin_key,payload_json,status,created_at) VALUES (0,%s,'EVENT_DELETED_SECRETARIAT',%s,%s,%s,%s)", $segreteria, $key, $payload, self::stato_nuova_email( $segreteria_snapshot ), current_time( 'mysql', true ) ) ) ) throw new RuntimeException( 'Avviso alla segreteria non salvato.' );
		}
	}

	/** Avviso interno indipendente dalla presenza di iscritti, con lo stile istituzionale. */
	public static function accoda_avviso_annullamento_segreteria( $event_id, $reason ) {
		global $wpdb;
		$recipient = self::destinatario_segreteria();
		if ( ! is_email( $recipient ) ) return new WP_Error( 'mi_annullamento_destinatario', 'Indirizzo della segreteria non valido.' );
		$title = html_entity_decode( get_the_title( $event_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = "L’evento {$title} è stato annullato. Le iscrizioni sono state annullate e i dati restano nello storico. Gli eventuali rimborsi devono essere gestiti separatamente.";
		$body = '<p>L’evento <strong>' . esc_html( $title ) . '</strong> è stato annullato.</p><p>Le iscrizioni sono state annullate e i dati restano nello storico. Gli eventuali rimborsi devono essere gestiti separatamente.</p>';
		if ( '' !== trim( $reason ) ) { $body .= '<p><strong>Motivo:</strong><br>' . nl2br( esc_html( $reason ) ) . '</p>'; $text .= "\nMotivo: " . $reason; }
		$snapshot = MI_Modello_Email::crea_istantanea_istituzionale( $event_id, 'Evento annullato — ' . $title, 'Avviso interno per la segreteria.', $body, $text );
		$payload = wp_json_encode( array( 'event_id' => $event_id, 'email_preview' => $snapshot ) );
		$status = self::stato_nuova_email( $snapshot );
		if ( false === $payload ) return new WP_Error( 'mi_annullamento_json', 'Avviso alla segreteria non serializzabile.' );
		$count = 0;
		foreach ( self::destinatari_avvisi_evento( $event_id ) as $recipient ) {
		$key = hash( 'sha256', 'event-cancelled-secretariat|' . $event_id . '|' . strtolower( $recipient ) );
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}mi_email_outbox (registration_id,recipient,template_type,origin_key,payload_json,status,created_at) VALUES (0,%s,'EVENT_CANCELLED_SECRETARIAT',%s,%s,%s,%s)", $recipient, $key, $payload, $status, current_time( 'mysql', true ) ) );
		if ( false === $inserted ) return new WP_Error( 'mi_annullamento_archivio', 'Avviso alla segreteria non salvato.' );
		$count += $inserted ? 1 : 0;
		}
		if ( self::email_da_spedire( $status ) ) self::pianifica_spedizione();
		return array( 'ok' => true, 'count' => $count );
	}

	public static function stato_nuova_email( $istantanea ) {
		if ( empty( $istantanea['attivo'] ) ) return 'PREVIEW';
		if ( 'PROVA' === self::modalita() && self::destinatario_prova() ) return 'TEST_PENDING';
		return 'OPERATIVO' === self::modalita() && self::prova_verificata() ? 'PENDING' : 'PREVIEW';
	}


	public static function pianifica_spedizione( $delay = 0 ) {
		$abilitata = ( 'PROVA' === self::modalita() && self::destinatario_prova() ) || ( 'OPERATIVO' === self::modalita() && self::prova_verificata() ) || self::has_portal_test_pending();
		if ( ! $abilitata ) return;
		$when = max( time() + absint( $delay ), (int) get_option( 'mi_email_retry_after', 0 ) );
		$next = wp_next_scheduled( 'mi_spedisci_email_in_coda' );
		if ( $next && $next > $when ) wp_unschedule_event( $next, 'mi_spedisci_email_in_coda' );
		if ( ! $next || $next > $when ) wp_schedule_single_event( $when, 'mi_spedisci_email_in_coda' );
		if ( $when <= time() && class_exists( 'MI_Email_Jobs' ) ) self::tenta_spedizione_immediata();
	}

	/** Il browser riceve la risposta prima di contattare Google. Il cron resta il recupero persistente. */
	public static function tenta_spedizione_immediata() {
		if ( self::$invio_immediato_in_corso ) return;
		$abilitata = ( 'PROVA' === self::modalita() && self::destinatario_prova() ) || ( 'OPERATIVO' === self::modalita() && self::prova_verificata() ) || self::has_portal_test_pending();
		if ( ! $abilitata ) return;
		$now = time();
		if ( (int) get_option( 'mi_email_retry_after', 0 ) > $now ) return;
		$next = wp_next_scheduled( 'mi_spedisci_email_in_coda' );
		if ( $next && $next > $now ) wp_unschedule_event( $next, 'mi_spedisci_email_in_coda' );
		if ( ! $next || $next > $now ) wp_schedule_single_event( $now, 'mi_spedisci_email_in_coda' );
		self::$invio_immediato_in_corso = true;
		add_action( 'shutdown', static function () {
			if ( class_exists( 'MI_Email_Jobs' ) ) MI_Email_Jobs::dispatch();
			elseif ( ! wp_doing_cron() ) spawn_cron();
		} );
	}

	public static function email_da_spedire( $status ) {
		return in_array( $status, array( 'PENDING', 'TEST_PENDING' ), true );
	}

	private static function has_portal_test_pending() {
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE template_type='PORTAL_COMMUNICATION_TEST' AND status IN ('TEST_PENDING','TEST_SENDING') AND attempts < 5" ) > 0;
	}

	/** Prepara la comunicazione privata destinata al solo gestore responsabile dell'evento. */
	public static function accoda_notifica_gestore_evento( $event_id, ?WP_User $gestore, $sheet_url, $email_segreteria = '' ) {
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( $event_id ); if ( is_wp_error( $lease ) ) return $lease; }
		$event_id = absint( $event_id );
		$recipient = sanitize_email( $gestore ? $gestore->user_email : $email_segreteria );
		if ( ! $event_id || ! is_email( $recipient ) || 'publish' !== get_post_status( $event_id ) ) return new WP_Error( 'mi_notifica_gestore_non_valida', 'Evento non pubblicato o destinatario non valido.' );
		$snapshot = MI_Modello_Email::crea_istantanea_pubblicazione_evento( $event_id );
		$payload_json = wp_json_encode( array( 'event_id' => $event_id, 'template_type' => 'EVENT_MANAGER_READY', 'email_preview' => $snapshot ) );
		if ( false === $payload_json ) return new WP_Error( 'mi_notifica_gestore_json', 'Comunicazione al gestore non serializzabile.' );
		global $wpdb;
		$status = self::stato_nuova_email( $snapshot );
		$origin_key = hash( 'sha256', 'event-published|' . $event_id . '|' . strtolower( $recipient ) );
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}mi_email_outbox (registration_id,recipient,template_type,origin_key,payload_json,status,created_at) VALUES (0,%s,'EVENT_MANAGER_READY',%s,%s,%s,%s)", $recipient, $origin_key, $payload_json, $status, current_time( 'mysql', true ) ) );
		if ( false === $inserted ) return new WP_Error( 'mi_notifica_gestore_archivio', 'Comunicazione al gestore non salvata.' );
		if ( self::email_da_spedire( $status ) && $inserted ) self::pianifica_spedizione();
		return array( 'ok' => true, 'count' => $inserted ? 1 : 0, 'mode' => 'TEST_PENDING' === $status ? 'PROVA' : ( 'PENDING' === $status ? 'OPERATIVO' : 'ANTEPRIMA' ) );
	}

	/** Conferma di attivazione a segreteria, gruppo e operatore assegnato, senza duplicati. */
	public static function accoda_notifiche_attivazione_evento( $event_id, ?WP_User $gestore, $sheet_url, $email_segreteria ) {
		$email_segreteria = self::destinatario_segreteria();
		if ( ! is_email( $email_segreteria ) ) return new WP_Error( 'mi_notifica_segreteria_non_valida', 'L’indirizzo email della parrocchia non è valido.' );
		$destinatari = self::destinatari_avvisi_evento( $event_id, $gestore );
		$conteggio = 0;
		$modalita = 'ANTEPRIMA';
		foreach ( $destinatari as $destinatario ) {
			$result = self::accoda_notifica_gestore_evento( $event_id, null, $sheet_url, $destinatario );
			if ( is_wp_error( $result ) ) return $result;
			$conteggio += absint( $result['count'] ?? 0 );
			$modalita = (string) ( $result['mode'] ?? $modalita );
		}
		return array( 'ok' => true, 'count' => $conteggio, 'mode' => $modalita );
	}

	public static function accoda_comunicazione_operativa( array $payload ) {
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( absint( $payload['event_id'] ?? 0 ) ); if ( is_wp_error( $lease ) ) return $lease; }
		global $wpdb;
		$communication_id = sanitize_key( (string) ( $payload['communication_id'] ?? '' ) );
		$event_id = absint( $payload['event_id'] ?? 0 );
		$template_type = strtoupper( sanitize_key( (string) ( $payload['template_type'] ?? '' ) ) );
		$message = sanitize_textarea_field( (string) ( $payload['message'] ?? '' ) );
		$allow_operational = ! empty( $payload['allow_operational'] );
		$test_only = ! empty( $payload['test_only'] );
		$test_recipient = sanitize_email( (string) ( $payload['test_recipient'] ?? '' ) );
		if ( $test_only && ! is_email( $test_recipient ) ) return new WP_Error( 'mi_operational_email_test_recipient', 'Indirizzo email di prova non valido.', array( 'status' => 400 ) );
		$custom_types = get_option( 'mi_custom_communication_types', array() );
		$custom_allowed = is_array( $custom_types ) && isset( $custom_types[ $template_type ] ) && preg_match( '/^CUSTOM_[A-Z0-9_]{1,24}$/', $template_type );
		if ( ! $communication_id || ! $event_id || ( ! in_array( $template_type, array( 'PRE_DEPARTURE_REMINDER', 'DEPOSIT_REMINDER', 'BALANCE_REMINDER', 'EVENT_NOTICE', 'EVENT_CANCELLATION', 'REGISTRATION_CANCELLATION', 'MATERIAL_DELIVERY', 'PARTICIPATION_CANCELLATION_REMINDER' ), true ) && ! $custom_allowed ) ) return new WP_Error( 'mi_operational_email_invalid', 'Comunicazione non valida.', array( 'status' => 400 ) );
		if ( ( 'PRE_DEPARTURE_REMINDER' === $template_type || $custom_allowed ) && ! $message ) return new WP_Error( 'mi_operational_email_message_required', 'La comunicazione richiede un testo.', array( 'status' => 400 ) );
		if ( is_array( $payload['recipients'] ?? null ) && count( $payload['recipients'] ) > 1000 ) return new WP_Error( 'mi_email_recipients', 'Oltre 1000 prenotazioni: nessuna comunicazione preparata.' );
		$recipient_payload = is_array( $payload['recipients'] ?? null ) ? $payload['recipients'] : array();
		$recipient_state = array();
		foreach ( $recipient_payload as $recipient ) {
			if ( ! is_array( $recipient ) ) continue;
			$order_code = sanitize_text_field( (string) ( $recipient['order_code'] ?? '' ) );
			if ( ! $order_code || strlen( $order_code ) > 64 ) continue;
			$recipient_state[ $order_code ] = array(
				'paid_cents'    => max( 0, absint( $recipient['paid_cents'] ?? 0 ) ),
				'balance_cents' => max( 0, absint( $recipient['balance_cents'] ?? 0 ) ),
			);
		}
		if ( $test_only ) $recipient_state = array_slice( $recipient_state, 0, 1, true );
		if ( ! $recipient_state ) return array( 'ok' => true, 'count' => 0, 'mode' => self::modalita(), 'message' => 'Nessun destinatario corrisponde ai criteri.' );
		$event = MI_Registration_Service::public_event( $event_id, true );
		if ( is_wp_error( $event ) ) return $event;
		$lock_name = 'mi_email_' . substr( hash( 'sha256', $communication_id ), 0, 48 );
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock_name ) ) ) return new WP_Error( 'mi_operational_email_busy', 'Preparazione momentaneamente occupata.', array( 'status' => 503 ) );
		try {
			$idempotency_key = 'mi_operational_email_' . hash( 'sha256', $communication_id );
			$effective_mode = $test_only ? 'PROVA_PUNTUALE' : ( $allow_operational && in_array( self::modalita(), array( 'PROVA', 'OPERATIVO' ), true ) && 'publish' === get_post_status( $event_id ) ? self::modalita() : 'ANTEPRIMA' );
			$previous = get_transient( $idempotency_key );
			if ( $previous ) return array( 'ok' => true, 'count' => 0, 'mode' => $effective_mode, 'outbox_id' => is_array( $previous ) ? absint( $previous['outbox_id'] ?? 0 ) : 0, 'origin_keys' => is_array( $previous ) ? ( $previous['origin_keys'] ?? array() ) : array(), 'message' => 'Questa comunicazione era già stata preparata.' );
			$registrations_table = $wpdb->prefix . 'mi_registrations';
			$outbox_table = $wpdb->prefix . 'mi_email_outbox';
			$order_codes = array_keys( $recipient_state );
			$placeholders = implode( ',', array_fill( 0, count( $order_codes ), '%s' ) );
			$query_args = array_merge( array( $event_id ), $order_codes );
			if ( in_array( $template_type, array( 'EVENT_CANCELLATION', 'REGISTRATION_CANCELLATION' ), true ) ) $status_filter = "status='CANCELLED'";
			elseif ( in_array( $template_type, array( 'BALANCE_REMINDER', 'DEPOSIT_REMINDER' ), true ) ) $status_filter = "status IN ('CONFIRMED','PENDING_PAYMENT','WAITLISTED','WAITLIST_OFFERED') AND capacity_released_at IS NULL";
			else $status_filter = "status IN ('CONFIRMED','PENDING_PAYMENT','TRAVEL_PENDING','WAITLISTED','WAITLIST_OFFERED') AND capacity_released_at IS NULL";
			$registrations = $wpdb->get_results( $wpdb->prepare( "SELECT id,order_code,status,economic_mode,buyer_first_name,buyer_last_name,buyer_email,total_qty,total_cents,initial_due_cents,balance_cents,payment_methods_json,payment_deadline_at FROM {$registrations_table} WHERE event_id=%d AND order_code IN ({$placeholders}) AND {$status_filter} ORDER BY id LIMIT 1000", $query_args ), ARRAY_A );
			if ( $wpdb->last_error ) return new WP_Error( 'mi_email_read', 'Prenotazioni non disponibili.' );
			if ( 'EVENT_CANCELLATION' === $template_type && count( $registrations ) !== count( $order_codes ) ) return new WP_Error( 'mi_email_cancel_incomplete', 'Annullamento dei destinatari non completato.' );
			try { $positions = MI_Payment_Ledger::positions( $registrations ); }
			catch ( Throwable $error ) { return new WP_Error( 'mi_email_balance', 'Saldi non disponibili. Comunicazione non preparata.' ); }
			$now = current_time( 'mysql', true );
			$count = 0; $excluded = 0;
			$outbox_id = 0;
			$origin_keys = array();
			try {
				if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new RuntimeException( 'Transazione non disponibile.' );
				foreach ( $registrations as $registration ) {
					if ( ! $test_only && ! is_email( trim( (string) $registration['buyer_email'] ) ) ) { $excluded++; continue; }
					if ( in_array( $registration['status'], array( 'WAITLISTED', 'WAITLIST_OFFERED' ), true ) && ! in_array( $template_type, array( 'EVENT_CANCELLATION', 'PARTICIPATION_CANCELLATION_REMINDER' ), true ) ) continue;
					$position = $positions[$registration['id']];
					$paid = $position['effective_paid'];
					$balance = $position['managed'] ? $position['effective_balance'] : 0;
					if ( 'BALANCE_REMINDER' === $template_type && $balance < 1 ) continue;
					$deposit_missing = $position['individual_known'] ? (int) ( $position['individual_deposit_missing'] ?? max( 0, (int) $position['individual_deposit_due'] - (int) $paid ) ) : max( 0, (int) $registration['initial_due_cents'] - (int) $paid );
					if ( 'DEPOSIT_REMINDER' === $template_type && ( ! $position['managed'] || $deposit_missing < 1 ) ) continue;
					$economic = $test_only
						? array( 'total_cents' => 25000, 'initial_due_cents' => 5000, 'balance_cents' => 20000, 'payment_methods' => array( 'BANK_TRANSFER' ) )
						: array( 'total_cents' => (int) $position['effective_total'], 'initial_due_cents' => $position['individual_known'] ? (int) $position['individual_deposit_due'] : (int) $registration['initial_due_cents'], 'balance_cents' => $balance, 'payment_methods' => json_decode( (string) $registration['payment_methods_json'], true ) ?: array() );
					$status_labels = array( 'CONFIRMED' => 'Confermata', 'PENDING_PAYMENT' => 'Da pagare', 'TRAVEL_PENDING' => 'In attesa di passaggio', 'WAITLISTED' => 'Lista d’attesa', 'WAITLIST_OFFERED' => 'Posto proposto' );
					if ( 'DEPOSIT_REMINDER' === $template_type && ! $test_only ) $economic['initial_due_cents'] = $deposit_missing;
					$participant_count = (int) $position['individual_people_count'] > 0 ? (int) $position['individual_active_count'] : (int) $registration['total_qty'];
					// La scadenza della prenotazione è UTC e può differire da quella generale.
					// Se assente/non valida, il modello invita a contattare l'organizzazione.
					$registration_event = $event;
					$registration_event['payment_deadline_at'] = '';
					$deadline = (string) ( $registration['payment_deadline_at'] ?? '' );
					$deadline_date = $deadline ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $deadline, new DateTimeZone( 'UTC' ) ) : false;
					if ( $deadline_date && $deadline_date->format( 'Y-m-d H:i:s' ) === $deadline ) {
						$registration_event['payment_deadline_at'] = wp_date( 'Y-m-d\TH:i', $deadline_date->getTimestamp(), wp_timezone() );
					}
					$values = $test_only
						? MI_Modello_Email::valori_ordine( $registration_event, 'PROVA', 'Esempio', 1, 'Persona di prova', $economic, array(), 'Persona' )
						: MI_Modello_Email::valori_ordine( $registration_event, $registration['order_code'], $status_labels[ $registration['status'] ] ?? $registration['status'], $participant_count, trim( $registration['buyer_first_name'] . ' ' . $registration['buyer_last_name'] ), $economic, array(), $registration['buyer_first_name'] );
					$status_url = $test_only ? '' : MI_Portal::status_url( $registration['id'], $registration['order_code'], $registration['buyer_email'] );
					if ( ! $test_only && 'BALANCE_REMINDER' === $template_type ) $status_url = MI_Portal::balance_url( $registration['id'], $registration['order_code'], $registration['buyer_email'] );
					$live_participant_links = 'PARTICIPATION_CANCELLATION_REMINDER' === $template_type && 'OPERATIVO' === $effective_mode;
					$participant_management = $live_participant_links ? MI_Registration_Service::create_participant_cancellation_links( (int) $registration['id'] ) : array();
					$live_participant_links = $live_participant_links && (bool) $participant_management;
					if ( 'PARTICIPATION_CANCELLATION_REMINDER' === $template_type ) $status_url = '';
					$snapshot = MI_Modello_Email::crea_istantanea_operativa( $event_id, $values, $template_type, $message, $status_url, $participant_management, $live_participant_links, 'OPERATIVO' !== $effective_mode );
					$status = $test_only ? 'TEST_PENDING' : ( 'ANTEPRIMA' !== $effective_mode ? self::stato_nuova_email( $snapshot ) : 'PREVIEW' );
					$payload_json = wp_json_encode( array( 'communication_id' => $communication_id, 'event_id' => $event_id, 'event_title' => $event['title'], 'order_code' => $test_only ? 'PROVA' : $registration['order_code'], 'template_type' => $template_type, 'portal_test_copy' => $test_only, 'test_recipient' => $test_only ? $test_recipient : '', 'email_preview' => $snapshot ) );
					if ( false === $payload_json ) throw new RuntimeException( 'Coda email non salvata.' );
					$origin_key = hash( 'sha256', $communication_id . '|' . ( $test_only ? 'test' : $registration['id'] ) );
					$origin_keys[] = $origin_key;
					$outbox_type = $test_only ? 'PORTAL_COMMUNICATION_TEST' : $template_type;
					$outbox_registration_id = $test_only ? 0 : (int) $registration['id'];
					$outbox_recipient = $test_only ? $test_recipient : trim( (string) $registration['buyer_email'] );
					$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$outbox_table} (registration_id,recipient,template_type,origin_key,payload_json,status,created_at) VALUES (%d,%s,%s,%s,%s,%s,%s)", $outbox_registration_id, $outbox_recipient, $outbox_type, $origin_key, $payload_json, $status, $now ) );
					if ( false === $inserted ) throw new RuntimeException( 'Coda email non salvata.' );
					if ( $inserted ) $count++;
					if ( $test_only ) {
						$outbox_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$outbox_table} WHERE origin_key=%s AND template_type='PORTAL_COMMUNICATION_TEST'", $origin_key ) );
						if ( ! $outbox_id || $wpdb->last_error ) throw new RuntimeException( 'Identificativo della prova non disponibile.' );
					}
				}
				if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma non ricevuta.' );
			} catch ( Throwable $error ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'mi_operational_email_storage', 'Non è stato possibile preparare la comunicazione.', array( 'status' => 500 ) );
			}
			// origin_key univoca nella coda confermata protegge i nuovi tentativi anche senza cache o cron.
			try { set_transient( $idempotency_key, array( 'outbox_id' => $outbox_id, 'origin_keys' => $origin_keys ), DAY_IN_SECONDS ); } catch ( Throwable $error ) {}
			try { if ( ( $test_only || 'ANTEPRIMA' !== $effective_mode ) && $count ) self::pianifica_spedizione(); } catch ( Throwable $error ) {}
			return array( 'ok' => true, 'count' => $count, 'excluded' => $excluded, 'mode' => $effective_mode, 'outbox_id' => $outbox_id, 'origin_keys' => $origin_keys, 'message' => 'Comunicazione preparata nella coda WordPress.' );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	public static function aggiungi_pagina() {
		add_submenu_page( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE, 'Spedizione email', 'Spedizione email', 'manage_options', 'mi-spedizione-email', array( __CLASS__, 'mostra_pagina' ) );
	}

	public static function mostra_pagina() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		$modalita = self::modalita();
		$destinatario = (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' );
		$verificata = self::prova_verificata();
		$esito = isset( $_GET['mi_esito'] ) ? sanitize_key( wp_unslash( $_GET['mi_esito'] ) ) : '';
		$dettaglio_prova = get_transient( 'mi_esito_email_prova_' . get_current_user_id() );
		delete_transient( 'mi_esito_email_prova_' . get_current_user_id() );
		$messaggi = array(
			'salvato' => array( 'success', 'Impostazioni salvate.' ),
			'prova_ok' => array( 'success', 'Email sintetica di prova inviata. La modalità operativa può ora essere selezionata.' ),
			'prova_ko' => array( 'error', $dettaglio_prova ? 'Invio di prova non riuscito: ' . $dettaglio_prova : 'Invio di prova non riuscito tramite Apps Script.' ),
			'indirizzo' => array( 'error', 'Inserisci un indirizzo di prova valido.' ),
			'prova_richiesta' => array( 'error', 'La modalità operativa richiede prima un invio di prova riuscito verso l’indirizzo attuale.' ),
		);
		if ( isset( $messaggi[ $esito ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $messaggi[ $esito ][0] ) . '"><p>' . esc_html( $messaggi[ $esito ][1] ) . '</p></div>';
		}
		?>
		<div class="wrap"><h1>Spedizione email</h1>
		<p>La configurazione iniziale è <strong>Anteprima</strong>: nessun messaggio parte. In <strong>Prova</strong> tutte le email reali generate dal plugin — comprese quelle destinate alla parrocchia e ai gestori — vengono inviate esclusivamente all’indirizzo dedicato. <strong>Operativo</strong> usa invece i destinatari reali.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="mi_salva_spedizione_email"><?php wp_nonce_field( 'mi_salva_spedizione_email' ); ?>
		<table class="form-table"><tr><th scope="row"><label for="mi_modalita_spedizione_email">Modalità</label></th><td><select id="mi_modalita_spedizione_email" name="mi_modalita_spedizione_email">
		<option value="ANTEPRIMA" <?php selected( $modalita, 'ANTEPRIMA' ); ?>>Anteprima — nessun invio</option><option value="PROVA" <?php selected( $modalita, 'PROVA' ); ?>>Prova — tutte le email all’indirizzo dedicato</option><option value="OPERATIVO" <?php selected( $modalita, 'OPERATIVO' ); ?> <?php disabled( ! $verificata ); ?>>Operativo — destinatari reali</option></select>
		<?php if ( ! $verificata ) : ?><p class="description">Operativo resterà bloccato finché la prova non riuscirà.</p><?php endif; ?></td></tr>
		<tr><th scope="row"><label for="mi_destinatario_prova_email">Destinatario della prova</label></th><td><input class="regular-text" type="email" id="mi_destinatario_prova_email" name="mi_destinatario_prova_email" value="<?php echo esc_attr( $destinatario ); ?>" autocomplete="off"><p class="description">In modalità Prova riceve tutte le email generate dal plugin. Oggetto e contenuto mostrano a chi sarebbe stata destinata ciascuna email. In modalità Operativo non viene usato.</p></td></tr></table><?php submit_button( 'Salva impostazioni' ); ?></form>
		<hr><h2>Collaudo controllato</h2><p>Il messaggio contiene soltanto nomi, codici e dati dimostrativi.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Inviare ora una email sintetica all’indirizzo di prova?');"><input type="hidden" name="action" value="mi_invia_email_prova"><?php wp_nonce_field( 'mi_invia_email_prova' ); ?><?php submit_button( 'Invia email sintetica di prova', 'secondary' ); ?></form></div>
		<?php
	}

	public static function salva_impostazioni() {
		self::verifica_amministratore( 'mi_salva_spedizione_email' );
		$destinatario_grezzo = trim( (string) wp_unslash( $_POST['mi_destinatario_prova_email'] ?? '' ) );
		$destinatario = sanitize_email( $destinatario_grezzo );
		if ( $destinatario_grezzo && ( ! $destinatario || ! is_email( $destinatario ) ) ) {
			self::torna_alla_pagina( 'indirizzo' );
		}
		if ( $destinatario !== (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' ) ) {
			delete_option( self::OPZIONE_PROVA_VERIFICATA );
		}
		update_option( self::OPZIONE_DESTINATARIO_PROVA, $destinatario, false );
		$modalita = strtoupper( sanitize_key( wp_unslash( $_POST['mi_modalita_spedizione_email'] ?? 'ANTEPRIMA' ) ) );
		$modalita = in_array( $modalita, array( 'ANTEPRIMA', 'PROVA', 'OPERATIVO' ), true ) ? $modalita : 'ANTEPRIMA';
		if ( 'PROVA' === $modalita && ! is_email( $destinatario ) ) self::torna_alla_pagina( 'indirizzo' );
		if ( 'OPERATIVO' === $modalita && ! self::prova_verificata( $destinatario ) ) {
			update_option( self::OPZIONE_MODALITA, 'PROVA', false );
			self::torna_alla_pagina( 'prova_richiesta' );
		}
		update_option( self::OPZIONE_MODALITA, $modalita, false );
		self::pianifica_spedizione();
		self::torna_alla_pagina( 'salvato' );
	}

	public static function invia_prova() {
		self::verifica_amministratore( 'mi_invia_email_prova' );
		$destinatario = (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' );
		if ( ! is_email( $destinatario ) ) {
			self::torna_alla_pagina( 'indirizzo' );
		}
		$oggetto = 'Prova Modulo Iscrizioni — ' . wp_date( 'd/m/Y H:i' );
		$istantanea = array(
			'preheader' => 'Anteprima sintetica del nuovo modello email.',
			'html' => '<p>Questa è una <strong>email sintetica di prova</strong> del Modulo Iscrizioni.</p><p>Evento: Evento dimostrativo<br>Iscrizione a nome di: Persona Esempio</p><p>Nessun dato di un’iscrizione reale è stato utilizzato.</p>',
			'testo' => "Questa è una email sintetica di prova del Modulo Iscrizioni.\nEvento: Evento dimostrativo\nIscrizione a nome di: Persona Esempio\nNessun dato di un’iscrizione reale è stato utilizzato.",
			'footer' => 'Un saluto dall’organizzazione.',
			'identita' => array( 'nome_attivita' => 'Attività dimostrativa', 'primary_color' => '#151b38', 'secondary_color' => '#337ab7', 'primary_text_color' => '#ffffff', 'secondary_text_color' => '#ffffff' ),
			'identita_email' => array(),
			'evento' => array( 'titolo' => 'Evento dimostrativo', 'url' => '' ),
			'identificativo' => array( 'modalita' => 'NONE', 'codice' => '' ),
		);
		$corpo = MI_Modello_Email::componi_html( $istantanea );
		self::$corpo_testo = MI_Modello_Email::componi_testo( $istantanea );
		add_action( 'phpmailer_init', array( __CLASS__, 'incorpora_codice' ) );
		// La prova usa esclusivamente il canale firmato verso Apps Script.
		// La posta generale di WordPress e degli altri plugin resta invariata.
		$risposta_workspace = MI_Workspace_Client::request( 'INVIA_EMAIL_PROVA', array( 'delivery_key' => hash( 'sha256', home_url() . '|settings-test|' . bin2hex( random_bytes( 16 ) ) ), 'indirizzo_mittente' => MI_Modello_Email::EMAIL_SEGRETERIA, 'destinatario' => $destinatario, 'oggetto' => $oggetto, 'html' => $corpo, 'testo' => self::$corpo_testo ) );
		$inviata = ! is_wp_error( $risposta_workspace ) && ! empty( $risposta_workspace['ok'] );
		remove_action( 'phpmailer_init', array( __CLASS__, 'incorpora_codice' ) );
		self::$corpo_testo = '';
		if ( $inviata ) {
			update_option( self::OPZIONE_PROVA_VERIFICATA, self::impronta_destinatario( $destinatario ), false );
			self::torna_alla_pagina( 'prova_ok' );
		}
		if ( is_wp_error( $risposta_workspace ) ) {
			set_transient( 'mi_esito_email_prova_' . get_current_user_id(), sanitize_text_field( $risposta_workspace->get_error_message() ), MINUTE_IN_SECONDS );
		}
		self::torna_alla_pagina( 'prova_ko' );
	}

	public static function riaccoda_email() {
		self::verifica_amministratore( 'mi_riaccoda_email' );
		$id = absint( $_POST['email_id'] ?? 0 );
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		// Un'unica scrittura preserva la modalita anche con richieste concorrenti.
		// La chiave di consegna resta invariata: non aggirare le ricevute remote.
		$updated = $id ? $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = CASE WHEN status = 'TEST_FAILED' THEN 'TEST_PENDING' ELSE 'PENDING' END, attempts = 0, last_error = NULL, sent_at = NULL, processing_started_at = NULL WHERE id = %d AND status IN ('FAILED', 'TEST_FAILED')", $id ) ) : 0;
		$esito = false === $updated ? 'riaccoda_errore' : ( 1 === $updated ? 'riaccodata' : 'riaccoda_non_disponibile' );
		if ( 1 === $updated ) {
			try { self::pianifica_spedizione(); } catch ( Throwable $error ) { /* La coda persistente viene recuperata dal cron. */ }
		}
		wp_safe_redirect( add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-email-outbox', 'email_id' => $id, 'mi_esito' => $esito ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public static function rigenera_email() {
		self::verifica_amministratore( 'mi_rigenera_email' );
		$id = absint( $_POST['email_id'] ?? 0 );
		$result = self::rigenera_consegna( $id, sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ), '1' === ( $_POST['verified_not_sent'] ?? '' ) );
		set_transient( 'mi_rigenerazione_email_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : 'Nuova consegna #' . $result . ' preparata. La consegna precedente resta conservata nel registro.', MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-email-outbox', 'email_id' => is_wp_error( $result ) ? $id : $result ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/** Nuova identità di consegna solo dopo verifica umana: i retry ordinari conservano la vecchia. */
	public static function rigenera_consegna( $id, $reason, $verified ) {
		if ( ! current_user_can( 'manage_options' ) || MI_Access::is_suspended() ) return new WP_Error( 'mi_forbidden', 'Operazione non consentita.' );
		$reason = trim( sanitize_textarea_field( $reason ) );
		if ( true !== $verified || '' === $reason || mb_strlen( $reason ) > 500 ) return new WP_Error( 'mi_verification_required', 'Conferma il mancato invio e indica il motivo della verifica (massimo 500 caratteri).' );
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		$id = absint( $id );
		$origin = hash( 'sha256', 'manual-email-regeneration|' . $id );
		$seed = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( $wpdb->last_error || ! $seed ) return new WP_Error( 'mi_regeneration_failed', 'Email non disponibile.' );
		$seed_payload = json_decode( (string) $seed['payload_json'], true );
		$lease_id = ! empty( $seed['registration_id'] ) ? MI_Event_Deletion::registration_event( $seed['registration_id'] ) : absint( $seed_payload['event_id'] ?? 0 );
		if ( ! $lease_id || ! MI_Access::can_access_event( $lease_id ) ) return new WP_Error( 'mi_forbidden', 'Evento non accessibile.' );
		if ( in_array( $seed['template_type'], array( 'EVENT_DELETED_NOTICE', 'EVENT_DELETED_SECRETARIAT' ), true ) ) {
			if ( 'done' !== ( MI_Event_Deletion::job( $lease_id )['stage'] ?? '' ) ) return new WP_Error( 'mi_deletion_pending', 'Attendi il completamento dell’eliminazione.' );
			$lease_id = 0;
		} else {
			$lease = MI_Event_Deletion::enter( $lease_id );
			if ( is_wp_error( $lease ) ) { MI_Event_Deletion::release( $lease_id ); return $lease; }
		}
		try {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new RuntimeException( 'Impossibile avviare la rigenerazione.' );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $id ), ARRAY_A );
			if ( $wpdb->last_error || ! $row ) throw new RuntimeException( 'Email non disponibile.' );
			$payload = json_decode( (string) $row['payload_json'], true );
			if ( ! is_array( $payload ) ) throw new RuntimeException( 'Contenuto email non valido: rigenerazione annullata.' );
			$event_id = ! empty( $row['registration_id'] ) ? MI_Event_Deletion::registration_event( $row['registration_id'] ) : absint( $payload['event_id'] ?? 0 );
			if ( ! $event_id || ! MI_Access::can_access_event( $event_id ) ) throw new RuntimeException( 'Evento non accessibile.' );
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE origin_key = %s", $origin ) );
			if ( $wpdb->last_error ) throw new RuntimeException( 'Verifica della consegna precedente non riuscita.' );
			if ( $existing ) {
				if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma della rigenerazione non riuscita.' );
				return (int) $existing;
			}
			if ( ! in_array( $row['status'], array( 'FAILED', 'TEST_FAILED' ), true ) ) throw new RuntimeException( 'Rigenerazione disponibile soltanto per email in stato FAILED o TEST_FAILED. Attendi la conclusione dei tentativi in corso.' );
			$audit = array( 'source_id' => $id, 'operator_id' => get_current_user_id(), 'verified_at' => gmdate( 'Y-m-d H:i:s' ), 'reason' => $reason );
			$payload['delivery_regeneration'] = $audit;
			if ( false === $wpdb->insert( $table, array( 'registration_id' => (int) $row['registration_id'], 'recipient' => $row['recipient'], 'template_type' => $row['template_type'], 'payload_json' => wp_json_encode( $payload ), 'status' => 'TEST_FAILED' === $row['status'] ? 'TEST_PENDING' : 'PENDING', 'origin_key' => $origin, 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) ) ) throw new RuntimeException( 'Creazione della nuova consegna non riuscita.' );
			$new_id = (int) $wpdb->insert_id;
			$payload['delivery_regeneration']['replacement_id'] = $new_id;
			if ( 1 !== $wpdb->update( $table, array( 'status' => 'SUPERSEDED', 'payload_json' => wp_json_encode( $payload ), 'last_error' => 'Mancato invio verificato: sostituita dalla consegna #' . $new_id, 'processing_started_at' => null ), array( 'id' => $id, 'status' => $row['status'] ) ) ) throw new RuntimeException( 'Registrazione della sostituzione non riuscita.' );
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma della rigenerazione non riuscita.' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'mi_regeneration_failed', $error->getMessage() );
		} finally {
			if ( $lease_id ) MI_Event_Deletion::release( $lease_id );
		}
		try { self::pianifica_spedizione(); } catch ( Throwable $error ) { /* Il cron recupera la coda persistente. */ }
		return $new_id;
	}


	public static function spedisci_coda( $portal_test_id = 0 ) {
		global $wpdb;
		$lock = 'mi_mail_worker_' . md5( $wpdb->prefix );
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) return;
		try {
			if ( ! $portal_test_id && class_exists( 'MI_Email_Jobs' ) ) MI_Email_Jobs::prepare_next();
			if ( (int) get_option( 'mi_email_retry_after', 0 ) > time() ) { self::pianifica_spedizione(); return; }
			self::elabora_coda( absint( $portal_test_id ) );
		} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}

	private static function elabora_coda( $portal_test_id ) {
		$started = microtime( true );
		$deliveries = array(); $leases = array();
		try {
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		$stale = gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'PENDING', processing_started_at = NULL WHERE status = 'SENDING' AND processing_started_at < %s", $stale ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'TEST_PENDING', processing_started_at = NULL WHERE status = 'TEST_SENDING' AND processing_started_at < %s", $stale ) );
		// I tentativi interrotti restano visibili e possono essere ripresi manualmente.
		$wpdb->query( "UPDATE {$table} SET status = 'FAILED', processing_started_at = NULL, last_error = 'Tentativi esauriti; verifica l esito prima di riaccodare.' WHERE status = 'PENDING' AND attempts >= 5" );
		$wpdb->query( "UPDATE {$table} SET status = 'TEST_FAILED', processing_started_at = NULL, last_error = 'Tentativi esauriti; verifica l esito prima di riaccodare.' WHERE status = 'TEST_PENDING' AND attempts >= 5" );
		$modalita = self::modalita();
		$destinatario_prova = self::destinatario_prova();
		$operativo = 'OPERATIVO' === $modalita && self::prova_verificata();
		$portal_test_pending = self::has_portal_test_pending();
		if ( 'ANTEPRIMA' === $modalita && ! $portal_test_pending ) return;
		if ( 'PROVA' === $modalita && ! $destinatario_prova && ! $portal_test_pending ) return;
		if ( 'OPERATIVO' === $modalita && ! $operativo && ! $portal_test_pending ) return;
		// La modalità selezionata decide il canale: un invio di prova non può
		// raggiungere la casella di prova dopo il passaggio in Operativo.
		$stati = 'OPERATIVO' === $modalita && $operativo ? array( "'PENDING'" ) : ( 'PROVA' === $modalita && $destinatario_prova ? array( "'TEST_PENDING'" ) : array() );
		$portal_test_clause = "(status='TEST_PENDING' AND template_type='PORTAL_COMMUNICATION_TEST')";
		$status_filter = $stati ? '(status IN (' . implode( ',', $stati ) . ') OR ' . $portal_test_clause . ')' : $portal_test_clause;
		$runnable_filter = $status_filter;
		// La prova mantiene la priorità anche durante una campagna già avviata.
		if ( ! $portal_test_id && $portal_test_pending ) $portal_test_id = absint( $wpdb->get_var( "SELECT id FROM {$table} WHERE template_type='PORTAL_COMMUNICATION_TEST' AND status='TEST_PENDING' AND attempts < 5 ORDER BY id ASC LIMIT 1" ) );
		// Una prova esplicita elabora soltanto la propria riga persistente, senza attendere il lotto massivo.
		if ( $portal_test_id ) $status_filter = $wpdb->prepare( "(id=%d AND status='TEST_PENDING' AND template_type='PORTAL_COMMUNICATION_TEST')", $portal_test_id );
		// Esamina gli eventi sospesi senza consumare il limite di dieci invii.
		// Registra il punto raggiunto tra scansioni limitate, così un'eliminazione estesa
		// non ritarda gli eventi successivi. Una pagina vuota riporta la scansione all'inizio.
		$cursor_key = 'mi_email_scan_cursor_' . strtolower( $modalita );
		$cursor = $portal_test_id ? 0 : absint( get_option( $cursor_key, 0 ) ); $processed = 0; $blocked_events = array();
		for ( $batch = 0; $batch < 10 && $processed < 10 && microtime( true ) - $started < 10; $batch++ ) {
		$after = $cursor ? $wpdb->prepare( ' AND id > %d', $cursor ) : '';
		$righe = $wpdb->get_results( "SELECT id, registration_id, recipient, template_type, payload_json, attempts, status FROM {$table} WHERE {$status_filter} AND attempts < 5{$after} ORDER BY id ASC LIMIT 10", ARRAY_A );
		if ( $wpdb->last_error ) break;
		if ( ! $righe ) { $cursor = 0; break; }
		foreach ( $righe as $riga ) {
			if ( $processed >= 10 || microtime( true ) - $started >= 10 ) break;
			$cursor = absint( $riga['id'] );
			$event_payload = json_decode( (string) $riga['payload_json'], true );
			$event_id = ! empty( $riga['registration_id'] ) ? MI_Event_Deletion::registration_event( $riga['registration_id'] ) : absint( $event_payload['event_id'] ?? 0 );
			if ( ! $event_id ) {
				$wpdb->update( $table, array( 'status' => 'TEST_PENDING' === $riga['status'] ? 'TEST_FAILED' : 'FAILED', 'last_error' => 'Iscrizione o evento non più disponibile. Nessun invio effettuato.' ), array( 'id' => $riga['id'], 'status' => $riga['status'] ) );
				continue;
			}
			if ( (int) get_option( 'mi_email_event_retry_' . $event_id, 0 ) > time() ) { $blocked_events[$event_id] = true; continue; }
			if ( isset( $blocked_events[$event_id] ) ) continue;
			if ( in_array( $riga['template_type'], array( 'EVENT_DELETED_NOTICE', 'EVENT_DELETED_SECRETARIAT' ), true ) ) {
				if ( 'done' !== ( MI_Event_Deletion::job( $event_id )['stage'] ?? '' ) ) { $blocked_events[$event_id] = true; continue; }
			} elseif ( ! $event_id || is_wp_error( MI_Event_Deletion::enter( $event_id ) ) ) {
				$blocked_events[$event_id] = true;
				$wpdb->update( $table, array( 'last_error' => 'Evento temporaneamente non disponibile. Ripresa automatica dal cron di recupero.' ), array( 'id' => $riga['id'], 'status' => $riga['status'] ) );
				MI_Event_Deletion::release( $event_id );
				continue;
			}
			$processed++;
			try {
			$id = absint( $riga['id'] );
			$invio_prova = 'TEST_PENDING' === $riga['status'];
			$stato_invio = $invio_prova ? 'TEST_SENDING' : 'SENDING';
			if ( 1 !== $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s, attempts = attempts + 1, processing_started_at = %s WHERE id = %d AND status = %s", $stato_invio, gmdate( 'Y-m-d H:i:s' ), $id, $riga['status'] ) ) ) {
				continue;
			}
			$delivery_revision = '';
			if ( 'BOOKING_UPDATED' === $riga['template_type'] ) {
				try {
					$fresh = MI_Booking_Update_Email::refresh( $riga );
					if ( ! $fresh ) continue;
					$riga['payload_json'] = wp_json_encode( $fresh ); $riga['recipient'] = $fresh['recipient'];
					$delivery_revision = '|' . hash( 'sha256', $riga['payload_json'] );
				} catch ( Throwable $error ) {
					$wpdb->update( $table, array( 'status' => (int) $riga['attempts'] >= 4 ? ( $invio_prova ? 'TEST_FAILED' : 'FAILED' ) : $riga['status'], 'processing_started_at' => null, 'last_error' => 'Riepilogo aggiornato non disponibile.' ), array( 'id' => $id ) );
					continue;
				}
			}
			$payload = json_decode( (string) $riga['payload_json'], true );
			$istantanea = is_array( $payload ) && isset( $payload['email_preview'] ) && is_array( $payload['email_preview'] ) ? $payload['email_preview'] : array();
			// Normalizza prima di azzerare i metadati dell’identificativo: il filtro
			// rimuove soltanto il codice ordine interno, mai una riga di testo libera.
			$istantanea = MI_Modello_Email::ripara_istantanea_codifica( $istantanea );
			$istantanea['identificativo'] = array( 'modalita' => 'NONE', 'codice' => '', 'payload_qr' => '' );
			if ( 'REGISTRATION_SECRETARIAT_NOTIFICATION' === $riga['template_type'] ) {
				foreach ( array( 'html', 'testo' ) as $campo ) if ( isset( $istantanea[ $campo ] ) ) $istantanea[ $campo ] = str_replace( array( '<strong>Referente:</strong>', 'Referente:' ), array( '<strong>Iscrizione a nome di:</strong>', 'Iscrizione a nome di:' ), $istantanea[ $campo ] );
			}
			if ( ! empty( $riga['registration_id'] ) ) {
				$economia = $wpdb->get_row( $wpdb->prepare( "SELECT economic_mode,total_cents FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $riga['registration_id'] ), ARRAY_A );
				if ( is_array( $economia ) && ( ! in_array( $economia['economic_mode'], array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) || (int) $economia['total_cents'] <= 0 ) ) unset( $istantanea['status_url'] );
			}
			$portal_test_copy = 'PORTAL_COMMUNICATION_TEST' === $riga['template_type'] && ! empty( $payload['portal_test_copy'] );
			$destinatario = $invio_prova ? ( $portal_test_copy ? sanitize_email( (string) ( $payload['test_recipient'] ?? '' ) ) : $destinatario_prova ) : $riga['recipient'];
			if ( $invio_prova ) $istantanea = self::prepara_istantanea_prova( $istantanea, $portal_test_copy ? '' : $riga['recipient'], $portal_test_copy );

			$request = self::invia_istantanea( $destinatario, $istantanea, hash( 'sha256', home_url() . '|outbox|' . $id . $delivery_revision ), $invio_prova, $portal_test_copy ? 'PROVA_PUNTUALE' : null, true );
			$riga['event_id'] = $event_id;
			if ( is_array( $request ) ) {
				$deliveries[] = array( 'row' => $riga, 'payload' => $request );
				$leases[$event_id] = true;
			} else self::registra_esito( $riga, $request );
			} catch ( Throwable $error ) {
				if ( isset( $id ) ) self::registra_esito( $riga, new WP_Error( 'mi_email_prepare_failed', 'Preparazione email interrotta; tentativo conservato.' ) );
			} finally { if ( ! isset( $leases[$event_id] ) ) MI_Event_Deletion::release( $event_id ); }
		}
		}
		if ( $deliveries ) {
			// Recupero persistente anche se PHP viene interrotto durante il trasporto.
			if ( ! wp_next_scheduled( 'mi_spedisci_email_in_coda' ) ) wp_schedule_single_event( time() + 120, 'mi_spedisci_email_in_coda' );
			self::spedisci_lotto( $deliveries );
		}

		if ( ! $portal_test_id ) update_option( $cursor_key, $cursor, false );
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$runnable_filter} AND attempts < 5" ) > 0 && ( $processed > 0 || $cursor > 0 || ! $blocked_events ) ) {
			self::pianifica_spedizione( $deliveries ? 0 : 30 );
		}
		} finally { foreach ( $leases as $event_id => $held ) MI_Event_Deletion::release( $event_id ); }
	}

	/** Una richiesta firmata con esiti distinti e chiavi stabili per ogni destinatario. */
	private static function spedisci_lotto( array $deliveries ) {
		try {
			$response = MI_Workspace_Client::request( 'INVIA_EMAIL_LOTTO', array( 'messages' => array_column( $deliveries, 'payload' ) ) );
		} catch ( Throwable $error ) { $response = new WP_Error( 'mi_email_transport', 'Trasporto interrotto: verifica in corso tramite la stessa chiave di consegna.' ); }
		if ( ! is_wp_error( $response ) && isset( $response['quota_remaining'] ) ) update_option( 'mi_email_quota_remaining', max( 0, (int) $response['quota_remaining'] ), false );
		foreach ( $deliveries as $delivery ) {
			$key = $delivery['payload']['delivery_key'];
			$result = ! is_wp_error( $response ) && isset( $response['results'][$key] ) ? $response['results'][$key] : null;
			if ( is_wp_error( $response ) ) $outcome = $response;
			elseif ( is_array( $result ) && ! empty( $result['ok'] ) && 'GOOGLE_WORKSPACE' === ( $result['channel'] ?? '' ) ) $outcome = true;
			else {
				$code = sanitize_key( $result['error'] ?? 'EMAIL_RESULT_MISSING' );
				$messages = array(
					'email_quota_exceeded' => 'Quota giornaliera Google esaurita: invio sospeso, ripresa automatica.',
					'email_delivery_uncertain' => 'Esito incerto: verifica la consegna prima di rigenerare. Reinvio automatico bloccato.',
					'email_busy' => 'Google occupato: invio mantenuto in coda.',
					'email_deferred' => 'Lotto concluso: email mantenuta nel prossimo passaggio.',
				);
				$outcome = new WP_Error( 'mi_email_remote', $messages[$code] ?? 'Google ha rifiutato l’email (' . strtoupper( $code ) . ').', array( 'remote_code' => strtoupper( $code ) ) );
			}
			self::registra_esito( $delivery['row'], $outcome );
		}
	}

	private static function registra_esito( array $row, $result ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		$id = absint( $row['id'] );
		$sending = 'TEST_PENDING' === $row['status'] ? 'TEST_SENDING' : 'SENDING';
		if ( ! is_wp_error( $result ) && $result ) {
			$wpdb->update( $table, array( 'status' => 'TEST_PENDING' === $row['status'] ? 'TEST_SENT' : 'SENT', 'last_error' => null, 'sent_at' => current_time( 'mysql', true ), 'processing_started_at' => null ), array( 'id' => $id, 'status' => $sending ) );
			return;
		}
		$data = is_wp_error( $result ) ? $result->get_error_data() : array();
		$code = is_array( $data ) ? ( $data['remote_code'] ?? '' ) : '';
		$message = is_wp_error( $result ) ? sanitize_text_field( $result->get_error_message() ) : 'Contenuto email non valido o inattivo.';
		$deferred = in_array( $code, array( 'EMAIL_BUSY', 'WORKSPACE_BUSY', 'EVENT_BUSY', 'EMAIL_DEFERRED', 'EMAIL_QUOTA_EXCEEDED', 'EMAIL_STORAGE_UNAVAILABLE', 'ACTION_NOT_ALLOWED' ), true ) || ( is_wp_error( $result ) && 'mi_workspace_busy' === $result->get_error_code() );
		if ( $deferred ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s, attempts=GREATEST(0,attempts-1), processing_started_at=NULL, last_error=%s WHERE id=%d AND status=%s", $row['status'], $message, $id, $sending ) );
			if ( 'EVENT_BUSY' === $code && ! empty( $row['event_id'] ) ) update_option( 'mi_email_event_retry_' . absint( $row['event_id'] ), time() + 30, false );
			elseif ( 'EMAIL_DEFERRED' !== $code ) update_option( 'mi_email_retry_after', time() + ( in_array( $code, array( 'EMAIL_QUOTA_EXCEEDED', 'EMAIL_STORAGE_UNAVAILABLE', 'ACTION_NOT_ALLOWED' ), true ) ? 900 : 30 ), false );
			return;
		}
		$terminal = ! $result || in_array( $code, array( 'EMAIL_DELIVERY_UNCERTAIN', 'EMAIL_ALIAS_NOT_AUTHORIZED', 'EMAIL_ALIAS_CHECK_FAILED', 'EMAIL_SENDER_NOT_AUTHORIZED', 'INVALID_EMAIL_PAYLOAD', 'INVALID_SENDER_EMAIL', 'INVALID_REPLY_TO', 'INVALID_SENDER_NAME', 'TEST_RECIPIENT_MISMATCH' ), true );
		if ( ! $terminal ) update_option( 'mi_email_retry_after', time() + min( 900, 60 * ( 2 ** min( 4, (int) $row['attempts'] ) ) ), false );
		$failed = 'TEST_PENDING' === $row['status'] ? 'TEST_FAILED' : 'FAILED';
		$wpdb->update( $table, array( 'status' => $terminal || (int) $row['attempts'] >= 4 ? $failed : $row['status'], 'last_error' => $message, 'processing_started_at' => null ), array( 'id' => $id, 'status' => $sending ) );
	}

	private static function prepara_istantanea_prova( $istantanea, $destinatario_originale, $portal_test_copy = false ) {
		$destinatario_originale = sanitize_email( $destinatario_originale );
		$avviso_html = '<div style="margin:0 0 20px;padding:12px 16px;border:1px solid #C9D3E1;border-radius:8px;background:#F6F8FB;color:#111827;"><strong>' . ( $portal_test_copy ? 'Email di prova della comunicazione.' : 'Modalità prova.' ) . '</strong><br>' . ( $portal_test_copy ? 'Campione anonimizzato: nessuna email è stata inviata agli iscritti.' : 'Destinatario originale: ' . esc_html( $destinatario_originale ) ) . '</div>';
		$istantanea['oggetto'] = '[PROVA] ' . (string) ( $istantanea['oggetto'] ?? '' );
		$istantanea['html'] = $avviso_html . (string) ( $istantanea['html'] ?? '' );
		$istantanea['testo'] = $portal_test_copy ? "EMAIL DI PROVA DELLA COMUNICAZIONE\nCampione anonimizzato: nessuna email è stata inviata agli iscritti.\n\n" . (string) ( $istantanea['testo'] ?? '' ) : "MODALITÀ PROVA\nDestinatario originale: {$destinatario_originale}\n\n" . (string) ( $istantanea['testo'] ?? '' );
		if ( ! $portal_test_copy && isset( $istantanea['identita_email'] ) && is_array( $istantanea['identita_email'] ) ) $istantanea['identita_email']['indirizzo_risposte'] = self::destinatario_prova();
		return $istantanea;
	}

	private static function invia_istantanea( $destinatario, $istantanea, $delivery_key, $invio_prova, $mode_override = null, $prepare_only = false ) {
		if ( ! is_email( $destinatario ) ) return new WP_Error( 'mi_email_recipient_invalid', 'Destinatario email assente o non valido. Correggi il recapito prima di preparare una nuova consegna.', array( 'remote_code' => 'INVALID_EMAIL_PAYLOAD' ) );
		if ( empty( $istantanea['attivo'] ) || empty( $istantanea['oggetto'] ) ) {
			return false;
		}
		$istantanea = MI_Modello_Email::ripara_istantanea_codifica( $istantanea );
		$identita = isset( $istantanea['identita_email'] ) && is_array( $istantanea['identita_email'] ) ? $istantanea['identita_email'] : array();
		$intestazioni = array( 'Content-Type: text/html; charset=UTF-8' );
		$reply_to = ! empty( $identita['indirizzo_risposte'] ) && is_email( $identita['indirizzo_risposte'] ) ? sanitize_email( $identita['indirizzo_risposte'] ) : MI_Modello_Email::EMAIL_SEGRETERIA;
		$intestazioni[] = 'Reply-To: ' . $reply_to;
		$codice_html = '';
		self::$codice_incorporato = '';
		if ( isset( $istantanea['identificativo'] ) && is_array( $istantanea['identificativo'] ) && in_array( $istantanea['identificativo']['modalita'] ?? 'NONE', array( 'QR', 'BARCODE' ), true ) ) {
				$code_payload = 'QR' === $istantanea['identificativo']['modalita'] ? ( $istantanea['identificativo']['payload_qr'] ?? '' ) : ( $istantanea['identificativo']['codice'] ?? '' );
				try {
					self::$codice_incorporato = MI_Code_Image::svg( $istantanea['identificativo']['modalita'], $code_payload );
				} catch ( LengthException $error ) {
					// Interrompe l'invio prima di contattare Google e registra un esito consultabile,
					// senza scrivere il contenuto nel log o inviare un identificativo troncato.
					return new WP_Error( 'mi_email_qr_too_long', 'Email non inviata: il contenuto del QR supera 106 byte. Correggi l’identificativo prima di riprovare.' );
				}
				catch ( InvalidArgumentException $error ) { return new WP_Error( 'mi_email_barcode_invalid', $error->getMessage() ); }
				$codice_html = '<p><img src="cid:mi-registration-code" alt="Codice grafico dell’iscrizione" style="display:block;max-width:280px;height:auto;border:0;"></p>';
		}
		$corpo = MI_Modello_Email::componi_html( $istantanea, $codice_html );
		self::$corpo_testo = MI_Modello_Email::componi_testo( $istantanea );
		$mittente = trim( (string) ( $identita['indirizzo_mittente'] ?? MI_Modello_Email::EMAIL_SEGRETERIA ) );
		if ( ! is_email( $mittente ) ) return new WP_Error( 'mi_email_sender_invalid', 'Email non inviata: indirizzo mittente del gruppo non valido.' );
		// Apps Script verifica l'azione con mittente esplicito prima di avviare l'invio.
		$azione = strtolower( $mittente ) === strtolower( MI_Modello_Email::EMAIL_SEGRETERIA ) ? 'INVIA_EMAIL_CONFERMA' : 'INVIA_EMAIL_CONFERMA_MITTENTE';
		$request_payload = array(
			'indirizzo_mittente' => sanitize_email( $mittente ),
			'delivery_key' => $delivery_key,
			'mode' => $mode_override ?: ( $invio_prova ? 'PROVA' : 'OPERATIVO' ),
			'test_only' => 'PROVA_PUNTUALE' === $mode_override,
			'destinatario' => sanitize_email( $destinatario ),
			'reply_to' => $reply_to,
			'nome_mittente' => html_entity_decode( sanitize_text_field( $identita['nome_mittente'] ?? MI_Modello_Email::NOME_SEGRETERIA ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'oggetto' => html_entity_decode( sanitize_text_field( $istantanea['oggetto'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'html' => $corpo,
			'testo' => self::$corpo_testo,
			'codice_svg' => self::$codice_incorporato,
		);
		$inviata = $prepare_only ? null : MI_Workspace_Client::request( $azione, $request_payload );
		self::$nome_mittente = '';
		self::$indirizzo_mittente = '';
		self::$codice_incorporato = '';
		self::$corpo_testo = '';
		if ( $prepare_only ) return $request_payload;
		return is_wp_error( $inviata ) ? $inviata : ( ! empty( $inviata['ok'] ) && 'GOOGLE_WORKSPACE' === ( $inviata['channel'] ?? '' ) );
	}

	public static function incorpora_codice( $phpmailer ) {
		if ( self::$indirizzo_mittente && property_exists( $phpmailer, 'Sender' ) ) $phpmailer->Sender = self::$indirizzo_mittente;
		if ( self::$corpo_testo && property_exists( $phpmailer, 'AltBody' ) ) {
			$phpmailer->AltBody = self::$corpo_testo;
		}
		if ( self::$codice_incorporato && method_exists( $phpmailer, 'addStringEmbeddedImage' ) ) {
			$phpmailer->addStringEmbeddedImage( self::$codice_incorporato, 'mi-registration-code', 'codice-iscrizione.svg', 'base64', 'image/svg+xml' );
		}
	}

	public static function filtra_nome_mittente( $nome_corrente ) {
		return self::$nome_mittente ?: $nome_corrente;
	}

	public static function filtra_indirizzo_mittente( $indirizzo_corrente ) {
		return self::$indirizzo_mittente ?: $indirizzo_corrente;
	}

	private static function prova_verificata( $destinatario = null ) {
		$destinatario = null === $destinatario ? (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' ) : (string) $destinatario;
		return is_email( $destinatario ) && hash_equals( self::impronta_destinatario( $destinatario ), (string) get_option( self::OPZIONE_PROVA_VERIFICATA, '' ) );
	}

	private static function destinatario_prova() {
		$destinatario = sanitize_email( (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' ) );
		return is_email( $destinatario ) ? $destinatario : '';
	}

	private static function impronta_destinatario( $destinatario ) {
		return hash_hmac( 'sha256', strtolower( trim( $destinatario ) ), wp_salt( 'auth' ) );
	}

	private static function verifica_amministratore( $azione ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Operazione non consentita.', 'modulo-iscrizioni' ) );
		}
		check_admin_referer( $azione );
	}

	private static function torna_alla_pagina( $esito ) {
		wp_safe_redirect( add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-spedizione-email', 'mi_esito' => $esito ), admin_url( 'edit.php' ) ) );
		exit;
	}
}
