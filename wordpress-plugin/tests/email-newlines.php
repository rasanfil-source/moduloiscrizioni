<?php
require __DIR__ . '/php-behavior.php';

// Riproduce lo slashing ricorsivo di WordPress, incluso update_post_meta.
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['email_meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['email_meta'][$id][$key] = wp_unslash($value); }
function get_option($key, $default = null) { return $default; }
function get_bloginfo($key) { return 'Segreteria'; }
function get_the_title($id) { return 'Evento'; }
function get_post_thumbnail_id($id) { return 0; }
function get_the_post_thumbnail_url($id, $size) { return ''; }
function wp_json_encode($value) { return json_encode($value); }
require __DIR__ . '/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
class MI_Shortcode { static function url_iscrizione($id) { return 'https://example.invalid/evento'; } }
class MI_Access { static function can_access_event($id) { return true; } }
function wp_verify_nonce($nonce, $action) { return true; }
function wp_is_post_autosave($id) { return false; }
function wp_is_post_revision($id) { return false; }
function current_user_can($cap) { return true; }
function delete_post_meta($id, $key) { unset($GLOBALS['email_meta'][$id][$key]); }

$text = "Iscrizione registrata.\n\nQuando: {{evento.data}}\nDove: Basilica di Sant'Eugenio\nStato: Iscrizione\n\nConserva questa email: anno, quando, nessuno, saranno.";
$payment = "\n\nPagamento: {{pagamento.istruzioni}}";
// Il vecchio implode generava esattamente i caratteri osservati nell'outbox.
$old = implode('\\n', explode("\n", $text));
update_post_meta(99, '_mi_email_template', ['text' => $old]);
expect(str_contains(get_post_meta(99, '_mi_email_template')['text'], "Sant'EugenionStato"), 'riproduzione della causa');

foreach (['ZERO', 'CALCULATED'] as $mode) {
    foreach (["\n", "\r\n", "\r", '\\n', '\\r\\n'] as $separator) {
        $GLOBALS['email_meta'][42] = ['_mi_pricing_mode' => $mode];
        $input = str_replace("\n", $separator, $text . $payment);
        expect(true === MI_Modello_Email::salva_testo_portale(42, wp_slash('Conferma'), wp_slash($input)), 'salvataggio modello');
        $stored = get_post_meta(42, '_mi_email_template');
        expect(str_contains($stored['text'], "Sant'Eugenio\nStato:"), 'a capo persi nei metadati');
        expect(!str_contains($stored['text'], '\\n'), 'separatori letterali nei metadati');
        expect(str_contains($stored['html'], 'Eugenio<br><strong>Stato:</strong>'), 'HTML salvato senza separazione');
        $snapshot = MI_Modello_Email::crea_istantanea(42, ['{{evento.data}}' => '18 ottobre', '{{pagamento.istruzioni}}' => 'Bonifico']);
        $preview = json_decode(wp_json_encode(['email_preview' => $snapshot]), true)['email_preview'];
        expect(str_contains($preview['testo'], "registrata.\n\nQuando: 18 ottobre"), 'snapshot testo senza paragrafi');
        expect(str_contains($preview['testo'], "Sant'Eugenio\nStato: Iscrizione\n\nConserva"), 'snapshot testo corrotto');
        expect(str_contains($preview['html'], 'Eugenio<br><strong>Stato:</strong>'), 'snapshot HTML corrotto');
        foreach (['anno', 'quando', 'nessuno', 'saranno'] as $word) {
            expect(str_contains($preview['testo'], $word) && str_contains($preview['html'], $word), 'parola alterata: ' . $word);
        }
        expect(('ZERO' !== $mode) === str_contains($preview['testo'], 'Bonifico'), 'filtro pagamento testo');
        expect(('ZERO' !== $mode) === str_contains($preview['html'], 'Bonifico'), 'filtro pagamento HTML');
        // Un secondo salvataggio non deve reintrodurre la corruzione.
        MI_Modello_Email::salva_testo_portale(42, wp_slash('Conferma'), wp_slash($stored['text']));
        expect($stored['text'] === get_post_meta(42, '_mi_email_template')['text'], 'salvataggio non idempotente');
    }
}
$filtered = MI_Modello_Email::rimuovi_riferimenti_pagamento_gratuito($text . $payment);
expect($filtered === $text, 'il filtro gratuito modifica le righe non economiche');
expect(MI_Modello_Email::rimuovi_riferimenti_pagamento_gratuito($filtered) === $filtered, 'filtro non idempotente');
// L'editor HTML mantiene il markup autonomo e i backslash non usati come separatori.
$GLOBALS['email_meta'][42] = ['_mi_pricing_mode' => 'CALCULATED'];
$custom_html = '<p><em>anno quando nessuno</em>\\nSeconda riga</p>';
$_POST = wp_slash(['mi_modello_email_nonce' => 'ok', 'mi_email_enabled' => '1', 'mi_email_subject' => 'Conferma', 'mi_email_text' => $text, 'mi_email_html' => $custom_html, 'mi_email_footer' => 'Prima\\nSeconda C:\\documenti']);
MI_Modello_Email::salva(42, (object)['ID' => 42]);
$snapshot = MI_Modello_Email::crea_istantanea(42, []);
expect(str_contains($snapshot['html'], "<em>anno quando nessuno</em>\nSeconda riga"), 'markup personalizzato o a capo HTML persi');
expect($snapshot['footer'] === "Prima\nSeconda C:\\documenti", 'footer o backslash perso');
// I separatori espliciti sono normalizzati anche in HTML indipendente.
$GLOBALS['email_meta'][42]['_mi_email_template'] = ['text' => 'Registrata.\n\nQuando: domani', 'html' => '<p>Registrata.\n\n<strong>Quando:</strong> domani</p>'];
$snapshot = MI_Modello_Email::crea_istantanea(42, []);
expect(str_contains($snapshot['testo'], "Registrata.\n\nQuando:"), 'testo storico');
expect(!str_contains($snapshot['html'], '.nn'), 'HTML storico non riparato insieme al testo');
// Nessuna inferenza da parole, punteggiatura o maiuscole: n/nn nude sono testo.
$literal = 'IscrizionennConserva anno quando nessuno saranno. nnQuando: n**Dove:**';
$snapshot = MI_Modello_Email::ripara_istantanea_codifica(['testo' => $literal, 'html' => '']);
expect($snapshot['testo'] === str_replace('**', '', $literal), 'lettere n interpretate semanticamente');
foreach (["\n", "\r\n", "\r", '\n', '\r\n', "\\\n", "\\\r\n", '&#10;'] as $separator) {
    $marked = '**Voce:** uno' . $separator . '**Altra:** due' . $separator . $separator . 'Ultima riga';
    $snapshot = MI_Modello_Email::ripara_istantanea_codifica(['testo' => $marked, 'html' => '']);
    expect($snapshot['testo'] === "Voce: uno\nAltra: due\n\nUltima riga", 'separatore deterministico non normalizzato: ' . bin2hex($separator));
    expect(str_contains($snapshot['html'], '<strong>Voce:</strong> uno<br><strong>Altra:</strong> due</p><p>Ultima riga'), 'HTML separatori deterministici');
    expect(!str_contains($snapshot['html'], '\\'), 'barra di hard break residua');
}
fwrite(STDOUT, "Email newline save/snapshot regression: OK\n");
