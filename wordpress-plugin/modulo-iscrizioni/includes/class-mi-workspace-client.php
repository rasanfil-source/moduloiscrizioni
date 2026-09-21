<?php

defined( 'ABSPATH' ) || exit;

final class MI_Workspace_Client {
	public static function is_configured() {
		$url = self::webapp_url();
		$secret = self::shared_secret();
		return 0 === strpos( $url, 'https://script.google.com/macros/s/' ) && strlen( $secret ) >= 32;
	}

	public static function ping() {
		return self::request( 'PING', array( 'source' => 'WORDPRESS' ) );
	}

	public static function stato_schema() {
		return self::request( 'STATO_SCHEMA', array( 'source' => 'WORDPRESS' ) );
	}

	public static function request( $action, array $payload, $attempt = 0 ) {
		// La replica torna subito alla coda; la pubblicazione interattiva concede
		// tre secondi a una replica già in chiusura, evitando falsi "occupato".
		if ( 'PROIETTA_EVENTO' !== strtoupper( $action ) ) return self::request_unlocked( $action, $payload, $attempt );
		global $wpdb;
		$lock = 'mi_workspace_' . substr( hash( 'sha256', $wpdb->prefix ), 0, 40 );
		$wait = 3;
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock, $wait ) ) ) return new WP_Error( 'mi_workspace_busy', 'Sincronizzazione Google in corso; aggiornamento mantenuto in attesa.' );
		try { return self::request_unlocked( $action, $payload, $attempt ); }
		finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}

	private static function request_unlocked( $action, array $payload, $attempt = 0 ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'mi_workspace_not_configured', 'Collegamento Workspace non configurato.' );
		}

		$action = strtoupper( sanitize_key( $action ) );
		$timestamp = (int) floor( microtime( true ) * 1000 );
		$nonce = bin2hex( random_bytes( 16 ) );
		$payload_firmato = self::stable_json( $payload );
		// Il protocollo 2 firma l'impronta ASCII del contenuto. In questo modo PHP e
		// Apps Script non possono interpretare diversamente caratteri Unicode o URL.
		$payload_hash = hash( 'sha256', $payload_firmato );
		$message = $timestamp . "\n" . $nonce . "\n" . $action . "\n" . $payload_hash;
		$signature = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $message, self::shared_secret(), true ) ), '+/', '-_' ), '=' );
		$body = wp_json_encode(
			array(
				'protocollo' => 2,
				'timestamp' => $timestamp,
				'nonce'     => $nonce,
				'action'    => $action,
				'payload_firmato' => $payload_firmato,
				'payload_hash' => $payload_hash,
				'signature' => $signature,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		// La prima preparazione del foglio può richiedere più tempo delle normali repliche.
		// L'operazione remota è idempotente e un nuovo tentativo non duplica il foglio.
		// La prima costruzione del foglio include formattazione, convalide e schede
		// economiche. Le esecuzioni reali possono superare i tre minuti; interrompere
		// a 30 secondi produce un falso "non raggiungibile" mentre Google continua.
		// La replica gira nella coda: la formattazione Google può superare un minuto.
		$timeout = 'PROIETTA_EVENTO' === $action ? 240 : ( 'ELIMINA_DATI_EVENTO' === $action ? 110 : ( 'LEGGI_MODIFICHE_FOGLIO' === $action ? 45 : ( 'INVIA_EMAIL_PROVA' === $action ? 30 : 15 ) ) );
		if ( 'CONFERMA_MODIFICHE_FOGLIO' === $action ) $timeout = 180;
		if ( in_array( $action, array( 'VERIFICA_FOGLI_EVENTO', 'ORGANIZZA_FOGLI_EVENTO' ), true ) ) $timeout = 60;
		// These deliveries carry revisions and Google tombstones reject late writes.
		// Email/deletion retain their stronger exclusion until their side effect completes.
		if ( class_exists( 'MI_Event_Deletion' ) && 'PROIETTA_EVENTO' === $action ) MI_Event_Deletion::release( absint( $payload['event_id'] ?? 0 ) );
		$response = wp_remote_post(
			self::webapp_url(),
			array(
				'timeout'     => $timeout,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'        => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'mi_workspace_unreachable', 'Workspace non raggiungibile.' );
		}
		$http_status = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $http_status, array( 301, 302, 303, 307, 308 ), true ) ) {
			$location = (string) wp_remote_retrieve_header( $response, 'location' );
			$host = strtolower( (string) wp_parse_url( $location, PHP_URL_HOST ) );
			$scheme = strtolower( (string) wp_parse_url( $location, PHP_URL_SCHEME ) );
			$host_google = 'script.googleusercontent.com' === $host || '.googleusercontent.com' === substr( $host, -22 );
			if ( 'https' !== $scheme || ! $host_google ) {
				return new WP_Error( 'mi_workspace_redirect_invalid', 'Workspace ha restituito un reindirizzamento non valido.' );
			}
			$response = wp_remote_get( $location, array( 'timeout' => $timeout, 'redirection' => 3 ) );
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'mi_workspace_unreachable', 'Workspace non raggiungibile.' );
			}
			$http_status = (int) wp_remote_retrieve_response_code( $response );
		}
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		// ContentService può consegnare correttamente il JSON dopo un ponte Google
		// con uno stato HTTP non canonico. Il contenuto applicativo firmato prevale:
		// un esito positivo esplicito evita di dichiarare fallita un'email già inviata.
		if ( is_array( $decoded ) && ! empty( $decoded['ok'] ) ) {
			return $decoded;
		}
		// Un collegamento temporaneo di ContentService può eccezionalmente scadere
		// con 404. La preparazione è idempotente: un solo nuovo tentativo, con una
		// busta e un nonce nuovi, recupera il risultato senza duplicare il foglio.
		if ( 404 === $http_status && 'PROIETTA_EVENTO' === $action && 0 === (int) $attempt ) {
			return self::request_unlocked( $action, $payload, 1 );
		}
		if ( 200 !== $http_status ) {
			return new WP_Error( 'mi_workspace_http_' . $http_status, 'Workspace ha restituito una risposta HTTP inattesa (' . $http_status . ').' );
		}
		if ( ! is_array( $decoded ) || empty( $decoded['ok'] ) ) {
			$remote_code = is_array( $decoded ) ? strtoupper( sanitize_key( (string) ( $decoded['error'] ?? '' ) ) ) : '';
			$motivi = array(
				'EMAIL_SENDER_NOT_AUTHORIZED' => 'distribuisci Apps Script con l’account info@parrocchiasanteugenio.it; nessun invio eseguito',
				'EMAIL_DELIVERY_UNCERTAIN' => 'esito invio incerto: verificare il Registro invii email e i log Google prima di ritentare; reinvio automatico bloccato',
				'EMAIL_QUOTA_EXCEEDED' => 'quota giornaliera Google esaurita',
				'EMAIL_BUSY' => 'un altro invio è in corso',
				'EVENT_BUSY' => 'è in corso un aggiornamento Google; riprova tra poco',
				'DELETION_CONFLICT' => 'esiste una cancellazione con identificativo o scelta differenti',
				'INVALID_DELETION' => 'i parametri della cancellazione non sono validi',
				'SHARED_EVENT_SHEET' => 'il foglio risulta collegato anche a un altro evento',
				'ACTION_NOT_ALLOWED' => 'la distribuzione Apps Script non riconosce ancora questa operazione',
				'USE_STANDALONE_PROJECT' => 'la Web App è ancora collegata al progetto vincolato a DB_MODULI; collega il progetto Apps Script autonomo',
				'INVALID_SIGNATURE'  => 'la firma condivisa non coincide',
				'INVALID_PAYLOAD_HASH' => 'il contenuto ricevuto non coincide con quello firmato',
				'STALE_REQUEST'      => 'la richiesta è arrivata fuori tempo',
				'EVENT_DELETED' => 'l’evento è stato eliminato o è in eliminazione',
				'INVALID_OPEN_REQUEST' => 'la richiesta di apertura non è valida',
				'REPLAYED_REQUEST' => 'la richiesta risulta già utilizzata',
				'REPLAYED_NONCE'     => 'la richiesta risulta già utilizzata',
				'REQUEST_FAILED'     => 'Apps Script ha incontrato un errore durante l’elaborazione',
				'EMPTY_PAYLOAD'      => 'la richiesta è arrivata vuota',
				'TEST_RECIPIENT_NOT_CONFIGURED' => 'il destinatario di prova non è configurato nel progetto MODULI',
				'TEST_RECIPIENT_MISMATCH' => 'il destinatario non coincide con quello autorizzato nel progetto MODULI',
				'INVALID_EMAIL_PAYLOAD' => 'il contenuto dell’email di prova non è valido',
			);
			$detail = $motivi[ $remote_code ] ?? 'la richiesta non è stata accettata';
			if ( 'REQUEST_FAILED' === $remote_code && current_user_can( 'manage_options' ) && ! empty( $decoded['diagnostic'] ) ) {
				$diagnostic = sanitize_text_field( (string) $decoded['diagnostic'] );
				if ( $diagnostic ) $detail .= ' (' . $diagnostic . ')';
			}
			return new WP_Error( 'mi_workspace_rejected', 'Workspace ha rifiutato la richiesta: ' . $detail . '.', array( 'remote_code' => $remote_code, 'diagnostic' => current_user_can( 'manage_options' ) ? sanitize_text_field( (string) ( $decoded['diagnostic'] ?? '' ) ) : '' ) );
		}
		return $decoded;
	}

	private static function webapp_url() {
		return defined( 'MI_WORKSPACE_WEBAPP_URL' ) ? (string) MI_WORKSPACE_WEBAPP_URL : (string) get_option( 'mi_workspace_webapp_url', '' );
	}

	private static function shared_secret() {
		return defined( 'MI_WORKSPACE_SHARED_SECRET' ) ? (string) MI_WORKSPACE_SHARED_SECRET : (string) get_option( 'mi_workspace_shared_secret', '' );
	}

	public static function stable_json( $value ) {
		if ( is_array( $value ) ) {
			if ( self::is_list( $value ) ) {
				$parts = array_map( array( __CLASS__, 'stable_json' ), $value );
				return '[' . implode( ',', $parts ) . ']';
			}
			ksort( $value, SORT_STRING );
			$parts = array();
			foreach ( $value as $key => $item ) {
				$parts[] = wp_json_encode( (string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ':' . self::stable_json( $item );
			}
			return '{' . implode( ',', $parts ) . '}';
		}
		return wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function is_list( array $value ) {
		return empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
