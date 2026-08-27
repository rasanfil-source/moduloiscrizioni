<?php

defined( 'ABSPATH' ) || exit;

final class MI_Modello_Email {
	public static function avvia() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'aggiungi_riquadro' ) );
		add_action( 'save_post_' . MI_Event_Post_Type::EVENT_TYPE, array( __CLASS__, 'salva' ), 20, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'mostra_avviso' ) );
	}

	public static function segnaposto_ammessi() {
		return array(
			'{{evento.titolo}}',
			'{{evento.data}}',
			'{{evento.luogo}}',
			'{{attivita.nome}}',
			'{{ordine.codice}}',
			'{{ordine.stato}}',
			'{{ordine.partecipanti}}',
			'{{ordine.riepilogo}}',
			'{{ordine.riepilogo_economico}}',
			'{{ordine.totale}}',
			'{{referente.nome_completo}}',
			'{{pagamento.importo_dovuto}}',
			'{{pagamento.saldo}}',
			'{{pagamento.metodi}}',
			'{{pagamento.istruzioni}}',
			'{{pagamento.scadenza}}',
			'{{pagamento.causale}}',
			'{{legale.privacy_url}}',
		);
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
			'html'      => '<p>Gentile {{referente.nome_completo}},</p><p>la tua iscrizione a <strong>{{evento.titolo}}</strong> è stata registrata.</p><p><strong>Quando:</strong> {{evento.data}}<br><strong>Dove:</strong> {{evento.luogo}}</p><p><strong>Codice:</strong> {{ordine.codice}}<br><strong>Stato:</strong> {{ordine.stato}}<br><strong>Riepilogo:</strong> {{ordine.riepilogo}}</p><p><strong>Riepilogo economico:</strong> {{ordine.riepilogo_economico}}</p><p><strong>Pagamento:</strong> {{pagamento.istruzioni}}<br><strong>Scadenza:</strong> {{pagamento.scadenza}}<br><strong>Causale:</strong> {{pagamento.causale}}</p>',
			'text'      => "Gentile {{referente.nome_completo}},\n\nla tua iscrizione a {{evento.titolo}} è stata registrata.\nQuando: {{evento.data}}\nDove: {{evento.luogo}}\nCodice: {{ordine.codice}}\nStato: {{ordine.stato}}\nRiepilogo: {{ordine.riepilogo}}\nRiepilogo economico: {{ordine.riepilogo_economico}}\nPagamento: {{pagamento.istruzioni}}\nScadenza: {{pagamento.scadenza}}\nCausale: {{pagamento.causale}}",
			'footer'    => 'Un saluto dall’organizzazione.',
		);
		$saved = get_post_meta( $event_id, '_mi_email_template', true );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	public static function mostra_riquadro( $post ) {
		$settings = self::impostazioni( $post->ID );
		wp_nonce_field( 'mi_salva_modello_email', 'mi_modello_email_nonce' );
		$example = array(
			'{{evento.titolo}}'           => $post->post_title ?: 'Evento dimostrativo',
			'{{evento.data}}'             => '12 settembre 2026, ore 09:30',
			'{{evento.luogo}}'            => 'Oratorio parrocchiale',
			'{{attivita.nome}}'           => 'Attività dimostrativa',
			'{{ordine.codice}}'           => 'MI-ESEMPIO-0001',
			'{{ordine.stato}}'            => 'Confermata',
			'{{ordine.partecipanti}}'     => '2',
			'{{ordine.riepilogo}}'        => '2 × Quota ordinaria',
			'{{ordine.riepilogo_economico}}' => 'Totale: 40,00 € · Importo da versare: 20,00 € · Saldo: 20,00 €',
			'{{ordine.totale}}'           => '40,00 €',
			'{{referente.nome_completo}}' => 'Persona Esempio',
			'{{pagamento.importo_dovuto}}' => '20,00 €',
			'{{pagamento.saldo}}'         => '20,00 €',
			'{{pagamento.metodi}}'        => 'Bonifico',
			'{{pagamento.istruzioni}}'    => 'Bonifico: usa il codice iscrizione come causale.',
			'{{pagamento.scadenza}}'      => '5 settembre 2026, ore 23:59',
			'{{pagamento.causale}}'       => 'MI-ESEMPIO-0001',
			'{{legale.privacy_url}}'      => 'https://example.invalid/privacy',
		);
		$synthetic_snapshot = self::crea_istantanea( $post->ID, $example );
		?>
		<p><label><input type="checkbox" name="mi_email_enabled" value="1" <?php checked( '1', $settings['enabled'] ); ?>> Modello attivo per l’email di conferma</label></p>
		<div class="mi-admin-grid">
		<p><label for="mi_email_sender_name"><strong>Nome visualizzato del mittente</strong></label><br><input class="widefat" id="mi_email_sender_name" name="mi_email_sender_name" maxlength="120" value="<?php echo esc_attr( $settings['sender_name'] ); ?>" placeholder="Lascia vuoto per usare il valore organizzativo"></p>
		<p><label for="mi_email_reply_to"><strong>Indirizzo per le risposte</strong></label><br><input class="widefat" id="mi_email_reply_to" name="mi_email_reply_to" type="email" value="<?php echo esc_attr( $settings['reply_to'] ); ?>" placeholder="Lascia vuoto per usare il valore organizzativo"></p>
		</div>
		<p><label for="mi_email_internal_recipients"><strong>Indirizzi interni per le email di prova</strong></label><br><textarea class="widefat" id="mi_email_internal_recipients" name="mi_email_internal_recipients" rows="2" placeholder="Un indirizzo per riga, massimo 10"><?php echo esc_textarea( implode( "\n", (array) $settings['internal_recipients'] ) ); ?></textarea></p>
		<p class="description">Sono gli indirizzi interni riservati alle prove. In modalità Anteprima nessuna email viene inviata; gli indirizzi vengono soltanto validati e conservati nella configurazione riservata.</p>
		<p><label for="mi_email_subject"><strong>Oggetto</strong></label><br><input class="widefat" id="mi_email_subject" name="mi_email_subject" maxlength="180" value="<?php echo esc_attr( $settings['subject'] ); ?>"></p>
		<p><label for="mi_email_preheader"><strong>Preheader</strong></label><br><input class="widefat" id="mi_email_preheader" name="mi_email_preheader" maxlength="240" value="<?php echo esc_attr( $settings['preheader'] ); ?>"></p>
		<p><label for="mi_email_html"><strong>Corpo HTML</strong></label><br><textarea class="widefat" id="mi_email_html" name="mi_email_html" rows="8"><?php echo esc_textarea( $settings['html'] ); ?></textarea></p>
		<p><label for="mi_email_text"><strong>Testo semplice</strong></label><br><textarea class="widefat" id="mi_email_text" name="mi_email_text" rows="7"><?php echo esc_textarea( $settings['text'] ); ?></textarea></p>
		<p><label for="mi_email_footer"><strong>Footer</strong></label><br><textarea class="widefat" id="mi_email_footer" name="mi_email_footer" rows="3"><?php echo esc_textarea( $settings['footer'] ); ?></textarea></p>
		<p class="description"><strong>Segnaposto ammessi:</strong> <?php echo implode( ', ', array_map( static function ( $placeholder ) { return '<code>' . esc_html( $placeholder ) . '</code>'; }, self::segnaposto_ammessi() ) ); ?>.</p>
		<div class="mi-field-preview" data-mi-email-preview data-mi-email-values="<?php echo esc_attr( wp_json_encode( $example ) ); ?>"><strong>Anteprima con dati sintetici</strong><p><strong data-mi-email-preview-subject><?php echo esc_html( self::renderizza( $settings['subject'], $example ) ); ?></strong></p><p data-mi-email-preview-preheader><?php echo esc_html( self::renderizza( $settings['preheader'], $example ) ); ?></p><div><?php echo self::sanitizza_html_email( self::componi_html( $synthetic_snapshot ) ); ?></div><h4>Testo semplice</h4><pre data-mi-email-preview-text style="white-space:pre-wrap"><?php echo esc_html( self::renderizza( $settings['text'], $example ) ); ?></pre><hr><p data-mi-email-preview-footer><?php echo nl2br( esc_html( self::renderizza( $settings['footer'], $example ) ) ); ?></p><p class="notice-inline notice-error" data-mi-email-placeholder-error hidden></p></div>
		<p class="description">Questa schermata non invia email e usa esclusivamente dati di esempio.</p>
		<?php
	}

	public static function crea_istantanea( $event_id, $values ) {
		$settings = self::impostazioni( $event_id );
		$participant_management = isset( $values['_participant_management'] ) && is_array( $values['_participant_management'] ) ? $values['_participant_management'] : array();
		unset( $values['_participant_management'] );
		$snapshot = array( 'attivo' => '1' === $settings['enabled'] );
		foreach ( array( 'subject' => 'oggetto', 'preheader' => 'preheader', 'html' => 'html', 'text' => 'testo', 'footer' => 'footer' ) as $source => $destination ) {
			$snapshot[ $destination ] = 'html' === $source ? self::renderizza_html( $settings[ $source ], $values ) : self::renderizza( $settings[ $source ], $values );
		}
		$activity_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		$thumbnail_id = $activity_id ? get_post_thumbnail_id( $activity_id ) : 0;
		$legacy_color = $activity_id ? sanitize_hex_color( get_post_meta( $activity_id, '_mi_accent_color', true ) ) : '';
		$primary_color = $activity_id ? sanitize_hex_color( get_post_meta( $activity_id, '_mi_primary_color', true ) ) : '';
		$secondary_color = $activity_id ? sanitize_hex_color( get_post_meta( $activity_id, '_mi_secondary_color', true ) ) : '';
		$primary_color = $primary_color ?: ( $legacy_color ?: '#151b38' );
		$secondary_color = $secondary_color ?: '#337ab7';
		$snapshot['identita'] = array(
			'nome_attivita' => $activity_id ? get_the_title( $activity_id ) : '',
			'logo_url'      => $thumbnail_id ? (string) wp_get_attachment_image_url( $thumbnail_id, 'medium' ) : '',
			'logo_alt'      => $thumbnail_id ? (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) : '',
			'primary_color' => $primary_color,
			'secondary_color' => $secondary_color,
			'primary_text_color' => self::colore_testo_contrasto( $primary_color ),
			'secondary_text_color' => self::colore_testo_contrasto( $secondary_color ),
		);
		$snapshot['evento'] = array(
			'titolo' => sanitize_text_field( (string) ( $values['{{evento.titolo}}'] ?? get_the_title( $event_id ) ) ),
			'url'     => self::url_pubblica_evento( $event_id ),
		);
		$snapshot['identita_email'] = array(
			'nome_mittente'        => $settings['sender_name'],
			'indirizzo_risposte'   => $settings['reply_to'],
			'destinatari_interni'  => array_values( (array) $settings['internal_recipients'] ),
		);
		$identifier_mode = strtoupper( (string) get_post_meta( $event_id, '_mi_identifier_display', true ) );
		$snapshot['identificativo'] = array(
			'modalita' => in_array( $identifier_mode, array( 'NONE', 'TEXT', 'QR', 'BARCODE' ), true ) ? $identifier_mode : 'TEXT',
			'codice'   => (string) ( $values['{{ordine.codice}}'] ?? '' ),
			'payload_qr' => 'modulo-iscrizioni|evento:' . absint( $event_id ) . '|ordine:' . sanitize_text_field( (string) ( $values['{{ordine.codice}}'] ?? '' ) ),
		);
		$snapshot['gestione_partecipanti'] = array_values( array_filter( array_map( static function ( $item ) {
			$name = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
			$url = esc_url_raw( (string) ( $item['url'] ?? '' ) );
			return $name && $url ? array( 'nome' => $name, 'url' => $url ) : null;
		}, $participant_management ) ) );
		$snapshot['revisione'] = hash( 'sha256', wp_json_encode( $settings ) );
		return $snapshot;
	}

	public static function valori_ordine( $event, $order_code, $status_label, $quantity, $buyer_name, $economic_summary, $items = array() ) {
		$summary_lines = array();
		foreach ( (array) $items as $item ) {
			$name = sanitize_text_field( (string) ( $item['name'] ?? $item['code'] ?? '' ) );
			$item_quantity = absint( $item['quantity'] ?? 0 );
			if ( $name && $item_quantity ) {
				$summary_lines[] = $item_quantity . ' × ' . $name;
			}
		}
		if ( ! $summary_lines ) {
			$summary_lines[] = absint( $quantity ) . ( 1 === absint( $quantity ) ? ' partecipante' : ' partecipanti' );
		}
		$total = max( 0, (int) ( $economic_summary['total_cents'] ?? 0 ) );
		$due = max( 0, (int) ( $economic_summary['initial_due_cents'] ?? 0 ) );
		$balance = max( 0, (int) ( $economic_summary['balance_cents'] ?? 0 ) );
		$method_codes = array_values( array_intersect( array( 'BANK_TRANSFER', 'CARD', 'CASH' ), (array) ( $economic_summary['payment_methods'] ?? array() ) ) );
		$method_labels = array( 'BANK_TRANSFER' => 'Bonifico', 'CARD' => 'Carta', 'CASH' => 'Contanti' );
		$instruction_labels = array(
			'BANK_TRANSFER' => 'Bonifico: usa il codice iscrizione come causale e le coordinate comunicate dall’organizzazione.',
			'CARD'          => 'Carta: segui le indicazioni comunicate dall’organizzazione.',
			'CASH'          => 'Contanti: concorda la consegna con l’organizzazione.',
		);
		$methods = array_map( static function ( $code ) use ( $method_labels ) { return $method_labels[ $code ]; }, $method_codes );
		$instructions = array_map( static function ( $code ) use ( $instruction_labels ) { return $instruction_labels[ $code ]; }, $method_codes );
		if ( 0 === $due ) {
			$methods = array( 'Nessun pagamento previsto' );
			$instructions = array( 'Nessun pagamento richiesto.' );
		}
		$event_date = self::formatta_data_locale( $event['event_starts_at'] ?? '' );
		$deadline = $due ? self::formatta_data_locale( $event['payment_deadline_at'] ?? '' ) : 'Non applicabile';
		if ( $due && ! $deadline ) {
			$deadline = 'Contatta l’organizzazione';
		}
		$total_label = self::formatta_importo( $total );
		$due_label = self::formatta_importo( $due );
		$balance_label = self::formatta_importo( $balance );

		return array(
			'{{evento.titolo}}'              => sanitize_text_field( (string) ( $event['title'] ?? '' ) ),
			'{{evento.data}}'                => $event_date ?: 'Da definire',
			'{{evento.luogo}}'               => sanitize_text_field( (string) ( $event['event_location'] ?? '' ) ) ?: 'Da definire',
			'{{attivita.nome}}'              => sanitize_text_field( (string) ( $event['activity'] ?? '' ) ),
			'{{ordine.codice}}'              => sanitize_text_field( (string) $order_code ),
			'{{ordine.stato}}'               => sanitize_text_field( (string) $status_label ),
			'{{ordine.partecipanti}}'        => (string) absint( $quantity ),
			'{{ordine.riepilogo}}'           => implode( ' · ', $summary_lines ),
			'{{ordine.riepilogo_economico}}' => 'Totale: ' . $total_label . ' · Importo da versare: ' . $due_label . ' · Saldo: ' . $balance_label,
			'{{ordine.totale}}'              => $total_label,
			'{{referente.nome_completo}}'    => sanitize_text_field( (string) $buyer_name ),
			'{{pagamento.importo_dovuto}}'   => $due_label,
			'{{pagamento.saldo}}'            => $balance_label,
			'{{pagamento.metodi}}'           => implode( ', ', $methods ),
			'{{pagamento.istruzioni}}'       => implode( ' ', $instructions ),
			'{{pagamento.scadenza}}'         => $deadline,
			'{{pagamento.causale}}'          => $due ? sanitize_text_field( (string) $order_code ) : 'Non applicabile',
			'{{legale.privacy_url}}'         => esc_url_raw( $event['privacy_url'] ?? '' ),
		);
	}

	public static function sanitizza_html_email( $html ) {
		$allowed = wp_kses_allowed_html( 'post' );
		// Applichiamo direttamente l'allowlist email: un passaggio preliminare tramite
		// wp_kses_post() eliminerebbe gli attributi prima che possano essere autorizzati qui.
		$allowed['table'] = array_merge( $allowed['table'] ?? array(), array( 'role' => true, 'width' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true, 'bgcolor' => true, 'style' => true ) );
		$allowed['td'] = array_merge( $allowed['td'] ?? array(), array( 'role' => true, 'width' => true, 'align' => true, 'valign' => true, 'bgcolor' => true, 'style' => true ) );
		$allowed['tr'] = array_merge( $allowed['tr'] ?? array(), array( 'role' => true, 'bgcolor' => true, 'style' => true ) );
		return wp_kses( (string) $html, $allowed );
	}

	public static function componi_html( $istantanea, $codice_html = '' ) {
		$identity = isset( $istantanea['identita'] ) && is_array( $istantanea['identita'] ) ? $istantanea['identita'] : array();
		$email_identity = isset( $istantanea['identita_email'] ) && is_array( $istantanea['identita_email'] ) ? $istantanea['identita_email'] : array();
		$event = isset( $istantanea['evento'] ) && is_array( $istantanea['evento'] ) ? $istantanea['evento'] : array();
		$primary = sanitize_hex_color( $identity['primary_color'] ?? '' ) ?: '#151b38';
		$secondary = sanitize_hex_color( $identity['secondary_color'] ?? '' ) ?: '#337ab7';
		$primary_text = in_array( $identity['primary_text_color'] ?? '', array( '#ffffff', '#000000' ), true ) ? $identity['primary_text_color'] : self::colore_testo_contrasto( $primary );
		$secondary_text = in_array( $identity['secondary_text_color'] ?? '', array( '#ffffff', '#000000' ), true ) ? $identity['secondary_text_color'] : self::colore_testo_contrasto( $secondary );
		$preheader = ! empty( $istantanea['preheader'] ) ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . esc_html( $istantanea['preheader'] ) . '</div>' : '';
		$logo = '';
		if ( ! empty( $identity['logo_url'] ) ) {
			$logo = '<tr><td align="center" style="padding:20px 20px 0;"><img src="' . esc_url( $identity['logo_url'] ) . '" alt="' . esc_attr( $identity['logo_alt'] ?: ( $identity['nome_attivita'] ?? '' ) ) . '" width="160" style="display:block;width:auto;max-width:160px;height:auto;border:0;"></td></tr>';
		}
		$event_url = ! empty( $event['url'] ) ? esc_url( $event['url'] ) : '';
		$cta = $event_url ? '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;margin-bottom:20px;"><tr><td bgcolor="' . esc_attr( $secondary ) . '" style="border-radius:12px;"><a href="' . $event_url . '" style="display:inline-block;padding:14px 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:' . esc_attr( $secondary_text ) . ';text-decoration:none;font-weight:700;border-radius:12px;">Consulta la pagina dell’evento</a></td></tr></table>' : '';
		$reply_to = ! empty( $email_identity['indirizzo_risposte'] ) && is_email( $email_identity['indirizzo_risposte'] ) ? sanitize_email( $email_identity['indirizzo_risposte'] ) : '';
		$assistance = $reply_to ? 'Per domande o variazioni scrivi a <a href="mailto:' . esc_attr( $reply_to ) . '" style="color:' . esc_attr( $secondary ) . ';text-decoration:none;font-weight:700;">' . esc_html( $reply_to ) . '</a>.' : 'Per domande o variazioni, rispondi direttamente a questa email.';
		$activity_name = sanitize_text_field( (string) ( $identity['nome_attivita'] ?? '' ) );
		$title = sanitize_text_field( (string) ( $event['titolo'] ?? '' ) );
		$body = self::sanitizza_html_email( $istantanea['html'] ?? '' );
		$management_html = '';
		if ( ! empty( $istantanea['gestione_partecipanti'] ) && is_array( $istantanea['gestione_partecipanti'] ) ) {
			$management_html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin-top:20px;border-top:1px solid #e4e8ef;"><tr><td style="padding-top:18px"><div style="font-size:15px;font-weight:700;margin-bottom:8px;">Gestisci le partecipazioni</div><div style="font-size:13px;color:#666;margin-bottom:10px;">Ogni collegamento riguarda una sola persona e richiede una conferma.</div>';
			foreach ( $istantanea['gestione_partecipanti'] as $item ) $management_html .= '<div style="margin:8px 0"><a href="' . esc_url( $item['url'] ?? '' ) . '" style="color:' . esc_attr( $secondary ) . ';font-weight:700;text-decoration:none;">Annulla la partecipazione di ' . esc_html( $item['nome'] ?? '' ) . '</a></div>';
			$management_html .= '</td></tr></table>';
		}
		$footer = nl2br( esc_html( $istantanea['footer'] ?? '' ) );
		$code = (string) $codice_html;

		return '<!doctype html><html lang="it"><body style="margin:0;padding:0;background:#f6f8fc;">' . $preheader
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f6f8fc" style="width:100%;background:#f6f8fc;"><tr><td align="center" style="padding:24px 12px;">'
			. '<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;">'
			. $logo . '<tr><td bgcolor="' . esc_attr( $primary ) . '" style="background:' . esc_attr( $primary ) . ';padding:18px 20px;color:' . esc_attr( $primary_text ) . ';font-family:Arial,Helvetica,sans-serif;">'
			. '<div style="font-size:18px;font-weight:700;line-height:1.3;">' . esc_html( $title ?: 'Conferma iscrizione' ) . '</div>'
			. ( $activity_name ? '<div style="font-size:13px;line-height:1.4;margin-top:5px;opacity:0.9;">' . esc_html( $activity_name ) . '</div>' : '' )
			. '</td></tr><tr><td style="padding:24px 20px;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#333333;font-size:16px;line-height:1.6;">'
			. $body . $code . $cta . $management_html
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#eef2ff" style="width:100%;margin-top:20px;background:#eef2ff;border-radius:14px;"><tr><td style="padding:16px 20px;font-family:Arial,Helvetica,sans-serif;color:#333333;"><div style="font-size:15px;font-weight:700;margin-bottom:8px;">Assistenza</div><div style="font-size:14px;line-height:1.7;">' . $assistance . '</div></td></tr></table>'
			. '<div style="font-family:Arial,Helvetica,sans-serif;color:' . esc_attr( $secondary ) . ';font-size:14px;font-style:italic;font-weight:700;margin-top:18px;text-align:right;">' . $footer . '</div>'
			. '</td></tr></table>'
			. ( $event_url ? '<div style="font-family:Arial,Helvetica,sans-serif;color:#666666;font-size:12px;line-height:1.4;margin-top:12px;text-align:center;">Se il pulsante non funziona, apri: <a href="' . $event_url . '" style="color:' . esc_attr( $secondary ) . ';">' . esc_html( $event_url ) . '</a></div>' : '' )
			. '</td></tr></table></body></html>';
	}

	public static function componi_testo( $istantanea ) {
		$identity = isset( $istantanea['identita'] ) && is_array( $istantanea['identita'] ) ? $istantanea['identita'] : array();
		$email_identity = isset( $istantanea['identita_email'] ) && is_array( $istantanea['identita_email'] ) ? $istantanea['identita_email'] : array();
		$event = isset( $istantanea['evento'] ) && is_array( $istantanea['evento'] ) ? $istantanea['evento'] : array();
		$management_lines = array();
		foreach ( (array) ( $istantanea['gestione_partecipanti'] ?? array() ) as $item ) if ( ! empty( $item['nome'] ) && ! empty( $item['url'] ) ) $management_lines[] = 'Annulla la partecipazione di ' . sanitize_text_field( $item['nome'] ) . ': ' . esc_url_raw( $item['url'] );
		$parts = array_filter( array(
			sanitize_text_field( (string) ( $event['titolo'] ?? '' ) ),
			sanitize_text_field( (string) ( $identity['nome_attivita'] ?? '' ) ),
			sanitize_textarea_field( (string) ( $istantanea['testo'] ?? '' ) ),
			! empty( $istantanea['identificativo']['codice'] ) ? 'Codice: ' . sanitize_text_field( $istantanea['identificativo']['codice'] ) : '',
			! empty( $event['url'] ) ? 'Pagina evento: ' . esc_url_raw( $event['url'] ) : '',
			$management_lines ? "Gestisci le partecipazioni:\n" . implode( "\n", $management_lines ) : '',
			! empty( $email_identity['indirizzo_risposte'] ) ? 'Assistenza: ' . sanitize_email( $email_identity['indirizzo_risposte'] ) : 'Assistenza: rispondi a questa email.',
			sanitize_textarea_field( (string) ( $istantanea['footer'] ?? '' ) ),
		) );
		return implode( "\n\n", $parts );
	}

	private static function url_pubblica_evento( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return '';
		}
		$pages = get_posts( array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'numberposts'            => -1,
			's'                      => 'modulo_iscrizioni',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );
		$pattern = get_shortcode_regex( array( 'modulo_iscrizioni' ) );
		foreach ( $pages as $page ) {
			if ( ! preg_match_all( '/' . $pattern . '/s', (string) $page->post_content, $matches, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $matches as $match ) {
				if ( 'modulo_iscrizioni' !== ( $match[2] ?? '' ) ) {
					continue;
				}
				$attributes = shortcode_parse_atts( $match[3] ?? '' );
				if ( $event_id === absint( $attributes['event'] ?? 0 ) ) {
					return esc_url_raw( get_permalink( $page->ID ) );
				}
			}
		}
		return '';
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
			'html'      => self::sanitizza_html_email( wp_unslash( $_POST['mi_email_html'] ?? '' ) ),
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

	private static function colore_testo_contrasto( $background ) {
		$hex = ltrim( (string) sanitize_hex_color( $background ), '#' );
		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}
		$channels = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		$channels = array_map( static function ( $channel ) {
			$value = $channel / 255;
			return $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}, $channels );
		$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		$white_contrast = 1.05 / ( $luminance + 0.05 );
		return $white_contrast >= 4.5 ? '#ffffff' : '#000000';
	}

	private static function formatta_importo( $cents ) {
		return number_format( max( 0, (int) $cents ) / 100, 2, ',', '.' ) . ' €';
	}

	private static function formatta_data_locale( $value ) {
		if ( ! $value || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $value ) ) {
			return '';
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', (string) $value, wp_timezone() );
		return $date ? wp_date( 'd/m/Y, H:i', $date->getTimestamp(), wp_timezone() ) : '';
	}
}
