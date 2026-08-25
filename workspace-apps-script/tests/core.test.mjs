import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../src/Core.gs', import.meta.url), 'utf8');
const context = { Number, JSON };
vm.createContext(context);
vm.runInContext(source, context);

test('normalizza soltanto enum ammessi', () => {
  assert.equal(context.normalizzaValoreElenco_(' cash ', ['CASH', 'CARD']), 'CASH');
  assert.equal(context.normalizzaValoreElenco_('crypto', ['CASH', 'CARD']), '');
});

test('converte importi positivi in centesimi', () => {
  assert.equal(context.convertiEuroInCentesimi_('12,34'), 1234);
  assert.equal(context.convertiEuroInCentesimi_('-1'), null);
  assert.equal(context.convertiEuroInCentesimi_('x'), null);
});

test('neutralizza le formule da celle e CSV', () => {
  assert.equal(context.neutralizzaFormula_('=IMPORTXML("x")', 100), "'=IMPORTXML(\"x\")");
  assert.equal(context.neutralizzaFormula_('testo normale', 100), 'testo normale');
});

test('canonicalizza gli oggetti ordinando le chiavi', () => {
  assert.equal(context.serializzaInModoStabile_({ z: 1, a: { y: 2, b: 3 } }), '{"a":{"b":3,"y":2},"z":1}');
});

test('riconosce numeri di carta plausibili senza bloccare riferimenti ordinari', () => {
  assert.equal(context.contienePossibileNumeroCarta_('4111 1111 1111 1111'), true);
  assert.equal(context.contienePossibileNumeroCarta_('Rif. bonifico 2026-001'), false);
});
