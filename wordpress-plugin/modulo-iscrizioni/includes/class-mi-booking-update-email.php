<?php
defined( 'ABSPATH' ) || exit;

/** Transactional notification outbox for staff changes; never records a payment. */
final class MI_Booking_Update_Email {
	public static function snapshot( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $id ), ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Prenotazione non disponibile per la comunicazione.' );
		if ( ! $r || ! in_array( $r['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) || ! is_email( $r['buyer_email'] ) ) return null;
		$payments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_payments WHERE registration_id=%d ORDER BY id", $id ), ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Pagamenti non disponibili per la comunicazione.' );
		$position = MI_Payment_People::read( $r, $payments );
		$people = $wpdb->get_results( $wpdb->prepare( "SELECT id,first_name,last_name,options_json,room_code,extra_json,status FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d ORDER BY id", $id ), ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Partecipanti non disponibili per la comunicazione.' );
		$title = get_the_title( $r['event_id'] );
		$lines = array( 'La segreteria ha aggiornato la tua prenotazione per ' . $title . '.', 'Di seguito la situazione attuale.' );
		$individual = array_column( $position['people'], null, 'id' );
		$managed = in_array( $r['economic_mode'], array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true );
		$money = static function ( $cents ) { return number_format( $cents / 100, 2, ',', '.' ) . ' EUR'; };
		foreach ( $people as $person ) {
			if ( 'ACTIVE' !== $person['status'] ) continue;
			$lines[] = ''; $lines[] = trim( $person['first_name'] . ' ' . $person['last_name'] );
			$options = json_decode( $person['options_json'] ?: '[]', true ) ?: array();
			foreach ( $options as $option ) if ( ! empty( $option['quantity'] ) ) $lines[] = '- ' . $option['name'] . ( (int) $option['quantity'] > 1 ? ' × ' . (int) $option['quantity'] : '' );
			if ( $person['room_code'] ) $lines[] = 'Camera: ' . $person['room_code'];
			$fields = json_decode( $person['extra_json'] ?: '{}', true ) ?: array();
			if ( ! empty( $fields['pullman'] ) ) $lines[] = 'Pullman assegnato: ' . $fields['pullman'];
			$p = $individual[$person['id']] ?? null;
			if ( $managed && $p && $position['quotes_known'] && $position['payments_known'] ) {
				$lines[] = 'Quota: ' . $money( $p['total'] ) . ' — Versato netto: ' . $money( $p['paid'] );
				$lines[] = $p['paid'] > $p['total'] ? 'Credito da restituire: ' . $money( $p['paid'] - $p['total'] ) : 'Residuo da versare: ' . $money( max( 0, $p['total'] - $p['paid'] ) );
			}
		}
		$common = json_decode( $r['order_options_json'] ?? '[]', true ) ?: array();
		if ( $common ) {
			$lines[] = ''; $lines[] = 'Servizi comuni della prenotazione:';
			foreach ( $common as $option ) if ( ! empty( $option['quantity'] ) ) $lines[] = '- ' . ( $option['name'] ?? $option['code'] ) . ' × ' . (int) $option['quantity'];
		}
		if ( $managed ) {
			$lines[] = ''; $lines[] = 'Quota complessiva della prenotazione: ' . $money( (int) $r['total_cents'] );
			if ( ! $position['quotes_known'] || ! $position['payments_known'] ) $lines[] = 'La situazione individuale dei versamenti è in verifica presso la segreteria.';
		}
		$lines[] = ''; $lines[] = 'Per chiarimenti sui servizi o sui versamenti contatta la segreteria.';
		$snapshot = MI_Modello_Email::crea_istantanea( (int) $r['event_id'], array() );
		$snapshot['oggetto'] = 'Prenotazione aggiornata — ' . $title;
		$snapshot['titolo'] = 'Prenotazione aggiornata';
		$snapshot['preheader'] = 'Servizi, assegnazioni e situazione aggiornata.';
		$snapshot['testo'] = implode( "\n", $lines );
		$snapshot['html'] = nl2br( esc_html( $snapshot['testo'] ) );
		$snapshot['identificativo'] = array( 'modalita' => 'NONE', 'codice' => '', 'payload_qr' => '' );
		$snapshot['gestione_partecipanti'] = array();
		$snapshot['status_url'] = $managed ? MI_Portal::balance_url( $id, $r['order_code'], $r['buyer_email'] ) : '';
		return array( 'event_id' => (int) $r['event_id'], 'recipient' => $r['buyer_email'], 'email_preview' => $snapshot );
	}
	/** Must be called inside the same transaction as the audited change. */
	public static function enqueue( $id, $event_type, $audit_id ) {
		if ( ! in_array( strtolower( $event_type ), array( 'management_change_options', 'management_adjust_due', 'change_accommodation', 'room_assign', 'room_swap' ), true ) ) return;
		global $wpdb;
		$payload = self::snapshot( $id ); if ( ! $payload ) return;
		$table = $wpdb->prefix . 'mi_email_outbox';
		if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='SUPERSEDED' WHERE registration_id=%d AND template_type='BOOKING_UPDATED' AND status IN ('PREVIEW','PENDING','TEST_PENDING','FAILED','TEST_FAILED')", $id ) ) ) throw new RuntimeException( 'Coda comunicazioni non aggiornata.' );
		if ( false === $wpdb->insert( $table, array( 'registration_id' => $id, 'recipient' => $payload['recipient'], 'template_type' => 'BOOKING_UPDATED', 'origin_key' => hash( 'sha256', 'booking-update|' . $audit_id ), 'payload_json' => wp_json_encode( $payload ), 'status' => MI_Spedizione_Email::stato_nuova_email( $payload['email_preview'] ), 'created_at' => current_time( 'mysql', true ) ) ) ) throw new RuntimeException( 'Comunicazione non accodata.' );
	}
	public static function refresh( $row ) {
		global $wpdb;
		$newer = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}mi_email_outbox WHERE registration_id=%d AND template_type='BOOKING_UPDATED' AND id>%d ORDER BY id DESC LIMIT 1", $row['registration_id'], $row['id'] ) );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Coda comunicazioni non disponibile.' );
		$payload = $newer ? null : self::snapshot( (int) $row['registration_id'] );
		if ( ! $payload ) {
			if ( false === $wpdb->update( $wpdb->prefix . 'mi_email_outbox', array( 'status' => 'SUPERSEDED', 'processing_started_at' => null ), array( 'id' => $row['id'] ) ) ) throw new RuntimeException( 'Comunicazione superata non archiviata.' );
			return null;
		}
		if ( false === $wpdb->update( $wpdb->prefix . 'mi_email_outbox', array( 'recipient' => $payload['recipient'], 'payload_json' => wp_json_encode( $payload ) ), array( 'id' => $row['id'] ) ) ) throw new RuntimeException( 'Comunicazione non aggiornata.' );
		return $payload;
	}
}
