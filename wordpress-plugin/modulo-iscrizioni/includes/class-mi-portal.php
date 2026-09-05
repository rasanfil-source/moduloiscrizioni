<?php

defined( 'ABSPATH' ) || exit;

final class MI_Portal {
	const SHORTCODE = 'mi_portale_gestione';
	const CUSTOM_COMMUNICATION_TYPES_OPTION = 'mi_custom_communication_types';

	public static function boot() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		// Il portale autonomo deve terminare la richiesta prima che tema, builder e
		// plugin frontend eseguano i propri callback su template_redirect.
		add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ), -100 );
		add_action( 'template_redirect', array( __CLASS__, 'render_virtual_page' ), -90 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'send_headers', array( __CLASS__, 'secure_cancellation_headers' ) );
		add_action( 'mi_pulisci_bozze_cestinate', array( __CLASS__, 'purge_trashed_drafts' ) );
		add_action( 'mi_pulisci_bozze_cestinate', array( __CLASS__, 'archive_completed_event_sheets' ), 20 );
	}

	/** Elimina definitivamente soltanto le bozze-evento nel cestino da oltre 30 giorni e prive di iscrizioni. */
	public static function purge_trashed_drafts() {
		$drafts = get_posts( array(
			'post_type'              => MI_Event_Post_Type::EVENT_TYPE,
			'post_status'            => 'trash',
			'numberposts'            => 100,
			'fields'                 => 'ids',
			'date_query'             => array( array( 'column' => 'post_modified_gmt', 'before' => gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ), 'inclusive' => true ) ),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );
		if ( ! $drafts ) return;
		global $wpdb;
		foreach ( $drafts as $event_id ) {
			$registrations = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d", $event_id ) );
			if ( 0 !== $registrations ) continue;
			if ( ! get_post_meta( $event_id, '_mi_operational_sheet_id', true ) ) { wp_delete_post( $event_id, true ); continue; }
			$pulizia = MI_Workspace_Client::request( 'ELIMINA_FOGLIO_EVENTO', array( 'id_evento' => (string) $event_id ) );
			// In caso di indisponibilità Workspace conserviamo la bozza e riproviamo:
			// è preferibile non lasciare un documento Drive senza riferimento.
			if ( ! is_wp_error( $pulizia ) ) wp_delete_post( $event_id, true );
		}
	}

	/** Allinea i fogli alla stessa distinzione tra eventi correnti e passati mostrata nel portale. */
	public static function archive_completed_event_sheets() {
		$events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => 200, 'fields' => 'ids', 'meta_key' => '_mi_operational_sheet_id', 'no_found_rows' => true ) );
		if ( $events ) {
			$verifica = MI_Workspace_Client::request( 'VERIFICA_FOGLI_EVENTO', array( 'id_eventi' => array_map( 'strval', $events ) ) );
			if ( ! is_wp_error( $verifica ) && ! empty( $verifica['stati'] ) && is_array( $verifica['stati'] ) ) {
				foreach ( $verifica['stati'] as $stato ) {
					$id = absint( $stato['id_evento'] ?? 0 );
					if ( ! $id || ! in_array( $id, $events, true ) ) continue;
					if ( empty( $stato['esiste'] ) ) update_post_meta( $id, '_mi_sheet_missing', '1' );
					else delete_post_meta( $id, '_mi_sheet_missing' );
				}
			}
		}
		$correnti = array();
		$passati = array();
		foreach ( $events as $event_id ) {
			$annullato = (bool) get_post_meta( $event_id, '_mi_event_cancelled_at', true );
			$archiviato = (bool) get_post_meta( $event_id, '_mi_event_archived_at', true );
			$chiusura = (string) get_post_meta( $event_id, '_mi_registration_closes_at', true );
			$inizio = (string) get_post_meta( $event_id, '_mi_event_starts_at', true );
			$passato = $archiviato || ( ! $annullato && self::is_past_event( $chiusura ?: $inizio ) );
			if ( $passato ) $passati[] = (string) $event_id;
			else $correnti[] = (string) $event_id;
		}
		$result = MI_Workspace_Client::request( 'ORGANIZZA_FOGLI_EVENTO', array( 'eventi_correnti' => $correnti, 'eventi_passati' => $passati ) );
		if ( ! is_wp_error( $result ) ) {
			foreach ( $passati as $event_id ) update_post_meta( (int) $event_id, '_mi_sheet_archived_at', current_time( 'mysql', true ) );
			foreach ( $correnti as $event_id ) delete_post_meta( (int) $event_id, '_mi_sheet_archived_at' );
		}
	}

	public static function secure_cancellation_headers() {
		if ( ( empty( $_GET['mi_cancel_participant'] ) || empty( $_GET['mi_cancel_token'] ) ) && empty( $_GET['mi_status'] ) ) return;
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}

	public static function assets() {
		if ( ! is_singular() && empty( $_GET['mi_portal'] ) && empty( $_GET['mi_status'] ) ) return;
		$post = get_post();
		if ( empty( $_GET['mi_portal'] ) && empty( $_GET['mi_status'] ) && ( ! $post || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) ) return;
		wp_enqueue_style( 'mi-portal', MI_PLUGIN_URL . 'assets/portal.css', array(), MI_VERSION );
		wp_enqueue_script( 'mi-portal', MI_PLUGIN_URL . 'assets/portal.js', array(), MI_VERSION, true );
	}

	public static function handle_actions() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
		$action = sanitize_key( wp_unslash( $_POST['mi_portal_action'] ?? '' ) );
		if ( 'accedi_portale' === $action ) return self::gestisci_accesso_portale();
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
		if ( in_array( $action, array( 'update_event', 'cancel_event', 'archive_event', 'trash_event' ), true ) ) return self::handle_event_management_action( $action );
		if ( 'prepare_event_outputs' === $action ) return self::handle_event_outputs_action();
		if ( 'repair_event_sheet' === $action ) return self::handle_event_sheet_repair_action();
		if ( 'publish_event_portal' === $action ) return self::handle_event_publication_action();
		if ( 'prepare_communication' === $action ) return self::handle_communication_action();
		if ( in_array( $action, array( 'add_communication_type', 'delete_communication_type' ), true ) ) return self::handle_communication_type_action( $action );
		if ( in_array( $action, array( 'create_operator', 'update_operator' ), true ) ) return self::handle_operator_action( $action );
		if ( in_array( $action, array( 'create_group', 'update_group', 'delete_group' ), true ) ) return self::handle_group_action( $action );
		if ( 'create_event' !== $action ) return;
		if ( ! is_user_logged_in() || ! current_user_can( 'mi_create_events' ) ) wp_die( 'Accesso non consentito.', 403 );
		check_admin_referer( 'mi_portal_create_event', 'mi_portal_nonce' );
		$existing_event_id = absint( $_POST['event_id'] ?? $_POST['draft_event_id'] ?? 0 );
		$existing_event = null;
		if ( $existing_event_id ) {
			$existing_event = get_post( $existing_event_id );
			$allowed_statuses = array( 'draft', 'publish', 'private' );
			if ( ! $existing_event || MI_Event_Post_Type::EVENT_TYPE !== $existing_event->post_type || ! in_array( $existing_event->post_status, $allowed_statuses, true ) || get_post_meta( $existing_event_id, '_mi_event_cancelled_at', true ) || ! MI_Access::can_access_event( $existing_event_id ) ) wp_die( 'Evento non accessibile.', 403 );
		}
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		if ( ! $title ) return self::redirect_result( 'Titolo obbligatorio.', true );
		$starts_at = self::normalize_portal_date( sanitize_text_field( wp_unslash( $_POST['starts_at'] ?? '' ) ) );
		if ( ! $starts_at ) return self::redirect_result( 'La data di inizio deve usare il formato gg/mm/aaaa hh:mm, con un anno di quattro cifre.', true );
		$start_date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $starts_at, wp_timezone() );
		$unchanged_start = $existing_event && $starts_at === (string) get_post_meta( $existing_event_id, '_mi_event_starts_at', true );
		if ( ! $start_date || ( ! $unchanged_start && $start_date->format( 'Y-m-d' ) < current_time( 'Y-m-d' ) ) ) return self::redirect_result( 'La data di inizio non può essere precedente a oggi.', true );
		$latest_event_date = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '+10 years' )->setTime( 23, 59 );
		if ( $start_date > $latest_event_date ) return self::redirect_result( 'La data dell’evento non può essere oltre dieci anni nel futuro.', true );
		$opens_input = sanitize_text_field( wp_unslash( $_POST['opens_at'] ?? '' ) );
		$closes_input = sanitize_text_field( wp_unslash( $_POST['closes_at'] ?? '' ) );
		$opens_at = $opens_input ? self::normalize_portal_date( $opens_input ) : '';
		$closes_at = self::normalize_portal_date( $closes_input );
		if ( ( $opens_input && ! $opens_at ) || ! $closes_at ) return self::redirect_result( 'Le date devono usare il formato gg/mm/aaaa hh:mm, con un anno di quattro cifre.', true );
		$open_date = $opens_at ? DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $opens_at, wp_timezone() ) : null;
		$close_date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $closes_at, wp_timezone() );
		$now_minute = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', current_time( 'Y-m-d\TH:i' ), wp_timezone() );
		if ( ( $opens_at && ! $open_date ) || ! $close_date ) return self::redirect_result( 'Controlla le date di apertura e chiusura delle iscrizioni.', true );
		$unchanged_close = $existing_event && $closes_at === (string) get_post_meta( $existing_event_id, '_mi_registration_closes_at', true );
		if ( $now_minute && ! $unchanged_close && $close_date < $now_minute ) return self::redirect_result( 'La chiusura delle iscrizioni non può essere precedente a questo momento.', true );
		if ( $open_date && $close_date < $open_date ) return self::redirect_result( 'La chiusura delle iscrizioni non può precedere l’apertura.', true );
		if ( $close_date > $start_date ) return self::redirect_result( 'La chiusura delle iscrizioni non può essere successiva all’inizio dell’evento.', true );
		$copy_id = absint( $_POST['copy_event_id'] ?? 0 );
		if ( $copy_id && ! MI_Access::can_access_event( $copy_id ) ) wp_die( 'Evento modello non accessibile.', 403 );
		$description = self::limit_text_lines( sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 6, 1200 );
		if ( $existing_event ) {
			$event_id = wp_update_post( array( 'ID' => $existing_event_id, 'post_title' => $title, 'post_content' => $description ), true );
			if ( is_wp_error( $event_id ) ) return self::redirect_result( 'Non è stato possibile aggiornare l’evento.', true, $existing_event_id );
		} else {
			$event_id = wp_insert_post( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'draft', 'post_title' => $title, 'post_content' => $description, 'post_author' => get_current_user_id() ), true );
			if ( is_wp_error( $event_id ) ) return self::redirect_result( 'Non è stato possibile creare la bozza.', true );
			if ( $copy_id ) self::copy_configuration( $copy_id, $event_id );
		}
		$activity_id = absint( $_POST['activity_id'] ?? 0 );
		if ( $activity_id && MI_Access::can_access_activity( $activity_id ) ) update_post_meta( $event_id, '_mi_activity_id', $activity_id );
		$gestore = self::risolvi_gestore_evento( $event_id, false );
		if ( $gestore instanceof WP_User ) update_post_meta( $event_id, '_mi_manager_user_id', $gestore->ID );
		self::save_date( $event_id, '_mi_event_starts_at', $starts_at );
		self::save_date( $event_id, '_mi_registration_opens_at', $opens_at );
		self::save_date( $event_id, '_mi_registration_closes_at', $closes_at );
		update_post_meta( $event_id, '_mi_event_location', mb_substr( sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ), 0, 180 ) );
		update_post_meta( $event_id, '_mi_capacity', min( 10000, max( 1, absint( $_POST['capacity'] ?? 30 ) ) ) );
		update_post_meta( $event_id, '_mi_waitlist_enabled', ! empty( $_POST['waitlist_enabled'] ) ? '1' : '0' );
		update_post_meta( $event_id, '_mi_operational_profile', MI_Field_Schema::sanitize_operational_profile( wp_unslash( $_POST['operational_profile'] ?? 'AUTOMATICO' ) ) );
		$overnight = ! empty( $_POST['overnight'] ) ? '1' : '0';
		update_post_meta( $event_id, '_mi_overnight_enabled', $overnight );
		$accommodations = '1' === $overnight ? array_values( array_intersect( array( 'SINGOLA', 'DOPPIA_SEPARATI', 'DOPPIA_MATRIMONIALE', 'TRIPLA', 'MULTIPLA' ), array_map( 'strtoupper', array_map( 'sanitize_key', (array) wp_unslash( $_POST['accommodations'] ?? array() ) ) ) ) ) : array();
		update_post_meta( $event_id, '_mi_accommodations', $accommodations );
		$field_configuration = MI_Field_Schema::sanitize_configuration(
			'CUSTOM',
			(array) wp_unslash( $_POST['participant_fields'] ?? array() ),
			(array) wp_unslash( $_POST['participant_required'] ?? array() )
		);
		update_post_meta( $event_id, '_mi_data_profile', $field_configuration['profile'] );
		update_post_meta( $event_id, '_mi_participant_fields', $field_configuration['enabled'] );
		update_post_meta( $event_id, '_mi_participant_required_fields', $field_configuration['required'] );
		update_post_meta( $event_id, '_mi_high_impact_approved', MI_Field_Schema::has_high_impact_fields( $field_configuration ) ? '1' : '0' );
		$scope = strtoupper( sanitize_key( wp_unslash( $_POST['participant_extra_scope'] ?? 'ONE' ) ) );
		update_post_meta( $event_id, '_mi_participant_extra_scope', 'ALL' === $scope ? 'ALL' : 'ONE' );
		$question_labels = (array) wp_unslash( $_POST['custom_question_label'] ?? array() );
		$question_types = (array) wp_unslash( $_POST['custom_question_type'] ?? array() );
		$question_required = (array) wp_unslash( $_POST['custom_question_required'] ?? array() );
		$custom_questions = array();
		foreach ( array_slice( $question_labels, 0, 8, true ) as $index => $label ) {
			$label = sanitize_text_field( $label );
			if ( ! $label ) continue;
			$custom_questions[] = array( 'key' => 'domanda_' . ( $index + 1 ), 'label' => $label, 'type' => $question_types[ $index ] ?? 'text', 'required' => ! empty( $question_required[ $index ] ), 'retention' => 'STANDARD' );
		}
		update_post_meta( $event_id, '_mi_custom_participant_fields', MI_Field_Schema::sanitize_custom_fields( $custom_questions ) );
		update_post_meta( $event_id, '_mi_special_requests_enabled', ! empty( $_POST['special_requests_enabled'] ) ? '1' : '0' );
		update_post_meta( $event_id, '_mi_marketing_enabled', ! empty( $_POST['marketing_enabled'] ) ? '1' : '0' );
		$pricing_mode = strtoupper( sanitize_key( wp_unslash( $_POST['pricing_mode'] ?? 'NONE' ) ) );
		if ( ! in_array( $pricing_mode, array( 'NONE', 'ZERO', 'FIXED' ), true ) ) $pricing_mode = 'NONE';
		$fixed_price = self::parse_euro_cents( wp_unslash( $_POST['fixed_price'] ?? '' ) );
		$service_labels = array( 'pullman' => 'Pullman', 'pranzo' => 'Pranzo', 'rimborso-spese' => 'Rimborso spese' );
		$service_options = array();
		foreach ( $service_labels as $service_code => $service_label ) {
			if ( empty( $_POST['service_enabled'][ $service_code ] ) ) continue;
			$service_price = self::parse_euro_cents( wp_unslash( $_POST['service_price'][ $service_code ] ?? '' ) );
			if ( is_wp_error( $service_price ) || $service_price < 0 ) continue;
			$service_options[] = array( 'code' => $service_code, 'name' => $service_label, 'scope' => 'TICKET', 'price_cents' => $service_price, 'max_quantity' => 1 );
		}
		$accommodation_labels = array( 'SINGOLA' => 'Singola', 'DOPPIA_SEPARATI' => 'Doppia con letti separati', 'DOPPIA_MATRIMONIALE' => 'Doppia matrimoniale', 'TRIPLA' => 'Tripla', 'MULTIPLA' => 'Multipla' );
		$accommodation_options = array();
		foreach ( $accommodations as $accommodation_code ) {
			$accommodation_price = self::parse_euro_cents( wp_unslash( $_POST['accommodation_price'][ $accommodation_code ] ?? '' ) );
			if ( is_wp_error( $accommodation_price ) || $accommodation_price < 0 ) continue;
			$accommodation_options[] = array( 'code' => 'alloggio-' . strtolower( str_replace( '_', '-', $accommodation_code ) ), 'name' => 'Alloggio: ' . $accommodation_labels[ $accommodation_code ], 'scope' => 'TICKET', 'price_cents' => $accommodation_price, 'max_quantity' => 1 );
		}
		$priced_options = array_merge( $accommodation_options, $service_options );
		if ( in_array( $pricing_mode, array( 'ZERO', 'FIXED' ), true ) ) {
			$priced_options = array();
			$accommodations = array();
			update_post_meta( $event_id, '_mi_overnight_enabled', '0' );
			update_post_meta( $event_id, '_mi_accommodations', array() );
		} elseif ( $priced_options ) {
			$pricing_mode = 'CALCULATED';
		}
		update_post_meta( $event_id, '_mi_pricing_mode', $pricing_mode );
		update_post_meta( $event_id, '_mi_fixed_price_cents', 'FIXED' === $pricing_mode && ! is_wp_error( $fixed_price ) ? $fixed_price : 0 );
		update_post_meta( $event_id, '_mi_ticket_types', array( array(
			'code'          => 'standard',
			'name'          => 'ZERO' === $pricing_mode ? 'Iscrizione' : 'Quota di partecipazione',
			'price_cents'   => 'FIXED' === $pricing_mode && ! is_wp_error( $fixed_price ) ? max( 0, $fixed_price ) : 0,
			'max_per_order' => 20,
			'capacity'      => 0,
		) ) );
		update_post_meta( $event_id, '_mi_options', $priced_options );
		$economic_mode = strtoupper( sanitize_key( wp_unslash( $_POST['economic_mode'] ?? 'FULL_PAYMENT' ) ) );
		$economic_mode = 'ZERO' === $pricing_mode ? 'REGISTRATION_ONLY' : ( in_array( $economic_mode, array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ? $economic_mode : 'FULL_PAYMENT' );
		update_post_meta( $event_id, '_mi_economic_mode', $economic_mode );
		$deposit_mode = 'FIXED' === strtoupper( sanitize_key( wp_unslash( $_POST['deposit_mode'] ?? 'PERCENTAGE' ) ) ) ? 'FIXED' : 'PERCENTAGE';
		$deposit_fixed = self::parse_euro_cents( wp_unslash( $_POST['deposit_fixed'] ?? '' ) );
		if ( 'DEPOSIT_BALANCE' === $economic_mode && 'FIXED' === $deposit_mode && ( is_wp_error( $deposit_fixed ) || $deposit_fixed < 1 ) ) return self::redirect_result( 'Indica un importo fisso valido per la caparra.', true, $event_id );
		update_post_meta( $event_id, '_mi_deposit_mode', $deposit_mode );
		update_post_meta( $event_id, '_mi_deposit_percentage', min( 99, max( 1, absint( $_POST['deposit_percentage'] ?? 30 ) ) ) );
		update_post_meta( $event_id, '_mi_deposit_fixed_cents', 'FIXED' === $deposit_mode && ! is_wp_error( $deposit_fixed ) ? max( 0, $deposit_fixed ) : 0 );
		$payment_methods = array_values( array_intersect( array( 'BANK_TRANSFER', 'CARD', 'CASH' ), array_map( 'strtoupper', array_map( 'sanitize_key', (array) wp_unslash( $_POST['payment_methods'] ?? array() ) ) ) ) );
		update_post_meta( $event_id, '_mi_payment_methods', in_array( $economic_mode, array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ? $payment_methods : array() );
		$upload_warning = self::save_cover_upload( $event_id );
		$is_published_edit = $existing_event && 'publish' === $existing_event->post_status;
		$success_message = $is_published_edit ? 'Evento aggiornato correttamente.' : ( $existing_event ? 'Bozza aggiornata correttamente.' : 'Bozza creata correttamente.' );
		if ( $is_published_edit ) {
			$revision = MI_Registration_Service::ensure_published_revision( $event_id, true );
			if ( ! $revision ) return self::redirect_result( 'Le modifiche sono state salvate, ma non è stato possibile aggiornare la configurazione pubblica. Riprova.', true, $event_id );
			$workspace = self::prepara_produzioni_workspace( $event_id, 'PUBBLICATO' );
			if ( is_wp_error( $workspace ) ) return self::redirect_result( 'Le modifiche sono state salvate in WordPress; il foglio Google non è ancora allineato: ' . $workspace->get_error_message(), true, $event_id );
		}
		self::redirect_result( $upload_warning ? $success_message . ' Immagine non caricata: ' . $upload_warning : $success_message, false, $event_id, ! $is_published_edit );
	}

	private static function handle_event_outputs_action() {
		if ( ! is_user_logged_in() || ! current_user_can( 'mi_create_events' ) ) wp_die( 'Accesso non consentito.', 403 );
		$event_id = absint( $_POST['event_id'] ?? 0 );
		check_admin_referer( 'mi_portal_prepare_event_outputs_' . $event_id, 'mi_portal_nonce' );
		$event = get_post( $event_id );
		if ( ! $event || MI_Event_Post_Type::EVENT_TYPE !== $event->post_type || ! MI_Access::can_access_event( $event_id ) ) wp_die( 'Evento non accessibile.', 403 );
		$result = self::prepara_produzioni_workspace( $event_id, 'BOZZA' );
		if ( is_wp_error( $result ) ) return self::redirect_result( 'Il foglio Google non è stato creato: ' . $result->get_error_message(), true, $event_id );
		return self::redirect_result( 'Foglio Google collegato. Ora puoi pubblicare l’evento.', false, $event_id, true );
	}

	/** Verifica il collegamento e ricrea dai dati centrali un foglio mancante. */
	private static function handle_event_sheet_repair_action() {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'mi_portal_access' ) && ! current_user_can( 'manage_options' ) ) ) wp_die( 'Accesso non consentito.', 403 );
		$event_id = absint( $_POST['event_id'] ?? 0 );
		check_admin_referer( 'mi_portal_repair_event_sheet_' . $event_id, 'mi_portal_nonce' );
		if ( ! $event_id || ! MI_Access::can_access_event( $event_id ) ) wp_die( 'Evento non accessibile.', 403 );
		$verifica = MI_Workspace_Client::request( 'VERIFICA_FOGLIO_EVENTO', array( 'id_evento' => (string) $event_id ) );
		if ( is_wp_error( $verifica ) ) return self::redirect_result( 'Non è stato possibile verificare il foglio: ' . $verifica->get_error_message(), true, $event_id, true );
		if ( ! empty( $verifica['esiste'] ) ) return self::redirect_result( 'Il foglio Google è disponibile e correttamente collegato.', false, $event_id, true );
		update_post_meta( $event_id, '_mi_sheet_missing', '1' );
		$ricreato = self::prepara_produzioni_workspace( $event_id, 'publish' === get_post_status( $event_id ) ? 'PUBBLICATO' : 'BOZZA' );
		if ( is_wp_error( $ricreato ) ) return self::redirect_result( 'Il foglio non è disponibile e non è stato possibile ricrearlo: ' . $ricreato->get_error_message(), true, $event_id, true );
		return self::redirect_result( 'Il foglio mancante è stato ricreato dai dati conservati in DB_MODULI.', false, $event_id, true );
	}

	/** Crea o riallinea il foglio operativo e conserva tutti i collegamenti restituiti. */
	private static function prepara_produzioni_workspace( $event_id, $stato ) {
		$event = get_post( $event_id );
		if ( ! $event || MI_Event_Post_Type::EVENT_TYPE !== $event->post_type ) return new WP_Error( 'mi_evento_non_valido', 'Evento non valido.' );
		$gestore = self::risolvi_gestore_evento( $event_id, false );
		$url_iscrizione = MI_Shortcode::url_iscrizione( $event_id );
		$ha_saldo = 'DEPOSIT_BALANCE' === get_post_meta( $event_id, '_mi_economic_mode', true );
		$url_saldo = $ha_saldo ? add_query_arg( array( 'mi_status' => '1', 'evento' => $event_id ), home_url( '/' ) ) : '';
		$profilo_operativo = self::initial_operational_profile( $event_id );
		$result = MI_Workspace_Client::request( 'PREPARA_PRODUZIONI_EVENTO', array(
			'id_evento' => (string) $event_id,
			'id_gruppo' => (string) absint( get_post_meta( $event_id, '_mi_activity_id', true ) ),
			'titolo' => $event->post_title,
			'stato' => in_array( $stato, array( 'BOZZA', 'PUBBLICATO', 'PRIVATO' ), true ) ? $stato : 'BOZZA',
			'capienza' => max( 1, absint( get_post_meta( $event_id, '_mi_capacity', true ) ) ),
			'apertura_iscrizioni' => (string) get_post_meta( $event_id, '_mi_registration_opens_at', true ),
			'chiusura_iscrizioni' => (string) get_post_meta( $event_id, '_mi_registration_closes_at', true ),
			'modalita_prezzo' => (string) get_post_meta( $event_id, '_mi_economic_mode', true ),
			'profilo_operativo' => $profilo_operativo,
			'url_iscrizione' => $url_iscrizione,
			'url_saldo' => $url_saldo,
			'email_gestore' => $gestore ? $gestore->user_email : '',
		) );
		if ( is_wp_error( $result ) ) return $result;
		$sheet_id = sanitize_text_field( (string) ( $result['id_foglio'] ?? '' ) );
		$sheet_url = esc_url_raw( (string) ( $result['url_foglio'] ?? '' ) );
		if ( ! preg_match( '/^[A-Za-z0-9_-]{20,}$/', $sheet_id ) || 0 !== strpos( $sheet_url, 'https://docs.google.com/spreadsheets/' ) ) return new WP_Error( 'mi_foglio_non_valido', 'Workspace non ha restituito un collegamento al foglio valido.' );
		update_post_meta( $event_id, '_mi_operational_sheet_id', $sheet_id );
		update_post_meta( $event_id, '_mi_operational_sheet_url', $sheet_url );
		update_post_meta( $event_id, '_mi_registration_url', esc_url_raw( $url_iscrizione ) );
		if ( $url_saldo ) update_post_meta( $event_id, '_mi_balance_url', esc_url_raw( $url_saldo ) );
		else delete_post_meta( $event_id, '_mi_balance_url' );
		update_post_meta( $event_id, '_mi_outputs_prepared_at', current_time( 'mysql', true ) );
		delete_post_meta( $event_id, '_mi_sheet_missing' );
		if ( $gestore ) update_post_meta( $event_id, '_mi_manager_user_id', $gestore->ID );
		else delete_post_meta( $event_id, '_mi_manager_user_id' );
		return $result;
	}

	private static function initial_operational_profile( $event_id ) {
		$stored = MI_Field_Schema::sanitize_operational_profile( get_post_meta( $event_id, '_mi_operational_profile', true ) );
		if ( 'AUTOMATICO' !== $stored ) return $stored;
		$fields = array_map( 'sanitize_key', (array) get_post_meta( $event_id, '_mi_participant_fields', true ) );
		$document_fields = array( 'birth_date', 'document_type', 'document_number', 'document_issue_date', 'document_expiry_date', 'nationality' );
		if ( array_intersect( $fields, $document_fields ) || '1' === get_post_meta( $event_id, '_mi_overnight_enabled', true ) ) return 'VIAGGIO_COMPLESSO';
		$pricing = strtoupper( (string) get_post_meta( $event_id, '_mi_pricing_mode', true ) );
		$options = (array) get_post_meta( $event_id, '_mi_options', true );
		if ( 'NONE' === $pricing || $options ) return 'SERVIZI_MULTIPLI';
		if ( 'FIXED' === $pricing ) return 'QUOTA_UNICA';
		return 'MINIMO';
	}

	/** Individua un solo gestore responsabile, senza ampliare implicitamente l'accesso ai dati. */
	private static function risolvi_gestore_evento( $event_id, $obbligatorio ) {
		$event_id = absint( $event_id );
		$group_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		$stored_id = absint( get_post_meta( $event_id, '_mi_manager_user_id', true ) );
		$candidates = array();
		if ( $stored_id ) $candidates[] = $stored_id;
		$author_id = absint( get_post_field( 'post_author', $event_id ) );
		if ( $author_id ) $candidates[] = $author_id;
		$users = get_users( array( 'role__in' => array( 'mi_event_manager', 'mi_event_operator' ), 'fields' => array( 'ID', 'user_email', 'roles' ) ) );
		foreach ( $users as $user ) {
			if ( ! MI_Access::is_suspended( $user->ID ) && MI_Access::can_access_activity( $group_id, $user->ID ) ) $candidates[] = $user->ID;
		}
		$candidates = array_values( array_unique( array_filter( array_map( 'absint', $candidates ) ) ) );
		$valid = array();
		foreach ( $candidates as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( ! $user || ! is_email( $user->user_email ) || MI_Access::is_suspended( $user_id ) || ! MI_Access::can_access_activity( $group_id, $user_id ) ) continue;
			if ( ! array_intersect( array( 'mi_event_manager', 'mi_event_operator' ), (array) $user->roles ) ) continue;
			$valid[ $user_id ] = $user;
		}
		if ( $stored_id && isset( $valid[ $stored_id ] ) ) return $valid[ $stored_id ];
		if ( $author_id && isset( $valid[ $author_id ] ) ) return $valid[ $author_id ];
		if ( 1 === count( $valid ) ) return reset( $valid );
		if ( ! $obbligatorio ) return null;
		return new WP_Error( 'mi_gestore_evento_ambiguo', $valid ? 'Al gruppo risultano assegnati più gestori. Indica un solo responsabile prima di pubblicare.' : 'Assegna al gruppo un gestore con un indirizzo email valido prima di pubblicare.' );
	}

	private static function handle_event_publication_action() {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'mi_publish_events' ) && ! current_user_can( 'manage_options' ) ) ) wp_die( 'Non disponi del permesso per pubblicare eventi.', 403 );
		$event_id = absint( $_POST['event_id'] ?? 0 );
		check_admin_referer( 'mi_portal_publish_event_' . $event_id, 'mi_portal_nonce' );
		$event = get_post( $event_id );
		if ( ! $event || MI_Event_Post_Type::EVENT_TYPE !== $event->post_type || 'draft' !== $event->post_status || ! MI_Access::can_access_event( $event_id ) ) return self::redirect_result( 'La bozza non è pubblicabile.', true, $event_id, true );
		$configuration = MI_Registration_Service::public_event( $event_id, true );
		if ( is_wp_error( $configuration ) ) return self::redirect_result( 'Completa la configurazione prima di pubblicare: ' . $configuration->get_error_message(), true, $event_id, true );
		$preparazione = self::prepara_produzioni_workspace( $event_id, 'BOZZA' );
		if ( is_wp_error( $preparazione ) ) return self::redirect_result( 'L’evento resta in bozza perché Workspace non ha creato il foglio Google: ' . $preparazione->get_error_message(), true, $event_id, true );
		$result = wp_update_post( array( 'ID' => $event_id, 'post_status' => 'publish' ), true );
		if ( is_wp_error( $result ) || 'publish' !== get_post_status( $event_id ) ) return self::redirect_result( 'WordPress non ha potuto pubblicare l’evento.', true, $event_id, true );
		MI_Registration_Service::ensure_published_revision( $event_id, true );
		$workspace = self::prepara_produzioni_workspace( $event_id, 'PUBBLICATO' );
		if ( is_wp_error( $workspace ) ) return self::redirect_result( 'Evento pubblicato; Workspace non ha ancora registrato lo stato aggiornato: ' . $workspace->get_error_message(), true, $event_id, true );
		$gestore = self::risolvi_gestore_evento( $event_id, false );
		if ( ! $gestore ) {
			$email_segreteria = sanitize_email( get_option( 'mi_email_segreteria_eventi', '' ) );
			if ( ! is_email( $email_segreteria ) ) return self::redirect_result( 'Evento pubblicato e foglio Google collegato. Configura l’email della segreteria in Collegamento Workspace per ricevere la comunicazione in assenza di un operatore.', false, $event_id, true );
			$notifica = MI_Spedizione_Email::accoda_notifica_gestore_evento( $event_id, null, (string) ( $workspace['url_foglio'] ?? '' ), $email_segreteria );
			if ( is_wp_error( $notifica ) ) return self::redirect_result( 'Evento pubblicato; comunicazione alla segreteria non preparata: ' . $notifica->get_error_message(), true, $event_id, true );
			return self::redirect_result( 'Evento pubblicato e foglio Google collegato. Comunicazione alla segreteria preparata in modalità ' . esc_html( $notifica['mode'] ?? 'ANTEPRIMA' ) . '.', false, $event_id, true );
		}
		$notifica = MI_Spedizione_Email::accoda_notifica_gestore_evento( $event_id, $gestore, (string) ( $workspace['url_foglio'] ?? '' ) );
		if ( is_wp_error( $notifica ) ) return self::redirect_result( 'Evento pubblicato e foglio condiviso; non è stato possibile preparare la comunicazione al gestore: ' . $notifica->get_error_message(), true, $event_id, true );
		return self::redirect_result( 'Evento pubblicato. Foglio Google condiviso con il gestore e comunicazione preparata in modalità ' . esc_html( $notifica['mode'] ?? 'ANTEPRIMA' ) . '.', false, $event_id, true );
	}

	private static function handle_communication_action() {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'mi_portal_access' ) && ! current_user_can( 'manage_options' ) ) ) wp_die( 'Accesso non consentito.', 403 );
		check_admin_referer( 'mi_portal_prepare_communication', 'mi_portal_nonce' );
		$event_id = absint( $_POST['event_id'] ?? 0 );
		if ( ! $event_id || ! MI_Access::can_access_event( $event_id ) ) wp_die( 'Evento non accessibile.', 403 );
		$template = strtoupper( sanitize_key( wp_unslash( $_POST['template_type'] ?? '' ) ) );
		$allowed_templates = array_merge( array( 'PRE_DEPARTURE_REMINDER', 'BALANCE_REMINDER' ), array_keys( self::custom_communication_types() ) );
		if ( ! in_array( $template, $allowed_templates, true ) ) return self::redirect_portal_result( 'Tipo di comunicazione non valido.', true, 'communications' );
		$message = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ), 0, 4000 );
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT order_code,total_cents,balance_cents FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d AND status IN ('CONFIRMED','PENDING_PAYMENT') AND capacity_released_at IS NULL ORDER BY id LIMIT 1000", $event_id ), ARRAY_A );
		$recipients = array();
		foreach ( $rows as $row ) $recipients[] = array( 'order_code' => $row['order_code'], 'paid_cents' => max( 0, (int) $row['total_cents'] - (int) $row['balance_cents'] ), 'balance_cents' => max( 0, (int) $row['balance_cents'] ) );
		$result = MI_Spedizione_Email::accoda_comunicazione_operativa( array(
			'communication_id' => 'portal-' . $event_id . '-' . get_current_user_id() . '-' . time() . '-' . wp_generate_password( 6, false, false ),
			'event_id' => $event_id, 'template_type' => $template, 'message' => $message,
			'allow_operational' => false, 'recipients' => $recipients,
		) );
		if ( is_wp_error( $result ) ) return self::redirect_portal_result( $result->get_error_message(), true, 'communications' );
		return self::redirect_portal_result( (string) ( $result['message'] ?? 'Comunicazione preparata.' ) . ' Destinatari: ' . absint( $result['count'] ?? 0 ) . '. Modalità ANTEPRIMA.', false, 'communications' );
	}

	private static function handle_communication_type_action( $action ) {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'mi_manage_all_events' ) && ! current_user_can( 'manage_options' ) ) ) wp_die( 'Solo il segretario generale può modificare i tipi di comunicazione.', 403 );
		check_admin_referer( 'mi_portal_manage_communication_types', 'mi_portal_nonce' );
		$types = self::custom_communication_types();
		if ( 'add_communication_type' === $action ) {
			$label = mb_substr( sanitize_text_field( wp_unslash( $_POST['communication_type_label'] ?? '' ) ), 0, 80 );
			if ( ! $label ) return self::redirect_portal_result( 'Indica il nome del nuovo tipo di comunicazione.', true, 'communications' );
			$base = strtoupper( str_replace( '-', '_', sanitize_title( $label ) ) );
			$base = preg_replace( '/[^A-Z0-9_]/', '', $base );
			$code = 'CUSTOM_' . substr( $base ?: 'MESSAGGIO', 0, 24 );
			if ( isset( $types[ $code ] ) ) return self::redirect_portal_result( 'Esiste già un tipo di comunicazione con questo nome.', true, 'communications' );
			$types[ $code ] = $label;
			update_option( self::CUSTOM_COMMUNICATION_TYPES_OPTION, $types, false );
			return self::redirect_portal_result( 'Nuovo tipo di comunicazione salvato.', false, 'communications' );
		}
		$code = strtoupper( sanitize_key( wp_unslash( $_POST['communication_type_code'] ?? '' ) ) );
		if ( ! isset( $types[ $code ] ) ) return self::redirect_portal_result( 'Il tipo personalizzato non esiste.', true, 'communications' );
		unset( $types[ $code ] );
		update_option( self::CUSTOM_COMMUNICATION_TYPES_OPTION, $types, false );
		return self::redirect_portal_result( 'Tipo di comunicazione eliminato. Le comunicazioni storiche restano conservate.', false, 'communications' );
	}

	private static function custom_communication_types() {
		$stored = get_option( self::CUSTOM_COMMUNICATION_TYPES_OPTION, array() );
		$types = array();
		foreach ( is_array( $stored ) ? $stored : array() as $code => $label ) {
			$code = strtoupper( sanitize_key( (string) $code ) );
			$label = mb_substr( sanitize_text_field( (string) $label ), 0, 80 );
			if ( preg_match( '/^CUSTOM_[A-Z0-9_]{1,24}$/', $code ) && $label ) $types[ $code ] = $label;
		}
		return $types;
	}

	private static function operator_roles() {
		return array(
			'mi_secretary'      => 'Segretario iscrizioni — tutti gli eventi',
			'mi_event_manager'  => 'Gestore iscrizioni — gestisce i gruppi assegnati',
			'mi_event_operator' => 'Operatore di gruppo — consulta i gruppi assegnati',
		);
	}

	private static function limit_text_lines( $value, $maximum_lines, $maximum_characters ) {
		$lines = preg_split( '/\R/u', (string) $value );
		$lines = array_slice( is_array( $lines ) ? $lines : array(), 0, max( 1, (int) $maximum_lines ) );
		return mb_substr( implode( "\n", $lines ), 0, max( 1, (int) $maximum_characters ) );
	}

	private static function handle_operator_action( $action ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) wp_die( 'Solo un amministratore può gestire gli operatori.', 403 );
		check_admin_referer( 'mi_portal_manage_operators', 'mi_portal_nonce' );
		$roles = self::operator_roles();
		$role = sanitize_key( wp_unslash( $_POST['operator_role'] ?? '' ) );
		if ( ! isset( $roles[ $role ] ) ) return self::redirect_portal_result( 'Ruolo non valido.', true, 'operators' );
		$password = (string) wp_unslash( $_POST['operator_password'] ?? '' );
		$scope = isset( $_POST['operator_groups'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['operator_groups'] ) ) ) ) ) : array();
		if ( 'mi_secretary' === $role ) $scope = array();
		if ( 'create_operator' === $action ) {
			$username = sanitize_user( wp_unslash( $_POST['operator_username'] ?? '' ), true );
			$email = sanitize_email( wp_unslash( $_POST['operator_email'] ?? '' ) );
			$name = mb_substr( sanitize_text_field( wp_unslash( $_POST['operator_name'] ?? '' ) ), 0, 120 );
			if ( ! $username || ! is_email( $email ) ) return self::redirect_portal_result( 'Indica un codice utente e un indirizzo email validi.', true, 'operators' );
			if ( strlen( $password ) < 12 ) return self::redirect_portal_result( 'La parola d’accesso deve contenere almeno 12 caratteri.', true, 'operators' );
			$user_id = wp_insert_user( array( 'user_login' => $username, 'user_email' => $email, 'display_name' => $name ?: $username, 'user_pass' => $password, 'role' => $role ) );
			if ( is_wp_error( $user_id ) ) return self::redirect_portal_result( $user_id->get_error_message(), true, 'operators' );
			update_user_meta( $user_id, '_mi_activity_scope', $scope );
			delete_user_meta( $user_id, '_mi_access_suspended' );
			return self::redirect_portal_result( 'Operatore creato. La parola d’accesso non viene mostrata né inviata per email.', false, 'operators' );
		}
		$user_id = absint( $_POST['operator_id'] ?? 0 );
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || $user_id === get_current_user_id() || ! array_intersect( array_keys( $roles ), (array) $user->roles ) ) return self::redirect_portal_result( 'Operatore non modificabile.', true, 'operators' );
		$update = array( 'ID' => $user_id, 'role' => $role );
		$email = sanitize_email( wp_unslash( $_POST['operator_email'] ?? '' ) );
		$name = mb_substr( sanitize_text_field( wp_unslash( $_POST['operator_name'] ?? '' ) ), 0, 120 );
		if ( ! is_email( $email ) ) return self::redirect_portal_result( 'Indirizzo email non valido.', true, 'operators' );
		$update['user_email'] = $email;
		$update['display_name'] = $name ?: $user->user_login;
		if ( '' !== $password ) {
			if ( strlen( $password ) < 12 ) return self::redirect_portal_result( 'La nuova parola d’accesso deve contenere almeno 12 caratteri.', true, 'operators' );
			$update['user_pass'] = $password;
		}
		$result = wp_update_user( $update );
		if ( is_wp_error( $result ) ) return self::redirect_portal_result( $result->get_error_message(), true, 'operators' );
		update_user_meta( $user_id, '_mi_activity_scope', $scope );
		if ( ! empty( $_POST['operator_suspended'] ) ) update_user_meta( $user_id, '_mi_access_suspended', '1' ); else delete_user_meta( $user_id, '_mi_access_suspended' );
		return self::redirect_portal_result( 'Operatore aggiornato.', false, 'operators' );
	}

	private static function redirect_portal_result( $message, $error, $view ) {
		wp_safe_redirect( add_query_arg( array( 'mi_portal' => '1', 'mi_portal_view' => sanitize_key( $view ), 'mi_portal_message' => $message, 'mi_portal_error' => $error ? '1' : '0' ), self::url() ) );
		exit;
	}

	private static function handle_event_management_action( $action ) {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'mi_portal_access' ) && ! current_user_can( 'manage_options' ) ) ) wp_die( 'Accesso non consentito.', 403 );
		$event_id = absint( $_POST['event_id'] ?? 0 );
		if ( ! $event_id || ! MI_Access::can_access_event( $event_id ) ) wp_die( 'Evento non accessibile.', 403 );
		check_admin_referer( 'mi_portal_manage_event_' . $event_id, 'mi_portal_nonce' );
		if ( 'archive_event' === $action ) {
			if ( ! get_post_meta( $event_id, '_mi_event_cancelled_at', true ) ) return self::redirect_result( 'Soltanto un evento annullato può essere archiviato.', true, $event_id );
			update_post_meta( $event_id, '_mi_event_archived_at', current_time( 'mysql', true ) );
			return self::redirect_result( 'Evento annullato spostato nello storico.', false );
		}
		if ( 'trash_event' === $action ) {
			$event = get_post( $event_id );
			if ( ! $event || 'draft' !== $event->post_status ) return self::redirect_result( 'Soltanto una bozza può essere spostata nel cestino.', true, $event_id );
			global $wpdb;
			$registration_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d", $event_id ) );
			if ( $registration_count > 0 ) return self::redirect_result( 'La bozza possiede iscrizioni e non può essere eliminata. Puoi conservarla nello storico.', true, $event_id );
			if ( ! wp_trash_post( $event_id ) ) return self::redirect_result( 'Non è stato possibile spostare la bozza nel cestino.', true, $event_id );
			return self::redirect_result( 'Bozza spostata nel cestino. Potrà essere ripristinata per 30 giorni, poi sarà eliminata definitivamente.', false );
		}
		if ( 'update_event' === $action ) {
			$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
			if ( ! $title ) return self::redirect_result( 'Il titolo è obbligatorio.', true, $event_id );
			$starts_at = sanitize_text_field( wp_unslash( $_POST['starts_at'] ?? '' ) );
			$closes_at = sanitize_text_field( wp_unslash( $_POST['closes_at'] ?? '' ) );
			$start_date = $starts_at ? DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $starts_at, wp_timezone() ) : null;
			$close_date = $closes_at ? DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $closes_at, wp_timezone() ) : null;
			if ( ( $starts_at && ! $start_date ) || ( $closes_at && ! $close_date ) || ( $start_date && $close_date && $close_date > $start_date ) ) return self::redirect_result( 'Controlla le date: la chiusura delle iscrizioni non può seguire l’inizio dell’evento.', true, $event_id );
			$now_minute = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', current_time( 'Y-m-d\TH:i' ), wp_timezone() );
			if ( $close_date && $now_minute && $close_date < $now_minute ) return self::redirect_result( 'La chiusura delle iscrizioni non può essere precedente a questo momento.', true, $event_id );
			wp_update_post( array( 'ID' => $event_id, 'post_title' => $title ) );
			update_post_meta( $event_id, '_mi_event_location', mb_substr( sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ), 0, 180 ) );
			update_post_meta( $event_id, '_mi_capacity', min( 10000, max( 1, absint( $_POST['capacity'] ?? 1 ) ) ) );
			self::save_date( $event_id, '_mi_event_starts_at', $starts_at );
			self::save_date( $event_id, '_mi_registration_closes_at', $closes_at );
			return self::redirect_result( 'Dettagli dell’evento aggiornati.', false, $event_id );
		}
		if ( empty( $_POST['confirm_cancellation'] ) ) return self::redirect_result( 'Conferma esplicitamente l’annullamento dell’evento.', true, $event_id );
		$reason = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['cancellation_reason'] ?? '' ) ), 0, 2000 );
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,order_code,status,total_cents,balance_cents FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d AND status IN ('CONFIRMED','PENDING_PAYMENT','WAITLISTED') AND capacity_released_at IS NULL ORDER BY id", $event_id ), ARRAY_A );
		$recipients = array();
		foreach ( $rows as $row ) $recipients[] = array( 'order_code' => $row['order_code'], 'paid_cents' => max( 0, (int) $row['total_cents'] - (int) $row['balance_cents'] ), 'balance_cents' => max( 0, (int) $row['balance_cents'] ) );
		$email_result = $recipients ? MI_Spedizione_Email::accoda_comunicazione_operativa( array( 'communication_id' => 'event-cancel-' . $event_id . '-' . time(), 'event_id' => $event_id, 'template_type' => 'EVENT_CANCELLATION', 'message' => $reason, 'allow_operational' => true, 'recipients' => $recipients ) ) : array( 'count' => 0, 'mode' => MI_Spedizione_Email::modalita() );
		if ( is_wp_error( $email_result ) ) return self::redirect_result( 'Impossibile preparare gli avvisi: evento non annullato.', true, $event_id );
		foreach ( $rows as $row ) {
			$result = MI_Registration_Service::cancel_registration( (int) $row['id'], wp_get_current_user()->display_name );
			if ( is_wp_error( $result ) ) return self::redirect_result( 'Annullamento incompleto: controlla le iscrizioni prima di riprovare.', true, $event_id );
		}
		update_post_meta( $event_id, '_mi_event_cancelled_at', current_time( 'mysql', true ) );
		update_post_meta( $event_id, '_mi_event_cancellation_reason', $reason );
		wp_update_post( array( 'ID' => $event_id, 'post_status' => 'draft' ) );
		$count = (int) ( $email_result['count'] ?? 0 );
		$mode = sanitize_text_field( (string) ( $email_result['mode'] ?? 'ANTEPRIMA' ) );
		return self::redirect_result( 'Evento annullato. Avvisi preparati: ' . $count . ' (modalità ' . $mode . ').', false );
	}

	public static function url() {
		static $resolved_url = null;
		if ( null !== $resolved_url ) return $resolved_url;
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 's' => '[' . self::SHORTCODE ) );
		foreach ( $pages as $page ) if ( has_shortcode( $page->post_content, self::SHORTCODE ) ) return $resolved_url = get_permalink( $page->ID );
		return $resolved_url = add_query_arg( 'mi_portal', '1', home_url( '/' ) );
	}

	public static function participant_cancel_url( $participant_id, $token ) {
		return add_query_arg( array( 'mi_cancel_participant' => absint( $participant_id ), 'mi_cancel_token' => (string) $token ), self::url() );
	}

	public static function status_url( $registration_id, $order_code, $email ) {
		return add_query_arg( array( 'mi_status' => '1', 'ordine' => sanitize_text_field( (string) $order_code ), 'token' => MI_Registration_Service::public_status_token( $registration_id, $order_code, $email ) ), home_url( '/' ) );
	}

	public static function render_virtual_page() {
		if ( empty( $_GET['mi_portal'] ) && empty( $_GET['mi_status'] ) ) return;
		status_header( 200 );
		nocache_headers();
		$is_detail_request = ! empty( $_GET['mi_portal_booking'] ) && 'xmlhttprequest' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' ) ) );
		if ( $is_detail_request ) {
			if ( ! is_user_logged_in() || ( ! current_user_can( 'mi_portal_access' ) && ! current_user_can( 'manage_options' ) ) ) wp_die( 'Accesso non consentito.', 403 );
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			self::booking_detail( absint( $_GET['mi_portal_booking'] ) );
			exit;
		}
		$asset_version = rawurlencode( MI_VERSION );
		$page_title = ! empty( $_GET['mi_status'] ) ? 'Stato della prenotazione' : 'Segreteria eventi';
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><title><?php echo esc_html( get_bloginfo( 'name' ) . ' — ' . $page_title ); ?></title><link rel="stylesheet" href="<?php echo esc_url( MI_PLUGIN_URL . 'assets/portal.css?ver=' . $asset_version ); ?>"></head><body class="mi-portal-standalone"><?php echo self::render(); ?><script defer src="<?php echo esc_url( MI_PLUGIN_URL . 'assets/portal.js?ver=' . $asset_version ); ?>"></script></body></html><?php
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

	private static function valid_portal_date( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value );
	}

	private static function normalize_portal_date( $value ) {
		$value = trim( (string) $value );
		if ( self::valid_portal_date( $value ) ) return $value;
		if ( ! preg_match( '/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})$/', $value, $parts ) ) return '';
		$day = (int) $parts[1]; $month = (int) $parts[2]; $year = (int) $parts[3]; $hour = (int) $parts[4]; $minute = (int) $parts[5];
		if ( ! checkdate( $month, $day, $year ) || $hour > 23 || $minute > 59 ) return '';
		return sprintf( '%04d-%02d-%02dT%02d:%02d', $year, $month, $day, $hour, $minute );
	}

	private static function portal_date_input_value( $value ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', (string) $value, wp_timezone() );
		return $date instanceof DateTimeImmutable ? $date->format( 'd/m/Y H:i' ) : '';
	}

	private static function save_cover_upload( $event_id ) {
		if ( empty( $_FILES['cover_image']['name'] ) ) return '';
		$file = $_FILES['cover_image'];
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) return 'caricamento incompleto';
		if ( (int) $file['size'] > 2 * MB_IN_BYTES ) return 'file superiore a 2 MB';
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ) );
		if ( ! in_array( $checked['type'] ?? '', array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) return 'formato non ammesso';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$attachment_id = media_handle_upload( 'cover_image', $event_id, array(), array( 'test_form' => false ) );
		if ( is_wp_error( $attachment_id ) ) return 'WordPress non ha potuto salvare il file';
		set_post_thumbnail( $event_id, $attachment_id );
		return '';
	}

	/** Salva un'immagine del gruppo controllando formato e limite di 2 MB. */
	private static function save_group_image_upload( $field_name, $group_id ) {
		if ( empty( $_FILES[ $field_name ]['name'] ) ) return 0;
		$file = $_FILES[ $field_name ];
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) return new WP_Error( 'mi_group_upload', 'Caricamento immagine incompleto.' );
		if ( (int) $file['size'] > 2 * MB_IN_BYTES ) return new WP_Error( 'mi_group_upload_size', 'L’immagine non può superare 2 MB.' );
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ) );
		if ( ! in_array( $checked['type'] ?? '', array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) return new WP_Error( 'mi_group_upload_type', 'Sono ammesse soltanto immagini JPG, PNG o WebP.' );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$attachment_id = media_handle_upload( $field_name, $group_id, array(), array( 'test_form' => false ) );
		return is_wp_error( $attachment_id ) ? new WP_Error( 'mi_group_upload_failed', 'WordPress non ha potuto salvare l’immagine.' ) : absint( $attachment_id );
	}

	private static function can_manage_groups() {
		return current_user_can( 'manage_options' ) || current_user_can( 'mi_manage_all_events' );
	}

	private static function redirect_group_result( $message, $error = false, $group_id = 0 ) {
		$args = array(
			'mi_portal'         => '1',
			'mi_portal_view'    => 'groups',
			'mi_portal_message' => $message,
			'mi_portal_error'   => $error ? '1' : '0',
		);
		if ( $group_id ) $args['mi_portal_group'] = absint( $group_id );
		wp_safe_redirect( add_query_arg( $args, self::url() ) );
		exit;
	}

	private static function handle_group_action( $action ) {
		if ( ! is_user_logged_in() || ! self::can_manage_groups() ) wp_die( 'Solo un amministratore o un segretario può gestire i gruppi.', 403 );
		check_admin_referer( 'mi_portal_manage_groups', 'mi_portal_nonce' );
		$group_id = absint( $_POST['group_id'] ?? 0 );
		if ( 'create_group' === $action ) {
			$title = mb_substr( sanitize_text_field( wp_unslash( $_POST['group_title'] ?? '' ) ), 0, 120 );
			if ( ! $title ) return self::redirect_group_result( 'Indica il nome del gruppo.', true );
			if ( get_page_by_path( sanitize_title( $title ), OBJECT, MI_Event_Post_Type::GROUP_TYPE ) ) return self::redirect_group_result( 'Esiste già un gruppo con questo nome: aprilo e modificalo.', true );
			$group_id = wp_insert_post( array( 'post_type' => MI_Event_Post_Type::GROUP_TYPE, 'post_status' => 'publish', 'post_title' => $title, 'post_author' => get_current_user_id() ), true );
			if ( is_wp_error( $group_id ) ) return self::redirect_group_result( 'Non è stato possibile creare il gruppo.', true );
		} else {
			$group = get_post( $group_id );
			if ( ! $group || MI_Event_Post_Type::GROUP_TYPE !== $group->post_type ) wp_die( 'Gruppo non valido.', 404 );
		}
		if ( 'delete_group' === $action ) {
			$linked_events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private', 'mi_archived' ), 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_mi_activity_id', 'meta_value' => $group_id ) );
			if ( $linked_events ) return self::redirect_group_result( 'Il gruppo non può essere eliminato perché è assegnato ad almeno un evento.', true, $group_id );
			wp_trash_post( $group_id );
			return self::redirect_group_result( 'Gruppo spostato nel cestino.' );
		}
		$title = mb_substr( sanitize_text_field( wp_unslash( $_POST['group_title'] ?? '' ) ), 0, 120 );
		if ( ! $title ) return self::redirect_group_result( 'Indica il nome del gruppo.', true, $group_id );
		if ( 'update_group' === $action ) {
			$updated = wp_update_post( array( 'ID' => $group_id, 'post_title' => $title ), true );
			if ( is_wp_error( $updated ) ) return self::redirect_group_result( 'Non è stato possibile aggiornare il gruppo.', true, $group_id );
		}
		$primary = sanitize_hex_color( wp_unslash( $_POST['group_primary_color'] ?? '' ) ) ?: '#151b38';
		$secondary = sanitize_hex_color( wp_unslash( $_POST['group_secondary_color'] ?? '' ) ) ?: '#337ab7';
		update_post_meta( $group_id, '_mi_primary_color', $primary );
		update_post_meta( $group_id, '_mi_secondary_color', $secondary );
		update_post_meta( $group_id, '_mi_accent_color', $primary );
		if ( ! empty( $_POST['remove_group_logo'] ) ) delete_post_thumbnail( $group_id );
		if ( ! empty( $_POST['remove_group_cover'] ) ) delete_post_meta( $group_id, '_mi_group_cover_image_id' );
		$logo_id = self::save_group_image_upload( 'group_logo', $group_id );
		if ( is_wp_error( $logo_id ) ) return self::redirect_group_result( $logo_id->get_error_message(), true, $group_id );
		if ( $logo_id ) set_post_thumbnail( $group_id, $logo_id );
		$cover_id = self::save_group_image_upload( 'group_cover', $group_id );
		if ( is_wp_error( $cover_id ) ) return self::redirect_group_result( $cover_id->get_error_message(), true, $group_id );
		if ( $cover_id ) update_post_meta( $group_id, '_mi_group_cover_image_id', $cover_id );
		$dependent_events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_mi_activity_id', 'meta_value' => $group_id ) );
		foreach ( $dependent_events as $event_id ) update_post_meta( $event_id, '_mi_needs_republish', '1' );
		return self::redirect_group_result( 'create_group' === $action ? 'Gruppo creato. Ora è disponibile nella creazione degli eventi.' : 'Gruppo aggiornato.', false, $group_id );
	}

	private static function parse_euro_cents( $value ) {
		$value = preg_replace( '/[^0-9,.]/', '', (string) $value );
		if ( '' === $value ) return 0;
		if ( false !== strpos( $value, ',' ) ) $value = str_replace( '.', '', $value );
		$value = str_replace( ',', '.', $value );
		if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/', $value ) ) return new WP_Error( 'invalid_amount' );
		return (int) round( (float) $value * 100 );
	}

	private static function copy_configuration( $source, $target ) {
		$keys = array( '_mi_activity_id', '_mi_capacity', '_mi_waitlist_enabled', '_mi_pricing_mode', '_mi_fixed_price_cents', '_mi_economic_mode', '_mi_deposit_mode', '_mi_deposit_percentage', '_mi_deposit_fixed_cents', '_mi_payment_methods', '_mi_operational_profile', '_mi_data_profile', '_mi_participant_fields', '_mi_participant_required_fields', '_mi_custom_participant_fields', '_mi_participant_extra_scope', '_mi_ticket_types', '_mi_options', '_mi_marketing_enabled', '_mi_special_requests_enabled', '_mi_overnight_enabled', '_mi_accommodations' );
		foreach ( $keys as $key ) {
			$value = get_post_meta( $source, $key, true );
			if ( '' !== $value ) update_post_meta( $target, $key, $value );
		}
	}

	private static function redirect_result( $message, $error = false, $event_id = 0, $show_outputs = false ) {
		$url = self::url();
		$args = array( 'mi_portal' => '1', 'mi_portal_message' => $message, 'mi_portal_error' => $error ? '1' : '0', 'mi_portal_view' => 'manage' );
		if ( $event_id ) {
			$args['mi_portal_event'] = $event_id;
			// Mantieni l'esito vicino alla scheda e al pulsante anche in caso di errore.
			if ( $show_outputs ) $args['mi_portal_outputs'] = '1';
		}
		$destination = add_query_arg( $args, $url );
		if ( $event_id && ! $error && $show_outputs ) $destination .= '#mi-produzioni-evento';
		wp_safe_redirect( $destination );
		exit;
	}

	public static function render() {
		if ( ! is_ssl() ) return '<div class="mi-portal-notice mi-portal-error"><strong>Connessione sicura necessaria.</strong><p>Il portale non può essere usato finché HTTPS non è configurato correttamente.</p></div>';
		if ( ! empty( $_GET['mi_status'] ) ) return self::public_status_view();
		if ( ! empty( $_GET['mi_cancel_participant'] ) && ! empty( $_GET['mi_cancel_token'] ) ) return self::cancellation_view();
		if ( ! is_user_logged_in() ) return self::login_view();
		if ( MI_Access::is_suspended() ) return '<div class="mi-portal-empty"><h2>Accesso sospeso</h2><p>Questo account non può accedere alla Segreteria eventi. Contatta un amministratore.</p></div>';
		if ( ! current_user_can( 'mi_portal_access' ) && ! current_user_can( 'manage_options' ) ) return '<div class="mi-portal-empty"><h2>C’è qualcuno qui…?</h2><p>Il tuo account non è abilitato al servizio iscrizioni.</p></div>';
		$view = sanitize_key( wp_unslash( $_GET['mi_portal_view'] ?? 'manage' ) );
		$can_create = current_user_can( 'mi_create_events' ) || current_user_can( 'manage_options' );
		ob_start();
		?><main class="mi-portal"><header class="mi-portal-header"><div><span class="mi-portal-eyebrow">Area riservata</span><h1>Segreteria eventi</h1></div><a class="mi-portal-logout" href="<?php echo esc_url( wp_logout_url( self::base_url() ) ); ?>"><span aria-hidden="true">↗</span> Esci</a></header>
		<nav class="mi-portal-switcher" aria-label="Segreteria eventi"><a class="<?php echo 'manage' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'manage', self::base_url() ) ); ?>"><?php echo esc_html( self::manage_label() ); ?></a><?php if ( $can_create ) : ?><a class="<?php echo 'create' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'create', self::base_url() ) ); ?>">Crea evento</a><?php endif; ?><a class="<?php echo 'registrations' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'registrations', self::base_url() ) ); ?>">Iscrizioni</a><a class="<?php echo 'communications' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'communications', self::base_url() ) ); ?>">Comunicazioni</a><?php if ( self::can_manage_groups() ) : ?><a class="<?php echo 'groups' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'groups', self::base_url() ) ); ?>">Gruppi</a><?php endif; ?><?php if ( current_user_can( 'manage_options' ) ) : ?><a class="<?php echo 'operators' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'operators', self::base_url() ) ); ?>">Operatori</a><?php endif; ?></nav>
		<?php $notice_near_outputs = 'manage' === $view && ! empty( $_GET['mi_portal_event'] ) && ! empty( $_GET['mi_portal_outputs'] ); if ( ! $notice_near_outputs ) self::notice(); ?>
		<?php if ( 'create' === $view && $can_create ) self::create_view( absint( $_GET['mi_portal_edit'] ?? $_GET['mi_portal_draft'] ?? 0 ) ); elseif ( 'registrations' === $view ) self::portal_registrations_view(); elseif ( 'communications' === $view ) self::communications_view(); elseif ( 'groups' === $view && self::can_manage_groups() ) self::groups_view(); elseif ( 'operators' === $view && current_user_can( 'manage_options' ) ) self::operators_view(); else self::manage_view(); ?>
		</main><?php
		return ob_get_clean();
	}

	private static function public_status_view() {
		$event_id = absint( $_GET['evento'] ?? 0 );
		$order_code = sanitize_text_field( wp_unslash( $_GET['ordine'] ?? '' ) );
		$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
		$email = '';
		$result = null;
		if ( $order_code && $token ) {
			$result = MI_Registration_Service::public_status( $order_code, '', $token, $event_id );
		} elseif ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) && 'public_status_lookup' === sanitize_key( wp_unslash( $_POST['mi_portal_action'] ?? '' ) ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['mi_status_nonce'] ?? '' ) );
			$address = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
			$rate_key = 'mi_status_rate_' . substr( hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 0, 32 );
			$attempts = (int) get_transient( $rate_key );
			if ( ! wp_verify_nonce( $nonce, 'mi_public_status' ) || $attempts >= 10 ) {
				$result = new WP_Error( 'mi_status_unavailable', 'Non è stato possibile verificare la prenotazione. Riprova più tardi.' );
			} else {
				set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
				$order_code = sanitize_text_field( wp_unslash( $_POST['order_code'] ?? '' ) );
				$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
				$result = MI_Registration_Service::public_status( $order_code, $email, '', $event_id );
			}
		}
		ob_start(); ?>
		<main class="mi-portal mi-public-status"><section class="mi-portal-login"><span class="mi-portal-eyebrow">Consultazione riservata</span><h1>Stato della prenotazione</h1><p>Controlla conferma, pagamenti registrati e saldo residuo. Non vengono mostrati dati personali o note interne.</p>
		<?php if ( is_array( $result ) ) : ?><div class="mi-status-result"><p class="mi-status-event"><?php echo esc_html( $result['event_title'] ); ?></p><p class="mi-booking-detail__code">Codice <code><?php echo esc_html( $result['order_code'] ); ?></code></p><div class="mi-status-grid"><p><span>Prenotazione</span><strong><?php echo esc_html( $result['status'] ); ?></strong></p><p><span>Pagamento</span><strong><?php echo esc_html( $result['payment_status'] ); ?></strong></p><p><span>Versato</span><strong><?php echo esc_html( self::format_money( $result['paid_cents'] ) ); ?></strong></p><p><span>Saldo residuo</span><strong><?php echo esc_html( self::format_money( $result['balance_cents'] ) ); ?></strong></p></div><?php if ( ! empty( $result['payment_deadline'] ) ) : ?><p class="mi-portal-muted">Scadenza indicata: <?php echo esc_html( self::format_utc_date( $result['payment_deadline'] ) ); ?></p><?php endif; ?></div>
		<?php elseif ( is_wp_error( $result ) ) : ?><div class="mi-portal-notice mi-portal-error"><?php echo esc_html( $result->get_error_message() ); ?></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( add_query_arg( array_filter( array( 'mi_status' => '1', 'evento' => $event_id ) ), home_url( '/' ) ) ); ?>"><input type="hidden" name="mi_portal_action" value="public_status_lookup"><input type="hidden" name="mi_status_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mi_public_status' ) ); ?>"><label>Codice prenotazione<input name="order_code" value="<?php echo esc_attr( $order_code ); ?>" maxlength="32" autocomplete="off" required></label><label>Email del referente<input type="email" name="email" value="<?php echo esc_attr( $email ); ?>" autocomplete="email" required></label><button type="submit">Controlla stato e saldo</button></form></section></main><?php
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
		$errore = ! empty( $_GET['mi_errore_accesso'] );
		$attesa = ! empty( $_GET['mi_accesso_in_attesa'] );
		ob_start(); ?><section class="mi-portal mi-portal-login"><span class="mi-portal-eyebrow">Area riservata</span><h1>Segreteria eventi</h1><p>Accedi con le credenziali personali del servizio. Il segretario opera su tutto; ogni operatore vede soltanto i gruppi assegnati.</p><?php if ( $errore ) : ?><div class="mi-portal-notice mi-portal-error" role="alert"><strong>Accesso non riuscito.</strong><p><?php echo $attesa ? 'Troppi tentativi ravvicinati. Attendi quindici minuti prima di riprovare.' : 'Controlla nome utente e parola d’accesso oppure contatta un amministratore.'; ?></p></div><?php endif; ?><form class="mi-portal-login__form" method="post" action="<?php echo esc_url( self::base_url() ); ?>"><input type="hidden" name="mi_portal_action" value="accedi_portale"><?php wp_nonce_field( 'mi_accesso_portale', 'mi_portal_nonce' ); ?><label for="mi-login-utente">Nome utente per l’accesso</label><input id="mi-login-utente" name="mi_nome_utente" type="text" maxlength="60" autocomplete="username" required><label for="mi-login-password">Parola d’accesso</label><input id="mi-login-password" name="mi_parola_accesso" type="password" autocomplete="current-password" required><p class="login-remember"><label><input name="mi_ricordami" type="checkbox" value="1"> Ricordami</label></p><button class="mi-primary" type="submit">Accedi</button></form></section><?php return ob_get_clean();
	}

	/** Autentica esclusivamente nella pagina autonoma della Segreteria, senza passare da wp-login.php. */
	private static function gestisci_accesso_portale() {
		if ( empty( $_GET['mi_portal'] ) ) wp_die( 'Richiesta di accesso non valida.', 400 );
		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::base_url() );
			exit;
		}
		check_admin_referer( 'mi_accesso_portale', 'mi_portal_nonce' );
		$nome_utente = sanitize_user( wp_unslash( $_POST['mi_nome_utente'] ?? '' ), true );
		$parola_accesso = (string) wp_unslash( $_POST['mi_parola_accesso'] ?? '' );
		$ricordami = ! empty( $_POST['mi_ricordami'] );
		$chiave_limite = self::chiave_limite_accesso( $nome_utente );
		$tentativi = (int) get_transient( $chiave_limite );
		if ( $tentativi >= 5 ) return self::reindirizza_errore_accesso( true );
		if ( ! $nome_utente || '' === $parola_accesso ) return self::registra_errore_accesso( $chiave_limite, $tentativi );

		$utente = wp_signon(
			array(
				'user_login'    => $nome_utente,
				'user_password' => $parola_accesso,
				'remember'      => $ricordami,
			),
			is_ssl()
		);
		$parola_accesso = '';
		if ( is_wp_error( $utente ) ) return self::registra_errore_accesso( $chiave_limite, $tentativi );
		if ( ! user_can( $utente, 'mi_portal_access' ) && ! user_can( $utente, 'manage_options' ) ) {
			wp_clear_auth_cookie();
			return self::registra_errore_accesso( $chiave_limite, $tentativi );
		}
		delete_transient( $chiave_limite );
		wp_safe_redirect( self::base_url() );
		exit;
	}

	private static function chiave_limite_accesso( $nome_utente ) {
		$indirizzo = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$impronta = hash_hmac( 'sha256', strtolower( (string) $nome_utente ) . '|' . $indirizzo, wp_salt( 'auth' ) );
		return 'mi_accesso_' . substr( $impronta, 0, 32 );
	}

	private static function registra_errore_accesso( $chiave_limite, $tentativi ) {
		set_transient( $chiave_limite, min( 5, (int) $tentativi + 1 ), 15 * MINUTE_IN_SECONDS );
		return self::reindirizza_errore_accesso( false );
	}

	private static function reindirizza_errore_accesso( $attesa ) {
		$destinazione = add_query_arg(
			array(
				'mi_errore_accesso'    => '1',
				'mi_accesso_in_attesa' => $attesa ? '1' : false,
			),
			self::base_url()
		);
		wp_safe_redirect( $destinazione );
		exit;
	}

	private static function accessible_events() {
		$scope = MI_Access::event_ids();
		$query = array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' );
		if ( 'ALL' !== $scope ) $query['post__in'] = $scope ?: array( 0 );
		return get_posts( $query );
	}

	private static function portal_registrations_view() {
		$events = self::accessible_events();
		$event_ids = array_map( 'absint', wp_list_pluck( $events, 'ID' ) );
		if ( ! $event_ids ) { echo '<section><h2>Iscrizioni</h2><p class="mi-portal-muted">Non ci sono eventi accessibili.</p></section>'; return; }
		$selectable_events = array_values( array_filter( $events, static function ( $event ) {
			return ! get_post_meta( $event->ID, '_mi_event_cancelled_at', true );
		} ) );
		$period = sanitize_key( wp_unslash( $_GET['mi_portal_period'] ?? 'current' ) );
		if ( ! in_array( $period, array( 'current', 'past' ), true ) ) $period = 'current';
		$event_mode = sanitize_key( wp_unslash( $_GET['mi_portal_event_mode'] ?? 'all' ) );
		if ( ! in_array( $event_mode, array( 'all', 'single' ), true ) ) $event_mode = 'all';
		$period_events = array_values( array_filter( $selectable_events, static function ( $event ) use ( $period ) {
			$starts_at = (string) get_post_meta( $event->ID, '_mi_event_starts_at', true );
			$closes_at = (string) get_post_meta( $event->ID, '_mi_registration_closes_at', true );
			$is_past = (bool) get_post_meta( $event->ID, '_mi_event_archived_at', true ) || self::is_past_event( $closes_at ?: $starts_at );
			return 'past' === $period ? $is_past : ! $is_past;
		} ) );
		$period_event_ids = array_map( 'absint', wp_list_pluck( $period_events, 'ID' ) );
		$selected = absint( $_GET['mi_portal_event'] ?? 0 );
		if ( $selected && ! in_array( $selected, $event_ids, true ) ) wp_die( 'Evento non accessibile.', 403 );
		if ( 'single' !== $event_mode || ! in_array( $selected, $period_event_ids, true ) ) $selected = 0;
		$query = sanitize_text_field( wp_unslash( $_GET['mi_portal_query'] ?? '' ) );
		$status = strtoupper( sanitize_text_field( wp_unslash( $_GET['mi_portal_status'] ?? '' ) ) );
		$statuses = array( 'CONFIRMED' => 'Confermate', 'PENDING_PAYMENT' => 'In attesa di pagamento', 'WAITLISTED' => 'Lista d’attesa', 'CANCELLED' => 'Annullate', 'EXPIRED' => 'Scadute' );
		if ( ! isset( $statuses[ $status ] ) ) $status = '';
		echo '<section class="mi-registrations"><div class="mi-registrations__heading"><div><span class="mi-portal-eyebrow">Archivio operativo</span><h2>Iscrizioni</h2></div><p class="mi-portal-muted">Cerca una prenotazione e apri la scheda completa senza lasciare la pagina.</p></div><form class="mi-registrations-toolbar" method="get"><input type="hidden" name="mi_portal" value="1"><input type="hidden" name="mi_portal_view" value="registrations"><div class="mi-registration-search"><label class="screen-reader-text" for="mi-portal-query">Cerca nelle iscrizioni</label><input id="mi-portal-query" type="search" name="mi_portal_query" value="' . esc_attr( $query ) . '" placeholder="Nome, email, cellulare o codice prenotazione"><button class="mi-primary" type="submit">Cerca</button></div><div class="mi-registration-chips"><label>Periodo<select name="mi_portal_period" data-mi-auto-submit><option value="current" ' . selected( $period, 'current', false ) . '>Eventi in corso</option><option value="past" ' . selected( $period, 'past', false ) . '>Eventi passati</option></select></label><label>Eventi<select name="mi_portal_event_mode" data-mi-event-mode data-mi-auto-submit><option value="all" ' . selected( $event_mode, 'all', false ) . '>Tutti gli eventi</option><option value="single" ' . selected( $event_mode, 'single', false ) . '>Evento singolo</option></select></label><label data-mi-single-event ' . ( 'single' === $event_mode ? '' : 'hidden' ) . '>Evento<select name="mi_portal_event" data-mi-auto-submit><option value="">Scegli un evento</option>';
		foreach ( $period_events as $event ) echo '<option value="' . esc_attr( $event->ID ) . '" ' . selected( $selected, $event->ID, false ) . '>' . esc_html( $event->post_title ) . '</option>';
		echo '</select></label><label>Stato<select name="mi_portal_status" data-mi-auto-submit><option value="">Tutti gli stati</option>';
		foreach ( $statuses as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><button class="mi-secondary" type="submit">Applica filtri</button></div></form></section>';
		$listed_event_ids = 'single' === $event_mode ? array() : $period_event_ids;
		self::registrations_view( $selected, $listed_event_ids, $period, $event_mode );
	}

	private static function communications_view() {
		$events = self::accessible_events();
		$custom_types = self::custom_communication_types();
		echo '<section class="mi-portal-communications"><h2>Comunicazioni</h2><p class="mi-portal-notice"><strong>Modalità ANTEPRIMA.</strong> La comunicazione viene preparata nella coda WordPress, ma non viene spedita.</p>';
		if ( ! $events ) { echo '<p class="mi-portal-muted">Non ci sono eventi accessibili.</p></section>'; return; }
		echo '<form method="post"><input type="hidden" name="mi_portal_action" value="prepare_communication">';
		wp_nonce_field( 'mi_portal_prepare_communication', 'mi_portal_nonce' );
		echo '<label>Evento<select name="event_id" required><option value="">Scegli un evento</option>';
		foreach ( $events as $event ) echo '<option value="' . esc_attr( $event->ID ) . '">' . esc_html( $event->post_title ) . '</option>';
		echo '</select></label><label>Tipo<select name="template_type" required><option value="PRE_DEPARTURE_REMINDER">Informazioni prima dell’evento</option><option value="BALANCE_REMINDER">Promemoria del saldo</option>';
		foreach ( $custom_types as $code => $label ) echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $label ) . '</option>';
		echo '</select></label><label>Messaggio<textarea name="message" rows="8" maxlength="4000" placeholder="Punto di ritrovo, orario, cosa portare e altre indicazioni…"></textarea></label><button class="mi-primary" type="submit">Prepara in anteprima</button></form>';
		if ( current_user_can( 'mi_manage_all_events' ) || current_user_can( 'manage_options' ) ) {
			echo '<details class="mi-communication-types"><summary>Gestisci i tipi di messaggio</summary><div><form method="post"><input type="hidden" name="mi_portal_action" value="add_communication_type">';
			wp_nonce_field( 'mi_portal_manage_communication_types', 'mi_portal_nonce' );
			echo '<label>Nuovo tipo<input name="communication_type_label" maxlength="80" placeholder="Per esempio: Cambio del punto di ritrovo" required></label><button class="mi-secondary" type="submit">Aggiungi e salva</button></form>';
			if ( $custom_types ) {
				echo '<form class="mi-communication-type-delete" method="post" onsubmit="return confirm(\'Eliminare questo tipo di comunicazione? Le comunicazioni già preparate resteranno conservate.\')"><input type="hidden" name="mi_portal_action" value="delete_communication_type">';
				wp_nonce_field( 'mi_portal_manage_communication_types', 'mi_portal_nonce' );
				echo '<label>Tipo personalizzato<select name="communication_type_code" required>';
				foreach ( $custom_types as $code => $label ) echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $label ) . '</option>';
				echo '</select></label><button class="mi-text-danger" type="submit">Elimina</button></form>';
			}
			echo '<p class="mi-portal-muted"><small>I tipi di sistema non possono essere eliminati.</small></p></div></details>';
		}
		echo '</section>';
	}

	private static function groups_view() {
		if ( ! self::can_manage_groups() ) { wp_die( 'Solo un amministratore o un segretario può gestire i gruppi.', 403 ); }
		$groups = get_posts( array( 'post_type' => MI_Event_Post_Type::GROUP_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<section class="mi-groups"><div class="mi-groups__heading"><div><span class="mi-portal-eyebrow">Configurazione</span><h2>Gruppi</h2></div><p class="mi-portal-muted">Definisci una sola volta nome, logo, copertina e colori. I nuovi eventi del gruppo li erediteranno automaticamente.</p></div>
		<details class="mi-group-create" <?php echo $groups ? '' : 'open'; ?>><summary>Aggiungi un nuovo gruppo</summary><form method="post" enctype="multipart/form-data"><input type="hidden" name="mi_portal_action" value="create_group"><?php wp_nonce_field( 'mi_portal_manage_groups', 'mi_portal_nonce' ); ?>
		<div class="mi-group-form-grid"><label>Nome del gruppo<input name="group_title" maxlength="120" required placeholder="Es. Giovani"></label><label>Logo <small>(facoltativo)</small><input type="file" name="group_logo" accept="image/jpeg,image/png,image/webp" data-mi-max-bytes="2097152"><small>JPG, PNG o WebP, massimo 2 MB.</small></label><label>Immagine in evidenza <small>(facoltativa)</small><input type="file" name="group_cover" accept="image/jpeg,image/png,image/webp" data-mi-max-bytes="2097152"><small>Viene proposta agli eventi privi di una propria immagine.</small></label><label>Colore principale<input type="color" name="group_primary_color" value="#151b38"></label><label>Colore pulsanti<input type="color" name="group_secondary_color" value="#337ab7"></label></div><button class="mi-primary" type="submit">Crea gruppo</button></form></details>
		<div class="mi-group-list"><?php if ( ! $groups ) : ?><p class="mi-portal-empty">Non sono ancora presenti gruppi.</p><?php endif; ?><?php foreach ( $groups as $group ) : $logo = get_the_post_thumbnail_url( $group->ID, 'thumbnail' ); $cover_id = absint( get_post_meta( $group->ID, '_mi_group_cover_image_id', true ) ); $cover = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium' ) : ''; $primary = sanitize_hex_color( get_post_meta( $group->ID, '_mi_primary_color', true ) ) ?: '#151b38'; $secondary = sanitize_hex_color( get_post_meta( $group->ID, '_mi_secondary_color', true ) ) ?: '#337ab7'; $event_count = count( get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private', 'mi_archived' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_mi_activity_id', 'meta_value' => $group->ID ) ) ); ?>
		<details class="mi-group-card" <?php echo absint( $_GET['mi_portal_group'] ?? 0 ) === $group->ID ? 'open' : ''; ?>><summary><span class="mi-group-card__identity"><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt=""><?php else : ?><em aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $group->post_title, 0, 1 ) ) ); ?></em><?php endif; ?><span><strong><?php echo esc_html( $group->post_title ); ?></strong><small><?php echo esc_html( $event_count . ( 1 === $event_count ? ' evento collegato' : ' eventi collegati' ) ); ?></small></span></span><span>Configura</span></summary><form method="post" enctype="multipart/form-data"><input type="hidden" name="mi_portal_action" value="update_group"><input type="hidden" name="group_id" value="<?php echo esc_attr( $group->ID ); ?>"><?php wp_nonce_field( 'mi_portal_manage_groups', 'mi_portal_nonce' ); ?>
		<div class="mi-group-form-grid"><label>Nome del gruppo<input name="group_title" maxlength="120" value="<?php echo esc_attr( $group->post_title ); ?>" required></label><div class="mi-group-image-field"><strong>Logo</strong><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt=""><label class="mi-check"><input type="checkbox" name="remove_group_logo" value="1"> Rimuovi il logo</label><?php endif; ?><label>Sostituisci<input type="file" name="group_logo" accept="image/jpeg,image/png,image/webp" data-mi-max-bytes="2097152"></label></div><div class="mi-group-image-field"><strong>Immagine in evidenza</strong><?php if ( $cover ) : ?><img src="<?php echo esc_url( $cover ); ?>" alt=""><label class="mi-check"><input type="checkbox" name="remove_group_cover" value="1"> Rimuovi l’immagine</label><?php endif; ?><label>Sostituisci<input type="file" name="group_cover" accept="image/jpeg,image/png,image/webp" data-mi-max-bytes="2097152"></label></div><label>Colore principale<input type="color" name="group_primary_color" value="<?php echo esc_attr( $primary ); ?>"></label><label>Colore pulsanti<input type="color" name="group_secondary_color" value="<?php echo esc_attr( $secondary ); ?>"></label></div><div class="mi-group-card__actions"><button class="mi-primary" type="submit">Salva gruppo</button></div></form><?php if ( 0 === $event_count ) : ?><form method="post" class="mi-group-delete" onsubmit="return confirm('Eliminare questo gruppo? Sarà spostato nel cestino.');"><input type="hidden" name="mi_portal_action" value="delete_group"><input type="hidden" name="group_id" value="<?php echo esc_attr( $group->ID ); ?>"><?php wp_nonce_field( 'mi_portal_manage_groups', 'mi_portal_nonce' ); ?><button class="mi-danger" type="submit">Elimina gruppo</button></form><?php else : ?><p class="mi-portal-muted">Per eliminare il gruppo, assegna prima i suoi eventi a un altro gruppo.</p><?php endif; ?></details>
		<?php endforeach; ?></div></section>
		<?php
	}

	private static function operators_view() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Solo un amministratore può gestire gli operatori.', 403 ); }
		$roles = self::operator_roles();
		$groups = get_posts( array( 'post_type' => MI_Event_Post_Type::ACTIVITY_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$group_names = wp_list_pluck( $groups, 'post_title', 'ID' );
		$operators = get_users( array( 'role__in' => array_keys( $roles ), 'orderby' => 'display_name', 'order' => 'ASC' ) );
		?>
		<section class="mi-operators"><div class="mi-operators__heading"><div><span class="mi-portal-eyebrow">Amministrazione</span><h2>Operatori</h2></div><p class="mi-portal-muted">Ogni persona usa credenziali proprie. Il segretario vede tutto; gestori e operatori sono limitati ai gruppi selezionati.</p></div>
		<details class="mi-operator-create"><summary>Crea un nuovo operatore</summary><form method="post" autocomplete="off" data-mi-operator-form><input type="hidden" name="mi_portal_action" value="create_operator"><?php wp_nonce_field( 'mi_portal_manage_operators', 'mi_portal_nonce' ); ?>
		<div class="mi-operator-grid"><label>Nome visualizzato<input name="operator_name" maxlength="120" required></label><label>Nome utente per l’accesso<input name="operator_username" maxlength="60" autocomplete="off" required></label><label>Email<input type="email" name="operator_email" maxlength="100" autocomplete="off" required></label><label>Parola d’accesso iniziale<input type="password" name="operator_password" minlength="12" autocomplete="new-password" required><small>Almeno 12 caratteri. Comunicala direttamente alla persona interessata.</small></label><label>Ruolo<select name="operator_role" required><?php foreach ( $roles as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></div>
		<fieldset data-mi-operator-groups hidden><legend>Gruppi assegnati</legend><div class="mi-operator-groups"><?php foreach ( $groups as $group ) : ?><label class="mi-check"><input type="checkbox" name="operator_groups[]" value="<?php echo esc_attr( $group->ID ); ?>"> <?php echo esc_html( $group->post_title ); ?></label><?php endforeach; ?></div></fieldset>
		<button class="mi-primary" type="submit">Crea operatore</button></form></details>
		<div class="mi-operator-list"><?php if ( ! $operators ) : ?><p class="mi-portal-empty">Non sono ancora presenti operatori dedicati.</p><?php endif; ?><?php foreach ( $operators as $operator ) : $selected = MI_Access::activity_ids( $operator->ID ); $selected = is_array( $selected ) ? $selected : array(); $role = current( array_intersect( array_keys( $roles ), (array) $operator->roles ) ) ?: 'mi_event_operator'; $suspended = MI_Access::is_suspended( $operator->ID ); $selected_names = array_values( array_filter( array_map( static function ( $group_id ) use ( $group_names ) { return $group_names[ $group_id ] ?? ''; }, $selected ) ) ); $role_title = array( 'mi_secretary' => 'Segretario iscrizioni', 'mi_event_manager' => 'Gestore iscrizioni', 'mi_event_operator' => 'Operatore di gruppo' )[ $role ] ?? $role; $scope_description = 'mi_secretary' === $role ? 'tutti gli eventi' : ( ( 'mi_event_manager' === $role ? 'gestisce ' : 'consulta ' ) . ( $selected_names ? implode( ', ', $selected_names ) : 'nessun gruppo assegnato' ) ); ?>
		<details class="mi-operator-card<?php echo $suspended ? ' is-suspended' : ''; ?>"><summary><span><strong><?php echo esc_html( $operator->display_name ?: $operator->user_login ); ?></strong><small><?php echo esc_html( $operator->user_login . ' · ' . $role_title . ' — ' . $scope_description ); ?></small></span><em><?php echo $suspended ? 'Sospeso' : 'Attivo'; ?></em></summary><form method="post" autocomplete="off" data-mi-operator-form><input type="hidden" name="mi_portal_action" value="update_operator"><input type="hidden" name="operator_id" value="<?php echo esc_attr( $operator->ID ); ?>"><?php wp_nonce_field( 'mi_portal_manage_operators', 'mi_portal_nonce' ); ?>
		<div class="mi-operator-grid"><label>Nome visualizzato<input name="operator_name" maxlength="120" value="<?php echo esc_attr( $operator->display_name ); ?>" required></label><label>Nome utente per l’accesso<input value="<?php echo esc_attr( $operator->user_login ); ?>" disabled><small>Il nome utente non può essere modificato.</small></label><label>Email<input type="email" name="operator_email" maxlength="100" value="<?php echo esc_attr( $operator->user_email ); ?>" required></label><label>Nuova parola d’accesso <small>(facoltativa)</small><input type="password" name="operator_password" minlength="12" autocomplete="new-password" placeholder="Lascia vuoto per non cambiarla"></label><label>Ruolo<select name="operator_role" required><?php foreach ( $roles as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $role, $code ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></div>
		<fieldset data-mi-operator-groups <?php echo 'mi_secretary' === $role ? 'hidden' : ''; ?>><legend>Gruppi assegnati</legend><div class="mi-operator-groups"><?php foreach ( $groups as $group ) : ?><label class="mi-check"><input type="checkbox" name="operator_groups[]" value="<?php echo esc_attr( $group->ID ); ?>" <?php checked( in_array( $group->ID, $selected, true ) ); ?>> <?php echo esc_html( $group->post_title ); ?></label><?php endforeach; ?></div></fieldset><label class="mi-check mi-operator-suspension"><input type="checkbox" name="operator_suspended" value="1" <?php checked( $suspended ); ?>> Sospendi l’accesso alla Segreteria eventi</label><button class="mi-primary" type="submit">Salva operatore</button></form></details>
		<?php endforeach; ?></div></section>
		<?php
	}

	private static function manage_label() {
		$scope = MI_Access::event_ids();
		if ( 'ALL' !== $scope && 1 === count( $scope ) ) return 'Gestisci evento';
		if ( 'ALL' !== $scope ) return 'I miei eventi';
		return 'Gestisci eventi';
	}

	private static function manage_view() {
		global $wpdb;
		$scope = MI_Access::event_ids();
		if ( 'ALL' !== $scope && ! $scope ) { echo '<section class="mi-portal-empty"><div class="mi-portal-bubble">Na⁺</div><h2>C’è qualcuno qui…?</h2><p>Al momento non ti è stato assegnato nessun evento. Chiedi all’amministratore o al segretario di associarti a un evento.</p></section>'; return; }
		$query = array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC', 'update_post_term_cache' => false );
		if ( 'ALL' !== $scope ) $query['post__in'] = $scope;
		$all_events = get_posts( $query );
		if ( ! $all_events ) { echo '<section class="mi-portal-empty"><div class="mi-portal-bubble">Na⁺</div><h2>C’è qualcuno qui…?</h2><p>Non ci sono eventi visibili.</p></section>'; return; }
		$published_summaries = array();
		$revision_ids = array();
		foreach ( $all_events as $event ) if ( 'publish' === $event->post_status ) { $revision_id = absint( get_post_meta( $event->ID, '_mi_published_revision_id', true ) ); if ( $revision_id ) $revision_ids[] = $revision_id; }
		if ( $revision_ids ) {
			$revision_list = implode( ',', array_map( 'absint', array_unique( $revision_ids ) ) );
			foreach ( $wpdb->get_results( "SELECT event_id,config_json FROM {$wpdb->prefix}mi_event_revisions WHERE id IN ({$revision_list})", ARRAY_A ) as $revision ) {
				$config = json_decode( (string) $revision['config_json'], true );
				if ( is_array( $config ) ) $published_summaries[ (int) $revision['event_id'] ] = $config;
			}
		}
		$show_past = ! empty( $_GET['mi_portal_history'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['mi_portal_history'] ) );
		$current_events = array();
		$past_events = array();
		$start_timestamps = array();
		$cancelled_events = array();
		$archived_events = array();
		foreach ( $all_events as $event ) {
			$published = (array) ( $published_summaries[ $event->ID ] ?? array() );
			$starts_at = (string) ( $published['event_starts_at'] ?? get_post_meta( $event->ID, '_mi_event_starts_at', true ) );
			$closes_at = (string) ( $published['closes_at'] ?? get_post_meta( $event->ID, '_mi_registration_closes_at', true ) );
			$start_timestamps[ $event->ID ] = self::date_timestamp( $starts_at );
			$cancelled_events[ $event->ID ] = (bool) get_post_meta( $event->ID, '_mi_event_cancelled_at', true );
			$archived_events[ $event->ID ] = (bool) get_post_meta( $event->ID, '_mi_event_archived_at', true );
			if ( $archived_events[ $event->ID ] ) $past_events[] = $event;
			elseif ( $cancelled_events[ $event->ID ] ) $current_events[] = $event;
			elseif ( self::is_past_event( $closes_at ?: $starts_at ) ) $past_events[] = $event;
			else $current_events[] = $event;
		}
		$events = $show_past ? $past_events : $current_events;
		usort( $events, static function ( $a, $b ) use ( $start_timestamps, $cancelled_events, $show_past ) {
			if ( ! $show_past && $cancelled_events[ $a->ID ] !== $cancelled_events[ $b->ID ] ) return $cancelled_events[ $a->ID ] ? 1 : -1;
			$a_time = (int) ( $start_timestamps[ $a->ID ] ?? 0 );
			$b_time = (int) ( $start_timestamps[ $b->ID ] ?? 0 );
			if ( ! $a_time ) $a_time = $show_past ? 0 : PHP_INT_MAX;
			if ( ! $b_time ) $b_time = $show_past ? 0 : PHP_INT_MAX;
			return $show_past ? $b_time <=> $a_time : $a_time <=> $b_time;
		} );
		$ids = array_map( 'absint', wp_list_pluck( $events, 'ID' ) );
		$counts = array();
		$registration_counts = array();
		$active_registration_counts = array();
		if ( $ids ) {
			$safe_ids = implode( ',', $ids );
			foreach ( $wpdb->get_results( "SELECT event_id,confirmed_count FROM {$wpdb->prefix}mi_event_counters WHERE event_id IN ({$safe_ids})", ARRAY_A ) as $counter ) $counts[ (int) $counter['event_id'] ] = (int) $counter['confirmed_count'];
			foreach ( $wpdb->get_results( "SELECT event_id,COUNT(*) AS total_count,SUM(CASE WHEN status IN ('CONFIRMED','PENDING_PAYMENT','WAITLISTED') AND capacity_released_at IS NULL THEN 1 ELSE 0 END) AS active_count FROM {$wpdb->prefix}mi_registrations WHERE event_id IN ({$safe_ids}) GROUP BY event_id", ARRAY_A ) as $counter ) {
				$registration_counts[ (int) $counter['event_id'] ] = (int) $counter['total_count'];
				$active_registration_counts[ (int) $counter['event_id'] ] = (int) $counter['active_count'];
			}
		}
		$base_url = self::base_url();
		echo '<section id="mi-elenco-eventi"><h2>' . ( $show_past ? 'Eventi passati' : 'Eventi' ) . '</h2>';
		if ( $events ) echo '<div class="mi-event-grid">';
		foreach ( $events as $event ) {
			$count = (int) ( $counts[ $event->ID ] ?? 0 );
			$is_cancelled = ! empty( $cancelled_events[ $event->ID ] );
			$published = (array) ( $published_summaries[ $event->ID ] ?? array() );
			$capacity = max( 1, absint( $published['capacity'] ?? get_post_meta( $event->ID, '_mi_capacity', true ) ) );
			$starts_at = (string) ( $published['event_starts_at'] ?? get_post_meta( $event->ID, '_mi_event_starts_at', true ) );
			$closes_at = (string) ( $published['closes_at'] ?? get_post_meta( $event->ID, '_mi_registration_closes_at', true ) );
			$is_expired = self::is_past_event( $closes_at );
			$event_title = sanitize_text_field( (string) ( $published['title'] ?? $event->post_title ) );
			$activity_name = sanitize_text_field( (string) ( $published['activity'] ?? '' ) );
			$activity_id = absint( get_post_meta( $event->ID, '_mi_activity_id', true ) );
			if ( ! $activity_name && $activity_id ) $activity_name = get_the_title( $activity_id );
			$cover_image = esc_url( (string) ( $published['cover_image'] ?? get_the_post_thumbnail_url( $event->ID, 'thumbnail' ) ) );
			if ( ! $cover_image ) $cover_image = self::group_cover_url( $activity_id, 'thumbnail' );
			$date_badge = self::date_badge( $starts_at );
			$occupancy_percentage = min( 100, max( 0, (int) round( ( $count / $capacity ) * 100 ) ) );
			if ( 'draft' === $event->post_status && ! $is_cancelled ) {
				// Una bozza è sempre un processo di creazione interrotto: riapriamo
				// l'intero percorso, anche quando i soli elementi mancanti sono operativi.
				$url_args = array( 'mi_portal_view' => 'create', 'mi_portal_draft' => $event->ID );
			} else {
				$url_args = array( 'mi_portal_view' => 'manage', 'mi_portal_event' => $event->ID );
			}
			if ( $show_past ) $url_args['mi_portal_history'] = '1';
			$url = add_query_arg( $url_args, $base_url );
			echo '<article class="mi-event-card-shell"><a class="mi-event-card' . ( $is_cancelled ? ' is-cancelled' : ( $is_expired ? ' is-expired' : '' ) ) . '" href="' . esc_url( $url ) . '"><span class="mi-event-card__date"><small>' . esc_html( $date_badge['month'] ) . '</small><strong>' . esc_html( $date_badge['day'] ) . '</strong></span><span class="mi-event-card__content"><span class="mi-event-card__image">';
			if ( $cover_image ) echo '<img src="' . esc_url( $cover_image ) . '" alt="" loading="lazy" decoding="async" fetchpriority="low">';
			echo '</span><span class="mi-event-card__identity"><strong>' . esc_html( $event_title ) . '</strong>';
			if ( $activity_name ) echo '<small>' . esc_html( $activity_name ) . '</small>';
			$status_label = $is_cancelled ? 'Annullato' : ( $is_expired ? 'Scaduto' : ( 'publish' === $event->post_status ? ( $show_past ? 'Concluso' : 'Attivo' ) : 'Bozza' ) );
			echo '<small>' . esc_html( self::format_date( $starts_at ) ) . '</small></span><span class="mi-event-card__footer"><span class="mi-event-card__capacity"><small>Posti occupati</small><strong>' . esc_html( $count . ' / ' . $capacity ) . '</strong><i aria-hidden="true"><b style="width:' . esc_attr( $occupancy_percentage ) . '%"></b></i></span><span class="mi-event-card__status"><strong>' . esc_html( $status_label ) . '</strong><small>Scadenza: ' . esc_html( self::format_date( $closes_at ) ) . '</small></span></span></span></a>';
			$registration_count = (int) ( $registration_counts[ $event->ID ] ?? 0 );
			$active_count = (int) ( $active_registration_counts[ $event->ID ] ?? 0 );
			$can_trash = ! $is_cancelled && 'draft' === $event->post_status && 0 === $registration_count;
			$can_archive = $is_cancelled && empty( $archived_events[ $event->ID ] );
			$can_cancel = ! $is_cancelled && 'draft' !== $event->post_status;
			if ( $can_trash || $can_archive || $can_cancel ) {
				echo '<details class="mi-event-card-menu"><summary aria-label="Azioni per ' . esc_attr( $event_title ) . '">⋯</summary><div>';
				if ( $can_trash ) {
					echo '<form method="post" onsubmit="return confirm(\'Eliminare questa bozza? Sarà spostata nel cestino di WordPress.\')"><input type="hidden" name="mi_portal_action" value="trash_event"><input type="hidden" name="event_id" value="' . esc_attr( $event->ID ) . '">';
					wp_nonce_field( 'mi_portal_manage_event_' . $event->ID, 'mi_portal_nonce' );
					echo '<button class="mi-text-danger" type="submit">Elimina bozza</button></form>';
				} elseif ( $can_archive ) {
					echo '<form method="post" onsubmit="return confirm(\'Archiviare questo evento annullato? Resterà consultabile nello storico.\')"><input type="hidden" name="mi_portal_action" value="archive_event"><input type="hidden" name="event_id" value="' . esc_attr( $event->ID ) . '">';
					wp_nonce_field( 'mi_portal_manage_event_' . $event->ID, 'mi_portal_nonce' );
					echo '<button type="submit">Archivia</button></form>';
				} elseif ( $can_cancel ) {
					echo '<form method="post" onsubmit="return confirm(\'Confermi definitivamente l’annullamento di questo evento?\')"><input type="hidden" name="mi_portal_action" value="cancel_event"><input type="hidden" name="event_id" value="' . esc_attr( $event->ID ) . '">';
					wp_nonce_field( 'mi_portal_manage_event_' . $event->ID, 'mi_portal_nonce' );
					if ( $active_count > 0 ) echo '<p><strong>Attenzione:</strong> saranno annullate ' . esc_html( $active_count ) . ' prenotazioni. I dati resteranno nello storico.</p><label>Motivo <small>(facoltativo)</small><textarea name="cancellation_reason" rows="3" maxlength="2000"></textarea></label><label class="mi-check"><input type="checkbox" name="confirm_cancellation" value="1" required> Ho compreso che le iscrizioni saranno annullate</label>';
					else echo '<p>L’evento non ha prenotazioni attive.</p><input type="hidden" name="confirm_cancellation" value="1">';
					echo '<button class="mi-text-danger" type="submit">Annulla evento</button></form>';
				}
				echo '</div></details>';
			}
			echo '</article>';
		}
		if ( $events ) echo '</div>'; else echo '<p class="mi-portal-muted">' . ( $show_past ? 'Non ci sono eventi passati.' : 'Non ci sono eventi in corso, futuri o in bozza.' ) . '</p>';
		echo '</section>';
		if ( $ids ) {
			$selected = absint( $_GET['mi_portal_event'] ?? 0 );
			if ( $selected && in_array( $selected, $ids, true ) ) {
				self::event_management_card( $selected );
				if ( ! empty( $_GET['mi_portal_outputs'] ) ) {
					self::event_outputs_panel( $selected );
					self::notice();
				}
			}
		}
		$history_url = $show_past
			? add_query_arg( 'mi_portal_view', 'manage', $base_url )
			: add_query_arg( array( 'mi_portal_view' => 'manage', 'mi_portal_history' => '1' ), $base_url );
		echo '<p class="mi-event-history-link"><a href="' . esc_url( $history_url ) . '">' . ( $show_past ? 'Torna agli eventi attuali' : 'Visualizza eventi passati' ) . '</a></p>';
	}

	private static function event_management_card( $event_id ) {
		$event = get_post( $event_id );
		if ( ! $event || ! MI_Access::can_access_event( $event_id ) ) return;
		$starts_at = (string) get_post_meta( $event_id, '_mi_event_starts_at', true );
		$closes_at = (string) get_post_meta( $event_id, '_mi_registration_closes_at', true );
		$location = (string) get_post_meta( $event_id, '_mi_event_location', true );
		$capacity = max( 1, absint( get_post_meta( $event_id, '_mi_capacity', true ) ) );
		$cancelled = (string) get_post_meta( $event_id, '_mi_event_cancelled_at', true );
		$expired = self::is_past_event( $closes_at );
		$registration_url = 'publish' === $event->post_status ? esc_url( MI_Shortcode::url_iscrizione( $event_id ) ) : '';
		global $wpdb;
		$active_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d AND status IN ('CONFIRMED','PENDING_PAYMENT','WAITLISTED') AND capacity_released_at IS NULL", $event_id ) );
		$registration_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d", $event_id ) );
		$list_args = array( 'mi_portal_view' => 'manage' );
		if ( ! empty( $_GET['mi_portal_history'] ) ) $list_args['mi_portal_history'] = '1';
		$list_url = add_query_arg( $list_args, self::base_url() ) . '#mi-elenco-eventi';
		echo '<section class="mi-event-management" data-mi-selected-event tabindex="-1"><a class="mi-event-management__back" href="' . esc_url( $list_url ) . '" aria-label="Chiudi la scheda dell’evento"><span aria-hidden="true">×</span> Chiudi la scheda</a><div class="mi-event-management__heading"><div><span class="mi-portal-eyebrow">Evento selezionato</span><h2>' . esc_html( $event->post_title ) . '</h2></div><span class="mi-event-management__state">' . esc_html( $cancelled ? 'Annullato' : ( $expired ? 'Scaduto' : ( 'publish' === $event->post_status ? 'Attivo' : 'Bozza' ) ) ) . '</span></div>';
		if ( $cancelled ) { echo '<div class="mi-portal-notice mi-portal-error"><strong>Evento annullato</strong><p>La scheda e le iscrizioni sono conservate nello storico.</p></div></section>'; return; }
		echo '<details open><summary>Dettagli principali</summary><form class="mi-event-management__form" method="post"><input type="hidden" name="mi_portal_action" value="update_event"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
		wp_nonce_field( 'mi_portal_manage_event_' . $event_id, 'mi_portal_nonce' );
		echo '<label>Titolo<input name="title" maxlength="180" required value="' . esc_attr( $event->post_title ) . '"></label><label>Luogo<input name="location" maxlength="180" value="' . esc_attr( $location ) . '"></label><div class="mi-wizard-grid"><label>Data e ora di inizio<input type="datetime-local" name="starts_at" value="' . esc_attr( $starts_at ) . '"></label><label>Chiusura iscrizioni<input type="datetime-local" name="closes_at" value="' . esc_attr( $closes_at ) . '"></label><label>Posti disponibili<input type="number" min="1" max="10000" name="capacity" value="' . esc_attr( $capacity ) . '"></label></div><button class="mi-primary" type="submit">Salva modifiche</button></form>';
		if ( $registration_url ) {
			echo '<div class="mi-event-management__registration"><strong>Link per le iscrizioni</strong><div class="mi-output-copy mi-event-registration-link"><input type="url" readonly aria-label="Link per le iscrizioni" value="' . esc_attr( $registration_url ) . '"><button type="button" class="mi-secondary" data-mi-copy="' . esc_attr( $registration_url ) . '">Copia</button><button type="button" class="mi-event-link-share" data-mi-share="' . esc_attr( $registration_url ) . '" data-mi-share-title="' . esc_attr( $event->post_title ) . '" aria-label="Condividi il link per le iscrizioni" title="Condividi il link per le iscrizioni"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="18" cy="5" r="2.5"></circle><circle cx="6" cy="12" r="2.5"></circle><circle cx="18" cy="19" r="2.5"></circle><path d="M8.2 10.8l7.6-4.5M8.2 13.2l7.6 4.5"></path></svg></button></div></div>';
		}
		echo '<p class="mi-event-management__full-edit"><span>Vuoi modificare l’immagine, le domande, i servizi o altri particolari?</span><a class="mi-secondary" href="' . esc_url( add_query_arg( array( 'mi_portal_view' => 'create', 'mi_portal_edit' => $event_id ), self::base_url() ) ) . '">Modifica tutti i dettagli</a></p></details>';
		if ( $active_count > 0 ) {
			echo '<details class="mi-event-danger"><summary>Annulla l’evento</summary><div class="mi-event-danger__body"><p><strong>Attenzione:</strong> saranno annullate ' . esc_html( $active_count ) . ' prenotazioni attive o in lista d’attesa. I dati resteranno nello storico e gli eventuali rimborsi dovranno essere gestiti separatamente.</p><p>Per ogni referente con prenotazione attiva sarà preparato un avviso. L’invio effettivo dipende dalla modalità email generale; in ANTEPRIMA non parte alcuna email.</p><form method="post" onsubmit="return confirm(\'Confermi definitivamente l’annullamento di questo evento?\')"><input type="hidden" name="mi_portal_action" value="cancel_event"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
			wp_nonce_field( 'mi_portal_manage_event_' . $event_id, 'mi_portal_nonce' );
			echo '<label>Motivo da comunicare agli iscritti <small>(facoltativo)</small><textarea name="cancellation_reason" rows="5" maxlength="2000" placeholder="Spiega brevemente perché l’evento è stato annullato."></textarea></label><label class="mi-check"><input type="checkbox" name="confirm_cancellation" value="1" required> Ho compreso che tutte le iscrizioni saranno annullate</label><button class="mi-danger" type="submit">Annulla definitivamente l’evento</button></form></div></details>';
		}
		if ( 'draft' === $event->post_status && 0 === $registration_count ) {
			echo '<details class="mi-event-trash"><summary>Elimina questa bozza</summary><div><p>La bozza sarà spostata nel cestino di WordPress e potrà essere ripristinata. Questa opzione è disponibile soltanto quando non esistono iscrizioni.</p><form method="post" onsubmit="return confirm(\'Spostare questa bozza nel cestino? Potrai ripristinarla dal pannello WordPress.\')"><input type="hidden" name="mi_portal_action" value="trash_event"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
			wp_nonce_field( 'mi_portal_manage_event_' . $event_id, 'mi_portal_nonce' );
			echo '<button class="mi-danger" type="submit">Sposta la bozza nel cestino</button></form></div></details>';
		}
		echo '</section>';
	}

	private static function registrations_view( $event_id = 0, $event_ids = array(), $period = 'current', $event_mode = 'all' ) {
		global $wpdb;
		if ( $event_id ) { $conditions = array( $wpdb->prepare( 'r.event_id=%d', $event_id ) ); } else { $safe = array_values( array_filter( array_map( 'absint', $event_ids ) ) ); $conditions = array( $safe ? 'r.event_id IN (' . implode( ',', $safe ) . ')' : '1=0' ); }
		$query = sanitize_text_field( wp_unslash( $_GET['mi_portal_query'] ?? '' ) );
		$status = strtoupper( sanitize_text_field( wp_unslash( $_GET['mi_portal_status'] ?? '' ) ) );
		$allowed_statuses = array( 'CONFIRMED', 'PENDING_PAYMENT', 'WAITLISTED', 'CANCELLED', 'EXPIRED' );
		if ( in_array( $status, $allowed_statuses, true ) ) $conditions[] = $wpdb->prepare( 'r.status=%s', $status );
		if ( '' !== $query ) {
			$like = '%' . $wpdb->esc_like( $query ) . '%';
			$conditions[] = $wpdb->prepare( '(r.order_code LIKE %s OR r.buyer_first_name LIKE %s OR r.buyer_last_name LIKE %s OR r.buyer_email LIKE %s OR r.buyer_phone LIKE %s)', $like, $like, $like, $like, $like );
		}
		$where = implode( ' AND ', $conditions );
		$rows = $wpdb->get_results( "SELECT r.id registration_id,r.event_id,r.order_code,r.status,r.created_at,r.buyer_first_name,r.buyer_last_name,r.buyer_email,r.buyer_phone,r.total_qty,r.total_cents,r.balance_cents,events.post_title event_title FROM {$wpdb->prefix}mi_registrations r JOIN {$wpdb->posts} events ON events.ID=r.event_id WHERE {$where} ORDER BY r.created_at DESC,r.id DESC LIMIT 30", ARRAY_A );
		$base_url = self::base_url();
		$base_url = add_query_arg( array( 'mi_portal_period' => $period, 'mi_portal_event_mode' => $event_mode ), $base_url );
		if ( $event_id ) $base_url = add_query_arg( 'mi_portal_event', $event_id, $base_url );
		if ( '' !== $query ) $base_url = add_query_arg( 'mi_portal_query', $query, $base_url );
		if ( in_array( $status, $allowed_statuses, true ) ) $base_url = add_query_arg( 'mi_portal_status', $status, $base_url );
		$list_title = 'Prenotazioni';
		if ( $event_id ) {
			$event_title = sanitize_text_field( get_the_title( $event_id ) );
			if ( $event_title ) $list_title .= ' — ' . $event_title;
		}
		echo '<section class="mi-registration-results"><div class="mi-registration-results__heading"><h2>' . esc_html( $list_title ) . '</h2><span>' . esc_html( count( $rows ) . ( 30 === count( $rows ) ? '+' : '' ) ) . '</span></div><div class="mi-booking-list">';
		$status_labels = array( 'CONFIRMED' => 'Confermata', 'PENDING_PAYMENT' => 'Pagamento atteso', 'WAITLISTED' => 'Lista d’attesa', 'CANCELLED' => 'Annullata', 'EXPIRED' => 'Scaduta' );
		$status_classes = array( 'CONFIRMED' => 'is-green', 'PENDING_PAYMENT' => 'is-yellow', 'WAITLISTED' => 'is-blue', 'CANCELLED' => 'is-red', 'EXPIRED' => 'is-red' );
		foreach ( $rows as $row ) {
			$url = add_query_arg( array( 'mi_portal_view' => 'registrations', 'mi_portal_booking' => $row['registration_id'] ), $base_url );
			$name = trim( $row['buyer_first_name'] . ' ' . $row['buyer_last_name'] );
			$initials = strtoupper( substr( (string) $row['buyer_first_name'], 0, 1 ) . substr( (string) $row['buyer_last_name'], 0, 1 ) );
			$contact = $row['buyer_phone'] ?: $row['buyer_email'];
			$status_label = $status_labels[ $row['status'] ] ?? $row['status'];
			$status_class = $status_classes[ $row['status'] ] ?? 'is-blue';
			if ( (int) $row['total_cents'] < 1 ) { $payment_label = 'Gratuito'; $payment_class = 'is-blue'; }
			elseif ( (int) $row['balance_cents'] < 1 ) { $payment_label = 'Saldato'; $payment_class = 'is-green'; }
			else { $payment_label = 'Saldo ' . self::format_money( $row['balance_cents'] ); $payment_class = 'is-yellow'; }
			echo '<a class="mi-booking-card" data-mi-portal-booking-open href="' . esc_url( $url ) . '"><span class="mi-booking-card__avatar" aria-hidden="true">' . esc_html( $initials ?: '—' ) . '</span><span class="mi-booking-card__content"><strong>' . esc_html( $name ?: 'Referente non indicato' ) . '</strong><small>' . esc_html( $row['event_title'] . ' · ' . $row['order_code'] ) . '</small><small>' . esc_html( self::format_utc_date( $row['created_at'] ) . ( $contact ? ' · ' . $contact : '' ) . ' · ' . (int) $row['total_qty'] . ' partecipanti' ) . '</small></span><span class="mi-booking-card__states"><small class="mi-status-pill ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</small><small class="mi-status-pill ' . esc_attr( $payment_class ) . '">' . esc_html( $payment_label ) . '</small></span></a>';
		}
		if ( ! $rows ) echo '<div class="mi-registration-empty"><strong>Nessuna iscrizione trovata</strong><p class="mi-portal-muted">Prova a modificare il testo cercato o i filtri selezionati.</p></div>';
		echo '</div></section>';
		$booking_id = absint( $_GET['mi_portal_booking'] ?? 0 ); if ( $booking_id ) self::booking_detail( $booking_id );
	}

	private static function booking_detail( $registration_id ) {
		global $wpdb; $registration = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $registration_id ), ARRAY_A );
		if ( ! $registration || ! MI_Access::can_access_event( (int) $registration['event_id'] ) ) return;
		$participants = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d ORDER BY id", $registration_id ), ARRAY_A );
		$snapshot = json_decode( (string) ( $registration['snapshot_json'] ?? '' ), true );
		$snapshot_event = (array) ( $snapshot['event'] ?? array() );
		$event_name = sanitize_text_field( (string) ( $snapshot_event['title'] ?? get_the_title( (int) $registration['event_id'] ) ) );
		$activity_name = sanitize_text_field( (string) ( $snapshot_event['activity'] ?? '' ) );
		$cover_image = esc_url( (string) ( $snapshot_event['cover_image'] ?? get_the_post_thumbnail_url( (int) $registration['event_id'], 'large' ) ) );
		if ( ! $activity_name ) {
			$activity_id = absint( get_post_meta( (int) $registration['event_id'], '_mi_activity_id', true ) );
			if ( $activity_id ) $activity_name = get_the_title( $activity_id );
		}
		if ( ! isset( $activity_id ) ) $activity_id = absint( get_post_meta( (int) $registration['event_id'], '_mi_activity_id', true ) );
		if ( ! $cover_image ) $cover_image = self::group_cover_url( $activity_id, 'large' );
		$is_multiple = count( $participants ) > 1;
		$referent_participant_id = 0;
		if ( $is_multiple ) {
			$buyer_key = mb_strtolower( remove_accents( trim( $registration['buyer_first_name'] . ' ' . $registration['buyer_last_name'] ) ) );
			foreach ( $participants as $participant ) {
				$participant_key = mb_strtolower( remove_accents( trim( $participant['first_name'] . ' ' . $participant['last_name'] ) ) );
				if ( $participant_key === $buyer_key ) { $referent_participant_id = (int) $participant['id']; break; }
			}
			if ( ! $referent_participant_id && $participants ) $referent_participant_id = (int) $participants[0]['id'];
		}
		$field_labels = array();
		foreach ( (array) ( $snapshot['event']['participant_fields'] ?? array() ) as $field ) { $key = sanitize_key( $field['key'] ?? '' ); $label = sanitize_text_field( $field['label'] ?? '' ); if ( $key && $label ) $field_labels[ $key ] = $label; }
		echo '<section id="mi-portal-booking-detail" class="mi-booking-detail"><div class="mi-booking-detail__hero' . ( $cover_image ? ' has-cover' : '' ) . '">';
		if ( $cover_image ) echo '<img class="mi-booking-detail__cover" src="' . esc_url( $cover_image ) . '" alt="" loading="lazy" decoding="async">';
		echo '<header class="mi-booking-detail__header">';
		if ( $activity_name ) echo '<p class="mi-booking-detail__activity"><span>Gruppo</span>' . esc_html( $activity_name ) . '</p>';
		echo '<h2 class="mi-booking-detail__event">' . esc_html( $event_name ) . '</h2><p class="mi-booking-detail__code">Codice prenotazione <code>' . esc_html( $registration['order_code'] ) . '</code></p></header></div>';
		foreach ( $participants as $participant ) {
			$is_referent = $is_multiple && $referent_participant_id === (int) $participant['id'];
			echo '<article><h3 class="mi-booking-detail__person">';
			if ( $is_referent ) echo '<span class="mi-booking-detail__referent-dot" aria-label="Referente" title="Referente"></span>';
			echo '<span>' . esc_html( $participant['first_name'] . ' ' . $participant['last_name'] ) . '</span>' . ( 'CANCELLED' === $participant['status'] ? ' <small>— Annullata</small>' : '' ) . '</h3>';
			if ( ! empty( $registration['buyer_email'] ) || ! empty( $registration['buyer_phone'] ) ) {
				if ( ! $is_multiple || $is_referent ) {
					echo '<div class="mi-booking-detail__contacts">';
					if ( ! empty( $registration['buyer_email'] ) ) echo '<p><span>Email</span><strong>' . esc_html( $registration['buyer_email'] ) . '</strong></p>';
					if ( ! empty( $registration['buyer_phone'] ) ) echo '<p><span>Cellulare</span><strong>' . esc_html( $registration['buyer_phone'] ) . '</strong></p>';
					echo '</div>';
				}
			}
			$fields = json_decode( (string) $participant['extra_json'], true );
			foreach ( (array) $fields as $key => $value ) if ( '' !== (string) $value ) echo '<p><span>' . esc_html( $field_labels[ $key ] ?? ucfirst( str_replace( '_', ' ', preg_replace( '/^custom_/', '', $key ) ) ) ) . '</span><strong>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</strong></p>';
			if ( 'ACTIVE' === ( $participant['status'] ?: 'ACTIVE' ) ) {
				echo '<form class="mi-booking-detail__cancel" method="post" onsubmit="return confirm(\'Annullare la partecipazione di questa persona?\')"><input type="hidden" name="mi_portal_action" value="cancel_participant_portal"><input type="hidden" name="participant_id" value="' . esc_attr( $participant['id'] ) . '">';
				wp_nonce_field( 'mi_cancel_participant_portal_' . $participant['id'], 'mi_portal_nonce' );
				echo '<button class="mi-booking-detail__cancel-button" type="submit">Annulla partecipazione</button></form>';
			}
			echo '</article>';
		}
		echo '</section>';
	}

	private static function create_view( $existing_event_id = 0 ) {
		$event = $existing_event_id ? get_post( $existing_event_id ) : null;
		if ( $event && ( MI_Event_Post_Type::EVENT_TYPE !== $event->post_type || ! in_array( $event->post_status, array( 'draft', 'publish', 'private' ), true ) || get_post_meta( $existing_event_id, '_mi_event_cancelled_at', true ) || ! MI_Access::can_access_event( $existing_event_id ) ) ) $event = null;
		$is_editing = (bool) $event;
		$is_draft = $is_editing && 'draft' === $event->post_status;
		$value = static function ( $key, $default = '' ) use ( $existing_event_id, $is_editing ) { return $is_editing ? get_post_meta( $existing_event_id, $key, true ) : $default; };
		$activity_id = absint( $value( '_mi_activity_id' ) );
		$participant_fields = (array) $value( '_mi_participant_fields', array() );
		$participant_required = (array) $value( '_mi_participant_required_fields', array() );
		$custom_questions = array_values( (array) $value( '_mi_custom_participant_fields', array() ) );
		$options = array();
		foreach ( (array) $value( '_mi_options', array() ) as $option ) if ( ! empty( $option['code'] ) ) $options[ sanitize_key( $option['code'] ) ] = (array) $option;
		$accommodations_selected = (array) $value( '_mi_accommodations', array() );
		$payment_methods_selected = (array) $value( '_mi_payment_methods', array() );
		$pricing_mode_selected = strtoupper( (string) $value( '_mi_pricing_mode', 'ZERO' ) );
		if ( 'CALCULATED' === $pricing_mode_selected ) $pricing_mode_selected = 'NONE';
		$economic_mode_selected = strtoupper( (string) $value( '_mi_economic_mode', 'FULL_PAYMENT' ) );
		$deposit_mode_selected = 'FIXED' === strtoupper( (string) $value( '_mi_deposit_mode', 'PERCENTAGE' ) ) ? 'FIXED' : 'PERCENTAGE';
		$models = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => 30, 'orderby' => 'date', 'order' => 'DESC' ) );
		$activities = get_posts( array( 'post_type' => MI_Event_Post_Type::ACTIVITY_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$catalog = MI_Field_Schema::catalog();
		$common_fields = array_intersect_key( $catalog, array_flip( array( 'email', 'phone', 'birth_date' ) ) );
		$additional_fields = array_diff_key( $catalog, $common_fields );
		$today_start = current_time( 'Y-m-d\TH:i' );
		$draft_registration_count = 0;
		$draft_initial_step = 0;
		if ( $is_draft ) {
			global $wpdb;
			$draft_registration_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d", $existing_event_id ) );
			$draft_initial_step = self::draft_initial_step( $existing_event_id );
		}
		?><?php if ( $is_editing ) : ?><div class="mi-draft-resume"><div class="mi-portal-notice mi-portal-success"><strong><?php echo $is_draft ? 'Riprendi la creazione di' : 'Modifica'; ?> “<?php echo esc_html( $event->post_title ); ?>”</strong><p><?php echo $is_draft ? 'Completa o correggi i passaggi e salva nuovamente la stessa bozza.' : 'Stai aggiornando lo stesso evento. Il modulo e il foglio già collegati non saranno duplicati.'; ?></p></div><?php if ( $is_draft && 0 === $draft_registration_count ) : ?><form class="mi-draft-resume__delete" method="post" onsubmit="return confirm('Eliminare questa bozza? Sarà spostata nel cestino di WordPress.');"><input type="hidden" name="mi_portal_action" value="trash_event"><input type="hidden" name="event_id" value="<?php echo esc_attr( $existing_event_id ); ?>"><?php wp_nonce_field( 'mi_portal_manage_event_' . $existing_event_id, 'mi_portal_nonce' ); ?><button class="mi-danger" type="submit">Elimina questa bozza</button></form><?php endif; ?></div><?php endif; ?><form class="mi-event-wizard" method="post" enctype="multipart/form-data" data-mi-initial-step="<?php echo esc_attr( $draft_initial_step ); ?>" data-mi-operational-profile="<?php echo esc_attr( $value( '_mi_operational_profile', 'AUTOMATICO' ) ); ?>" data-mi-deposit-mode="<?php echo esc_attr( $deposit_mode_selected ); ?>" data-mi-deposit-fixed="<?php echo esc_attr( number_format( absint( $value( '_mi_deposit_fixed_cents', 0 ) ) / 100, 2, ',', '' ) ); ?>"><input type="hidden" name="mi_portal_action" value="create_event"><input type="hidden" name="event_id" value="<?php echo esc_attr( $is_editing ? $existing_event_id : 0 ); ?>"><?php wp_nonce_field( 'mi_portal_create_event', 'mi_portal_nonce' ); ?>
		<section class="mi-wizard-step is-active"><span class="mi-portal-eyebrow">1 di 8</span><h2>Come si chiama?</h2><label>Titolo dell’evento<input name="title" required maxlength="180" value="<?php echo esc_attr( $is_editing ? $event->post_title : '' ); ?>" placeholder="Es. Pellegrinaggio ad Assisi"></label><label>Gruppo organizzatore<select name="activity_id"><option value="">Scegli un gruppo</option><?php foreach ( $activities as $activity ) : ?><option value="<?php echo esc_attr( $activity->ID ); ?>" <?php selected( $activity_id, $activity->ID ); ?>><?php echo esc_html( $activity->post_title ); ?></option><?php endforeach; ?></select></label><?php if ( self::can_manage_groups() ) : ?><p class="mi-portal-muted mi-wizard-group-link">Crea o modifica gruppo in: <a href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'groups', self::base_url() ) ); ?>" target="_blank" rel="noopener">Gruppi</a></p><?php endif; ?><?php if ( ! $is_editing ) : ?><label>Vuoi partire dalla configurazione di un evento precedente?<select name="copy_event_id"><option value="">No, configuro liberamente il nuovo evento</option><?php foreach ( $models as $model ) : ?><option value="<?php echo esc_attr( $model->ID ); ?>"><?php echo esc_html( $model->post_title ); ?></option><?php endforeach; ?></select></label><p class="mi-portal-muted">Vengono copiate solo impostazioni e domande, mai iscrizioni o dati personali.</p><?php endif; ?></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">2 di 8</span><h2>Cosa devono sapere le persone?</h2><label>Luogo<input name="location" maxlength="180" value="<?php echo esc_attr( $value( '_mi_event_location' ) ); ?>" placeholder="Es. Basilica di Sant’Eugenio"></label><label>Presentazione e informazioni<textarea name="description" rows="6" maxlength="1200" data-mi-max-lines="6" placeholder="Descrivi programma, orari, cosa portare e ogni informazione utile."><?php echo esc_textarea( $is_editing ? $event->post_content : '' ); ?></textarea><small>Massimo 6 righe. Gli a capo saranno mantenuti nel modulo.</small></label></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">3 di 8</span><h2>Immagine</h2><?php $current_cover = $is_editing ? get_the_post_thumbnail_url( $existing_event_id, 'medium_large' ) : ''; if ( $current_cover ) : ?><figure class="mi-wizard-current-cover"><img src="<?php echo esc_url( $current_cover ); ?>" alt=""><figcaption>Immagine attuale</figcaption></figure><?php endif; ?><label><?php echo $current_cover ? 'Sostituisci l’immagine in evidenza' : 'Immagine in evidenza'; ?> <small>(facoltativa)</small><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" data-mi-max-bytes="2097152"></label><p class="mi-portal-muted">JPG, PNG o WebP, massimo 2 MB. Comparirà nella scheda e nella pagina dell’evento.</p></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">4 di 8</span><h2>Date e posti</h2><div class="mi-wizard-grid"><label>Apertura iscrizioni<input type="text" inputmode="numeric" maxlength="16" pattern="\d{2}/\d{2}/\d{4} \d{2}:\d{2}" name="opens_at" value="<?php echo esc_attr( self::portal_date_input_value( $value( '_mi_registration_opens_at' ) ) ); ?>" placeholder="gg/mm/aaaa hh:mm" data-mi-opens></label><label>Chiusura iscrizioni<input type="text" inputmode="numeric" maxlength="16" pattern="\d{2}/\d{2}/\d{4} \d{2}:\d{2}" name="closes_at" value="<?php echo esc_attr( self::portal_date_input_value( $value( '_mi_registration_closes_at' ) ) ); ?>" placeholder="gg/mm/aaaa hh:mm" data-mi-closes required></label><label>Data e ora di inizio<input type="text" inputmode="numeric" maxlength="16" pattern="\d{2}/\d{2}/\d{4} \d{2}:\d{2}" name="starts_at" value="<?php echo esc_attr( self::portal_date_input_value( $value( '_mi_event_starts_at' ) ) ); ?>" placeholder="gg/mm/aaaa hh:mm" data-mi-starts required></label><label>Posti disponibili<input type="number" min="1" max="10000" name="capacity" value="<?php echo esc_attr( max( 1, absint( $value( '_mi_capacity', 30 ) ) ) ); ?>" required></label></div><p class="mi-portal-muted">Formato richiesto: gg/mm/aaaa hh:mm. Ordine: apertura iscrizioni, chiusura iscrizioni, inizio evento. La chiusura e l’inizio non possono essere nel passato.</p><label class="mi-check"><input type="checkbox" name="waitlist_enabled" value="1" <?php checked( '1', $value( '_mi_waitlist_enabled' ) ); ?>> Attiva automaticamente la lista d’attesa a esaurimento posti</label></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">5 di 8</span><h2>Quali dati chiediamo?</h2><p>Nome e cognome sono sempre obbligatori per ogni partecipante.</p><fieldset><legend>Dati aggiuntivi da compilare</legend><label class="mi-check"><input type="radio" name="participant_extra_scope" value="ONE" <?php checked( 'ONE', $value( '_mi_participant_extra_scope', 'ONE' ) ); ?>> Solo per uno degli iscritti (il primo, modificabile)</label><label class="mi-check"><input type="radio" name="participant_extra_scope" value="ALL" <?php checked( 'ALL', $value( '_mi_participant_extra_scope', 'ONE' ) ); ?>> Per tutti gli iscritti</label></fieldset><div class="mi-field-choice-list"><?php self::render_field_choices( $common_fields, $participant_fields, $participant_required ); ?></div><details class="mi-additional-fields"><summary>Ulteriori dati per alcuni eventi</summary><div class="mi-field-choice-list"><?php self::render_field_choices( $additional_fields, $participant_fields, $participant_required ); ?></div></details></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">6 di 8</span><h2>Domande particolari</h2><p class="mi-portal-muted">Aggiungi solo ciò che serve davvero. Puoi lasciare tutto vuoto.</p><div class="mi-custom-questions"><?php for ( $i = 0; $i < 4; $i++ ) : $question = (array) ( $custom_questions[ $i ] ?? array() ); $question_type = sanitize_key( $question['type'] ?? 'text' ); ?><div class="mi-custom-question"><label>Domanda <?php echo esc_html( $i + 1 ); ?><input name="custom_question_label[]" maxlength="120" value="<?php echo esc_attr( $question['label'] ?? '' ); ?>" placeholder="Es. Allergie da segnalare"></label><label>Risposta<select name="custom_question_type[]"><option value="text" <?php selected( $question_type, 'text' ); ?>>Breve</option><option value="yesno" <?php selected( $question_type, 'yesno' ); ?>>Sì / No</option><option value="textarea" <?php selected( $question_type, 'textarea' ); ?>>Testo libero</option><option value="date" <?php selected( $question_type, 'date' ); ?>>Data</option><option value="email" <?php selected( $question_type, 'email' ); ?>>Email</option><option value="tel" <?php selected( $question_type, 'tel' ); ?>>Telefono</option></select></label><label class="mi-check"><input type="checkbox" name="custom_question_required[<?php echo esc_attr( $i ); ?>]" value="1" <?php checked( ! empty( $question['required'] ) ); ?>> Obbligatoria</label></div><?php endfor; ?></div><label class="mi-check"><input type="checkbox" name="special_requests_enabled" value="1" <?php checked( '1', $value( '_mi_special_requests_enabled' ) ); ?>> Mostra uno spazio facoltativo per richieste particolari</label><label class="mi-check"><input type="checkbox" name="marketing_enabled" value="1" <?php checked( '1', $value( '_mi_marketing_enabled' ) ); ?>> Mostra “Comunicazioni su future iniziative”</label></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">7 di 8</span><h2>Servizi e quote (se presenti)</h2><label class="mi-check"><input type="checkbox" name="overnight" value="1" data-mi-overnight <?php checked( '1', $value( '_mi_overnight_enabled' ) ); ?>> È previsto il pernottamento</label><div data-mi-accommodations hidden><h3>Tipi di alloggio</h3><?php foreach ( array( 'SINGOLA' => 'Singola', 'DOPPIA_SEPARATI' => 'Doppia con letti separati', 'DOPPIA_MATRIMONIALE' => 'Doppia matrimoniale', 'TRIPLA' => 'Tripla', 'MULTIPLA' => 'Multipla' ) as $code => $label ) : ?><div class="mi-accommodation-fee"><label class="mi-check"><input type="checkbox" name="accommodations[]" value="<?php echo esc_attr( $code ); ?>" data-mi-accommodation <?php checked( in_array( $code, $accommodations_selected, true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php $accommodation_option = (array) ( $options[ 'alloggio-' . strtolower( str_replace( '_', '-', $code ) ) ] ?? array() ); ?><label>Quota (€)<input name="accommodation_price[<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( ! empty( $accommodation_option ) ? number_format( absint( $accommodation_option['price_cents'] ?? 0 ) / 100, 2, ',', '' ) : '' ); ?>" inputmode="decimal" placeholder="Es. 80,00" <?php disabled( ! in_array( $code, $accommodations_selected, true ) ); ?>></label></div><?php endforeach; ?></div><h3>Quote accessorie per partecipante</h3><p class="mi-portal-muted">Sono indipendenti dal pernottamento. Lascia una voce non selezionata se non è prevista.</p><?php foreach ( array( 'pullman' => 'Pullman', 'pranzo' => 'Pranzo', 'rimborso-spese' => 'Rimborso spese generico' ) as $service_code => $service_label ) : $service = (array) ( $options[ $service_code ] ?? array() ); ?><div class="mi-service-fee"><label class="mi-check"><input type="checkbox" name="service_enabled[<?php echo esc_attr( $service_code ); ?>]" value="1" data-mi-service-fee <?php checked( ! empty( $service ) ); ?>> <?php echo esc_html( $service_label ); ?></label><label>Quota (€)<input name="service_price[<?php echo esc_attr( $service_code ); ?>]" value="<?php echo esc_attr( ! empty( $service ) ? number_format( absint( $service['price_cents'] ?? 0 ) / 100, 2, ',', '' ) : '' ); ?>" inputmode="decimal" placeholder="Es. 25,00" <?php disabled( empty( $service ) ); ?>></label></div><?php endforeach; ?><label>Come sarà l’evento?<select name="pricing_mode" data-mi-pricing><option value="ZERO" <?php selected( $pricing_mode_selected, 'ZERO' ); ?>>Evento totalmente gratuito</option><option value="FIXED" <?php selected( $pricing_mode_selected, 'FIXED' ); ?>>Quota uguale per tutti</option><option value="NONE" <?php selected( $pricing_mode_selected, 'NONE' ); ?>>In base ai servizi scelti</option></select></label><label data-mi-fixed-price hidden>Quota base per partecipante (€)<input name="fixed_price" value="<?php echo esc_attr( number_format( absint( $value( '_mi_fixed_price_cents', 0 ) ) / 100, 2, ',', '' ) ); ?>" inputmode="decimal" placeholder="Es. 120,00"></label><label>Modalità di pagamento richiesta<select name="economic_mode" data-mi-economic><option value="FULL_PAYMENT" <?php selected( $economic_mode_selected, 'FULL_PAYMENT' ); ?>>Unica soluzione</option><option value="DEPOSIT_BALANCE" <?php selected( $economic_mode_selected, 'DEPOSIT_BALANCE' ); ?>>Caparra e saldo</option></select></label><div data-mi-payment hidden><label data-mi-deposit hidden>Percentuale caparra<input type="number" name="deposit_percentage" min="1" max="99" value="<?php echo esc_attr( min( 99, max( 1, absint( $value( '_mi_deposit_percentage', 30 ) ) ) ) ); ?>"></label><fieldset><legend>Metodi accettati</legend><label class="mi-check"><input type="checkbox" name="payment_methods[]" value="BANK_TRANSFER" <?php checked( in_array( 'BANK_TRANSFER', $payment_methods_selected, true ) ); ?>> Bonifico</label><label class="mi-check"><input type="checkbox" name="payment_methods[]" value="CARD" <?php checked( in_array( 'CARD', $payment_methods_selected, true ) ); ?>> Carta</label><label class="mi-check"><input type="checkbox" name="payment_methods[]" value="CASH" <?php checked( in_array( 'CASH', $payment_methods_selected, true ) ); ?>> Contanti</label></fieldset></div></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">8 di 8</span><h2><?php echo $is_draft ? 'Controlla e aggiorna la bozza' : ( $is_editing ? 'Controlla le modifiche' : 'Controlla e crea la bozza' ); ?></h2><div class="mi-wizard-review" data-mi-review></div><?php if ( $is_editing && ! $is_draft ) : ?><p>L’evento resterà attivo. Saranno aggiornati i suoi dati senza duplicare il modulo o il foglio Google e non partirà alcuna email.</p><button class="mi-primary" type="submit">Salva le modifiche</button><?php else : ?><p>Nulla sarà pubblicato automaticamente e non partirà alcuna email. Dopo il salvataggio passerai alla schermata “Attiva l’evento”.</p><button class="mi-primary" type="submit"><?php echo $is_draft ? 'Salva la bozza e vai ad Attiva l’evento' : 'Crea la bozza e vai ad Attiva l’evento'; ?></button><?php endif; ?></section>
		<div class="mi-wizard-actions"><button type="button" class="mi-secondary" data-mi-back disabled>Indietro</button><button type="button" class="mi-primary" data-mi-next>Continua</button></div></form><?php
	}

	private static function render_field_choices( $fields, $enabled = array(), $required = array() ) {
		foreach ( $fields as $key => $field ) {
			echo '<div class="mi-field-choice"><label class="mi-check"><input type="checkbox" name="participant_fields[]" value="' . esc_attr( $key ) . '" data-mi-field="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $enabled, true ), true, false ) . '> ' . esc_html( $field['label'] ) . '</label><label class="mi-check mi-field-required"><input type="checkbox" name="participant_required[]" value="' . esc_attr( $key ) . '" data-mi-required="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $required, true ), true, false ) . '> Obbligatorio</label><small>' . esc_html( $field['help'] ?? '' ) . '</small></div>';
		}
	}

	private static function format_date( $value ) {
		if ( ! $value ) return 'Data da definire';
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', (string) $value, wp_timezone() );
		return $date instanceof DateTimeImmutable ? wp_date( 'd/m/Y H:i', $date->getTimestamp(), wp_timezone() ) : (string) $value;
	}
	private static function date_badge( $value ) {
		if ( ! $value ) return array( 'month' => 'DATA', 'day' => '—' );
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', (string) $value, wp_timezone() );
		if ( ! $date instanceof DateTimeImmutable ) return array( 'month' => 'DATA', 'day' => '—' );
		return array(
			'month' => mb_strtoupper( rtrim( wp_date( 'M', $date->getTimestamp(), wp_timezone() ), '.' ) ),
			'day'   => wp_date( 'd', $date->getTimestamp(), wp_timezone() ),
		);
	}
	private static function date_timestamp( $value ) {
		if ( ! $value ) return 0;
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', (string) $value, wp_timezone() );
		return $date instanceof DateTimeImmutable ? $date->getTimestamp() : 0;
	}
	private static function is_past_event( $value ) {
		$timestamp = self::date_timestamp( $value );
		return $timestamp > 0 && $timestamp < current_time( 'timestamp', true );
	}
	private static function format_utc_date( $value ) {
		if ( ! $value ) return 'Data non disponibile';
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) $value, new DateTimeZone( 'UTC' ) );
		return $date instanceof DateTimeImmutable ? wp_date( 'd/m/Y H:i', $date->getTimestamp(), wp_timezone() ) : (string) $value;
	}
	private static function format_money( $cents ) { return number_format( max( 0, (int) $cents ) / 100, 2, ',', '.' ) . ' €'; }
	private static function group_cover_url( $activity_id, $size = 'large' ) {
		$activity_id = absint( $activity_id );
		if ( ! $activity_id ) return '';
		$cover_id = absint( get_post_meta( $activity_id, '_mi_group_cover_image_id', true ) );
		if ( ! $cover_id ) $cover_id = get_post_thumbnail_id( $activity_id );
		if ( $cover_id ) return esc_url( (string) wp_get_attachment_image_url( $cover_id, $size ) );
		$external_cover = esc_url( (string) get_post_meta( $activity_id, '_mi_group_cover_image_url', true ) );
		return $external_cover ?: esc_url( (string) get_post_meta( $activity_id, '_mi_group_logo_url', true ) );
	}
	private static function draft_configuration_complete( $event_id ) {
		$event = get_post( $event_id );
		if ( ! $event || ! trim( (string) $event->post_title ) ) return false;
		if ( ! self::valid_portal_date( (string) get_post_meta( $event_id, '_mi_event_starts_at', true ) ) ) return false;
		if ( ! self::valid_portal_date( (string) get_post_meta( $event_id, '_mi_registration_closes_at', true ) ) ) return false;
		if ( absint( get_post_meta( $event_id, '_mi_capacity', true ) ) < 1 ) return false;
		return true;
	}
	private static function draft_initial_step( $event_id ) {
		$event = get_post( $event_id );
		if ( ! $event || ! trim( (string) $event->post_title ) ) return 0;
		if ( ! self::valid_portal_date( (string) get_post_meta( $event_id, '_mi_event_starts_at', true ) ) || ! self::valid_portal_date( (string) get_post_meta( $event_id, '_mi_registration_closes_at', true ) ) || absint( get_post_meta( $event_id, '_mi_capacity', true ) ) < 1 ) return 3;
		return 7;
	}
	private static function event_outputs_panel( $event_id ) {
		if ( ! $event_id || ! MI_Access::can_access_event( $event_id ) ) return;
		$event = get_post( $event_id );
		$event_title = $event ? trim( (string) $event->post_title ) : '';
		if ( ! $event_title ) $event_title = 'Evento';
		$group_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		$group_title = $group_id ? trim( (string) get_the_title( $group_id ) ) : '';
		if ( ! $group_title ) $group_title = 'Parrocchia Sant’Eugenio';
		$group_logo_id = $group_id ? get_post_thumbnail_id( $group_id ) : 0;
		$group_logo = $group_logo_id ? (string) wp_get_attachment_image_url( $group_logo_id, 'thumbnail' ) : '';
		if ( ! $group_logo && $group_id ) $group_logo = esc_url( (string) get_post_meta( $group_id, '_mi_group_logo_url', true ) );
		$group_initials = implode( '', array_map( static function ( $word ) { return function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 ); }, array_slice( preg_split( '/\s+/', $group_title ) ?: array(), 0, 2 ) ) );
		$ticket_types = get_post_meta( $event_id, '_mi_ticket_types', true );
		if ( ! is_array( $ticket_types ) || empty( $ticket_types ) ) {
			$pricing_mode = (string) get_post_meta( $event_id, '_mi_pricing_mode', true );
			$fixed_price = max( 0, absint( get_post_meta( $event_id, '_mi_fixed_price_cents', true ) ) );
			update_post_meta( $event_id, '_mi_ticket_types', array( array( 'code' => 'standard', 'name' => 'ZERO' === $pricing_mode ? 'Iscrizione' : 'Quota di partecipazione', 'price_cents' => 'FIXED' === $pricing_mode ? $fixed_price : 0, 'max_per_order' => 20, 'capacity' => 0 ) ) );
		}
		$sheet_url = esc_url( (string) get_post_meta( $event_id, '_mi_operational_sheet_url', true ) );
		$sheet_missing = '1' === get_post_meta( $event_id, '_mi_sheet_missing', true );
		$registration_url = esc_url( (string) get_post_meta( $event_id, '_mi_registration_url', true ) );
		if ( ! $registration_url ) $registration_url = esc_url( MI_Shortcode::url_iscrizione( $event_id ) );
		$balance_url = esc_url( (string) get_post_meta( $event_id, '_mi_balance_url', true ) );
		$has_balance = 'DEPOSIT_BALANCE' === get_post_meta( $event_id, '_mi_economic_mode', true );
		$is_published = 'publish' === get_post_status( $event_id );
		$preview_url = wp_nonce_url( admin_url( 'admin-post.php?action=mi_anteprima_evento&event=' . $event_id ), 'mi_anteprima_evento_' . $event_id );
		echo '<section class="mi-event-outputs' . ( $is_published ? ' is-published' : '' ) . '" id="mi-produzioni-evento" tabindex="-1" data-mi-event-outputs>';
		echo '<header class="mi-event-outputs__header"><div class="mi-event-outputs__brand">' . ( $group_logo ? '<img src="' . esc_url( $group_logo ) . '" alt="">' : '<span aria-hidden="true">' . esc_html( strtoupper( $group_initials ?: 'SE' ) ) . '</span>' ) . '<div><small>Gruppo organizzatore</small><strong>' . esc_html( $group_title ) . '</strong></div></div><div class="mi-event-outputs__event"><span class="mi-portal-eyebrow">Passaggio conclusivo</span><h2>' . esc_html( $event_title ) . '</h2></div></header>';
		if ( ! $is_published ) {
			echo '<h3>Controlla e pubblica</h3>';
			echo '<p>Controlla il modulo senza renderlo pubblico. Quando confermi, WordPress pubblica l’evento e crea automaticamente il relativo foglio Google.</p><ol><li><strong>Visualizza anteprima</strong><a href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener noreferrer">Apri l’anteprima del modulo di iscrizione <span aria-hidden="true">↗</span><span class="screen-reader-text"> (si apre in una nuova scheda)</span></a></li><li><strong>Pubblica evento</strong><span>Crea il foglio Google e attiva il modulo di iscrizione.</span></li></ol>';
		}
		if ( ! $is_published && ( current_user_can( 'mi_publish_events' ) || current_user_can( 'manage_options' ) ) ) {
			echo '<form method="post"><input type="hidden" name="mi_portal_action" value="publish_event_portal"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
			wp_nonce_field( 'mi_portal_publish_event_' . $event_id, 'mi_portal_nonce' );
			echo '<button class="mi-primary" type="submit">Pubblica evento</button><span class="mi-action-progress" role="status" aria-live="polite" hidden></span></form>';
		} elseif ( $is_published ) {
			echo '<div class="mi-event-outputs__success"><span aria-hidden="true">✓</span><div><strong>L’evento è pubblicato</strong><p>Il modulo di iscrizione è pronto. Puoi condividerlo con le persone interessate.</p></div></div>';
			echo '<div class="mi-event-outputs__grid"><article class="mi-output-card mi-output-card--public"><span class="mi-output-card__audience">Per i partecipanti</span><h3>Condividi il modulo</h3><label>Link per le iscrizioni<div class="mi-output-copy"><input type="url" readonly value="' . esc_attr( $registration_url ) . '"><button type="button" class="mi-primary" data-mi-copy="' . esc_attr( $registration_url ) . '">Copia link</button></div></label><a class="mi-secondary mi-output-link" href="' . $registration_url . '" target="_blank" rel="noopener noreferrer">Apri il modulo <span aria-hidden="true">↗</span><span class="screen-reader-text"> (si apre in una nuova scheda)</span></a></article>';
			echo '<article class="mi-output-card mi-output-card--internal"><span class="mi-output-card__audience">Per gli operatori</span><h3>Gestisci le iscrizioni</h3><p>Consulta e aggiorna i dati nel foglio Google dell’evento.</p><div class="mi-output-document"><span aria-hidden="true">▦</span><div><strong>Foglio iscrizioni</strong><small>' . esc_html( $event_title ) . '</small></div></div>' . ( $sheet_missing ? '<p class="mi-portal-notice mi-portal-error"><strong>Foglio non disponibile.</strong> Usa il comando qui sotto per ricrearlo dai dati conservati.</p>' : ( $sheet_url ? '<a class="mi-secondary mi-output-link" href="' . $sheet_url . '" target="_blank" rel="noopener noreferrer">Apri il foglio Google <span aria-hidden="true">↗</span><span class="screen-reader-text"> (si apre in una nuova scheda)</span></a><details class="mi-output-disclosure"><summary>Mostra il collegamento al foglio</summary><label>Link del foglio Google<div class="mi-output-copy"><input type="url" readonly value="' . esc_attr( $sheet_url ) . '"><button type="button" class="mi-secondary" data-mi-copy="' . esc_attr( $sheet_url ) . '">Copia</button></div></label></details>' : '<p class="mi-portal-muted">Collegamento al foglio non ancora disponibile.</p>' ) ) . '</article></div>';
			echo '<details class="mi-output-integration"><summary><span><strong>Inserisci il modulo nel sito</strong><small>Codice e indicazioni per WordPress e Divi</small></span><em>Facoltativo</em></summary><div><label>Codice da inserire nella pagina<div class="mi-output-copy"><input type="text" readonly value="' . esc_attr( '[modulo_iscrizioni event="' . $event_id . '"]' ) . '"><button type="button" class="mi-secondary" data-mi-copy="' . esc_attr( '[modulo_iscrizioni event="' . $event_id . '"]' ) . '">Copia codice</button></div></label><p>In Divi puoi anche aggiungere il modulo <strong>“Modulo iscrizioni”</strong> e selezionare <strong>' . esc_html( $event_title ) . '</strong>.</p></div></details>';
			echo '<form method="post" class="mi-output-sheet-check"><input type="hidden" name="mi_portal_action" value="repair_event_sheet"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
			wp_nonce_field( 'mi_portal_repair_event_sheet_' . $event_id, 'mi_portal_nonce' );
			echo '<button type="submit" class="mi-secondary">Verifica o ricrea il foglio Google</button></form>';
			if ( $has_balance ) echo '<div class="mi-output-balance"><h3>Controllo stato e saldo</h3>' . ( $balance_url ? '<a class="mi-secondary mi-output-link" href="' . $balance_url . '" target="_blank" rel="noopener noreferrer">Apri la pagina stato e saldo <span aria-hidden="true">↗</span></a><label>Collegamento per il pulsante Saldo<div class="mi-output-copy"><input type="url" readonly value="' . esc_attr( $balance_url ) . '"><button type="button" class="mi-secondary" data-mi-copy="' . esc_attr( $balance_url ) . '">Copia</button></div></label>' : '<p>Collegamento non ancora disponibile.</p>' ) . '</div>';
		}
		echo '</section>';
	}
	private static function base_url() { return ! empty( $_GET['mi_portal'] ) ? add_query_arg( 'mi_portal', '1', home_url( '/' ) ) : get_permalink(); }
	private static function notice() {
		if ( empty( $_GET['mi_portal_message'] ) ) return;
		$error = ! empty( $_GET['mi_portal_error'] );
		$event_id = absint( $_GET['mi_portal_event'] ?? 0 );
		// Il pannello conclusivo contiene già stato e collegamenti dell'evento:
		// non ripetiamo sotto di esso il vecchio riepilogo di pubblicazione.
		if ( ! $error && $event_id && ! empty( $_GET['mi_portal_outputs'] ) && 'publish' === get_post_status( $event_id ) ) return;
		echo '<div class="mi-portal-notice ' . ( $error ? 'mi-portal-error' : 'mi-portal-success' ) . '"><strong>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mi_portal_message'] ) ) ) . '</strong>';
		if ( ! $error && $event_id && MI_Access::can_access_event( $event_id ) ) {
			$edit_url = get_edit_post_link( $event_id, 'raw' );
			$preview_url = wp_nonce_url( admin_url( 'admin-post.php?action=mi_anteprima_evento&event=' . $event_id ), 'mi_anteprima_evento_' . $event_id );
			$is_published = 'publish' === get_post_status( $event_id );
			echo '<p>' . ( $is_published ? 'L’evento #' . esc_html( $event_id ) . ' è pubblicato.' : 'La bozza #' . esc_html( $event_id ) . ' è stata salvata e resta non pubblicata.' ) . '</p><div class="mi-portal-notice__actions">';
			if ( ! $is_published && $edit_url ) echo '<a class="mi-primary" href="' . esc_url( $edit_url ) . '">Completa la bozza</a>';
			echo '<a class="mi-secondary" href="' . esc_url( $preview_url ) . '">Apri anteprima</a></div>';
		}
		echo '</div>';
	}
}
