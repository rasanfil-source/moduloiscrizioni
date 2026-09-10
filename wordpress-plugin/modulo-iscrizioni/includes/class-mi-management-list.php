<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-mi-booking-search.php';

/** Shared selection for displayed pages, CSV and printing. No writes or Sheet calls. */
final class MI_Management_List {
	public static function page( $summary, $context, $offset = 0, $limit = 30 ) {
		$context = is_array( $context ) ? $context : array();
		$query = mb_strtolower( trim( (string) ( $context['query'] ?? '' ) ), 'UTF-8' );
		$filter = $context['filter'] ?? 'all';
		$state = $context['state'] ?? '';
		$matches = static function ( $row ) use ( $query ) {
			return MI_Booking_Search::matches( array_intersect_key( $row, array_flip( array( 'name', 'buyer', 'code', 'email', 'phone' ) ) ), $query );
		};
		$option_matches = static function ( $options, $code ) {
			foreach ( $options as $option ) if ( ( $option['code'] ?? $option['name'] ?? '' ) === $code && (int) ( $option['quantity'] ?? 0 ) > 0 ) return true;
			return false;
		};
		$logistics = static function ( $person ) use ( $context, $option_matches ) {
			$room = $context['room'] ?? ''; $service = $context['service'] ?? '';
			return ( ! $room || ( 'unassigned' === $room ? empty( $person['room'] ) : $person['room'] === substr( $room, 5 ) ) ) && ( ! $service || $option_matches( $person['options'] ?? array(), $service ) );
		};
		$common = static function ( $row ) use ( $context, $state ) {
			if ( $state && $row['status'] !== $state ) return false;
			$deposit = $context['deposit'] ?? '';
			if ( $deposit ) {
				if ( ! in_array( $row['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ) return false;
				$balance = (int) ( $row['balance'] ?? 0 ); $paid = (int) ( $row['paid'] ?? 0 );
				$payment_matches = array(
					'none' => $paid <= 0 && $balance > 0,
					'partial' => $paid > 0 && ! empty( $row['deposit_missing'] ) && $balance > 0,
					'covered' => ! empty( $row['deposit_covered'] ) && $balance > 0,
					'settled' => $balance <= 0,
					'unpaid' => $balance > 0,
					'missing' => ! empty( $row['deposit_missing'] ),
				);
				if ( empty( $payment_matches[$deposit] ) ) return false;
			}
			$requests = $context['requests'] ?? '';
			if ( $requests && ( ! trim( $row['requests'] ?? '' ) || ( 'pending' === $requests && ! empty( $row['requests_reviewed'] ) ) || ( 'reviewed' === $requests && empty( $row['requests_reviewed'] ) ) ) ) return false;
			$deadline = $context['deadline'] ?? '';
			if ( $deadline ) {
				$at = empty( $row['offer_expires_at'] ) ? false : strtotime( $row['offer_expires_at'] . ' UTC' );
				if ( 'WAITLIST_OFFERED' !== $row['status'] || false === $at ) return false;
				$remaining = $at - time();
				if ( 'expired' === $deadline ? $remaining > 0 : ( $remaining <= 0 || $remaining > 86400 ) ) return false;
			}
			return true;
		};
		$by_order = array(); foreach ( $summary['people'] as $person ) $by_order[$person['code']][] = $person;
		$individual = 'orders' !== ( $context['view'] ?? 'people' );
		$rows = array();
		foreach ( $individual ? $summary['people'] : $summary['items'] as $row ) {
			if ( ! $common( $row ) ) continue;
			$open = ! in_array( $row['status'], array( 'CANCELLED', 'EXPIRED' ), true );
			if ( $individual ) {
				if ( ! $logistics( $row ) || ! $matches( $row ) ) continue;
				$excluded = 'balance' === $filter ? empty( $row['collectible'] ) : ( 'missing' === $filter ? empty( $row['missing'] ) : empty( $row['unassigned'] ) );
				if ( 'all' !== $filter && ( ! $open || $excluded ) ) continue;
			} else {
				if ( ! empty( $context['orderService'] ) && ! $option_matches( $row['order_options'] ?? array(), $context['orderService'] ) ) continue;
				$persons = $by_order[$row['code']] ?? array();
				if ( ( ! empty( $context['room'] ) || ! empty( $context['service'] ) ) && ! array_filter( $persons, $logistics ) ) continue;
				if ( ! $matches( $row ) && ! array_filter( $persons, $matches ) ) continue;
				if ( 'all' !== $filter && ( ! $open || empty( $row[$filter] ) || ( 'balance' === $filter && ( empty( $row['collectible'] ) || $row['balance'] <= 0 ) ) ) ) continue;
			}
			$rows[] = $row;
		}
		$sort = in_array( $context['sort'] ?? '', array( 'name', 'buyer', 'code', 'room' ), true ) ? $context['sort'] : 'name';
		$direction = 'desc' === ( $context['direction'] ?? '' ) ? -1 : 1;
		usort( $rows, static function ( $a, $b ) use ( $sort, $direction ) {
			$result = strnatcasecmp( remove_accents( $a[$sort] ?? '' ), remove_accents( $b[$sort] ?? '' ) );
			return $direction * ( $result ?: ( strcmp( $a['code'], $b['code'] ) ?: ( ( $a['id'] ?? 0 ) <=> ( $b['id'] ?? 0 ) ) ) );
		} );
		$offset = max( 0, (int) $offset ); $limit = max( 1, min( 200, (int) $limit ) );
		return array( 'rows' => array_slice( $rows, $offset, $limit ), 'total' => count( $rows ), 'offset' => $offset, 'limit' => $limit, 'fingerprint' => hash( 'sha256', wp_json_encode( $rows ) ) );
	}

	/** Keep only the names and assignments needed by the inventory and service counters. */
	public static function compact( $summary ) {
		$keys = array();
		foreach ( $summary['people'] as &$person ) {
			foreach ( array_keys( $person['fields'] ) as $key ) $keys[$key] = true;
			$person = array_intersect_key( $person, array_flip( array( 'id', 'number', 'code', 'name', 'status', 'room', 'options', 'attendance' ) ) );
		}
		unset( $person );
		$summary['field_keys'] = array_keys( $keys );
		$summary['server_paging'] = true;
		return $summary;
	}
}
