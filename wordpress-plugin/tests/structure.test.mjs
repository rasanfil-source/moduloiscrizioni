import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../modulo-iscrizioni/', import.meta.url);
const read = (path) => readFile(new URL(path, root), 'utf8');

test('il bootstrap dichiara la versione e non esegue fuori da WordPress', async () => {
  const source = await read('modulo-iscrizioni.php');
	assert.match(source, /Version:\s+3\.5\.13/);
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
  assert.match(service, /\$payments_table\s*=\s*\$wpdb->prefix\s*\.\s*'mi_payments'/);
  assert.match(service, /administrative_note FROM \{\$payments_table\}/);
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

test('anti replay e limiti pubblici sono atomici anche fra richieste concorrenti', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const rest = await read('includes/class-mi-rest-controller.php');
  assert.match(service, /consume_registration_rate_limit/);
  assert.match(service, /SELECT GET_LOCK/);
  assert.match(service, /SELECT RELEASE_LOCK/);
  assert.match(service, /'email\|'/);
  assert.match(rest, /mi_ws_nonce_/);
  assert.match(rest, /mi_ws_draft_/);
  assert.match(rest, /SELECT GET_LOCK/);
  assert.match(rest, /finally/);
});

test('gli asset pubblici sono caricati soltanto in presenza dello shortcode', async () => {
  const source = await read('includes/class-mi-shortcode.php');
  assert.match(source, /has_shortcode/);
  assert.match(source, /wp_enqueue_scripts/);
  assert.match(source, /maybe_disable_page_cache/);
  assert.match(source, /DONOTCACHEPAGE/);
});

test('il controllo temporale misura la sessione browser e il QR viene caricato solo quando serve', async () => {
  const script = await read('assets/public.js');
  const shortcode = await read('includes/class-mi-shortcode.php');
  assert.match(script, /const startedAt = Math\.floor\(Date\.now\(\) \/ 1000\)/);
  assert.match(script, /started_at: startedAt/);
  assert.doesNotMatch(script, /started_at: Math\.floor\(Date\.now\(\) \/ 1000\)/);
  assert.match(script, /ensureQrGenerator/);
  assert.match(script, /data-mi-qrcode-generator/);
  assert.match(script, /catch \(qrError\)/);
  assert.doesNotMatch(shortcode, /wp_enqueue_script\(\s*'mi-qrcode-generator'/);
});

test('il percorso pubblico è progressivo e dispone di un modello concentrato', async () => {
  const shortcode = await read('includes/class-mi-shortcode.php');
  const script = await read('assets/public.js');
  const template = await read('templates/pagina-iscrizione-concentrata.php');
  assert.match(shortcode, /data-mi-step="1"/);
  assert.match(shortcode, /data-mi-step="3"/);
  assert.match(shortcode, /theme_page_templates/);
  assert.match(script, /showStep/);
  assert.match(script, /prefillBuyerFromFirstParticipant/);
  assert.match(script, /buyerEdited\.firstName/);
  assert.match(script, /buyerEdited\.lastName/);
	assert.match(script, /Qualche dato aggiuntivo/);
	assert.match(script, /Aggiungi, se vuoi, anche i dati degli altri partecipanti/);
	assert.doesNotMatch(script, /data-mi-required-when-open/);
	assert.match(script, /input\.required = true/);
	assert.match(script, /participant_extra_scope === 'ALL'/);
	assert.match(script, /identityDetailField/);
  assert.match(template, /wp_head/);
  assert.doesNotMatch(template, /get_header|get_sidebar/);
});

test('nome e cognome sono obbligatori per tutti; i dati aggiuntivi seguono l’ambito evento', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const script = await read('assets/public.js');
	assert.match(service, /! \$first_name \|\| ! \$last_name/);
	assert.match(service, /'ALL' === \$extra_scope \|\| 0 === \$participant_position/);
	assert.match(script, /input\.required = required && Boolean\(field\.required\)/);
});

test('la barra mobile è compatta e indica esplicitamente gli eventi gratuiti', async () => {
	const script = await read('assets/public.js');
	const style = await read('assets/public.css');
	assert.match(script, /evento gratuito/);
	assert.match(style, /align-items: baseline/);
	assert.match(style, /min-height: 44px/);
});

test('checkbox e radio del pannello evento mantengono dimensioni compatte', async () => {
	const style = await read('assets/admin.css');
	assert.match(style, /input:not\(\[type="checkbox"\]\):not\(\[type="radio"\]\)/);
	assert.match(style, /input\[type="checkbox"\][\s\S]*width: 1rem/);
});

test('la bacheca offre ai delegati un accesso diretto al servizio moduli', async () => {
  const admin = await read('includes/class-mi-admin.php');
  const access = await read('includes/class-mi-access.php');
  assert.match(admin, /wp_dashboard_setup/);
  assert.match(admin, /Servizio moduli iscrizioni/);
  assert.match(admin, /Apri il servizio moduli/);
  assert.match(admin, /mi_view_registrations/);
  assert.doesNotMatch(access, /remove_menu_page\( 'index\.php' \)/);
});

test('il portale web riusa WordPress e limita operatori ed eventi sul server', async () => {
  const portal = await read('includes/class-mi-portal.php');
  const access = await read('includes/class-mi-access.php');
  const activator = await read('includes/class-mi-activator.php');
  const eventType = await read('includes/class-mi-event-post-type.php');
  const script = await read('assets/portal.js');
  assert.match(portal, /mi_portale_gestione/);
  assert.match(portal, /wp_login_form/);
  assert.match(portal, /Crea evento/);
  assert.match(portal, /Gestisci eventi/);
  assert.match(portal, /C’è qualcuno qui/);
  assert.match(portal, /MI_Access::can_access_event/);
  assert.match(access, /_mi_event_scope/);
  assert.match(activator, /mi_secretary/);
  assert.match(activator, /mi_event_operator/);
  assert.match(eventType, /Operatori dell’evento/);
  assert.match(eventType, /wp_create_user/);
  assert.match(eventType, /strlen\( \$password \) >= 12/);
  assert.match(script, /reportValidity/);
});

test('il portale tecnico evita Divi e aggrega i dati della dashboard', async () => {
  const portal = await read('includes/class-mi-portal.php');
  const activator = await read('includes/class-mi-activator.php');
  assert.doesNotMatch(portal, /wp_head\(\)|wp_footer\(\)/);
  assert.match(portal, /assets\/portal\.css\?ver=/);
  assert.match(portal, /assets\/portal\.js\?ver=/);
  assert.match(portal, /SELECT event_id,confirmed_count/);
  assert.match(portal, /JOIN \{\$wpdb->posts\} events/);
  assert.doesNotMatch(portal, /SELECT COALESCE\(SUM\(total_qty\)/);
  assert.match(activator, /KEY event_created \(event_id,created_at\)/);
  assert.match(portal, /mi_event_revisions/);
  assert.match(portal, /posti occupati/i);
  assert.match(portal, /createFromFormat\( '!Y-m-d\\TH:i'/);
  assert.match(portal, /new DateTimeZone\( 'UTC' \)/);
});

test('il portale resta utilizzabile fra telefono e tablet', async () => {
  const css = await read('assets/portal.css');
  assert.match(css, /mi-portal-header[^}]+flex-wrap:wrap/);
  assert.match(css, /mi-portal-switcher[^}]+flex-wrap:wrap/);
  assert.match(css, /min-height:44px/);
  assert.match(css, /@media\(max-width:760px\)/);
  assert.match(css, /overflow-wrap:anywhere/);
  assert.match(css, /overflow-x:auto/);
});

test('WooCommerce viene alleggerito soltanto fuori dai percorsi commerciali', async () => {
  const bootstrap = await read('modulo-iscrizioni.php');
  const plugin = await read('includes/class-mi-plugin.php');
  const performance = await read('includes/class-mi-site-performance.php');
  assert.match(bootstrap, /class-mi-site-performance\.php/);
  assert.match(plugin, /MI_Site_Performance::boot/);
  assert.match(performance, /wp_print_styles/);
  assert.match(performance, /wp_enqueue_scripts[^\n]+200/);
  assert.match(performance, /style_loader_tag/);
  assert.match(performance, /script_loader_tag/);
  assert.match(performance, /filter_unused_woocommerce_style/);
  assert.match(performance, /is_woocommerce/);
  assert.match(performance, /is_cart/);
  assert.match(performance, /is_checkout/);
  assert.match(performance, /is_account_page/);
  assert.match(performance, /wc-ajax/);
  assert.match(performance, /wp:woocommerce\//);
  assert.match(performance, /woocommerce-general/);
  assert.match(performance, /wc-cart-fragments/);
  assert.match(performance, /wc-order-attribution/);
});

test('i dati dimostrativi sono riservati a bozze, amministratori ed email in anteprima', async () => {
  const admin = await read('includes/class-mi-admin.php');
  const registration = await read('includes/class-mi-registration-service.php');
  assert.match(admin, /admin_post_mi_seed_demo_registrations/);
  assert.match(admin, /current_user_can\(\s*'manage_options'\s*\)/);
  assert.match(admin, /'ANTEPRIMA'\s*!==\s*MI_Spedizione_Email::modalita/);
  assert.match(admin, /array\(\s*'draft',\s*'private'\s*\)/);
  assert.match(admin, /'ADMIN_DEMO'/);
  assert.match(registration, /\$allow_unpublished\s*=\s*false/);
  assert.match(registration, /!\s*\$allow_unpublished\s*&&\s*'OPEN'\s*!==\s*self::registration_state/);
  assert.match(registration, /!\s*\$allow_unpublished\s*&&\s*'OPEN'\s*!==\s*self::registration_time_state/);
});

test('il wizard è breve, crea solo bozze e rende gli alloggi condizionali', async () => {
  const portal = await read('includes/class-mi-portal.php');
  const script = await read('assets/portal.js');
  assert.match(portal, /1 di 5/);
  assert.match(portal, /5 di 5/);
  assert.match(portal, /post_status' => 'draft'/);
  assert.match(portal, /Vuoi partire da un evento precedente/);
  assert.match(portal, /data-mi-overnight/);
  assert.match(portal, /data-mi-accommodations hidden/);
  assert.match(script, /rooms\.hidden\s*=\s*!overnight\.checked/);
  assert.doesNotMatch(portal, /wp_insert_post\([\s\S]{0,300}post_status' => 'publish'/);
});

test('ogni partecipante dispone di annullamento individuale confermato e auditabile', async () => {
  const activator = await read('includes/class-mi-activator.php');
  const service = await read('includes/class-mi-registration-service.php');
  const portal = await read('includes/class-mi-portal.php');
  assert.match(activator, /cancellation_token_hash char\(64\)/);
  assert.match(activator, /cancelled_at datetime/);
  assert.match(service, /random_bytes\( 32 \)/);
  assert.match(service, /hash\( 'sha256', \$cancel_token \)/);
  assert.match(service, /PARTICIPANT_CANCELLED/);
  assert.match(service, /GREATEST\(0,\{\$counter_field\}-1\)/);
  assert.match(service, /remaining_participants/);
  assert.match(service, /promote_waitlisted_locked/);
  assert.match(portal, /cancel_participant_public/);
  assert.match(portal, /Referrer-Policy: no-referrer/);
  assert.match(service, /cancellation_token_hash=NULL/);
  assert.match(service, /status = 'ACTIVE' GROUP BY ticket_type_code/);
  assert.match(portal, /cancel_participant_portal/);
	assert.match(portal, /mi-booking-detail__cancel-button/);
  assert.match(portal, /Conferma richiesta/);
  assert.match(portal, /Eventuali rimborsi devono essere concordati separatamente/);
});

test('le email includono collegamenti personali senza inviare in modalità anteprima', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const model = await read('includes/class-mi-modello-email.php');
  const sender = await read('includes/class-mi-spedizione-email.php');
  assert.match(service, /participant_cancel_url/);
  assert.match(service, /_participant_management/);
  assert.match(model, /gestione_partecipanti/);
  assert.match(model, /Annulla la partecipazione di/);
  assert.match(sender, /get_option\( self::OPZIONE_MODALITA, 'ANTEPRIMA' \)/);
  assert.match(sender, /'OPERATIVO' === self::modalita\(\)/);
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
	assert.match(shortcode, /set_current_screen\( 'mi_event_preview' \)/);
	assert.match(shortcode, /show_admin_bar\( false \)/);
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
  assert.match(postType, /posti occupati/);
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

test('i documenti sono raccolti solo come dati testuali e mai come foto o scansioni', async () => {
  const schema = await read('includes/class-mi-field-schema.php');
  const eventType = await read('includes/class-mi-event-post-type.php');
  const adminScript = await read('assets/admin.js');
  const service = await read('includes/class-mi-registration-service.php');
  for (const key of ['document_type', 'document_number', 'document_country', 'document_expiry']) {
    assert.match(schema, new RegExp(`'${key}'`));
  }
  assert.match(schema, /Non caricare fotografie o scansioni/);
  assert.match(schema, /'retention'\s*=>\s*'SHEETS_ONLY'/);
  assert.match(service, /scrub_relay_only_fields/);
  assert.match(service, /MI_Field_Schema::relay_only_keys/);
  assert.doesNotMatch(schema + eventType + adminScript, /type=["']file["']/i);
  assert.doesNotMatch(eventType + adminScript, /<option value=["']file["']/i);
});

test('il pannello mostra partecipanti e dati aggiuntivi con etichette leggibili', async () => {
  const source = await read('includes/class-mi-admin.php');
  assert.match(source, /mi-booking-title/);
  assert.match(source, /MI_Field_Schema::catalog/);
  assert.match(source, /extra_json/);
  assert.match(source, /Nessun dato aggiuntivo raccolto/);
  assert.match(source, /detail_field_labels/);
  assert.match(source, /snapshot_json/);
  assert.match(source, /if \( \$detail\['special_requests'\] \)/);
});

test('il dettaglio iscrizione separa le azioni dai dati tecnici', async () => {
	const admin = await readFile(new URL('../modulo-iscrizioni/includes/class-mi-admin.php', import.meta.url), 'utf8');
	const style = await readFile(new URL('../modulo-iscrizioni/assets/admin.css', import.meta.url), 'utf8');
	assert.match(admin, /Dati utili alla gestione/);
	assert.match(admin, /<details class="mi-registration-technical">/);
	assert.match(admin, /<summary>Dettagli tecnici<\/summary>/);
	assert.match(style, /\.mi-registration-technical/);
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

test('la coda recupera le email rimaste in elaborazione', async () => {
  const sender = await read('includes/class-mi-spedizione-email.php');
  const activator = await read('includes/class-mi-activator.php');
  assert.match(sender, /processing_started_at < %s/);
  assert.match(sender, /processing_started_at = %s/);
  assert.match(activator, /processing_started_at datetime/);
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
  assert.match(admin, /MI_Modello_Email::sanitizza_html_email/);
});

test('l’anteprima storica conserva il branding dell’attività', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  const admin = await read('includes/class-mi-admin.php');
  assert.match(model, /nome_attivita/);
  assert.match(model, /wp_get_attachment_image_url/);
  assert.match(model, /logo_alt/);
  assert.match(model, /primary_color/);
  assert.match(model, /secondary_color/);
  assert.match(admin, /MI_Modello_Email::componi_html/);
});

test('il guscio email usa il branding dello snapshot e componenti email-safe', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  const sender = await read('includes/class-mi-spedizione-email.php');
  assert.match(sender, /MI_Modello_Email::componi_html\(\s*\$istantanea/);
  assert.match(sender, /MI_Modello_Email::componi_testo/);
  assert.match(sender, /AltBody/);
  assert.match(model, /max-width:600px/);
  assert.match(model, /opacity:0;color:transparent/);
  assert.match(model, /Assistenza/);
  assert.match(model, /border-radius:12px/);
  assert.match(model, /font-style:italic/);
  assert.match(model, /#151b38/);
  assert.match(model, /#337ab7/);
  assert.doesNotMatch(model, /#1a365d|#F97316/i);
  assert.match(model, /url_pubblica_evento/);
  assert.match(model, /'post_status'\s*=>\s*'publish'/);
  assert.match(model, /shortcode_parse_atts/);
});

test('i segnaposto email coprono evento, riepilogo economico e pagamento', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  const service = await read('includes/class-mi-registration-service.php');
  for (const placeholder of [
    '{{evento.data}}',
    '{{evento.luogo}}',
    '{{ordine.riepilogo_economico}}',
    '{{ordine.totale}}',
    '{{pagamento.importo_dovuto}}',
    '{{pagamento.istruzioni}}',
    '{{pagamento.scadenza}}',
    '{{pagamento.causale}}',
  ]) assert.ok(model.includes(placeholder), `segnaposto mancante: ${placeholder}`);
  assert.match(service, /MI_Modello_Email::valori_ordine/);
});

test('il sanitizzatore email dichiara gli attributi da preservare', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  assert.match(model, /wp_kses_allowed_html\(\s*'post'\s*\)/);
  for (const attribute of ['role', 'cellpadding', 'cellspacing', 'bgcolor', 'style']) {
    assert.ok(model.includes(`'${attribute}' => true`), `attributo email-safe non dichiarato: ${attribute}`);
  }
  assert.doesNotMatch(model, /\$html\s*=\s*wp_kses_post/);
  assert.match(model, /wp_kses\(\s*\(string\) \$html,\s*\$allowed\s*\)/);
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
	assert.match(model, /Indirizzi interni per le email di prova/);
	assert.match(model, /In modalità Anteprima nessuna email viene inviata/);
  assert.match(model, /count\(\s*\$recipients\s*\) > 10/);
  assert.match(model, /identita_email/);
  assert.doesNotMatch(model, /wp_mail\s*\(/);
});

test('l’identificativo QR è facoltativo e non contiene dati personali', async () => {
  const model = await read('includes/class-mi-modello-email.php');
  const postType = await read('includes/class-mi-event-post-type.php');
  const sender = await read('includes/class-mi-spedizione-email.php');
  assert.match(postType, /QR facoltativo/);
  assert.match(model, /payload_qr/);
  assert.match(model, /_mi_identifier_display/);
  assert.match(sender, /identificativo/);
  assert.doesNotMatch(model, /buyer_email/);
});

test('il QR grafico usa una libreria locale MIT senza servizi esterni', async () => {
  const shortcode = await read('includes/class-mi-shortcode.php');
  const service = await read('includes/class-mi-registration-service.php');
  const script = await read('assets/public.js');
  const library = await read('assets/qrcode-generator-2.0.4.js');
  const licence = await read('assets/qrcode-generator-LICENZA-MIT.txt');
  assert.match(shortcode, /qrcode-generator-2\.0\.4\.js/);
  assert.match(service, /identifier_display/);
  assert.match(script, /createSvgTag/);
  assert.match(script, /window\.qrcode/);
  assert.match(library, /qrcode/);
  assert.match(licence, /MIT/i);
  assert.doesNotMatch(script.replaceAll('http://www.w3.org/2000/svg', ''), /https?:\/\//);
});

test('la gestione economica distingue i quattro casi senza attivare riscossioni', async () => {
  const eventType = await read('includes/class-mi-event-post-type.php');
  const service = await read('includes/class-mi-registration-service.php');
  assert.match(eventType, /Nessun pagamento previsto/);
  assert.match(eventType, /Prezzo solamente informativo/);
  assert.match(eventType, /Pagamento completo richiesto/);
  assert.match(eventType, /Caparra richiesta, saldo successivo/);
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

test('la gratuità è visibile e compatibile soltanto con la sola iscrizione', async () => {
  const shortcode = await read('includes/class-mi-shortcode.php');
  const admin = await read('includes/class-mi-admin.php');
  const script = await read('assets/admin.js');
  assert.match(shortcode, /'ZERO'.+<small>Gratuito<\/small>/);
  assert.match(admin, /registration_only_price/);
  assert.match(admin, /array\(\s*'NONE',\s*'ZERO'\s*\)/);
	assert.match(admin, /“Gratuito” richiede “Nessun pagamento previsto”/);
  assert.match(script, /\['NONE', 'ZERO'\]\.includes/);
});

test('il prezzo supporta una quota di partecipazione uguale per tutti', async () => {
	const eventType = await read('includes/class-mi-event-post-type.php');
	const service = await read('includes/class-mi-registration-service.php');
	const shortcode = await read('includes/class-mi-shortcode.php');
	const adminScript = await read('assets/admin.js');
	const publicScript = await read('assets/public.js');
	assert.match(eventType, /value="FIXED"[\s\S]*Quota di partecipazione uguale per tutti/);
	assert.match(eventType, /_mi_fixed_price_cents/);
	assert.match(service, /'FIXED' === \$event\['pricing_mode'\]/);
	assert.match(shortcode, /Quota di partecipazione:/);
	assert.match(adminScript, /\['FIXED', 'CALCULATED'\]/);
	assert.match(publicScript, /fixed_price_cents/);
});

test('i metadati tecnici dei consensi non compaiono nel pannello evento', async () => {
	const eventType = await read('includes/class-mi-event-post-type.php');
	assert.doesNotMatch(eventType, /<strong>Versione informativa privacy<\/strong>/);
	assert.doesNotMatch(eventType, /<strong>ID consenso privacy<\/strong>/);
	assert.doesNotMatch(eventType, /<strong>ID del consenso alle comunicazioni<\/strong>/);
	assert.match(eventType, /'privacy-' \. \$post_id/);
	assert.match(eventType, /'marketing-' \. \$post_id/);
});

test('email e cellulare dei partecipanti sono campi configurabili e validati', async () => {
	const schema = await read('includes/class-mi-field-schema.php');
	const script = await read('assets/public.js');
	assert.match(schema, /'email'\s*=>[\s\S]*Email del partecipante/);
	assert.match(schema, /'phone'\s*=>[\s\S]*Cellulare del partecipante/);
	assert.match(schema, /mi_participant_email_invalid/);
	assert.match(schema, /mi_participant_phone_invalid/);
	assert.match(script, /\['date', 'email', 'tel'\]/);
});

test('gli eventi supportano domande personalizzate e richieste particolari', async () => {
	const eventType = await read('includes/class-mi-event-post-type.php');
	const schema = await read('includes/class-mi-field-schema.php');
	const service = await read('includes/class-mi-registration-service.php');
	const activator = await read('includes/class-mi-activator.php');
	const adminScript = await read('assets/admin.js');
	const publicScript = await read('assets/public.js');
	assert.match(eventType, /Domande personalizzate/);
	assert.match(schema, /sanitize_custom_fields/);
	assert.match(adminScript, /mi-add-custom-field/);
	assert.match(eventType, /mi_special_requests_enabled/);
	assert.match(activator, /special_requests text NULL/);
	assert.match(service, /mi_special_requests_invalid/);
	assert.match(publicScript, /Richieste particolari \(facoltativo\)/);
});

test('consenso futuro e approvazioni tecniche hanno il corretto livello di interfaccia', async () => {
	const eventType = await read('includes/class-mi-event-post-type.php');
	const publicScript = await read('assets/public.js');
	assert.match(eventType, /Vuoi essere avvisato delle future iniziative/);
	assert.doesNotMatch(eventType, /name="mi_high_impact_approved"/);
	assert.match(publicScript, /Vuoi essere avvisato delle future iniziative/);
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
  const service = await read('includes/class-mi-registration-service.php');
  assert.match(service, /crea_istantanea\(\s*\$event_id,\s*\$email_values\s*\)/);
  assert.doesNotMatch(service, /array_map\(\s*'esc_html'\s*,\s*\$email_values/);
});

test('l’idempotenza viene risolta prima dello stato evento e dei limiti anti abuso', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const create = service.slice(service.indexOf('public static function create'), service.indexOf('public static function riepilogo_economico'));
  assert.ok(create.indexOf('SELECT id, order_code, status') < create.indexOf('public_event( $event_id,'));
  assert.ok(create.indexOf("'replayed' => true") < create.indexOf('registration_state( $event )'));
  assert.ok(create.indexOf("'replayed' => true") < create.indexOf('set_transient'));
});

test('revisioni, snapshot e consensi sono immutabili e replicati', async () => {
  const activator = await read('includes/class-mi-activator.php');
  const service = await read('includes/class-mi-registration-service.php');
  const eventType = await read('includes/class-mi-event-post-type.php');
  for (const token of ['mi_event_revisions', 'event_revision_hash', 'snapshot_json', 'privacy_consent_id', 'marketing_consent_id', 'order_options_json', 'mi_registration_events']) assert.match(activator + service, new RegExp(token));
  assert.match(eventType, /ensure_published_revision/);
  assert.match(service, /stable_json/);
  assert.match(service, /build_order_snapshot/);
	assert.match(service, /true !== \( \$payload\['privacy_accepted'\]/);
	assert.match(service, /strlen\( \$snapshot_json \) > 45000/);
  assert.match(service, /empty\( \$result\['complete'\] \)/);
});

test('l’ACL per attività è applicata alle capability meta di WordPress', async () => {
  const access = await read('includes/class-mi-access.php');
  const postType = await read('includes/class-mi-event-post-type.php');
  assert.match(access, /add_filter\( 'map_meta_cap'/);
  assert.match(access, /can_access_event\( \$event_id, \$user_id \)/);
  assert.match(access, /do_not_allow/);
  assert.match(postType, /'map_meta_cap'\s*=>\s*true/);
  assert.match(postType, /'edit_post'\s*=>\s*'edit_mi_event'/);
	assert.match(postType, /Attività non modificata/);
	assert.match(postType, /mi_registrations WHERE event_id/);
	assert.match((await read('includes/class-mi-admin.php')), /activity_stable/);
});

test('capienza per tipologia e partecipanti mappati sono protetti nella transazione', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const activator = await read('includes/class-mi-activator.php');
  assert.match(service, /mi_ticket_counters/);
  assert.match(service, /ticket_type_code = %s FOR UPDATE/);
  assert.match(service, /ticket_index/);
  assert.match(service, /seen_indexes/);
	assert.match(service, /ticket_slots/);
  assert.match(activator, /PRIMARY KEY \(event_id,ticket_type_code\)/);
});

test('scadenza e annullamento liberano una sola volta tutti i contatori', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const plugin = await read('includes/class-mi-plugin.php');
  const activator = await read('includes/class-mi-activator.php');
  assert.match(service, /expire_due_registrations/);
  assert.match(service, /capacity_released_at IS NULL/);
  assert.match(service, /GREATEST\(0, \{\$counter_field\} - %d\)/);
  assert.match(service, /cancel_registration/);
  assert.match(plugin, /mi_expire_registrations/);
  assert.match(activator, /wp_schedule_event[\s\S]+mi_expire_registrations/);
});

test('rimborsi concorrenti e codici grafici email hanno protezioni dedicate', async () => {
  const admin = await read('includes/class-mi-admin.php');
  const sender = await read('includes/class-mi-spedizione-email.php');
  const images = await read('includes/class-mi-code-image.php');
  const publicScript = await read('assets/public.js');
	const service = await read('includes/class-mi-registration-service.php');
  assert.match(admin, /SELECT id, status, initial_due_cents, payment_deadline_at FROM \{\$wpdb->prefix\}mi_registrations WHERE id = %d FOR UPDATE/);
  assert.match(admin, /initial_due_cents, payment_deadline_at FROM/);
	assert.match(admin, /expires_at/);
	assert.match(service, /'EXPIRED' === \$target_status[\s\S]+SUM\(CASE WHEN transaction_kind = 'REFUND'/);
  assert.match(sender, /addStringEmbeddedImage/);
  assert.match(images, /reed_solomon/);
  assert.match(images, /barcode_svg/);
  assert.match(publicScript, /createBarcode/);
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

test('il registro pagamenti filtra in SQL, pagina la UI ed esporta a blocchi', async () => {
  const admin = await read('includes/class-mi-admin.php');
  assert.match(admin, /payment_where/);
  assert.match(admin, /LIMIT %d OFFSET %d/);
  assert.match(admin, /LIMIT 500 OFFSET %d/);
  assert.match(admin, /payment_from/);
  assert.match(admin, /payment_to/);
  assert.match(admin, /SELECT COUNT\(\*\)/);
  assert.match(admin, /paginate_links/);
  assert.match(admin, /Riepilogo filtro/);
});

test('rimborsi, scadenza originaria e lista attesa sono riconciliati', async () => {
  const activator = await read('includes/class-mi-activator.php');
  const service = await read('includes/class-mi-registration-service.php');
  const admin = await read('includes/class-mi-admin.php');
  assert.match(activator, /payment_deadline_at/);
  assert.match(admin, /\$net_paid/);
  assert.match(admin, /\$locked\['payment_deadline_at'\]/);
  assert.match(service, /promote_waitlisted_locked/);
  assert.match(service, /WAITLIST_PROMOTED/);
  assert.match(service, /WAITLIST_PROMOTION/);
  assert.match(service, /ORDER BY created_at, id FOR UPDATE/);
  assert.match(service, /'publish' !== get_post_status\( \$event_id \)/);
  assert.match(service, /participant_cancel_url/);
  assert.match(service, /\$email_values\['_participant_management'\]/);
});

test('la rimozione dei dati di solo transito usa lo schema storico dell’iscrizione', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  assert.match(service, /SELECT snapshot_json/);
  assert.match(service, /\$snapshot\['event'\]\['participant_fields'\]/);
  assert.match(service, /scrub_relay_only_fields/);
});

test('la scadenza dei pagamenti usa una data e ora esplicita', async () => {
  const eventType = await read('includes/class-mi-event-post-type.php');
  const service = await read('includes/class-mi-registration-service.php');
  assert.match(eventType, /name="mi_payment_deadline_at" type="datetime-local"/);
  assert.doesNotMatch(eventType, /name="mi_reservation_minutes"/);
  assert.match(service, /payment_deadline_at/);
  assert.match(service, /setTimezone\( new DateTimeZone\( 'UTC' \) \)/);
});

test('gli importi italiani con migliaia sono normalizzati e il nome completo è ricercabile', async () => {
  const admin = await read('includes/class-mi-admin.php');
  assert.match(admin, /parse_importo_centesimi/);
  assert.doesNotMatch(admin, /str_replace\( ',', '\.', sanitize_text_field/);
  assert.match(admin, /str_replace\( array\( '\.', ',' \), array\( '', '\.' \), \$value \)/);
  assert.match(admin, /CONCAT\(buyer_first_name, ' ', buyer_last_name\)/);
  assert.match(admin, /CONCAT\(r\.buyer_first_name, ' ', r\.buyer_last_name\)/);
});

test('i pagamenti Workspace possono essere riconciliati senza eco dei movimenti WordPress', async () => {
  const activator = await read('includes/class-mi-activator.php');
  const service = await read('includes/class-mi-registration-service.php');
  assert.match(activator, /origin_payment/);
  assert.match(service, /ELENCA_PAGAMENTI/);
  assert.match(service, /INSERT IGNORE/);
  assert.match(service, /origin_channel/);
});

test('il registro pagamenti rifiuta date effettive normalizzate silenziosamente', async () => {
  const admin = await read('includes/class-mi-admin.php');
  assert.match(admin, /Data effettiva non valida/);
  assert.match(admin, /format\( 'Y-m-d\\\\TH:i' \) !== \$effective_raw/);
});

test('il pannello non conserva numeri completi di carta nei pagamenti', async () => {
  const admin = await read('includes/class-mi-admin.php');
  assert.match(admin, /contiene_numero_carta/);
  assert.match(admin, /Non inserire numeri completi di carta/);
  assert.match(admin, /Luhn|alternate/);
  assert.doesNotMatch(admin, /'CARD' === \$source[\s\S]{0,200}contiene_numero_carta/);
});

test('il pannello non conferma pagamenti non salvati', async () => {
  const admin = await read('includes/class-mi-admin.php');
  assert.match(admin, /\$inserted\s*=\s*\$wpdb->insert/);
  assert.match(admin, /false === \$inserted/);
  assert.match(admin, /Il movimento non è stato salvato/);
});

test('il pagamento manuale e la replica Workspace restano atomici', async () => {
  const admin = await read('includes/class-mi-admin.php');
  assert.match(admin, /START TRANSACTION/);
  assert.match(admin, /ROLLBACK/);
  assert.match(admin, /marked_pending/);
  assert.match(admin, /COMMIT/);
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
  assert.match(script, /Importo da versare/);
  assert.match(script, /registrat[oi] manualmente dall’organizzazione/);
  assert.doesNotMatch(script, /stripe|paypal|checkout/i);
});

test('le prenotazioni a pagamento attendono il versamento prima della conferma', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const admin = await read('includes/class-mi-admin.php');
  const script = await read('assets/public.js');
  assert.match(service, /'PENDING_PAYMENT'/);
  assert.match(service, /WHERE r\.status = 'PENDING_PAYMENT'/);
  assert.match(service, /PAYMENT_STATUS_CHANGED/);
  assert.match(admin, /'PENDING_PAYMENT'.*'CONFIRMED'/s);
  assert.match(admin, /In attesa di pagamento/);
  assert.match(script, /Prenotazione registrata e in attesa di pagamento/);
});

test('il nome storico della tipologia resta una stringa', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  assert.match(service, /ticket_type_name' => \$item\['name'\][\s\S]{0,180}array\( '%d', '%s', '%s', '%d', '%d' \)/);
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
	assert.match(settings, /1\.6\.0/);
	assert.match(settings, /accommodation_headers/);
});

test('l’elenco e la scheda prenotazione usano una presentazione operativa e responsive', async () => {
  const admin = await read('includes/class-mi-admin.php');
  const css = await read('assets/admin.css');
  assert.match(admin, /mi-bookings-table/);
  assert.match(admin, /mi-booking-hero/);
  assert.match(admin, /mi-participants-overview/);
  assert.match(admin, /Evento gratuito/);
  assert.match(admin, /Dati dei partecipanti/);
  assert.match(css, /\.mi-responsive-table/);
  assert.match(css, /@media \(max-width: 960px\)/);
  assert.match(css, /@media \(max-width: 782px\)/);
});

test('la scheda prenotazione si apre in un popup accessibile con fallback', async () => {
  const admin = await read('includes/class-mi-admin.php');
  const script = await read('assets/admin.js');
  const css = await read('assets/admin.css');
  assert.match(admin, /data-mi-booking-open/);
  assert.match(admin, /id="mi-booking-detail"/);
  assert.match(script, /role="dialog" aria-modal="true"/);
  assert.match(script, /fetch\(link\.href/);
  assert.match(script, /DOMParser/);
  assert.match(script, /'Escape'/);
  assert.match(script, /'Tab'/);
  assert.match(script, /AbortController/);
  assert.match(script, /replaceState/);
  assert.match(script, /window\.location\.assign\(link\.href\)/);
  assert.match(css, /\.mi-booking-modal__backdrop/);
  assert.match(css, /min-height: 100vh/);
});

test('anche il portale apre la scheda prenotazione in sovrimpressione', async () => {
  const portal = await read('includes/class-mi-portal.php');
  const script = await read('assets/portal.js');
  const css = await read('assets/portal.css');
  assert.match(portal, /data-mi-portal-booking-open/);
  assert.match(portal, /id="mi-portal-booking-detail"/);
  assert.match(script, /role="dialog" aria-modal="true"/);
  assert.match(script, /fetch\(link\.href/);
  assert.match(script, /DOMParser/);
  assert.match(script, /AbortController/);
  assert.match(script, /'Escape'/);
  assert.match(script, /'Tab'/);
  assert.match(script, /replaceState/);
  assert.match(script, /window\.location\.assign\(link\.href\)/);
	assert.match(script, /data-mi-portal-booking-previous/);
	assert.match(script, /data-mi-portal-booking-next/);
	assert.match(script, /'ArrowLeft'/);
	assert.match(script, /'ArrowRight'/);
	assert.match(script, /findIndex\(\(candidate\) => candidate\.href === link\.href\)/);
  assert.match(css, /\.mi-portal-modal__backdrop/);
	assert.match(css, /\.mi-portal-modal__nav--previous/);
	assert.match(css, /\.mi-portal-modal__nav--next/);
  assert.match(css, /min-height:100vh/);
});

test('il portale mette in evidenza persone, data e immagine senza duplicare il referente', async () => {
  const portal = await read('includes/class-mi-portal.php');
  const css = await read('assets/portal.css');
  assert.match(portal, /mi-event-card__date/);
  assert.match(portal, /date_badge/);
  assert.match(portal, /mi-event-card__image/);
  assert.match(portal, /mi-booking-detail__cover/);
  assert.match(portal, /snapshot_event\['cover_image'\]/);
  assert.match(portal, /mi-booking-detail__code/);
  assert.match(portal, /mi-booking-detail__person/);
  assert.match(portal, /\$is_referent\s*=\s*\$is_multiple\s*&&/);
  assert.match(portal, /mi-booking-detail__referent-dot/);
  assert.doesNotMatch(portal, /<strong>Referente:<\/strong>/);
  assert.match(css, /\.mi-event-card__date/);
  assert.match(css, /\.mi-booking-detail__person/);
  assert.match(css, /\.mi-booking-detail__referent-dot/);
});

test('gli eventi passati sono separati dalla vista operativa ordinaria', async () => {
  const portal = await read('includes/class-mi-portal.php');
  const css = await read('assets/portal.css');
  assert.match(portal, /mi_portal_history/);
  assert.match(portal, /Visualizza eventi passati/);
  assert.match(portal, /Torna agli eventi attuali/);
  assert.match(portal, /is_past_event/);
  assert.match(portal, /Eventi passati/);
  assert.match(css, /\.mi-event-history-link/);
});

test('l’elenco iscrizioni indica l’evento selezionato', async () => {
  const portal = await read('includes/class-mi-portal.php');
  assert.match(portal, /\$list_title = 'Ultime iscrizioni'/);
  assert.match(portal, /\$list_title \.= ' — ' \. \$event_title/);
  assert.match(portal, /esc_html\( \$list_title \)/);
});

test('il referente consulta stato e saldo senza esporre dati personali', async () => {
  const portal = await read('includes/class-mi-portal.php');
  const service = await read('includes/class-mi-registration-service.php');
  const email = await read('includes/class-mi-modello-email.php');
  assert.match(portal, /mi_status/);
  assert.match(portal, /public_status_view/);
  assert.match(portal, /mi_public_status/);
  assert.match(portal, /mi_status_rate_/);
  assert.match(portal, /noindex,nofollow,noarchive/);
  assert.match(service, /public_status_token/);
  assert.match(service, /hash_equals/);
  assert.match(service, /reconcile_workspace_payments\( array\( \$registration\['order_code'\] \) \)/);
  assert.doesNotMatch(service, /public_status[\s\S]{0,4000}buyer_phone/);
  assert.match(email, /Controlla stato e saldo/);
});

test('i promemoria operativi passano dalla coda protetta e le bozze restano in anteprima', async () => {
  const activator = await read('includes/class-mi-activator.php');
  const controller = await read('includes/class-mi-rest-controller.php');
  const sender = await read('includes/class-mi-spedizione-email.php');
  const model = await read('includes/class-mi-modello-email.php');
  assert.match(controller, /QUEUE_OPERATIONAL_EMAILS/);
	assert.match(controller, /GET_EMAIL_MODE/);
  assert.match(controller, /verify_workspace_envelope/);
  assert.match(sender, /accoda_comunicazione_operativa/);
  assert.match(sender, /PRE_DEPARTURE_REMINDER/);
  assert.match(sender, /BALANCE_REMINDER/);
  assert.match(sender, /get_post_status\( \$event_id \).*PREVIEW/s);
	assert.match(sender, /allow_operational/);
  assert.match(sender, /communication_id/);
	assert.match(activator, /UNIQUE KEY origin_key/);
	assert.match(sender, /INSERT IGNORE INTO/);
  assert.match(model, /crea_istantanea_operativa/);
});
