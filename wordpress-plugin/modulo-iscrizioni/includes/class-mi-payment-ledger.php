<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-mi-payment-people.php';

/** Registro autorevole MySQL. Nessuna richiesta Google nel percorso di lettura o salvataggio. */
final class MI_Payment_Ledger {
	/** Current amounts, distinct from the original installment plan. No database writes. */
	public static function position( array $registration, $net_paid ) {
		$total = max( 0, (int) $registration['total_cents'] );
		$paid = max( 0, (int) $net_paid );
		$managed = in_array( $registration['economic_mode'] ?? 'FULL_PAYMENT', array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true );
		$deposit = 'DEPOSIT_BALANCE' === ( $registration['economic_mode'] ?? '' );
		$initial = $deposit ? min( $total, max( 0, (int) ( $registration['initial_due_cents'] ?? 0 ) ) ) : 0;
		return array( 'total' => $total, 'paid' => $paid, 'balance' => max( 0, $total - $paid ), 'managed' => $managed, 'deposit_plan' => $deposit, 'deposit_due' => $initial, 'deposit_missing' => max( 0, $initial - $paid ), 'deposit_covered' => $deposit && $initial > 0 && $paid >= $initial );
	}
	/** Caller supplies already scoped registrations; bounded bulk reads also preserve individual debts. */
	public static function positions( array $registrations, $id_key = 'id' ) {
		global $wpdb;
		if ( ! $registrations ) return array();
		$ids = array_values( array_filter( array_unique( array_map( 'intval', array_column( $registrations, $id_key ) ) ) ) );
		if ( ! $ids ) return array();
		$id_list = implode( ',', $ids );
		$stored = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE id IN ({$id_list})", ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Prenotazioni non disponibili. Riprova.' );
		$people = $wpdb->get_results( "SELECT id,registration_id,ticket_type_code,first_name,last_name,options_json,status,deposit_due_cents FROM {$wpdb->prefix}mi_participants WHERE registration_id IN ({$id_list}) ORDER BY registration_id,id", ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Partecipanti non disponibili. Riprova.' );
		$items = $wpdb->get_results( "SELECT registration_id,ticket_type_code,unit_price_cents FROM {$wpdb->prefix}mi_registration_items WHERE registration_id IN ({$id_list}) ORDER BY registration_id,id", ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Quote non disponibili. Riprova.' );
		$payments = $wpdb->get_results( "SELECT registration_id,transaction_kind,amount_cents,participant_allocations_json FROM {$wpdb->prefix}mi_payments WHERE registration_id IN ({$id_list}) ORDER BY registration_id,id", ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Saldo non disponibile. Riprova.' );
		$stored_by_id = array_column( $stored, null, 'id' );
		$people_by_id = array(); $items_by_id = array(); $payments_by_id = array(); $paid_by_id = array();
		foreach ( $people as $row ) $people_by_id[(int) $row['registration_id']][] = $row;
		foreach ( $items as $row ) $items_by_id[(int) $row['registration_id']][] = $row;
		foreach ( $payments as $row ) {
			$id = (int) $row['registration_id']; $payments_by_id[$id][] = $row;
			$paid_by_id[$id] = ( $paid_by_id[$id] ?? 0 ) + ( 'REFUND' === $row['transaction_kind'] ? -1 : 1 ) * (int) $row['amount_cents'];
		}
		$result = array();
		foreach ( $registrations as $registration ) {
			$id = (int) $registration[$id_key];
			$authoritative = $stored_by_id[$id] ?? array_replace( $registration, array( 'id' => $id ) );
			$position = self::position( $authoritative, $paid_by_id[$id] ?? 0 );
			$individual = MI_Payment_People::calculate( $authoritative, $people_by_id[$id] ?? array(), $items_by_id[$id] ?? array(), $payments_by_id[$id] ?? array() );
			$summary = MI_Payment_People::summary( $individual );
			$position['individual'] = $individual;
			$position['individual_known'] = $summary['known'];
			foreach ( array( 'people_count', 'active_count', 'total', 'paid', 'balance', 'credit', 'deposit_due', 'deposit_missing' ) as $field ) $position['individual_' . $field] = $summary[$field];
			$position['effective_total'] = $summary['known'] ? $summary['total'] : $position['total'];
			$position['effective_paid'] = $summary['known'] ? $summary['paid'] : $position['paid'];
			$position['effective_balance'] = $summary['known'] ? $summary['balance'] : $position['balance'];
			$result[$id] = $position;
		}
		return $result;
	}
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
		$position = self::position( $r, $paid );
		foreach ( $rows as $index => $movement ) {
			$allocations = json_decode( $movement['participant_allocations_json'] ?? '[]', true ) ?: array();
			$movements[$index]['persone'] = implode( ', ', array_column( $allocations, 'name' ) );
		}
		$individual = MI_Payment_People::read( $r, $rows ); $summary = MI_Payment_People::summary( $individual );
		if ( $summary['known'] ) { foreach ( array( 'total', 'paid', 'balance', 'deposit_due', 'deposit_missing' ) as $field ) $position[$field] = $summary[$field]; $position['deposit_covered'] = $position['deposit_plan'] && $summary['deposit_due'] > 0 && $summary['deposit_missing'] === 0; }
		return array( 'ok' => true, 'data' => wp_date( 'Y-m-d' ), 'saldo' => array( 'codice' => $r['order_code'], 'referente' => trim( $r['buyer_first_name'] . ' ' . $r['buyer_last_name'] ), 'evento' => html_entity_decode( get_the_title( (int) $r['event_id'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'totale' => $position['total'], 'versato' => $position['paid'], 'residuo' => $position['balance'], 'deposit_plan' => $position['deposit_plan'], 'deposit_due' => $position['deposit_due'], 'deposit_missing' => $position['deposit_missing'], 'deposit_covered' => $position['deposit_covered'], 'individual' => $individual, 'movimenti' => $movements ) );
	}
	public static function save( $id, array $input ) {
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( MI_Event_Deletion::registration_event( $id ) ); if ( is_wp_error( $lease ) ) return $lease; }
		global $wpdb;
		if ( ! MI_Portal_Payments::allowed() ) return new WP_Error( 'mi_payment_forbidden', 'Accesso non consentito.' );
		try { $payment = self::normalize( $input ); } catch ( InvalidArgumentException $e ) { return array( 'ok' => true, 'saved' => false, 'message' => $e->getMessage() ); }
		$actor = 'WP#' . get_current_user_id() . ' · ' . wp_get_current_user()->display_name;
		if ( 0 !== strpos( $payment['origin_id'], 'wp_' . get_current_user_id() . '_' ) ) return new WP_Error( 'mi_payment_forbidden', 'Operatore non valido.' );
		$payment['registration_id'] = (int) $id;
		$participant_ids = isset( $input['participant_ids'] ) ? json_decode( (string) $input['participant_ids'], true ) : null;
		if ( null !== $participant_ids && ( ! is_array( $participant_ids ) || count( $participant_ids ) > 100 || array_filter( $participant_ids, static function ( $id ) { return ! is_int( $id ) || $id < 1; } ) ) ) return array( 'ok' => true, 'saved' => false, 'message' => 'Selezione partecipanti non valida.' );
		if ( is_array( $participant_ids ) ) {
			sort( $participant_ids, SORT_NUMERIC );
			if ( 'PAYMENT' === $payment['transaction_kind'] ) $payment['installment_kind'] = (string) ( $input['rata'] ?? '' );
		}
		$payment['request_hash'] = hash( 'sha256', wp_json_encode( null === $participant_ids ? $payment : array( $payment, $participant_ids ) ) );
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
			$history = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_payments WHERE registration_id=%d ORDER BY id", $id ), ARRAY_A );
			if ( $wpdb->last_error ) throw new RuntimeException( 'Storico non disponibile.' );
			$individual = MI_Payment_People::read( $r, $history );
			if ( $incoming ) {
				if ( ! is_array( $participant_ids ) ) throw new InvalidArgumentException( 'Riapri Pagamenti e seleziona le persone per cui registrare il versamento.' );
				$payment['participant_allocations_json'] = wp_json_encode( MI_Payment_People::plan( $individual, $participant_ids, $payment['installment_kind'], $amount ) );
			} elseif ( ! empty( $individual['requires_refund_allocation'] ) || is_array( $participant_ids ) ) {
				if ( ! is_array( $participant_ids ) ) throw new InvalidArgumentException( 'Seleziona la persona a cui attribuire il rimborso o storno.' );
				$payment['participant_allocations_json'] = wp_json_encode( MI_Payment_People::refund_plan( $individual, $participant_ids, $amount ) );
			}
			if ( $incoming && ! in_array( $r['status'], array( 'PENDING_PAYMENT', 'CONFIRMED' ), true ) ) throw new InvalidArgumentException( 'Questa prenotazione non può ricevere incassi.' );
			if ( $incoming && (int) $r['total_cents'] < 1 ) throw new InvalidArgumentException( 'L’evento non prevede pagamenti.' );
			// plan() ha già verificato l'importo sulle sole persone selezionate.
			if ( ! $incoming && $amount > max( 0, $paid ) ) throw new InvalidArgumentException( 'Il rimborso o storno supera quanto versato.' );
			$payment['created_at'] = current_time( 'mysql', true );
			if ( false === $wpdb->insert( $wpdb->prefix . 'mi_payments', $payment ) ) throw new RuntimeException( 'Movimento non salvato.' );
			$payment_id = (int) $wpdb->insert_id; $paid += $incoming ? $amount : -$amount;
			$status = $r['status']; $changes = array( 'workspace_status' => 'PENDING', 'workspace_attempts' => 0, 'workspace_last_error' => 'payment_changed', 'workspace_revision' => (int) $r['workspace_revision'] + 1 );
			if ( in_array( $status, array( 'PENDING_PAYMENT', 'CONFIRMED' ), true ) ) {
				$covered = $paid >= (int) $r['initial_due_cents'];
				$updated_history = $history; $updated_history[] = $payment;
				$updated_individual = MI_Payment_People::read( $r, $updated_history );
				$individual_covered = MI_Payment_People::covered( $updated_individual, $r['economic_mode'] );
				if ( null !== $individual_covered ) $covered = $individual_covered;
				$status = $covered ? 'CONFIRMED' : 'PENDING_PAYMENT';
				$deadline = 'CONFIRMED' === $status ? null : MI_Registration_Service::reopened_payment_deadline( $r );
				$changes['status'] = $status; $changes['expires_at'] = $deadline;
				if ( null !== $deadline ) $changes['payment_deadline_at'] = $deadline;
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
