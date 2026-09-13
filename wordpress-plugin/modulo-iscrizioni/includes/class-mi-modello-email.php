<?php

defined( 'ABSPATH' ) || exit;

final class MI_Modello_Email {
	const OPZIONE_STILE_DEFAULT = 'mi_email_stile_default';

	/** Token sempre disponibili: le configurazioni di gruppo ed evento contengono solo override. */
	public static function stile_default() {
		$defaults = array(
			'identity_name'   => get_bloginfo( 'name' ) ?: 'Parrocchia Sant’Eugenio',
			'identity_detail' => 'Viale delle Belle Arti 10, Roma',
			'contact_email'   => sanitize_email( get_option( 'admin_email', '' ) ),
			'logo_url'        => '',
			'logo_enabled'    => false,
			'banner_url'      => defined( 'MI_PLUGIN_URL' ) ? MI_PLUGIN_URL . 'assets/email-banner-default.svg' : '',
			'primary_color'   => '#151b38',
			'secondary_color' => '#337ab7',
			'signature'       => 'Segreteria parrocchiale',
		);
		$saved = get_option( self::OPZIONE_STILE_DEFAULT, array() );
		return self::sanitizza_stile( array_merge( $defaults, is_array( $saved ) ? $saved : array() ), false );
	}

	public static function sanitizza_stile( $style, $overrides_only = true ) {
		$style = is_array( $style ) ? $style : array();
		$clean = array();
		foreach ( array( 'identity_name' => 120, 'identity_detail' => 180, 'signature' => 240 ) as $key => $limit ) {
			$value = self::pulisci_riga( $style[ $key ] ?? '', $limit );
			if ( ! $overrides_only || '' !== $value ) $clean[ $key ] = $value;
		}
		$email = sanitize_email( $style['contact_email'] ?? '' );
		if ( ! $overrides_only || $email ) $clean['contact_email'] = $email;
		foreach ( array( 'logo_url', 'banner_url' ) as $key ) {
			$url = esc_url_raw( (string) ( $style[ $key ] ?? '' ), array( 'https' ) );
			if ( ! $overrides_only || $url ) $clean[ $key ] = $url;
		}
		foreach ( array( 'primary_color', 'secondary_color' ) as $key ) {
			$color = sanitize_hex_color( $style[ $key ] ?? '' );
			if ( ! $overrides_only || $color ) $clean[ $key ] = $color;
		}
		if ( ! empty( $style['logo_enabled'] ) ) $clean['logo_enabled'] = true;
		elseif ( ! $overrides_only ) $clean['logo_enabled'] = false;
		return $clean;
	}

