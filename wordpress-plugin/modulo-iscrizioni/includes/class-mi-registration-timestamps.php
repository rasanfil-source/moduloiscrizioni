<?php
defined( 'ABSPATH' ) || exit;

/** Date canoniche di iscrizione e annullamento, presentate nel fuso orario del sito. */
final class MI_Registration_Timestamps {
	const EVENT_META = '_mi_registration_timestamps';

	public static function enabled( $event_id ) {
		return '1' === get_post_meta( $event_id, self::EVENT_META, true );
	}

	private static function local_time( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $value ) ) return '';
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if ( ! $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) return '';
		return $date->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
	}

	public static function values( array $registration, ?array $participant = null ) {
		$cancelled = '';
		$status = $participant['status'] ?? 'ACTIVE';
		if ( 'CANCELLED' === $status ) $cancelled = $participant['cancelled_at'] ?? '';
		elseif ( 'ACTIVE' === $status && 'CANCELLED' === ( $registration['status'] ?? '' ) ) $cancelled = $registration['capacity_released_at'] ?? '';
		return array( 'registration_time' => self::local_time( $registration['created_at'] ?? '' ), 'cancellation_time' => self::local_time( $cancelled ) );
	}
}
