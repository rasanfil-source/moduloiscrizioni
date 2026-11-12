<?php
defined( 'ABSPATH' ) || exit;

/** Usa gli stessi termini e campi per gli accessi alla prenotazione; ogni chiamante applica il proprio ambito autorizzativo. */
final class MI_Booking_Search {
	public static function words( $query ) {
		return array_slice( preg_split( '/\s+/u', trim( mb_substr( (string) $query, 0, 80 ) ), -1, PREG_SPLIT_NO_EMPTY ), 0, 12 );
	}
	private static function phone_term( $word ) {
		$digits = preg_replace( '/\D/', '', $word );
		return strlen( $digits ) >= 4 && preg_match( '/^[+0-9().-]+$/D', $word ) ? $digits : null;
	}
	public static function matches( $values, $query ) {
		foreach ( $values as $value ) if ( preg_match( '/^\+?[0-9\s().-]+$/D', (string) $value ) ) $values[] = preg_replace( '/\D/', '', (string) $value );
		$text = mb_strtolower( remove_accents( implode( ' ', $values ) ), 'UTF-8' );
		foreach ( self::words( $query ) as $word ) {
			if ( false !== mb_strpos( $text, mb_strtolower( remove_accents( $word ), 'UTF-8' ) ) ) continue;
			$digits = self::phone_term( $word );
			if ( null === $digits || false === strpos( $text, $digits ) ) return false;
		}
		return true;
	}
	public static function sql( $query ) {
		global $wpdb;
		$clauses = array();
		$buyer_phone = 'r.buyer_phone';
		$person_phones = array();
		foreach ( array( 'phone', 'participant_phone', 'mobile' ) as $key ) $person_phones[] = "IF(JSON_VALID(person.extra_json),JSON_UNQUOTE(JSON_EXTRACT(person.extra_json,'$.{$key}')),'')";
		foreach ( array( ' ', '+', '-', '(', ')', '.' ) as $separator ) {
			$buyer_phone = "REPLACE({$buyer_phone},'{$separator}','')";
			foreach ( $person_phones as &$phone ) $phone = "REPLACE({$phone},'{$separator}','')";
			unset( $phone );
		}
		$person_phones = "CONCAT_WS(' '," . implode( ',', $person_phones ) . ')';
		foreach ( self::words( $query ) as $word ) {
			$like = '%' . $wpdb->esc_like( $word ) . '%';
			$digits = self::phone_term( $word );
			$phone_match = '';
			if ( null !== $digits ) $phone_match = $wpdb->prepare( " OR {$buyer_phone} LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->prefix}mi_participants person WHERE person.registration_id=r.id AND {$person_phones} LIKE %s)", '%' . $digits . '%', '%' . $digits . '%' );
			$clauses[] = $wpdb->prepare( "(CONCAT_WS(' ',r.order_code,r.buyer_first_name,r.buyer_last_name,r.buyer_email,r.buyer_phone) LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->prefix}mi_participants person WHERE person.registration_id=r.id AND CONCAT_WS(' ',person.first_name,person.last_name,IF(JSON_VALID(person.extra_json),JSON_UNQUOTE(JSON_EXTRACT(person.extra_json,'$.email')),''),IF(JSON_VALID(person.extra_json),JSON_UNQUOTE(JSON_EXTRACT(person.extra_json,'$.participant_email')),''),IF(JSON_VALID(person.extra_json),JSON_UNQUOTE(JSON_EXTRACT(person.extra_json,'$.phone')),''),IF(JSON_VALID(person.extra_json),JSON_UNQUOTE(JSON_EXTRACT(person.extra_json,'$.participant_phone')),''),IF(JSON_VALID(person.extra_json),JSON_UNQUOTE(JSON_EXTRACT(person.extra_json,'$.mobile')),'')) LIKE %s)", $like, $like ) . $phone_match . ')';
		}
		return $clauses ? '(' . implode( ' AND ', $clauses ) . ')' : '1=1';
	}
}
