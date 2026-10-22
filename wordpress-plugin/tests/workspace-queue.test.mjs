import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = name => readFile(new URL('../modulo-iscrizioni/includes/' + name, import.meta.url), 'utf8');

test('la coda evento accorpa le modifiche e non conferma una versione superata', async () => {
  const portal = await source('class-mi-portal.php');
  assert.match(portal, /_mi_workspace_event_pending', wp_generate_uuid4\(\)/);
  assert.match(portal, /\$token !== \(string\) get_post_meta/);
  assert.match(portal, /delete_post_meta\( \$event_id, '_mi_workspace_event_pending', \$token \)/);
  assert.match(portal, /min\( 3600, 30 \* \( 2 \*\* min\( \$attempts, 7 \) \) \)/);
  assert.match(portal, /sincronizzazione Google in attesa/);
  assert.match(portal, /_mi_workspace_event_attempts', true \) \) >= 3/);
});

test('le scritture Google sono serializzate e i retry persistenti non riacquisiscono il lock', async () => {
  const client = await source('class-mi-workspace-client.php');
  const service = await source('class-mi-registration-service.php');
  assert.match(client, /GET_LOCK\(%s, %d\)/);
  assert.match(client, /'PROIETTA_EVENTO' !== strtoupper\( \$action \)/);
  assert.match(client, /\$wait = 3/);
  assert.match(client, /finally.*RELEASE_LOCK/s);
  assert.match(client, /self::request_unlocked\( \$action, \$payload, 1 \)/);
  assert.match(service, /workspace_next_attempt_at = %s/);
  assert.match(service, /workspace_next_attempt_at IS NULL OR workspace_next_attempt_at <= %s/);
  assert.match(service, /wp_unschedule_event\( \$scheduled, 'mi_sync_workspace_registration'/);
  assert.match(service, /schedule_workspace_registration\( \$registration_id, time\(\) \)/);
  assert.doesNotMatch(service, /mi_workspace_retry_/);
  assert.match(service, /schedule_workspace_registration\( \$registration_id, time\(\) \+ \$delay \)/);
});
