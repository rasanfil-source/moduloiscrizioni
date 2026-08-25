<?php

defined( 'ABSPATH' ) || exit;

final class MI_Modello_Email {
	public static function avvia() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'aggiungi_riquadro' ) );
		add_action( 'save_post_' . MI_Event_Post_Type::EVENT_TYPE, array( __CLASS__, 'salva' ), 20, 2 );
	}

	public static function aggiungi_riquadro() {
		add_meta_box( 'mi_modello_email', 'Email di conferma — anteprima', array( __CLASS__, 'mostra_riquadro' ), MI_Event_Post_Type::EVENT_TYPE, 'normal', 'default' );
	}

	public static function impostazioni( $event_id ) {
		$defaults = array(
			'enabled'   => '1',
			'subject'   => 'Iscrizione a {{evento.titolo}} — {{ordine.codice}}',
			'preheader' => 'Riepilogo della tua iscrizione.',
			'html'      => '<p>Gentile {{referente.nome_completo}},</p><p>la tua iscrizione a <strong>{{evento.titolo}}</strong> è stata registrata.</p><p>Codice: <strong>{{ordine.codice}}</strong><br>Stato: {{ordine.stato}}<br>Partecipanti: {{ordine.partecipanti}}</p>',
			'text'      => "Gentile {{referente.nome_completo}},\n\nla tua iscrizione a {{evento.titolo}} è stata registrata.\nCodice: {{ordine.codice}}\nStato: {{ordine.stato}}\nPartecipanti: {{ordine.partecipanti}}",
			'footer'    => 'Messaggio automatico in anteprima. Nessuna email viene spedita in questa fase.',
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
		<p><label><input type="checkbox" name="mi_email_enabled" value="1" <?php checked( '1', $settings['enabled'] ); ?>> Modello attivo per la futura email di conferma</label></p>
		<p><label for="mi_email_subject"><strong>Oggetto</strong></label><br><input class="widefat" id="mi_email_subject" name="mi_email_subject" maxlength="180" value="<?php echo esc_attr( $settings['subject'] ); ?>"></p>
		<p><label for="mi_email_preheader"><strong>Preheader</strong></label><br><input class="widefat" id="mi_email_preheader" name="mi_email_preheader" maxlength="240" value="<?php echo esc_attr( $settings['preheader'] ); ?>"></p>
		<p><label for="mi_email_html"><strong>Corpo HTML</strong></label><br><textarea class="widefat" id="mi_email_html" name="mi_email_html" rows="8"><?php echo esc_textarea( $settings['html'] ); ?></textarea></p>
		<p><label for="mi_email_text"><strong>Testo semplice</strong></label><br><textarea class="widefat" id="mi_email_text" name="mi_email_text" rows="7"><?php echo esc_textarea( $settings['text'] ); ?></textarea></p>
		<p><label for="mi_email_footer"><strong>Footer</strong></label><br><textarea class="widefat" id="mi_email_footer" name="mi_email_footer" rows="3"><?php echo esc_textarea( $settings['footer'] ); ?></textarea></p>
		<p class="description"><strong>Segnaposto ammessi:</strong> <code>{{evento.titolo}}</code>, <code>{{attivita.nome}}</code>, <code>{{ordine.codice}}</code>, <code>{{ordine.stato}}</code>, <code>{{ordine.partecipanti}}</code>, <code>{{referente.nome_completo}}</code>.</p>
		<div class="mi-field-preview"><strong>Anteprima con dati sintetici</strong><p><strong><?php echo esc_html( self::renderizza( $settings['subject'], $example ) ); ?></strong></p><p><?php echo esc_html( self::renderizza( $settings['preheader'], $example ) ); ?></p><div><?php echo wp_kses_post( self::renderizza( $settings['html'], $example ) ); ?></div><hr><p><?php echo nl2br( esc_html( self::renderizza( $settings['footer'], $example ) ) ); ?></p></div>
		<p class="description">Questa schermata non invia email e usa esclusivamente dati di esempio.</p>
		<?php
	}

	public static function salva( $post_id, $post ) {
		if ( ! isset( $_POST['mi_modello_email_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mi_modello_email_nonce'] ) ), 'mi_salva_modello_email' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'mi_manage_events' ) || ! MI_Access::can_access_event( $post_id ) ) {
			return;
		}
		$settings = array(
			'enabled'   => isset( $_POST['mi_email_enabled'] ) ? '1' : '0',
			'subject'   => self::pulisci_riga( $_POST['mi_email_subject'] ?? '', 180 ),
			'preheader' => self::pulisci_riga( $_POST['mi_email_preheader'] ?? '', 240 ),
			'html'      => wp_kses_post( wp_unslash( $_POST['mi_email_html'] ?? '' ) ),
			'text'      => sanitize_textarea_field( wp_unslash( $_POST['mi_email_text'] ?? '' ) ),
			'footer'    => sanitize_textarea_field( wp_unslash( $_POST['mi_email_footer'] ?? '' ) ),
		);
		update_post_meta( $post_id, '_mi_email_template', $settings );
	}

	private static function pulisci_riga( $value, $maximum ) {
		$value = sanitize_text_field( wp_unslash( $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $maximum ) : substr( $value, 0, $maximum );
	}

	private static function renderizza( $template, $values ) {
		return strtr( (string) $template, $values );
	}
}
