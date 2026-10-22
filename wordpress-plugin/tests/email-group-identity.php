<?php
require __DIR__ . '/email-newlines.php';

$GLOBALS['email_meta'][42] = ['_mi_activity_id' => 9];
$GLOBALS['email_meta'][9] = ['_mi_email_style' => ['identity_name' => 'Gruppo Giovani', 'signature' => 'I responsabili del gruppo']];
$snapshot = MI_Modello_Email::crea_istantanea(42, []);
expect($snapshot['identita_email']['nome_mittente'] === 'Gruppo Giovani', 'nome del gruppo ignorato');
class MI_Workspace_Client {
    static $payload;
    static $action;
    static function request($action, $payload) { expect(in_array($action, ['INVIA_EMAIL_CONFERMA','INVIA_EMAIL_CONFERMA_MITTENTE'], true), 'azione invio'); self::$action = $action; self::$payload = $payload; return ['ok' => true, 'channel' => 'GOOGLE_WORKSPACE']; }
}
$send = new ReflectionMethod(MI_Spedizione_Email::class, 'invia_istantanea');
$send->setAccessible(true);
expect(true === $send->invoke(null, 'test@example.invalid', $snapshot, str_repeat('a', 64), false), 'invio simulato');
expect(MI_Workspace_Client::$payload['nome_mittente'] === 'Gruppo Giovani', 'nome perso nel payload Apps Script');
expect(str_contains(MI_Workspace_Client::$payload['html'], 'I responsabili del gruppo') && str_contains(MI_Workspace_Client::$payload['testo'], 'I responsabili del gruppo'), 'firma persa nel payload');
foreach (['componi_html', 'componi_testo'] as $method) {
    $body = MI_Modello_Email::$method($snapshot);
    expect(str_contains($body, 'A presto!') && str_contains($body, 'I responsabili del gruppo'), 'saluto nasconde la firma: ' . $method);
    $snapshot['footer'] = 'I responsabili del gruppo';
    expect(substr_count(MI_Modello_Email::$method($snapshot), 'I responsabili del gruppo') === 1, 'firma duplicata: ' . $method);
    $snapshot['footer'] = 'A presto!';
}
$GLOBALS['email_meta'][42]['_mi_email_style'] = ['identity_name' => 'Equipe evento', 'signature' => 'La squadra evento'];
$snapshot = MI_Modello_Email::crea_istantanea(42, []);
expect($snapshot['identita_email']['nome_mittente'] === 'Equipe evento' && $snapshot['identita']['firma'] === 'La squadra evento', 'override evento ignorato');
$GLOBALS['email_meta'][42] = [];
$snapshot = MI_Modello_Email::crea_istantanea(42, []);
expect($snapshot['identita_email']['nome_mittente'] === 'Segreteria', 'fallback identità default');
expect(str_contains(MI_Modello_Email::componi_testo($snapshot), MI_Modello_Email::NOME_SEGRETERIA), 'firma default assente');
echo "Group identity and signature: OK\n";
$snapshot = MI_Modello_Email::crea_istantanea_annullamento_partecipazione_iscritto(42, 'Persona Esempio');
expect(str_contains($snapshot['html'], 'Persona Esempio') && str_contains($snapshot['testo'], 'Persona Esempio'), 'persona cancellata assente');
expect(!str_contains($snapshot['testo'], 'annullata dalla segreteria') && str_contains($snapshot['testo'], 'altre partecipazioni'), 'conferma parziale ambigua');
echo "Participant cancellation template: OK\n";

$GLOBALS['email_meta'][42] = ['_mi_activity_id' => 9];
$GLOBALS['email_meta'][9]['_mi_email_style'] = ['identity_name' => 'Gruppo Giovani', 'sender_email' => 'sender@example.invalid', 'contact_email' => 'contact@example.invalid'];
// I gestori evento non possono sostituire l'indirizzo scelto per il gruppo.
$GLOBALS['email_meta'][42]['_mi_email_style'] = ['sender_email' => 'other@example.invalid'];
$snapshots = [MI_Modello_Email::crea_istantanea(42, []), MI_Modello_Email::crea_istantanea_annullamento_iscrizione_iscritto(42, 'Persona Esempio', 'EXAMPLE'), MI_Modello_Email::crea_istantanea_annullamento_partecipazione_iscritto(42, 'Persona Esempio'), MI_Modello_Email::crea_istantanea_operativa(42, [], 'REGISTRATION_CANCELLATION', '', '')];
foreach ($snapshots as $snapshot) {
    expect($snapshot['identita_email']['indirizzo_mittente'] === 'sender@example.invalid', 'mittente gruppo perso in conferma/annullamento');
    expect($snapshot['identita_email']['nome_mittente'] === 'Gruppo Giovani', 'nome gruppo perso in annullamento');
    expect($snapshot['identita_email']['indirizzo_risposte'] === 'contact@example.invalid', 'risposte devono restare distinte dal mittente');
    $send->invoke(null, 'test@example.invalid', $snapshot, str_repeat('a', 64), false);
    expect(MI_Workspace_Client::$action === 'INVIA_EMAIL_CONFERMA_MITTENTE', 'vecchio GAS non deve poter ignorare il mittente');
    expect(MI_Workspace_Client::$payload['indirizzo_mittente'] === 'sender@example.invalid', 'mittente assente dal payload');
    expect(MI_Workspace_Client::$payload['reply_to'] === 'contact@example.invalid', 'risposte alterate nel payload');
}
$internal = MI_Modello_Email::crea_istantanea_nuova_iscrizione_segreteria(42, []);
expect($internal['identita_email']['indirizzo_mittente'] === MI_Modello_Email::EMAIL_SEGRETERIA, 'notifica istituzionale alterata');
$GLOBALS['email_meta'][43] = ['_mi_activity_id' => 10];
expect(MI_Modello_Email::crea_istantanea(43, [])['identita_email']['indirizzo_mittente'] === MI_Modello_Email::EMAIL_SEGRETERIA, 'mittente propagato ad altro gruppo');
expect(!isset(MI_Modello_Email::sanitizza_stile(['sender_email' => 'invalid'])['sender_email']), 'mittente non valido accettato');
echo "Group From, Reply-To and cancellation routing: OK\n";

