<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-mi-booking-search.php';

/** Shared selection for displayed pages, CSV and printing. No writes or Sheet calls. */
final class MI_Management_List {
	public static function page( $summary, $context, $offset = 0, $limit = 30, $source_version = null ) {
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
				if ( false === ( $row['totals_known'] ?? $row['economics_known'] ?? true ) ) return false;
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
			if ( ! $open && empty( $context['includeClosed'] ) && ! in_array( $state, array( 'CANCELLED', 'EXPIRED' ), true ) ) continue;
			if ( $individual ) {
				if ( ! $logistics( $row ) || ! $matches( $row ) ) continue;
				$excluded = 'balance' === $filter ? empty( $row['collectible'] ) : ( 'missing' === $filter ? empty( $row['missing'] ) : ( 'requests' === $filter ? ! trim( $row['requests'] ?? '' ) : empty( $row['unassigned'] ) ) );
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
		$rows = self::sort_rows( $rows, $context );
		$offset = max( 0, (int) $offset ); $limit = max( 1, min( 200, (int) $limit ) );
		$deadline_ids = empty( $context['deadline'] ) ? array() : array_column( $rows, $individual ? 'id' : 'code' );
		$fingerprint = null === $source_version ? hash( 'sha256', wp_json_encode( $rows ) ) : self::fingerprint( $source_version, $context, count( $rows ), $deadline_ids );
		return array( 'rows' => array_slice( $rows, $offset, $limit ), 'total' => count( $rows ), 'offset' => $offset, 'limit' => $limit, 'fingerprint' => $fingerprint );
	}

	/** Cache-independent selection version; only timed filters can change without a canonical write. */
	public static function fingerprint( $source_version, array $context, $total, array $deadline_ids = array() ) {
		unset( $context['shown'] );
		$deadline_ids = array_map( 'strval', $deadline_ids );
		sort( $deadline_ids, SORT_STRING );
		return hash( 'sha256', wp_json_encode( array( $source_version, $context, (int) $total, $deadline_ids ) ) );
	}

	/** One comparator for complete lists and bounded, cross-chunk page selection. */
	private static function sort_rows( array $rows, array $context ) {
		$sort = in_array( $context['sort'] ?? '', array( 'name', 'buyer', 'code', 'room', 'created_at' ), true ) ? $context['sort'] : 'created_at';
		$direction = 'desc' === ( $context['direction'] ?? ( 'created_at' === $sort ? 'desc' : 'asc' ) ) ? -1 : 1;
		$rows = array_map( static function ( $row ) use ( $sort ) { return array( 'row' => $row, 'sort_key' => remove_accents( $row[$sort] ?? '' ) ); }, $rows );
		usort( $rows, static function ( $left, $right ) use ( $direction, $sort ) {
			$a = $left['row']; $b = $right['row'];
			if ( 'created_at' === $sort ) {
				$result = strcmp( $a['created_at'] ?? '', $b['created_at'] ?? '' ) ?: ( ( $a['registration_id'] ?? 0 ) <=> ( $b['registration_id'] ?? 0 ) );
				return $direction * $result ?: ( ( $a['id'] ?? 0 ) <=> ( $b['id'] ?? 0 ) );
			}
			$closed = (int) in_array( $a['status'], array( 'CANCELLED', 'EXPIRED' ), true ) <=> (int) in_array( $b['status'], array( 'CANCELLED', 'EXPIRED' ), true );
			if ( $closed ) return $closed;
			$result = strnatcasecmp( $left['sort_key'], $right['sort_key'] );
			return $direction * ( $result ?: ( strcmp( $a['code'], $b['code'] ) ?: ( ( $a['id'] ?? 0 ) <=> ( $b['id'] ?? 0 ) ) ) );
		} );
		return array_column( $rows, 'row' );
	}

	/** Retain only the first offset+limit matches, regardless of SQL chunk order. */
	public static function sorted_prefix( array $retained, array $incoming, array $context, $keep ) {
		return array_slice( self::sort_rows( array_merge( $retained, $incoming ), $context ), 0, max( 0, (int) $keep ) );
	}

	/** Aggregates only: payload size depends on schema/services, not participant count. */
	public static function overview( array $summary ) {
		$result = array_intersect_key( $summary, array_flip( array( 'ok', 'features', 'updated_at', 'registration_url', 'field_labels', 'payment_counts', 'room_types' ) ) );
		$metrics = array( 'states' => array(), 'missing' => 0, 'unassigned' => 0, 'receivable' => 0, 'paid' => 0, 'admitted' => 0, 'offers' => 0, 'has_requests' => false, 'has_missing' => false, 'has_attendance' => false );
		$order_services = array(); $services = array(); $filters = array();
		$keys = array_fill_keys( array_keys( $summary['field_labels'] ?? array() ), true );
		foreach ( $summary['items'] as $item ) {
			$status = $item['status'];
			$metrics['states'][$status] = ( $metrics['states'][$status] ?? 0 ) + (int) ( $item['participants'] ?? 0 );
			if ( ! empty( $item['active'] ) ) foreach ( array( 'missing', 'unassigned' ) as $key ) $metrics[$key] += (int) ( $item[$key] ?? 0 );
			if ( $item['collectible'] ?? in_array( $status, array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ) $metrics['receivable'] += (int) ( $item['balance'] ?? 0 );
			$metrics['paid'] += (int) ( $item['paid'] ?? 0 );
			$metrics['has_requests'] = $metrics['has_requests'] || ( ! empty( $item['active'] ) && '' !== trim( (string) ( $item['requests'] ?? '' ) ) );
			$metrics['has_missing'] = $metrics['has_missing'] || (int) ( $item['missing'] ?? 0 ) > 0;
			if ( 'WAITLIST_OFFERED' === $status ) $metrics['offers']++;
			if ( in_array( $status, array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ) ) foreach ( $item['order_options'] ?? array() as $option ) {
				$key = $option['code'] ?? $option['name'];
				if ( ! isset( $order_services[$key] ) ) $order_services[$key] = array( 'code' => $key, 'name' => $option['name'] ?? $key, 'quantity' => 0, 'orders' => 0 );
				$order_services[$key]['quantity'] += (float) ( $option['quantity'] ?? 0 );
				if ( (float) ( $option['quantity'] ?? 0 ) > 0 ) $order_services[$key]['orders']++;
			}
		}
		foreach ( $summary['people'] as $person ) {
			foreach ( array_keys( $person['fields'] ?? array() ) as $key ) $keys[$key] = true;
			$attendance = $person['attendance'] ?? '';
			$metrics['has_attendance'] = $metrics['has_attendance'] || in_array( is_array( $attendance ) ? ( $attendance['state'] ?? '' ) : $attendance, array( 'PRESENT', 'ABSENT' ), true );
			$admitted = in_array( $person['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true );
			if ( $admitted ) $metrics['admitted']++;
			foreach ( $person['options'] ?? array() as $option ) {
				$key = $option['code'] ?? $option['name'];
				$filters[$key] = array( 'code' => $key, 'name' => $option['name'] ?? $key );
				if ( ! $admitted || 0 === strpos( (string) $key, 'alloggio-' ) ) continue;
				// Name is relevant to category classification (e.g. insurance).
				$group = wp_json_encode( array( $key, $option['name'] ?? $key ) );
				if ( ! isset( $services[$group] ) ) $services[$group] = array( 'code' => $key, 'name' => $option['name'] ?? $key, 'quantity' => 0, 'people' => 0 );
				$services[$group]['quantity'] += (float) ( $option['quantity'] ?? 0 );
				if ( (float) ( $option['quantity'] ?? 0 ) > 0 ) $services[$group]['people']++;
			}
		}
		$result['metrics'] = $metrics;
		$result['service_totals'] = array_values( $services );
		$result['order_service_totals'] = array_values( $order_services );
		$result['service_filters'] = array_values( $filters );
		$result['option_definitions'] = array_map( static function ( $option ) { return array_intersect_key( $option, array_flip( array( 'code', 'name', 'category' ) ) ); }, $summary['option_definitions'] ?? array() );
		$result['field_keys'] = array_keys( $keys );
		$result['server_paging'] = true;
		$result['lazy_panels'] = true;
		return $result;
	}

	/** Minimal panel-specific data, requested only after the user opens that panel. */
	public static function panel( array $summary, $panel ) {
		if ( 'offers' === $panel ) return array( 'offers' => array_values( array_map( static function ( $item ) { return array_intersect_key( $item, array_flip( array( 'name', 'code', 'offer_expires_at' ) ) ); }, array_filter( $summary['items'], static function ( $item ) { return 'WAITLIST_OFFERED' === $item['status']; } ) ) ) );
		if ( 'attendance' === $panel ) return array( 'people' => array_values( array_map( static function ( $person ) { return array_intersect_key( $person, array_flip( array( 'id', 'name', 'attendance' ) ) ); }, array_filter( $summary['people'], static function ( $person ) { return in_array( $person['status'], array( 'CONFIRMED', 'PENDING_PAYMENT' ), true ); } ) ) ) );
		if ( 'rooms' !== $panel ) throw new InvalidArgumentException( 'Pannello non disponibile.' );
		$people = array_values( array_filter( $summary['people'], static function ( $person ) { return ! in_array( $person['status'], array( 'CANCELLED', 'EXPIRED' ), true ); } ) );
		return array( 'people' => array_map( static function ( $person ) { return array_intersect_key( $person, array_flip( array( 'id', 'number', 'code', 'name', 'status', 'room', 'options' ) ) ); }, $people ), 'rooms' => $summary['rooms'] ?? array(), 'room_types' => $summary['room_types'] ?? array(), 'rooms_version' => hash( 'sha256', wp_json_encode( $summary['rooms'] ?? array() ) ) );
	}

	/** Keep only the names and assignments needed by the inventory and service counters. */
	public static function compact( $summary ) {
		$keys = array_fill_keys( array_keys( $summary['field_labels'] ?? array() ), true );
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
