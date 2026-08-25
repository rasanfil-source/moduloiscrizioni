<?php

define( 'ABSPATH', __DIR__ . '/' );

final class WP_Error {
	public $code;
	public $message;
	public function __construct( $code, $message, $data = null ) { $this->code = $code; $this->message = $message; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }

require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-field-schema.php';
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';

function invoke_private( $name, array $arguments ) {
	$method = new ReflectionMethod( MI_Registration_Service::class, $name );
	$method->setAccessible( true );
	return $method->invokeArgs( null, $arguments );
}

function expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$event = array(
	'pricing_mode' => 'CALCULATED',
	'ticket_types' => array(
		array( 'code' => 'intero', 'price_cents' => 1200, 'max_per_order' => 4 ),
		array( 'code' => 'gratuito', 'price_cents' => 0, 'max_per_order' => 2 ),
	),
);

$selection = invoke_private( 'validate_selection', array( $event, array( 'intero' => 2, 'gratuito' => 1 ) ) );
expect( ! is_wp_error( $selection ), 'selezione valida rifiutata' );
expect( 3 === $selection['quantity'], 'quantità totale errata' );
expect( 2400 === $selection['total_cents'], 'totale server-side errato' );

$free_event = $event;
$free_event['pricing_mode'] = 'ZERO';
$free_selection = invoke_private( 'validate_selection', array( $free_event, array( 'intero' => 2 ) ) );
expect( ! is_wp_error( $free_selection ) && 0 === $free_selection['total_cents'], 'evento gratuito con totale diverso da zero' );

$too_many = invoke_private( 'validate_selection', array( $event, array( 'intero' => 5 ) ) );
expect( is_wp_error( $too_many ) && 'mi_ticket_limit' === $too_many->code, 'limite quota non applicato' );

$participants = invoke_private( 'validate_participants', array( array( array( 'first_name' => 'Persona', 'last_name' => 'Demo' ) ), 1, array() ) );
expect( ! is_wp_error( $participants ) && 1 === count( $participants ), 'partecipante minimo non accettato' );

$extended_fields = array(
	array( 'key' => 'birth_date', 'type' => 'date', 'required' => true ),
	array( 'key' => 'tshirt_size', 'type' => 'select', 'required' => false, 'options' => array( 'S', 'M', 'L' ) ),
);
$extended = invoke_private( 'validate_participants', array( array( array( 'first_name' => 'Persona', 'last_name' => 'Demo', 'fields' => array( 'birth_date' => '2000-01-02', 'tshirt_size' => 'M', 'ignored' => 'no' ) ) ), 1, $extended_fields ) );
expect( ! is_wp_error( $extended ) && ! isset( $extended[0]['fields']['ignored'] ), 'allowlist campi estesi non applicata' );
$missing_required = invoke_private( 'validate_participants', array( array( array( 'first_name' => 'Persona', 'last_name' => 'Demo', 'fields' => array() ) ), 1, $extended_fields ) );
expect( is_wp_error( $missing_required ), 'campo esteso obbligatorio non applicato' );

$implausible_birth_date = invoke_private( 'validate_participants', array( array( array( 'first_name' => 'Persona', 'last_name' => 'Demo', 'fields' => array( 'birth_date' => '1800-01-01' ) ) ), 1, $extended_fields ) );
expect( is_wp_error( $implausible_birth_date ), 'data di nascita anteriore a 120 anni accettata' );

$buyer = invoke_private( 'validate_buyer', array( array( 'first_name' => 'Referente', 'last_name' => 'Demo', 'email' => 'referente@example.invalid', 'phone' => '+39 000 0000000' ) ) );
expect( ! is_wp_error( $buyer ), 'referente dimostrativo valido rifiutato' );

$bad_buyer = invoke_private( 'validate_buyer', array( array( 'first_name' => 'Referente', 'last_name' => 'Demo', 'email' => 'non-valida', 'phone' => '123' ) ) );
expect( is_wp_error( $bad_buyer ), 'referente non valido accettato' );

$state = MI_Registration_Service::registration_state( array( 'opens_at' => '2030-02-02T10:00', 'closes_at' => '2030-02-01T10:00' ) );
expect( 'MISCONFIGURED' === $state, 'finestra temporale inversa non rilevata' );

fwrite( STDOUT, "PHP behavior tests: OK\n" );
