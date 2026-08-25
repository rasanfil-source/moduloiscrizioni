<?php

defined( 'ABSPATH' ) || exit;

final class MI_Spedizione_Email {
	const OPZIONE_MODALITA = 'mi_modalita_spedizione_email';
	const OPZIONE_DESTINATARIO_PROVA = 'mi_destinatario_prova_email';
	const OPZIONE_PROVA_VERIFICATA = 'mi_prova_email_verificata';
	private static $nome_mittente = '';

	public static function avvia() {
		add_action( 'admin_menu', array( __CLASS__, 'aggiungi_pagina' ) );
		add_action( 'admin_post_mi_salva_spedizione_email', array( __CLASS__, 'salva_impostazioni' ) );
		add_action( 'admin_post_mi_invia_email_prova', array( __CLASS__, 'invia_prova' ) );
		add_action( 'mi_spedisci_email_in_coda', array( __CLASS__, 'spedisci_coda' ) );
	}

	public static function modalita() {
		$modalita = strtoupper( (string) get_option( self::OPZIONE_MODALITA, 'ANTEPRIMA' ) );
		return in_array( $modalita, array( 'ANTEPRIMA', 'PROVA', 'OPERATIVO' ), true ) ? $modalita : 'ANTEPRIMA';
	}

	public static function stato_nuova_email( $istantanea ) {
		return ! empty( $istantanea['attivo'] ) && 'OPERATIVO' === self::modalita() && self::prova_verificata() ? 'PENDING' : 'PREVIEW';
	}

	public static function pianifica_spedizione() {
		if ( 'OPERATIVO' === self::modalita() && ! wp_next_scheduled( 'mi_spedisci_email_in_coda' ) ) {
			wp_schedule_single_event( time() + 30, 'mi_spedisci_email_in_coda' );
		}
	}

	public static function aggiungi_pagina() {
		add_submenu_page( 'edit.php?post_type=' . MI_Event_Post_Type::EVENT_TYPE, 'Spedizione email', 'Spedizione email', 'manage_options', 'mi-spedizione-email', array( __CLASS__, 'mostra_pagina' ) );
	}

