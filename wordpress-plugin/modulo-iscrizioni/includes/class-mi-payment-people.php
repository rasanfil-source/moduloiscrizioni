<?php
defined( 'ABSPATH' ) || exit;

/** Quote individuali e attribuzioni: nessuna ripartizione implicita dei versamenti storici. */
final class MI_Payment_People {
	public static function read( array $registration, array $payments ) {
		global $wpdb;
		$id = (int) $registration['id'];
		$people = $wpdb->get_results( $wpdb->prepare( "SELECT id,ticket_type_code,first_name,last_name,options_json,status,deposit_due_cents FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d ORDER BY id", $id ), ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Partecipanti non disponibili.' );
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT ticket_type_code,unit_price_cents FROM {$wpdb->prefix}mi_registration_items WHERE registration_id=%d ORDER BY id", $id ), ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Quote non disponibili.' );
		return self::calculate( $registration, $people ?: array(), $items ?: array(), $payments );
	}
	public static function calculate( array $registration, array $people, array $items, array $payments ) {
		$prices = array_column( $items, 'unit_price_cents', 'ticket_type_code' );
		$snapshot = json_decode( $registration['snapshot_json'] ?? '{}', true ) ?: array();
		$deposit_plan = 'DEPOSIT_BALANCE' === ( $registration['economic_mode'] ?? '' );
		$rows = array(); $sum = 0; $issue = '';
		foreach ( $people as $person ) {
			$total = (int) ( $prices[$person['ticket_type_code']] ?? 0 );
			if ( ! array_key_exists( $person['ticket_type_code'], $prices ) ) $issue = 'La quota individuale non è disponibile. Verifica le quote della prenotazione.';
			foreach ( json_decode( $person['options_json'] ?? '[]', true ) ?: array() as $option ) $total += (int) ( $option['quantity'] ?? 0 ) * (int) ( $option['unit_price_cents'] ?? 0 );
			$sum += $total;
			$rows[(int) $person['id']] = array( 'id' => (int) $person['id'], 'name' => trim( $person['first_name'] . ' ' . $person['last_name'] ), 'total' => $total, 'deposit' => 0, 'paid' => 0, 'active' => 'ACTIVE' === $person['status'] );
		}
		if ( ! $rows ) $issue = 'Partecipanti non disponibili.';
		if ( $sum !== (int) $registration['total_cents'] ) $issue = 'Il totale comprende quote comuni o rettifiche non attribuite alle persone. Verifica le quote individuali prima di registrare il pagamento.';
		$quotes_known = '' === $issue;
		// La stessa formula della creazione: quota fissa per persona oppure percentuale.
		$deposit_total = min( $sum, max( 0, (int) ( $registration['initial_due_cents'] ?? 0 ) ) );
		$event = $snapshot['event'] ?? array(); $remainders = array(); $assigned = 0;
		$deposits = array_column( $people, 'deposit_due_cents', 'id' );
		if ( $deposit_plan && $sum > 0 ) {
			foreach ( $rows as $id => &$row ) {
				if ( isset( $deposits[$id] ) ) {
					$row['deposit'] = min( $row['total'], (int) $deposits[$id] );
				} elseif ( 'FIXED' === ( $event['deposit_mode'] ?? '' ) ) {
					$row['deposit'] = min( $row['total'], (int) ( $event['deposit_fixed_cents'] ?? 0 ) );
				} else {
					$product = $row['total'] * $deposit_total;
					$row['deposit'] = intdiv( $product, $sum ); $remainders[$id] = $product % $sum;
				}
				$assigned += $row['deposit'];
			} unset( $row );
			if ( $remainders ) { arsort( $remainders, SORT_NUMERIC ); foreach ( $remainders as $id => $remainder ) { if ( $assigned >= $deposit_total ) break; $rows[$id]['deposit']++; $assigned++; } }
			if ( $assigned !== $deposit_total ) $issue = 'La caparra complessiva non coincide con le quote individuali. Verifica la prenotazione.';
		}
		$deposits = array_column( $people, 'deposit_due_cents', 'id' );
		foreach ( $rows as $id => &$row ) if ( isset( $deposits[$id] ) ) $row['deposit'] = min( $row['total'], (int) $deposits[$id] );
		unset( $row );
		$payments_known = true;
		$has_allocated_payments = false;
		$unassigned = 0;
		foreach ( $payments as $payment ) {
			$sign = 'REFUND' === $payment['transaction_kind'] ? -1 : 1;
			$allocations = json_decode( $payment['participant_allocations_json'] ?? 'null', true );
			if ( ! is_array( $allocations ) || ! $allocations ) { $unassigned += $sign * (int) $payment['amount_cents']; continue; }
			$has_allocated_payments = true;
			$allocated = 0;
			foreach ( $allocations as $allocation ) {
				$id = (int) $allocation['participant_id']; $amount = (int) $allocation['amount_cents'];
				if ( ! isset( $rows[$id] ) || $amount < 1 ) { $payments_known = false; $issue = 'Attribuzioni dei versamenti da verificare.'; continue; }
				$rows[$id]['paid'] += $sign * $amount; $allocated += $amount;
			}
			if ( $allocated !== (int) $payment['amount_cents'] ) { $payments_known = false; $issue = 'Attribuzioni dei versamenti da verificare.'; }
		}
		if ( $unassigned !== 0 ) {
			if ( count( $rows ) === 1 ) { $id = array_key_first( $rows ); $rows[$id]['paid'] += $unassigned; }
			else $issue = 'Sono presenti versamenti precedenti senza attribuzione individuale. Occorre verificare a chi appartengono prima di usare la selezione delle persone.';
		}
		foreach ( $rows as &$row ) {
			$row['balance'] = max( 0, $row['total'] - $row['paid'] );
			$row['deposit_missing'] = max( 0, $row['deposit'] - $row['paid'] );
			$row['saldo'] = $row['total'] - $row['deposit'];
			$row['credit'] = max( 0, $row['paid'] - $row['total'] );
			if ( $row['paid'] < 0 ) $issue = 'La posizione individuale richiede una verifica prima di un nuovo versamento.';
		} unset( $row );
		return array( 'ready' => '' === $issue, 'quotes_known' => $quotes_known, 'payments_known' => $payments_known && ( ! $unassigned || count( $rows ) === 1 ), 'requires_refund_allocation' => $has_allocated_payments, 'message' => $issue, 'deposit_plan' => $deposit_plan, 'people' => array_values( $rows ) );
	}
	public static function plan( array $position, array $ids, $installment, $amount ) {
		if ( ! $position['ready'] ) throw new InvalidArgumentException( $position['message'] );
		if ( ! $ids || count( $ids ) !== count( array_unique( $ids ) ) ) throw new InvalidArgumentException( 'Seleziona le persone per cui registrare il pagamento.' );
		if ( ! in_array( $installment, array( 'DEPOSIT', 'BALANCE', 'FULL' ), true ) || ( ! $position['deposit_plan'] && 'FULL' !== $installment ) ) throw new InvalidArgumentException( 'Tipo di versamento non valido.' );
		$people = array_column( $position['people'], null, 'id' ); $plan = array(); $total = 0;
		sort( $ids, SORT_NUMERIC );
		foreach ( $ids as $id ) {
			$person = $people[$id] ?? null;
			if ( ! $person || ! $person['active'] ) throw new InvalidArgumentException( 'Partecipante non disponibile per questo versamento.' );
			if ( 'BALANCE' === $installment && $person['deposit_missing'] > 0 ) throw new InvalidArgumentException( 'Prima del saldo deve essere versata la caparra per tutte le persone selezionate.' );
			$due = 'DEPOSIT' === $installment ? $person['deposit_missing'] : $person['balance'];
			if ( $due < 1 ) throw new InvalidArgumentException( 'Una delle persone selezionate ha già versato questa quota. Aggiorna la selezione.' );
			$plan[] = array( 'participant_id' => (int) $id, 'name' => $person['name'], 'amount_cents' => $due ); $total += $due;
		}
		if ( $total !== $amount ) throw new InvalidArgumentException( 'L’importo deve corrispondere esattamente alle quote delle persone selezionate. Ricarica la prenotazione se è stata aggiornata.' );
		return $plan;
	}
	public static function refund_plan( array $position, array $ids, $amount ) {
		if ( empty( $position['payments_known'] ) ) throw new InvalidArgumentException( $position['message'] ?: 'I versamenti precedenti devono essere attribuiti prima di registrare il rimborso.' );
		if ( 1 !== count( $ids ) || 1 !== count( array_unique( $ids ) ) ) throw new InvalidArgumentException( 'Seleziona una sola persona a cui attribuire il rimborso o storno.' );
		$id = (int) reset( $ids );
		$person = array_column( $position['people'], null, 'id' )[$id] ?? null;
		if ( ! $person ) throw new InvalidArgumentException( 'Persona non disponibile per questo rimborso.' );
		if ( $amount < 1 || $amount > max( 0, (int) $person['paid'] ) ) throw new InvalidArgumentException( 'Il rimborso o storno supera quanto versato dalla persona selezionata.' );
		return array( array( 'participant_id' => $id, 'name' => $person['name'], 'amount_cents' => (int) $amount ) );
	}
}
