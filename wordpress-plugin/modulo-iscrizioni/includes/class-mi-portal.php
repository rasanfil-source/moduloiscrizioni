<?php

defined( 'ABSPATH' ) || exit;

final class MI_Portal {
	const SHORTCODE = 'mi_portale_gestione';

	public static function boot() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render_virtual_page' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'send_headers', array( __CLASS__, 'secure_cancellation_headers' ) );
	}

	public static function secure_cancellation_headers() {
		if ( empty( $_GET['mi_cancel_participant'] ) || empty( $_GET['mi_cancel_token'] ) ) return;
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}

	public static function assets() {
		if ( ! is_singular() && empty( $_GET['mi_portal'] ) ) return;
		$post = get_post();
		if ( empty( $_GET['mi_portal'] ) && ( ! $post || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) ) return;
		wp_enqueue_style( 'mi-portal', MI_PLUGIN_URL . 'assets/portal.css', array(), MI_VERSION );
		wp_enqueue_script( 'mi-portal', MI_PLUGIN_URL . 'assets/portal.js', array(), MI_VERSION, true );
	}

	public static function handle_actions() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
		$action = sanitize_key( wp_unslash( $_POST['mi_portal_action'] ?? '' ) );
		if ( 'cancel_participant_public' === $action ) {
			$participant_id = absint( $_POST['participant_id'] ?? 0 );
			check_admin_referer( 'mi_cancel_participant_public_' . $participant_id, 'mi_portal_nonce' );
			return self::redirect_cancel_result( MI_Registration_Service::cancel_participant_with_token( $participant_id, sanitize_text_field( wp_unslash( $_POST['cancel_token'] ?? '' ) ) ) );
		}
		if ( 'cancel_participant_portal' === $action ) {
			if ( ! is_user_logged_in() || ( ! current_user_can( 'mi_portal_access' ) && ! current_user_can( 'manage_options' ) ) ) wp_die( 'Accesso non consentito.', 403 );
			$participant_id = absint( $_POST['participant_id'] ?? 0 );
			check_admin_referer( 'mi_cancel_participant_portal_' . $participant_id, 'mi_portal_nonce' );
			global $wpdb; $event_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT r.event_id FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE p.id=%d", $participant_id ) );
			if ( ! $event_id || ! MI_Access::can_access_event( $event_id ) ) wp_die( 'Partecipante non accessibile.', 403 );
			return self::redirect_cancel_result( MI_Registration_Service::cancel_participant( $participant_id, wp_get_current_user()->display_name ) );
		}
		if ( 'create_event' !== $action ) return;
		if ( ! is_user_logged_in() || ! current_user_can( 'mi_create_events' ) ) wp_die( 'Accesso non consentito.', 403 );
		check_admin_referer( 'mi_portal_create_event', 'mi_portal_nonce' );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		if ( ! $title ) return self::redirect_result( 'Titolo obbligatorio.', true );
		$copy_id = absint( $_POST['copy_event_id'] ?? 0 );
		if ( $copy_id && ! MI_Access::can_access_event( $copy_id ) ) wp_die( 'Evento modello non accessibile.', 403 );
		$event_id = wp_insert_post( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'draft', 'post_title' => $title, 'post_author' => get_current_user_id() ), true );
		if ( is_wp_error( $event_id ) ) return self::redirect_result( 'Non è stato possibile creare la bozza.', true );
		if ( $copy_id ) self::copy_configuration( $copy_id, $event_id );
		$activity_id = absint( $_POST['activity_id'] ?? 0 );
		if ( $activity_id && MI_Access::can_access_activity( $activity_id ) ) update_post_meta( $event_id, '_mi_activity_id', $activity_id );
		self::save_date( $event_id, '_mi_event_starts_at', $_POST['starts_at'] ?? '' );
		self::save_date( $event_id, '_mi_registration_closes_at', $_POST['closes_at'] ?? '' );
		update_post_meta( $event_id, '_mi_capacity', min( 10000, max( 1, absint( $_POST['capacity'] ?? 30 ) ) ) );
		$overnight = ! empty( $_POST['overnight'] ) ? '1' : '0';
		update_post_meta( $event_id, '_mi_overnight_enabled', $overnight );
		$accommodations = '1' === $overnight ? array_values( array_intersect( array( 'SINGOLA', 'DOPPIA_SEPARATI', 'DOPPIA_MATRIMONIALE', 'TRIPLA', 'MULTIPLA' ), array_map( 'strtoupper', array_map( 'sanitize_key', (array) wp_unslash( $_POST['accommodations'] ?? array() ) ) ) ) ) : array();
		update_post_meta( $event_id, '_mi_accommodations', $accommodations );
		self::redirect_result( 'Bozza creata.', false, $event_id );
	}

	public static function url() {
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 's' => '[' . self::SHORTCODE ) );
		foreach ( $pages as $page ) if ( has_shortcode( $page->post_content, self::SHORTCODE ) ) return get_permalink( $page->ID );
		return add_query_arg( 'mi_portal', '1', home_url( '/' ) );
	}

	public static function participant_cancel_url( $participant_id, $token ) {
		return add_query_arg( array( 'mi_cancel_participant' => absint( $participant_id ), 'mi_cancel_token' => (string) $token ), self::url() );
	}

	public static function render_virtual_page() {
		if ( empty( $_GET['mi_portal'] ) ) return;
		status_header( 200 ); nocache_headers(); self::assets();
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><?php wp_head(); ?></head><body class="mi-portal-standalone"><?php echo self::render(); wp_footer(); ?></body></html><?php
		exit;
	}

	private static function redirect_cancel_result( $result ) {
		$error = is_wp_error( $result );
		wp_safe_redirect( add_query_arg( array( 'mi_portal' => '1', 'mi_portal_message' => $error ? $result->get_error_message() : 'Partecipazione annullata correttamente.', 'mi_portal_error' => $error ? '1' : '0' ), self::url() ) ); exit;
	}

	private static function save_date( $event_id, $key, $value ) {
		$value = sanitize_text_field( wp_unslash( $value ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) update_post_meta( $event_id, $key, $value );
	}

	private static function copy_configuration( $source, $target ) {
		$keys = array( '_mi_activity_id', '_mi_capacity', '_mi_waitlist_enabled', '_mi_pricing_mode', '_mi_fixed_price_cents', '_mi_economic_mode', '_mi_deposit_percentage', '_mi_payment_methods', '_mi_data_profile', '_mi_participant_fields', '_mi_participant_required_fields', '_mi_custom_participant_fields', '_mi_participant_extra_scope', '_mi_ticket_types', '_mi_options', '_mi_marketing_enabled', '_mi_special_requests_enabled', '_mi_overnight_enabled', '_mi_accommodations' );
		foreach ( $keys as $key ) {
			$value = get_post_meta( $source, $key, true );
			if ( '' !== $value ) update_post_meta( $target, $key, $value );
		}
	}

	private static function redirect_result( $message, $error = false, $event_id = 0 ) {
		$url = wp_get_referer() ?: home_url( '/' );
		$args = array( 'mi_portal_message' => $message, 'mi_portal_error' => $error ? '1' : '0', 'mi_portal_view' => 'manage' );
		if ( $event_id ) $args['mi_portal_event'] = $event_id;
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	public static function render() {
		if ( ! is_ssl() ) return '<div class="mi-portal-notice mi-portal-error"><strong>Connessione sicura necessaria.</strong><p>Il portale non può essere usato finché HTTPS non è configurato correttamente.</p></div>';
		if ( ! empty( $_GET['mi_cancel_participant'] ) && ! empty( $_GET['mi_cancel_token'] ) ) return self::cancellation_view();
		if ( ! is_user_logged_in() ) return self::login_view();
		if ( ! current_user_can( 'mi_portal_access' ) && ! current_user_can( 'manage_options' ) ) return '<div class="mi-portal-empty"><h2>C’è qualcuno qui…?</h2><p>Il tuo account non è abilitato al servizio iscrizioni.</p></div>';
		$view = sanitize_key( wp_unslash( $_GET['mi_portal_view'] ?? 'manage' ) );
		$can_create = current_user_can( 'mi_create_events' ) || current_user_can( 'manage_options' );
		ob_start();
		?><main class="mi-portal"><header class="mi-portal-header"><div><span class="mi-portal-eyebrow">Servizio iscrizioni</span><h1>Gestione eventi</h1></div><a class="mi-portal-logout" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">Esci</a></header>
		<nav class="mi-portal-switcher" aria-label="Vista portale"><?php if ( $can_create ) : ?><a class="<?php echo 'create' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'create' ) ); ?>">Crea evento</a><?php endif; ?><a class="<?php echo 'create' !== $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'manage' ) ); ?>"><?php echo esc_html( self::manage_label() ); ?></a></nav>
		<?php self::notice(); ?>
		<?php if ( 'create' === $view && $can_create ) self::create_view(); else self::manage_view(); ?>
		</main><?php
		return ob_get_clean();
	}

	private static function cancellation_view() {
		$participant_id = absint( $_GET['mi_cancel_participant'] ?? 0 );
		$token = sanitize_text_field( wp_unslash( $_GET['mi_cancel_token'] ?? '' ) );
		$participant = MI_Registration_Service::participant_from_token( $participant_id, $token );
		if ( is_wp_error( $participant ) ) return '<section class="mi-portal mi-portal-empty"><h1>Collegamento non disponibile</h1><p>' . esc_html( $participant->get_error_message() ) . '</p></section>';
		if ( 'CANCELLED' === $participant['status'] ) return '<section class="mi-portal mi-portal-empty"><h1>Partecipazione già annullata</h1><p>Non è necessaria alcuna altra operazione.</p></section>';
		ob_start(); ?><section class="mi-portal mi-cancel-confirm"><span class="mi-portal-eyebrow">Conferma richiesta</span><h1>Annullare questa partecipazione?</h1><p><strong><?php echo esc_html( $participant['first_name'] . ' ' . $participant['last_name'] ); ?></strong><br><?php echo esc_html( get_the_title( (int) $participant['event_id'] ) ); ?><br>Prenotazione <?php echo esc_html( $participant['order_code'] ); ?></p><p>L’operazione libera il posto di questa persona. Eventuali rimborsi devono essere concordati separatamente con la segreteria.</p><form method="post"><input type="hidden" name="mi_portal_action" value="cancel_participant_public"><input type="hidden" name="participant_id" value="<?php echo esc_attr( $participant_id ); ?>"><input type="hidden" name="cancel_token" value="<?php echo esc_attr( $token ); ?>"><?php wp_nonce_field( 'mi_cancel_participant_public_' . $participant_id, 'mi_portal_nonce' ); ?><button class="mi-danger" type="submit">Sì, annulla la partecipazione</button></form></section><?php return ob_get_clean();
	}

	private static function login_view() {
		ob_start(); ?><section class="mi-portal mi-portal-login"><span class="mi-portal-eyebrow">Area riservata</span><h1>Gestione iscrizioni</h1><p>Accedi come segretario o operatore. Se sei già autenticato in WordPress entrerai direttamente.</p><?php wp_login_form( array( 'redirect' => get_permalink(), 'label_username' => 'Utente', 'label_password' => 'Parola d’accesso', 'label_log_in' => 'Accedi', 'remember' => true ) ); ?></section><?php return ob_get_clean();
	}

	private static function manage_label() {
		$scope = MI_Access::event_ids();
		if ( 'ALL' !== $scope && 1 === count( $scope ) ) return 'Gestisci evento';
		if ( 'ALL' !== $scope ) return 'I miei eventi';
		return 'Gestisci eventi';
	}

	private static function accessible_event_ids() {
		$scope = MI_Access::event_ids();
		if ( 'ALL' !== $scope ) return $scope;
		return get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'fields' => 'ids' ) );
	}

	private static function manage_view() {
		global $wpdb;
		$ids = self::accessible_event_ids();
		if ( ! $ids ) { echo '<section class="mi-portal-empty"><div class="mi-portal-bubble">Na⁺</div><h2>C’è qualcuno qui…?</h2><p>Al momento non ti è stato assegnato nessun evento. Chiedi all’amministratore o al segretario di associarti a un evento.</p></section>'; return; }
		$events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'post__in' => $ids, 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
		echo '<section><h2>Eventi</h2><div class="mi-event-grid">';
		foreach ( $events as $event ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total_qty),0) FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d AND status IN ('CONFIRMED','PENDING_PAYMENT')", $event->ID ) );
			$capacity = max( 1, absint( get_post_meta( $event->ID, '_mi_capacity', true ) ) );
			$url = add_query_arg( array( 'mi_portal_view' => 'manage', 'mi_portal_event' => $event->ID ), get_permalink() );
			echo '<a class="mi-event-card" href="' . esc_url( $url ) . '"><strong>' . esc_html( $event->post_title ) . '</strong><small>' . esc_html( self::format_date( get_post_meta( $event->ID, '_mi_event_starts_at', true ) ) ) . '</small><small>' . esc_html( $count . ' / ' . $capacity . ' prenotazioni · ' . ( 'publish' === $event->post_status ? 'Attivo' : 'Bozza' ) ) . '</small><small>Scadenza: ' . esc_html( self::format_date( get_post_meta( $event->ID, '_mi_registration_closes_at', true ) ) ) . '</small></a>';
		}
		echo '</div></section>';
		$selected = absint( $_GET['mi_portal_event'] ?? 0 );
		if ( $selected && in_array( $selected, $ids, true ) ) self::registrations_view( $selected ); else self::registrations_view( 0, $ids );
	}

	private static function registrations_view( $event_id = 0, $event_ids = array() ) {
		global $wpdb;
		if ( $event_id ) { $where = $wpdb->prepare( 'r.event_id=%d', $event_id ); } else { $safe = array_values( array_filter( array_map( 'absint', $event_ids ) ) ); $where = 'r.event_id IN (' . implode( ',', $safe ) . ')'; }
		$rows = $wpdb->get_results( "SELECT r.id registration_id,r.event_id,r.created_at,r.buyer_email,p.id participant_id,p.first_name,p.last_name FROM {$wpdb->prefix}mi_registrations r JOIN {$wpdb->prefix}mi_participants p ON p.registration_id=r.id WHERE {$where} ORDER BY r.created_at DESC,p.id ASC LIMIT 10", ARRAY_A );
		echo '<section><h2>Ultime iscrizioni</h2><div class="mi-booking-list">';
		foreach ( $rows as $index => $row ) { $url = add_query_arg( array( 'mi_portal_view' => 'manage', 'mi_portal_booking' => $row['registration_id'] ), get_permalink() ); echo '<a href="' . esc_url( $url ) . '"><span>' . esc_html( $index + 1 ) . '</span><strong>' . esc_html( $row['first_name'] . ' ' . $row['last_name'] ) . '</strong><small>' . esc_html( get_the_title( $row['event_id'] ) . ' · ' . $row['created_at'] . ' · ' . $row['buyer_email'] ) . '</small></a>'; }
		if ( ! $rows ) echo '<p class="mi-portal-muted">Nessuna iscrizione presente.</p>';
		echo '</div></section>';
		$booking_id = absint( $_GET['mi_portal_booking'] ?? 0 ); if ( $booking_id ) self::booking_detail( $booking_id );
	}

	private static function booking_detail( $registration_id ) {
		global $wpdb; $registration = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $registration_id ), ARRAY_A );
		if ( ! $registration || ! MI_Access::can_access_event( (int) $registration['event_id'] ) ) return;
		$participants = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d ORDER BY id", $registration_id ), ARRAY_A );
		echo '<section class="mi-booking-detail"><h2>Prenotazione ' . esc_html( $registration['order_code'] ) . '</h2><p><strong>Referente:</strong> ' . esc_html( $registration['buyer_first_name'] . ' ' . $registration['buyer_last_name'] ) . '<br>' . esc_html( $registration['buyer_email'] . ' · ' . $registration['buyer_phone'] ) . '</p>';
		foreach ( $participants as $participant ) {
			echo '<article><h3>' . esc_html( $participant['first_name'] . ' ' . $participant['last_name'] ) . ( 'CANCELLED' === $participant['status'] ? ' <small>— Annullata</small>' : '' ) . '</h3>';
			$fields = json_decode( (string) $participant['extra_json'], true );
			foreach ( (array) $fields as $key => $value ) if ( '' !== (string) $value ) echo '<p><span>' . esc_html( ucfirst( str_replace( '_', ' ', preg_replace( '/^custom_/', '', $key ) ) ) ) . '</span><strong>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</strong></p>';
			if ( 'ACTIVE' === ( $participant['status'] ?: 'ACTIVE' ) ) {
				echo '<form method="post" onsubmit="return confirm(\'Annullare la partecipazione di questa persona?\')"><input type="hidden" name="mi_portal_action" value="cancel_participant_portal"><input type="hidden" name="participant_id" value="' . esc_attr( $participant['id'] ) . '">';
				wp_nonce_field( 'mi_cancel_participant_portal_' . $participant['id'], 'mi_portal_nonce' );
				echo '<button class="mi-danger" type="submit">Annulla partecipazione</button></form>';
			}
			echo '</article>';
		}
		echo '</section>';
	}

	private static function create_view() {
		$models = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => 30, 'orderby' => 'date', 'order' => 'DESC' ) );
		$activities = get_posts( array( 'post_type' => MI_Event_Post_Type::ACTIVITY_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?><form class="mi-event-wizard" method="post"><input type="hidden" name="mi_portal_action" value="create_event"><?php wp_nonce_field( 'mi_portal_create_event', 'mi_portal_nonce' ); ?>
		<section class="mi-wizard-step is-active"><span class="mi-portal-eyebrow">1 di 5</span><h2>La nuova attività</h2><label>Titolo<input name="title" required placeholder="Es. Pellegrinaggio ad Assisi"></label><label>Gruppo o settore organizzatore <small>(facoltativo)</small><select name="activity_id"><option value="">Nessuno</option><?php foreach ( $activities as $activity ) : ?><option value="<?php echo esc_attr( $activity->ID ); ?>"><?php echo esc_html( $activity->post_title ); ?></option><?php endforeach; ?></select></label></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">2 di 5</span><h2>Vuoi partire da un evento precedente?</h2><select name="copy_event_id"><option value="">No, parto da zero</option><?php foreach ( $models as $model ) : ?><option value="<?php echo esc_attr( $model->ID ); ?>"><?php echo esc_html( $model->post_title ); ?></option><?php endforeach; ?></select><p class="mi-portal-muted">Copiamo soltanto configurazione e domande, mai iscrizioni o dati personali.</p></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">3 di 5</span><h2>Date e posti</h2><label>Data e ora di inizio<input type="datetime-local" name="starts_at"></label><label>Chiusura iscrizioni<input type="datetime-local" name="closes_at"></label><label>Posti disponibili<input type="number" min="1" max="10000" name="capacity" value="30"></label></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">4 di 5</span><h2>Servizi essenziali</h2><label class="mi-check"><input type="checkbox" name="overnight" value="1" data-mi-overnight> È previsto il pernottamento</label><div data-mi-accommodations hidden><h3>Tipi di alloggio</h3><?php foreach ( array( 'SINGOLA' => 'Singola', 'DOPPIA_SEPARATI' => 'Doppia con letti separati', 'DOPPIA_MATRIMONIALE' => 'Doppia matrimoniale', 'TRIPLA' => 'Tripla', 'MULTIPLA' => 'Multipla' ) as $code => $label ) : ?><label class="mi-check"><input type="checkbox" name="accommodations[]" value="<?php echo esc_attr( $code ); ?>"> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></div></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">5 di 5</span><h2>Controlla e crea la bozza</h2><p>La bozza non sarà pubblicata automaticamente. Potrai completare quote, servizi e domande prima dell’anteprima.</p><button class="mi-primary" type="submit">Crea in bozza</button></section>
		<div class="mi-wizard-actions"><button type="button" class="mi-secondary" data-mi-back disabled>Indietro</button><button type="button" class="mi-primary" data-mi-next>Continua</button></div></form><?php
	}

	private static function format_date( $value ) { if ( ! $value ) return 'Data da definire'; $time = strtotime( str_replace( 'T', ' ', $value ) ); return $time ? wp_date( 'd/m/Y H:i', $time ) : $value; }
	private static function notice() { if ( empty( $_GET['mi_portal_message'] ) ) return; $error = ! empty( $_GET['mi_portal_error'] ); echo '<div class="mi-portal-notice ' . ( $error ? 'mi-portal-error' : '' ) . '">' . esc_html( sanitize_text_field( wp_unslash( $_GET['mi_portal_message'] ) ) ) . '</div>'; }
}