	public static function mostra_pagina() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accesso non consentito.', 'modulo-iscrizioni' ) );
		}
		$modalita = self::modalita();
		$destinatario = (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' );
		$verificata = self::prova_verificata();
		$esito = isset( $_GET['mi_esito'] ) ? sanitize_key( wp_unslash( $_GET['mi_esito'] ) ) : '';
		$messaggi = array(
			'salvato' => array( 'success', 'Impostazioni salvate.' ),
			'prova_ok' => array( 'success', 'Email sintetica di prova inviata. La modalità operativa può ora essere selezionata.' ),
			'prova_ko' => array( 'error', 'Invio di prova non riuscito. Controlla la configurazione di posta del sito.' ),
			'indirizzo' => array( 'error', 'Inserisci un indirizzo di prova valido.' ),
			'prova_richiesta' => array( 'error', 'La modalità operativa richiede prima un invio di prova riuscito verso l’indirizzo attuale.' ),
		);
		if ( isset( $messaggi[ $esito ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $messaggi[ $esito ][0] ) . '"><p>' . esc_html( $messaggi[ $esito ][1] ) . '</p></div>';
		}
		?>
		<div class="wrap"><h1>Spedizione email</h1>
		<p>La configurazione iniziale è <strong>Anteprima</strong>: nessun messaggio parte. <strong>Prova</strong> abilita esclusivamente un invio sintetico all’indirizzo indicato. <strong>Operativo</strong> mette in coda le nuove conferme reali.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="mi_salva_spedizione_email"><?php wp_nonce_field( 'mi_salva_spedizione_email' ); ?>
		<table class="form-table"><tr><th scope="row"><label for="mi_modalita_spedizione_email">Modalità</label></th><td><select id="mi_modalita_spedizione_email" name="mi_modalita_spedizione_email">
		<option value="ANTEPRIMA" <?php selected( $modalita, 'ANTEPRIMA' ); ?>>Anteprima — nessun invio</option><option value="PROVA" <?php selected( $modalita, 'PROVA' ); ?>>Prova — solo messaggio sintetico</option><option value="OPERATIVO" <?php selected( $modalita, 'OPERATIVO' ); ?> <?php disabled( ! $verificata ); ?>>Operativo — conferme reali</option></select>
		<?php if ( ! $verificata ) : ?><p class="description">Operativo resterà bloccato finché la prova non riuscirà.</p><?php endif; ?></td></tr>
		<tr><th scope="row"><label for="mi_destinatario_prova_email">Destinatario della prova</label></th><td><input class="regular-text" type="email" id="mi_destinatario_prova_email" name="mi_destinatario_prova_email" value="<?php echo esc_attr( $destinatario ); ?>" autocomplete="off"><p class="description">Non viene usato come destinatario delle conferme operative.</p></td></tr></table><?php submit_button( 'Salva impostazioni' ); ?></form>
		<hr><h2>Collaudo controllato</h2><p>Il messaggio contiene soltanto nomi, codici e dati dimostrativi.</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Inviare ora una email sintetica all’indirizzo di prova?');"><input type="hidden" name="action" value="mi_invia_email_prova"><?php wp_nonce_field( 'mi_invia_email_prova' ); ?><?php submit_button( 'Invia email sintetica di prova', 'secondary' ); ?></form></div>
		<?php
	}

	public static function salva_impostazioni() {
		self::verifica_amministratore( 'mi_salva_spedizione_email' );
		$destinatario_grezzo = trim( (string) wp_unslash( $_POST['mi_destinatario_prova_email'] ?? '' ) );
		$destinatario = sanitize_email( $destinatario_grezzo );
		if ( $destinatario_grezzo && ( ! $destinatario || ! is_email( $destinatario ) ) ) {
			self::torna_alla_pagina( 'indirizzo' );
		}
		if ( $destinatario !== (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' ) ) {
			delete_option( self::OPZIONE_PROVA_VERIFICATA );
		}
		update_option( self::OPZIONE_DESTINATARIO_PROVA, $destinatario, false );
		$modalita = strtoupper( sanitize_key( wp_unslash( $_POST['mi_modalita_spedizione_email'] ?? 'ANTEPRIMA' ) ) );
		$modalita = in_array( $modalita, array( 'ANTEPRIMA', 'PROVA', 'OPERATIVO' ), true ) ? $modalita : 'ANTEPRIMA';
		if ( 'OPERATIVO' === $modalita && ! self::prova_verificata( $destinatario ) ) {
			update_option( self::OPZIONE_MODALITA, 'PROVA', false );
			self::torna_alla_pagina( 'prova_richiesta' );
		}
		update_option( self::OPZIONE_MODALITA, $modalita, false );
		self::pianifica_spedizione();
		self::torna_alla_pagina( 'salvato' );
	}

	public static function invia_prova() {
		self::verifica_amministratore( 'mi_invia_email_prova' );
		$destinatario = (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' );
		if ( ! is_email( $destinatario ) ) {
			self::torna_alla_pagina( 'indirizzo' );
		}
		$oggetto = 'Prova Modulo Iscrizioni — ' . wp_date( 'd/m/Y H:i' );
		$corpo = '<p>Questa è una <strong>email sintetica di prova</strong> del Modulo Iscrizioni.</p><p>Evento: Evento dimostrativo<br>Codice: MI-PROVA-0001<br>Referente: Persona Esempio</p><p>Nessun dato di un’iscrizione reale è stato utilizzato.</p>';
		if ( wp_mail( $destinatario, $oggetto, $corpo, array( 'Content-Type: text/html; charset=UTF-8' ) ) ) {
			update_option( self::OPZIONE_PROVA_VERIFICATA, self::impronta_destinatario( $destinatario ), false );
			self::torna_alla_pagina( 'prova_ok' );
		}
		self::torna_alla_pagina( 'prova_ko' );
	}

	public static function spedisci_coda() {
		if ( 'OPERATIVO' !== self::modalita() || ! self::prova_verificata() ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'mi_email_outbox';
		$righe = $wpdb->get_results( "SELECT id, recipient, payload_json, attempts FROM {$table} WHERE status = 'PENDING' AND attempts < 5 ORDER BY id ASC LIMIT 10", ARRAY_A );
		foreach ( $righe as $riga ) {
			$id = absint( $riga['id'] );
			if ( 1 !== $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'SENDING', attempts = attempts + 1 WHERE id = %d AND status = 'PENDING'", $id ) ) ) {
				continue;
			}
			$payload = json_decode( (string) $riga['payload_json'], true );
			$istantanea = is_array( $payload ) && isset( $payload['email_preview'] ) && is_array( $payload['email_preview'] ) ? $payload['email_preview'] : array();
			if ( self::invia_istantanea( $riga['recipient'], $istantanea ) ) {
				$wpdb->update( $table, array( 'status' => 'SENT', 'last_error' => null, 'sent_at' => current_time( 'mysql', true ) ), array( 'id' => $id ), array( '%s', '%s', '%s' ), array( '%d' ) );
			} else {
				$tentativi = (int) $riga['attempts'] + 1;
				$wpdb->update( $table, array( 'status' => $tentativi >= 5 ? 'FAILED' : 'PENDING', 'last_error' => 'wp_mail non ha accettato il messaggio.' ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
			}
		}
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'PENDING' AND attempts < 5" ) > 0 ) {
			self::pianifica_spedizione();
		}
	}

	private static function invia_istantanea( $destinatario, $istantanea ) {
		if ( ! is_email( $destinatario ) || empty( $istantanea['attivo'] ) || empty( $istantanea['oggetto'] ) ) {
			return false;
		}
		$identita = isset( $istantanea['identita_email'] ) && is_array( $istantanea['identita_email'] ) ? $istantanea['identita_email'] : array();
		$intestazioni = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! empty( $identita['indirizzo_risposte'] ) && is_email( $identita['indirizzo_risposte'] ) ) {
			$intestazioni[] = 'Reply-To: ' . sanitize_email( $identita['indirizzo_risposte'] );
		}
		$preheader = ! empty( $istantanea['preheader'] ) ? '<div style="display:none;max-height:0;overflow:hidden">' . esc_html( $istantanea['preheader'] ) . '</div>' : '';
		$corpo = $preheader . wp_kses_post( $istantanea['html'] ?? '' ) . '<hr><p>' . nl2br( esc_html( $istantanea['footer'] ?? '' ) ) . '</p>';
		self::$nome_mittente = sanitize_text_field( $identita['nome_mittente'] ?? '' );
		if ( self::$nome_mittente ) {
			add_filter( 'wp_mail_from_name', array( __CLASS__, 'filtra_nome_mittente' ) );
		}
		$inviata = wp_mail( sanitize_email( $destinatario ), sanitize_text_field( $istantanea['oggetto'] ), $corpo, $intestazioni );
		remove_filter( 'wp_mail_from_name', array( __CLASS__, 'filtra_nome_mittente' ) );
		self::$nome_mittente = '';
		return $inviata;
	}

	public static function filtra_nome_mittente( $nome_corrente ) {
		return self::$nome_mittente ?: $nome_corrente;
	}

	private static function prova_verificata( $destinatario = null ) {
		$destinatario = null === $destinatario ? (string) get_option( self::OPZIONE_DESTINATARIO_PROVA, '' ) : (string) $destinatario;
		return is_email( $destinatario ) && hash_equals( self::impronta_destinatario( $destinatario ), (string) get_option( self::OPZIONE_PROVA_VERIFICATA, '' ) );
	}

	private static function impronta_destinatario( $destinatario ) {
		return hash_hmac( 'sha256', strtolower( trim( $destinatario ) ), wp_salt( 'auth' ) );
	}

	private static function verifica_amministratore( $azione ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Operazione non consentita.', 'modulo-iscrizioni' ) );
		}
		check_admin_referer( $azione );
	}

	private static function torna_alla_pagina( $esito ) {
		wp_safe_redirect( add_query_arg( array( 'post_type' => MI_Event_Post_Type::EVENT_TYPE, 'page' => 'mi-spedizione-email', 'mi_esito' => $esito ), admin_url( 'edit.php' ) ) );
		exit;
	}
}
