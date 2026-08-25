<?php

defined( 'ABSPATH' ) || exit;

final class MI_Workspace_Client {
	public static function is_configured() {
		return defined( 'MI_WORKSPACE_WEBAPP_URL' )
			&& defined( 'MI_WORKSPACE_SHARED_SECRET' )
			&& 0 === strpos( MI_WORKSPACE_WEBAPP_URL, 'https://script.google.com/' )
			&& strlen( MI_WORKSPACE_SHARED_SECRET ) >= 32;
	}

	public static function ping() {
		return self::request( 'PING', array( 'source' => 'WORDPRESS' ) );
	}

	public static function request( $action, array $payload ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'mi_workspace_not_configured', 'Collegamento Workspace non configurato.' );
		}

		$action = strtoupper( sanitize_key( $action ) );
		$timestamp = (int) floor( microtime( true ) * 1000 );
		$nonce = bin2hex( random_bytes( 16 ) );
		$message = $timestamp . "\n" . $nonce . "\n" . $action . "\n" . self::stable_json( $payload );
		$signature = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $message, MI_WORKSPACE_SHARED_SECRET, true ) ), '+/', '-_' ), '=' );
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
			MI_WORKSPACE_WEBAPP_URL,
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
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'mi_workspace_http', 'Workspace ha restituito una risposta HTTP inattesa.' );
		}
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['ok'] ) ) {
			return new WP_Error( 'mi_workspace_rejected', 'Workspace ha rifiutato la richiesta.' );
		}
		return $decoded;
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
