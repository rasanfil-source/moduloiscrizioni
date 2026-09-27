<?php
defined( 'ABSPATH' ) || exit;

/** One parser for publication checks and persisted event prices. Invalid is distinct from zero. */
final class MI_Amount {
 public static function cents( $raw ) {
  if ( ! is_scalar( $raw ) ) return null;
  $value = preg_replace( '/\s+/', '', trim( sanitize_text_field( (string) $raw ) ) );
  if ( preg_match( '/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/D', $value ) ) $value = str_replace( array( '.', ',' ), array( '', '.' ), $value );
  elseif ( preg_match( '/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/D', $value ) ) $value = str_replace( ',', '', $value );
  elseif ( preg_match( '/^\d+(?:[.,]\d{1,2})?$/D', $value ) ) $value = str_replace( ',', '.', $value );
  else return null;
  $parts = explode( '.', $value );
  if ( strlen( ltrim( $parts[0], '0' ) ) > 7 || (int) $parts[0] > 1000000 ) return null;
  $cents = (int) $parts[0] * 100 + (int) str_pad( $parts[1] ?? '', 2, '0' );
  return $cents <= 100000000 ? $cents : null;
 }
}
