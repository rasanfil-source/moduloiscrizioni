<?php
defined( 'ABSPATH' ) || exit;

/** Passaggi privati: inventario distinto dai servizi e dal registro degli incassi. */
final class MI_Travel {
	const OFFER_SECONDS = 86400;
	const LOST_RIDE_DECISION_SECONDS = 86400;
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
			decision_deadline_at datetime NULL,
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
		if ( 'OWN' === $row['mode'] ) {
			$row['origin'] = '';
			$row['departure_at'] = null;
		}
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
					$choice['route_key'] = $assignments[$assignment_index]['route_key'] ?? '';
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
			if ( ! in_array( $mode, array( 'OWN', 'DRIVER', 'NEED' ), true ) ) return new WP_Error( 'mi_travel_choice', 'Scegli una modalità di spostamento valida.', array( 'status' => 400 ) );
			$deadline = self::local_timestamp( $config['deadline'] ?? '' );
			$requests_closed = false === $deadline || time() >= $deadline - self::OFFER_SECONDS;
			if ( $requests_closed && ( 'NEED' === $mode || ( 'DRIVER' === $mode && ! empty( $choice['seats'] ) ) ) ) return new WP_Error( 'mi_travel_closed', 'Le nuove richieste e offerte di passaggio chiudono 24 ore prima del termine, per lasciare alle persone il tempo di rispondere. Puoi partecipare organizzandoti autonomamente.', array( 'status' => 409 ) );
			$travel = array( 'mode' => $mode, 'seats' => 0, 'price_cents' => 0, 'origin' => '', 'departure_at' => null, 'required_ride' => 0, 'driver_id' => null, 'state' => 'NONE' );
			if ( ( 'NEED' === $mode || ( 'DRIVER' === $mode && ! empty( $choice['seats'] ) ) ) && ! is_email( $email ) ) return new WP_Error( 'mi_travel_email', 'Per offrire o richiedere passaggi occorre l’email del referente.', array( 'status' => 400 ) );
			if ( 'DRIVER' === $mode ) {
				$seats = filter_var( $choice['seats'] ?? null, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0, 'max_range' => 8 ) ) );
				if ( false === $seats ) return new WP_Error( 'mi_travel_seats', 'Indica da 0 a 8 posti disponibili per altre persone.', array( 'status' => 400 ) );
				$travel['seats'] = $seats;
				if ( $seats ) {
					$origin = mb_substr( sanitize_text_field( $choice['origin'] ?? '' ), 0, 180 );
					if ( '' === $origin ) $origin = mb_substr( sanitize_text_field( $event['event_location'] ?? '' ), 0, 180 );
					$input = trim( (string) ( $choice['departure'] ?? '' ) );
					$departure = self::local_timestamp( '' === $input ? ( $event['event_starts_at'] ?? '' ) : $input );
					$price = 'FIXED' === $config['price_mode'] ? $config['price_cents'] : MI_Amount::cents( $choice['price'] ?? '' );
					if ( ! $origin || ! $departure || $departure <= self::local_timestamp( $config['deadline'] ) || $departure > self::local_timestamp( $event['event_starts_at'] ) || null === $price || $price > 100000000 ) return new WP_Error( 'mi_travel_driver', 'Indica luogo di partenza, orario successivo al termine dei passaggi e precedente all’evento, e un contributo valido.', array( 'status' => 400 ) );
					$travel = array_merge( $travel, array( 'origin' => $origin, 'departure_at' => gmdate( 'Y-m-d H:i:s', $departure ), 'price_cents' => $price ) );
				}
			}
			if ( 'NEED' === $mode ) {
				$driver_id = absint( $choice['driver_id'] ?? 0 );
				$fallback = (string) ( $choice['fallback'] ?? '' );
				if ( ! in_array( $fallback, array( 'REQUIRED', 'ANYWAY' ), true ) ) return new WP_Error( 'mi_travel_fallback', 'Indica cosa fare se il passaggio assegnato non è più disponibile.', array( 'status' => 400 ) );
				$travel['required_ride'] = 'REQUIRED' === $fallback ? 1 : 0;
				$travel['driver_id'] = $driver_id ?: null;
				if ( $driver_id ) $travel['route_key'] = (string) ( $choice['route_key'] ?? '' );
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
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.*,p.first_name,p.last_name,p.status person_status,p.cancellation_actor,r.status booking_status,r.buyer_email,r.buyer_phone,r.order_code,r.capacity_released_at FROM {$wpdb->prefix}mi_travel t JOIN {$wpdb->prefix}mi_participants p ON p.id=t.participant_id JOIN {$wpdb->prefix}mi_registrations r ON r.id=t.registration_id WHERE t.event_id=%d ORDER BY t.created_at,t.participant_id" . ( $lock ? ' FOR UPDATE' : '' ), $event_id ), ARRAY_A );
		self::check();
		return $rows;
	}
	private static function admitted( array $row ) {
		return 'ACTIVE' === $row['person_status'] && empty( $row['capacity_released_at'] ) && in_array( $row['booking_status'], array( 'CONFIRMED', 'PENDING_PAYMENT', 'TRAVEL_PENDING' ), true );
	}
	private static function driver_active( array $row ) {
		return self::admitted( $row ) && 'TRAVEL_PENDING' !== $row['booking_status'] && 'DRIVER' === $row['mode'] && (int) $row['seats'] > 0;
	}
	private static function route_key( array $driver ) {
		return hash( 'sha256', wp_json_encode( array( $driver['origin'], $driver['departure_at'], (int) $driver['price_cents'] ) ) );
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
		$buyer_email = $wpdb->get_var( $wpdb->prepare( "SELECT buyer_email FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $registration_id ) ); self::check();
		$stored = $wpdb->get_results( $wpdb->prepare( "SELECT id,ticket_type_code,ticket_index FROM {$wpdb->prefix}mi_participants WHERE registration_id=%d", $registration_id ), ARRAY_A ); self::check();
		$ids = array(); foreach ( $stored as $row ) $ids[$row['ticket_type_code'] . ':' . $row['ticket_index']] = $row['id'];
		$booking = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $registration_id ) ); self::check();
		$management = array();
		$driver_notices = array();
		foreach ( $people as $person ) {
			$data = $person['travel'];
			$id = $ids[$person['ticket_type_code'] . ':' . $person['ticket_index']];
			if ( ! empty( $data['driver_id'] ) ) {
				$rows = self::rows( $event['id'], true ); $driver = null;
				foreach ( $rows as $row ) if ( (int) $row['participant_id'] === (int) $data['driver_id'] ) $driver = $row;
				if ( ! $driver || ! self::driver_active( $driver ) || self::route_key( $driver ) !== $data['route_key'] || self::free_seats( $driver, $rows ) < 1 || (int) $data['price_cents'] !== (int) $driver['price_cents'] ) throw new InvalidArgumentException( 'Il passaggio scelto non è più disponibile alle condizioni mostrate. Aggiorna le disponibilità.' );
				unset( $data['route_key'] );
				$data['origin'] = $driver['origin']; $data['departure_at'] = $driver['departure_at'];
			}
			if ( 'WAITLISTED' === $booking ) { $data['driver_id'] = null; if ( 'NEED' === $data['mode'] ) $data['state'] = 'WAITING'; }
			if ( ! empty( $data['driver_id'] ) && ! in_array( $booking, array( 'WAITLISTED', 'WAITLIST_OFFERED' ), true ) ) {
				if ( ! isset( $driver_notices[$driver['participant_id']] ) ) $driver_notices[$driver['participant_id']] = array( 'driver' => $driver, 'passengers' => array() );
				$driver_notices[$driver['participant_id']]['passengers'][] = trim( $person['first_name'] . ' ' . $person['last_name'] );
			}
			self::check( $wpdb->insert( $wpdb->prefix . 'mi_travel', array_merge( $data, array( 'participant_id' => $id, 'registration_id' => $registration_id, 'event_id' => $event['id'], 'created_at' => current_time( 'mysql', true ) ) ) ) );
			$audit_row = array_merge( $data, array( 'participant_id' => (int) $id, 'registration_id' => (int) $registration_id, 'event_id' => (int) $event['id'], 'offer_expires_at' => null ) );
			self::audit_locked( $audit_row, 'TRAVEL_REGISTERED', array(), 'PUBLIC_REGISTRATION' );
			$management[] = array( 'participant_id' => (int) $id, 'name' => trim( $person['first_name'] . ' ' . $person['last_name'] ) );
		}
		foreach ( $driver_notices as $notice ) {
			$passengers = array_values( array_unique( $notice['passengers'] ) );
			$message = 1 === count( $passengers ) ? $passengers[0] . ' ha scelto un posto sull’auto indicata.' : implode( ', ', $passengers ) . ' hanno scelto posti sull’auto indicata.';
			self::notify_locked( $notice['driver'], 'Nuovi passeggeri per il viaggio', $message . ' Il riepilogo del viaggio riporta i contatti e il riepilogo dei passeggeri.' );
		}
		if ( $management ) self::notify_locked(
			array( 'participant_id' => $management[0]['participant_id'], 'registration_id' => (int) $registration_id, 'event_id' => (int) $event['id'], 'buyer_email' => $buyer_email ),
			'Spostamenti: riepilogo del viaggio',
			'Dal collegamento è possibile consultare gli spostamenti, i passaggi assegnati e le proposte delle persone della prenotazione. Il contributo per il passaggio, andata e ritorno, viene corrisposto direttamente al conducente ed è separato dalle eventuali quote dell’evento.',
			$management
		);
	}
	private static function token( $id ) { return hash_hmac( 'sha256', 'mi-travel|' . (int) $id, wp_salt( 'auth' ) ); }
	public static function url( $id ) { return add_query_arg( array( 'mi_travel' => (int) $id, 'travel_token' => self::token( $id ) ), home_url( '/' ) ); }
	public static function pending_email( $event_id, array $management ) {
		$message = 'La partecipazione è subordinata all’assegnazione dei passaggi richiesti. Il posto all’evento è riservato fino al termine delle assegnazioni. Durante l’attesa non è richiesto alcun pagamento dell’evento. Ogni proposta di passaggio indica il termine entro cui accettare il posto auto riservato. Il contributo A/R viene versato direttamente al conducente. Dal collegamento personale è possibile gestire il passaggio, scegliere uno spostamento autonomo oppure annullare la partecipazione.';
		$html = '<p>' . esc_html( $message ) . '</p>';
		foreach ( $management as $person ) {
			$name = esc_html( $person['name'] ?? '' );
			$travel_url = ! empty( $person['participant_id'] ) ? self::url( (int) $person['participant_id'] ) : '';
			$html .= '<p>' . $name . ': <a href="' . esc_url( $travel_url ) . '">Gestione spostamenti e partecipazione</a>';
			if ( ! empty( $person['url'] ) ) $html .= ' · <a href="' . esc_url( $person['url'] ) . '">Annullamento della partecipazione</a>';
			$html .= '</p>';
		}
		return MI_Modello_Email::crea_istantanea_istituzionale( $event_id, 'Partecipazione in attesa di passaggio', 'Richiesta registrata: in attesa di passaggio.', $html, $message );
	}
	private static function notify_locked( array $row, $subject, $message, array $management = array() ) {
		if ( empty( $row['buyer_email'] ) ) return;
		global $wpdb;
		if ( ! $management ) $management = array( array( 'participant_id' => (int) $row['participant_id'], 'name' => '' ) );
		$html = implode( '', array_map( static function ( $paragraph ) { return '<p>' . esc_html( $paragraph ) . '</p>'; }, preg_split( '/\n+/', trim( (string) $message ) ) ) );
		$text = $message;
		$actions = array();
		if ( count( $management ) > 1 ) $html .= '<ul>';
		foreach ( $management as $person ) {
			$url = self::url( $person['participant_id'] );
			$label = trim( (string) ( $person['name'] ?? '' ) );
			$label = $label ? $label . ': ' : '';
			$html .= ( count( $management ) > 1 ? '<li>' : '<p>' ) . esc_html( $label ) . '<a href="' . esc_url( $url ) . '">Gestione degli spostamenti</a>' . ( count( $management ) > 1 ? '</li>' : '</p>' );
			$text .= "\n" . $label . $url;
			$actions[] = array( 'label' => $label ? rtrim( $label ) . ' — gestione degli spostamenti' : 'Gestione degli spostamenti', 'url' => $url );
		}
		if ( count( $management ) > 1 ) $html .= '</ul>';
		$event_id = ! empty( $row['event_id'] ) ? (int) $row['event_id'] : (int) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}mi_registrations WHERE id=%d", $row['registration_id'] ) ); self::check();
		$snapshot = MI_Modello_Email::crea_istantanea_istituzionale( $event_id, $subject, $subject, $html, $text, $actions );
		$status = MI_Spedizione_Email::stato_nuova_email( $snapshot );
		self::check( $wpdb->insert( $wpdb->prefix . 'mi_email_outbox', array( 'registration_id' => $row['registration_id'], 'recipient' => $row['buyer_email'], 'template_type' => 'TRAVEL_UPDATE', 'payload_json' => wp_json_encode( array( 'email_preview' => $snapshot ) ), 'status' => $status, 'created_at' => current_time( 'mysql', true ) ) ) );
	}
	private static function queue_update( array &$queue, array $row, $subject, $message ) {
		$key = (int) $row['registration_id'];
		if ( ! isset( $queue[$key] ) ) $queue[$key] = array( 'row' => $row, 'subject' => $subject, 'messages' => array(), 'management' => array() );
		elseif ( $queue[$key]['subject'] !== $subject ) $queue[$key]['subject'] = 'Aggiornamento dei passaggi';
		$name = trim( (string) ( $row['first_name'] ?? '' ) . ' ' . (string) ( $row['last_name'] ?? '' ) );
		$queue[$key]['messages'][] = ( $name ? $name . ': ' : '' ) . $message;
		$queue[$key]['management'][(int) $row['participant_id']] = array( 'participant_id' => (int) $row['participant_id'], 'name' => $name );
	}
	private static function flush_updates( array $queue ) {
		foreach ( $queue as $notice ) self::notify_locked( $notice['row'], $notice['subject'], implode( "\n", array_values( array_unique( $notice['messages'] ) ) ), array_values( $notice['management'] ) );
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
		$notifications = array();
		foreach ( $rows as &$row ) {
			if ( ! in_array( $row['state'], array( 'ASSIGNED', 'OFFERED', 'WAITING' ), true ) ) continue;
			$driver = $by_id[$row['driver_id']] ?? null;
			$audit_extra = array();
			if ( 'ACTIVE' === $row['person_status'] && in_array( $row['booking_status'], array( 'WAITLISTED', 'WAITLIST_OFFERED' ), true ) ) continue;
			if ( ! self::admitted( $row ) ) {
				if ( 'ASSIGNED' === $row['state'] && ! empty( $row['driver_id'] ) && isset( $by_id[$row['driver_id']] ) && self::driver_active( $by_id[$row['driver_id']] ) ) self::queue_update( $notifications, $by_id[$row['driver_id']], 'Posto auto liberato', $row['first_name'] . ' ' . $row['last_name'] . ' non partecipa più all’evento; il posto sull’auto indicata è libero.' );
				$previous_state = $row['state']; $previous_driver_id = $row['driver_id'];
				$row = array_merge( $row, array( 'state' => 'CLOSED', 'driver_id' => null, 'offer_expires_at' => null, 'decision_deadline_at' => null ) );
				$audit_extra = array( 'previous_state' => $previous_state, 'previous_driver_id' => $previous_driver_id, 'reason' => 'PARTICIPATION_INACTIVE' );
			} elseif ( 'WAITING' === $row['state'] ) {
				continue;
			} elseif ( ! $driver || ! self::driver_active( $driver ) ) {
				$previous_state = $row['state']; $previous_driver_id = $row['driver_id'];
				$decision_deadline = null;
				$event_cancelling = get_post_meta( $event_id, '_mi_event_cancellation_job', true ) || get_post_meta( $event_id, '_mi_event_cancelled_at', true );
				$event_start = self::local_timestamp( (string) get_post_meta( $event_id, '_mi_event_starts_at', true ) );
				if ( ! $event_cancelling && $deadline && time() + self::OFFER_SECONDS > $deadline ) {
					$decision_timestamp = time() + self::LOST_RIDE_DECISION_SECONDS;
					if ( $event_start ) $decision_timestamp = min( $decision_timestamp, $event_start );
					$decision_deadline = gmdate( 'Y-m-d H:i:s', $decision_timestamp );
				}
				$row = array_merge( $row, array( 'state' => 'WAITING', 'driver_id' => null, 'offer_expires_at' => null, 'decision_deadline_at' => $decision_deadline ) );
				$details = $decision_deadline ? ' Non ci sono 24 ore complete per una nuova proposta. È possibile scegliere uno spostamento autonomo o annullare la partecipazione entro ' . wp_date( 'd/m/Y H:i', strtotime( $decision_deadline . ' UTC' ), wp_timezone() ) . '.' : ' Cerchiamo un’altra disponibilità fino al termine delle assegnazioni.';
				$participation = ! empty( $row['required_ride'] ) ? ' La partecipazione resta riservata e il pagamento dell’evento è sospeso mentre cerchiamo una soluzione.' : ' L’iscrizione resta attiva secondo le condizioni di pagamento dell’evento.';
				self::queue_update( $notifications, $row, 'Il passaggio assegnato non è più disponibile', 'Il passaggio assegnato non è più disponibile.' . $participation . $details );
				$audit_extra = array( 'previous_state' => $previous_state, 'previous_driver_id' => $previous_driver_id, 'decision_deadline_at' => $decision_deadline, 'reason' => 'DRIVER_UNAVAILABLE' );
			} elseif ( 'OFFERED' === $row['state'] && strtotime( $row['offer_expires_at'] . ' UTC' ) <= time() ) {
				$previous_state = $row['state']; $previous_driver_id = $row['driver_id'];
				$row = array_merge( $row, array( 'state' => 'EXPIRED', 'driver_id' => null, 'offer_expires_at' => null ) );
				self::queue_update( $notifications, $row, 'Proposta di passaggio scaduta', 'Il termine di 24 ore è trascorso e il posto è nuovamente disponibile. È possibile rientrare in lista o scegliere uno spostamento autonomo dal collegamento personale, entro il termine dei passaggi.' );
				$audit_extra = array( 'previous_state' => $previous_state, 'previous_driver_id' => $previous_driver_id, 'reason' => 'OFFER_EXPIRED' );
			} else continue;
			self::write( $row['participant_id'], array_intersect_key( $row, array_flip( array( 'state', 'driver_id', 'offer_expires_at', 'decision_deadline_at' ) ) ) );
			self::audit_locked( $row, 'TRAVEL_' . $row['state'], $audit_extra, 'SYSTEM' );
			$changed[$row['registration_id']] = true;
		}
		unset( $row );
		// Un ritiro del conducente sospende nuovamente le partecipazioni subordinate al passaggio.
		foreach ( $rows as $row ) if ( self::admitted( $row ) && ( 'TRAVEL_PENDING' === $row['booking_status'] || ( ! empty( $row['required_ride'] ) && 'ASSIGNED' !== $row['state'] ) ) ) $changed[$row['registration_id']] = true;
		foreach ( array_keys( $changed ) as $id ) self::refresh_booking_locked( $id );
		$rows = self::rows( $event_id, true );
		foreach ( $rows as $row ) {
			if ( ! self::admitted( $row ) || 'NEED' !== $row['mode'] || 'WAITING' !== $row['state'] || ! empty( $row['required_ride'] ) ) continue;
			$decision_deadline = ! empty( $row['decision_deadline_at'] ) ? strtotime( $row['decision_deadline_at'] . ' UTC' ) : false;
			$decision_expired = false !== $decision_deadline && $decision_deadline <= time();
			$travel_deadline_reached = $deadline && time() >= $deadline && false === $decision_deadline;
			if ( ! $decision_expired && ! $travel_deadline_reached ) continue;
			self::write( $row['participant_id'], array( 'mode' => 'OWN', 'state' => 'NONE', 'required_ride' => 0, 'driver_id' => null, 'offer_expires_at' => null, 'decision_deadline_at' => null ) );
			$closed_row = array_merge( $row, array( 'mode' => 'OWN', 'state' => 'NONE', 'required_ride' => 0, 'driver_id' => null, 'offer_expires_at' => null, 'decision_deadline_at' => null ) );
			self::audit_locked( $closed_row, 'TRAVEL_AUTONOMOUS', array( 'previous_state' => 'WAITING', 'reason' => $decision_expired ? 'DECISION_WINDOW_EXPIRED' : 'TRAVEL_DEADLINE' ), 'SYSTEM' );
			self::queue_update( $notifications, $closed_row, 'Nessun passaggio assegnato', 'Non è stato assegnato un passaggio. L’iscrizione resta attiva secondo le condizioni di pagamento dell’evento. Il luogo dell’evento dovrà essere raggiunto con mezzi autonomi.' );
		}
		// Una proposta concede sempre 24 ore complete e non supera il termine finale.
		if ( ! $deadline || time() + self::OFFER_SECONDS > $deadline || get_post_meta( $event_id, '_mi_event_cancellation_job', true ) || get_post_meta( $event_id, '_mi_event_cancelled_at', true ) ) { self::flush_updates( $notifications ); return; }
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
				self::queue_update( $notifications, $row, 'Passaggio disponibile', self::describe( $row ) . '.' . self::driver_contact_text( $driver ) . ' Accettazione o rinuncia entro ' . wp_date( 'd/m/Y H:i', strtotime( $row['offer_expires_at'] . ' UTC' ), wp_timezone() ) . '. Il posto è riservato fino alla scadenza.' );
				self::audit_locked( $row, 'TRAVEL_OFFERED' );
				--$available[$driver['participant_id']];
				break;
			}
		}
		unset( $row );
		self::flush_updates( $notifications );
	}
	private static function audit_locked( array $row, $type, array $extra = array(), $actor = 'TRAVEL' ) {
		$details = array_merge( array( 'participant_id' => (int) $row['participant_id'], 'mode' => $row['mode'], 'state' => $row['state'], 'driver_id' => $row['driver_id'], 'required_ride' => (int) $row['required_ride'], 'seats' => (int) $row['seats'], 'origin' => (string) $row['origin'], 'departure_at' => $row['departure_at'], 'price_cents' => (int) $row['price_cents'], 'offer_expires_at' => $row['offer_expires_at'], 'decision_deadline_at' => $row['decision_deadline_at'] ?? null ), $extra );
		if ( ! MI_Registration_Service::append_registration_event( $row['registration_id'], $type, '', '', $actor, $details ) ) throw new RuntimeException( 'Aggiornamento passaggio non registrato.' );
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
				else {
					$accepted = self::check( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}mi_registration_events WHERE registration_id=%d AND event_type='WAITLIST_ACCEPTED' LIMIT 1", $id ) ) );
					if ( $accepted ) $deadline = gmdate( 'Y-m-d H:i:s', time() + min( 168, max( 1, absint( $event['waitlist_offer_hours'] ?? 48 ) ) ) * HOUR_IN_SECONDS );
				}
			}
			self::check( $wpdb->update( $wpdb->prefix . 'mi_registrations', array( 'status' => $target, 'initial_due_cents' => $economic['initial_due_cents'], 'balance_cents' => $economic['balance_cents'], 'expires_at' => $deadline, 'payment_deadline_at' => $deadline ), array( 'id' => $id ) ) );
			if ( class_exists( 'MI_Management_Service' ) ) MI_Management_Service::auto_assign_rooms_locked( $id );
			$travel_row = array( 'participant_id' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT participant_id FROM {$wpdb->prefix}mi_travel WHERE registration_id=%d ORDER BY participant_id LIMIT 1", $id ) ), 'registration_id' => $id, 'buyer_email' => $row['buyer_email'] );
			$message = 'La gestione dei passaggi per la prenotazione è stata aggiornata. ' . ( 'PENDING_PAYMENT' === $target ? 'La partecipazione è ora da pagare. Il riepilogo dell’evento è disponibile al collegamento: ' . MI_Portal::status_url( $id, $row['order_code'], $row['buyer_email'] ) : 'La partecipazione è confermata.' );
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
		$items = array(); $deadline = self::local_timestamp( $event['travel']['deadline'] ?? '' );
		$accepting_new_requests = ! empty( $event['travel']['enabled'] ) && $deadline && time() < $deadline - self::OFFER_SECONDS && ! get_post_meta( $event['id'], '_mi_event_cancelled_at', true );
		try {
			if ( $accepting_new_requests ) {
				$rows = self::rows( $event['id'] ); $occupied = self::seat_counts( $rows );
				foreach ( $rows as $row ) {
					$free = (int) $row['seats'] - ( $occupied[$row['participant_id']] ?? 0 );
					if ( self::driver_active( $row ) && $free > 0 ) $items[] = array( 'route_key' => self::route_key( $row ), 'id' => (int) $row['participant_id'], 'origin' => $row['origin'], 'departure' => wp_date( 'd/m/Y H:i', strtotime( $row['departure_at'] . ' UTC' ), wp_timezone() ), 'seats' => $free, 'price_cents' => (int) $row['price_cents'] );
				}
			}
			$response = new WP_REST_Response( array( 'rides' => $items, 'accepting_new_requests' => (bool) $accepting_new_requests ) ); $response->header( 'Cache-Control', 'no-store' ); return $response;
		} catch ( Throwable $error ) { return new WP_Error( 'mi_travel_unavailable', 'Disponibilità passaggi non disponibile. Riprova.', array( 'status' => 503 ) ); }
	}
	public static function tick() {
		global $wpdb;
		$events = $wpdb->get_col( "SELECT DISTINCT t.event_id FROM {$wpdb->prefix}mi_travel t JOIN {$wpdb->prefix}mi_registrations r ON r.id=t.registration_id WHERE t.state IN ('WAITING','OFFERED') OR r.status='TRAVEL_PENDING' ORDER BY t.event_id" );
		foreach ( (array) $events as $event ) {
			try {
				self::maintain( $event );
				$config = self::config( $event );
				if ( ! $config['enabled'] || ! self::local_timestamp( $config['deadline'] ) || self::local_timestamp( $config['deadline'] ) > time() ) continue;
				$pending = $wpdb->get_col( $wpdb->prepare( "SELECT t.participant_id FROM {$wpdb->prefix}mi_travel t JOIN {$wpdb->prefix}mi_registrations r ON r.id=t.registration_id JOIN {$wpdb->prefix}mi_participants p ON p.id=t.participant_id WHERE t.event_id=%d AND t.required_ride=1 AND t.state<>'ASSIGNED' AND r.status='TRAVEL_PENDING' AND p.status='ACTIVE' AND (t.decision_deadline_at IS NULL OR t.decision_deadline_at<=%s)", $event, current_time( 'mysql', true ) ) );
				foreach ( (array) $pending as $id ) MI_Registration_Service::cancel_participant( $id, 'TRAVEL_DEADLINE' );
			} finally {
				if ( class_exists( 'MI_Event_Deletion' ) ) MI_Event_Deletion::release( $event );
			}
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
			set_transient( 'mi_travel_maintained_' . absint( $event_id ), 1, 60 );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( 'MI_TRAVEL_MAINTENANCE_FAILED event=' . absint( $event_id ) . ' type=' . get_class( $error ) );
			return new WP_Error( 'mi_travel_maintenance', 'Aggiornamento dei passaggi temporaneamente non disponibile. Riprova tra poco.' );
		}
		try { MI_Spedizione_Email::pianifica_spedizione(); } catch ( Throwable $ignored ) { /* Il cron recupera la coda persistente. */ }
	}
	public static function decide( $id, $token, $action, $expected_driver = 0, $expected_expiry = '', $requested_seats = 0, array $requested_details = array() ) {
		if ( ! is_string( $token ) || ! hash_equals( self::token( $id ), $token ) ) return new WP_Error( 'mi_travel_token', 'Collegamento non valido.' );
		global $wpdb;
		$event_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$wpdb->prefix}mi_travel WHERE participant_id=%d", $id ) );
		try {
			if ( class_exists( 'MI_Event_Deletion' ) && is_wp_error( MI_Event_Deletion::enter( $event_id ) ) ) throw new RuntimeException( 'Evento non disponibile.' );
			self::check( $wpdb->query( 'START TRANSACTION' ) ); MI_Management_Service::lock_room_event( $event_id );
			$config = self::config( $event_id );
			$deadline = self::local_timestamp( $config['deadline'] );
			$after_deadline = false === $deadline || $deadline <= time();
			$allowed_after_deadline = in_array( $action, array( 'own', 'withdraw' ), true );
			if ( ! $config['enabled'] || ( $after_deadline && ! $allowed_after_deadline ) || get_post_meta( $event_id, '_mi_event_cancellation_job', true ) || get_post_meta( $event_id, '_mi_event_cancelled_at', true ) ) throw new InvalidArgumentException( 'Le assegnazioni sono chiuse. Puoi ancora organizzarti autonomamente, ritirare i posti offerti o annullare la partecipazione.' );
			$rows = self::rows( $event_id, true ); $row = null; $by_id = array_column( $rows, null, 'participant_id' ); $released_driver = null; $released_message = ''; $audit_context = array();
			foreach ( $rows as $entry ) if ( (int) $entry['participant_id'] === (int) $id ) $row = $entry;
			if ( ! $row || ! self::admitted( $row ) ) throw new InvalidArgumentException( 'La partecipazione non è attiva o è ancora nella lista d’attesa dell’evento.' );
			if ( in_array( $action, array( 'accept', 'decline' ), true ) ) {
				if ( 'OFFERED' !== $row['state'] || strtotime( $row['offer_expires_at'] . ' UTC' ) <= time() || (int) $row['driver_id'] !== (int) $expected_driver || $row['offer_expires_at'] !== $expected_expiry ) throw new InvalidArgumentException( 'La proposta è cambiata o è scaduta. Ricarica la pagina.' );
				$driver = array_column( $rows, null, 'participant_id' )[$row['driver_id']] ?? null;
				if ( ! $driver || ! self::driver_active( $driver ) ) throw new InvalidArgumentException( 'Il passaggio non è più disponibile.' );
				$row['state'] = 'accept' === $action ? 'ASSIGNED' : 'DECLINED';
				if ( 'accept' === $action ) {
					self::notify_locked( $driver, 'Passaggio accettato', $row['first_name'] . ' ' . $row['last_name'] . ' ha accettato il passaggio proposto. Il riepilogo del viaggio riporta i contatti.' );
					self::notify_locked( $row, 'Passaggio assegnato', self::describe( $row ) . '. Il riepilogo del viaggio riporta i contatti del conducente.' );
				}
				if ( 'decline' === $action ) { $released_driver = $driver; $released_message = $row['first_name'] . ' ' . $row['last_name'] . ' ha rifiutato la proposta; il posto è di nuovo disponibile.'; $row['driver_id'] = null; }
				$row['decision_deadline_at'] = null;
			} elseif ( 'rejoin' === $action && 'NEED' === $row['mode'] && in_array( $row['state'], array( 'DECLINED', 'EXPIRED' ), true ) ) {
				$row['state'] = 'WAITING'; $row['driver_id'] = null; $row['decision_deadline_at'] = null;
				self::write( $id, array( 'created_at' => current_time( 'mysql', true ) ) );
			} elseif ( 'update_seats' === $action && 'DRIVER' === $row['mode'] ) {
				$requested_seats = (int) $requested_seats;
				if ( $requested_seats < 1 || $requested_seats > 8 ) throw new InvalidArgumentException( 'Indica da 1 a 8 posti offerti.' );
				if ( $deadline && time() + self::OFFER_SECONDS > $deadline && $requested_seats > (int) $row['seats'] ) throw new InvalidArgumentException( 'Nelle ultime 24 ore non puoi aumentare i posti: le nuove proposte non avrebbero il tempo completo per essere accettate.' );
				$used_seats = (int) ( self::seat_counts( $rows )[$id] ?? 0 );
				if ( $requested_seats < $used_seats ) throw new InvalidArgumentException( 'Non puoi offrire meno posti di quelli già assegnati o riservati da una proposta.' );
				$origin = mb_substr( sanitize_text_field( $requested_details['origin'] ?? $row['origin'] ), 0, 180 );
				if ( '' === $origin ) $origin = mb_substr( sanitize_text_field( get_post_meta( $event_id, '_mi_event_location', true ) ), 0, 180 );
				$departure_input = trim( (string) ( $requested_details['departure'] ?? '' ) );
				if ( '' === $departure_input && ! empty( $row['departure_at'] ) ) $departure_input = wp_date( 'd/m/Y H:i', strtotime( $row['departure_at'] . ' UTC' ), wp_timezone() );
				if ( '' === $departure_input ) $departure_input = (string) get_post_meta( $event_id, '_mi_event_starts_at', true );
				$departure = self::local_timestamp( $departure_input );
				$event_start = self::local_timestamp( (string) get_post_meta( $event_id, '_mi_event_starts_at', true ) );
				$price_input = $requested_details['price'] ?? '';
				if ( 'FIXED' === $config['price_mode'] ) $price = (int) $config['price_cents'];
				elseif ( '' === trim( (string) $price_input ) ) $price = (int) $row['price_cents'];
				else $price = MI_Amount::cents( $price_input );
				if ( ! $origin || false === $departure || ! $event_start || $departure <= self::local_timestamp( $config['deadline'] ) || $departure > $event_start || null === $price || $price < 0 || $price > 100000000 ) throw new InvalidArgumentException( 'Per offrire posti indica una località, un orario successivo al termine dei passaggi e non oltre l’inizio dell’evento, e un contributo valido.' );
				$departure_utc = gmdate( 'Y-m-d H:i:s', $departure );
				if ( $used_seats && ( $origin !== $row['origin'] || $departure_utc !== $row['departure_at'] || (int) $price !== (int) $row['price_cents'] ) ) throw new InvalidArgumentException( 'Non puoi modificare partenza, orario o contributo mentre ci sono posti assegnati o riservati.' );
				$audit_context = array( 'previous_seats' => (int) $row['seats'], 'previous_origin' => $row['origin'], 'previous_departure_at' => $row['departure_at'], 'previous_price_cents' => (int) $row['price_cents'] );
				$row['seats'] = $requested_seats;
				$row['origin'] = $origin; $row['departure_at'] = $departure_utc; $row['price_cents'] = (int) $price;
			} elseif ( 'withdraw' === $action && 'DRIVER' === $row['mode'] ) {
				$audit_context = array( 'previous_seats' => (int) $row['seats'], 'previous_state' => $row['state'] );
				$row['seats'] = 0;
			} elseif ( 'own' === $action && 'NEED' === $row['mode'] ) {
				if ( ! empty( $row['driver_id'] ) && in_array( $row['state'], array( 'ASSIGNED', 'OFFERED' ), true ) ) {
					$released_driver = $by_id[$row['driver_id']] ?? null;
					$released_message = $row['first_name'] . ' ' . $row['last_name'] . ' ha rinunciato al passaggio e si organizzerà autonomamente; il posto è di nuovo disponibile.';
				}
				$audit_context = array( 'previous_state' => $row['state'], 'previous_driver_id' => $row['driver_id'], 'previous_required_ride' => (int) $row['required_ride'] );
				$row['state'] = 'NONE'; $row['driver_id'] = null; $row['mode'] = 'OWN'; $row['required_ride'] = 0; $row['decision_deadline_at'] = null;
			} else throw new InvalidArgumentException( 'Scelta non disponibile.' );
			$row['offer_expires_at'] = null;
			self::write( $id, array_intersect_key( $row, array_flip( array( 'state', 'driver_id', 'mode', 'required_ride', 'offer_expires_at', 'decision_deadline_at', 'seats', 'origin', 'departure_at', 'price_cents' ) ) ) );
			if ( $released_driver && self::driver_active( $released_driver ) ) self::notify_locked( $released_driver, 'Posto auto liberato', $released_message );
			self::audit_locked( $row, 'TRAVEL_' . strtoupper( $action ), array_merge( $audit_context, array( 'previous_driver_id' => $audit_context['previous_driver_id'] ?? ( $by_id[$id]['driver_id'] ?? null ) ) ), 'PARTICIPANT_LINK' );
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
			$action = sanitize_key( wp_unslash( $_POST['travel_action'] ?? '' ) );
			if ( 'cancel_participation' === $action ) {
				$result = MI_Registration_Service::cancel_participant( $id, 'TRAVEL_LINK' );
				$message = is_wp_error( $result ) ? $result->get_error_message() : 'Partecipazione annullata. La segreteria ha ricevuto la comunicazione.';
			} else {
				$details = array( 'origin' => sanitize_text_field( wp_unslash( $_POST['origin'] ?? '' ) ), 'departure' => sanitize_text_field( wp_unslash( $_POST['departure'] ?? '' ) ), 'price' => sanitize_text_field( wp_unslash( $_POST['price'] ?? '' ) ) );
				$result = self::decide( $id, $token, $action, absint( $_POST['driver_id'] ?? 0 ), sanitize_text_field( wp_unslash( $_POST['offer_expires_at'] ?? '' ) ), absint( $_POST['seats'] ?? 0 ), $details );
				$message = is_wp_error( $result ) ? $result->get_error_message() : 'Spostamento aggiornato.';
			}
		}
		$maintenance = get_transient( 'mi_travel_maintained_' . $event_id ) ? null : self::maintain( $event_id );
		if ( is_wp_error( $maintenance ) ) $message = $maintenance->get_error_message();
		$rows = self::rows( $event_id ); $row = array_column( $rows, null, 'participant_id' )[$id] ?? null;
		if ( ! $row ) wp_die( 'Viaggio non disponibile.' );
		$config = self::config( $event_id );
		$deadline = self::local_timestamp( $config['deadline'] );
		$deadline_passed = false === $deadline || $deadline <= time();
		$event_cancelled = get_post_meta( $event_id, '_mi_event_cancellation_job', true ) || get_post_meta( $event_id, '_mi_event_cancelled_at', true );
		$closed = $deadline_passed || $event_cancelled;
		$decision_deadline = ! empty( $row['decision_deadline_at'] ) ? strtotime( $row['decision_deadline_at'] . ' UTC' ) : false;
		echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>I tuoi spostamenti</title><style>body{font:17px/1.6 system-ui;background:#f4f6fa;color:#151b38;margin:0}main{max-width:720px;margin:32px auto;padding:24px;background:white;border-radius:12px}button{font:inherit;padding:10px 16px;margin:8px;border:1px solid #337ab7;border-radius:6px;background:#fff;cursor:pointer}button:focus-visible,a:focus-visible{outline:3px solid #337ab7}label{display:block}table{width:100%}td,th{text-align:left;padding:8px;border-bottom:1px solid #ddd}</style></head><body><main><h1>I tuoi spostamenti</h1><h2>' . esc_html( get_the_title( $event_id ) ) . '</h2><p>' . esc_html( $row['first_name'] . ' ' . $row['last_name'] ) . '</p>';
		if ( $message ) echo '<p role="status">' . esc_html( $message ) . '</p>';
		echo '<p>' . nl2br( esc_html( $config['instructions'] ) ) . '</p><p>Il contributo per il passaggio comprende andata e ritorno ed è versato direttamente al conducente, separatamente dalle eventuali quote dell’evento.</p>';
		if ( 'WAITING' === $row['state'] && ! $deadline_passed && $deadline - time() < self::OFFER_SECONDS ) echo '<p>Non verranno inviate nuove proposte automatiche: non restano 24 ore complete prima del termine. Puoi organizzarti autonomamente o annullare la partecipazione.</p>';
		if ( 'DRIVER' === $row['mode'] && ! $deadline_passed && $deadline - time() < self::OFFER_SECONDS ) echo '<p>Nelle ultime 24 ore puoi ridurre o ritirare i posti, ma non aumentarli: ogni nuova proposta deve lasciare 24 ore complete per la risposta.</p>';
		if ( false !== $decision_deadline && $decision_deadline > time() ) echo '<p>Il passaggio assegnato non è più disponibile. Puoi organizzarti autonomamente o annullare la partecipazione entro ' . esc_html( wp_date( 'd/m/Y H:i', $decision_deadline, wp_timezone() ) ) . '. ' . ( $row['required_ride'] ? 'Se non scegli, la partecipazione sarà annullata automaticamente perché avevi indicato che potevi partecipare solo con un passaggio.' : 'Se non scegli, parteciperai organizzandoti autonomamente.' ) . '</p>';
		elseif ( false !== $decision_deadline && $decision_deadline <= time() && ! empty( $row['required_ride'] ) && 'WAITING' === $row['state'] ) echo '<p>Il termine per scegliere è trascorso; la cancellazione automatica della partecipazione è in elaborazione. Puoi ancora annullare la partecipazione da questa pagina.</p>';
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
		if ( ! $event_cancelled && self::admitted( $row ) ) {
			echo '<form method="post">'; wp_nonce_field( 'mi_travel_' . $id );
			echo '<input type="hidden" name="driver_id" value="' . esc_attr( $row['driver_id'] ) . '"><input type="hidden" name="offer_expires_at" value="' . esc_attr( $row['offer_expires_at'] ) . '">';
			if ( ! $deadline_passed && 'OFFERED' === $row['state'] ) echo '<button name="travel_action" value="accept">Accetta il passaggio</button><button name="travel_action" value="decline">Rifiuta</button>';
			if ( ! $closed && 'NEED' === $row['mode'] && in_array( $row['state'], array( 'DECLINED', 'EXPIRED' ), true ) ) echo '<button name="travel_action" value="rejoin">Rientra in lista d’attesa auto</button>';
			if ( 'NEED' === $row['mode'] ) echo '<button name="travel_action" value="own">Partecipo organizzandomi autonomamente</button>';
			if ( 'DRIVER' === $row['mode'] ) {
				if ( ! $deadline_passed ) {
					$default_departure = $row['departure_at'] ? wp_date( 'd/m/Y H:i', strtotime( $row['departure_at'] . ' UTC' ), wp_timezone() ) : '';
					$default_price = 'FIXED' === $config['price_mode'] ? number_format( $config['price_cents'] / 100, 2, ',', '' ) : number_format( (int) $row['price_cents'] / 100, 2, ',', '' );
					echo '<label>Posti che offri (da 1 a 8)<input type="number" name="seats" min="1" max="8" step="1" value="' . esc_attr( max( 1, (int) $row['seats'] ) ) . '" required></label>';
					echo '<label>Località e punto di partenza<input name="origin" maxlength="180" value="' . esc_attr( $row['origin'] ) . '" required></label><label>Data e ora di partenza (gg/mm/aaaa hh:mm)<input name="departure" inputmode="numeric" placeholder="gg/mm/aaaa hh:mm" value="' . esc_attr( $default_departure ) . '" required></label>';
					if ( 'DRIVER' === $config['price_mode'] ) echo '<label>Contributo per persona A/R (€)<input name="price" inputmode="decimal" value="' . esc_attr( $default_price ) . '" required></label>';
					echo '<p>Per attivare un’offerta servono partenza, orario e contributo validi. Non puoi scendere sotto i posti già assegnati o riservati; se un posto è occupato o riservato non puoi cambiare i dettagli del viaggio.</p><button name="travel_action" value="update_seats">Aggiorna posti e dettagli</button>';
				}
				if ( (int) $row['seats'] ) echo '<p>Ritirando tutti i posti, i passeggeri assegnati saranno avvisati e potranno organizzarsi autonomamente o annullare la partecipazione.</p><button name="travel_action" value="withdraw" formnovalidate>Ritira tutti i posti offerti</button>';
			}
			echo '<hr><p>Se non partecipi più all’evento, usa questo pulsante: la segreteria sarà avvisata. Eventuali importi già versati richiedono una verifica separata.</p><button name="travel_action" value="cancel_participation" formnovalidate>Annulla la partecipazione all’evento</button>';
			echo '</form>';
		}
		if ( $closed ) echo '<p>Il termine per le nuove proposte è trascorso. Puoi ancora organizzarti autonomamente, ritirare i posti offerti o annullare la partecipazione.</p>';
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
