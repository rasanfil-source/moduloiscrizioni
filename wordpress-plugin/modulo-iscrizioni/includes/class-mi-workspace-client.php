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

	public static function request( $action, array $payload ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'mi_workspace_not_configured', 'Collegamento Workspace non configurato.' );
		}

		$action = strtoupper( sanitize_key( $action ) );
		$timestamp = (int) floor( microtime( true ) * 1000 );
		$nonce = bin2hex( random_bytes( 16 ) );
		$message = $timestamp . "\n" . $nonce . "\n" . $action . "\n" . self::stable_json( $payload );
		$signature = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $message, self::shared_secret(), true ) ), '+/', '-_' ), '=' );
		$body = wp_json_encode(
			array(
				'timestamp' => $timestamp,
				'nonce'     => $nonce,
				'action'    => $action,
				'payload'   => $payload,
				'signature' => $signature,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		$response = wp_remote_post(
			self::webapp_url(),
			array(
				'timeout'     => 15,
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
			$response = wp_remote_get( $location, array( 'timeout' => 15, 'redirection' => 3 ) );
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'mi_workspace_unreachable', 'Workspace non raggiungibile.' );
			}
			$http_status = (int) wp_remote_retrieve_response_code( $response );
		}
		if ( 200 !== $http_status ) {
			return new WP_Error( 'mi_workspace_http_' . $http_status, 'Workspace ha restituito una risposta HTTP inattesa.' );
		}
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['ok'] ) ) {
			$remote_code = is_array( $decoded ) ? strtoupper( sanitize_key( (string) ( $decoded['error'] ?? '' ) ) ) : '';
			$motivi = array(
				'ACTION_NOT_ALLOWED' => 'la distribuzione Apps Script non riconosce ancora questa operazione',
				'INVALID_SIGNATURE'  => 'la firma condivisa non coincide',
				'STALE_REQUEST'      => 'la richiesta è arrivata fuori tempo',
				'REPLAYED_NONCE'     => 'la richiesta risulta già utilizzata',
				'REQUEST_FAILED'     => 'Apps Script ha incontrato un errore durante l’elaborazione',
				'EMPTY_PAYLOAD'      => 'la richiesta è arrivata vuota',
			);
			$detail = $motivi[ $remote_code ] ?? 'la richiesta non è stata accettata';
			return new WP_Error( 'mi_workspace_rejected', 'Workspace ha rifiutato la richiesta: ' . $detail . '.' );
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
