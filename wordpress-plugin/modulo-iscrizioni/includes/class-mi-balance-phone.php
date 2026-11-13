<?php
defined( 'ABSPATH' ) || exit;

/** Cautela facoltativa per evento; non verifica il possesso del telefono. */
final class MI_Balance_Phone {
	public static function phone( $bundle, $id ) {
		foreach ( $bundle['people'] as $person ) if ( (int) $person['id'] === (int) $id ) {
			$fields = json_decode( $person['extra_json'] ?? '{}', true ) ?: array();
			foreach ( array( 'participant_phone', 'phone', 'mobile' ) as $key ) {
				$phone = preg_replace( '/\D/', '', (string) ( $fields[$key] ?? '' ) );
				if ( strlen( $phone ) >= 7 ) return $phone;
			}
		}
		$phone = preg_replace( '/\D/', '', (string) ( $bundle['registration']['buyer_phone'] ?? '' ) );
		return strlen( $phone ) >= 7 ? $phone : '';
	}
	private static function signature( $event, $id, $phone, $purpose ) { return hash_hmac( 'sha256', 'balance-phone|' . $purpose . '|' . $event . '|' . $id . '|' . $phone, wp_salt( 'auth' ) ); }
	public static function grant( $event, $id, $phone ) {
		$expires = time() + 1800;
		return $expires . '.' . self::signature( $event, $id, $phone, 'access:' . $expires );
	}
	public static function allowed( $event, $id, $phone, $grant ) {
		if ( ! is_string( $grant ) ) return false;
		$parts = explode( '.', (string) $grant );
		return $phone && 2 === count( $parts ) && ctype_digit( $parts[0] ) && (int) $parts[0] >= time() && (int) $parts[0] <= time() + 1800 && hash_equals( self::signature( $event, $id, $phone, 'access:' . $parts[0] ), $parts[1] );
	}
	public static function challenge( $event, $id, $phone ) {
		if ( ! $phone ) return array( 'success' => false, 'error' => 'Non è presente un numero valido. Contatta la segreteria per aggiornare il recapito.' );
		return array( 'success' => false, 'error' => 'phone_required', 'row' => $id, 'phone_masked' => '+' . substr( $phone, 0, -4 ) . ' ••••', 'challenge' => self::signature( $event, $id, $phone, 'challenge' ) );
	}
	public static function verify( $event, $id, $phone, $data ) {
		global $wpdb;
		if ( ! $phone || ! is_string( $data['challenge'] ?? null ) || ! hash_equals( self::signature( $event, $id, $phone, 'challenge' ), $data['challenge'] ) ) throw new InvalidArgumentException( 'Ricarica la prenotazione e riprova.' );
		$key = 'mi_balance_phone_' . hash_hmac( 'sha256', $event . '|' . $id, wp_salt( 'auth' ) );
		$lock = substr( $key, 0, 64 );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,2)', $lock ) ) ) throw new RuntimeException( 'Verifica momentaneamente occupata. Riprova.' );
		try {
			$state = (array) get_transient( $key );
			if ( (int) ( $state['until'] ?? 0 ) > time() ) return array( 'success' => false, 'error' => 'Attendi 5 minuti prima di riprovare.' );
			$digits = is_string( $data['digits'] ?? null ) ? $data['digits'] : '';
			if ( ! preg_match( '/^[0-9]{4}$/D', $digits ) || ! hash_equals( substr( $phone, -4 ), $digits ) ) {
				$failures = (int) ( $state['failures'] ?? 0 ) + 1;
				set_transient( $key, array( 'failures' => $failures, 'until' => $failures >= 5 ? time() + 300 : 0 ), 300 );
				return array( 'success' => false, 'error' => $failures >= 5 ? 'Attendi 5 minuti prima di riprovare.' : 'Le cifre non corrispondono. Controlla il numero indicato e riprova.' );
			}
			delete_transient( $key );
			return array( 'success' => true );
		} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}
}
