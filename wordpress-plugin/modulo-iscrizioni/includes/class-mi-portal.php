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
		$description = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
		$event_id = wp_insert_post( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'draft', 'post_title' => $title, 'post_content' => $description, 'post_author' => get_current_user_id() ), true );
		if ( is_wp_error( $event_id ) ) return self::redirect_result( 'Non è stato possibile creare la bozza.', true );
		if ( $copy_id ) self::copy_configuration( $copy_id, $event_id );
		$activity_id = absint( $_POST['activity_id'] ?? 0 );
		if ( $activity_id && MI_Access::can_access_activity( $activity_id ) ) update_post_meta( $event_id, '_mi_activity_id', $activity_id );
		self::save_date( $event_id, '_mi_event_starts_at', $_POST['starts_at'] ?? '' );
		self::save_date( $event_id, '_mi_registration_opens_at', $_POST['opens_at'] ?? '' );
		self::save_date( $event_id, '_mi_registration_closes_at', $_POST['closes_at'] ?? '' );
		update_post_meta( $event_id, '_mi_event_location', mb_substr( sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ), 0, 180 ) );
		update_post_meta( $event_id, '_mi_capacity', min( 10000, max( 1, absint( $_POST['capacity'] ?? 30 ) ) ) );
		update_post_meta( $event_id, '_mi_waitlist_enabled', ! empty( $_POST['waitlist_enabled'] ) ? '1' : '0' );
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
		update_post_meta( $event_id, '_mi_pricing_mode', $pricing_mode );
		update_post_meta( $event_id, '_mi_fixed_price_cents', 'FIXED' === $pricing_mode && ! is_wp_error( $fixed_price ) ? $fixed_price : 0 );
		$economic_mode = strtoupper( sanitize_key( wp_unslash( $_POST['economic_mode'] ?? 'REGISTRATION_ONLY' ) ) );
		if ( ! in_array( $economic_mode, array( 'REGISTRATION_ONLY', 'PRICE_ONLY', 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ) $economic_mode = 'REGISTRATION_ONLY';
		update_post_meta( $event_id, '_mi_economic_mode', $economic_mode );
		update_post_meta( $event_id, '_mi_deposit_percentage', min( 99, max( 1, absint( $_POST['deposit_percentage'] ?? 30 ) ) ) );
		$payment_methods = array_values( array_intersect( array( 'BANK_TRANSFER', 'CARD', 'CASH' ), array_map( 'strtoupper', array_map( 'sanitize_key', (array) wp_unslash( $_POST['payment_methods'] ?? array() ) ) ) ) );
		update_post_meta( $event_id, '_mi_payment_methods', in_array( $economic_mode, array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ? $payment_methods : array() );
		$upload_warning = self::save_cover_upload( $event_id );
		self::redirect_result( $upload_warning ? 'Bozza creata; immagine non caricata: ' . $upload_warning : 'Bozza creata correttamente.', false, $event_id );
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
		$asset_version = rawurlencode( MI_VERSION );
		$page_title = ! empty( $_GET['mi_status'] ) ? 'Stato della prenotazione' : 'Gestione iscrizioni';
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

	private static function save_cover_upload( $event_id ) {
		if ( empty( $_FILES['cover_image']['name'] ) ) return '';
		$file = $_FILES['cover_image'];
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) return 'caricamento incompleto';
		if ( (int) $file['size'] > 5 * MB_IN_BYTES ) return 'file superiore a 5 MB';
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

	private static function parse_euro_cents( $value ) {
		$value = preg_replace( '/[^0-9,.]/', '', (string) $value );
		if ( '' === $value ) return 0;
		if ( false !== strpos( $value, ',' ) ) $value = str_replace( '.', '', $value );
		$value = str_replace( ',', '.', $value );
		if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/', $value ) ) return new WP_Error( 'invalid_amount' );
		return (int) round( (float) $value * 100 );
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
		if ( ! empty( $_GET['mi_status'] ) ) return self::public_status_view();
		if ( ! empty( $_GET['mi_cancel_participant'] ) && ! empty( $_GET['mi_cancel_token'] ) ) return self::cancellation_view();
		if ( ! is_user_logged_in() ) return self::login_view();
		if ( ! current_user_can( 'mi_portal_access' ) && ! current_user_can( 'manage_options' ) ) return '<div class="mi-portal-empty"><h2>C’è qualcuno qui…?</h2><p>Il tuo account non è abilitato al servizio iscrizioni.</p></div>';
		$view = sanitize_key( wp_unslash( $_GET['mi_portal_view'] ?? 'manage' ) );
		$can_create = current_user_can( 'mi_create_events' ) || current_user_can( 'manage_options' );
		ob_start();
		?><main class="mi-portal"><header class="mi-portal-header"><div><span class="mi-portal-eyebrow">Servizio iscrizioni</span><h1>Gestione eventi</h1></div><a class="mi-portal-logout" href="<?php echo esc_url( wp_logout_url( self::base_url() ) ); ?>">Esci</a></header>
		<nav class="mi-portal-switcher" aria-label="Vista portale"><?php if ( $can_create ) : ?><a class="<?php echo 'create' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'create' ) ); ?>">Crea evento</a><?php endif; ?><a class="<?php echo 'create' !== $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'mi_portal_view', 'manage' ) ); ?>"><?php echo esc_html( self::manage_label() ); ?></a></nav>
		<?php self::notice(); ?>
		<?php if ( 'create' === $view && $can_create ) self::create_view(); else self::manage_view(); ?>
		</main><?php
		return ob_get_clean();
	}

	private static function public_status_view() {
		$order_code = sanitize_text_field( wp_unslash( $_GET['ordine'] ?? '' ) );
		$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
		$email = '';
		$result = null;
		if ( $order_code && $token ) {
			$result = MI_Registration_Service::public_status( $order_code, '', $token );
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
				$result = MI_Registration_Service::public_status( $order_code, $email );
			}
		}
		ob_start(); ?>
		<main class="mi-portal mi-public-status"><section class="mi-portal-login"><span class="mi-portal-eyebrow">Consultazione riservata</span><h1>Stato della prenotazione</h1><p>Controlla conferma, pagamenti registrati e saldo residuo. Non vengono mostrati dati personali o note interne.</p>
		<?php if ( is_array( $result ) ) : ?><div class="mi-status-result"><p class="mi-status-event"><?php echo esc_html( $result['event_title'] ); ?></p><p class="mi-booking-detail__code">Codice <code><?php echo esc_html( $result['order_code'] ); ?></code></p><div class="mi-status-grid"><p><span>Prenotazione</span><strong><?php echo esc_html( $result['status'] ); ?></strong></p><p><span>Pagamento</span><strong><?php echo esc_html( $result['payment_status'] ); ?></strong></p><p><span>Versato</span><strong><?php echo esc_html( self::format_money( $result['paid_cents'] ) ); ?></strong></p><p><span>Saldo residuo</span><strong><?php echo esc_html( self::format_money( $result['balance_cents'] ) ); ?></strong></p></div><?php if ( ! empty( $result['payment_deadline'] ) ) : ?><p class="mi-portal-muted">Scadenza indicata: <?php echo esc_html( self::format_utc_date( $result['payment_deadline'] ) ); ?></p><?php endif; ?></div>
		<?php elseif ( is_wp_error( $result ) ) : ?><div class="mi-portal-notice mi-portal-error"><?php echo esc_html( $result->get_error_message() ); ?></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( add_query_arg( 'mi_status', '1', home_url( '/' ) ) ); ?>"><input type="hidden" name="mi_portal_action" value="public_status_lookup"><input type="hidden" name="mi_status_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mi_public_status' ) ); ?>"><label>Codice prenotazione<input name="order_code" value="<?php echo esc_attr( $order_code ); ?>" maxlength="32" autocomplete="off" required></label><label>Email del referente<input type="email" name="email" value="<?php echo esc_attr( $email ); ?>" autocomplete="email" required></label><button type="submit">Controlla lo stato</button></form></section></main><?php
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
		ob_start(); ?><section class="mi-portal mi-portal-login"><span class="mi-portal-eyebrow">Area riservata</span><h1>Gestione iscrizioni</h1><p>Accedi come segretario o operatore. Se sei già autenticato in WordPress entrerai direttamente.</p><?php wp_login_form( array( 'redirect' => self::base_url(), 'label_username' => 'Utente', 'label_password' => 'Parola d’accesso', 'label_log_in' => 'Accedi', 'remember' => true ) ); ?></section><?php return ob_get_clean();
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
		foreach ( $all_events as $event ) {
			$published = (array) ( $published_summaries[ $event->ID ] ?? array() );
			$starts_at = (string) ( $published['event_starts_at'] ?? get_post_meta( $event->ID, '_mi_event_starts_at', true ) );
			$start_timestamps[ $event->ID ] = self::date_timestamp( $starts_at );
			if ( self::is_past_event( $starts_at ) ) $past_events[] = $event; else $current_events[] = $event;
		}
		$events = $show_past ? $past_events : $current_events;
		usort( $events, static function ( $a, $b ) use ( $start_timestamps, $show_past ) {
			$a_time = (int) ( $start_timestamps[ $a->ID ] ?? 0 );
			$b_time = (int) ( $start_timestamps[ $b->ID ] ?? 0 );
			if ( ! $a_time ) $a_time = $show_past ? 0 : PHP_INT_MAX;
			if ( ! $b_time ) $b_time = $show_past ? 0 : PHP_INT_MAX;
			return $show_past ? $b_time <=> $a_time : $a_time <=> $b_time;
		} );
		$ids = array_map( 'absint', wp_list_pluck( $events, 'ID' ) );
		$counts = array();
		if ( $ids ) {
			$safe_ids = implode( ',', $ids );
			foreach ( $wpdb->get_results( "SELECT event_id,confirmed_count FROM {$wpdb->prefix}mi_event_counters WHERE event_id IN ({$safe_ids})", ARRAY_A ) as $counter ) $counts[ (int) $counter['event_id'] ] = (int) $counter['confirmed_count'];
		}
		$base_url = self::base_url();
		echo '<section><h2>' . ( $show_past ? 'Eventi passati' : 'Eventi' ) . '</h2>';
		if ( $events ) echo '<div class="mi-event-grid">';
		foreach ( $events as $event ) {
			$count = (int) ( $counts[ $event->ID ] ?? 0 );
			$published = (array) ( $published_summaries[ $event->ID ] ?? array() );
			$capacity = max( 1, absint( $published['capacity'] ?? get_post_meta( $event->ID, '_mi_capacity', true ) ) );
			$starts_at = (string) ( $published['event_starts_at'] ?? get_post_meta( $event->ID, '_mi_event_starts_at', true ) );
			$closes_at = (string) ( $published['closes_at'] ?? get_post_meta( $event->ID, '_mi_registration_closes_at', true ) );
			$event_title = sanitize_text_field( (string) ( $published['title'] ?? $event->post_title ) );
			$activity_name = sanitize_text_field( (string) ( $published['activity'] ?? '' ) );
			if ( ! $activity_name ) { $activity_id = absint( get_post_meta( $event->ID, '_mi_activity_id', true ) ); if ( $activity_id ) $activity_name = get_the_title( $activity_id ); }
			$cover_image = esc_url( (string) ( $published['cover_image'] ?? get_the_post_thumbnail_url( $event->ID, 'thumbnail' ) ) );
			$date_badge = self::date_badge( $starts_at );
			$occupancy_percentage = min( 100, max( 0, (int) round( ( $count / $capacity ) * 100 ) ) );
			$url_args = array( 'mi_portal_view' => 'manage', 'mi_portal_event' => $event->ID );
			if ( $show_past ) $url_args['mi_portal_history'] = '1';
			$url = add_query_arg( $url_args, $base_url );
			echo '<a class="mi-event-card" href="' . esc_url( $url ) . '"><span class="mi-event-card__date"><small>' . esc_html( $date_badge['month'] ) . '</small><strong>' . esc_html( $date_badge['day'] ) . '</strong></span><span class="mi-event-card__content"><span class="mi-event-card__image">';
			if ( $cover_image ) echo '<img src="' . esc_url( $cover_image ) . '" alt="">';
			echo '</span><span class="mi-event-card__identity"><strong>' . esc_html( $event_title ) . '</strong>';
			if ( $activity_name ) echo '<small>' . esc_html( $activity_name ) . '</small>';
			$status_label = 'publish' === $event->post_status ? ( $show_past ? 'Concluso' : 'Attivo' ) : 'Bozza';
			echo '<small>' . esc_html( self::format_date( $starts_at ) ) . '</small></span><span class="mi-event-card__footer"><span class="mi-event-card__capacity"><small>Posti occupati</small><strong>' . esc_html( $count . ' / ' . $capacity ) . '</strong><i aria-hidden="true"><b style="width:' . esc_attr( $occupancy_percentage ) . '%"></b></i></span><span class="mi-event-card__status"><strong>' . esc_html( $status_label ) . '</strong><small>Scadenza: ' . esc_html( self::format_date( $closes_at ) ) . '</small></span></span></span></a>';
		}
		if ( $events ) echo '</div>'; else echo '<p class="mi-portal-muted">' . ( $show_past ? 'Non ci sono eventi passati.' : 'Non ci sono eventi in corso, futuri o in bozza.' ) . '</p>';
		echo '</section>';
		if ( $ids ) {
			$selected = absint( $_GET['mi_portal_event'] ?? 0 );
			if ( $selected && in_array( $selected, $ids, true ) ) self::registrations_view( $selected ); else self::registrations_view( 0, $ids );
		}
		$history_url = $show_past
			? add_query_arg( 'mi_portal_view', 'manage', $base_url )
			: add_query_arg( array( 'mi_portal_view' => 'manage', 'mi_portal_history' => '1' ), $base_url );
		echo '<p class="mi-event-history-link"><a href="' . esc_url( $history_url ) . '">' . ( $show_past ? 'Torna agli eventi attuali' : 'Visualizza eventi passati' ) . '</a></p>';
	}

	private static function registrations_view( $event_id = 0, $event_ids = array() ) {
		global $wpdb;
		if ( $event_id ) { $where = $wpdb->prepare( 'r.event_id=%d', $event_id ); } else { $safe = array_values( array_filter( array_map( 'absint', $event_ids ) ) ); $where = 'r.event_id IN (' . implode( ',', $safe ) . ')'; }
		$rows = $wpdb->get_results( "SELECT r.id registration_id,r.event_id,r.created_at,r.buyer_email,p.id participant_id,p.first_name,p.last_name,events.post_title event_title FROM {$wpdb->prefix}mi_registrations r JOIN {$wpdb->prefix}mi_participants p ON p.registration_id=r.id JOIN {$wpdb->posts} events ON events.ID=r.event_id WHERE {$where} ORDER BY r.created_at DESC,p.id ASC LIMIT 10", ARRAY_A );
		$base_url = self::base_url();
		if ( $event_id ) $base_url = add_query_arg( 'mi_portal_event', $event_id, $base_url );
		if ( ! empty( $_GET['mi_portal_history'] ) ) $base_url = add_query_arg( 'mi_portal_history', '1', $base_url );
		$list_title = 'Ultime iscrizioni';
		if ( $event_id ) {
			$event_title = sanitize_text_field( get_the_title( $event_id ) );
			if ( $event_title ) $list_title .= ' — ' . $event_title;
		}
		echo '<section><h2>' . esc_html( $list_title ) . '</h2><div class="mi-booking-list">';
		foreach ( $rows as $index => $row ) { $url = add_query_arg( array( 'mi_portal_view' => 'manage', 'mi_portal_booking' => $row['registration_id'] ), $base_url ); echo '<a data-mi-portal-booking-open href="' . esc_url( $url ) . '"><span>' . esc_html( $index + 1 ) . '</span><strong>' . esc_html( $row['first_name'] . ' ' . $row['last_name'] ) . '</strong><small>' . esc_html( $row['event_title'] . ' · ' . self::format_utc_date( $row['created_at'] ) . ' · ' . $row['buyer_email'] ) . '</small></a>'; }
		if ( ! $rows ) echo '<p class="mi-portal-muted">Nessuna iscrizione presente.</p>';
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
		if ( $cover_image ) echo '<img class="mi-booking-detail__cover" src="' . esc_url( $cover_image ) . '" alt="">';
		echo '<header class="mi-booking-detail__header">';
		if ( $activity_name ) echo '<p class="mi-booking-detail__activity"><span>Attività</span>' . esc_html( $activity_name ) . '</p>';
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

	private static function create_view() {
		$models = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => 30, 'orderby' => 'date', 'order' => 'DESC' ) );
		$activities = get_posts( array( 'post_type' => MI_Event_Post_Type::ACTIVITY_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$catalog = MI_Field_Schema::catalog();
		?><form class="mi-event-wizard" method="post" enctype="multipart/form-data"><input type="hidden" name="mi_portal_action" value="create_event"><?php wp_nonce_field( 'mi_portal_create_event', 'mi_portal_nonce' ); ?>
		<section class="mi-wizard-step is-active"><span class="mi-portal-eyebrow">1 di 8</span><h2>Come si chiama?</h2><label>Titolo dell’evento<input name="title" required maxlength="180" placeholder="Es. Pellegrinaggio ad Assisi"></label><label>Gruppo o settore organizzatore <small>(facoltativo)</small><select name="activity_id"><option value="">Nessuno</option><?php foreach ( $activities as $activity ) : ?><option value="<?php echo esc_attr( $activity->ID ); ?>"><?php echo esc_html( $activity->post_title ); ?></option><?php endforeach; ?></select></label><label>Oppure riutilizza la configurazione di un evento precedente<select name="copy_event_id"><option value="">No, parto da zero</option><?php foreach ( $models as $model ) : ?><option value="<?php echo esc_attr( $model->ID ); ?>"><?php echo esc_html( $model->post_title ); ?></option><?php endforeach; ?></select></label><p class="mi-portal-muted">Vengono copiate solo impostazioni e domande, mai iscrizioni o dati personali.</p></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">2 di 8</span><h2>Cosa devono sapere le persone?</h2><label>Luogo<input name="location" maxlength="180" placeholder="Es. Basilica di Sant’Eugenio"></label><label>Presentazione e informazioni<textarea name="description" rows="7" placeholder="Descrivi programma, orari, cosa portare e ogni informazione utile."></textarea></label></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">3 di 8</span><h2>Immagine</h2><label>Immagine in evidenza <small>(facoltativa)</small><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"></label><p class="mi-portal-muted">JPG, PNG o WebP, massimo 5 MB. Comparirà nella scheda e nella pagina dell’evento.</p></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">4 di 8</span><h2>Date e posti</h2><div class="mi-wizard-grid"><label>Apertura iscrizioni<input type="datetime-local" name="opens_at"></label><label>Data e ora di inizio<input type="datetime-local" name="starts_at" required></label><label>Chiusura iscrizioni<input type="datetime-local" name="closes_at" required></label><label>Posti disponibili<input type="number" min="1" max="10000" name="capacity" value="30" required></label></div><label class="mi-check"><input type="checkbox" name="waitlist_enabled" value="1"> Attiva automaticamente la lista d’attesa a esaurimento posti</label></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">5 di 8</span><h2>Quali dati chiediamo?</h2><p>Nome e cognome sono sempre obbligatori per ogni partecipante.</p><div class="mi-field-choice-list"><?php foreach ( $catalog as $key => $field ) : ?><div class="mi-field-choice"><label class="mi-check"><input type="checkbox" name="participant_fields[]" value="<?php echo esc_attr( $key ); ?>" data-mi-field="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $field['label'] ); ?></label><label class="mi-check mi-field-required"><input type="checkbox" name="participant_required[]" value="<?php echo esc_attr( $key ); ?>" data-mi-required="<?php echo esc_attr( $key ); ?>"> Obbligatorio</label><small><?php echo esc_html( $field['help'] ?? '' ); ?></small></div><?php endforeach; ?></div><fieldset><legend>Dati aggiuntivi da compilare</legend><label class="mi-check"><input type="radio" name="participant_extra_scope" value="ONE" checked> Solo per uno degli iscritti (il primo, modificabile)</label><label class="mi-check"><input type="radio" name="participant_extra_scope" value="ALL"> Per tutti gli iscritti</label></fieldset></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">6 di 8</span><h2>Domande particolari</h2><p class="mi-portal-muted">Aggiungi solo ciò che serve davvero. Puoi lasciare tutto vuoto.</p><div class="mi-custom-questions"><?php for ( $i = 0; $i < 4; $i++ ) : ?><div class="mi-custom-question"><label>Domanda <?php echo esc_html( $i + 1 ); ?><input name="custom_question_label[]" maxlength="120" placeholder="Es. Allergie da segnalare"></label><label>Risposta<select name="custom_question_type[]"><option value="text">Breve</option><option value="textarea">Testo libero</option><option value="date">Data</option><option value="email">Email</option><option value="tel">Telefono</option></select></label><label class="mi-check"><input type="checkbox" name="custom_question_required[<?php echo esc_attr( $i ); ?>]" value="1"> Obbligatoria</label></div><?php endfor; ?></div><label class="mi-check"><input type="checkbox" name="special_requests_enabled" value="1"> Mostra uno spazio facoltativo per richieste particolari</label><label class="mi-check"><input type="checkbox" name="marketing_enabled" value="1"> Mostra “Comunicazioni su future iniziative”</label></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">7 di 8</span><h2>Servizi e quota</h2><label class="mi-check"><input type="checkbox" name="overnight" value="1" data-mi-overnight> È previsto il pernottamento</label><div data-mi-accommodations hidden><h3>Tipi di alloggio</h3><?php foreach ( array( 'SINGOLA' => 'Singola', 'DOPPIA_SEPARATI' => 'Doppia con letti separati', 'DOPPIA_MATRIMONIALE' => 'Doppia matrimoniale', 'TRIPLA' => 'Tripla', 'MULTIPLA' => 'Multipla' ) as $code => $label ) : ?><label class="mi-check"><input type="checkbox" name="accommodations[]" value="<?php echo esc_attr( $code ); ?>"> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></div><label>Prezzo<select name="pricing_mode" data-mi-pricing><option value="NONE">Nessun prezzo</option><option value="ZERO">Evento gratuito</option><option value="FIXED">Quota uguale per tutti</option></select></label><label data-mi-fixed-price hidden>Quota per partecipante (€)<input name="fixed_price" inputmode="decimal" placeholder="Es. 120,00"></label><label>Modalità di pagamento richiesta<select name="economic_mode" data-mi-economic><option value="REGISTRATION_ONLY">Nessun pagamento previsto</option><option value="PRICE_ONLY">Prezzo solamente informativo</option><option value="FULL_PAYMENT">Pagamento completo richiesto</option><option value="DEPOSIT_BALANCE">Caparra richiesta, saldo successivo</option></select></label><div data-mi-payment hidden><label data-mi-deposit hidden>Percentuale caparra<input type="number" name="deposit_percentage" min="1" max="99" value="30"></label><fieldset><legend>Metodi accettati</legend><label class="mi-check"><input type="checkbox" name="payment_methods[]" value="BANK_TRANSFER"> Bonifico</label><label class="mi-check"><input type="checkbox" name="payment_methods[]" value="CARD"> Carta</label><label class="mi-check"><input type="checkbox" name="payment_methods[]" value="CASH"> Contanti</label></fieldset></div></section>
		<section class="mi-wizard-step"><span class="mi-portal-eyebrow">8 di 8</span><h2>Controlla e crea la bozza</h2><div class="mi-wizard-review" data-mi-review></div><p>Nulla sarà pubblicato automaticamente e non partirà alcuna email. Dopo la creazione vedrai una conferma chiara e i collegamenti alla bozza e all’anteprima.</p><button class="mi-primary" type="submit">Crea la bozza dell’evento</button></section>
		<div class="mi-wizard-actions"><button type="button" class="mi-secondary" data-mi-back disabled>Indietro</button><button type="button" class="mi-primary" data-mi-next>Continua</button></div></form><?php
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
	private static function base_url() { return ! empty( $_GET['mi_portal'] ) ? add_query_arg( 'mi_portal', '1', home_url( '/' ) ) : get_permalink(); }
	private static function notice() {
		if ( empty( $_GET['mi_portal_message'] ) ) return;
		$error = ! empty( $_GET['mi_portal_error'] );
		$event_id = absint( $_GET['mi_portal_event'] ?? 0 );
		echo '<div class="mi-portal-notice ' . ( $error ? 'mi-portal-error' : 'mi-portal-success' ) . '"><strong>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mi_portal_message'] ) ) ) . '</strong>';
		if ( ! $error && $event_id && MI_Access::can_access_event( $event_id ) ) {
			$edit_url = get_edit_post_link( $event_id, 'raw' );
			$preview_url = wp_nonce_url( admin_url( 'admin-post.php?action=mi_anteprima_evento&event=' . $event_id ), 'mi_anteprima_evento_' . $event_id );
			echo '<p>La bozza #' . esc_html( $event_id ) . ' è stata salvata e resta non pubblicata.</p><div class="mi-portal-notice__actions">';
			if ( $edit_url ) echo '<a class="mi-primary" href="' . esc_url( $edit_url ) . '">Completa la bozza</a>';
			echo '<a class="mi-secondary" href="' . esc_url( $preview_url ) . '">Apri anteprima</a></div>';
		}
		echo '</div>';
	}
}
