<?php

defined( 'ABSPATH' ) || exit;

final class MI_REST_Controller {
	const NAMESPACE = 'modulo-iscrizioni/v1';

	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NAMESPACE,
			'/workspace/commands',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'workspace_command' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/events/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_event' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'validate_callback' => static function ( $value ) { return absint( $value ) > 0; } ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/events/(?P<id>\d+)/registrations',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_registration' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'validate_callback' => static function ( $value ) { return absint( $value ) > 0; } ) ),
			)
		);
	}

	public static function workspace_command( WP_REST_Request $request ) {
		$envelope = (array) $request->get_json_params();
		$verified = self::verify_workspace_envelope( $envelope );
		if ( is_wp_error( $verified ) ) return $verified;
		$action = strtoupper( sanitize_key( (string) ( $envelope['action'] ?? '' ) ) );
		$payload = $verified;
		if ( 'GET_EVENT_PROJECTION' === $action ) return self::event_projection_for_workspace( $payload );
		if ( 'GET_EMAIL_MODE' === $action ) return array( 'ok' => true, 'mode' => MI_Spedizione_Email::modalita() );
		if ( 'GET_EMAIL_RETENTION' === $action ) return MI_Spedizione_Email::retention_event_deadlines( $payload );
		if ( 'ADVANCE_PROJECTION_GENERATION' === $action ) return MI_Event_Projection::advance_generation( $payload );
		return new WP_Error( 'mi_workspace_action_not_allowed', 'Azione Workspace non consentita.', array( 'status' => 403 ) );
	}

	private static function event_projection_for_workspace( array $payload ) {
		$event_id = absint( $payload['event_id'] ?? 0 );
		$expected_hash = strtolower( (string) ( $payload['projection_hash'] ?? '' ) );
		$expected_fingerprint = strtolower( (string) ( $payload['fingerprint'] ?? '' ) );
		if ( ! $event_id || ! preg_match( '/^[a-f0-9]{64}$/D', $expected_hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $expected_fingerprint ) ) return new WP_Error( 'mi_projection_invalid', 'Richiesta di proiezione non valida.', array( 'status' => 400 ) );
		if ( class_exists( 'MI_Event_Deletion' ) ) {
			$lease = MI_Event_Deletion::enter( $event_id );
			if ( is_wp_error( $lease ) ) { MI_Event_Deletion::release( $event_id ); return new WP_Error( 'mi_projection_deleted', 'Evento non disponibile.', array( 'status' => 409 ) ); }
		}
		try {
			$snapshot = MI_Event_Projection::snapshot( $event_id );
			if ( ! hash_equals( $expected_fingerprint, $snapshot['fingerprint'] ) ) return new WP_Error( 'mi_projection_changed', 'La proiezione è cambiata; ripetere la richiesta.', array( 'status' => 409 ) );
			$transfer = MI_Event_Projection::encoded_snapshot( $snapshot );
			if ( ! hash_equals( $expected_hash, $transfer['projection_hash'] ) ) return new WP_Error( 'mi_projection_changed', 'La proiezione è cambiata; ripetere la richiesta.', array( 'status' => 409 ) );
			return array( 'ok' => true, 'projection_gzip' => $transfer['projection_gzip'], 'projection_hash' => $transfer['projection_hash'] );
		} catch ( Throwable $error ) {
			return new WP_Error( 'mi_projection_unavailable', 'Proiezione non disponibile.', array( 'status' => 503 ) );
		} finally { if ( class_exists( 'MI_Event_Deletion' ) ) MI_Event_Deletion::release( $event_id ); }
	}

	private static function verify_workspace_envelope( array $envelope ) {
		global $wpdb;
		$timestamp = isset( $envelope['timestamp'] ) ? (int) $envelope['timestamp'] : 0;
		$nonce = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $envelope['nonce'] ?? '' ) );
		$action = strtoupper( sanitize_key( (string) ( $envelope['action'] ?? '' ) ) );
		$signature = (string) ( $envelope['signature'] ?? '' );
		$payload = (array) ( $envelope['payload'] ?? array() );
		$protocol = $envelope['protocollo'] ?? 1;
		if ( ! in_array( $protocol, array( 1, 2 ), true ) ) return new WP_Error( 'mi_workspace_protocol_invalid', 'Protocollo Workspace non valido.', array( 'status' => 400 ) );
		$content = MI_Workspace_Client::stable_json( $payload );
		if ( 2 === $protocol ) {
			$raw = $envelope['payload_firmato'] ?? null;
			$hash = $envelope['payload_hash'] ?? null;
			if ( ! is_string( $raw ) || strlen( $raw ) > 2000000 || ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) || ! hash_equals( hash( 'sha256', $raw ), $hash ) ) return new WP_Error( 'mi_workspace_payload_invalid', 'Contenuto Workspace non valido.', array( 'status' => 401 ) );
			// Il comando usa esclusivamente il JSON autenticato, senza un secondo contenuto da elaborare.
			$decoded = json_decode( $raw );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_object( $decoded ) ) return new WP_Error( 'mi_workspace_payload_invalid', 'Contenuto Workspace non valido.', array( 'status' => 400 ) );
			$payload = json_decode( $raw, true );
			$content = $hash;
		}
		$secret = defined( 'MI_WORKSPACE_SHARED_SECRET' ) ? (string) MI_WORKSPACE_SHARED_SECRET : (string) get_option( 'mi_workspace_shared_secret', '' );
		$window_ms = 120000;
		if ( strlen( $secret ) < 32 || strlen( $nonce ) < 32 || abs( (int) floor( microtime( true ) * 1000 ) - $timestamp ) > $window_ms ) return new WP_Error( 'mi_workspace_signature_invalid', 'Firma Workspace non valida.', array( 'status' => 401 ) );
		$message = $timestamp . "\n" . $nonce . "\n" . $action . "\n" . $content;
		$expected = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $message, $secret, true ) ), '+/', '-_' ), '=' );
		if ( ! hash_equals( $expected, $signature ) ) return new WP_Error( 'mi_workspace_signature_invalid', 'Firma Workspace non valida.', array( 'status' => 401 ) );
		$hash = hash( 'sha256', $nonce );
		// Durante l'aggiornamento rispetta anche le ricevute della versione precedente.
		if ( get_transient( 'mi_workspace_command_nonce_' . $hash ) ) return new WP_Error( 'mi_workspace_replay', 'Richiesta Workspace già utilizzata.', array( 'status' => 409 ) );
		$table = $wpdb->prefix . 'mi_workspace_nonces';
		$now = (int) floor( microtime( true ) * 1000 );
		$expires = $timestamp + $window_ms;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %d ORDER BY expires_at LIMIT 100", $now ) );
		$claimed = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (nonce_hash,expires_at) VALUES (%s,%d)", $hash, $expires ) );
		if ( false === $claimed ) return new WP_Error( 'mi_workspace_busy', 'Registrazione del comando Workspace temporaneamente non disponibile.', array( 'status' => 503 ) );
		if ( 1 !== $claimed ) return new WP_Error( 'mi_workspace_replay', 'Richiesta Workspace già utilizzata.', array( 'status' => 409 ) );
		if ( (int) floor( microtime( true ) * 1000 ) > $expires ) return new WP_Error( 'mi_workspace_signature_invalid', 'Firma Workspace scaduta.', array( 'status' => 401 ) );
		return $payload;
	}

	public static function get_event( WP_REST_Request $request ) {
		$event = MI_Registration_Service::public_event( absint( $request['id'] ) );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		$event['registration_state'] = MI_Registration_Service::registration_state( $event );
		return rest_ensure_response( $event );
	}

	public static function create_registration( WP_REST_Request $request ) {
		$content_type = (string) $request->get_header( 'content-type' );
		if ( false === stripos( $content_type, 'application/json' ) ) {
			return new WP_Error( 'mi_content_type', 'È richiesto un payload JSON.', array( 'status' => 415 ) );
		}
		$idempotency_key = (string) $request->get_header( 'x-idempotency-key' );
		$result = MI_Registration_Service::create( absint( $request['id'] ), (array) $request->get_json_params(), $idempotency_key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->set_status( $result['replayed'] ? 200 : 201 );
		return $response;
	}
}
