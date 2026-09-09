<?php
defined( 'ABSPATH' ) || exit;

/** Unico accesso operativo: autorizzazioni WordPress, dati correnti MySQL. */
final class MI_Portal_Management {
	public static function boot() { add_action( 'wp_ajax_mi_portal_management', array( __CLASS__, 'ajax' ) ); }
	public static function allowed() { return is_user_logged_in() && ! MI_Access::is_suspended() && ( current_user_can( 'manage_options' ) || ( current_user_can( 'mi_portal_access' ) && current_user_can( 'mi_manage_events' ) ) ); }
	public static function url( $event_id = 0, $order = '' ) { return add_query_arg( array( 'mi_portal_view' => 'management', 'mi_portal_event' => absint( $event_id ), 'mi_order' => $order ), MI_Portal::url() ); }
	public static function ajax() {
		nocache_headers();
		if ( ! self::allowed() || ! check_ajax_referer( 'mi_portal_management', 'nonce', false ) ) wp_send_json_error( array( 'message' => 'Accesso non consentito o sessione scaduta.' ), 403 );
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) );
		$event_id = absint( $_POST['event_id'] ?? 0 );
		if ( ! $event_id || ! MI_Access::can_access_event( $event_id ) ) wp_send_json_error( array( 'message' => 'Evento non accessibile.' ), 403 );
		if ( 'summary' === $operation ) {
			$result = MI_Management_Service::summary( $event_id );
		} elseif ( 'sheet_changes' === $operation ) {
			$result = MI_Workspace_Client::request( 'LEGGI_MODIFICHE_FOGLIO', array( 'event_id' => (string) $event_id ) );
		} elseif ( 'sheet_save' === $operation ) {
			$changes = json_decode( wp_unslash( $_POST['data'] ?? 'null' ), true );
			$request_id = 'wp_' . get_current_user_id() . '_' . sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );
			$result = MI_Management_Service::save_sheet( $event_id, $changes, $request_id );
		} else {
			global $wpdb;
			$code = sanitize_text_field( wp_unslash( $_POST['order_code'] ?? '' ) );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,event_id,order_code FROM {$wpdb->prefix}mi_registrations WHERE order_code=%s AND event_id=%d", $code, $event_id ), ARRAY_A );
			if ( ! $row ) wp_send_json_error( array( 'message' => 'Prenotazione non accessibile.' ), 403 );
			$payload = array( 'event_id' => (string) $event_id, 'order_code' => $row['order_code'], 'operator_label' => mb_substr( 'WP#' . get_current_user_id() . ' · ' . wp_get_current_user()->display_name, 0, 100 ) );
			if ( 'detail' === $operation ) {
				$result = MI_Management_Service::detail( (int) $row['id'] );
			} elseif ( 'cancel' === $operation ) {
				$participant_id = absint( $_POST['participant_id'] ?? 0 );
				$belongs = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}mi_participants WHERE id=%d AND registration_id=%d", $participant_id, $row['id'] ) );
				if ( ! $belongs ) wp_send_json_error( array( 'message' => 'Partecipante non accessibile.' ), 403 );
				$result = MI_Registration_Service::cancel_participant( $participant_id, 'WP#' . get_current_user_id() . ' · ' . wp_get_current_user()->display_name );
				if ( ! is_wp_error( $result ) ) $result = array( 'ok' => true, 'saved' => true, 'message' => 'Partecipazione annullata. L’allineamento dei fogli può richiedere qualche istante.' );
			} elseif ( in_array( $operation, array( 'participant', 'room_save', 'room_delete' ), true ) ) {
				$request_id = sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );
				if ( ! preg_match( '/^[a-f0-9-]{36}$/i', $request_id ) ) wp_send_json_error( array( 'message' => 'Richiesta non valida.' ), 400 );
				$data = json_decode( wp_unslash( $_POST['data'] ?? '{}' ), true );
				if ( ! is_array( $data ) ) wp_send_json_error( array( 'message' => 'Dati non validi.' ), 400 );
				$payload += array( 'operation' => $operation, 'data' => $data, 'version' => sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) ), 'request_id' => 'wp_' . get_current_user_id() . '_' . $request_id, 'operator_label' => mb_substr( 'WP#' . get_current_user_id() . ' · ' . wp_get_current_user()->display_name, 0, 100 ) );
				$result = MI_Management_Service::save( (int) $row['id'], $operation, $data, $payload['version'], $payload['request_id'] );
			} else { wp_send_json_error( array( 'message' => 'Operazione non valida.' ), 400 ); }
		}
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
		$result['sheet_url'] = esc_url_raw( (string) get_post_meta( $event_id, '_mi_operational_sheet_url', true ), array( 'https' ) );
		wp_send_json_success( $result );
	}
	public static function render( $registration_id = 0 ) {
		if ( ! self::allowed() ) { echo '<p>Non disponi del permesso per gestire le iscrizioni.</p>'; return; }
		$event_id = absint( $_GET['mi_portal_event'] ?? 0 );
		$order = sanitize_text_field( wp_unslash( $_GET['mi_order'] ?? '' ) );
		if ( $registration_id ) {
			$row = MI_Portal_Payments::registration( $registration_id );
			if ( ! $row ) return;
			$event_id = (int) $row['event_id']; $order = $row['order_code'];
		}
		if ( ! $event_id && $order ) { global $wpdb; $event_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}mi_registrations WHERE order_code=%s", $order ) ); }
		if ( $event_id && ! MI_Access::can_access_event( $event_id ) ) { echo '<p>Evento non accessibile.</p>'; return; }
		$scope = MI_Access::event_ids();
		$query = array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish','private','draft' ), 'numberposts' => -1 );
		if ( 'ALL' !== $scope ) $query['post__in'] = $scope ?: array( 0 );
		$events = get_posts( $query );
		$periods = array();
		foreach ( $events as $event ) {
			$end = get_post_meta( $event->ID, '_mi_registration_closes_at', true ) ?: get_post_meta( $event->ID, '_mi_event_starts_at', true );
			$periods[$event->ID] = get_post_meta( $event->ID, '_mi_event_archived_at', true ) || MI_Portal::is_past_event( $end ) ? 'past' : 'current';
		}
		$period = $periods[$event_id] ?? ( 'past' === ( $_GET['mi_portal_period'] ?? '' ) ? 'past' : 'current' );
		?>
		<section class="mi-management" data-mi-management data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mi_portal_management' ) ); ?>" data-event="<?php echo esc_attr( $event_id ); ?>" data-order="<?php echo esc_attr( $order ); ?>">
		<h2>Gestione e riepilogo evento</h2>
		<label>Periodo<select data-period-select aria-label="Periodo"><option value="current" <?php selected( $period, 'current' ); ?>>Eventi attivi</option><option value="past" <?php selected( $period, 'past' ); ?>>Eventi passati</option></select></label>
		<label>Evento<select data-event-select aria-label="Evento"><option value="">Scegli un evento</option><?php foreach ( $events as $event ) : ?><option data-period="<?php echo esc_attr( $periods[$event->ID] ); ?>" value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $event_id, $event->ID ); ?>><?php echo esc_html( $event->post_title ); ?></option><?php endforeach; ?></select></label>
		<div class="mi-booking-detail__actions"><button type="button" data-refresh>Aggiorna riepilogo</button><button type="button" data-sheet-sync data-auto="<?php echo empty( $_GET['mi_sheet_sync'] ) ? '0' : '1'; ?>">Sincronizza foglio Google</button><button type="button" data-print>Stampa vista</button></div>
		<p><a data-open-sheet hidden target="_blank" rel="noopener">Apri foglio Google ↗</a></p>
		<p data-management-status role="status" aria-live="polite"></p><div data-management-content></div>
		</section>
		<?php
	}
}
