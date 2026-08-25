import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../modulo-iscrizioni/', import.meta.url);
const read = (path) => readFile(new URL(path, root), 'utf8');

test('il bootstrap dichiara la versione e non esegue fuori da WordPress', async () => {
  const source = await read('modulo-iscrizioni.php');
  assert.match(source, /Version:\s+0\.4\.4/);
  assert.match(source, /defined\(\s*'ABSPATH'\s*\)\s*\|\|\s*exit/);
});

test('la configurazione Workspace è riservata e non mostra il segreto salvato', async () => {
  const settings = await read('includes/class-mi-workspace-settings.php');
  assert.match(settings, /manage_options/);
  assert.match(settings, /check_admin_referer/);
  assert.match(settings, /type="password"/);
  assert.doesNotMatch(settings, /get_option\(\s*'mi_workspace_shared_secret'.*value=/s);
  assert.match(settings, /MI_Workspace_Client::ping/);
  assert.match(settings, /get_error_code/);
});

test('il client Workspace firma le richieste e non contiene configurazione privata', async () => {
  const source = await read('includes/class-mi-workspace-client.php');
  assert.match(source, /hash_hmac\(\s*'sha256'/);
  assert.match(source, /random_bytes\(\s*16\s*\)/);
  assert.match(source, /MI_WORKSPACE_WEBAPP_URL/);
  assert.match(source, /MI_WORKSPACE_SHARED_SECRET/);
  assert.match(source, /stable_json/);
  assert.doesNotMatch(source, /script\.google\.com\/macros\/s\/[A-Za-z0-9_-]+/);
});

test('le iscrizioni vengono replicate con idempotenza senza perdere il salvataggio locale', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const activator = await read('includes/class-mi-activator.php');
  assert.match(service, /APPEND_REGISTRATION/);
  assert.match(service, /sync_workspace/);
  assert.match(service, /workspace_status/);
  assert.match(service, /'COMMIT'[\s\S]+sync_workspace/);
  assert.match(service, /sync_pending_workspace/);
  assert.match(service, /LIMIT 10/);
  assert.match(activator, /workspace_status varchar\(24\)/);
  assert.match(activator, /workspace_attempts/);
  assert.match(activator, /wp_schedule_event/);
});

test('le bozze incomplete possono essere salvate senza aggirare il controllo di pubblicazione', async () => {
  const source = await read('assets/admin.js');
  const postType = await read('includes/class-mi-event-post-type.php');
  assert.match(source, /save-post/);
  assert.match(source, /formnovalidate/);
  assert.match(postType, /\$activity_id\s*\?\s*get_post/);
});

test('attivazione e disattivazione non riscrivono le regole del sito', async () => {
  const source = await read('includes/class-mi-activator.php');
  assert.doesNotMatch(source, /flush_rewrite_rules\s*\(/);
});

test('la registrazione usa transazione, lock di riga e idempotenza', async () => {
  const source = await read('includes/class-mi-registration-service.php');
  assert.match(source, /START TRANSACTION/);
  assert.match(source, /FOR UPDATE/);
  assert.match(source, /idempotency_key/);
  assert.match(source, /ROLLBACK/);
  assert.match(source, /COMMIT/);
});

test('gli asset pubblici sono caricati soltanto in presenza dello shortcode', async () => {
  const source = await read('includes/class-mi-shortcode.php');
  assert.match(source, /has_shortcode/);
  assert.match(source, /wp_enqueue_scripts/);
});

test('i campi partecipante usano profili, allowlist e validazione server', async () => {
  const schema = await read('includes/class-mi-field-schema.php');
  const service = await read('includes/class-mi-registration-service.php');
  const publicScript = await read('assets/public.js');
  const activator = await read('includes/class-mi-activator.php');
  assert.match(schema, /'MINIMAL'/);
  assert.match(schema, /'STANDARD'/);
  assert.match(schema, /'TRAVEL'/);
  assert.match(schema, /validate_answers/);
  assert.match(schema, /-120 years/);
  assert.match(service, /MI_Field_Schema::validate_answers/);
  assert.match(publicScript, /data-mi-participant-field/);
  assert.match(activator, /extra_json longtext/);
  assert.match(activator, /maybe_upgrade/);
});

test('il pannello mostra partecipanti e dati aggiuntivi con etichette leggibili', async () => {
  const source = await read('includes/class-mi-admin.php');
  assert.match(source, /Dettaglio iscrizione/);
  assert.match(source, /MI_Field_Schema::catalog/);
  assert.match(source, /extra_json/);
  assert.match(source, /Nessun dato aggiuntivo raccolto/);
});

test('filtri ed esportazione rispettano accessi e neutralizzano formule CSV', async () => {
  const source = await read('includes/class-mi-admin.php');
  assert.match(source, /mi_export_registrations/);
  assert.match(source, /check_admin_referer/);
  assert.match(source, /MI_Access::can_access_event/);
  assert.match(source, /safe_csv_value/);
  assert.match(source, /Esporta CSV filtrato/);
});

test('la coda email rimane in anteprima', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const admin = await read('includes/class-mi-admin.php');
  assert.match(service, /'status'\s*=>\s*'PREVIEW'/);
  assert.doesNotMatch(service, /wp_mail\s*\(/);
  assert.match(admin, /Nessuna email viene spedita/);
});

test('l’editor email usa segnaposto controllati e non invia messaggi', async () => {
  const source = await read('includes/class-mi-modello-email.php');
  assert.match(source, /Email di conferma — anteprima/);
  assert.match(source, /\{\{evento\.titolo\}\}/);
  assert.match(source, /\{\{ordine\.codice\}\}/);
  assert.match(source, /wp_kses_post/);
  assert.match(source, /MI_Access::can_access_event/);
  assert.doesNotMatch(source, /wp_mail\s*\(/);
});

test('l’outbox conserva e mostra una revisione immutabile dell’anteprima', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  const service = await read('includes/class-mi-registration-service.php');
  const admin = await read('includes/class-mi-admin.php');
  assert.match(model, /crea_istantanea/);
  assert.match(model, /hash\(\s*'sha256'/);
  assert.match(service, /email_preview/);
  assert.match(admin, /Anteprima email conservata/);
  assert.match(admin, /wp_kses_post/);
});

test('l’anteprima storica conserva il branding dell’attività', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  const admin = await read('includes/class-mi-admin.php');
  assert.match(model, /nome_attivita/);
  assert.match(model, /wp_get_attachment_image_url/);
  assert.match(model, /logo_alt/);
  assert.match(admin, /identity\['logo_url'\]/);
  assert.match(admin, /identity\['logo_alt'\]/);
});

test('l’editor aggiorna l’anteprima e rifiuta segnaposto non ammessi', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  const script = await read('assets/admin.js');
  assert.match(model, /segnaposto_ammessi/);
  assert.match(model, /trova_segnaposto_non_ammessi/);
  assert.match(model, /Modello email non aggiornato/);
  assert.match(script, /aggiornaAnteprimaEmail/);
  assert.match(script, /setCustomValidity/);
  assert.match(script, /Segnaposto non ammessi/);
});

test('l’identità email valida reply-to e destinatari senza spedire', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  assert.match(model, /Nome visualizzato del mittente/);
  assert.match(model, /Indirizzo per le risposte/);
  assert.match(model, /Destinatari interni in anteprima/);
  assert.match(model, /count\(\s*\$recipients\s*\) > 10/);
  assert.match(model, /identita_email/);
  assert.doesNotMatch(model, /wp_mail\s*\(/);
});

test('la gestione economica distingue i quattro casi senza attivare riscossioni', async () => {
  const eventType = await read('includes/class-mi-event-post-type.php');
  const service = await read('includes/class-mi-registration-service.php');
  assert.match(eventType, /Solo iscrizione/);
  assert.match(eventType, /Prezzo informativo/);
  assert.match(eventType, /Versamento completo/);
  assert.match(eventType, /Caparra e saldo/);
  assert.match(eventType, /BANK_TRANSFER/);
  assert.match(eventType, /CARD/);
  assert.match(eventType, /CASH/);
  assert.match(service, /deposit_percentage/);
  assert.doesNotMatch(eventType, /IBAN|numero della carta|payment_url/i);
});

test('la pubblicazione richiede una configurazione economica coerente', async () => {
  const admin = await read('includes/class-mi-admin.php');
  const script = await read('assets/admin.js');
  assert.match(admin, /valid_economic/);
  assert.match(admin, /CALCULATED/);
  assert.match(admin, /payment_methods/);
  assert.match(script, /aggiornaConfigurazioneEconomica/);
  assert.match(script, /setCustomValidity/);
  assert.match(script, /data-mi-economic-payments/);
});

test('l’iscrizione conserva totale, primo versamento e saldo', async () => {
  const activator = await read('includes/class-mi-activator.php');
  const service = await read('includes/class-mi-registration-service.php');
  const admin = await read('includes/class-mi-admin.php');
  assert.match(activator, /initial_due_cents/);
  assert.match(activator, /balance_cents/);
  assert.match(service, /riepilogo_economico/);
  assert.match(service, /economic_summary/);
  assert.match(service, /'WAITLISTED'/);
  assert.match(admin, /Riepilogo economico conservato/);
});

test('il pannello e il CSV espongono gli importi economici', async () => {
  const admin = await read('includes/class-mi-admin.php');
  assert.match(admin, /Primo versamento/);
  assert.match(admin, /Saldo successivo/);
  assert.match(admin, /Totale centesimi/);
  assert.match(admin, /formatta_importo/);
  assert.match(admin, /etichetta_modalita_economica/);
});

test('il modulo mostra totale, caparra e saldo senza pagamenti online', async () => {
  const shortcode = await read('includes/class-mi-shortcode.php');
  const script = await read('assets/public.js');
  assert.match(shortcode, /data-mi-economic-summary/);
  assert.match(script, /renderEconomicSummary/);
  assert.match(script, /deposit_percentage/);
  assert.match(script, /Primo versamento previsto/);
  assert.match(script, /non effettua pagamenti online/);
  assert.doesNotMatch(script, /stripe|paypal|checkout/i);
});
