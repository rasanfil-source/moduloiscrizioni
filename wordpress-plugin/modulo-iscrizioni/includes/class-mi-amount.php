<?php
defined( 'ABSPATH' ) || exit;

/** Un solo parser verifica la pubblicazione e i prezzi salvati. Un valore non valido resta distinto da zero. */
final class MI_Amount {
 public static function valid_event_prices( array $input ) {
  if ( 'FIXED' === strtoupper( sanitize_key( wp_unslash( $input['mi_pricing_mode'] ?? 'NONE' ) ) ) && ( self::cents( wp_unslash( $input['mi_fixed_price'] ?? '' ) ) ?? 0 ) < 1 ) return false;
  if ( 'DEPOSIT_BALANCE' === strtoupper( sanitize_key( wp_unslash( $input['mi_economic_mode'] ?? '' ) ) ) && 'FIXED' === strtoupper( sanitize_key( wp_unslash( $input['mi_deposit_mode'] ?? '' ) ) ) && ( self::cents( wp_unslash( $input['mi_deposit_fixed'] ?? '' ) ) ?? 0 ) < 1 ) return false;
  foreach ( array( 'mi_ticket_price', 'mi_option_price' ) as $key ) foreach ( (array) wp_unslash( $input[$key] ?? array() ) as $raw ) if ( null === self::cents( $raw ) ) return false;
  return true;
 }
 public static function cents( $raw, $maximum_cents = 100000000 ) {
  if ( ! is_scalar( $raw ) ) return null;
  $value = preg_replace( '/\s+/', '', trim( sanitize_text_field( (string) $raw ) ) );
  if ( preg_match( '/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/D', $value ) ) $value = str_replace( array( '.', ',' ), array( '', '.' ), $value );
  elseif ( preg_match( '/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/D', $value ) ) $value = str_replace( ',', '', $value );
  elseif ( preg_match( '/^\d+(?:[.,]\d{1,2})?$/D', $value ) ) $value = str_replace( ',', '.', $value );
  else return null;
  $parts = explode( '.', $value );
  if ( strlen( ltrim( $parts[0], '0' ) ) > strlen( (string) intdiv( $maximum_cents, 100 ) ) ) return null;
  $cents = (int) $parts[0] * 100 + (int) str_pad( $parts[1] ?? '', 2, '0' );
  return $cents <= $maximum_cents ? $cents : null;
 }
}
