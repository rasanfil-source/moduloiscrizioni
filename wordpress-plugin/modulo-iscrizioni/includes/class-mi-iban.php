<?php
defined( 'ABSPATH' ) || exit;

final class MI_IBAN {
	/** IBAN italiano: struttura, CIN nazionale e controllo internazionale MOD-97. */
	public static function valid( $value ) {
		$iban = strtoupper( preg_replace( '/\s+/', '', (string) $value ) );
		if ( ! preg_match( '/^IT[0-9]{2}[A-Z][0-9]{10}[A-Z0-9]{12}$/D', $iban ) ) return false;
		$odd = array( 1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23 );
		$account = substr( $iban, 5 ); $sum = 0;
		for ( $i = 0; $i < strlen( $account ); ++$i ) {
			$n = ord( $account[$i] ) - ( ctype_digit( $account[$i] ) ? 48 : 65 );
			$sum += 0 === $i % 2 ? $odd[$n] : $n;
		}
		if ( chr( 65 + $sum % 26 ) !== $iban[4] ) return false;
		$rotated = substr( $iban, 4 ) . substr( $iban, 0, 4 ); $remainder = 0;
		foreach ( str_split( $rotated ) as $character ) {
			$digits = ctype_digit( $character ) ? $character : (string) ( ord( $character ) - 55 );
			foreach ( str_split( $digits ) as $digit ) $remainder = ( $remainder * 10 + (int) $digit ) % 97;
		}
		return 1 === $remainder;
	}
}
