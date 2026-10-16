<?php

defined( 'ABSPATH' ) || exit;

/** Public registration QR: generated locally in the browser, never by a third party. */
final class MI_Event_QR {
	public static function boot() {
		add_action( 'wp_ajax_mi_event_qr', array( __CLASS__, 'data' ) );
	}

	public static function assets() {
		if ( ! is_user_logged_in() ) return;
		wp_enqueue_script( 'mi-qr-generator', MI_PLUGIN_URL . 'assets/qrcode-generator-2.0.4.js', array(), '2.0.4', true );
		wp_enqueue_script( 'mi-event-qr', MI_PLUGIN_URL . 'assets/event-qr.js', array( 'mi-qr-generator' ), MI_VERSION, true );
	}

	/** The standalone portal deliberately does not call wp_footer(). */
	public static function standalone_scripts() {
		if ( ! is_user_logged_in() ) return;
		foreach ( array( 'qrcode-generator-2.0.4.js', 'event-qr.js' ) as $asset ) {
			echo '<script defer src="' . esc_url( MI_Assets::filter_url( MI_PLUGIN_URL . 'assets/' . $asset . '?ver=' . MI_VERSION ) ) . '"></script>';
		}
	}

	public static function render( $event_id ) {
		if ( 'publish' !== get_post_status( $event_id ) || '1' !== get_post_meta( $event_id, '_mi_event_qr_enabled', true ) ) return;
		self::assets();
		$url = add_query_arg( array( 'action' => 'mi_event_qr', 'event_id' => $event_id, '_ajax_nonce' => wp_create_nonce( 'mi_event_qr_' . $event_id ) ), admin_url( 'admin-ajax.php' ) );
		echo '<div data-mi-event-qr="' . esc_url( $url ) . '"><p><strong>QR per le iscrizioni</strong></p><img data-mi-qr-preview hidden alt="QR con il link pubblico di iscrizione" width="240" height="240" style="max-width:100%;height:auto"><p data-mi-qr-status role="status">Preparazione del PNG…</p><button type="button" class="mi-secondary" data-mi-qr-download disabled>Scarica QR in PNG</button><noscript>Attiva JavaScript per generare il PNG.</noscript></div>';
	}

	public static function data() {
		$event_id = absint( $_GET['event_id'] ?? 0 );
		check_ajax_referer( 'mi_event_qr_' . $event_id );
		if ( ! is_user_logged_in() || ! MI_Access::can_access_event( $event_id ) || ( ! current_user_can( 'mi_manage_events' ) && ! current_user_can( 'manage_options' ) ) ) wp_send_json_error( 'Non puoi accedere al QR di questo evento.', 403 );
		if ( MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) || '1' !== get_post_meta( $event_id, '_mi_event_qr_enabled', true ) ) wp_send_json_error( 'QR non disponibile: pubblica l’evento e attiva l’opzione QR.', 409 );
		$url = esc_url_raw( MI_Shortcode::url_iscrizione( $event_id ), array( 'https', 'http' ) );
		if ( ! $url ) wp_send_json_error( 'Il link pubblico di iscrizione non è disponibile.', 409 );
		$logo = self::logo( absint( get_post_meta( $event_id, '_mi_activity_id', true ) ) );
		if ( is_wp_error( $logo ) ) wp_send_json_error( $logo->get_error_message(), 422 );
		nocache_headers();
		wp_send_json_success( array( 'url' => $url, 'logo' => $logo, 'filename' => 'iscrizioni-evento-' . $event_id . '-qr.png' ) );
	}

	public static function logo( $group_id ) {
		$missing = new WP_Error( 'mi_qr_logo', 'Aggiungi un logo JPG, PNG o WebP al gruppo per generare il QR, poi riprova.' );
		if ( ! $group_id ) return $missing;
		$id = get_post_thumbnail_id( $group_id );
		$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : get_post_meta( $group_id, '_mi_group_logo_url', true );
		if ( ! $url ) return $missing;
		// Safe HTTP validates destinations/redirects; embed bytes to avoid canvas CORS errors.
		$response = wp_safe_remote_get( $url, array( 'timeout' => 10, 'redirection' => 3, 'limit_response_size' => 2097153 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return new WP_Error( 'mi_qr_logo_load', 'Il logo del gruppo non è raggiungibile. Controlla il logo e riprova.' );
		$bytes = wp_remote_retrieve_body( $response );
		$size = strlen( $bytes );
		$info = $size && $size <= 2097152 ? @getimagesizefromstring( $bytes ) : false;
		if ( ! $info || ! in_array( $info['mime'], array( 'image/png', 'image/jpeg', 'image/webp' ), true ) || $info[0] * $info[1] > 16000000 ) return $missing;
		return 'data:' . $info['mime'] . ';base64,' . base64_encode( $bytes );
	}
}
