<?php
defined( 'ABSPATH' ) || exit;

/** Opening a sheet is a verified refresh, never a direct link to a stale projection. */
final class MI_Sheet_Open {
	private static $working = false;
	public static function boot() {
		add_action( 'template_redirect', array( __CLASS__, 'render' ), -105 );
		add_action( 'wp_ajax_mi_open_sheet', array( __CLASS__, 'ajax' ) );
		add_action( 'mi_refresh_event_sheet', array( __CLASS__, 'refresh_background' ) );
	}
	/** Durable, coalesced projection job; never call Google in the saving request. */
	public static function enqueue( $event_id, $delay = 0 ) {
		$event_id = absint( $event_id );
		if ( ! $event_id || self::$working ) return;
		$args = array( $event_id );
		if ( ! wp_next_scheduled( 'mi_refresh_event_sheet', $args ) ) wp_schedule_single_event( time() + $delay, 'mi_refresh_event_sheet', $args );
		if ( ! $delay && ! wp_doing_cron() ) add_action( 'shutdown', static function () { spawn_cron(); } );
	}
	public static function refresh_background( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! get_post_meta( $event_id, '_mi_operational_sheet_url', true ) || 'trash' === get_post_status( $event_id ) ) return;
		if ( class_exists( 'MI_Event_Deletion' ) && MI_Event_Deletion::job( $event_id ) ) return;
		// Schedule recovery first, including when a remote request exhausts PHP's time budget.
		self::enqueue( $event_id, 60 );
		self::$working = true;
		try {
			$started = microtime( true );
			$token = (string) get_transient( 'mi_sheet_worker_' . $event_id );
			do {
				$result = self::advance( $event_id, $token, true );
				if ( is_wp_error( $result ) || ! empty( $result['ready'] ) || ! empty( $result['retry_after'] ) ) break;
				$token = $result['token'];
			} while ( microtime( true ) - $started < 20 );
			if ( is_wp_error( $result ) ) {
				delete_transient( 'mi_sheet_worker_' . $event_id );
				$attempts = (int) get_transient( 'mi_sheet_failures_' . $event_id ) + 1;
				set_transient( 'mi_sheet_failures_' . $event_id, $attempts, 7200 );
				wp_clear_scheduled_hook( 'mi_refresh_event_sheet', array( $event_id ) );
				wp_schedule_single_event( time() + min( 3600, 60 * ( 2 ** min( $attempts, 6 ) ) ), 'mi_refresh_event_sheet', array( $event_id ) );
				return;
			}
			if ( ! empty( $result['ready'] ) ) {
				delete_transient( 'mi_sheet_failures_' . $event_id );
				delete_transient( 'mi_sheet_worker_' . $event_id );
				wp_clear_scheduled_hook( 'mi_refresh_event_sheet', array( $event_id ) );
			} else set_transient( 'mi_sheet_worker_' . $event_id, $result['token'], 600 );
		} finally { self::$working = false; }
	}
	public static function url( $event_id ) { return add_query_arg( 'mi_open_sheet', absint( $event_id ), home_url( '/' ) ); }
	private static function snapshot( $event_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,order_code,workspace_revision,workspace_status FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d ORDER BY id", $event_id ), ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Prenotazioni non disponibili.' );
		$revision = $wpdb->get_var( $wpdb->prepare( "SELECT revision FROM {$wpdb->prefix}mi_management_state WHERE event_id=%d", $event_id ) );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Versione evento non disponibile.' );
		$rooms = $wpdb->get_results( $wpdb->prepare( "SELECT code,name,capacity FROM {$wpdb->prefix}mi_rooms WHERE event_id=%d ORDER BY code", $event_id ), ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Camere non disponibili.' );
		wp_cache_delete( $event_id, 'post_meta' );
		$profile = MI_Field_Schema::resolved_operational_profile( $event_id );
		$schema = MI_Field_Schema::workspace_event_schema( $event_id );
		$versions = array_map( static function ( $row ) { return array( 'id' => (int) $row['id'], 'order_code' => $row['order_code'], 'revision' => (string) $row['workspace_revision'] ); }, $rows );
		return array( 'schema' => $schema, 'profile' => $profile, 'rows' => $rows, 'versions' => $versions, 'rooms' => $rooms, 'revision' => (string) ( $revision ?? '0' ), 'fingerprint' => hash( 'sha256', wp_json_encode( array( $versions, $revision, $rooms, $profile, $schema, get_post_meta( $event_id, '_mi_operational_sheet_url', true ), get_post_meta( $event_id, '_mi_workspace_event_pending', true ) ) ) ) );
	}
	public static function step( $event_id, $token = '' ) {
		if ( ! MI_Portal_Management::allowed() || ! MI_Access::can_access_event( $event_id ) ) return new WP_Error( 'mi_sheet_access', 'Evento non accessibile.' );
		return self::advance( $event_id, $token );
	}
	private static function advance( $event_id, $token = '', $background = false ) {
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( $event_id ); if ( is_wp_error( $lease ) ) return $lease; }
		try {
			$current = self::snapshot( $event_id );
			$receipt = get_transient( 'mi_sheet_ready_' . $event_id );
			$pending = array_filter( $current['rows'], static function ( $r ) { return 'SYNCED' !== $r['workspace_status']; } );
			// Editable sheets still need Google's check for uncommitted operator edits.
			if ( '' === $token && ! $pending && 'ZERO' === $current['schema']['pricing'] && is_array( $receipt ) && ! empty( $receipt['read_only'] ) && hash_equals( $current['fingerprint'], $receipt['fingerprint'] ) ) return array( 'ready' => true, 'url' => $receipt['url'] );
			$owner = $background ? 'background' : get_current_user_id();
			if ( '' === $token ) {
				$token = bin2hex( random_bytes( 16 ) );
				$session = array( 'event' => $event_id, 'fingerprint' => $current['fingerprint'], 'queue' => array_values( array_map( 'intval', array_column( array_filter( $current['rows'], static function ( $r ) { return 'SYNCED' !== $r['workspace_status']; } ), 'id' ) ) ), 'repaired' => false );
			} else {
				if ( ! preg_match( '/^[a-f0-9]{32}$/D', $token ) ) throw new RuntimeException( 'Richiesta non valida.' );
				$session = get_transient( 'mi_sheet_open_' . $owner . '_' . $token );
				if ( ! is_array( $session ) || $session['event'] !== $event_id ) throw new RuntimeException( 'Sessione scaduta. Riprova.' );
			}
			$key = 'mi_sheet_open_' . $owner . '_' . $token;
			if ( ! hash_equals( $session['fingerprint'], $current['fingerprint'] ) ) throw new RuntimeException( 'I dati sono cambiati durante l’apertura. Riprova per leggere la situazione aggiornata.' );
			if ( $session['queue'] ) {
				$id = $session['queue'][0];
				if ( 'SYNCED' !== MI_Registration_Service::sync_workspace( $id, true ) ) throw new RuntimeException( 'Aggiornamento Google non completato. Riprova tra poco.' );
				array_shift( $session['queue'] );
				set_transient( $key, $session, 600 );
				return array( 'ready' => false, 'token' => $token );
			}
			$result = MI_Workspace_Client::request( 'PREPARA_APERTURA_FOGLIO', array( 'event_id' => (string) $event_id, 'event_schema' => $current['schema'], 'operational_profile' => $current['profile'], 'registrations' => $current['versions'], 'rooms' => $current['rooms'], 'workspace_event_revision' => $current['revision'], 'background' => $background ) );
			if ( is_wp_error( $result ) ) throw new RuntimeException( $result->get_error_message() );
			if ( ! empty( $result['busy'] ) ) {
				$session['busy_since'] = $session['busy_since'] ?? time();
				if ( time() - $session['busy_since'] >= 120 ) throw new RuntimeException( 'Google sta ancora completando un aggiornamento. Attesa interrotta dopo due minuti; riprova tra poco.' );
				set_transient( $key, $session, 600 );
				return array( 'ready' => false, 'token' => $token, 'retry_after' => 3, 'message' => 'Google sta completando un aggiornamento. Nuovo tentativo automatico tra pochi secondi…' );
			}
			if ( ! empty( $result['needs_sync'] ) && ! $session['repaired'] ) {
				$by_code = array_column( $current['rows'], 'id', 'order_code' );
				foreach ( $result['needs_sync'] as $code ) { if ( ! isset( $by_code[$code] ) ) throw new RuntimeException( 'Replica Google incoerente.' ); $session['queue'][] = (int) $by_code[$code]; }
				$session['repaired'] = true; set_transient( $key, $session, 600 );
				return array( 'ready' => false, 'token' => $token );
			}
			if ( empty( $result['ready'] ) || empty( $result['event_sheet_complete'] ) || ! preg_match( '~^https://docs\.google\.com/spreadsheets/d/[A-Za-z0-9_-]+(?:/|$)~D', $result['url_foglio'] ?? '' ) ) throw new RuntimeException( 'Il foglio non è aggiornato. Conferma prima le modifiche pendenti tramite Sincronizza nel portale, poi riprova.' );
			if ( ( $result['operational_profile'] ?? '' ) !== $current['profile'] ) throw new RuntimeException( 'La versione Workspace non conferma il profilo corrente. Aggiorna Apps Script e la distribuzione Web App, poi riprova.' );
			if ( MI_Workspace_Client::stable_json( $result['event_schema'] ?? null ) !== MI_Workspace_Client::stable_json( $current['schema'] ) ) throw new RuntimeException( 'Workspace non conferma i campi dell’evento. Aggiorna Apps Script e la distribuzione Web App, poi riprova.' );
			if ( class_exists( 'MI_Event_Deletion' ) && is_wp_error( MI_Event_Deletion::enter( $event_id ) ) ) throw new RuntimeException( 'Evento non disponibile.' );
			$after = self::snapshot( $event_id );
			if ( ! hash_equals( $current['fingerprint'], $after['fingerprint'] ) ) throw new RuntimeException( 'I dati sono cambiati durante l’apertura. Riprova.' );
			delete_transient( $key );
			set_transient( 'mi_sheet_ready_' . $event_id, array( 'fingerprint' => $current['fingerprint'], 'url' => $result['url_foglio'], 'read_only' => ! empty( $result['read_only'] ) ), 300 );
			return array( 'ready' => true, 'url' => $result['url_foglio'] );
		} catch ( Throwable $error ) { if ( isset( $key ) ) delete_transient( $key ); return new WP_Error( 'mi_sheet_open', $error->getMessage() ); }
	}
	public static function ajax() {
		nocache_headers();
		if ( ! check_ajax_referer( 'mi_open_sheet', 'nonce', false ) ) wp_send_json_error( array( 'message' => 'Sessione scaduta. Riapri dal portale.' ), 403 );
		$result = self::step( absint( $_POST['event_id'] ?? 0 ), sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		wp_send_json_success( $result );
	}
	public static function render() {
		if ( empty( $_GET['mi_open_sheet'] ) ) return;
		if ( ! is_user_logged_in() ) { auth_redirect(); exit; }
		$event = absint( $_GET['mi_open_sheet'] );
		if ( ! MI_Portal_Management::allowed() || ! MI_Access::can_access_event( $event ) ) wp_die( 'Evento non accessibile.', '', array( 'response' => 403 ) );
		// Only use an existing receipt here: the fallback page owns slow remote work.
		if ( get_transient( 'mi_sheet_ready_' . $event ) ) {
			try {
				if ( class_exists( 'MI_Event_Deletion' ) && is_wp_error( MI_Event_Deletion::enter( $event ) ) ) throw new RuntimeException( 'Evento occupato.' );
				$current = self::snapshot( $event );
				$receipt = get_transient( 'mi_sheet_ready_' . $event );
				$pending = array_filter( $current['rows'], static function ( $r ) { return 'SYNCED' !== $r['workspace_status']; } );
				if ( ! $pending && 'ZERO' === $current['schema']['pricing'] && ! empty( $receipt['read_only'] ) && hash_equals( $current['fingerprint'], $receipt['fingerprint'] ) && preg_match( '~^https://docs\\.google\\.com/spreadsheets/d/[A-Za-z0-9_-]+(?:/|$)~D', $receipt['url'] ) ) { nocache_headers(); wp_redirect( $receipt['url'] ); exit; }
			} catch ( Throwable $error ) { /* Use the normal verified opening path. */ }
		}
		nocache_headers(); header( 'Content-Type: text/html; charset=UTF-8' );
		?><!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Apertura del foglio</title><body><main style="max-width:42rem;margin:12vh auto;padding:1.5rem;font:1.1rem/1.6 system-ui"><h1>Apertura del foglio</h1><p id="status" role="status">Aggiornamento in corso. Attendi…</p><button id="retry" type="button" hidden>Riprova</button> <a href="<?php echo esc_url( MI_Portal_Management::url( $event ) ); ?>">Torna alla gestione</a> <a href="<?php echo esc_url( add_query_arg( 'mi_sheet_sync', '1', MI_Portal_Management::url( $event ) ) ); ?>">Controlla le modifiche del foglio</a></main><script>
		const config=<?php echo wp_json_encode( array( 'endpoint' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'mi_open_sheet' ), 'event' => $event ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
		async function openSheet(){const status=document.getElementById('status'),retry=document.getElementById('retry');retry.hidden=true;status.textContent='Aggiornamento in corso. Attendi…';let token='';try{for(;;){const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),240000);let json;try{const response=await fetch(config.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,body:new URLSearchParams({action:'mi_open_sheet',nonce:config.nonce,event_id:config.event,token})});try{json=await response.json();}catch(parseError){throw new Error('Il server ha interrotto la risposta durante l’aggiornamento. Attendi qualche secondo e riprova.');}if(!response.ok||!json.success)throw new Error(json.data?.message||'Aggiornamento non riuscito.');}finally{clearTimeout(timeout);}if(json.data.ready){const url=new URL(json.data.url);if(url.origin!=='https://docs.google.com'||!url.pathname.startsWith('/spreadsheets/d/'))throw new Error('Collegamento non valido.');location.replace(url.href);return;}if(!json.data.token)throw new Error('Aggiornamento incompleto.');token=json.data.token;if(json.data.retry_after){status.textContent=json.data.message||'Google occupato. Nuovo tentativo automatico…';await new Promise(resolve=>setTimeout(resolve,Math.min(5,Math.max(1,Number(json.data.retry_after)))*1000));}}}catch(error){status.textContent=error.name==='AbortError'?'Aggiornamento non completato in tempo. Riprova.':error.message;retry.hidden=false;}}
		document.getElementById('retry').onclick=openSheet;openSheet();
		</script></body></html><?php exit;
	}
}
