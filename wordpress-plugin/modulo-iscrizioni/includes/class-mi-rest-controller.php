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
