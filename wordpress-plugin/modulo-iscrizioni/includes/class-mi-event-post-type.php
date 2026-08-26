<?php

defined( 'ABSPATH' ) || exit;

final class MI_Event_Post_Type {
	const EVENT_TYPE = 'mi_event';
	const ACTIVITY_TYPE = 'mi_activity';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_types' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::EVENT_TYPE, array( __CLASS__, 'save_event' ), 10, 2 );
		add_action( 'save_post_' . self::EVENT_TYPE, array( __CLASS__, 'publish_revision' ), 30, 2 );
		add_action( 'save_post_' . self::ACTIVITY_TYPE, array( __CLASS__, 'save_activity' ), 10, 2 );
		add_filter( 'manage_' . self::EVENT_TYPE . '_posts_columns', array( __CLASS__, 'event_columns' ) );
		add_action( 'manage_' . self::EVENT_TYPE . '_posts_custom_column', array( __CLASS__, 'render_event_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
	}

	public static function register_types() {
		register_post_status(
			'mi_archived',
			array(
				'label'                     => 'Archiviato',
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Archiviato <span class="count">(%s)</span>', 'Archiviati <span class="count">(%s)</span>', 'modulo-iscrizioni' ),
			)
		);

		$event_caps = array(
			'edit_post'              => 'edit_mi_event',
			'read_post'              => 'read_mi_event',
			'delete_post'            => 'delete_mi_event',
			'edit_posts'             => 'mi_manage_events',
			'edit_others_posts'      => 'mi_manage_events',
			'publish_posts'          => 'mi_publish_events',
			'read_private_posts'     => 'mi_manage_events',
			'delete_posts'           => 'mi_manage_events',
			'delete_private_posts'   => 'mi_manage_events',
			'delete_published_posts' => 'mi_manage_events',
			'edit_private_posts'     => 'mi_manage_events',
			'edit_published_posts'   => 'mi_manage_events',
			'create_posts'           => 'mi_manage_events',
		);

		register_post_type(
			self::EVENT_TYPE,
			array(
				'labels' => array(
					'name'          => 'Eventi iscrizioni',
					'singular_name' => 'Evento iscrizioni',
					'add_new_item'  => 'Aggiungi evento',
					'edit_item'     => 'Modifica evento',
					'menu_name'     => 'Modulo iscrizioni',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-tickets-alt',
				'supports'            => array( 'title', 'editor', 'thumbnail' ),
				'capabilities'        => $event_caps,
				'map_meta_cap'        => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
			)
		);

		register_post_type(
			self::ACTIVITY_TYPE,
			array(
				'labels' => array(
					'name'          => 'Attività',
					'singular_name' => 'Attività',
					'add_new_item'  => 'Aggiungi attività',
					'edit_item'     => 'Modifica attività',
					'menu_name'     => 'Attività',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::EVENT_TYPE,
				'supports'            => array( 'title', 'editor', 'thumbnail' ),
				'capabilities'        => array_fill_keys( array_keys( $event_caps ), 'manage_options' ),
				'map_meta_cap'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
			)
		);
	}

	public static function add_meta_boxes() {
		add_meta_box( 'mi_event_configuration', 'Configurazione iscrizioni', array( __CLASS__, 'render_event_box' ), self::EVENT_TYPE, 'normal', 'high' );
		add_meta_box( 'mi_event_shortcode', 'Pubblicazione nel sito', array( __CLASS__, 'render_shortcode_box' ), self::EVENT_TYPE, 'side', 'default' );
		add_meta_box( 'mi_activity_branding', 'Identità attività', array( __CLASS__, 'render_activity_box' ), self::ACTIVITY_TYPE, 'side', 'default' );
	}

	public static function render_event_box( $post ) {
		wp_nonce_field( 'mi_save_event', 'mi_event_nonce' );
		$activity_id = absint( get_post_meta( $post->ID, '_mi_activity_id', true ) );
		$opens_at = (string) get_post_meta( $post->ID, '_mi_registration_opens_at', true );
		$closes_at = (string) get_post_meta( $post->ID, '_mi_registration_closes_at', true );
		$event_starts_at = (string) get_post_meta( $post->ID, '_mi_event_starts_at', true );
		$event_location = (string) get_post_meta( $post->ID, '_mi_event_location', true );
		$capacity = max( 1, absint( get_post_meta( $post->ID, '_mi_capacity', true ) ?: 30 ) );
		$waitlist = '1' === get_post_meta( $post->ID, '_mi_waitlist_enabled', true );
		$pricing_mode = get_post_meta( $post->ID, '_mi_pricing_mode', true ) ?: 'NONE';
		$economic_mode = get_post_meta( $post->ID, '_mi_economic_mode', true ) ?: 'REGISTRATION_ONLY';
		$deposit_percentage = min( 99, max( 1, absint( get_post_meta( $post->ID, '_mi_deposit_percentage', true ) ?: 30 ) ) );
		$payment_methods = get_post_meta( $post->ID, '_mi_payment_methods', true );
		$payment_methods = is_array( $payment_methods ) ? $payment_methods : array();
		$identifier_display = get_post_meta( $post->ID, '_mi_identifier_display', true ) ?: 'TEXT';
		$payment_deadline_at = (string) get_post_meta( $post->ID, '_mi_payment_deadline_at', true );
		$privacy_policy_version = (string) get_post_meta( $post->ID, '_mi_privacy_policy_version', true );
		$privacy_consent_id = (string) ( get_post_meta( $post->ID, '_mi_privacy_consent_id', true ) ?: 'privacy-' . $post->ID );
		$marketing_enabled = '1' === get_post_meta( $post->ID, '_mi_marketing_enabled', true );
		$marketing_consent_id = (string) ( get_post_meta( $post->ID, '_mi_marketing_consent_id', true ) ?: 'marketing-' . $post->ID );
		$high_impact_approved = '1' === get_post_meta( $post->ID, '_mi_high_impact_approved', true );
		$ticket_types = get_post_meta( $post->ID, '_mi_ticket_types', true );
		$options = get_post_meta( $post->ID, '_mi_options', true );
		$options = is_array( $options ) ? $options : array();
		$field_configuration = MI_Field_Schema::event_configuration( $post->ID );
		$field_catalog = MI_Field_Schema::catalog();
		$field_profiles = MI_Field_Schema::profiles();
		if ( ! is_array( $ticket_types ) || empty( $ticket_types ) ) {
			$ticket_types = array( array( 'code' => 'standard', 'name' => 'Iscrizione', 'price_cents' => 0, 'max_per_order' => 5, 'capacity' => 0 ) );
		}
		$activities = get_posts( array( 'post_type' => self::ACTIVITY_TYPE, 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		if ( class_exists( 'MI_Access' ) && 'ALL' !== MI_Access::activity_ids() ) {
			$scope = MI_Access::activity_ids();
			$activities = array_values( array_filter( $activities, static function ( $activity ) use ( $scope ) { return in_array( $activity->ID, $scope, true ); } ) );
		}
		?>
		<div class="mi-admin-grid">
			<p><label for="mi_activity_id"><strong>Attività organizzatrice</strong></label><br>
			<select id="mi_activity_id" name="mi_activity_id" required>
				<option value="">Seleziona attività</option>
				<?php foreach ( $activities as $activity ) : ?>
					<option value="<?php echo esc_attr( $activity->ID ); ?>" <?php selected( $activity_id, $activity->ID ); ?>><?php echo esc_html( $activity->post_title ); ?></option>
				<?php endforeach; ?>
			</select></p>
			<p><label for="mi_capacity"><strong>Posti disponibili</strong></label><br><input id="mi_capacity" name="mi_capacity" type="number" min="1" max="10000" value="<?php echo esc_attr( $capacity ); ?>" required></p>
			<p><label for="mi_registration_opens_at"><strong>Apertura iscrizioni</strong></label><br><input id="mi_registration_opens_at" name="mi_registration_opens_at" type="datetime-local" value="<?php echo esc_attr( $opens_at ); ?>" required></p>
			<p><label for="mi_registration_closes_at"><strong>Chiusura iscrizioni</strong></label><br><input id="mi_registration_closes_at" name="mi_registration_closes_at" type="datetime-local" value="<?php echo esc_attr( $closes_at ); ?>" required></p>
			<p><label for="mi_event_starts_at"><strong>Data e ora dell’evento</strong></label><br><input id="mi_event_starts_at" name="mi_event_starts_at" type="datetime-local" value="<?php echo esc_attr( $event_starts_at ); ?>"></p>
			<p><label for="mi_event_location"><strong>Luogo dell’evento</strong></label><br><input id="mi_event_location" name="mi_event_location" type="text" maxlength="180" value="<?php echo esc_attr( $event_location ); ?>" placeholder="Es. Piazza San Pietro, Roma"></p>
			<p><label><input name="mi_waitlist_enabled" type="checkbox" value="1" <?php checked( $waitlist ); ?>> Attiva automaticamente la lista d’attesa a esaurimento posti</label></p>
			<p><label for="mi_payment_deadline_at"><strong>Scadenza prenotazioni non saldate</strong></label><br><input id="mi_payment_deadline_at" name="mi_payment_deadline_at" type="datetime-local" value="<?php echo esc_attr( $payment_deadline_at ); ?>"></p>
			<p class="description">Lascia vuoto per non applicare una scadenza automatica. È usata soltanto per gli eventi con versamenti tracciati.</p>
			<p><label for="mi_pricing_mode"><strong>Prezzo</strong></label><br><select id="mi_pricing_mode" name="mi_pricing_mode"><option value="NONE" <?php selected( $pricing_mode, 'NONE' ); ?>>Nessun prezzo</option><option value="ZERO" <?php selected( $pricing_mode, 'ZERO' ); ?>>Gratuito esplicito</option><option value="CALCULATED" <?php selected( $pricing_mode, 'CALCULATED' ); ?>>Calcolato dalle quote</option></select></p>
			<p><label for="mi_economic_mode"><strong>Modalità di pagamento richiesta</strong></label><br><select id="mi_economic_mode" name="mi_economic_mode"><option value="REGISTRATION_ONLY" <?php selected( $economic_mode, 'REGISTRATION_ONLY' ); ?>>Nessun pagamento previsto</option><option value="PRICE_ONLY" <?php selected( $economic_mode, 'PRICE_ONLY' ); ?>>Prezzo solamente informativo</option><option value="FULL_PAYMENT" <?php selected( $economic_mode, 'FULL_PAYMENT' ); ?>>Pagamento completo richiesto</option><option value="DEPOSIT_BALANCE" <?php selected( $economic_mode, 'DEPOSIT_BALANCE' ); ?>>Caparra richiesta, saldo successivo</option></select></p>
			<p data-mi-economic-deposit><label for="mi_deposit_percentage"><strong>Caparra percentuale</strong></label><br><input id="mi_deposit_percentage" name="mi_deposit_percentage" type="number" min="1" max="99" value="<?php echo esc_attr( $deposit_percentage ); ?>"> %</p>
			<fieldset data-mi-economic-payments><legend><strong>Fonti di pagamento ammesse</strong></legend><label><input type="checkbox" name="mi_payment_methods[]" value="BANK_TRANSFER" <?php checked( in_array( 'BANK_TRANSFER', $payment_methods, true ) ); ?>> Bonifico</label><br><label><input type="checkbox" name="mi_payment_methods[]" value="CARD" <?php checked( in_array( 'CARD', $payment_methods, true ) ); ?>> Carta</label><br><label><input type="checkbox" name="mi_payment_methods[]" value="CASH" <?php checked( in_array( 'CASH', $payment_methods, true ) ); ?>> Contante</label></fieldset>
			<p class="description" data-mi-economic-help aria-live="polite"></p>
			<p><label for="mi_identifier_display"><strong>Identificativo nell’email</strong></label><br><select id="mi_identifier_display" name="mi_identifier_display"><option value="NONE" <?php selected( $identifier_display, 'NONE' ); ?>>Non mostrare</option><option value="TEXT" <?php selected( $identifier_display, 'TEXT' ); ?>>Testo</option><option value="QR" <?php selected( $identifier_display, 'QR' ); ?>>QR facoltativo</option><option value="BARCODE" <?php selected( $identifier_display, 'BARCODE' ); ?>>Barcode facoltativo</option></select></p>
			<p class="description">QR e barcode sono scelte dell’organizzatore. Il payload resta legato all’evento e al codice ordine, senza dati personali.</p>
			<p><label for="mi_privacy_policy_version"><strong>Versione informativa privacy</strong></label><br><input id="mi_privacy_policy_version" name="mi_privacy_policy_version" maxlength="64" value="<?php echo esc_attr( $privacy_policy_version ); ?>" placeholder="Es. 2026-08"></p>
			<p><label for="mi_privacy_consent_id"><strong>ID consenso privacy</strong></label><br><input id="mi_privacy_consent_id" name="mi_privacy_consent_id" maxlength="100" value="<?php echo esc_attr( $privacy_consent_id ); ?>"></p>
			<p><label><input name="mi_marketing_enabled" type="checkbox" value="1" <?php checked( $marketing_enabled ); ?>> Mostra il campo facoltativo “Comunicazioni su future iniziative”</label></p>
			<p><label for="mi_marketing_consent_id"><strong>ID del consenso alle comunicazioni</strong></label><br><input id="mi_marketing_consent_id" name="mi_marketing_consent_id" maxlength="100" value="<?php echo esc_attr( $marketing_consent_id ); ?>"></p>
			<p><label><input name="mi_high_impact_approved" type="checkbox" value="1" <?php checked( $high_impact_approved ); ?>> La verifica privacy per campi ad alto impatto è stata approvata</label></p>
		</div>
		<hr>
		<h3>Dati dei partecipanti</h3>
		<?php $participant_extra_scope = 'ALL' === strtoupper( (string) get_post_meta( $post->ID, '_mi_participant_extra_scope', true ) ) ? 'ALL' : 'ONE'; ?>
		<fieldset><legend><strong>A chi chiedere i dati aggiuntivi</strong></legend>
			<label><input type="radio" name="mi_participant_extra_scope" value="ONE" <?php checked( $participant_extra_scope, 'ONE' ); ?>> Solo a uno degli iscritti (il primo è selezionato automaticamente)</label><br>
			<label><input type="radio" name="mi_participant_extra_scope" value="ALL" <?php checked( $participant_extra_scope, 'ALL' ); ?>> A tutti gli iscritti</label>
		</fieldset>
		<p class="description">Nome e cognome restano sempre obbligatori per ogni partecipante. Se scegli “solo a uno”, gli altri dati possono comunque essere aggiunti facoltativamente.</p>
		<p><label for="mi_data_profile"><strong>Profilo iniziale</strong></label><br>
		<select id="mi_data_profile" name="mi_data_profile">
		<?php foreach ( $field_profiles as $profile_key => $profile ) : ?>
			<option value="<?php echo esc_attr( $profile_key ); ?>" <?php selected( $field_configuration['profile'], $profile_key ); ?>><?php echo esc_html( $profile['label'] ); ?></option>
		<?php endforeach; ?>
		</select></p>
		<p class="description">Nome e cognome sono sempre richiesti. Attiva soltanto gli altri dati realmente necessari per questo evento.</p>
		<div class="mi-field-config" data-mi-profiles="<?php echo esc_attr( wp_json_encode( $field_profiles ) ); ?>">
		<?php foreach ( $field_catalog as $field_key => $field ) : ?>
			<div class="mi-field-config__item" data-mi-field="<?php echo esc_attr( $field_key ); ?>">
				<label><input type="checkbox" name="mi_participant_fields[]" value="<?php echo esc_attr( $field_key ); ?>" <?php checked( in_array( $field_key, $field_configuration['enabled'], true ) ); ?> data-mi-field-enabled> <strong><?php echo esc_html( $field['label'] ); ?></strong></label>
				<label class="mi-field-config__required"><input type="checkbox" name="mi_participant_required[]" value="<?php echo esc_attr( $field_key ); ?>" <?php checked( in_array( $field_key, $field_configuration['required'], true ) ); ?> data-mi-field-required> Obbligatorio</label>
				<small><?php echo esc_html( $field['help'] ); ?></small>
			</div>
		<?php endforeach; ?>
		</div>
		<div class="mi-field-preview" aria-live="polite">
			<strong>Anteprima partecipante</strong>
			<ul data-mi-field-preview></ul>
		</div>
		<h3>Tipologie di iscrizione</h3>
		<table class="widefat striped" id="mi-ticket-types"><thead><tr><th>Codice</th><th>Nome</th><th>Prezzo €</th><th>Massimo per ordine</th><th>Capienza tipo</th><th></th></tr></thead><tbody>
		<?php foreach ( $ticket_types as $index => $ticket ) : ?>
			<tr><td><input name="mi_ticket_code[]" value="<?php echo esc_attr( $ticket['code'] ); ?>" pattern="[a-z0-9-]+" required></td><td><input name="mi_ticket_name[]" value="<?php echo esc_attr( $ticket['name'] ); ?>" required></td><td><input name="mi_ticket_price[]" type="number" min="0" step="0.01" value="<?php echo esc_attr( number_format( (int) $ticket['price_cents'] / 100, 2, '.', '' ) ); ?>" required></td><td><input name="mi_ticket_max[]" type="number" min="1" max="20" value="<?php echo esc_attr( $ticket['max_per_order'] ); ?>" required></td><td><input name="mi_ticket_capacity[]" type="number" min="0" max="10000" value="<?php echo esc_attr( absint( $ticket['capacity'] ?? 0 ) ); ?>"><small>0 = capienza evento</small></td><td><button type="button" class="button mi-remove-ticket">Rimuovi</button></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<p><button type="button" class="button" id="mi-add-ticket">Aggiungi tipologia</button></p>
		<h3>Opzioni</h3>
		<table class="widefat striped" id="mi-options"><thead><tr><th>Codice</th><th>Etichetta</th><th>Ambito</th><th>Prezzo €</th><th>Massimo</th><th></th></tr></thead><tbody>
		<?php foreach ( $options as $option ) : ?>
		<tr><td><input name="mi_option_code[]" value="<?php echo esc_attr( $option['code'] ); ?>" pattern="[a-z0-9-]+" required></td><td><input name="mi_option_name[]" value="<?php echo esc_attr( $option['name'] ); ?>" required></td><td><select name="mi_option_scope[]"><option value="ORDER" <?php selected( $option['scope'], 'ORDER' ); ?>>Ordine</option><option value="TICKET" <?php selected( $option['scope'], 'TICKET' ); ?>>Partecipante</option></select></td><td><input name="mi_option_price[]" type="number" min="0" step="0.01" value="<?php echo esc_attr( number_format( (int) $option['price_cents'] / 100, 2, '.', '' ) ); ?>"></td><td><input name="mi_option_max[]" type="number" min="1" max="20" value="<?php echo esc_attr( absint( $option['max_quantity'] ?? 1 ) ); ?>"></td><td><button type="button" class="button mi-remove-option">Rimuovi</button></td></tr>
		<?php endforeach; ?>
		</tbody></table><p><button type="button" class="button" id="mi-add-option">Aggiungi opzione</button></p>
		<p class="description">Il totale sarà sempre ricalcolato sul server. La spedizione delle conferme dipende dalla modalità email scelta dall’amministratore.</p>
		<?php
	}

	public static function render_shortcode_box( $post ) {
		echo '<p>Inserisci questo shortcode nella bozza destinata al modulo:</p>';
		echo '<code>[modulo_iscrizioni event=&quot;' . esc_html( $post->ID ) . '&quot;]</code>';
		$preview_url = wp_nonce_url( add_query_arg( array( 'action' => 'mi_anteprima_evento', 'event' => $post->ID ), admin_url( 'admin-post.php' ) ), 'mi_anteprima_evento_' . $post->ID );
		echo '<p><a class="button button-primary" href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener">Apri anteprima riservata</a></p>';
		echo '<p><strong>Pagina consigliata:</strong> scegli il modello “Iscrizione — modalità concentrata” negli attributi della pagina. Il modello nasconde soltanto in quella pagina menu, intestazione, barra laterale e piè di pagina del tema.</p>';
		echo '<p>Per completare la testata pubblica imposta data, ora e luogo e usa l’immagine in evidenza come copertina.</p>';
	}

	public static function render_activity_box( $post ) {
		wp_nonce_field( 'mi_save_activity', 'mi_activity_nonce' );
		$primary_color = sanitize_hex_color( get_post_meta( $post->ID, '_mi_primary_color', true ) ) ?: ( sanitize_hex_color( get_post_meta( $post->ID, '_mi_accent_color', true ) ) ?: '#151b38' );
		$secondary_color = sanitize_hex_color( get_post_meta( $post->ID, '_mi_secondary_color', true ) ) ?: '#337ab7';
		echo '<p>Usa l’immagine in evidenza come logo dell’attività. Logo e colori vengono ereditati dai suoi eventi e dalle email.</p>';
		echo '<p><label for="mi_primary_color"><strong>Colore primario</strong></label><br><input id="mi_primary_color" name="mi_primary_color" type="color" value="' . esc_attr( $primary_color ) . '"></p>';
		echo '<p><label for="mi_secondary_color"><strong>Colore secondario / CTA</strong></label><br><input id="mi_secondary_color" name="mi_secondary_color" type="color" value="' . esc_attr( $secondary_color ) . '"></p>';
		echo '<p class="description">Il primario identifica intestazioni e stati attivi; il secondario viene usato per pulsanti e link. Il testo email sceglie automaticamente bianco o scuro per il contrasto.</p>';
	}

	public static function save_activity( $post_id, $post ) {
		if ( ! isset( $_POST['mi_activity_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_activity_nonce'] ) ), 'mi_save_activity' ) ) return;
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'manage_options' ) ) return;
		$primary_color = isset( $_POST['mi_primary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['mi_primary_color'] ) ) : '';
		$secondary_color = isset( $_POST['mi_secondary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['mi_secondary_color'] ) ) : '';
		$primary_color = $primary_color ?: '#151b38';
		$secondary_color = $secondary_color ?: '#337ab7';
		update_post_meta( $post_id, '_mi_primary_color', $primary_color );
		update_post_meta( $post_id, '_mi_secondary_color', $secondary_color );
		update_post_meta( $post_id, '_mi_accent_color', $primary_color );
		$dependent_events = get_posts( array( 'post_type' => self::EVENT_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_mi_activity_id', 'meta_value' => $post_id ) );
		foreach ( $dependent_events as $event_id ) update_post_meta( $event_id, '_mi_needs_republish', '1' );
	}

	public static function save_event( $post_id, $post ) {
		if ( ! isset( $_POST['mi_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_event_nonce'] ) ), 'mi_save_event' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'mi_manage_events' ) ) {
			return;
		}

		$activity_id = isset( $_POST['mi_activity_id'] ) ? absint( $_POST['mi_activity_id'] ) : 0;
		$current_activity_id = absint( get_post_meta( $post_id, '_mi_activity_id', true ) );
		if ( $current_activity_id && $activity_id !== $current_activity_id ) {
			global $wpdb;
			$has_registrations = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}mi_registrations WHERE event_id = %d LIMIT 1", $post_id ) );
			if ( $has_registrations ) {
				$activity_id = $current_activity_id;
				set_transient( 'mi_publication_error_' . get_current_user_id(), 'Attività non modificata: un evento con iscrizioni richiede una migrazione amministrativa esplicita.', MINUTE_IN_SECONDS );
			}
		}
		if ( $activity_id && self::ACTIVITY_TYPE === get_post_type( $activity_id ) ) {
			if ( ! MI_Access::can_access_activity( $activity_id ) ) {
				$activity_id = $current_activity_id;
				set_transient( 'mi_publication_error_' . get_current_user_id(), 'Attività non modificata: non disponi dell’autorizzazione necessaria. Le altre modifiche sono state salvate.', MINUTE_IN_SECONDS );
			}
			if ( $activity_id ) update_post_meta( $post_id, '_mi_activity_id', $activity_id );
		}

		$capacity = isset( $_POST['mi_capacity'] ) ? min( 10000, max( 1, absint( $_POST['mi_capacity'] ) ) ) : 30;
		update_post_meta( $post_id, '_mi_capacity', $capacity );
		update_post_meta( $post_id, '_mi_waitlist_enabled', isset( $_POST['mi_waitlist_enabled'] ) ? '1' : '0' );
		$payment_deadline_at = isset( $_POST['mi_payment_deadline_at'] ) ? sanitize_text_field( wp_unslash( $_POST['mi_payment_deadline_at'] ) ) : '';
		if ( '' === $payment_deadline_at || preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $payment_deadline_at ) ) {
			update_post_meta( $post_id, '_mi_payment_deadline_at', $payment_deadline_at );
			delete_post_meta( $post_id, '_mi_reservation_minutes' );
		}
		update_post_meta( $post_id, '_mi_privacy_policy_version', sanitize_text_field( wp_unslash( $_POST['mi_privacy_policy_version'] ?? '' ) ) );
		update_post_meta( $post_id, '_mi_privacy_consent_id', sanitize_key( wp_unslash( $_POST['mi_privacy_consent_id'] ?? '' ) ) );
		update_post_meta( $post_id, '_mi_marketing_enabled', isset( $_POST['mi_marketing_enabled'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_mi_marketing_consent_id', sanitize_key( wp_unslash( $_POST['mi_marketing_consent_id'] ?? '' ) ) );
		update_post_meta( $post_id, '_mi_high_impact_approved', isset( $_POST['mi_high_impact_approved'] ) ? '1' : '0' );

		foreach ( array( 'opens_at', 'closes_at' ) as $field ) {
			$key = 'mi_registration_' . $field;
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
				update_post_meta( $post_id, '_mi_registration_' . $field, $value );
			}
		}
		$event_starts_at = isset( $_POST['mi_event_starts_at'] ) ? sanitize_text_field( wp_unslash( $_POST['mi_event_starts_at'] ) ) : '';
		if ( '' === $event_starts_at || preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $event_starts_at ) ) {
			update_post_meta( $post_id, '_mi_event_starts_at', $event_starts_at );
		}
		$event_location = isset( $_POST['mi_event_location'] ) ? sanitize_text_field( wp_unslash( $_POST['mi_event_location'] ) ) : '';
		update_post_meta( $post_id, '_mi_event_location', mb_substr( $event_location, 0, 180 ) );

		$pricing_mode = isset( $_POST['mi_pricing_mode'] ) ? sanitize_key( wp_unslash( $_POST['mi_pricing_mode'] ) ) : 'none';
		$pricing_mode = strtoupper( $pricing_mode );
		$pricing_mode = in_array( $pricing_mode, array( 'NONE', 'ZERO', 'CALCULATED' ), true ) ? $pricing_mode : 'NONE';
		update_post_meta( $post_id, '_mi_pricing_mode', $pricing_mode );

		$economic_mode = isset( $_POST['mi_economic_mode'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['mi_economic_mode'] ) ) ) : 'REGISTRATION_ONLY';
		$economic_modes = array( 'REGISTRATION_ONLY', 'PRICE_ONLY', 'FULL_PAYMENT', 'DEPOSIT_BALANCE' );
		$economic_mode = in_array( $economic_mode, $economic_modes, true ) ? $economic_mode : 'REGISTRATION_ONLY';
		update_post_meta( $post_id, '_mi_economic_mode', $economic_mode );
		$deposit_percentage = isset( $_POST['mi_deposit_percentage'] ) ? min( 99, max( 1, absint( $_POST['mi_deposit_percentage'] ) ) ) : 30;
		update_post_meta( $post_id, '_mi_deposit_percentage', $deposit_percentage );
		$raw_payment_methods = isset( $_POST['mi_payment_methods'] ) ? (array) wp_unslash( $_POST['mi_payment_methods'] ) : array();
		$payment_methods = array_values( array_intersect( array( 'BANK_TRANSFER', 'CARD', 'CASH' ), array_map( 'strtoupper', array_map( 'sanitize_key', $raw_payment_methods ) ) ) );
		if ( ! in_array( $economic_mode, array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ) {
			$payment_methods = array();
		}
		update_post_meta( $post_id, '_mi_payment_methods', $payment_methods );

		$identifier = isset( $_POST['mi_identifier_display'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['mi_identifier_display'] ) ) ) : 'TEXT';
		update_post_meta( $post_id, '_mi_identifier_display', in_array( $identifier, array( 'NONE', 'TEXT', 'QR', 'BARCODE' ), true ) ? $identifier : 'TEXT' );

		$field_configuration = MI_Field_Schema::sanitize_configuration(
			isset( $_POST['mi_data_profile'] ) ? wp_unslash( $_POST['mi_data_profile'] ) : 'MINIMAL',
			isset( $_POST['mi_participant_fields'] ) ? (array) wp_unslash( $_POST['mi_participant_fields'] ) : array(),
			isset( $_POST['mi_participant_required'] ) ? (array) wp_unslash( $_POST['mi_participant_required'] ) : array()
		);
		update_post_meta( $post_id, '_mi_data_profile', $field_configuration['profile'] );
		update_post_meta( $post_id, '_mi_participant_fields', $field_configuration['enabled'] );
		update_post_meta( $post_id, '_mi_participant_required_fields', $field_configuration['required'] );
		$participant_extra_scope = isset( $_POST['mi_participant_extra_scope'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['mi_participant_extra_scope'] ) ) ) : 'ONE';
		update_post_meta( $post_id, '_mi_participant_extra_scope', 'ALL' === $participant_extra_scope ? 'ALL' : 'ONE' );

		$codes = isset( $_POST['mi_ticket_code'] ) ? (array) wp_unslash( $_POST['mi_ticket_code'] ) : array();
		$names = isset( $_POST['mi_ticket_name'] ) ? (array) wp_unslash( $_POST['mi_ticket_name'] ) : array();
		$prices = isset( $_POST['mi_ticket_price'] ) ? (array) wp_unslash( $_POST['mi_ticket_price'] ) : array();
		$maximums = isset( $_POST['mi_ticket_max'] ) ? (array) wp_unslash( $_POST['mi_ticket_max'] ) : array();
		$capacities = isset( $_POST['mi_ticket_capacity'] ) ? (array) wp_unslash( $_POST['mi_ticket_capacity'] ) : array();
		$tickets = array();
		$seen = array();
		foreach ( array_slice( $codes, 0, 50, true ) as $index => $raw_code ) {
			$code = sanitize_title( $raw_code );
			$name = isset( $names[ $index ] ) ? sanitize_text_field( $names[ $index ] ) : '';
			if ( ! $code || ! $name || isset( $seen[ $code ] ) ) {
				continue;
			}
			$seen[ $code ] = true;
			$tickets[] = array(
				'code'          => $code,
				'name'          => $name,
				'price_cents'   => max( 0, (int) round( (float) ( $prices[ $index ] ?? 0 ) * 100 ) ),
				'max_per_order' => min( 20, max( 1, absint( $maximums[ $index ] ?? 1 ) ) ),
				'capacity'      => min( 10000, absint( $capacities[ $index ] ?? 0 ) ),
			);
		}
		if ( empty( $tickets ) ) {
			$tickets[] = array( 'code' => 'standard', 'name' => 'Iscrizione', 'price_cents' => 0, 'max_per_order' => 5, 'capacity' => 0 );
		}
		update_post_meta( $post_id, '_mi_ticket_types', $tickets );

		$option_codes = isset( $_POST['mi_option_code'] ) ? (array) wp_unslash( $_POST['mi_option_code'] ) : array();
		$option_names = isset( $_POST['mi_option_name'] ) ? (array) wp_unslash( $_POST['mi_option_name'] ) : array();
		$option_scopes = isset( $_POST['mi_option_scope'] ) ? (array) wp_unslash( $_POST['mi_option_scope'] ) : array();
		$option_prices = isset( $_POST['mi_option_price'] ) ? (array) wp_unslash( $_POST['mi_option_price'] ) : array();
		$option_maximums = isset( $_POST['mi_option_max'] ) ? (array) wp_unslash( $_POST['mi_option_max'] ) : array();
		$options = array();
		$seen_options = array();
		foreach ( array_slice( $option_codes, 0, 50, true ) as $index => $raw_code ) {
			$code = sanitize_title( $raw_code );
			$name = sanitize_text_field( $option_names[ $index ] ?? '' );
			if ( ! $code || ! $name || isset( $seen_options[ $code ] ) ) {
				continue;
			}
			$seen_options[ $code ] = true;
			$scope = strtoupper( sanitize_key( $option_scopes[ $index ] ?? 'ORDER' ) );
			$options[] = array(
				'code'         => $code,
				'name'         => $name,
				'scope'        => in_array( $scope, array( 'ORDER', 'TICKET' ), true ) ? $scope : 'ORDER',
				'price_cents'  => max( 0, (int) round( (float) ( $option_prices[ $index ] ?? 0 ) * 100 ) ),
				'max_quantity' => min( 20, max( 1, absint( $option_maximums[ $index ] ?? 1 ) ) ),
			);
		}
		update_post_meta( $post_id, '_mi_options', $options );
	}

	public static function publish_revision( $post_id, $post ) {
		if ( 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		MI_Registration_Service::ensure_published_revision( $post_id, true );
	}

	public static function event_columns( $columns ) {
		$columns['mi_activity'] = 'Attività';
		$columns['mi_window'] = 'Finestra iscrizioni';
		$columns['mi_capacity'] = 'Capienza';
		return $columns;
	}

	public static function render_event_column( $column, $post_id ) {
		if ( 'mi_activity' === $column ) {
			$activity_id = absint( get_post_meta( $post_id, '_mi_activity_id', true ) );
			$activity = $activity_id ? get_post( $activity_id ) : null;
			echo $activity ? esc_html( $activity->post_title ) : '—';
			if ( get_post_meta( $post_id, '_mi_needs_republish', true ) ) {
				echo '<br><strong style="color:#b32d2e">Ripubblicazione richiesta</strong>';
			}
		} elseif ( 'mi_window' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_mi_registration_opens_at', true ) . ' → ' . get_post_meta( $post_id, '_mi_registration_closes_at', true ) );
		} elseif ( 'mi_capacity' === $column ) {
			$published_event = MI_Registration_Service::public_event( $post_id );
			$draft_capacity = absint( get_post_meta( $post_id, '_mi_capacity', true ) );
			$capacity_event = is_wp_error( $published_event ) ? array( 'id' => $post_id, 'capacity' => $draft_capacity ) : $published_event;
			$availability = MI_Registration_Service::availability( $capacity_event );
			echo esc_html( $availability['confirmed'] . '/' . $availability['capacity'] . ' posti occupati' );
			if ( ! is_wp_error( $published_event ) && $draft_capacity !== (int) $availability['capacity'] ) echo '<br><small style="color:#b32d2e">Bozza: ' . esc_html( $draft_capacity ) . ' · ripubblicare</small>';
			if ( $availability['waitlisted'] ) {
				echo '<br><small>' . esc_html( $availability['waitlisted'] . ' in attesa' ) . '</small>';
			}
		}
	}

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::EVENT_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'mi-admin', MI_PLUGIN_URL . 'assets/admin.css', array(), MI_VERSION );
		wp_enqueue_script( 'mi-admin', MI_PLUGIN_URL . 'assets/admin.js', array(), MI_VERSION, true );
	}
}
