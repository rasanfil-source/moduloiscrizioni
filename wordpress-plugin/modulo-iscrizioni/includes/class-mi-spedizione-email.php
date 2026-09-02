<?php

defined( 'ABSPATH' ) || exit;

final class MI_Spedizione_Email {
	const OPZIONE_MODALITA = 'mi_modalita_spedizione_email';
	const OPZIONE_DESTINATARIO_PROVA = 'mi_destinatario_prova_email';
	const OPZIONE_PROVA_VERIFICATA = 'mi_prova_email_verificata';
	private static $nome_mittente = '';
	private static $codice_incorporato = '';
	private static $corpo_testo = '';

	public static function avvia() {
		add_action( 'admin_menu', array( __CLASS__, 'aggiungi_pagina' ) );
		add_action( 'admin_post_mi_salva_spedizione_email', array( __CLASS__, 'salva_impostazioni' ) );
		add_action( 'admin_post_mi_invia_email_prova', array( __CLASS__, 'invia_prova' ) );
		add_action( 'admin_post_mi_riaccoda_email', array( __CLASS__, 'riaccoda_email' ) );
		add_action( 'mi_spedisci_email_in_coda', array( __CLASS__, 'spedisci_coda' ) );
	}

	public static function modalita() {
		$modalita = strtoupper( (string) get_option( self::OPZIONE_MODALITA, 'ANTEPRIMA' ) );
		return in_array( $modalita, array( 'ANTEPRIMA', 'PROVA', 'OPERATIVO' ), true ) ? $modalita : 'ANTEPRIMA';
	}

	public static function stato_nuova_email( $istantanea ) {
		return ! empty( $istantanea['attivo'] ) && 'OPERATIVO' === self::modalita() && self::prova_verificata() ? 'PENDING' : 'PREVIEW';
	}

	public static function pianifica_spedizione() {
		if ( 'OPERATIVO' === self::modalita() && ! wp_next_scheduled( 'mi_spedisci_email_in_coda' ) ) {
			wp_schedule_single_event( time() + 30, 'mi_spedisci_email_in_coda' );
		}
	}

