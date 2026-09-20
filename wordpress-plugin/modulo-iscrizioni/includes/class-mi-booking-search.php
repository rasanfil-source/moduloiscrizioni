<?php
defined( 'ABSPATH' ) || exit;

/** Same words and fields for the booking entry points; callers enforce access scope. */
final class MI_Booking_Search {
	public static function words( $query ) {
		return array_slice( preg_split( '/\s+/u', trim( mb_substr( (string) $query, 0, 80 ) ), -1, PREG_SPLIT_NO_EMPTY ), 0, 12 );
	}
	public static function matches( $values, $query ) {
		$text = mb_strtolower( remove_accents( implode( ' ', $values ) ), 'UTF-8' );
		foreach ( self::words( $query ) as $word ) if ( false === mb_strpos( $text, mb_strtolower( remove_accents( $word ), 'UTF-8' ) ) ) return false;
		return true;
	}
	public static function sql( $query ) {
		global $wpdb;
		$clauses = array();
		foreach ( self::words( $query ) as $word ) {
			$like = '%' . $wpdb->esc_like( $word ) . '%';
			$clauses[] = $wpdb->prepare( "(CONCAT_WS(' ',r.order_code,r.buyer_first_name,r.buyer_last_name,r.buyer_email,r.buyer_phone) LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->prefix}mi_participants person WHERE person.registration_id=r.id AND CONCAT_WS(' ',person.first_name,person.last_name) LIKE %s))", $like, $like );
		}
		return $clauses ? '(' . implode( ' AND ', $clauses ) . ')' : '1=1';
	}
}
