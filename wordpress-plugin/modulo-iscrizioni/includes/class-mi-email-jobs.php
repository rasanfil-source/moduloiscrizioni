<?php
defined( 'ABSPATH' ) || exit;

/** Richieste durevoli, preparazione limitata e risveglio indipendente dalle visite. */
final class MI_Email_Jobs {
	private const PROGRESS_RETENTION_SECONDS = 1800;
	private const JOB_RETENTION_SECONDS = 604800;
	public static function boot() {
		add_action( 'wp_ajax_mi_email_worker', array( __CLASS__, 'worker' ) );
		add_action( 'wp_ajax_nopriv_mi_email_worker', array( __CLASS__, 'worker' ) );
		add_action( 'wp_ajax_mi_email_progress', array( __CLASS__, 'progress' ) );
		add_action( 'mi_prepara_comunicazioni', array( 'MI_Spedizione_Email', 'spedisci_coda' ) );
		add_action( 'mi_avvia_comunicazione_programmata', array( __CLASS__, 'wake_scheduled' ) );
		add_action( 'mi_pulisci_bozze_cestinate', array( __CLASS__, 'cleanup' ), 30 );
		add_action( 'mi_pulisci_email_jobs', array( __CLASS__, 'cleanup' ) );
	}
	public static function mode_ready( $mode ) {
		return 'ANTEPRIMA' === $mode || ( 'OPERATIVO' === $mode ? 'PENDING' : 'TEST_PENDING' ) === MI_Spedizione_Email::stato_nuova_email( array( 'attivo' => true ) );
	}
	public static function enqueue( array $payload ) {
		$mode = ! empty( $payload['test_only'] ) ? 'PROVA_PUNTUALE' : ( 'publish' === get_post_status( absint( $payload['event_id'] ?? 0 ) ) && ! empty( $payload['allow_operational'] ) ? MI_Spedizione_Email::modalita() : 'ANTEPRIMA' );
		if ( empty( $payload['test_only'] ) && ! empty( $payload['start_at'] ) && 'OPERATIVO' !== $mode ) return new WP_Error( 'mi_email_schedule_mode', 'La programmazione richiede un evento pubblicato e la modalità operativa.' );
		if ( empty( $payload['test_only'] ) && ! self::mode_ready( $mode ) ) return new WP_Error( 'mi_email_mode_unverified', 'Invio non abilitato: configura la casella di prova e completa una prova riuscita nelle impostazioni email.' );
		if ( 'ANTEPRIMA' === $mode ) $payload['allow_operational'] = false;
		$id = hash( 'sha256', get_current_user_id() . '|' . ( $payload['communication_id'] ?? '' ) );
		$key = 'mi_email_job_' . $id;
		$job = get_option( $key );
		if ( ! is_array( $job ) ) {
			$recipients = array_values( (array) ( $payload['recipients'] ?? array() ) );
			if ( ! empty( $payload['test_only'] ) ) $recipients = array_slice( $recipients, 0, 1 );
			if ( ! $recipients || count( $recipients ) > 1000 ) return new WP_Error( 'mi_email_recipients', 'Numero di destinatari non valido.' );
			$payload['recipients'] = $recipients;
			$job = array( 'owner' => get_current_user_id(), 'event_id' => absint( $payload['event_id'] ), 'test_only' => ! empty( $payload['test_only'] ), 'state' => 'pending', 'mode' => $mode, 'payload' => $payload, 'offset' => 0, 'total' => count( $recipients ), 'origin_keys' => array(), 'excluded' => 0, 'error' => '', 'created_at' => time() );
			if ( 'OPERATIVO' === $mode && ! empty( $payload['start_at'] ) ) $job['start_at'] = (int) $payload['start_at'];
			if ( ! add_option( $key, $job, '', false ) && ! is_array( get_option( $key ) ) ) return new WP_Error( 'mi_email_storage', 'Richiesta non salvata: riprova.' );
			$job = get_option( $key );
		}
		update_option( ( ! empty( $job['test_only'] ) ? 'mi_email_last_test_job_' : 'mi_email_last_job_' ) . get_current_user_id(), $id, false );
		if ( 'pending' === $job['state'] ) {
			if ( ! empty( $job['start_at'] ) && empty( $job['started_at'] ) ) {
				$args = array( $id );
				if ( ! wp_next_scheduled( 'mi_avvia_comunicazione_programmata', $args ) && ! wp_schedule_single_event( max( time(), $job['start_at'] ), 'mi_avvia_comunicazione_programmata', $args ) ) return new WP_Error( 'mi_email_schedule', 'Programmazione non registrata nel cron. Riprova.' );
				add_option( 'mi_email_pending_' . $id, $id, '', false );
				update_option( 'mi_email_scheduled_' . $job['owner'] . '_' . $id, $id, false );
				return array( 'ok' => true, 'job_id' => $id, 'count' => $job['total'], 'mode' => $job['mode'], 'start_at' => $job['start_at'] );
			}
			add_option( 'mi_email_pending_' . ( ! empty( $job['test_only'] ) ? 'test_' : '' ) . $id, $id, '', false );
			if ( ! wp_next_scheduled( 'mi_prepara_comunicazioni' ) ) wp_schedule_single_event( time(), 'mi_prepara_comunicazioni' );
			add_action( 'shutdown', array( __CLASS__, 'dispatch' ) );
		}
		return array( 'ok' => true, 'job_id' => $id, 'count' => $job['total'], 'mode' => $job['mode'] );
	}
	public static function wake_scheduled( $id ) {
		MI_Spedizione_Email::spedisci_coda();
		$job = get_option( 'mi_email_job_' . $id );
		if ( is_array( $job ) && 'pending' === $job['state'] && empty( $job['started_at'] ) && ! empty( $job['start_at'] ) ) {
			wp_schedule_single_event( max( time() + 60, $job['start_at'] ), 'mi_avvia_comunicazione_programmata', array( $id ) );
		}
	}
	public static function cancel_scheduled( $id ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $id ) ) return false;
		$lock = 'mi_mail_worker_' . md5( $wpdb->prefix );
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) return false;
		try {
			$key = 'mi_email_job_' . $id;
			$job = get_option( $key );
			if ( ! is_array( $job ) || empty( $job['start_at'] ) || ! empty( $job['started_at'] ) || 'pending' !== $job['state'] || $job['owner'] !== get_current_user_id() || ! MI_Access::can_access_event( $job['event_id'] ) ) return false;
			$job['state'] = 'cancelled';
			unset( $job['payload'] );
			if ( ! update_option( $key, $job, false ) ) return false;
			self::remove_pending( $id );
			delete_option( 'mi_email_scheduled_' . $job['owner'] . '_' . $id );
			wp_clear_scheduled_hook( 'mi_avvia_comunicazione_programmata', array( $id ) );
			return true;
		} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}

	public static function scheduled_jobs( $owner = 0 ) {
		global $wpdb;
		$prefix = $wpdb->esc_like( 'mi_email_scheduled_' . ( $owner ? $owner . '_' : '' ) ) . '%';
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix ) );
		$scheduled = array();
		foreach ( (array) $ids as $id ) {
			$job = get_option( 'mi_email_job_' . $id );
			if ( ! is_array( $job ) || 'pending' !== $job['state'] || empty( $job['start_at'] ) || ! empty( $job['started_at'] ) || ( $owner && $owner !== (int) $job['owner'] ) || ! MI_Access::can_access_event( $job['event_id'] ) ) continue;
			$scheduled[ $id ] = array(
				'event_id' => $job['event_id'], 'start_at' => $job['start_at'], 'total' => $job['total'],
				'label' => ( $job['payload']['subject'] ?? '' ) ?: ( $job['payload']['template_type'] ?? '' ),
			);
		}
		uasort( $scheduled, static fn( $a, $b ) => $a['start_at'] <=> $b['start_at'] );
		return $scheduled;
	}

	public static function render_scheduled() {
		foreach ( self::scheduled_jobs( get_current_user_id() ) as $id => $job ) {
			echo '<form method="post"><p><strong>Programmato</strong> · ' . esc_html( get_the_title( $job['event_id'] ) . ' · ' . wp_date( 'd/m/Y H:i', $job['start_at'], new DateTimeZone( 'Europe/Rome' ) ) ) . '</p>';
			echo '<input type="hidden" name="mi_portal_action" value="cancel_scheduled_communication"><input type="hidden" name="scheduled_job" value="' . esc_attr( $id ) . '">';
			wp_nonce_field( 'mi_portal_cancel_scheduled_communication', 'mi_portal_nonce' );
			echo '<button type="submit" class="mi-secondary">Annulla invio programmato</button></form>';
		}
	}
	public static function dispatch() {
		$stamp = (string) time();
		$signature = hash_hmac( 'sha256', 'mi_email_worker|' . $stamp, wp_salt( 'auth' ) );
		wp_remote_post( admin_url( 'admin-ajax.php' ), array( 'timeout' => 0.01, 'blocking' => false, 'redirection' => 0, 'body' => array( 'action' => 'mi_email_worker', 'stamp' => $stamp, 'signature' => $signature ) ) );
	}
	public static function worker() {
		$stamp = (string) ( $_POST['stamp'] ?? '' );
		$signature = (string) ( $_POST['signature'] ?? '' );
		if ( ! ctype_digit( $stamp ) || abs( time() - (int) $stamp ) > 120 || ! hash_equals( hash_hmac( 'sha256', 'mi_email_worker|' . $stamp, wp_salt( 'auth' ) ), $signature ) ) wp_die( '', '', array( 'response' => 403 ) );
		MI_Spedizione_Email::spedisci_coda();
		wp_die( '', '', array( 'response' => 204 ) );
	}
	private static function remove_pending( $id ) {
		delete_option( 'mi_email_pending_' . $id );
		delete_option( 'mi_email_pending_test_' . $id );
	}
	public static function prepare_next() {
		global $wpdb;
		$cursor = absint( get_option( 'mi_email_job_cursor', 0 ) );
		// Salta pagine di job sospesi nello stesso passaggio, senza fermare quelli successivi.
		for ( $page = 0; $page < 10; $page++ ) {
		$ids = $wpdb->get_results( $wpdb->prepare( "SELECT option_id,option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND (option_id > %d OR option_name LIKE %s) ORDER BY (option_name LIKE %s) DESC,option_id LIMIT 10", $wpdb->esc_like( 'mi_email_pending_' ) . '%', $cursor, $wpdb->esc_like( 'mi_email_pending_test_' ) . '%', $wpdb->esc_like( 'mi_email_pending_test_' ) . '%' ), ARRAY_A );
		if ( ! $ids ) { update_option( 'mi_email_job_cursor', 0, false ); return; }
		foreach ( $ids as $entry ) {
			$id = $entry['option_value'];
			$cursor = (int) $entry['option_id'];
			update_option( 'mi_email_job_cursor', $cursor, false );
			if ( ! preg_match( '/^[a-f0-9]{64}$/D', $id ) ) continue;
			$key = 'mi_email_job_' . $id;
			$job = get_option( $key );
			if ( ! is_array( $job ) || 'pending' !== $job['state'] ) { self::remove_pending( $id ); continue; }
			if ( (int) ( $job['start_at'] ?? 0 ) > time() ) continue;
			$payload = $job['payload'];
			if ( empty( $payload['test_only'] ) && 'ANTEPRIMA' !== $job['mode'] && ( $job['mode'] !== MI_Spedizione_Email::modalita() || ! self::mode_ready( $job['mode'] ) ) ) continue;
			if ( 'ANTEPRIMA' === $job['mode'] ) $payload['allow_operational'] = false;
			if ( ! empty( $job['start_at'] ) && empty( $job['started_at'] ) ) {
				$job['started_at'] = time();
				if ( ! update_option( $key, $job, false ) ) continue;
				delete_option( 'mi_email_scheduled_' . $job['owner'] . '_' . $id );
			}
			$chunk = array_slice( $payload['recipients'], $job['offset'], 10 );
			$payload['recipients'] = $chunk;
			$payload['communication_id'] = 'job-' . $id . '-' . $job['offset'];
			try { $result = MI_Spedizione_Email::accoda_comunicazione_operativa( $payload ); }
			catch ( Throwable $error ) { $result = new WP_Error( 'mi_email_job_failed', 'Preparazione interrotta: verifica il registro prima di ripetere la richiesta.' ); }
			finally { MI_Event_Deletion::release( $job['event_id'] ); }
			if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'mi_event_busy', 'mi_operational_email_busy' ), true ) ) {
				update_option( 'mi_email_job_cursor', 0, false );
				if ( ! wp_next_scheduled( 'mi_prepara_comunicazioni' ) ) wp_schedule_single_event( time() + 30, 'mi_prepara_comunicazioni' );
				return;
			}
			if ( is_wp_error( $result ) ) {
				$job['state'] = 'failed'; $job['error'] = $result->get_error_message(); unset( $job['payload'] );
			} else {
				$job['origin_keys'] = array_values( array_unique( array_merge( $job['origin_keys'], $result['origin_keys'] ?? array() ) ) );
				$job['offset'] += count( $chunk );
				$job['excluded'] = (int) ( $job['excluded'] ?? 0 ) + (int) ( $result['excluded'] ?? 0 );
				update_option( 'mi_email_job_cursor', 0, false );
				if ( $job['offset'] >= $job['total'] ) { $job['state'] = 'prepared'; unset( $job['payload'] ); }
			}
			update_option( $key, $job, false );
			if ( 'pending' !== $job['state'] ) self::remove_pending( $id );
			else {
				if ( ! wp_next_scheduled( 'mi_prepara_comunicazioni' ) ) wp_schedule_single_event( time() + 30, 'mi_prepara_comunicazioni' );
				add_action( 'shutdown', array( __CLASS__, 'dispatch' ) );
			}
			return;
		}
		if ( count( $ids ) < 10 ) { update_option( 'mi_email_job_cursor', 0, false ); return; }
		}
		if ( ! wp_next_scheduled( 'mi_prepara_comunicazioni' ) ) wp_schedule_single_event( time() + 1, 'mi_prepara_comunicazioni' );
	}
	public static function status( $id ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', (string) $id ) ) return false;
		$job = get_option( 'mi_email_job_' . $id );
		if ( ! is_array( $job ) || ! MI_Access::can_access_event( $job['event_id'] ) || ( $job['owner'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) return false;
		$delivery = self::delivery_state( $job );
		if ( false === $delivery ) return false;
		list( $counts, $errors ) = $delivery;
		$sent = ( $counts['SENT'] ?? 0 ) + ( $counts['TEST_SENT'] ?? 0 ); $failed = ( $counts['FAILED'] ?? 0 ) + ( $counts['TEST_FAILED'] ?? 0 );
		$pending = ( $counts['PENDING'] ?? 0 ) + ( $counts['TEST_PENDING'] ?? 0 ) + ( $counts['SENDING'] ?? 0 ) + ( $counts['TEST_SENDING'] ?? 0 );
		$paused = empty( $job['test_only'] ) && 'ANTEPRIMA' !== $job['mode'] && ( $job['mode'] !== MI_Spedizione_Email::modalita() || ! self::mode_ready( $job['mode'] ) );
		$done = 'pending' !== $job['state'] && ! $pending;
		// Separato dal job: il polling non sovrascrive la preparazione di un worker.
		$completion_key = 'mi_email_job_completed_' . $id;
		$completed_at = (int) get_option( $completion_key, 0 );
		if ( $done && ! $completed_at ) {
			add_option( $completion_key, time(), '', false );
			$completed_at = (int) get_option( $completion_key, time() );
		} elseif ( ! $done && $completed_at ) {
			// Una coda riaperta deve tornare visibile e ricevere una nuova scadenza.
			delete_option( $completion_key );
			$completed_at = 0;
		}
		return array( 'dismiss_after' => $done ? max( 0, $completed_at + self::PROGRESS_RETENTION_SECONDS - time() ) : null, 'total' => $job['total'], 'prepared' => $job['offset'], 'queued' => count( $job['origin_keys'] ), 'excluded' => (int) ( $job['excluded'] ?? 0 ), 'test_sent' => (int) ( $counts['TEST_SENT'] ?? 0 ), 'sent' => $sent, 'failed' => $failed, 'pending' => $pending, 'preview' => $counts['PREVIEW'] ?? 0, 'done' => $done, 'paused' => $paused, 'quota_remaining' => null === get_option( 'mi_email_quota_remaining', null ) ? null : (int) get_option( 'mi_email_quota_remaining' ), 'retry_after' => max( 0, (int) get_option( 'mi_email_retry_after', 0 ) - time() ), 'error' => $job['error'] ?: implode( ' ', array_unique( $errors ) ) );
	}
	private static function delivery_state( array $job ) {
		global $wpdb;
		$counts = array(); $errors = array();
		if ( $job['origin_keys'] ) {
			$placeholders = implode( ',', array_fill( 0, count( $job['origin_keys'] ), '%s' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status,COUNT(*) AS total,MAX(last_error) AS last_error FROM {$wpdb->prefix}mi_email_outbox WHERE origin_key IN ({$placeholders}) GROUP BY status", $job['origin_keys'] ), ARRAY_A );
			if ( $wpdb->last_error || ! is_array( $rows ) ) return false;
			foreach ( $rows as $row ) { $counts[$row['status']] = (int) $row['total']; if ( $row['last_error'] ) $errors[] = sanitize_text_field( $row['last_error'] ); }
		}
		return array( $counts, $errors );
	}
	/** Elimina solo riepiloghi conclusi; la coda e le ricevute WordPress restano intatte. */
	public static function cleanup() {
		global $wpdb;
		$lock = 'mi_mail_worker_' . md5( $wpdb->prefix );
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) return;
		try {
			$cursor = absint( get_option( 'mi_email_cleanup_cursor', 0 ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_id,option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id>%d ORDER BY option_id LIMIT 100", $wpdb->esc_like( 'mi_email_job_' ) . '%', $cursor ), ARRAY_A );
			if ( $wpdb->last_error || ! is_array( $rows ) ) return;
			foreach ( $rows as $row ) {
				$cursor = (int) $row['option_id'];
				if ( preg_match( '/^mi_email_job_completed_([a-f0-9]{64})$/D', $row['option_name'], $orphan ) ) {
					$stamp = (int) get_option( $row['option_name'], 0 );
					if ( $stamp > 0 && $stamp <= time() - self::JOB_RETENTION_SECONDS && ! get_option( 'mi_email_job_' . $orphan[1] ) ) delete_option( $row['option_name'] );
					continue;
				}
				if ( ! preg_match( '/^mi_email_job_([a-f0-9]{64})$/D', $row['option_name'], $match ) ) continue;
				$id = $match[1]; $job = get_option( $row['option_name'] );
				if ( ! is_array( $job ) || ! in_array( $job['state'] ?? '', array( 'prepared', 'failed', 'cancelled' ), true ) ) continue;
				$delivery = self::delivery_state( $job );
				if ( false === $delivery ) continue;
				$counts = $delivery[0];
				$completion = 'mi_email_job_completed_' . $id;
				// Stati sconosciuti, righe mancanti e invii riaperti non provano una conclusione.
				if ( array_diff( array_keys( $counts ), array( 'SENT', 'TEST_SENT', 'FAILED', 'TEST_FAILED', 'PREVIEW', 'SUPERSEDED' ) ) || array_sum( $counts ) < count( $job['origin_keys'] ) ) {
					delete_option( $completion );
					continue;
				}
				$completed_at = (int) get_option( $completion, 0 );
				if ( ! $completed_at ) { add_option( $completion, time(), '', false ); continue; }
				if ( $completed_at > time() - self::JOB_RETENTION_SECONDS ) continue;
				delete_option( $row['option_name'] );
				delete_option( $completion );
				self::remove_pending( $id );
				foreach ( array( 'mi_email_last_job_', 'mi_email_last_test_job_' ) as $prefix ) {
					$key = $prefix . absint( $job['owner'] );
					if ( get_option( $key ) === $id ) delete_option( $key );
				}
			}
			update_option( 'mi_email_cleanup_cursor', count( $rows ) === 100 ? $cursor : 0, false );
			if ( count( $rows ) === 100 && ! wp_next_scheduled( 'mi_pulisci_email_jobs' ) ) wp_schedule_single_event( time() + 30, 'mi_pulisci_email_jobs' );
		} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}
	public static function progress() {
		if ( ! is_user_logged_in() || MI_Access::is_suspended() || ( ! current_user_can( 'mi_manage_communications' ) && ! current_user_can( 'manage_options' ) ) ) wp_send_json_error( array( 'message' => 'Accesso non consentito.' ), 403 );
		check_ajax_referer( 'mi_email_progress', 'nonce' );
		$state = self::status( sanitize_key( $_POST['job'] ?? '' ) );
		if ( ! $state ) wp_send_json_error( array( 'message' => 'Stato non disponibile.' ), 404 );
		wp_send_json_success( $state );
	}
	public static function render_progress( $id ) {
		if ( ! is_string( $id ) ) return;
		$job = preg_match( '/^[a-f0-9]{64}$/D', $id ) ? get_option( 'mi_email_job_' . $id ) : false;
		if ( is_array( $job ) && ( 'cancelled' === $job['state'] || ( 'pending' === $job['state'] && ! empty( $job['start_at'] ) && empty( $job['started_at'] ) ) ) ) return;
		$state = self::status( $id );
		if ( ! $state || ( $state['done'] && 0 === $state['dismiss_after'] ) ) return;
		echo '<p role="status" aria-live="polite" data-mi-email-progress data-job="' . esc_attr( $id ) . '" data-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'mi_email_progress' ) ) . '">Preparazione e invio in background. Verifica dell’avanzamento…</p>';
	}
}
