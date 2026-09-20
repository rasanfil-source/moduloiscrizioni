<?php
defined( 'ABSPATH' ) || exit;

/** Semantic rules shared by registration, staff changes and public self-service. */
final class MI_Option_Rules {
	public static function is_accommodation( array $option ) {
		return 'alloggio' === ( $option['category'] ?? '' ) || 'alloggio' === ( $option['choice_group'] ?? '' ) || 0 === strpos( (string) ( $option['code'] ?? '' ), 'alloggio-' );
	}
	public static function is_bus( array $option ) {
		return ! self::is_accommodation( $option ) && ( 'pullman' === ( $option['category'] ?? '' ) || 0 === strpos( (string) ( $option['code'] ?? '' ), 'pullman-' ) );
	}
	public static function choice_group( array $option ) {
		$group = trim( (string) ( $option['choice_group'] ?? '' ) );
		// Category describes a service; only a group makes it an alternative.
		// Preserve cumulative accommodation extras (for example additional nights).
		return '' !== $group ? $group : ( 0 === strpos( (string) ( $option['code'] ?? '' ), 'alloggio-' ) ? 'alloggio' : '' );
	}
}
