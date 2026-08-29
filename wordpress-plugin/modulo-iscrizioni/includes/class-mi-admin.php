<?php

defined( 'ABSPATH' ) || exit;

final class MI_Admin {
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mi_archive_event', array( __CLASS__, 'archive_event' ) );
		add_action( 'admin_post_mi_export_registrations', array( __CLASS__, 'export_registrations' ) );
		add_action( 'admin_post_mi_retry_workspace', array( __CLASS__, 'riaccoda_workspace' ) );
		add_action( 'admin_post_mi_add_payment', array( __CLASS__, 'add_payment' ) );
		add_action( 'admin_post_mi_export_payments', array( __CLASS__, 'export_payments' ) );
		add_action( 'admin_post_mi_cancel_registration', array( __CLASS__, 'cancel_registration' ) );
		add_action( 'admin_post_mi_seed_demo_registrations', array( __CLASS__, 'seed_demo_registrations' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'event_row_actions' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_publication' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'publication_notice' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );
	}

	public static function dashboard_widget() {
		if ( ! current_user_can( 'mi_manage_events' ) ) return;
		wp_add_dashboard_widget( 'mi_dashboard_service', 'Servizio moduli iscrizioni', array( __CLASS__, 'render_dashboard_widget' ) );
	}

	public static function render_dashboard_widget() {
		$base = 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE;
		?>
		<p>Gestisci gli eventi e consulta le iscrizioni dei gruppi che ti sono stati assegnati.</p>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( $base ) ); ?>">Apri il servizio moduli</a>
		<?php if ( current_user_can( 'mi_view_registrations' ) ) : ?>
			<a class="button" href="<?php echo esc_url( admin_url( $base . '&page=mi-registrations' ) ); ?>">Vedi le iscrizioni</a>
		<?php endif; ?></p>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE ) ); ?>">Crea un nuovo evento</a></li>
			<?php if ( current_user_can( 'mi_view_registrations' ) ) : ?><li><a href="<?php echo esc_url( admin_url( $base . '&page=mi-email-outbox' ) ); ?>">Controlla la coda email</a></li><?php endif; ?>
			<?php if ( current_user_can( 'mi_manage_payments' ) ) : ?><li><a href="<?php echo esc_url( admin_url( $base . '&page=mi-payments' ) ); ?>">Consulta i pagamenti</a></li><?php endif; ?>
		</ul>
		<p><small>Ogni delegato vede esclusivamente i gruppi autorizzati dall’amministratore.</small></p>
		<?php
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE,
			'Iscrizioni',
			'Iscrizioni',
			'mi_view_registrations',
			'mi-registrations',
			array( __CLASS__, 'registrations_page' )
		);
		add_submenu_page(
			'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE,
			'Coda email',
			'Coda email',
			'mi_view_registrations',
			'mi-email-outbox',
			array( __CLASS__, 'outbox_page' )
		);
		add_submenu_page( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE, 'Pagamenti', 'Pagamenti', 'mi_view_registrations', 'mi-payments', array( __CLASS__, 'payments_page' ) );
		add_submenu_page( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE, 'Dati dimostrativi', 'Dati dimostrativi', 'manage_options', 'mi-demo-data', array( __CLASS__, 'demo_data_page' ) );
	}

	public static function demo_data_page() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		$events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => array( 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$created = absint( $_GET['mi_demo_created'] ?? 0 );
		$error = sanitize_text_field( wp_unslash( $_GET['mi_demo_error'] ?? '' ) );
		?><div class="wrap"><h1>Dati dimostrativi</h1>
		<p>Crea iscrizioni inventate per collaudare elenchi e schede senza pubblicare l’evento. Lo strumento funziona soltanto su eventi in bozza e con le email in modalità Anteprima.</p>
		<?php if ( $created ) : ?><div class="notice notice-success"><p><?php echo esc_html( $created ); ?> iscrizioni dimostrative create.</p></div><?php endif; ?>
		<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="mi_seed_demo_registrations"><?php wp_nonce_field( 'mi_seed_demo_registrations' ); ?>
		<table class="form-table"><tr><th><label for="mi_demo_event_id">Evento in bozza</label></th><td><select id="mi_demo_event_id" name="event_id" required><option value="">— Seleziona —</option><?php foreach ( $events as $event ) : ?><option value="<?php echo esc_attr( $event->ID ); ?>"><?php echo esc_html( $event->post_title . ' (ID ' . $event->ID . ')' ); ?></option><?php endforeach; ?></select></td></tr>
		<tr><th><label for="mi_demo_count">Numero di iscrizioni</label></th><td><input id="mi_demo_count" name="count" type="number" min="1" max="6" value="3"></td></tr></table>
		<?php submit_button( 'Crea iscrizioni dimostrative' ); ?></form></div><?php
	}

	public static function seed_demo_registrations() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		check_admin_referer( 'mi_seed_demo_registrations' );
		if ( 'ANTEPRIMA' !== MI_Spedizione_Email::modalita() ) wp_die( esc_html__( 'Riporta prima le email in modalità Anteprima.', 'modulo-iscrizioni' ) );
		$event_id = absint( $_POST['event_id'] ?? 0 );
		$count = min( 6, max( 1, absint( $_POST['count'] ?? 3 ) ) );
		$event_post = get_post( $event_id );
		if ( ! $event_post || MI_Event_Post_Type::EVENT_TYPE !== $event_post->post_type || ! in_array( $event_post->post_status, array( 'draft', 'private' ), true ) ) wp_die( esc_html__( 'Seleziona un evento in bozza.', 'modulo-iscrizioni' ) );
		$event = MI_Registration_Service::public_event( $event_id, true );
		if ( is_wp_error( $event ) ) wp_die( esc_html( $event->get_error_message() ) );
		$names = array( array( 'Totuccio', 'Mangiafichi' ), array( 'Fiomena', 'Rossi' ), array( 'Gelsomina', 'Bianchi' ), array( 'Pasqualino', 'Verdi' ), array( 'Concetta', 'Blu' ), array( 'Arcibaldo', 'Girasole' ) );
		$ticket = reset( $event['ticket_types'] );
		$created = 0; $errors = array();
		for ( $index = 0; $index < $count; $index++ ) {
			$name = $names[ $index ];
			$email = strtolower( sanitize_title( $name[0] . '.' . $name[1] ) ) . '.' . $event_id . '@example.invalid';
			$phone = '+39 320 000 ' . str_pad( (string) ( $event_id % 1000 * 10 + $index ), 4, '0', STR_PAD_LEFT );
			$fields = self::demo_participant_fields( (array) $event['participant_fields'], $index, $email, $phone );
			$payload = array( 'started_at' => time() - 5, 'tickets' => array( sanitize_key( $ticket['code'] ) => 1 ), 'order_options' => array(), 'participants' => array( array( 'ticket_type_code' => sanitize_key( $ticket['code'] ), 'ticket_index' => 1, 'first_name' => $name[0], 'last_name' => $name[1], 'fields' => $fields, 'options' => array() ) ), 'buyer' => array( 'first_name' => $name[0], 'last_name' => $name[1], 'email' => $email, 'phone' => $phone ), 'special_requests' => 'Iscrizione dimostrativa generata dal pannello amministrativo.', 'privacy_accepted' => true, 'marketing_accepted' => false );
			$key = 'admin-demo-' . $event_id . '-' . gmdate( 'YmdHis' ) . '-' . $index . '-' . wp_generate_password( 8, false, false );
			$result = MI_Registration_Service::create( $event_id, $payload, $key, true, 'ADMIN_DEMO' );
			if ( is_wp_error( $result ) ) $errors[] = $result->get_error_message(); else $created++;
		}
		$url = add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-demo-data', 'mi_demo_created' => $created ), admin_url( 'edit.php' ) );
		if ( $errors ) $url = add_query_arg( 'mi_demo_error', implode( ' ', array_unique( $errors ) ), $url );
		wp_safe_redirect( $url ); exit;
	}

	private static function demo_participant_fields( $definitions, $index, $email, $phone ) {
		$result = array();
		foreach ( $definitions as $field ) {
			$key = sanitize_key( $field['key'] ?? '' ); $type = $field['type'] ?? 'text';
			if ( 'email' === $type ) $value = $email;
			elseif ( 'tel' === $type ) $value = $phone;
			elseif ( 'date' === $type ) $value = 'future' === ( $field['date_rule'] ?? '' ) ? '2030-12-31' : '198' . ( $index + 1 ) . '-0' . ( ( $index % 6 ) + 1 ) . '-15';
			elseif ( 'select' === $type ) $value = (string) ( reset( $field['options'] ) ?: '' );
			elseif ( 'nationality' === $key ) $value = 'Italiana';
			elseif ( 'document_number' === $key ) $value = 'DEMO-' . ( $index + 1 );
			elseif ( 'document_country' === $key ) $value = 'Italia';
			elseif ( 'postal_address' === $key ) $value = 'Via Dimostrativa ' . ( $index + 1 ) . ', 00100 Roma';
			else $value = 'Dato dimostrativo ' . ( $index + 1 );
			if ( ! empty( $field['required'] ) || in_array( $type, array( 'email', 'tel' ), true ) ) $result[ $key ] = $value;
		}
		return $result;
	}

	public static function add_payment() {
		if ( ! current_user_can( 'mi_manage_payments' ) ) { wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) ); }
		$registration_id = isset( $_POST['registration_id'] ) ? absint( $_POST['registration_id'] ) : 0;
		check_admin_referer( 'mi_add_payment_' . $registration_id );
		global $wpdb;
		$registration = $wpdb->get_row( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}mi_registrations WHERE id = %d", $registration_id ), ARRAY_A );
		if ( ! $registration || ! MI_Access::can_access_event( (int) $registration['event_id'] ) ) { wp_die( esc_html__( 'Iscrizione non accessibile.', 'modulo-iscrizioni' ) ); }
		$source = strtoupper( sanitize_key( wp_unslash( $_POST['payment_source'] ?? '' ) ) );
		$kind = strtoupper( sanitize_key( wp_unslash( $_POST['installment_kind'] ?? 'FULL' ) ) );
		$transaction = strtoupper( sanitize_key( wp_unslash( $_POST['transaction_kind'] ?? 'PAYMENT' ) ) );
		$amount = self::parse_importo_centesimi( wp_unslash( $_POST['amount'] ?? '' ) );
		$effective_raw = sanitize_text_field( wp_unslash( $_POST['effective_at'] ?? '' ) );
		$effective_at = current_time( 'mysql', true );
		if ( $effective_raw ) {
			$effective_date = DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i', $effective_raw, wp_timezone() );
			if ( ! $effective_date || $effective_date->format( 'Y-m-d\\TH:i' ) !== $effective_raw ) { wp_die( esc_html__( 'Data effettiva non valida.', 'modulo-iscrizioni' ) ); }
			$effective_at = $effective_date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		}
		if ( ! in_array( $source, array( 'BANK_TRANSFER', 'CARD', 'CASH' ), true ) || ! in_array( $kind, array( 'DEPOSIT', 'BALANCE', 'FULL', 'OTHER' ), true ) || ! in_array( $transaction, array( 'PAYMENT', 'REFUND' ), true ) || null === $amount ) { wp_die( esc_html__( 'Dati del versamento non validi.', 'modulo-iscrizioni' ) ); }
		$external_reference = sanitize_text_field( wp_unslash( $_POST['external_reference'] ?? '' ) );
		$administrative_note = sanitize_textarea_field( wp_unslash( $_POST['administrative_note'] ?? '' ) );
		if ( self::contiene_numero_carta( $external_reference ) || self::contiene_numero_carta( $administrative_note ) ) { wp_die( esc_html__( 'Non inserire numeri completi di carta.', 'modulo-iscrizioni' ) ); }
		$wpdb->query( 'START TRANSACTION' );
		$locked = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, initial_due_cents, payment_deadline_at FROM {$wpdb->prefix}mi_registrations WHERE id = %d FOR UPDATE", $registration_id ), ARRAY_A );
		if ( ! $locked ) { $wpdb->query( 'ROLLBACK' ); wp_die( esc_html__( 'Iscrizione non disponibile.', 'modulo-iscrizioni' ) ); }
		if ( 'PAYMENT' === $transaction && in_array( $locked['status'], array( 'CANCELLED', 'EXPIRED' ), true ) ) { $wpdb->query( 'ROLLBACK' ); wp_die( esc_html__( 'Non è possibile registrare un nuovo versamento su un’iscrizione annullata o scaduta.', 'modulo-iscrizioni' ) ); }
		if ( 'REFUND' === $transaction && $amount > self::totale_pagamenti( $registration_id ) ) { $wpdb->query( 'ROLLBACK' ); wp_die( esc_html__( 'Il rimborso non può superare il totale già versato.', 'modulo-iscrizioni' ) ); }
		$inserted = $wpdb->insert( $wpdb->prefix . 'mi_payments', array( 'registration_id' => $registration_id, 'transaction_kind' => $transaction, 'installment_kind' => $kind, 'effective_at' => $effective_at, 'amount_cents' => $amount, 'payment_source' => $source, 'external_reference' => $external_reference, 'operator_label' => wp_get_current_user()->display_name, 'administrative_note' => $administrative_note, 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
		if ( false === $inserted ) { $wpdb->query( 'ROLLBACK' ); wp_die( esc_html__( 'Il movimento non è stato salvato. Riprova.', 'modulo-iscrizioni' ) ); }
		$registration_changes = array( 'workspace_status' => 'PENDING', 'workspace_last_error' => 'payment_changed' );
		$registration_formats = array( '%s', '%s' );
		$net_paid = self::totale_pagamenti( $registration_id );
		$new_status = $locked['status'];
		if ( 'PENDING_PAYMENT' === $locked['status'] && $net_paid >= (int) $locked['initial_due_cents'] ) {
			$new_status = 'CONFIRMED';
			$registration_changes['status'] = $new_status;
			$registration_formats[] = '%s';
			$registration_changes['expires_at'] = null;
			$registration_formats[] = '%s';
		} elseif ( 'CONFIRMED' === $locked['status'] && (int) $locked['initial_due_cents'] > 0 && $net_paid < (int) $locked['initial_due_cents'] ) {
			$new_status = 'PENDING_PAYMENT';
			$registration_changes['status'] = $new_status;
			$registration_formats[] = '%s';
			$registration_changes['expires_at'] = $locked['payment_deadline_at'];
			$registration_formats[] = '%s';
		}
		$marked_pending = $wpdb->update( $wpdb->prefix . 'mi_registrations', $registration_changes, array( 'id' => $registration_id ), $registration_formats, array( '%d' ) );
		if ( false === $marked_pending ) { $wpdb->query( 'ROLLBACK' ); wp_die( esc_html__( 'Il movimento non è stato accodato per Workspace. Riprova.', 'modulo-iscrizioni' ) ); }
		if ( $new_status !== $locked['status'] && ! MI_Registration_Service::append_registration_event( $registration_id, 'PAYMENT_STATUS_CHANGED', $locked['status'], $new_status, wp_get_current_user()->display_name, array( 'net_paid_cents' => $net_paid, 'initial_due_cents' => (int) $locked['initial_due_cents'] ) ) ) { $wpdb->query( 'ROLLBACK' ); wp_die( esc_html__( 'Lo stato del pagamento non è stato registrato. Riprova.', 'modulo-iscrizioni' ) ); }
		$wpdb->query( 'COMMIT' );
		MI_Registration_Service::accoda_iscrizione_workspace( $registration_id );
		$url = add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-registrations', 'registration_id' => $registration_id, 'mi_payment_added' => '1' ), admin_url( 'edit.php' ) );
		wp_safe_redirect( $url ); exit;
	}

	public static function cancel_registration() {
		if ( ! current_user_can( 'mi_manage_events' ) ) { wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) ); }
		$registration_id = absint( $_POST['registration_id'] ?? 0 );
		check_admin_referer( 'mi_cancel_registration_' . $registration_id );
		global $wpdb;
		$event_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}mi_registrations WHERE id = %d", $registration_id ) );
		if ( ! $event_id || ! MI_Access::can_access_event( $event_id ) ) { wp_die( esc_html__( 'Iscrizione non accessibile.', 'modulo-iscrizioni' ) ); }
		$result = MI_Registration_Service::cancel_registration( $registration_id, wp_get_current_user()->display_name );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		wp_safe_redirect( add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-registrations', 'registration_id' => $registration_id, 'mi_cancelled' => '1' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public static function payments_page() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) { wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) ); }
		global $wpdb;
		$filter_event = isset( $_GET['payment_event_id'] ) ? absint( $_GET['payment_event_id'] ) : 0;
		$filter_source = strtoupper( sanitize_key( wp_unslash( $_GET['payment_source'] ?? '' ) ) );
		$filter_transaction = strtoupper( sanitize_key( wp_unslash( $_GET['transaction_kind'] ?? '' ) ) );
		$filter_from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['payment_from'] ?? '' ) ) ? sanitize_text_field( wp_unslash( $_GET['payment_from'] ) ) : '';
		$filter_to = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['payment_to'] ?? '' ) ) ? sanitize_text_field( wp_unslash( $_GET['payment_to'] ) ) : '';
		if ( $filter_from && $filter_to && $filter_from > $filter_to ) { $filter_from = ''; $filter_to = ''; }
		list( $payment_where, $payment_parameters ) = self::payment_where( $filter_event, $filter_source, $filter_transaction, $filter_from, $filter_to );
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page = 100;
		$offset = ( $page - 1 ) * $per_page;
		$query = "SELECT p.*, r.order_code, r.event_id FROM {$wpdb->prefix}mi_payments p INNER JOIN {$wpdb->prefix}mi_registrations r ON r.id = p.registration_id {$payment_where} ORDER BY p.id DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $query, array_merge( $payment_parameters, array( $per_page, $offset ) ) ), ARRAY_A );
		$count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}mi_payments p INNER JOIN {$wpdb->prefix}mi_registrations r ON r.id = p.registration_id {$payment_where}";
		$total_rows = (int) $wpdb->get_var( $payment_parameters ? $wpdb->prepare( $count_query, $payment_parameters ) : $count_query );
		$summary_query = "SELECT
			COALESCE(SUM(CASE WHEN p.transaction_kind='PAYMENT' THEN p.amount_cents ELSE 0 END),0) payments,
			COALESCE(SUM(CASE WHEN p.transaction_kind='REFUND' THEN p.amount_cents ELSE 0 END),0) refunds,
			COALESCE(SUM(CASE WHEN p.payment_source='BANK_TRANSFER' THEN p.amount_cents ELSE 0 END),0) bank_transfers,
			COALESCE(SUM(CASE WHEN p.payment_source='CARD' THEN p.amount_cents ELSE 0 END),0) cards,
			COALESCE(SUM(CASE WHEN p.payment_source='CASH' THEN p.amount_cents ELSE 0 END),0) cash
			FROM {$wpdb->prefix}mi_payments p INNER JOIN {$wpdb->prefix}mi_registrations r ON r.id = p.registration_id {$payment_where}";
		$summary_row = $wpdb->get_row( $payment_parameters ? $wpdb->prepare( $summary_query, $payment_parameters ) : $summary_query, ARRAY_A );
		$labels = array( 'BANK_TRANSFER' => 'Bonifico', 'CARD' => 'Carta', 'CASH' => 'Contante' );
		$export_url = wp_nonce_url( add_query_arg( array( 'action' => 'mi_export_payments', 'payment_event_id' => $filter_event, 'payment_source' => $filter_source, 'transaction_kind' => $filter_transaction, 'payment_from' => $filter_from, 'payment_to' => $filter_to ), admin_url( 'admin-post.php' ) ), 'mi_export_payments' );
		if ( ! current_user_can( 'mi_manage_payments' ) ) { echo '<p class="notice notice-info"><strong>Consultazione soltanto:</strong> non disponi del permesso per registrare versamenti o rimborsi.</p>'; }
		echo '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE . '&page=mi-payments' ) ) . '">Azzera filtri</a>';
		echo '<form method="get"><input type="hidden" name="post_type" value="' . esc_attr( MI_Event_Post_Type::EVENT_TYPE ) . '"><input type="hidden" name="page" value="mi-payments"><input type="hidden" name="payment_event_id" value="' . esc_attr( $filter_event ) . '"><input type="hidden" name="payment_source" value="' . esc_attr( $filter_source ) . '"><input type="hidden" name="transaction_kind" value="' . esc_attr( $filter_transaction ) . '"><label>Dal <input type="date" name="payment_from" value="' . esc_attr( $filter_from ) . '"></label> <label>Al <input type="date" name="payment_to" value="' . esc_attr( $filter_to ) . '"></label> <button class="button">Applica intervallo</button></form>';
		$summary = array( 'PAYMENT' => (int) ( $summary_row['payments'] ?? 0 ), 'REFUND' => (int) ( $summary_row['refunds'] ?? 0 ), 'BANK_TRANSFER' => (int) ( $summary_row['bank_transfers'] ?? 0 ), 'CARD' => (int) ( $summary_row['cards'] ?? 0 ), 'CASH' => (int) ( $summary_row['cash'] ?? 0 ) );
		echo '<p><strong>Movimenti:</strong> ' . esc_html( $total_rows ) . ' totali nel filtro · ' . esc_html( count( $rows ) ) . ' in questa pagina</p>';
		echo '<p><strong>Riepilogo filtro:</strong> versamenti ' . esc_html( self::formatta_importo( $summary['PAYMENT'] ) ) . ' · rimborsi ' . esc_html( self::formatta_importo( $summary['REFUND'] ) ) . ' · bonifici ' . esc_html( self::formatta_importo( $summary['BANK_TRANSFER'] ) ) . ' · carte ' . esc_html( self::formatta_importo( $summary['CARD'] ) ) . ' · contanti ' . esc_html( self::formatta_importo( $summary['CASH'] ) ) . '</p>';
		$scope = MI_Access::activity_ids();
		$events = 'ALL' === $scope ? get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) : get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'post__in' => get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) ) ?: array( 0 ), 'orderby' => 'title', 'order' => 'ASC' ) );
		?><div class="wrap"><h1>Pagamenti registrati</h1><p>I movimenti sono registrati manualmente; al raggiungimento o alla perdita della quota richiesta lo stato dell’iscrizione viene riconciliato automaticamente. <a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>">Esporta CSV</a></p><form method="get" style="margin:16px 0"><input type="hidden" name="post_type" value="<?php echo esc_attr( MI_Event_Post_Type::EVENT_TYPE ); ?>"><input type="hidden" name="page" value="mi-payments"><label>Evento <select name="payment_event_id"><option value="0">Tutti</option><?php foreach ( $events as $event ) : ?><option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $filter_event, $event->ID ); ?>><?php echo esc_html( $event->post_title ); ?></option><?php endforeach; ?></select></label> <label>Fonte <select name="payment_source"><option value="">Tutte</option><option value="BANK_TRANSFER" <?php selected( $filter_source, 'BANK_TRANSFER' ); ?>>Bonifico</option><option value="CARD" <?php selected( $filter_source, 'CARD' ); ?>>Carta</option><option value="CASH" <?php selected( $filter_source, 'CASH' ); ?>>Contante</option></select></label> <label>Movimento <select name="transaction_kind"><option value="">Tutti</option><option value="PAYMENT" <?php selected( $filter_transaction, 'PAYMENT' ); ?>>Versamenti</option><option value="REFUND" <?php selected( $filter_transaction, 'REFUND' ); ?>>Rimborsi</option></select></label> <button class="button">Filtra</button></form><table class="widefat striped"><thead><tr><th>Data</th><th>Ordine</th><th>Evento</th><th>Movimento</th><th>Rata</th><th>Importo</th><th>Fonte</th><th>Riferimento</th><th>Operatore</th></tr></thead><tbody><?php if ( ! $rows ) : ?><tr><td colspan="9">Nessun movimento registrato.</td></tr><?php endif; foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( self::formatta_data_locale( $row['effective_at'] ) ); ?></td><td><code><?php echo esc_html( $row['order_code'] ); ?></code></td><td><?php echo esc_html( get_the_title( (int) $row['event_id'] ) ); ?></td><td><?php echo esc_html( 'REFUND' === $row['transaction_kind'] ? 'Rimborso' : 'Versamento' ); ?></td><td><?php echo esc_html( $row['installment_kind'] ); ?></td><td><?php echo esc_html( self::formatta_importo( $row['amount_cents'] ) ); ?></td><td><?php echo esc_html( $labels[ $row['payment_source'] ] ?? $row['payment_source'] ); ?></td><td><?php echo esc_html( $row['external_reference'] ?: '—' ); ?></td><td><?php echo esc_html( $row['operator_label'] ?: '—' ); ?></td></tr><?php endforeach; ?></tbody></table><?php self::render_pagination( $page, $per_page, $total_rows ); ?></div><?php
	}

	public static function export_payments() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) { wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) ); }
		check_admin_referer( 'mi_export_payments' );
		global $wpdb;
		$filter_event = isset( $_GET['payment_event_id'] ) ? absint( $_GET['payment_event_id'] ) : 0;
		$filter_source = strtoupper( sanitize_key( wp_unslash( $_GET['payment_source'] ?? '' ) ) );
		$filter_transaction = strtoupper( sanitize_key( wp_unslash( $_GET['transaction_kind'] ?? '' ) ) );
		$filter_from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['payment_from'] ?? '' ) ) ? sanitize_text_field( wp_unslash( $_GET['payment_from'] ) ) : '';
		$filter_to = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_GET['payment_to'] ?? '' ) ) ? sanitize_text_field( wp_unslash( $_GET['payment_to'] ) ) : '';
		if ( $filter_from && $filter_to && $filter_from > $filter_to ) { $filter_from = ''; $filter_to = ''; }
		list( $payment_where, $payment_parameters ) = self::payment_where( $filter_event, $filter_source, $filter_transaction, $filter_from, $filter_to );
		$labels = array( 'BANK_TRANSFER' => 'Bonifico', 'CARD' => 'Carta', 'CASH' => 'Contante' );
		header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="pagamenti-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$output = fopen( 'php://output', 'w' ); fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, array( 'Data UTC', 'Codice iscrizione', 'Evento', 'Tipo transazione', 'Rata', 'Importo centesimi', 'Fonte pagamento', 'Riferimento esterno', 'Operatore', 'Nota amministrativa' ), ';' );
		$offset = 0;
		do {
			$query = "SELECT p.*, r.order_code, r.event_id FROM {$wpdb->prefix}mi_payments p INNER JOIN {$wpdb->prefix}mi_registrations r ON r.id = p.registration_id {$payment_where} ORDER BY p.effective_at, p.id LIMIT 500 OFFSET %d";
			$rows = $wpdb->get_results( $wpdb->prepare( $query, array_merge( $payment_parameters, array( $offset ) ) ), ARRAY_A );
			foreach ( $rows as $row ) { $line = array( $row['effective_at'], $row['order_code'], get_the_title( (int) $row['event_id'] ), $row['transaction_kind'], $row['installment_kind'], $row['amount_cents'], $labels[ $row['payment_source'] ] ?? $row['payment_source'], $row['external_reference'], $row['operator_label'], $row['administrative_note'] ); fputcsv( $output, array_map( array( __CLASS__, 'safe_csv_value' ), $line ), ';' ); }
			$offset += count( $rows );
		} while ( count( $rows ) === 500 );
		fclose( $output ); exit;
	}

	public static function registrations_page() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'mi_registrations';
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		$search = isset( $_GET['mi_search'] ) ? sanitize_text_field( wp_unslash( $_GET['mi_search'] ) ) : '';
		$workspace_filter = isset( $_GET['mi_workspace_status'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['mi_workspace_status'] ) ) ) : '';
		$workspace_filter = in_array( $workspace_filter, array( 'PENDING', 'SYNCED' ), true ) ? $workspace_filter : '';
		$detail_id = isset( $_GET['registration_id'] ) ? absint( $_GET['registration_id'] ) : 0;
		$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$per_page = 50;
		$offset = ( $page - 1 ) * $per_page;
		$scope = MI_Access::activity_ids();
		$allowed_events = 'ALL' === $scope ? array() : get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) );
		if ( $event_id && ! MI_Access::can_access_event( $event_id ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		$scope_conditions = array();
		$scope_parameters = array();
		if ( $event_id ) {
			$scope_conditions[] = 'event_id = %d';
			$scope_parameters[] = $event_id;
		} elseif ( 'ALL' !== $scope ) {
			$scope_conditions[] = 'event_id IN (' . implode( ',', array_map( 'absint', $allowed_events ?: array( 0 ) ) ) . ')';
		}
		$conditions = $scope_conditions;
		$parameters = $scope_parameters;
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$conditions[] = "(order_code LIKE %s OR buyer_first_name LIKE %s OR buyer_last_name LIKE %s OR CONCAT(buyer_first_name, ' ', buyer_last_name) LIKE %s OR buyer_email LIKE %s)";
			$parameters = array_merge( $parameters, array( $like, $like, $like, $like, $like ) );
		}
		if ( $workspace_filter ) {
			$conditions[] = 'workspace_status = %s';
			$parameters[] = $workspace_filter;
		}
		$where = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
		if ( $parameters ) {
			$where = $wpdb->prepare( $where, $parameters );
		}
		$scope_where = $scope_conditions ? 'WHERE ' . implode( ' AND ', $scope_conditions ) : '';
		if ( $scope_parameters ) {
			$scope_where = $wpdb->prepare( $scope_where, $scope_parameters );
		}
		$workspace_counts = array( 'PENDING' => 0, 'SYNCED' => 0 );
		foreach ( $wpdb->get_results( "SELECT workspace_status, COUNT(*) AS totale FROM {$table} {$scope_where} GROUP BY workspace_status", ARRAY_A ) as $workspace_count ) {
			if ( isset( $workspace_counts[ $workspace_count['workspace_status'] ] ) ) {
				$workspace_counts[ $workspace_count['workspace_status'] ] = (int) $workspace_count['totale'];
			}
		}
		$rows = $wpdb->get_results( "SELECT id, order_code, event_id, status, workspace_status, workspace_attempts, buyer_first_name, buyer_last_name, buyer_email, total_qty, economic_mode, total_cents, initial_due_cents, balance_cents, created_at FROM {$table} {$where} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}", ARRAY_A );
		$total_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
		$visible_events = 'ALL' === $scope ? get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) : get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'post__in' => $allowed_events ?: array( 0 ), 'orderby' => 'title', 'order' => 'ASC' ) );
		$detail = null;
		$participants = array();
		$registration_items = array();
		$registration_events = array();
		$payment_rows = array();
		$detail_field_labels = array();
		$detail_marketing_requested = false;
		if ( $detail_id ) {
			$detail = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $detail_id ), ARRAY_A );
			if ( ! $detail || ! MI_Access::can_access_event( (int) $detail['event_id'] ) ) {
				wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
			}
			$participants_table = $wpdb->prefix . 'mi_participants';
			$participants = $wpdb->get_results( $wpdb->prepare( "SELECT id, ticket_type_code, ticket_index, first_name, last_name, extra_json, options_json, status FROM {$participants_table} WHERE registration_id = %d ORDER BY id", $detail_id ), ARRAY_A );
			$registration_items = $wpdb->get_results( $wpdb->prepare( "SELECT ticket_type_code, ticket_type_name, quantity, unit_price_cents, options_json FROM {$wpdb->prefix}mi_registration_items WHERE registration_id = %d ORDER BY id", $detail_id ), ARRAY_A );
			$registration_events = $wpdb->get_results( $wpdb->prepare( "SELECT event_type, from_status, to_status, actor_label, detail_json, created_at FROM {$wpdb->prefix}mi_registration_events WHERE registration_id = %d ORDER BY id", $detail_id ), ARRAY_A );
			$payment_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_payments WHERE registration_id = %d ORDER BY effective_at", $detail_id ), ARRAY_A );
			$detail_snapshot = json_decode( (string) ( $detail['snapshot_json'] ?? '' ), true );
			if ( is_array( $detail_snapshot ) ) {
				foreach ( (array) ( $detail_snapshot['event']['participant_fields'] ?? array() ) as $field ) {
					$key = sanitize_key( $field['key'] ?? '' );
					$label = sanitize_text_field( $field['label'] ?? '' );
					if ( $key && $label ) $detail_field_labels[ $key ] = $label;
				}
				$detail_marketing_requested = ! empty( $detail_snapshot['event']['marketing_enabled'] );
			}
		}
		?>
		<div class="wrap"><h1>Iscrizioni</h1>
		<p>Registro locale autorevole con replica firmata sul registro Workspace. La spedizione segue la modalità email configurata dall’amministratore.</p>
		<ul class="subsubsub" aria-label="Riepilogo repliche Workspace"><li><strong><?php echo esc_html( 'Sincronizzate: ' . $workspace_counts['SYNCED'] ); ?></strong> | </li><li><strong><?php echo esc_html( 'In attesa: ' . $workspace_counts['PENDING'] ); ?></strong></li></ul><div class="clear"></div>
		<?php if ( isset( $_GET['mi_workspace_retry'] ) ) : ?>
		<?php $retry_result = sanitize_key( wp_unslash( $_GET['mi_workspace_retry'] ) ); ?>
		<div class="notice notice-success"><p><?php echo esc_html( 'synced' === $retry_result ? 'La replica era già sincronizzata.' : 'Replica Workspace riaccodata. Il registro locale resta autorevole durante il nuovo tentativo.' ); ?></p></div>
		<?php endif; ?>
		<form method="get" style="margin:16px 0">
		<input type="hidden" name="post_type" value="<?php echo esc_attr( MI_Event_Post_Type::EVENT_TYPE ); ?>">
		<input type="hidden" name="page" value="mi-registrations">
		<label for="mi-event-filter">Evento</label>
		<select id="mi-event-filter" name="event_id"><option value="0">Tutti gli eventi</option><?php foreach ( $visible_events as $visible_event ) : ?><option value="<?php echo esc_attr( $visible_event->ID ); ?>" <?php selected( $event_id, $visible_event->ID ); ?>><?php echo esc_html( $visible_event->post_title ); ?></option><?php endforeach; ?></select>
		<label for="mi-search">Ricerca</label>
		<input id="mi-search" type="search" name="mi_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Codice, referente o email">
		<label for="mi-workspace-filter">Workspace</label>
		<select id="mi-workspace-filter" name="mi_workspace_status"><option value="">Tutti gli stati</option><option value="PENDING" <?php selected( $workspace_filter, 'PENDING' ); ?>>In attesa</option><option value="SYNCED" <?php selected( $workspace_filter, 'SYNCED' ); ?>>Sincronizzate</option></select>
		<button class="button">Filtra</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE . '&page=mi-registrations' ) ); ?>">Azzera</a>
		<?php $export_url = wp_nonce_url( add_query_arg( array( 'action' => 'mi_export_registrations', 'event_id' => $event_id, 'mi_search' => $search ), admin_url( 'admin-post.php' ) ), 'mi_export_registrations' ); ?>
		<a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>">Esporta CSV filtrato</a>
		</form>
		<div class="mi-responsive-table"><table class="widefat mi-bookings-table"><thead><tr><th>Prenotazione</th><th>Evento</th><th>Referente</th><th>Stato</th><th>Data</th><th>Importo</th><th><span class="screen-reader-text">Azioni</span></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="7">Nessuna iscrizione.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?>
		<?php $detail_url = add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-registrations', 'event_id' => $event_id, 'mi_search' => $search, 'mi_workspace_status' => $workspace_filter, 'paged' => $page, 'registration_id' => (int) $row['id'] ), admin_url( 'edit.php' ) ); ?>
		<?php $event_start = get_post_meta( (int) $row['event_id'], '_mi_event_starts_at', true ); ?><tr><td><a class="mi-booking-code" data-mi-booking-open href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $row['order_code'] ); ?></a><small><?php echo esc_html( (int) $row['total_qty'] . ( 1 === (int) $row['total_qty'] ? ' partecipante' : ' partecipanti' ) ); ?></small></td><td><strong><?php echo esc_html( get_the_title( (int) $row['event_id'] ) ); ?></strong><small><?php echo esc_html( $event_start ? self::formatta_data_locale( $event_start ) : 'Data da definire' ); ?></small></td><td><strong><?php echo esc_html( $row['buyer_first_name'] . ' ' . $row['buyer_last_name'] ); ?></strong><small><?php echo esc_html( $row['buyer_email'] ); ?></small></td><td><span class="mi-status-pill mi-status-<?php echo esc_attr( sanitize_html_class( strtolower( $row['status'] ) ) ); ?>"><?php echo esc_html( self::etichetta_stato( $row['status'] ) ); ?></span></td><td><?php echo esc_html( self::formatta_data_locale( $row['created_at'] ) ); ?></td><td><?php echo 0 === (int) $row['total_cents'] ? '<span class="mi-free-label">Evento gratuito</span>' : esc_html( self::formatta_importo( $row['total_cents'] ) ); ?></td><td><a class="button button-small" data-mi-booking-open href="<?php echo esc_url( $detail_url ); ?>">Apri</a></td></tr>
		<?php endforeach; ?>
		</tbody></table></div><?php self::render_pagination( $page, $per_page, $total_rows ); ?>
		<?php if ( $detail ) : ?>
		<?php $detail_event_id = (int) $detail['event_id']; $detail_image = get_the_post_thumbnail_url( $detail_event_id, 'medium_large' ); $detail_event_start = get_post_meta( $detail_event_id, '_mi_event_starts_at', true ); ?>
		<div id="mi-booking-detail" class="mi-booking-detail"><hr><div class="mi-booking-title"><div><span class="mi-booking-eyebrow">Prenotazione</span><h2><?php echo esc_html( $detail['order_code'] ); ?></h2></div><a class="button" data-mi-booking-close href="<?php echo esc_url( remove_query_arg( 'registration_id' ) ); ?>">Torna all’elenco</a></div>
		<section class="mi-booking-hero">
		<div class="mi-booking-event"><?php if ( $detail_image ) : ?><img src="<?php echo esc_url( $detail_image ); ?>" alt=""><?php endif; ?><div><h3><?php echo esc_html( get_the_title( $detail_event_id ) ); ?></h3><p><?php echo esc_html( $detail_event_start ? self::formatta_data_locale( $detail_event_start ) : 'Data da definire' ); ?></p></div></div>
		<div class="mi-booking-facts"><div><span>Referente</span><strong><?php echo esc_html( $detail['buyer_first_name'] . ' ' . $detail['buyer_last_name'] ); ?></strong><small><?php echo esc_html( $detail['buyer_email'] ); ?></small></div><div><span>Stato</span><strong><?php echo esc_html( self::etichetta_stato( $detail['status'] ) ); ?></strong></div><div><span>Partecipanti</span><strong><?php echo esc_html( (string) count( $participants ) ); ?></strong></div><div><span>Importo previsto</span><strong><?php echo 0 === (int) $detail['total_cents'] ? 'Evento gratuito' : esc_html( self::formatta_importo( $detail['total_cents'] ) ); ?></strong></div><div><span>Creata il</span><strong><?php echo esc_html( self::formatta_data_locale( $detail['created_at'] ) ); ?></strong></div><div><span>Cellulare</span><strong><?php echo esc_html( $detail['buyer_phone'] ); ?></strong></div></div>
		</section>
		<section class="mi-participants-overview"><div class="mi-section-heading"><div><span class="mi-booking-eyebrow">Persone associate</span><h3>Partecipanti</h3></div><span><?php echo esc_html( count( $participants ) . ( 1 === count( $participants ) ? ' persona' : ' persone' ) ); ?></span></div>
		<?php if ( ! $participants ) : ?><p>Nessun partecipante associato.</p><?php else : ?><div class="mi-responsive-table"><table class="widefat"><thead><tr><th>Partecipante</th><th>Quota</th><th>Stato</th><th>Dati raccolti</th></tr></thead><tbody><?php foreach ( $participants as $participant ) : ?><?php $participant_answers = json_decode( (string) $participant['extra_json'], true ); $participant_answers = is_array( $participant_answers ) ? $participant_answers : array(); ?><tr><td><strong><?php echo esc_html( $participant['first_name'] . ' ' . $participant['last_name'] ); ?></strong></td><td><?php echo esc_html( $participant['ticket_type_code'] ?: 'Quota storica' ); ?></td><td><?php echo esc_html( 'CANCELLED' === ( $participant['status'] ?? '' ) ? 'Annullata' : 'Attiva' ); ?></td><td><?php echo esc_html( $participant_answers ? count( $participant_answers ) . ' campi' : 'Solo nome e cognome' ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
		<div class="mi-registration-workspace">
		<h3>Dati utili alla gestione</h3>
		<table class="widefat striped mi-registration-summary"><tbody>
		<tr><th scope="row">Evento</th><td><?php echo esc_html( get_the_title( (int) $detail['event_id'] ) ); ?></td></tr>
		<tr><th scope="row">Stato</th><td><?php echo esc_html( self::etichetta_stato( $detail['status'] ) ); ?></td></tr>
		<?php if ( $detail['expires_at'] ) : ?><tr><th scope="row">Scadenza prenotazione</th><td><?php echo esc_html( self::formatta_data_locale( $detail['expires_at'] ) ); ?></td></tr><?php endif; ?>
		<tr><th scope="row">Referente</th><td><?php echo esc_html( $detail['buyer_first_name'] . ' ' . $detail['buyer_last_name'] ); ?></td></tr>
		<tr><th scope="row">Email</th><td><?php echo esc_html( $detail['buyer_email'] ); ?></td></tr>
		<tr><th scope="row">Cellulare</th><td><?php echo esc_html( $detail['buyer_phone'] ); ?></td></tr>
		<?php if ( $detail['special_requests'] ) : ?><tr><th scope="row">Richieste particolari</th><td><?php echo nl2br( esc_html( $detail['special_requests'] ) ); ?></td></tr><?php endif; ?>
		<?php if ( $detail_marketing_requested ) : ?><tr><th scope="row">Future iniziative</th><td><?php echo esc_html( $detail['marketing_consent_id'] ? 'Consenso prestato' : 'Consenso non prestato' ); ?></td></tr><?php endif; ?>
		<?php if ( 'REGISTRATION_ONLY' !== $detail['economic_mode'] || (int) $detail['total_cents'] > 0 ) : ?>
		<tr><th scope="row">Modalità di pagamento richiesta</th><td><?php echo esc_html( self::etichetta_modalita_economica( $detail['economic_mode'] ) ); ?></td></tr>
		<tr><th scope="row">Totale</th><td><?php echo esc_html( self::formatta_importo( $detail['total_cents'] ) ); ?></td></tr>
		<?php if ( (int) $detail['initial_due_cents'] > 0 ) : ?><tr><th scope="row">Primo versamento</th><td><?php echo esc_html( self::formatta_importo( $detail['initial_due_cents'] ) ); ?></td></tr><?php endif; ?>
		<?php if ( (int) $detail['balance_cents'] > 0 ) : ?><tr><th scope="row">Saldo successivo previsto</th><td><?php echo esc_html( self::formatta_importo( $detail['balance_cents'] ) ); ?></td></tr><?php endif; ?>
		<?php $detail_paid_cents = 0; foreach ( $payment_rows as $payment_row ) $detail_paid_cents += 'REFUND' === $payment_row['transaction_kind'] ? -(int) $payment_row['amount_cents'] : (int) $payment_row['amount_cents']; ?><?php if ( $payment_rows ) : ?><tr><th scope="row">Versato</th><td><?php echo esc_html( self::formatta_importo( max( 0, $detail_paid_cents ) ) ); ?></td></tr><tr><th scope="row">Residuo calcolato</th><td><strong><?php echo esc_html( self::formatta_importo( max( 0, (int) $detail['total_cents'] - $detail_paid_cents ) ) ); ?></strong></td></tr><?php endif; ?>
		<?php endif; ?>
		</tbody></table>
		</div>
		<details class="mi-registration-technical">
		<summary>Dettagli tecnici</summary>
		<p>Informazioni utili per assistenza, controlli e sincronizzazione. Non servono per la gestione ordinaria.</p>
		<table class="widefat striped"><tbody>
		<tr><th scope="row">Codice iscrizione</th><td><code><?php echo esc_html( $detail['order_code'] ); ?></code></td></tr>
		<tr><th scope="row">Workspace</th><td><?php echo esc_html( $detail['workspace_status'] ); ?></td></tr>
		<tr><th scope="row">Tentativi Workspace</th><td><?php echo esc_html( (string) (int) $detail['workspace_attempts'] ); ?></td></tr>
		<tr><th scope="row">Ultimo errore Workspace</th><td><?php echo esc_html( $detail['workspace_last_error'] ?: 'Nessuno' ); ?></td></tr>
		<tr><th scope="row">Sincronizzata il</th><td><?php echo esc_html( $detail['workspace_synced_at'] ?: 'Non ancora sincronizzata' ); ?></td></tr>
		<tr><th scope="row">Revisione evento</th><td><?php echo esc_html( $detail['event_revision_id'] ?: 'Storica non disponibile' ); ?><?php if ( $detail['event_revision_hash'] ) : ?> · <code><?php echo esc_html( substr( $detail['event_revision_hash'], 0, 16 ) ); ?></code><?php endif; ?></td></tr>
		<tr><th scope="row">Consenso privacy</th><td><?php echo esc_html( $detail['privacy_consent_id'] ?: 'Storico non disponibile' ); ?> · versione <?php echo esc_html( $detail['privacy_policy_version'] ?: '—' ); ?> · <?php echo esc_html( $detail['privacy_accepted_at'] ?: '—' ); ?></td></tr>
		<tr><th scope="row">Consenso future iniziative</th><td><?php echo esc_html( $detail['marketing_consent_id'] ? $detail['marketing_consent_id'] . ' · ' . $detail['marketing_accepted_at'] : 'Non prestato' ); ?></td></tr>
		<tr><th scope="row">Posti liberati il</th><td><?php echo esc_html( $detail['capacity_released_at'] ?: 'Non liberati' ); ?></td></tr>
		</tbody></table>
		</details>
		<?php if ( $payment_rows || in_array( $detail['economic_mode'], array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ) : ?><h3>Versamenti registrati</h3><?php if ( ! $payment_rows ) : ?><p>Nessun versamento registrato.</p><?php else : ?><table class="widefat striped" style="max-width:900px"><thead><tr><th>Data</th><th>Rata</th><th>Importo</th><th>Fonte</th><th>Riferimento</th><th>Nota</th></tr></thead><tbody><?php $payment_labels = array( 'BANK_TRANSFER' => 'Bonifico', 'CARD' => 'Carta', 'CASH' => 'Contante' ); foreach ( $payment_rows as $payment ) : ?><tr><td><?php echo esc_html( self::formatta_data_locale( $payment['effective_at'] ) ); ?></td><td><?php echo esc_html( $payment['installment_kind'] ); ?></td><td><?php echo esc_html( self::formatta_importo( $payment['amount_cents'] ) ); ?></td><td><?php echo esc_html( $payment_labels[ $payment['payment_source'] ] ?? $payment['payment_source'] ); ?></td><td><?php echo esc_html( $payment['external_reference'] ?: '—' ); ?></td><td><?php echo esc_html( $payment['administrative_note'] ?: '—' ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?><?php endif; ?>
		<p class="notice notice-info" style="max-width:900px;padding:12px"><strong>Operazioni di segreteria in Google Sheets.</strong> Pagamenti, rimborsi, variazioni, ritiri e integrazioni si registrano dal menu <em>Modulo iscrizioni → Gestisci un’iscrizione</em> nel foglio di lavoro.</p>
		<h3>Tipologie e opzioni ordine</h3>
		<?php $detail_order_options = json_decode( (string) ( $detail['order_options_json'] ?? '' ), true ); if ( ! is_array( $detail_order_options ) && $registration_items ) { $detail_order_options = json_decode( (string) $registration_items[0]['options_json'], true ); } $detail_order_options = is_array( $detail_order_options ) ? $detail_order_options : array(); ?>
		<?php if ( $detail_order_options ) : ?><p><strong>Opzioni ordine:</strong> <?php echo esc_html( implode( ', ', array_map( static function ( $option ) { return ( $option['name'] ?? $option['code'] ?? 'Opzione' ) . ' × ' . absint( $option['quantity'] ?? 0 ); }, $detail_order_options ) ) ); ?></p><?php else : ?><p>Nessuna opzione ordine.</p><?php endif; ?>
		<?php if ( ! $registration_items ) : ?><p>Nessuna tipologia storica disponibile.</p><?php else : ?><table class="widefat striped" style="max-width:900px"><thead><tr><th>Codice</th><th>Nome</th><th>Quantità</th><th>Prezzo unitario</th></tr></thead><tbody><?php foreach ( $registration_items as $registration_item ) : ?><tr><td><code><?php echo esc_html( $registration_item['ticket_type_code'] ); ?></code></td><td><?php echo esc_html( $registration_item['ticket_type_name'] ?: 'Nome storico non disponibile' ); ?></td><td><?php echo esc_html( $registration_item['quantity'] ); ?></td><td><?php echo esc_html( self::formatta_importo( $registration_item['unit_price_cents'] ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
		<?php if ( 'SYNCED' !== $detail['workspace_status'] ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:16px 0">
		<input type="hidden" name="action" value="mi_retry_workspace">
		<input type="hidden" name="registration_id" value="<?php echo esc_attr( $detail_id ); ?>">
		<?php wp_nonce_field( 'mi_retry_workspace_' . $detail_id ); ?>
		<button class="button button-secondary">Riaccoda replica Workspace</button>
		</form>
		<?php endif; ?>
		<h3>Storico stato</h3>
		<?php if ( ! $registration_events ) : ?><p>Nessun evento di audit disponibile per questa iscrizione storica.</p><?php else : ?><table class="widefat striped" style="max-width:900px"><thead><tr><th>Data UTC</th><th>Evento</th><th>Da</th><th>A</th><th>Attore</th></tr></thead><tbody><?php foreach ( $registration_events as $registration_event ) : ?><tr><td><?php echo esc_html( $registration_event['created_at'] ); ?></td><td><?php echo esc_html( strtoupper( $registration_event['event_type'] ) ); ?></td><td><?php echo esc_html( strtoupper( $registration_event['from_status'] ?: '—' ) ); ?></td><td><?php echo esc_html( strtoupper( $registration_event['to_status'] ?: '—' ) ); ?></td><td><?php echo esc_html( $registration_event['actor_label'] ?: '—' ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
		<h3>Dati dei partecipanti</h3>
		<?php if ( ! $participants ) : ?><p>Nessun partecipante associato.</p><?php endif; ?>
		<?php $catalog = MI_Field_Schema::catalog(); ?>
		<?php foreach ( $participants as $position => $participant ) : ?>
		<?php $answers = json_decode( (string) $participant['extra_json'], true ); $answers = is_array( $answers ) ? $answers : array(); $participant_options = json_decode( (string) $participant['options_json'], true ); $participant_options = is_array( $participant_options ) ? $participant_options : array(); ?>
		<h4><?php echo esc_html( ( $position + 1 ) . '. ' . $participant['first_name'] . ' ' . $participant['last_name'] . ' — ' . ( $participant['ticket_type_code'] ?: 'tipologia storica non disponibile' ) . ( $participant['ticket_index'] ? ' #' . $participant['ticket_index'] : '' ) ); ?></h4>
		<?php if ( $participant_options ) : ?><p><strong>Opzioni:</strong> <?php echo esc_html( implode( ', ', array_map( static function ( $option ) { return ( $option['name'] ?? $option['code'] ?? 'Opzione' ) . ' × ' . absint( $option['quantity'] ?? 0 ); }, $participant_options ) ) ); ?></p><?php endif; ?>
		<?php if ( ! $answers ) : ?><p>Nessun dato aggiuntivo raccolto.</p><?php else : ?>
		<table class="widefat striped" style="max-width:900px"><tbody>
		<?php foreach ( $answers as $key => $value ) : ?>
		<?php $label = $detail_field_labels[ $key ] ?? ( isset( $catalog[ $key ]['label'] ) ? $catalog[ $key ]['label'] : ucfirst( str_replace( '_', ' ', preg_replace( '/^custom_/', '', $key ) ) ) ); ?>
		<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo nl2br( esc_html( (string) $value ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php endif; ?>
		<?php endforeach; ?>
		</div>
		<?php endif; ?>
		</div>
		<?php
	}

	public static function riaccoda_workspace() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		$registration_id = isset( $_POST['registration_id'] ) ? absint( $_POST['registration_id'] ) : 0;
		check_admin_referer( 'mi_retry_workspace_' . $registration_id );
		global $wpdb;
		$table = $wpdb->prefix . 'mi_registrations';
		$registration = $wpdb->get_row( $wpdb->prepare( "SELECT event_id, workspace_status FROM {$table} WHERE id = %d", $registration_id ), ARRAY_A );
		if ( ! $registration || ! MI_Access::can_access_event( (int) $registration['event_id'] ) ) {
			wp_die( esc_html__( 'Iscrizione non accessibile.', 'modulo-iscrizioni' ) );
		}
		$result = MI_Registration_Service::accoda_iscrizione_workspace( $registration_id );
		$url = add_query_arg(
			array(
				'post_type'          => MI_Event_Post_Type::EVENT_TYPE,
				'page'               => 'mi-registrations',
				'registration_id'    => $registration_id,
				'mi_workspace_retry' => 'SYNCED' === $result ? 'synced' : 'queued',
			),
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public static function export_registrations() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		check_admin_referer( 'mi_export_registrations' );
		global $wpdb;
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		$search = isset( $_GET['mi_search'] ) ? sanitize_text_field( wp_unslash( $_GET['mi_search'] ) ) : '';
		if ( $event_id && ! MI_Access::can_access_event( $event_id ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		$scope = MI_Access::activity_ids();
		$allowed_events = 'ALL' === $scope ? array() : get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) );
		$conditions = array();
		$parameters = array();
		if ( $event_id ) {
			$conditions[] = 'r.event_id = %d';
			$parameters[] = $event_id;
		} elseif ( 'ALL' !== $scope ) {
			$conditions[] = 'r.event_id IN (' . implode( ',', array_map( 'absint', $allowed_events ?: array( 0 ) ) ) . ')';
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$conditions[] = "(r.order_code LIKE %s OR r.buyer_first_name LIKE %s OR r.buyer_last_name LIKE %s OR CONCAT(r.buyer_first_name, ' ', r.buyer_last_name) LIKE %s OR r.buyer_email LIKE %s)";
			$parameters = array_merge( $parameters, array( $like, $like, $like, $like, $like ) );
		}
		$where = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';
		if ( $parameters ) {
			$where = $wpdb->prepare( $where, $parameters );
		}
		$registrations = $wpdb->prefix . 'mi_registrations';
		$participants = $wpdb->prefix . 'mi_participants';
		$rows = $wpdb->get_results( "SELECT r.order_code, r.event_id, r.status, r.workspace_status, r.buyer_first_name, r.buyer_last_name, r.buyer_email, r.buyer_phone, r.special_requests, r.economic_mode, r.total_cents, r.initial_due_cents, r.balance_cents, r.order_options_json, r.privacy_consent_id, r.privacy_policy_version, r.privacy_accepted_at, r.created_at, p.ticket_type_code, p.first_name, p.last_name, p.extra_json, p.options_json FROM {$registrations} r LEFT JOIN {$participants} p ON p.registration_id = r.id {$where} ORDER BY r.id DESC, p.id ASC", ARRAY_A );
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="iscrizioni-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" );
		$catalog = MI_Field_Schema::catalog();
		$extra_keys = array();
		foreach ( $rows as $row ) {
			$answers = json_decode( (string) $row['extra_json'], true );
			if ( is_array( $answers ) ) {
				$extra_keys = array_values( array_unique( array_merge( $extra_keys, array_keys( $answers ) ) ) );
			}
		}
		$headers = array( 'Codice iscrizione', 'Evento', 'Stato', 'Stato Workspace', 'Nome referente', 'Cognome referente', 'Email referente', 'Cellulare referente', 'Richieste particolari', 'Modalità di pagamento richiesta', 'Totale centesimi', 'Primo versamento centesimi', 'Saldo centesimi', 'Opzioni ordine JSON', 'ID consenso privacy', 'Versione informativa', 'Accettazione privacy UTC', 'Data UTC', 'Tipologia', 'Nome partecipante', 'Cognome partecipante', 'Opzioni partecipante JSON' );
		foreach ( $extra_keys as $key ) {
			$headers[] = isset( $catalog[ $key ]['label'] ) ? $catalog[ $key ]['label'] : 'Dato aggiuntivo (' . $key . ')';
		}
		fputcsv( $output, $headers, ';' );
		foreach ( $rows as $row ) {
			$answers = json_decode( (string) $row['extra_json'], true );
			$answers = is_array( $answers ) ? $answers : array();
			$line = array( $row['order_code'], get_the_title( (int) $row['event_id'] ), $row['status'], $row['workspace_status'], $row['buyer_first_name'], $row['buyer_last_name'], $row['buyer_email'], $row['buyer_phone'], $row['special_requests'], self::etichetta_modalita_economica( $row['economic_mode'] ), $row['total_cents'], $row['initial_due_cents'], $row['balance_cents'], $row['order_options_json'], $row['privacy_consent_id'], $row['privacy_policy_version'], $row['privacy_accepted_at'], $row['created_at'], $row['ticket_type_code'], $row['first_name'], $row['last_name'], $row['options_json'] );
			foreach ( $extra_keys as $key ) {
				$line[] = isset( $answers[ $key ] ) ? $answers[ $key ] : '';
			}
			fputcsv( $output, array_map( array( __CLASS__, 'safe_csv_value' ), $line ), ';' );
		}
		fclose( $output );
		exit;
	}

	private static function safe_csv_value( $value ) {
		$value = (string) $value;
		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
	}

	private static function formatta_importo( $cents ) {
		return number_format_i18n( max( 0, (int) $cents ) / 100, 2 ) . ' €';
	}

	private static function formatta_data_locale( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) return '—';
		$timezone = wp_timezone();
		$formats = false !== strpos( $value, 'T' )
			? array( 'Y-m-d\\TH:i:s', 'Y-m-d\\TH:i' )
			: array( 'Y-m-d H:i:s', 'Y-m-d H:i' );
		$source_timezone = false !== strpos( $value, 'T' ) ? $timezone : new DateTimeZone( 'UTC' );
		foreach ( $formats as $format ) {
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $value, $source_timezone );
			if ( $date instanceof DateTimeImmutable ) return wp_date( 'd M Y, H:i', $date->getTimestamp(), $timezone );
		}
		return $value;
	}

	private static function render_pagination( $page, $per_page, $total_rows ) {
		$total_pages = (int) ceil( max( 0, (int) $total_rows ) / max( 1, (int) $per_page ) );
		if ( $total_pages < 2 ) return;
		$base = str_replace( '999999999', '%#%', esc_url_raw( add_query_arg( 'paged', 999999999, remove_query_arg( array( 'registration_id', 'paged' ) ) ) ) );
		echo '<nav class="tablenav-pages" aria-label="Navigazione pagine">' . wp_kses_post( paginate_links( array( 'base' => $base, 'format' => '', 'current' => max( 1, (int) $page ), 'total' => $total_pages, 'prev_text' => '‹ Precedente', 'next_text' => 'Successiva ›', 'type' => 'plain' ) ) ) . '</nav>';
	}

	private static function parse_importo_centesimi( $raw ) {
		$value = preg_replace( '/\s+/', '', trim( sanitize_text_field( (string) $raw ) ) );
		if ( preg_match( '/^(?:\d{1,3})(?:\.\d{3})+(?:,\d{1,2})?$/', $value ) ) {
			$normalized = str_replace( array( '.', ',' ), array( '', '.' ), $value );
		} elseif ( preg_match( '/^(?:\d{1,3})(?:,\d{3})+(?:\.\d{1,2})?$/', $value ) ) {
			$normalized = str_replace( ',', '', $value );
		} elseif ( preg_match( '/^(?:0|[1-9]\d{0,6})(?:[.,]\d{1,2})?$/', $value ) ) {
			$normalized = str_replace( ',', '.', $value );
		} else {
			return null;
		}
		$amount = (float) $normalized;
		return $amount > 0 && $amount <= 1000000 ? (int) round( $amount * 100 ) : null;
	}

	private static function payment_where( $event_id, $source, $transaction, $from, $to ) {
		$scope = MI_Access::activity_ids();
		$conditions = array();
		$parameters = array();
		if ( $event_id ) {
			if ( ! MI_Access::can_access_event( $event_id ) ) return array( 'WHERE 1 = 0', array() );
			$conditions[] = 'r.event_id = %d'; $parameters[] = $event_id;
		} elseif ( 'ALL' !== $scope ) {
			$allowed = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) );
			$conditions[] = 'r.event_id IN (' . implode( ',', array_map( 'absint', $allowed ?: array( 0 ) ) ) . ')';
		}
		if ( $source ) { $conditions[] = 'p.payment_source = %s'; $parameters[] = $source; }
		if ( $transaction ) { $conditions[] = 'p.transaction_kind = %s'; $parameters[] = $transaction; }
		if ( $from ) { $conditions[] = 'p.effective_at >= %s'; $parameters[] = $from . ' 00:00:00'; }
		if ( $to ) { $conditions[] = 'p.effective_at <= %s'; $parameters[] = $to . ' 23:59:59'; }
		return array( $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '', $parameters );
	}

	private static function totale_pagamenti( $registration_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN transaction_kind = 'REFUND' THEN -amount_cents ELSE amount_cents END), 0) FROM {$wpdb->prefix}mi_payments WHERE registration_id = %d", $registration_id ) );
	}

	private static function contiene_numero_carta( $value ) {
		preg_match_all( '/(?:\d[ -]?){13,19}/', (string) $value, $matches );
		foreach ( $matches[0] as $match ) {
			$digits = preg_replace( '/\D/', '', $match );
			$sum = 0; $alternate = false;
			for ( $index = strlen( $digits ) - 1; $index >= 0; $index-- ) { $digit = (int) $digits[ $index ]; if ( $alternate ) { $digit *= 2; if ( $digit > 9 ) { $digit -= 9; } } $sum += $digit; $alternate = ! $alternate; }
			if ( strlen( $digits ) >= 13 && strlen( $digits ) <= 19 && 0 === $sum % 10 ) { return true; }
		}
		return false;
	}

	private static function etichetta_modalita_economica( $mode ) {
		$labels = array( 'REGISTRATION_ONLY' => 'Nessun pagamento previsto', 'PRICE_ONLY' => 'Prezzo solamente informativo', 'FULL_PAYMENT' => 'Pagamento completo richiesto', 'DEPOSIT_BALANCE' => 'Caparra richiesta, saldo successivo' );
		return $labels[ $mode ] ?? 'Nessun pagamento previsto';
	}

	private static function etichetta_stato( $status ) {
		$labels = array( 'PENDING_PAYMENT' => 'In attesa di pagamento', 'CONFIRMED' => 'Confermata', 'WAITLISTED' => 'Lista d’attesa', 'CANCELLED' => 'Annullata', 'EXPIRED' => 'Scaduta' );
		return $labels[ $status ] ?? (string) $status;
	}

	public static function outbox_page() {
		if ( ! current_user_can( 'mi_view_registrations' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		$registrations = $wpdb->prefix . 'mi_registrations';
		$detail_id = isset( $_GET['email_id'] ) ? absint( $_GET['email_id'] ) : 0;
		$scope = MI_Access::activity_ids();
		if ( 'ALL' === $scope ) {
			$where = '';
		} else {
			$allowed_events = get_posts( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mi_activity_id', 'value' => $scope ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' ) ) ) );
			$where = 'WHERE r.event_id IN (' . implode( ',', array_map( 'absint', $allowed_events ?: array( 0 ) ) ) . ')';
		}
		$rows = $wpdb->get_results( "SELECT o.id, o.registration_id, o.recipient, o.template_type, o.status, o.attempts, o.last_error, o.sent_at, o.created_at FROM {$table} o INNER JOIN {$registrations} r ON r.id = o.registration_id {$where} ORDER BY o.id DESC LIMIT 100", ARRAY_A );
		$detail = null;
		if ( $detail_id ) {
			$detail = $wpdb->get_row( $wpdb->prepare( "SELECT o.*, r.event_id FROM {$table} o INNER JOIN {$registrations} r ON r.id = o.registration_id WHERE o.id = %d", $detail_id ), ARRAY_A );
			if ( ! $detail || ! MI_Access::can_access_event( (int) $detail['event_id'] ) ) {
				wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
			}
		}
		?>
		<div class="wrap"><h1>Coda email</h1><p>Lo stato <code>PREVIEW</code> non viene spedito; <code>PENDING</code> indica una conferma operativa in attesa.</p>
		<table class="widefat striped"><thead><tr><th>ID</th><th>Iscrizione</th><th>Destinatario</th><th>Modello</th><th>Stato</th><th>Tentativi</th><th>Ultimo errore</th><th>Inviata il</th><th>Data UTC</th><th></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="10">Coda vuota.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?><?php $preview_url = add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-email-outbox', 'email_id' => (int) $row['id'] ), admin_url( 'edit.php' ) ); ?><tr><td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['registration_id'] ); ?></td><td><?php echo esc_html( $row['recipient'] ); ?></td><td><?php echo esc_html( $row['template_type'] ); ?></td><td><?php echo esc_html( $row['status'] ); ?></td><td><?php echo esc_html( $row['attempts'] ); ?></td><td><?php echo esc_html( $row['last_error'] ?: 'Nessuno' ); ?></td><td><?php echo esc_html( $row['sent_at'] ?: 'Non inviata' ); ?></td><td><?php echo esc_html( $row['created_at'] ); ?></td><td><a href="<?php echo esc_url( $preview_url ); ?>">Apri dettaglio</a><?php if ( in_array( $row['status'], array( 'FAILED', 'SENDING' ), true ) ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:8px"><input type="hidden" name="action" value="mi_riaccoda_email"><input type="hidden" name="email_id" value="<?php echo esc_attr( $row['id'] ); ?>"><?php wp_nonce_field( 'mi_riaccoda_email' ); ?><button class="button-link">Riaccoda</button></form><?php endif; ?></td></tr><?php endforeach; ?>
		</tbody></table>
		<?php if ( $detail ) : ?><?php $payload = json_decode( (string) $detail['payload_json'], true ); $preview = is_array( $payload ) && isset( $payload['email_preview'] ) && is_array( $payload['email_preview'] ) ? $payload['email_preview'] : array(); ?>
		<hr><h2>Anteprima email conservata</h2>
		<?php $economic_summary = is_array( $payload ) && isset( $payload['economic_summary'] ) && is_array( $payload['economic_summary'] ) ? $payload['economic_summary'] : array(); ?>
		<?php if ( $economic_summary ) : ?><div class="card" style="max-width:900px"><h3>Riepilogo economico conservato</h3><p><strong>Totale:</strong> <?php echo esc_html( number_format_i18n( (int) $economic_summary['total_cents'] / 100, 2 ) ); ?> €</p><p><strong>Primo versamento:</strong> <?php echo esc_html( number_format_i18n( (int) $economic_summary['initial_due_cents'] / 100, 2 ) ); ?> €</p><p><strong>Saldo successivo:</strong> <?php echo esc_html( number_format_i18n( (int) $economic_summary['balance_cents'] / 100, 2 ) ); ?> €</p></div><?php endif; ?>
		<?php if ( ! $preview ) : ?><p>Questa voce precede l’introduzione delle anteprime complete.</p><?php else : ?>
		<?php if ( ! empty( $preview['identificativo'] ) && is_array( $preview['identificativo'] ) && 'NONE' !== ( $preview['identificativo']['modalita'] ?? 'NONE' ) ) : ?><p><strong>Identificativo <?php echo esc_html( $preview['identificativo']['modalita'] ); ?>:</strong> <code><?php echo esc_html( $preview['identificativo']['codice'] ?? '' ); ?></code></p><?php endif; ?>
		<p><strong>Oggetto:</strong> <?php echo esc_html( $preview['oggetto'] ?? '' ); ?></p>
		<p><strong>Preheader:</strong> <?php echo esc_html( $preview['preheader'] ?? '' ); ?></p>
		<div class="card" style="max-width:900px"><?php echo MI_Modello_Email::sanitizza_html_email( MI_Modello_Email::componi_html( $preview ) ); ?></div>
		<h3>Versione testo semplice</h3><pre style="white-space:pre-wrap;max-width:900px"><?php echo esc_html( $preview['testo'] ?? '' ); ?></pre>
		<p><small>Revisione: <code><?php echo esc_html( substr( (string) ( $preview['revisione'] ?? '' ), 0, 12 ) ); ?></code></small></p>
		<?php endif; ?><?php endif; ?>
		</div>
		<?php
	}

	public static function event_row_actions( $actions, $post ) {
		if ( MI_Event_Post_Type::EVENT_TYPE !== $post->post_type || 'mi_archived' === $post->post_status || ! current_user_can( 'mi_manage_events' ) ) {
			return $actions;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=mi_archive_event&event_id=' . $post->ID ), 'mi_archive_event_' . $post->ID );
		$actions['mi_archive'] = '<a href="' . esc_url( $url ) . '">Archivia</a>';
		return $actions;
	}

	public static function archive_event() {
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		check_admin_referer( 'mi_archive_event_' . $event_id );
		if ( ! $event_id || ! current_user_can( 'mi_manage_events' ) || MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event_id ) || ! MI_Access::can_access_event( $event_id ) ) {
			wp_die( esc_html__( 'Operazione non consentita.', 'modulo-iscrizioni' ) );
		}
		wp_update_post( array( 'ID' => $event_id, 'post_status' => 'mi_archived' ) );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE ) );
		exit;
	}

	public static function guard_publication( $data, $postarr ) {
		if ( MI_Event_Post_Type::EVENT_TYPE !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) ) {
			return $data;
		}
		if ( ! isset( $_POST['mi_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_event_nonce'] ) ), 'mi_save_event' ) ) {
			return $data;
		}
		$activity_id = isset( $_POST['mi_activity_id'] ) ? absint( $_POST['mi_activity_id'] ) : 0;
		$post_id = absint( $postarr['ID'] ?? 0 );
		$current_activity_id = $post_id ? absint( get_post_meta( $post_id, '_mi_activity_id', true ) ) : 0;
		$activity_stable = true;
		if ( $post_id && $current_activity_id && $activity_id !== $current_activity_id ) {
			global $wpdb;
			$activity_stable = ! (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}mi_registrations WHERE event_id = %d LIMIT 1", $post_id ) );
		}
		$opens = isset( $_POST['mi_registration_opens_at'] ) ? sanitize_text_field( wp_unslash( $_POST['mi_registration_opens_at'] ) ) : '';
		$closes = isset( $_POST['mi_registration_closes_at'] ) ? sanitize_text_field( wp_unslash( $_POST['mi_registration_closes_at'] ) ) : '';
		$valid_dates = preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $opens ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $closes ) && $closes > $opens;
		$has_ticket = ! empty( $_POST['mi_ticket_code'][0] ) && ! empty( $_POST['mi_ticket_name'][0] );
		$economic_mode = isset( $_POST['mi_economic_mode'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['mi_economic_mode'] ) ) ) : 'REGISTRATION_ONLY';
		$pricing_mode = isset( $_POST['mi_pricing_mode'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['mi_pricing_mode'] ) ) ) : 'NONE';
		$prices = isset( $_POST['mi_ticket_price'] ) ? array_map( 'floatval', (array) wp_unslash( $_POST['mi_ticket_price'] ) ) : array();
		$option_prices = isset( $_POST['mi_option_price'] ) ? array_map( 'floatval', (array) wp_unslash( $_POST['mi_option_price'] ) ) : array();
		$all_prices = array_merge( $prices, $option_prices );
		$payment_methods = isset( $_POST['mi_payment_methods'] ) ? (array) wp_unslash( $_POST['mi_payment_methods'] ) : array();
		$privacy_version = (string) ( get_post_meta( $post_id, '_mi_privacy_policy_version', true ) ?: wp_date( 'Y-m' ) );
		$privacy_consent_id = (string) ( get_post_meta( $post_id, '_mi_privacy_consent_id', true ) ?: 'privacy-' . $post_id );
		$privacy_valid = '' !== $privacy_version && '' !== $privacy_consent_id && (bool) get_privacy_policy_url();
		$marketing_valid = ! isset( $_POST['mi_marketing_enabled'] ) || '' !== (string) ( get_post_meta( $post_id, '_mi_marketing_consent_id', true ) ?: 'marketing-' . $post_id );
		$uses_price = in_array( $economic_mode, array( 'PRICE_ONLY', 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true );
		$collects_payment = in_array( $economic_mode, array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true );
		$registration_only_price = 'REGISTRATION_ONLY' === $economic_mode && in_array( $pricing_mode, array( 'NONE', 'ZERO' ), true ) && max( array_merge( array( 0 ), $all_prices ) ) <= 0;
		$calculated_price = $uses_price && 'CALCULATED' === $pricing_mode && max( array_merge( array( 0 ), $all_prices ) ) > 0;
		$fixed_price_cents = self::parse_importo_centesimi( wp_unslash( $_POST['mi_fixed_price'] ?? '' ) );
		$fixed_price_valid = $uses_price && 'FIXED' === $pricing_mode && null !== $fixed_price_cents && $fixed_price_cents > 0;
		$valid_economic = ( $registration_only_price || $calculated_price || $fixed_price_valid ) && ( ! $collects_payment || ! empty( $payment_methods ) );
		if ( ! $activity_id || MI_Event_Post_Type::ACTIVITY_TYPE !== get_post_type( $activity_id ) || ! $activity_stable || ! $valid_dates || ! $has_ticket || ! $valid_economic || ! $privacy_valid || ! $marketing_valid ) {
			$data['post_status'] = 'draft';
			$message = 'Evento mantenuto in bozza: completa gruppo, date e tipologie.';
			if ( ! $activity_stable ) {
				$message = 'Evento mantenuto in bozza: il gruppo non può cambiare dopo la prima iscrizione senza una migrazione amministrativa esplicita.';
			} elseif ( ! $valid_economic ) {
				$message = 'Evento mantenuto in bozza: “Gratuito” richiede “Nessun pagamento previsto”; le altre modalità richiedono una quota positiva e, quando previsto, almeno una fonte di pagamento.';
			} elseif ( ! $privacy_valid ) {
				$message = 'Evento mantenuto in bozza: configura la pagina privacy di WordPress, la versione dell’informativa e l’ID del consenso.';
			} elseif ( ! $marketing_valid ) {
				$message = 'Evento mantenuto in bozza: il campo facoltativo “Comunicazioni su future iniziative” richiede un ID specifico.';
			}
			set_transient( 'mi_publication_error_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
		}
		return $data;
	}

	public static function publication_notice() {
		$key = 'mi_publication_error_' . get_current_user_id();
		$message = get_transient( $key );
		if ( $message ) {
			delete_transient( $key );
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->post_type, array( MI_Event_Post_Type::EVENT_TYPE, MI_Event_Post_Type::ACTIVITY_TYPE ), true ) ) {
			echo '<div class="notice notice-info"><p><strong>Spedizione email:</strong> la modalità iniziale è Anteprima; l’amministratore può collaudare un messaggio sintetico prima di abilitare le conferme operative.</p></div>';
		}
	}
}
