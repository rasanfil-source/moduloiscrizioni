<?php
defined( 'ABSPATH' ) || exit;

/** Public participant workflow adapted from the supplied Cammino balance model. */
final class MI_Public_Balance {
	private static function decode( $json ) { return (array) json_decode( (string) $json, true ); }
	private static function check() { global $wpdb; if ( $wpdb->last_error ) throw new RuntimeException( 'Servizio momentaneamente non disponibile. Riprova.' ); }
	public static function normalize( $text ) {
		$text = strtolower( remove_accents( sanitize_text_field( (string) $text ) ) );
		$text = str_replace( array( "'", '’', '‘', 'ʼ', '`' ), '', $text );
		$text = str_replace( array( '-', '–', '—', "\xc2\xa0" ), ' ', $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}
	private static function token( $event, $id ) { return hash_hmac( 'sha256', 'balance-person|' . $event . '|' . $id, wp_salt( 'auth' ) ); }
	private static function event( $event ) {
		if ( ! $event || 'publish' !== get_post_status( $event ) || MI_Event_Post_Type::EVENT_TYPE !== get_post_type( $event ) || get_post_meta( $event, '_mi_event_cancelled_at', true ) ) throw new InvalidArgumentException( 'Evento non disponibile.' );
	}
	public static function payment_config( $event ) {
		$defaults = (array) get_option( 'mi_public_balance_payment', array() );
		$config_file = MI_PLUGIN_DIR . 'public-balance-config.json';
		if ( ! $defaults && is_readable( $config_file ) ) { $defaults = self::decode( file_get_contents( $config_file ) ); update_option( 'mi_public_balance_payment', $defaults, false ); }
		return array(
			'iban' => (string) ( get_post_meta( $event, '_mi_balance_iban', true ) ?: ( $defaults['iban'] ?? '' ) ),
			'holder' => (string) ( get_post_meta( $event, '_mi_balance_holder', true ) ?: ( $defaults['holder'] ?? '' ) ),
			'cardUrl' => esc_url_raw( get_post_meta( $event, '_mi_balance_card_url', true ) ?: ( $defaults['cardUrl'] ?? '' ), array( 'https' ) ),
			'contact' => sanitize_email( get_post_meta( $event, '_mi_balance_contact', true ) ?: ( $defaults['contact'] ?? get_option( 'admin_email' ) ) ),
			'methods' => (array) get_post_meta( $event, '_mi_payment_methods', true ),
		);
	}
	public static function route() {
		if ( empty( $_GET['mi_public_balance'] ) ) return;
		nocache_headers();
		try {
			$event = absint( $_GET['mi_public_balance'] ); self::event( $event );
			if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) throw new InvalidArgumentException( 'Richiesta non valida.' );
			$raw = file_get_contents( 'php://input', false, null, 0, 65537 );
			if ( strlen( $raw ) > 65536 ) throw new InvalidArgumentException( 'Richiesta troppo grande.' );
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) || ! wp_verify_nonce( $data['nonce'] ?? '', 'mi_public_balance_' . $event ) ) throw new InvalidArgumentException( 'La pagina è scaduta. Ricaricala e riprova.' );
			$key = 'mi_balance_rate_' . hash_hmac( 'sha256', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), wp_salt( 'nonce' ) );
			$count = (int) get_transient( $key );
			if ( $count > 120 ) throw new InvalidArgumentException( 'Troppe richieste ravvicinate. Attendi qualche minuto.' );
			set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );
			$action = $data['action'] ?? '';
			if ( '__ping__' === $action ) $result = array( 'success' => true );
			elseif ( in_array( $action, array( 'lookupByCognome', 'lookupPersona' ), true ) ) $result = self::lookup( $event, $data );
			elseif ( in_array( $action, array( 'preview', 'salvaTransfer' ), true ) ) $result = self::save( $event, $data, 'preview' === $action );
			else throw new InvalidArgumentException( 'Operazione non disponibile.' );
			wp_send_json( $result );
		} catch ( Throwable $error ) { wp_send_json( array( 'success' => false, 'error' => $error->getMessage(), 'message' => $error->getMessage() ) ); }
	}
	public static function lookup( $event, $data ) {
		global $wpdb;
		self::event( $event );
		$surname = self::normalize( $data['cognome'] ?? '' );
		$name = self::normalize( $data['nome'] ?? '' );
		if ( ! $surname ) throw new InvalidArgumentException( 'Inserisci il cognome.' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.id,p.first_name,p.last_name,p.room_code FROM {$wpdb->prefix}mi_participants p JOIN {$wpdb->prefix}mi_registrations r ON r.id=p.registration_id WHERE r.event_id=%d AND p.status='ACTIVE' AND r.status IN ('CONFIRMED','PENDING_PAYMENT') ORDER BY p.last_name,p.first_name,p.id", $event ), ARRAY_A ); self::check();
		$matches = array_values( array_filter( $rows, static function ( $row ) use ( $surname ) { return self::normalize( $row['last_name'] ) === $surname; } ) );
		if ( 'lookupPersona' === ( $data['action'] ?? '' ) ) $matches = array_values( array_filter( $matches, static function ( $row ) use ( $name, $data ) { return self::normalize( $row['first_name'] ) === $name && ( empty( $data['candidate'] ) || (int) $row['id'] === (int) $data['candidate'] ); } ) );
		if ( ! $matches ) return array( 'success' => false, 'error' => 'not_found' );
		if ( count( $matches ) > 1 ) return array( 'success' => false, 'error' => 'duplicate', 'candidates' => array_map( static function ( $row ) { return array( 'nome' => $row['first_name'], 'cognome' => $row['last_name'], 'row' => (int) $row['id'], 'room' => $row['room_code'] ); }, $matches ) );
		$result = self::person( $event, (int) $matches[0]['id'] );
		// Only an explicitly shared matrimonial room implies a joint choice, as in the model.
		$result['partner'] = null;
		if ( preg_match( '/^DM[1-9][0-9]*$/', (string) $matches[0]['room_code'] ) ) foreach ( $rows as $row ) {
			if ( $row['id'] !== $matches[0]['id'] && $row['room_code'] === $matches[0]['room_code'] ) { $result['partner'] = self::person( $event, (int) $row['id'] ); break; }
		}
		return $result;
	}
	private static function bundle( $event, $id, $lock = false ) {
		global $wpdb;
		$reg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE event_id=%d AND id=(SELECT registration_id FROM {$wpdb->prefix}mi_participants WHERE id=%d)" . ( $lock ? ' FOR UPDATE' : '' ), $event, $id ), ARRAY_A ); self::check();
		if ( ! $reg || ! in_array( $reg['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ) throw new InvalidArgumentException( 'Iscrizione non disponibile. Ricarica la pagina.' );
		$people = $wpdb->get_results( $wpdb->prepare( "SELECT id,first_name,last_name,status,room_code,options_json,extra_json FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d ORDER BY id" . ( $lock ? ' FOR UPDATE' : '' ), $reg['id'] ), ARRAY_A ); self::check();
		$payments = $wpdb->get_results( $wpdb->prepare( "SELECT id,amount_cents,transaction_kind FROM {$wpdb->prefix}mi_payments WHERE registration_id=%d ORDER BY id" . ( $lock ? ' FOR UPDATE' : '' ), $reg['id'] ), ARRAY_A ); self::check();
		$paid = 0; foreach ( $payments as $payment ) $paid += ( 'REFUND' === $payment['transaction_kind'] ? -1 : 1 ) * (int) $payment['amount_cents'];
		$version = hash( 'sha256', wp_json_encode( array( $reg['status'], $reg['total_cents'], $reg['initial_due_cents'], $people, $payments ) ) );
		return array( 'registration' => $reg, 'people' => $people, 'paid' => $paid, 'version' => $version );
	}
	private static function split( $amount, $count, $index ) {
		$base = intdiv( $amount, $count ); $remainder = $amount % $count;
		return $base + ( $index < abs( $remainder ) ? ( $remainder < 0 ? -1 : 1 ) : 0 );
	}
	private static function option_total( $options ) { $sum = 0; foreach ( $options as $o ) $sum += (int) ( $o['quantity'] ?? 0 ) * (int) ( $o['unit_price_cents'] ?? 0 ); return $sum; }
	public static function payment_position( $total, $deposit, $paid ) {
		$total = max( 0, (int) $total ); $deposit = min( $total, max( 0, (int) $deposit ) ); $paid = max( 0, (int) $paid );
		return array( 'deposit' => $deposit, 'depositPaid' => min( $deposit, $paid ), 'depositDue' => max( 0, $deposit - $paid ), 'saldoDue' => max( 0, $total - max( $deposit, $paid ) ), 'balance' => max( 0, $total - $paid ) );
	}
	private static function definitions( $bundle ) {
		$snapshot = self::decode( $bundle['registration']['snapshot_json'] );
		$definitions = array_column( (array) get_post_meta( $bundle['registration']['event_id'], '_mi_options', true ), null, 'code' );
		foreach ( $snapshot['event']['options'] ?? array() as $o ) $definitions[$o['code']] = $o;
		return $definitions;
	}
	private static function person( $event, $id, $bundle = null ) {
		$b = $bundle ?: self::bundle( $event, $id ); $r = $b['registration'];
		$active = array_values( array_filter( $b['people'], static function ( $p ) { return 'ACTIVE' === $p['status']; } ) );
		$index = array_search( $id, array_map( 'intval', array_column( $active, 'id' ) ), true );
		if ( false === $index ) throw new InvalidArgumentException( 'Persona non disponibile.' );
		$p = $active[$index]; $opts = self::decode( $p['options_json'] ); $definitions = self::definitions( $b );
		$editable = array(); $locked = array(); $selected = array_column( $opts, null, 'code' );
		$snapshot = self::decode( $r['snapshot_json'] );
		$can_edit = 'ALL' === ( $snapshot['event']['participant_extra_scope'] ?? 'ONE' ) || (int) $b['people'][0]['id'] === $id;
		$managed = in_array( get_post_meta( $event, '_mi_economic_mode', true ), array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true );
		foreach ( $definitions as $code => $o ) if ( $can_edit && $managed && 'TICKET' === ( $o['scope'] ?? '' ) && ( 'pullman' === ( $o['category'] ?? '' ) || 0 === strpos( $code, 'pullman-' ) ) ) {
			$direction = preg_match( '/ritorno|fiumicino.{0,5}roma|santiago.{0,5}a coru/i', $o['name'] ) ? 'Al ritorno' : ( preg_match( '/andata|roma.{0,5}fiumicino|porto.{0,5}tui/i', $o['name'] ) ? 'All’andata' : 'Trasferimenti' );
			$editable[] = array( 'code' => $code, 'name' => $o['name'], 'price' => isset( $selected[$code] ) ? (int) $selected[$code]['unit_price_cents'] : (int) $o['price_cents'], 'selected' => ! empty( $selected[$code]['quantity'] ), 'group' => $o['choice_group'] ?? '', 'direction' => $direction );
		}
		$editable_codes = array_column( $editable, 'code' );
		foreach ( $opts as $o ) if ( ! in_array( $o['code'], $editable_codes, true ) && ! empty( $o['quantity'] ) ) $locked[] = array( 'name' => $o['name'], 'accommodation' => 0 === strpos( $o['code'], 'alloggio-' ) || 'alloggio' === ( $definitions[$o['code']]['category'] ?? '' ), 'price' => (int) $o['unit_price_cents'] * (int) $o['quantity'] );
		$all_options = 0; foreach ( $active as $a ) $all_options += self::option_total( self::decode( $a['options_json'] ) );
		$base = self::split( (int) $r['total_cents'] - $all_options, count( $active ), $index );
		$fixed = $base + array_sum( array_column( $locked, 'price' ) );
		$extra = self::decode( $p['extra_json'] ); $email = sanitize_email( $extra['email'] ?? $r['buyer_email'] );
		return array( 'success' => true, 'row' => $id, 'persona' => array( 'nome' => $p['first_name'], 'cognome' => $p['last_name'], 'email' => $email, 'siglaAlloggio' => $p['room_code'], 'alloggio' => $p['room_code'], 'locked' => $locked, 'services' => $editable, 'fixed' => $managed ? $fixed : 0, 'paid' => $managed ? self::split( max( 0, $b['paid'] ), count( $active ), $index ) : 0, 'deposit' => $managed && 'DEPOSIT_BALANCE' === $r['economic_mode'] ? self::split( (int) $r['initial_due_cents'], count( $active ), $index ) : 0, 'managed' => $managed, 'shared' => count( $active ) > 1, 'token' => self::token( $event, $id ), 'version' => $b['version'] ) );
	}
	private static function queue_email( $event, $receipt, $key, $registration ) {
		global $wpdb;
		$payment = self::payment_config( $event );
		$money = static function ( $c ) { return number_format( $c / 100, 2, ',', '.' ) . ' €'; };
		$text = 'Riepilogo — ' . get_the_title( $event ) . "\n\n";
		foreach ( $receipt['people'] as $p ) { $text .= $p['name'] . "\n"; foreach ( $p['lines'] as $line ) $text .= '• ' . $line['name'] . ': ' . $money( $line['price'] ) . "\n"; }
		if ( $receipt['deposit'] ) $text .= "\nCaparra versata: " . $money( $receipt['depositPaid'] ) . "\nCaparra da versare: " . $money( $receipt['depositDue'] ) . "\nSaldo da versare: " . $money( $receipt['saldoDue'] );
		$text .= "\nTotale: " . $money( $receipt['total'] ) . "\nVersato (esclusi rimborsi effettuati): " . $money( $receipt['paid'] ) . "\nSaldo da versare: " . $money( $receipt['balance'] ) . "\nCausale: " . $receipt['causale'];
		if ( $receipt['balance'] > 0 && in_array( 'BANK_TRANSFER', $payment['methods'], true ) ) $text .= "\nIBAN: " . $payment['iban'] . "\nIntestatario: " . $payment['holder'];
		if ( $receipt['balance'] > 0 && in_array( 'CARD', $payment['methods'], true ) ) $text .= "\nPagamento con carta: " . $payment['cardUrl'];
		foreach ( $receipt['people'] as $person ) if ( ! empty( $person['missing'] ) ) $text .= "\n\nPrima dell’evento — " . $person['name'] . ': ' . implode( ', ', $person['missing'] ) . '. Rispondi a questa email con le informazioni richieste.';
		$snapshot = MI_Modello_Email::crea_istantanea( $event, array() );
		$snapshot['attivo'] = true; $snapshot['oggetto'] = 'Ecco il tuo saldo e le istruzioni di pagamento — ' . get_the_title( $event ); $snapshot['testo'] = $text;
		$snapshot['titolo'] = 'Riepilogo della prenotazione'; $snapshot['preheader'] = 'Importi, scadenze e indicazioni aggiornate.'; $snapshot['html'] = self::email_body( $event, $receipt, $payment );
		$snapshot['identita_email']['indirizzo_risposte'] = $payment['contact'];
		$snapshot['identificativo'] = array( 'modalita' => 'NONE', 'codice' => '', 'payload_qr' => '' );
		$status = MI_Spedizione_Email::stato_nuova_email( $snapshot );
		$payload = wp_json_encode( array( 'event_id' => $event, 'template_type' => 'PUBLIC_BALANCE', 'email_preview' => $snapshot ) );
		if ( false === $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}mi_email_outbox (registration_id,recipient,template_type,origin_key,payload_json,status,created_at) VALUES (%d,%s,'PUBLIC_BALANCE',%s,%s,%s,%s)", $registration, $receipt['email'], $key, $payload, $status, current_time( 'mysql', true ) ) ) ) throw new RuntimeException( 'Riepilogo non archiviato. Riprova.' );
		return 'PENDING' === $status;
	}
	private static function email_body( $event, $receipt, $payment ) {
		$money = static function ( $c ) { return number_format( $c / 100, 2, ',', '.' ) . ' €'; };
		$rows = ''; $missing = ''; $deadlines = array();
		foreach ( $receipt['people'] as $person ) {
			$rows .= '<tr><td style="padding:12px;border-bottom:1px solid #eee"><strong>' . esc_html( $person['name'] ) . '</strong>';
			foreach ( $person['lines'] as $line ) $rows .= '<br><small style="color:#718096">' . esc_html( $line['name'] . ' — ' . $money( $line['price'] ) ) . '</small>';
			$rows .= '</td></tr>';
			if ( ! empty( $person['missing'] ) ) $missing .= '<p><strong>' . esc_html( $person['name'] ) . '</strong></p><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $person['missing'] ) ) . '</li></ul>';
			if ( ! empty( $person['deadline'] ) ) $deadlines[] = $person['deadline'];
		}
		$costs = array( 'Totale complessivo' => $receipt['total'] );
		if ( $receipt['deposit'] ) $costs += array( 'Caparra versata' => $receipt['depositPaid'], 'Caparra da versare' => $receipt['depositDue'], 'Saldo da versare' => $receipt['saldoDue'] );
		$costs += array( 'Versato (esclusi rimborsi effettuati)' => $receipt['paid'], 'Totale da versare' => $receipt['balance'] );
		$cost_html = ''; foreach ( $costs as $label => $value ) $cost_html .= '<tr><td style="padding:8px 0;color:#4a5568">' . esc_html( $label ) . '</td><td style="padding:8px 0;text-align:right;font-weight:600">' . esc_html( $money( $value ) ) . '</td></tr>';
		$html = '<p style="color:#64748B;font-size:13px">Emesso il ' . esc_html( current_time( 'd/m/Y H:i' ) ) . '</p><h2 style="font-size:18px">Partecipanti</h2><table style="width:100%;border-collapse:collapse;margin-bottom:25px">' . $rows . '</table><div style="background:#f7fafc;border-radius:8px;padding:20px;margin-bottom:25px"><table style="width:100%">' . $cost_html . '</table></div>';
		if ( $deadlines && $receipt['balance'] > 0 ) $html .= '<div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:15px;border-radius:0 8px 8px 0;margin-bottom:25px"><strong>Scadenza indicata:</strong> ' . esc_html( implode( ' · ', array_unique( $deadlines ) ) ) . '</div>';
		if ( $receipt['balance'] > 0 && in_array( 'BANK_TRANSFER', $payment['methods'], true ) ) $html .= '<h2 style="color:#1a365d;font-size:18px">Coordinate per il bonifico</h2><div style="background:#ebf8ff;border-radius:8px;padding:20px"><p><strong>Intestatario:</strong> ' . esc_html( $payment['holder'] ) . '</p><p><strong>IBAN:</strong> <code style="background:#fff;padding:4px 11px;border-radius:4px;font-size:16px;user-select:all">' . esc_html( $payment['iban'] ) . '</code></p><p><strong>Causale:</strong> ' . esc_html( $receipt['causale'] ) . '</p></div>';
		if ( $missing ) $html .= '<div style="background:#f9fafb;border-left:6px solid #f59e0b;border-radius:10px;padding:20px 22px;margin-top:32px"><h3>📍 Prima dell’evento…</h3><p>Per completare al meglio l’organizzazione, ci manca ancora qualche informazione:</p>' . $missing . '<p>È sufficiente rispondere a questa email con le informazioni richieste.<br>Grazie per la collaborazione 💛</p></div>';
		return $html;
	}
	public static function email_html( $event, $receipt, $payment ) {
		$snapshot = MI_Modello_Email::crea_istantanea( $event, array() );
		$snapshot['attivo'] = true; $snapshot['oggetto'] = 'Riepilogo — ' . get_the_title( $event ); $snapshot['titolo'] = 'Riepilogo della prenotazione'; $snapshot['html'] = self::email_body( $event, $receipt, $payment );
		return MI_Modello_Email::componi_html( $snapshot );
	}
	public static function save( $event, $data, $preview = false ) {
		global $wpdb;
		self::event( $event );
		$people = $data['persone'] ?? null; $email = sanitize_email( $data['email'] ?? '' );
		if ( ! is_array( $people ) || ! count( $people ) || count( $people ) > 20 || ( ! $preview && ! is_email( $email ) ) ) throw new InvalidArgumentException( 'Carica le persone e inserisci un’email valida prima di confermare.' );
		$request = (string) ( $data['requestId'] ?? '' );
		if ( ! $preview && ! preg_match( '/^[a-f0-9-]{36}$/i', $request ) ) throw new InvalidArgumentException( 'Identificativo richiesta non valido.' );
		$hash = hash( 'sha256', wp_json_encode( array( $event, $people, $email ) ) );
		$key = hash( 'sha256', 'public-balance|' . $event . '|' . $request );
		$seen = array(); foreach ( $people as $p ) {
			$id = absint( $p['row'] ?? 0 );
			if ( ! $id || isset( $seen[$id] ) || ! hash_equals( self::token( $event, $id ), (string) ( $p['token'] ?? '' ) ) ) throw new InvalidArgumentException( 'Persona duplicata o riferimento non valido. Ricarica la prenotazione.' );
			$seen[$id] = true;
		}
		if ( class_exists( 'MI_Event_Deletion' ) ) { $lease = MI_Event_Deletion::enter( $event ); if ( is_wp_error( $lease ) ) throw new RuntimeException( $lease->get_error_message() ); }
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new RuntimeException( 'Registro non disponibile.' );
		try {
			MI_Management_Service::lock_room_event( $event );
			if ( ! $preview ) {
				$prior = $wpdb->get_var( $wpdb->prepare( "SELECT detail_json FROM {$wpdb->prefix}mi_registration_events WHERE event_type='public_balance' AND actor_label=%s LIMIT 1", $key ) ); self::check();
				if ( $prior ) { $prior = self::decode( $prior ); if ( ! hash_equals( $hash, $prior['hash'] ) ) throw new InvalidArgumentException( 'Richiesta già utilizzata con dati diversi.' ); $wpdb->query( 'COMMIT' ); return $prior['receipt']; }
			}
			$bundles = array(); $changes = array(); $deltas = array(); $receipt = array( 'success' => true, 'people' => array(), 'total' => 0, 'paid' => 0, 'email' => $email, 'deposit' => 0, 'depositPaid' => 0, 'depositDue' => 0, 'saldoDue' => 0, 'balance' => 0 );
			foreach ( $people as $person ) {
				$id = (int) $person['row']; $b = self::bundle( $event, $id, true ); $r = $b['registration']; $rid = (int) $r['id'];
				if ( ! hash_equals( $b['version'], (string) ( $person['version'] ?? '' ) ) ) throw new InvalidArgumentException( 'Iscrizione o pagamenti aggiornati dalla segreteria. Premi Cambia e carica nuovamente le persone.' );
				$bundles[$rid] = $b; $view = self::person( $event, $id, $b )['persona'];
				$input = $person['services'] ?? array(); if ( ! is_array( $input ) ) throw new InvalidArgumentException( 'Servizi non validi.' );
				$allowed = array_column( $view['services'], null, 'code' );
				foreach ( $input as $code => $quantity ) if ( ! isset( $allowed[$code] ) || ! in_array( $quantity, array( 0, 1 ), true ) ) throw new InvalidArgumentException( 'Servizio non modificabile.' );
				$original = self::decode( array_column( $b['people'], null, 'id' )[$id]['options_json'] );
				$options = array_values( array_filter( $original, static function ( $o ) use ( $allowed ) { return ! isset( $allowed[$o['code']] ); } ) );
				$lines = $view['locked']; $sum = $view['fixed']; $groups = array();
				$base = $sum - array_sum( array_column( $lines, 'price' ) );
				if ( $base ) array_unshift( $lines, array( 'name' => 'Quota base e rettifiche', 'price' => $base ) );
				foreach ( $allowed as $code => $service ) if ( ! empty( $input[$code] ) ) {
					if ( $service['group'] && isset( $groups[$service['group']] ) ) throw new InvalidArgumentException( 'Scegli una sola alternativa per ogni gruppo di servizi.' );
					$groups[$service['group']] = true;
					$options[] = array( 'code' => $code, 'name' => $service['name'], 'quantity' => 1, 'unit_price_cents' => $service['price'] );
					$sum += $service['price']; $lines[] = array( 'name' => $service['name'], 'price' => $service['price'] );
				}
				if ( $sum < 0 ) throw new InvalidArgumentException( 'La rettifica presente richiede una verifica della segreteria.' );
				$delta = self::option_total( $options ) - self::option_total( $original );
				$deltas[$rid] = ( $deltas[$rid] ?? 0 ) + $delta;
				$changes[$id] = array( 'before' => $original, 'after' => $options );
				$person_row = array_column( $b['people'], null, 'id' )[$id]; $fields = self::decode( $person_row['extra_json'] ); $snapshot = self::decode( $r['snapshot_json'] ); $missing = array();
				if ( 'ALL' === ( $snapshot['event']['participant_extra_scope'] ?? 'ONE' ) || (int) $b['people'][0]['id'] === $id ) foreach ( $snapshot['event']['participant_fields'] ?? array() as $field ) {
					if ( ! empty( $field['required'] ) && '' === trim( (string) ( $fields[$field['key']] ?? '' ) ) ) $missing[] = $field['label'] ?? $field['key'];
				}
				$deadline = (string) ( $r['payment_deadline_at'] ?? '' );
				if ( $deadline && function_exists( 'get_date_from_gmt' ) ) $deadline = get_date_from_gmt( $deadline, 'd/m/Y H:i' );
				$receipt['people'][] = array( 'row' => $id, 'name' => $view['cognome'] . ' ' . $view['nome'], 'lines' => $lines, 'total' => $sum, 'paid' => $view['paid'], 'missing' => $missing, 'deadline' => $deadline );
				$position = self::payment_position( $sum, $view['deposit'], $view['paid'] );
				foreach ( $position as $field => $amount ) $receipt[$field] += $amount;
				$receipt['total'] += $sum; $receipt['paid'] += $view['paid'];
			}
			$receipt['causale'] = 'Saldo ' . get_the_title( $event ) . ' — ' . implode( ', ', array_column( $receipt['people'], 'name' ) );
			$receipt['fingerprint'] = hash( 'sha256', wp_json_encode( $receipt ) );
			if ( $preview ) { $wpdb->query( 'ROLLBACK' ); return $receipt; }
			if ( ! hash_equals( $receipt['fingerprint'], (string) ( $data['fingerprint'] ?? '' ) ) ) throw new InvalidArgumentException( 'Il riepilogo è cambiato. Controlla di nuovo gli importi prima di confermare.' );
			foreach ( $changes as $id => $change ) if ( $change['before'] !== $change['after'] && false === $wpdb->update( $wpdb->prefix . 'mi_participants', array( 'options_json' => wp_json_encode( $change['after'] ) ), array( 'id' => $id ) ) ) throw new RuntimeException( 'Servizi non salvati.' );
			foreach ( $bundles as $rid => $b ) {
				$r = $b['registration']; $total = (int) $r['total_cents'] + $deltas[$rid]; if ( $total < 0 ) throw new InvalidArgumentException( 'La rettifica presente richiede una verifica della segreteria.' );
				$initial = 'FULL_PAYMENT' === $r['economic_mode'] ? $total : min( $total, (int) $r['initial_due_cents'] );
				$status = in_array( $r['economic_mode'], array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ? ( $b['paid'] >= $initial ? 'CONFIRMED' : 'PENDING_PAYMENT' ) : $r['status'];
				if ( false === $wpdb->update( $wpdb->prefix . 'mi_registrations', array( 'total_cents' => $total, 'initial_due_cents' => $initial, 'balance_cents' => $total - $initial, 'status' => $status, 'expires_at' => 'CONFIRMED' === $status ? null : $r['payment_deadline_at'] ), array( 'id' => $rid ) ) ) throw new RuntimeException( 'Importi non salvati.' );
				MI_Registration_Service::mark_workspace_changed_locked( $rid );
			}
			$receipt['emailQueued'] = self::queue_email( $event, $receipt, $key, (int) array_key_first( $bundles ) );
			$receipt['versions'] = array(); foreach ( $people as $p ) $receipt['versions'][(int) $p['row']] = self::bundle( $event, (int) $p['row'], true )['version'];
			foreach ( $bundles as $rid => $b ) if ( ! MI_Registration_Service::append_registration_event( $rid, 'public_balance', $b['registration']['status'], '', $key, array( 'hash' => $hash, 'receipt' => $receipt, 'changes' => $changes ) ) ) throw new RuntimeException( 'Conferma non archiviata.' );
			if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mi_management_state SET revision=revision+1 WHERE event_id=%d", $event ) ) || false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Conferma non disponibile.' );
		} catch ( Throwable $error ) { $wpdb->query( 'ROLLBACK' ); throw $error; }
		MI_Spedizione_Email::pianifica_spedizione();
		foreach ( $bundles as $rid => $b ) try { MI_Registration_Service::accoda_iscrizione_workspace( $rid ); } catch ( Throwable $error ) { /* Durable workspace queue will retry. */ }
		return $receipt;
	}
	public static function render( $event ) {
		try { self::event( $event ); } catch ( Throwable $error ) { wp_die( esc_html( $error->getMessage() ) ); }
		nocache_headers();
		$config = array_merge( self::payment_config( $event ), array( 'eventTitle' => get_the_title( $event ), 'endpoint' => add_query_arg( 'mi_public_balance', $event, home_url( '/' ) ), 'nonce' => wp_create_nonce( 'mi_public_balance_' . $event ) ) );
		$asset = MI_PLUGIN_URL . 'assets/';
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><title>Saldo — ' . esc_html( get_the_title( $event ) ) . '</title><link rel="stylesheet" href="' . esc_url( $asset . 'public-balance.css?ver=' . MI_VERSION ) . '"></head><body>';
		include MI_PLUGIN_DIR . 'templates/public-balance.php';
		echo '<script>window.MIBalance=' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';</script><script src="' . esc_url( $asset . 'public-balance.js?ver=' . MI_VERSION ) . '"></script></body></html>';
	}
}
