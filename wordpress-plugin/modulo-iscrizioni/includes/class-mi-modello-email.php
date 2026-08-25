<?php

defined( 'ABSPATH' ) || exit;

final class MI_Modello_Email {
	public static function avvia() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'aggiungi_riquadro' ) );
		add_action( 'save_post_' . MI_Event_Post_Type::EVENT_TYPE, array( __CLASS__, 'salva' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'mostra_avviso' ) );
	}

	public static function segnaposto_ammessi() {
		return array( '{{evento.titolo}}', '{{attivita.nome}}', '{{ordine.codice}}', '{{ordine.stato}}', '{{ordine.partecipanti}}', '{{referente.nome_completo}}' );
	}

	public static function aggiungi_riquadro() {
		add_meta_box( 'mi_modello_email', 'Email di conferma — anteprima', array( __CLASS__, 'mostra_riquadro' ), MI_Event_Post_Type::EVENT_TYPE, 'normal', 'default' );
	}

	public static function impostazioni( $event_id ) {
		$defaults = array(
			'enabled'   => '1',
			'sender_name' => '',
			'reply_to'    => '',
			'internal_recipients' => array(),
			'subject'   => 'Iscrizione a {{evento.titolo}} — {{ordine.codice}}',
			'preheader' => 'Riepilogo della tua iscrizione.',
			'html'      => '<p>Gentile {{referente.nome_completo}},</p><p>la tua iscrizione a <strong>{{evento.titolo}}</strong> è stata registrata.</p><p>Codice: <strong>{{ordine.codice}}</strong><br>Stato: {{ordine.stato}}<br>Partecipanti: {{ordine.partecipanti}}</p>',
			'text'      => "Gentile {{referente.nome_completo}},\n\nla tua iscrizione a {{evento.titolo}} è stata registrata.\nCodice: {{ordine.codice}}\nStato: {{ordine.stato}}\nPartecipanti: {{ordine.partecipanti}}",
			'footer'    => 'Messaggio automatico del sistema di iscrizione.',
		);
		$saved = get_post_meta( $event_id, '_mi_email_template', true );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	public static function mostra_riquadro( $post ) {
		$settings = self::impostazioni( $post->ID );
		wp_nonce_field( 'mi_salva_modello_email', 'mi_modello_email_nonce' );
		$example = array(
			'{{evento.titolo}}'           => $post->post_title ?: 'Evento dimostrativo',
			'{{attivita.nome}}'           => 'Attività dimostrativa',
			'{{ordine.codice}}'           => 'MI-ESEMPIO-0001',
			'{{ordine.stato}}'            => 'Confermata',
			'{{ordine.partecipanti}}'     => '2',
			'{{referente.nome_completo}}' => 'Persona Esempio',
		);
		?>
		<p><label><input type="checkbox" name="mi_email_enabled" value="1" <?php checked( '1', $settings['enabled'] ); ?>> Modello attivo per l’email di conferma</label></p>
		<div class="mi-admin-grid">
		<p><label for="mi_email_sender_name"><strong>Nome visualizzato del mittente</strong></label><br><input class="widefat" id="mi_email_sender_name" name="mi_email_sender_name" maxlength="120" value="<?php echo esc_attr( $settings['sender_name'] ); ?>" placeholder="Lascia vuoto per usare il valore organizzativo"></p>
		<p><label for="mi_email_reply_to"><strong>Indirizzo per le risposte</strong></label><br><input class="widefat" id="mi_email_reply_to" name="mi_email_reply_to" type="email" value="<?php echo esc_attr( $settings['reply_to'] ); ?>" placeholder="Lascia vuoto per usare il valore organizzativo"></p>
		</div>
		<p><label for="mi_email_internal_recipients"><strong>Destinatari interni in anteprima</strong></label><br><textarea class="widefat" id="mi_email_internal_recipients" name="mi_email_internal_recipients" rows="2" placeholder="Un indirizzo per riga, massimo 10"><?php echo esc_textarea( implode( "\n", (array) $settings['internal_recipients'] ) ); ?></textarea></p>
		<p class="description">Questi indirizzi vengono soltanto validati e conservati nella configurazione riservata. Nessun messaggio viene inviato.</p>
		<p><label for="mi_email_subject"><strong>Oggetto</strong></label><br><input class="widefat" id="mi_email_subject" name="mi_email_subject" maxlength="180" value="<?php echo esc_attr( $settings['subject'] ); ?>"></p>
		<p><label for="mi_email_preheader"><strong>Preheader</strong></label><br><input class="widefat" id="mi_email_preheader" name="mi_email_preheader" maxlength="240" value="<?php echo esc_attr( $settings['preheader'] ); ?>"></p>
		<p><label for="mi_email_html"><strong>Corpo HTML</strong></label><br><textarea class="widefat" id="mi_email_html" name="mi_email_html" rows="8"><?php echo esc_textarea( $settings['html'] ); ?></textarea></p>
		<p><label for="mi_email_text"><strong>Testo semplice</strong></label><br><textarea class="widefat" id="mi_email_text" name="mi_email_text" rows="7"><?php echo esc_textarea( $settings['text'] ); ?></textarea></p>
		<p><label for="mi_email_footer"><strong>Footer</strong></label><br><textarea class="widefat" id="mi_email_footer" name="mi_email_footer" rows="3"><?php echo esc_textarea( $settings['footer'] ); ?></textarea></p>
		<p class="description"><strong>Segnaposto ammessi:</strong> <code>{{evento.titolo}}</code>, <code>{{attivita.nome}}</code>, <code>{{ordine.codice}}</code>, <code>{{ordine.stato}}</code>, <code>{{ordine.partecipanti}}</code>, <code>{{referente.nome_completo}}</code>.</p>
		<div class="mi-field-preview" data-mi-email-preview data-mi-email-values="<?php echo esc_attr( wp_json_encode( $example ) ); ?>"><strong>Anteprima con dati sintetici</strong><p><strong data-mi-email-preview-subject><?php echo esc_html( self::renderizza( $settings['subject'], $example ) ); ?></strong></p><p data-mi-email-preview-preheader><?php echo esc_html( self::renderizza( $settings['preheader'], $example ) ); ?></p><div><?php echo wp_kses_post( self::renderizza_html( $settings['html'], $example ) ); ?></div><h4>Testo semplice</h4><pre data-mi-email-preview-text style="white-space:pre-wrap"><?php echo esc_html( self::renderizza( $settings['text'], $example ) ); ?></pre><hr><p data-mi-email-preview-footer><?php echo nl2br( esc_html( self::renderizza( $settings['footer'], $example ) ) ); ?></p><p class="notice-inline notice-error" data-mi-email-placeholder-error hidden></p></div>
		<p class="description">Questa schermata non invia email e usa esclusivamente dati di esempio.</p>
		<?php
	}

	public static function crea_istantanea( $event_id, $values ) {
		$settings = self::impostazioni( $event_id );
		$snapshot = array( 'attivo' => '1' === $settings['enabled'] );
		foreach ( array( 'subject' => 'oggetto', 'preheader' => 'preheader', 'html' => 'html', 'text' => 'testo', 'footer' => 'footer' ) as $source => $destination ) {
			$snapshot[ $destination ] = 'html' === $source ? self::renderizza_html( $settings[ $source ], $values ) : self::renderizza( $settings[ $source ], $values );
		}
		$activity_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		$thumbnail_id = $activity_id ? get_post_thumbnail_id( $activity_id ) : 0;
		$snapshot['identita'] = array(
			'nome_attivita' => $activity_id ? get_the_title( $activity_id ) : '',
			'logo_url'      => $thumbnail_id ? (string) wp_get_attachment_image_url( $thumbnail_id, 'medium' ) : '',
			'logo_alt'      => $thumbnail_id ? (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '',
		);
		$snapshot['identita_email'] = array(
			'nome_mittente'        => $settings['sender_name'],
			'indirizzo_risposte'   => $settings['reply_to'],
			'destinatari_interni'  => array_values( (array) $settings['internal_recipients'] ),
		);
		$snapshot['revisione'] = hash( 'sha256', wp_json_encode( $settings ) );
		return $snapshot;
	}

	public static function salva( $post_id, $post ) {
		if ( ! isset( $_POST['mi_modello_email_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_modello_email_nonce'] ) ), 'mi_salva_modello_email' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'mi_manage_events' ) || ! MI_Access::can_access_event( $post_id ) ) {
			return;
		}
		$raw_reply_to = sanitize_text_field( wp_unslash( $_POST['mi_email_reply_to'] ?? '' ) );
		$raw_recipients = sanitize_textarea_field( wp_unslash( $_POST['mi_email_internal_recipients'] ?? '' ) );
		$recipient_candidates = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,;]+/', $raw_recipients ) ) ) );
		$recipients = array_values( array_filter( array_map( 'sanitize_email', $recipient_candidates ), 'is_email' ) );
		if ( ( $raw_reply_to && ! is_email( $raw_reply_to ) ) || count( $recipient_candidates ) !== count( $recipients ) || count( $recipients ) > 10 ) {
			set_transient( 'mi_email_identity_error_' . get_current_user_id(), '1', MINUTE_IN_SECONDS );
			return;
		}
		$settings = array(
			'enabled'   => isset( $_POST['mi_email_enabled'] ) ? '1' : '0',
			'sender_name' => self::pulisci_riga( $_POST['mi_email_sender_name'] ?? '', 120 ),
			'reply_to'    => $raw_reply_to ? sanitize_email( $raw_reply_to ) : '',
			'internal_recipients' => $recipients,
			'subject'   => self::pulisci_riga( $_POST['mi_email_subject'] ?? '', 180 ),
			'preheader' => self::pulisci_riga( $_POST['mi_email_preheader'] ?? '', 240 ),
			'html'      => wp_kses_post( wp_unslash( $_POST['mi_email_html'] ?? '' ) ),
			'text'      => sanitize_textarea_field( wp_unslash( $_POST['mi_email_text'] ?? '' ) ),
			'footer'    => sanitize_textarea_field( wp_unslash( $_POST['mi_email_footer'] ?? '' ) ),
		);
		$unknown = self::trova_segnaposto_non_ammessi( $settings );
		if ( $unknown ) {
			set_transient( 'mi_email_placeholder_error_' . get_current_user_id(), implode( ', ', $unknown ), MINUTE_IN_SECONDS );
			return;
		}
		update_post_meta( $post_id, '_mi_email_template', $settings );
	}

	public static function mostra_avviso() {
		$identity_key = 'mi_email_identity_error_' . get_current_user_id();
		if ( get_transient( $identity_key ) ) {
			delete_transient( $identity_key );
			echo '<div class="notice notice-error"><p>Identità email non aggiornata: controlla l’indirizzo per le risposte e i destinatari interni, fino a un massimo di 10 indirizzi validi.</p></div>';
		}
		$key = 'mi_email_placeholder_error_' . get_current_user_id();
		$unknown = get_transient( $key );
		if ( ! $unknown ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-error"><p>Modello email non aggiornato: segnaposto non ammessi: <code>' . esc_html( $unknown ) . '</code>.</p></div>';
	}

	private static function pulisci_riga( $value, $maximum ) {
		$value = sanitize_text_field( wp_unslash( $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $maximum ) : substr( $value, 0, $maximum );
	}

	private static function trova_segnaposto_non_ammessi( $settings ) {
		$found = array();
		foreach ( $settings as $value ) {
			if ( is_string( $value ) && preg_match_all( '/{{[^{}]+}}/', $value, $matches ) ) {
				$found = array_merge( $found, $matches[0] );
			}
		}
		return array_values( array_diff( array_unique( $found ), self::segnaposto_ammessi() ) );
	}

	private static function renderizza( $template, $values ) {
		$clean_values = array_map(
			static function ( $value ) {
				return sanitize_text_field( (string) $value );
			},
			$values
		);
		return strtr( (string) $template, $clean_values );
	}

	private static function renderizza_html( $template, $values ) {
		$escaped_values = array_map(
			static function ( $value ) {
				return esc_html( sanitize_text_field( (string) $value ) );
			},
			$values
		);
		return strtr( (string) $template, $escaped_values );
	}
}
