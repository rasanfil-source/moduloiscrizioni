<?php
defined( 'ABSPATH' ) || exit;

/** Registro autorevole MySQL. Nessuna richiesta Google nel percorso di lettura o salvataggio. */
final class MI_Payment_Ledger {
	public static function normalize( array $input ) {
		$amount = trim( (string) ( $input['importo'] ?? '' ) );
		if ( ! preg_match( '/^([0-9]{1,8})(?:[.,]([0-9]{1,2}))?$/D', $amount, $matches ) ) throw new InvalidArgumentException( 'Importo non valido.' );
		$cents = (int) $matches[1] * 100 + (int) str_pad( $matches[2] ?? '', 2, '0' );
		if ( $cents < 1 || $cents > 4294967295 ) throw new InvalidArgumentException( 'Importo fuori dai limiti consentiti.' );
		$kind = (string) ( $input['tipo'] ?? '' );
		$source = array( 'BONIFICO' => 'BANK_TRANSFER', 'CARTA' => 'CARD', 'CONTANTE' => 'CASH' )[ $input['metodo'] ?? '' ] ?? '';
		if ( ! in_array( $kind, array( 'INCASSO', 'RIMBORSO', 'STORNO' ), true ) || ! $source ) throw new InvalidArgumentException( 'Tipo o metodo non valido.' );
		$raw_date = (string) ( $input['data'] ?? '' );
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $raw_date, wp_timezone() );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $raw_date || $date->format( 'Y' ) < '1000' ) throw new InvalidArgumentException( 'Data effettiva non valida.' );
		$id = (string) ( $input['request_id'] ?? '' );
		if ( ! preg_match( '/^wp_[1-9][0-9]*_[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD', $id ) ) throw new InvalidArgumentException( 'Identificativo del movimento non valido.' );
		$reference = mb_substr( sanitize_text_field( $input['riferimento'] ?? '' ), 0, 120 );
		$note = mb_substr( sanitize_textarea_field( $input['nota'] ?? '' ), 0, 500 );
		if ( self::contains_card( $reference ) || self::contains_card( $note ) ) throw new InvalidArgumentException( 'Non inserire numeri completi di carta.' );
		return array( 'transaction_kind' => 'INCASSO' === $kind ? 'PAYMENT' : 'REFUND', 'movement_kind' => $kind, 'installment_kind' => 'OTHER', 'effective_at' => $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), 'amount_cents' => $cents, 'payment_source' => $source, 'external_reference' => $reference, 'administrative_note' => $note, 'origin_channel' => 'WORDPRESS', 'origin_id' => strtolower( $id ) );
	}
	private static function contains_card( $value ) {
		preg_match_all( '/(?:\d[ -]?){13,19}/', $value, $matches );
		foreach ( $matches[0] as $candidate ) {
			$digits = preg_replace( '/\D/', '', $candidate ); $sum = 0; $double = false;
			for ( $i = strlen( $digits ) - 1; $i >= 0; $i-- ) { $n = (int) $digits[$i]; if ( $double ) { $n *= 2; if ( $n > 9 ) $n -= 9; } $sum += $n; $double = ! $double; }
			if ( $sum > 0 && 0 === $sum % 10 ) return true;
		}
		return false;
	}
	public static function net_paid( $id ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN transaction_kind='REFUND' THEN -amount_cents ELSE amount_cents END),0) FROM {$wpdb->prefix}mi_payments WHERE registration_id=%d", $id ) );
		if ( $wpdb->last_error || null === $value ) throw new RuntimeException( 'Saldo non disponibile.' );
		return (int) $value;
	}
	public static function detail( $id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $id ), ARRAY_A );
		if ( ! $r || ! MI_Access::can_access_event( (int) $r['event_id'] ) ) return new WP_Error( 'mi_payment_forbidden', 'Prenotazione non accessibile.' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_payments WHERE registration_id=%d ORDER BY effective_at,id", $id ), ARRAY_A );
		if ( $wpdb->last_error ) return new WP_Error( 'mi_payment_read', 'Movimenti non disponibili. Riprova.' );
		$paid = 0; $movements = array();
		foreach ( $rows as $p ) {
			$amount = ( 'REFUND' === $p['transaction_kind'] ? -1 : 1 ) * (int) $p['amount_cents']; $paid += $amount;
			$movements[] = array( 'id' => 'mysql_' . $p['id'], 'data' => gmdate( 'c', strtotime( $p['effective_at'] . ' UTC' ) ), 'tipo' => $p['movement_kind'] ?: ( $amount < 0 ? 'RIMBORSO' : 'INCASSO' ), 'importo' => $amount, 'metodo' => array( 'BANK_TRANSFER' => 'BONIFICO', 'CARD' => 'CARTA', 'CASH' => 'CONTANTE' )[ $p['payment_source'] ] ?? $p['payment_source'], 'riferimento' => $p['external_reference'], 'operatore' => $p['operator_label'], 'nota' => $p['administrative_note'] );
		}
		return array( 'ok' => true, 'data' => wp_date( 'Y-m-d' ), 'saldo' => array( 'codice' => $r['order_code'], 'referente' => trim( $r['buyer_first_name'] . ' ' . $r['buyer_last_name'] ), 'evento' => get_the_title( (int) $r['event_id'] ), 'totale' => (int) $r['total_cents'], 'versato' => max( 0, $paid ), 'residuo' => max( 0, (int) $r['total_cents'] - $paid ), 'movimenti' => $movements ) );
	}
	public static function save( $id, array $input ) {
		global $wpdb;
		if ( ! MI_Portal_Payments::allowed() ) return new WP_Error( 'mi_payment_forbidden', 'Accesso non consentito.' );
		try { $payment = self::normalize( $input ); } catch ( InvalidArgumentException $e ) { return array( 'ok' => true, 'saved' => false, 'message' => $e->getMessage() ); }
		$actor = 'WP#' . get_current_user_id() . ' · ' . wp_get_current_user()->display_name;
		if ( 0 !== strpos( $payment['origin_id'], 'wp_' . get_current_user_id() . '_' ) ) return new WP_Error( 'mi_payment_forbidden', 'Operatore non valido.' );
		$payment['registration_id'] = (int) $id;
		$payment['request_hash'] = hash( 'sha256', wp_json_encode( $payment ) );
		$payment['operator_label'] = mb_substr( $actor, 0, 120 );
		$table = $wpdb->prefix . 'mi_registrations';
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) return new WP_Error( 'mi_payment_database', 'Salvataggio non disponibile.' );
		try {
			$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d FOR UPDATE", $id ), ARRAY_A );
			if ( $wpdb->last_error ) throw new RuntimeException( 'Lettura non riuscita.' );
			if ( ! $r || ! MI_Access::can_access_event( (int) $r['event_id'] ) ) throw new InvalidArgumentException( 'Prenotazione non accessibile.' );
			$old = $wpdb->get_row( $wpdb->prepare( "SELECT id,request_hash FROM {$wpdb->prefix}mi_payments WHERE origin_channel='WORDPRESS' AND origin_id=%s", $payment['origin_id'] ), ARRAY_A );
			if ( $wpdb->last_error ) throw new RuntimeException( 'Verifica richiesta non riuscita.' );
			if ( $old ) {
				if ( ! hash_equals( (string) $old['request_hash'], $payment['request_hash'] ) ) throw new InvalidArgumentException( 'Identificativo già usato per un movimento diverso.' );
				$wpdb->query( 'ROLLBACK' );
				return array( 'ok' => true, 'saved' => true, 'replayed' => true, 'message' => 'Movimento già registrato.', 'payment_id' => (int) $old['id'] );
			}
			$paid = self::net_paid( $id ); $amount = $payment['amount_cents']; $incoming = 'PAYMENT' === $payment['transaction_kind'];
			if ( $incoming && ! in_array( $r['status'], array( 'PENDING_PAYMENT', 'CONFIRMED' ), true ) ) throw new InvalidArgumentException( 'Questa prenotazione non può ricevere incassi.' );
			if ( $incoming && (int) $r['total_cents'] < 1 ) throw new InvalidArgumentException( 'L’evento non prevede pagamenti.' );
			if ( $incoming && $amount > max( 0, (int) $r['total_cents'] - $paid ) ) throw new InvalidArgumentException( 'L’importo supera il saldo residuo.' );
			if ( ! $incoming && $amount > max( 0, $paid ) ) throw new InvalidArgumentException( 'Il rimborso o storno supera quanto versato.' );
			$payment['created_at'] = current_time( 'mysql', true );
			if ( false === $wpdb->insert( $wpdb->prefix . 'mi_payments', $payment ) ) throw new RuntimeException( 'Movimento non salvato.' );
			$payment_id = (int) $wpdb->insert_id; $paid += $incoming ? $amount : -$amount;
			$status = $r['status']; $changes = array( 'workspace_status' => 'PENDING', 'workspace_attempts' => 0, 'workspace_last_error' => 'payment_changed', 'workspace_revision' => (int) $r['workspace_revision'] + 1 );
			if ( in_array( $status, array( 'PENDING_PAYMENT', 'CONFIRMED' ), true ) ) {
				$status = $paid >= (int) $r['initial_due_cents'] ? 'CONFIRMED' : 'PENDING_PAYMENT';
				$changes['status'] = $status; $changes['expires_at'] = 'CONFIRMED' === $status ? null : $r['payment_deadline_at'];
			}
			if ( false === $wpdb->update( $table, $changes, array( 'id' => $id ) ) || ! MI_Registration_Service::append_registration_event( $id, 'PAYMENT_RECORDED', $r['status'], $status, $actor, array( 'payment_id' => $payment_id, 'net_paid_cents' => $paid ) ) ) throw new RuntimeException( 'Registrazione incompleta.' );
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma non ricevuta.' );
		} catch ( InvalidArgumentException $e ) {
			$wpdb->query( 'ROLLBACK' ); return array( 'ok' => true, 'saved' => false, 'message' => $e->getMessage() );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); return new WP_Error( 'mi_payment_uncertain', 'Salvataggio non confermato. Riprova con lo stesso identificativo.' );
		}
		// La coda persistente è già stata salvata nella transazione: un errore del cron non annulla l'incasso.
		try { MI_Registration_Service::accoda_iscrizione_workspace( $id ); } catch ( Throwable $e ) {}
		return array( 'ok' => true, 'saved' => true, 'payment_id' => $payment_id, 'message' => 'Movimento registrato. Il foglio Google si aggiornerà in differita.' );
	}
}
