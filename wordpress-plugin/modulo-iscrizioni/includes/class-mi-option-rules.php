<?php
defined( 'ABSPATH' ) || exit;

/** Regole di significato condivise da iscrizioni, modifiche della segreteria e funzioni pubbliche. */
final class MI_Option_Rules {
	public static function is_accommodation( array $option ) {
		return 'alloggio' === ( $option['category'] ?? '' ) || 'alloggio' === ( $option['choice_group'] ?? '' ) || 0 === strpos( (string) ( $option['code'] ?? '' ), 'alloggio-' );
	}
	public static function is_bus( array $option ) {
		return ! self::is_accommodation( $option ) && ( 'pullman' === ( $option['category'] ?? '' ) || 0 === strpos( (string) ( $option['code'] ?? '' ), 'pullman-' ) );
	}
	public static function choice_group( array $option ) {
		$group = trim( (string) ( $option['choice_group'] ?? '' ) );
		// La categoria descrive il servizio; un gruppo definisce le opzioni alternative.
		// I servizi aggiuntivi per l'alloggio sono cumulabili, ad esempio per notti ulteriori.
		return '' !== $group ? $group : ( 0 === strpos( (string) ( $option['code'] ?? '' ), 'alloggio-' ) ? 'alloggio' : '' );
	}
}
