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
		$payload = (array) ( $envelope['payload'] ?? array() );
		if ( 'CREATE_EVENT_DRAFT' === $action ) return self::create_event_draft_from_workspace( $payload );
		if ( 'CREATE_GROUP' === $action ) return self::create_group_from_workspace( $payload );
		if ( 'GET_EMAIL_MODE' === $action ) return array( 'ok' => true, 'mode' => MI_Spedizione_Email::modalita() );
		if ( 'QUEUE_OPERATIONAL_EMAILS' === $action ) return MI_Spedizione_Email::accoda_comunicazione_operativa( $payload );
		return new WP_Error( 'mi_workspace_action_not_allowed', 'Azione Workspace non consentita.', array( 'status' => 403 ) );
	}

	private static function create_group_from_workspace( array $payload ) {
		$name = mb_substr( sanitize_text_field( $payload['name'] ?? '' ), 0, 120 );
		$slug = sanitize_title( $payload['slug'] ?? $name );
		$logo_url = self::sanitize_https_asset_url( $payload['logo_url'] ?? '' );
		$image_url = self::sanitize_https_asset_url( $payload['image_url'] ?? '' );
		if ( ! $name || ! $slug ) return new WP_Error( 'mi_workspace_group_invalid', 'Nome del gruppo non valido.', array( 'status' => 400 ) );
		$existing = get_page_by_path( $slug, OBJECT, MI_Event_Post_Type::GROUP_TYPE );
		if ( $existing ) {
			self::save_group_asset_urls( (int) $existing->ID, $logo_url, $image_url );
			return array( 'ok' => true, 'group_id' => (int) $existing->ID, 'name' => $existing->post_title, 'slug' => $existing->post_name, 'existing' => true );
		}
		$group_id = wp_insert_post( array( 'post_type' => MI_Event_Post_Type::GROUP_TYPE, 'post_status' => 'publish', 'post_title' => $name, 'post_name' => $slug ), true );
		if ( is_wp_error( $group_id ) ) return $group_id;
		self::save_group_asset_urls( (int) $group_id, $logo_url, $image_url );
		return array( 'ok' => true, 'group_id' => (int) $group_id, 'name' => get_the_title( $group_id ), 'slug' => $slug, 'existing' => false );
	}

	private static function sanitize_https_asset_url( $value ) {
		$url = esc_url_raw( (string) $value, array( 'https' ) );
		return $url && 0 === strpos( $url, 'https://' ) ? $url : '';
	}

	private static function save_group_asset_urls( $group_id, $logo_url, $image_url ) {
		if ( $logo_url ) update_post_meta( $group_id, '_mi_group_logo_url', $logo_url );
		if ( $image_url ) update_post_meta( $group_id, '_mi_group_cover_image_url', $image_url );
	}

	private static function verify_workspace_envelope( array $envelope ) {
		global $wpdb;
		$timestamp = isset( $envelope['timestamp'] ) ? (int) $envelope['timestamp'] : 0;
		$nonce = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $envelope['nonce'] ?? '' ) );
		$action = strtoupper( sanitize_key( (string) ( $envelope['action'] ?? '' ) ) );
		$signature = (string) ( $envelope['signature'] ?? '' );
		$payload = (array) ( $envelope['payload'] ?? array() );
		$secret = defined( 'MI_WORKSPACE_SHARED_SECRET' ) ? (string) MI_WORKSPACE_SHARED_SECRET : (string) get_option( 'mi_workspace_shared_secret', '' );
		if ( strlen( $secret ) < 32 || strlen( $nonce ) < 32 || abs( (int) floor( microtime( true ) * 1000 ) - $timestamp ) > 120000 ) return new WP_Error( 'mi_workspace_signature_invalid', 'Firma Workspace non valida.', array( 'status' => 401 ) );
		$message = $timestamp . "\n" . $nonce . "\n" . $action . "\n" . MI_Workspace_Client::stable_json( $payload );
		$expected = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $message, $secret, true ) ), '+/', '-_' ), '=' );
		if ( ! hash_equals( $expected, $signature ) ) return new WP_Error( 'mi_workspace_signature_invalid', 'Firma Workspace non valida.', array( 'status' => 401 ) );
		$nonce_key = 'mi_workspace_command_nonce_' . hash( 'sha256', $nonce );
		$lock_name = 'mi_ws_nonce_' . substr( hash( 'sha256', $nonce ), 0, 48 );
		$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $lock_name ) );
		if ( 1 !== $locked ) return new WP_Error( 'mi_workspace_busy', 'Verifica Workspace momentaneamente occupata.', array( 'status' => 503 ) );
		try {
			if ( get_transient( $nonce_key ) ) return new WP_Error( 'mi_workspace_replay', 'Richiesta Workspace già utilizzata.', array( 'status' => 409 ) );
			set_transient( $nonce_key, 1, 3 * MINUTE_IN_SECONDS );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
		return true;
	}

	private static function create_event_draft_from_workspace( array $payload ) {
		global $wpdb;
		$title = mb_substr( sanitize_text_field( $payload['title'] ?? '' ), 0, 160 );
		$draft_id = sanitize_key( $payload['draft_id'] ?? '' );
		if ( ! $title || ! $draft_id ) return new WP_Error( 'mi_workspace_event_invalid', 'Nome o identificativo della bozza mancante.', array( 'status' => 400 ) );
		$lock_name = 'mi_ws_draft_' . substr( hash( 'sha256', $draft_id ), 0, 48 );
		$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock_name ) );
		if ( 1 !== $locked ) return new WP_Error( 'mi_workspace_busy', 'Creazione bozza momentaneamente occupata.', array( 'status' => 503 ) );
		try {
		$existing = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => '_mi_workspace_draft_id', 'meta_value' => $draft_id ) );
		if ( $existing ) return rest_ensure_response( self::event_draft_response( (int) $existing[0]->ID ) );
		$copy_id = absint( $payload['copy_event_id'] ?? 0 );
		$activity_id = absint( $payload['group_id'] ?? $payload['activity_id'] ?? 0 );
		$group_slug = sanitize_title( $payload['group_slug'] ?? '' );
		if ( ! $activity_id && $group_slug ) {
			$group = get_page_by_path( $group_slug, OBJECT, MI_Event_Post_Type::GROUP_TYPE );
			if ( $group ) $activity_id = (int) $group->ID;
		}
		if ( $copy_id && MI_Event_Post_Type::EVENT_TYPE === get_post_type( $copy_id ) ) {
			$copy_activity = absint( get_post_meta( $copy_id, '_mi_activity_id', true ) );
			if ( ! $activity_id ) $activity_id = $copy_activity;
		}
		$event_id = wp_insert_post( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'draft', 'post_title' => $title ), true );
		if ( is_wp_error( $event_id ) ) return $event_id;
		$copy_keys = array( '_mi_data_profile', '_mi_participant_fields', '_mi_participant_required_fields', '_mi_custom_participant_fields', '_mi_participant_extra_scope', '_mi_operational_profile', '_mi_special_requests_enabled', '_mi_marketing_enabled', '_mi_email_settings', '_mi_identifier_mode' );
		if ( $copy_id ) foreach ( $copy_keys as $key ) { $value = get_post_meta( $copy_id, $key, true ); if ( '' !== $value ) update_post_meta( $event_id, $key, $value ); }
		update_post_meta( $event_id, '_mi_workspace_draft_id', $draft_id );
		update_post_meta( $event_id, '_mi_activity_id', $activity_id );
		update_post_meta( $event_id, '_mi_capacity', min( 10000, max( 1, absint( $payload['capacity'] ?? 50 ) ) ) );
		update_post_meta( $event_id, '_mi_event_starts_at', self::sanitize_local_datetime( $payload['starts_at'] ?? '' ) );
		update_post_meta( $event_id, '_mi_registration_closes_at', self::sanitize_local_datetime( $payload['closes_at'] ?? '' ) );
		$economic_mode = in_array( $payload['economic_mode'] ?? '', array( 'REGISTRATION_ONLY', 'PRICE_ONLY', 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ? $payload['economic_mode'] : 'REGISTRATION_ONLY';
		$fixed_price = max( 0, absint( $payload['fixed_price_cents'] ?? 0 ) );
		update_post_meta( $event_id, '_mi_economic_mode', $economic_mode );
		update_post_meta( $event_id, '_mi_pricing_mode', $fixed_price > 0 ? 'FIXED' : 'NONE' );
		update_post_meta( $event_id, '_mi_fixed_price_cents', $fixed_price );
		update_post_meta( $event_id, '_mi_operational_profile', MI_Field_Schema::sanitize_operational_profile( $payload['operational_profile'] ?? 'AUTOMATICO' ) );
		$deposit_mode = 'FIXED' === strtoupper( sanitize_key( $payload['deposit_mode'] ?? 'PERCENTAGE' ) ) ? 'FIXED' : 'PERCENTAGE';
		update_post_meta( $event_id, '_mi_deposit_mode', $deposit_mode );
		update_post_meta( $event_id, '_mi_deposit_percentage', min( 99, max( 1, absint( $payload['deposit_percentage'] ?? 30 ) ) ) );
		update_post_meta( $event_id, '_mi_deposit_fixed_cents', 'DEPOSIT_BALANCE' === $economic_mode && 'FIXED' === $deposit_mode ? max( 0, absint( $payload['deposit_fixed_cents'] ?? 0 ) ) : 0 );
		update_post_meta( $event_id, '_mi_ticket_types', array( array( 'code' => 'standard', 'name' => 'Quota di partecipazione', 'price_cents' => 0, 'max_per_order' => 20, 'capacity' => 0 ) ) );
		$options = self::workspace_service_options( (array) ( $payload['services'] ?? array() ), (array) ( $payload['accommodations'] ?? array() ) );
		update_post_meta( $event_id, '_mi_options', $options );
		$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $title, 'post_content' => '[modulo_iscrizioni event="' . (int) $event_id . '"]' ), true );
		if ( ! is_wp_error( $page_id ) ) { update_post_meta( $page_id, '_wp_page_template', MI_Shortcode::FOCUSED_TEMPLATE ); update_post_meta( $event_id, '_mi_registration_page_id', (int) $page_id ); }
		return rest_ensure_response( self::event_draft_response( $event_id ) );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	private static function event_draft_response( $event_id ) {
		$page_id = absint( get_post_meta( $event_id, '_mi_registration_page_id', true ) );
		return array( 'ok' => true, 'status' => 'DRAFT', 'event_id' => $event_id, 'title' => get_the_title( $event_id ), 'registration_url' => $page_id ? get_permalink( $page_id ) : '', 'balance_url' => '', 'edit_url' => get_edit_post_link( $event_id, 'raw' ) );
	}

	private static function sanitize_local_datetime( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ? $value : '';
	}

	private static function workspace_service_options( array $services, array $accommodations ) {
		$labels = array( 'PULLMAN' => 'Pullman', 'PERNOTTAMENTO' => 'Pernottamento', 'COLAZIONE' => 'Colazione', 'PRANZO' => 'Pranzo', 'CENA' => 'Cena', 'ALTRO' => 'Altro servizio', 'SINGOLA' => 'Camera singola', 'DOPPIA_SEPARATI' => 'Camera doppia con letti separati', 'DOPPIA_MATRIMONIALE' => 'Camera doppia matrimoniale', 'TRIPLA' => 'Camera tripla', 'MULTIPLA' => 'Camera multipla' );
		$result = array();
		foreach ( array_unique( array_merge( $services, $accommodations ) ) as $code ) if ( isset( $labels[ $code ] ) ) $result[] = array( 'code' => strtolower( str_replace( '_', '-', $code ) ), 'name' => $labels[ $code ], 'scope' => 'TICKET', 'price_cents' => 0, 'max_quantity' => 1 );
		return $result;
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
