import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../modulo-iscrizioni/assets/portal.js', import.meta.url), 'utf8');
const cacheSource = source.slice(0, source.indexOf("document.addEventListener('DOMContentLoaded'"));
function setup(load, options) {
  let now = 0;
  const context = vm.createContext({ AbortController, DOMException, setTimeout, clearTimeout, Date: { now: () => now } });
  vm.runInContext(cacheSource, context);
  return { cache: context.miPanelCache(load, options), advance: (ms) => { now += ms; } };
}
const deferred = () => {
  let resolve;
  let reject;
  const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
};

test('prefetch e clic simultanei condividono una sola richiesta e la risposta', async () => {
  const pending = deferred();
  let calls = 0;
  const { cache } = setup(() => { calls++; return pending.promise; });
  const preload = cache.get('event', true);
  const clicked = cache.get('event');
  assert.equal(preload, clicked);
  pending.resolve({ id: 1 });
  assert.equal(await preload, await clicked);
  await cache.get('event');
  assert.equal(calls, 1);
});

test('le schede scadono dopo 30 secondi e vengono rilette', async () => {
  let calls = 0;
  const { cache, advance } = setup(async () => ({ revision: ++calls }));
  assert.equal((await cache.get('event')).revision, 1);
  advance(29999);
  assert.equal((await cache.get('event')).revision, 1);
  advance(1);
  assert.equal((await cache.get('event')).revision, 2);
});

test('la cache espelle la scheda meno recentemente usata e si può svuotare', async () => {
  const { cache } = setup(async (id) => ({ id }), { limit: 2 });
  await cache.get('a');
  await cache.get('b');
  cache.peek('a');
  await cache.get('c');
  assert.equal(cache.peek('b'), null);
  assert.equal(cache.peek('a').id, 'a');
  cache.clear();
  assert.equal(cache.peek('a'), null);
  assert.equal(cache.peek('c'), null);
});

test('un solo prefetch in corso; il clic su un altra scheda lo interrompe', async () => {
  const pending = deferred();
  let signal;
  const { cache } = setup((id, abortSignal) => {
    if (id === 'a') { signal = abortSignal; return pending.promise; }
    return { id };
  });
  const first = cache.get('a', true);
  await Promise.resolve();
  assert.equal(await cache.get('b', true), null);
  assert.equal((await cache.get('b')).id, 'b');
  assert.equal(signal.aborted, true);
  pending.resolve({ id: 'a' });
  await assert.rejects(first, { name: 'AbortError' });
  assert.equal(cache.peek('a'), null);
});

test('gli errori non vengono memorizzati e consentono di riprovare', async () => {
  let calls = 0;
  const { cache } = setup(async () => {
    if (++calls === 1) throw new Error('offline');
    return { ok: true };
  });
  await assert.rejects(cache.get('event'), /offline/);
  assert.equal((await cache.get('event')).ok, true);
  assert.equal(calls, 2);
});

test('una risposta in volo non ripopola una cache invalidata', async () => {
  const pending = deferred();
  const { cache } = setup(() => pending.promise);
  const request = cache.get('event');
  cache.clear();
  pending.resolve({ id: 'event' });
  await request;
  assert.equal(cache.peek('event'), null);
});
