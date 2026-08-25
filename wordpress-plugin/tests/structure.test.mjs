import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../modulo-iscrizioni/', import.meta.url);
const read = (path) => readFile(new URL(path, root), 'utf8');

test('il bootstrap dichiara la versione e non esegue fuori da WordPress', async () => {
  const source = await read('modulo-iscrizioni.php');
  assert.match(source, /Version:\s+1\.0\.0/);
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

test('il percorso pubblico è progressivo e dispone di un modello concentrato', async () => {
  const shortcode = await read('includes/class-mi-shortcode.php');
  const script = await read('assets/public.js');
  const template = await read('templates/pagina-iscrizione-concentrata.php');
  assert.match(shortcode, /data-mi-step="1"/);
  assert.match(shortcode, /data-mi-step="3"/);
  assert.match(shortcode, /theme_page_templates/);
  assert.match(script, /showStep/);
  assert.match(template, /wp_head/);
  assert.doesNotMatch(template, /get_header|get_sidebar/);
});

test('l’integrazione Divi è facoltativa e riusa il motore dello shortcode', async () => {
  const integration = await read('includes/class-mi-integrazione-divi.php');
  const module = await read('includes/class-mi-divi-modulo-iscrizioni.php');
  assert.match(integration, /et_builder_ready/);
  assert.match(integration, /class_exists\( 'ET_Builder_Module' \)/);
  assert.match(module, /extends ET_Builder_Module/);
  assert.match(module, /vb_support = 'partial'/);
  assert.match(module, /MI_Shortcode::render/);
  assert.match(module, /MI_Access::can_access_event/);
	const shortcode = await read('includes/class-mi-shortcode.php');
	assert.match(shortcode, /has_shortcode\( \$post->post_content, 'mi_divi_modulo_iscrizioni' \)/);
});

test('l’anteprima riservata non invia iscrizioni e accetta le bozze autorizzate', async () => {
  const shortcode = await read('includes/class-mi-shortcode.php');
  const service = await read('includes/class-mi-registration-service.php');
  const script = await read('assets/public.js');
  assert.match(shortcode, /admin_post_mi_anteprima_evento/);
  assert.match(shortcode, /check_admin_referer/);
  assert.match(shortcode, /MI_Access::can_access_event/);
  assert.match(service, /\$allow_unpublished/);
  assert.match(script, /if \(config\.preview\)/);
});

test('capienza, scadenze e lista d’attesa sono verificate anche nella transazione', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const shortcode = await read('includes/class-mi-shortcode.php');
  const postType = await read('includes/class-mi-event-post-type.php');
  assert.match(service, /registration_time_state/);
  assert.match(service, /FOR UPDATE[\s\S]+registration_time_state/);
  assert.match(service, /'SOLD_OUT'/);
  assert.match(service, /'remaining'/);
  assert.match(shortcode, /Posti ordinari esauriti/);
  assert.match(postType, /confermati/);
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

test('la coda email resta sicura fino all’attivazione operativa', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const sender = await read('includes/class-mi-spedizione-email.php');
  assert.match(service, /stato_nuova_email/);
  assert.doesNotMatch(service, /wp_mail\s*\(/);
  assert.match(sender, /get_option\( self::OPZIONE_MODALITA, 'ANTEPRIMA' \)/);
  assert.match(sender, /'OPERATIVO' === self::modalita\(\)/);
  assert.match(sender, /prova_verificata/);
  assert.match(sender, /wp_mail\s*\(/);
  assert.match(sender, /MI-PROVA-0001/);
});

test('la spedizione usa una coda acquisita atomicamente e tentativi limitati', async () => {
  const sender = await read('includes/class-mi-spedizione-email.php');
  const activator = await read('includes/class-mi-activator.php');
  assert.match(sender, /status = 'SENDING'.*status = 'PENDING'/s);
  assert.match(sender, /attempts < 5/);
  assert.match(sender, /'SENT'/);
  assert.match(sender, /'FAILED'/);
  assert.match(activator, /last_error varchar/);
  assert.match(activator, /sent_at datetime/);
});

test('il pannello email mostra lo stato della consegna', async () => {
  const admin = await read('includes/class-mi-admin.php');
  assert.match(admin, /o\.attempts/);
  assert.match(admin, /o\.last_error/);
  assert.match(admin, /o\.sent_at/);
  assert.match(admin, /Ultimo errore/);
  assert.match(admin, /Non inviata/);
});

test('le email fallite possono essere riaccodate con protezione amministrativa', async () => {
  const sender = await read('includes/class-mi-spedizione-email.php');
  const admin = await read('includes/class-mi-admin.php');
  assert.match(sender, /admin_post_mi_riaccoda_email/);
  assert.match(sender, /status IN \('FAILED', 'SENDING'\)/);
  assert.match(sender, /attempts = 0/);
  assert.match(sender, /check_admin_referer/);
  assert.match(admin, /mi_riaccoda_email/);
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

test('la gratuità esplicita è visibile e compatibile soltanto con la sola iscrizione', async () => {
  const shortcode = await read('includes/class-mi-shortcode.php');
  const admin = await read('includes/class-mi-admin.php');
  const script = await read('assets/admin.js');
  assert.match(shortcode, /'ZERO'.+<small>Gratuito<\/small>/);
  assert.match(admin, /registration_only_price/);
  assert.match(admin, /array\(\s*'NONE',\s*'ZERO'\s*\)/);
  assert.match(admin, /Gratuito esplicito.+Solo iscrizione/);
  assert.match(script, /\['NONE', 'ZERO'\]\.includes/);
});

test('la configurazione economica viene normalizzata prima di ogni uso', async () => {
  const eventType = await read('includes/class-mi-event-post-type.php');
  assert.match(eventType, /\$pricing_mode\s*=\s*in_array[\s\S]+update_post_meta\(\s*\$post_id,\s*'_mi_pricing_mode',\s*\$pricing_mode\s*\)/);
  assert.match(eventType, /\$economic_mode\s*=\s*in_array[\s\S]+update_post_meta\(\s*\$post_id,\s*'_mi_economic_mode',\s*\$economic_mode\s*\)/);
  assert.match(eventType, /in_array\(\s*\$economic_mode,\s*array\(\s*'FULL_PAYMENT',\s*'DEPOSIT_BALANCE'/);
});

test('i valori dei segnaposto email sono protetti in base al contesto', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  assert.match(model, /renderizza_html/);
  assert.match(model, /esc_html\(\s*sanitize_text_field/);
  assert.match(model, /'html' === \$source/);
  assert.match(model, /sanitize_text_field\(\s*\(string\) \$value\s*\)/);
});

test('la replica Workspace è accodata dopo il commit senza bloccare la risposta', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const plugin = await read('includes/class-mi-plugin.php');
  const activator = await read('includes/class-mi-activator.php');
  assert.match(service, /'COMMIT'[\s\S]+accoda_sincronizzazione_workspace/);
  assert.match(service, /wp_schedule_single_event/);
  assert.match(service, /wp_next_scheduled/);
  assert.match(service, /sincronizza_iscrizione_workspace/);
  assert.match(plugin, /mi_sync_workspace_registration/);
  assert.match(activator, /wp_clear_scheduled_hook\(\s*'mi_sync_workspace_registration'/);
});

test('il pannello espone e riaccoda in sicurezza una replica Workspace', async () => {
  const admin = await read('includes/class-mi-admin.php');
  const service = await read('includes/class-mi-registration-service.php');
  assert.match(admin, /admin_post_mi_retry_workspace/);
  assert.match(admin, /check_admin_referer\(\s*'mi_retry_workspace_'/);
  assert.match(admin, /MI_Access::can_access_event/);
  assert.match(admin, /Tentativi Workspace/);
  assert.match(admin, /Ultimo errore Workspace/);
  assert.match(admin, /Sincronizzata il/);
  assert.match(admin, /Riaccoda replica Workspace/);
  assert.match(service, /accoda_iscrizione_workspace/);
  assert.match(service, /wp_schedule_single_event/);
});

test('il pannello riepiloga e filtra le repliche nel perimetro accessibile', async () => {
  const admin = await read('includes/class-mi-admin.php');
  assert.match(admin, /mi_workspace_status/);
  assert.match(admin, /workspace_filter/);
  assert.match(admin, /GROUP BY workspace_status/);
  assert.match(admin, /Riepilogo repliche Workspace/);
  assert.match(admin, /Sincronizzate:/);
  assert.match(admin, /In attesa:/);
  assert.match(admin, /scope_conditions/);
  assert.match(admin, /MI_Access::activity_ids/);
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
  assert.match(script, /registrat[oi] manualmente dall’organizzazione/);
  assert.doesNotMatch(script, /stripe|paypal|checkout/i);
});

test('il modulo presenta in italiano le fonti registrate manualmente', async () => {
  const shortcode = await read('includes/class-mi-shortcode.php');
  const script = await read('assets/public.js');
  assert.match(shortcode, /Fonti ammesse/);
  assert.match(script, /BANK_TRANSFER:\s*'Bonifico'/);
  assert.match(script, /CARD:\s*'Carta'/);
  assert.match(script, /CASH:\s*'Contante'/);
  assert.match(script, /registrat[oi] manualmente dall’organizzazione/);
});

test('la replica Workspace include il riepilogo economico storico', async () => {
  const activator = await read('includes/class-mi-activator.php');
  const service = await read('includes/class-mi-registration-service.php');
  assert.match(activator, /payment_methods_json/);
  assert.match(service, /economic_mode/);
  assert.match(service, /initial_due_cents/);
  assert.match(service, /balance_cents/);
  assert.match(service, /payment_methods/);
});

test('il pannello verifica lo schema economico senza creare iscrizioni', async () => {
  const settings = await read('includes/class-mi-workspace-settings.php');
  const client = await read('includes/class-mi-workspace-client.php');
  assert.match(settings, /Verifica schema economico/);
  assert.match(settings, /schema_version/);
  assert.match(client, /STATO_SCHEMA/);
});
