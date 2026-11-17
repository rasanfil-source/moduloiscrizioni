<?php
// Modello reale, database simulato e nessun invio.
ob_start(); require __DIR__ . '/email-newlines.php'; ob_end_clean();
define('ARRAY_A', 'ARRAY_A');
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-travel.php';
class ConfirmationTravelDB {
    public $prefix = 'wp_', $last_error = '', $people = [], $reads = 0;
    function prepare($sql, ...$args) {
        expect($args === [9, 42], 'Riepilogo fuori dalla prenotazione/evento');
        expect(str_contains($sql, "p.status='ACTIVE'"), 'Partecipanti cancellati inclusi');
        return $sql;
    }
    function get_results($sql, $format) { ++$this->reads; return $this->people; }
}
$wpdb = new ConfirmationTravelDB();
$GLOBALS['email_meta'][42] = ['_mi_pricing_mode' => 'NONE'];
$base = MI_Modello_Email::crea_istantanea(42, []);
foreach (['html', 'testo'] as $format) expect(!str_contains($base[$format], 'Riferimenti dell’iscrizione:'), 'Frase standard residua');
$event = ['id' => 42, 'travel' => ['enabled' => true]];
$cases = [
    [['mode' => 'DRIVER', 'seats' => 2], 'Trasferimento con auto propria e offerta di 2 posti'],
    [['mode' => 'DRIVER', 'seats' => 1], 'Trasferimento con auto propria e offerta di 1 posto'],
    [['mode' => 'DRIVER', 'seats' => 0], 'Trasferimento con auto propria'],
    [['mode' => 'OWN'], 'Trasferimento autonomo'],
    [['mode' => 'NEED', 'state' => 'WAITING', 'required_ride' => 1], 'In attesa di passaggio necessario per partecipare'],
    [['mode' => 'NEED', 'state' => 'WAITING', 'required_ride' => 0], 'In attesa di passaggio'],
    [['mode' => 'NEED', 'state' => 'ASSIGNED'], 'Passaggio assegnato'],
    [['mode' => 'NEED', 'state' => 'OFFERED'], 'Passaggio proposto'],
];
foreach ($cases as [$person, $expected]) {
    $wpdb->people = [$person];
    $sample = MI_Travel::confirmation_email($base, $event, 9);
    expect(str_ends_with($sample['testo'], "Spostamenti:\n" . $expected), 'Riepilogo errato: ' . $expected);
    expect(str_contains($sample['html'], esc_html($expected)), 'Riepilogo HTML mancante');
    $internal = MI_Modello_Email::crea_istantanea_nuova_iscrizione_segreteria(42, ['{{evento.titolo}}'=>'Evento sintetico', '{{sottoscrittore.nome_completo}}'=>'Persona Esempio'], 9);
    $internal = MI_Travel::confirmation_email($internal, $event, 9);
    foreach (['html', 'testo'] as $format) {
        expect(str_contains($internal[$format], $expected), 'Trasferimento assente nella notifica segreteria');
        expect(str_contains($internal[$format], 'Persona Esempio'), 'Riferimenti prenotazione persi');
    }
    if (($person['state'] ?? '') === 'ASSIGNED') expect(!str_contains($sample['testo'], 'In attesa di passaggio'), 'Passaggio assegnato indicato in attesa');
}
$wpdb->people = [
    ['mode' => 'DRIVER', 'seats' => 2, 'first_name' => 'Ada & <test>', 'last_name' => 'Esempio'],
    ['mode' => 'NEED', 'state' => 'WAITING', 'required_ride' => 1, 'first_name' => 'Bruno', 'last_name' => 'Esempio'],
];
$sample = MI_Travel::confirmation_email($base, $event, 9);
expect(str_contains($sample['html'], 'Ada &amp; &lt;test&gt; Esempio:'), 'Nomi multipli senza escaping');
expect(str_contains($sample['testo'], 'Bruno Esempio: In attesa di passaggio necessario'), 'Stati individuali persi');
$internal = MI_Travel::confirmation_email(MI_Modello_Email::crea_istantanea_nuova_iscrizione_segreteria(42, [], 9), $event, 9);
expect(str_contains($internal['html'], 'Ada &amp; &lt;test&gt; Esempio:'), 'Nomi nella notifica segreteria senza escaping');
expect(str_contains($internal['testo'], 'Bruno Esempio: In attesa di passaggio necessario'), 'Trasferimenti multipli persi nella segreteria');
$pending = MI_Travel::confirmation_email(MI_Travel::pending_email(42, []), $event, 9);
foreach (['html', 'testo'] as $format) {
    expect(str_contains($pending[$format], 'Durante l’attesa non è richiesto alcun pagamento'), 'Condizioni della partecipazione in attesa perse');
    expect(str_contains($pending[$format], 'Bruno Esempio: In attesa di passaggio necessario'), 'Riepilogo assente nella conferma subordinata al passaggio');
}
$reads = $wpdb->reads;
expect(MI_Travel::confirmation_email($base, ['id' => 42], 9) === $base && $wpdb->reads === $reads, 'Eventi senza passaggi modificati');
$wpdb->people = [];
expect(MI_Travel::confirmation_email($base, $event, 9) === $base, 'Sezione vuota aggiunta');
$wpdb->last_error = 'errore sintetico';
try { MI_Travel::confirmation_email($base, $event, 9); expect(false, 'Errore database ignorato'); } catch (RuntimeException $e) {}
foreach ([
    'Conserva questa email: contiene i riferimenti utili per la tua iscrizione.',
    'Riferimenti dell’iscrizione: questa email contiene i riferimenti utili per l’iscrizione.',
] as $legacy) {
    $saved = ['html' => '<p>Nota personalizzata.</p><p><strong>' . strstr($legacy, ':', true) . ':</strong>' . substr(strstr($legacy, ':'), 1) . '</p>', 'text' => "Nota personalizzata.\n\n" . $legacy];
    $GLOBALS['email_meta'][42]['_mi_email_template'] = $saved;
    $sample = MI_Modello_Email::crea_istantanea(42, []);
    foreach (['html', 'testo'] as $format) expect(!str_contains($sample[$format], 'riferimenti utili') && str_contains($sample[$format], 'Nota personalizzata.'), 'Modello storico non aggiornato');
    expect($GLOBALS['email_meta'][42]['_mi_email_template'] === $saved, 'Metadati modificati durante la lettura');
}
$GLOBALS['email_meta'][42]['_mi_email_template'] = ['html' => '<p>Riferimenti dell’iscrizione: portare il documento.</p>', 'text' => 'Riferimenti dell’iscrizione: portare il documento.'];
$sample = MI_Modello_Email::crea_istantanea(42, []);
expect(str_contains($sample['html'], 'portare il documento') && str_contains($sample['testo'], 'portare il documento'), 'Riferimenti personalizzati rimossi');
echo "PASS: conferme con viaggi, posti, attesa necessaria/facoltativa, assegnazioni, gruppi e modelli storici.\n";
