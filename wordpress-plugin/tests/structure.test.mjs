import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../modulo-iscrizioni/', import.meta.url);
const read = (path) => readFile(new URL(path, root), 'utf8');

test('il bootstrap dichiara la versione e non esegue fuori da WordPress', async () => {
  const source = await read('modulo-iscrizioni.php');
  assert.match(source, /Version:\s+0\.2\.6/);
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
  assert.match(service, /MI_Field_Schema::validate_answers/);
  assert.match(publicScript, /data-mi-participant-field/);
  assert.match(activator, /extra_json longtext/);
  assert.match(activator, /maybe_upgrade/);
});

test('la coda email rimane in anteprima', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const admin = await read('includes/class-mi-admin.php');
  assert.match(service, /'status'\s*=>\s*'PREVIEW'/);
  assert.doesNotMatch(service, /wp_mail\s*\(/);
  assert.match(admin, /Nessuna email viene spedita/);
});
