<?php
defined( 'ABSPATH' ) || exit;

/** Passaggi privati: inventario distinto dai servizi e dal registro degli incassi. */
final class MI_Travel {
	const OFFER_SECONDS = 86400;
	private static $projection = array();
	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), -120 );
		add_action( 'mi_expire_registrations', array( __CLASS__, 'tick' ), 30 );
	}
	public static function create_table() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$wpdb->prefix}mi_travel (
			participant_id bigint(20) unsigned NOT NULL,
			registration_id bigint(20) unsigned NOT NULL,
			event_id bigint(20) unsigned NOT NULL,
			mode varchar(16) NOT NULL,
			seats smallint(5) unsigned NOT NULL DEFAULT 0,
			price_cents int(10) unsigned NOT NULL DEFAULT 0,
			origin varchar(180) NOT NULL DEFAULT '',
			departure_at datetime NULL,
			required_ride tinyint(1) NOT NULL DEFAULT 0,
			state varchar(24) NOT NULL DEFAULT 'NONE',
			driver_id bigint(20) unsigned NULL,
			offer_expires_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (participant_id),
			KEY registration_id (registration_id),
			KEY event_queue (event_id,state,created_at),
			KEY driver_seats (driver_id,state,offer_expires_at)
		) ENGINE=InnoDB {$charset};" );
	}
	public static function config( $event_id ) {
		$config = (array) get_post_meta( $event_id, '_mi_travel', true );
		return array( 'enabled' => ! empty( $config['enabled'] ), 'instructions' => (string) ( $config['instructions'] ?? '' ), 'price_mode' => 'DRIVER' === ( $config['price_mode'] ?? '' ) ? 'DRIVER' : 'FIXED', 'price_cents' => max( 0, (int) ( $config['price_cents'] ?? 0 ) ), 'deadline' => (string) ( $config['deadline'] ?? '' ) );
	}
	public static function has_records( $event_id ) {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT participant_id FROM {$wpdb->prefix}mi_travel WHERE event_id=%d LIMIT 1", $event_id ) ); self::check();
		return (bool) $result;
	}
	public static function projection_fields( $participant_id, $event_id ) {
		global $wpdb;
		if ( ! self::config( $event_id )['enabled'] ) return array();
		if ( ! isset( self::$projection[$event_id] ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.*,p.first_name,p.last_name FROM {$wpdb->prefix}mi_travel t JOIN {$wpdb->prefix}mi_participants p ON p.id=t.participant_id WHERE t.event_id=%d ORDER BY t.participant_id", $event_id ), ARRAY_A ); self::check();
			self::$projection[$event_id] = array_column( $rows, null, 'participant_id' );
		}
		$row = self::$projection[$event_id][$participant_id] ?? null;
		if ( ! $row ) return array();
		$driver = self::$projection[$event_id][$row['driver_id']] ?? array();
		return array( 'travel_mode' => array( 'OWN' => 'Autonomo', 'DRIVER' => 'Conducente', 'NEED' => 'Cerca passaggio' )[$row['mode']] ?? '', 'travel_seats' => (string) $row['seats'], 'travel_origin' => $row['origin'], 'travel_departure' => $row['departure_at'] ? wp_date( 'd/m/Y H:i', strtotime( $row['departure_at'] . ' UTC' ), wp_timezone() ) : '', 'travel_state' => self::state_label( $row['state'] ), 'travel_driver' => trim( ( $driver['first_name'] ?? '' ) . ' ' . ( $driver['last_name'] ?? '' ) ), 'travel_required' => $row['required_ride'] ? 'Sì' : 'No', 'travel_contribution' => in_array( $row['mode'], array( 'DRIVER', 'NEED' ), true ) ? number_format( $row['price_cents'] / 100, 2, ',', '' ) . ' € al conducente (A/R)' : '', 'travel_offer_expiry' => $row['offer_expires_at'] ? wp_date( 'd/m/Y H:i', strtotime( $row['offer_expires_at'] . ' UTC' ), wp_timezone() ) : '' );
	}
	public static function validate_config( $raw, $starts_at, $previous_deadline = '' ) {
		if ( empty( $raw['travel_enabled'] ) ) return array( 'enabled' => false );
		$mode = (string) ( $raw['travel_price_mode'] ?? 'FIXED' );
		$price = MI_Amount::cents( $raw['travel_price'] ?? '' );
		$deadline = self::local_timestamp( $raw['travel_deadline'] ?? '' );
		$start = self::local_timestamp( $starts_at );
		if ( ! in_array( $mode, array( 'FIXED', 'DRIVER' ), true ) || ( 'FIXED' === $mode && ( null === $price || $price > 100000000 ) ) ) return new WP_Error( 'mi_travel_price', 'Indica un contributo valido per il passaggio, anche zero.' );
		if ( ! $deadline || ! $start || $deadline >= $start || ( $deadline <= time() && $deadline !== self::local_timestamp( $previous_deadline ) ) ) return new WP_Error( 'mi_travel_deadline', 'Il termine di assegnazione dei passaggi deve essere futuro e precedente all’inizio dell’evento.' );
		return array( 'enabled' => true, 'instructions' => mb_substr( sanitize_textarea_field( $raw['travel_instructions'] ?? '' ), 0, 3000 ), 'price_mode' => $mode, 'price_cents' => 'FIXED' === $mode ? $price : 0, 'deadline' => wp_date( 'Y-m-d\TH:i', $deadline, wp_timezone() ) );
	}
	public static function local_timestamp( $value ) {
		if ( ! is_string( $value ) ) return false;
		$format = false !== strpos( $value, '/' ) ? 'd/m/Y H:i' : 'Y-m-d\TH:i';
		$date = DateTimeImmutable::createFromFormat( '!' . $format, $value, wp_timezone() );
		return $date && $date->format( $format ) === $value ? $date->getTimestamp() : false;
	}
	private static function check( $result = true ) {
		global $wpdb;
		if ( false === $result || $wpdb->last_error ) throw new RuntimeException( 'Gestione passaggi momentaneamente non disponibile.' );
		return $result;
	}
	private static function state_label( $state ) {
		return array( 'WAITING' => 'In attesa di passaggio', 'OFFERED' => 'Passaggio proposto', 'ASSIGNED' => 'Passaggio assegnato', 'DECLINED' => 'Proposta rifiutata', 'EXPIRED' => 'Proposta scaduta', 'CLOSED' => 'Viaggio chiuso', 'NONE' => 'Nessun passaggio richiesto' )[$state] ?? '';
	}
	private static function write( $id, array $data ) {
		global $wpdb;
		self::$projection = array();
		self::check( $wpdb->update( $wpdb->prefix . 'mi_travel', $data, array( 'participant_id' => $id ) ) );
	}
	public static function validate_people( array $event, array $people, $raw, $email, $booking_choice = null ) {
		if ( empty( $event['travel']['enabled'] ) ) return $people;
		if ( null !== $booking_choice ) {
			if ( ! is_array( $booking_choice ) || ! $people ) return new WP_Error( 'mi_travel_choice', 'Indica il viaggio della prenotazione.', array( 'status' => 400 ) );
			$raw = array(); $assignments = array_values( (array) ( $booking_choice['assignments'] ?? array() ) ); $assignment_index = 0; $assignment_remaining = 0;
			foreach ( $assignments as $assignment ) if ( ! is_array( $assignment ) || absint( $assignment['driver_id'] ?? 0 ) < 1 || (int) ( $assignment['seats'] ?? 0 ) < 1 || (int) ( $assignment['seats'] ?? 0 ) > 8 ) return new WP_Error( 'mi_travel_choice', 'Selezione dei passaggi non valida.', array( 'status' => 400 ) );
			foreach ( $people as $index => $person ) {
				$choice = $booking_choice; unset( $choice['assignments'] );
				if ( 'DRIVER' === ( $booking_choice['mode'] ?? '' ) && 0 !== $index ) $choice = array( 'mode' => 'OWN' );
				if ( 'NEED' === ( $booking_choice['mode'] ?? '' ) ) {
					while ( $assignment_index < count( $assignments ) && $assignment_remaining < 1 ) { $assignment_remaining = (int) $assignments[$assignment_index]['seats']; if ( $assignment_remaining < 1 ) ++$assignment_index; }
					$choice['driver_id'] = $assignment_index < count( $assignments ) && $assignment_remaining > 0 ? absint( $assignments[$assignment_index]['driver_id'] ) : 0;
					$choice['price_cents'] = $assignment_index < count( $assignments ) ? max( 0, (int) ( $assignments[$assignment_index]['price_cents'] ?? 0 ) ) : 0;
					if ( $assignment_index < count( $assignments ) && $assignment_remaining > 0 && 0 === --$assignment_remaining ) ++$assignment_index;
				}
				$raw[] = array( 'ticket_type_code' => $person['ticket_type_code'], 'ticket_index' => $person['ticket_index'], 'travel' => $choice );
			}
		}
		$config = $event['travel'];
		$choices = array();
		foreach ( (array) $raw as $person ) {
			if ( ! is_array( $person ) ) continue;
			$choices[ ( $person['ticket_type_code'] ?? '' ) . ':' . ( $person['ticket_index'] ?? '' ) ] = $person['travel'] ?? null;
		}
		foreach ( $people as &$person ) {
			$choice = $choices[ $person['ticket_type_code'] . ':' . $person['ticket_index'] ] ?? null;
			if ( ! is_array( $choice ) ) return new WP_Error( 'mi_travel_choice', 'Indica come raggiungerà l’evento ogni partecipante.', array( 'status' => 400 ) );
			foreach ( $choice as $value ) if ( ! is_scalar( $value ) && null !== $value ) return new WP_Error( 'mi_travel_choice', 'Dati del viaggio non validi.', array( 'status' => 400 ) );
			$mode = (string) ( $choice['mode'] ?? '' );
			if ( self::local_timestamp( $config['deadline'] ?? '' ) <= time() && ( 'NEED' === $mode || ( 'DRIVER' === $mode && ! empty( $choice['seats'] ) ) ) ) return new WP_Error( 'mi_travel_closed', 'Il termine per organizzare i passaggi è trascorso. Puoi partecipare organizzandoti autonomamente.', array( 'status' => 409 ) );
			if ( ! in_array( $mode, array( 'OWN', 'DRIVER', 'NEED' ), true ) ) return new WP_Error( 'mi_travel_choice', 'Scegli una modalità di spostamento valida.', array( 'status' => 400 ) );
			$travel = array( 'mode' => $mode, 'seats' => 0, 'price_cents' => 0, 'origin' => '', 'departure_at' => null, 'required_ride' => 0, 'driver_id' => null, 'state' => 'NONE' );
			if ( ( 'NEED' === $mode || ( 'DRIVER' === $mode && ! empty( $choice['seats'] ) ) ) && ! is_email( $email ) ) return new WP_Error( 'mi_travel_email', 'Per offrire o richiedere passaggi occorre l’email del referente.', array( 'status' => 400 ) );
			if ( 'DRIVER' === $mode ) {
				$seats = filter_var( $choice['seats'] ?? null, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0, 'max_range' => 8 ) ) );
				if ( false === $seats ) return new WP_Error( 'mi_travel_seats', 'Indica da 0 a 8 posti disponibili per altre persone.', array( 'status' => 400 ) );
				$travel['seats'] = $seats;
				if ( $seats ) {
					$origin = mb_substr( sanitize_text_field( $choice['origin'] ?? '' ), 0, 180 );
					if ( '' === $origin ) $origin = mb_substr( sanitize_text_field( $event['event_location'] ?? '' ), 0, 180 );
					$departure = self::local_timestamp( $choice['departure'] ?? '' );
					if ( false === $departure ) $departure = self::local_timestamp( $event['event_starts_at'] ?? '' );
					$price = 'FIXED' === $config['price_mode'] ? $config['price_cents'] : MI_Amount::cents( $choice['price'] ?? '' );
					if ( ! $origin || ! $departure || $departure <= self::local_timestamp( $config['deadline'] ) || $departure > self::local_timestamp( $event['event_starts_at'] ) || null === $price || $price > 100000000 ) return new WP_Error( 'mi_travel_driver', 'Indica luogo di partenza, orario successivo al termine dei passaggi e precedente all’evento, e un contributo valido.', array( 'status' => 400 ) );
					$travel = array_merge( $travel, array( 'origin' => $origin, 'departure_at' => gmdate( 'Y-m-d H:i:s', $departure ), 'price_cents' => $price ) );
				}
			}
			if ( 'NEED' === $mode ) {
				$driver_id = absint( $choice['driver_id'] ?? 0 );
				if ( ! $driver_id && ! in_array( $choice['fallback'] ?? '', array( 'REQUIRED', 'ANYWAY' ), true ) ) return new WP_Error( 'mi_travel_fallback', 'Specifica se parteciperai anche senza un passaggio.', array( 'status' => 400 ) );
				$travel['required_ride'] = 'ANYWAY' === ( $choice['fallback'] ?? '' ) ? 0 : 1;
				$travel['driver_id'] = $driver_id ?: null;
				$travel['price_cents'] = $travel['driver_id'] ? max( 0, (int) ( $choice['price_cents'] ?? 0 ) ) : ( 'FIXED' === $config['price_mode'] ? (int) $config['price_cents'] : 0 );
				$travel['origin'] = mb_substr( sanitize_text_field( $choice['origin'] ?? '' ), 0, 180 );
				$travel['state'] = $travel['driver_id'] ? 'ASSIGNED' : 'WAITING';
				if ( ! $travel['driver_id'] && true !== ( $choice['waitlist'] ?? false ) ) return new WP_Error( 'mi_travel_waitlist', 'Scegli un passaggio oppure richiedi l’ingresso nella lista d’attesa auto.', array( 'status' => 400 ) );
			}
			$person['travel'] = $travel;
		}
		unset( $person );
		return $people;
	}
	public static function pending_people( array $people ) {
		foreach ( $people as $person ) if ( ! empty( $person['travel']['required_ride'] ) && empty( $person['travel']['driver_id'] ) ) return true;
		return false;
	}
	private static function rows( $event_id, $lock = false ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.*,p.first_name,p.last_name,p.status person_status,r.status booking_status,r.buyer_email,r.buyer_phone,r.order_code,r.capacity_released_at FROM {$wpdb->prefix}mi_travel t JOIN {$wpdb->prefix}mi_participants p ON p.id=t.participant_id JOIN {$wpdb->prefix}mi_registrations r ON r.id=t.registration_id WHERE t.event_id=%d ORDER BY t.created_at,t.participant_id" . ( $lock ? ' FOR UPDATE' : '' ), $event_id ), ARRAY_A );
		self::check();
		return $rows;
	}
	private static function admitted( array $row ) {
		return 'ACTIVE' === $row['person_status'] && empty( $row['capacity_released_at'] ) && in_array( $row['booking_status'], array( 'CONFIRMED', 'PENDING_PAYMENT', 'TRAVEL_PENDING' ), true );
	}
	private static function driver_active( array $row ) {
		return self::admitted( $row ) && 'TRAVEL_PENDING' !== $row['booking_status'] && 'DRIVER' === $row['mode'] && (int) $row['seats'] > 0;
	}
	private static function free_seats( array $driver, array $rows ) {
		$used = 0;
		foreach ( $rows as $row ) if ( (int) $row['driver_id'] === (int) $driver['participant_id'] && self::admitted( $row ) && ( 'ASSIGNED' === $row['state'] || ( 'OFFERED' === $row['state'] && strtotime( $row['offer_expires_at'] . ' UTC' ) > time() ) ) ) ++$used;
		return max( 0, (int) $driver['seats'] - $used );
	}
	private static function seat_counts( array $rows ) {
		$used = array();
		foreach ( $rows as $row ) if ( ! empty( $row['driver_id'] ) && self::admitted( $row ) && ( 'ASSIGNED' === $row['state'] || ( 'OFFERED' === $row['state'] && strtotime( $row['offer_expires_at'] . ' UTC' ) > time() ) ) ) $used[$row['driver_id']] = ( $used[$row['driver_id']] ?? 0 ) + 1;
		return $used;
	}
	public static function save_locked( $event, $registration_id, array $people ) {
		if ( empty( $event['travel']['enabled'] ) ) return;
		self::$projection = array();
		global $wpdb;
		$stored = $wpdb->get_results( $wpdb->prepare( "SELECT id,ticket_type_code,ticket_index FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d", $registration_id ), ARRAY_A ); self::check();
		$ids = array(); foreach ( $stored as $row ) $ids[$row['ticket_type_code'] . ':' . $row['ticket_index']] = $row['id'];
		$booking = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $registration_id ) ); self::check();
		foreach ( $people as $person ) {
			$data = $person['travel'];
			$id = $ids[$person['ticket_type_code'] . ':' . $person['ticket_index']];
			if ( ! empty( $data['driver_id'] ) ) {
				$rows = self::rows( $event['id'], true ); $driver = null;
				foreach ( $rows as $row ) if ( (int) $row['participant_id'] === (int) $data['driver_id'] ) $driver = $row;
				if ( ! $driver || ! self::driver_active( $driver ) || self::free_seats( $driver, $rows ) < 1 || (int) $data['price_cents'] !== (int) $driver['price_cents'] ) throw new InvalidArgumentException( 'Il passaggio scelto non è più disponibile alle condizioni mostrate. Aggiorna le disponibilità.' );
				$data['origin'] = $driver['origin']; $data['departure_at'] = $driver['departure_at'];
			}
			if ( 'WAITLISTED' === $booking ) { $data['driver_id'] = null; if ( 'NEED' === $data['mode'] ) $data['state'] = 'WAITING'; }
			if ( ! empty( $data['driver_id'] ) ) self::notify_locked( $driver, 'Nuovo passeggero per il tuo viaggio', $person['first_name'] . ' ' . $person['last_name'] . ' ha scelto un posto sulla tua auto. Consulta il viaggio per i contatti e il riepilogo dei passeggeri.' );
			self::check( $wpdb->insert( $wpdb->prefix . 'mi_travel', array_merge( $data, array( 'participant_id' => $id, 'registration_id' => $registration_id, 'event_id' => $event['id'], 'created_at' => current_time( 'mysql', true ) ) ) ) );
			self::notify_locked( array_merge( $data, array( 'participant_id' => $id, 'registration_id' => $registration_id, 'buyer_email' => $wpdb->get_var( $wpdb->prepare( "SELECT buyer_email FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $registration_id ) ) ) ), 'Spostamenti: gestisci il tuo viaggio', 'Consulta il tuo viaggio, gli eventuali passaggi assegnati e le proposte. Il contributo per il passaggio, andata e ritorno, viene corrisposto direttamente al conducente ed è separato dalle eventuali quote dell’evento.' );
		}
	}
	private static function token( $id ) { return hash_hmac( 'sha256', 'mi-travel|' . (int) $id, wp_salt( 'auth' ) ); }
	public static function url( $id ) { return add_query_arg( array( 'mi_travel' => (int) $id, 'travel_token' => self::token( $id ) ), home_url( '/' ) ); }
	public static function pending_email( $event_id, array $management ) {
		$message = 'La partecipazione è subordinata all’assegnazione dei passaggi richiesti. Il posto all’evento è riservato fino al termine delle assegnazioni. Durante l’attesa non è richiesto alcun pagamento dell’evento. Le proposte di passaggio riservano il posto auto per 24 ore dall’invio. Il contributo A/R viene versato direttamente al conducente.';
		$html = '<p>' . esc_html( $message ) . '</p>';
		foreach ( $management as $person ) $html .= '<p>' . esc_html( $person['name'] ) . ': <a href="' . esc_url( $person['url'] ) . '">Annulla partecipazione</a></p>';
		return MI_Modello_Email::crea_istantanea_istituzionale( $event_id, 'Partecipazione in attesa di passaggio', 'Richiesta registrata: attendi il passaggio.', $html, $message );
	}
	private static function notify_locked( array $row, $subject, $message ) {
		if ( empty( $row['buyer_email'] ) ) return;
		global $wpdb;
		$html = '<p>' . esc_html( $message ) . '</p><p><a href="' . esc_url( self::url( $row['participant_id'] ) ) . '">Gestisci gli spostamenti</a></p>';
		$event_id = ! empty( $row['event_id'] ) ? (int) $row['event_id'] : (int) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $row['registration_id'] ) ); self::check();
		$snapshot = MI_Modello_Email::crea_istantanea_istituzionale( $event_id, $subject, $subject, $html, $message . "\n" . self::url( $row['participant_id'] ), array( array( 'label' => 'Gestisci gli spostamenti', 'url' => self::url( $row['participant_id'] ) ) ) );
		$status = MI_Spedizione_Email::stato_nuova_email( $snapshot );
		self::check( $wpdb->insert( $wpdb->prefix . 'mi_email_outbox', array( 'registration_id' => $row['registration_id'], 'recipient' => $row['buyer_email'], 'template_type' => 'TRAVEL_UPDATE', 'payload_json' => wp_json_encode( array( 'email_preview' => $snapshot ) ), 'status' => $status, 'created_at' => current_time( 'mysql', true ) ) ) );
	}
	public static function needs_ride_locked( $registration_id ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mi_travel t JOIN {$wpdb->prefix}mi_participants p ON p.id=t.participant_id WHERE t.registration_id=%d AND p.status='ACTIVE' AND t.required_ride=1 AND t.state<>'ASSIGNED'", $registration_id ) ); self::check();
		return (int) $value > 0;
	}
	private static function describe( array $row ) {
		return $row['origin'] . ' · ' . wp_date( 'd/m/Y H:i', strtotime( $row['departure_at'] . ' UTC' ), wp_timezone() ) . ' · €' . number_format( (int) $row['price_cents'] / 100, 2, ',', '.' ) . ' per persona, andata e ritorno, direttamente al conducente';
	}
	private static function driver_contact_text( array $driver ) {
		$details = array();
		if ( ! empty( $driver['buyer_email'] ) ) $details[] = 'email ' . sanitize_email( $driver['buyer_email'] );
		if ( ! empty( $driver['buyer_phone'] ) ) $details[] = 'telefono ' . sanitize_text_field( $driver['buyer_phone'] );
		return $details ? ' Per accordare i dettagli del viaggio, contatta il referente della prenotazione: ' . implode( ' · ', $details ) . '.' : '';
	}
	/** Il chiamante mantiene il lock evento nella stessa transazione dell'iscrizione. */
	public static function maintain_locked( $event_id ) {
		$config = self::config( $event_id );
		if ( empty( $config['enabled'] ) ) return;
		$deadline = self::local_timestamp( $config['deadline'] );
		$rows = self::rows( $event_id, true );
		$by_id = array_column( $rows, null, 'participant_id' );
		$changed = array();
		foreach ( $rows as &$row ) {
			if ( ! in_array( $row['state'], array( 'ASSIGNED', 'OFFERED' ), true ) ) continue;
			$driver = $by_id[$row['driver_id']] ?? null;
			if ( ! self::admitted( $row ) ) {
				$row = array_merge( $row, array( 'state' => 'CLOSED', 'driver_id' => null, 'offer_expires_at' => null ) );
			} elseif ( ! $driver || ! self::driver_active( $driver ) ) {
				$row = array_merge( $row, array( 'state' => 'WAITING', 'driver_id' => null, 'offer_expires_at' => null ) );
				self::notify_locked( $row, 'Passaggio non più disponibile', 'Il passaggio è stato ritirato. Cerchiamo un’altra disponibilità. Contatta la segreteria se devi modificare la partecipazione.' );
			} elseif ( 'OFFERED' === $row['state'] && strtotime( $row['offer_expires_at'] . ' UTC' ) <= time() ) {
				$row = array_merge( $row, array( 'state' => 'EXPIRED', 'driver_id' => null, 'offer_expires_at' => null ) );
				self::notify_locked( $row, 'Proposta di passaggio scaduta', 'Il termine di 24 ore è trascorso: il posto è nuovamente disponibile. Puoi rientrare in lista dal collegamento personale, entro il termine dei passaggi.' );
			} else continue;
			self::write( $row['participant_id'], array_intersect_key( $row, array_flip( array( 'state', 'driver_id', 'offer_expires_at' ) ) ) );
			self::audit_locked( $row, 'TRAVEL_' . $row['state'] );
			$changed[$row['registration_id']] = true;
		}
		unset( $row );
		// Un ritiro del conducente sospende nuovamente le partecipazioni subordinate al passaggio.
		foreach ( $rows as $row ) if ( self::admitted( $row ) && ( 'TRAVEL_PENDING' === $row['booking_status'] || ( ! empty( $row['required_ride'] ) && 'ASSIGNED' !== $row['state'] ) ) ) $changed[$row['registration_id']] = true;
		foreach ( array_keys( $changed ) as $id ) self::refresh_booking_locked( $id );
		$rows = self::rows( $event_id, true );
		// Una proposta concede sempre 24 ore complete e non supera il termine finale.
		if ( ! $deadline || time() + self::OFFER_SECONDS > $deadline || get_post_meta( $event_id, '_mi_event_cancellation_job', true ) || get_post_meta( $event_id, '_mi_event_cancelled_at', true ) ) return;
		$occupied = self::seat_counts( $rows ); $drivers = array(); $available = array();
		foreach ( $rows as $candidate ) if ( self::driver_active( $candidate ) ) {
			$free = (int) $candidate['seats'] - ( $occupied[$candidate['participant_id']] ?? 0 );
			if ( $free > 0 ) { $drivers[] = $candidate; $available[$candidate['participant_id']] = $free; }
		}
		foreach ( $rows as &$row ) {
			if ( 'NEED' !== $row['mode'] || 'WAITING' !== $row['state'] || ! self::admitted( $row ) ) continue;
			foreach ( $drivers as $driver ) {
				if ( ( $available[$driver['participant_id']] ?? 0 ) < 1 || (int) $driver['participant_id'] === (int) $row['participant_id'] ) continue;
				$data = array( 'state' => 'OFFERED', 'driver_id' => $driver['participant_id'], 'price_cents' => $driver['price_cents'], 'origin' => $driver['origin'], 'departure_at' => $driver['departure_at'], 'offer_expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::OFFER_SECONDS ) );
				self::write( $row['participant_id'], $data ); $row = array_merge( $row, $data );
				self::notify_locked( $row, 'Passaggio disponibile: hai 24 ore per rispondere', self::describe( $row ) . '.' . self::driver_contact_text( $driver ) . ' Accetta o rifiuta entro ' . wp_date( 'd/m/Y H:i', strtotime( $row['offer_expires_at'] . ' UTC' ), wp_timezone() ) . '. Il posto è riservato a te fino alla scadenza.' );
				self::audit_locked( $row, 'TRAVEL_OFFERED' );
				--$available[$driver['participant_id']];
				break;
			}
		}
		unset( $row );
	}
	private static function audit_locked( array $row, $type ) {
		if ( ! MI_Registration_Service::append_registration_event( $row['registration_id'], $type, '', '', 'TRAVEL', array( 'participant_id' => (int) $row['participant_id'], 'state' => $row['state'], 'driver_id' => $row['driver_id'], 'price_cents' => (int) $row['price_cents'], 'offer_expires_at' => $row['offer_expires_at'] ) ) ) throw new RuntimeException( 'Aggiornamento passaggio non registrato.' );
		MI_Registration_Service::mark_workspace_changed_locked( $row['registration_id'] );
	}
	private static function refresh_booking_locked( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_registrations WHERE id=%d FOR UPDATE", $id ), ARRAY_A ); self::check();
		if ( ! $row || ! in_array( $row['status'], array( 'CONFIRMED', 'PENDING_PAYMENT', 'TRAVEL_PENDING' ), true ) ) return;
		$pending = self::needs_ride_locked( $id );
		if ( $pending && 'TRAVEL_PENDING' !== $row['status'] ) {
			self::check( $wpdb->update( $wpdb->prefix . 'mi_registrations', array( 'status' => 'TRAVEL_PENDING', 'initial_due_cents' => 0, 'balance_cents' => $row['total_cents'], 'expires_at' => null, 'payment_deadline_at' => null ), array( 'id' => $id ) ) );
		} elseif ( ! $pending && 'TRAVEL_PENDING' === $row['status'] ) {
			$snapshot = json_decode( $row['snapshot_json'], true ); $event = (array) ( $snapshot['event'] ?? array() );
			$event['economic_mode'] = $row['economic_mode'];
			$payments = self::check( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mi_payments WHERE registration_id=%d ORDER BY id", $id ), ARRAY_A ) );
			$position = MI_Payment_People::read( $row, $payments );
			if ( empty( $position['quotes_known'] ) || empty( $position['payments_known'] ) ) throw new RuntimeException( 'Quote del partecipante non disponibili.' );
			$economic = MI_Registration_Service::riepilogo_economico( $event, (int) $row['total_cents'], 'PENDING_PAYMENT', count( $position['people'] ), array_column( $position['people'], 'total' ) );
			// La copertura è individuale: il credito di una persona non paga la quota di un'altra.
			$repriced = MI_Payment_People::read( array_merge( $row, array( 'initial_due_cents' => $economic['initial_due_cents'] ) ), $payments );
			$covered = MI_Payment_People::covered( $repriced, $row['economic_mode'] );
			if ( ! in_array( $row['economic_mode'], array( 'FULL_PAYMENT', 'DEPOSIT_BALANCE' ), true ) ) $covered = true;
			if ( null === $covered ) throw new RuntimeException( 'Copertura dei pagamenti non disponibile.' );
			$target = $covered ? 'CONFIRMED' : 'PENDING_PAYMENT';
			$deadline = null;
			if ( 'PENDING_PAYMENT' === $target ) {
				$common = self::local_timestamp( $event['payment_deadline_at'] ?? '' );
				if ( $common || ! empty( $event['reservation_minutes'] ) ) $deadline = gmdate( 'Y-m-d H:i:s', max( $common ?: 0, time() + max( 60, (int) ( $event['reservation_minutes'] ?? 0 ) * 60, (int) ( $event['reopened_payment_hours'] ?? 48 ) * 3600 ) ) );
			}
			self::check( $wpdb->update( $wpdb->prefix . 'mi_registrations', array( 'status' => $target, 'initial_due_cents' => $economic['initial_due_cents'], 'balance_cents' => $economic['balance_cents'], 'expires_at' => $deadline, 'payment_deadline_at' => $deadline ), array( 'id' => $id ) ) );
			if ( class_exists( 'MI_Management_Service' ) ) MI_Management_Service::auto_assign_rooms_locked( $id );
			$travel_row = array( 'participant_id' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT participant_id FROM {$wpdb->prefix}mi_travel WHERE registration_id=%d ORDER BY participant_id LIMIT 1", $id ) ), 'registration_id' => $id, 'buyer_email' => $row['buyer_email'] );
			$message = 'Tutti i passaggi necessari sono assegnati. ' . ( 'PENDING_PAYMENT' === $target ? 'La partecipazione è ora da pagare. Consulta il riepilogo dell’evento: ' . MI_Portal::status_url( $id, $row['order_code'], $row['buyer_email'] ) : 'La partecipazione è confermata.' );
			self::notify_locked( $travel_row, 'Partecipazione aggiornata', $message );
		} else return;
		MI_Registration_Service::mark_workspace_changed_locked( $id );
		if ( ! MI_Registration_Service::append_registration_event( $id, 'TRAVEL_PARTICIPATION_CHANGED', $row['status'], $pending ? 'TRAVEL_PENDING' : $target, 'TRAVEL' ) ) throw new RuntimeException( 'Stato partecipazione non registrato.' );
	}
	public static function routes() {
		register_rest_route( MI_REST_Controller::NAMESPACE, '/events/(?P<id>\d+)/travel', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'availability' ) ) );
	}
	public static function availability( $request ) {
		$event = MI_Registration_Service::public_event( absint( $request['id'] ) );
		if ( is_wp_error( $event ) ) return $event;
		$items = array();
		try {
			if ( ! empty( $event['travel']['enabled'] ) && self::local_timestamp( $event['travel']['deadline'] ) > time() && ! get_post_meta( $event['id'], '_mi_event_cancelled_at', true ) ) {
				$rows = self::rows( $event['id'] ); $occupied = self::seat_counts( $rows );
				foreach ( $rows as $row ) {
					$free = (int) $row['seats'] - ( $occupied[$row['participant_id']] ?? 0 );
					if ( self::driver_active( $row ) && $free > 0 ) $items[] = array( 'id' => (int) $row['participant_id'], 'origin' => $row['origin'], 'departure' => wp_date( 'd/m/Y H:i', strtotime( $row['departure_at'] . ' UTC' ), wp_timezone() ), 'seats' => $free, 'price_cents' => (int) $row['price_cents'] );
				}
			}
			$response = new WP_REST_Response( array( 'rides' => $items ) ); $response->header( 'Cache-Control', 'no-store' ); return $response;
		} catch ( Throwable $error ) { return new WP_Error( 'mi_travel_unavailable', 'Disponibilità passaggi non disponibile. Riprova.', array( 'status' => 503 ) ); }
	}
	public static function tick() {
		global $wpdb;
		$events = $wpdb->get_col( "SELECT DISTINCT t.event_id FROM {$wpdb->prefix}mi_travel t JOIN {$wpdb->prefix}mi_registrations r ON r.id=t.registration_id WHERE t.state IN ('WAITING','OFFERED','ASSIGNED') OR r.status='TRAVEL_PENDING' ORDER BY t.event_id" );
		foreach ( (array) $events as $event ) {
			self::maintain( $event );
			$config = self::config( $event );
			if ( ! $config['enabled'] || ! self::local_timestamp( $config['deadline'] ) || self::local_timestamp( $config['deadline'] ) > time() ) continue;
			$pending = $wpdb->get_col( $wpdb->prepare( "SELECT t.participant_id FROM {$wpdb->prefix}mi_travel t JOIN {$wpdb->prefix}mi_registrations r ON r.id=t.registration_id JOIN {$wpdb->prefix}mi_participants p ON p.id=t.participant_id WHERE t.event_id=%d AND t.required_ride=1 AND t.state<>'ASSIGNED' AND r.status='TRAVEL_PENDING' AND p.status='ACTIVE'", $event ) );
			foreach ( (array) $pending as $id ) MI_Registration_Service::cancel_participant( $id, 'TRAVEL_DEADLINE' );
		}
	}
	public static function maintain( $event_id ) {
		global $wpdb;
		try {
			if ( class_exists( 'MI_Event_Deletion' ) && is_wp_error( MI_Event_Deletion::enter( $event_id ) ) ) return;
			self::check( $wpdb->query( 'START TRANSACTION' ) );
			MI_Management_Service::lock_room_event( $event_id );
			self::maintain_locked( $event_id );
			self::check( $wpdb->query( 'COMMIT' ) );
		} catch ( Throwable $error ) { $wpdb->query( 'ROLLBACK' ); }
		try { MI_Spedizione_Email::pianifica_spedizione(); } catch ( Throwable $ignored ) { /* Il cron recupera la coda persistente. */ }
	}
	public static function decide( $id, $token, $action, $expected_driver = 0, $expected_expiry = '' ) {
		if ( ! is_string( $token ) || ! hash_equals( self::token( $id ), $token ) ) return new WP_Error( 'mi_travel_token', 'Collegamento non valido.' );
		global $wpdb;
		$event_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}mi_travel WHERE participant_id=%d", $id ) );
		try {
			if ( class_exists( 'MI_Event_Deletion' ) && is_wp_error( MI_Event_Deletion::enter( $event_id ) ) ) throw new RuntimeException( 'Evento non disponibile.' );
			self::check( $wpdb->query( 'START TRANSACTION' ) ); MI_Management_Service::lock_room_event( $event_id );
			$config = self::config( $event_id );
			if ( ! $config['enabled'] || self::local_timestamp( $config['deadline'] ) <= time() || get_post_meta( $event_id, '_mi_event_cancellation_job', true ) || get_post_meta( $event_id, '_mi_event_cancelled_at', true ) ) throw new InvalidArgumentException( 'La gestione dei passaggi è chiusa.' );
			$rows = self::rows( $event_id, true ); $row = null;
			foreach ( $rows as $entry ) if ( (int) $entry['participant_id'] === (int) $id ) $row = $entry;
			if ( ! $row || ! self::admitted( $row ) ) throw new InvalidArgumentException( 'La partecipazione non è attiva o è ancora nella lista d’attesa dell’evento.' );
			if ( in_array( $action, array( 'accept', 'decline' ), true ) ) {
				if ( 'OFFERED' !== $row['state'] || strtotime( $row['offer_expires_at'] . ' UTC' ) <= time() || (int) $row['driver_id'] !== (int) $expected_driver || $row['offer_expires_at'] !== $expected_expiry ) throw new InvalidArgumentException( 'La proposta è cambiata o è scaduta. Ricarica la pagina.' );
				$driver = array_column( $rows, null, 'participant_id' )[$row['driver_id']] ?? null;
				if ( ! $driver || ! self::driver_active( $driver ) ) throw new InvalidArgumentException( 'Il passaggio non è più disponibile.' );
				$row['state'] = 'accept' === $action ? 'ASSIGNED' : 'DECLINED';
				if ( 'accept' === $action ) {
					self::notify_locked( $driver, 'Passaggio accettato', $row['first_name'] . ' ' . $row['last_name'] . ' ha accettato il passaggio proposto. Consulta il viaggio per i contatti.' );
					self::notify_locked( $row, 'Passaggio assegnato', self::describe( $row ) . '. Consulta il viaggio per i contatti del conducente.' );
				}
				if ( 'decline' === $action ) $row['driver_id'] = null;
			} elseif ( 'rejoin' === $action && 'NEED' === $row['mode'] && in_array( $row['state'], array( 'DECLINED', 'EXPIRED' ), true ) ) {
				$row['state'] = 'WAITING'; $row['driver_id'] = null;
				self::write( $id, array( 'created_at' => current_time( 'mysql', true ) ) );
			} elseif ( 'withdraw' === $action && 'DRIVER' === $row['mode'] ) {
				self::write( $id, array( 'seats' => 0 ) );
			} elseif ( 'own' === $action && 'NEED' === $row['mode'] ) {
				$row['state'] = 'NONE'; $row['driver_id'] = null; $row['mode'] = 'OWN'; $row['required_ride'] = 0;
			} else throw new InvalidArgumentException( 'Scelta non disponibile.' );
			$row['offer_expires_at'] = null;
			self::write( $id, array_intersect_key( $row, array_flip( array( 'state', 'driver_id', 'mode', 'required_ride', 'offer_expires_at' ) ) ) );
			self::audit_locked( $row, 'TRAVEL_' . strtoupper( $action ) );
			self::refresh_booking_locked( $row['registration_id'] );
			self::maintain_locked( $event_id );
			self::check( $wpdb->query( 'COMMIT' ) );
			try { MI_Spedizione_Email::pianifica_spedizione(); } catch ( Throwable $ignored ) {}
			return true;
		} catch ( Throwable $error ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'mi_travel_decision', $error instanceof InvalidArgumentException ? $error->getMessage() : 'Aggiornamento non riuscito. Riprova.' ); }
	}
	public static function route() {
		$id = absint( $_GET['mi_travel'] ?? 0 ); if ( ! $id ) return;
		nocache_headers(); header( 'Referrer-Policy: no-referrer' ); header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		$token = sanitize_text_field( wp_unslash( $_GET['travel_token'] ?? '' ) );
		if ( ! hash_equals( self::token( $id ), $token ) ) wp_die( 'Collegamento non valido.', '', array( 'response' => 403 ) );
		global $wpdb;
		$event_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}mi_travel WHERE participant_id=%d", $id ) );
		if ( ! $event_id ) wp_die( 'Viaggio non disponibile.', '', array( 'response' => 404 ) );
		$message = '';
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			check_admin_referer( 'mi_travel_' . $id );
			$result = self::decide( $id, $token, sanitize_key( wp_unslash( $_POST['travel_action'] ?? '' ) ), absint( $_POST['driver_id'] ?? 0 ), sanitize_text_field( wp_unslash( $_POST['offer_expires_at'] ?? '' ) ) );
			$message = is_wp_error( $result ) ? $result->get_error_message() : 'Spostamento aggiornato.';
		}
		self::maintain( $event_id );
		$rows = self::rows( $event_id ); $row = array_column( $rows, null, 'participant_id' )[$id] ?? null;
		if ( ! $row ) wp_die( 'Viaggio non disponibile.' );
		$config = self::config( $event_id );
		$closed = self::local_timestamp( $config['deadline'] ) <= time() || get_post_meta( $event_id, '_mi_event_cancelled_at', true );
		echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>I tuoi spostamenti</title><style>body{font:17px/1.6 system-ui;background:#f4f6fa;color:#151b38;margin:0}main{max-width:720px;margin:32px auto;padding:24px;background:white;border-radius:12px}button{font:inherit;padding:10px 16px;margin:8px;border:1px solid #337ab7;border-radius:6px;background:#fff;cursor:pointer}button:focus-visible,a:focus-visible{outline:3px solid #337ab7}label{display:block}table{width:100%}td,th{text-align:left;padding:8px;border-bottom:1px solid #ddd}</style></head><body><main><h1>I tuoi spostamenti</h1><h2>' . esc_html( get_the_title( $event_id ) ) . '</h2><p>' . esc_html( $row['first_name'] . ' ' . $row['last_name'] ) . '</p>';
		if ( $message ) echo '<p role="status">' . esc_html( $message ) . '</p>';
		echo '<p>' . nl2br( esc_html( $config['instructions'] ) ) . '</p><p>Il contributo per il passaggio comprende andata e ritorno ed è versato direttamente al conducente, separatamente dalle eventuali quote dell’evento.</p>';
		$labels = array( 'WAITING' => 'In lista d’attesa per un passaggio', 'OFFERED' => 'Passaggio proposto: risposta richiesta entro 24 ore', 'ASSIGNED' => 'Passaggio assegnato', 'DECLINED' => 'Proposta rifiutata', 'EXPIRED' => 'Proposta scaduta', 'CLOSED' => 'Passaggio chiuso', 'NONE' => 'Viaggio autonomo' );
		echo '<p><strong>' . esc_html( 'DRIVER' === $row['mode'] ? 'Posti offerti: ' . $row['seats'] : ( $labels[$row['state']] ?? $row['state'] ) ) . '</strong></p>';
		if ( 'TRAVEL_PENDING' === $row['booking_status'] ) echo '<p>La partecipazione è subordinata all’assegnazione di tutti i passaggi richiesti nella prenotazione. Il posto all’evento è riservato fino al termine dei passaggi. Nessun pagamento dell’evento è richiesto durante questa attesa.</p>';
		if ( in_array( $row['booking_status'], array( 'WAITLISTED', 'WAITLIST_OFFERED' ), true ) ) echo '<p>Occorre prima completare l’ammissione all’evento; i passaggi saranno cercati successivamente.</p>';
		if ( in_array( $row['state'], array( 'ASSIGNED', 'OFFERED' ), true ) ) {
			echo '<p>' . esc_html( self::describe( $row ) ) . '</p>';
			if ( 'OFFERED' === $row['state'] ) echo '<p>Scadenza: ' . esc_html( wp_date( 'd/m/Y H:i', strtotime( $row['offer_expires_at'] . ' UTC' ), wp_timezone() ) ) . '</p>';
			if ( 'ASSIGNED' === $row['state'] ) {
				$driver = array_column( $rows, null, 'participant_id' )[$row['driver_id']] ?? null;
				if ( $driver ) echo '<p>Conducente: ' . esc_html( $driver['first_name'] . ' ' . $driver['last_name'] ) . ' · Contatto del referente: <a href="mailto:' . esc_attr( $driver['buyer_email'] ) . '">' . esc_html( $driver['buyer_email'] ) . '</a></p>';
			}
		}
		if ( 'DRIVER' === $row['mode'] ) foreach ( $rows as $passenger ) if ( (int) $passenger['driver_id'] === $id && 'ASSIGNED' === $passenger['state'] && self::admitted( $passenger ) ) echo '<p>Passeggero: ' . esc_html( $passenger['first_name'] . ' ' . $passenger['last_name'] ) . ' · ' . esc_html( $passenger['buyer_email'] ) . '</p>';
		if ( ! $closed && self::admitted( $row ) ) {
			echo '<form method="post">'; wp_nonce_field( 'mi_travel_' . $id );
			echo '<input type="hidden" name="driver_id" value="' . esc_attr( $row['driver_id'] ) . '"><input type="hidden" name="offer_expires_at" value="' . esc_attr( $row['offer_expires_at'] ) . '">';
			if ( 'OFFERED' === $row['state'] ) echo '<button name="travel_action" value="accept">Accetta il passaggio</button><button name="travel_action" value="decline">Rifiuta</button>';
			if ( 'NEED' === $row['mode'] && in_array( $row['state'], array( 'DECLINED', 'EXPIRED' ), true ) ) echo '<button name="travel_action" value="rejoin">Rientra in lista d’attesa auto</button>';
			if ( 'NEED' === $row['mode'] ) echo '<button name="travel_action" value="own">Partecipo organizzandomi autonomamente</button>';
			if ( 'DRIVER' === $row['mode'] && (int) $row['seats'] ) echo '<p>Ritirando i posti, i passeggeri assegnati saranno avvisati e torneranno in attesa di un altro passaggio.</p><button name="travel_action" value="withdraw">Ritira i posti offerti</button>';
			echo '</form>';
		}
		if ( $closed ) echo '<p>Il termine per le assegnazioni è trascorso.</p>';
		if ( in_array( $row['booking_status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ) echo '<p><a href="' . esc_url( MI_Portal::status_url( $row['registration_id'], $row['order_code'], $row['buyer_email'] ) ) . '">Consulta la partecipazione e le eventuali quote dell’evento</a></p>';
		echo '</main></body></html>'; exit;
	}
	public static function render_config( $event_id ) {
		$c = self::config( $event_id );
		echo '<div class="mi-travel-settings"><label class="mi-check"><input type="checkbox" name="travel_enabled" value="1" ' . checked( $c['enabled'], true, false ) . '> Passaggi condivisi in auto</label><div data-mi-travel-settings-details><p>Raccogli offerte e richieste di passaggio tra partecipanti.</p><details class="mi-travel-waitlist-rules"><summary>Come funziona l’attesa di un passaggio?</summary><p>Dopo l’ammissione all’evento, il sistema propone automaticamente i passaggi disponibili alle persone in attesa. Il passaggio viene assegnato quando la persona accetta la proposta. Se una persona può partecipare solo trovando un passaggio, il posto all’evento resta riservato. Non viene richiesto il pagamento finché non sono state soddisfatte le richieste di passaggio indispensabili per quella prenotazione. Se il passaggio non si trova entro il termine, la partecipazione della persona viene annullata.</p></details><label>Indicazioni di viaggio e ritrovo<textarea name="travel_instructions" maxlength="3000" placeholder="Es. Ritrovo in piazza">' . esc_textarea( $c['instructions'] ) . '</textarea></label><label>Contributo per il passaggio<select name="travel_price_mode"><option value="FIXED" ' . selected( $c['price_mode'], 'FIXED', false ) . '>Importo fisso stabilito dall’organizzatore</option><option value="DRIVER" ' . selected( $c['price_mode'], 'DRIVER', false ) . '>Importo stabilito dal conducente</option></select></label><label>Contributo fisso per persona (€), andata e ritorno<input name="travel_price" inputmode="decimal" value="' . esc_attr( number_format( $c['price_cents'] / 100, 2, ',', '' ) ) . '"></label><p>È ammesso zero. Il contributo viene versato direttamente al conducente e non entra nei pagamenti dell’evento.</p><label>Termine per completare le assegnazioni<input name="travel_deadline" placeholder="gg/mm/aaaa hh:mm" value="' . esc_attr( $c['deadline'] ? wp_date( 'd/m/Y H:i', self::local_timestamp( $c['deadline'] ), wp_timezone() ) : '' ) . '"></label><p>Ogni proposta riserva un posto auto per 24 ore. L’ultima proposta automatica parte almeno 24 ore prima del termine, che deve precedere l’inizio dell’evento.</p></div></div>';
	}
	public static function render_management( $event_id ) {
		if ( ! MI_Access::can_access_event( $event_id ) || ! self::config( $event_id )['enabled'] ) return;
		echo '<details class="mi-travel-management"><summary>Spostamenti e passaggi in auto</summary><p>Contributi versati direttamente ai conducenti. Per modifiche operative apri il viaggio del partecipante.</p><div style="overflow-x:auto"><table><thead><tr><th>Partecipante</th><th>Viaggio</th><th>Località</th><th>Posti offerti</th><th>Stato</th><th>Contributo A/R</th><th>Gestione</th></tr></thead><tbody>';
		foreach ( self::rows( $event_id ) as $row ) echo '<tr><td>' . esc_html( $row['first_name'] . ' ' . $row['last_name'] ) . '</td><td>' . esc_html( array( 'OWN' => 'Autonomo', 'DRIVER' => 'Conducente', 'NEED' => 'Cerca passaggio' )[$row['mode']] ?? '' ) . '</td><td>' . esc_html( $row['origin'] ) . '</td><td>' . esc_html( $row['seats'] ) . '</td><td>' . esc_html( self::state_label( $row['state'] ) . ( $row['required_ride'] ? ' · partecipa solo con passaggio' : '' ) ) . '</td><td>' . esc_html( number_format( $row['price_cents'] / 100, 2, ',', '.' ) . ' €' ) . '</td><td><a href="' . esc_url( self::url( $row['participant_id'] ) ) . '">Apri viaggio</a></td></tr>';
		echo '</tbody></table></div></details>';
	}
}
