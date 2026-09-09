<?php
defined( 'ABSPATH' ) || exit;

final class MI_Extra_Services {
	public static function parse( $input ) {
		$labels = (array) ( $input['extra_service_label'] ?? array() );
		if ( count( $labels ) > 20 ) return new WP_Error( 'mi_extra_limit', 'Sono consentite al massimo 20 voci aggiuntive.' );
		$result = array(); $seen = array();
		foreach ( $labels as $i => $label ) {
			$label = sanitize_text_field( $label );
			if ( ! $label || mb_strlen( $label ) > 120 ) return new WP_Error( 'mi_extra_label', 'Indica un’etichetta per ogni voce aggiuntiva (massimo 120 caratteri).' );
			$price = trim( (string) ( $input['extra_service_price'][$i] ?? '' ) );
			if ( ! preg_match( '/^([0-9]{1,7})(?:[.,]([0-9]{1,2}))?$/', $price, $m ) ) return new WP_Error( 'mi_extra_price', 'Indica una quota valida per ' . $label . '.' );
			$cents = (int) $m[1] * 100 + (int) str_pad( $m[2] ?? '', 2, '0' );
			$code = sanitize_key( $input['extra_service_code'][$i] ?? '' );
			if ( ! preg_match( '/^extra-[a-z0-9-]{1,50}$/', $code ) ) $code = 'extra-' . str_replace( '-', '', wp_generate_uuid4() );
			if ( isset( $seen[$code] ) ) return new WP_Error( 'mi_extra_duplicate', 'Voce aggiuntiva duplicata.' );
			$seen[$code] = true;
			$category = sanitize_key( $input['extra_service_category'][$i] ?? 'altro' );
			if ( ! in_array( $category, array( 'alloggio','pullman','pranzo','altro' ), true ) ) $category = 'altro';
			$group = sanitize_key( $input['extra_service_group'][$i] ?? '' );
			if ( strlen( $group ) > 40 ) return new WP_Error( 'mi_extra_group', 'Il gruppo di alternative deve avere al massimo 40 caratteri.' );
			$result[] = array( 'code' => $code, 'name' => $label, 'scope' => 'TICKET', 'price_cents' => $cents, 'max_quantity' => 1, 'category' => $category, 'choice_group' => $group );
		}
		return $result;
	}
	public static function render( $options ) {
		echo '<div data-mi-extra-services><h3>Voci aggiuntive</h3><p>Aggiungi servizi o supplementi con la loro quota. Le voci sono cumulabili. Per renderle alternative, assegna lo stesso gruppo: usa “alloggio” per un’alternativa ai tipi di alloggio.</p><div data-mi-extra-list>';
		foreach ( $options as $option ) if ( 0 === strpos( (string) ( $option['code'] ?? '' ), 'extra-' ) ) self::row( $option );
		echo '</div><button type="button" data-mi-add-extra>Aggiungi voce</button><template data-mi-extra-template>';
		self::row( array() );
		echo '</template></div>';
	}
	private static function row( $option ) {
		echo '<div class="mi-extra-service"><input type="hidden" name="extra_service_code[]" value="' . esc_attr( $option['code'] ?? '' ) . '"><label>Etichetta<input name="extra_service_label[]" maxlength="120" required value="' . esc_attr( $option['name'] ?? '' ) . '"></label><label>Categoria<select name="extra_service_category[]">';
		foreach ( array( 'alloggio' => 'Alloggio','pullman' => 'Pullman','pranzo' => 'Pasti','altro' => 'Altro' ) as $code => $label ) echo '<option value="' . esc_attr( $code ) . '" ' . selected( $option['category'] ?? 'altro', $code, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><label>Quota (€)<input name="extra_service_price[]" inputmode="decimal" required value="' . esc_attr( isset( $option['price_cents'] ) ? number_format( $option['price_cents'] / 100, 2, ',', '' ) : '' ) . '"></label><label>Gruppo di alternative (facoltativo)<input name="extra_service_group[]" maxlength="40" pattern="[a-z0-9_-]*" value="' . esc_attr( $option['choice_group'] ?? '' ) . '" placeholder="Vuoto: voce cumulabile"></label><button type="button" data-mi-remove-extra>Rimuovi voce</button></div>';
	}
}
