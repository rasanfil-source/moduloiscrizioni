<?php

defined( 'ABSPATH' ) || exit;

/** One event lease covers SQL writes and their external effects until request shutdown. */
final class MI_Event_Deletion {
	private static $leases = array();
	private static $finalizing = 0;
	private const CHILDREN = array( 'mi_registration_items', 'mi_participants', 'mi_payments', 'mi_registration_events', 'mi_email_outbox' );
	private const DIRECT = array( 'mi_event_counters', 'mi_ticket_counters', 'mi_event_revisions', 'mi_rooms', 'mi_management_state', 'mi_management_requests', 'mi_booking_codes' );

	public static function boot() {
		add_filter( 'pre_delete_post', array( __CLASS__, 'guard_delete' ), 10, 3 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_post' ), 10, 2 );
		add_action( 'save_post_' . MI_Event_Post_Type::EVENT_TYPE, array( __CLASS__, 'guard_saved_post' ), 0 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 20, 2 );
		add_action( 'admin_post_mi_delete_event', array( __CLASS__, 'handle' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}
	public static function allowed() {
		return is_user_logged_in() && ! MI_Access::is_suspended() && MI_Access::is_global_manager() && ( current_user_can( 'mi_manage_events' ) || current_user_can( 'manage_options' ) );
	}
	public static function job( $id ) { return get_option( 'mi_delete_event_' . absint( $id ), array() ); }
	public static function enter( $id, $deleting = false ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) return new WP_Error( 'mi_event_missing', 'Evento non disponibile.' );
		if ( ! isset( self::$leases[ $id ] ) ) {
			$name = 'mi_event_' . md5( $wpdb->prefix . ':' . $id );
			if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $name ) ) ) return new WP_Error( 'mi_event_busy', 'È in corso un’altra operazione sull’evento. Riprova tra poco.' );
			self::$leases[ $id ] = $name;
			register_shutdown_function( static function () use ( $name ) { global $wpdb; $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); } );
		}
		// Refresh after acquiring the lease: another request may have started deletion.
		wp_cache_delete( 'mi_delete_event_' . $id, 'options' );
		if ( ! $deleting && self::job( $id ) ) return new WP_Error( 'mi_event_deleting', 'Evento in eliminazione: le modifiche sono bloccate.' );
		if ( ! $deleting && MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $id ) ) return new WP_Error( 'mi_event_missing', 'Evento non disponibile.' );
		return true;
	}
	public static function registration_event( $id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $id ) );
	}
	public static function participant_event( $id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT r.event_id FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE p.id=%d", $id ) );
	}
	public static function url( $id ) { return add_query_arg( array( 'mi_portal' => 1, 'mi_portal_view' => 'delete', 'mi_portal_event' => absint( $id ) ), home_url( '/' ) ); }
	public static function row_actions( $actions, $post ) {
		if ( MI_Event_Post_Type::EVENT_TYPE === $post->post_type && self::allowed() ) $actions['delete'] = '<a href="' . esc_url( self::url( $post->ID ) ) . '">Elimina evento e dati…</a>';
		return $actions;
	}
	public static function guard_delete( $delete, $post, $force ) {
		if ( MI_Event_Post_Type::EVENT_TYPE !== $post->post_type || self::$finalizing === (int) $post->ID ) return $delete;
		if ( ! wp_doing_cron() && self::allowed() ) { wp_safe_redirect( self::url( $post->ID ) ); exit; }
		return false;
	}
	public static function guard_post( $data, $postarr ) {
		$id = absint( $postarr['ID'] ?? 0 );
		if ( $id && MI_Event_Post_Type::EVENT_TYPE === ( $data['post_type'] ?? '' ) && self::$finalizing !== $id ) {
			$result = self::enter( $id );
			if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ), 'Evento non disponibile', array( 'response' => 409 ) );
		}
		return $data;
	}
	public static function guard_saved_post( $id ) {
		if ( self::$finalizing === (int) $id ) return;
		$result = self::enter( $id );
		if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ), 'Evento non disponibile', array( 'response' => 409 ) );
	}
	private static function query( $sql ) {
		global $wpdb;
		$result = $wpdb->query( $sql );
		if ( false === $result ) throw new RuntimeException( 'Operazione sul database non riuscita. Riprova.' );
		return $result;
	}
	private static function save_job( $id, $job ) {
		$key = 'mi_delete_event_' . $id;
		update_option( $key, $job, false );
		wp_cache_delete( $key, 'options' );
		if ( get_option( $key ) !== $job ) throw new RuntimeException( 'Avanzamento non salvato. Riprova.' );
	}
	public static function preview( $id ) {
		global $wpdb;
		$post = get_post( $id );
		if ( ! $post || MI_Event_Post_Type::EVENT_TYPE !== $post->post_type ) throw new RuntimeException( 'Evento non trovato.' );
		$counts = array();
		$counts['Iscrizioni'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d", $id ) );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Conteggi non disponibili.' );
		foreach ( array( 'mi_participants' => 'Partecipanti', 'mi_payments' => 'Movimenti di pagamento', 'mi_email_outbox' => 'Comunicazioni delle iscrizioni' ) as $table => $label ) {
			$counts[ $label ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} c JOIN {$wpdb->prefix}mi_registrations r ON r.id=c.registration_id WHERE r.event_id=%d", $id ) );
			if ( $wpdb->last_error ) throw new RuntimeException( 'Conteggi non disponibili.' );
		}
		$counts['Camere'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mi_rooms WHERE event_id=%d", $id ) );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Conteggi non disponibili.' );
		$event_emails = self::event_emails( $id );
		$counts['Comunicazioni dell’evento'] = count( $event_emails );
		$data = array( 'title' => $post->post_title, 'status' => $post->post_status, 'counts' => $counts, 'sheet_url' => (string) get_post_meta( $id, '_mi_operational_sheet_url', true ), 'sheet_id' => (string) get_post_meta( $id, '_mi_operational_sheet_id', true ), 'page_id' => absint( get_post_meta( $id, '_mi_registration_page_id', true ) ) );
		// Include all mutable rows, not just counts, so changed values require review.
		$hash = hash_init( 'sha256' );
		hash_update( $hash, wp_json_encode( array( $data, get_post_meta( $id ), $post->post_modified_gmt ) ) );
		foreach ( array_merge( array( 'mi_registrations' ), self::DIRECT ) as $table ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$table} WHERE event_id=%d", $id ), ARRAY_A );
			if ( $wpdb->last_error ) throw new RuntimeException( 'Riepilogo non disponibile.' );
			hash_update( $hash, wp_json_encode( $rows ) );
		}
		foreach ( self::CHILDREN as $table ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.* FROM {$wpdb->prefix}{$table} c JOIN {$wpdb->prefix}mi_registrations r ON r.id=c.registration_id WHERE r.event_id=%d ORDER BY c.id", $id ), ARRAY_A );
			if ( $wpdb->last_error ) throw new RuntimeException( 'Riepilogo non disponibile.' );
			hash_update( $hash, wp_json_encode( $rows ) );
		}
		hash_update( $hash, wp_json_encode( $event_emails ) );
		$data['fingerprint'] = hash_final( $hash );
		return $data;
	}
	private static function event_emails( $id ) {
		global $wpdb;
		// Legacy event notifications use registration_id=0 and a structured payload.
		$rows = $wpdb->get_results( "SELECT id,payload_json FROM {$wpdb->prefix}mi_email_outbox WHERE registration_id=0 ORDER BY id", ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Comunicazioni non disponibili.' );
		return array_values( array_filter( $rows, static function ( $row ) use ( $id ) { $payload = json_decode( $row['payload_json'], true ); return (int) ( $payload['event_id'] ?? 0 ) === (int) $id; } ) );
	}
	public static function begin( $id, $fingerprint, $request, $mode, $title, $confirmed ) {
		if ( ! self::allowed() || ! in_array( $mode, array( 'trash', 'keep' ), true ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $request ) || ! $confirmed ) return new WP_Error( 'mi_delete_request', 'Controlla la scelta sul foglio e conferma l’eliminazione.' );
		$lock = self::enter( $id, true );
		if ( is_wp_error( $lock ) ) return $lock;
		try {
			$job = self::job( $id );
			if ( $job ) {
				if ( $job['request'] !== $request || $job['mode'] !== $mode ) throw new RuntimeException( 'È già presente una procedura: usa Riprendi eliminazione.' );
				return $job;
			}
			$preview = self::preview( $id );
			if ( ! hash_equals( $preview['fingerprint'], $fingerprint ) ) throw new RuntimeException( 'I dati sono cambiati. Controlla il riepilogo aggiornato e conferma nuovamente.' );
			if ( $preview['counts']['Iscrizioni'] && $title !== $preview['title'] ) throw new RuntimeException( 'Riscrivi esattamente il titolo dell’evento.' );
			$job = array( 'request' => $request, 'mode' => $mode, 'stage' => 'google', 'title' => $preview['title'], 'sheet_url' => $preview['sheet_url'], 'sheet_id' => $preview['sheet_id'], 'page_id' => $preview['page_id'], 'actor' => get_current_user_id(), 'error' => '' );
			self::save_job( $id, $job );
			return $job;
		} catch ( Throwable $error ) { return new WP_Error( 'mi_delete_error', $error->getMessage() ); }
	}
	public static function advance( $id ) {
		global $wpdb;
		if ( ! self::allowed() ) return new WP_Error( 'mi_delete_access', 'Accesso non consentito.' );
		$lease = self::enter( $id, true );
		if ( is_wp_error( $lease ) ) return $lease;
		$job = self::job( $id );
		if ( ! $job || 'done' === $job['stage'] ) return $job;
		try {
			if ( 'google' === $job['stage'] ) {
				$codes = $wpdb->get_col( $wpdb->prepare( "SELECT order_code FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d", $id ) );
				if ( $wpdb->last_error ) throw new RuntimeException( 'Codici iscrizione non disponibili.' );
				$result = MI_Workspace_Client::request( 'ELIMINA_DATI_EVENTO', array( 'id_evento' => (string) $id, 'request_id' => $job['request'], 'mode' => $job['mode'], 'id_foglio' => $job['sheet_id'], 'order_codes' => $codes ) );
				if ( is_wp_error( $result ) ) throw new RuntimeException( 'Pulizia Google non completata: ' . $result->get_error_message() );
				if ( empty( $result['complete'] ) ) { $job['error'] = ''; self::save_job( $id, $job ); return $job; }
				$job['sheet_url'] = (string) ( $result['sheet_url'] ?? $job['sheet_url'] );
				$job['stage'] = 'sql'; self::save_job( $id, $job );
			}
			if ( 'sql' === $job['stage'] ) {
				self::query( 'START TRANSACTION' );
				try {
					foreach ( self::CHILDREN as $table ) self::query( $wpdb->prepare( "DELETE c FROM {$wpdb->prefix}{$table} c JOIN {$wpdb->prefix}mi_registrations r ON r.id=c.registration_id WHERE r.event_id=%d", $id ) );
					foreach ( self::event_emails( $id ) as $row ) self::query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}mi_email_outbox WHERE id=%d AND registration_id=0", $row['id'] ) );
					foreach ( self::DIRECT as $table ) self::query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}{$table} WHERE event_id=%d", $id ) );
					self::query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d", $id ) );
					self::query( 'COMMIT' );
				} catch ( Throwable $error ) { $wpdb->query( 'ROLLBACK' ); throw $error; }
				$job['stage'] = 'wordpress'; self::save_job( $id, $job );
			}
			if ( 'wordpress' === $job['stage'] ) {
				$users = get_users( array( 'meta_key' => '_mi_event_scope', 'fields' => 'ID' ) );
				foreach ( $users as $user_id ) {
					$scope = array_map( 'absint', (array) get_user_meta( $user_id, '_mi_event_scope', true ) );
					if ( in_array( (int) $id, $scope, true ) ) {
						$new_scope = array_values( array_diff( $scope, array( (int) $id ) ) );
						update_user_meta( $user_id, '_mi_event_scope', $new_scope );
						if ( get_user_meta( $user_id, '_mi_event_scope', true ) !== $new_scope ) throw new RuntimeException( 'Assegnazioni operatori non aggiornate.' );
					}
				}
				$page = $job['page_id'] ? get_post( $job['page_id'] ) : null;
				if ( $page ) {
					$owned = 'page' === $page->post_type && trim( $page->post_content ) === '[modulo_iscrizioni event="' . (int) $id . '"]';
					$shared = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_mi_registration_page_id' AND meta_value=%s AND post_id<>%d LIMIT 1", (string) $page->ID, $id ) );
					if ( $wpdb->last_error ) throw new RuntimeException( 'Verifica pagina non riuscita.' );
					if ( $owned && ! $shared ) { if ( ! wp_delete_post( $page->ID, true ) ) throw new RuntimeException( 'Pagina non eliminata.' ); }
					else $job['page_warning'] = 'La pagina collegata è stata conservata perché contiene altri contenuti o è condivisa.';
				}
				self::$finalizing = (int) $id;
				try { if ( get_post( $id ) && ! wp_delete_post( $id, true ) ) throw new RuntimeException( 'Evento non eliminato.' ); } finally { self::$finalizing = 0; }
				clean_post_cache( $id );
				$job['stage'] = 'done'; $job['error'] = ''; unset( $job['title'] ); self::save_job( $id, $job );
			}
			return $job;
		} catch ( Throwable $error ) {
			$job['error'] = $error->getMessage(); self::save_job( $id, $job );
			return new WP_Error( 'mi_delete_retry', $job['error'] );
		}
	}
	public static function handle() {
		if ( ! self::allowed() ) wp_die( 'Accesso non consentito.', '', array( 'response' => 403 ) );
		$id = absint( $_POST['event_id'] ?? 0 );
		check_admin_referer( 'mi_delete_event_' . $id );
		$job = self::job( $id );
		$result = $job ?: self::begin( $id, sanitize_text_field( wp_unslash( $_POST['fingerprint'] ?? '' ) ), sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) ), sanitize_key( $_POST['sheet_mode'] ?? '' ), sanitize_text_field( wp_unslash( $_POST['confirm_title'] ?? '' ) ), ! empty( $_POST['confirm_delete'] ) );
		if ( ! is_wp_error( $result ) ) $result = self::advance( $id );
		$url = self::url( $id );
		if ( is_wp_error( $result ) ) $url = add_query_arg( 'mi_delete_error', $result->get_error_message(), $url );
		wp_safe_redirect( $url ); exit;
	}
	public static function admin_menu() { add_submenu_page( 'edit.php?post_type=mi_event', 'Eliminazioni', 'Eliminazioni', 'mi_manage_all_events', 'mi-event-deletions', array( __CLASS__, 'admin_page' ) ); }
	public static function admin_page() {
		if ( ! self::allowed() ) return;
		global $wpdb;
		echo '<div class="wrap"><h1>Eliminazioni eventi</h1><p>Le procedure interrotte restano bloccate alle modifiche. Aprile per riprendere la pulizia.</p><ul>';
		$rows = $wpdb->get_results( "SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE 'mi_delete_event_%'", ARRAY_A );
		foreach ( $rows as $row ) { $job = maybe_unserialize( $row['option_value'] ); if ( ! is_array( $job ) || 'done' === ( $job['stage'] ?? '' ) ) continue; $id = (int) substr( $row['option_name'], strlen( 'mi_delete_event_' ) ); echo '<li><a href="' . esc_url( self::url( $id ) ) . '">' . esc_html( $job['title'] ?? $id ) . ' — Riprendi eliminazione</a></li>'; }
		echo '</ul></div>';
	}
	public static function render() {
		if ( ! self::allowed() ) { echo '<p>Accesso non consentito.</p>'; return; }
		$id = absint( $_GET['mi_portal_event'] ?? 0 );
		$job = self::job( $id );
		echo '<section class="mi-portal-card"><h2>Eliminazione evento</h2>';
		if ( 'done' === ( $job['stage'] ?? '' ) ) {
			echo '<p role="status">Evento eliminato. La pulizia di WordPress e Google è completata.</p>';
			if ( 'keep' === $job['mode'] && $job['sheet_url'] ) echo '<p><a target="_blank" rel="noopener" href="' . esc_url( $job['sheet_url'] ) . '">Apri il foglio conservato, scollegato dall’evento</a></p>';
			if ( ! empty( $job['page_warning'] ) ) echo '<p>' . esc_html( $job['page_warning'] ) . '</p>';
		} else {
			$error = sanitize_text_field( wp_unslash( $_GET['mi_delete_error'] ?? ( $job['error'] ?? '' ) ) );
			if ( $error ) echo '<p role="alert">' . esc_html( $error ) . '</p>';
			if ( $job ) echo '<p class="mi-action-progress" role="status">' . esc_html( $job['title'] ) . ' — In eliminazione. ' . ( 'google' === $job['stage'] ? 'Pulizia Google da completare.' : 'Pulizia WordPress da completare.' ) . ' Le nuove operazioni sono bloccate.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate data-mi-delete-form' . ( $job && ! $error ? ' data-mi-delete-continue' : '' ) . '><input type="hidden" name="action" value="mi_delete_event"><input type="hidden" name="event_id" value="' . esc_attr( $id ) . '">';
			wp_nonce_field( 'mi_delete_event_' . $id );
			if ( ! $job ) {
				try {
					$data = self::preview( $id );
					echo '<h3>' . esc_html( $data['title'] ) . '</h3><dl>';
					foreach ( $data['counts'] as $label => $count ) echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $count ) . '</dd>';
					echo '</dl><p>Questi dati saranno eliminati definitivamente, insieme a contatori, revisioni e repliche centrali. Gruppi, utenti e immagini condivise rimarranno.</p>';
					if ( $data['sheet_url'] ) echo '<p><a href="' . esc_url( $data['sheet_url'] ) . '" target="_blank" rel="noopener">Apri il foglio collegato</a></p>';
					echo '<fieldset><legend>Eventuale foglio Google</legend><label class="mi-check"><input type="radio" name="sheet_mode" value="trash"> Sposta anche il foglio Google nel cestino</label><label class="mi-check"><input type="radio" name="sheet_mode" value="keep"> Conserva il foglio come copia scollegata</label></fieldset>';
					echo '<input type="hidden" name="fingerprint" value="' . esc_attr( $data['fingerprint'] ) . '"><input type="hidden" name="request_id" value="' . esc_attr( wp_generate_uuid4() ) . '">';
					if ( $data['counts']['Iscrizioni'] ) echo '<label>Per confermare riscrivi il titolo dell’evento<input name="confirm_title" autocomplete="off"></label>';
					echo '<label class="mi-check"><input type="checkbox" name="confirm_delete" value="1"> Ho compreso che i dati dell’evento saranno eliminati</label><button type="submit" class="mi-danger">Elimina evento e dati</button>';
				} catch ( Throwable $error ) { echo '<p role="alert">' . esc_html( $error->getMessage() ) . '</p>'; }
			} else echo '<button type="submit">Riprendi eliminazione</button>';
			echo '</form>';
		}
		echo '<p><a href="' . esc_url( add_query_arg( array( 'mi_portal' => 1, 'mi_portal_view' => 'manage' ), home_url( '/' ) ) ) . '">Torna a Gestisci eventi</a></p></section>';
	}
}