	/** Prepara la comunicazione privata destinata al solo gestore responsabile dell'evento. */
	public static function accoda_notifica_gestore_evento( $event_id, ?WP_User $gestore, $sheet_url, $email_segreteria = '' ) {
		$event_id = absint( $event_id );
		$recipient = sanitize_email( $gestore ? $gestore->user_email : $email_segreteria );
		$nome_destinatario = $gestore ? $gestore->display_name : 'Segreteria';
		$sheet_url = esc_url_raw( (string) $sheet_url, array( 'https' ) );
		$event_url = MI_Shortcode::url_iscrizione( $event_id );
		if ( ! $event_id || ! is_email( $recipient ) || 0 !== strpos( $sheet_url, 'https://docs.google.com/spreadsheets/' ) ) return new WP_Error( 'mi_notifica_gestore_non_valida', 'Dati della comunicazione al gestore non validi.' );
		$values = array( '{{evento.titolo}}' => get_the_title( $event_id ), '{{ordine.codice}}' => '', '{{referente.nome_completo}}' => $nome_destinatario );
		$snapshot = MI_Modello_Email::crea_istantanea( $event_id, $values );
		$snapshot['attivo'] = true;
		$snapshot['oggetto'] = 'Evento pronto — ' . sanitize_text_field( get_the_title( $event_id ) );
		$snapshot['preheader'] = 'Il modulo e il foglio operativo sono pronti.';
		$snapshot['evento']['url'] = esc_url_raw( $event_url, array( 'https' ) );
		$snapshot['html'] = '<p>Gentile ' . esc_html( $nome_destinatario ) . ',</p><p>l’evento <strong>' . esc_html( get_the_title( $event_id ) ) . '</strong> è stato pubblicato.</p><p>Il riferimento per la gestione è <strong>' . esc_html( $recipient ) . '</strong>. Per aprire il foglio devi essere autenticato in Google con questo indirizzo e avere i permessi sul documento.</p><p><strong>Foglio operativo:</strong><br><a href="' . esc_url( $sheet_url ) . '">' . esc_html( $sheet_url ) . '</a><br><span aria-hidden="true">⧉</span> Seleziona e copia il collegamento quando ti serve.</p>';
		$snapshot['testo'] = "Gentile " . sanitize_text_field( $nome_destinatario ) . ",\n\nl’evento “" . sanitize_text_field( get_the_title( $event_id ) ) . "” è stato pubblicato.\n\nAccedi a Google con {$recipient} per aprire il foglio operativo:\n{$sheet_url}\n\nPagina dell’evento:\n{$event_url}";
		$snapshot['identificativo'] = array( 'modalita' => 'NONE', 'codice' => '', 'payload_qr' => '' );
		$payload_json = wp_json_encode( array( 'event_id' => $event_id, 'template_type' => 'EVENT_MANAGER_READY', 'email_preview' => $snapshot ) );
		if ( false === $payload_json ) return new WP_Error( 'mi_notifica_gestore_json', 'Comunicazione al gestore non serializzabile.' );
		global $wpdb;
		$status = 'OPERATIVO' === self::modalita() && self::prova_verificata() ? 'PENDING' : 'PREVIEW';
		$origin_key = hash( 'sha256', 'event-manager-ready|' . $event_id . '|' . strtolower( $recipient ) . '|' . $sheet_url );
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}mi_email_outbox (registration_id,recipient,template_type,origin_key,payload_json,status,created_at) VALUES (0,%s,'EVENT_MANAGER_READY',%s,%s,%s,%s)", $recipient, $origin_key, $payload_json, $status, current_time( 'mysql', true ) ) );
		if ( false === $inserted ) return new WP_Error( 'mi_notifica_gestore_archivio', 'Comunicazione al gestore non salvata.' );
		if ( 'PENDING' === $status && $inserted ) self::pianifica_spedizione();
		return array( 'ok' => true, 'count' => $inserted ? 1 : 0, 'mode' => 'PENDING' === $status ? 'OPERATIVO' : 'ANTEPRIMA' );
	}

	public static function accoda_comunicazione_operativa( array $payload ) {
		global $wpdb;
		$communication_id = sanitize_key( (string) ( $payload['communication_id'] ?? '' ) );
		$event_id = absint( $payload['event_id'] ?? 0 );
		$template_type = strtoupper( sanitize_key( (string) ( $payload['template_type'] ?? '' ) ) );
		$message = sanitize_textarea_field( (string) ( $payload['message'] ?? '' ) );
		$allow_operational = ! empty( $payload['allow_operational'] );
		$custom_types = get_option( 'mi_custom_communication_types', array() );
		$custom_allowed = is_array( $custom_types ) && isset( $custom_types[ $template_type ] ) && preg_match( '/^CUSTOM_[A-Z0-9_]{1,24}$/', $template_type );
		if ( ! $communication_id || ! $event_id || ( ! in_array( $template_type, array( 'PRE_DEPARTURE_REMINDER', 'BALANCE_REMINDER', 'EVENT_CANCELLATION' ), true ) && ! $custom_allowed ) ) return new WP_Error( 'mi_operational_email_invalid', 'Comunicazione non valida.', array( 'status' => 400 ) );
		if ( ( 'PRE_DEPARTURE_REMINDER' === $template_type || $custom_allowed ) && ! $message ) return new WP_Error( 'mi_operational_email_message_required', 'La comunicazione richiede un testo.', array( 'status' => 400 ) );
		$recipient_payload = is_array( $payload['recipients'] ?? null ) ? array_slice( $payload['recipients'], 0, 1000 ) : array();
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
		if ( ! $recipient_state ) return array( 'ok' => true, 'count' => 0, 'mode' => self::modalita(), 'message' => 'Nessun destinatario corrisponde ai criteri.' );
		$event = MI_Registration_Service::public_event( $event_id, true );
		if ( is_wp_error( $event ) ) return $event;
		$lock_name = 'mi_email_' . substr( hash( 'sha256', $communication_id ), 0, 48 );
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock_name ) ) ) return new WP_Error( 'mi_operational_email_busy', 'Preparazione momentaneamente occupata.', array( 'status' => 503 ) );
		try {
			$idempotency_key = 'mi_operational_email_' . hash( 'sha256', $communication_id );
			$effective_mode = $allow_operational && 'OPERATIVO' === self::modalita() && 'publish' === get_post_status( $event_id ) ? 'OPERATIVO' : 'ANTEPRIMA';
			if ( get_transient( $idempotency_key ) ) return array( 'ok' => true, 'count' => 0, 'mode' => $effective_mode, 'message' => 'Questa comunicazione era già stata preparata.' );
			$registrations_table = $wpdb->prefix . 'mi_registrations';
			$outbox_table = $wpdb->prefix . 'mi_email_outbox';
			$order_codes = array_keys( $recipient_state );
			$placeholders = implode( ',', array_fill( 0, count( $order_codes ), '%s' ) );
			$query_args = array_merge( array( $event_id ), $order_codes );
			$registrations = $wpdb->get_results( $wpdb->prepare( "SELECT id,order_code,status,buyer_first_name,buyer_last_name,buyer_email,total_qty,total_cents,initial_due_cents,balance_cents,payment_methods_json FROM {$registrations_table} WHERE event_id=%d AND order_code IN ({$placeholders}) AND status IN ('CONFIRMED','PENDING_PAYMENT','WAITLISTED') AND capacity_released_at IS NULL ORDER BY id LIMIT 1000", $query_args ), ARRAY_A );
			$now = current_time( 'mysql', true );
			$count = 0;
			$wpdb->query( 'START TRANSACTION' );
			try {
				foreach ( $registrations as $registration ) {
					if ( 'WAITLISTED' === $registration['status'] && 'EVENT_CANCELLATION' !== $template_type ) continue;
					$financial_state = $recipient_state[ $registration['order_code'] ] ?? array();
					$paid = min( (int) $registration['total_cents'], max( 0, (int) ( $financial_state['paid_cents'] ?? 0 ) ) );
					$balance = min( (int) $registration['total_cents'], max( 0, (int) ( $financial_state['balance_cents'] ?? 0 ) ) );
					if ( 'BALANCE_REMINDER' === $template_type && $balance < 1 ) continue;
					$economic = array( 'total_cents' => (int) $registration['total_cents'], 'initial_due_cents' => (int) $registration['initial_due_cents'], 'balance_cents' => $balance, 'payment_methods' => json_decode( (string) $registration['payment_methods_json'], true ) ?: array() );
					$status_labels = array( 'CONFIRMED' => 'Confermata', 'PENDING_PAYMENT' => 'In attesa di pagamento', 'WAITLISTED' => 'Lista d’attesa' );
					$values = MI_Modello_Email::valori_ordine( $event, $registration['order_code'], $status_labels[ $registration['status'] ] ?? $registration['status'], (int) $registration['total_qty'], trim( $registration['buyer_first_name'] . ' ' . $registration['buyer_last_name'] ), $economic );
					$status_url = MI_Portal::status_url( $registration['id'], $registration['order_code'], $registration['buyer_email'] );
					$snapshot = MI_Modello_Email::crea_istantanea_operativa( $event_id, $values, $template_type, $message, $status_url );
					$status = 'OPERATIVO' === $effective_mode ? self::stato_nuova_email( $snapshot ) : 'PREVIEW';
					$payload_json = wp_json_encode( array( 'communication_id' => $communication_id, 'event_title' => $event['title'], 'order_code' => $registration['order_code'], 'template_type' => $template_type, 'email_preview' => $snapshot ) );
					if ( false === $payload_json ) throw new RuntimeException( 'Coda email non salvata.' );
					$origin_key = hash( 'sha256', $communication_id . '|' . $registration['id'] );
					$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$outbox_table} (registration_id,recipient,template_type,origin_key,payload_json,status,created_at) VALUES (%d,%s,%s,%s,%s,%s,%s)", $registration['id'], $registration['buyer_email'], $template_type, $origin_key, $payload_json, $status, $now ) );
					if ( false === $inserted ) throw new RuntimeException( 'Coda email non salvata.' );
					if ( $inserted ) $count++;
				}
				$wpdb->query( 'COMMIT' );
				set_transient( $idempotency_key, 1, DAY_IN_SECONDS );
			} catch ( Throwable $error ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'mi_operational_email_storage', 'Non è stato possibile preparare la comunicazione.', array( 'status' => 500 ) );
			}
			if ( 'OPERATIVO' === $effective_mode && $count ) self::pianifica_spedizione();
			return array( 'ok' => true, 'count' => $count, 'mode' => $effective_mode, 'message' => 'Comunicazione preparata nella coda WordPress.' );
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
			'prova_ko' => array( 'error', $dettaglio_prova ? 'Invio di prova non riuscito: ' . $dettaglio_prova : 'Invio di prova non riuscito tramite MODULI.' ),
			'indirizzo' => array( 'error', 'Inserisci un indirizzo di prova valido.' ),
			'prova_richiesta' => array( 'error', 'La modalità operativa richiede prima un invio di prova riuscito verso l’indirizzo attuale.' ),
		);
		if ( isset( $messaggi[ $esito ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $messaggi[ $esito ][0] ) . '"><p>' . esc_html( $messaggi[ $esito ][1] ) . '</p></div>';
		}
		?>
		<div class="wrap"><h1>Spedizione email</h1>
		<p>La configurazione iniziale è <strong>Anteprima</strong>: nessun messaggio parte. <strong>Prova</strong> abilita esclusivamente un invio sintetico all’indirizzo indicato. <strong>Operativo</strong> mette in coda le nuove conferme reali.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="mi_salva_spedizione_email"><?php wp_nonce_field( 'mi_salva_spedizione_email' ); ?>
		<table class="form-table"><tr><th scope="row"><label for="mi_modalita_spedizione_email">Modalità</label></th><td><select id="mi_modalita_spedizione_email" name="mi_modalita_spedizione_email">
		<option value="ANTEPRIMA" <?php selected( $modalita, 'ANTEPRIMA' ); ?>>Anteprima — nessun invio</option><option value="PROVA" <?php selected( $modalita, 'PROVA' ); ?>>Prova — solo messaggio sintetico</option><option value="OPERATIVO" <?php selected( $modalita, 'OPERATIVO' ); ?> <?php disabled( ! $verificata ); ?>>Operativo — conferme reali</option></select>
		<?php if ( ! $verificata ) : ?><p class="description">Operativo resterà bloccato finché la prova non riuscirà.</p><?php endif; ?></td></tr>
		<tr><th scope="row"><label for="mi_destinatario_prova_email">Destinatario della prova</label></th><td><input class="regular-text" type="email" id="mi_destinatario_prova_email" name="mi_destinatario_prova_email" value="<?php echo esc_attr( $destinatario ); ?>" autocomplete="off"><p class="description">Non viene usato come destinatario delle conferme operative.</p></td></tr></table><?php submit_button( 'Salva impostazioni' ); ?></form>
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
			'html' => '<p>Questa è una <strong>email sintetica di prova</strong> del Modulo Iscrizioni.</p><p>Evento: Evento dimostrativo<br>Codice: MI-PROVA-0001<br>Referente: Persona Esempio</p><p>Nessun dato di un’iscrizione reale è stato utilizzato.</p>',
			'testo' => "Questa è una email sintetica di prova del Modulo Iscrizioni.\nEvento: Evento dimostrativo\nCodice: MI-PROVA-0001\nReferente: Persona Esempio\nNessun dato di un’iscrizione reale è stato utilizzato.",
			'footer' => 'Un saluto dall’organizzazione.',
			'identita' => array( 'nome_attivita' => 'Attività dimostrativa', 'primary_color' => '#151b38', 'secondary_color' => '#337ab7', 'primary_text_color' => '#ffffff', 'secondary_text_color' => '#ffffff' ),
			'identita_email' => array(),
			'evento' => array( 'titolo' => 'Evento dimostrativo', 'url' => '' ),
			'identificativo' => array( 'codice' => 'MI-PROVA-0001' ),
		);
		$corpo = MI_Modello_Email::componi_html( $istantanea );
		self::$corpo_testo = MI_Modello_Email::componi_testo( $istantanea );
		add_action( 'phpmailer_init', array( __CLASS__, 'incorpora_codice' ) );
		// La prova usa esclusivamente il canale firmato del progetto MODULI.
		// La posta generale di WordPress e degli altri plugin resta invariata.
		$risposta_workspace = MI_Workspace_Client::request( 'INVIA_EMAIL_PROVA', array( 'destinatario' => $destinatario, 'oggetto' => $oggetto, 'html' => $corpo, 'testo' => self::$corpo_testo ) );
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
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'PENDING', attempts = 0, last_error = NULL, sent_at = NULL WHERE id = %d AND status IN ('FAILED', 'SENDING')", $id ) );
		self::pianifica_spedizione();
		wp_safe_redirect( add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-email-outbox', 'email_id' => $id, 'mi_esito' => 'riaccodata' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public static function spedisci_coda() {
		if ( 'OPERATIVO' !== self::modalita() || ! self::prova_verificata() ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		$stale = gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'PENDING', processing_started_at = NULL WHERE status = 'SENDING' AND processing_started_at < %s", $stale ) );
		$righe = $wpdb->get_results( "SELECT id, recipient, payload_json, attempts FROM {$table} WHERE status = 'PENDING' AND attempts < 5 ORDER BY id ASC LIMIT 10", ARRAY_A );
		foreach ( $righe as $riga ) {
			$id = absint( $riga['id'] );
			if ( 1 !== $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'SENDING', attempts = attempts + 1, processing_started_at = %s WHERE id = %d AND status = 'PENDING'", gmdate( 'Y-m-d H:i:s' ), $id ) ) ) {
				continue;
			}
			$payload = json_decode( (string) $riga['payload_json'], true );
			$istantanea = is_array( $payload ) && isset( $payload['email_preview'] ) && is_array( $payload['email_preview'] ) ? $payload['email_preview'] : array();
			if ( self::invia_istantanea( $riga['recipient'], $istantanea ) ) {
				$wpdb->update( $table, array( 'status' => 'SENT', 'last_error' => null, 'sent_at' => current_time( 'mysql', true ), 'processing_started_at' => null ), array( 'id' => $id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
			} else {
				$tentativi = (int) $riga['attempts'] + 1;
				$wpdb->update( $table, array( 'status' => $tentativi >= 5 ? 'FAILED' : 'PENDING', 'last_error' => 'wp_mail non ha accettato il messaggio.', 'processing_started_at' => null ), array( 'id' => $id ), array( '%s', '%s', '%s' ), array( '%d' ) );
			}
		}
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'PENDING' AND attempts < 5" ) > 0 ) {
			self::pianifica_spedizione();
		}
	}

	private static function invia_istantanea( $destinatario, $istantanea ) {
		if ( ! is_email( $destinatario ) || empty( $istantanea['attivo'] ) || empty( $istantanea['oggetto'] ) ) {
			return false;
		}
		$identita = isset( $istantanea['identita_email'] ) && is_array( $istantanea['identita_email'] ) ? $istantanea['identita_email'] : array();
		$intestazioni = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! empty( $identita['indirizzo_risposte'] ) && is_email( $identita['indirizzo_risposte'] ) ) {
			$intestazioni[] = 'Reply-To: ' . sanitize_email( $identita['indirizzo_risposte'] );
		}
		$codice_html = '';
		if ( isset( $istantanea['identificativo'] ) && is_array( $istantanea['identificativo'] ) && in_array( $istantanea['identificativo']['modalita'] ?? 'NONE', array( 'TEXT', 'QR', 'BARCODE' ), true ) ) {
			$codice_html = '<p style="margin-top:20px;"><strong>Codice:</strong> <code>' . esc_html( $istantanea['identificativo']['codice'] ?? '' ) . '</code></p>';
			if ( in_array( $istantanea['identificativo']['modalita'], array( 'QR', 'BARCODE' ), true ) ) {
				$code_payload = 'QR' === $istantanea['identificativo']['modalita'] ? ( $istantanea['identificativo']['payload_qr'] ?? '' ) : ( $istantanea['identificativo']['codice'] ?? '' );
				self::$codice_incorporato = MI_Code_Image::svg( $istantanea['identificativo']['modalita'], $code_payload );
				$codice_html .= '<p><img src="cid:mi-registration-code" alt="Codice grafico dell’iscrizione" style="display:block;max-width:280px;height:auto;border:0;"></p>';
			}
		}
		$corpo = MI_Modello_Email::componi_html( $istantanea, $codice_html );
		self::$corpo_testo = MI_Modello_Email::componi_testo( $istantanea );
		add_action( 'phpmailer_init', array( __CLASS__, 'incorpora_codice' ) );
		self::$nome_mittente = sanitize_text_field( $identita['nome_mittente'] ?? '' );
		if ( self::$nome_mittente ) {
			add_filter( 'wp_mail_from_name', array( __CLASS__, 'filtra_nome_mittente' ) );
		}
		$inviata = wp_mail( sanitize_email( $destinatario ), sanitize_text_field( $istantanea['oggetto'] ), $corpo, $intestazioni );
		remove_action( 'phpmailer_init', array( __CLASS__, 'incorpora_codice' ) );
		remove_filter( 'wp_mail_from_name', array( __CLASS__, 'filtra_nome_mittente' ) );
		self::$nome_mittente = '';
		self::$codice_incorporato = '';
		self::$corpo_testo = '';
		return $inviata;
	}

	public static function incorpora_codice( $phpmailer ) {
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

	private static function prova_verificata( $destinatario = null ) {
		$destinatario = null === $destinatario ? (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' ) : (string) $destinatario;
		return is_email( $destinatario ) && hash_equals( self::impronta_destinatario( $destinatario ), (string) get_option( self::OPZIONE_PROVA_VERIFICATA, '' ) );
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
