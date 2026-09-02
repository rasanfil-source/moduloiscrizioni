<?php

defined( 'ABSPATH' ) || exit;

final class MI_Field_Schema {
	public static function catalog() {
		return array(
			'email' => array(
				'key'          => 'email',
				'label'        => 'Email',
				'type'         => 'email',
				'max_length'   => 254,
				'autocomplete' => 'email',
				'help'         => '',
			),
			'phone' => array(
				'key'          => 'phone',
				'label'        => 'Cellulare',
				'type'         => 'tel',
				'max_length'   => 32,
				'autocomplete' => 'tel',
				'help'         => 'Inserire il prefisso internazionale, per esempio +39.',
			),
			'birth_date' => array(
				'key'          => 'birth_date',
				'label'        => 'Data di nascita',
				'type'         => 'date',
				'autocomplete' => 'bday',
				'help'         => 'Raccogli soltanto se necessaria per l’evento.',
				'high_impact'  => true,
			),
			'nationality' => array(
				'key'          => 'nationality',
				'label'        => 'Nazionalità',
				'type'         => 'text',
				'max_length'   => 80,
				'autocomplete' => 'country-name',
				'help'         => 'Non sostituisce i dati di un documento di viaggio.',
				'high_impact'  => true,
			),
			'document_type' => array(
				'key' => 'document_type', 'label' => 'Tipo di documento', 'type' => 'select',
				'options' => array( 'Carta di identità', 'Passaporto' ), 'help' => 'Non caricare fotografie o scansioni.', 'high_impact' => true, 'retention' => 'SHEETS_ONLY',
			),
			'document_number' => array(
				'key' => 'document_number', 'label' => 'Numero del documento', 'type' => 'text', 'max_length' => 80,
				'help' => 'Viene trasferito a Sheets e rimosso da WordPress dopo la consegna.', 'high_impact' => true, 'retention' => 'SHEETS_ONLY',
			),
			'document_country' => array(
				'key' => 'document_country', 'label' => 'Paese di rilascio del documento', 'type' => 'text', 'max_length' => 80,
				'help' => 'Viene trasferito a Sheets e rimosso da WordPress dopo la consegna.', 'high_impact' => true, 'retention' => 'SHEETS_ONLY',
			),
			'document_expiry' => array(
				'key' => 'document_expiry', 'label' => 'Scadenza del documento', 'type' => 'date', 'date_rule' => 'future',
				'help' => 'Viene trasferita a Sheets e rimossa da WordPress dopo la consegna.', 'high_impact' => true, 'retention' => 'SHEETS_ONLY',
			),
			'postal_address' => array(
				'key'          => 'postal_address',
				'label'        => 'Indirizzo di residenza',
				'type'         => 'textarea',
				'max_length'   => 300,
				'autocomplete' => 'street-address',
				'help'         => 'Via, numero civico, CAP, comune e provincia.',
			),
			'tshirt_size' => array(
				'key'        => 'tshirt_size',
				'label'      => 'Taglia maglietta',
				'type'       => 'select',
				'options'    => array( 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL' ),
				'help'       => 'Usa una scelta controllata invece di testo libero.',
			),
		);
	}

	public static function profiles() {
		return array(
			'MINIMAL' => array(
				'label'    => 'Minimo',
				'enabled'  => array(),
				'required' => array(),
			),
			'STANDARD' => array(
				'label'    => 'Standard',
				'enabled'  => array( 'birth_date' ),
				'required' => array( 'birth_date' ),
			),
			'TRAVEL' => array(
				'label'    => 'Viaggio',
				'enabled'  => array( 'birth_date', 'nationality', 'postal_address' ),
				'required' => array( 'birth_date', 'nationality' ),
			),
			'CUSTOM' => array(
				'label'    => 'Personalizzato',
				'enabled'  => array(),
				'required' => array(),
			),
		);
	}

	public static function operational_profiles() {
		return array(
			'AUTOMATICO'       => 'Automatico in base ai dati e alle quote',
			'MINIMO'           => 'Elenco minimo: nominativo e cellulare',
			'QUOTA_UNICA'      => 'Quota unica con dettaglio degli incassi',
			'SERVIZI_MULTIPLI' => 'Più servizi: per esempio pullman e pranzo',
			'VIAGGIO_COMPLESSO'=> 'Viaggio complesso: documenti, servizi, sistemazioni e rate',
		);
	}

	public static function sanitize_operational_profile( $profile ) {
		$profile = strtoupper( sanitize_key( (string) $profile ) );
		return isset( self::operational_profiles()[ $profile ] ) ? $profile : 'AUTOMATICO';
	}

	public static function event_configuration( $event_id ) {
		$profiles = self::profiles();
		$profile = strtoupper( (string) get_post_meta( $event_id, '_mi_data_profile', true ) );
		if ( ! isset( $profiles[ $profile ] ) ) {
			$profile = 'MINIMAL';
		}

		$enabled = get_post_meta( $event_id, '_mi_participant_fields', true );
		$required = get_post_meta( $event_id, '_mi_participant_required_fields', true );
		if ( ! is_array( $enabled ) ) {
			$enabled = $profiles[ $profile ]['enabled'];
		}
		if ( ! is_array( $required ) ) {
			$required = $profiles[ $profile ]['required'];
		}

		return self::sanitize_configuration( $profile, $enabled, $required );
	}

	public static function sanitize_configuration( $profile, $enabled, $required ) {
		$profiles = self::profiles();
		$catalog = self::catalog();
		$profile = strtoupper( sanitize_key( (string) $profile ) );
		if ( ! isset( $profiles[ $profile ] ) ) {
			$profile = 'CUSTOM';
		}
		$enabled = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $enabled ), static function ( $key ) use ( $catalog ) {
			return isset( $catalog[ $key ] );
		} ) ) );
		$required = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $required ), static function ( $key ) use ( $enabled ) {
			return in_array( $key, $enabled, true );
		} ) ) );
		return array( 'profile' => $profile, 'enabled' => $enabled, 'required' => $required );
	}

	public static function public_fields( $configuration ) {
		$catalog = self::catalog();
		$fields = array();
		foreach ( $configuration['enabled'] as $key ) {
			if ( ! isset( $catalog[ $key ] ) ) {
				continue;
			}
			$field = $catalog[ $key ];
			$field['required'] = in_array( $key, $configuration['required'], true );
			$fields[] = $field;
		}
		return $fields;
	}

	public static function sanitize_custom_fields( $raw_fields ) {
		$result = array();
		$seen = array();
		foreach ( array_slice( (array) $raw_fields, 0, 20 ) as $index => $raw ) {
			if ( ! is_array( $raw ) ) continue;
			$label = mb_substr( sanitize_text_field( $raw['label'] ?? '' ), 0, 120 );
			$key = sanitize_key( $raw['key'] ?? '' );
			$key = $key ? 'custom_' . preg_replace( '/^custom_/', '', $key ) : 'custom_domanda_' . ( $index + 1 );
			$type = in_array( $raw['type'] ?? '', array( 'text', 'textarea', 'date', 'select', 'yesno', 'email', 'tel' ), true ) ? $raw['type'] : 'text';
			if ( ! $label || isset( $seen[ $key ] ) ) continue;
			$seen[ $key ] = true;
			$retention = 'SHEETS_ONLY' === strtoupper( sanitize_key( $raw['retention'] ?? '' ) ) ? 'SHEETS_ONLY' : 'STANDARD';
			$field = array( 'key' => $key, 'label' => $label, 'type' => $type, 'required' => ! empty( $raw['required'] ), 'max_length' => 'textarea' === $type ? 1000 : ( 'email' === $type ? 254 : 180 ), 'help' => '', 'retention' => $retention );
			if ( 'yesno' === $type ) {
				$field['options'] = array( 'Sì', 'No' );
				$field['help'] = 'Scegli la risposta appropriata.';
			} elseif ( 'select' === $type ) {
				$options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', preg_split( '/[\r\n|]+/', (string) ( $raw['options'] ?? '' ) ) ) ) ) );
				if ( count( $options ) < 2 ) continue;
				$field['options'] = array_slice( $options, 0, 30 );
			}
			$result[] = $field;
		}
		return $result;
	}

	public static function has_high_impact_fields( $configuration ) {
		$catalog = self::catalog();
		foreach ( (array) ( $configuration['enabled'] ?? array() ) as $key ) {
			if ( ! empty( $catalog[ $key ]['high_impact'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function validate_answers( $raw, $fields ) {
		$raw = is_array( $raw ) ? $raw : array();
		$answers = array();
		foreach ( $fields as $field ) {
			$key = $field['key'];
			$value = $raw[ $key ] ?? '';
			if ( is_array( $value ) ) {
				return new WP_Error( 'mi_participant_field_invalid', 'Controlla i dati aggiuntivi dei partecipanti.', array( 'status' => 400 ) );
			}
			$value = trim( (string) $value );
			if ( empty( $value ) ) {
				if ( ! empty( $field['required'] ) ) {
					return new WP_Error( 'mi_participant_field_required', 'Completa tutti i dati obbligatori dei partecipanti.', array( 'status' => 400 ) );
				}
				continue;
			}
			if ( 'email' === $field['type'] ) {
				$value = sanitize_email( $value );
				if ( ! is_email( $value ) ) return new WP_Error( 'mi_participant_email_invalid', 'Controlla le email dei partecipanti.', array( 'status' => 400 ) );
				$answers[ $key ] = $value;
			} elseif ( 'tel' === $field['type'] ) {
				$value = preg_replace( '/[^0-9+().\s-]/', '', $value );
				if ( ! preg_match( '/^\+[1-9][0-9().\s-]{6,30}$/', $value ) ) return new WP_Error( 'mi_participant_phone_invalid', 'Controlla i cellulari dei partecipanti.', array( 'status' => 400 ) );
				$answers[ $key ] = $value;
			} elseif ( 'date' === $field['type'] ) {
				$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
				$today = new DateTimeImmutable( 'today' );
				$oldest = $today->modify( '-120 years' );
				$future_rule = 'future' === ( $field['date_rule'] ?? '' );
				$invalid_date = ! $date || $date->format( 'Y-m-d' ) !== $value || ( $future_rule ? $date < $today || $date > $today->modify( '+20 years' ) : $date > $today || $date < $oldest );
				if ( $invalid_date ) {
					return new WP_Error( 'mi_participant_date_invalid', 'Controlla le date dei partecipanti.', array( 'status' => 400 ) );
				}
				$answers[ $key ] = $value;
			} elseif ( in_array( $field['type'], array( 'select', 'yesno' ), true ) ) {
				if ( ! in_array( $value, $field['options'], true ) ) {
					return new WP_Error( 'mi_participant_choice_invalid', 'Controlla le opzioni dei partecipanti.', array( 'status' => 400 ) );
				}
				$answers[ $key ] = $value;
			} else {
				$value = sanitize_textarea_field( $value );
				if ( strlen( $value ) > (int) ( $field['max_length'] ?? 300 ) ) {
					return new WP_Error( 'mi_participant_text_invalid', 'Uno dei dati dei partecipanti è troppo lungo.', array( 'status' => 400 ) );
				}
				$answers[ $key ] = $value;
			}
		}
		return $answers;
	}

	public static function relay_only_keys( $fields ) {
		return array_values( array_map( static function ( $field ) { return $field['key']; }, array_filter( (array) $fields, static function ( $field ) { return 'SHEETS_ONLY' === ( $field['retention'] ?? 'STANDARD' ); } ) ) );
	}
}