// Il nome numerico del gruppo deve avere una destinazione esplicita in entrambe le firme.
foreach ($snapshots as $snapshot) {
    if ('INSTITUTIONAL' === ($snapshot['layout'] ?? '')) continue;
    $snapshot['identita']['nome'] = '12 Gruppo & Esempio';
    $snapshot['identita']['firma'] = "Il Team 12 Gruppo & Esempio\nA presto!";
    $snapshot['identita']['contatto'] = 'contact@example.invalid';
    $html = MI_Modello_Email::componi_html($snapshot);
    $link = '<a href="mailto:contact@example.invalid" style="color:inherit;text-decoration:none;">12 Gruppo &amp; Esempio</a>';
    expect(substr_count($html, $link) === 2, 'nome non collegato al contatto in firma e footer');
    expect(str_contains($html, 'Il Team ' . $link . '<br'), 'firma o a capo alterati');
    expect(str_contains(MI_Modello_Email::componi_testo($snapshot), "Il Team 12 Gruppo & Esempio\nA presto!"), 'firma testo semplice alterata');
    $snapshot['identita']['contatto'] = 'invalid';
    expect(!str_contains(MI_Modello_Email::componi_html($snapshot), 'style="color:inherit;text-decoration:none;"'), 'link identità creato senza contatto valido');
}
echo "Explicit signature contact links: OK\n";

$highlight = new ReflectionMethod(MI_Modello_Email::class, 'evidenzia_titolo_evento');
$highlight->setAccessible(true);
$title = 'L’Uomo & il progetto – Chi sono?';
foreach ([$title, 'L&#8217;Uomo &amp; il progetto &ndash; Chi sono?'] as $stored_title) {
    $body = '<p>Iscrizione a L&#8217;Uomo &amp; il progetto &ndash; Chi sono? registrata.</p>';
    $expected = '<p>Iscrizione a <strong style="font-weight:700;">' . esc_html($title) . '</strong> registrata.</p>';
    expect($highlight->invoke(null, $body, $stored_title) === $expected, 'titolo con entità HTML non evidenziato');
    expect($highlight->invoke(null, $expected, $stored_title) === $expected, 'grassetto annidato');
}
$already_bold = '<p><strong class="title">Evento esempio</strong><a title="Evento esempio" href="https://example.invalid/">Apri</a></p>';
expect($highlight->invoke(null, $already_bold, 'Evento esempio') === $already_bold, 'markup o attributi alterati');
echo "Email title emphasis: OK\n";

$values = MI_Modello_Email::valori_ordine([], 'EXAMPLE', 'Confermata', 1, 'Maria Luisa Esempio', [], [], 'Maria Luisa');
expect($values['{{sottoscrittore.nome}}'] === 'Maria Luisa', 'nome composto troncato');
expect($values['{{sottoscrittore.nome_completo}}'] === 'Maria Luisa Esempio', 'nome completo alterato');
expect(in_array('{{sottoscrittore.nome}}', MI_Modello_Email::segnaposto_ammessi(), true), 'segnaposto nome non ammesso');
MI_Modello_Email::salva_testo_portale(42, wp_slash('Conferma'), wp_slash('Ciao {{sottoscrittore.nome}},'));
$snapshot = MI_Modello_Email::crea_istantanea(42, $values);
expect(str_contains($snapshot['html'], 'Ciao Maria Luisa,') && str_contains($snapshot['testo'], 'Ciao Maria Luisa,'), 'solo nome assente da HTML o testo');
echo "First-name confirmation placeholder: OK\n";
