<?php

defined( 'ABSPATH' ) || exit;

final class MI_Workspace_Settings {
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mi_save_workspace', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_mi_test_workspace', array( __CLASS__, 'test_connection' ) );
		add_action( 'admin_post_mi_test_workspace_schema', array( __CLASS__, 'test_schema' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE,
			'Collegamento Workspace',
			'Collegamento Workspace',
			'manage_options',
			'mi-workspace',
			array( __CLASS__, 'page' )
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		$url = (string) get_option( 'mi_workspace_webapp_url', '' );
		$configured = MI_Workspace_Client::is_configured();
		$notice = isset( $_GET['mi_esito'] ) ? sanitize_key( wp_unslash( $_GET['mi_esito'] ) ) : '';
		$error_code = isset( $_GET['mi_codice'] ) ? sanitize_key( wp_unslash( $_GET['mi_codice'] ) ) : '';
		?>
		<div class="wrap">
			<h1>Collegamento Google Workspace</h1>
			<?php if ( 'salvato' === $notice ) : ?><div class="notice notice-success"><p>Configurazione salvata.</p></div><?php endif; ?>
			<?php if ( 'ping_ok' === $notice ) : ?><div class="notice notice-success"><p>Collegamento firmato verificato. Workspace è in modalità ANTEPRIMA.</p></div><?php endif; ?>
			<?php if ( 'ping_errore' === $notice ) : ?><div class="notice notice-error"><p>Collegamento non riuscito. Codice diagnostico: <code><?php echo esc_html( $error_code ?: 'non_disponibile' ); ?></code>.</p></div><?php endif; ?>
			<?php if ( 'schema_ok' === $notice ) : ?><div class="notice notice-success"><p>Schema Workspace 1.8.0 verificato: gruppi, eventi, report, prenotazioni, sistemazioni e colonne economiche sono disponibili.</p></div><?php endif; ?>
			<?php if ( 'schema_errore' === $notice ) : ?><div class="notice notice-error"><p>Schema Workspace non allineato. Aggiorna il deployment e la struttura del foglio.</p></div><?php endif; ?>
			<p>Il segreto salvato non viene mai mostrato. Inseriscilo nuovamente soltanto per sostituirlo.</p>
			<p><strong>URL per la procedura guidata Sheets:</strong><br><code><?php echo esc_html( rest_url( MI_REST_Controller::NAMESPACE . '/workspace/commands' ) ); ?></code></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mi_save_workspace">
				<?php wp_nonce_field( 'mi_save_workspace' ); ?>
				<table class="form-table"><tbody>
				<tr><th scope="row"><label for="mi_workspace_webapp_url">URL Web App</label></th><td><input class="regular-text code" type="url" id="mi_workspace_webapp_url" name="mi_workspace_webapp_url" value="<?php echo esc_attr( $url ); ?>" required autocomplete="off"></td></tr>
				<tr><th scope="row"><label for="mi_workspace_shared_secret">Nuovo segreto condiviso</label></th><td><input class="regular-text" type="password" id="mi_workspace_shared_secret" name="mi_workspace_shared_secret" value="" minlength="32" autocomplete="new-password"><p class="description"><?php echo $configured ? 'Un segreto è già configurato.' : 'Configura un segreto casuale di almeno 32 caratteri.'; ?></p></td></tr>
				</tbody></table>
				<?php submit_button( 'Salva configurazione' ); ?>
			</form>
			<?php if ( $configured ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mi_test_workspace">
				<?php wp_nonce_field( 'mi_test_workspace' ); ?>
				<?php submit_button( 'Verifica collegamento firmato', 'secondary' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mi_test_workspace_schema">
				<?php wp_nonce_field( 'mi_test_workspace_schema' ); ?>
				<?php submit_button( 'Verifica schema Workspace', 'secondary' ); ?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function save() {
		self::authorize( 'mi_save_workspace' );
		$url = esc_url_raw( wp_unslash( $_POST['mi_workspace_webapp_url'] ?? '' ) );
		if ( ! preg_match( '#^https://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec$#', $url ) ) {
			wp_die( esc_html__( 'URL Web App non valido.', 'modulo-iscrizioni' ) );
		}
		update_option( 'mi_workspace_webapp_url', $url, false );
		$secret = (string) wp_unslash( $_POST['mi_workspace_shared_secret'] ?? '' );
		if ( '' !== $secret ) {
			if ( strlen( $secret ) < 32 || strlen( $secret ) > 200 ) {
				wp_die( esc_html__( 'Il segreto deve contenere da 32 a 200 caratteri.', 'modulo-iscrizioni' ) );
			}
			update_option( 'mi_workspace_shared_secret', $secret, false );
		}
		wp_safe_redirect( self::page_url( 'salvato' ) );
		exit;
	}

	public static function test_connection() {
		self::authorize( 'mi_test_workspace' );
		$result = MI_Workspace_Client::ping();
		$url = self::page_url( is_wp_error( $result ) ? 'ping_errore' : 'ping_ok' );
		if ( is_wp_error( $result ) ) $url = add_query_arg( 'mi_codice', sanitize_key( $result->get_error_code() ), $url );
		wp_safe_redirect( $url );
		exit;
	}

	public static function test_schema() {
		self::authorize( 'mi_test_workspace_schema' );
		$result = MI_Workspace_Client::stato_schema();
		$required = array( 'modalita_economica', 'primo_versamento_centesimi', 'saldo_centesimi', 'fonti_pagamento_json', 'id_revisione_evento', 'snapshot_json', 'id_consenso_privacy', 'opzioni_ordine_json', 'id_consenso_marketing' );
		$headers = is_wp_error( $result ) ? array() : (array) ( $result['registration_headers'] ?? array() );
		$accommodation_headers = is_wp_error( $result ) ? array() : (array) ( $result['accommodation_headers'] ?? array() );
		$group_headers = is_wp_error( $result ) ? array() : (array) ( $result['group_headers'] ?? array() );
		$report_headers = is_wp_error( $result ) ? array() : (array) ( $result['report_template_headers'] ?? array() );
		$event_headers = is_wp_error( $result ) ? array() : (array) ( $result['event_headers'] ?? array() );
		$valid = ! is_wp_error( $result ) && '1.8.0' === ( $result['schema_version'] ?? '' ) && ! array_diff( $required, $headers ) && ! array_diff( array( 'id_evento', 'codice', 'nome', 'capienza', 'attiva' ), $accommodation_headers ) && ! array_diff( array( 'id_gruppo', 'nome', 'slug', 'stato', 'logo_url', 'immagine_url' ), $group_headers ) && ! array_diff( array( 'id_modello', 'nome', 'tipo', 'colonne_json', 'filtri_json' ), $report_headers ) && ! array_diff( array( 'id_evento', 'id_gruppo', 'titolo' ), $event_headers );
		wp_safe_redirect( self::page_url( $valid ? 'schema_ok' : 'schema_errore' ) );
		exit;
	}

	private static function authorize( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		check_admin_referer( $action );
	}

	private static function page_url( $result ) {
		return add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-workspace', 'mi_esito' => $result ), admin_url( 'edit.php' ) );
	}
}
