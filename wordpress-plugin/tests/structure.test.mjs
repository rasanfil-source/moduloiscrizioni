import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../modulo-iscrizioni/', import.meta.url);
const read = (path) => readFile(new URL(path, root), 'utf8');

test('il bootstrap dichiara la versione e non esegue fuori da WordPress', async () => {
  const source = await read('modulo-iscrizioni.php');
  assert.match(source, /Version:\s+0\.1\.0/);
  assert.match(source, /defined\(\s*'ABSPATH'\s*\)\s*\|\|\s*exit/);
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

test('la coda email rimane in anteprima', async () => {
  const service = await read('includes/class-mi-registration-service.php');
  const admin = await read('includes/class-mi-admin.php');
  assert.match(service, /'status'\s*=>\s*'PREVIEW'/);
  assert.doesNotMatch(service, /wp_mail\s*\(/);
  assert.match(admin, /Nessuna email viene spedita/);
});
