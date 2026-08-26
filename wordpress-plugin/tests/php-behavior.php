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
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function esc_url_raw( $value ) { return esc_url( $value ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? strtolower( (string) $value ) : null; }
function wp_date( $format, $timestamp, $timezone = null ) { $date = new DateTimeImmutable( '@' . $timestamp ); return $date->setTimezone( $timezone ?: wp_timezone() )->format( $format ); }
function wp_kses_allowed_html( $context ) { return array( 'p' => array( 'style' => true ), 'table' => array( 'style' => true ), 'tr' => array( 'style' => true ), 'td' => array( 'style' => true ), 'a' => array( 'href' => true, 'style' => true ), 'div' => array( 'style' => true ), 'strong' => array(), 'code' => array(), 'br' => array(), 'img' => array( 'src' => true, 'alt' => true, 'style' => true ) ); }
function wp_kses_post( $value ) { return (string) $value; }
function wp_kses( $value, $allowed ) { return (string) $value; }

require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-field-schema.php';
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-registration-service.php';
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-code-image.php';
require_once __DIR__ . '/../modulo-iscrizioni/includes/class-mi-modello-email.php';

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
		array( 'code' => 'intero', 'name' => 'Intero', 'price_cents' => 1200, 'max_per_order' => 4, 'capacity' => 10 ),
		array( 'code' => 'gratuito', 'name' => 'Gratuito', 'price_cents' => 0, 'max_per_order' => 2, 'capacity' => 0 ),
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

$fixed_event = $event;
$fixed_event['pricing_mode'] = 'FIXED';
$fixed_event['fixed_price_cents'] = 1500;
$fixed_selection = invoke_private( 'validate_selection', array( $fixed_event, array( 'intero' => 2 ) ) );
expect( ! is_wp_error( $fixed_selection ) && 3000 === $fixed_selection['total_cents'] && 1500 === $fixed_selection['items'][0]['unit_price_cents'], 'quota uguale per tutti non applicata' );

$too_many = invoke_private( 'validate_selection', array( $event, array( 'intero' => 5 ) ) );
expect( is_wp_error( $too_many ) && 'mi_ticket_limit' === $too_many->code, 'limite quota non applicato' );
$fractional = invoke_private( 'validate_selection', array( $event, array( 'intero' => 1.5 ) ) );
expect( is_wp_error( $fractional ) && 'mi_ticket_quantity_invalid' === $fractional->code, 'quantità frazionaria accettata' );

$one_selection = invoke_private( 'validate_selection', array( $event, array( 'intero' => 1 ) ) );
$participants = invoke_private( 'validate_participants', array( array( array( 'ticket_type_code' => 'intero', 'ticket_index' => 1, 'first_name' => 'Persona', 'last_name' => 'Demo' ) ), $one_selection, array(), array() ) );
expect( ! is_wp_error( $participants ) && 1 === count( $participants ), 'partecipante minimo non accettato' );

$extended_fields = array(
	array( 'key' => 'birth_date', 'type' => 'date', 'required' => true ),
	array( 'key' => 'tshirt_size', 'type' => 'select', 'required' => false, 'options' => array( 'S', 'M', 'L' ) ),
);
$extended = invoke_private( 'validate_participants', array( array( array( 'ticket_type_code' => 'intero', 'ticket_index' => 1, 'first_name' => 'Persona', 'last_name' => 'Demo', 'fields' => array( 'birth_date' => '2000-01-02', 'tshirt_size' => 'M', 'ignored' => 'no' ) ) ), $one_selection, $extended_fields, array() ) );
expect( ! is_wp_error( $extended ) && ! isset( $extended[0]['fields']['ignored'] ), 'allowlist campi estesi non applicata' );
$missing_required = invoke_private( 'validate_participants', array( array( array( 'ticket_type_code' => 'intero', 'ticket_index' => 1, 'first_name' => 'Persona', 'last_name' => 'Demo', 'fields' => array() ) ), $one_selection, $extended_fields, array() ) );
expect( is_wp_error( $missing_required ), 'campo esteso obbligatorio non applicato' );

$implausible_birth_date = invoke_private( 'validate_participants', array( array( array( 'ticket_type_code' => 'intero', 'ticket_index' => 1, 'first_name' => 'Persona', 'last_name' => 'Demo', 'fields' => array( 'birth_date' => '1800-01-01' ) ) ), $one_selection, $extended_fields, array() ) );
expect( is_wp_error( $implausible_birth_date ), 'data di nascita anteriore a 120 anni accettata' );

$two_selection = invoke_private( 'validate_selection', array( $event, array( 'intero' => 2 ) ) );
$duplicate_ticket_index = invoke_private( 'validate_participants', array( array(
	array( 'ticket_type_code' => 'intero', 'ticket_index' => 1, 'first_name' => 'Uno', 'last_name' => 'Demo' ),
	array( 'ticket_type_code' => 'intero', 'ticket_index' => 1, 'first_name' => 'Due', 'last_name' => 'Demo' ),
), $two_selection, array(), array() ) );
expect( is_wp_error( $duplicate_ticket_index ), 'indice tipologia duplicato accettato' );

$missing_second_name = invoke_private( 'validate_participants', array( array(
	array( 'ticket_type_code' => 'intero', 'ticket_index' => 1, 'first_name' => 'Uno', 'last_name' => 'Demo' ),
	array( 'ticket_type_code' => 'intero', 'ticket_index' => 2, 'first_name' => '', 'last_name' => '' ),
), $two_selection, array(), array(), 'ONE' ) );
expect( is_wp_error( $missing_second_name ), 'nome e cognome del secondo partecipante non richiesti' );

$one_scope = invoke_private( 'validate_participants', array( array(
	array( 'ticket_type_code' => 'intero', 'ticket_index' => 1, 'first_name' => 'Uno', 'last_name' => 'Demo', 'fields' => array( 'birth_date' => '2000-01-02' ) ),
	array( 'ticket_type_code' => 'intero', 'ticket_index' => 2, 'first_name' => 'Due', 'last_name' => 'Demo', 'fields' => array() ),
), $two_selection, $extended_fields, array(), 'ONE' ) );
expect( ! is_wp_error( $one_scope ), 'ambito dati aggiuntivi ONE non rispettato' );
$all_scope = invoke_private( 'validate_participants', array( array(
	array( 'ticket_type_code' => 'intero', 'ticket_index' => 1, 'first_name' => 'Uno', 'last_name' => 'Demo', 'fields' => array( 'birth_date' => '2000-01-02' ) ),
	array( 'ticket_type_code' => 'intero', 'ticket_index' => 2, 'first_name' => 'Due', 'last_name' => 'Demo', 'fields' => array() ),
), $two_selection, $extended_fields, array(), 'ALL' ) );
expect( is_wp_error( $all_scope ), 'ambito dati aggiuntivi ALL non applicato' );

$options = array(
	array( 'code' => 'pranzo', 'name' => 'Pranzo', 'scope' => 'ORDER', 'price_cents' => 500, 'max_quantity' => 2 ),
	array( 'code' => 'maglia', 'name' => 'Maglia', 'scope' => 'TICKET', 'price_cents' => 800, 'max_quantity' => 1 ),
);
$order_options = invoke_private( 'validate_options', array( array( 'pranzo' => 2 ), $options, 'ORDER' ) );
$ticket_options = invoke_private( 'validate_options', array( array( 'maglia' => 1 ), $options, 'TICKET' ) );
expect( ! is_wp_error( $order_options ) && 1 === count( $order_options ), 'opzione ordine valida rifiutata' );
$options_total = invoke_private( 'options_total', array( $order_options, array( array( 'options' => $ticket_options ) ) ) );
expect( 1800 === $options_total, 'totale opzioni server-side errato' );
$cross_scope = invoke_private( 'validate_options', array( array( 'maglia' => 1 ), $options, 'ORDER' ) );
expect( is_wp_error( $cross_scope ), 'opzione partecipante accettata a livello ordine' );
$fractional_option = invoke_private( 'validate_options', array( array( 'pranzo' => 1.5 ), $options, 'ORDER' ) );
expect( is_wp_error( $fractional_option ), 'quantità opzione frazionaria accettata' );

$buyer = invoke_private( 'validate_buyer', array( array( 'first_name' => 'Referente', 'last_name' => 'Demo', 'email' => 'referente@example.invalid', 'phone' => '+39 000 0000000' ) ) );
expect( ! is_wp_error( $buyer ), 'referente dimostrativo valido rifiutato' );

$bad_buyer = invoke_private( 'validate_buyer', array( array( 'first_name' => 'Referente', 'last_name' => 'Demo', 'email' => 'non-valida', 'phone' => '123' ) ) );
expect( is_wp_error( $bad_buyer ), 'referente non valido accettato' );

$state = MI_Registration_Service::registration_state( array( 'opens_at' => '2030-02-02T10:00', 'closes_at' => '2030-02-01T10:00' ) );
expect( 'MISCONFIGURED' === $state, 'finestra temporale inversa non rilevata' );

$qr_svg = MI_Code_Image::svg( 'QR', 'modulo-iscrizioni|evento:42|ordine:MI-260826-DEMO1234' );
expect( false !== strpos( $qr_svg, '<svg' ) && false !== strpos( $qr_svg, 'viewBox="0 0 45 45"' ), 'QR locale non generato' );
$barcode_svg = MI_Code_Image::svg( 'BARCODE', 'MI-260826-DEMO1234' );
expect( false !== strpos( $barcode_svg, '<svg' ) && false !== strpos( $barcode_svg, 'MI-260826-DEMO1234' ), 'barcode locale non generato' );

$email_event = array(
	'title' => 'Evento Demo',
	'activity' => 'Attività Demo',
	'event_starts_at' => '2030-05-04T09:30',
	'event_location' => 'Oratorio',
	'payment_deadline_at' => '2030-05-01T23:59',
	'privacy_url' => 'https://example.invalid/privacy',
);
$email_economic = array( 'total_cents' => 5000, 'initial_due_cents' => 2000, 'balance_cents' => 3000, 'payment_methods' => array( 'BANK_TRANSFER' ) );
$email_values = MI_Modello_Email::valori_ordine( $email_event, 'MI-DEMO-1', 'In attesa di pagamento', 2, 'Persona Demo', $email_economic, array( array( 'name' => 'Quota', 'quantity' => 2 ) ) );
expect( 'Oratorio' === $email_values['{{evento.luogo}}'], 'luogo evento assente dai segnaposto email' );
expect( false !== strpos( $email_values['{{ordine.riepilogo_economico}}'], '50,00 €' ), 'riepilogo economico email errato' );
expect( false !== strpos( $email_values['{{pagamento.istruzioni}}'], 'Bonifico' ), 'istruzioni di pagamento email assenti' );

$email_html = MI_Modello_Email::componi_html( array(
	'preheader' => 'Anteprima nascosta',
	'html' => '<p>Contenuto</p>',
	'footer' => 'Un saluto',
	'identita' => array( 'nome_attivita' => 'Attività Demo', 'primary_color' => '#151b38', 'secondary_color' => '#337ab7', 'primary_text_color' => '#ffffff', 'secondary_text_color' => '#ffffff' ),
	'identita_email' => array( 'indirizzo_risposte' => 'assistenza@example.invalid' ),
	'evento' => array( 'titolo' => 'Evento Demo', 'url' => 'https://example.invalid/evento' ),
) );
expect( false !== strpos( $email_html, 'max-width:600px' ), 'card email da 600px assente' );
expect( false !== strpos( $email_html, 'opacity:0;color:transparent' ), 'preheader nascosto incompleto' );
expect( false !== strpos( $email_html, 'role="presentation"' ) && false !== strpos( $email_html, 'cellpadding="0"' ) && false !== strpos( $email_html, 'bgcolor="#151b38"' ), 'markup email-safe incompleto' );
expect( false !== strpos( $email_html, 'Assistenza' ) && false !== strpos( $email_html, 'border-radius:12px' ) && false !== strpos( $email_html, 'font-style:italic' ), 'componenti del restyling email assenti' );

fwrite( STDOUT, "PHP behavior tests: OK\n" );