	public static function stile_risolto( $event_id ) {
		$event_id = absint( $event_id );
		$group_id = $event_id ? absint( get_post_meta( $event_id, '_mi_activity_id', true ) ) : 0;
		$group = $group_id ? self::sanitizza_stile( get_post_meta( $group_id, '_mi_email_style', true ) ) : array();
		$event = $event_id ? self::sanitizza_stile( get_post_meta( $event_id, '_mi_email_style', true ) ) : array();
		$style = array_merge( self::stile_default(), $group, $event );
		$group_logo_id = $group_id ? get_post_thumbnail_id( $group_id ) : 0;
		$group_cover_id = $group_id ? absint( get_post_meta( $group_id, '_mi_group_cover_image_id', true ) ) : 0;
		$event_image = $event_id ? (string) get_the_post_thumbnail_url( $event_id, 'large' ) : '';
		$group_banner = $group_cover_id ? (string) wp_get_attachment_image_url( $group_cover_id, 'large' ) : (string) get_post_meta( $group_id, '_mi_group_cover_image_url', true );
		$group_logo = $group_logo_id ? (string) wp_get_attachment_image_url( $group_logo_id, 'medium' ) : (string) get_post_meta( $group_id, '_mi_group_logo_url', true );
		if ( empty( $event['logo_url'] ) && empty( $group['logo_url'] ) && $group_logo ) $style['logo_url'] = esc_url_raw( $group_logo, array( 'https' ) );
		if ( $group_logo && ! array_key_exists( 'logo_enabled', $event ) && ! array_key_exists( 'logo_enabled', $group ) ) $style['logo_enabled'] = true;
		if ( empty( $style['logo_enabled'] ) ) $style['logo_url'] = '';
		// Un override esplicito resta possibile; altrimenti l'immagine evento è automatica.
		if ( empty( $event['banner_url'] ) ) $style['banner_url'] = $event_image ?: ( ! empty( $group['banner_url'] ) ? $group['banner_url'] : ( $group_banner ?: $style['banner_url'] ) );
		$style['source'] = ( $event || $event_image ) ? 'event' : ( ( $group || $group_logo || $group_banner ) ? 'group' : 'default' );
		$style['group_id'] = $group_id;
		return $style;
	}
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
			'{{sottoscrittore.nome_completo}}',
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
			'subject'   => 'Iscrizione confermata — {{evento.titolo}}',
			'preheader' => 'La tua iscrizione è stata registrata. Qui trovi il riepilogo e le prossime indicazioni.',
			'html'      => '<p style="margin:0 0 16px;">Ciao {{sottoscrittore.nome_completo}},</p><p style="margin:0 0 20px;">la tua iscrizione a <strong>{{evento.titolo}}</strong> è stata registrata.</p><p style="margin:0 0 20px;"><strong>Quando:</strong> {{evento.data}}<br><strong>Dove:</strong> {{evento.luogo}}<br><strong>Codice iscrizione:</strong> {{ordine.codice}}<br><strong>Stato:</strong> {{ordine.stato}}<br><strong>Partecipazione:</strong> {{ordine.riepilogo}}</p><p style="margin:0 0 20px;"><strong>Situazione economica:</strong><br>{{ordine.riepilogo_economico}}</p><p style="margin:0 0 20px;"><strong>Indicazioni per il pagamento:</strong><br>{{pagamento.istruzioni}}<br><strong>Scadenza:</strong> {{pagamento.scadenza}}<br><strong>Causale:</strong> {{pagamento.causale}}</p><p style="margin:24px 0 0;"><strong>Conserva questa email:</strong> contiene i riferimenti utili per la tua iscrizione.</p>',
			'text'      => "Ciao {{sottoscrittore.nome_completo}},\n\nla tua iscrizione a {{evento.titolo}} è stata registrata.\n\nQuando: {{evento.data}}\nDove: {{evento.luogo}}\nCodice iscrizione: {{ordine.codice}}\nStato: {{ordine.stato}}\nPartecipazione: {{ordine.riepilogo}}\n\nSituazione economica: {{ordine.riepilogo_economico}}\nIndicazioni per il pagamento: {{pagamento.istruzioni}}\nScadenza: {{pagamento.scadenza}}\nCausale: {{pagamento.causale}}\n\nConserva questa email: contiene i riferimenti utili per la tua iscrizione.",
			'footer'    => 'A presto!',
		);
		$saved = get_post_meta( $event_id, '_mi_email_template', true );
		return self::aggiorna_segnaposto( array_merge( $defaults, is_array( $saved ) ? $saved : array() ) );
	}

	private static function aggiorna_segnaposto( $settings ) {
		foreach ( $settings as $key => $value ) {
			if ( is_string( $value ) ) $settings[ $key ] = str_replace( array( '{{referente.nome_completo}}', '{{iscrivente.nome_completo}}' ), '{{sottoscrittore.nome_completo}}', $value );
		}
		return $settings;
	}

	public static function mostra_riquadro( $post ) {
		$settings = self::impostazioni( $post->ID );
		$email_style = (array) get_post_meta( $post->ID, '_mi_email_style', true );
		$resolved_style = self::stile_risolto( $post->ID );
		$identity_override = ! empty( array_intersect_key( $email_style, array_flip( array( 'identity_name', 'identity_detail', 'contact_email', 'signature' ) ) ) );
		$color_override = ! empty( array_intersect_key( $email_style, array_flip( array( 'primary_color', 'secondary_color' ) ) ) );
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
			'{{sottoscrittore.nome_completo}}' => 'Persona Esempio',
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
		<h3>Aspetto email</h3>
		<p><strong><?php echo $email_style ? 'Override evento attivi' : 'Usa lo stile ereditato'; ?></strong> · <?php echo esc_html( 'group' === $resolved_style['source'] ? 'Gruppo: ' . get_the_title( $resolved_style['group_id'] ) : ( 'event' === $resolved_style['source'] ? 'Evento' : 'Default parrocchia' ) ); ?></p>
		<p class="description"><?php echo get_post_thumbnail_id( $post->ID ) ? 'Banner email: immagine dell’evento.' : 'Banner email: gruppo/default, salvo override esplicito.'; ?></p>
		<p><label><input type="checkbox" name="mi_email_event_identity_enabled" value="1" <?php checked( $identity_override ); ?>> Personalizza identità e firma</label></p>
		<div class="mi-admin-grid"><p><label>Nome visualizzato<input class="widefat" name="mi_email_event_identity_name" maxlength="120" value="<?php echo esc_attr( $email_style['identity_name'] ?? '' ); ?>"></label></p><p><label>Dettaglio istituzionale<input class="widefat" name="mi_email_event_identity_detail" maxlength="180" value="<?php echo esc_attr( $email_style['identity_detail'] ?? '' ); ?>"></label></p><p><label>Email di contatto<input class="widefat" type="email" name="mi_email_event_contact" value="<?php echo esc_attr( $email_style['contact_email'] ?? '' ); ?>"></label></p><p><label>Firma<input class="widefat" name="mi_email_event_signature" maxlength="240" value="<?php echo esc_attr( $email_style['signature'] ?? '' ); ?>"></label></p></div>
		<p><label><input type="checkbox" name="mi_email_event_colors_enabled" value="1" <?php checked( $color_override ); ?>> Personalizza colori</label></p>
		<div class="mi-admin-grid"><p><label>Colore principale<input type="color" name="mi_email_event_primary" value="<?php echo esc_attr( $email_style['primary_color'] ?? $resolved_style['primary_color'] ); ?>"></label></p><p><label>Colore CTA<input type="color" name="mi_email_event_secondary" value="<?php echo esc_attr( $email_style['secondary_color'] ?? $resolved_style['secondary_color'] ); ?>"></label></p></div>
		<p><label><input type="checkbox" name="mi_email_event_banner_enabled" value="1" <?php checked( ! empty( $email_style['banner_url'] ) ); ?>> Sostituisci solo il banner</label><br><input class="widefat" type="url" name="mi_email_event_banner" value="<?php echo esc_attr( $email_style['banner_url'] ?? '' ); ?>" placeholder="https://…"></p><hr>
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
		if ( 'ZERO' === strtoupper( (string) get_post_meta( $event_id, '_mi_pricing_mode', true ) ) ) {
			$settings['html'] = self::rimuovi_riferimenti_pagamento_gratuito( $settings['html'], true );
			$settings['text'] = self::rimuovi_riferimenti_pagamento_gratuito( $settings['text'] );
		}
		$participant_management = isset( $values['_participant_management'] ) && is_array( $values['_participant_management'] ) ? $values['_participant_management'] : array();
		unset( $values['_participant_management'] );
		$snapshot = array( 'attivo' => '1' === $settings['enabled'] );
		foreach ( array( 'subject' => 'oggetto', 'preheader' => 'preheader', 'html' => 'html', 'text' => 'testo', 'footer' => 'footer' ) as $source => $destination ) {
			$snapshot[ $destination ] = 'html' === $source ? self::renderizza_html( $settings[ $source ], $values ) : self::renderizza( $settings[ $source ], $values );
		}
		$activity_id = absint( get_post_meta( $event_id, '_mi_activity_id', true ) );
		$style = self::stile_risolto( $event_id );
		$snapshot['identita'] = array(
			'nome_attivita' => $activity_id ? get_the_title( $activity_id ) : '',
			'nome'          => $style['identity_name'],
			'dettaglio'     => $style['identity_detail'],
			'contatto'      => $style['contact_email'],
			'firma'         => $style['signature'],
			'logo_url'      => $style['logo_url'],
			'logo_alt'      => $style['identity_name'],
			'primary_color' => $style['primary_color'],
			'secondary_color' => $style['secondary_color'],
			'primary_text_color' => self::colore_testo_contrasto( $style['primary_color'] ),
			'secondary_text_color' => self::colore_testo_contrasto( $style['secondary_color'] ),
			'origine_stile' => $style['source'],
		);
		$snapshot['evento'] = array(
			'titolo' => sanitize_text_field( (string) ( $values['{{evento.titolo}}'] ?? get_the_title( $event_id ) ) ),
			'url'     => self::url_pubblica_evento( $event_id ),
			'cover_url' => esc_url_raw( (string) $style['banner_url'], array( 'https' ) ),
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

	/** Comunicazione dedicata: la richiesta è in coda e non richiede azioni o pagamenti. */
	public static function crea_istantanea_lista_attesa( $event_id, $values ) {
		$snapshot = self::crea_istantanea( $event_id, $values );
		$name = sanitize_text_field( (string) ( $values['{{sottoscrittore.nome_completo}}'] ?? '' ) );
		$title = sanitize_text_field( (string) ( $values['{{evento.titolo}}'] ?? get_the_title( $event_id ) ) );
		$summary = sanitize_text_field( (string) ( $values['{{ordine.riepilogo}}'] ?? '' ) );
		$snapshot['oggetto'] = 'Richiesta in lista d’attesa — ' . $title;
		$snapshot['preheader'] = 'La richiesta è stata registrata. Per ora non devi fare nulla.';
		$snapshot['html'] = '<p>Ciao ' . esc_html( $name ) . ',</p><p>la tua richiesta per <strong>' . esc_html( $title ) . '</strong> è stata inserita in lista d’attesa.</p>' . ( $summary ? '<p><strong>Partecipazione:</strong> ' . esc_html( $summary ) . '</p>' : '' ) . '<p>Il posto non è ancora confermato. Per ora non devi pagare né fare altro.</p><p>Se si libereranno posti compatibili con la tua richiesta, riceverai un’altra email con il termine entro cui accettare o rinunciare.</p>';
		$snapshot['testo'] = "Ciao {$name},\n\nla tua richiesta per {$title} è stata inserita in lista d’attesa.\n\nIl posto non è ancora confermato. Per ora non devi pagare né fare altro.\n\nSe si libereranno posti compatibili con la tua richiesta, riceverai un’altra email con il termine entro cui accettare o rinunciare.";
		$snapshot['identificativo'] = array( 'modalita' => 'NONE', 'codice' => '', 'payload_qr' => '' );
		$snapshot['gestione_partecipanti'] = array();
		$snapshot['status_url'] = '';
		return $snapshot;
	}

	/** Comunicazione dedicata: il posto è riservato fino alla scadenza indicata. */
	public static function crea_istantanea_offerta_lista_attesa( $event_id, $values, $offer_url, $expires_label ) {
		$snapshot = self::crea_istantanea( $event_id, $values );
		$name = sanitize_text_field( (string) ( $values['{{sottoscrittore.nome_completo}}'] ?? '' ) );
		$title = sanitize_text_field( (string) ( $values['{{evento.titolo}}'] ?? get_the_title( $event_id ) ) );
		$expires_label = sanitize_text_field( (string) $expires_label );
		$snapshot['oggetto'] = 'Si è liberato un posto — ' . $title;
		$snapshot['preheader'] = 'Accetta o rinuncia entro ' . $expires_label . '.';
		$snapshot['html'] = '<p>Ciao ' . esc_html( $name ) . ',</p><p>si sono liberati i posti richiesti per <strong>' . esc_html( $title ) . '</strong>.</p><p>Li abbiamo riservati per te fino a <strong>' . esc_html( $expires_label ) . '</strong>.</p><p>Apri il collegamento e scegli <strong>Accetta il posto</strong> oppure <strong>Rinuncia</strong>. Se non rispondi entro il termine, la proposta scadrà e i posti passeranno alla richiesta successiva.</p><p>Non effettuare pagamenti prima di aver accettato.</p>';
		$snapshot['testo'] = "Ciao {$name},\n\nsi sono liberati i posti richiesti per {$title}. Li abbiamo riservati per te fino a {$expires_label}.\n\nApri il collegamento e scegli Accetta il posto oppure Rinuncia. Se non rispondi entro il termine, la proposta scadrà e i posti passeranno alla richiesta successiva.\n\nNon effettuare pagamenti prima di aver accettato.";
		$snapshot['identificativo'] = array( 'modalita' => 'NONE', 'codice' => '', 'payload_qr' => '' );
		$snapshot['gestione_partecipanti'] = array();
		$snapshot['status_url'] = '';
		$snapshot['action_url'] = esc_url_raw( (string) $offer_url );
		$snapshot['action_label'] = 'Rispondi alla proposta';
		return $snapshot;
	}

	public static function crea_istantanea_operativa( $event_id, $values, $template_type, $message, $status_url ) {
		$snapshot = self::crea_istantanea( $event_id, $values );
		$snapshot['identificativo']['modalita'] = 'NONE';
		$snapshot['gestione_partecipanti'] = array();
		$snapshot['status_url'] = esc_url_raw( (string) $status_url );
		$event_title = sanitize_text_field( (string) ( $values['{{evento.titolo}}'] ?? get_the_title( $event_id ) ) );
		$buyer_name = sanitize_text_field( (string) ( $values['{{sottoscrittore.nome_completo}}'] ?? '' ) );
		if ( 'EVENT_CANCELLATION' === $template_type ) {
			$clean_message = sanitize_textarea_field( (string) $message );
			$snapshot['oggetto'] = 'Evento annullato — ' . $event_title;
			$snapshot['preheader'] = 'Comunicazione importante relativa alla tua iscrizione.';
			$snapshot['html'] = '<p>Gentile ' . esc_html( $buyer_name ) . ',</p><p>ti informiamo che <strong>' . esc_html( $event_title ) . '</strong> è stato annullato.</p>' . ( $clean_message ? '<p><strong>Motivo comunicato:</strong><br>' . nl2br( esc_html( $clean_message ) ) . '</p>' : '' ) . '<p>La segreteria ti contatterà separatamente se sono necessari rimborsi o altri adempimenti.</p>';
			$snapshot['testo'] = "Gentile {$buyer_name},\n\nl’evento {$event_title} è stato annullato." . ( $clean_message ? "\n\nMotivo comunicato:\n{$clean_message}" : '' ) . "\n\nLa segreteria ti contatterà separatamente se sono necessari rimborsi o altri adempimenti.";
			$snapshot['titolo'] = 'Evento annullato';
			$snapshot['status_url'] = '';
		} elseif ( in_array( $template_type, array( 'DEPOSIT_REMINDER', 'BALANCE_REMINDER' ), true ) ) {
			$is_deposit = 'DEPOSIT_REMINDER' === $template_type;
			$amount = (string) ( $values[ $is_deposit ? '{{pagamento.importo_dovuto}}' : '{{pagamento.saldo}}' ] ?? '' );
			$label = $is_deposit ? 'caparra' : 'saldo';
			$snapshot['oggetto'] = 'Promemoria ' . $label . ' — ' . $event_title;
			$snapshot['titolo'] = 'Promemoria ' . $label;
			$snapshot['preheader'] = 'Controlla l’importo e la scadenza.';
			$snapshot['html'] = '<p>Gentile ' . esc_html( $buyer_name ) . ',</p><p>ti ricordiamo la scadenza relativa a <strong>' . esc_html( $event_title ) . '</strong>.</p><p><strong>' . esc_html( ucfirst( $label ) ) . ' da versare:</strong> ' . esc_html( $amount ) . '<br><strong>Scadenza:</strong> ' . esc_html( (string) ( $values['{{pagamento.scadenza}}'] ?? '' ) ) . '<br><strong>Causale:</strong> ' . esc_html( (string) ( $values['{{pagamento.causale}}'] ?? '' ) ) . '</p>';
			$snapshot['testo'] = "Gentile {$buyer_name},\n\nper {$event_title}: {$label} da versare {$amount}.\nScadenza: " . (string) ( $values['{{pagamento.scadenza}}'] ?? '' ) . "\nCausale: " . (string) ( $values['{{pagamento.causale}}'] ?? '' );
		} elseif ( 'REGISTRATION_CANCELLATION' === $template_type ) {
			$snapshot['oggetto'] = 'Iscrizione cancellata — ' . $event_title; $snapshot['titolo'] = 'Iscrizione cancellata';
			$snapshot['preheader'] = 'La cancellazione è stata registrata.';
			$snapshot['html'] = '<p>Gentile ' . esc_html( $buyer_name ) . ',</p><p>la cancellazione dell’iscrizione a <strong>' . esc_html( $event_title ) . '</strong> è stata registrata.</p>';
			$snapshot['testo'] = "Gentile {$buyer_name},\n\nla cancellazione dell’iscrizione a {$event_title} è stata registrata.";
			$snapshot['status_url'] = '';
		} else {
			$clean_message = sanitize_textarea_field( (string) $message );
			$snapshot['oggetto'] = ( 'MATERIAL_DELIVERY' === $template_type ? 'Materiale' : 'Informazioni utili' ) . ' — ' . $event_title;
			$snapshot['titolo'] = 'MATERIAL_DELIVERY' === $template_type ? 'Materiale per l’evento' : 'Comunicazione';
			$snapshot['preheader'] = 'Indicazioni operative prima dell’evento.';
			$snapshot['html'] = '<p>Gentile ' . esc_html( $buyer_name ) . ',</p><p>ecco le informazioni aggiornate per <strong>' . esc_html( $event_title ) . '</strong>.</p><p>' . nl2br( esc_html( $clean_message ) ) . '</p>';
			$snapshot['testo'] = "Gentile {$buyer_name},\n\necco le informazioni aggiornate per {$event_title}.\n\n{$clean_message}";
		}
		return $snapshot;
	}

	/** Le notifiche alla parrocchia non devono mai ereditare asset o colori dell'evento. */
	public static function crea_istantanea_istituzionale( $event_id, $subject, $preheader, $body_html, $body_text, $actions = array() ) {
		$event_title = sanitize_text_field( get_the_title( absint( $event_id ) ) );
		return array(
			'attivo' => true,
			'layout' => 'INSTITUTIONAL',
			'oggetto' => sanitize_text_field( $subject ),
			'titolo' => sanitize_text_field( $subject ),
			'preheader' => sanitize_text_field( $preheader ),
			'html' => self::sanitizza_html_email( $body_html ),
			'testo' => sanitize_textarea_field( $body_text ),
			'evento' => array( 'titolo' => $event_title ),
			'azioni' => array_values( array_filter( array_map( static function ( $action ) {
				$url = esc_url_raw( (string) ( $action['url'] ?? '' ), array( 'https' ) );
				$label = sanitize_text_field( (string) ( $action['label'] ?? '' ) );
				return $url && $label ? array( 'url' => $url, 'label' => $label ) : null;
			}, (array) $actions ) ) ),
			'identita_email' => array(),
			'identificativo' => array( 'modalita' => 'NONE', 'codice' => '', 'payload_qr' => '' ),
		);
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
			'{{sottoscrittore.nome_completo}}' => sanitize_text_field( (string) $buyer_name ),
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
		if ( 'PUBLIC_BALANCE' === ( $istantanea['layout'] ?? '' ) ) return (string) ( $istantanea['html'] ?? '' );
		if ( 'INSTITUTIONAL' === ( $istantanea['layout'] ?? '' ) ) return self::componi_html_istituzionale( $istantanea );
		$identity = isset( $istantanea['identita'] ) && is_array( $istantanea['identita'] ) ? $istantanea['identita'] : array();
		$email_identity = isset( $istantanea['identita_email'] ) && is_array( $istantanea['identita_email'] ) ? $istantanea['identita_email'] : array();
		$event = isset( $istantanea['evento'] ) && is_array( $istantanea['evento'] ) ? $istantanea['evento'] : array();
		$primary = sanitize_hex_color( $identity['primary_color'] ?? '' ) ?: '#151b38';
		$secondary = sanitize_hex_color( $identity['secondary_color'] ?? '' ) ?: '#337ab7';
		$primary_text = in_array( $identity['primary_text_color'] ?? '', array( '#ffffff', '#000000' ), true ) ? $identity['primary_text_color'] : self::colore_testo_contrasto( $primary );
		$secondary_text = in_array( $identity['secondary_text_color'] ?? '', array( '#ffffff', '#000000' ), true ) ? $identity['secondary_text_color'] : self::colore_testo_contrasto( $secondary );
		$title = sanitize_text_field( (string) ( $event['titolo'] ?? '' ) );
		$communication_title = sanitize_text_field( (string) ( $istantanea['titolo'] ?? $istantanea['oggetto'] ?? $title ) );
		$preheader = ! empty( $istantanea['preheader'] ) ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">' . esc_html( $istantanea['preheader'] ) . '</div>' : '';
		$logo = '';
		if ( ! empty( $identity['logo_url'] ) ) {
			$logo = '<img src="' . esc_url( $identity['logo_url'] ) . '" alt="' . esc_attr( $identity['logo_alt'] ?: ( $identity['nome_attivita'] ?? '' ) ) . '" width="54" height="54" style="display:block;width:54px;height:54px;border:3px solid #ffffff;border-radius:50%;object-fit:cover;background:#ffffff;box-shadow:0 1px 4px rgba(0,0,0,.22);">';
		}
		$event_banner = ! empty( $event['cover_url'] ) ? '<tr><td background="' . esc_url( $event['cover_url'] ) . '" valign="top" style="height:210px;padding:16px;background-color:' . esc_attr( $primary ) . ';background-image:url(\'' . esc_url( $event['cover_url'] ) . '\');background-position:center;background-size:cover;background-repeat:no-repeat;">' . $logo . '</td></tr>' : ( $logo ? '<tr><td bgcolor="' . esc_attr( $primary ) . '" style="padding:16px;">' . $logo . '</td></tr>' : '' );
		$event_url = ! empty( $event['url'] ) ? esc_url( $event['url'] ) : '';
		$cta = $event_url ? '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;margin-bottom:20px;"><tr><td bgcolor="' . esc_attr( $secondary ) . '" style="border-radius:12px;"><a href="' . $event_url . '" style="display:inline-block;padding:14px 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:' . esc_attr( $secondary_text ) . ';text-decoration:none;font-weight:700;border-radius:12px;">Consulta la pagina dell’evento</a></td></tr></table>' : '';
		$reply_to = ! empty( $email_identity['indirizzo_risposte'] ) && is_email( $email_identity['indirizzo_risposte'] ) ? sanitize_email( $email_identity['indirizzo_risposte'] ) : '';
		$assistance = $reply_to ? 'Per domande o variazioni scrivi a <a href="mailto:' . esc_attr( $reply_to ) . '" style="color:' . esc_attr( $primary ) . ';text-decoration:none;font-weight:700;">' . esc_html( $reply_to ) . '</a>.' : 'Per domande o variazioni, rispondi direttamente a questa email.';
		$activity_name = sanitize_text_field( (string) ( $identity['nome_attivita'] ?? '' ) );
		$body = self::sanitizza_html_email( $istantanea['html'] ?? '' );
		$management_html = '';
		if ( ! empty( $istantanea['gestione_partecipanti'] ) && is_array( $istantanea['gestione_partecipanti'] ) ) {
			$management_html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin-top:20px;border-top:1px solid #e4e8ef;"><tr><td style="padding-top:18px"><div style="font-size:15px;font-weight:700;margin-bottom:8px;">Gestisci le partecipazioni</div><div style="font-size:13px;color:#666;margin-bottom:10px;">Ogni collegamento riguarda una sola persona e richiede una conferma.</div>';
			foreach ( $istantanea['gestione_partecipanti'] as $item ) $management_html .= '<div style="margin:8px 0"><a href="' . esc_url( $item['url'] ?? '' ) . '" style="color:' . esc_attr( $secondary ) . ';font-weight:700;text-decoration:none;">Annulla la partecipazione di ' . esc_html( $item['nome'] ?? '' ) . '</a></div>';
			$management_html .= '</td></tr></table>';
		}
		$status_html = ! empty( $istantanea['status_url'] ) ? '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;"><tr><td bgcolor="' . esc_attr( $secondary ) . '" style="border-radius:12px;"><a href="' . esc_url( $istantanea['status_url'] ) . '" style="display:inline-block;padding:14px 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:' . esc_attr( $secondary_text ) . ';text-decoration:none;font-weight:700;border-radius:12px;">Controlla stato e saldo</a></td></tr></table>' : '';
		$action_url = ! empty( $istantanea['action_url'] ) ? esc_url( $istantanea['action_url'] ) : '';
		$action_label = sanitize_text_field( (string) ( $istantanea['action_label'] ?? 'Apri' ) );
		$action_html = $action_url ? '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;"><tr><td bgcolor="' . esc_attr( $secondary ) . '" style="border-radius:12px;"><a href="' . $action_url . '" style="display:inline-block;padding:14px 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:' . esc_attr( $secondary_text ) . ';text-decoration:none;font-weight:700;border-radius:12px;">' . esc_html( $action_label ) . '</a></td></tr></table>' : '';
		$footer = nl2br( esc_html( $istantanea['footer'] ?? '' ) );
		$code = (string) $codice_html;

		return '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ?: 'Comunicazione iscrizione' ) . '</title></head><body style="margin:0;padding:0;background:#f6f8fc;">' . $preheader
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f6f8fc" style="width:100%;background:#f6f8fc;"><tr><td align="center" style="padding:24px 12px;">'
			. '<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e4e8ef;">'
			. $event_banner . '<tr><td bgcolor="' . esc_attr( $primary ) . '" style="background:' . esc_attr( $primary ) . ';padding:20px;color:' . esc_attr( $primary_text ) . ';font-family:Arial,Helvetica,sans-serif;">'
			. '<div style="font-size:13px;line-height:1.4;opacity:0.9;">' . esc_html( $title ) . '</div>'
			. '<div style="font-size:24px;font-weight:700;line-height:1.3;margin-top:5px;">' . esc_html( $communication_title ?: 'Comunicazione iscrizione' ) . '</div>'
			. ( $activity_name ? '<div style="font-size:13px;line-height:1.4;margin-top:5px;opacity:0.9;">' . esc_html( $activity_name ) . '</div>' : '' )
			. '</td></tr><tr><td style="padding:26px 22px;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#111827;font-size:16px;line-height:1.65;">'
			. $body . $code . $action_html . $cta . $status_html . $management_html
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#eef2ff" style="width:100%;margin-top:20px;background:#eef2ff;border-radius:14px;"><tr><td style="padding:16px 20px;font-family:Arial,Helvetica,sans-serif;color:#333333;"><div style="font-size:15px;font-weight:700;margin-bottom:8px;">Assistenza</div><div style="font-size:15px;line-height:1.7;">' . $assistance . '</div></td></tr></table>'
			. '<div style="font-family:Arial,Helvetica,sans-serif;color:' . esc_attr( $secondary ) . ';font-size:14px;font-style:italic;font-weight:700;margin-top:18px;text-align:right;">' . ( $footer ?: esc_html( $identity['firma'] ?? '' ) ) . '</div>'
			. '</td></tr></table>'
			. ( $event_url ? '<div style="font-family:Arial,Helvetica,sans-serif;color:#666666;font-size:12px;line-height:1.4;margin-top:12px;text-align:center;">Se il pulsante non funziona, apri: <a href="' . $event_url . '" style="color:' . esc_attr( $secondary ) . ';">' . esc_html( $event_url ) . '</a></div>' : '' )
			. '<div style="font-family:Arial,Helvetica,sans-serif;color:#6B7280;font-size:12px;line-height:1.6;margin-top:16px;text-align:center;">' . esc_html( $identity['nome'] ?? '' ) . ( ! empty( $identity['dettaglio'] ) ? '<br>' . esc_html( $identity['dettaglio'] ) : '' ) . ( ! empty( $identity['contatto'] ) ? '<br><a href="mailto:' . esc_attr( $identity['contatto'] ) . '" style="color:' . esc_attr( $primary ) . ';">' . esc_html( $identity['contatto'] ) . '</a>' : '' ) . '</div>'
			. '</td></tr></table></body></html>';
	}

	private static function componi_html_istituzionale( $snapshot ) {
		$title = sanitize_text_field( (string) ( $snapshot['titolo'] ?? $snapshot['oggetto'] ?? 'Comunicazione di segreteria' ) );
		$event = sanitize_text_field( (string) ( $snapshot['evento']['titolo'] ?? '' ) );
		$body = self::sanitizza_html_email( $snapshot['html'] ?? '' );
		$actions = '';
		foreach ( (array) ( $snapshot['azioni'] ?? array() ) as $action ) {
			$url = esc_url( $action['url'] ?? '' ); $label = sanitize_text_field( $action['label'] ?? '' );
			if ( $url && $label ) $actions .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:14px;"><tr><td bgcolor="#111827" style="border-radius:7px;"><a href="' . $url . '" style="display:inline-block;padding:12px 17px;font-family:Arial,Helvetica,sans-serif;color:#FFFFFF;text-decoration:none;font-size:14px;font-weight:700;">' . esc_html( $label ) . '</a></td></tr></table>';
		}
		$preheader = sanitize_text_field( (string) ( $snapshot['preheader'] ?? '' ) );
		return '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title></head><body style="margin:0;padding:0;background:#F3F4F6;">'
			. ( $preheader ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">' . esc_html( $preheader ) . '</div>' : '' )
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F3F4F6;"><tr><td align="center" style="padding:24px 12px;"><table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;background:#FFFFFF;border:1px solid #D1D5DB;border-radius:10px;overflow:hidden;"><tr><td bgcolor="#E5E7EB" style="padding:22px 24px;border-bottom:1px solid #D1D5DB;"><div style="font-family:Arial,Helvetica,sans-serif;color:#374151;font-size:12px;line-height:1.4;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;">Segreteria parrocchiale · Portale eventi</div><h1 style="font-family:Arial,Helvetica,sans-serif;color:#111827;font-size:23px;line-height:1.3;margin:7px 0 0;font-weight:700;">' . esc_html( $title ) . '</h1>' . ( $event ? '<div style="font-family:Arial,Helvetica,sans-serif;color:#4B5563;font-size:14px;line-height:1.5;margin-top:7px;">Evento: ' . esc_html( $event ) . '</div>' : '' ) . '</td></tr><tr><td style="padding:24px;font-family:Arial,Helvetica,sans-serif;color:#111827;font-size:15px;line-height:1.6;">' . $body . $actions . '</td></tr></table><div style="font-family:Arial,Helvetica,sans-serif;color:#6B7280;font-size:12px;line-height:1.6;margin-top:14px;text-align:center;">Parrocchia di S. Eugenio · Viale delle Belle Arti 10, Roma</div></td></tr></table></body></html>';
	}

	public static function componi_testo( $istantanea ) {
		if ( 'INSTITUTIONAL' === ( $istantanea['layout'] ?? '' ) ) {
			$parts = array( sanitize_text_field( $istantanea['oggetto'] ?? '' ), ! empty( $istantanea['evento']['titolo'] ) ? 'Evento: ' . sanitize_text_field( $istantanea['evento']['titolo'] ) : '', sanitize_textarea_field( $istantanea['testo'] ?? '' ) );
			foreach ( (array) ( $istantanea['azioni'] ?? array() ) as $action ) if ( ! empty( $action['label'] ) && ! empty( $action['url'] ) ) $parts[] = sanitize_text_field( $action['label'] ) . ': ' . esc_url_raw( $action['url'] );
			return implode( "\n\n", array_filter( $parts ) );
		}
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
			! empty( $istantanea['status_url'] ) ? 'Controlla stato e saldo: ' . esc_url_raw( $istantanea['status_url'] ) : '',
			! empty( $istantanea['action_url'] ) ? sanitize_text_field( (string) ( $istantanea['action_label'] ?? 'Apri' ) ) . ': ' . esc_url_raw( $istantanea['action_url'] ) : '',
			! empty( $email_identity['indirizzo_risposte'] ) ? 'Assistenza: ' . sanitize_email( $email_identity['indirizzo_risposte'] ) : 'Assistenza: rispondi a questa email.',
			sanitize_textarea_field( (string) ( $istantanea['footer'] ?? '' ) ),
		) );
		return implode( "\n\n", $parts );
	}

	private static function url_pubblica_evento( $event_id ) {
		static $resolved = array();
		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return '';
		}
		if ( array_key_exists( $event_id, $resolved ) ) return $resolved[ $event_id ];
		$page_id = absint( get_post_meta( $event_id, '_mi_registration_page_id', true ) );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) return $resolved[ $event_id ] = esc_url_raw( get_permalink( $page_id ) );
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
					return $resolved[ $event_id ] = esc_url_raw( get_permalink( $page->ID ) );
				}
			}
		}
		return $resolved[ $event_id ] = '';
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
		$email_style = array();
		if ( ! empty( $_POST['mi_email_event_identity_enabled'] ) ) $email_style = array( 'identity_name' => $_POST['mi_email_event_identity_name'] ?? '', 'identity_detail' => $_POST['mi_email_event_identity_detail'] ?? '', 'contact_email' => $_POST['mi_email_event_contact'] ?? '', 'signature' => $_POST['mi_email_event_signature'] ?? '' );
		if ( ! empty( $_POST['mi_email_event_colors_enabled'] ) ) $email_style = array_merge( $email_style, array( 'primary_color' => $_POST['mi_email_event_primary'] ?? '', 'secondary_color' => $_POST['mi_email_event_secondary'] ?? '' ) );
		if ( ! empty( $_POST['mi_email_event_banner_enabled'] ) ) $email_style['banner_url'] = $_POST['mi_email_event_banner'] ?? '';
		$email_style = self::sanitizza_stile( wp_unslash( $email_style ) );
		if ( $email_style ) update_post_meta( $post_id, '_mi_email_style', $email_style ); else delete_post_meta( $post_id, '_mi_email_style' );
		$settings = self::aggiorna_segnaposto( $settings );
		$unknown = self::trova_segnaposto_non_ammessi( $settings );
		if ( $unknown ) {
			set_transient( 'mi_email_placeholder_error_' . get_current_user_id(), implode( ', ', $unknown ), MINUTE_IN_SECONDS );
			return;
		}
		update_post_meta( $post_id, '_mi_email_template', $settings );
	}

	/** Salva dal wizard soltanto oggetto e testo, conservando le altre impostazioni email. */
	public static function salva_testo_portale( $event_id, $subject, $text ) {
		$settings = self::impostazioni( $event_id );
		$settings['enabled'] = '1';
		$settings['subject'] = self::pulisci_riga( $subject, 180 );
		$settings['text'] = mb_substr( sanitize_textarea_field( wp_unslash( $text ) ), 0, 5000 );
		if ( 'ZERO' === strtoupper( (string) get_post_meta( $event_id, '_mi_pricing_mode', true ) ) ) $settings['text'] = self::rimuovi_riferimenti_pagamento_gratuito( $settings['text'] );
		$settings['html'] = self::sanitizza_html_email( wpautop( esc_html( $settings['text'] ) ) );
		if ( ! $settings['subject'] || ! $settings['text'] ) return new WP_Error( 'mi_email_vuota', 'Oggetto e testo dell’email non possono essere vuoti.' );
		$settings = self::aggiorna_segnaposto( $settings );
		$unknown = self::trova_segnaposto_non_ammessi( $settings );
		if ( $unknown ) return new WP_Error( 'mi_email_segnaposto', 'Elimina i segnaposto non riconosciuti: ' . implode( ', ', $unknown ) . '.' );
		update_post_meta( $event_id, '_mi_email_template', $settings );
		return true;
	}

	/** Nei gratuiti i blocchi economici non devono raggiungere né l’email né la sua anteprima. */
	public static function rimuovi_riferimenti_pagamento_gratuito( $template, $html = false ) {
		$placeholders = array( '{{ordine.riepilogo_economico}}', '{{pagamento.istruzioni}}', '{{pagamento.scadenza}}', '{{pagamento.causale}}' );
		$template = (string) $template;
		if ( $html ) {
			return (string) preg_replace_callback( '#<p\\b[^>]*>.*?</p>#is', static function ( $match ) use ( $placeholders ) {
				foreach ( $placeholders as $placeholder ) if ( false !== strpos( $match[0], $placeholder ) ) return '';
				return $match[0];
			}, $template );
		}
		$lines = preg_split( '/\\r?\\n/', $template );
		$lines = array_filter( $lines, static function ( $line ) use ( $placeholders ) {
			foreach ( $placeholders as $placeholder ) if ( false !== strpos( $line, $placeholder ) ) return false;
			return true;
		} );
		return trim( preg_replace( "/\\n{3,}/", "\\n\\n", implode( "\\n", $lines ) ) );
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
