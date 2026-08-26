<?php

defined( 'ABSPATH' ) || exit;

final class MI_Field_Schema {
	public static function catalog() {
		return array(
			'birth_date' => array(
				'key'          => 'birth_date',
				'label'        => 'Data di nascita',
				'type'         => 'date',
				'autocomplete' => 'bday',
				'help'         => 'Raccogli soltanto se necessaria per l’attività.',
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
			if ( 'date' === $field['type'] ) {
				$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
				$today = new DateTimeImmutable( 'today' );
				$oldest = $today->modify( '-120 years' );
				if ( ! $date || $date->format( 'Y-m-d' ) !== $value || $date > $today || $date < $oldest ) {
					return new WP_Error( 'mi_participant_date_invalid', 'Controlla le date dei partecipanti.', array( 'status' => 400 ) );
				}
				$answers[ $key ] = $value;
			} elseif ( 'select' === $field['type'] ) {
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
}
