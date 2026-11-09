<?php
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
function is_email($value) { return is_string($value) && false !== strpos($value, '@'); }
function get_the_title($id) { return 'Evento prova'; }
function wp_json_encode($value) { return json_encode($value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function current_time($type, $gmt = false) { return '2026-11-08 12:00:00'; }
class MI_Modello_Email { static function crea_istantanea($event, $values) { return array('attivo' => false, 'html' => '', 'testo' => ''); } }
class MI_Portal { static function balance_url($id, $code, $email) { return 'https://example.invalid/balance'; } }
class BookingUpdateDatabase {
	public $prefix = 'wp_';
	public $last_error = '';
	public $registration = array(
		'id' => 7, 'event_id' => 42, 'order_code' => 'ORDER7', 'status' => 'CONFIRMED',
		'buyer_email' => 'buyer@example.invalid', 'economic_mode' => 'DEPOSIT_BALANCE',
		'total_cents' => 5000, 'initial_due_cents' => 1000,
		'snapshot_json' => '{"event":{"deposit_mode":"FIXED","deposit_fixed_cents":1000}}',
		'order_options_json' => '[{"code":"shared","name":"Servizio comune","quantity":1,"unit_price_cents":1000}]',
		'common_allocations_json' => '{"11":999}'
	);
	public $person = array('id' => 11, 'ticket_type_code' => 'base', 'first_name' => 'Ada', 'last_name' => 'Rossi', 'status' => 'ACTIVE', 'room_code' => '', 'options_json' => '[]', 'extra_json' => '{}', 'deposit_due_cents' => 1000);
	function prepare($sql, ...$args) { return array($sql, $args); }
	function get_row($query, $format) { return $this->registration; }
	function get_results($query, $format) {
		$sql = is_array($query) ? $query[0] : $query;
		if (strpos($sql, 'mi_payments') !== false) return array();
		if (strpos($sql, 'mi_registration_items') !== false) return array(array('ticket_type_code' => 'base', 'unit_price_cents' => 5000));
		return array($this->person);
	}
}
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-payment-people.php';
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-booking-update-email.php';
$wpdb = new BookingUpdateDatabase();
$payload = MI_Booking_Update_Email::snapshot(7);
if (empty($payload['email_preview']['attivo'])) throw new RuntimeException('La notifica aggiornamento deve essere autonoma dalla conferma iniziale disabilitata.');
$text = $payload['email_preview']['testo'] ?? '';
if (!$payload || !str_contains($text, 'Ada Rossi') || !str_contains($text, 'Servizio comune') || !str_contains($text, 'La situazione economica è da verificare con la segreteria.') || str_contains($text, 'Quota complessiva dei partecipanti attivi')) {
	throw new RuntimeException('L’email deve essere accodata senza importi quando la ripartizione storica non è valida.');
}
echo "Email aggiornamento prenotazione: ripartizione incoerente isolata senza bloccare la modifica.\n";
