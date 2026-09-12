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
		if ( 'annual_report' === $operation ) {
			$report_event = absint( $_POST['event_id'] ?? 0 );
			$report_group = absint( get_post_meta( $report_event, '_mi_activity_id', true ) );
			if ( ! $report_event || ! MI_Access::can_access_event( $report_event ) || ! $report_group || $report_group !== absint( $_POST['group_id'] ?? 0 ) || '1' !== get_post_meta( $report_group, '_mi_annual_attendance_report', true ) ) wp_send_json_error( array( 'message' => 'Rapporto annuale non attivo per il gruppo di questo evento.' ), 403 );
			$result = MI_Attendance_Report::read( absint( $_POST['group_id'] ?? 0 ), absint( $_POST['year'] ?? 0 ), absint( $_POST['minimum'] ?? 1 ) );
			if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			wp_send_json_success( $result );
		}
		$event_id = absint( $_POST['event_id'] ?? 0 );
		if ( ! $event_id || ! MI_Access::can_access_event( $event_id ) ) wp_send_json_error( array( 'message' => 'Evento non accessibile.' ), 403 );
		if ( in_array( $operation, array( 'summary', 'list_page' ), true ) ) {
			$result = MI_Management_Service::summary( $event_id );
			if ( ! is_wp_error( $result ) ) $result['rooms_version'] = hash( 'sha256', wp_json_encode( $result['rooms'] ) );
			if ( ! is_wp_error( $result ) ) {
				if ( 'list_page' === $operation ) $result = MI_Management_List::page( $result, json_decode( wp_unslash( $_POST['context'] ?? '{}' ), true ), absint( $_POST['offset'] ?? 0 ), absint( $_POST['limit'] ?? 30 ) );
				else {
					$result = MI_Management_List::compact( $result );
					$group_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
					$result['annual_report_group'] = $group_id && '1' === get_post_meta( $group_id, '_mi_annual_attendance_report', true ) && MI_Access::can_access_activity( $group_id ) ? array( 'id' => $group_id, 'name' => get_the_title( $group_id ) ) : null;
				}
			}
		} elseif ( in_array( $operation, array( 'accommodation_preview', 'change_accommodation' ), true ) ) {
			$result = MI_Management_Service::change_accommodation( $event_id, json_decode( wp_unslash( $_POST['data'] ?? 'null' ), true ), 'change_accommodation' === $operation ? sanitize_text_field( wp_unslash( $_POST['preview_version'] ?? '' ) ) : null, 'wp_' . get_current_user_id() . '_' . sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) ) );
		} elseif ( in_array( $operation, array( 'event_room_save', 'event_room_delete' ), true ) ) {
			$result = MI_Management_Service::save_event_room( $event_id, 'event_room_save' === $operation ? 'room_save' : 'room_delete', json_decode( wp_unslash( $_POST['data'] ?? 'null' ), true ), sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) ), 'wp_' . get_current_user_id() . '_' . sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) ) );
		} elseif ( 'identity_preview' === $operation ) {
			try { $result = MI_Attendance_Report::target( $event_id, sanitize_text_field( wp_unslash( $_POST['target_order'] ?? '' ) ), absint( $_POST['target_number'] ?? 0 ) ); }
			catch ( Throwable $error ) { wp_send_json_error( array( 'message' => $error->getMessage() ), 400 ); }
		} elseif ( 'sheet_changes' === $operation ) {
			$result = MI_Workspace_Client::request( 'LEGGI_MODIFICHE_FOGLIO', array( 'event_id' => (string) $event_id ) );
		} elseif ( in_array( $operation, array( 'sheet_save', 'room_swap', 'room_assign' ), true ) ) {
			$changes = json_decode( wp_unslash( $_POST['data'] ?? 'null' ), true );
			$request_id = 'wp_' . get_current_user_id() . '_' . sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );
			$result = MI_Management_Service::save_sheet( $event_id, $changes, $request_id, 'room_assign' === $operation ? 'ROOM_ASSIGN' : ( 'room_swap' === $operation ? 'ROOM_SWAP' : 'SHEET_SYNC' ) );
		} else {
			global $wpdb;
			$code = sanitize_text_field( wp_unslash( $_POST['order_code'] ?? '' ) );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,event_id,order_code FROM {$wpdb->prefix}mi_registrations WHERE order_code=%s AND event_id=%d", $code, $event_id ), ARRAY_A );
			if ( ! $row ) wp_send_json_error( array( 'message' => 'Prenotazione non accessibile.' ), 403 );
			$payload = array( 'event_id' => (string) $event_id, 'order_code' => $row['order_code'], 'operator_label' => mb_substr( 'WP#' . get_current_user_id() . ' · ' . wp_get_current_user()->display_name, 0, 100 ) );
			if ( 'detail' === $operation ) {
				$result = MI_Management_Service::detail( (int) $row['id'] );
			} elseif ( 'cancel_registration' === $operation ) {
				$result = MI_Registration_Service::cancel_registration( (int) $row['id'], 'WP#' . get_current_user_id() . ' · ' . wp_get_current_user()->display_name, false );
				if ( ! is_wp_error( $result ) ) $result = array( 'ok' => true, 'saved' => true, 'message' => 'Prenotazione annullata. L’allineamento dei fogli può richiedere qualche istante.' );
			} elseif ( 'cancel' === $operation ) {
				$participant_id = absint( $_POST['participant_id'] ?? 0 );
				$belongs = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}mi_participants WHERE id=%d AND registration_id=%d", $participant_id, $row['id'] ) );
				if ( ! $belongs ) wp_send_json_error( array( 'message' => 'Partecipante non accessibile.' ), 403 );
				$result = MI_Registration_Service::cancel_participant( $participant_id, 'WP#' . get_current_user_id() . ' · ' . wp_get_current_user()->display_name );
				if ( ! is_wp_error( $result ) ) $result = array( 'ok' => true, 'saved' => true, 'message' => 'Partecipazione annullata. L’allineamento dei fogli può richiedere qualche istante.' );
			} elseif ( in_array( $operation, array( 'participant', 'room_save', 'room_delete', 'request_review', 'attendance', 'adjust_due', 'identity_link', 'change_options' ), true ) ) {
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
		$result['report_url'] = current_user_can( 'mi_view_registrations' ) || current_user_can( 'manage_options' ) ? add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-payments', 'payment_event_id' => $event_id ), admin_url( 'edit.php' ) ) : '';
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
			$end = get_post_meta( $event->ID, '_mi_event_starts_at', true ) ?: get_post_meta( $event->ID, '_mi_registration_closes_at', true );
			$periods[$event->ID] = get_post_meta( $event->ID, '_mi_event_archived_at', true ) || MI_Portal::is_past_event( $end ) ? 'past' : 'current';
		}
		$period = $periods[$event_id] ?? ( 'past' === ( $_GET['mi_portal_period'] ?? '' ) ? 'past' : 'current' );
		$compact = (bool) $registration_id;
		?>
		<section class="mi-management<?php echo $compact ? ' mi-management--compact' : ''; ?>" data-mi-management data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mi_portal_management' ) ); ?>" data-event="<?php echo esc_attr( $event_id ); ?>" data-order="<?php echo esc_attr( $order ); ?>">
		<?php if ( ! $compact ) : ?><h2>Gestione iscrizioni</h2><?php endif; ?>
		<details data-annual-report hidden><summary>Rapporto annuale delle presenze per gruppo</summary><p>Conta gli eventi con presenza effettiva registrata, riconoscendo la persona dal cellulare personale fornito. Il numero condiviso del referente non identifica i singoli iscritti di una prenotazione multipla.</p>
		<label>Gruppo<select data-annual-group><option value="">Scegli un gruppo</option><?php foreach ( get_posts( array( 'post_type' => MI_Event_Post_Type::ACTIVITY_TYPE, 'post_status' => array( 'publish', 'private', 'draft' ), 'numberposts' => -1 ) ) as $group ) if ( MI_Access::can_access_activity( $group->ID ) ) : ?><option value="<?php echo esc_attr( $group->ID ); ?>"><?php echo esc_html( $group->post_title ); ?></option><?php endif; ?></select></label>
		<label>Anno<input data-annual-year type="number" min="2000" max="2200" value="<?php echo esc_attr( wp_date( 'Y' ) ); ?>"></label><label>Numero minimo di eventi frequentati<input data-annual-minimum type="number" min="1" max="1000" value="2"></label><button type="button" data-load-annual>Genera rapporto annuale</button><p data-annual-status role="status"></p><div data-annual-results></div></details>
		<div class="mi-management-event-selectors"<?php echo $compact ? ' hidden' : ''; ?>><label><select data-period-select aria-label="Eventi attivi o passati"><option value="current" <?php selected( $period, 'current' ); ?>>Eventi attivi</option><option value="past" <?php selected( $period, 'past' ); ?>>Eventi passati</option></select></label>
		<label>Evento<select data-event-select aria-label="Evento"><option value="">Scegli un evento</option><?php foreach ( $events as $event ) : ?><option data-period="<?php echo esc_attr( $periods[$event->ID] ); ?>" value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $event_id, $event->ID ); ?>><?php echo esc_html( $event->post_title ); ?></option><?php endforeach; ?></select></label></div>
		<div data-event-actions data-sheet-auto="<?php echo empty( $_GET['mi_sheet_sync'] ) ? '0' : '1'; ?>" hidden><div class="mi-booking-detail__actions mi-event-toolbar"><button type="button" data-refresh>Aggiorna riepilogo</button><button type="button" data-print>Stampa riepilogo iscritti</button><a class="mi-sheet-button" data-open-sheet hidden target="_blank" rel="noopener"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false"><path d="M6 2h8l5 5v15H6zM14 2v6h5"/><path d="M9 11h7v8H9zM9 15h7M12.5 11v8"/></svg><span>Apri foglio Google</span><span aria-hidden="true">↗</span></a></div>
		<p data-management-status role="status" aria-live="polite"></p></div><div data-management-content></div>
		</section>
		<?php
	}
}
