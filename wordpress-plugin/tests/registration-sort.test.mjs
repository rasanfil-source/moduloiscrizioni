import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const script = readFileSync(new URL('../modulo-iscrizioni/assets/portal-management.js', import.meta.url), 'utf8');
const preferences = script.slice(script.indexOf('  const registrationSortOptions'), script.indexOf('  function registrationSortMenu'));

test('registration ordering defaults to newest first and retains explicit choices', () => {
  for (const stored of [null, '', 'invalid', 'name', 'created_at']) {
    const localStorage = { getItem: () => stored };
    assert.equal(runInNewContext(preferences + '\nreadRegistrationSort()', { localStorage }), stored === 'name' ? 'name' : 'created_at');
  }
  const localStorage = { getItem() { throw new Error('Storage disabled'); } };
  assert.equal(runInNewContext(preferences + '\nreadRegistrationSort()', { localStorage }), 'created_at');
  let saved;
  runInNewContext(preferences + '\nsaveRegistrationSort("name")', { localStorage: { setItem: (key, value) => { saved = [key, value]; } } });
  assert.deepEqual(saved, ['mi-registration-sort', 'name']);
});

test('both event views initialize from the same sorting preference', () => {
  assert.match(script, /allSort=readRegistrationSort\(\)/);
  assert.match(script, /let listContext=\{[^\n]*sort:readRegistrationSort\(\)/);
  assert.match(script, /if\(listEvent!==event\)\{listContext=\{[^\n]*sort:readRegistrationSort\(\)/);
});
